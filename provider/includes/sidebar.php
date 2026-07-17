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
$iconOnly = true; // Changed to true for default icon-only
$hasSub = false;
?>
<aside class="sidebar" id="providerSidebar">

    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <?php echo htmlspecialchars(substr($platform_name, 0, 1)); ?>
        </div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name"><?php echo htmlspecialchars($platform_name); ?></span>
            <span class="sidebar-brand-role">Provider Panel</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">

            <li>
                <a href="dashboard.php" class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    <span class="nav-label">Dashboard</span>
                    <?php if ($notificationCounts['notifications'] > 0): ?>
                        <span class="nav-badge"><?php echo $notificationCounts['notifications']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="services.php" class="<?php echo $current === 'services.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-briefcase"></i></span>
                    <span class="nav-label">My Services</span>
                </a>
            </li>

            <li>
                <a href="bookings.php" class="<?php echo $current === 'bookings.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-calendar-check"></i></span>
                    <span class="nav-label">Bookings</span>
                    <?php if ($notificationCounts['bookings'] > 0): ?>
                        <span class="nav-badge"><?php echo $notificationCounts['bookings']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="schedule.php" class="<?php echo $current === 'schedule.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-clock"></i></span>
                    <span class="nav-label">Schedule</span>
                </a>
            </li>

            <li>
                <a href="reviews.php" class="<?php echo $current === 'reviews.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-star"></i></span>
                    <span class="nav-label">Reviews</span>
                    <?php if ($notificationCounts['reviews'] > 0): ?>
                        <span class="nav-badge"><?php echo $notificationCounts['reviews']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="earnings.php" class="<?php echo $current === 'earnings.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-money-bill-wave"></i></span>
                    <span class="nav-label">Earnings</span>
                </a>
            </li>

            <li>
                <a href="complaints.php" class="<?php echo $current === 'complaints.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-flag"></i></span>
                    <span class="nav-label">Complaints</span>
                    <?php if ($notificationCounts['complaints'] > 0): ?>
                        <span class="nav-badge"><?php echo $notificationCounts['complaints']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="messages.php" class="<?php echo $current === 'messages.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-comments"></i></span>
                    <span class="nav-label">Messages</span>
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
                        <span class="nav-badge"><?php echo $unread_msgs; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="settings.php" class="<?php echo $current === 'settings.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    <span class="nav-label">Settings</span>
                </a>
            </li>

        </ul>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="profile.php" class="footer-profile <?php echo $current === 'profile.php' ? 'active' : ''; ?>">
            <span class="nav-icon"><i class="fas fa-user-circle"></i></span>
            <span class="nav-label">My Profile</span>
        </a>
        <a href="../logout.php" class="footer-logout">
            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span class="nav-label">Logout</span>
        </a>
    </div>

</aside>

<style>
/* ═══════════════════════════════════════════
   PROVIDER SIDEBAR — Modern Design System
   Matches Plus Jakarta Sans design tokens
═══════════════════════════════════════════ */

:root {
    --sb-width:        260px;
    --sb-accent:       #0d6efd;
    --sb-accent-dark:  #0a58ca;
    --sb-accent-light: rgba(13,110,253,0.12);
    --sb-surface:      #ffffff;
    --sb-border:       #e8eaf0;
    --sb-text:         #374151;
    --sb-text-muted:   #9ca3af;
    --sb-hover-bg:     #f7f8fc;
    --sb-active-bg:    rgba(13,110,253,0.08);
    --sb-radius:       8px;
    --sb-transition:   all 0.18s cubic-bezier(0.4,0,0.2,1);
}

/* Dark Mode Variables for Sidebar */
[data-theme="dark"] {
    --sb-accent:       #3b82f6;
    --sb-accent-dark:  #2563eb;
    --sb-accent-light: rgba(59,130,246,0.12);
    --sb-surface:      #0f172a;
    --sb-border:       #334155;
    --sb-text:         #f1f5f9;
    --sb-text-muted:   #94a3b8;
    --sb-hover-bg:     #1e293b;
    --sb-active-bg:    rgba(59,130,246,0.08);
}

/* Dark Mode Logout Button Overrides */
[data-theme="dark"] .footer-logout {
    color: #94a3b8;
}

[data-theme="dark"] .footer-logout:hover {
    background: rgba(220,38,38,0.15);
    color: #ef4444;
}

[data-theme="dark"] .footer-logout .nav-icon {
    color: #94a3b8;
}

[data-theme="dark"] .footer-logout:hover .nav-icon {
    background: rgba(220,38,38,0.15);
    color: #ef4444;
}

/* ── SIDEBAR SHELL ── */
.sidebar {
    width: 60px; /* Default to icon-only */
    background: var(--sb-surface);
    border-right: 1px solid var(--sb-border);
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    overflow: hidden;
    transition: var(--sb-transition);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* Hover to expand */
.sidebar:hover {
    width: 260px;
}

/* ── BRAND HEADER ── */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 1.25rem 1rem;
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
}

.sidebar-brand-icon {
    width: 36px;
    height: 36px;
    background: var(--sb-accent);
    color: white;
    border-radius: var(--sb-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
    flex-shrink: 0;
    letter-spacing: -0.5px;
}

.sidebar-brand-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.sidebar-brand-name {
    font-weight: 800;
    font-size: 0.875rem;
    color: var(--sb-accent);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.3px;
}

.sidebar-brand-role {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--sb-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
/* ── SIDEBAR TOGGLE ── */
.sidebar-toggle {
    background: none;
    border: none;
    color: var(--sb-text-muted);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: var(--sb-radius);
    transition: var(--sb-transition);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sidebar-toggle:hover {
    background: var(--sb-hover-bg);
    color: var(--sb-accent);
}

/* Default icon-only styles */
.sidebar .sidebar-toggle { display: none; } /* Hide toggle for hover behavior */
.sidebar .sidebar-brand-text { display: none; }
.sidebar .sidebar-brand { justify-content: center; padding: 1rem 0.5rem; }
.sidebar .nav-label { display: none; }
.sidebar .sidebar-menu a { justify-content: center; padding: 0.6rem; }
.sidebar .sidebar-footer { padding: 0.625rem 0.5rem; }
.sidebar .footer-profile,
.sidebar .footer-logout { justify-content: center; padding: 0.6rem; }
.sidebar .nav-badge { position: absolute; top: -5px; right: -5px; font-size: 0.6rem; min-width: 16px; height: 16px; }

/* Expanded on hover styles */
.sidebar:hover .sidebar-toggle { display: block; margin-left: auto; }
.sidebar:hover .sidebar-brand-text { display: flex; }
.sidebar:hover .sidebar-brand { justify-content: flex-start; padding: 1.25rem 1.25rem 1rem; }
.sidebar:hover .nav-label { display: block; }
.sidebar:hover .sidebar-menu a { justify-content: flex-start; padding: 0.625rem 0.875rem; }
.sidebar:hover .sidebar-footer { padding: 0.625rem 0.75rem; }
.sidebar:hover .footer-profile,
.sidebar:hover .footer-logout { justify-content: flex-start; padding: 0.6rem 0.875rem; }
.sidebar:hover .nav-badge { position: static; top: auto; right: auto; font-size: 0.65rem; min-width: 18px; height: 18px; }
/* ── NAVIGATION ── */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem 0.75rem;
    scrollbar-width: thin;
    scrollbar-color: var(--sb-border) transparent;
}

.sidebar-nav::-webkit-scrollbar { width: 3px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: var(--sb-border); border-radius: 99px; }

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.625rem 0.875rem;
    color: var(--sb-text);
    text-decoration: none;
    border-radius: var(--sb-radius);
    transition: var(--sb-transition);
    font-size: 0.875rem;
    font-weight: 500;
    position: relative;
}

.sidebar-menu a:hover {
    background: var(--sb-hover-bg);
    color: var(--sb-accent);
}

.sidebar-menu a.active {
    background: var(--sb-active-bg);
    color: var(--sb-accent);
    font-weight: 700;
}

/* ── NAV ICON ── */
.nav-icon {
    width: 32px;
    height: 32px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.875rem;
    color: var(--sb-text-muted);
    background: transparent;
    transition: var(--sb-transition);
}

.sidebar-menu a:hover .nav-icon {
    background: var(--sb-accent-light);
    color: var(--sb-accent);
}

.sidebar-menu a.active .nav-icon {
    background: var(--sb-accent);
    color: white;
}

/* ── NAV LABEL ── */
.nav-label {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
}

/* ── NOTIFICATION BADGE ── */
.nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    color: white;
    border-radius: 100px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 0.65rem;
    font-weight: 800;
    flex-shrink: 0;
    letter-spacing: 0;
    box-shadow: 0 1px 4px rgba(239,68,68,0.4);
    animation: badgePulse 2.5s ease-in-out infinite;
}

@keyframes badgePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.85; transform: scale(0.95); }
}

/* ── FOOTER ── */
.sidebar-footer {
    flex-shrink: 0;
    border-top: 1px solid var(--sb-border);
    padding: 0.625rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.footer-profile,
.footer-logout {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.875rem;
    border-radius: var(--sb-radius);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--sb-transition);
    color: var(--sb-text);
}

.footer-profile:hover,
.footer-profile.active {
    background: var(--sb-hover-bg);
    color: var(--sb-accent);
}

.footer-profile .nav-icon { color: var(--sb-text-muted); }
.footer-profile:hover .nav-icon,
.footer-profile.active .nav-icon { background: var(--sb-accent-light); color: var(--sb-accent); }

.footer-logout {
    color: var(--sb-text-muted);
}

.footer-logout:hover {
    background: rgba(220,38,38,0.1);
    color: #dc2626;
}

.footer-logout .nav-icon { color: var(--sb-text-muted); }
.footer-logout:hover .nav-icon { background: rgba(220,38,38,0.1); color: #dc2626; }

/* ── MAIN CONTENT OFFSET ── */
.main-content {
    margin-left: 70px;
    transition: margin-left 0.18s cubic-bezier(0.4,0,0.2,1);
}

/* ── SECTION DIVIDER (optional utility) ── */
.nav-section-label {
    padding: 0.875rem 0.875rem 0.35rem;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--sb-text-muted);
}

/* ── MOBILE ── */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
    }

    .sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: 4px 0 20px rgba(0,0,0,0.12);
    }

    .main-content {
        margin-left: 0 !important;
    }
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('providerSidebar');
        const mainContent = document.querySelector('.main-content');

        function updateTooltips() {
            const links = sidebar.querySelectorAll('.sidebar-menu a, .footer-profile, .footer-logout');
            links.forEach(link => {
                const label = link.querySelector('.nav-label');
                if (label) {
                    link.title = label.textContent.trim();
                }
            });
        }

        // Handle sidebar hover to adjust main content margin
        if (mainContent) {
            sidebar.addEventListener('mouseenter', function() {
                mainContent.style.transition = 'none';
                mainContent.style.marginLeft = '280px';
                mainContent.offsetHeight; // force reflow
                mainContent.style.transition = 'margin-left 0.18s cubic-bezier(0.4,0,0.2,1)';
            });

            sidebar.addEventListener('mouseleave', function() {
                mainContent.style.transition = 'none';
                mainContent.style.marginLeft = '70px';
                mainContent.offsetHeight; // force reflow
                mainContent.style.transition = 'margin-left 0.18s cubic-bezier(0.4,0,0.2,1)';
            });

            // Set default margin for icon-only
            mainContent.style.marginLeft = '70px';
        }

        // Always show tooltips since sidebar is icon-only by default
        updateTooltips();

        // Dark Mode Initialization for all provider pages
        const savedTheme = localStorage.getItem('provider_theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    });
</script>