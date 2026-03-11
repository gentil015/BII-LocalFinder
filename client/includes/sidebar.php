<?php
// Ensure DB connection and fetch platform name from system_settings
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
    // fallback to default on error
}

$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar collapsed" id="clientSidebar">
    <div class="sidebar-header">
        <h2><?php echo htmlspecialchars($platform_name); ?></h2>
        <p>Client Area</p>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-section-title">MAIN</li>
        <li>
            <a href="dashboard.php" class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" data-label="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="providers.php" class="<?php echo $current === 'providers.php' ? 'active' : ''; ?>" data-label="Find Providers">
                <i class="fas fa-search"></i>
                <span>Find Providers</span>
            </a>
        </li>

        <li>
            <a href="providers.php?section=top-rated" class="<?php echo isset($_GET['section']) && $_GET['section'] === 'top-rated' ? 'active' : ''; ?>" data-label="Top Providers">
                <i class="fas fa-star-half-alt"></i>
                <span>Top Providers</span>
            </a>
        </li>

        <li>
            <a href="providers.php?section=offers" class="<?php echo isset($_GET['section']) && $_GET['section'] === 'offers' ? 'active' : ''; ?>" data-label="Special Offers">
                <i class="fas fa-tags"></i>
                <span>Special Offers</span>
            </a>
        </li>


        <li>
            <a href="my-bookings.php" class="<?php echo $current === 'my-bookings.php' ? 'active' : ''; ?>" data-label="My Bookings">
                <i class="fas fa-calendar-check"></i>
                <span>My Bookings</span>
            </a>
        </li>

        <li>
            <a href="messages.php" class="<?php echo $current === 'messages.php' ? 'active' : ''; ?>" data-label="Messages">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
                <?php 
                    $unread = 0;
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $result = $stmt->fetch();
                        $unread = $result['count'] ?? 0;
                    } catch (Exception $e) {}
                    if ($unread > 0): 
                ?>
                    <span class="badge bg-danger" style="margin-left: auto; display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; border-radius: 50%; font-size: 0.75rem; font-weight: 700;"><?php echo $unread; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="my-reviews.php" class="<?php echo $current === 'my-reviews.php' ? 'active' : ''; ?>" data-label="My Reviews">
                <i class="fas fa-star"></i>
                <span>My Reviews</span>
            </a>
        </li>

        <li>
            <a href="complaints.php" class="<?php echo $current === 'complaints.php' ? 'active' : ''; ?>" data-label="Complaint Center">
                <i class="fas fa-flag"></i>
                <span>Complaint Center</span>
            </a>
        </li>

        <li>
            <a href="profile.php" class="<?php echo $current === 'profile.php' ? 'active' : ''; ?>" data-label="Profile">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </li>

        <li>
            <a href="settings.php" class="<?php echo $current === 'settings.php' ? 'active' : ''; ?>" data-label="Settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="../logout.php" data-label="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
/* Client sidebar styling (matching admin blue sidebar) */
.sidebar {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #0a58ca;
    color: rgba(255, 255, 255, 0.8);
    min-height: 100vh;
    width: 260px;
    transition: width 0.3s ease;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
    transition: all 0.3s ease;
}

.sidebar.collapsed .sidebar-header {
    padding: 1rem 0.5rem;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: white;
    font-weight: 600;
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .sidebar-header h2 {
    font-size: 0.9rem;
    opacity: 0;
    height: 0;
    overflow: hidden;
}

.sidebar-header p {
    margin: 0.25rem 0 0 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .sidebar-header p {
    opacity: 0;
    height: 0;
    overflow: hidden;
}

.sidebar-toggle-btn {
    position: absolute;
    top: 1.5rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    font-size: 0.9rem;
}

.sidebar-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.sidebar.collapsed .sidebar-toggle-btn i {
    transform: rotate(180deg);
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

.sidebar-section-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.65);
    padding: 0.75rem 1.5rem 0.25rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.sidebar-section-title + li {
    margin-top: 0.25rem;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 0.8rem 1.5rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s;
    border-left: 3px solid transparent;
    position: relative;
}

.sidebar.collapsed .sidebar-menu a {
    padding: 1rem 0.5rem;
    justify-content: center;
}

.sidebar.collapsed .sidebar-menu a span {
    display: none;
}

.sidebar.expanded-on-hover {
    width: 260px !important;
}

.sidebar.expanded-on-hover .sidebar-header h2,
.sidebar.expanded-on-hover .sidebar-header p {
    opacity: 1 !important;
    height: auto !important;
}

.sidebar.expanded-on-hover .sidebar-menu a span {
    display: inline !important;
}

.sidebar.expanded-on-hover .sidebar-menu a {
    justify-content: flex-start;
    padding: 0.8rem 1.5rem;
}

.sidebar.collapsed .sidebar-menu a i {
    margin-right: 0;
    font-size: 1.3rem;
}

.sidebar.collapsed .sidebar-menu a:hover::after {
    content: attr(data-label);
    position: absolute;
    left: 80px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
    z-index: 1001;
    font-size: 0.85rem;
    font-weight: 500;
}

.sidebar-menu a i {
    width: 25px;
    margin-right: 10px;
    font-size: 1.1rem;
    transition: all 0.3s;
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
    border-left-color: #0a58ca;
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
    position: relative;
}

.sidebar.collapsed .sidebar-footer a {
    padding: 1rem 0.5rem;
    justify-content: center;
}

.sidebar.collapsed .sidebar-footer a span {
    display: none;
}

.sidebar.collapsed .sidebar-footer a i {
    margin-right: 0;
}

.sidebar.collapsed .sidebar-footer a:hover::after {
    content: attr(data-label);
    position: absolute;
    left: 80px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
    z-index: 1001;
    font-size: 0.85rem;
    font-weight: 500;
}

.sidebar-footer i {
    width: 25px;
    margin-right: 10px;
    font-size: 1.1rem;
}
</style>