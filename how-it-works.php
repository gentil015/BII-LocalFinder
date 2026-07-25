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
    <title>How It Works - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--dark);
            background: #ffffff;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Hero Section */
        .hero-how-it-works {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(30, 64, 175, 0.9)), 
                        url('assets/images/how-it-works-bg.jpg') center/cover no-repeat;
            color: white;
            padding: 100px 0 80px;
            text-align: center;
        }
        
        .hero-how-it-works h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }
        
        .hero-how-it-works p {
            font-size: 1.25rem;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Section Styling */
        .section-header {
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--dark);
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 2px;
        }
        
        .section-subtitle {
            font-size: 1.125rem;
            color: var(--secondary);
            max-width: 600px;
        }
        
        /* Steps Process */
        .process-section {
            padding: 4rem 0;
        }
        
        .process-timeline {
            position: relative;
            padding: 2rem 0;
        }
        
        .process-timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 4px;
            background: linear-gradient(to bottom, var(--primary), var(--primary-dark));
            transform: translateX(-50%);
        }
        
        .process-step {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 4rem;
            position: relative;
        }
        
        .step-number {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            z-index: 2;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
            flex-shrink: 0;
        }
        
        .step-content {
            background: white;
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            margin-left: 2rem;
            flex: 1;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .step-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .step-content p {
            color: var(--secondary);
            line-height: 1.7;
            margin-bottom: 0;
        }
        
        .step-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        /* For Mobile */
        @media (max-width: 768px) {
            .process-timeline:before {
                left: 40px;
            }
            
            .process-step {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 3rem;
            }
            
            .step-number {
                margin-bottom: 1rem;
            }
            
            .step-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* Benefits Section */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .benefit-card {
            background: white;
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .benefit-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.15);
        }
        
        .benefit-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(30, 64, 175, 0.1));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: var(--primary);
        }
        
        /* Comparison Section */
        .comparison-section {
            background: var(--light);
            padding: 4rem 0;
        }
        
        .comparison-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .comparison-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem 2rem;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .comparison-row {
            display: flex;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        
        .comparison-row:last-child {
            border-bottom: none;
        }
        
        .comparison-method {
            flex: 1;
            font-weight: 600;
            color: var(--dark);
        }
        
        .comparison-status {
            width: 150px;
            text-align: center;
        }
        
        .status-good {
            color: var(--success);
            font-weight: 600;
        }
        
        .status-bad {
            color: var(--danger);
            font-weight: 600;
        }
        
        /* CTA Section */
        .cta-how-it-works {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 5rem 0;
            border-radius: 20px;
            color: white;
            text-align: center;
        }
        
        .cta-how-it-works h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        /* Footer */
        .footer {
            background: var(--dark);
            padding: 4rem 0 2rem;
            margin-top: 4rem;
        }
        
        .footer-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
        }
        
        .footer-link {
            color: var(--secondary);
            text-decoration: none;
            transition: color 0.3s ease;
            display: block;
            margin-bottom: 0.75rem;
        }
        
        .footer-link:hover {
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-how-it-works h1 {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .hero-how-it-works {
                padding: 60px 0 40px;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-buttons .btn {
                width: 100%;
                max-width: 250px;
            }
            
            .comparison-row {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .comparison-method, .comparison-status {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
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
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faq.php">FAQ</a>
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
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary px-4" href="register.php">Get Started</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-how-it-works">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h1 class="display-4 fw-bold mb-4">How <?php echo htmlspecialchars($platform_name); ?> Works</h1>
                    <p class="lead mb-0">
                        A simple, transparent process to connect you with trusted local service professionals. 
                        Whether you're looking for services or offering them, we make the connection easy and reliable.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- For Clients Section -->
    <section class="process-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">For Clients: Find & Hire Professionals</h2>
                <p class="section-subtitle">Follow these simple steps to find the perfect service provider for your needs</p>
            </div>
            
            <div class="process-timeline">
                <!-- Step 1 -->
                <div class="process-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>Search for Services</h3>
                        <p>
                            Use our advanced search to find service providers by category, location, rating, or specific skills. 
                            Browse through profiles of verified professionals in your area.
                        </p>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="process-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3>Compare & Verify</h3>
                        <p>
                            View detailed profiles with ratings, reviews, experience, and pricing. 
                            Check verification badges to ensure you're choosing a trusted professional.
                        </p>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="process-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>Contact Directly</h3>
                        <p>
                            Contact providers directly through phone or WhatsApp. 
                            Discuss your project requirements, pricing, and schedule. No middleman, no platform fees.
                        </p>
                    </div>
                </div>
                
                <!-- Step 4 -->
                <div class="process-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Book & Pay Directly</h3>
                        <p>
                            Schedule your service at a convenient time. 
                            Pay the provider directly for the work completed. Simple, transparent, and secure.
                        </p>
                    </div>
                </div>
                
                <!-- Step 5 -->
                <div class="process-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Leave a Review</h3>
                        <p>
                            Share your experience to help others in the community. 
                            Your feedback helps maintain quality standards and builds trust.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- For Providers Section -->
    <section class="process-section bg-light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">For Providers: Grow Your Business</h2>
                <p class="section-subtitle">Join our platform and start connecting with clients who need your services</p>
            </div>
            
            <div class="process-timeline">
                <!-- Step 1 -->
                <div class="process-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3>Create Your Profile</h3>
                        <p>
                            Sign up as a service provider and create a comprehensive profile showcasing your skills, 
                            experience, and qualifications. Add photos of your previous work.
                        </p>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="process-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-list-alt"></i>
                        </div>
                        <h3>List Your Services</h3>
                        <p>
                            Add your services with clear descriptions, pricing, and availability. 
                            Specify your service areas and working hours.
                        </p>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="process-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Get Verified</h3>
                        <p>
                            Complete our verification process to earn trust badges. 
                            Verified profiles receive more visibility and client trust.
                        </p>
                    </div>
                </div>
                
                <!-- Step 4 -->
                <div class="process-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Receive Client Requests</h3>
                        <p>
                            Get notified when clients are looking for your services. 
                            Respond quickly to increase your chances of getting hired.
                        </p>
                    </div>
                </div>
                
                <!-- Step 5 -->
                <div class="process-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <div class="step-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Grow Your Reputation</h3>
                        <p>
                            Deliver excellent service, earn positive reviews, and build your reputation. 
                            Higher ratings lead to more visibility and more clients.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="process-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Why Choose <?php echo htmlspecialchars($platform_name); ?></h2>
                <p class="section-subtitle">Experience the benefits of our trusted platform</p>
            </div>
            
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <h3>Verified Professionals</h3>
                    <p>All providers undergo identity and skill verification to ensure quality and reliability.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Real Reviews</h3>
                    <p>Make informed decisions based on authentic feedback from real clients.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Local Focus</h3>
                    <p>Find professionals in your neighborhood for quick service and local expertise.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Secure & Transparent</h3>
                    <p>Clear pricing, direct communication, and secure platform for peace of mind.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Time Saving</h3>
                    <p>Find and hire professionals in minutes instead of days of searching.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Driven</h3>
                    <p>Built by Rwandans for Rwandans, understanding local needs and culture.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Section -->
    <section class="comparison-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title"><?php echo htmlspecialchars($platform_name); ?> vs Traditional Methods</h2>
                <p class="section-subtitle">See why our platform is better than old-fashioned ways of finding service providers</p>
            </div>
            
            <div class="comparison-table">
                <div class="comparison-header">
                    Finding Service Providers: Comparison
                </div>
                
                <div class="comparison-row">
                    <div class="comparison-method">Verification Process</div>
                    <div class="comparison-status status-good">✓ All providers verified</div>
                    <div class="comparison-status status-bad">✗ No verification</div>
                </div>
                
                <div class="comparison-row">
                    <div class="comparison-method">Read Reviews First</div>
                    <div class="comparison-status status-good">✓ Real client reviews</div>
                    <div class="comparison-status status-bad">✗ Word of mouth only</div>
                </div>
                
                <div class="comparison-row">
                    <div class="comparison-method">Compare Multiple Providers</div>
                    <div class="comparison-status status-good">✓ Side-by-side comparison</div>
                    <div class="comparison-status status-bad">✗ One at a time</div>
                </div>
                
                <div class="comparison-row">
                    <div class="comparison-method">Find Nearby Professionals</div>
                    <div class="comparison-status status-good">✓ Location-based search</div>
                    <div class="comparison-status status-bad">✗ Limited to known contacts</div>
                </div>
                
                <div class="comparison-row">
                    <div class="comparison-method">Time to Find Provider</div>
                    <div class="comparison-status status-good">✓ Minutes</div>
                    <div class="comparison-status status-bad">✗ Days or weeks</div>
                </div>
                
                <div class="comparison-row">
                    <div class="comparison-method">Quality Assurance</div>
                    <div class="comparison-status status-good">✓ Rating system ensures quality</div>
                    <div class="comparison-status status-bad">✗ Trial and error</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="container my-5">
        <div class="cta-how-it-works">
            <h2>Ready to Get Started?</h2>
            <p class="lead mb-4">
                Join thousands of satisfied users already benefiting from <?php echo htmlspecialchars($platform_name); ?>
            </p>
            
            <div class="cta-buttons">
                <a href="providers.php" class="btn btn-light btn-lg px-4">
                    <i class="fas fa-search me-2"></i>Find a Provider
                </a>
                
                <?php if ($provider_registration_enabled): ?>
                    <a href="register.php?type=provider" class="btn btn-outline-light btn-lg px-4">
                        <i class="fas fa-user-plus me-2"></i>Register as Provider
                    </a>
                <?php endif; ?>
                
                <a href="faq.php" class="btn btn-outline-light btn-lg px-4">
                    <i class="fas fa-question-circle me-2"></i>Visit FAQ
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title"><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-secondary"><?php echo htmlspecialchars($platform_description); ?></p>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title">Quick Links</h5>
                    <a href="about.php" class="footer-link">About Us</a>
                    <a href="services.php" class="footer-link">Services</a>
                    <a href="providers.php" class="footer-link">Find Providers</a>
                    <a href="how-it-works.php" class="footer-link">How It Works</a>
                    <a href="faq.php" class="footer-link">FAQ</a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="footer-title">For Providers</h5>
                    <?php if ($provider_registration_enabled): ?>
                        <a href="register.php?type=provider" class="footer-link">Register</a>
                    <?php endif; ?>
                    <a href="login.php" class="footer-link">Login</a>
                    <a href="how-it-works.php" class="footer-link">Getting Started</a>
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
            <div class="footer-bottom border-top border-secondary border-opacity-25 pt-4 mt-4 text-center">
                <p class="text-secondary mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animation for step cards on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const stepCards = document.querySelectorAll('.process-step');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            stepCards.forEach(step => {
                step.style.opacity = '0';
                step.style.transform = 'translateY(20px)';
                step.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(step);
            });
        });
    </script>
</body>
</html>