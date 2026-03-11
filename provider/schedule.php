<?php
session_start();
require_once '../config/.env.loader.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/GoogleCalendarAuth.php';
require_once '../includes/GoogleCalendarAPI.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];
// Active tab (server-side) - falls back to 'calendar'
$active_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'calendar';

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize Google Calendar Auth
    $google_auth = new GoogleCalendarAuth($provider['id']);
    $google_auth->initializeTokenTable();
    
    // Start Google Calendar authentication
    if (isset($_POST['start_google_auth'])) {
        try {
            $auth_url = $google_auth->getAuthorizationUrl();
            $_SESSION['google_auth_redirect'] = $_POST['return_url'] ?? 'schedule.php?tab=integrations';
            header('Location: ' . $auth_url);
            exit;
        } catch (Exception $e) {
            $errors[] = "Failed to start Google authentication: " . $e->getMessage();
        }
    }
    
    // Disconnect Google Calendar
    if (isset($_POST['disconnect_google'])) {
        try {
            if ($google_auth->revokeAccess()) {
                $success = __('schedule.google_disconnected', [], 'dashboard');
            } else {
                $errors[] = __('schedule.google_disconnect_failed', [], 'dashboard');
            }
        } catch (Exception $e) {
            $errors[] = __('schedule.google_disconnect_error', [], 'dashboard') . $e->getMessage();
        }
    }
    
    // Update working hours
    if (isset($_POST['update_hours'])) {
        try {
            $working_days = $_POST['working_days'] ?? [];
            $start_time = sanitize($_POST['start_time']);
            $end_time = sanitize($_POST['end_time']);
            $break_start = sanitize($_POST['break_start'] ?? '');
            $break_end = sanitize($_POST['break_end'] ?? '');
            $buffer_time = intval($_POST['buffer_time']);
            $max_bookings_per_day = intval($_POST['max_bookings_per_day']);
            
            $stmt = $db->prepare("
                UPDATE service_providers SET 
                    working_days = ?,
                    working_hours_start = ?,
                    working_hours_end = ?,
                    break_start = ?,
                    break_end = ?,
                    buffer_time = ?,
                    max_daily_bookings = ?
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                implode(',', $working_days),
                $start_time,
                $end_time,
                $break_start,
                $break_end,
                $buffer_time,
                $max_bookings_per_day,
                $_SESSION['user_id']
            ]);
            
            $success = __('schedule.working_hours_updated', [], 'dashboard');
        } catch (Exception $e) {
            $errors[] = __('schedule.working_hours_failed', [], 'dashboard') . $e->getMessage();
        }
    }
    
    // Add time off
    if (isset($_POST['add_time_off'])) {
        $start_date = sanitize($_POST['time_off_start']);
        $end_date = sanitize($_POST['time_off_end']);
        $reason = sanitize($_POST['time_off_reason']);
        
        if (empty($start_date) || empty($end_date)) {
            $errors[] = __('schedule.dates_required', [], 'dashboard');
        } elseif ($end_date < $start_date) {
            $errors[] = __('schedule.date_invalid', [], 'dashboard');
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO provider_time_off (provider_id, start_date, end_date, reason, is_approved)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$provider['id'], $start_date, $end_date, $reason]);
                $success = __('schedule.timeoff_added', [], 'dashboard');
            } catch (Exception $e) {
                $errors[] = __('schedule.timeoff_failed', [], 'dashboard') . $e->getMessage();
            }
        }
    }
    
    // Update specific day availability
    if (isset($_POST['update_day_availability'])) {
        $date = sanitize($_POST['specific_date']);
        $is_available = isset($_POST['specific_is_available']) ? 1 : 0;
        $start_time = sanitize($_POST['specific_start_time'] ?? '');
        $end_time = sanitize($_POST['specific_end_time'] ?? '');
        $notes = sanitize($_POST['specific_notes'] ?? '');
        
        if (empty($date)) {
            $errors[] = __('schedule.date_select', [], 'dashboard');
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO provider_availability (provider_id, date, is_available, start_time, end_time, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        is_available = VALUES(is_available),
                        start_time = VALUES(start_time),
                        end_time = VALUES(end_time),
                        notes = VALUES(notes)
                ");
                $stmt->execute([$provider['id'], $date, $is_available, $start_time, $end_time, $notes]);
                $success = __('schedule.availability_updated', [], 'dashboard');
            } catch (Exception $e) {
                $errors[] = __('schedule.availability_failed', [], 'dashboard') . $e->getMessage();
            }
        }
    }
    
    // Delete time off
    if (isset($_POST['delete_time_off'])) {
        $time_off_id = intval($_POST['time_off_id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM provider_time_off WHERE id = ? AND provider_id = ?");
            $stmt->execute([$time_off_id, $provider['id']]);
            $success = __('schedule.timeoff_deleted', [], 'dashboard');
        } catch (Exception $e) {
            $errors[] = "Failed to delete time off: " . $e->getMessage();
        }
    }
    
    // Import Google Calendar
    if (isset($_POST['import_google_calendar'])) {
        $google_calendar_id = sanitize($_POST['google_calendar_id']);
        
        if (!empty($google_calendar_id)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                    VALUES (?, 'google_calendar_id', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$provider['id'], $google_calendar_id]);
                $success = "Google Calendar ID saved. Please connect via OAuth for full integration.";
            } catch (Exception $e) {
                $errors[] = "Failed to save Google Calendar ID: " . $e->getMessage();
            }
        }
    }
    
    // Bulk update availability
    if (isset($_POST['bulk_update_availability'])) {
        $bulk_start_date = sanitize($_POST['bulk_start_date']);
        $bulk_end_date = sanitize($_POST['bulk_end_date']);
        $bulk_days = $_POST['bulk_days'] ?? [];
        $bulk_is_available = isset($_POST['bulk_is_available']) ? 1 : 0;
        $bulk_start_time = sanitize($_POST['bulk_start_time'] ?? '');
        $bulk_end_time = sanitize($_POST['bulk_end_time'] ?? '');
        
        if (empty($bulk_start_date) || empty($bulk_end_date) || empty($bulk_days)) {
            $errors[] = __('schedule.dates_required', [], 'dashboard');
        } else {
            try {
                $start = new DateTime($bulk_start_date);
                $end = new DateTime($bulk_end_date);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end);
                
                foreach ($period as $date) {
                    $dayOfWeek = $date->format('N'); // 1=Monday, 7=Sunday
                    if (in_array($dayOfWeek, $bulk_days)) {
                        $dateStr = $date->format('Y-m-d');
                        
                        $stmt = $db->prepare("
                            INSERT INTO provider_availability (provider_id, date, is_available, start_time, end_time)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                is_available = VALUES(is_available),
                                start_time = VALUES(start_time),
                                end_time = VALUES(end_time)
                        ");
                        $stmt->execute([$provider['id'], $dateStr, $bulk_is_available, $bulk_start_time, $bulk_end_time]);
                    }
                }
                
                $success = __('schedule.bulk_availability_updated', [], 'dashboard');
            } catch (Exception $e) {
                $errors[] = __('schedule.bulk_availability_failed', [], 'dashboard') . $e->getMessage();
            }
        }
    }
}

// Get provider's working days
$working_days = $provider['working_days'] ? explode(',', $provider['working_days']) : [1,2,3,4,5]; // Default: Mon-Fri

// Get upcoming bookings
$stmt = $db->prepare("
    SELECT b.*, u.full_name as client_name, u.phone as client_phone, 
           u.email as client_email, ps.name as service_name
    FROM bookings b
    JOIN users u ON b.client_id = u.id
    LEFT JOIN provider_services ps ON b.service_id = ps.id
    WHERE b.provider_id = ? AND b.status IN ('confirmed', 'pending')
    AND DATE(b.preferred_date) >= CURDATE()
    ORDER BY b.preferred_date ASC, b.preferred_time ASC
    LIMIT 20
");
$stmt->execute([$provider['id']]);
$upcoming_bookings = $stmt->fetchAll();

// Get time off requests
$stmt = $db->prepare("
    SELECT * FROM provider_time_off 
    WHERE provider_id = ? AND end_date >= CURDATE()
    ORDER BY start_date ASC
");
$stmt->execute([$provider['id']]);
$time_off_requests = $stmt->fetchAll();

// Get availability exceptions
$stmt = $db->prepare("
    SELECT * FROM provider_availability 
    WHERE provider_id = ? AND date >= CURDATE()
    ORDER BY date ASC
    LIMIT 30
");
$stmt->execute([$provider['id']]);
$availability_exceptions = $stmt->fetchAll();

// Get statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_upcoming,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM bookings
    WHERE provider_id = ? AND DATE(preferred_date) >= CURDATE()
");
$stmt->execute([$provider['id']]);
$stats = $stmt->fetch();

// Get booked dates for calendar
$stmt = $db->prepare("
    SELECT DISTINCT DATE(preferred_date) as booked_date
    FROM bookings
    WHERE provider_id = ? AND status IN ('confirmed', 'pending')
    AND DATE(preferred_date) >= CURDATE()
    LIMIT 60
");
$stmt->execute([$provider['id']]);
$booked_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get time off dates for calendar
$stmt = $db->prepare("
    SELECT start_date, end_date FROM provider_time_off 
    WHERE provider_id = ? AND end_date >= CURDATE()
");
$stmt->execute([$provider['id']]);
$time_off_periods = $stmt->fetchAll();

// Get next available slot
$stmt = $db->prepare("
    SELECT MIN(preferred_date) as next_available
    FROM bookings
    WHERE provider_id = ? AND status = 'confirmed'
    AND preferred_date > NOW()
");
$stmt->execute([$provider['id']]);
$next_available = $stmt->fetchColumn();

// Google Calendar integration status
$stmt = $db->prepare("
    SELECT setting_value FROM provider_settings 
    WHERE provider_id = ? AND setting_key = 'google_calendar_id'
");
$stmt->execute([$provider['id']]);
$google_calendar_id = $stmt->fetchColumn();

// Initialize Google Calendar Auth for display
$google_auth = new GoogleCalendarAuth($provider['id']);
$google_auth->initializeTokenTable();
$google_auth_status = $google_auth->getAuthStatus();
$google_authenticated = $google_auth_status['authenticated'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - <?php echo getPlatformName(); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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
        
        /* Maintenance Warning */
        .maintenance-warning {
            background: linear-gradient(135deg, var(--warning), #e0a800);
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
        
        /* Tabs Navigation */
        .tabs-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--secondary);
        }
        
        .tab-button:hover {
            background: #f8f9fa;
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Settings Section */
        .settings-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        /* Calendar Container */
        .calendar-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .fc-event {
            cursor: pointer;
        }
        
        /* Booking List */
        .booking-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .booking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s;
        }
        
        .booking-item:hover {
            background: #f8fafc;
        }
        
        .booking-item:last-child {
            border-bottom: none;
        }
        
        .booking-info {
            flex: 1;
        }
        
        .booking-client {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
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
        
        .booking-datetime {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .booking-service {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge.confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Time Off List */
        .time-off-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .time-off-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .time-off-item:last-child {
            border-bottom: none;
        }
        
        .time-off-dates {
            font-weight: 600;
            color: var(--dark);
        }
        
        .time-off-reason {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        /* Days of week selector */
        .days-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .day-checkbox {
            position: relative;
        }
        
        .day-checkbox input {
            display: none;
        }
        
        .day-checkbox label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .day-checkbox input:checked + label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Integration Card */
        .integration-card {
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
        }
        
        .integration-card:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }
        
        .integration-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        
        /* Bulk Update Form */
        .bulk-update-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1.5rem;
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
            
            .tabs-navigation {
                flex-direction: column;
            }
            
            .tab-button {
                text-align: center;
            }
            
            .booking-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
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
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .time-slot-legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .legend-booked { background: var(--danger); }
        .legend-available { background: var(--success); }
        .legend-time-off { background: var(--secondary); }
        .legend-break { background: var(--warning); }
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
                <strong><?php echo __('schedule.maintenance_mode', [], 'dashboard'); ?></strong>
                <p class="mb-0 mt-2"><?php echo __('schedule.maintenance_message', [], 'dashboard'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> <?php echo __('schedule.title', [], 'dashboard'); ?></h1>
            <p><?php echo __('schedule.subtitle', [], 'dashboard'); ?></p>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $stats['total_upcoming'] ?? 0; ?></h3>
                <p><?php echo __('schedule.upcoming_bookings', [], 'dashboard'); ?></p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                <p><?php echo __('schedule.pending_approvals', [], 'dashboard'); ?></p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $stats['confirmed'] ?? 0; ?></h3>
                <p><?php echo __('schedule.confirmed_bookings', [], 'dashboard'); ?></p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <h3>
                    <?php echo $next_available ? date('M d', strtotime($next_available)) : 'N/A'; ?>
                </h3>
                <p><?php echo __('schedule.next_available', [], 'dashboard'); ?></p>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-navigation">
            <a href="?tab=calendar" class="tab-button<?php echo $active_tab === 'calendar' ? ' active' : ''; ?>" data-tab="calendar"><?php echo __('schedule.tab_calendar', [], 'dashboard'); ?></a>
            <a href="?tab=settings" class="tab-button<?php echo $active_tab === 'settings' ? ' active' : ''; ?>" data-tab="settings"><?php echo __('schedule.tab_settings', [], 'dashboard'); ?></a>
            <a href="?tab=availability" class="tab-button<?php echo $active_tab === 'availability' ? ' active' : ''; ?>" data-tab="availability"><?php echo __('schedule.tab_availability', [], 'dashboard'); ?></a>
            <a href="?tab=timeoff" class="tab-button<?php echo $active_tab === 'timeoff' ? ' active' : ''; ?>" data-tab="timeoff"><?php echo __('schedule.tab_timeoff', [], 'dashboard'); ?></a>
            <a href="?tab=upcoming" class="tab-button<?php echo $active_tab === 'upcoming' ? ' active' : ''; ?>" data-tab="upcoming"><?php echo __('schedule.tab_bookings', [], 'dashboard'); ?></a>
            <a href="?tab=integrations" class="tab-button<?php echo $active_tab === 'integrations' ? ' active' : ''; ?>" data-tab="integrations"><?php echo __('schedule.tab_integrations', [], 'dashboard'); ?></a>
        </div>

        <!-- Calendar View Tab -->
        <div id="calendar" class="tab-content<?php echo $active_tab === 'calendar' ? ' active' : ''; ?>">
            <div class="calendar-container">
                <h3 class="section-title"><?php echo __('schedule.calendar_overview', [], 'dashboard'); ?></h3>
                <div id="calendarEl"></div>
                
                <div class="time-slot-legend">
                    <div class="legend-item">
                        <div class="legend-color legend-booked"></div>
                        <span><?php echo __('schedule.calendar_booked', [], 'dashboard'); ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-available"></div>
                        <span><?php echo __('schedule.calendar_available', [], 'dashboard'); ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-time-off"></div>
                        <span><?php echo __('schedule.calendar_time_off', [], 'dashboard'); ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-break"></div>
                        <span><?php echo __('schedule.calendar_break', [], 'dashboard'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Working Hours Settings Tab -->
        <div id="settings" class="tab-content<?php echo $active_tab === 'settings' ? ' active' : ''; ?>">
            <div class="settings-section">
                <h3 class="section-title"><?php echo __('schedule.settings_title', [], 'dashboard'); ?></h3>
                
                <form method="POST">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.working_days', [], 'dashboard'); ?></label>
                            <div class="days-selector">
                                <?php 
                                $days = [
                                    1 => __('schedule.day_monday', [], 'dashboard'),
                                    2 => __('schedule.day_tuesday', [], 'dashboard'),
                                    3 => __('schedule.day_wednesday', [], 'dashboard'),
                                    4 => __('schedule.day_thursday', [], 'dashboard'),
                                    5 => __('schedule.day_friday', [], 'dashboard'),
                                    6 => __('schedule.day_saturday', [], 'dashboard'),
                                    7 => __('schedule.day_sunday', [], 'dashboard')
                                ];
                                foreach ($days as $value => $label): ?>
                                    <div class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="<?php echo $value; ?>" 
                                               id="day<?php echo $value; ?>" 
                                               <?php echo in_array($value, $working_days) ? 'checked' : ''; ?>>
                                        <label for="day<?php echo $value; ?>"><?php echo $label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.start_time', [], 'dashboard'); ?></label>
                            <input type="time" class="form-control" name="start_time" 
                                   value="<?php echo $provider['working_hours_start'] ?: '08:00'; ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.end_time', [], 'dashboard'); ?></label>
                            <input type="time" class="form-control" name="end_time" 
                                   value="<?php echo $provider['working_hours_end'] ?: '17:00'; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.break_start', [], 'dashboard'); ?></label>
                            <input type="time" class="form-control" name="break_start" 
                                   value="<?php echo $provider['break_start'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.break_end', [], 'dashboard'); ?></label>
                            <input type="time" class="form-control" name="break_end" 
                                   value="<?php echo $provider['break_end'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.buffer_time', [], 'dashboard'); ?></label>
                            <input type="number" class="form-control" name="buffer_time" 
                                   value="<?php echo $provider['buffer_time'] ?: '15'; ?>" min="0" max="60" step="5">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('schedule.max_daily', [], 'dashboard'); ?></label>
                            <input type="number" class="form-control" name="max_bookings_per_day" 
                                   value="<?php echo $provider['max_daily_bookings'] ?: '8'; ?>" min="1" max="20">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_hours" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo __('schedule.save_working_hours', [], 'dashboard'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Availability Exceptions Tab -->
        <div id="availability" class="tab-content<?php echo $active_tab === 'availability' ? ' active' : ''; ?>">
            <div class="settings-section">
                <h3 class="section-title"><?php echo __('schedule.availability_title', [], 'dashboard'); ?></h3>
                
                <form method="POST">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-check-label" for="sync_bookings">
                                <?php echo __('schedule.sync_new_bookings', [], 'dashboard'); ?>
                            </label>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><?php echo __('schedule.start_time', [], 'dashboard'); ?> (<?php echo __('profile.optional', [], 'profile'); ?>)</label>
                            <input type="time" class="form-control" name="specific_start_time">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><?php echo __('schedule.end_time', [], 'dashboard'); ?> (<?php echo __('profile.optional', [], 'profile'); ?>)</label>
                            <input type="time" class="form-control" name="specific_end_time">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <label class="form-label"><?php echo __('schedule.notes', [], 'dashboard'); ?></label>
                            <input type="text" class="form-control" name="specific_notes" 
                                   placeholder="<?php echo __('schedule.notes_placeholder', [], 'dashboard'); ?>">
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       name="specific_is_available" id="specific_is_available" value="1" checked>
                                <label class="form-check-label" for="specific_is_available"><?php echo __('schedule.available', [], 'dashboard'); ?></label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_day_availability" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i> <?php echo __('schedule.update_day_availability', [], 'dashboard'); ?>
                    </button>
                </form>
                
                <!-- Bulk Update -->
                <div class="bulk-update-form">
                    <h5 class="mb-3"><?php echo __('schedule.bulk_update_title', [], 'dashboard'); ?></h5>
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label"><?php echo __('schedule.start_date', [], 'dashboard'); ?></label>
                                <input type="date" class="form-control" name="bulk_start_date" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label"><?php echo __('schedule.end_date', [], 'dashboard'); ?></label>
                                <input type="date" class="form-control" name="bulk_end_date" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label"><?php echo __('schedule.time_slot', [], 'dashboard'); ?></label>
                                <div class="input-group">
                                    <input type="time" class="form-control" name="bulk_start_time" placeholder="Start">
                                    <span class="input-group-text"><?php echo __('schedule.time_slot_to', [], 'dashboard'); ?></span>
                                    <input type="time" class="form-control" name="bulk_end_time" placeholder="End">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo __('schedule.days_of_week', [], 'dashboard'); ?></label>
                                <div class="days-selector">
                                    <?php foreach ($days as $value => $label): ?>
                                        <div class="day-checkbox">
                                            <input type="checkbox" name="bulk_days[]" value="<?php echo $value; ?>" 
                                                   id="bulk_day<?php echo $value; ?>">
                                            <label for="bulk_day<?php echo $value; ?>"><?php echo $label; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" 
                                           name="bulk_is_available" id="bulk_is_available" value="1" checked>
                                    <label class="form-check-label" for="bulk_is_available"><?php echo __('schedule.set_as_available', [], 'dashboard'); ?></label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="bulk_update_availability" class="btn btn-secondary">
                            <i class="fas fa-calendar-week me-2"></i> <?php echo __('schedule.apply_bulk_update', [], 'dashboard'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Availability Exceptions List -->
                <div class="mt-4">
                    <h5 class="mb-3"><?php echo __('schedule.upcoming_exceptions', [], 'dashboard'); ?></h5>
                    <?php if (empty($availability_exceptions)): ?>
                        <p class="text-muted"><?php echo __('schedule.no_exceptions', [], 'dashboard'); ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo __('schedule.exception_date', [], 'dashboard'); ?></th>
                                        <th><?php echo __('schedule.exception_status', [], 'dashboard'); ?></th>
                                        <th><?php echo __('schedule.exception_time_slot', [], 'dashboard'); ?></th>
                                        <th><?php echo __('schedule.notes', [], 'dashboard'); ?></th>
                                        <th><?php echo __('schedule.exception_actions', [], 'dashboard'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availability_exceptions as $exception): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($exception['date'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $exception['is_available'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $exception['is_available'] ? __('schedule.available', [], 'dashboard') : __('profile.unavailable', [], 'profile'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($exception['start_time'] && $exception['end_time']): ?>
                                                    <?php echo date('g:i A', strtotime($exception['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($exception['end_time'])); ?>
                                                <?php else: ?>
                                                    <?php echo __('schedule.exception_all_day', [], 'dashboard'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($exception['notes']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteAvailability(<?php echo $exception['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Time Off Tab -->
        <div id="timeoff" class="tab-content<?php echo $active_tab === 'timeoff' ? ' active' : ''; ?>">
            <div class="settings-section">
                <h3 class="section-title"><?php echo __('schedule.timeoff_title', [], 'dashboard'); ?></h3>
                
                <form method="POST" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label"><?php echo __('schedule.start_date', [], 'dashboard'); ?></label>
                            <input type="date" class="form-control" name="time_off_start" 
                                   value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label"><?php echo __('schedule.end_date', [], 'dashboard'); ?></label>
                            <input type="date" class="form-control" name="time_off_end" 
                                   value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><?php echo __('schedule.timeoff_reason', [], 'dashboard'); ?></label>
                            <input type="text" class="form-control" name="time_off_reason" 
                                   placeholder="<?php echo __('schedule.reason_placeholder', [], 'dashboard'); ?>" required>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="add_time_off" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i> <?php echo __('schedule.add_time_off', [], 'dashboard'); ?>
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Time Off List -->
                <h5 class="mb-3"><?php echo __('schedule.scheduled_timeoff', [], 'dashboard'); ?></h5>
                <?php if (empty($time_off_requests)): ?>
                    <p class="text-muted"><?php echo __('schedule.no_timeoff', [], 'dashboard'); ?></p>
                <?php else: ?>
                    <div class="time-off-list">
                        <?php foreach ($time_off_requests as $time_off): ?>
                            <div class="time-off-item">
                                <div>
                                    <div class="time-off-dates">
                                        <?php echo date('M d, Y', strtotime($time_off['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($time_off['end_date'])); ?>
                                    </div>
                                    <div class="time-off-reason">
                                        <?php echo htmlspecialchars($time_off['reason']); ?>
                                    </div>
                                </div>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('<?php echo __('schedule.delete_time_off_confirm', [], 'dashboard'); ?>')">
                                    <input type="hidden" name="time_off_id" value="<?php echo $time_off['id']; ?>">
                                    <button type="submit" name="delete_time_off" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Bookings Tab -->
        <div id="upcoming" class="tab-content<?php echo $active_tab === 'upcoming' ? ' active' : ''; ?>">
            <div class="settings-section">
                <h3 class="section-title"><?php echo __('schedule.bookings_title', [], 'dashboard'); ?></h3>
                
                <?php if (empty($upcoming_bookings)): ?>
                    <p class="text-center text-muted py-4"><?php echo __('schedule.no_bookings', [], 'dashboard'); ?></p>
                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($upcoming_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-info">
                                    <div class="booking-client">
                                        <div class="client-avatar">
                                            <?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($booking['client_name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($booking['client_phone']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="booking-datetime">
                                        <i class="far fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?>
                                        <?php if ($booking['preferred_time']): ?>
                                            <i class="far fa-clock ms-2 me-1"></i>
                                            <?php echo date('g:i A', strtotime($booking['preferred_time'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-service">
                                        <?php echo htmlspecialchars($booking['service_name'] ?? $booking['service_description']); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge <?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Integrations Tab -->
        <div id="integrations" class="tab-content<?php echo $active_tab === 'integrations' ? ' active' : ''; ?>">
            <div class="settings-section">
                <h3 class="section-title"><?php echo __('schedule.integrations_title', [], 'dashboard'); ?></h3>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="integration-card">
                            <div class="integration-icon">
                                <i class="fab fa-google"></i>
                            </div>
                            <h5><?php echo __('schedule.google_calendar_title', [], 'dashboard'); ?></h5>
                            <p class="text-muted mb-3"><?php echo __('schedule.google_calendar_desc', [], 'dashboard'); ?></p>
                            
                            <?php if ($google_authenticated): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong><?php echo __('schedule.google_connected', [], 'dashboard'); ?></strong>
                                    <br>
                                    <small>
                                        <?php echo __('schedule.connected_since', [], 'dashboard'); ?><?php echo isset($google_auth_status['authenticated_at']) ? date('M d, Y', strtotime($google_auth_status['authenticated_at'])) : 'N/A'; ?>
                                    </small>
                                </div>
                                
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="disconnect_google" class="btn btn-outline-danger"
                                            onclick="return confirm('<?php echo __('schedule.disconnect_google_confirm', [], 'dashboard'); ?>')">
                                        <i class="fas fa-unlink me-2"></i> <?php echo __('schedule.disconnect_google', [], 'dashboard'); ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted small mb-3"><?php echo __('schedule.google_not_connected', [], 'dashboard'); ?></p>
                                
                                <form method="POST">
                                    <input type="hidden" name="return_url" value="schedule.php?tab=integrations">
                                    <button type="submit" name="start_google_auth" class="btn btn-primary w-100">
                                        <i class="fab fa-google me-2"></i> <?php echo __('schedule.connect_google', [], 'dashboard'); ?>
                                    </button>
                                </form>
                                
                                <div class="alert alert-info mt-3" style="font-size: 0.85rem;">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong><?php echo __('profile.first_time', [], 'profile'); ?></strong> <?php echo __('schedule.google_auth_info', [], 'dashboard'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="integration-card">
                            <div class="integration-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h5><?php echo __('schedule.ical_title', [], 'dashboard'); ?></h5>
                            <p class="text-muted mb-3"><?php echo __('schedule.ical_desc', [], 'dashboard'); ?></p>
                            
                            <a href="schedule-export.php?format=ical" class="btn btn-success">
                                <i class="fas fa-download me-2"></i> <?php echo __('schedule.download_ical', [], 'dashboard'); ?>
                            </a>
                            <a href="schedule-export.php?format=outlook" class="btn btn-outline-primary ms-2">
                                <i class="fas fa-external-link-alt me-2"></i> <?php echo __('schedule.open_outlook', [], 'dashboard'); ?>
                            </a>
                            
                            <div class="mt-3">
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo __('schedule.calendar_feed_url', [], 'dashboard'); ?>
                                    <code>https://<?php echo $_SERVER['HTTP_HOST']; ?>/provider/calendar/<?php echo $provider['id']; ?>.ics</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sync Settings -->
                <div class="mt-4">
                    <h5 class="mb-3"><?php echo __('schedule.sync_settings_title', [], 'dashboard'); ?></h5>
                    <form>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sync_bookings" checked>
                                    <label class="form-check-label" for="sync_bookings">
                                        <?php echo __('schedule.sync_new_bookings', [], 'dashboard'); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sync_cancellations" checked>
                                    <label class="form-check-label" for="sync_cancellations">
                                        <?php echo __('schedule.sync_availability_changes', [], 'dashboard'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sync_time_off" checked>
                                    <label class="form-check-label" for="sync_time_off">
                                        <?php echo __('schedule.sync_time_off_periods', [], 'dashboard'); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('schedule.sync_frequency', [], 'dashboard'); ?></label>
                                <select class="form-select">
                                    <option value="15"><?php echo __('schedule.every_15_min', [], 'dashboard'); ?></option>
                                    <option value="30" selected><?php echo __('schedule.every_30_min', [], 'dashboard'); ?></option>
                                    <option value="60"><?php echo __('schedule.every_hour', [], 'dashboard'); ?></option>
                                    <option value="manual"><?php echo __('schedule.manual_sync', [], 'dashboard'); ?></option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync me-2"></i> <?php echo __('schedule.save_sync_settings', [], 'dashboard'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    
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
        
        // Tab navigation
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab
            const tab = document.getElementById(tabName);
            if (tab) tab.classList.add('active');

            // Activate matching button by data-tab
            const btn = document.querySelector('.tab-button[data-tab="' + tabName + '"]');
            if (btn) btn.classList.add('active');

            // Persist tab in URL (so links can open specific tab)
            try {
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                history.replaceState(null, '', url);
            } catch (e) {
                // ignore
            }
        }
        
        // Initialize FullCalendar and tabs
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs from URL param (if present)
            try {
                const params = new URLSearchParams(window.location.search);
                const initial = params.get('tab') || document.querySelector('.tab-button.active')?.dataset.tab || 'calendar';
                switchTab(initial);

                // Attach click handlers to ensure URL updates and consistent behavior
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const t = this.dataset.tab || this.getAttribute('data-tab');
                        if (t) switchTab(t);
                    });
                });
            } catch (e) {
                // ignore URL parsing errors
            }

            const calendarEl = document.getElementById('calendarEl');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    // Add booked dates
                    <?php foreach ($booked_dates as $date): ?>
                    {
                        title: 'Booked',
                        start: '<?php echo $date; ?>',
                        allDay: true,
                        color: 'var(--danger)'
                    },
                    <?php endforeach; ?>
                    
                    // Add time off periods
                    <?php foreach ($time_off_periods as $period): ?>
                    {
                        title: 'Time Off',
                        start: '<?php echo $period['start_date']; ?>',
                        end: '<?php echo date('Y-m-d', strtotime($period['end_date'] . ' +1 day')); ?>',
                        allDay: true,
                        color: 'var(--secondary)'
                    },
                    <?php endforeach; ?>
                    
                    // Add working hours (sample for today)
                    {
                        title: 'Available: <?php echo $provider['working_hours_start'] ?? "08:00"; ?> - <?php echo $provider['working_hours_end'] ?? "17:00"; ?>',
                        start: '<?php echo date('Y-m-d'); ?>T<?php echo $provider['working_hours_start'] ?? "08:00"; ?>',
                        end: '<?php echo date('Y-m-d'); ?>T<?php echo $provider['working_hours_end'] ?? "17:00"; ?>',
                        color: 'var(--success)'
                    }
                ],
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    alert('Event: ' + info.event.title);
                },
                dateClick: function(info) {
                    // Switch to availability tab and set date
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    
                    const availBtn = document.querySelector('.tab-button[data-tab="availability"]');
                    if (availBtn) availBtn.classList.add('active');
                    document.getElementById('availability').classList.add('active');
                    
                    // Set the date in the specific date field
                    document.querySelector('input[name="specific_date"]').value = info.dateStr;
                },
                businessHours: {
                    daysOfWeek: [<?php echo implode(',', $working_days); ?>],
                    startTime: '<?php echo $provider['working_hours_start'] ?? "08:00"; ?>',
                    endTime: '<?php echo $provider['working_hours_end'] ?? "17:00"; ?>'
                },
                height: 'auto',
                nowIndicator: true,
                navLinks: true,
                editable: false,
                selectable: false,
                selectMirror: true,
                dayMaxEvents: true,
                weekends: <?php echo in_array(6, $working_days) || in_array(7, $working_days) ? 'true' : 'false'; ?>,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: 'short'
                }
            });
            
            calendar.render();
        });
        
        // Delete availability function
        function deleteAvailability(id) {
            if (confirm(<?php echo json_encode(__('schedule.delete_availability_confirm', [], 'dashboard')); ?>)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_availability';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Form validation for time off
        document.querySelector('form[name="add_time_off"]')?.addEventListener('submit', function(e) {
            const startDate = new Date(this.querySelector('input[name="time_off_start"]').value);
            const endDate = new Date(this.querySelector('input[name="time_off_end"]').value);
            
            if (endDate < startDate) {
                e.preventDefault();
                alert(<?php echo json_encode(__('schedule.time_off_validate_error', [], 'dashboard')); ?>);
                return false;
            }
            
            // Check if time off overlaps with existing bookings
            const reason = this.querySelector('input[name="time_off_reason"]').value.trim();
            if (!reason) {
                e.preventDefault();
                alert(<?php echo json_encode(__('schedule.provide_reason_error', [], 'dashboard')); ?>);
                return false;
            }
        });
        
        // Toggle bulk days based on working days
        document.addEventListener('DOMContentLoaded', function() {
            const workingDays = [<?php echo implode(',', $working_days); ?>];
            const bulkDayCheckboxes = document.querySelectorAll('input[name="bulk_days[]"]');
            
            // Pre-select working days in bulk form
            bulkDayCheckboxes.forEach(checkbox => {
                if (workingDays.includes(parseInt(checkbox.value))) {
                    checkbox.checked = true;
                }
            });
        });
    </script>
</body>
</html>
