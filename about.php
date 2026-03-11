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
        
        .hero-about {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 5rem 0;
            text-align: center;
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
        
        .content-box {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .mission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .mission-card {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .mission-card:hover {
            transform: translateY(-10px);
        }
        
        .mission-icon {
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
        
        .stats-section {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 5rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: #e0e7ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .contact-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            border: none;
        }
        
        .contact-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.8rem;
            color: white;
        }
        
        .cta-box {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 4rem 2rem;
            border-radius: 15px;
            text-align: center;
        }
        
        .btn-white {
            background: white;
            color: var(--primary);
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: var(--primary);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
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
        
        /* Custom list styling */
        .custom-list {
            list-style: none;
            padding-left: 0;
        }
        
        .custom-list li {
            padding: 0.5rem 0;
            color: var(--secondary);
            line-height: 1.6;
            position: relative;
            padding-left: 2rem;
        }
        
        .custom-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }
        
        .bg-light-custom {
            background-color: #f8f9fa !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .section-title {
                font-size: 2rem;
            }
            
            .hero-about {
                padding: 3rem 0;
            }
            
            .content-box {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
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
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="providers.php">Find Providers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About</a>
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
    <section class="hero-about">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">About <?php echo htmlspecialchars($platform_name); ?></h1>
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
                    <h2 class="section-title text-center">Who We Are</h2>
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
            <h2 class="section-title text-center">Our Mission & Vision</h2>
            <p class="section-subtitle text-center mx-auto">We're on a mission to transform how Rwandans connect with local service providers</p>
            
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

    <!-- The Problem We Solve -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h2 class="section-title text-center">The Problem We Solve</h2>
                    <p class="section-subtitle text-center mx-auto">Understanding the challenges in Rwanda's service industry</p>
                    
                    <div class="content-box">
                        <h3 class="h4 mb-4"><i class="fas fa-exclamation-circle text-primary me-2"></i> The Challenge</h3>
                        <p class="fs-5">
                            In Rwanda, especially in cities like Rubavu, Musanze, and parts of Kigali, people face significant challenges finding nearby skilled service providers. Most residents rely on word of mouth and personal recommendations, which is:
                        </p>
                        <ul class="custom-list fs-5">
                            <li><strong>Time-consuming:</strong> Takes days or weeks to find someone reliable</li>
                            <li><strong>Unreliable:</strong> No way to verify skills or read reviews beforehand</li>
                            <li><strong>Limited:</strong> Only aware of providers within immediate social circle</li>
                            <li><strong>Inefficient:</strong> Skilled workers struggle to reach potential clients</li>
                        </ul>
                        <p class="fs-5 mb-0">
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
            <h2 class="section-title text-center">Our Solution</h2>
            <p class="section-subtitle text-center mx-auto">A comprehensive digital platform that transforms service discovery</p>
            
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
            <h2 class="section-title text-center">Who We Serve</h2>
            <p class="section-subtitle text-center mx-auto">Our platform is designed for everyone in Rwanda's service ecosystem</p>
            
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
            <h2 class="section-title text-center">Why Choose <?php echo htmlspecialchars($platform_name); ?>?</h2>
            <p class="section-subtitle text-center mx-auto">We're more than just a directory – we're your trusted partner</p>
            
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
                    <h2 class="section-title text-center">Our Vision for the Future</h2>
                    <div class="content-box">
                        <p class="fs-5">
                            At <?php echo htmlspecialchars($platform_name); ?>, we envision a future where:
                        </p>
                        <ul class="custom-list fs-5">
                            <li><strong>Unemployment is reduced</strong> as skilled workers easily connect with clients across Rwanda</li>
                            <li><strong>Quality service delivery</strong> becomes the norm through our rating and review system</li>
                            <li><strong><?php echo htmlspecialchars($platform_name); ?> becomes the #1 platform</strong> for service connections in Rwanda</li>
                            <li><strong>Expansion across all districts</strong> ensures every Rwandan has access to skilled professionals</li>
                            <li><strong>International growth</strong> brings our model to other East African countries</li>
                            <li><strong>Technology integration</strong> with AI-powered matching and instant booking capabilities</li>
                        </ul>
                        <p class="fs-5 mb-0">
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
            <h2 class="section-title text-center">Get In Touch</h2>
            <p class="section-subtitle text-center mx-auto">Have questions? We'd love to hear from you</p>
            
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
    <section class="py-5">
        <div class="container">
            <div class="cta-box">
                <h2 class="display-5 fw-bold mb-3">Ready to Get Started?</h2>
                <p class="fs-5 mb-4">Join thousands of Rwandans already using <?php echo htmlspecialchars($platform_name); ?> to connect with skilled professionals</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="providers.php" class="btn-white">
                        <i class="fas fa-search me-2"></i> Find Providers
                    </a>
                    <?php if ($provider_registration_enabled): ?>
                        <a href="register.php?type=provider" class="btn-white">
                            <i class="fas fa-user-plus me-2"></i> Register as Provider
                        </a>
                    <?php endif; ?>
                </div>
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