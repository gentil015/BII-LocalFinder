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

// Helper function to fetch counts
function fetchCount($db, $query, $params = []) {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Get system settings
function getSystemSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

// Fetch platform settings
$platform_settings = [
    'platform_name' => getSystemSetting($db, 'platform_name', 'BII LocalFinder'),
    'maintenance_mode' => getSystemSetting($db, 'maintenance_mode', '0'),
    'client_registration' => getSystemSetting($db, 'client_registration', '1'),
    'provider_registration' => getSystemSetting($db, 'provider_registration', '1')
];

// Fetch data for overview
$stats = [
    'total_users' => fetchCount($db, "SELECT COUNT(*) FROM users", []),
    'total_clients' => fetchCount($db, "SELECT COUNT(*) FROM users WHERE user_type = ?", ['client']),
    'total_providers' => fetchCount($db, "SELECT COUNT(*) FROM users WHERE user_type = ?", ['provider']),
    'active_users' => fetchCount($db, "SELECT COUNT(*) FROM users WHERE is_verified = ?", [1]),
    'pending_users' => fetchCount($db, "SELECT COUNT(*) FROM users WHERE is_verified = ?", [0]),
    'total_bookings' => fetchCount($db, "SELECT COUNT(*) FROM bookings", []),
    'pending_bookings' => fetchCount($db, "SELECT COUNT(*) FROM bookings WHERE status = ?", ['pending']),
    'completed_bookings' => fetchCount($db, "SELECT COUNT(*) FROM bookings WHERE status = ?", ['completed']),
    'cancelled_bookings' => fetchCount($db, "SELECT COUNT(*) FROM bookings WHERE status = ?", ['cancelled']),
    'total_reviews' => fetchCount($db, "SELECT COUNT(*) FROM reviews", []),
    'pending_reports' => fetchCount($db, "SELECT COUNT(*) FROM reports WHERE status = ?", ['pending']),
    'total_categories' => fetchCount($db, "SELECT COUNT(*) FROM categories WHERE is_active = ?", [1]),
    'featured_providers' => fetchCount($db, "SELECT COUNT(*) FROM service_providers WHERE is_featured = ?", [1]),
    'banned_providers' => fetchCount($db, "SELECT COUNT(*) FROM service_providers WHERE is_banned = ?", [1]),
];

// Recent activity
$recent_users = $db->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 6
")->fetchAll();

// Recent bookings
$recent_bookings = $db->query("
    SELECT b.*, u.full_name as client_name, sp.profession, u2.full_name as provider_name
    FROM bookings b
    JOIN users u ON b.client_id = u.id
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN users u2 ON sp.user_id = u2.id
    ORDER BY b.created_at DESC 
    LIMIT 5
")->fetchAll();

// Top providers
$top_providers = $db->query("
    SELECT u.full_name, sp.profession, sp.average_rating, sp.total_reviews, sp.location, sp.verification_level
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE u.is_verified = 1 AND sp.is_banned = 0
    ORDER BY sp.average_rating DESC, sp.total_reviews DESC
    LIMIT 5
")->fetchAll();

// System health check
$system_health = [
    'database_size' => $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn(),
    'active_sessions' => fetchCount($db, "SELECT COUNT(*) FROM sessions WHERE last_activity > ?", [time() - 3600]),
    'pending_tasks' => fetchCount($db, "SELECT COUNT(*) FROM bookings WHERE status = ?", ['pending']),
    'unread_messages' => fetchCount($db, "SELECT COUNT(*) FROM messages WHERE is_read = ?", [0]),
];

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_maintenance'])) {
        $new_mode = $platform_settings['maintenance_mode'] === '1' ? '0' : '1';
        try {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('maintenance_mode', ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$new_mode, $new_mode]);
            $platform_settings['maintenance_mode'] = $new_mode;
            $success = "Maintenance mode " . ($new_mode === '1' ? 'enabled' : 'disabled');
        } catch (Exception $e) {
            $errors[] = "Failed to update maintenance mode";
        }
    }
    
    if (isset($_POST['clear_cache'])) {
        try {
            $cache_files = glob('../cache/*.cache');
            foreach ($cache_files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $success = "Cache cleared successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to clear cache";
        }
    }
}

// Calculate growth percentages
$growth = [
    'users' => calculateGrowth($db, 'users', 'created_at'),
    'bookings' => calculateGrowth($db, 'bookings', 'created_at'),
    'revenue' => 0 // You can implement revenue tracking
];

function calculateGrowth($db, $table, $date_column) {
    $current_month = $db->query("SELECT COUNT(*) FROM $table WHERE $date_column >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $previous_month = $db->query("SELECT COUNT(*) FROM $table WHERE $date_column BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    
    if ($previous_month > 0) {
        return round((($current_month - $previous_month) / $previous_month) * 100, 1);
    }
    return $current_month > 0 ? 100 : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($platform_settings['platform_name']); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
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
        
        .growth-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
        }
        
        .growth-positive {
            background: #d1fae5;
            color: #065f46;
        }
        
        .growth-negative {
            background: #fecaca;
            color: #991b1b;
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
        
        /* System Health */
        .system-health {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .health-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid var(--success);
        }
        
        .health-item.warning {
            border-left-color: var(--warning);
        }
        
        .health-item.danger {
            border-left-color: var(--danger);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: var(--dark);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge.client {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge.provider {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.featured {
            background: #fecaca;
            color: #991b1b;
        }
        
        .badge.banned {
            background: #374151;
            color: white;
        }
        
        /* Verification Badges */
        .verification-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            margin-left: 0.3rem;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-gold {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-premium {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        /* Status Badges */
        .status-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-cancelled {
            background: #fecaca;
            color: #991b1b;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .system-health {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
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
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .maintenance-alert {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
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
                        <h1><i class="fas fa-chart-line me-2"></i> Dashboard Overview</h1>
                        <p>Welcome back! Here's what's happening with <?php echo htmlspecialchars($platform_settings['platform_name']); ?></p>
                    </div>
                    <div class="quick-actions">
                        <form method="POST" class="d-inline">
                            <button type="submit" name="clear_cache" class="btn btn-outline-secondary">
                                <i class="fas fa-broom me-2"></i> Clear Cache
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="toggle_maintenance" class="btn btn-<?php echo $platform_settings['maintenance_mode'] === '1' ? 'success' : 'warning'; ?>">
                                <i class="fas fa-tools me-2"></i>
                                <?php echo $platform_settings['maintenance_mode'] === '1' ? 'Disable Maintenance' : 'Enable Maintenance'; ?>
                            </button>
                        </form>
                        <a href="settings.php" class="btn btn-primary">
                            <i class="fas fa-cog me-2"></i> System Settings
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($platform_settings['maintenance_mode'] === '1'): ?>
                <div class="alert maintenance-alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Maintenance Mode is Active</strong> - Your platform is currently in maintenance mode and may not be accessible to regular users.
                </div>
            <?php endif; ?>

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

            <!-- System Health -->
            <div class="system-health">
                <div class="health-item <?php echo $system_health['database_size'] > 100 ? 'warning' : ''; ?>">
                    <div class="stat-value"><?php echo $system_health['database_size']; ?> MB</div>
                    <div class="stat-label">Database Size</div>
                </div>
                <div class="health-item">
                    <div class="stat-value"><?php echo $system_health['active_sessions']; ?></div>
                    <div class="stat-label">Active Sessions</div>
                </div>
                <div class="health-item <?php echo $system_health['pending_tasks'] > 10 ? 'warning' : ''; ?>">
                    <div class="stat-value"><?php echo $system_health['pending_tasks']; ?></div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
                <div class="health-item <?php echo $system_health['unread_messages'] > 5 ? 'warning' : ''; ?>">
                    <div class="stat-value"><?php echo $system_health['unread_messages']; ?></div>
                    <div class="stat-label">Unread Messages</div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p>Total Users</p>
                    <?php if ($growth['users'] != 0): ?>
                        <span class="growth-badge <?php echo $growth['users'] >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                            <i class="fas fa-<?php echo $growth['users'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs($growth['users']); ?>%
                        </span>
                    <?php endif; ?>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3><?php echo $stats['total_clients']; ?></h3>
                    <p>Clients</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                        <i class="fas fa-toolbox"></i>
                    </div>
                    <h3><?php echo $stats['total_providers']; ?></h3>
                    <p>Service Providers</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3><?php echo $stats['pending_bookings']; ?></h3>
                    <p>Pending Bookings</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #fecaca; color: #991b1b;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3><?php echo $stats['total_bookings']; ?></h3>
                    <p>Total Bookings</p>
                    <?php if ($growth['bookings'] != 0): ?>
                        <span class="growth-badge <?php echo $growth['bookings'] >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                            <i class="fas fa-<?php echo $growth['bookings'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs($growth['bookings']); ?>%
                        </span>
                    <?php endif; ?>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #fed7aa; color: #9a3412;">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3><?php echo $stats['total_reviews']; ?></h3>
                    <p>Total Reviews</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #e9d5ff; color: #6b21a8;">
                        <i class="fas fa-flag"></i>
                    </div>
                    <h3><?php echo $stats['pending_reports']; ?></h3>
                    <p>Pending Reports</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #bfdbfe; color: #1e40af;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h3><?php echo $stats['completed_bookings']; ?></h3>
                    <p>Completed Jobs</p>
                </div>
            </div>

            <!-- Recent Activity Row -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <h3><i class="fas fa-users me-2"></i> Recent User Registrations</h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_users)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                                No users found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $user['user_type']; ?>">
                                                        <?php echo ucfirst($user['user_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $user['is_verified'] ? 'verified' : 'pending'; ?>">
                                                        <?php echo $user['is_verified'] ? 'Verified' : 'Pending'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($user['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <h3><i class="fas fa-calendar-alt me-2"></i> Recent Bookings</h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Provider</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_bookings)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-calendar fa-2x mb-2 d-block"></i>
                                                No bookings found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['client_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($booking['provider_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['profession']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></small>
                                                <br>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($booking['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <h3><i class="fas fa-trophy me-2"></i> Top Rated Providers</h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Profession</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($top_providers)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-toolbox fa-2x mb-2 d-block"></i>
                                                No providers found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($top_providers as $provider): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($provider['full_name']); ?></strong>
                                                    <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                                                        <span class="badge-<?php echo $provider['verification_level']; ?> verification-badge">
                                                            <?php echo strtoupper($provider['verification_level']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($provider['profession']); ?></td>
                                                <td>
                                                    <span class="text-warning">
                                                        <?php echo number_format($provider['average_rating'], 1); ?> 
                                                        <i class="fas fa-star"></i>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">(<?php echo $provider['total_reviews']; ?> reviews)</small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($provider['location']); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <h3><i class="fas fa-chart-pie me-2"></i> Platform Analytics</h3>
                        <div class="row text-center p-3">
                            <div class="col-6">
                                <canvas id="userDistributionChart" width="200" height="200"></canvas>
                            </div>
                            <div class="col-6">
                                <canvas id="bookingStatusChart" width="200" height="200"></canvas>
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

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // User Distribution Chart
            const userCtx = document.getElementById('userDistributionChart').getContext('2d');
            const userChart = new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Clients', 'Providers'],
                    datasets: [{
                        data: [<?php echo $stats['total_clients']; ?>, <?php echo $stats['total_providers']; ?>],
                        backgroundColor: ['#4f46e5', '#10b981'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Booking Status Chart
            const bookingCtx = document.getElementById('bookingStatusChart').getContext('2d');
            const bookingChart = new Chart(bookingCtx, {
                type: 'pie',
                data: {
                    labels: ['Pending', 'Completed', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $stats['pending_bookings']; ?>,
                            <?php echo $stats['completed_bookings']; ?>,
                            <?php echo $stats['cancelled_bookings']; ?>
                        ],
                        backgroundColor: ['#ffc107', '#198754', '#dc3545'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>