<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (isProvider()) {
    redirect('../provider/dashboard.php');
}

$db = Database::getInstance()->getConnection();

// Load platform settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

$platform_name = getSetting($db, 'platform_name', 'BII LocalFinder');

// Get client info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

// Filter parameters
$search      = isset($_GET['search'])   ? sanitize($_GET['search'])   : '';
$category_id = isset($_GET['category']) ? intval($_GET['category'])   : 0;
$sort        = isset($_GET['sort'])     ? sanitize($_GET['sort'])     : 'popular';
$min_price   = isset($_GET['min_price'])? intval($_GET['min_price'])  : 0;
$max_price   = isset($_GET['max_price'])? intval($_GET['max_price'])  : 0;
$page        = isset($_GET['page'])     ? max(1, intval($_GET['page'])): 1;
$per_page    = 12;
$offset      = ($page - 1) * $per_page;

// Get all categories for filter sidebar
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Build services query
$sql = "
    SELECT
        ps.*,
        c.name  AS category_name,
        c.icon  AS category_icon,
        sp.id   AS provider_id,
        sp.location,
        sp.average_rating,
        sp.total_reviews,
        sp.experience_years,
        sp.is_verified,
        sp.availability,
        u.full_name    AS provider_name,
        u.profile_image AS provider_image,
        u.is_verified  AS user_verified
    FROM provider_services ps
    JOIN categories        c  ON ps.category_id = c.id
    JOIN service_providers sp ON ps.provider_id  = sp.id
    JOIN users             u  ON sp.user_id      = u.id
    WHERE ps.is_available = 1
      AND sp.is_active    = 1
      AND sp.is_banned    = 0
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (ps.name LIKE ? OR ps.description LIKE ? OR c.name LIKE ? OR u.full_name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($category_id > 0) {
    $sql .= " AND ps.category_id = ?";
    $params[] = $category_id;
}

if ($min_price > 0) {
    $sql .= " AND ps.price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $sql .= " AND ps.price <= ?";
    $params[] = $max_price;
}

// Count
$count_sql  = preg_replace('/^SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql);
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_services = (int) $count_stmt->fetchColumn();
$total_pages    = max(1, ceil($total_services / $per_page));

// Sort
switch ($sort) {
    case 'price_asc':  $sql .= " ORDER BY ps.price ASC";  break;
    case 'price_desc': $sql .= " ORDER BY ps.price DESC"; break;
    case 'rating':     $sql .= " ORDER BY sp.average_rating DESC, sp.total_reviews DESC"; break;
    case 'newest':     $sql .= " ORDER BY ps.created_at DESC"; break;
    default:           $sql .= " ORDER BY sp.total_reviews DESC, sp.average_rating DESC"; break;
}

$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Stats for header
$total_stmt = $db->query("SELECT COUNT(*) FROM provider_services WHERE is_available = 1");
$grand_total = (int) $total_stmt->fetchColumn();

$cat_stmt = $db->query("SELECT COUNT(*) FROM categories");
$total_cats = (int) $cat_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services - <?php echo htmlspecialchars($platform_name); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --shadow-lg:     0 8px 32px rgba(0,0,0,0.12);
            --transition:    all 0.18s cubic-bezier(0.4,0,0.2,1);
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
            position: fixed;
            height: 100vh;
            left: 0; top: 0;
            z-index: 1000;
            transition: var(--transition);
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
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.75rem 2rem;
            min-height: 100vh;
        }

        /* ── HERO BANNER ── */
        .services-hero {
            background: linear-gradient(135deg, var(--accent) 0%, #1e40af 100%);
            border-radius: var(--radius-xl);
            padding: 2.5rem 2rem;
            color: white;
            margin-bottom: 1.75rem;
            position: relative;
            overflow: hidden;
        }
        .services-hero::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.07) 1px, transparent 0);
            background-size: 28px 28px;
        }
        .services-hero::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        }
        .services-hero h1 {
            font-size: 1.75rem; font-weight: 800; letter-spacing: -0.4px;
            margin-bottom: 0.4rem; position: relative; z-index: 1;
        }
        .services-hero p { opacity: 0.88; margin: 0; position: relative; z-index: 1; font-size: 0.92rem; }
        .hero-stats {
            display: flex; gap: 2rem; margin-top: 1.5rem;
            position: relative; z-index: 1; flex-wrap: wrap;
        }
        .hero-stat { text-align: center; }
        .hero-stat-num { font-size: 1.6rem; font-weight: 800; line-height: 1; }
        .hero-stat-lbl { font-size: 0.72rem; opacity: 0.8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }

        /* ── LAYOUT ── */
        .page-layout { display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; align-items: start; }

        /* ── FILTER SIDEBAR ── */
        .filter-sidebar {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 1.25rem;
            box-shadow: var(--shadow-xs);
            position: sticky; top: 1.75rem;
        }
        .filter-title {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 1rem;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .filter-section { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
        .filter-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .filter-section-title { font-weight: 700; font-size: 0.82rem; color: var(--text-primary); margin-bottom: 0.75rem; }
        .form-control, .form-select {
            padding: 0.55rem 0.875rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border); font-family: inherit;
            font-size: 0.85rem; color: var(--text-primary); background: var(--surface);
            transition: var(--transition); width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(13,110,253,0.08); outline: none;
        }
        .category-filter-list { display: flex; flex-direction: column; gap: 0.25rem; }
        .category-filter-btn {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0.75rem; border-radius: var(--radius-sm);
            text-decoration: none; color: var(--text-secondary);
            font-size: 0.82rem; font-weight: 500; transition: var(--transition);
            border: 1px solid transparent;
        }
        .category-filter-btn:hover { background: var(--accent-light); color: var(--accent); text-decoration: none; }
        .category-filter-btn.active { background: var(--accent); color: white; font-weight: 600; }
        .category-filter-btn i { width: 16px; font-size: 0.78rem; }
        .cat-count {
            margin-left: auto;
            background: var(--border);
            color: var(--text-muted);
            border-radius: 100px;
            padding: 0 6px;
            font-size: 0.65rem;
            font-weight: 800;
            min-width: 18px;
            height: 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .category-filter-btn.active .cat-count { background: rgba(255,255,255,0.25); color: white; }

        /* ── MAIN CONTENT AREA ── */
        .services-main {}

        .toolbar {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;
        }
        .toolbar-left { display: flex; align-items: center; gap: 0.5rem; }
        .results-count { font-size: 0.85rem; color: var(--text-secondary); }
        .results-count strong { color: var(--text-primary); }

        .sort-select {
            padding: 0.45rem 0.875rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 0.82rem;
            color: var(--text-primary);
            background: var(--surface);
            cursor: pointer;
        }

        /* ── SERVICE CARDS GRID ── */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .service-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
            text-decoration: none;
            color: inherit;
        }

        /* Card color banner */
        .service-card-banner {
            height: 6px;
            background: linear-gradient(90deg, var(--accent), #1e40af);
        }
        .service-card-banner.cat-0 { background: linear-gradient(90deg, #0d6efd, #1e40af); }
        .service-card-banner.cat-1 { background: linear-gradient(90deg, #16a34a, #166534); }
        .service-card-banner.cat-2 { background: linear-gradient(90deg, #d97706, #92400e); }
        .service-card-banner.cat-3 { background: linear-gradient(90deg, #dc2626, #991b1b); }
        .service-card-banner.cat-4 { background: linear-gradient(90deg, #7c3aed, #4c1d95); }
        .service-card-banner.cat-5 { background: linear-gradient(90deg, #0891b2, #164e63); }

        .service-card-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }

        /* Category badge */
        .service-category-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
            background: var(--accent-light); color: var(--accent);
            padding: 0.2rem 0.6rem; border-radius: 100px;
            margin-bottom: 0.6rem;
        }
        .service-category-badge i { font-size: 0.6rem; }

        /* Negotiable tag */
        .negotiable-tag {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.65rem; font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 0.2rem 0.55rem; border-radius: 100px;
            margin-left: 0.35rem;
        }

        .service-name {
            font-size: 1rem; font-weight: 800; color: var(--text-primary);
            margin-bottom: 0.45rem; letter-spacing: -0.2px; line-height: 1.3;
        }
        .service-description {
            font-size: 0.82rem; color: var(--text-secondary);
            line-height: 1.55; margin-bottom: 0.875rem;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            flex: 1;
        }

        /* Provider mini */
        .service-provider {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.6rem 0; border-top: 1px solid var(--border-subtle);
            margin-bottom: 0.75rem;
        }
        .provider-mini-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--accent); color: white; font-size: 0.8rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
        }
        .provider-mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .provider-mini-name { font-size: 0.8rem; font-weight: 700; color: var(--text-primary); }
        .provider-mini-loc { font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem; }
        .provider-mini-loc i { font-size: 0.65rem; }

        .rating-mini {
            margin-left: auto; display: flex; align-items: center; gap: 0.25rem;
            font-size: 0.75rem; font-weight: 700; color: var(--warning);
        }
        .rating-mini .reviews { color: var(--text-muted); font-weight: 500; font-size: 0.7rem; }

        /* Price & CTA footer */
        .service-card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 0.75rem; border-top: 1px solid var(--border-subtle);
        }
        .service-price {
            font-size: 1.15rem; font-weight: 800; color: var(--accent);
            font-variant-numeric: tabular-nums;
        }
        .service-price-range { font-size: 0.82rem; font-weight: 700; color: #667eea; }
        .service-price-label { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; }
        .service-duration { font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem; }
        .service-duration i { color: var(--accent); }

        .btn-book-now {
            background: var(--accent); color: white;
            border: none; border-radius: var(--radius-sm);
            padding: 0.45rem 1rem; font-size: 0.78rem; font-weight: 700;
            font-family: inherit; cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.3rem;
            transition: var(--transition); text-decoration: none;
        }
        .btn-book-now:hover { background: var(--accent-dark); color: white; transform: translateY(-1px); text-decoration: none; }

        /* Availability pill */
        .availability-pill {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.2rem 0.6rem; border-radius: 100px;
            font-size: 0.65rem; font-weight: 700;
        }
        .availability-pill.available { background: var(--success-light); color: var(--success); }
        .availability-pill.busy      { background: var(--warning-light); color: var(--warning); }
        .availability-pill.unavailable { background: var(--danger-light); color: var(--danger); }
        .availability-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 4rem 2rem; }
        .empty-state i { font-size: 3rem; color: var(--border); display: block; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.1rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.4rem; }
        .empty-state p  { font-size: 0.85rem; color: var(--text-muted); }

        /* ── PAGINATION ── */
        .pagination { display: flex; justify-content: center; gap: 0.35rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .page-btn {
            padding: 0.45rem 0.875rem; border: 1px solid var(--border);
            border-radius: var(--radius-sm); text-decoration: none;
            color: var(--text-secondary); font-size: 0.82rem; font-weight: 600;
            transition: var(--transition); display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .page-btn:hover { background: var(--accent-light); color: var(--accent); border-color: var(--accent); text-decoration: none; }
        .page-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

        /* ── MOBILE TOGGLE ── */
        .mobile-menu-toggle {
            display: none;
            position: fixed; top: 1rem; left: 1rem; z-index: 1100;
            background: var(--accent); color: white; border: none;
            border-radius: var(--radius-sm); width: 42px; height: 42px;
            align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-md);
        }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; }
        .overlay.active { display: block; }

        /* ── VERIFIED BADGE ── */
        .verified-icon { color: #16a34a; font-size: 0.75rem; margin-left: 2px; }

        /* ── SEARCH BAR in toolbar ── */
        .search-bar-inline {
            display: flex; align-items: center; gap: 0.5rem;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: 0.45rem 0.875rem;
            flex: 1; max-width: 360px;
        }
        .search-bar-inline i { color: var(--text-muted); font-size: 0.85rem; }
        .search-bar-inline input {
            border: none; outline: none; background: transparent;
            font-family: inherit; font-size: 0.85rem; color: var(--text-primary); width: 100%;
        }
        .btn-search { background: var(--accent); color: white; border: none; border-radius: var(--radius-sm); padding: 0.45rem 0.875rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: var(--transition); }
        .btn-search:hover { background: var(--accent-dark); }

        /* ── ACTIVE FILTERS ── */
        .active-filters { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-tag {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: var(--accent-light); color: var(--accent);
            border-radius: 100px; padding: 0.25rem 0.6rem;
            font-size: 0.72rem; font-weight: 700; text-decoration: none;
        }
        .filter-tag:hover { background: var(--accent); color: white; text-decoration: none; }
        .filter-tag i { font-size: 0.6rem; }

        @media (max-width: 1024px) {
            .page-layout { grid-template-columns: 1fr; }
            .filter-sidebar { position: static; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.12); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .services-grid { grid-template-columns: 1fr; }
            .hero-stats { gap: 1.25rem; }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
    <div class="overlay" id="overlay"></div>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- Hero Banner -->
        <div class="services-hero">
            <h1><i class="fas fa-concierge-bell me-2"></i> Browse Services</h1>
            <p>Discover skilled professionals ready to help you — from home repairs to personal services</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-num"><?php echo number_format($grand_total); ?></div>
                    <div class="hero-stat-lbl">Services Available</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num"><?php echo number_format($total_cats); ?></div>
                    <div class="hero-stat-lbl">Categories</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">24/7</div>
                    <div class="hero-stat-lbl">Always Available</div>
                </div>
            </div>
        </div>

        <!-- Page Layout -->
        <div class="page-layout">

            <!-- Filter Sidebar -->
            <aside class="filter-sidebar">
                <div class="filter-title"><i class="fas fa-filter"></i> Filters</div>

                <form method="GET" action="services.php" id="filterForm">

                    <!-- Search -->
                    <div class="filter-section">
                        <div class="filter-section-title">Search</div>
                        <div style="position:relative;">
                            <i class="fas fa-search" style="position:absolute;left:0.7rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;"></i>
                            <input type="text" name="search" class="form-control" style="padding-left:2rem;" placeholder="Search services..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="filter-section">
                        <div class="filter-section-title">Category</div>
                        <div class="category-filter-list">
                            <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price])); ?>"
                               class="category-filter-btn <?php echo $category_id === 0 ? 'active' : ''; ?>">
                                <i class="fas fa-th-large"></i> All Categories
                            </a>
                            <?php foreach ($categories as $i => $cat):
                                // Count services per category
                                $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM provider_services ps JOIN service_providers sp ON ps.provider_id=sp.id WHERE ps.category_id=? AND ps.is_available=1 AND sp.is_active=1 AND sp.is_banned=0");
                                $cnt_stmt->execute([$cat['id']]);
                                $cat_count = (int) $cnt_stmt->fetchColumn();
                                if ($cat_count === 0) continue;
                            ?>
                                <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'category'=>$cat['id'],'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price])); ?>"
                                   class="category-filter-btn <?php echo $category_id === (int)$cat['id'] ? 'active' : ''; ?>">
                                    <i class="fas <?php echo htmlspecialchars($cat['icon'] ?? 'fa-briefcase'); ?>"></i>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <span class="cat-count"><?php echo $cat_count; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-section">
                        <div class="filter-section-title">Price Range (RWF)</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                            <div>
                                <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;display:block;margin-bottom:0.25rem;">Min</label>
                                <input type="number" name="min_price" class="form-control" placeholder="0" value="<?php echo $min_price ?: ''; ?>" min="0">
                            </div>
                            <div>
                                <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;display:block;margin-bottom:0.25rem;">Max</label>
                                <input type="number" name="max_price" class="form-control" placeholder="Any" value="<?php echo $max_price ?: ''; ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields to preserve state -->
                    <?php if ($category_id > 0): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                    <button type="submit" class="btn-book-now w-100" style="justify-content:center;padding:0.6rem;">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>

                    <?php if (!empty($search) || $category_id || $min_price || $max_price): ?>
                        <a href="services.php" style="display:block;text-align:center;margin-top:0.5rem;font-size:0.78rem;color:var(--text-muted);text-decoration:none;">
                            <i class="fas fa-times me-1"></i> Clear all filters
                        </a>
                    <?php endif; ?>
                </form>
            </aside>

            <!-- Services Main -->
            <div class="services-main">

                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <span class="results-count">
                            <strong><?php echo number_format($total_services); ?></strong> service<?php echo $total_services !== 1 ? 's' : ''; ?> found
                            <?php if (!empty($search)): ?>
                                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <label style="font-size:0.78rem;color:var(--text-muted);font-weight:600;white-space:nowrap;">Sort by:</label>
                        <select class="sort-select" onchange="applySortChange(this.value)">
                            <option value="popular"    <?php echo $sort==='popular'    ?'selected':''; ?>>Most Popular</option>
                            <option value="rating"     <?php echo $sort==='rating'     ?'selected':''; ?>>Highest Rated</option>
                            <option value="price_asc"  <?php echo $sort==='price_asc'  ?'selected':''; ?>>Price: Low → High</option>
                            <option value="price_desc" <?php echo $sort==='price_desc' ?'selected':''; ?>>Price: High → Low</option>
                            <option value="newest"     <?php echo $sort==='newest'     ?'selected':''; ?>>Newest First</option>
                        </select>
                    </div>
                </div>

                <!-- Active filter tags -->
                <?php if (!empty($search) || $category_id || $min_price || $max_price): ?>
                    <div class="active-filters">
                        <?php if (!empty($search)): ?>
                            <a href="?<?php echo http_build_query(array_filter(['category'=>$category_id,'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price])); ?>" class="filter-tag">
                                <i class="fas fa-times"></i> "<?php echo htmlspecialchars($search); ?>"
                            </a>
                        <?php endif; ?>
                        <?php if ($category_id): ?>
                            <?php $cat_name = array_values(array_filter($categories, fn($c) => (int)$c['id'] === $category_id))[0]['name'] ?? ''; ?>
                            <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price])); ?>" class="filter-tag">
                                <i class="fas fa-times"></i> <?php echo htmlspecialchars($cat_name); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($min_price || $max_price): ?>
                            <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'category'=>$category_id,'sort'=>$sort])); ?>" class="filter-tag">
                                <i class="fas fa-times"></i> Price filter
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Services Grid -->
                <?php if (empty($services)): ?>
                    <div class="empty-state" style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);">
                        <i class="fas fa-search"></i>
                        <h3>No services found</h3>
                        <p>Try adjusting your filters or search term to find what you're looking for.</p>
                        <a href="services.php" class="btn-book-now" style="margin-top:1rem;display:inline-flex;">
                            <i class="fas fa-refresh"></i> Reset Filters
                        </a>
                    </div>
                <?php else: ?>
                    <div class="services-grid">
                        <?php foreach ($services as $i => $service): ?>
                            <div class="service-card">
                                <div class="service-card-banner cat-<?php echo $i % 6; ?>"></div>
                                <div class="service-card-body">
                                    <!-- Category badge -->
                                    <div>
                                        <span class="service-category-badge">
                                            <i class="fas <?php echo htmlspecialchars($service['category_icon'] ?? 'fa-briefcase'); ?>"></i>
                                            <?php echo htmlspecialchars($service['category_name']); ?>
                                        </span>
                                        <?php if ($service['negotiable']): ?>
                                            <span class="negotiable-tag">
                                                <i class="fas fa-handshake"></i> Negotiable
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Service name & description -->
                                    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                    <div class="service-description">
                                        <?php echo htmlspecialchars($service['description'] ?: 'Professional service offered by a verified local provider.'); ?>
                                    </div>

                                    <!-- Provider mini -->
                                    <div class="service-provider">
                                        <div class="provider-mini-avatar">
                                            <?php if (!empty($service['provider_image'])): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($service['provider_image']); ?>"
                                                     alt="<?php echo htmlspecialchars($service['provider_name']); ?>"
                                                     onerror="this.style.display='none';this.parentNode.textContent='<?php echo strtoupper(substr($service['provider_name'],0,1)); ?>';">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($service['provider_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="provider-mini-name">
                                                <?php echo htmlspecialchars($service['provider_name']); ?>
                                                <?php if ($service['is_verified'] || $service['user_verified']): ?>
                                                    <i class="fas fa-check-circle verified-icon" title="Verified Provider"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="provider-mini-loc">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($service['location']); ?>
                                            </div>
                                        </div>
                                        <div class="rating-mini">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($service['average_rating'], 1); ?>
                                            <span class="reviews">(<?php echo $service['total_reviews']; ?>)</span>
                                        </div>
                                    </div>

                                    <!-- Price & CTA -->
                                    <div class="service-card-footer">
                                        <div>
                                            <?php if ($service['negotiable'] && $service['min_price'] && $service['max_price']): ?>
                                                <div class="service-price-range">
                                                    RWF <?php echo number_format($service['min_price'],0); ?> – <?php echo number_format($service['max_price'],0); ?>
                                                </div>
                                                <div class="service-price-label">Negotiable price range</div>
                                            <?php else: ?>
                                                <div class="service-price">RWF <?php echo number_format($service['price'],0); ?></div>
                                                <div class="service-duration">
                                                    <i class="fas fa-clock"></i> <?php echo (int)$service['duration']; ?> mins
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.35rem;">
                                            <span class="availability-pill <?php echo htmlspecialchars($service['availability'] ?? 'available'); ?>">
                                                <?php echo ucfirst($service['availability'] ?? 'Available'); ?>
                                            </span>
                                            <a href="provider-profile.php?id=<?php echo $service['provider_id']; ?>&service_id=<?php echo $service['id']; ?>"
                                               class="btn-book-now">
                                                <i class="fas fa-calendar-plus"></i> Book Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'category'=>$category_id,'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price,'page'=>$page-1])); ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i> Prev
                                </a>
                            <?php endif; ?>
                            <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
                                <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'category'=>$category_id,'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price,'page'=>$p])); ?>"
                                   class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_filter(['search'=>$search,'category'=>$category_id,'sort'=>$sort,'min_price'=>$min_price,'max_price'=>$max_price,'page'=>$page+1])); ?>" class="page-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div><!-- /services-main -->
        </div><!-- /page-layout -->
    </div><!-- /main-content -->

    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar       = document.getElementById('providerSidebar') || document.querySelector('.sidebar');
        const overlay       = document.getElementById('overlay');

        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            });
        }
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar && sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Sort dropdown — preserve all current filters
        function applySortChange(sortValue) {
            const url  = new URL(window.location.href);
            url.searchParams.set('sort', sortValue);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        // Auto-submit filter form on category click (already done via links)
        // Keyboard shortcut: press / to focus search in filter sidebar
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                document.querySelector('input[name="search"]')?.focus();
            }
        });
    </script>
</body>
</html>