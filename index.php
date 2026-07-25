<?php
session_start();

// Enable gzip compression if available
if (!headers_sent()) {
    ob_start('ob_gzhandler');
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'controllers/pages/HomePageController.php';

// Release session lock early for better performance
session_write_close();

$db = Database::getInstance()->getConnection();
$controller = new HomePageController();
$viewData = $controller->index($db);

if (!empty($viewData['maintenance_mode']) && !(isset($_SESSION['user_id']) && isAdmin())) {
    header('Location: maintenance.php');
    exit;
}

$categories = $viewData['categories'] ?? [];
$featured_providers = $viewData['featured_providers'] ?? [];
$nearby_providers = $viewData['nearby_providers'] ?? [];
$recent_providers = $viewData['recent_providers'] ?? [];
$districts = $viewData['districts'] ?? [];

$platform_name = $viewData['platform_name'] ?? 'BII LocalFinder';
$contact_email = $viewData['contact_email'] ?? 'support@biilocalfinder.com';
$contact_phone = $viewData['contact_phone'] ?? '+250 788 123 456';
$platform_description = $viewData['platform_description'] ?? 'Connecting clients with trusted local service providers';
$copyright_text = $viewData['copyright_text'] ?? '© 2024 BII LocalFinder. All rights reserved.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform_name); ?> - Find Local Service Providers in Rwanda</title>
    <meta name="description" content="<?php echo htmlspecialchars($platform_description); ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6,
        .navbar-brand, .section-title, .cta-title,
        .stat-number, .provider-name, .category-name,
        .step-title, .service-title-popular {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* ───── SCROLLBAR ───── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

        /* ───── NAVBAR ───── */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226,232,240,0.8);
            padding: 0.85rem 0;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }

        .navbar .nav-link {
            font-weight: 500;
            font-size: 0.93rem;
            color: var(--secondary) !important;
            padding: 0.5rem 0.9rem !important;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
            letter-spacing: 0.01em;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.fw-semibold {
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
            letter-spacing: 0.01em;
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

        /* ───── HERO ───── */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-image: url('assets/images/world-map.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            overflow: hidden;
            padding: 110px 0 90px;
            isolation: isolate;
        }

        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 15% 45%, rgba(255,255,255,0.13) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 85% 75%, rgba(255,255,255,0.09) 0%, transparent 60%),
                linear-gradient(155deg, rgba(37,99,235,0.90) 0%, rgba(30,64,175,0.93) 55%, rgba(37,99,235,0.88) 100%);
            z-index: -1;
        }

        .hero::after {
            content: '';
            position: absolute; inset: 0;
            background-image:
                radial-gradient(circle at 1px 1px, rgba(255,255,255,0.07) 1px, transparent 0);
            background-size: 36px 36px;
            z-index: -1;
        }

        .hero-content { position: relative; z-index: 2; }

        .hero h1 {
            font-size: clamp(2.2rem, 5vw, 3.6rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 1.25rem;
            letter-spacing: -0.03em;
            text-shadow: 0 2px 20px rgba(0,0,0,0.12);
        }

        .hero h1 span {
            position: relative;
            display: inline-block;
        }

        .hero h1 span::after {
            content: '';
            position: absolute;
            bottom: 2px; left: 0; right: 0;
            height: 3px;
            background: rgba(255,255,255,0.5);
            border-radius: 2px;
        }

        .hero p {
            font-size: 1.15rem;
            opacity: 0.9;
            font-weight: 400;
            line-height: 1.65;
        }

        /* ───── SEARCH BOX ───── */
        .search-box {
            background: rgba(255,255,255,0.99);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            box-shadow: 0 24px 80px rgba(0,0,0,0.18), 0 0 0 1px rgba(255,255,255,0.2);
            padding: 2rem 2.2rem;
        }

        .search-box-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--secondary);
            margin-bottom: 0.45rem;
            display: block;
        }

        .search-input-group {
            position: relative;
        }

        .search-input-group .form-control,
        .search-input-group .form-select {
            border: 1.5px solid var(--border);
            padding: 0.85rem 1rem 0.85rem 2.9rem;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 500;
            background: var(--surface-2);
            color: var(--dark);
            transition: all 0.25s ease;
            height: auto;
        }

        .search-input-group .form-control:hover,
        .search-input-group .form-select:hover {
            border-color: #cbd5e1;
            background: #fff;
        }

        .search-input-group .form-control:focus,
        .search-input-group .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
            background: #fff;
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            z-index: 10;
        }

        .btn-search {
            padding: 0.85rem 1.8rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            transition: all 0.25s ease;
            box-shadow: var(--shadow-primary);
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.35);
        }

        .advanced-toggle {
            padding: 0.85rem 1.4rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            border: 1.5px solid var(--border);
            color: var(--secondary);
            background: transparent;
            transition: all 0.25s ease;
        }

        .advanced-toggle:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        /* Quick Filters */
        .quick-filters { margin-top: 1.5rem; }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 1rem;
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.28);
            border-radius: 100px;
            color: rgba(255,255,255,0.95);
            text-decoration: none;
            margin: 0.2rem;
            transition: all 0.25s ease;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            backdrop-filter: blur(4px);
        }

        .filter-chip:hover {
            background: rgba(255,255,255,0.95);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        /* ───── STATS ───── */
        .stats-section {
            background: var(--surface-2);
            padding: 0;
            border-bottom: 1px solid var(--border);
        }

        .stats-inner {
            display: flex;
            align-items: stretch;
            divide-x: 1px solid var(--border);
        }

        .stat-card {
            text-align: center;
            padding: 2.2rem 1.5rem;
            flex: 1;
            border-right: 1px solid var(--border);
            transition: background 0.25s ease;
        }

        .stat-card:last-child { border-right: none; }
        .stat-card:hover { background: rgba(37,99,235,0.03); }

        .stat-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(30,64,175,0.08));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            color: var(--primary);
            font-size: 1.3rem;
        }

        .stat-number {
            font-size: 2.3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.3rem;
            letter-spacing: -0.03em;
            line-height: 1;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.01em;
        }

        /* ───── SECTION HEADERS ───── */
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

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
            max-width: 560px;
            margin: 0 auto;
            line-height: 1.65;
        }

        /* ───── CATEGORY CARDS ───── */
        .category-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem 1.5rem;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            height: 100%;
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: var(--shadow-sm);
        }

        .category-card:hover {
            border-color: var(--primary);
            transform: translateY(-8px) scale(1.01);
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(37,99,235,0.08);
        }

        .category-icon {
            width: 68px; height: 68px;
            background: linear-gradient(135deg, rgba(37,99,235,0.09), rgba(30,64,175,0.06));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.8rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .category-card:hover .category-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.08) rotate(-3deg);
            box-shadow: 0 8px 20px rgba(37,99,235,0.3);
        }

        .category-name {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.45rem;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .category-description {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }

        .category-count {
            font-size: 0.82rem;
            color: var(--primary);
            font-weight: 700;
        }

        /* ───── PROVIDER CARDS ───── */
        .provider-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            height: 100%;
            box-shadow: var(--shadow-sm);
        }

        .provider-card:hover {
            border-color: var(--primary);
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(37,99,235,0.08);
        }

        .provider-image {
            position: relative;
            height: 230px;
            overflow: hidden;
            background: linear-gradient(135deg, #334155, #475569);
        }

        .provider-image img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.55s cubic-bezier(0.25,0.46,0.45,0.94);
        }

        .avatar-letter {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            font-size: 3.5rem;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            user-select: none;
            letter-spacing: -0.02em;
        }

        .provider-card:hover .provider-image img { transform: scale(1.08); }

        .provider-badge {
            position: absolute;
            top: 0.9rem;
            padding: 0.35rem 0.75rem;
            border-radius: 100px;
            font-size: 0.72rem;
            font-weight: 700;
            backdrop-filter: blur(10px);
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-featured {
            left: 0.9rem;
            background: rgba(239,68,68,0.92);
            color: white;
        }

        .badge-available {
            right: 0.9rem;
            background: rgba(16,185,129,0.92);
            color: white;
        }

        .badge-new {
            left: 0.9rem;
            background: rgba(245,158,11,0.92);
            color: white;
        }

        .provider-content { padding: 1.4rem 1.4rem 1.2rem; }

        .provider-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            color: var(--dark);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.68rem;
            padding: 0.2rem 0.55rem;
            border-radius: 100px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .badge-verified { background: #d1fae5; color: #065f46; }
        .badge-gold     { background: #fef3c7; color: #92400e; }
        .badge-premium  { background: #e0e7ff; color: #3730a3; }

        .provider-profession {
            color: var(--secondary);
            font-size: 0.88rem;
            margin-bottom: 0.55rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
        }

        .provider-location {
            color: var(--text-muted);
            font-size: 0.82rem;
            margin-bottom: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .provider-rating { margin-bottom: 0.9rem; display: flex; align-items: center; gap: 0.4rem; }

        .rating-stars { color: #fbbf24; font-size: 0.85rem; }

        .rating-count { color: var(--text-muted); font-size: 0.82rem; }

        .provider-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
            margin-top: 0.25rem;
        }

        .provider-rate {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--success);
            letter-spacing: -0.01em;
        }

        .btn-view-profile {
            padding: 0.45rem 1.1rem;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.82rem;
            transition: all 0.25s ease;
            letter-spacing: 0.01em;
        }

        .btn-view-profile:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-primary);
        }

        /* ───── LOCATION CARDS ───── */
        .location-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1.25rem;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: var(--shadow-sm);
        }

        .location-card:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }

        .location-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 0.9rem;
            font-size: 1.35rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .location-card:hover .location-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .location-name {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .location-count { font-size: 0.8rem; color: var(--secondary); font-weight: 500; }

        /* ───── HOW IT WORKS ───── */
        .how-it-works-section { background: var(--surface-2); }

        .step-card {
            position: relative;
            text-align: center;
            padding: 2.5rem 1.75rem;
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .step-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .step-connector {
            display: none;
        }

        @media (min-width: 992px) {
            .step-connector {
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                top: 2rem;
            }
            .step-connector::before {
                content: '';
                display: block;
                width: 100%;
                height: 2px;
                background: linear-gradient(90deg, var(--primary-light), var(--border));
                border-radius: 1px;
            }
        }

        .step-number {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.4rem;
            color: white;
            font-size: 1.25rem;
            font-weight: 800;
            box-shadow: 0 8px 20px rgba(37,99,235,0.28);
            position: relative;
        }

        .step-number::after {
            content: '';
            position: absolute; inset: -4px;
            border-radius: 50%;
            border: 2px solid rgba(37,99,235,0.2);
        }

        .step-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1.25rem;
            display: block;
        }

        .step-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.6rem;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .step-description {
            color: var(--secondary);
            font-size: 0.9rem;
            line-height: 1.65;
        }

        /* ───── TRUST SECTION ───── */
        .trust-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            height: 100%;
        }

        .trust-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .trust-icon-wrap {
            width: 72px; height: 72px;
            border-radius: var(--radius-lg);
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
            transition: transform 0.3s ease;
        }

        .trust-card:hover .trust-icon-wrap { transform: scale(1.08); }

        /* ───── SERVICES ───── */
        .service-card-popular {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            position: relative;
            height: 100%;
            transition: all 0.3s ease;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .service-card-popular::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .service-card-popular:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }

        .service-card-popular:hover::before { opacity: 1; }

        .service-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .service-icon-popular {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }

        .service-card-popular:hover .service-icon-popular {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.08);
        }

        .service-badge-popular .badge {
            font-size: 0.68rem;
            padding: 0.3rem 0.7rem;
            border-radius: 100px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .service-card-body { margin-bottom: 1.5rem; }

        .service-title-popular {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.6rem;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        .service-description-popular {
            font-size: 0.88rem;
            color: var(--secondary);
            line-height: 1.6;
            margin-bottom: 0;
        }

        .service-card-footer {
            border-top: 1px solid var(--border-light);
            padding-top: 1rem;
            margin-bottom: 1rem;
        }

        .service-price-popular { margin-bottom: 0.75rem; }

        .price-amount {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--success);
            display: block;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .price-unit { font-size: 0.82rem; color: var(--text-muted); font-weight: 500; }

        .service-availability {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .provider-count-popular {
            font-size: 0.82rem;
            color: var(--success);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .service-rating-popular {
            font-size: 0.82rem;
            color: var(--warning);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .service-view-link {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .service-view-link:hover { color: var(--primary-dark); gap: 0.5rem; }

        .service-card-popular { position: relative; }
        .service-card-popular > *:not(.service-view-link) { position: relative; z-index: 1; }
        .service-view-link { position: absolute; bottom: 1.5rem; left: 1.5rem; z-index: 2; }

        /* ───── ICON WRAPPER ───── */
        .icon-wrapper { transition: transform 0.3s ease, background-color 0.3s ease; }
        .card:hover .icon-wrapper { transform: scale(1.08); background-color: rgba(37,99,235,0.14); }

        /* ───── FAQ ───── */
        .accordion-item {
            border: 1.5px solid var(--border) !important;
            border-radius: var(--radius-md) !important;
            margin-bottom: 0.6rem;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .accordion-item:hover { box-shadow: var(--shadow-sm); }

        .accordion-button {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            color: var(--dark);
            padding: 1rem 1.25rem;
            box-shadow: none !important;
            border: none !important;
            transition: all 0.25s ease;
            font-size: 0.95rem;
            letter-spacing: -0.005em;
            background: transparent !important;
        }

        .accordion-button:not(.collapsed) {
            color: var(--primary) !important;
            background: rgba(37,99,235,0.03) !important;
        }

        .accordion-button:focus {
            box-shadow: none !important;
        }

        .accordion-button::after { background-size: 1rem; transition: transform 0.3s ease; }
        .accordion-button:not(.collapsed)::after { transform: rotate(-180deg); }

        .accordion-body {
            padding-top: 0.25rem;
            padding-bottom: 0.75rem;
            color: var(--secondary);
            font-size: 0.92rem;
            line-height: 1.7;
        }

        .bg-opacity-10  { --bs-bg-opacity: 0.1; }
        .border-opacity-25 { --bs-border-opacity: 0.25; }

        /* ───── CTA SECTION ───── */
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 5.5rem 0;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .cta-section::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 70% at 80% 50%, rgba(255,255,255,0.08) 0%, transparent 65%),
                radial-gradient(ellipse 40% 50% at 10% 30%, rgba(255,255,255,0.06) 0%, transparent 55%);
            z-index: -1;
        }

        .cta-section::after {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 32px 32px;
            z-index: -1;
        }

        .cta-content { position: relative; z-index: 2; }

        .cta-title {
            font-size: clamp(1.8rem, 3.5vw, 2.6rem);
            font-weight: 800;
            margin-bottom: 0.9rem;
            letter-spacing: -0.03em;
        }

        .cta-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn-cta {
            padding: 0.95rem 2.4rem;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: 0.01em;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0,0,0,0.18);
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.25);
        }

        /* ───── FOOTER ───── */
        .footer {
            background: var(--dark);
            padding: 4.5rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer-title {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            margin-bottom: 1.4rem;
            color: white;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .footer-link {
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.65rem;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .footer-link:hover {
            color: white;
            gap: 0.6rem;
        }

        .footer-link::before {
            content: '›';
            font-size: 1rem;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .footer-link:hover::before { opacity: 1; }

        .social-link {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-sm);
            color: #94a3b8;
            margin-right: 0.6rem;
            transition: all 0.25s ease;
            font-size: 0.9rem;
        }

        .social-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37,99,235,0.3);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.07);
            padding-top: 2rem;
            margin-top: 3.5rem;
            text-align: center;
            color: #475569;
            font-size: 0.88rem;
        }

        /* ───── UTILS ───── */
        .bg-surface-2 { background: var(--surface-2); }
        .rounded-xl { border-radius: var(--radius-xl) !important; }
        .rounded-lg { border-radius: var(--radius-lg) !important; }

        /* ───── ANIMATIONS ───── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fade-in-up {
            animation: fadeInUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        /* ───── RESPONSIVE ───── */
        @media (max-width: 768px) {
            .hero { padding: 80px 0 60px; }
            .hero h1 { font-size: 2.1rem; }
            .section-title { font-size: 1.8rem; }
            .search-box { padding: 1.5rem; }
            .stat-card { border-right: none; border-bottom: 1px solid var(--border); }
            .stat-card:last-child { border-bottom: none; }
            .accordion-button { padding: 0.875rem 1rem; font-size: 0.9rem; }
        }

        @media (max-width: 576px) {
            .service-card-popular { padding: 1.25rem; }
            .service-icon-popular { width: 48px; height: 48px; font-size: 1.25rem; }
            .service-title-popular { font-size: 0.95rem; }
            .price-amount { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <span style="width:32px;height:32px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:9px;display:inline-flex;align-items:center;justify-content:center;">
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
                        <a class="nav-link fw-semibold" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
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

    <!-- Hero Section with Enhanced Search -->
    <section class="hero">
        <div class="container hero-content">
            <div class="row justify-content-center">
                <div class="col-lg-10 text-center">
                    <div class="d-inline-flex align-items-center gap-2 mb-4" style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:100px;padding:0.4rem 1.1rem;backdrop-filter:blur(8px);">
                        <span style="width:8px;height:8px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 0 3px rgba(16,185,129,0.3);"></span>
                        <span class="text-white" style="font-size:0.82rem;font-weight:600;letter-spacing:0.04em;">TRUSTED PROFESSIONALS IN RWANDA</span>
                    </div>
                    <h1 class="text-white mb-4">Find Verified Local<br>Professionals Near You</h1>
                    <p class="text-white mb-5" style="max-width:560px;margin-left:auto;margin-right:auto;"><?php echo htmlspecialchars($platform_description); ?></p>
                    
                    <div class="search-box">
                        <form action="providers.php" method="GET" id="advancedSearchForm">
                            <div class="row g-3">
                                <!-- Service Search -->
                                <div class="col-md-4">
                                    <div class="search-input-group">
                                        <i class="fas fa-search search-icon"></i>
                                        <input type="text" name="query" class="form-control" 
                                               placeholder="What service do you need?" 
                                               id="serviceSearch">
                                    </div>
                                </div>
                                
                                <!-- Location Filter -->
                                <div class="col-md-4">
                                    <div class="search-input-group">
                                        <i class="fas fa-map-marker-alt search-icon"></i>
                                        <select name="district" class="form-select" id="districtFilter">
                                            <option value="">All Districts</option>
                                            <?php foreach ($districts as $district): ?>
                                                <option value="<?php echo htmlspecialchars($district['code']); ?>">
                                                    <?php echo htmlspecialchars($district['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Category Filter -->
                                <div class="col-md-4">
                                    <div class="search-input-group">
                                        <i class="fas fa-th-large search-icon"></i>
                                        <select name="category" class="form-select" id="categoryFilter">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Advanced Filters (Collapsible) -->
                            <div class="collapse mt-3" id="advancedFilters">
                                <div class="row g-3">
                                    <!-- Rating Filter -->
                                    <div class="col-md-3">
                                        <select name="min_rating" class="form-select">
                                            <option value="">Any Rating</option>
                                            <option value="4.5">4.5+ Stars</option>
                                            <option value="4.0">4.0+ Stars</option>
                                            <option value="3.5">3.5+ Stars</option>
                                            <option value="3.0">3.0+ Stars</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Price Range Filter -->
                                    <div class="col-md-3">
                                        <select name="price_range" class="form-select">
                                            <option value="">Any Price</option>
                                            <option value="0-5000">Under 5,000 RWF/hr</option>
                                            <option value="5000-10000">5,000 - 10,000 RWF/hr</option>
                                            <option value="10000-20000">10,000 - 20,000 RWF/hr</option>
                                            <option value="20000+">20,000+ RWF/hr</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Experience Filter -->
                                    <div class="col-md-3">
                                        <select name="experience" class="form-select">
                                            <option value="">Any Experience</option>
                                            <option value="5+">5+ Years</option>
                                            <option value="3+">3+ Years</option>
                                            <option value="1+">1+ Years</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Verification Filter -->
                                    <div class="col-md-3">
                                        <select name="verification" class="form-select">
                                            <option value="">Any Verification</option>
                                            <option value="premium">Premium</option>
                                            <option value="gold">Gold</option>
                                            <option value="verified">Verified</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12 d-flex justify-content-center gap-2">
                                    <button type="submit" class="btn btn-primary btn-search">
                                        <i class="fas fa-search me-2"></i>Search Providers
                                    </button>
                                    <button type="button" class="advanced-toggle" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                        <i class="fas fa-sliders-h me-2"></i>Advanced Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Quick Filter Chips -->
                        <div class="quick-filters text-center mt-3">
                            <small class="text-muted d-block mb-2">Popular searches:</small>
                            <a href="providers.php?category=1" class="filter-chip">
                                <i class="fas fa-wrench me-1"></i>Plumbers
                            </a>
                            <a href="providers.php?category=2" class="filter-chip">
                                <i class="fas fa-bolt me-1"></i>Electricians
                            </a>
                            <a href="providers.php?category=3" class="filter-chip">
                                <i class="fas fa-paint-roller me-1"></i>Painters
                            </a>
                            <a href="providers.php?verification=premium" class="filter-chip">
                                <i class="fas fa-star me-1"></i>Premium Only
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Browse by Category -->
    <section class="py-5">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-th-large"></i> Service Categories</div>
                <h2 class="section-title">Browse by Category</h2>
                <p class="section-subtitle">Find the right professional for your needs</p>
            </div>
            <div class="row g-4">
                <?php foreach (array_slice($categories, 0, 8) as $category): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="providers.php?category=<?php echo $category['id']; ?>" class="category-card">
                        <div class="category-icon">
                            <i class="fas <?php echo $category['icon']; ?>"></i>
                        </div>
                        <h5 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h5>
                        <p class="category-description"><?php echo htmlspecialchars(substr($category['description'], 0, 60)); ?><?php echo strlen($category['description']) > 60 ? '...' : ''; ?></p>
                        <?php if ($category['is_premium']): ?>
                            <span class="badge bg-warning text-dark mt-2">Premium</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($categories) > 8): ?>
            <div class="text-center mt-4">
                <a href="services.php#service-categories" class="btn btn-outline-primary btn-lg">
                    View All Categories <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Featured Providers -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-star"></i> Top Professionals</div>
                <h2 class="section-title">Featured Service Providers</h2>
                <p class="section-subtitle">Top-rated professionals ready to help you</p>
            </div>
            <?php if (empty($featured_providers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No featured providers available</h4>
                    <p class="text-muted">Check back later for featured service providers</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($featured_providers as $provider): ?>
                    <div class="col-sm-6 col-lg-3">
                        <div class="provider-card">
                            <div class="provider-image">
                                <?php
                                    // determine profile image URL (fallback to default)
                                    $profileImage = $provider['profile_image'] ?? '';
                                    $path1 = 'uploads/profiles/' . $profileImage;
                                    $path2 = 'uploads/' . $profileImage;
                                    if ($profileImage && file_exists(__DIR__ . '/' . $path1)) {
                                        $imgUrl = $path1;
                                    } elseif ($profileImage && file_exists(__DIR__ . '/' . $path2)) {
                                        $imgUrl = $path2;
                                    } else {
                                        $imgUrl = false; // render initial instead
                                    }
                                ?>
                            <?php if ($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($provider['full_name']); ?>" loading="lazy">
                            <?php else: ?>
                                <?php $initial = strtoupper(mb_substr(trim($provider['full_name'] ?? ''), 0, 1)); ?>
                                <div class="avatar-letter" aria-hidden="true"><?php echo htmlspecialchars($initial ?: 'U'); ?></div>
                            <?php endif; ?>
                            <span class="provider-badge badge-featured">
                                <i class="fas fa-star me-1"></i>Featured
                            </span>
                            <span class="provider-badge badge-available">
                                <i class="fas fa-check me-1"></i>Available
                            </span>
                            </div>
                            <div class="provider-content">
                                <h5 class="provider-name">
                                    <?php echo htmlspecialchars($provider['full_name']); ?>
                                    <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                                        <span class="verification-badge badge-<?php echo $provider['verification_level']; ?>">
                                            <i class="fas fa-shield-alt me-1"></i><?php echo ucfirst($provider['verification_level']); ?>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <div class="provider-profession">
                                    <i class="fas fa-briefcase me-2"></i>
                                    <?php echo htmlspecialchars($provider['profession']); ?>
                                </div>
                                <div class="provider-location">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($provider['location']); ?>
                                    <?php if ($provider['district']): ?>, <?php echo htmlspecialchars($provider['district']); ?><?php endif; ?>
                                </div>
                                <div class="provider-rating">
                                    <span class="rating-stars">
                                        <?php 
                                        $rating = $provider['average_rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++): 
                                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        endfor; ?>
                                    </span>
                                    <span class="rating-count">(<?php echo $provider['total_reviews'] ?? 0; ?>)</span>
                                </div>
                                <div class="provider-footer">
                                    <span class="provider-rate">
                                        <?php echo number_format($provider['hourly_rate'] ?? 0); ?> RWF/hr
                                    </span>
                                    <a href="provider-profile.php?id=<?php echo $provider['provider_id']; ?>" 
                                       class="btn btn-outline-primary btn-view-profile"> Hire Now </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-5">
                    <a href="providers.php" class="btn btn-primary btn-lg px-5">
                        View All Providers <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>


        <!-- Why Choose BII LocalFinder - Trust Section -->
    <section class="py-5">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-shield-alt"></i> Why Choose Us</div>
                <h2 class="section-title">Why Choose BII LocalFinder</h2>
                <p class="section-subtitle">Trust, quality, and transparency — your reliable local service marketplace</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="trust-card">
                        <div class="trust-icon-wrap bg-primary bg-opacity-10" style="width:72px;height:72px;">
                            <i class="fas fa-shield-check fa-2x text-primary"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="letter-spacing:-0.01em;">Verified Providers</h5>
                        <p class="text-muted mb-0" style="font-size:0.9rem;line-height:1.65;">Every provider is thoroughly reviewed and verified before appearing on our platform.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="trust-card">
                        <div class="trust-icon-wrap bg-success bg-opacity-10" style="width:72px;height:72px;">
                            <i class="fas fa-star fa-2x text-success"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="letter-spacing:-0.01em;">Real Ratings & Reviews</h5>
                        <p class="text-muted mb-0" style="font-size:0.9rem;line-height:1.65;">Authentic feedback from real customers to help you make informed decisions.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="trust-card">
                        <div class="trust-icon-wrap bg-warning bg-opacity-10" style="width:72px;height:72px;">
                            <i class="fas fa-map-marker-alt fa-2x text-warning"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="letter-spacing:-0.01em;">Local Professionals Near You</h5>
                        <p class="text-muted mb-0" style="font-size:0.9rem;line-height:1.65;">Find trusted service providers in your neighborhood for quick and reliable service.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="trust-card">
                        <div class="trust-icon-wrap bg-info bg-opacity-10" style="width:72px;height:72px;">
                            <i class="fas fa-lock fa-2x text-info"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="letter-spacing:-0.01em;">Secure & Transparent Platform</h5>
                        <p class="text-muted mb-0" style="font-size:0.9rem;line-height:1.65;">Your safety is our priority with secure payments and clear service terms.</p>
                    </div>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-12">
                    <div style="background:linear-gradient(135deg,var(--primary-light),rgba(219,234,254,0.4));border:1.5px solid rgba(37,99,235,0.14);border-radius:var(--radius-lg);padding:2.5rem 3rem;" class="p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-3 fw-bold" style="letter-spacing:-0.02em;">Better than WhatsApp or Asking Friends</h4>
                                <p class="mb-0 text-muted" style="font-size:0.95rem;line-height:1.7;">
                                    Unlike random WhatsApp recommendations or friends' suggestions, we provide verified professionals with documented experience, real customer reviews, and clear pricing. No more uncertainty about quality, reliability, or fair pricing.
                                </p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-4 mt-md-0">
                                <div class="d-inline-flex align-items-center bg-white px-4 py-3 rounded-3 shadow-sm gap-3">
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                    <div class="text-start">
                                        <div class="h3 mb-0 fw-bold" style="letter-spacing:-0.03em;">100%</div>
                                        <small class="text-muted fw-500">Verified Trust</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Browse by Location -->
    <?php if (!empty($nearby_providers)): ?>
    <section class="py-5">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-map-pin"></i> Browse by District</div>
                <h2 class="section-title">Find Providers Near You</h2>
                <p class="section-subtitle">Browse professionals in popular locations</p>
            </div>
            <div class="row g-4">
                <?php foreach ($nearby_providers as $location): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="providers.php?district=<?php echo urlencode($location['district']); ?>" class="location-card">
                        <div class="location-icon">
                            <i class="fas fa-map-pin"></i>
                        </div>
                        <h6 class="location-name"><?php echo htmlspecialchars($location['district']); ?></h6>
                        <p class="location-count"><?php echo $location['provider_count']; ?> providers</p>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>


        <!-- Popular Services Near You (Demand-Based Section) -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Popular Services Near You</h2>
                <p class="section-subtitle">Most requested services in your area</p>
            </div>
            
            <?php
            // Fetch popular individual services (not just categories)
            $popular_services_data = cache_get('popular_individual_services', 300);
            if ($popular_services_data === false) {
                $providerWhere = ["u.is_verified = 1", "sp.availability = 'available'", "ps.is_available = 1"];
                if ($hasIsActive) $providerWhere[] = "sp.is_active = 1";
                if ($hasIsBanned) $providerWhere[] = "sp.is_banned = 0";
                $providerWhereSql = implode(' AND ', $providerWhere);
                
                // Get most popular individual services based on bookings or provider count
                $stmt = $db->prepare("
                    SELECT 
                        ps.id as service_id,
                        ps.name as service_name,
                        ps.description as service_description,
                        ps.price as service_price,
                        ps.payment_type,
                        ps.duration,
                        GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_name,
                        GROUP_CONCAT(DISTINCT c.icon SEPARATOR ', ') as category_icon,
                        COUNT(DISTINCT sp.id) as provider_count,
                        COUNT(DISTINCT b.id) as booking_count,
                        AVG(sp.average_rating) as avg_rating
                    FROM provider_services ps
                    JOIN service_providers sp ON ps.provider_id = sp.id
                    JOIN users u ON sp.user_id = u.id
                    JOIN categories c ON ps.category_id = c.id
                    LEFT JOIN bookings b ON ps.id = b.service_id AND b.status = 'completed'
                    WHERE {$providerWhereSql}
                    AND c.is_active = 1
                    GROUP BY ps.id, ps.name, ps.description, ps.price, ps.payment_type
                    HAVING provider_count > 0
                    ORDER BY booking_count DESC, provider_count DESC
                    LIMIT 8
                ");
                $stmt->execute();
                $popular_services_data = $stmt->fetchAll();
                cache_set('popular_individual_services', $popular_services_data);
            }
            
            // If no data from actual services, show default services
            if (empty($popular_services_data)) {
                $default_services = [
                    [
                        'service_name' => 'Plumbing Repair', 
                        'service_description' => 'Fix leaks, install pipes, unclog drains',
                        'service_price' => 15000, 
                        'payment_type' => 'per_service',
                        'category_icon' => 'fa-wrench',
                        'provider_count' => 23
                    ],
                    [
                        'service_name' => 'Electrical Wiring', 
                        'service_description' => 'Install outlets, fix circuits, lighting',
                        'service_price' => 12000, 
                        'payment_type' => 'per_hour',
                        'category_icon' => 'fa-bolt',
                        'provider_count' => 18
                    ],
                    [
                        'service_name' => 'Phone Screen Repair', 
                        'service_description' => 'Fix cracked screens, replace batteries',
                        'service_price' => 25000, 
                        'payment_type' => 'per_service',
                        'category_icon' => 'fa-mobile-alt',
                        'provider_count' => 15
                    ],
                    [
                        'service_name' => 'House Cleaning', 
                        'service_description' => 'Deep cleaning, regular maintenance',
                        'service_price' => 8000, 
                        'payment_type' => 'per_hour',
                        'category_icon' => 'fa-broom',
                        'provider_count' => 27
                    ],
                    [
                        'service_name' => 'Car Maintenance', 
                        'service_description' => 'Oil change, brake repair, diagnostics',
                        'service_price' => 20000, 
                        'payment_type' => 'per_service',
                        'category_icon' => 'fa-car',
                        'provider_count' => 12
                    ],
                    [
                        'service_name' => 'Interior Painting', 
                        'service_description' => 'Wall painting, color consultation',
                        'service_price' => 10000, 
                        'payment_type' => 'per_day',
                        'category_icon' => 'fa-paint-roller',
                        'provider_count' => 14
                    ],
                    [
                        'service_name' => 'Furniture Making', 
                        'service_description' => 'Custom furniture, repairs, installations',
                        'service_price' => 18000, 
                        'payment_type' => 'per_service',
                        'category_icon' => 'fa-hammer',
                        'provider_count' => 11
                    ],
                    [
                        'service_name' => 'Garden Maintenance', 
                        'service_description' => 'Lawn care, planting, landscaping',
                        'service_price' => 7000, 
                        'payment_type' => 'per_hour',
                        'category_icon' => 'fa-leaf',
                        'provider_count' => 9
                    ]
                ];
                $popular_services_data = $default_services;
            }
            ?>
            
            <div class="row g-4">
                <?php foreach ($popular_services_data as $service): 
                    // Format payment type for display
                    $payment_types = [
                        'fixed_price' => 'Fixed Price',
                        'hourly_rate' => 'Hourly Rate',
                        'per_job_estimate' => 'Per Job Estimate',
                        'per_day' => 'Per Day',
                        'per_service' => 'Per Service', 
                        'base_price' => 'Base Price'
                    ];
                    $payment_type_display = $payment_types[$service['payment_type']] ?? 'Per Service';
                    
                    // Shorten description if too long
                    $short_description = strlen($service['service_description']) > 70 ? 
                        substr($service['service_description'], 0, 70) . '...' : 
                        $service['service_description'];
                ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="service-card-popular">
                        <div class="service-card-header">
                            <div class="service-icon-popular">
                                <i class="fas <?php echo $service['category_icon'] ?? 'fa-tools'; ?>"></i>
                            </div>
                            <div class="service-badge-popular">
                                <span class="badge bg-primary"><?php echo $payment_type_display; ?></span>
                            </div>
                        </div>
                        
                        <div class="service-card-body">
                            <h5 class="service-title-popular">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                            </h5>
                            <p class="service-description-popular">
                                <?php echo htmlspecialchars($short_description); ?>
                            </p>
                        </div>
                        
                        <div class="service-card-footer">
                            <div class="service-price-popular">
                                <span class="price-amount">
                                    RWF <?php echo number_format($service['service_price']); ?>
                                </span>
                                <small class="price-unit">
                                    <?php 
                                        echo strtolower(str_replace('Per ', '', $payment_type_display));
                                        if (isset($service['duration'])) {
                                            echo ' • ' . $service['duration'] . ' mins';
                                        }
                                    ?>
                                </small>
                            </div>
                            <div class="service-availability">
                                <span class="provider-count-popular">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $service['provider_count']; ?> available
                                </span>
                                <?php if (isset($service['avg_rating']) && $service['avg_rating'] > 0): ?>
                                <span class="service-rating-popular">
                                    <i class="fas fa-star text-warning me-1"></i>
                                    <?php echo number_format($service['avg_rating'], 1); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <a href="providers.php?service_id=<?php echo isset($service['service_id']) ? intval($service['service_id']) : 0; ?>&query=<?php echo urlencode($service['service_name']); ?>" 
                           class="stretched-link service-view-link">
                            Find Providers <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($popular_services_data) >= 8): ?>
            <div class="text-center mt-4">
                <a href="services.php" class="btn btn-outline-primary btn-lg">
                    Browse All Services <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>



    <!-- Recently Joined Providers -->
    <?php if (!empty($recent_providers)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">New to <?php echo htmlspecialchars($platform_name); ?></h2>
                <p class="section-subtitle">Recently joined service providers</p>
            </div>
            <div class="row g-4">
                <?php foreach ($recent_providers as $provider): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="provider-card">
                        <div class="provider-image">
                            <?php
                                // determine profile image URL (fallback to default)
                                $profileImage = $provider['profile_image'] ?? '';
                                $path1 = 'uploads/profiles/' . $profileImage;
                                $path2 = 'uploads/' . $profileImage;
                                if ($profileImage && file_exists(__DIR__ . '/' . $path1)) {
                                    $imgUrl = $path1;
                                } elseif ($profileImage && file_exists(__DIR__ . '/' . $path2)) {
                                    $imgUrl = $path2;
                                } else {
                                    $imgUrl = false; // render initial instead
                                }
                            ?>
                            <?php if ($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($provider['full_name']); ?>" loading="lazy">
                            <?php else: ?>
                                <?php $initial = strtoupper(mb_substr(trim($provider['full_name'] ?? ''), 0, 1)); ?>
                                <div class="avatar-letter" aria-hidden="true"><?php echo htmlspecialchars($initial ?: 'U'); ?></div>
                            <?php endif; ?>
                            <span class="provider-badge badge-new">
                                <i class="fas fa-certificate me-1"></i>New
                            </span>
                        </div>
                        <div class="provider-content">
                            <h5 class="provider-name">
                                <?php echo htmlspecialchars($provider['full_name']); ?>
                                <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                                    <span class="verification-badge badge-<?php echo $provider['verification_level']; ?>">
                                        <i class="fas fa-shield-alt me-1"></i><?php echo ucfirst($provider['verification_level']); ?>
                                    </span>
                                <?php endif; ?>
                            </h5>
                            <div class="provider-profession">
                                <i class="fas fa-briefcase me-2"></i>
                                <?php echo htmlspecialchars($provider['profession']); ?>
                            </div>
                            <div class="provider-location">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($provider['location']); ?>
                                <?php if ($provider['district']): ?>, <?php echo htmlspecialchars($provider['district']); ?><?php endif; ?>
                            </div>
                            <div class="provider-rating">
                                <span class="rating-stars">
                                    <?php 
                                    $rating = $provider['average_rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): 
                                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    endfor; ?>
                                </span>
                                <span class="rating-count">(<?php echo $provider['total_reviews'] ?? 0; ?>)</span>
                            </div>
                            <div class="provider-footer mt-3">
                                <a href="provider-profile.php?id=<?php echo $provider['provider_id']; ?>" 
                                   class="btn btn-outline-primary btn-view-profile w-100">
                                    View Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- How It Works -->
    <section class="py-5">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">How It Works</h2>
                <p class="section-subtitle">Find and hire professionals in 4 easy steps</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h5 class="step-title">Search Services</h5>
                        <p class="step-description">Browse or search for the service you need in your location</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h5 class="step-title">Compare Providers</h5>
                        <p class="step-description">View profiles, ratings, and reviews to find the right professional</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h5 class="step-title">Book Service</h5>
                        <p class="step-description">Contact the provider and schedule your service</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h5 class="step-title">Leave Review</h5>
                        <p class="step-description">Rate and review the service to help others</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

        <!-- Frequently Asked Questions (Trust & Clarity Section) -->
    <section class="py-5">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">Clear answers to common questions about our platform</p>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <!-- Client Questions -->
                    <div class="mb-5">
                        <h3 class="h4 mb-4 text-primary">
                            <i class="fas fa-users me-2"></i> For Clients
                        </h3>
                        <div class="accordion" id="clientFAQ">
                            <!-- Q1: Is it free to use? -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq1" 
                                            aria-expanded="true" aria-controls="faq1">
                                        <strong>Is it free to use BII LocalFinder?</strong>
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse show" 
                                     data-bs-parent="#clientFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex mb-2">
                                            <div class="me-3">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-check-circle text-success fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold text-success">Yes, completely free for clients.</p>
                                            </div>
                                        </div>
                                        <p class="mb-0 ps-4">
                                            Browsing services, searching providers, and contacting professionals is completely free for clients. 
                                            You only pay the service provider directly for the work done.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Q2: How do I contact a provider? -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq2">
                                        <strong>How do I contact a service provider?</strong>
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" 
                                     data-bs-parent="#clientFAQ">
                                    <div class="accordion-body pt-3">
                                        <p class="mb-3">You can contact providers directly in two ways:</p>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="bg-light rounded-3 p-3 mb-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                                            <i class="fas fa-phone text-primary"></i>
                                                        </div>
                                                        <strong>Direct Phone Call</strong>
                                                    </div>
                                                    <p class="small mb-0">View provider's phone number on their profile</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="bg-light rounded-3 p-3 mb-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                                            <i class="fab fa-whatsapp text-success"></i>
                                                        </div>
                                                        <strong>WhatsApp (if available)</strong>
                                                    </div>
                                                    <p class="small mb-0">Many providers use WhatsApp for quick communication</p>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mb-0 fw-semibold">No registration needed to contact providers. No middleman.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Q3: Are providers verified? -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq3">
                                        <strong>Are providers on BII LocalFinder verified?</strong>
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" 
                                     data-bs-parent="#clientFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                        <i class="fas fa-shield-check text-success fa-lg"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <p class="mb-0 fw-semibold">Yes, all providers go through verification.</p>
                                                </div>
                                            </div>
                                            <p>
                                                Providers go through an identity and profile review process before appearing on the platform. 
                                                Verified providers are clearly marked on their profiles.
                                            </p>
                                        </div>
                                        
                                        <div class="bg-light rounded-3 p-3">
                                            <h6 class="mb-2">Verification Levels:</h6>
                                            <div class="row g-2">
                                                <div class="col-12">
                                                    <div class="d-flex align-items-center p-2 bg-white rounded-2">
                                                        <span class="badge bg-success me-3">✓ Verified</span>
                                                        <small>Identity confirmed, basic profile review</small>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex align-items-center p-2 bg-white rounded-2">
                                                        <span class="badge bg-warning text-dark me-3">⭐ Gold</span>
                                                        <small>High ratings, verified experience</small>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex align-items-center p-2 bg-white rounded-2">
                                                        <span class="badge bg-primary me-3">💎 Premium</span>
                                                        <small>Top-rated professionals, premium service</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Q4: Ratings & Reviews -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq4">
                                        <strong>How do ratings and reviews work?</strong>
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" 
                                     data-bs-parent="#clientFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-star text-warning fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold">Real reviews from real clients.</p>
                                            </div>
                                        </div>
                                        <p>
                                            After a service is completed, clients can leave a rating and review based on their actual experience. 
                                            These reviews help others choose trusted professionals.
                                        </p>
                                        
                                        <div class="bg-light rounded-3 p-3 mt-3">
                                            <div class="d-flex align-items-start">
                                                <i class="fas fa-info-circle text-primary mt-1 me-2"></i>
                                                <div>
                                                    <small class="text-muted">
                                                        Reviews are only accepted from clients who have actually booked and completed services. 
                                                        This ensures authenticity and helps build trust in our community.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Q5: Payment handling -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq5">
                                        <strong>Is payment handled on the platform?</strong>
                                    </button>
                                </h2>
                                <div id="faq5" class="accordion-collapse collapse" 
                                     data-bs-parent="#clientFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-money-bill-wave text-info fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold">Direct payments to providers.</p>
                                            </div>
                                        </div>
                                        <p class="mb-3">
                                            Currently, payments are made directly between you and the service provider. 
                                            We focus on connecting you with trusted professionals and ensuring a smooth service experience.
                                        </p>
                                        <div class="alert alert-info mb-0">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-rocket me-2"></i>
                                                <div>
                                                    <strong>Coming Soon:</strong> Secure online payment options will be introduced in future updates.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <!-- Provider Questions -->
                    <div class="mb-5">
                        <h3 class="h4 mb-4 text-success">
                            <i class="fas fa-briefcase me-2"></i> For Service Providers
                        </h3>
                        <div class="accordion" id="providerFAQ">
                            <!-- Q6: How to become a provider -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq6" 
                                            aria-expanded="true">
                                        <strong>How can I become a service provider?</strong>
                                    </button>
                                </h2>
                                <div id="faq6" class="accordion-collapse collapse show" 
                                     data-bs-parent="#providerFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-user-plus text-success fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold">Simple registration process.</p>
                                            </div>
                                        </div>
                                        <p class="mb-3">
                                            You can register as a service provider by clicking "Register as Provider" and completing your profile. 
                                            Once your profile is reviewed and approved, it becomes visible to clients in your area.
                                        </p>
                                        <div class="text-center">
                                            <a href="register.php?type=provider" class="btn btn-success btn-sm">
                                                <i class="fas fa-user-plus me-2"></i> Register as Provider
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Q7: Is provider registration free? -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq7">
                                        <strong>Is provider registration free?</strong>
                                    </button>
                                </h2>
                                <div id="faq7" class="accordion-collapse collapse" 
                                     data-bs-parent="#providerFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-check-circle text-success fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold">Yes, basic registration is completely free.</p>
                                            </div>
                                        </div>
                                        <p>
                                            There are no hidden fees. You can create your profile, list your services, and start getting clients without any cost.
                                        </p>
                                        <div class="bg-light rounded-3 p-3">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Optional premium features may be introduced in the future to help increase your visibility, 
                                                but basic access will always remain free.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Q8: How to get more clients -->
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq8">
                                        <strong>How do I get more clients?</strong>
                                    </button>
                                </h2>
                                <div id="faq8" class="accordion-collapse collapse" 
                                     data-bs-parent="#providerFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-chart-line text-primary fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold">Complete profile = More clients</p>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-6">
                                                <div class="bg-white border rounded-2 p-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="bg-success bg-opacity-10 rounded-circle p-1 me-2">
                                                            <i class="fas fa-user-check text-success"></i>
                                                        </div>
                                                        <small class="fw-semibold">Complete Profile</small>
                                                    </div>
                                                    <small class="text-muted">100% profile completion</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="bg-white border rounded-2 p-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="bg-warning bg-opacity-10 rounded-circle p-1 me-2">
                                                            <i class="fas fa-star text-warning"></i>
                                                        </div>
                                                        <small class="fw-semibold">Good Ratings</small>
                                                    </div>
                                                    <small class="text-muted">Positive client reviews</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="bg-white border rounded-2 p-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="bg-info bg-opacity-10 rounded-circle p-1 me-2">
                                                            <i class="fas fa-clock text-info"></i>
                                                        </div>
                                                        <small class="fw-semibold">Fast Response</small>
                                                    </div>
                                                    <small class="text-muted">Quick reply to inquiries</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="bg-white border rounded-2 p-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="bg-primary bg-opacity-10 rounded-circle p-1 me-2">
                                                            <i class="fas fa-images text-primary"></i>
                                                        </div>
                                                        <small class="fw-semibold">Portfolio Photos</small>
                                                    </div>
                                                    <small class="text-muted">Show your previous work</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <p class="mb-0 small text-muted">
                                            Providers with complete profiles, good ratings, and fast response times are 
                                            more likely to get contacted by clients.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Q9: Edit profile -->
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-light rounded-3 collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq9">
                                        <strong>Can I edit my profile later?</strong>
                                    </button>
                                </h2>
                                <div id="faq9" class="accordion-collapse collapse" 
                                     data-bs-parent="#providerFAQ">
                                    <div class="accordion-body pt-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                                    <i class="fas fa-edit text-primary fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold">Yes, full control at any time.</p>
                                            </div>
                                        </div>
                                        <p>
                                            Providers can update their profile information, service offerings, availability, 
                                            and pricing at any time from their dashboard. Your changes appear immediately.
                                        </p>
                                        <div class="bg-light rounded-3 p-3">
                                            <div class="d-flex">
                                                <i class="fas fa-sync-alt text-primary mt-1 me-2"></i>
                                                <div>
                                                    <small class="text-muted">
                                                        Regular updates to your profile, services, and portfolio can help attract more clients 
                                                        and keep your business growing.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Still have questions? -->
                    <div class="bg-primary bg-opacity-5 rounded-3 p-4 border border-primary border-opacity-25">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <div class="bg-primary rounded-circle p-2">
                                    <i class="fas fa-question-circle fa-lg text-white"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-0">Still have questions?</h5>
                            </div>
                        </div>
                        <p class="mb-3">We're here to help you get the most out of BII LocalFinder.</p>
                        <div class="d-flex gap-2">
                            <a href="contact.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope me-2"></i> Contact Support
                            </a>
                            <a href="faq.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-list-alt me-2"></i> View Full FAQ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section text-white">
        <div class="container cta-content text-center">
            <h2 class="cta-title">Are You a Service Provider?</h2>
            <p class="cta-subtitle">Join <?php echo htmlspecialchars($platform_name); ?> and connect with thousands of potential clients</p>
            <?php if (getPlatformSetting('provider_registration', '1')): ?>
                <a href="register.php?type=provider" class="btn btn-light btn-cta">
                    <i class="fas fa-user-plus me-2"></i>Register as Provider
                </a>
            <?php else: ?>
                <button class="btn btn-light btn-cta" disabled>
                    <i class="fas fa-pause me-2"></i>Provider Registration Temporarily Closed
                </button>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title"><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-secondary"><?php echo htmlspecialchars($platform_description); ?></p>
                    <div class="social-links mt-4">
                        <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title">Quick Links</h5>
                    <a href="about.php" class="footer-link">About Us</a>
                    <a href="services.php" class="footer-link">Services</a>
                    <a href="providers.php" class="footer-link">Find Providers</a>
                    <a href="contact.php" class="footer-link">Contact</a>
                    <a href="faq.php" class="footer-link">FAQ</a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title">For Providers</h5>
                    <a href="register.php?type=provider" class="footer-link">Register</a>
                    <a href="login.php" class="footer-link">Login</a>
                    <a href="how-it-works.php" class="footer-link">How It Works</a>
                    <a href="pricing.php" class="footer-link">Pricing</a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title">Contact Us</h5>
                    <p class="text-secondary mb-2">
                        <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($contact_email); ?>
                    </p>
                    <p class="text-secondary">
                        <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($contact_phone); ?>
                    </p>
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
        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.style.boxShadow = '0 4px 24px rgba(15,23,42,0.10)';
            } else {
                navbar.style.boxShadow = 'none';
            }
        }, { passive: true });

        // Animated stats counter
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stat-number');
            const speed = 200;
            
            const animateCounter = (counter) => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                if (isNaN(target)) return;
                const increment = target / speed;
                let current = 0;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.ceil(current).toLocaleString();
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };
                updateCounter();
            };
            
            // Intersection Observer for scroll-triggered animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        if (el.classList.contains('stat-number')) {
                            animateCounter(el);
                        }
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.2 });

            counters.forEach(counter => observer.observe(counter));

            // Animate cards on scroll
            document.querySelectorAll('.category-card, .provider-card, .trust-card, .step-card, .service-card-popular, .location-card').forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(18px)';
                el.style.transition = `opacity 0.5s cubic-bezier(0.16,1,0.3,1) ${(i % 4) * 0.07}s, transform 0.5s cubic-bezier(0.16,1,0.3,1) ${(i % 4) * 0.07}s`;
                observer.observe(el);
            });
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>