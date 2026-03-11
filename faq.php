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
        .hero-faq {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(30, 64, 175, 0.9)), 
                        url('assets/images/faq-bg.jpg') center/cover no-repeat;
            color: white;
            padding: 100px 0 80px;
            text-align: center;
        }
        
        .hero-faq h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }
        
        .hero-faq p {
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
        
        /* FAQ Accordion */
        .faq-section {
            padding: 4rem 0;
        }
        
        .accordion-faq .accordion-item {
            border: 2px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .accordion-faq .accordion-item:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }
        
        .accordion-faq .accordion-button {
            padding: 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            background: white;
            border: none;
            box-shadow: none;
            transition: all 0.3s ease;
        }
        
        .accordion-faq .accordion-button:not(.collapsed) {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            border-bottom: 2px solid var(--primary);
        }
        
        .accordion-faq .accordion-button:focus {
            box-shadow: none;
            border-color: var(--primary);
        }
        
        .accordion-faq .accordion-button:after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%232563eb'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            transform: rotate(-90deg);
            transition: transform 0.2s ease-in-out;
        }
        
        .accordion-faq .accordion-button:not(.collapsed):after {
            transform: rotate(0deg);
        }
        
        .accordion-faq .accordion-body {
            padding: 1.5rem;
            color: var(--secondary);
            line-height: 1.7;
            font-size: 1rem;
        }
        
        .faq-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.1rem;
        }
        
        /* Trust Badges */
        .trust-badges {
            background: var(--light);
            border-radius: 16px;
            padding: 2rem;
            margin: 3rem 0;
        }
        
        .trust-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .trust-icon {
            width: 50px;
            height: 50px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--success);
            font-size: 1.2rem;
        }
        
        /* CTA Section */
        .cta-help {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 5rem 0;
            border-radius: 20px;
            color: white;
            text-align: center;
        }
        
        .cta-help h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .contact-methods {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .contact-method {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            min-width: 200px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
        }
        
        .contact-method:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
            color: white;
            text-decoration: none;
        }
        
        .contact-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
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
            .hero-faq h1 {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .hero-faq {
                padding: 60px 0 40px;
            }
            
            .contact-methods {
                flex-direction: column;
                align-items: center;
            }
            
            .contact-method {
                width: 100%;
                max-width: 300px;
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
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/dashboard.php'; ?>">
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
    <section class="hero-faq">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h1 class="display-4 fw-bold mb-4">Frequently Asked Questions</h1>
                    <p class="lead mb-0">
                        Answers to common questions about using <?php echo htmlspecialchars($platform_name); ?>. 
                        If you don't find your answer here, our support team is ready to help.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Section -->
    <div class="container">
        <div class="trust-badges">
            <div class="row">
                <div class="col-md-4">
                    <div class="trust-item">
                        <div class="trust-icon">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Safe & Secure</h5>
                            <p class="text-muted mb-0">Verified providers only</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="trust-item">
                        <div class="trust-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">24/7 Support</h5>
                            <p class="text-muted mb-0">We're always here to help</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="trust-item">
                        <div class="trust-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">No Hidden Fees</h5>
                            <p class="text-muted mb-0">Transparent and honest</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client FAQs -->
    <section class="faq-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">For Clients</h2>
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
    <section class="faq-section bg-light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">For Service Providers</h2>
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
    <section class="faq-section">
        <div class="container">
            <div class="section-header">
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
    <section class="faq-section bg-light">
        <div class="container">
            <div class="section-header">
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
    <section class="faq-section">
        <div class="container">
            <div class="section-header">
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
    <section class="faq-section bg-light">
        <div class="container">
            <div class="section-header">
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
    <section class="faq-section">
        <div class="container">
            <div class="section-header">
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
    <section class="container my-5">
        <div class="cta-help">
            <h2>Still need help?</h2>
            <p class="lead mb-4">
                Our support team is ready to assist you anytime.
            </p>

            <div class="contact-methods">
                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-method">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <strong>Email</strong><br>
                    <?php echo htmlspecialchars($contact_email); ?>
                </a>

                <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $contact_whatsapp); ?>" class="contact-method">
                    <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                    <strong>WhatsApp</strong><br>
                    <?php echo htmlspecialchars($contact_whatsapp); ?>
                </a>

                <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="contact-method">
                    <div class="contact-icon"><i class="fas fa-phone"></i></div>
                    <strong>Call Us</strong><br>
                    <?php echo htmlspecialchars($contact_phone); ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p class="text-muted mb-0">
                <?php echo htmlspecialchars($copyright_text); ?>
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
