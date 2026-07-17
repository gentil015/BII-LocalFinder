<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$provider_filter = $_GET['provider'] ?? '';
$client_filter = $_GET['client'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';

// Build query for bookings with filters
$query = "
    SELECT 
        b.*,
        u1.full_name as client_name,
        u1.email as client_email,
        u1.phone as client_phone,
        u2.full_name as provider_name,
        u2.email as provider_email,
        u2.phone as provider_phone,
        sp.profession,
        sp.hourly_rate,
        c.name as category_name,
        (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) as has_review,
        0 as has_complaint
    FROM bookings b
    JOIN users u1 ON b.client_id = u1.id
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN users u2 ON sp.user_id = u2.id
    LEFT JOIN provider_services ps ON sp.id = ps.provider_id
    LEFT JOIN categories c ON ps.category_id = c.id
    WHERE 1=1
";

$params = [];

// Search filter
if (!empty($search)) {
    $query .= " AND (b.id = ? OR u1.full_name LIKE ? OR u2.full_name LIKE ? OR b.service_description LIKE ?)";
    if (is_numeric($search)) {
        $params[] = $search;
    } else {
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
}

// Status filter
if (!empty($status_filter)) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

// Provider filter
if (!empty($provider_filter)) {
    $query .= " AND sp.id = ?";
    $params[] = $provider_filter;
}

// Client filter
if (!empty($client_filter)) {
    $query .= " AND u1.id = ?";
    $params[] = $client_filter;
}

// Category filter
if (!empty($category_filter)) {
    $query .= " AND c.id = ?";
    $params[] = $category_filter;
}

// Date range filter
if (!empty($date_from)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

// Payment status filter
if (!empty($payment_filter)) {
    $query .= " AND b.payment_status = ?";
    $params[] = $payment_filter;
}

$query .= " GROUP BY b.id ORDER BY b.created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get data for filters
$providers = $db->query("
    SELECT sp.id, u.full_name, sp.profession 
    FROM service_providers sp 
    JOIN users u ON sp.user_id = u.id 
    ORDER BY u.full_name
")->fetchAll();

$clients = $db->query("
    SELECT id, full_name, email 
    FROM users 
    WHERE user_type = 'client' 
    ORDER BY full_name
")->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🔴 Booking Status Control
    if (isset($_POST['update_booking_status'])) {
        $id = intval($_POST['booking_id']);
        $new_status = sanitize($_POST['new_status']);
        $admin_notes = sanitize($_POST['admin_notes'] ?? '');
        
        try {
            $db->beginTransaction();
            
            // Update booking status
            $stmt = $db->prepare("UPDATE bookings SET status = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$new_status, $admin_notes, $id]);
            
            // Auto-mark payment as completed when booking is completed
            if ($new_status === 'completed') {
                $db->prepare("UPDATE bookings SET payment_status = 'completed' WHERE id = ?")->execute([$id]);

                $bookingDetails = $db->prepare("SELECT client_id, provider_id FROM bookings WHERE id = ?");
                $bookingDetails->execute([$id]);
                $bookingRow = $bookingDetails->fetch(PDO::FETCH_ASSOC);
                if ($bookingRow) {
                    updateMlPredictionOutcome($db, (int) $bookingRow['client_id'], (int) $bookingRow['provider_id'], 1);
                }
            }
            
            // Log status change
            $db->prepare("
                INSERT INTO booking_logs (booking_id, action, performed_by, notes) 
                VALUES (?, 'status_change', 'admin', ?)
            ")->execute([$id, "Status changed to: {$new_status}. Notes: {$admin_notes}"]);
            
            $db->commit();
            $success = "Booking status updated successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to update booking status: " . $e->getMessage();
        }
    }
    
    // 🟣 Booking Assignment Management
    if (isset($_POST['reassign_booking'])) {
        $id = intval($_POST['booking_id']);
        $new_provider_id = intval($_POST['new_provider_id']);
        $reassignment_reason = sanitize($_POST['reassignment_reason'] ?? '');
        
        try {
            $db->beginTransaction();
            
            // Get old provider info for log
            $old_provider_stmt = $db->prepare("SELECT provider_id FROM bookings WHERE id = ?");
            $old_provider_stmt->execute([$id]);
            $old_provider_id = $old_provider_stmt->fetchColumn();
            
            // Reassign booking
            $stmt = $db->prepare("UPDATE bookings SET provider_id = ?, previous_provider_id = ? WHERE id = ?");
            $stmt->execute([$new_provider_id, $old_provider_id, $id]);
            
            // Log reassignment
            $db->prepare("
                INSERT INTO booking_logs (booking_id, action, performed_by, notes) 
                VALUES (?, 'reassigned', 'admin', ?)
            ")->execute([$id, "Reassigned from provider {$old_provider_id} to {$new_provider_id}. Reason: {$reassignment_reason}"]);
            
            $db->commit();
            $success = "Booking reassigned successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to reassign booking: " . $e->getMessage();
        }
    }
    
    // 🟢 Dispute & Complaint Handling
    if (isset($_POST['handle_dispute'])) {
        $id = intval($_POST['booking_id']);
        $dispute_resolution = sanitize($_POST['dispute_resolution']);
        $resolution_notes = sanitize($_POST['resolution_notes'] ?? '');
        $winner = sanitize($_POST['winner'] ?? '');
        
        try {
            $db->beginTransaction();
            
            // Update dispute resolution
            $stmt = $db->prepare("
                UPDATE bookings 
                SET dispute_resolution = ?, resolution_notes = ?, dispute_winner = ?, dispute_resolved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$dispute_resolution, $resolution_notes, $winner, $id]);
            
            // Log dispute resolution
            $db->prepare("
                INSERT INTO booking_logs (booking_id, action, performed_by, notes) 
                VALUES (?, 'dispute_resolved', 'admin', ?)
            ")->execute([$id, "Dispute resolved. Winner: {$winner}. Resolution: {$dispute_resolution}"]);
            
            $db->commit();
            $success = "Dispute resolved successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to resolve dispute: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['freeze_booking'])) {
        $id = intval($_POST['booking_id']);
        $freeze_reason = sanitize($_POST['freeze_reason'] ?? '');
        
        try {
            $db->prepare("UPDATE bookings SET is_frozen = 1, freeze_reason = ? WHERE id = ?")->execute([$freeze_reason, $id]);
            $success = "Booking frozen successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to freeze booking";
        }
    }
    
    if (isset($_POST['unfreeze_booking'])) {
        $id = intval($_POST['booking_id']);
        try {
            $db->prepare("UPDATE bookings SET is_frozen = 0, freeze_reason = NULL WHERE id = ?")->execute([$id]);
            $success = "Booking unfrozen successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to unfreeze booking";
        }
    }
    
    // 🟠 Payment & Financial Tracking
    if (isset($_POST['update_payment_status'])) {
        $id = intval($_POST['booking_id']);
        $payment_status = sanitize($_POST['payment_status']);
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $provider_earnings = floatval($_POST['provider_earnings'] ?? 0);
        
        try {
            $stmt = $db->prepare("
                UPDATE bookings 
                SET payment_status = ?, commission_rate = ?, provider_earnings = ? 
                WHERE id = ?
            ");
            $stmt->execute([$payment_status, $commission_rate, $provider_earnings, $id]);
            $success = "Payment status updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update payment status: " . $e->getMessage();
        }
    }
    
    // 🔵 Admin Notes & Evidence
    if (isset($_POST['add_admin_notes'])) {
        $id = intval($_POST['booking_id']);
        $admin_notes = sanitize($_POST['admin_notes']);
        
        try {
            $db->prepare("UPDATE bookings SET admin_notes = ? WHERE id = ?")->execute([$admin_notes, $id]);
            $success = "Admin notes updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update admin notes";
        }
    }
    
    // Delete booking
    if (isset($_POST['delete_booking'])) {
        $id = intval($_POST['booking_id']);
        try {
            $db->beginTransaction();
            
            // Delete related records
            $db->prepare("DELETE FROM booking_logs WHERE booking_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM reviews WHERE booking_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM reports WHERE booking_id = ?")->execute([$id]);
            
            // Delete booking
            $db->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
            
            $db->commit();
            $success = "Booking deleted successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to delete booking: " . $e->getMessage();
        }
    }
}

// Function to get booking timeline
function getBookingTimeline($db, $booking_id) {
    $timeline = $db->prepare("
        SELECT * FROM booking_logs 
        WHERE booking_id = ? 
        ORDER BY created_at ASC
    ");
    $timeline->execute([$booking_id]);
    return $timeline->fetchAll();
}

// Function to get similar providers for reassignment
function getSimilarProviders($db, $category_id, $location, $exclude_provider_id) {
    $stmt = $db->prepare("
        SELECT sp.id, u.full_name, sp.profession, sp.location, sp.average_rating
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        JOIN provider_services ps ON sp.id = ps.provider_id
        WHERE ps.category_id = ? 
        AND sp.location LIKE ? 
        AND sp.id != ?
        AND sp.is_active = 1
        AND u.is_verified = 1
        ORDER BY sp.average_rating DESC
        LIMIT 10
    ");
    $stmt->execute([$category_id, "%$location%", $exclude_provider_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - BII LocalFinder</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Admin Layout */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .sidebar-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .card h3 {
            margin: 0 0 1.5rem 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        /* Filters Card */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        /* Booking Cards */
        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .booking-id {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .booking-service {
            color: var(--secondary);
            margin: 0.5rem 0;
        }
        
        /* Booking Parties Grid */
        .booking-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .booking-parties {
                grid-template-columns: 1fr;
            }
        }
        
        .party-card {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        
        .party-header {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary);
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .party-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .party-contact {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        /* Booking Stats Grid */
        .booking-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: #e2e8f0;
        }
        
        .stat-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-frozen {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        /* Payment Badges */
        .payment-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-refunded {
            background: #e2e3e5;
            color: #383d41;
        }
        
        /* Urgency Indicators */
        .urgency-high {
            border-left-color: #dc3545;
        }
        
        .urgency-medium {
            border-left-color: #ffc107;
        }
        
        .urgency-low {
            border-left-color: #198754;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #f1f5f9;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-sm:hover {
            transform: translateY(-1px);
        }
        
        /* Summary Stats */
        .summary-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
        }
        
        .summary-stats span {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .close:hover {
            color: var(--dark);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        /* Timeline Styles */
        .timeline-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            margin-bottom: 0.5rem;
            background: #f8fafc;
            border-radius: 0 8px 8px 0;
        }
        
        .timeline-time {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .booking-header {
                flex-direction: column;
            }
            
            .booking-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-sm {
                width: 100%;
                justify-content: center;
            }
            
            .summary-stats {
                flex-direction: column;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .overlay.active {
            display: block;
        }
        
        /* Price Display */
        .price-display {
            text-align: right;
        }
        
        .booking-price {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--success);
        }
        
        .booking-date {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Admin Layout -->
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1><i class="fas fa-calendar-check me-2"></i> Booking Management</h1>
                        <p>Comprehensive booking management and dispute resolution system</p>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-success" onclick="exportBookings()">
                            <i class="fas fa-download me-2"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="filters-card">
                <h3 class="mb-3">Search & Filter Bookings</h3>
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="form-control" placeholder="Booking ID, client, provider, or service...">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Provider</label>
                            <select name="provider" class="form-select">
                                <option value="">All Providers</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?php echo $provider['id']; ?>" <?php echo $provider_filter == $provider['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($provider['full_name'] . ' - ' . $provider['profession']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Client</label>
                            <select name="client" class="form-select">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo $client_filter == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="">All Payments</option>
                                <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Apply Filters
                            </button>
                        </div>
                        <div class="filter-group">
                            <a href="bookings.php" class="btn btn-secondary w-100">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bookings List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h3 class="mb-0">All Bookings (<?php echo count($bookings); ?>)</h3>
                    <div class="summary-stats">
                        <span><strong>Pending:</strong> <?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'pending')); ?></span>
                        <span><strong>Confirmed:</strong> <?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed')); ?></span>
                        <span><strong>Completed:</strong> <?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'completed')); ?></span>
                        <span><strong>Revenue:</strong> RWF <?php 
                            $total_revenue = array_sum(array_map(fn($b) => $b['hourly_rate'] ?? 0, $bookings));
                            echo number_format($total_revenue);
                        ?></span>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No bookings found</h3>
                        <p>No bookings found matching your criteria</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): 
                        $urgency_class = '';
                        $created_date = strtotime($booking['created_at']);
                        $days_ago = floor((time() - $created_date) / (60 * 60 * 24));
                        
                        if ($days_ago > 7) $urgency_class = 'urgency-high';
                        elseif ($days_ago > 3) $urgency_class = 'urgency-medium';
                        else $urgency_class = 'urgency-low';
                    ?>
                        <div class="booking-card <?php echo $urgency_class; ?>">
                            <div class="booking-header">
                                <div class="flex-grow-1">
                                    <div class="booking-id">
                                        Booking #<?php echo htmlspecialchars($booking['id'] ?? ''); ?>
                                        <?php if (!empty($booking['is_frozen'] ?? false)): ?>
                                            <span class="badge-frozen status-badge ms-2">FROZEN</span>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['has_complaint'] ?? false)): ?>
                                            <span class="badge-cancelled status-badge ms-2">DISPUTE</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-service">
                                        <strong>Service:</strong> <?php echo htmlspecialchars($booking['service_description']); ?>
                                    </div>
                                    <div class="mt-2">
                                        <span class="status-badge badge-<?php echo htmlspecialchars($booking['status'] ?? 'unknown'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($booking['status'] ?? 'Unknown')); ?>
                                        </span>
                                        <?php if (!empty($booking['payment_status'] ?? null)): ?>
                                            <span class="payment-badge badge-<?php echo htmlspecialchars($booking['payment_status']); ?>">
                                                Payment: <?php echo htmlspecialchars(ucfirst($booking['payment_status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="price-display">
                                    <div class="booking-price">
                                        RWF <?php echo number_format($booking['hourly_rate'] ?? 0); ?>
                                    </div>
                                    <div class="booking-date">
                                        <?php echo date('M d, Y H:i', strtotime($booking['created_at'] ?? 'now')); ?>
                                    </div>
                                    <?php if (!empty($booking['preferred_date'] ?? null)): ?>
                                        <div class="booking-date">
                                            Preferred: <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="booking-parties">
                                <div class="party-card">
                                    <div class="party-header">
                                        <i class="fas fa-user me-1"></i> CLIENT
                                    </div>
                                    <div class="party-name"><?php echo htmlspecialchars($booking['client_name']); ?></div>
                                    <div class="party-contact"><?php echo htmlspecialchars($booking['client_email']); ?></div>
                                    <div class="party-contact"><?php echo htmlspecialchars($booking['client_phone']); ?></div>
                                </div>
                                
                                <div class="party-card">
                                    <div class="party-header">
                                        <i class="fas fa-tools me-1"></i> PROVIDER
                                    </div>
                                    <div class="party-name"><?php echo htmlspecialchars($booking['provider_name']); ?></div>
                                    <div class="party-contact"><?php echo htmlspecialchars($booking['profession']); ?></div>
                                    <div class="party-contact"><?php echo htmlspecialchars($booking['provider_phone']); ?></div>
                                </div>
                            </div>
                            
                            <div class="booking-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $days_ago; ?>d</div>
                                    <div class="stat-label">Days Ago</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $booking['has_review'] ? 'Yes' : 'No'; ?></div>
                                    <div class="stat-label">Reviewed</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $booking['has_complaint'] ? 'Yes' : 'No'; ?></div>
                                    <div class="stat-label">Complaint</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $booking['category_name'] ?? 'N/A'; ?></div>
                                    <div class="stat-label">Category</div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <!-- 🔴 Status Control -->
                                <button type="button" class="btn btn-primary btn-sm" onclick="changeBookingStatus(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-sync me-1"></i> Change Status
                                </button>
                                
                                <!-- 🟣 Assignment Management -->
                                <button type="button" class="btn btn-info btn-sm" onclick="reassignBooking(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-user-friends me-1"></i> Reassign
                                </button>
                                
                                <!-- 🟢 Dispute Handling -->
                                <?php if ($booking['has_complaint'] && !$booking['dispute_resolution']): ?>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="resolveDispute(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-gavel me-1"></i> Resolve Dispute
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (empty($booking['is_frozen'] ?? false)): ?>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="freezeBooking(<?php echo htmlspecialchars($booking['id'] ?? 0); ?>)">
                                        <i class="fas fa-lock me-1"></i> Freeze
                                    </button>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['id'] ?? 0); ?>">
                                        <button type="submit" name="unfreeze_booking" class="btn btn-success btn-sm">
                                            <i class="fas fa-unlock me-1"></i> Unfreeze
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <!-- 🟠 Payment Management -->
                                <button type="button" class="btn btn-success btn-sm" onclick="managePayment(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-money-bill me-1"></i> Payment
                                </button>
                                
                                <!-- 🔵 Admin Notes -->
                                <button type="button" class="btn btn-secondary btn-sm" onclick="addAdminNotes(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-sticky-note me-1"></i> Notes
                                </button>
                                
                                <!-- ⚫ Timeline -->
                                <button type="button" class="btn btn-dark btn-sm" onclick="viewTimeline(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-history me-1"></i> Timeline
                                </button>
                                
                                <!-- Delete -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.')">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <button type="submit" name="delete_booking" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Change Booking Status</h3>
            <form method="POST" id="statusForm">
                <input type="hidden" name="booking_id" id="status_booking_id">
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="new_status" class="form-select" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Admin Notes</label>
                    <textarea name="admin_notes" class="form-control" rows="4" placeholder="Reason for status change..."></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="update_booking_status" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="reassignModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close">&times;</span>
            <h3>Reassign Booking</h3>
            <form method="POST" id="reassignForm">
                <input type="hidden" name="booking_id" id="reassign_booking_id">
                <div class="form-group">
                    <label>New Provider</label>
                    <select name="new_provider_id" id="new_provider_id" class="form-select" required>
                        <option value="">Select Provider</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="form-group">
                    <label>Reassignment Reason</label>
                    <textarea name="reassignment_reason" class="form-control" rows="4" placeholder="Why is this booking being reassigned?" required></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="reassign_booking" class="btn btn-primary">Reassign Booking</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reassignModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Additional modals for dispute resolution, payment management, etc. would be implemented similarly -->

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Modal functions
        function changeBookingStatus(bookingId) {
            document.getElementById('status_booking_id').value = bookingId;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function reassignBooking(bookingId) {
            document.getElementById('reassign_booking_id').value = bookingId;
            
            // Fetch similar providers for reassignment
            fetch(`api/get_similar_providers.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(providers => {
                    const select = document.getElementById('new_provider_id');
                    select.innerHTML = '<option value="">Select Provider</option>';
                    providers.forEach(provider => {
                        const option = document.createElement('option');
                        option.value = provider.id;
                        option.textContent = `${provider.full_name} - ${provider.profession} (${provider.location}) - ⭐${provider.average_rating}`;
                        select.appendChild(option);
                    });
                })
                .catch(error => console.error('Error:', error));
            
            document.getElementById('reassignModal').style.display = 'block';
        }
        
        function resolveDispute(bookingId) {
            // Implementation for dispute resolution modal
            alert('Dispute resolution for booking: ' + bookingId);
        }
        
        function freezeBooking(bookingId) {
            const reason = prompt('Please enter the reason for freezing this booking:');
            if (reason) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const bookingIdInput = document.createElement('input');
                bookingIdInput.name = 'booking_id';
                bookingIdInput.value = bookingId;
                form.appendChild(bookingIdInput);
                
                const reasonInput = document.createElement('input');
                reasonInput.name = 'freeze_reason';
                reasonInput.value = reason;
                form.appendChild(reasonInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function managePayment(bookingId) {
            // Implementation for payment management modal
            alert('Payment management for booking: ' + bookingId);
        }
        
        function addAdminNotes(bookingId) {
            const notes = prompt('Enter admin notes for this booking:');
            if (notes !== null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const bookingIdInput = document.createElement('input');
                bookingIdInput.name = 'booking_id';
                bookingIdInput.value = bookingId;
                form.appendChild(bookingIdInput);
                
                const notesInput = document.createElement('input');
                notesInput.name = 'admin_notes';
                notesInput.value = notes;
                form.appendChild(notesInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewTimeline(bookingId) {
            // Implementation for timeline view modal
            alert('Timeline view for booking: ' + bookingId);
        }
        
        function exportBookings() {
            // Add export functionality
            const params = new URLSearchParams(window.location.search);
            window.open(`api/export_bookings.php?${params.toString()}`, '_blank');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Close modals with close button
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = 'none';
            };
        });
    </script>
</body>
</html>