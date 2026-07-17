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
    <!-- Dark Mode CSS -->
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --accent:        #0d6efd;
            --accent-dark:   #0a58ca;
            --accent-light:  #eff4ff;
            --success:       #16a34a;
            --success-light: #f0fdf4;
            --danger:        #dc2626;
            --danger-light:  #fef2f2;
            --warning:       #d97706;
            --warning-light: #fffbeb;
            --info:          #0891b2;
            --info-light:    #ecfeff;
            --surface:       #ffffff;
            --surface-2:     #f7f8fc;
            --border:        #e8eaf0;
            --border-subtle: #f0f2f7;
            --text-primary:  #0f1117;
            --text-secondary:#6b7280;
            --text-muted:    #9ca3af;
            --sidebar-width: 260px;
            --radius-sm:     8px;
            --radius-md:     12px;
            --radius-lg:     16px;
            --radius-xl:     20px;
            --shadow-xs:     0 1px 3px rgba(0,0,0,0.06);
            --shadow-sm:     0 2px 8px rgba(0,0,0,0.07);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.09);
            --transition:    all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --accent:        #3b82f6;
            --accent-dark:   #2563eb;
            --accent-light:  #1e3a8a;
            --success:       #10b981;
            --success-light: #064e3b;
            --danger:        #ef4444;
            --danger-light:  #7f1d1d;
            --warning:       #f59e0b;
            --warning-light: #78350f;
            --info:          #06b6d4;
            --info-light:    #164e63;
            --surface:       #0f172a;
            --surface-2:     #1e293b;
            --border:        #334155;
            --border-subtle: #475569;
            --text-primary:  #f8fafc;
            --text-secondary:#cbd5e1;
            --text-muted:    #94a3b8;
            --shadow-xs:     0 1px 3px rgba(0,0,0,0.3);
            --shadow-sm:     0 2px 8px rgba(0,0,0,0.4);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.5);
        }

        body {
            background: var(--surface-2);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: fixed; height: 100vh; left: 0; top: 0;
            transition: var(--transition); z-index: 1000;
        }
        .sidebar-header { padding: 1.5rem 1.25rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
        .sidebar-header h2 { margin: 0; font-weight: 800; font-size: 1.1rem; color: var(--accent); }
        .sidebar-header p  { margin: 0.3rem 0 0; color: var(--text-muted); font-size: 0.78rem; }
        .sidebar-menu { list-style: none; padding: 0.75rem; margin: 0; }
        .sidebar-menu li { margin: 2px 0; }
        .sidebar-menu a {
            color: var(--text-secondary); text-decoration: none;
            padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.65rem;
            transition: var(--transition); border-radius: var(--radius-sm);
            font-size: 0.875rem; font-weight: 500;
        }
        .sidebar-menu a:hover { background: var(--accent-light); color: var(--accent); }
        .sidebar-menu a.active { background: var(--accent); color: white; font-weight: 600; }
        .sidebar-menu i { width: 18px; font-size: 0.9rem; flex-shrink: 0; }

        /* ── MAIN ── */
        .main-content { margin-left: var(--sidebar-width); padding: 1.75rem 2rem; min-height: 100vh; }

        /* ── ALERTS ── */
        .alert {
            border-radius: var(--radius-md); border: 1px solid transparent;
            padding: 0.875rem 1.125rem; margin-bottom: 1.25rem; font-size: 0.875rem;
        }
        .alert-success { background: var(--success-light); color: var(--success); border-color: #bbf7d0; }
        .alert-danger  { background: var(--danger-light);  color: var(--danger);  border-color: #fecaca; }
        .alert-warning, .maintenance-warning { background: var(--warning-light); color: var(--warning); border-color: #fde68a; }

        /* ── PAGE HEADER ── */
        .page-header {
            background: var(--surface); border-radius: var(--radius-lg);
            padding: 1.25rem 1.75rem; margin-bottom: 1.5rem;
            border: 1px solid var(--border); box-shadow: var(--shadow-xs);
        }
        .page-header h1 {
            color: var(--text-primary); margin: 0 0 0.2rem; font-weight: 800;
            font-size: 1.4rem; letter-spacing: -0.4px;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .page-header h1 i { color: var(--accent); font-size: 1.1rem; }
        .page-header p { color: var(--text-muted); margin: 0; font-size: 0.82rem; }

        /* ── STAT CARDS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.125rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--surface); border-radius: var(--radius-lg);
            padding: 1.375rem 1.5rem; box-shadow: var(--shadow-xs);
            border: 1px solid var(--border); position: relative; overflow: hidden;
            text-decoration: none !important; color: inherit; transition: var(--transition);
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: var(--accent); border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); text-decoration: none; color: inherit; }
        .stat-icon {
            width: 44px; height: 44px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 0.875rem; font-size: 1.1rem;
        }
        .stat-card h3 { font-size: 1.85rem; font-weight: 800; margin: 0 0 0.2rem; color: var(--text-primary); letter-spacing: -1px; font-variant-numeric: tabular-nums; }
        .stat-card p  { color: var(--text-secondary); margin: 0; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }

        /* ── TABS ── */
        .tabs-navigation {
            display: flex; gap: 0.25rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            background: var(--surface); padding: 0.3rem;
            border-radius: var(--radius-md); border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
        }
        .tab-button {
            padding: 0.55rem 1.1rem; background: transparent;
            border: none; border-radius: var(--radius-sm);
            cursor: pointer; font-weight: 600; transition: var(--transition);
            color: var(--text-secondary); font-size: 0.82rem; font-family: inherit;
            display: flex; align-items: center; gap: 0.4rem;
            text-decoration: none;
        }
        .tab-button:hover { color: var(--accent); background: var(--accent-light); text-decoration: none; }
        .tab-button.active { background: var(--accent); color: white; }
        .tab-button i { font-size: 0.78rem; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: secIn 0.2s ease; }
        @keyframes secIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ── SECTION CARDS ── */
        .settings-section {
            background: var(--surface); padding: 1.625rem 1.75rem;
            border-radius: var(--radius-lg); box-shadow: var(--shadow-xs);
            border: 1px solid var(--border); margin-bottom: 1.375rem;
        }
        .section-title {
            font-size: 0.975rem; font-weight: 800; color: var(--text-primary);
            margin-bottom: 1.25rem; padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex; align-items: center; gap: 0.5rem; letter-spacing: -0.2px;
        }
        .section-title i { color: var(--accent); font-size: 0.875rem; }

        /* ── CALENDAR ── */
        .calendar-container {
            background: var(--surface); padding: 1.625rem 1.75rem;
            border-radius: var(--radius-lg); box-shadow: var(--shadow-xs);
            border: 1px solid var(--border); margin-bottom: 1.375rem;
        }
        #calendarEl { max-width: 100%; }
        .fc-event { cursor: pointer; border-radius: var(--radius-sm) !important; font-size: 0.72rem !important; }
        .fc .fc-toolbar-title { font-size: 1rem !important; font-weight: 800 !important; }
        .fc .fc-button { font-size: 0.78rem !important; font-weight: 600 !important; }

        .time-slot-legend {
            display: flex; gap: 1.125rem; flex-wrap: wrap; margin-top: 1.25rem;
            padding: 0.875rem 1.125rem; background: var(--surface-2);
            border-radius: var(--radius-md); border: 1px solid var(--border);
        }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--text-secondary); font-weight: 600; }
        .legend-color { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; }
        .legend-booked    { background: var(--danger); }
        .legend-available { background: var(--success); }
        .legend-time-off  { background: var(--text-muted); }
        .legend-break     { background: var(--warning); }

        /* ── BOOKING LIST ── */
        .booking-list { max-height: 520px; overflow-y: auto; }
        .booking-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 0; border-bottom: 1px solid var(--border-subtle);
            transition: var(--transition); gap: 1rem;
        }
        .booking-item:last-child { border-bottom: none; }
        .booking-item:hover { background: transparent; }
        .booking-info { flex: 1; min-width: 0; }
        .booking-client { display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.375rem; }
        .client-avatar {
            width: 38px; height: 38px; border-radius: var(--radius-sm);
            background: var(--accent); display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; overflow: hidden;
        }
        .client-avatar img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; }
        .booking-datetime { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem; }
        .booking-service  { font-size: 0.78rem; color: var(--text-muted); }

        /* Status badges */
        .badge {
            padding: 0.25rem 0.6rem; border-radius: 100px;
            font-size: 0.68rem; font-weight: 700;
        }
        .badge.confirmed, .badge-confirmed { background: var(--info-light); color: var(--info); border: 1px solid #a5f3fc; }
        .badge.pending,   .badge-pending   { background: var(--warning-light); color: var(--warning); border: 1px solid #fde68a; }
        .bg-success { background: var(--success-light) !important; color: var(--success) !important; border: 1px solid #bbf7d0; }
        .bg-danger  { background: var(--danger-light)  !important; color: var(--danger)  !important; border: 1px solid #fecaca; }

        /* ── TIME OFF LIST ── */
        .time-off-list { max-height: 320px; overflow-y: auto; }
        .time-off-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.875rem 0; border-bottom: 1px solid var(--border-subtle);
        }
        .time-off-item:last-child { border-bottom: none; }
        .time-off-dates { font-weight: 700; font-size: 0.875rem; color: var(--text-primary); }
        .time-off-reason { color: var(--text-muted); font-size: 0.78rem; margin-top: 0.2rem; }

        /* ── DAYS SELECTOR ── */
        .days-selector { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .day-checkbox { position: relative; }
        .day-checkbox input { position: absolute; opacity: 0; }
        .day-checkbox label {
            display: flex; align-items: center; justify-content: center;
            width: 42px; height: 42px;
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            cursor: pointer; font-weight: 700; font-size: 0.78rem;
            transition: var(--transition); color: var(--text-secondary);
            background: var(--surface-2);
        }
        .day-checkbox label:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
        .day-checkbox input:checked + label { background: var(--accent); color: white; border-color: var(--accent); }

        /* ── FORMS ── */
        .form-label { font-weight: 600; margin-bottom: 0.35rem; color: var(--text-primary); font-size: 0.8rem; display: block; }
        .form-control, .form-select {
            padding: 0.575rem 0.875rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border); font-family: inherit; font-size: 0.875rem;
            color: var(--text-primary); background: var(--surface-2); transition: var(--transition);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); background: var(--surface);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.08); outline: none;
        }
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }

        /* Save button */
        .btn-save {
            background: var(--accent); color: white; border: none;
            padding: 0.6rem 1.375rem; border-radius: var(--radius-sm);
            font-family: inherit; font-size: 0.875rem; font-weight: 700;
            cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .btn-save:hover { background: var(--accent-dark); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .btn-save.secondary { background: var(--surface); color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-save.secondary:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }

        /* Bootstrap btn shims */
        .btn { font-family: inherit; font-weight: 600; border-radius: var(--radius-sm); transition: var(--transition); font-size: 0.82rem; }
        .btn-primary { background: var(--accent); color: white; border-color: var(--accent); }
        .btn-primary:hover { background: var(--accent-dark); color: white; transform: translateY(-1px); }
        .btn-secondary { background: var(--surface); color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-secondary:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
        .btn-sm { font-size: 0.72rem; padding: 0.32rem 0.7rem; }
        .btn-outline-danger { color: var(--danger); border: 1px solid #fecaca; background: transparent; }
        .btn-outline-danger:hover { background: var(--danger-light); color: var(--danger); }

        /* ── BULK UPDATE PANEL ── */
        .bulk-update-form {
            background: var(--surface-2); padding: 1.375rem;
            border-radius: var(--radius-md); margin-top: 1.5rem;
            border: 1px solid var(--border);
        }
        .bulk-update-form h5 { font-size: 0.875rem; font-weight: 800; color: var(--text-primary); margin-bottom: 1rem; }

        /* ── INTEGRATION CARDS ── */
        .integration-card {
            border: 1px dashed var(--border); border-radius: var(--radius-lg);
            padding: 2rem; text-align: center; transition: var(--transition);
        }
        .integration-card:hover { border-color: var(--accent); background: var(--accent-light); }
        .integration-icon {
            width: 64px; height: 64px; border-radius: var(--radius-lg);
            background: var(--accent); color: white;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem; font-size: 1.75rem;
            box-shadow: 0 4px 14px rgba(13,110,253,0.25);
        }

        /* ── TABLE ── */
        .table { font-size: 0.82rem; }
        .table th { font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); }
        .table td { padding: 0.6rem 0.75rem; color: var(--text-primary); vertical-align: middle; border-bottom: 1px solid var(--border-subtle); }
        .table-hover tbody tr:hover td { background: var(--surface-2); }

        /* ── MOBILE ── */
        .mobile-menu-toggle {
            display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1100;
            background: var(--accent); color: white; border: none;
            border-radius: var(--radius-sm); width: 42px; height: 42px;
            align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-md);
        }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; backdrop-filter: blur(2px); }
        .overlay.active { display: block; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.12); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .tabs-navigation { width: 100%; overflow-x: auto; }
            .booking-item { flex-direction: column; align-items: flex-start; }
        }
        ::-webkit-scrollbar { width: 4px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
    </style>
</head>
<body>
    <script>
        // Initialize theme from localStorage
        (function() {
            const theme = localStorage.getItem('provider_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
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
                <div class="stat-icon" style="background:#e0e7ff;color:#4f46e5;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $stats['total_upcoming'] ?? 0; ?></h3>
                <p><?php echo __('schedule.upcoming_bookings', [], 'dashboard'); ?></p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                <p><?php echo __('schedule.pending_approvals', [], 'dashboard'); ?></p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $stats['confirmed'] ?? 0; ?></h3>
                <p><?php echo __('schedule.confirmed_bookings', [], 'dashboard'); ?></p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#d1fae5;color:#16a34a;">
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
            <a href="?tab=calendar" class="tab-button<?php echo $active_tab === 'calendar' ? ' active' : ''; ?>" data-tab="calendar"><i class="fas fa-calendar-alt"></i> <?php echo __('schedule.tab_calendar', [], 'dashboard'); ?></a>
            <a href="?tab=settings" class="tab-button<?php echo $active_tab === 'settings' ? ' active' : ''; ?>" data-tab="settings"><i class="fas fa-cog"></i> <?php echo __('schedule.tab_settings', [], 'dashboard'); ?></a>
            <a href="?tab=availability" class="tab-button<?php echo $active_tab === 'availability' ? ' active' : ''; ?>" data-tab="availability"><i class="fas fa-toggle-on"></i> <?php echo __('schedule.tab_availability', [], 'dashboard'); ?></a>
            <a href="?tab=timeoff" class="tab-button<?php echo $active_tab === 'timeoff' ? ' active' : ''; ?>" data-tab="timeoff"><i class="fas fa-umbrella-beach"></i> <?php echo __('schedule.tab_timeoff', [], 'dashboard'); ?></a>
            <a href="?tab=upcoming" class="tab-button<?php echo $active_tab === 'upcoming' ? ' active' : ''; ?>" data-tab="upcoming"><i class="fas fa-list"></i> <?php echo __('schedule.tab_bookings', [], 'dashboard'); ?></a>
            <a href="?tab=integrations" class="tab-button<?php echo $active_tab === 'integrations' ? ' active' : ''; ?>" data-tab="integrations"><i class="fas fa-plug"></i> <?php echo __('schedule.tab_integrations', [], 'dashboard'); ?></a>
        </div>

        <!-- Calendar View Tab -->
        <div id="calendar" class="tab-content<?php echo $active_tab === 'calendar' ? ' active' : ''; ?>">
            <div class="calendar-container">
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> <?php echo __('schedule.calendar_overview', [], 'dashboard'); ?></h3>
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
                <h3 class="section-title"><i class="fas fa-cog"></i> <?php echo __('schedule.settings_title', [], 'dashboard'); ?></h3>
                
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
                    
                    <button type="submit" name="update_hours" class="btn-save"><i class="fas fa-save"></i> <?php echo __('schedule.save_working_hours', [], 'dashboard'); ?></button>
                </form>
            </div>
        </div>

        <!-- Availability Exceptions Tab -->
        <div id="availability" class="tab-content<?php echo $active_tab === 'availability' ? ' active' : ''; ?>">
            <div class="settings-section">
                <h3 class="section-title"><i class="fas fa-toggle-on"></i> <?php echo __('schedule.availability_title', [], 'dashboard'); ?></h3>
                
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
                    
                    <button type="submit" name="update_day_availability" class="btn-save"><i class="fas fa-calendar-plus"></i> <?php echo __('schedule.update_day_availability', [], 'dashboard'); ?></button>
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
                        
                        <button type="submit" name="bulk_update_availability" class="btn-save secondary"><i class="fas fa-calendar-week"></i> <?php echo __('schedule.apply_bulk_update', [], 'dashboard'); ?></button>
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
                <h3 class="section-title"><i class="fas fa-umbrella-beach"></i> <?php echo __('schedule.timeoff_title', [], 'dashboard'); ?></h3>
                
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
                            <button type="submit" name="add_time_off" class="btn-save" style="width:100%;">
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
                                <form method="POST" class="d-inline" 
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
                <h3 class="section-title"><i class="fas fa-list"></i> <?php echo __('schedule.bookings_title', [], 'dashboard'); ?></h3>
                
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
                <h3 class="section-title"><i class="fas fa-plug"></i> <?php echo __('schedule.integrations_title', [], 'dashboard'); ?></h3>
                
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
                                
                                <form method="POST" class="d-inline">
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
                                
                                <div class="alert alert-info mt-3" >
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
        
        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            });
        }
        if (overlay) {
            overlay.addEventListener('click', () => {
                if (sidebar) sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
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
        
        setTimeout(() => {
            document.querySelectorAll('.alert.alert-dismissible').forEach(el => {
                try { new bootstrap.Alert(el).close(); } catch(e) {}
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