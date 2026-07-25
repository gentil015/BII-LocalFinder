<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();

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

// Get platform information
$platform_name = getPlatformSetting('platform_name', 'BII LocalFinder');
$contact_email = getPlatformSetting('contact_email', 'info@biilocalfinder.com');
$contact_phone = getPlatformSetting('contact_phone', '+250 788 000 000');
$copyright_text = getPlatformSetting('copyright_text', '© 2024 BII LocalFinder. All rights reserved.');
$platform_description = getPlatformSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

// Check if provider registration is enabled
$provider_registration_enabled = getPlatformSetting('provider_registration', '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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

        h1,h2,h3,h4,h5,h6,.navbar-brand {
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
        }

        .navbar-brand {
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

        .navbar .nav-link:hover, .navbar .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-light);
        }

        .navbar .btn-primary {
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.5rem 1.3rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-primary);
            border: none;
            transition: all 0.25s ease;
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
        .hero-about {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 100px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .hero-about::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 32px 32px;
            z-index: -1;
        }

        .hero-about::after {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 360px; height: 360px;
            background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 65%);
            z-index: -1;
        }

        .hero-about h1 {
            font-size: clamp(2rem, 4.5vw, 3.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 1rem;
        }

        .hero-about .lead {
            font-size: 1.1rem;
            opacity: 0.88;
            max-width: 540px;
            margin: 0 auto;
            line-height: 1.65;
        }

        /* ── SECTION HEADERS ── */
        .section-eyebrow {
            display: inline-flex;
            align-items: center; gap: 0.5rem;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            margin-bottom: 0.85rem;
        }

        .section-title {
            font-size: clamp(1.6rem, 3.2vw, 2.3rem);
            font-weight: 800;
            margin-bottom: 0.75rem;
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

        /* ── CONTENT BOX ── */
        .content-box {
            background: var(--surface);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .content-box p { font-size: 1rem; line-height: 1.75; color: var(--secondary); }
        .content-box p strong { color: var(--dark); }

        /* ── CUSTOM LIST ── */
        .custom-list {
            list-style: none;
            padding-left: 0;
            margin: 1.25rem 0;
        }

        .custom-list li {
            padding: 0.6rem 0 0.6rem 2.25rem;
            color: var(--secondary);
            line-height: 1.65;
            font-size: 0.97rem;
            position: relative;
            border-bottom: 1px solid var(--border-light);
        }

        .custom-list li:last-child { border-bottom: none; }

        .custom-list li::before {
            content: '';
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            width: 22px; height: 22px;
            background: linear-gradient(135deg, var(--success), #059669);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }

        .custom-list li::after {
            content: '✓';
            position: absolute;
            left: 5px; top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .custom-list li strong { color: var(--dark); font-weight: 700; }

        /* ── MISSION GRID ── */
        .mission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .mission-card {
            background: var(--surface);
            padding: 2rem 1.75rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .mission-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mission-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .mission-card:hover::before { opacity: 1; }

        .mission-icon {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.4rem;
            font-size: 1.8rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .mission-card:hover .mission-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.1) rotate(-4deg);
            box-shadow: 0 8px 20px rgba(37,99,235,0.28);
        }

        .mission-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.6rem;
        }

        .mission-card p { font-size: 0.9rem; color: var(--secondary); line-height: 1.65; margin: 0; }

        /* ── STATS SECTION ── */
        .stats-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 5.5rem 0;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .stats-section::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 30px 30px;
            z-index: -1;
        }

        .stats-section::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -60px;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
            z-index: -1;
        }

        .stat-item { text-align: center; padding: 1.5rem 1rem; }

        .stat-number {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            margin-bottom: 0.4rem;
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.85;
            font-weight: 500;
        }

        .stat-divider {
            width: 1px;
            background: rgba(255,255,255,0.18);
            align-self: stretch;
            margin: 1rem 0;
        }

        /* ── FEATURE CARDS ── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 1.5rem;
        }

        .feature-card {
            background: var(--surface);
            padding: 1.75rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-sm);
            height: 100%;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }

        .feature-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, rgba(37,99,235,0.09), rgba(30,64,175,0.05));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.08) rotate(-3deg);
            box-shadow: 0 6px 16px rgba(37,99,235,0.25);
        }

        .feature-card h4 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.5rem;
        }

        .feature-card p { font-size: 0.88rem; color: var(--secondary); line-height: 1.65; margin: 0; }

        /* ── CONTACT CARDS ── */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.5rem;
        }

        .contact-card {
            background: var(--surface);
            padding: 2rem 1.75rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }

        .contact-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .contact-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.5rem;
            color: white;
            box-shadow: var(--shadow-primary);
            transition: transform 0.3s ease;
        }

        .contact-card:hover .contact-icon { transform: scale(1.08) rotate(-4deg); }

        .contact-card h4 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.6rem;
        }

        .contact-card p, .contact-card a {
            font-size: 0.9rem;
            color: var(--secondary);
            line-height: 1.65;
            margin: 0;
            text-decoration: none;
        }

        .contact-card a:hover { color: var(--primary); }

        /* ── SOCIAL LINKS ── */
        .social-links { display: flex; gap: 0.6rem; justify-content: center; margin-top: 1rem; }

        .social-links a {
            width: 38px; height: 38px;
            background: rgba(37,99,235,0.08);
            border: 1.5px solid rgba(37,99,235,0.18);
            border-radius: var(--radius-sm);
            color: var(--primary);
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

        /* footer social links variant */
        .footer-social a {
            background: rgba(255,255,255,0.07);
            border-color: rgba(255,255,255,0.1);
            color: #94a3b8;
        }

        .footer-social a:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* ── CTA BOX ── */
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
            background-size: 28px 28px;
            z-index: -1;
        }

        .cta-box::after {
            content: '';
            position: absolute;
            bottom: -70px; left: -70px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
            z-index: -1;
        }

        .cta-box h2 {
            font-size: clamp(1.75rem, 3.5vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.85rem;
        }

        .cta-box p { font-size: 1.05rem; opacity: 0.9; margin-bottom: 0; line-height: 1.65; }

        .btn-white {
            background: white;
            color: var(--primary);
            padding: 0.85rem 2rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex; align-items: center; gap: 0.5rem;
            transition: all 0.25s ease;
            border: none;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }

        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0,0,0,0.22);
            color: var(--primary);
        }

        /* ── FOOTER ── */
        .footer {
            background: var(--dark) !important;
            padding: 4.5rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: white;
            margin-bottom: 1.25rem;
        }

        .footer a.text-muted, .footer .text-muted { color: #64748b !important; font-size: 0.9rem; }
        .footer a.text-muted:hover { color: white !important; }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.07) !important;
            color: #475569;
            font-size: 0.88rem;
        }

        .bg-light-custom { background: var(--surface-2) !important; }

        /* ── PROBLEM/SOLUTION CALLOUT ── */
        .challenge-callout {
            background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(239,68,68,0.02));
            border: 1.5px solid rgba(239,68,68,0.18);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }

        .challenge-callout i {
            color: var(--danger);
            font-size: 1.1rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .hero-about { padding: 70px 0 55px; }
            .section-title { font-size: 1.75rem; }
            .content-box { padding: 1.5rem; }
            .cta-box { padding: 2.5rem 1.5rem; }
            .mission-grid, .features-grid, .contact-grid { grid-template-columns: 1fr; }
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="providers.php">Find Providers</a></li>
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/home.php'; ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Sign In</a></li>
                        <li class="nav-item ms-1">
                            <a class="btn btn-primary" href="register.php">Get Started &rarr;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-about">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 mb-4" style="background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.24);border-radius:100px;padding:0.4rem 1.1rem;backdrop-filter:blur(8px);">
                        <span style="width:8px;height:8px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 0 3px rgba(16,185,129,0.3);"></span>
                        <span style="font-size:0.8rem;font-weight:700;letter-spacing:0.05em;color:rgba(255,255,255,0.95);">OUR STORY</span>
                    </div>
                    <h1 class="mb-3">About <?php echo htmlspecialchars($platform_name); ?></h1>
                    <p class="lead mb-0"><?php echo htmlspecialchars($platform_description); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Who We Are -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-4">
                        <div class="section-eyebrow"><i class="fas fa-info-circle"></i> Who We Are</div>
                        <h2 class="section-title">The Platform Behind the Mission</h2>
                    </div>
                    <div class="content-box">
                        <p class="fs-5">
                            <strong><?php echo htmlspecialchars($platform_name); ?></strong> is a revolutionary digital platform developed by <strong>BII Technologies</strong> that connects Rwandan residents with trusted local service providers. Whether you need an electrician, plumber, cleaner, mechanic, carpenter, or any other skilled professional, we make it easy to find the right person for the job.
                        </p>
                        <p class="fs-5 mb-0">
                            Our platform bridges the gap between skilled workers and clients who need their services, creating a transparent, efficient, and reliable marketplace for local services across Rwanda.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Mission -->
    <section class="py-5 bg-light-custom">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow"><i class="fas fa-bullseye"></i> Mission & Vision</div>
                <h2 class="section-title">Our Mission & Vision</h2>
                <p class="section-subtitle mx-auto">We're on a mission to transform how Rwandans connect with local service providers</p>
            </div>
            <div class="mission-grid">
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="h4 mb-3">Easy Discovery</h3>
                    <p class="text-muted">Make it effortless for people to find reliable, skilled service providers in their area quickly and efficiently.</p>
                </div>
                
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="h4 mb-3">Empower Workers</h3>
                    <p class="text-muted">Help skilled workers gain more visibility, connect with clients, and grow their businesses sustainably.</p>
                </div>
                
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="h4 mb-3">Build Trust</h3>
                    <p class="text-muted">Promote transparency and trust through verified profiles, ratings, and genuine client reviews.</p>
                </div>
                
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="h4 mb-3">Economic Growth</h3>
                    <p class="text-muted">Contribute to local economic development by creating opportunities and reducing unemployment.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row g-0 justify-content-center">
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">500<span style="font-size:0.6em;opacity:0.7;">+</span></div>
                        <div class="stat-label">Verified Providers</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item" style="border-left:1px solid rgba(255,255,255,0.18);">
                        <div class="stat-number">30<span style="font-size:0.6em;opacity:0.7;">+</span></div>
                        <div class="stat-label">Districts Covered</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item" style="border-left:1px solid rgba(255,255,255,0.18);">
                        <div class="stat-number">2K<span style="font-size:0.6em;opacity:0.7;">+</span></div>
                        <div class="stat-label">Completed Bookings</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item" style="border-left:1px solid rgba(255,255,255,0.18);">
                        <div class="stat-number">4.8<span style="font-size:0.6em;opacity:0.7;">★</span></div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- The Problem We Solve -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-4">
                        <div class="section-eyebrow"><i class="fas fa-exclamation-circle"></i> The Challenge</div>
                        <h2 class="section-title">The Problem We Solve</h2>
                        <p class="section-subtitle mx-auto">Understanding the challenges in Rwanda's service industry</p>
                    </div>
                    
                    <div class="content-box">
                        <div class="challenge-callout">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong style="color:var(--dark);font-size:0.97rem;">The Challenge</strong>
                                <p class="mb-0 mt-1" style="font-size:0.92rem;color:var(--secondary);">
                                    In Rwanda, especially in cities like Rubavu, Musanze, and parts of Kigali, people face significant challenges finding nearby skilled service providers. Most residents rely on word of mouth and personal recommendations, which is:
                                </p>
                            </div>
                        </div>
                        <ul class="custom-list fs-5">
                            <li><strong>Time-consuming:</strong> Takes days or weeks to find someone reliable</li>
                            <li><strong>Unreliable:</strong> No way to verify skills or read reviews beforehand</li>
                            <li><strong>Limited:</strong> Only aware of providers within immediate social circle</li>
                            <li><strong>Inefficient:</strong> Skilled workers struggle to reach potential clients</li>
                        </ul>
                        <p style="font-size:0.97rem;color:var(--secondary);margin:0;line-height:1.75;">
                            As a result, many qualified professionals struggle to get clients, while customers waste valuable time searching for trusted service providers.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Solution -->
    <section class="py-5 bg-light-custom">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow"><i class="fas fa-lightbulb"></i> Our Solution</div>
                <h2 class="section-title">A Platform Built to Solve It</h2>
                <p class="section-subtitle mx-auto">A comprehensive digital platform that transforms service discovery</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search-location"></i>
                    </div>
                    <h4 class="h5 mb-3">Smart Search & Filters</h4>
                    <p class="text-muted">Find providers by service type, location, rating, availability, and more with our advanced search system.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h4 class="h5 mb-3">Detailed Profiles</h4>
                    <p class="text-muted">View comprehensive provider profiles with skills, experience, pricing, and verified contact information.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h4 class="h5 mb-3">Ratings & Reviews</h4>
                    <p class="text-muted">Read authentic reviews from real clients and make informed decisions based on community feedback.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4 class="h5 mb-3">Easy Booking</h4>
                    <p class="text-muted">Book services directly through the platform with clear communication and scheduling.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4 class="h5 mb-3">Location-Based</h4>
                    <p class="text-muted">Find providers near you with our intelligent location matching and distance calculation.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="h5 mb-3">Verified Providers</h4>
                    <p class="text-muted">All service providers go through verification to ensure quality and reliability.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Who We Serve -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow"><i class="fas fa-users"></i> Who We Serve</div>
                <h2 class="section-title">Who We Serve</h2>
                <p class="section-subtitle mx-auto">Our platform is designed for everyone in Rwanda's service ecosystem</p>
            </div>
            <div class="mission-grid">
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3 class="h4 mb-3">Residents</h3>
                    <p class="text-muted">Homeowners and renters seeking skilled workers for home repairs, maintenance, and improvements.</p>
                </div>
                
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="h4 mb-3">Service Providers</h3>
                    <p class="text-muted">Electricians, plumbers, cleaners, mechanics, and other professionals looking to expand their client base.</p>
                </div>
                
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="h4 mb-3">Small Businesses</h3>
                    <p class="text-muted">Companies managing technical staff and seeking reliable service providers for various needs.</p>
                </div>
                
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h3 class="h4 mb-3">Community Managers</h3>
                    <p class="text-muted">Administrators tracking service reliability and quality in their communities.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-5 bg-light-custom">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow"><i class="fas fa-trophy"></i> Why Choose Us</div>
                <h2 class="section-title">Why Choose <?php echo htmlspecialchars($platform_name); ?>?</h2>
                <p class="section-subtitle mx-auto">We're more than just a directory – we're your trusted partner</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h4 class="h5 mb-3">Fast & Reliable</h4>
                    <p class="text-muted">Find and book services in minutes, not days. Our platform is available 24/7.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h4 class="h5 mb-3">Trusted Reviews</h4>
                    <p class="text-muted">Make decisions based on real experiences from verified clients in your community.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <h4 class="h5 mb-3">Fair Visibility</h4>
                    <p class="text-muted">All providers get equal opportunity to showcase their skills and attract clients.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h4 class="h5 mb-3">Community Focused</h4>
                    <p class="text-muted">Built for Rwandans, by Rwandans. We understand local needs and culture.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h4 class="h5 mb-3">Easy to Use</h4>
                    <p class="text-muted">Simple, intuitive interface designed for everyone, regardless of technical skills.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <h4 class="h5 mb-3">Economic Impact</h4>
                    <p class="text-muted">Every booking supports local workers and contributes to community growth.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Vision for Future -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-4">
                        <div class="section-eyebrow"><i class="fas fa-rocket"></i> Looking Ahead</div>
                        <h2 class="section-title">Our Vision for the Future</h2>
                    </div>
                    <div class="content-box">
                        <p style="font-size:0.97rem;color:var(--secondary);line-height:1.75;">
                            At <?php echo htmlspecialchars($platform_name); ?>, we envision a future where:
                        </p>
                        <ul class="custom-list">
                            <li><strong>Unemployment is reduced</strong> as skilled workers easily connect with clients across Rwanda</li>
                            <li><strong>Quality service delivery</strong> becomes the norm through our rating and review system</li>
                            <li><strong><?php echo htmlspecialchars($platform_name); ?> becomes the #1 platform</strong> for service connections in Rwanda</li>
                            <li><strong>Expansion across all districts</strong> ensures every Rwandan has access to skilled professionals</li>
                            <li><strong>International growth</strong> brings our model to other East African countries</li>
                            <li><strong>Technology integration</strong> with AI-powered matching and instant booking capabilities</li>
                        </ul>
                        <p style="font-size:0.97rem;color:var(--secondary);margin:0;line-height:1.75;">
                            We're committed to continuous innovation, always listening to our users, and building features that make life easier for both service providers and clients.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5 bg-light-custom">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow"><i class="fas fa-envelope"></i> Contact</div>
                <h2 class="section-title">Get In Touch</h2>
                <p class="section-subtitle mx-auto">Have questions? We'd love to hear from you</p>
            </div>
            <div class="contact-grid">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h4 class="h5 mb-3">Email Us</h4>
                    <p class="text-muted">
                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="text-decoration-none"><?php echo htmlspecialchars($contact_email); ?></a><br>
                        <a href="mailto:support@biilocalfinder.com" class="text-decoration-none">support@biilocalfinder.com</a>
                    </p>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h4 class="h5 mb-3">Call Us</h4>
                    <p class="text-muted">
                        <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="text-decoration-none"><?php echo htmlspecialchars($contact_phone); ?></a><br>
                        Monday - Saturday: 8AM - 6PM
                    </p>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4 class="h5 mb-3">Visit Us</h4>
                    <p class="text-muted">
                        Kigali, Rwanda<br>
                        KG 123 Street, Gasabo District
                    </p>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h4 class="h5 mb-3">Follow Us</h4>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5" style="background:var(--surface-2);">
        <div class="container">
            <div class="cta-box">
                <div class="section-eyebrow" style="background:rgba(255,255,255,0.18);color:rgba(255,255,255,0.95);border:1px solid rgba(255,255,255,0.25);margin-bottom:1.25rem;">
                    <i class="fas fa-rocket"></i> Get Started Today
                </div>
                <h2 class="mb-3">Ready to Get Started?</h2>
                <p class="mb-0" style="max-width:520px;margin:0 auto 2rem;">Join thousands of Rwandans already using <?php echo htmlspecialchars($platform_name); ?> to connect with skilled professionals</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
                    <a href="providers.php" class="btn-white">
                        <i class="fas fa-search"></i> Find Providers
                    </a>
                    <?php if ($provider_registration_enabled): ?>
                        <a href="register.php?type=provider" class="btn-white">
                            <i class="fas fa-user-plus"></i> Register as Provider
                        </a>
                    <?php endif; ?>
                </div>
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
                    <div class="social-links footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="about.php" class="text-decoration-none text-muted">About Us</a></li>
                        <li class="mb-2"><a href="services.php" class="text-decoration-none text-muted">Services</a></li>
                        <li class="mb-2"><a href="providers.php" class="text-decoration-none text-muted">Find Providers</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-decoration-none text-muted">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>For Providers</h5>
                    <ul class="list-unstyled">
                        <?php if ($provider_registration_enabled): ?>
                            <li class="mb-2"><a href="register.php?type=provider" class="text-decoration-none text-muted">Register</a></li>
                        <?php endif; ?>
                        <li class="mb-2"><a href="login.php" class="text-decoration-none text-muted">Login</a></li>
                        <li class="mb-2"><a href="about.php" class="text-decoration-none text-muted">How It Works</a></li>
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
        // Navbar scroll shadow
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 20
                ? '0 4px 24px rgba(15,23,42,0.10)'
                : 'none';
        }, { passive: true });

        // Scroll-reveal animations
        document.addEventListener('DOMContentLoaded', function () {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            document.querySelectorAll(
                '.mission-card, .feature-card, .contact-card, .content-box, .stat-item'
            ).forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `opacity 0.5s cubic-bezier(0.16,1,0.3,1) ${(i % 4) * 0.08}s, transform 0.5s cubic-bezier(0.16,1,0.3,1) ${(i % 4) * 0.08}s`;
                observer.observe(el);
            });
        });
    </script>
</body>
</html>