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
<style>:root { --sidebar-width: 280px; }</style>
<aside class="sidebar icon-only" id="clientSidebar">

    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <?php echo htmlspecialchars(substr($platform_name, 0, 1)); ?>
        </div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name"><?php echo htmlspecialchars($platform_name); ?></span>
            <span class="sidebar-brand-role">Clients Area</span>
        </div>

    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">

            <li class="nav-section-label">Explore</li>

            <li>
                <a href="home.php" class="<?php echo ($current === 'home.php' || $current === 'providers.php') ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    <span class="nav-label">Home</span>
                </a>
            </li>

            <li>
                <a href="providers.php" class="<?php echo $current === 'providers.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-search"></i></span>
                    <span class="nav-label">Find Providers</span>
                </a>
            </li>

            <li>
                <a href="providers.php?section=top-rated" class="<?php echo (isset($_GET['section']) && $_GET['section'] === 'top-rated') ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-star-half-alt"></i></span>
                    <span class="nav-label">Top Providers</span>
                </a>
            </li>

            <li>
                <a href="providers.php?section=offers" class="<?php echo (isset($_GET['section']) && $_GET['section'] === 'offers') ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-tags"></i></span>
                    <span class="nav-label">Special Offers</span>
                </a>
            </li>

            <li>
                <a href="services.php" class="<?php echo $current === 'services.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-tools"></i></span>
                    <span class="nav-label">Services</span>
                </a>
            </li>

            <li class="nav-section-label">My Activity</li>

            <li>
                <a href="my-bookings.php" class="<?php echo $current === 'my-bookings.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-calendar-check"></i></span>
                    <span class="nav-label">My Bookings</span>
                </a>
            </li>

            <li>
                <a href="favorites.php" class="<?php echo $current === 'favorites.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-heart"></i></span>
                    <span class="nav-label">Favorites</span>
                </a>
            </li>

            <li>
                <a href="messages.php" class="<?php echo $current === 'messages.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-comments"></i></span>
                    <span class="nav-label">Messages</span>
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
                        <span class="nav-badge"><?php echo $unread; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="my-reviews.php" class="<?php echo $current === 'my-reviews.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-star"></i></span>
                    <span class="nav-label">My Reviews</span>
                </a>
            </li>

            <li>
                <a href="complaints.php" class="<?php echo $current === 'complaints.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-flag"></i></span>
                    <span class="nav-label">Complaints</span>
                </a>
            </li>

            <li class="nav-section-label">Account</li>

            <li>
                <a href="profile.php" class="<?php echo $current === 'profile.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-user"></i></span>
                    <span class="nav-label">Profile</span>
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
        <a href="../logout.php" class="footer-logout">
            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span class="nav-label">Logout</span>
        </a>
    </div>

</aside>

<style>
/* ═══════════════════════════════════════════
   CLIENT SIDEBAR — Modern Design System
   Matches Plus Jakarta Sans design tokens
═══════════════════════════════════════════ */

:root {
    --sb-width:        260px;
    --sb-accent:       #0d6efd;
    --sb-accent-dark:  #0a58ca;
    --sb-accent-light: rgba(13,110,253,0.10);
    --sb-surface:      #ffffff;
    --sb-border:       #e8eaf0;
    --sb-text:         #374151;
    --sb-text-muted:   #9ca3af;
    --sb-hover-bg:     #f7f8fc;
    --sb-active-bg:    rgba(13,110,253,0.08);
    --sb-radius:       8px;
    --sb-transition:   all 0.18s cubic-bezier(0.4,0,0.2,1);
}

/* ── SIDEBAR SHELL ── */
.sidebar {
    width: 70px;
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

.sidebar.icon-only .sidebar-toggle {
    margin-left: auto;
}

/* ── NAVIGATION ── */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
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

/* Section labels */
.nav-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--sb-text-muted);
    padding: 0.875rem 0.875rem 0.35rem;
    pointer-events: none;
}

.sidebar-menu li:first-child .nav-section-label,
.nav-section-label:first-child {
    padding-top: 0.35rem;
}

/* Nav links */
.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.875rem;
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
    text-decoration: none;
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
}

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
    color: #6b7280;
}

.footer-logout:hover {
    background: #fef2f2;
    color: #dc2626;
    text-decoration: none;
}

.footer-logout .nav-icon { color: #9ca3af; }
.footer-logout:hover .nav-icon { background: rgba(220,38,38,0.1); color: #dc2626; }

/* ── MAIN CONTENT OFFSET ── */
.main-content {
    margin-left: 85px;
    transition: margin-left 0.18s cubic-bezier(0.4,0,0.2,1);
}

/* ── ICON-ONLY MODE (DEFAULT) ── */
.sidebar.icon-only {
    width: 70px;
}

.sidebar.icon-only .sidebar-brand-text { display: none; }
.sidebar.icon-only .sidebar-brand { justify-content: center; padding: 1rem 0.5rem; width: 70px; }
.sidebar.icon-only .nav-section-label { display: none; }
.sidebar.icon-only .nav-label { display: none; }
.sidebar.icon-only .sidebar-menu a { justify-content: center; padding: 0.6rem; position: relative; }
.sidebar.icon-only .sidebar-menu a .nav-icon { width: 36px; height: 36px; }
.sidebar.icon-only .sidebar-footer { padding: 0.625rem 0.5rem; }
.sidebar.icon-only .footer-logout { justify-content: center; padding: 0.6rem; }
.sidebar.icon-only .nav-badge { position: absolute; top: -5px; right: -5px; font-size: 0.6rem; min-width: 16px; height: 16px; }

/* ── HOVER EXPANDED MODE ── */
.sidebar.hover-expanded {
    width: 280px;
    box-shadow: 2px 0 12px rgba(0,0,0,0.08);
}

.sidebar.hover-expanded .sidebar-brand-text { display: flex; }
.sidebar.hover-expanded .sidebar-brand { justify-content: flex-start; padding: 1.25rem 1.25rem 1rem; }
.sidebar.hover-expanded .nav-section-label { display: block; }
.sidebar.hover-expanded .nav-label { display: inline; }
.sidebar.hover-expanded .sidebar-menu a { justify-content: flex-start; padding: 0.6rem 0.875rem; }
.sidebar.hover-expanded .sidebar-menu a .nav-icon { width: 32px; height: 32px; }
.sidebar.hover-expanded .sidebar-footer { padding: 0.625rem 0.75rem; }
.sidebar.hover-expanded .footer-logout { justify-content: flex-start; }

/* ── MOBILE ── */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
        transform: translateX(-100%);
        box-shadow: none;
    }

    .sidebar.hover-expanded {
        transform: translateX(0);
        width: 280px;
        box-shadow: 4px 0 20px rgba(0,0,0,0.12);
    }

    .sidebar.icon-only .sidebar-brand-text { display: none; }
    .sidebar.hover-expanded .sidebar-brand-text { display: flex; }

    .main-content {
        margin-left: 0 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('clientSidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (!sidebar) return;

    // Hover to expand
    sidebar.addEventListener('mouseenter', function() {
        sidebar.classList.add('hover-expanded');
        if (mainContent) {
            mainContent.style.transition = 'none';
            mainContent.style.marginLeft = '280px';
            // Force reflow
            mainContent.offsetHeight;
            mainContent.style.transition = 'margin-left 0.18s cubic-bezier(0.4,0,0.2,1)';
        }
    });

    // Hover out to collapse
    sidebar.addEventListener('mouseleave', function() {
        sidebar.classList.remove('hover-expanded');
        if (mainContent) {
            mainContent.style.transition = 'none';
            mainContent.style.marginLeft = '85px';
            // Force reflow
            mainContent.offsetHeight;
            mainContent.style.transition = 'margin-left 0.18s cubic-bezier(0.4,0,0.2,1)';
        }
    });

    // Set initial margin for icon-only state (85px to account for sidebar + badges)
    if (mainContent) {
        mainContent.style.marginLeft = '85px';
        mainContent.style.transition = 'margin-left 0.18s cubic-bezier(0.4,0,0.2,1)';
    }

    // Add tooltips when icon-only
    function updateTooltips() {
        const isIconOnly = sidebar.classList.contains('icon-only');
        const links = sidebar.querySelectorAll('.sidebar-menu a, .footer-logout');
        links.forEach(link => {
            const label = link.querySelector('.nav-label');
            if (isIconOnly && label) {
                link.title = label.textContent.trim();
            } else {
                link.removeAttribute('title');
            }
        });
    }

    updateTooltips();

    // Update tooltips on hover state changes
    sidebar.addEventListener('mouseenter', updateTooltips);
    sidebar.addEventListener('mouseleave', updateTooltips);
});
</script>