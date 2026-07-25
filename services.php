<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

// Load platform settings
function getPlatformSetting($key, $default = '') {
    global $db;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    return $settings[$key] ?? $default;
}

$db = Database::getInstance()->getConnection();

// Get platform settings
$platform_name = getPlatformSetting('platform_name', 'BII LocalFinder');
$contact_email = getPlatformSetting('contact_email', 'info@biilocalfinder.com');
$contact_phone = getPlatformSetting('contact_phone', '+250 788 000 000');
$copyright_text = getPlatformSetting('copyright_text', '© 2024 BII LocalFinder. All rights reserved.');
$platform_description = getPlatformSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

// Check registration settings
$provider_registration_enabled = getPlatformSetting('provider_registration', '1');

// Get all active categories with provider count
// Get all active categories (provider counts removed)
$stmt = $db->query("SELECT id, name, description, icon, is_premium, monthly_fee FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll();

// Get platform statistics (only active and verified providers)
$total_providers = $db->query("
    SELECT COUNT(*) 
    FROM service_providers sp 
    JOIN users u ON sp.user_id = u.id 
    WHERE sp.is_active = 1 AND sp.is_banned = 0 AND u.is_verified = 1
")->fetchColumn();

$total_services = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();

// Get featured providers count
$featured_providers = $db->query("
    SELECT COUNT(*) 
    FROM service_providers 
    WHERE is_featured = 1 AND is_active = 1 AND is_banned = 0
")->fetchColumn();

// Get premium categories count
$premium_categories = $db->query("
    SELECT COUNT(*) 
    FROM categories 
    WHERE is_premium = 1 AND is_active = 1
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('services.page_title', [], 'services'); ?> - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0dcaf0;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --text-muted: #94a3b8;
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
            --shadow-sm: 0 1px 3px rgba(15,23,42,0.06), 0 1px 2px rgba(15,23,42,0.04);
            --shadow-md: 0 4px 16px rgba(15,23,42,0.08), 0 2px 6px rgba(15,23,42,0.05);
            --shadow-lg: 0 12px 40px rgba(15,23,42,0.10), 0 4px 12px rgba(15,23,42,0.06);
            --shadow-primary: 0 8px 24px rgba(37,99,235,0.22);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--dark);
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }

        h1,h2,h3,h4,h5,h6,
        .navbar-brand, .section-title, .step-item h4 {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

        /* ── NAVBAR ── */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226,232,240,0.8);
            padding: 0.85rem 0;
            transition: box-shadow 0.3s ease;
        }

        .navbar-brand {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
            text-decoration: none;
        }

        .navbar .nav-link {
            font-weight: 500;
            font-size: 0.93rem;
            color: var(--secondary) !important;
            padding: 0.5rem 0.9rem !important;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-light);
        }

        .navbar .btn-primary {
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.5rem 1.3rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-primary);
            transition: all 0.25s ease;
            border: none;
        }

        .navbar .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.30);
        }

        .navbar-toggler {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.65rem;
        }

        /* ── HERO ── */
        .hero-services {
            background: linear-gradient(rgba(13,47,120,0.82), rgba(10,35,100,0.88)),
                        url('assets/images/services.jpg') center/cover no-repeat;
            color: white;
            padding: 100px 0 80px;
            text-align: center;
            min-height: 420px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .hero-services::after {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 36px 36px;
            z-index: -1;
        }

        .hero-services .container { position: relative; z-index: 2; }

        .hero-services h1 {
            font-size: clamp(2rem, 4.5vw, 3.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 1rem;
        }

        .hero-services .lead {
            font-size: 1.1rem;
            opacity: 0.88;
            max-width: 540px;
            margin: 0 auto 1.75rem;
            line-height: 1.65;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.22);
            backdrop-filter: blur(8px);
            padding: 0.45rem 1rem;
            border-radius: 100px;
            margin: 0.3rem;
            font-size: 0.87rem;
            font-weight: 600;
            color: rgba(255,255,255,0.95);
        }

        /* ── SECTION HEADERS ── */
        .section-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: clamp(1.75rem, 3.5vw, 2.4rem);
            font-weight: 800;
            margin-bottom: 0.85rem;
            color: var(--dark);
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .section-subtitle {
            font-size: 1.05rem;
            color: var(--secondary);
            margin-bottom: 3rem;
            max-width: 620px;
            line-height: 1.7;
        }

        /* ── SERVICE CARDS ── */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .service-card {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            height: 100%;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .service-card:hover::before { opacity: 1; }

        .service-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.4rem;
            font-size: 1.6rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .service-card:hover .service-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.08) rotate(-3deg);
            box-shadow: 0 8px 20px rgba(37,99,235,0.28);
        }

        .service-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.6rem;
        }

        .service-features {
            list-style: none;
            padding: 0;
            margin: 1.25rem 0 0 0;
        }

        .service-features li {
            padding: 0.45rem 0;
            color: var(--secondary);
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border-light);
            line-height: 1.5;
        }

        .service-features li:last-child { border-bottom: none; }

        .service-features li i {
            color: var(--success);
            font-size: 0.9rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* ── CATEGORY GRID ── */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .category-card {
            background: var(--surface);
            padding: 1.75rem 1.5rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            display: block;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .category-card:hover {
            transform: translateY(-6px) scale(1.01);
            text-decoration: none;
            color: inherit;
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .category-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, rgba(37,99,235,0.09), rgba(30,64,175,0.05));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.7rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .category-card:hover .category-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.1) rotate(-3deg);
            box-shadow: 0 8px 20px rgba(37,99,235,0.28);
        }

        .category-card h4 {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--dark);
            margin-bottom: 0.4rem;
        }

        .category-card p {
            font-size: 0.83rem;
            color: var(--secondary);
            line-height: 1.55;
            margin-bottom: 0;
        }

        .premium-badge {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.25rem 0.65rem;
            border-radius: 100px;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .provider-count {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.35rem 0.85rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 0.75rem;
        }

        /* ── HOW IT WORKS ── */
        .how-it-works {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 5.5rem 0;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .how-it-works::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 32px 32px;
            z-index: -1;
        }

        .how-it-works::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            z-index: -1;
        }

        .how-it-works .section-title { color: white; }
        .how-it-works .section-subtitle { color: rgba(255,255,255,0.75); }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-top: 3rem;
        }

        .step-item {
            text-align: center;
            padding: 2rem 1.25rem;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(4px);
            transition: all 0.3s ease;
        }

        .step-item:hover {
            background: rgba(255,255,255,0.13);
            transform: translateY(-4px);
        }

        .step-number {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.35rem;
            font-weight: 800;
            border: 2px solid rgba(255,255,255,0.35);
            position: relative;
        }

        .step-number::after {
            content: '';
            position: absolute; inset: -5px;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.15);
        }

        .step-item h4 {
            margin-bottom: 0.65rem;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            letter-spacing: -0.01em;
        }

        .step-item p {
            opacity: 0.82;
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.65;
        }

        /* ── CTA SECTION ── */
        .cta-section { padding: 5rem 0; background: var(--surface-2); }

        .cta-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 4rem 3rem;
            border-radius: var(--radius-xl);
            text-align: center;
            position: relative;
            overflow: hidden;
            isolation: isolate;
            box-shadow: var(--shadow-primary);
        }

        .cta-box::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 30px 30px;
            z-index: -1;
        }

        .cta-box::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -80px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
            z-index: -1;
        }

        .cta-box h2 {
            font-size: clamp(1.75rem, 3.5vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
        }

        .cta-box p { opacity: 0.9; font-size: 1.05rem; line-height: 1.65; }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .cta-buttons .btn {
            padding: 0.85rem 2rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            transition: all 0.25s ease;
        }

        .cta-buttons .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .cta-buttons .btn-outline-light {
            border-width: 1.5px;
        }

        .cta-buttons .btn-outline-light:hover {
            transform: translateY(-2px);
        }

        /* ── FOOTER ── */
        .footer {
            background: var(--dark) !important;
            padding: 4.5rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: white;
            margin-bottom: 1.25rem;
        }

        .footer a.text-muted, .footer .text-muted {
            color: #64748b !important;
            font-size: 0.9rem;
            transition: color 0.2s ease;
            text-decoration: none;
        }

        .footer a.text-muted:hover { color: white !important; }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.07) !important;
            color: #475569;
            font-size: 0.88rem;
        }

        .social-links { display: flex; gap: 0.6rem; margin-top: 1rem; }

        .social-links a {
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-sm);
            color: #94a3b8;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.25s ease;
        }

        .social-links a:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37,99,235,0.3);
        }

        .bg-light-custom { background: var(--surface-2) !important; }

        /* ── ANIMATIONS ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .hero-services { padding: 70px 0 55px; min-height: auto; }
            .section-title { font-size: 1.8rem; }
            .services-grid { grid-template-columns: 1fr; }
            .categories-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
            .service-card { padding: 1.5rem; }
            .cta-box { padding: 2.5rem 1.5rem; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .cta-buttons .btn { width: 100%; max-width: 260px; }
            .stats-badge { margin: 0.2rem; font-size: 0.82rem; }
        }

        @media (max-width: 576px) {
            .steps-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 400px) {
            .steps-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <span style="width:32px;height:32px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:9px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-map-marked-alt text-white" style="font-size:0.85rem;"></i>
                </span>
                <?php echo htmlspecialchars($platform_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-1">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="providers.php">Find Providers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/home.php'; ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Sign In</a>
                        </li>
                        <li class="nav-item ms-1">
                            <a class="btn btn-primary" href="register.php">Get Started &rarr;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-services">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 mb-4" style="background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.24);border-radius:100px;padding:0.4rem 1.1rem;backdrop-filter:blur(8px);">
                        <span style="width:8px;height:8px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 0 3px rgba(16,185,129,0.3);"></span>
                        <span style="font-size:0.8rem;font-weight:700;letter-spacing:0.05em;color:rgba(255,255,255,0.95);">EXPLORE OUR PLATFORM</span>
                    </div>
                    <h1 class="display-4 fw-bold mb-3"><?php echo __('services.hero.title', [], 'services'); ?></h1>
                    <p class="lead mb-4"><?php echo __('services.hero.subtitle', [], 'services'); ?></p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <span class="stats-badge">
                            <i class="fas fa-users"></i>
                            <?php echo number_format($total_providers); ?>+ Verified Providers
                        </span>
                        <span class="stats-badge">
                            <i class="fas fa-star"></i>
                            <?php echo number_format($featured_providers); ?>+ Featured
                        </span>
                        <span class="stats-badge">
                            <i class="fas fa-tools"></i>
                            <?php echo number_format(count($categories)); ?>+ Categories
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services for Clients -->
    <section class="py-5">
        <div class="container">
            <div class="text-center">
                <div class="section-eyebrow"><i class="fas fa-users"></i> For Clients</div>
                <h2 class="section-title"><?php echo __('services.for_clients.title', [], 'services'); ?></h2>
                <p class="section-subtitle mx-auto"><?php echo __('services.for_clients.subtitle', [], 'services'); ?></p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_clients.find_providers.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_clients.find_providers.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.find_providers.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.find_providers.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.find_providers.feature_3', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_clients.view_profiles.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_clients.view_profiles.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.view_profiles.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.view_profiles.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.view_profiles.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.view_profiles.feature_4', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.view_profiles.feature_5', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_clients.filter_search.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_clients.filter_search.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.filter_search.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.filter_search.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.filter_search.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.filter_search.feature_4', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.filter_search.feature_5', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_clients.ratings_reviews.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_clients.ratings_reviews.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.ratings_reviews.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.ratings_reviews.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.ratings_reviews.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.ratings_reviews.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_clients.booking_system.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_clients.booking_system.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.booking_system.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.booking_system.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.booking_system.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.booking_system.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_clients.customer_support.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_clients.customer_support.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.customer_support.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.customer_support.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.customer_support.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_clients.customer_support.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Services for Providers -->
    <section class="py-5 bg-light-custom">
        <div class="container">
            <div class="text-center">
                <div class="section-eyebrow"><i class="fas fa-briefcase"></i> For Providers</div>
                <h2 class="section-title"><?php echo __('services.for_providers.title', [], 'services'); ?></h2>
                <p class="section-subtitle mx-auto"><?php echo __('services.for_providers.subtitle', [], 'services'); ?></p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_providers.create_profile.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_providers.create_profile.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.create_profile.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.create_profile.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.create_profile.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.create_profile.feature_4', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.create_profile.feature_5', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.create_profile.feature_6', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_providers.get_clients.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_providers.get_clients.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.get_clients.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.get_clients.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.get_clients.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.get_clients.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_providers.manage_availability.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_providers.manage_availability.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.manage_availability.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.manage_availability.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.manage_availability.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.manage_availability.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_providers.build_reputation.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_providers.build_reputation.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.build_reputation.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.build_reputation.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.build_reputation.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.build_reputation.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_providers.job_requests.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_providers.job_requests.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.job_requests.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.job_requests.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.job_requests.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.job_requests.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="h4 mb-3"><?php echo __('services.for_providers.track_performance.title', [], 'services'); ?></h3>
                    <p class="text-muted"><?php echo __('services.for_providers.track_performance.description', [], 'services'); ?></p>
                    <ul class="service-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.track_performance.feature_1', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.track_performance.feature_2', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.track_performance.feature_3', [], 'services'); ?></li>
                        <li><i class="fas fa-check-circle"></i> <?php echo __('services.for_providers.track_performance.feature_4', [], 'services'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Categories -->
    <section class="py-5" id="service-categories">
        <div class="container">
            <div class="text-center">
                <div class="section-eyebrow"><i class="fas fa-th-large"></i> Browse Categories</div>
                <h2 class="section-title"><?php echo __('services.categories.title', [], 'services'); ?></h2>
                <p class="section-subtitle mx-auto"><?php echo sprintf(__('services.categories.subtitle', [], 'services'), number_format($total_providers), number_format(count($categories))); ?></p>
            </div>
            
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <a href="providers.php?category=<?php echo $category['id']; ?>" class="category-card">
                        <?php if ($category['is_premium']): ?>
                            <span class="premium-badge">
                                <i class="fas fa-crown me-1"></i> PREMIUM
                            </span>
                        <?php endif; ?>
                        <div class="category-icon">
                            <i class="fas <?php echo $category['icon']; ?>"></i>
                        </div>
                        <h4 class="h5 mb-2"><?php echo htmlspecialchars($category['name']); ?></h4>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($category['description']); ?></p>
                        
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center">
                        <div class="section-eyebrow" style="background:rgba(255,255,255,0.18);color:rgba(255,255,255,0.95);border:1px solid rgba(255,255,255,0.25);">
                            <i class="fas fa-route"></i> Simple Process
                        </div>
                        <h2 class="section-title text-white"><?php echo __('services.how_it_works.title', [], 'services'); ?></h2>
                        <p class="section-subtitle text-white opacity-75 mx-auto"><?php echo __('services.how_it_works.subtitle', [], 'services'); ?></p>
                    </div>
                    
                    <div class="steps-grid">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <h4><?php echo __('services.how_it_works.step_1.title', [], 'services'); ?></h4>
                            <p><?php echo __('services.how_it_works.step_1.description', [], 'services'); ?></p>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <h4><?php echo __('services.how_it_works.step_2.title', [], 'services'); ?></h4>
                            <p><?php echo __('services.how_it_works.step_2.description', [], 'services'); ?></p>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <h4><?php echo __('services.how_it_works.step_3.title', [], 'services'); ?></h4>
                            <p><?php echo __('services.how_it_works.step_3.description', [], 'services'); ?></p>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">4</div>
                            <h4><?php echo __('services.how_it_works.step_4.title', [], 'services'); ?></h4>
                            <p><?php echo __('services.how_it_works.step_4.description', [], 'services'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-box">
                <h2 class="display-5 fw-bold mb-3"><?php echo __('services.cta.title', [], 'services'); ?></h2>
                <p class="fs-5 mb-4"><?php echo sprintf(__('services.cta.subtitle', [], 'services'), htmlspecialchars($platform_name)); ?></p>
                <div class="cta-buttons">
                    <a href="providers.php" class="btn btn-light btn-lg">
                        <i class="fas fa-search me-2"></i> <?php echo __('services.cta.find_button', [], 'services'); ?>
                    </a>
                    <?php if ($provider_registration_enabled): ?>
                        <a href="register.php?type=provider" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i> <?php echo __('services.cta.register_button', [], 'services'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <p class="mt-4 mb-0 fs-6 opacity-75">
                    <strong><?php echo number_format($total_services); ?>+</strong> <?php echo __('services.cta.success_text', [], 'services'); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <h5><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($platform_description); ?></p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="about.php" class="text-muted text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="services.php" class="text-muted text-decoration-none">Services</a></li>
                        <li class="mb-2"><a href="providers.php" class="text-muted text-decoration-none">Find Providers</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>For Providers</h5>
                    <ul class="list-unstyled">
                        <?php if ($provider_registration_enabled): ?>
                            <li class="mb-2"><a href="register.php?type=provider" class="text-muted text-decoration-none">Register</a></li>
                        <?php endif; ?>
                        <li class="mb-2"><a href="login.php" class="text-muted text-decoration-none">Login</a></li>
                        <li class="mb-2"><a href="about.php" class="text-muted text-decoration-none">How It Works</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>Contact Us</h5>
                    <p class="text-muted mb-2"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($contact_email); ?></p>
                    <p class="text-muted"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($contact_phone); ?></p>
                </div>
            </div>
            <div class="footer-bottom border-top pt-4 mt-4 text-center">
                <p class="mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 20
                ? '0 4px 24px rgba(15,23,42,0.10)'
                : 'none';
        }, { passive: true });

        // Scroll-reveal cards
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            document.querySelectorAll('.service-card, .category-card, .step-item').forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `opacity 0.5s cubic-bezier(0.16,1,0.3,1) ${(i % 3) * 0.08}s, transform 0.5s cubic-bezier(0.16,1,0.3,1) ${(i % 3) * 0.08}s`;
                observer.observe(el);
            });
        });
    </script>
</body>
</html>