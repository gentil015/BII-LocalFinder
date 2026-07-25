<?php

if (!function_exists('client_header_get_db')) {
    function client_header_get_db()
    {
        global $db;
        if (isset($db) && $db instanceof PDO) {
            return $db;
        }
        if (class_exists('Database')) {
            try {
                return Database::getInstance()->getConnection();
            } catch (Throwable $e) {
                return null;
            }
        }
        return null;
    }
}

if (!function_exists('client_header_get_platform_name')) {
    function client_header_get_platform_name(): string
    {
        global $platform_name;
        if (!empty($platform_name)) {
            return (string)$platform_name;
        }

        $db = client_header_get_db();
        if ($db) {
            try {
                $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'platform_name' LIMIT 1");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                if ($val !== false && trim((string)$val) !== '') {
                    return (string)$val;
                }
            } catch (Throwable $e) {
            }
        }
        return 'BII LocalFinder';
    }
}

if (!function_exists('client_header_get_client_name')) {
    function client_header_get_client_name(): string
    {
        global $clientName, $client;
        if (!empty($clientName)) {
            return (string)$clientName;
        }
        if (!empty($client['full_name'])) {
            return (string)$client['full_name'];
        }

        $db = client_header_get_db();
        if ($db && isset($_SESSION['user_id'])) {
            try {
                $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$_SESSION['user_id']]);
                $name = $stmt->fetchColumn();
                if ($name !== false && trim((string)$name) !== '') {
                    return (string)$name;
                }
            } catch (Throwable $e) {
            }
        }
        return 'there';
    }
}

if (!function_exists('client_header_get_platform_stats')) {
    function client_header_get_platform_stats(): array
    {
        global $platformStats;
        if (!empty($platformStats) && is_array($platformStats)) {
            return $platformStats;
        }

        $db = client_header_get_db();
        $stats = ['providers' => 0, 'districts' => 0, 'categories' => 0, 'avg_rating' => 0.0, 'verified' => 0];
        if (!$db) {
            return $stats;
        }

        try {
            $stats['providers'] = (int) $db->query("SELECT COUNT(*) FROM service_providers WHERE is_active=1 AND is_banned=0")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $stats['districts'] = (int) $db->query("SELECT COUNT(DISTINCT name) FROM districts")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $stats['categories'] = (int) $db->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $stats['avg_rating'] = (float) $db->query("SELECT AVG(average_rating) FROM service_providers WHERE is_active=1 AND average_rating>0")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $stats['verified'] = (int) $db->query("SELECT COUNT(*) FROM service_providers WHERE is_active=1 AND is_banned=0 AND (is_verified=1 OR verification_level IN ('verified','gold','premium'))")->fetchColumn();
        } catch (Throwable $e) {
        }

        return $stats;
    }
}

if (!function_exists('client_header_get_nav_links')) {
    function client_header_get_nav_links(string $activePage = ''): array
    {
        $activePage = trim($activePage ?: basename($_SERVER['PHP_SELF'] ?? ''));
        $links = [
            ['href' => 'home.php',        'icon' => 'fa-house',          'label' => 'Home'],
            ['href' => 'providers.php',   'icon' => 'fa-magnifying-glass','label' => 'Find providers'],
            ['href' => 'my-bookings.php', 'icon' => 'fa-calendar-check', 'label' => 'Bookings'],
            ['href' => 'messages.php',    'icon' => 'fa-comment-dots',   'label' => 'Messages'],
            ['href' => 'favorites.php',   'icon' => 'fa-heart',          'label' => 'Favorites'],
        ];

        foreach ($links as &$link) {
            $hrefFile = basename($link['href']);
            if ($hrefFile === $activePage) {
                $link['active'] = true;
            }
        }
        unset($link);
        return $links;
    }
}

if (!function_exists('client_header_render_styles')) {
    function client_header_render_styles(): void
    {
        ?>
        <style>
        :root {
          --ink:        #0B1F17;
          --ink-2:      #12291F;
          --ink-3:      #1B382A;
          --paper:      #F6F3EC;
          --paper-2:    #FFFFFF;
          --card:       #FFFFFF;
          --line:       #E7E2D6;
          --line-soft:  #EFEBE0;
          --brass:      #B9822E;
          --brass-2:    #D9A64E;
          --moss:       #3F6B4A;
          --moss-2:     #2E5038;
          --clay:       #A8432E;
          --clay-dim:   rgba(168,67,46,.12);
          --brass-dim:  rgba(185,130,46,.16);
          --text-1:     #10201A;
          --text-2:     #5B685F;
          --text-3:     #94A092;
          --text-inv:   #F6F3EC;
          --text-inv-2: rgba(246,243,236,.66);
          --header-h:   68px;
          --r-sm: 10px; --r-md: 16px; --r-lg: 22px; --r-xl: 30px;
          --shadow-card: 0 1px 2px rgba(16,32,26,.04), 0 12px 28px rgba(16,32,26,.07);
          --shadow-pop:  0 20px 55px rgba(11,31,23,.28);
          --ease: cubic-bezier(.16,.8,.24,1);
          --font-display: 'Syne', sans-serif;
          --font-body: 'DM Sans', system-ui, sans-serif;
          --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
        }
        *,*::before,*::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
          background: var(--paper);
          font-family: var(--font-body);
          color: var(--text-1);
          -webkit-font-smoothing: antialiased;
          min-height: 100vh;
        }
        .main-content {
          min-height: 100vh;
          padding: 2rem;
          margin: 0;
        }
        h1,h2,h3,h4 { font-family: var(--font-display); letter-spacing:-.01em; }
        a { color: inherit; }
        :focus-visible { outline: 2px solid var(--brass); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { *,*::before,*::after { animation-duration:.001ms !important; transition-duration:.001ms !important; } }

        .site-header {
          position: sticky; top:0; z-index: 1000; background: rgba(246,243,236,.86); backdrop-filter: blur(14px);
          border-bottom: 1px solid var(--line); height: var(--header-h);
        }
        .site-header-inner {
          max-width: 1320px; margin:0 auto; height:100%; padding: 0 2rem;
          display:flex; align-items:center; justify-content:space-between; gap:1.5rem;
        }
        .brand { display:flex; align-items:center; gap:.6rem; text-decoration:none; color: var(--text-1); flex-shrink:0; }
        .brand-mark {
          width:36px; height:36px; border-radius:10px; background: var(--ink);
          display:flex; align-items:center; justify-content:center; color: var(--brass-2); font-size:1rem; flex-shrink:0;
        }
        .brand-word { font-family: var(--font-display); font-weight:800; font-size:1.05rem; line-height:1.1; }
        .brand-word small { display:block; font-family: var(--font-mono); font-weight:400; font-size:.6rem; color: var(--text-3); letter-spacing:.06em; text-transform:uppercase; }

        .main-nav { display:flex; align-items:center; gap:.15rem; flex:1; justify-content:center; }
        .main-nav a {
          text-decoration:none; color: var(--text-2); font-size:.86rem; font-weight:600; padding:.55rem .9rem;
          border-radius: var(--r-sm); transition:.15s var(--ease); position:relative;
        }
        .main-nav a:hover { color: var(--text-1); background: var(--line-soft); }
        .main-nav a.active { color: var(--ink); }
        .main-nav a.active::after { content:''; position:absolute; left:.9rem; right:.9rem; bottom:.2rem; height:2px; background: var(--brass); border-radius:2px; }

        .header-actions { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
        .header-icon-btn {
          width:38px; height:38px; border-radius:10px; border:1px solid var(--line); background: var(--paper-2);
          color: var(--text-2); display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none;
          transition:.15s var(--ease); position:relative; font-size:.9rem;
        }
        .header-icon-btn:hover { border-color: var(--brass); color: var(--brass); }
        .header-icon-btn .ping { position:absolute; top:6px; right:6px; width:7px; height:7px; border-radius:50%; background: var(--clay); border:1.5px solid var(--paper-2); }

        .user-menu { position:relative; }
        .user-menu-btn {
          display:flex; align-items:center; gap:.55rem; background: var(--paper-2); border:1px solid var(--line);
          border-radius: 100px; padding:.3rem .8rem .3rem .3rem; cursor:pointer; font-family:inherit; transition:.15s var(--ease);
        }
        .user-menu-btn:hover { border-color: var(--brass); }
        .user-menu-avatar {
          width:30px; height:30px; border-radius:50%; background: linear-gradient(135deg, var(--brass), var(--brass-2));
          color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.78rem; flex-shrink:0;
        }
        .user-menu-name { font-size:.82rem; font-weight:700; color: var(--text-1); max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .user-menu-btn i.chev { font-size:.6rem; color: var(--text-3); transition:.15s var(--ease); }
        .user-menu.open .chev { transform: rotate(180deg); }
        .user-menu-dropdown {
          position:absolute; top:calc(100% + .6rem); right:0; background: var(--paper-2); border:1px solid var(--line);
          border-radius: var(--r-lg); box-shadow: var(--shadow-card); min-width:200px; padding:.5rem; display:none; z-index:1200;
        }
        .user-menu.open .user-menu-dropdown { display:block; animation: slideDown .16s ease; }
        .user-menu-dropdown a { display:flex; align-items:center; gap:.6rem; padding:.6rem .7rem; border-radius: var(--r-sm); text-decoration:none; color: var(--text-2); font-size:.84rem; font-weight:600; transition:.15s var(--ease); }
        .user-menu-dropdown a:hover { background: var(--brass-dim); color: var(--brass); }
        .user-menu-dropdown a i { width:16px; text-align:center; color: var(--text-3); }
        .user-menu-dropdown a:hover i { color: var(--brass); }
        .user-menu-dropdown .divider { height:1px; background: var(--line-soft); margin:.4rem .2rem; }
        .user-menu-dropdown a.logout { color: var(--clay); }
        .user-menu-dropdown a.logout i { color: var(--clay); }

        .mobile-nav-toggle { display:none; width:38px; height:38px; border-radius:10px; border:1px solid var(--line); background: var(--paper-2); align-items:center; justify-content:center; cursor:pointer; font-size:1rem; color: var(--text-1); }
        .mobile-nav-panel {
          display:none; background: var(--paper-2); border-bottom: 1px solid var(--line); padding: .5rem 1.1rem 1rem;
        }
        .mobile-nav-panel.open { display:block; animation: slideDown .18s ease; }
        .mobile-nav-panel a { display:flex; align-items:center; gap:.65rem; padding:.75rem .5rem; text-decoration:none; color: var(--text-2); font-size:.9rem; font-weight:600; border-bottom:1px solid var(--line-soft); }
        .mobile-nav-panel a:last-child { border-bottom:none; }
        .mobile-nav-panel a.active { color: var(--brass); }
        .mobile-nav-panel a i { width:18px; color: var(--text-3); }

        .util-bar {
          background: var(--ink); color: var(--text-inv-2); font-family: var(--font-mono);
          font-size:.72rem; padding:.5rem 2rem; display:flex; justify-content:space-between; align-items:center;
          gap:1rem; flex-wrap:wrap;
        }
        .util-bar span { display:inline-flex; align-items:center; gap:.4rem; }
        .util-bar .dot-live { width:6px; height:6px; border-radius:50%; background:#7CD68C; box-shadow:0 0 8px #7CD68C; animation: blink 2s infinite; }

        @keyframes slideDown { from{opacity:0; transform:translateY(-8px)} to{opacity:1; transform:translateY(0)} }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

        @media (max-width: 900px) {
          .main-nav { display:none; }
          .mobile-nav-toggle { display:flex; }
          .user-menu-name { display:none; }
          .site-header-inner { padding: 0 1.1rem; }
        }
        </style>
        <?php
    }
}

if (!function_exists('client_header_render_markup')) {
    function client_header_render_markup(string $activePage = ''): void
    {
        $platform_name = client_header_get_platform_name();
        $clientName = client_header_get_client_name();
        $clientInitial = strtoupper(substr(trim($clientName), 0, 1)) ?: 'U';
        $platformStats = client_header_get_platform_stats();
        $navLinks = client_header_get_nav_links($activePage);
        ?>
        <header class="site-header">
          <div class="site-header-inner">
            <a href="home.php" class="brand">
              <span class="brand-mark"><i class="fas fa-map-location-dot"></i></span>
              <span class="brand-word"><?php echo htmlspecialchars($platform_name); ?><small>Rwanda · local services</small></span>
            </a>

            <nav class="main-nav">
              <?php foreach ($navLinks as $nl): ?>
                <a href="<?php echo htmlspecialchars($nl['href']); ?>" class="<?php echo !empty($nl['active']) ? 'active' : ''; ?>">
                  <?php echo htmlspecialchars($nl['label']); ?>
                </a>
              <?php endforeach; ?>
            </nav>

            <div class="header-actions">
              <a href="favorites.php" class="header-icon-btn" title="Favorites"><i class="fas fa-heart"></i></a>
              <a href="messages.php" class="header-icon-btn" title="Messages"><i class="fas fa-comment-dots"></i></a>
              <a href="notifications.php" class="header-icon-btn" title="Notifications"><i class="fas fa-bell"></i><span class="ping"></span></a>

              <div class="user-menu" id="userMenu">
                <button class="user-menu-btn" id="userMenuBtn" type="button">
                  <span class="user-menu-avatar"><?php echo htmlspecialchars($clientInitial); ?></span>
                  <span class="user-menu-name"><?php echo htmlspecialchars($clientName); ?></span>
                  <i class="fas fa-chevron-down chev"></i>
                </button>
                <div class="user-menu-dropdown">
                  <a href="profile.php"><i class="fas fa-user"></i> My profile</a>
                  <a href="my-bookings.php"><i class="fas fa-calendar-check"></i> My bookings</a>
                  <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
                  <div class="divider"></div>
                  <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
                </div>
              </div>

              <button class="mobile-nav-toggle" id="mobileNavToggle" type="button"><i class="fas fa-bars"></i></button>
            </div>
          </div>

          <nav class="mobile-nav-panel" id="mobileNavPanel">
            <?php foreach ($navLinks as $nl): ?>
              <a href="<?php echo htmlspecialchars($nl['href']); ?>" class="<?php echo !empty($nl['active']) ? 'active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($nl['icon']); ?>"></i> <?php echo htmlspecialchars($nl['label']); ?>
              </a>
            <?php endforeach; ?>
            <a href="profile.php"><i class="fas fa-user"></i> My profile</a>
            <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
            <a href="../logout.php" style="color:var(--clay);"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
          </nav>
        </header>

        <div class="util-bar">
          <span><span class="dot-live"></span> <?php echo number_format($platformStats['providers']); ?> active pros online across <?php echo max(1, (int)$platformStats['districts']); ?> districts</span>
          <span><i class="fas fa-clock"></i> <?php echo date('D, j M · H:i'); ?>, Kigali time</span>
        </div>
        <?php
    }
}

if (!function_exists('client_header_render_scripts')) {
    function client_header_render_scripts(): void
    {
        ?>
        <script>
        const mobileNavToggle = document.getElementById('mobileNavToggle');
        const mobileNavPanel = document.getElementById('mobileNavPanel');
        mobileNavToggle?.addEventListener('click', () => {
          mobileNavPanel.classList.toggle('open');
          const icon = mobileNavToggle.querySelector('i');
          icon.className = mobileNavPanel.classList.contains('open') ? 'fas fa-xmark' : 'fas fa-bars';
        });

        const userMenu = document.getElementById('userMenu');
        const userMenuBtn = document.getElementById('userMenuBtn');
        userMenuBtn?.addEventListener('click', (e) => {
          e.stopPropagation();
          userMenu.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
          if (userMenu && !userMenu.contains(e.target)) userMenu.classList.remove('open');
        });
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            userMenu?.classList.remove('open');
            mobileNavPanel?.classList.remove('open');
            if (mobileNavToggle) mobileNavToggle.querySelector('i').className = 'fas fa-bars';
          }
        });
        </script>
        <?php
    }
}
