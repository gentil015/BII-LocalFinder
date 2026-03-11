<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    // Allow providers to access but show maintenance warning
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$total_bookings = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$provider['id']]);
$pending_bookings = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$total_reviews = (int) $stmt->fetchColumn();

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = sanitize($_POST['status']);
    
    // Check if provider is allowed to reject bookings
    if ($new_status === 'cancelled' && !isProviderRejectionAllowed()) {
        $errors[] = "Booking rejection is currently disabled by admin.";
    } else {
        // Verify booking belongs to provider
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND provider_id = ?");
        $stmt->execute([$booking_id, $provider['id']]);
        
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_status, $booking_id])) {
                // Update total_jobs if completed and mark payment as completed
                if ($new_status === 'completed') {
                    $stmt = $db->prepare("UPDATE service_providers SET total_jobs = total_jobs + 1 WHERE id = ?");
                    $stmt->execute([$provider['id']]);
                    
                    // Auto-mark payment as completed when booking is completed
                    $stmt = $db->prepare("UPDATE bookings SET payment_status = 'completed' WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    
                    // Check if rating is required after completion
                    if (isRatingRequiredAfterCompletion()) {
                        // You could trigger a review reminder here
                        error_log("Review reminder should be sent for booking: " . $booking_id);
                    }
                }
                
                $success = "Booking status updated successfully!";
                
                // Send notification if enabled
                if (isEmailNotificationsEnabled()) {
                    require_once '../includes/mailer.php';
                    
                    // Get booking details for notification
                    $stmt = $db->prepare("
                        SELECT u.email, u.full_name, b.service_description 
                        FROM bookings b 
                        JOIN users u ON b.client_id = u.id 
                        WHERE b.id = ?
                    ");
                    $stmt->execute([$booking_id]);
                    $booking_details = $stmt->fetch();
                    
                    if ($booking_details) {
                        Mailer::sendBookingStatusUpdate(
                            $booking_details['email'],
                            $booking_details['full_name'],
                            $provider['full_name'],
                            $booking_details['service_description'],
                            $new_status
                        );
                    }
                }
            } else {
                $errors[] = "Failed to update booking status";
            }
        } else {
            $errors[] = "Invalid booking";
        }
    }
}

// Handle bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = sanitize($_POST['bulk_action_type']);
    $booking_ids = $_POST['booking_ids'] ?? [];
    
    if (!empty($booking_ids) && in_array($action, ['confirmed', 'cancelled'])) {
        // Check if provider is allowed to reject bookings
        if ($action === 'cancelled' && !isProviderRejectionAllowed()) {
            $errors[] = "Booking rejection is currently disabled by admin.";
        } else {
            $placeholders = str_repeat('?,', count($booking_ids) - 1) . '?';
            $params = array_merge($booking_ids, [$provider['id']]);
            
            $stmt = $db->prepare("
                UPDATE bookings 
                SET status = '$action', updated_at = NOW()
                WHERE id IN ($placeholders) AND provider_id = ? AND status = 'pending'
            ");
            
            if ($stmt->execute($params)) {
                $success = count($booking_ids) . " booking(s) updated successfully!";
                
                // Send notifications if enabled
                if (isEmailNotificationsEnabled() && !empty($booking_ids)) {
                    require_once '../includes/mailer.php';
                    
                    foreach ($booking_ids as $booking_id) {
                        $stmt = $db->prepare("
                            SELECT u.email, u.full_name, b.service_description 
                            FROM bookings b 
                            JOIN users u ON b.client_id = u.id 
                            WHERE b.id = ?
                        ");
                        $stmt->execute([$booking_id]);
                        $booking_details = $stmt->fetch();
                        
                        if ($booking_details) {
                            Mailer::sendBookingStatusUpdate(
                                $booking_details['email'],
                                $booking_details['full_name'],
                                $provider['full_name'],
                                $booking_details['service_description'],
                                $action
                            );
                        }
                    }
                }
            } else {
                $errors[] = "Failed to update bookings";
            }
        }
    } else {
        $errors[] = "Please select bookings to update";
    }
}

// Get view type (bookings or offers)
$view = isset($_GET['view']) ? sanitize($_GET['view']) : 'bookings';

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle offer response (accept/reject/counter-offer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offer_action'])) {
    $offer_id = intval($_POST['offer_id']);
    $action = sanitize($_POST['offer_action']);
    
    // Verify offer belongs to this provider
    $stmt = $db->prepare("SELECT * FROM service_offers WHERE id = ? AND provider_id = ?");
    $stmt->execute([$offer_id, $_SESSION['user_id']]);
    $offer = $stmt->fetch();
    
    if ($offer) {
        if ($action === 'accept') {
            // OPTION 1: AUTO-CONFIRM BOOKING
            // When provider accepts offer, automatically:
            // 1. Accept the offer
            // 2. Finalize the price in finalized_service_prices
            // 3. Update booking status to 'confirmed'
            
            try {
                // Step 1: Update offer status to 'accepted'
                $stmt = $db->prepare("UPDATE service_offers SET status = 'accepted', responded_at = NOW() WHERE id = ?");
                if (!$stmt->execute([$offer_id])) {
                    throw new Exception("Failed to accept offer");
                }
                
                // Step 2: Create/finalize the price record
                $finalized_price = $offer['offered_price']; // Use the client's offered price
                $stmt = $db->prepare("
                    INSERT INTO finalized_service_prices 
                    (booking_id, service_id, client_id, provider_id, finalized_price, negotiation_rounds, client_final_offer_id, status)
                    VALUES (?, ?, ?, ?, ?, 1, ?, 'active')
                    ON DUPLICATE KEY UPDATE
                    finalized_price = VALUES(finalized_price),
                    updated_at = NOW()
                ");
                if (!$stmt->execute([$offer['booking_id'], $offer['service_id'], $offer['client_id'], $_SESSION['user_id'], $finalized_price, $offer_id])) {
                    throw new Exception("Failed to finalize price");
                }
                
                // Step 3: Update booking status to 'confirmed' with final price
                $stmt = $db->prepare("
                    UPDATE bookings 
                    SET status = 'confirmed', 
                        responded_at = NOW(),
                        amount = ?
                    WHERE id = ? AND provider_id = ?
                ");
                if (!$stmt->execute([$finalized_price, $offer['booking_id'], $provider['id']])) {
                    throw new Exception("Failed to confirm booking");
                }
                
                // Step 4: Log this action in negotiation history
                $stmt = $db->prepare("
                    INSERT INTO negotiation_history 
                    (booking_id, offer_id, action_type, price_offered, actor_id, actor_type, notes)
                    VALUES (?, ?, 'offer_accepted', ?, ?, 'provider', 'Offer accepted by provider - Booking confirmed')
                ");
                $stmt->execute([$offer['booking_id'], $offer_id, $finalized_price, $_SESSION['user_id']]);
                
                // Send notification to client
                if (isEmailNotificationsEnabled()) {
                    require_once '../includes/mailer.php';
                    
                    // Notify client
                    $stmt = $db->prepare("
                        SELECT u.email, u.full_name FROM users u WHERE u.id = ?
                    ");
                    $stmt->execute([$offer['client_id']]);
                    $client_info = $stmt->fetch();
                    
                    if ($client_info) {
                        Mailer::sendOfferAcceptedNotification(
                            $client_info['email'],
                            $client_info['full_name'],
                            $provider['full_name'],
                            $finalized_price,
                            $offer['booking_id']
                        );
                    }
                    
                    // Notify provider of confirmation (optional but good UX)
                    Mailer::sendOfferAcceptanceConfirmation(
                        $_SESSION['user_email'] ?? $provider['email'],
                        $provider['full_name'],
                        $client_info['full_name'],
                        $finalized_price,
                        $offer['booking_id']
                    );
                }
                
                $success = "✅ Offer accepted and booking confirmed! Final price: RWF " . number_format($finalized_price, 0);
                
            } catch (Exception $e) {
                error_log("Offer acceptance error: " . $e->getMessage());
                $errors[] = "Failed to accept offer: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("UPDATE service_offers SET status = 'rejected', responded_at = NOW() WHERE id = ?");
            if ($stmt->execute([$offer_id])) {
                $success = "Offer rejected successfully!";
            } else {
                $errors[] = "Failed to reject offer";
            }
        } elseif ($action === 'counter') {
            $counter_price = isset($_POST['counter_price']) ? floatval($_POST['counter_price']) : 0;
            $counter_notes = isset($_POST['counter_notes']) ? sanitize($_POST['counter_notes']) : '';
            
            if ($counter_price > 0) {
                // Get offer details to access service_id and client_id
                $stmt = $db->prepare("SELECT service_id, client_id FROM service_offers WHERE id = ?");
                $stmt->execute([$offer_id]);
                $offer_details = $stmt->fetch();
                
                if ($offer_details) {
                    // Create counter offer
                    $expires_at = date('Y-m-d H:i:s', strtotime('+3 days'));
                    $stmt = $db->prepare("
                        INSERT INTO service_counteroffers (offer_id, service_id, provider_id, client_id, proposed_price, response_notes, expires_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$offer_id, $offer_details['service_id'], $_SESSION['user_id'], $offer_details['client_id'], $counter_price, $counter_notes, $expires_at])) {
                        $success = "Counter-offer sent successfully!";
                    } else {
                        $errors[] = "Failed to send counter-offer";
                    }
                } else {
                    $errors[] = "Could not find offer details";
                }
            } else {
                $errors[] = "Counter-offer price must be greater than 0";
            }
        }
    } else {
        $errors[] = "Invalid offer or unauthorized";
    }
}

// Determine which column (if any) in bookings references provider_services
if ($view === 'bookings') {
    $joinColumn = null;
    try {
        $cols = $db->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
        $candidates = ['service_id', 'provider_service_id', 'provider_services_id', 'service', 'provider_service'];
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                $joinColumn = $c;
                break;
            }
        }
    } catch (Throwable $e) {
        // If SHOW COLUMNS fails, log and continue without join
        error_log('Bookings: failed to inspect bookings columns: ' . $e->getMessage());
        $joinColumn = null;
    }
    
    // Build query using detected join column (if any)
    $serviceSelect = $joinColumn ? "s.name as service_name" : "NULL as service_name";
    $serviceJoin = $joinColumn ? "LEFT JOIN provider_services s ON b.{$joinColumn} = s.id" : "";
    
    $sql = "
        SELECT b.*, 
               u.full_name as client_name, u.phone as client_phone, 
               u.email as client_email, u.profile_image as client_image,
               {$serviceSelect}
        FROM bookings b
        JOIN users u ON b.client_id = u.id
        {$serviceJoin}
        WHERE b.provider_id = ?
    ";
    
    $params = [$provider['id']];
    
    if (!empty($status_filter)) {
        $sql .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $sql .= " AND DATE(b.preferred_date) = ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.full_name LIKE ? OR b.service_description LIKE ? OR " . ($joinColumn ? "s.name LIKE ?" : "b.service_description LIKE ?") . ")";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count safely
    $count_sql = preg_replace('/^SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql);
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_bookings = (int) $count_stmt->fetchColumn();
    
    // add pagination total pages to avoid undefined variable
    $total_pages = ($per_page > 0) ? (int) max(1, ceil($total_bookings / $per_page)) : 1;
    
    // Get bookings with pagination
    $sql .= " ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'confirmed' THEN 2
            WHEN b.status = 'completed' THEN 3
            ELSE 4
        END,
        b.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} else {
    // OFFERS VIEW
    $sql = "
        SELECT so.*, 
               u.full_name as client_name, u.phone as client_phone, 
               u.email as client_email, u.profile_image as client_image,
               ps.name as service_name
        FROM service_offers so
        JOIN users u ON so.client_id = u.id
        JOIN provider_services ps ON so.service_id = ps.id
        WHERE so.provider_id = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    if (!empty($status_filter)) {
        $sql .= " AND so.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.full_name LIKE ? OR ps.name LIKE ? OR so.offered_price LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count
    $count_sql = preg_replace('/^SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql);
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = (int) $count_stmt->fetchColumn();
    
    // add pagination total pages
    $total_pages = ($per_page > 0) ? (int) max(1, ceil($total_items / $per_page)) : 1;
    
    // Get offers with pagination
    $sql .= " ORDER BY 
        CASE 
            WHEN so.status = 'pending' THEN 1
            WHEN so.status = 'accepted' THEN 2
            WHEN so.status = 'rejected' THEN 3
            ELSE 4
        END,
        so.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo getPlatformName(); ?></title>
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
        }
        
        /* Maintenance Warning */
        .maintenance-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #856404;
            border: none;
            margin-bottom: 1rem;
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
        }
        
        /* Header */
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-header p {
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
            transition: all 0.3s ease;
            text-decoration: none !important;
            color: inherit;
            border: 2px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
        }
        
        .stat-card.active {
            border-color: var(--primary);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
        }
        
        /* Filters Card */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .table-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
        }
        
        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--dark);
            padding: 1rem 0.75rem;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }
        
        .client-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .client-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .client-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
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
        
        .badge.accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.expired {
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
        
        .view-tab .badge {
            margin-left: 0.5rem;
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
        
        .offer-client {
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
        
        .btn-accept-offer {
            background: var(--success);
            color: white;
        }
        
        .btn-accept-offer:hover {
            background: #157347;
        }
        
        .btn-reject-offer {
            background: var(--danger);
            color: white;
        }
        
        .btn-reject-offer:hover {
            background: #bb2d3b;
        }
        
        .btn-counter-offer {
            background: var(--info);
            color: white;
        }
        
        .btn-counter-offer:hover {
            background: #0aa2c0;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
        
        .btn-accept {
            background: var(--success);
            color: white;
        }
        
        .btn-accept:hover {
            background: #157347;
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        
        .btn-reject:hover {
            background: #bb2d3b;
            color: white;
        }
        
        .btn-complete {
            background: var(--info);
            color: white;
        }
        
        .btn-complete:hover {
            background: #0aa2c0;
            color: white;
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
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            text-decoration: none;
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .bulk-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .table-responsive {
                font-size: 0.9rem;
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
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .booking-details {
            max-width: 300px;
            word-wrap: break-word;
        }
        
        .service-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        /* Booking Cards Grid */
        .booking-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }

        .client-avatar-large {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .client-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        /* Booking Detail Modal */
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

        .modal-client-avatar {
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

        .modal-client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-client-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-client-info p {
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

        .modal-section:last-child {
            margin-bottom: 0;
        }

        .modal-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-section-title i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .info-value {
            color: var(--dark);
            font-weight: 600;
            text-align: right;
            flex: 1;
            margin-left: 1rem;
        }

        .contact-method {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
        }

        .contact-method:hover {
            background: #e9ecef;
            color: var(--primary);
        }

        .contact-method:last-child {
            margin-bottom: 0;
        }

        .contact-method i {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .modal-action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 0 16px 16px;
        }

        .modal-action-buttons .btn {
            padding: 0.8rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .modal-action-buttons .btn-accept {
            background: linear-gradient(135deg, var(--success), #157347);
            color: white;
        }

        .modal-action-buttons .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.4);
        }

        .modal-action-buttons .btn-reject {
            background: linear-gradient(135deg, var(--danger), #bb2d3b);
            color: white;
        }

        .modal-action-buttons .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        @media (max-width: 600px) {
            .booking-modal-content {
                width: 95%;
                max-height: 95vh;
                border-radius: 12px;
            }

            .booking-modal-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 1.5rem;
            }

            .modal-client-avatar {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .modal-action-buttons {
                grid-template-columns: 1fr;
            }
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
        <!-- Maintenance Warning -->
        <?php if (isset($maintenance_warning)): ?>
            <div class="alert maintenance-warning">
                <i class="fas fa-tools me-2"></i>
                <strong>Maintenance Mode Active</strong>
                <p class="mb-0 mt-2">The platform is currently under maintenance. Some features may be limited.</p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> <?php echo $view === 'offers' ? __('bookings.offers.title', [], 'dashboard') : __('bookings.title', [], 'dashboard'); ?></h1>
            <p><?php echo $view === 'offers' ? __('bookings.offers.subtitle', [], 'dashboard') : __('bookings.subtitle', [], 'dashboard'); ?></p>
        </div>

        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=bookings" class="view-tab <?php echo $view === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> <?php echo __('bookings.tab_bookings', [], 'dashboard'); ?>
                <span class="badge bg-secondary"><?php echo isset($total_bookings) ? $total_bookings : 0; ?></span>
            </a>
            <a href="?view=offers" class="view-tab <?php echo $view === 'offers' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i> <?php echo __('bookings.tab_offers', [], 'dashboard'); ?>
                <?php 
                // Get count of pending offers
                $stmt = $db->prepare("SELECT COUNT(*) FROM service_offers WHERE provider_id = ? AND status = 'pending'");
                $stmt->execute([$_SESSION['user_id']]);
                $pending_offers = (int) $stmt->fetchColumn();
                if ($pending_offers > 0):
                ?>
                    <span class="badge bg-danger"><?php echo $pending_offers; ?></span>
                <?php endif; ?>
            </a>
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

        <?php if ($view === 'bookings'): ?>
        <!-- Statistics (Only show for Bookings view) -->
        <?php 
        // Get booking statistics
        $stats_sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM bookings
            WHERE provider_id = ?
        ";
        $stats_stmt = $db->prepare($stats_sql);
        $stats_stmt->execute([$provider['id']]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_bookings = isset($stats['total']) ? $stats['total'] : 0;
        $pending_bookings = isset($stats['pending']) ? $stats['pending'] : 0;
        $confirmed_bookings = isset($stats['confirmed']) ? $stats['confirmed'] : 0;
        $completed_bookings = isset($stats['completed']) ? $stats['completed'] : 0;
        $cancelled_bookings = isset($stats['cancelled']) ? $stats['cancelled'] : 0;
        ?>
        <div class="stats-grid">
            <a href="?view=bookings&status=" class="stat-card <?php echo empty($status_filter) ? 'active' : ''; ?>">
                <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3><?php echo $total_bookings; ?></h3>
                <p><?php echo __('bookings.stat_total', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=pending" class="stat-card <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $pending_bookings; ?></h3>
                <p><?php echo __('bookings.stat_pending', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=confirmed" class="stat-card <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $confirmed_bookings; ?></h3>
                <p><?php echo __('bookings.stat_confirmed', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=completed" class="stat-card <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $completed_bookings; ?></h3>
                <p><?php echo __('bookings.stat_completed', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=cancelled" class="stat-card <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                <div class="stat-icon" style="background: #fee2e2; color: #991b1b;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3><?php echo $cancelled_bookings; ?></h3>
                <p><?php echo __('bookings.stat_cancelled', [], 'dashboard'); ?></p>
            </a>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <?php if ($view === 'bookings'): ?>
        <div class="filters-card">
            <form method="GET" action="bookings.php">
                <input type="hidden" name="view" value="bookings">
                <div class="filters-grid">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_search', [], 'dashboard'); ?></label>
                        <input type="text" name="search" class="form-control" placeholder="<?php echo __('bookings.search_placeholder', [], 'dashboard'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_status', [], 'dashboard'); ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?php echo __('bookings.all_status', [], 'dashboard'); ?></option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('bookings.status_pending', [], 'dashboard'); ?></option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>><?php echo __('bookings.status_confirmed', [], 'dashboard'); ?></option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>><?php echo __('bookings.status_completed', [], 'dashboard'); ?></option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo __('bookings.status_cancelled', [], 'dashboard'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_date', [], 'dashboard'); ?></label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> <?php echo __('bookings.apply_filters', [], 'dashboard'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="filters-card">
            <form method="GET" action="bookings.php">
                <input type="hidden" name="view" value="offers">
                <div class="filters-grid">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_search', [], 'dashboard'); ?></label>
                        <input type="text" name="search" class="form-control" placeholder="<?php echo __('bookings.search_placeholder', [], 'dashboard'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_status', [], 'dashboard'); ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?php echo __('bookings.all_status', [], 'dashboard'); ?></option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('bookings.status_pending', [], 'dashboard'); ?></option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>><?php echo __('bookings.status_accepted', [], 'dashboard'); ?></option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>><?php echo __('bookings.status_rejected', [], 'dashboard'); ?></option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>><?php echo __('bookings.status_expired', [], 'dashboard'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> <?php echo __('bookings.apply_filters', [], 'dashboard'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Bookings/Offers Display -->
        <?php if ($view === 'bookings'): ?>
        <!-- Bookings Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo __('bookings.table_title', [], 'dashboard') . ' (' . $total_bookings . ')'; ?></h3>
                <?php if (!empty($items)): ?>
                    <form method="POST" class="bulk-actions">
                        <select name="bulk_action_type" class="form-select" style="width: auto;">
                            <option value=""><?php echo __('bookings.bulk_actions', [], 'dashboard'); ?></option>
                            <option value="confirmed"><?php echo __('bookings.accept_selected', [], 'dashboard'); ?></option>
                            <?php if (isProviderRejectionAllowed()): ?>
                                <option value="cancelled"><?php echo __('bookings.reject_selected', [], 'dashboard'); ?></option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" name="bulk_action" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()"><?php echo __('bookings.apply', [], 'dashboard'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar"></i>
                    <h3><?php echo __('bookings.no_bookings', [], 'dashboard'); ?></h3>
                    <p>
                        <?php if (!empty($status_filter) || !empty($search) || !empty($date_filter)): ?>
                            <?php echo __('bookings.adjust_filters', [], 'dashboard'); ?>
                        <?php else: ?>
                            <?php echo __('bookings.no_bookings_yet', [], 'dashboard'); ?>
                        <?php endif; ?>
                    </p>
                    <?php if (empty($status_filter) && empty($search) && empty($date_filter)): ?>
                        <a href="services.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i> <?php echo __('bookings.add_services', [], 'dashboard'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Bookings Grid View -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.75rem; margin-bottom: 2rem;">
                    <?php foreach ($items as $booking): ?>
                        <div class="booking-card" style="cursor: pointer;" onclick="openBookingModal(this)" 
                             data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>"
                             data-client-name="<?php echo htmlspecialchars($booking['client_name']); ?>"
                             data-client-email="<?php echo htmlspecialchars($booking['client_email']); ?>"
                             data-client-phone="<?php echo htmlspecialchars($booking['client_phone']); ?>"
                             data-client-image="<?php echo htmlspecialchars($booking['client_image']); ?>"
                             data-service-name="<?php echo htmlspecialchars($booking['service_name'] ?? ''); ?>"
                             data-service-desc="<?php echo htmlspecialchars($booking['service_description'] ?? ''); ?>"
                             data-preferred-date="<?php echo htmlspecialchars($booking['preferred_date']); ?>"
                             data-preferred-time="<?php echo htmlspecialchars($booking['preferred_time'] ?? ''); ?>"
                             data-location="<?php echo htmlspecialchars($booking['location']); ?>"
                             data-amount="<?php echo htmlspecialchars($booking['amount']); ?>"
                             data-status="<?php echo htmlspecialchars($booking['status']); ?>"
                             data-created-at="<?php echo htmlspecialchars($booking['created_at']); ?>"
                             data-urgency="<?php echo htmlspecialchars($booking['urgency_level'] ?? ''); ?>"
                             data-can-respond="<?php echo intval($booking['status'] === 'pending' ? 1 : 0); ?>">
                            <div style="position: relative; flex-grow: 1;">
                                <div class="client-avatar-large">
                                    <?php if (!empty($booking['client_image'])): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($booking['client_image']); ?>" alt="<?php echo htmlspecialchars($booking['client_name']); ?>">
                                    <?php else: ?>
                                        <div style="font-size: 3.5rem; font-weight: bold; color: white; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                            <?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge badge-<?php echo htmlspecialchars($booking['status']); ?>" style="position: absolute; top: 12px; right: 12px; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                            <div style="padding: 1.25rem; flex-grow: 1; display: flex; flex-direction: column;">
                                <h4 style="margin-bottom: 0.5rem; font-weight: 700; color: var(--dark); font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($booking['client_name']); ?>
                                </h4>
                                <p style="font-size: 0.9rem; color: var(--secondary); margin-bottom: 0.75rem;">
                                    <i class="fas fa-concierge-bell" style="margin-right: 0.5rem;"></i>
                                    <?php echo htmlspecialchars($booking['service_name'] ?? 'Service'); ?>
                                </p>
                                <div style="border-top: 1px solid #dee2e6; padding-top: 0.75rem; margin-top: 0.75rem; flex-grow: 1;">
                                    <p style="font-size: 0.85rem; color: var(--secondary); margin-bottom: 0.5rem;">
                                        <i class="fas fa-calendar" style="margin-right: 0.4rem; color: var(--primary);"></i>
                                        <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?>
                                    </p>
                                    <p style="font-size: 0.85rem; color: var(--secondary); margin-bottom: 0.5rem;">
                                        <i class="fas fa-map-marker-alt" style="margin-right: 0.4rem; color: var(--primary);"></i>
                                        <?php echo htmlspecialchars(strlen($booking['location']) > 25 ? substr($booking['location'], 0, 25) . '...' : $booking['location']); ?>
                                    </p>
                                    <p style="font-size: 0.85rem; color: var(--secondary);">
                                        <i class="fas fa-clock" style="margin-right: 0.4rem; color: var(--primary);"></i>
                                        <?php echo __('bookings.requested', [], 'dashboard'); ?>: <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                    </p>
                                </div>
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <?php if (!empty($booking['amount'])): ?>
                                            <p style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--primary);">
                                                RWF <?php echo number_format($booking['amount'], 0); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 1.5rem; color: var(--primary); transition: transform 0.3s;">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: none;">
                    <!-- Hidden table for pagination and compatibility -->
                    <form method="POST" id="bulkForm">
                        <div class="table-responsive">
                            <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleSelectAll()">
                                    </th>
                                    <th><?php echo __('bookings.client_name', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.service', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.date_time', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.status', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.actions', [], 'dashboard'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $booking): ?>
                                    <tr>
                                        <td>
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <input type="checkbox" name="booking_ids[]" value="<?php echo $booking['id']; ?>" class="form-check-input booking-checkbox">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="client-info">
                                                <div class="client-avatar">
                                                    <?php if (!empty($booking['client_image'])): ?>
                                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($booking['client_image']); ?>" alt="<?php echo htmlspecialchars($booking['client_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>';">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($booking['client_name']); ?></strong>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['client_phone']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($booking['client_email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="booking-details">
                                                <?php if (!empty($booking['service_name'])): ?>
                                                    <div class="service-name"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                                                <?php endif; ?>
                                                <div class="service-description">
                                                    <?php echo nl2br(htmlspecialchars($booking['service_description'])); ?>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    <i class="fas fa-clock"></i> <?php echo __('bookings.requested', [], 'dashboard'); ?>: <?php echo date('M d, Y h:i A', strtotime($booking['created_at'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?></strong>
                                            <?php if (!empty($booking['preferred_time'])): ?>
                                                <div class="text-muted small">
                                                    <?php echo date('h:i A', strtotime($booking['preferred_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" name="update_status" class="btn-sm btn-accept" title="<?php echo __('bookings.confirm', [], 'dashboard'); ?>">
                                                            <i class="fas fa-check"></i> <?php echo __('bookings.confirm', [], 'dashboard'); ?>
                                                        </button>
                                                    </form>
                                                    <?php if (isProviderRejectionAllowed()): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('<?php echo __('bookings.confirm_reject', [], 'dashboard'); ?>')">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" name="update_status" class="btn-sm btn-reject" title="<?php echo __('bookings.reject', [], 'dashboard'); ?>">
                                                                <i class="fas fa-times"></i> <?php echo __('bookings.reject', [], 'dashboard'); ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" name="update_status" class="btn-sm btn-complete" title="<?php echo __('bookings.complete', [], 'dashboard'); ?>">
                                                            <i class="fas fa-check-circle"></i> <?php echo __('bookings.complete', [], 'dashboard'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?view=bookings&page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'date' => $date_filter, 'search' => $search])); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> <?php echo __('bookings.previous', [], 'dashboard'); ?>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?view=bookings&page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'date' => $date_filter, 'search' => $search])); ?>" 
                               class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?view=bookings&page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'date' => $date_filter, 'search' => $search])); ?>" class="page-btn">
                                <?php echo __('bookings.next', [], 'dashboard'); ?> <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Offers Display -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo __('bookings.offers.title', [], 'dashboard') . ' (' . count($items) . ')'; ?></h3>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-handshake"></i>
                    <h3><?php echo __('bookings.no_offers', [], 'dashboard'); ?></h3>
                    <p>
                        <?php if (!empty($status_filter) || !empty($search)): ?>
                            <?php echo __('bookings.adjust_filters', [], 'dashboard'); ?>
                        <?php else: ?>
                            <?php echo __('bookings.no_offers_yet', [], 'dashboard'); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="offers-list">
                    <?php foreach ($items as $offer): 
                        // Check if offer is expired
                        $is_expired = strtotime($offer['expires_at']) < time();
                        if ($is_expired && $offer['status'] === 'pending') {
                            $offer['status'] = 'expired';
                        }
                    ?>
                        <div class="offer-card">
                            <div class="offer-header">
                                <div class="offer-client">
                                    <div class="client-avatar">
                                        <?php if (!empty($offer['client_image'])): ?>
                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($offer['client_image']); ?>" alt="<?php echo htmlspecialchars($offer['client_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo strtoupper(substr($offer['client_name'], 0, 1)); ?>';">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($offer['client_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($offer['client_name']); ?></strong>
                                        <div class="text-muted small">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($offer['client_email']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($offer['client_phone']); ?>
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
                                <p class="mb-2"><strong><?php echo __('bookings.service', [], 'dashboard'); ?>:</strong> <?php echo htmlspecialchars($offer['service_name']); ?></p>
                                <p class="mb-2"><strong><?php echo __('bookings.offer_price', [], 'dashboard'); ?>:</strong> <span class="offer-price">RWF <?php echo number_format($offer['offered_price'], 2); ?></span></p>
                                <p class="mb-2"><strong><?php echo __('bookings.negotiation_round', [], 'dashboard'); ?>:</strong> <?php echo $offer['round_number']; ?></p>
                                <p class="mb-2"><strong><?php echo __('bookings.submitted', [], 'dashboard'); ?>:</strong> <?php echo date('M d, Y h:i A', strtotime($offer['created_at'])); ?></p>
                                <?php if ($offer['status'] !== 'pending' && !empty($offer['responded_at'])): ?>
                                    <p class="mb-2"><strong><?php echo __('bookings.response_date', [], 'dashboard'); ?>:</strong> <?php echo date('M d, Y h:i A', strtotime($offer['responded_at'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($offer['response_notes'])): ?>
                                    <p class="mb-0"><strong><?php echo __('bookings.your_response', [], 'dashboard'); ?>:</strong> <?php echo nl2br(htmlspecialchars($offer['response_notes'])); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if ($offer['status'] === 'pending'): ?>
                            <div class="offer-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                    <input type="hidden" name="offer_action" value="accept">
                                    <button type="submit" class="btn-offer-action btn-accept-offer" onclick="return confirm('<?php echo __('bookings.confirm_accept_offer', [], 'dashboard'); ?>')">
                                        <i class="fas fa-check"></i> <?php echo __('bookings.accept_offer', [], 'dashboard'); ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                    <input type="hidden" name="offer_action" value="reject">
                                    <button type="submit" class="btn-offer-action btn-reject-offer" onclick="return confirm('<?php echo __('bookings.confirm_reject_offer', [], 'dashboard'); ?>')">
                                        <i class="fas fa-times"></i> <?php echo __('bookings.reject_offer', [], 'dashboard'); ?>
                                    </button>
                                </form>
                                <button type="button" class="btn-offer-action btn-counter-offer" onclick="toggleCounterForm(<?php echo $offer['id']; ?>)">
                                    <i class="fas fa-hand-paper"></i> <?php echo __('bookings.send_counter_offer', [], 'dashboard'); ?>
                                </button>
                            </div>

                            <!-- Counter-Offer Form (Hidden by default) -->
                            <form method="POST" id="counter-form-<?php echo $offer['id']; ?>" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                <input type="hidden" name="offer_action" value="counter">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?php echo __('bookings.counter_price', [], 'dashboard'); ?> (RWF)</label>
                                    <input type="number" name="counter_price" class="form-control" step="0.01" min="0" required placeholder="<?php echo __('bookings.enter_counter_price', [], 'dashboard'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?php echo __('bookings.notes_optional', [], 'dashboard'); ?></label>
                                    <textarea name="counter_notes" class="form-control" rows="3" placeholder="<?php echo __('bookings.add_notes', [], 'dashboard'); ?>"></textarea>
                                </div>
                                <div class="offer-actions">
                                    <button type="submit" class="btn-offer-action btn-counter-offer">
                                        <i class="fas fa-paper-plane"></i> <?php echo __('bookings.send_counter_offer', [], 'dashboard'); ?>
                                    </button>
                                    <button type="button" class="btn-offer-action" style="background: #6c757d; color: white;" onclick="toggleCounterForm(<?php echo $offer['id']; ?>)">
                                        <i class="fas fa-times"></i> <?php echo __('bookings.cancel', [], 'dashboard'); ?>
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination for Offers -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?view=offers&page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> <?php echo __('bookings.previous', [], 'dashboard'); ?>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?view=offers&page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>" 
                               class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?view=offers&page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>" class="page-btn">
                                <?php echo __('bookings.next', [], 'dashboard'); ?> <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

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
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.booking-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        function confirmBulkAction() {
            const selected = document.querySelectorAll('.booking-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one booking to perform bulk action.');
                return false;
            }
            return confirm(`Are you sure you want to update ${selected.length} booking(s)?`);
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Update select all checkbox state
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.booking-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    selectAll.checked = allChecked;
                });
            });
        });

        // Toggle counter-offer form
        function toggleCounterForm(offerId) {
            const form = document.getElementById('counter-form-' + offerId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                form.style.display = 'none';
            }
        }

        // Booking Modal Functions
        function openBookingModal(element) {
            const modal = document.getElementById('bookingModal');
            
            // Extract data from data attributes
            const bookingData = {
                id: element.getAttribute('data-booking-id'),
                client_name: element.getAttribute('data-client-name'),
                client_email: element.getAttribute('data-client-email'),
                client_phone: element.getAttribute('data-client-phone'),
                client_image: element.getAttribute('data-client-image'),
                service_name: element.getAttribute('data-service-name'),
                service_description: element.getAttribute('data-service-desc'),
                preferred_date: element.getAttribute('data-preferred-date'),
                preferred_time: element.getAttribute('data-preferred-time'),
                location: element.getAttribute('data-location'),
                amount: element.getAttribute('data-amount'),
                status: element.getAttribute('data-status'),
                created_at: element.getAttribute('data-created-at'),
                urgency_level: element.getAttribute('data-urgency')
            };
            
            const canRespond = element.getAttribute('data-can-respond') === '1';

            // Populate modal with booking data
            document.getElementById('modalClientName').textContent = bookingData.client_name;
            document.getElementById('modalClientEmail').textContent = bookingData.client_email;
            document.getElementById('modalClientEmailContact').textContent = bookingData.client_email;
            document.getElementById('modalClientPhone').textContent = bookingData.client_phone;
            document.getElementById('modalClientPhoneContact').textContent = bookingData.client_phone;
            
            // Set contact links
            document.getElementById('modalContactEmail').href = 'mailto:' + bookingData.client_email;
            document.getElementById('modalContactPhone').href = 'tel:' + bookingData.client_phone;
            
            // Set client avatar
            const clientAvatar = document.getElementById('modalClientAvatar');
            if (bookingData.client_image) {
                clientAvatar.innerHTML = '<img src="../uploads/profiles/' + escapeHtml(bookingData.client_image) + '" alt="' + escapeHtml(bookingData.client_name) + '">';
            } else {
                clientAvatar.innerHTML = bookingData.client_name.charAt(0).toUpperCase();
            }

            // Populate booking details
            document.getElementById('modalServiceName').textContent = bookingData.service_name || '—';
            document.getElementById('modalServiceDesc').textContent = bookingData.service_description || '—';
            
            try {
                const dateObj = new Date(bookingData.preferred_date + 'T00:00:00');
                document.getElementById('modalPreferredDate').textContent = dateObj.toLocaleDateString('en-RW', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            } catch (e) {
                document.getElementById('modalPreferredDate').textContent = bookingData.preferred_date;
            }
            
            if (bookingData.preferred_time) {
                try {
                    const timeObj = new Date('2000-01-01T' + bookingData.preferred_time);
                    document.getElementById('modalPreferredTime').textContent = timeObj.toLocaleTimeString('en-RW', { hour: '2-digit', minute: '2-digit' });
                } catch (e) {
                    document.getElementById('modalPreferredTime').textContent = bookingData.preferred_time;
                }
            } else {
                document.getElementById('modalPreferredTime').textContent = '—';
            }
            
            document.getElementById('modalLocation').textContent = bookingData.location || '—';
            document.getElementById('modalAmount').textContent = bookingData.amount && parseFloat(bookingData.amount) > 0 ? 'RWF ' + parseInt(bookingData.amount).toLocaleString() : '—';
            document.getElementById('modalUrgency').textContent = bookingData.urgency_level || '—';

            // Set status badge color
            const statusBadge = document.getElementById('modalStatusBadge');
            statusBadge.className = 'badge badge-' + bookingData.status;
            statusBadge.textContent = bookingData.status.charAt(0).toUpperCase() + bookingData.status.slice(1);

            // Show/hide action buttons based on booking status and permissions
            const actionButtons = document.querySelector('.modal-action-buttons');
            if (canRespond && bookingData.status === 'pending') {
                actionButtons.style.display = 'grid';
                
                // Set up form submissions
                const confirmForm = document.getElementById('confirmBookingForm');
                const rejectForm = document.getElementById('rejectBookingForm');
                
                const bookingIdInput1 = confirmForm.querySelector('input[name="booking_id"]');
                const bookingIdInput2 = rejectForm.querySelector('input[name="booking_id"]');
                
                if (bookingIdInput1) bookingIdInput1.value = bookingData.id;
                if (bookingIdInput2) bookingIdInput2.value = bookingData.id;
            } else if (bookingData.status === 'confirmed') {
                actionButtons.innerHTML = `
                    <form method="POST" style="width: 100%; grid-column: 1/-1;">
                        <input type="hidden" name="booking_id" value="${bookingData.id}">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" name="update_status" class="btn btn-accept" style="width: 100%;">
                            <i class="fas fa-check-circle"></i> Mark as Completed
                        </button>
                    </form>
                `;
                actionButtons.style.display = 'grid';
            } else {
                actionButtons.style.display = 'none';
            }

            // Show modal
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

        // Close modal when clicking outside of it
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('bookingModal');
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeBookingModal();
                }
            });
        });
    </script>

    <!-- Booking Detail Modal -->
    <div class="booking-modal" id="bookingModal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <button class="close-btn" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-client-avatar" id="modalClientAvatar"></div>
                <div class="modal-client-info">
                    <h3 id="modalClientName">Client Name</h3>
                    <p><i class="fas fa-envelope" style="margin-right: 0.5rem;"></i><span id="modalClientEmail">email@example.com</span></p>
                    <p><i class="fas fa-phone" style="margin-right: 0.5rem;"></i><span id="modalClientPhone">+250700000000</span></p>
                </div>
            </div>

            <div class="booking-modal-body">
                <!-- Booking Details Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-briefcase"></i> Service Details
                    </div>
                    <div class="info-row">
                        <span class="info-label">Service Name</span>
                        <span class="info-value" id="modalServiceName">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Description</span>
                        <span class="info-value" id="modalServiceDesc" style="text-align: left; display: block; margin-top: 0.5rem;">—</span>
                    </div>
                </div>

                <!-- Booking Schedule Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-calendar-alt"></i> Schedule
                    </div>
                    <div class="info-row">
                        <span class="info-label">Preferred Date</span>
                        <span class="info-value" id="modalPreferredDate">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Preferred Time</span>
                        <span class="info-value" id="modalPreferredTime">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Service Location</span>
                        <span class="info-value" id="modalLocation">—</span>
                    </div>
                </div>

                <!-- Booking Info Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-info-circle"></i> Booking Information
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value" style="text-align: left;">
                            <span class="badge" id="modalStatusBadge" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem;"></span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Amount</span>
                        <span class="info-value" id="modalAmount">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Urgency Level</span>
                        <span class="info-value" id="modalUrgency">—</span>
                    </div>
                </div>

                <!-- Contact Methods Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-phone-alt"></i> Contact Methods
                    </div>
                    <a class="contact-method" href="mailto:" id="modalContactEmail">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <div style="font-size: 0.7rem; color: var(--secondary); text-transform: uppercase; margin-bottom: 0.2rem; font-weight: 600;">Email</div>
                            <div id="modalClientEmailContact" style="font-weight: 600; font-size: 0.95rem;">email@example.com</div>
                        </div>
                    </a>
                    <a class="contact-method" href="tel:" id="modalContactPhone">
                        <i class="fas fa-phone"></i>
                        <div>
                            <div style="font-size: 0.7rem; color: var(--secondary); text-transform: uppercase; margin-bottom: 0.2rem; font-weight: 600;">Phone</div>
                            <div id="modalClientPhoneContact" style="font-weight: 600; font-size: 0.95rem;">+250700000000</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="modal-action-buttons">
                <form method="POST" id="confirmBookingForm" style="width: 100%;">
                    <input type="hidden" name="booking_id">
                    <input type="hidden" name="status" value="confirmed">
                    <button type="submit" name="update_status" class="btn btn-accept" style="width: 100%; margin-bottom: 0;">
                        <i class="fas fa-check"></i> Confirm Booking
                    </button>
                </form>
                <form method="POST" id="rejectBookingForm" style="width: 100%;" onsubmit="return confirm('Are you sure you want to reject this booking?');">
                    <input type="hidden" name="booking_id">
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" name="update_status" class="btn btn-reject" style="width: 100%; margin-bottom: 0;">
                        <i class="fas fa-times"></i> Reject Booking
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>