<?php
// Ensure we have a DB connection and fetch platform name from system_settings
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $db = Database::getInstance()->getConnection();
}

$platform_name = 'BII LocalFinder';
try {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'platform_name' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false && trim($val) !== '') {
        $platform_name = $val;
    }
} catch (Exception $e) {
    // fallback to default name on error
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2><?php echo $platform_name; ?></h2>
        <p>Admin Panel</p>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        
        <li>
            <a href="providers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'providers.php' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i>
                <span>Providers</span>
            </a>
        </li>

         <li>
            <a href="verifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-circle-check"></i>
                <span>Verifications</span>
            </a>
        </li>
        
        <li>
            <a href="bookings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Bookings</span>
            </a>
        </li>
        
        <li>
            <a href="complaints.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'complaints.php' ? 'active' : ''; ?>">
                <i class="fas fa-flag"></i>
                <span>Complaints</span>
            </a>
        </li>
        
        <li>
            <a href="categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Categories</span>
            </a>
        </li>
        
        <li>
            <a href="notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
        </li>
        
        <li>
            <a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
        </li>
        
        <li>
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
/* Sidebar Scrolling Fix */
.sidebar {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    padding-right: 5px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 6px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}

.sidebar-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding: 1rem 0;
    margin-top: auto;
}

.sidebar-footer a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    padding: 0.8rem 1.5rem;
    display: flex;
    align-items: center;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.sidebar-footer a:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    border-left-color: white;
}

.sidebar-footer i {
    width: 25px;
    margin-right: 10px;
    font-size: 1.1rem;
}
</style>