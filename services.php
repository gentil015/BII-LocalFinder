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
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        .hero-services {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 5rem 0;
            text-align: center;
        }

        .hero-services {
            background: linear-gradient(rgba(13, 110, 253, 0.7), rgba(10, 88, 202, 0.7)), url('assets/images/services.jpg') center/cover no-repeat;
            color: white;
            padding: 5rem 0;
            text-align: center;
            min-height: 400px;
            display: flex;
            align-items: center;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .section-subtitle {
            font-size: 1.2rem;
            color: var(--secondary);
            margin-bottom: 3rem;
            max-width: 700px;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .service-card {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
        }
        
        .service-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }
        
        .service-features {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0 0 0;
        }
        
        .service-features li {
            padding: 0.5rem 0;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .service-features li i {
            color: var(--success);
            font-size: 1.1rem;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .category-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: none;
            display: block;
            text-align: center;
            position: relative;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            text-decoration: none;
            color: inherit;
        }
        
        .category-icon {
            width: 70px;
            height: 70px;
            background: #e0e7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .provider-count {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .premium-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, var(--warning), #e0a800);
            color: #000;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .how-it-works {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 5rem 0;
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .step-item {
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .step-item h4 {
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .step-item p {
            opacity: 0.9;
            margin: 0;
        }
        
        .cta-section {
            padding: 5rem 0;
        }
        
        .cta-box {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 4rem 2rem;
            border-radius: 15px;
            text-align: center;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .bg-light-custom {
            background-color: #f8f9fa !important;
        }
        
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin: 0 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .section-title {
                font-size: 2rem;
            }
            
            .hero-services {
                padding: 3rem 0;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .service-card {
                padding: 2rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-buttons .btn {
                width: 100%;
                max-width: 250px;
            }
            
            .stats-badge {
                margin: 0.25rem;
            }
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: #0a58ca;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="index.php">
                <i class="fas fa-map-marked-alt me-2"></i>
                <?php echo htmlspecialchars($platform_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
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
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/dashboard.php'; ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary" href="register.php">Register</a>
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
                    <h1 class="display-4 fw-bold mb-4"><?php echo __('services.hero.title', [], 'services'); ?></h1>
                    <p class="lead mb-4"><?php echo __('services.hero.subtitle', [], 'services'); ?></p>
                    <div class="d-flex flex-wrap justify-content-center">
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
            <h2 class="section-title text-center"><?php echo __('services.for_clients.title', [], 'services'); ?></h2>
            <p class="section-subtitle text-center mx-auto"><?php echo __('services.for_clients.subtitle', [], 'services'); ?></p>
            
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
            <h2 class="section-title text-center"><?php echo __('services.for_providers.title', [], 'services'); ?></h2>
            <p class="section-subtitle text-center mx-auto"><?php echo __('services.for_providers.subtitle', [], 'services'); ?></p>
            
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
            <h2 class="section-title text-center"><?php echo __('services.categories.title', [], 'services'); ?></h2>
            <p class="section-subtitle text-center mx-auto"><?php echo sprintf(__('services.categories.subtitle', [], 'services'), number_format($total_providers), number_format(count($categories))); ?></p>
            
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
                    <h2 class="section-title text-center text-white"><?php echo __('services.how_it_works.title', [], 'services'); ?></h2>
                    <p class="section-subtitle text-center text-white opacity-75"><?php echo __('services.how_it_works.subtitle', [], 'services'); ?></p>
                    
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
    <footer class="footer bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <h5 class="mb-3"><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($platform_description); ?></p>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="about.php" class="text-decoration-none text-muted">About Us</a></li>
                        <li class="mb-2"><a href="services.php" class="text-decoration-none text-muted">Services</a></li>
                        <li class="mb-2"><a href="providers.php" class="text-decoration-none text-muted">Find Providers</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-decoration-none text-muted">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="mb-3">For Providers</h5>
                    <ul class="list-unstyled">
                        <?php if ($provider_registration_enabled): ?>
                            <li class="mb-2"><a href="register.php?type=provider" class="text-decoration-none text-muted">Register</a></li>
                        <?php endif; ?>
                        <li class="mb-2"><a href="login.php" class="text-decoration-none text-muted">Login</a></li>
                        <li class="mb-2"><a href="about.php" class="text-decoration-none text-muted">How It Works</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="mb-3">Contact Us</h5>
                    <p class="text-muted mb-2"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($contact_email); ?></p>
                    <p class="text-muted mb-3"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($contact_phone); ?></p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom border-top pt-4 mt-4 text-center text-muted">
                <p><?php echo htmlspecialchars($copyright_text); ?></p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>