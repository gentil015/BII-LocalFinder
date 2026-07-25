<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

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
$contact_whatsapp = getPlatformSetting('whatsapp_contact', '+250 788 123 456');
$copyright_text = getPlatformSetting('copyright_text', '© 2024 BII LocalFinder. All rights reserved.');
$platform_description = getPlatformSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

// Check registration settings
$provider_registration_enabled = getPlatformSetting('provider_registration', '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frequently Asked Questions - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background: var(--surface);
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
        .hero-faq {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 100px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .hero-faq::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 32px 32px;
            z-index: -1;
        }

        .hero-faq::after {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 360px; height: 360px;
            background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 65%);
            z-index: -1;
        }

        .hero-faq h1 {
            font-size: clamp(2rem, 4.5vw, 3.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 1.1rem;
        }

        .hero-faq .lead {
            font-size: 1.1rem;
            opacity: 0.88;
            max-width: 580px;
            margin: 0 auto;
            line-height: 1.65;
        }

        /* ── SEARCH BAR ── */
        .faq-search-wrap {
            margin-top: 2rem;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
        }

        .faq-search {
            width: 100%;
            padding: 0.9rem 1.25rem 0.9rem 3rem;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 0.97rem;
            font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            background: rgba(255,255,255,0.97);
            color: var(--dark);
            box-shadow: 0 8px 32px rgba(0,0,0,0.14);
            outline: none;
            transition: box-shadow 0.25s ease;
        }

        .faq-search:focus { box-shadow: 0 12px 40px rgba(0,0,0,0.2); }
        .faq-search::placeholder { color: var(--text-muted); }

        .faq-search-icon {
            position: absolute;
            left: 1.1rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.95rem;
            pointer-events: none;
        }

        /* ── TRUST STRIP ── */
        .trust-strip {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0;
        }

        .trust-strip-inner {
            display: flex;
            align-items: stretch;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.75rem 1.5rem;
            flex: 1;
            border-right: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        .trust-item:last-child { border-right: none; }
        .trust-item:hover { background: rgba(37,99,235,0.025); }

        .trust-icon {
            width: 48px; height: 48px;
            background: rgba(16,185,129,0.1);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: var(--success);
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .trust-item:hover .trust-icon { transform: scale(1.08); }

        .trust-item h5 {
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 0.15rem;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .trust-item p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* ── CATEGORY NAV ── */
        .faq-nav {
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 69px;
            z-index: 100;
        }

        .faq-nav-inner {
            display: flex;
            gap: 0;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .faq-nav-inner::-webkit-scrollbar { display: none; }

        .faq-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.4rem;
            font-size: 0.87rem;
            font-weight: 600;
            color: var(--secondary);
            text-decoration: none;
            white-space: nowrap;
            border-bottom: 2.5px solid transparent;
            transition: all 0.22s ease;
            letter-spacing: 0.01em;
        }

        .faq-nav-link:hover {
            color: var(--primary);
            background: rgba(37,99,235,0.04);
        }

        .faq-nav-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
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

        .section-header { margin-bottom: 2.5rem; }

        .section-title {
            font-size: clamp(1.5rem, 3vw, 2.1rem);
            font-weight: 800;
            margin-bottom: 0.6rem;
            color: var(--dark);
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .section-subtitle {
            font-size: 1rem;
            color: var(--secondary);
            max-width: 560px;
            line-height: 1.65;
            margin: 0;
        }

        /* ── FAQ SECTIONS ── */
        .faq-section { padding: 4.5rem 0; }
        .faq-section.alt { background: var(--surface-2); }

        /* ── ACCORDION ── */
        .accordion-faq .accordion-item {
            border: 1.5px solid var(--border) !important;
            border-radius: var(--radius-lg) !important;
            margin-bottom: 0.75rem;
            overflow: hidden;
            transition: all 0.25s ease;
            box-shadow: var(--shadow-sm);
        }

        .accordion-faq .accordion-item:hover {
            border-color: rgba(37,99,235,0.3) !important;
            box-shadow: var(--shadow-md);
        }

        .accordion-faq .accordion-item.open {
            border-color: var(--primary) !important;
            box-shadow: 0 4px 16px rgba(37,99,235,0.10);
        }

        .accordion-faq .accordion-button {
            padding: 1.3rem 1.5rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.97rem;
            font-weight: 700;
            color: var(--dark);
            background: var(--surface);
            border: none;
            box-shadow: none !important;
            transition: all 0.25s ease;
            letter-spacing: -0.01em;
            line-height: 1.4;
        }

        .accordion-faq .accordion-button:not(.collapsed) {
            color: var(--primary);
            background: rgba(37,99,235,0.04);
            border-bottom: 1px solid rgba(37,99,235,0.14);
        }

        .accordion-faq .accordion-button:focus { box-shadow: none !important; }

        .accordion-faq .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%232563eb'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            flex-shrink: 0;
            transition: transform 0.25s ease;
        }

        .accordion-faq .accordion-button.collapsed::after {
            transform: rotate(-90deg);
        }

        .accordion-faq .accordion-button:not(.collapsed)::after {
            transform: rotate(0deg);
        }

        .accordion-faq .accordion-body {
            padding: 1.4rem 1.5rem;
            color: var(--secondary);
            line-height: 1.75;
            font-size: 0.95rem;
            background: var(--surface);
        }

        .accordion-faq .accordion-body p { margin-bottom: 0.85rem; }
        .accordion-faq .accordion-body p:last-child { margin-bottom: 0; }

        .accordion-faq .accordion-body ul,
        .accordion-faq .accordion-body ol {
            padding-left: 1.5rem;
            margin-bottom: 0.85rem;
        }

        .accordion-faq .accordion-body li {
            margin-bottom: 0.45rem;
            line-height: 1.65;
        }

        .accordion-faq .accordion-body li:last-child { margin-bottom: 0; }

        .accordion-faq .accordion-body strong { color: var(--dark); }

        /* ── FAQ ICON ── */
        .faq-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
            font-size: 0.95rem;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .accordion-button:not(.collapsed) .faq-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        /* ── INLINE INFO CARDS ── */
        .info-card {
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.1rem 1.25rem;
            margin-bottom: 0.75rem;
        }

        .info-card:last-child { margin-bottom: 0; }

        .info-card h5 {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            letter-spacing: -0.01em;
        }

        .info-card p { font-size: 0.88rem; margin: 0; }

        /* Alerts inside accordion */
        .accordion-faq .alert {
            border-radius: var(--radius-md);
            border: none;
            font-size: 0.9rem;
            padding: 0.85rem 1.1rem;
            margin-top: 1rem;
        }

        .accordion-faq .alert-info {
            background: rgba(37,99,235,0.07);
            border: 1.5px solid rgba(37,99,235,0.18);
            color: var(--primary-dark);
        }

        .accordion-faq .alert-warning {
            background: rgba(245,158,11,0.08);
            border: 1.5px solid rgba(245,158,11,0.24);
            color: #92400e;
        }

        .accordion-faq .alert-light {
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            color: var(--secondary);
        }

        /* register CTA inside accordion */
        .accordion-faq .btn-primary {
            border-radius: var(--radius-md);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 0.88rem;
            padding: 0.6rem 1.4rem;
            box-shadow: var(--shadow-primary);
            border: none;
            transition: all 0.25s ease;
        }

        .accordion-faq .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.30);
        }

        /* ── CTA HELP SECTION ── */
        .cta-help {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 4.5rem 3rem;
            border-radius: var(--radius-xl);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            isolation: isolate;
            box-shadow: var(--shadow-primary);
        }

        .cta-help::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 28px 28px;
            z-index: -1;
        }

        .cta-help::after {
            content: '';
            position: absolute;
            bottom: -70px; left: -70px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
            z-index: -1;
        }

        .cta-help h2 {
            font-size: clamp(1.75rem, 3.5vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
        }

        .cta-help .lead {
            font-size: 1.05rem;
            opacity: 0.88;
            max-width: 480px;
            margin: 0 auto 2rem;
            line-height: 1.65;
        }

        .contact-methods {
            display: flex;
            justify-content: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .contact-method {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(8px);
            padding: 1.5rem 1.75rem;
            border-radius: var(--radius-lg);
            text-align: center;
            min-width: 185px;
            transition: all 0.28s ease;
            text-decoration: none;
            color: white;
        }

        .contact-method:hover {
            background: rgba(255,255,255,0.22);
            transform: translateY(-5px);
            color: white;
            text-decoration: none;
            box-shadow: 0 12px 28px rgba(0,0,0,0.18);
        }

        .contact-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.4rem;
            transition: transform 0.3s ease;
        }

        .contact-method:hover .contact-icon { transform: scale(1.1); }

        .contact-method strong {
            display: block;
            font-size: 0.97rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .contact-method span {
            font-size: 0.85rem;
            opacity: 0.82;
        }

        /* ── FOOTER ── */
        .footer {
            background: var(--dark);
            padding: 4.5rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: 0;
        }

        .footer-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: white;
            margin-bottom: 1.25rem;
        }

        .footer-link {
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
            display: block;
            margin-bottom: 0.65rem;
        }

        .footer-link:hover { color: white; }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.07);
            padding-top: 2rem;
            margin-top: 3.5rem;
            text-align: center;
            color: #475569;
            font-size: 0.88rem;
        }

        /* ── FADE-IN ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .hero-faq { padding: 70px 0 55px; }
            .hero-faq h1 { font-size: 2rem; }
            .trust-strip-inner { flex-direction: column; }
            .trust-item { border-right: none; border-bottom: 1px solid var(--border); padding: 1.25rem 1rem; }
            .trust-item:last-child { border-bottom: none; }
            .faq-section { padding: 3rem 0; }
            .cta-help { padding: 2.5rem 1.5rem; }
            .contact-methods { flex-direction: column; align-items: center; }
            .contact-method { width: 100%; max-width: 300px; }
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
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="faq.php">FAQ</a></li>
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
    <section class="hero-faq">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="d-inline-flex align-items-center gap-2 mb-4" style="background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.24);border-radius:100px;padding:0.4rem 1.1rem;backdrop-filter:blur(8px);">
                        <span style="width:8px;height:8px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 0 3px rgba(16,185,129,0.3);"></span>
                        <span style="font-size:0.8rem;font-weight:700;letter-spacing:0.05em;color:rgba(255,255,255,0.95);">HELP CENTER</span>
                    </div>
                    <h1 class="mb-3">Frequently Asked Questions</h1>
                    <p class="lead mb-0">
                        Answers to common questions about using <?php echo htmlspecialchars($platform_name); ?>.
                        Can't find what you need? Our support team is ready to help.
                    </p>
                    <!-- Search -->
                    <div class="faq-search-wrap">
                        <i class="fas fa-search faq-search-icon"></i>
                        <input type="text" class="faq-search" id="faqSearch" placeholder="Search questions…" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Strip -->
    <div class="trust-strip">
        <div class="container p-0">
            <div class="trust-strip-inner">
                <div class="trust-item">
                    <div class="trust-icon"><i class="fas fa-shield-check"></i></div>
                    <div>
                        <h5>Safe & Secure</h5>
                        <p>Verified providers only</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="fas fa-comments"></i></div>
                    <div>
                        <h5>24/7 Support</h5>
                        <p>We're always here to help</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="fas fa-lock"></i></div>
                    <div>
                        <h5>No Hidden Fees</h5>
                        <p>Transparent and honest</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Nav -->
    <div class="faq-nav">
        <div class="container p-0">
            <div class="faq-nav-inner">
                <a href="#clientFaqSection" class="faq-nav-link active"><i class="fas fa-user"></i> For Clients</a>
                <a href="#providerFaqSection" class="faq-nav-link"><i class="fas fa-tools"></i> For Providers</a>
                <a href="#bookingFaqSection" class="faq-nav-link"><i class="fas fa-calendar-check"></i> Booking</a>
                <a href="#disputesFaqSection" class="faq-nav-link"><i class="fas fa-balance-scale"></i> Disputes</a>
                <a href="#safetyFaqSection" class="faq-nav-link"><i class="fas fa-shield-alt"></i> Safety</a>
                <a href="#paymentFaqSection" class="faq-nav-link"><i class="fas fa-credit-card"></i> Payments</a>
                <a href="#accountFaqSection" class="faq-nav-link"><i class="fas fa-cog"></i> Account</a>
            </div>
        </div>
    </div>

    <!-- Client FAQs -->
    <section class="faq-section" id="clientFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-user"></i> For Clients</div>
                <h2 class="section-title">Finding & Hiring Providers</h2>
                <p class="section-subtitle">Everything you need to know about finding and hiring service providers</p>
            </div>
            
            <div class="accordion accordion-faq" id="clientFaq">
                <!-- Question 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#client1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            Is it free to use <?php echo htmlspecialchars($platform_name); ?>?
                        </button>
                    </h2>
                    <div id="client1" class="accordion-collapse collapse show" data-bs-parent="#clientFaq">
                        <div class="accordion-body">
                            <p><strong>Yes, it's completely free for clients.</strong> You can:</p>
                            <ul>
                                <li>Browse service providers without any charges</li>
                                <li>View detailed profiles and reviews</li>
                                <li>Contact providers directly</li>
                                <li>Book services at no extra cost</li>
                            </ul>
                            <p>You only pay the service provider directly for the work they do. We don't add any platform fees or commissions on top of their pricing.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#client2">
                            <div class="faq-icon me-3">
                                <i class="fas fa-search"></i>
                            </div>
                            How do I find a reliable service provider?
                        </button>
                    </h2>
                    <div id="client2" class="accordion-collapse collapse" data-bs-parent="#clientFaq">
                        <div class="accordion-body">
                            <p>We've made it easy to find trustworthy providers:</p>
                            <ol>
                                <li><strong>Use our advanced search filters</strong> to find providers by service type, location, rating, and availability</li>
                                <li><strong>Check verification badges</strong> - look for verified, gold, or premium badges on profiles</li>
                                <li><strong>Read genuine reviews</strong> from other clients who have used their services</li>
                                <li><strong>Compare multiple providers</strong> side by side based on pricing, experience, and ratings</li>
                                <li><strong>Look for complete profiles</strong> with photos, detailed descriptions, and work examples</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Question 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#client3">
                            <div class="faq-icon me-3">
                                <i class="fas fa-phone"></i>
                            </div>
                            How do I contact a service provider?
                        </button>
                    </h2>
                    <div id="client3" class="accordion-collapse collapse" data-bs-parent="#clientFaq">
                        <div class="accordion-body">
                            <p>There are two main ways to contact providers:</p>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h5 class="fw-bold"><i class="fas fa-phone text-primary me-2"></i> Direct Phone Call</h5>
                                        <p class="mb-0">View the provider's phone number on their profile page and call them directly.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h5 class="fw-bold"><i class="fab fa-whatsapp text-success me-2"></i> WhatsApp (if available)</h5>
                                        <p class="mb-0">Many providers offer WhatsApp for easy communication and file sharing.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2"><strong>No registration is needed</strong> to contact providers. You can reach out directly without any middleman or platform fees.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#client4">
                            <div class="faq-icon me-3">
                                <i class="fas fa-star"></i>
                            </div>
                            How do ratings and reviews work?
                        </button>
                    </h2>
                    <div id="client4" class="accordion-collapse collapse" data-bs-parent="#clientFaq">
                        <div class="accordion-body">
                            <p>Our review system is designed to be transparent and helpful:</p>
                            <ul>
                                <li><strong>Only verified clients</strong> can leave reviews after completing a service</li>
                                <li><strong>Ratings are based on actual experience</strong> - not paid reviews or fake feedback</li>
                                <li><strong>Reviews include both ratings (1-5 stars)</strong> and detailed written feedback</li>
                                <li><strong>You can filter providers by rating</strong> to see the highest-rated professionals first</li>
                            </ul>
                            <p>This system helps ensure that reviews are authentic and useful for making decisions.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 5 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#client5">
                            <div class="faq-icon me-3">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            What if something goes wrong with a service?
                        </button>
                    </h2>
                    <div id="client5" class="accordion-collapse collapse" data-bs-parent="#clientFaq">
                        <div class="accordion-body">
                            <p>If you're not satisfied with a service:</p>
                            <ol>
                                <li><strong>Contact the provider first</strong> - most issues can be resolved directly</li>
                                <li><strong>Leave an honest review</strong> to help others make informed decisions</li>
                                <li><strong>Report serious issues</strong> to our support team through the contact form</li>
                                <li><strong>Use our rating system</strong> to hold providers accountable for quality</li>
                            </ol>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Important:</strong> Always discuss and agree on pricing, scope, and expectations before work begins to avoid misunderstandings.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Provider FAQs -->
    <section class="faq-section alt" id="providerFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-tools"></i> For Providers</div>
                <h2 class="section-title">Growing Your Business</h2>
                <p class="section-subtitle">Everything you need to know about growing your business on our platform</p>
            </div>
            
            <div class="accordion accordion-faq" id="providerFaq">
                <!-- Question 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#provider1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            How can I become a service provider?
                        </button>
                    </h2>
                    <div id="provider1" class="accordion-collapse collapse show" data-bs-parent="#providerFaq">
                        <div class="accordion-body">
                            <p>Becoming a provider is simple:</p>
                            <ol>
                                <li><strong>Click "Register as Provider"</strong> on the registration page</li>
                                <li><strong>Complete your profile</strong> with detailed information about your skills and experience</li>
                                <li><strong>Add your services</strong> with clear descriptions and pricing</li>
                                <li><strong>Wait for profile approval</strong> - we review all new provider profiles</li>
                                <li><strong>Start getting clients</strong> once your profile is approved</li>
                            </ol>
                            <?php if ($provider_registration_enabled): ?>
                                <a href="register.php?type=provider" class="btn btn-primary mt-3">
                                    <i class="fas fa-user-plus me-2"></i>Register as Provider
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Question 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#provider2">
                            <div class="faq-icon me-3">
                                <i class="fas fa-money-check-alt"></i>
                            </div>
                            Is provider registration free? Are there any hidden fees?
                        </button>
                    </h2>
                    <div id="provider2" class="accordion-collapse collapse" data-bs-parent="#providerFaq">
                        <div class="accordion-body">
                            <p><strong>Yes, basic registration is completely free with no hidden fees.</strong> You can:</p>
                            <ul>
                                <li>Create your profile at no cost</li>
                                <li>List all your services for free</li>
                                <li>Get contacted by clients without paying anything</li>
                                <li>Receive unlimited booking requests</li>
                            </ul>
                            <p>We believe in helping skilled professionals grow their businesses without financial barriers.</p>
                            <div class="alert alert-light border mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Optional premium features</strong> may be introduced in the future to increase your visibility, but basic access will always remain free.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#provider3">
                            <div class="faq-icon me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            How do I get more clients?
                        </button>
                    </h2>
                    <div id="provider3" class="accordion-collapse collapse" data-bs-parent="#providerFaq">
                        <div class="accordion-body">
                            <p>Here are proven ways to attract more clients:</p>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-user-check text-success me-2"></i> Complete Profile</h6>
                                        <p class="small mb-0">100% complete profiles get 3x more views</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-star text-warning me-2"></i> Good Ratings</h6>
                                        <p class="small mb-0">High-rated providers appear first in searches</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-clock text-primary me-2"></i> Fast Response</h6>
                                        <p class="small mb-0">Quick replies increase booking chances by 60%</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-images text-info me-2"></i> Portfolio Photos</h6>
                                        <p class="small mb-0">Showcase your previous work with photos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#provider4">
                            <div class="faq-icon me-3">
                                <i class="fas fa-edit"></i>
                            </div>
                            Can I edit my profile or services later?
                        </button>
                    </h2>
                    <div id="provider4" class="accordion-collapse collapse" data-bs-parent="#providerFaq">
                        <div class="accordion-body">
                            <p><strong>Yes, you have full control over your profile at any time.</strong> You can:</p>
                            <ul>
                                <li>Update your contact information</li>
                                <li>Add or remove services</li>
                                <li>Change your pricing</li>
                                <li>Update your availability status</li>
                                <li>Add new portfolio photos</li>
                                <li>Edit your bio and descriptions</li>
                            </ul>
                            <p>All changes appear immediately on your public profile.</p>
                            <div class="alert alert-light border mt-3">
                                <i class="fas fa-sync-alt me-2"></i>
                                <strong>Tip:</strong> Regular updates to your profile and portfolio can help attract more clients and keep your business growing.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 5 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#provider5">
                            <div class="faq-icon me-3">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            Why was my profile rejected or deactivated?
                        </button>
                    </h2>
                    <div id="provider5" class="accordion-collapse collapse" data-bs-parent="#providerFaq">
                        <div class="accordion-body">
                            <p>Common reasons for profile rejection or deactivation include:</p>
                            <ul>
                                <li><strong>Incomplete information</strong> - missing essential details about your services</li>
                                <li><strong>Unverified identity</strong> - we couldn't confirm your identity</li>
                                <li><strong>Multiple negative reviews</strong> - consistent poor feedback from clients</li>
                                <li><strong>Violation of terms</strong> - breaking platform rules or policies</li>
                                <li><strong>Inactivity</strong> - not responding to client inquiries for extended periods</li>
                            </ul>
                            <p>If your profile was rejected or deactivated, you'll receive an email explaining the reason and steps to fix it.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Price Negotiation & Booking FAQs -->
    <section class="faq-section" id="bookingFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-calendar-check"></i> Booking</div>
                <h2 class="section-title">Price Negotiation & Booking</h2>
                <p class="section-subtitle">Understanding how our price negotiation and booking confirmation system works</p>
            </div>
            
            <div class="accordion accordion-faq" id="negotiationFaq">
                <!-- Question 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-handshake"></i>
                            </div>
                            What is the price negotiation system?
                        </button>
                    </h2>
                    <div id="negotiation1" class="accordion-collapse collapse show" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                Our <strong>Price Negotiation System</strong> allows clients and service providers to discuss and agree on pricing directly through the platform.
                            </p>
                            <p><strong>How it works:</strong></p>
                            <ol>
                                <li><strong>Client makes an offer</strong> - Propose a price within the provider's range</li>
                                <li><strong>Provider responds</strong> - Accept, reject, or send a counter-offer</li>
                                <li><strong>Client responds</strong> - Accept the counter-offer or propose a different price</li>
                                <li><strong>Agreement locked</strong> - Once either side accepts, the price is finalized</li>
                            </ol>
                            <p class="mb-0">
                                This system ensures fair pricing and gives both parties the ability to negotiate before service begins.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation2">
                            <div class="faq-icon me-3">
                                <i class="fas fa-comments"></i>
                            </div>
                            Which services can be negotiated?
                        </button>
                    </h2>
                    <div id="negotiation2" class="accordion-collapse collapse" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                <strong>Not all services are negotiable.</strong> Service providers decide which of their services support price negotiation.
                            </p>
                            <p>When browsing a provider's profile:</p>
                            <ul>
                                <li><strong>Negotiable services</strong> display a <i class="fas fa-handshake text-primary"></i> icon and show a price range (e.g., RWF 4,000 - RWF 6,000)</li>
                                <li><strong>Fixed-price services</strong> have a set price with no negotiation option</li>
                            </ul>
                            <p class="mb-0">
                                The provider's profile clearly indicates which services are open to negotiation.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation3">
                            <div class="faq-icon me-3">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            What does "3 negotiation rounds" mean?
                        </button>
                    </h2>
                    <div id="negotiation3" class="accordion-collapse collapse" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                To prevent endless back-and-forth discussions, the platform limits negotiation to <strong>maximum 3 rounds</strong> per booking.
                            </p>
                            <p><strong>How it counts:</strong></p>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold">Round 1</h6>
                                        <p class="small mb-0">Client sends initial offer</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold">Round 2</h6>
                                        <p class="small mb-0">Provider sends counter or client sends new offer</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold">Round 3</h6>
                                        <p class="small mb-0">Final offer/counter before agreement required</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 mb-3">
                                        <h6 class="fw-bold">No Round 4</h6>
                                        <p class="small mb-0">After 3 rounds, one side must accept or negotiation ends</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                This limit ensures timely agreement and prevents indecision.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation4">
                            <div class="faq-icon me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            What does "30-minute expiry" mean for offers?
                        </button>
                    </h2>
                    <div id="negotiation4" class="accordion-collapse collapse" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                Each offer or counter-offer remains valid for exactly <strong>30 minutes</strong>. After that, it expires automatically.
                            </p>
                            <p><strong>Why this matters:</strong></p>
                            <ul>
                                <li><strong>Urgency:</strong> Both parties need to respond quickly to keep negotiations moving</li>
                                <li><strong>Fairness:</strong> Prevents stale offers from sitting indefinitely</li>
                                <li><strong>Auto-expiry:</strong> No need to manually reject - offers expire on their own</li>
                            </ul>
                            <p><strong>What happens after expiry:</strong></p>
                            <ul>
                                <li>The offer/counter becomes invalid</li>
                                <li>The other party can no longer accept it</li>
                                <li>Either party must start a new round (if within the 3-round limit)</li>
                            </ul>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Pro Tip:</strong> Set a reminder when you send an offer so you can follow up if the other party doesn't respond.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 5 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation5">
                            <div class="faq-icon me-3">
                                <i class="fas fa-lock"></i>
                            </div>
                            What is "price locking" and when does it happen?
                        </button>
                    </h2>
                    <div id="negotiation5" class="accordion-collapse collapse" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                <strong>Price locking</strong> is when the agreed price becomes final and cannot be changed.
                            </p>
                            <p><strong>When price locks:</strong></p>
                            <ul>
                                <li>When <strong>provider accepts the client's offer</strong></li>
                                <li>When <strong>client accepts the provider's counter-offer</strong></li>
                            </ul>
                            <p><strong>What happens after price locks:</strong></p>
                            <ul>
                                <li>✅ The price becomes <strong>final and binding</strong></li>
                                <li>✅ Your booking status automatically changes to <strong>"confirmed"</strong></li>
                                <li>✅ Both parties receive <strong>email confirmation</strong></li>
                                <li>✅ The agreed price is recorded in the system with <strong>full history</strong></li>
                                <li>✅ Service can proceed based on this locked price</li>
                            </ul>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Once price is locked, neither party can request price changes. Discuss and agree carefully before accepting.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 6 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation6">
                            <div class="faq-icon me-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            What is automatic booking confirmation?
                        </button>
                    </h2>
                    <div id="negotiation6" class="accordion-collapse collapse" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                When you and the service provider agree on a price through our negotiation system, your booking <strong>automatically confirms</strong>.
                            </p>
                            <p><strong>How it works:</strong></p>
                            <ol>
                                <li><strong>You send an offer</strong> - Propose a price for the service</li>
                                <li><strong>Provider accepts</strong> - Click "Accept Offer"</li>
                                <li>🎉 <strong>AUTOMATIC</strong> - Booking status instantly changes to "confirmed"</li>
                            </ol>
                            <p><strong>You don't need to:</strong></p>
                            <ul>
                                <li>Click any additional confirmation buttons</li>
                                <li>Fill out any more forms</li>
                                <li>Take any manual action</li>
                            </ul>
                            <p class="mb-0">
                                The system handles everything automatically - you just wait for the provider's response!
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 7 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#negotiation7">
                            <div class="faq-icon me-3">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            Can I propose an offer outside the provider's price range?
                        </button>
                    </h2>
                    <div id="negotiation7" class="accordion-collapse collapse" data-bs-parent="#negotiationFaq">
                        <div class="accordion-body">
                            <p>
                                <strong>No.</strong> The system requires your offer to be within the provider's specified minimum and maximum price range.
                            </p>
                            <p><strong>Example:</strong></p>
                            <ul>
                                <li>Provider's range: RWF 4,000 - RWF 6,000</li>
                                <li>Your offer: RWF 5,000 ✅ <strong>Allowed</strong></li>
                                <li>Your offer: RWF 3,000 ❌ <strong>Below minimum, rejected</strong></li>
                                <li>Your offer: RWF 7,000 ❌ <strong>Above maximum, rejected</strong></li>
                            </ul>
                            <p class="mb-0">
                                This protects providers and ensures negotiations stay realistic.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Disputes & Complaints FAQs -->
    <section class="faq-section alt" id="disputesFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-balance-scale"></i> Disputes</div>
                <h2 class="section-title">Disputes & Complaints</h2>
                <p class="section-subtitle">How to handle issues and file complaints</p>
            </div>
            
            <div class="accordion accordion-faq" id="disputeFaq">
                <!-- Question 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dispute1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            What can I do if there's a problem with a service?
                        </button>
                    </h2>
                    <div id="dispute1" class="accordion-collapse collapse show" data-bs-parent="#disputeFaq">
                        <div class="accordion-body">
                            <p><strong>First, try to resolve it directly:</strong></p>
                            <ol>
                                <li>Contact the service provider directly via phone or WhatsApp</li>
                                <li>Explain the issue clearly and calmly</li>
                                <li>Give them a reasonable time to respond and fix the problem</li>
                                <li>Most issues can be resolved through conversation</li>
                            </ol>
                            <p><strong>If direct resolution doesn't work:</strong></p>
                            <ol>
                                <li>Go to your dashboard and find the booking</li>
                                <li>Click "File a Complaint"</li>
                                <li>Describe the issue in detail</li>
                                <li>Add any supporting documents or photos</li>
                                <li>Submit and our team will investigate</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Question 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dispute2">
                            <div class="faq-icon me-3">
                                <i class="fas fa-calendar"></i>
                            </div>
                            What's the deadline for filing a complaint?
                        </button>
                    </h2>
                    <div id="dispute2" class="accordion-collapse collapse" data-bs-parent="#disputeFaq">
                        <div class="accordion-body">
                            <p>
                                <strong>Complaints must be filed within 14 days of service completion.</strong>
                            </p>
                            <p>
                                After 14 days, we can't investigate or take action, so it's important to file quickly if there's an issue.
                            </p>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Tip:</strong> File immediately if there's a problem - don't wait until the deadline.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dispute3">
                            <div class="faq-icon me-3">
                                <i class="fas fa-search"></i>
                            </div>
                            What happens when I file a complaint?
                        </button>
                    </h2>
                    <div id="dispute3" class="accordion-collapse collapse" data-bs-parent="#disputeFaq">
                        <div class="accordion-body">
                            <p><strong>Our investigation process:</strong></p>
                            <ol>
                                <li><strong>Acknowledgment</strong> - We confirm receipt of your complaint</li>
                                <li><strong>Review</strong> - We examine your details and any evidence you provided</li>
                                <li><strong>Provider Response</strong> - We ask the provider for their side of the story</li>
                                <li><strong>Investigation</strong> - We carefully review both accounts</li>
                                <li><strong>Decision</strong> - We make a determination based on the evidence</li>
                                <li><strong>Notification</strong> - We inform both parties of the outcome</li>
                            </ol>
                            <p class="mb-0">
                                This process typically takes 5-10 business days.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Question 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dispute4">
                            <div class="faq-icon me-3">
                                <i class="fas fa-gavel"></i>
                            </div>
                            What are the possible outcomes of a complaint?
                        </button>
                    </h2>
                    <div id="dispute4" class="accordion-collapse collapse" data-bs-parent="#disputeFaq">
                        <div class="accordion-body">
                            <p>Depending on our investigation, we may:</p>
                            <ul>
                                <li><strong>Dismiss the complaint</strong> if we find insufficient evidence or both parties shared responsibility</li>
                                <li><strong>Issue a warning</strong> to the provider for minor violations</li>
                                <li><strong>Suspend the provider account</strong> temporarily for serious issues</li>
                                <li><strong>Permanently ban the provider</strong> for fraud or repeated violations</li>
                                <li><strong>Recommend legal action</strong> for criminal behavior</li>
                            </ul>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> We act as a facilitator to protect the platform community. For major financial disputes, you may need to pursue legal remedies through the courts.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 5 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dispute5">
                            <div class="faq-icon me-3">
                                <i class="fas fa-star"></i>
                            </div>
                            Can I rate and review if there was a problem?
                        </button>
                    </h2>
                    <div id="dispute5" class="accordion-collapse collapse" data-bs-parent="#disputeFaq">
                        <div class="accordion-body">
                            <p>
                                <strong>Yes, absolutely.</strong> You can leave an honest review reflecting your actual experience, even if there were problems.
                            </p>
                            <p><strong>Your review helps:</strong></p>
                            <ul>
                                <li>Other clients make informed decisions</li>
                                <li>Hold providers accountable for quality</li>
                                <li>Providers understand areas for improvement</li>
                            </ul>
                            <p><strong>Keep reviews honest and fair:</strong></p>
                            <ul>
                                <li>Describe what actually happened</li>
                                <li>Be specific about issues</li>
                                <li>Avoid insults or defamatory language</li>
                                <li>Be constructive when possible</li>
                            </ul>
                            <p class="mb-0">
                                This helps maintain trust in the platform for everyone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Safety & Trust FAQs -->
    <section class="faq-section" id="safetyFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-shield-alt"></i> Safety</div>
                <h2 class="section-title">Safety & Trust</h2>
                <p class="section-subtitle">Your security and trust are our top priorities</p>
            </div>
            
            <div class="accordion accordion-faq" id="safetyFaq">
                <!-- Question 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#safety1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            How are service providers verified?
                        </button>
                    </h2>
                    <div id="safety1" class="accordion-collapse collapse show" data-bs-parent="#safetyFaq">
                        <div class="accordion-body">
                            <p>All providers go through a verification process that includes:</p>
                            <ul>
                                <li><strong>Identity verification</strong> - confirming personal information</li>
                                <li><strong>Skill validation</strong> - reviewing experience and qualifications</li>
                                <li><strong>Profile review</strong> - checking for completeness and accuracy</li>
                                <li><strong>Documentation</strong> - where applicable, verifying certifications or licenses</li>
                            </ul>
                            <p>Verified providers receive badges on their profiles:</p>
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 text-center">
                                        <span class="badge bg-success mb-2">✓ Verified</span>
                                        <p class="small mb-0">Identity confirmed, basic profile review</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 text-center">
                                        <span class="badge bg-warning text-dark mb-2">⭐ Gold</span>
                                        <p class="small mb-0">High ratings, verified experience</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 text-center">
                                        <span class="badge bg-primary mb-2">💎 Premium</span>
                                        <p class="small mb-0">Top-rated professionals, premium service</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#safety2">
                            <div class="faq-icon me-3">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            How are clients protected?
                        </button>
                    </h2>
                    <div id="safety2" class="accordion-collapse collapse" data-bs-parent="#safetyFaq">
                        <div class="accordion-body">
                            <p>We protect clients through:</p>
                            <ul>
                                <li><strong>Verified providers only</strong> - all providers undergo identity checks</li>
                                <li><strong>Transparent reviews</strong> - see what others say before booking</li>
                                <li><strong>Profile reporting</strong> - report any suspicious behavior</li>
                                <li><strong>Clear expectations</strong> - providers must clearly state pricing and services</li>
                                <li><strong>Accountability system</strong> - providers must maintain good ratings to stay visible</li>
                            </ul>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Important:</strong> Always discuss pricing and scope of work before service begins. Never pay full amounts upfront for large projects.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Question 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#safety3">
                            <div class="faq-icon me-3">
                                <i class="fas fa-flag"></i>
                            </div>
                            What should I do if I encounter fraud or abuse?
                        </button>
                    </h2>
                    <div id="safety3" class="accordion-collapse collapse" data-bs-parent="#safetyFaq">
                        <div class="accordion-body">
                            <p>If you encounter any suspicious activity:</p>
                            <ol>
                                <li><strong>Do not proceed</strong> with any transactions</li>
                                <li><strong>Report the profile immediately</strong> using the "Report" button on their profile</li>
                                <li><strong>Contact our support team</strong> with details of the incident</li>
                                                                <li><strong>Provide evidence</strong> if available (screenshots, messages, etc.)</li>
                                <li><strong>We investigate quickly</strong> and take action if needed</li>
                            </ol>
                            <p class="mb-0">
                                Serious violations can result in permanent account suspension to protect the community.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Payments & Pricing FAQs -->
    <section class="faq-section alt" id="paymentFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-credit-card"></i> Payments</div>
                <h2 class="section-title">Payments & Pricing</h2>
                <p class="section-subtitle">Clear and transparent information about costs and payments</p>
            </div>

            <div class="accordion accordion-faq" id="paymentFaq">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#payment1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            Does <?php echo htmlspecialchars($platform_name); ?> handle payments?
                        </button>
                    </h2>
                    <div id="payment1" class="accordion-collapse collapse show" data-bs-parent="#paymentFaq">
                        <div class="accordion-body">
                            <p>
                                <strong>No.</strong> Payments are handled directly between clients and service providers.
                            </p>
                            <ul>
                                <li>No platform transaction fees</li>
                                <li>No forced payment methods</li>
                                <li>Providers and clients agree on payment terms directly</li>
                            </ul>
                            <p class="mb-0">
                                This keeps pricing fair, flexible, and transparent.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Account & Technical FAQs -->
    <section class="faq-section" id="accountFaqSection">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-cog"></i> Account</div>
                <h2 class="section-title">Account & Technical</h2>
                <p class="section-subtitle">Help with accounts, login, and technical issues</p>
            </div>

            <div class="accordion accordion-faq" id="accountFaq">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#account1">
                            <div class="faq-icon me-3">
                                <i class="fas fa-key"></i>
                            </div>
                            I forgot my password. What should I do?
                        </button>
                    </h2>
                    <div id="account1" class="accordion-collapse collapse show" data-bs-parent="#accountFaq">
                        <div class="accordion-body">
                            <p>
                                Click <strong>“Forgot Password”</strong> on the login page and follow the instructions.
                            </p>
                            <p class="mb-0">
                                A reset link will be sent to your registered email address.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#account2">
                            <div class="faq-icon me-3">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            Can I delete my account?
                        </button>
                    </h2>
                    <div id="account2" class="accordion-collapse collapse" data-bs-parent="#accountFaq">
                        <div class="accordion-body">
                            <p>
                                Yes. You can request account deletion from your dashboard or contact support.
                            </p>
                            <p class="mb-0">
                                We respect user privacy and data protection.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Still Need Help CTA -->
    <section style="background:var(--surface-2);padding:4rem 0;">
        <div class="container">
            <div class="cta-help">
                <div class="section-eyebrow" style="background:rgba(255,255,255,0.18);color:rgba(255,255,255,0.95);border:1px solid rgba(255,255,255,0.25);margin-bottom:1.25rem;">
                    <i class="fas fa-headset"></i> Support
                </div>
                <h2>Still need help?</h2>
                <p class="lead">Our support team is ready to assist you anytime.</p>

                <div class="contact-methods">
                    <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-method">
                        <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                        <strong>Email Us</strong>
                        <span><?php echo htmlspecialchars($contact_email); ?></span>
                    </a>

                    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $contact_whatsapp); ?>" class="contact-method">
                        <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                        <strong>WhatsApp</strong>
                        <span><?php echo htmlspecialchars($contact_whatsapp); ?></span>
                    </a>

                    <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="contact-method">
                        <div class="contact-icon"><i class="fas fa-phone"></i></div>
                        <strong>Call Us</strong>
                        <span><?php echo htmlspecialchars($contact_phone); ?></span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="footer-title"><?php echo htmlspecialchars($platform_name); ?></div>
                    <p style="color:#64748b;font-size:0.9rem;line-height:1.65;"><?php echo htmlspecialchars($platform_description); ?></p>
                </div>
                <div class="col-md-3 col-lg-2">
                    <div class="footer-title">Navigation</div>
                    <a href="index.php" class="footer-link">Home</a>
                    <a href="services.php" class="footer-link">Services</a>
                    <a href="providers.php" class="footer-link">Find Providers</a>
                    <a href="about.php" class="footer-link">About</a>
                </div>
                <div class="col-md-3 col-lg-2">
                    <div class="footer-title">Help</div>
                    <a href="faq.php" class="footer-link">FAQ</a>
                    <a href="contact.php" class="footer-link">Contact</a>
                    <a href="login.php" class="footer-link">Sign In</a>
                    <a href="register.php" class="footer-link">Register</a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="footer-title">Contact</div>
                    <p style="color:#64748b;font-size:0.9rem;margin-bottom:0.4rem;"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($contact_email); ?></p>
                    <p style="color:#64748b;font-size:0.9rem;"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($contact_phone); ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── Navbar scroll shadow
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 20
                ? '0 4px 24px rgba(15,23,42,0.10)'
                : 'none';
        }, { passive: true });

        // ── Category nav active highlight on scroll
        const faqSections = document.querySelectorAll('section[id$="FaqSection"]');
        const navLinks = document.querySelectorAll('.faq-nav-link');

        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    navLinks.forEach(link => {
                        link.classList.toggle('active', link.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-25% 0px -65% 0px' });

        faqSections.forEach(s => sectionObserver.observe(s));

        // ── Smooth scroll with offset for sticky nav
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offset = 135; // navbar + faq-nav height
                    const top = target.getBoundingClientRect().top + window.scrollY - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });

        // ── Live search filter
        const searchInput = document.getElementById('faqSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const q = this.value.trim().toLowerCase();

                document.querySelectorAll('.accordion-faq .accordion-item').forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = (q === '' || text.includes(q)) ? '' : 'none';
                });

                // hide entire sections if no matching questions
                document.querySelectorAll('.faq-section').forEach(section => {
                    const hasVisible = [...section.querySelectorAll('.accordion-item')]
                        .some(i => i.style.display !== 'none');
                    section.style.display = (q === '' || hasVisible) ? '' : 'none';
                });
            });
        }

        // ── Mark open items with .open class for border styling
        document.querySelectorAll('.accordion-faq .accordion-button').forEach(btn => {
            // mark already-open items on load
            if (!btn.classList.contains('collapsed')) {
                btn.closest('.accordion-item').classList.add('open');
            }
            btn.addEventListener('click', function() {
                const item = this.closest('.accordion-item');
                setTimeout(() => {
                    item.classList.toggle('open', !this.classList.contains('collapsed'));
                }, 50);
            });
        });

        // ── Scroll-reveal animations
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        document.querySelectorAll('.accordion-faq .accordion-item, .trust-item, .contact-method').forEach((el, i) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(14px)';
            el.style.transition = `opacity 0.45s cubic-bezier(0.16,1,0.3,1) ${(i % 5) * 0.06}s, transform 0.45s cubic-bezier(0.16,1,0.3,1) ${(i % 5) * 0.06}s`;
            revealObserver.observe(el);
        });
    </script>
</body>
</html>