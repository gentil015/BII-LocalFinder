<?php
// Ensure DB connection and fetch platform name from system_settings
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $db = Database::getInstance()->getConnection();
}

// Get current provider ID from session
$provider_id = $_SESSION['user_id'] ?? null;

$platform_name = 'BII LocalFinder';
try {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'platform_name' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false && trim($val) !== '') {
        $platform_name = $val;
    }
} catch (Exception $e) {
    // fallback to default on error
}

// Function to get notification counts
function getNotificationCounts($db, $provider_id) {
    $counts = [
        'bookings' => 0,
        'complaints' => 0,
        'reviews' => 0,
        'notifications' => 0
    ];
    
    if (!$provider_id) {
        return $counts;
    }
    
    try {
        // Count pending bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE provider_id = ? AND status = 'pending'");
        $stmt->execute([$provider_id]);
        $result = $stmt->fetch();
        $counts['bookings'] = $result['count'] ?? 0;
        
        // Count unread complaints
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM complaints WHERE provider_id = ? AND status = 'open'");
        $stmt->execute([$provider_id]);
        $result = $stmt->fetch();
        $counts['complaints'] = $result['count'] ?? 0;
        
        // Count new reviews (not yet responded to)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM reviews WHERE provider_id = ? AND is_new = 1");
        $stmt->execute([$provider_id]);
        $result = $stmt->fetch();
        $counts['reviews'] = $result['count'] ?? 0;
        
        // Count new notifications from unified notification system
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$provider_id]);
        $result = $stmt->fetch();
        $counts['notifications'] = $result['count'] ?? 0;
        
    } catch (Exception $e) {
        // Silently fail and return zero counts
    }
    
    return $counts;
}

$notificationCounts = getNotificationCounts($db, $provider_id);
$current = basename($_SERVER['PHP_SELF']);

// determine current settings subsection for label
$settings_section = isset($_GET['section']) ? sanitize($_GET['section']) : 'identity';
$settings_labels = [
    'identity' => 'Identity',
    'visibility' => 'Visibility',
    'pricing' => 'Pricing',
    'availability' => 'Availability',
    'location' => 'Location',
    'ai' => 'AI Features',
    'payment' => 'Payment',
    'communication' => 'Communication',
    'language' => 'Language',
    'notifications' => 'Notifications',
    'reviews' => 'Reviews',
    'security' => 'Security',
    'analytics' => 'Analytics',
    'account' => 'Account',
    'requirements' => 'Requirements'
];

// Show both icon and text on all pages; disable submenu entirely
$iconOnly = false;
$hasSub = false;
?>
<aside class="sidebar<?php echo $iconOnly ? ' icon-only' : ''; ?><?php echo $hasSub ? ' has-submenu' : ''; ?>" id="providerSidebar">
    <div class="sidebar-header">
        <h2 title="<?php echo htmlspecialchars($platform_name); ?>"><?php echo htmlspecialchars(substr($platform_name,0,1)); ?></h2>
        <p>Panel</p>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" title="Dashboard">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
                <?php if ($notificationCounts['notifications'] > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCounts['notifications']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="services.php" class="<?php echo $current === 'services.php' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i>
                <span class="menu-text">My Services</span>
            </a>
        </li>

        <li>
            <a href="bookings.php" class="<?php echo $current === 'bookings.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span class="menu-text">Bookings</span>
                <?php if ($notificationCounts['bookings'] > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCounts['bookings']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="schedule.php" class="<?php echo $current === 'schedule.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span class="menu-text">Schedule</span>
            </a>
        </li>

        <li>
            <a href="reviews.php" class="<?php echo $current === 'reviews.php' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span class="menu-text">Reviews</span>
                <?php if ($notificationCounts['reviews'] > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCounts['reviews']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="earnings.php" class="<?php echo $current === 'earnings.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span class="menu-text">Earnings</span>
            </a>
        </li>

        <li>
            <a href="complaints.php" class="<?php echo $current === 'complaints.php' ? 'active' : ''; ?>">
                <i class="fas fa-flag"></i>
                <span class="menu-text">Complaints</span>
                <?php if ($notificationCounts['complaints'] > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCounts['complaints']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="messages.php" class="<?php echo $current === 'messages.php' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i>
                <span class="menu-text">Messages</span>
                <?php 
                    $unread_msgs = 0;
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
                        $stmt->execute([$provider_id]);
                        $result = $stmt->fetch();
                        $unread_msgs = $result['count'] ?? 0;
                    } catch (Exception $e) {}
                    if ($unread_msgs > 0): 
                ?>
                    <span class="notification-badge"><?php echo $unread_msgs; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="settings.php" class="<?php echo $current === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>
        </li>
    </ul>

<?php if ($hasSub): ?>
    <ul class="sidebar-submenu">
        <?php foreach ($settings_labels as $key => $label): ?>
            <li>
                <a href="settings.php?section=<?php echo $key; ?>" class="<?php echo $settings_section === $key ? 'active' : ''; ?>"><?php echo htmlspecialchars($label); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>



    <div class="sidebar-footer">
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
/* Provider sidebar styling */
.sidebar {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #1e3a8a;
    color: rgba(255, 255, 255, 0.8);
    min-height: 100vh;
    width: 240px; /* default with labels */
}
.sidebar.has-submenu {
    flex-direction: row;
    width: 320px; /* icons + submenu */
}
.sidebar.has-submenu .sidebar-menu {
    flex: none;
    width: 80px; /* keep icon column fixed */
}
.sidebar.has-submenu .sidebar-submenu {
    flex: 1;
}
.sidebar.icon-only {
    width: 80px; /* compact icon-only width */
}
.sidebar.icon-only.has-submenu {
    /* fallback: treat as normal if misapplied */
    width: 240px;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: white;
    font-weight: 600;
}

.sidebar-header p {
    margin: 0.25rem 0 0 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    flex: 1;
    overflow-y: auto;
    padding-right: 5px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 6px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

.sidebar-menu li {
    margin: 0;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 0.8rem 1.5rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.sidebar-menu a i {
    width: 25px;
    margin-right: 10px;
    font-size: 1.1rem;
}

/* remove right margin when icon-only (no text) */
.sidebar.icon-only .sidebar-menu a i {
    margin-right: 0;
}

.sidebar-menu a span {
    flex: 1;
}

.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: white;
}

.sidebar-menu a.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: #60a5fa;
}

.sidebar-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1rem 0;
}

.sidebar-footer a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    padding: 0.8rem 1.5rem;
    display: flex;
    align-items: center;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.sidebar-footer a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: white;
}

.sidebar-footer i {
    width: 25px;
    margin-right: 10px;
    font-size: 1.1rem;
}

/* Notification badge styling */
.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    min-width: 24px;
    height: 24px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-left: auto;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

/* center icons when compact */
.sidebar.icon-only .sidebar-menu a {
    justify-content: center;
}

.sidebar-menu a span {
    flex: 1;
}

/* Icon-only: hide textual labels permanently when class present */
.sidebar.icon-only .menu-text {
    display: none !important;
}

/* Submenu listing for settings page */
.sidebar-submenu {
    list-style: none;
    margin: 0;
    padding: 0;
    background: #1e3a8a;
    overflow-y: auto;
    flex: 1;
}

.sidebar-submenu li { margin: 0; }

.sidebar-submenu a {
    display: block;
    padding: 0.8rem 1rem;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: background 0.3s;
}

.sidebar-submenu a.active,
.sidebar-submenu a:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

/* Ensure main content lines up with sidebar */
.main-content {
    margin-left: 240px;
}
.sidebar.icon-only ~ .main-content {
    margin-left: 80px !important;
}
.sidebar.has-submenu ~ .main-content {
    margin-left: 320px; /* icons + submenu */
}

/* Sidebar hover rules only apply for icon-only mode (keep fixed width otherwise) */
.sidebar.icon-only:hover { width: 80px; }
.sidebar.icon-only:hover .sidebar-menu a { justify-content: center; padding: 0.8rem 1rem; }
.sidebar.icon-only:hover .sidebar-header h2, .sidebar.icon-only:hover .sidebar-header p { display: block; }

@media (max-width: 768px) {
    .sidebar { width: var(--sidebar-width); }
    .sidebar:hover { width: var(--sidebar-width); }
    .sidebar-menu a .tab-label { display: none !important; }
    .sidebar-menu a .menu-text { display: inline-block; margin-left: 12px; }
    .sidebar-submenu { display: none; }
}
</style>