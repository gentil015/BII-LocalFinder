<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';
require_once '../includes/language.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    // Allow providers to access but show maintenance warning
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get provider's schedule information
$stmt = $db->prepare("
    SELECT 
        working_days,
        working_hours_start,
        working_hours_end,
        break_start,
        break_end,
        buffer_time,
        max_daily_bookings
    FROM service_providers 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$schedule_info = $stmt->fetch();

// Parse working days
$working_days = $schedule_info['working_days'] ? explode(',', $schedule_info['working_days']) : [1,2,3,4,5];
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$formatted_working_days = [];
foreach ($working_days as $day_num) {
    if (isset($day_names[$day_num-1])) {
        $formatted_working_days[] = $day_names[$day_num-1];
    }
}

// Get today's schedule summary
$today = date('Y-m-d');
$day_of_week = date('N');
$is_working_day = in_array($day_of_week, $working_days);
$working_hours_display = '';
if ($schedule_info['working_hours_start'] && $schedule_info['working_hours_end'] && $is_working_day) {
    $working_hours_display = date('g:i A', strtotime($schedule_info['working_hours_start'])) . ' - ' . 
                            date('g:i A', strtotime($schedule_info['working_hours_end']));
}

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$total_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$provider['id']]);
$pending_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$total_reviews = $stmt->fetch()['total'];

// Calculate earnings (if commission is enabled)
$total_earnings = 0;
if (isCommissionEnabled()) {
    $stmt = $db->prepare("
        SELECT SUM(amount) as total_earnings 
        FROM bookings 
        WHERE provider_id = ? AND status = 'completed' AND payment_status = 'completed'
    ");
    $stmt->execute([$provider['id']]);
    $earnings_data = $stmt->fetch();
    $total_earnings = $earnings_data['total_earnings'] ?? 0;
}

// Get today's bookings count
$stmt = $db->prepare("
    SELECT COUNT(*) as today_bookings 
    FROM bookings 
    WHERE provider_id = ? AND DATE(preferred_date) = CURDATE() AND status IN ('confirmed', 'pending')
");
$stmt->execute([$provider['id']]);
$today_bookings = $stmt->fetch()['today_bookings'];

// Get recent bookings
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as client_name, u.phone as client_phone, u.email as client_email,
               s.name as service_name,
               DATE_FORMAT(b.preferred_date, '%W, %M %d, %Y') as formatted_date,
               DATE_FORMAT(b.preferred_time, '%h:%i %p') as formatted_time
        FROM bookings b
        JOIN users u ON b.client_id = u.id
        LEFT JOIN provider_services s ON b.service_id = s.id
        WHERE b.provider_id = ?
        ORDER BY b.preferred_date DESC, b.preferred_time DESC
        LIMIT 5
    ");
    $stmt->execute([$provider['id']]);
    $recent_bookings = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Dashboard: failed to load recent bookings: ' . $e->getMessage());
    $recent_bookings = [];
}

// Get upcoming bookings for today
$stmt = $db->prepare("
    SELECT COUNT(*) as upcoming_today
    FROM bookings 
    WHERE provider_id = ? AND DATE(preferred_date) = CURDATE() 
    AND status IN ('confirmed', 'pending')
    AND (preferred_time IS NULL OR preferred_time > CURTIME())
");
$stmt->execute([$provider['id']]);
$upcoming_today = $stmt->fetch()['upcoming_today'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['availability']);
    $stmt = $db->prepare("UPDATE service_providers SET availability = ? WHERE user_id = ?");
    if ($stmt->execute([$new_status, $_SESSION['user_id']])) {
        $_SESSION['success_message'] = "Availability status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update availability status.";
    }
    header("Location: dashboard.php");
    exit();
}

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = sanitize($_POST['booking_action']);
    
    // Check if provider is allowed to reject bookings
    if ($action === 'reject' && !isProviderRejectionAllowed()) {
        $_SESSION['error_message'] = "Booking rejection is currently disabled by admin.";
        header("Location: dashboard.php");
        exit();
    }
    
    $allowed_statuses = [];
    switch ($action) {
        case 'confirm':
            $new_status = 'confirmed';
            $allowed_statuses = ['pending'];
            break;
        case 'reject':
            $new_status = 'cancelled';
            $allowed_statuses = ['pending'];
            break;
        case 'complete':
            $new_status = 'completed';
            $allowed_statuses = ['confirmed'];
            break;
        default:
            $_SESSION['error_message'] = "Invalid action.";
            header("Location: dashboard.php");
            exit();
    }
    
    // Verify booking belongs to provider and is in allowed status
    $stmt = $db->prepare("SELECT status FROM bookings WHERE id = ? AND provider_id = ?");
    $stmt->execute([$booking_id, $provider['id']]);
    $booking = $stmt->fetch();
    
    if ($booking && in_array($booking['status'], $allowed_statuses)) {
        $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $booking_id])) {
            $_SESSION['success_message'] = "Booking {$action}ed successfully!";
            
            // Send notification if enabled
            if (isEmailNotificationsEnabled()) {
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
                    require_once '../includes/mailer.php';
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
            $_SESSION['error_message'] = "Failed to update booking.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid booking or action not allowed.";
    }
    
    header("Location: dashboard.php");
    exit();
}

// Get provider's services with schedule info
try {
    $stmt = $db->prepare("
        SELECT s.*, c.name as category_name, c.icon as category_icon,
               (SELECT COUNT(*) FROM bookings WHERE service_id = s.id AND status IN ('confirmed', 'pending')) as upcoming_bookings
        FROM provider_services s 
        LEFT JOIN categories c ON s.category_id = c.id 
        WHERE s.provider_id = ? 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$provider['id']]);
    $recent_services = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Dashboard: failed to load provider services: ' . $e->getMessage());
    $recent_services = [];
}

// Get notification filter from request
$notification_filter = sanitize($_GET['filter'] ?? 'all');
$allowed_filters = ['all', 'booking', 'offer', 'favorite', 'service_update', 'service_added', 'review', 'profile_view', 'complaint', 'system'];
if (!in_array($notification_filter, $allowed_filters)) {
    $notification_filter = 'all';
}

// Get notifications
$notification_options = [
    'limit' => 100,
    'offset' => 0
];

if ($notification_filter !== 'all') {
    $notification_options['type'] = $notification_filter;
}

$all_notifications = getNotifications($_SESSION['user_id'], $notification_options);
$unread_count = getUnreadNotificationCount($_SESSION['user_id']);
$notification_stats = getNotificationStats($_SESSION['user_id']);

// Handle notification actions via AJAX (API endpoint)
// Remove old form-based POST handling - now handled via AJAX in frontend

// Get schedule summary for the week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$stmt = $db->prepare("
    SELECT DATE(preferred_date) as booking_date, COUNT(*) as booking_count
    FROM bookings
    WHERE provider_id = ? AND preferred_date BETWEEN ? AND ?
    AND status IN ('confirmed', 'pending')
    GROUP BY DATE(preferred_date)
    ORDER BY booking_date
");
$stmt->execute([$provider['id'], $week_start, $week_end]);
$weekly_bookings = $stmt->fetchAll();

// Calculate weekly booking distribution
$weekly_data = [];
$total_weekly = 0;
foreach ($weekly_bookings as $booking) {
    $weekly_data[$booking['booking_date']] = $booking['booking_count'];
    $total_weekly += $booking['booking_count'];
}

// Check if provider needs to complete profile
$profile_completion = 0;
$required_fields = ['full_name', 'email', 'phone', 'business_name', 'description'];
$completed_fields = 0;

foreach ($required_fields as $field) {
    if (!empty($provider[$field])) {
        $completed_fields++;
    }
}
$profile_completion = ($completed_fields / count($required_fields)) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('title', [], 'dashboard'); ?> - <?php echo getPlatformName(); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .dashboard-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .dashboard-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard-header p {
            color: var(--secondary);
            margin: 0;
        }

        .welcome-section h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .welcome-section p {
            color: var(--secondary);
            margin: 0;
        }

        .date-display {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
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
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            background: var(--primary);
            color: white;
        }

        .stat-card.earnings .stat-icon {
            background: var(--success);
        }

        .stat-card.rating .stat-icon {
            background: var(--warning);
            color: #7a4d00;
        }

        .stat-card.schedule .stat-icon {
            background: var(--info);
        }

        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: var(--dark);
        }

        .stat-content p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
        }

        .card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.3rem;
        }

        .card-header h3 i {
            color: var(--primary);
        }

        /* Availability Status */
        .availability-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .status-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .status-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            opacity: 0.9;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-dot.available {
            background: var(--success);
            box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.4);
        }

        .status-dot.busy {
            background: var(--warning);
            box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4);
        }

        .status-dot.unavailable {
            background: var(--danger);
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(25, 135, 84, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(25, 135, 84, 0);
            }
        }

        .status-text {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .status-selector {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
        }

        .status-selector select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .status-selector select:focus {
            outline: none;
            border-color: white;
        }

        .status-selector select option {
            background: var(--primary);
            color: white;
        }

        /* Schedule Summary */
        .schedule-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            transition: all 0.3s;
        }

        .schedule-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(3px);
        }

        .schedule-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .schedule-info h5 {
            margin: 0 0 0.25rem 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .schedule-info p {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        /* Bookings List */
        .bookings-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .booking-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.25rem;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }

        .booking-item:hover {
            background: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .booking-client {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .client-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .client-info h5 {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .client-info p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
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

        .booking-details {
            margin: 1rem 0;
        }

        .booking-service {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .booking-description {
            color: var(--secondary);
            line-height: 1.5;
            margin: 0;
            font-size: 0.9rem;
        }

        .booking-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .booking-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .booking-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .action-btn.confirm {
            background: var(--success);
            color: white;
        }

        .action-btn.reject {
            background: var(--danger);
            color: white;
        }

        .action-btn.complete {
            background: var(--info);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        /* Services List */
        .services-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .service-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.25rem;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }

        .service-item:hover {
            background: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .service-info h5 {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 1rem;
        }

        .service-category {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 500;
            font-size: 0.85rem;
        }

        .service-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--success);
        }

        .service-schedule {
            background: rgba(13, 110, 253, 0.05);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
        }

        .schedule-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .schedule-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.5rem;
        }

        .schedule-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--secondary);
        }

        .schedule-detail i {
            color: var(--primary);
            width: 18px;
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .action-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .action-card h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            color: #e9ecef;
            opacity: 0.7;
        }

        .empty-state h4 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
            padding: 1.25rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
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

            .main-grid {
                grid-template-columns: 1fr;
            }

            .booking-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .booking-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .status-display {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .schedule-summary {
                grid-template-columns: 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Notification Filter Styles */
        .notification-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow-x: auto;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.6rem 1.2rem;
            border: 2px solid #e9ecef;
            background: white;
            color: var(--secondary);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            text-decoration: none;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .filter-btn i {
            font-size: 1rem;
        }

        /* Notification Card */
        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid var(--secondary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .notification-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .notification-card.unread {
            background: #f0f6ff;
            border-left-color: var(--primary);
        }

        .notification-card.booking {
            border-left-color: #007bff;
        }

        .notification-card.offer {
            border-left-color: #28a745;
        }

        .notification-card.favorite {
            border-left-color: #dc3545;
        }

        .notification-card.service_update,
        .notification-card.service_added {
            border-left-color: #ffc107;
        }

        .notification-card.review {
            border-left-color: #ffc107;
        }

        .notification-card.profile_view {
            border-left-color: #17a2b8;
        }

        .notification-card.complaint {
            border-left-color: #dc3545;
        }

        .notification-card.system {
            border-left-color: #6c757d;
        }

        .notification-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            background: rgba(0,0,0,0.05);
        }

        .notification-icon.booking {
            background: rgba(0,123,255,0.1);
            color: #007bff;
        }

        .notification-icon.offer {
            background: rgba(40,167,69,0.1);
            color: #28a745;
        }

        .notification-icon.favorite {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }

        .notification-icon.service_update,
        .notification-icon.service_added {
            background: rgba(255,193,7,0.1);
            color: #ffc107;
        }

        .notification-icon.review {
            background: rgba(255,193,7,0.1);
            color: #ffc107;
        }

        .notification-icon.profile_view {
            background: rgba(23,162,184,0.1);
            color: #17a2b8;
        }

        .notification-icon.complaint {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }

        .notification-title {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .notification-title h5 {
            margin: 0;
            color: var(--dark);
            font-weight: 700;
            font-size: 1.1rem;
            flex: 1;
        }

        .notification-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--light);
            color: var(--secondary);
            margin-left: 1rem;
        }

        .notification-badge.unread {
            background: var(--primary);
            color: white;
        }

        .notification-badge.urgent {
            background: var(--danger);
            color: white;
        }

        .notification-badge.high {
            background: var(--warning);
            color: #333;
        }

        .notification-message {
            color: var(--secondary);
            margin-bottom: 0.75rem;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .notification-time {
            font-size: 0.85rem;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--light);
            color: var(--secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .notification-btn:hover {
            background: var(--primary);
            color: white;
        }

        .notification-btn.primary {
            background: var(--primary);
            color: white;
        }

        .notification-btn.primary:hover {
            background: #0b5ed7;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--secondary);
            background: white;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #e9ecef;
            opacity: 0.7;
        }

        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
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

        /* View All Button */
        .view-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            background: rgba(13, 110, 253, 0.1);
            transition: all 0.3s;
        }

        .view-all-btn:hover {
            background: rgba(13, 110, 253, 0.2);
            transform: translateX(3px);
        }
    </style>
    <!-- Shared User Behavior Tracking -->
    <?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
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
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-tools fa-2x me-3"></i>
                    <div>
                        <h4 class="mb-1">Maintenance Mode Active</h4>
                        <p class="mb-0">The platform is currently under maintenance. Some features may be limited.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Completion Alert -->
        <?php if ($profile_completion < 100): ?>
            <div class="alert alert-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-check fa-2x me-3"></i>
                        <div>
                            <h4 class="mb-1">Complete Your Profile</h4>
                            <p class="mb-0">Your profile is <?php echo round($profile_completion); ?>% complete.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 100px;">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: <?php echo $profile_completion; ?>%"></div>
                            </div>
                        </div>
                        <a href="profile.php" class="btn btn-light btn-sm px-3">
                            <i class="fas fa-edit me-2"></i>Complete
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div class="flex-grow-1">
                        <?php echo $_SESSION['success_message']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: brightness(0) invert(1);"></button>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div class="flex-grow-1">
                        <?php echo $_SESSION['error_message']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: brightness(0) invert(1);"></button>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($provider['full_name']); ?>.</h1>
                <p><?php echo __('welcome.subtitle', [], 'dashboard'); ?></p>
            </div>
            <div class="date-display">
                <i class="fas fa-calendar-alt me-2"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_bookings; ?></h3>
                    <p><?php echo __('statistics.total_bookings', [], 'dashboard'); ?></p>
                    <?php if ($today_bookings > 0): ?>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span><?php echo $today_bookings; ?> <?php echo __('today', [], 'dashboard'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_bookings; ?></h3>
                    <p><?php echo __('statistics.pending_bookings', [], 'dashboard'); ?></p>
                    <?php if ($pending_bookings > 0): ?>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo __('bookings.action_success', [], 'dashboard'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card rating">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($provider['average_rating'] ?? 0, 1); ?></h3>
                    <p><?php echo __('statistics.average_rating', [], 'dashboard'); ?></p>
                    <div class="stat-trend">
                        <i class="fas fa-star text-warning"></i>
                        <span><?php echo $total_reviews; ?> <?php echo __('notifications.review', [], 'dashboard'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card schedule">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $upcoming_today; ?></h3>
                    <p><?php echo __('statistics.today_bookings', [], 'dashboard'); ?></p>
                    <?php if ($is_working_day && $working_hours_display): ?>
                        <div class="stat-trend">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $working_hours_display; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isCommissionEnabled()): ?>
            <div class="stat-card earnings">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3>RWF <?php echo number_format($total_earnings, 0); ?></h3>
                    <p><?php echo __('statistics.total_earnings', [], 'dashboard'); ?></p>
                    <?php if ($total_earnings > 0): ?>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-chart-line"></i>
                            <span>Keep it up!</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Availability Card -->
        <div class="availability-card">
            <form method="POST">
                <div class="status-display">
                    <div class="status-info">
                        <h4><?php echo __('availability.title', [], 'dashboard'); ?></h4>
                        <div class="status-indicator">
                            <div class="status-dot <?php echo $provider['availability'] ?? 'available'; ?>"></div>
                            <div class="status-text"><?php echo ucfirst($provider['availability'] ?? 'available'); ?></div>
                        </div>
                    </div>
                    <div class="status-selector" style="min-width: 250px;">
                        <select name="availability" class="form-select" onchange="this.form.submit()">
                            <option value="available" <?php echo ($provider['availability'] ?? 'available') === 'available' ? 'selected' : ''; ?>><?php echo __('availability.available', [], 'dashboard'); ?> - <?php echo __('bookings.confirmed', [], 'dashboard'); ?></option>
                            <option value="busy" <?php echo ($provider['availability'] ?? 'available') === 'busy' ? 'selected' : ''; ?>><?php echo __('availability.busy', [], 'dashboard'); ?> - <?php echo __('availability.working_hours', [], 'dashboard'); ?></option>
                            <option value="unavailable" <?php echo ($provider['availability'] ?? 'available') === 'unavailable' ? 'selected' : ''; ?>><?php echo __('availability.unavailable', [], 'dashboard'); ?> - <?php echo __('bookings.no_bookings', [], 'dashboard'); ?></option>
                        </select>
                        <input type="hidden" name="update_status" value="1">
                    </div>
                </div>
                
                <?php if ($schedule_info && $is_working_day): ?>
                <div class="schedule-summary">
                    <div class="schedule-item">
                        <div class="schedule-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="schedule-info">
                            <h5><?php echo __('schedule.today_summary', [], 'dashboard'); ?></h5>
                            <p><?php echo $working_hours_display; ?></p>
                        </div>
                    </div>
                    
                    <div class="schedule-item">
                        <div class="schedule-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="schedule-info">
                            <h5><?php echo __('schedule.working_days', [], 'dashboard'); ?></h5>
                            <p><?php echo implode(', ', array_slice($formatted_working_days, 0, 3)); if (count($formatted_working_days) > 3) echo '...'; ?></p>
                        </div>
                    </div>
                    
                    <?php if ($schedule_info['buffer_time']): ?>
                    <div class="schedule-item">
                        <div class="schedule-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="schedule-info">
                            <h5><?php echo __('schedule.buffer_time', [], 'dashboard'); ?></h5>
                            <p><?php echo $schedule_info['buffer_time']; ?> <?php echo __('settings.availability.buffer_time', [], 'settings'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($schedule_info['max_daily_bookings']): ?>
                    <div class="schedule-item">
                        <div class="schedule-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="schedule-info">
                            <h5><?php echo __('schedule.max_daily', [], 'dashboard'); ?></h5>
                            <p><?php echo $schedule_info['max_daily_bookings']; ?> <?php echo __('bookings.bookings', [], 'dashboard'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Notifications Center -->
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> <?php echo __('notifications.title', [], 'dashboard'); ?></h3>
                    <?php if ($unread_count > 0): ?>
                        <button id="markAllReadBtn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-check-double me-1"></i> <?php echo __('notifications.mark_all_read', [], 'dashboard'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Filter Tabs -->
                <div class="notification-filters">
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'all' ? 'active' : ''; ?>" data-filter="all">
                        <i class="fas fa-inbox"></i> <?php echo __('notifications.all', [], 'dashboard'); ?>
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'booking' ? 'active' : ''; ?>" data-filter="booking">
                        <i class="fas fa-calendar-check"></i> <?php echo __('notifications.booking', [], 'dashboard'); ?> (<span class="count"><?php echo $notification_stats['booking'] ?? 0; ?></span>)
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'offer' ? 'active' : ''; ?>" data-filter="offer">
                        <i class="fas fa-handshake"></i> <?php echo __('notifications.offer', [], 'dashboard'); ?> (<span class="count"><?php echo $notification_stats['offer'] ?? 0; ?></span>)
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'favorite' ? 'active' : ''; ?>" data-filter="favorite">
                        <i class="fas fa-heart"></i> <?php echo __('notifications.favorite', [], 'dashboard'); ?> (<span class="count"><?php echo $notification_stats['favorite'] ?? 0; ?></span>)
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'service_update' ? 'active' : ''; ?>" data-filter="service_update">
                        <i class="fas fa-sync"></i> <?php echo __('notifications.service_update', [], 'dashboard'); ?> (<span class="count"><?php echo ($notification_stats['service_update'] ?? 0) + ($notification_stats['service_added'] ?? 0); ?></span>)
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'review' ? 'active' : ''; ?>" data-filter="review">
                        <i class="fas fa-star"></i> <?php echo __('notifications.review', [], 'dashboard'); ?> (<span class="count"><?php echo $notification_stats['review'] ?? 0; ?></span>)
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'profile_view' ? 'active' : ''; ?>" data-filter="profile_view">
                        <i class="fas fa-eye"></i> <?php echo __('notifications.profile_view', [], 'dashboard'); ?> (<span class="count"><?php echo $notification_stats['profile_view'] ?? 0; ?></span>)
                    </a>
                    <a href="javascript:void(0)" class="filter-btn <?php echo $notification_filter === 'complaint' ? 'active' : ''; ?>" data-filter="complaint">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo __('notifications.complaint', [], 'dashboard'); ?> (<span class="count"><?php echo $notification_stats['complaint'] ?? 0; ?></span>)
                    </a>
                </div>

                <!-- Notifications List -->
                <?php if (empty($all_notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3><?php echo __('notifications.no_notifications', [], 'dashboard'); ?></h3>
                        <p><?php echo __('messages.loading', [], 'dashboard'); ?></p>
                    </div>
                <?php else: ?>
                    <div id="notificationsList" style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                        <?php foreach ($all_notifications as $notification): ?>
                            <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['notification_type']; ?>" data-notification-id="<?php echo $notification['id']; ?>">
                                <div class="notification-header">
                                    <div class="notification-icon <?php echo $notification['notification_type']; ?>">
                                        <i class="fas <?php echo $notification['icon']; ?>"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div class="notification-title">
                                            <h5><?php echo htmlspecialchars($notification['title']); ?></h5>
                                            <span class="notification-badge <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['priority']; ?>">
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                        </div>
                                        <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    </div>
                                </div>

                                <div class="notification-footer">
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <span class="time-ago"><?php echo timeAgo($notification['created_at']); ?></span>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!$notification['is_read']): ?>
                                            <button class="notification-btn primary mark-read-btn" data-notification-id="<?php echo $notification['id']; ?>">
                                                <i class="fas fa-check"></i> Mark Read
                                            </button>
                                        <?php endif; ?>
                                        <button class="notification-btn delete-btn" data-notification-id="<?php echo $notification['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Bookings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> <?php echo __('bookings.title', [], 'dashboard'); ?></h3>
                    <a href="bookings.php" class="view-all-btn">
                        <?php echo __('bookings.view_all', [], 'dashboard'); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if (empty($recent_bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4><?php echo __('bookings.no_bookings', [], 'dashboard'); ?></h4>
                        <p><?php echo __('welcome.subtitle', [], 'dashboard'); ?></p>
                        <a href="services.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i> <?php echo __('services.add_service', [], 'dashboard'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bookings-list">
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-header">
                                    <div class="booking-client">
                                        <div class="client-avatar">
                                            <?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>
                                        </div>
                                        <div class="client-info">
                                            <h5><?php echo htmlspecialchars($booking['client_name']); ?></h5>
                                            <p>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($booking['client_email']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="badge badge-<?php echo $booking['status']; ?>">
                                        <?php echo __('bookings.' . $booking['status'], [], 'dashboard'); ?>
                                    </span>
                                </div>
                                
                                <div class="booking-details">
                                    <?php if (!empty($booking['service_name'])): ?>
                                        <div class="booking-service">
                                            <i class="fas fa-concierge-bell me-2"></i><?php echo htmlspecialchars($booking['service_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="booking-description">
                                        <?php echo htmlspecialchars($booking['service_description']); ?>
                                    </p>
                                </div>
                                
                                <div class="booking-footer">
                                    <div class="booking-time">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo $booking['formatted_date']; ?></span>
                                        <?php if ($booking['formatted_time']): ?>
                                            <i class="fas fa-clock ms-2"></i>
                                            <span><?php echo $booking['formatted_time']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                                        <div class="booking-actions">
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="booking_action" value="confirm">
                                                    <button type="submit" class="action-btn confirm">
                                                        <i class="fas fa-check me-1"></i> <?php echo __('bookings.confirm', [], 'dashboard'); ?>
                                                    </button>
                                                </form>
                                                <?php if (isProviderRejectionAllowed()): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="booking_action" value="reject">
                                                    <button type="submit" class="action-btn reject">
                                                        <i class="fas fa-times me-1"></i> <?php echo __('bookings.reject', [], 'dashboard'); ?>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="booking_action" value="complete">
                                                    <button type="submit" class="action-btn complete">
                                                        <i class="fas fa-check-double me-1"></i> <?php echo __('bookings.complete', [], 'dashboard'); ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Services with Schedule -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-concierge-bell"></i> <?php echo __('services.title', [], 'dashboard'); ?></h3>
                    <a href="services.php" class="view-all-btn">
                        <?php echo __('settings.common.update', [], 'settings'); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if (empty($recent_services)): ?>
                    <div class="empty-state">
                        <i class="fas fa-concierge-bell"></i>
                        <h4><?php echo __('services.no_services', [], 'dashboard'); ?></h4>
                        <p><?php echo __('welcome.subtitle', [], 'dashboard'); ?></p>
                        <a href="services.php?action=add" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i> <?php echo __('services.add_service', [], 'dashboard'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="services-list">
                        <?php foreach ($recent_services as $service): ?>
                            <div class="service-item">
                                <div class="service-header">
                                    <div class="service-info">
                                        <h5><?php echo htmlspecialchars($service['name']); ?></h5>
                                        <div class="service-category">
                                            <?php if ($service['category_icon']): ?>
                                                <i class="fas <?php echo $service['category_icon']; ?>"></i>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($service['category_name'] ?? 'Uncategorized'); ?></span>
                                        </div>
                                    </div>
                                    <div class="service-price">
                                        RWF <?php echo number_format($service['price'], 0); ?>
                                    </div>
                                </div>
                                
                                <!-- Service Schedule Information -->
                                <div class="service-schedule">
                                    <div class="schedule-title">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo __('settings.availability.section_title', [], 'settings'); ?>
                                    </div>
                                    <div class="schedule-details">
                                        <div class="schedule-detail">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo __('schedule.working_hours', [], 'dashboard'); ?>: <?php echo $service['duration']; ?> mins</span>
                                        </div>
                                        
                                        <?php if ($service['upcoming_bookings'] > 0): ?>
                                        <div class="schedule-detail">
                                            <i class="fas fa-calendar-check"></i>
                                            <span><?php echo $service['upcoming_bookings']; ?> upcoming bookings</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($schedule_info['working_hours_start'] && $schedule_info['working_hours_end']): ?>
                                        <div class="schedule-detail">
                                            <i class="fas fa-business-time"></i>
                                            <span>Hours: <?php echo date('g:i A', strtotime($schedule_info['working_hours_start'])); ?> - <?php echo date('g:i A', strtotime($schedule_info['working_hours_end'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($schedule_info['buffer_time']): ?>
                                        <div class="schedule-detail">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span>Buffer: <?php echo $schedule_info['buffer_time']; ?> mins</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Service Schedule Summary -->
                    <div class="service-schedule mt-3">
                        <div class="schedule-title">
                            <i class="fas fa-calendar-week"></i>
                            Weekly Schedule Overview
                        </div>
                        <div class="schedule-details">
                            <div class="schedule-detail">
                                <i class="fas fa-calendar-day"></i>
                                <span><strong>Working Days:</strong> <?php echo implode(', ', $formatted_working_days); ?></span>
                            </div>
                            
                            <?php if ($total_weekly > 0): ?>
                            <div class="schedule-detail">
                                <i class="fas fa-chart-line"></i>
                                <span><strong>This Week:</strong> <?php echo $total_weekly; ?> bookings</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($schedule_info['max_daily_bookings']): ?>
                            <div class="schedule-detail">
                                <i class="fas fa-user-check"></i>
                                <span><strong>Daily Limit:</strong> <?php echo $schedule_info['max_daily_bookings']; ?> bookings/day</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="schedule-detail">
                                <i class="fas fa-info-circle"></i>
                                <span><a href="schedule-management.php" class="text-primary">Manage schedule settings</a></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="quick-actions-grid">
                <a href="profile.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h6>Update Profile</h6>
                </a>
                
                <a href="bookings.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h6>Manage Bookings</h6>
                </a>
                
                <a href="services.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <h6>My Services</h6>
                </a>
                
                <a href="schedule-management.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h6>Schedule Settings</h6>
                </a>
                
                <a href="reviews.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <h6>View Reviews</h6>
                </a>
                
                <a href="../index.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-external-link-alt"></i>
                    </div>
                    <h6>View Profile</h6>
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== REAL-TIME NOTIFICATIONS SYSTEM =====
        
        // Current filter state
        let currentFilter = '<?php echo $notification_filter; ?>';
        let unreadCount = <?php echo $unread_count; ?>;
        
        // API Endpoint
        const API_URL = './api/notifications.php';
        
        // Initialize notification handlers
        document.addEventListener('DOMContentLoaded', function() {
            setupFilterButtons();
            setupNotificationActions();
            setupMarkAllRead();
            setupAutoRefresh();
        });
        
        // ===== FILTER BUTTONS =====
        function setupFilterButtons() {
            document.querySelectorAll('.notification-filters .filter-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filter = this.dataset.filter;
                    filterNotifications(filter);
                });
            });
        }
        
        function filterNotifications(filter) {
            currentFilter = filter;
            
            // Update active button
            document.querySelectorAll('.notification-filters .filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                }
            });
            
            // Load notifications via AJAX
            loadNotifications(filter);
        }
        
        // ===== LOAD NOTIFICATIONS =====
        function loadNotifications(filter = 'all') {
            const params = new URLSearchParams();
            params.append('action', 'get_notifications');
            if (filter !== 'all') {
                params.append('type', filter);
            }
            params.append('limit', 100);
            
            fetch(API_URL + '?' + params, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderNotifications(data.notifications);
                    updateUnreadCount();
                } else {
                    showError('Failed to load notifications');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error loading notifications');
            });
        }
        
        // ===== RENDER NOTIFICATIONS =====
        function renderNotifications(notifications) {
            const container = document.getElementById('notificationsList');
            if (!container) return;
            
            if (notifications.length === 0) {
                container.parentElement.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications</h3>
                        <p>You're all caught up! No new notifications at this time.</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = notifications.map(notif => `
                <div class="notification-card ${notif.is_read ? '' : 'unread'} ${notif.notification_type}" data-notification-id="${notif.id}">
                    <div class="notification-header">
                        <div class="notification-icon ${notif.notification_type}">
                            <i class="fas ${notif.icon}"></i>
                        </div>
                        <div style="flex: 1;">
                            <div class="notification-title">
                                <h5>${escapeHtml(notif.title)}</h5>
                                <span class="notification-badge ${notif.is_read ? '' : 'unread'} ${notif.priority}">
                                    ${notif.priority.charAt(0).toUpperCase() + notif.priority.slice(1)}
                                </span>
                            </div>
                            <p class="notification-message">${escapeHtml(notif.message)}</p>
                        </div>
                    </div>
                    <div class="notification-footer">
                        <div class="notification-time">
                            <i class="fas fa-clock"></i>
                            <span class="time-ago">${notif.time_ago}</span>
                        </div>
                        <div class="notification-actions">
                            ${!notif.is_read ? `
                                <button class="notification-btn primary mark-read-btn" data-notification-id="${notif.id}">
                                    <i class="fas fa-check"></i> Mark Read
                                </button>
                            ` : ''}
                            <button class="notification-btn delete-btn" data-notification-id="${notif.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
            
            setupNotificationActions();
        }
        
        // ===== NOTIFICATION ACTIONS =====
        function setupNotificationActions() {
            // Mark as read buttons
            document.querySelectorAll('.mark-read-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const notificationId = this.dataset.notificationId;
                    markNotificationAsRead(notificationId);
                });
            });
            
            // Delete buttons
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const notificationId = this.dataset.notificationId;
                    deleteNotification(notificationId);
                });
            });
        }
        
        // Mark notification as read
        function markNotificationAsRead(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', notificationId);
            
            fetch(API_URL, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update card immediately
                    const card = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (card) {
                        card.classList.remove('unread');
                        const markBtn = card.querySelector('.mark-read-btn');
                        if (markBtn) markBtn.remove();
                    }
                    updateUnreadCount();
                    showSuccess('Notification marked as read');
                } else {
                    showError('Failed to mark notification as read');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error updating notification');
            });
        }
        
        // Delete notification
        function deleteNotification(notificationId) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('notification_id', notificationId);
            
            fetch(API_URL, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove card with animation
                    const card = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (card) {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(100%)';
                        setTimeout(() => card.remove(), 300);
                    }
                    updateUnreadCount();
                    showSuccess('Notification deleted');
                } else {
                    showError('Failed to delete notification');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error deleting notification');
            });
        }
        
        // ===== MARK ALL READ =====
        function setupMarkAllRead() {
            const btn = document.getElementById('markAllReadBtn');
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    markAllAsRead();
                });
            }
        }
        
        function markAllAsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            
            fetch(API_URL, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        const markBtn = card.querySelector('.mark-read-btn');
                        if (markBtn) markBtn.remove();
                    });
                    updateUnreadCount();
                    showSuccess('All notifications marked as read');
                    
                    // Hide mark all read button
                    const btn = document.getElementById('markAllReadBtn');
                    if (btn) btn.style.display = 'none';
                } else {
                    showError('Failed to mark all as read');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error updating notifications');
            });
        }
        
        // ===== UPDATE UNREAD COUNT =====
        function updateUnreadCount() {
            fetch(API_URL + '?action=get_unread_count', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    unreadCount = data.unread_count;
                    updateBadge(unreadCount);
                    
                    // Hide mark all read if no unread
                    if (unreadCount === 0) {
                        const btn = document.getElementById('markAllReadBtn');
                        if (btn) btn.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function updateBadge(count) {
            // Update sidebar badge
            const badges = document.querySelectorAll('.sidebar-menu .notification-badge');
            if (badges.length > 0 && count > 0) {
                badges.forEach(badge => {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                });
            } else if (badges.length > 0) {
                badges.forEach(badge => badge.style.display = 'none');
            }
        }
        
        // ===== AUTO REFRESH =====
        function setupAutoRefresh() {
            // Refresh notification count every 30 seconds
            setInterval(() => {
                updateUnreadCount();
            }, 30000);
        }
        
        // ===== TOAST NOTIFICATIONS =====
        function showSuccess(message) {
            showToast(message, 'success');
        }
        
        function showError(message) {
            showToast(message, 'danger');
        }
        
        function showToast(message, type = 'info') {
            const alertClass = `alert-${type}`;
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show`;
            alert.role = 'alert';
            alert.style.position = 'fixed';
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '9999';
            alert.style.minWidth = '300px';
            alert.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    <span>${escapeHtml(message)}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.appendChild(alert);
            
            const bsAlert = new bootstrap.Alert(alert);
            setTimeout(() => bsAlert.close(), 4000);
        }
        
        // ===== UTILITY FUNCTIONS =====
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Mobile sidebar toggle (existing code)
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            });
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Close sidebar when clicking on menu items on mobile
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert:not([style*="position"])');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Confirm booking actions
        document.querySelectorAll('form[method="POST"] button[type="submit"]').forEach(button => {
            if (button.textContent.includes('Reject') || button.textContent.includes('Complete')) {
                button.addEventListener('click', function(e) {
                    const action = this.textContent.includes('Reject') ? 'reject' : 'complete';
                    const message = action === 'reject' 
                        ? 'Are you sure you want to reject this booking?' 
                        : 'Mark this booking as completed?';
                    
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            }
        });
        
        // Animate stat cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Apply animation to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(card);
        });
        
        // Update time every minute
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateStr = now.toLocaleDateString('en-US', options);
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            
            const dateElement = document.querySelector('.date-display');
            if (dateElement) {
                dateElement.innerHTML = `<i class="fas fa-calendar-alt me-2"></i>${dateStr} | ${timeStr}`;
            }
        }
        
        // Initial update and set interval
        updateTime();
        setInterval(updateTime, 60000);
        
        // Add hover effect to cards
        document.querySelectorAll('.card, .stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '';
            });
        });
        
        // Handle window resize
        function handleResize() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        window.addEventListener('resize', handleResize);
    </script>
</body>
</html>