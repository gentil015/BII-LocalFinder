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

// Set today's date for "Last Updated"
$last_updated = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - <?php echo htmlspecialchars($platform_name); ?></title>
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
            line-height: 1.7;
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
        .hero-terms {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(30, 64, 175, 0.9));
            color: white;
            padding: 80px 0 60px;
            text-align: center;
        }
        
        .hero-terms h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .hero-terms .last-updated {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            display: inline-block;
            font-size: 0.95rem;
            margin-top: 1rem;
        }
        
        /* Content Section */
        .terms-content {
            padding: 4rem 0;
        }
        
        .terms-section {
            margin-bottom: 3rem;
        }
        
        .terms-section:last-child {
            margin-bottom: 0;
        }
        
        .terms-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
            padding-bottom: 0.75rem;
            border-bottom: 3px solid var(--primary);
        }
        
        .terms-section h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            color: var(--dark);
        }
        
        .terms-section p {
            margin-bottom: 1.2rem;
            color: var(--secondary);
        }
        
        .terms-section ul, .terms-section ol {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        
        .terms-section li {
            margin-bottom: 0.8rem;
            color: var(--secondary);
        }
        
        .terms-section strong {
            color: var(--dark);
        }
        
        /* Definition Box */
        .definition-box {
            background: var(--light);
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .definition-box h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .definition-item {
            margin-bottom: 0.8rem;
        }
        
        .definition-term {
            font-weight: 600;
            color: var(--dark);
            display: inline-block;
            min-width: 160px;
        }
        
        /* Warning Box */
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid var(--warning);
            border-left: 6px solid var(--warning);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .warning-box-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .warning-box-header i {
            color: var(--warning);
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        /* Important Note */
        .important-note {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid var(--danger);
            border-left: 6px solid var(--danger);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .important-note-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .important-note-header i {
            color: var(--danger);
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        /* Acceptance Box */
        .acceptance-box {
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin: 3rem 0;
        }
        
        /* Back to Top */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .back-to-top:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            color: white;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        
        /* Table of Contents */
        .toc-container {
            background: var(--light);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 2px solid var(--border);
        }
        
        .toc-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .toc-list {
            columns: 2;
            column-gap: 2rem;
        }
        
        .toc-item {
            margin-bottom: 0.75rem;
            break-inside: avoid;
        }
        
        .toc-item a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .toc-item a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .toc-item a:before {
            content: "•";
            margin-right: 0.75rem;
            color: var(--primary);
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
            .hero-terms h1 {
                font-size: 2.5rem;
            }
            
            .hero-terms {
                padding: 60px 0 40px;
            }
            
            .toc-list {
                columns: 1;
            }
            
            .back-to-top {
                bottom: 20px;
                right: 20px;
                width: 45px;
                height: 45px;
            }
        }
        
        @media (max-width: 576px) {
            .definition-item {
                display: block;
            }
            
            .definition-term {
                display: block;
                margin-bottom: 0.25rem;
                min-width: auto;
            }
        }
        
        /* Print Styles */
        @media print {
            .navbar, .back-to-top, .footer {
                display: none;
            }
            
            .hero-terms {
                padding: 2rem 0;
            }
            
            .terms-content {
                padding: 2rem 0;
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
                        <a class="nav-link" href="faq.php">FAQ</a>
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
    <section class="hero-terms">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h1 class="display-4 fw-bold mb-4">Terms & Conditions</h1>
                    <p class="lead mb-0">
                        Please read these Terms & Conditions carefully before using <?php echo htmlspecialchars($platform_name); ?>.
                    </p>
                    <div class="last-updated">
                        <i class="fas fa-calendar-alt me-2"></i>Last Updated: <?php echo $last_updated; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Table of Contents -->
    <section class="terms-content">
        <div class="container">
            <div class="toc-container">
                <h2 class="toc-title"><i class="fas fa-list me-2"></i>Table of Contents</h2>
                <div class="toc-list">
                    <div class="toc-item"><a href="#section1">1. Definitions</a></div>
                    <div class="toc-item"><a href="#section2">2. Eligibility</a></div>
                    <div class="toc-item"><a href="#section3">3. Account Registration & Security</a></div>
                    <div class="toc-item"><a href="#section4">4. Service Providers</a></div>
                    <div class="toc-item"><a href="#section5">5. Clients Responsibilities</a></div>
                    <div class="toc-item"><a href="#section6">6. Payments</a></div>
                    <div class="toc-item"><a href="#section7">7. Bookings & Communication</a></div>
                    <div class="toc-item"><a href="#section7a">7.1 Price Negotiation System</a></div>
                    <div class="toc-item"><a href="#section7b">7.2 Booking Confirmation & Auto-Confirm</a></div>
                    <div class="toc-item"><a href="#section8">8. Ratings, Reviews & Content</a></div>
                    <div class="toc-item"><a href="#section9">9. Prohibited Activities</a></div>
                    <div class="toc-item"><a href="#section10">10. Dispute Resolution & Complaints</a></div>
                    <div class="toc-item"><a href="#section11">11. Suspension & Termination</a></div>
                    <div class="toc-item"><a href="#section12">12. Disclaimer & Limitation of Liability</a></div>
                    <div class="toc-item"><a href="#section13">13. Privacy</a></div>
                    <div class="toc-item"><a href="#section14">14. Intellectual Property</a></div>
                    <div class="toc-item"><a href="#section15">15. Changes to Terms</a></div>
                    <div class="toc-item"><a href="#section16">16. Governing Law</a></div>
                </div>
            </div>

            <!-- Acceptance Box -->
            <div class="acceptance-box">
                <div class="mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h3 class="fw-bold mb-2">Important Notice</h3>
                </div>
                <p class="mb-4">
                    By accessing, registering, or using the <?php echo htmlspecialchars($platform_name); ?> platform, 
                    you agree to be bound by these Terms & Conditions. If you do not agree with any part of these terms, 
                    please do not use our platform.
                </p>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> These Terms & Conditions constitute a legally binding agreement between you and BII Technologies.
                </div>
            </div>

            <!-- Section 1: Definitions -->
            <div class="terms-section" id="section1">
                <h2>1. Definitions</h2>
                <p>
                    To help you understand these Terms & Conditions, here are some key definitions:
                </p>
                
                <div class="definition-box">
                    <h4><i class="fas fa-book me-2"></i>Key Terms Defined</h4>
                    <div class="definition-item">
                        <span class="definition-term">"Platform"</span>
                        refers to the <?php echo htmlspecialchars($platform_name); ?> website, mobile application, and all related services.
                    </div>
                    <div class="definition-item">
                        <span class="definition-term">"Client"</span>
                        means a user seeking a service through the platform.
                    </div>
                    <div class="definition-item">
                        <span class="definition-term">"Service Provider"</span>
                        means an individual or business offering services through the platform.
                    </div>
                    <div class="definition-item">
                        <span class="definition-term">"We", "Us", "Our"</span>
                        refer to BII Technologies, the operator of <?php echo htmlspecialchars($platform_name); ?>.
                    </div>
                    <div class="definition-item">
                        <span class="definition-term">"User"</span>
                        refers to any person accessing or using the platform, including both Clients and Service Providers.
                    </div>
                </div>
            </div>

            <!-- Section 2: Eligibility -->
            <div class="terms-section" id="section2">
                <h2>2. Eligibility</h2>
                <h3>Age Requirement</h3>
                <p>
                    Users must be at least <strong>16 years old</strong> to use the platform. By using our services, 
                    you represent and warrant that you meet this age requirement.
                </p>
                
                <h3>Accuracy of Information</h3>
                <p>
                    By registering on <?php echo htmlspecialchars($platform_name); ?>, you confirm that all information 
                    provided during registration and in your profile is accurate, complete, and truthful.
                </p>
                
                <h3>Service Provider Requirements</h3>
                <p>
                    Service Providers must:
                </p>
                <ul>
                    <li>Have the legal right and necessary skills to offer their services</li>
                    <li>Comply with all applicable Rwandan laws and regulations</li>
                    <li>Maintain any required licenses or certifications for their profession</li>
                </ul>
            </div>

            <!-- Section 3: Account Registration & Security -->
            <div class="terms-section" id="section3">
                <h2>3. Account Registration & Security</h2>
                
                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-shield-alt"></i>
                        <h4 class="mb-0">Account Security is Your Responsibility</h4>
                    </div>
                    <p class="mb-0">
                        You are responsible for maintaining the confidentiality of your login credentials and for all activities 
                        that occur under your account.
                    </p>
                </div>
                
                <h3>Single Account Policy</h3>
                <p>
                    Each user may create <strong>only one account</strong> on the platform. Creating multiple accounts 
                    may result in suspension of all associated accounts.
                </p>
                
                <h3>Account Security</h3>
                <p>
                    You agree to:
                </p>
                <ul>
                    <li>Keep your password secure and confidential</li>
                    <li>Notify us immediately of any unauthorized use of your account</li>
                    <li>Ensure that you log out at the end of each session</li>
                </ul>
                
                <div class="important-note">
                    <div class="important-note-header">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4 class="mb-0">Important Notice</h4>
                    </div>
                    <p class="mb-0">
                        <?php echo htmlspecialchars($platform_name); ?> is <strong>not responsible</strong> for unauthorized 
                        account access resulting from user negligence, such as sharing passwords or failing to secure devices.
                    </p>
                </div>
            </div>

            <!-- Section 4: Service Providers -->
            <div class="terms-section" id="section4">
                <h2>4. Service Providers</h2>
                
                <h3>Independent Contractor Status</h3>
                <p>
                    Service Providers using <?php echo htmlspecialchars($platform_name); ?> are <strong>independent contractors</strong>, 
                    not employees, agents, or partners of BII Technologies or <?php echo htmlspecialchars($platform_name); ?>.
                </p>
                
                <h3>Provider Responsibilities</h3>
                <p>
                    Service Providers are solely responsible for:
                </p>
                <ul>
                    <li>The accuracy and truthfulness of their profile information</li>
                    <li>The quality and timeliness of services provided to Clients</li>
                    <li>Setting and communicating their pricing to Clients</li>
                    <li>Compliance with all applicable Rwandan laws and regulations</li>
                    <li>Maintaining any necessary insurance, licenses, or certifications</li>
                    <li>Tax obligations related to income earned through the platform</li>
                </ul>
                
                <h3>Profile Verification</h3>
                <p>
                    Provider profiles become visible to Clients only after passing our verification process. 
                    Verification includes identity confirmation and basic eligibility checks.
                </p>
                
                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-info-circle"></i>
                        <h4 class="mb-0">Verification Disclaimer</h4>
                    </div>
                    <p class="mb-0">
                        <strong>Important:</strong> Profile verification does <strong>not</strong> guarantee service quality, 
                        reliability, or fitness for a particular purpose. It only confirms identity and basic eligibility 
                        to use the platform.
                    </p>
                </div>
            </div>

            <!-- Section 5: Clients Responsibilities -->
            <div class="terms-section" id="section5">
                <h2>5. Clients Responsibilities</h2>
                
                <p>
                    When using <?php echo htmlspecialchars($platform_name); ?> to find and hire service providers, 
                    Clients agree to:
                </p>
                
                <ul>
                    <li>
                        <strong>Provide Clear Service Requests:</strong> Accurately describe the service needed, 
                        including scope, timeline, and any special requirements.
                    </li>
                    <li>
                        <strong>Respect Service Providers:</strong> Treat all service providers with professionalism 
                        and respect. This includes being punctual for appointments and providing a safe working environment.
                    </li>
                    <li>
                        <strong>Avoid Fake or Malicious Bookings:</strong> Do not create bookings without genuine intent 
                        to receive services.
                    </li>
                    <li>
                        <strong>Pay Agreed Fees:</strong> Pay the service provider directly for services rendered, 
                        according to the agreed terms.
                    </li>
                    <li>
                        <strong>Communicate Clearly:</strong> Discuss all details of the service, including pricing, 
                        timeline, and expectations, before work begins.
                    </li>
                </ul>
            </div>

            <!-- Section 6: Payments -->
            <div class="terms-section" id="section6">
                <h2>6. Payments</h2>
                
                <div class="important-note">
                    <div class="important-note-header">
                        <i class="fas fa-money-bill-wave"></i>
                        <h4 class="mb-0">Payment Processing</h4>
                    </div>
                    <p class="mb-0">
                        <strong>All payments are handled outside the platform.</strong> 
                        <?php echo htmlspecialchars($platform_name); ?> does not process, hold, or control payments 
                        between Clients and Service Providers.
                    </p>
                </div>
                
                <h3>Payment Methods</h3>
                <p>
                    Clients and Service Providers may agree on any payment method, including but not limited to:
                </p>
                <ul>
                    <li>Cash payments</li>
                    <li>Mobile money (MTN Mobile Money, Airtel Money, etc.)</li>
                    <li>Bank transfers</li>
                    <li>Any other mutually agreed payment method</li>
                </ul>
                
                <h3>Pricing</h3>
                <p>
                    Prices for services are set <strong>solely by Service Providers</strong>. 
                    <?php echo htmlspecialchars($platform_name); ?> does not determine, recommend, or guarantee pricing.
                </p>
                
                <div class="warning-box" id="future-fees">
                    <div class="warning-box-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4 class="mb-0">⚠️ Future Fees</h4>
                    </div>
                    <p class="mb-0">
                        <strong><?php echo htmlspecialchars($platform_name); ?> reserves the right to introduce 
                        commissions, subscription fees, or paid features in the future.</strong> 
                        Any such changes will be communicated to users with prior notice, 
                        typically 30 days in advance.
                    </p>
                </div>
            </div>

            <!-- Section 7: Bookings & Communication -->
            <div class="terms-section" id="section7">
                <h2>7. Bookings & Communication</h2>
                
                <h3>Platform Role</h3>
                <p>
                    <?php echo htmlspecialchars($platform_name); ?> acts <strong>solely as a connection tool</strong> 
                    between Clients and Service Providers. We facilitate introductions but do not participate in 
                    the actual service agreements.
                </p>
                
                <h3>Service Agreements</h3>
                <p>
                    All service agreements are made <strong>strictly between Client and Service Provider</strong>. 
                    These agreements should cover:
                </p>
                <ul>
                    <li>Scope of work</li>
                    <li>Pricing and payment terms</li>
                    <li>Timeline and deadlines</li>
                    <li>Any warranties or guarantees</li>
                    <li>Cancellation and refund policies</li>
                </ul>
                
                <h3>Platform Limitation of Responsibility</h3>
                <p>
                    <?php echo htmlspecialchars($platform_name); ?> is <strong>not responsible</strong> for:
                </p>
                <ul>
                    <li>Service outcomes, quality, or completeness</li>
                    <li>Delays in service delivery</li>
                    <li>Disputes between Clients and Service Providers</li>
                    <li>Cancellations or no-shows</li>
                    <li>Property damage, loss, or personal injury</li>
                    <li>Financial losses resulting from service agreements</li>
                </ul>
            </div>

            <!-- Section 7.1: Price Negotiation System -->
            <div class="terms-section" id="section7a">
                <h2>7.1 Price Negotiation System</h2>
                
                <div class="definition-box">
                    <h4><i class="fas fa-handshake me-2"></i>Overview</h4>
                    <p>
                        <?php echo htmlspecialchars($platform_name); ?> provides an optional <strong>Price Negotiation System</strong> 
                        that allows Clients and Service Providers to agree on pricing through a structured offer-and-counter-offer process. 
                        Not all services support negotiation—only services marked as "negotiable" by the Service Provider.
                    </p>
                </div>
                
                <h3>How Negotiation Works</h3>
                <p>
                    When a service is marked as negotiable, the following process applies:
                </p>
                <ol>
                    <li><strong>Client Creates Offer:</strong> Client proposes a price within the provider's specified range (minimum to maximum)</li>
                    <li><strong>Provider Responds:</strong> Provider can accept, reject, or send a counter-offer with a different price</li>
                    <li><strong>Client Responds:</strong> Client can accept counter-offer or send another offer</li>
                    <li><strong>Price Locked:</strong> Once either party accepts an offer/counter-offer, the price is finalized and locked</li>
                </ol>
                
                <h3>Negotiation Limits & Timeline</h3>
                <p>
                    The negotiation system includes the following restrictions to prevent endless discussions:
                </p>
                <ul>
                    <li><strong>Maximum 3 Rounds:</strong> No more than 3 rounds of offers/counter-offers are permitted per booking</li>
                    <li><strong>30-Minute Expiry:</strong> Each offer or counter-offer expires automatically after 30 minutes of no response</li>
                    <li><strong>Automatic Expiration:</strong> If no response within 30 minutes, the offer expires and cannot be accepted</li>
                    <li><strong>Price Range Enforcement:</strong> Clients cannot offer prices outside the provider's specified minimum and maximum range</li>
                </ul>
                
                <h3>Price Locking & Finalization</h3>
                <p>
                    Once a price is agreed upon (either through direct acceptance or counter-offer acceptance):
                </p>
                <ul>
                    <li>The agreed price is <strong>locked and cannot be changed</strong></li>
                    <li>The booking status automatically updates to <strong>"confirmed"</strong></li>
                    <li>Both parties receive notification of the finalized price</li>
                    <li>The price is recorded in our system with complete negotiation history</li>
                    <li>Service can proceed based on the locked price</li>
                </ul>
                
                <h3>Non-Negotiable Services</h3>
                <p>
                    For services marked as <strong>non-negotiable</strong>, the provider's set price applies and 
                    no negotiation system is available. Clients must accept the service at the stated price.
                </p>
                
                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-clock"></i>
                        <h4 class="mb-0">Time Expiry Warning</h4>
                    </div>
                    <p class="mb-0">
                        <strong>Important:</strong> All offers and counter-offers expire after 30 minutes. 
                        If you do not respond within this time, the offer becomes invalid and you will need 
                        to start a new negotiation round (up to the maximum of 3 rounds).
                    </p>
                </div>
            </div>

            <!-- Section 7.2: Booking Confirmation & Auto-Confirm -->
            <div class="terms-section" id="section7b">
                <h2>7.2 Booking Confirmation & Auto-Confirm</h2>
                
                <h3>Automatic Booking Confirmation</h3>
                <p>
                    To streamline the booking process, <?php echo htmlspecialchars($platform_name); ?> implements 
                    an <strong>automatic booking confirmation system</strong> where:
                </p>
                <ul>
                    <li>When a Client sends an offer and a Provider accepts it, the booking <strong>automatically confirms</strong></li>
                    <li>When a Provider sends a counter-offer and the Client accepts it, the booking <strong>automatically confirms</strong></li>
                    <li>No additional forms or manual confirmation steps are required</li>
                    <li>The booking status immediately changes to "confirmed" with the finalized price</li>
                </ul>
                
                <h3>Booking Status Flow</h3>
                <p>
                    Bookings progress through the following statuses:
                </p>
                <ul>
                    <li><strong>Pending:</strong> Initial status when booking is created but price not agreed</li>
                    <li><strong>In Negotiation:</strong> Offers/counter-offers are being exchanged</li>
                    <li><strong>Confirmed:</strong> Price agreed and locked; service is ready to proceed</li>
                    <li><strong>In Progress:</strong> Service is currently being delivered</li>
                    <li><strong>Completed:</strong> Service has been completed</li>
                    <li><strong>Cancelled:</strong> Booking was cancelled by Client or Provider</li>
                </ul>
                
                <h3>Client Responsibilities in Booking</h3>
                <p>
                    When creating a booking, Clients must provide:
                </p>
                <ul>
                    <li>Clear description of the service needed</li>
                    <li>Preferred date and time for service delivery</li>
                    <li>Location/address where service will be provided</li>
                    <li>Any special requirements or additional details</li>
                    <li>A proposed price (if service is negotiable)</li>
                </ul>
                
                <div class="important-note">
                    <div class="important-note-header">
                        <i class="fas fa-check-circle"></i>
                        <h4 class="mb-0">No Manual Action Required</h4>
                    </div>
                    <p class="mb-0">
                        Once both parties agree on price through the negotiation system, 
                        the booking automatically confirms. You do not need to click any additional buttons 
                        or fill any additional forms—the system handles this automatically.
                    </p>
                </div>
            </div>

            <!-- Section 8: Ratings, Reviews & Content -->
            <div class="terms-section" id="section8">
                <h2>8. Ratings, Reviews & Content</h2>
                
                <h3>Authentic Feedback</h3>
                <p>
                    Users may leave ratings and reviews based on <strong>genuine experiences</strong> with Service Providers 
                    or Clients. This feedback system helps maintain quality standards across the platform.
                </p>
                
                <h3>Review Guidelines</h3>
                <p>
                    All reviews and ratings must be:
                </p>
                <ul>
                    <li><strong>Honest:</strong> Based on actual experiences</li>
                    <li><strong>Respectful:</strong> Professional and constructive</li>
                    <li><strong>Accurate:</strong> Factually correct</li>
                    <li><strong>Non-defamatory:</strong> Not containing false statements that harm reputation</li>
                    <li><strong>Relevant:</strong> Related to the service experience</li>
                </ul>
                
                <h3>Content Moderation</h3>
                <p>
                    <?php echo htmlspecialchars($platform_name); ?> reserves the right to:
                </p>
                <ul>
                    <li>Remove or edit content that violates these Terms & Conditions</li>
                    <li>Delete fake, misleading, or inappropriate reviews</li>
                    <li>Suspend users who consistently violate content guidelines</li>
                </ul>
            </div>

            <!-- Section 9: Prohibited Activities -->
            <div class="terms-section" id="section9">
                <h2>9. Prohibited Activities</h2>
                
                <p>
                    Users must <strong>not</strong> engage in any of the following activities:
                </p>
                
                <ul>
                    <li>
                        <strong>Create Fake Accounts:</strong> Registering multiple accounts or impersonating others
                    </li>
                    <li>
                        <strong>Post False Information:</strong> Providing misleading details in profiles, services, or reviews
                    </li>
                    <li>
                        <strong>Offer Illegal Services:</strong> Listing or requesting services that violate Rwandan law
                    </li>
                    <li>
                        <strong>Harassment:</strong> Threatening, abusing, or harassing other users
                    </li>
                    <li>
                        <strong>Rating Manipulation:</strong> Creating fake reviews or manipulating ratings
                    </li>
                    <li>
                        <strong>Fraud & Scams:</strong> Using the platform for fraudulent activities or scams
                    </li>
                    <li>
                        <strong>Commercial Spamming:</strong> Sending unsolicited commercial messages
                    </li>
                    <li>
                        <strong>Copyright Violation:</strong> Posting content that infringes on intellectual property rights
                    </li>
                    <li>
                        <strong>Negotiation System Abuse:</strong> Manipulating offers/counter-offers, 
                        deliberately placing impossible offers, or refusing legitimate counter-offers with intent to abuse the system
                    </li>
                    <li>
                        <strong>Price Manipulation:</strong> Artificially inflating or deflating prices in negotiations 
                        with intent to defraud or harm other users
                    </li>
                </ul>
                
                <div class="important-note">
                    <div class="important-note-header">
                        <i class="fas fa-ban"></i>
                        <h4 class="mb-0">Consequences of Violation</h4>
                    </div>
                    <p class="mb-0">
                        Violation of these prohibitions may result in <strong>immediate account suspension or termination</strong>, 
                        without prior notice or refund of any fees paid.
                    </p>
                </div>
            </div>

            <!-- Section 10: Dispute Resolution & Complaints -->
            <div class="terms-section" id="section10">
                <h2>10. Dispute Resolution & Complaints</h2>
                
                <h3>Complaint Types</h3>
                <p>
                    <?php echo htmlspecialchars($platform_name); ?> accepts complaints related to:
                </p>
                <ul>
                    <li>Service quality issues (work not completed, poor workmanship)</li>
                    <li>Unfair pricing or abuse of negotiation system</li>
                    <li>Unprofessional behavior or harassment from other users</li>
                    <li>Undelivered services or missed appointments</li>
                    <li>Fraud or scams</li>
                    <li>Policy violations</li>
                </ul>
                
                <h3>Filing a Complaint</h3>
                <p>
                    To file a complaint:
                </p>
                <ol>
                    <li>Access your dashboard and locate the "Complaints" section</li>
                    <li>Click "File a Complaint"</li>
                    <li>Select the relevant booking and describe the issue in detail</li>
                    <li>Provide any supporting documentation or evidence</li>
                    <li>Submit the complaint</li>
                </ol>
                
                <p>
                    <strong>Timeline:</strong> Complaints must be filed within <strong>14 days</strong> of the service 
                    completion date for us to investigate and take action.
                </p>
                
                <h3>Our Investigation Process</h3>
                <p>
                    When a complaint is filed:
                </p>
                <ol>
                    <li>We acknowledge receipt of the complaint</li>
                    <li>We review the complaint details and supporting evidence</li>
                    <li>We contact the other party for their response</li>
                    <li>We investigate both versions of the incident</li>
                    <li>We make a determination based on available evidence</li>
                    <li>We communicate the outcome to both parties</li>
                </ol>
                
                <h3>Possible Complaint Outcomes</h3>
                <p>
                    Based on our investigation, we may:
                </p>
                <ul>
                    <li><strong>Dismiss the complaint</strong> if insufficient evidence is provided</li>
                    <li><strong>Issue warnings</strong> to the violating party for minor infractions</li>
                    <li><strong>Suspend the account</strong> of the violating party</li>
                    <li><strong>Terminate the account</strong> for serious or repeated violations</li>
                    <li><strong>Recommend legal action</strong> in cases of fraud or criminal behavior</li>
                </ul>
                
                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-gavel"></i>
                        <h4 class="mb-0">Limitations on Dispute Resolution</h4>
                    </div>
                    <p class="mb-0">
                        <strong>Important:</strong> <?php echo htmlspecialchars($platform_name); ?> acts as a 
                        facilitator, not an arbiter. We cannot enforce payment of disputed amounts or compel service completion. 
                        For disputes involving payment or significant financial losses, users may need to pursue legal remedies 
                        through the Rwandan court system.
                    </p>
                </div>
                
                <h3>Direct Resolution Between Parties</h3>
                <p>
                    We encourage Clients and Service Providers to resolve disputes directly before filing a formal complaint. 
                    Many disagreements can be resolved through direct communication and mutual understanding. 
                    Use the messaging system within <?php echo htmlspecialchars($platform_name); ?> to attempt resolution first.
                </p>
            </div>

            <!-- Section 11: Suspension & Termination -->
            <div class="terms-section" id="section11">
                <h2>11. Suspension & Termination</h2>
                
                <h3>Platform Rights</h3>
                <p>
                    <?php echo htmlspecialchars($platform_name); ?> reserves the right to:
                </p>
                <ul>
                    <li>Suspend or terminate user accounts</li>
                    <li>Remove Service Providers from listings</li>
                    <li>Delete user-generated content</li>
                    <li>Restrict access to platform features</li>
                </ul>
                
                <h3>Grounds for Action</h3>
                <p>
                    These actions may be taken with or without prior notice, especially in cases of:
                </p>
                <ul>
                    <li><strong>Policy Violations:</strong> Breach of these Terms & Conditions</li>
                    <li><strong>Fraudulent Activity:</strong> Suspected or confirmed fraud</li>
                    <li><strong>Security Risks:</strong> Activities that threaten platform security</li>
                    <li><strong>Legal Concerns:</strong> Violation of applicable laws</li>
                    <li><strong>User Complaints:</strong> Multiple or serious complaints from other users</li>
                    <li><strong>Platform Integrity:</strong> Activities that harm platform integrity or reputation</li>
                </ul>
                
                <h3>User Rights</h3>
                <p>
                    Users may terminate their accounts at any time by contacting our support team or using account 
                    deletion features in their dashboard.
                </p>
            </div>

            <!-- Section 12: Disclaimer & Limitation of Liability -->
            <div class="terms-section" id="section12">
                <h2>12. Disclaimer & Limitation of Liability</h2>
                
                <h3>"As Is" Service</h3>
                <p>
                    The <?php echo htmlspecialchars($platform_name); ?> platform is provided <strong>"as is"</strong> and 
                    <strong>"as available"</strong>. We make no warranties, express or implied, regarding the platform's 
                    functionality, reliability, or availability.
                </p>
                
                <h3>No Guarantees</h3>
                <p>
                    <?php echo htmlspecialchars($platform_name); ?> does not guarantee:
                </p>
                <ul>
                    <li>Availability of specific Service Providers</li>
                    <li>Quality or suitability of services offered</li>
                    <li>Successful completion of bookings</li>
                    <li>Continuous, uninterrupted access to the platform</li>
                    <li>Accuracy of information provided by users</li>
                </ul>
                
                <h3>Limitation of Liability</h3>
                <p>
                    To the maximum extent permitted by law, <?php echo htmlspecialchars($platform_name); ?> and BII Technologies 
                    shall <strong>not be liable</strong> for:
                </p>
                <ul>
                    <li>Personal injury or property damage</li>
                    <li>Financial losses or damages</li>
                    <li>Disputes between Clients and Service Providers</li>
                    <li>Service failures or unsatisfactory results</li>
                    <li>Unauthorized access to user data</li>
                    <li>Third-party actions or content</li>
                </ul>
                
                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-balance-scale"></i>
                        <h4 class="mb-0">Maximum Liability</h4>
                    </div>
                    <p class="mb-0">
                        In no event shall our total liability to you for all damages, losses, and causes of action 
                        exceed the amount paid by you, if any, for accessing the platform during the six (6) months 
                        preceding your claim.
                    </p>
                </div>
            </div>

            <!-- Section 13: Privacy -->
            <div class="terms-section" id="section13">
                <h2>13. Privacy</h2>
                
                <h3>Privacy Policy</h3>
                <p>
                    Your privacy is important to us. All user data is handled according to our 
                    <strong>Privacy Policy</strong>, which is incorporated into these Terms & Conditions by reference.
                </p>
                
                <h3>Data Collection & Usage</h3>
                <p>
                    By using the platform, you consent to:
                </p>
                <ul>
                    <li>Collection of necessary personal information</li>
                    <li>Processing of your data for platform operation</li>
                    <li>Communication regarding your account and services</li>
                    <li>Use of anonymized data for platform improvement</li>
                </ul>
                
                <p>
                    We implement reasonable security measures to protect your data, but cannot guarantee 
                    absolute security due to the nature of internet transmission.
                </p>
            </div>

            <!-- Section 14: Intellectual Property -->
            <div class="terms-section" id="section14">
                <h2>14. Intellectual Property</h2>
                
                <h3>Platform Ownership</h3>
                <p>
                    All intellectual property rights in the <?php echo htmlspecialchars($platform_name); ?> platform, 
                    including but not limited to:
                </p>
                <ul>
                    <li>Software code and algorithms</li>
                    <li>Website design and layout</li>
                    <li>Brand names, logos, and trademarks</li>
                    <li>Documentation and manuals</li>
                    <li>System architecture and database design</li>
                                        <li>User interface elements and visual assets</li>
                </ul>

                <h3>User Content License</h3>
                <p>
                    By submitting content (profiles, reviews, messages) to <?php echo htmlspecialchars($platform_name); ?>, 
                    you grant BII Technologies a non-exclusive, royalty-free license to use, display, and distribute 
                    such content solely for platform operation and improvement.
                </p>

                <p>
                    Users may not copy, modify, distribute, sell, or exploit any part of the platform 
                    without prior written permission from BII Technologies.
                </p>
            </div>

            <!-- Section 15: Changes to Terms -->
            <div class="terms-section" id="section15">
                <h2>15. Changes to Terms</h2>

                <p>
                    <?php echo htmlspecialchars($platform_name); ?> reserves the right to modify, update, or replace 
                    these Terms & Conditions at any time.
                </p>

                <p>
                    When changes are made:
                </p>
                <ul>
                    <li>The "Last Updated" date will be revised</li>
                    <li>Major changes may be communicated via email or platform notification</li>
                    <li>Continued use of the platform constitutes acceptance of the updated terms</li>
                </ul>

                <div class="warning-box">
                    <div class="warning-box-header">
                        <i class="fas fa-sync-alt"></i>
                        <h4 class="mb-0">Your Responsibility</h4>
                    </div>
                    <p class="mb-0">
                        It is your responsibility to review these Terms & Conditions periodically 
                        to stay informed of any updates.
                    </p>
                </div>
            </div>

            <!-- Section 16: Governing Law -->
            <div class="terms-section" id="section16">
                <h2>16. Governing Law</h2>

                <p>
                    These Terms & Conditions shall be governed by and interpreted in accordance with the 
                    <strong>laws of the Republic of Rwanda</strong>.
                </p>

                <p>
                    Any disputes arising out of or relating to these Terms & Conditions or the use of 
                    <?php echo htmlspecialchars($platform_name); ?> shall be subject to the exclusive jurisdiction 
                    of the competent courts of Rwanda.
                </p>
            </div>

            <!-- Contact Section -->
            <div class="terms-section">
                <h2>Contact Information</h2>

                <p>
                    If you have any questions, concerns, or complaints regarding these Terms & Conditions, 
                    please contact us using the details below:
                </p>

                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-envelope me-2 text-primary"></i>
                        <strong>Email:</strong> <?php echo htmlspecialchars($contact_email); ?>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-phone me-2 text-primary"></i>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($contact_phone); ?>
                    </li>
                </ul>
            </div>

        </div>
    </section>

    <!-- Back to Top -->
    <a href="#" class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </a>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="footer-title"><?php echo htmlspecialchars($platform_name); ?></h4>
                    <p class="text-secondary">
                        <?php echo htmlspecialchars($platform_description); ?>
                    </p>
                </div>

                <div class="col-md-4 mb-4">
                    <h4 class="footer-title">Quick Links</h4>
                    <a href="about.php" class="footer-link">About Us</a>
                    <a href="services.php" class="footer-link">Services</a>
                    <a href="providers.php" class="footer-link">Find Providers</a>
                    <a href="faq.php" class="footer-link">FAQ</a>
                </div>

                <div class="col-md-4 mb-4">
                    <h4 class="footer-title">Legal</h4>
                    <a href="terms.php" class="footer-link">Terms & Conditions</a>
                    <a href="privacy.php" class="footer-link">Privacy Policy</a>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <div class="text-center text-secondary">
                <?php echo htmlspecialchars($copyright_text); ?>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Back to Top Script -->
    <script>
        const backToTop = document.querySelector('.back-to-top');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTop.style.display = 'flex';
            } else {
                backToTop.style.display = 'none';
            }
        });
    </script>
</body>
</html>

               