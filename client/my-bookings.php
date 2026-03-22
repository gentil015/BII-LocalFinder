<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat.php';
require_once '../includes/event_tracking.php';

// Check if user is logged in and is a client
if (!isLoggedIn()) {
    redirect('login.php');
}

if (isProvider()) {
    redirect('provider/dashboard.php');
}

$db = Database::getInstance()->getConnection();

// Load system settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

// Get all system settings
$system_settings = [
    'platform_name' => getSetting($db, 'platform_name', 'BII LocalFinder'),
    'contact_email' => getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
    'contact_phone' => getSetting($db, 'contact_phone', '+250 788 123 456'),
    'platform_description' => getSetting($db, 'platform_description', 'Connecting clients with trusted local service providers'),
    'timezone' => getSetting($db, 'timezone', 'Africa/Kigali'),
    
    // Booking settings
    'max_pending_time' => intval(getSetting($db, 'max_pending_time', '15')),
    'allow_booking_editing' => getSetting($db, 'allow_booking_editing', '1'),
    'auto_cancel_unconfirmed' => getSetting($db, 'auto_cancel_unconfirmed', '1'),
    'require_rating_after_completion' => getSetting($db, 'require_rating_after_completion', '0'),
    'max_cancellations_per_month' => intval(getSetting($db, 'max_cancellations_per_month', '3')),
    
    // Payment settings
    'enable_commission' => getSetting($db, 'enable_commission', '0'),
    'commission_rate' => floatval(getSetting($db, 'commission_rate', '10')),
    
    // Notification settings
    'enable_email_notifications' => getSetting($db, 'enable_email_notifications', '1'),
    'enable_sms_notifications' => getSetting($db, 'enable_sms_notifications', '0'),
];

// Get client information
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

// Get view type (bookings or my-offers)
$view = isset($_GET['view']) ? sanitize($_GET['view']) : 'bookings';

// Get booking statistics
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'confirmed'");
$stmt->execute([$_SESSION['user_id']]);
$confirmed_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$completed_bookings = $stmt->fetch()['total'];

// Get monthly cancellation count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'cancelled' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stmt->execute([$_SESSION['user_id']]);
$monthly_cancellations = $stmt->fetch()['total'];

// Handle offer response (withdraw or view counter-offers)
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offer_action'])) {
    $offer_id = intval($_POST['offer_id']);
    $action = sanitize($_POST['offer_action']);
    
    // Verify offer belongs to this client
    $stmt = $db->prepare("SELECT * FROM service_offers WHERE id = ? AND client_id = ?");
    $stmt->execute([$offer_id, $_SESSION['user_id']]);
    $offer = $stmt->fetch();
    
    if ($offer) {
        if ($action === 'withdraw') {
            $stmt = $db->prepare("UPDATE service_offers SET status = 'withdrawn' WHERE id = ?");
            if ($stmt->execute([$offer_id])) {
                $success = "Offer withdrawn successfully!";
            }
        } elseif ($action === 'accept_counter') {
            // AUTO-CONFIRM BOOKING when client accepts counter-offer
            try {
                $counter_id = intval($_POST['counter_id']);
                
                // Get counter-offer details
                $stmt = $db->prepare("SELECT * FROM service_counteroffers WHERE id = ? AND client_id = ?");
                $stmt->execute([$counter_id, $_SESSION['user_id']]);
                $counter_offer = $stmt->fetch();
                
                if (!$counter_offer) {
                    throw new Exception("Counter-offer not found or unauthorized");
                }
                
                // Step 1: Update counter-offer status to 'accepted'
                $stmt = $db->prepare("UPDATE service_counteroffers SET status = 'accepted' WHERE id = ?");
                if (!$stmt->execute([$counter_id])) {
                    throw new Exception("Failed to accept counter-offer");
                }
                
                // Step 2: Update original offer status to 'accepted'
                $stmt = $db->prepare("UPDATE service_offers SET status = 'accepted' WHERE id = ?");
                if (!$stmt->execute([$offer_id])) {
                    throw new Exception("Failed to update offer status");
                }
                
                // Step 3: Finalize the price using counter-offer amount
                $finalized_price = $counter_offer['proposed_price'];
                $stmt = $db->prepare("
                    INSERT INTO finalized_service_prices 
                    (booking_id, service_id, client_id, provider_id, finalized_price, negotiation_rounds, provider_final_counteroffer_id, status)
                    VALUES (?, ?, ?, ?, ?, 2, ?, 'active')
                    ON DUPLICATE KEY UPDATE
                    finalized_price = VALUES(finalized_price),
                    negotiation_rounds = 2,
                    provider_final_counteroffer_id = VALUES(provider_final_counteroffer_id),
                    updated_at = NOW()
                ");
                if (!$stmt->execute([$offer['booking_id'], $offer['service_id'], $_SESSION['user_id'], $counter_offer['provider_id'], $finalized_price, $counter_id])) {
                    throw new Exception("Failed to finalize price");
                }
                
                // Step 4: Update booking status to 'confirmed' with final price
                $stmt = $db->prepare("
                    UPDATE bookings 
                    SET status = 'confirmed', 
                        agreed_price = ?
                    WHERE id = ? AND client_id = ?
                ");
                if (!$stmt->execute([$finalized_price, $offer['booking_id'], $_SESSION['user_id']])) {
                    throw new Exception("Failed to confirm booking");
                }
                
                // Step 5: Log this action in negotiation history
                $stmt = $db->prepare("
                    INSERT INTO negotiation_history 
                    (booking_id, offer_id, counteroffer_id, action_type, price_offered, actor_id, actor_type, notes)
                    VALUES (?, ?, ?, 'counteroffer_accepted', ?, ?, 'client', 'Counter-offer accepted by client - Booking confirmed')
                ");
                $stmt->execute([$offer['booking_id'], $offer_id, $counter_id, $finalized_price, $_SESSION['user_id']]);
                
                $success = "✅ Counter-offer accepted and booking confirmed! Final price: RWF " . number_format($finalized_price, 0);
                
            } catch (Exception $e) {
                error_log("Counter-offer acceptance error: " . $e->getMessage());
                $success = "Error accepting counter-offer: " . $e->getMessage();
            }
        }
    }
}

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for all bookings with filters
$query = "
    SELECT b.*, 
           sp.profession, sp.location, sp.availability, sp.hourly_rate,
           u.full_name as provider_name, u.phone as provider_phone, 
           u.email as provider_email, u.profile_image as provider_image
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE b.client_id = ?
";

$params = [$_SESSION['user_id']];

// Apply filters
if (!empty($status_filter)) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR sp.profession LIKE ? OR b.service_description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$all_bookings = $stmt->fetchAll();

// Handle booking cancellation with system settings validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $cancellation_reason = isset($_POST['cancellation_reason']) ? sanitize($_POST['cancellation_reason']) : null;

    // Check monthly cancellation limit
    if ($monthly_cancellations >= $system_settings['max_cancellations_per_month']) {
        $error = "You have reached your monthly cancellation limit ({$system_settings['max_cancellations_per_month']}). Please contact support.";
    } else {
        // Verify booking belongs to client and is pending/confirmed
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND client_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Get the provider for messaging and timeline integration
            $stmtProvider = $db->prepare("SELECT provider_id FROM bookings WHERE id = ?");
            $stmtProvider->execute([$booking_id]);
            $providerData = $stmtProvider->fetch(PDO::FETCH_ASSOC);
            $providerId = $providerData['provider_id'] ?? null;

            $updateQuery = "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?";
            $stmt = $db->prepare($updateQuery);
            if ($stmt->execute([$cancellation_reason, $booking_id])) {
                $success = "Booking cancelled successfully";
                // Log activity
                logActivity($db, $_SESSION['user_id'], 'booking_cancelled', "Cancelled booking #{$booking_id} - Reason: {$cancellation_reason}");

                // Track booking cancelled event
                trackEvent('booking_cancelled', 'booking', $booking_id, [
                    'cancellation_reason' => $cancellation_reason,
                    'client_id' => $_SESSION['user_id'],
                    'provider_id' => $providerId
                ], $_SESSION['user_id']);

                if ($providerId) {
                    sendMessage($_SESSION['user_id'], $providerId, "Booking #{$booking_id} has been cancelled by the client. Reason: {$cancellation_reason}");
                }

                // Refresh page
                header("Location: my-bookings.php?cancelled=1");
                exit();
            }
        } else {
            $error = "Booking not found or cannot be cancelled";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo $system_settings['platform_name']; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* EXACT SAME STYLES AS DASHBOARD - NO CHANGES */
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
            margin-left: 260px;
            padding: 1rem 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .welcome-text h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .welcome-text p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
        }
        
        /* Booking Items */
        .booking-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .booking-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .provider-info {
            display: flex;
            gap: 1rem;
            flex: 1;
        }
        
        .provider-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.withdrawn {
            background: #e2e3e5;
            color: #383d41;
        }
        
        /* Tabs */
        .view-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .view-tab {
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: var(--secondary);
            font-weight: 500;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-tab:hover {
            color: var(--primary);
        }
        
        .view-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Offer Card */
        .offer-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--warning);
            transition: all 0.3s;
        }
        
        .offer-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .offer-provider {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .offer-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .offer-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .btn-offer-action {
            padding: 0.4rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-accept-counter {
            background: var(--success);
            color: white;
        }
        
        .btn-accept-counter:hover {
            background: #157347;
        }
        
        .btn-withdraw {
            background: var(--danger);
            color: white;
        }
        
        .btn-withdraw:hover {
            background: #bb2d3b;
        }
        
        /* Action Buttons */
        .booking-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: #0a58ca;
            color: white;
            text-decoration: none;
        }
        
        .btn-review {
            background: var(--warning);
            color: black;
        }
        
        .btn-review:hover {
            background: #e0a800;
            color: black;
            text-decoration: none;
        }
        
        .btn-cancel {
            background: var(--danger);
            color: white;
        }
        
        .btn-cancel:hover {
            background: #bb2d3b;
            color: white;
        }
        
        /* Provider Cards */
        .provider-card {
            text-align: center;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .provider-card:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
        }
        
        .provider-card .provider-avatar {
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .provider-card h4 {
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .rating {
            color: #ffc107;
            margin: 0.5rem 0;
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
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .welcome-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-header {
                flex-direction: column;
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

        /* System Notice */
        .system-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        /* Filter Styles */
        .filter-card {
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
        
        .filter-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Booking Detail Modal (client view) */
        .booking-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        .booking-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .booking-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .booking-modal-header {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: flex-end;
            padding: 2rem 1.5rem 1.5rem;
            color: white;
        }

        .booking-modal-header .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .booking-modal-header .close-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .modal-provider-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
            margin-right: 1.5rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .modal-provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-provider-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-provider-info p {
            margin: 0.25rem 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .booking-modal-body {
            padding: 2rem 1.5rem;
        }

        .modal-section {
            margin-bottom: 2rem;
        }

        .modal-section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark);
        }

        .info-value {
            color: var(--secondary);
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

    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-section">
                <div class="welcome-text">
                    <h1><?php echo $view === 'my-offers' ? 'My Price Offers' : 'My Bookings'; ?></h1>
                    <p><?php echo $view === 'my-offers' ? 'View and manage your price offers and negotiations' : 'Manage and track all your service bookings in one place'; ?></p>
                </div>
                <div class="quick-actions">
                    <a href="../providers.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> New Booking
                    </a>
                </div>
            </div>
        </div>

        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=bookings" class="view-tab <?php echo $view === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> My Bookings
                <span class="badge bg-secondary"><?php echo $total_bookings; ?></span>
            </a>
            <a href="?view=my-offers" class="view-tab <?php echo $view === 'my-offers' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i> My Offers
                <?php 
                // Get count of pending offers
                $stmt = $db->prepare("SELECT COUNT(*) FROM service_offers WHERE client_id = ? AND status = 'pending'");
                $stmt->execute([$_SESSION['user_id']]);
                $pending_offers = (int) $stmt->fetchColumn();
                if ($pending_offers > 0):
                ?>
                    <span class="badge bg-danger"><?php echo $pending_offers; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Booking cancelled successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3><?php echo $total_bookings; ?></h3>
                <p>Total Bookings</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $pending_bookings; ?></h3>
                <p>Pending Requests</p>
                <?php if ($system_settings['max_pending_time'] > 0): ?>
                    <small class="text-muted">Auto-cancels in <?php echo $system_settings['max_pending_time']; ?>min</small>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $confirmed_bookings; ?></h3>
                <p>Confirmed</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $completed_bookings; ?></h3>
                <p>Completed</p>
                <?php if ($system_settings['require_rating_after_completion']): ?>
                    <small class="text-muted">Rating required</small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <?php if ($view === 'bookings'): ?>
                <!-- Filters -->
                <div class="filter-card">
                    <h3 class="mb-3">Filter Bookings</h3>
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="view" value="bookings">
                        <div class="filter-row">
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
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                            </div>
                            <div class="filter-group">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Provider name, profession, or service...">
                            </div>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="?view=bookings" class="btn btn-secondary">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <!-- My Offers Filters -->
                <div class="filter-card">
                    <h3 class="mb-3">Filter Offers</h3>
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="view" value="my-offers">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="withdrawn" <?php echo $status_filter === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Provider name or service...">
                            </div>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="?view=my-offers" class="btn btn-secondary">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($view === 'bookings'): ?>
                <!-- All Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Bookings (<?php echo count($all_bookings); ?>)</h3>
                    </div>

                    <?php if (empty($all_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <h3>No bookings found</h3>
                            <p>No bookings match your current filters</p>
                            <a href="../providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_bookings as $booking): ?>
                            <?php
                                // build attributes string to avoid quote/parsing problems
                                $booking_attrs = sprintf(
                                    'onclick="openBookingModalClient(this)" ' .
                                    'data-booking-id="%d" ' .
                                    'data-provider-name="%s" ' .
                                    'data-provider-email="%s" ' .
                                    'data-provider-phone="%s" ' .
                                    'data-provider-image="%s" ' .
                                    'data-service-desc="%s" ' .
                                    'data-preferred-date="%s" ' .
                                    'data-preferred-time="%s" ' .
                                    'data-location="%s" ' .
                                    'data-status="%s" ' .
                                    'data-created-at="%s" ' .
                                    'data-urgency="%s"',
                                    $booking['id'],
                                    htmlspecialchars($booking['provider_name'] ?? ''),
                                    htmlspecialchars($booking['provider_email'] ?? ''),
                                    htmlspecialchars($booking['provider_phone'] ?? ''),
                                    htmlspecialchars($booking['provider_image'] ?? ''),
                                    htmlspecialchars($booking['service_description'] ?? ''),
                                    htmlspecialchars($booking['preferred_date'] ?? ''),
                                    htmlspecialchars($booking['preferred_time'] ?? ''),
                                    htmlspecialchars($booking['location'] ?? ''),
                                    htmlspecialchars($booking['status'] ?? ''),
                                    htmlspecialchars($booking['created_at'] ?? ''),
                                    htmlspecialchars($booking['urgency_level'] ?? '')
                                );
                            ?>
                            <div class="booking-item" <?php echo $booking_attrs; ?>>
                                <div class="booking-header">
                                    <div class="provider-info">
                                        <?php 
                                        $provider_image = $booking['provider_image'] ?? '';
                                        $provider_initial = strtoupper(substr($booking['provider_name'] ?? '', 0, 1)) ?: '?';
                                        ?>
                                        <div class="provider-avatar">
                                            <?php if (!empty($provider_image)): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($provider_image); ?>" alt="<?php echo htmlspecialchars($booking['provider_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($provider_initial); ?>';">
                                            <?php else: ?>
                                                <?php echo $provider_initial; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($booking['provider_name']); ?></h5>
                                            <p class="text-primary fw-semibold small mb-1">
                                                <?php echo htmlspecialchars($booking['profession']); ?>
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($booking['location']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                    <?php if ($booking['status'] === 'cancelled' && !empty($booking['cancellation_reason'])): ?>
                                        <div class="text-danger small mt-1">Reason: <?php echo htmlspecialchars($booking['cancellation_reason']); ?></div>
                                    <?php endif; ?>
                                </div>
 
                                <p class="mb-2">
                                    <strong>Service:</strong> <?php echo htmlspecialchars($booking['service_description']); ?>
                                </p>

                                <p class="text-muted small mb-2">
                                    <i class="fas fa-calendar me-1"></i> Preferred: <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?>
                                    <span class="ms-3">
                                        <i class="fas fa-clock me-1"></i> Booked: <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                    </span>
                                </p>

                                <div class="booking-actions">
                                    <a href="../client/provider-profile.php?id=<?php echo $booking['provider_id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation();">
                                        <i class="fas fa-user me-1"></i> View Provider
                                    </a>
                                    
                                    <?php if ($system_settings['allow_booking_editing'] && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                        <a href="edit-booking.php?id=<?php echo $booking['id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation();">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['status'] === 'completed'): ?>
                                        <?php
                                        // Prefer checking by booking_id so each completed booking can get its own review.
                                        $review_check = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND booking_id = ? LIMIT 1");
                                        $review_check->execute([$_SESSION['user_id'], $booking['id']]);
                                        $has_review = $review_check->fetch();

                                        // Fallback: if no booking-specific review exists, check provider-level (older behaviour)
                                        if (!$has_review) {
                                            $review_check2 = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND provider_id = ? LIMIT 1");
                                            $review_check2->execute([$_SESSION['user_id'], $booking['provider_id']]);
                                            $has_review = $review_check2->fetch();
                                        }
                                        ?>
                                        <?php if (!$has_review): ?>
                                            <a href="write-review.php?provider_id=<?php echo $booking['provider_id']; ?>&booking_id=<?php echo $booking['id']; ?>" class="btn-sm btn-review" onclick="event.stopPropagation();">
                                                <i class="fas fa-star me-1"></i> Write Review
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-success">Reviewed</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                        <?php if ($monthly_cancellations < $system_settings['max_cancellations_per_month']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <div class="d-flex align-items-center gap-2 mb-1" style="flex-wrap:wrap;">
                                                    <select name="cancellation_reason" class="form-select form-select-sm" required style="width:auto; min-width:180px;">
                                                        <option value="">Select cancellation reason</option>
                                                        <option value="too expensive">too expensive</option>
                                                        <option value="provider did not respond">provider did not respond</option>
                                                        <option value="found another provider">found another provider</option>
                                                        <option value="changed mind">changed mind</option>
                                                        <option value="bad communication">bad communication</option>
                                                        <option value="other">other</option>
                                                    </select>
                                                    <button type="submit" name="cancel_booking" class="btn-sm btn-cancel" onclick="event.stopPropagation();">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancellation Limit Reached</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($view === 'my-offers'): ?>
                <!-- My Offers View -->
                <div class="card">
                    <div class="card-header">
                        <h3>My Price Offers</h3>
                    </div>

                    <?php 
                    // Get client's offers with counter-offers
                    $stmt = $db->prepare("
                        SELECT so.*, ps.name as service_name, u.full_name as provider_name, u.profile_image, u.email, u.phone
                        FROM service_offers so
                        JOIN provider_services ps ON so.service_id = ps.id
                        JOIN users u ON so.provider_id = u.id
                        WHERE so.client_id = ?
                        ORDER BY 
                            CASE 
                                WHEN so.status = 'pending' THEN 1
                                WHEN so.status = 'accepted' THEN 2
                                ELSE 3
                            END,
                            so.created_at DESC
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $my_offers = $stmt->fetchAll();
                    ?>

                    <?php if (empty($my_offers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-handshake"></i>
                            <h3>No price offers yet</h3>
                            <p>You haven't sent any price offers to providers</p>
                            <a href="../providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="offers-list">
                            <?php foreach ($my_offers as $offer): 
                                // Check if offer is expired
                                $is_expired = strtotime($offer['expires_at']) < time();
                                if ($is_expired && $offer['status'] === 'pending') {
                                    $offer['status'] = 'expired';
                                }

                                // Get counter-offers if any
                                $stmt_counter = $db->prepare("
                                    SELECT * FROM service_counteroffers 
                                    WHERE offer_id = ? 
                                    ORDER BY created_at DESC
                                ");
                                $stmt_counter->execute([$offer['id']]);
                                $counter_offers = $stmt_counter->fetchAll();
                            ?>
                                <div class="offer-card">
                                    <div class="offer-header">
                                        <div class="offer-provider">
                                            <div class="provider-avatar" style="width: 45px; height: 45px;">
                                                <?php if (!empty($offer['profile_image'])): ?>
                                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($offer['profile_image']); ?>" alt="<?php echo htmlspecialchars($offer['provider_name']); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($offer['provider_name'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($offer['provider_name']); ?></strong>
                                                <div class="text-muted small">
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($offer['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge <?php echo $offer['status']; ?>">
                                                <?php echo ucfirst($offer['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="margin-bottom: 1rem;">
                                        <p class="mb-2"><strong>Service:</strong> <?php echo htmlspecialchars($offer['service_name']); ?></p>
                                        <p class="mb-2"><strong>Your Offer:</strong> <span class="offer-price">RWF <?php echo number_format($offer['offered_price'], 2); ?></span></p>
                                        <p class="mb-2"><strong>Negotiation Round:</strong> <?php echo $offer['round_number']; ?></p>
                                        <p class="mb-2"><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($offer['created_at'])); ?></p>
                                        <?php if ($offer['expires_at']): ?>
                                            <p class="mb-0 text-muted small">
                                                <i class="fas fa-clock"></i> 
                                                Expires: <?php echo date('M d, Y h:i A', strtotime($offer['expires_at'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Counter-Offers -->
                                    <?php if (!empty($counter_offers)): ?>
                                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                                            <h6 class="mb-2">Provider's Counter-Offers:</h6>
                                            <?php foreach ($counter_offers as $counter): ?>
                                                <div class="mb-3 pb-3" style="border-bottom: 1px solid #dee2e6;">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div>
                                                            <strong>RWF <?php echo number_format($counter['proposed_price'], 2); ?></strong>
                                                            <span class="badge bg-info ms-2"><?php echo ucfirst($counter['status']); ?></span>
                                                        </div>
                                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($counter['created_at'])); ?></small>
                                                    </div>
                                                    <?php if (!empty($counter['response_notes'])): ?>
                                                        <p class="text-muted small mb-0"><?php echo nl2br(htmlspecialchars($counter['response_notes'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($counter['status'] === 'pending'): ?>
                                                        <form method="POST" style="display: inline; margin-top: 0.5rem;">
                                                            <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                                            <input type="hidden" name="counter_id" value="<?php echo $counter['id']; ?>">
                                                            <input type="hidden" name="offer_action" value="accept_counter">
                                                            <button type="submit" class="btn-offer-action btn-accept-counter" onclick="return confirm('Accept this counter-offer?')">
                                                                <i class="fas fa-check"></i> Accept
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($offer['status'] === 'pending'): ?>
                                    <div class="offer-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                            <input type="hidden" name="offer_action" value="withdraw">
                                            <button type="submit" class="btn-offer-action btn-withdraw" onclick="return confirm('Withdraw this offer?')">
                                                <i class="fas fa-times"></i> Withdraw Offer
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Quick Stats -->
                <div class="card">
                    <h3 class="mb-3">Booking Summary</h3>
                    <div class="d-grid gap-2">
                        <a href="?view=bookings" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i> All Bookings (<?php echo $total_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=pending" class="btn btn-outline-warning">
                            <i class="fas fa-clock me-2"></i> Pending (<?php echo $pending_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=confirmed" class="btn btn-outline-info">
                            <i class="fas fa-check me-2"></i> Confirmed (<?php echo $confirmed_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=completed" class="btn btn-outline-success">
                            <i class="fas fa-check-circle me-2"></i> Completed (<?php echo $completed_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=cancelled" class="btn btn-outline-danger">
                            <i class="fas fa-times me-2"></i> Cancelled
                        </a>
                    </div>
                    
                    <!-- System Information -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted mb-2">Booking Information</h6>
                        <div class="small text-muted">
                            <div class="mb-1">
                                <i class="fas fa-times-circle me-1"></i> Cancellations: <?php echo $monthly_cancellations; ?>/<?php echo $system_settings['max_cancellations_per_month']; ?> this month
                            </div>
                            <?php if ($system_settings['max_pending_time'] > 0): ?>
                                <div class="mb-1">
                                    <i class="fas fa-clock me-1"></i> Pending bookings auto-cancel after <?php echo $system_settings['max_pending_time']; ?> minutes
                                </div>
                            <?php endif; ?>
                            <?php if ($system_settings['require_rating_after_completion']): ?>
                                <div class="mb-1">
                                    <i class="fas fa-star me-1"></i> Rating required after completion
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Need Help? -->
                <div class="card">
                    <h3 class="mb-3">Need Help?</h3>
                    <div class="d-grid gap-2">
                        <a href="../contact.php" class="btn btn-outline-primary">
                            <i class="fas fa-question-circle me-2"></i> Contact Support
                        </a>
                        <a href="../help.php" class="btn btn-outline-primary">
                            <i class="fas fa-life-ring me-2"></i> Help Center
                        </a>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted mb-2">Support Information</h6>
                        <div class="small text-muted">
                            <div class="mb-1">
                                <i class="fas fa-phone me-1"></i> <?php echo $system_settings['contact_phone']; ?>
                            </div>
                            <div class="mb-1">
                                <i class="fas fa-envelope me-1"></i> <?php echo $system_settings['contact_email']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar collapse toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const clientSidebar = document.getElementById('clientSidebar');
        
        if (sidebarToggle && clientSidebar) {
            // Load sidebar state from localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                clientSidebar.classList.add('collapsed');
            }
            
            // Toggle sidebar on button click
            sidebarToggle.addEventListener('click', () => {
                clientSidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', clientSidebar.classList.contains('collapsed'));
            });
        }

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

        // Booking Modal JS for client
        function openBookingModalClient(element) {
            const modal = document.getElementById('bookingModal');
            const bookingData = {
                id: element.getAttribute('data-booking-id'),
                provider_name: element.getAttribute('data-provider-name'),
                provider_email: element.getAttribute('data-provider-email'),
                provider_phone: element.getAttribute('data-provider-phone'),
                provider_image: element.getAttribute('data-provider-image'),
                service_description: element.getAttribute('data-service-desc'),
                preferred_date: element.getAttribute('data-preferred-date'),
                preferred_time: element.getAttribute('data-preferred-time'),
                location: element.getAttribute('data-location'),
                status: element.getAttribute('data-status'),
                created_at: element.getAttribute('data-created-at'),
                urgency: element.getAttribute('data-urgency') || ''
            };

            // populate contact links & text
            document.getElementById('modalContactEmail').href = 'mailto:' + (bookingData.provider_email || '');
            document.getElementById('modalContactPhone').href = 'tel:' + (bookingData.provider_phone || '');
            document.getElementById('modalContactEmailContact').textContent = bookingData.provider_email || '—';
            document.getElementById('modalContactPhoneContact').textContent = bookingData.provider_phone || '—';

            const avatar = document.getElementById('modalProviderAvatar');
            if (bookingData.provider_image) {
                avatar.innerHTML = '<img src="../uploads/profiles/' + escapeHtml(bookingData.provider_image) + '" alt="' + escapeHtml(bookingData.provider_name) + '">';
            } else {
                avatar.innerHTML = bookingData.provider_name.charAt(0).toUpperCase();
            }

            // populate details
            document.getElementById('modalServiceDesc').textContent = bookingData.service_description || '—';
            document.getElementById('modalPreferredDate').textContent = bookingData.preferred_date || '—';
            document.getElementById('modalPreferredTime').textContent = bookingData.preferred_time || '—';
            document.getElementById('modalLocation').textContent = bookingData.location || '—';
            const statusBadge = document.getElementById('modalStatusBadge');
            statusBadge.className = 'badge badge-' + bookingData.status;
            statusBadge.textContent = bookingData.status.charAt(0).toUpperCase() + bookingData.status.slice(1);

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBookingModal() {
            const modal = document.getElementById('bookingModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('bookingModal');
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeBookingModal();
                }
            });
        });
    </script>
    <!-- Booking Modal HTML -->
    <div class="booking-modal" id="bookingModal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <button class="close-btn" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-provider-avatar" id="modalProviderAvatar"></div>
                <div class="modal-provider-info">
                    <h3 id="modalProviderName">Provider Name</h3>
                    <p><i class="fas fa-envelope" style="margin-right: 0.5rem;"></i><span id="modalProviderEmail">email@example.com</span></p>
                    <p><i class="fas fa-phone" style="margin-right: 0.5rem;"></i><span id="modalProviderPhone">+250700000000</span></p>
                </div>
            </div>
            <div class="booking-modal-body">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-briefcase"></i> Service Details</div>
                    <div class="info-row"><span class="info-label">Description</span><span class="info-value" id="modalServiceDesc">—</span></div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-calendar-alt"></i> Schedule</div>
                    <div class="info-row"><span class="info-label">Preferred Date</span><span class="info-value" id="modalPreferredDate">—</span></div>
                    <div class="info-row"><span class="info-label">Preferred Time</span><span class="info-value" id="modalPreferredTime">—</span></div>
                    <div class="info-row"><span class="info-label">Location</span><span class="info-value" id="modalLocation">—</span></div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-info-circle"></i> Booking Info</div>
                    <div class="info-row"><span class="info-label">Status</span><span class="info-value"><span class="badge" id="modalStatusBadge"></span></span></div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-phone-alt"></i> Contact</div>
                    <a class="contact-method" href="mailto:" id="modalContactEmail"><i class="fas fa-envelope"></i><div><div style="font-size:0.7rem;color:var(--secondary);text-transform:uppercase;margin-bottom:0.2rem;font-weight:600;">Email</div><div id="modalContactEmailContact" style="font-weight:600;font-size:0.95rem;">email@example.com</div></div></a>
                    <a class="contact-method" href="tel:" id="modalContactPhone"><i class="fas fa-phone"></i><div><div style="font-size:0.7rem;color:var(--secondary);text-transform:uppercase;margin-bottom:0.2rem;font-weight:600;">Phone</div><div id="modalContactPhoneContact" style="font-weight:600;font-size:0.95rem;">+250700000000</div></div></a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>