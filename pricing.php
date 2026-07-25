<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize session with security settings
initSession();

// Get platform settings
$platform_name = getPlatformName();
$contact_email = getContactEmail();
$copyright_text = getCopyrightText();
$platform_description = getSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

// Get pricing settings from system
$enable_subscriptions = getSetting('enable_subscriptions', '1');
$basic_subscription_price = getSetting('basic_subscription_price', '5000');
$premium_subscription_price = getSetting('premium_subscription_price', '15000');
$featured_listing_price = getSetting('featured_listing_price', '10000');
$verification_fee = getSetting('verification_fee', '2000');
$commission_rate = getSetting('commission_rate', '10');

// Get categories with pricing
$db = Database::getInstance()->getConnection();
$categories = $db->query("
    SELECT * FROM categories 
    WHERE is_premium = 1 
    ORDER BY monthly_fee DESC, name ASC
")->fetchAll();

// Handle subscription selection
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    if (isset($_POST['select_plan'])) {
        $plan_type = sanitize($_POST['plan_type']);
        $user_id = $_SESSION['user_id'];
        
        if (!isProvider()) {
            $errors[] = "Only service providers can subscribe to plans.";
        } else {
            try {
                // Check if user already has an active subscription
                $stmt = $db->prepare("
                    SELECT * FROM provider_subscriptions 
                    WHERE provider_id = (SELECT id FROM service_providers WHERE user_id = ?) 
                    AND status = 'active' AND end_date > NOW()
                ");
                $stmt->execute([$user_id]);
                $active_subscription = $stmt->fetch();
                
                if ($active_subscription) {
                    $errors[] = "You already have an active subscription. Please wait for it to expire or cancel it first.";
                } else {
                    // Calculate prices based on plan type
                    $amount = 0;
                    $features = [];
                    
                    switch ($plan_type) {
                        case 'basic':
                            $amount = $basic_subscription_price;
                            $features = [
                                'Basic profile listing',
                                'Up to 5 service categories',
                                'Standard search visibility',
                                'Client reviews and ratings',
                                'Basic analytics'
                            ];
                            break;
                            
                        case 'premium':
                            $amount = $premium_subscription_price;
                            $features = [
                                'Premium profile listing',
                                'Unlimited service categories',
                                'Enhanced search visibility',
                                'Priority in search results',
                                'Advanced analytics dashboard',
                                'Featured provider badge',
                                'Direct client messaging',
                                'No commission fees'
                            ];
                            break;
                            
                        case 'featured':
                            $amount = $featured_listing_price;
                            $features = [
                                'Top placement in search results',
                                'Featured on homepage',
                                'Premium badge display',
                                'Increased booking conversion',
                                '30-day featured listing'
                            ];
                            break;
                    }
                    
                    // Store subscription intent in session for payment processing
                    $_SESSION['subscription_intent'] = [
                        'plan_type' => $plan_type,
                        'amount' => $amount,
                        'features' => $features,
                        'user_id' => $user_id
                    ];
                    
                    // Redirect to payment page
                    header('Location: payment.php?type=subscription');
                    exit;
                    
                }
            } catch (Exception $e) {
                $errors[] = "Error processing subscription: " . $e->getMessage();
            }
        }
    }
    
    // Handle category subscription
    if (isset($_POST['subscribe_category'])) {
        $category_id = intval($_POST['category_id']);
        $user_id = $_SESSION['user_id'];
        
        if (!isProvider()) {
            $errors[] = "Only service providers can subscribe to premium categories.";
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM categories WHERE id = ? AND is_premium = 1");
                $stmt->execute([$category_id]);
                $category = $stmt->fetch();
                
                if (!$category) {
                    $errors[] = "Invalid premium category selected.";
                } else {
                    // Check if already subscribed to this category
                    $provider_stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
                    $provider_stmt->execute([$user_id]);
                    $provider = $provider_stmt->fetch();
                    
                    if ($provider) {
                        $check_stmt = $db->prepare("
                            SELECT * FROM provider_category_subscriptions 
                            WHERE provider_id = ? AND category_id = ? AND status = 'active' AND end_date > NOW()
                        ");
                        $check_stmt->execute([$provider['id'], $category_id]);
                        
                        if ($check_stmt->fetch()) {
                            $errors[] = "You are already subscribed to this premium category.";
                        } else {
                            // Store category subscription intent
                            $_SESSION['category_subscription_intent'] = [
                                'category_id' => $category_id,
                                'category_name' => $category['name'],
                                'amount' => $category['monthly_fee'],
                                'provider_id' => $provider['id']
                            ];
                            
                            // Redirect to payment page
                            header('Location: payment.php?type=category');
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error processing category subscription: " . $e->getMessage();
            }
        }
    }
}

// Get current user's active subscriptions if logged in
$user_subscriptions = [];
$user_category_subscriptions = [];

if (isLoggedIn() && isProvider()) {
    try {
        $user_id = $_SESSION['user_id'];
        
        // Get provider subscriptions
        $stmt = $db->prepare("
            SELECT ps.*, sp.profession 
            FROM provider_subscriptions ps 
            JOIN service_providers sp ON ps.provider_id = sp.id 
            WHERE sp.user_id = ? AND ps.status = 'active' AND ps.end_date > NOW()
        ");
        $stmt->execute([$user_id]);
        $user_subscriptions = $stmt->fetchAll();
        
        // Get category subscriptions
        $cat_stmt = $db->prepare("
            SELECT pcs.*, c.name as category_name, c.icon 
            FROM provider_category_subscriptions pcs 
            JOIN categories c ON pcs.category_id = c.id 
            JOIN service_providers sp ON pcs.provider_id = sp.id 
            WHERE sp.user_id = ? AND pcs.status = 'active' AND pcs.end_date > NOW()
        ");
        $cat_stmt->execute([$user_id]);
        $user_category_subscriptions = $cat_stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching user subscriptions: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - <?php echo htmlspecialchars($platform_name); ?></title>
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
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }
        
        .pricing-section {
            padding: 4rem 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .section-title p {
            color: var(--secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .pricing-card {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .pricing-card.popular {
            border: 3px solid var(--primary);
            transform: scale(1.05);
        }
        
        .pricing-card.popular:hover {
            transform: scale(1.05) translateY(-5px);
        }
        
        .popular-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 0 15px 0 15px;
        }
        
        .plan-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }
        
        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .plan-period {
            color: var(--secondary);
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }
        
        .plan-features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
        }
        
        .plan-features li i {
            color: var(--success);
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }
        
        .plan-features li.disabled {
            color: var(--secondary);
            text-decoration: line-through;
        }
        
        .plan-features li.disabled i {
            color: var(--secondary);
        }
        
        .btn-pricing {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            width: 100%;
        }
        
        .commission-section {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            margin: 4rem 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .commission-rate {
            font-size: 4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .commission-description {
            color: var(--secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .categories-section {
            padding: 4rem 0;
            background: #f8fafc;
        }
        
        .category-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            border-left: 4px solid var(--primary);
        }
        
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .category-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .category-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .category-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 1rem;
        }
        
        .category-description {
            color: var(--secondary);
            margin-bottom: 1.5rem;
        }
        
        .user-subscriptions {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            margin: 3rem 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .subscription-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .subscription-item:last-child {
            border-bottom: none;
        }
        
        .subscription-info h5 {
            margin: 0;
            color: var(--dark);
        }
        
        .subscription-info p {
            margin: 0.25rem 0 0 0;
            color: var(--secondary);
        }
        
        .subscription-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        
        .faq-section {
            padding: 4rem 0;
        }
        
        .faq-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .faq-question {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .faq-answer {
            color: var(--secondary);
            margin: 0;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .pricing-card.popular {
                transform: none;
            }
            
            .pricing-card.popular:hover {
                transform: translateY(-5px);
            }
            
            .hero-section {
                padding: 3rem 0;
            }
            
            .pricing-section, .categories-section, .faq-section {
                padding: 2rem 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
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
                        <a class="nav-link active" href="pricing.php">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/home.php'; ?>">Dashboard</a>
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
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Simple, Transparent Pricing</h1>
            <p class="lead mb-4">Choose the perfect plan for your service business. No hidden fees, no surprises.</p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <span class="badge bg-light text-dark p-2"><i class="fas fa-check text-success me-2"></i> No Commission for Premium Plans</span>
                        <span class="badge bg-light text-dark p-2"><i class="fas fa-check text-success me-2"></i> Cancel Anytime</span>
                        <span class="badge bg-light text-dark p-2"><i class="fas fa-check text-success me-2"></i> Secure Payments</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Plans -->
    <section class="pricing-section">
        <div class="container">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="section-title">
                <h2>Provider Subscription Plans</h2>
                <p>Boost your visibility and grow your business with our tailored subscription plans</p>
            </div>

            <?php if (!$enable_subscriptions): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Subscription plans are currently disabled. Please check back later.
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <!-- Basic Plan -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="pricing-card">
                            <div class="plan-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="plan-name">Basic</div>
                            <div class="plan-price">RWF <?php echo number_format($basic_subscription_price); ?></div>
                            <div class="plan-period">per month</div>
                            
                            <ul class="plan-features">
                                <li><i class="fas fa-check"></i> Basic profile listing</li>
                                <li><i class="fas fa-check"></i> Up to 5 service categories</li>
                                <li><i class="fas fa-check"></i> Standard search visibility</li>
                                <li><i class="fas fa-check"></i> Client reviews and ratings</li>
                                <li><i class="fas fa-check"></i> Basic analytics dashboard</li>
                                <li class="disabled"><i class="fas fa-times"></i> Priority in search results</li>
                                <li class="disabled"><i class="fas fa-times"></i> Featured provider badge</li>
                                <li class="disabled"><i class="fas fa-times"></i> No commission fees</li>
                            </ul>
                            
                            <?php if (isLoggedIn() && isProvider()): ?>
                                <form method="POST">
                                    <input type="hidden" name="plan_type" value="basic">
                                    <button type="submit" name="select_plan" class="btn btn-outline-primary btn-pricing">
                                        Select Basic Plan
                                    </button>
                                </form>
                            <?php elseif (isLoggedIn() && !isProvider()): ?>
                                <button class="btn btn-outline-secondary btn-pricing" disabled>
                                    For Providers Only
                                </button>
                            <?php else: ?>
                                <a href="register.php?type=provider" class="btn btn-outline-primary btn-pricing">
                                    Sign Up as Provider
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Premium Plan (Popular) -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="pricing-card popular">
                            <div class="popular-badge">MOST POPULAR</div>
                            <div class="plan-icon">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="plan-name">Premium</div>
                            <div class="plan-price">RWF <?php echo number_format($premium_subscription_price); ?></div>
                            <div class="plan-period">per month</div>
                            
                            <ul class="plan-features">
                                <li><i class="fas fa-check"></i> Premium profile listing</li>
                                <li><i class="fas fa-check"></i> Unlimited service categories</li>
                                <li><i class="fas fa-check"></i> Enhanced search visibility</li>
                                <li><i class="fas fa-check"></i> Priority in search results</li>
                                <li><i class="fas fa-check"></i> Advanced analytics dashboard</li>
                                <li><i class="fas fa-check"></i> Featured provider badge</li>
                                <li><i class="fas fa-check"></i> Direct client messaging</li>
                                <li><i class="fas fa-check"></i> No commission fees</li>
                            </ul>
                            
                            <?php if (isLoggedIn() && isProvider()): ?>
                                <form method="POST">
                                    <input type="hidden" name="plan_type" value="premium">
                                    <button type="submit" name="select_plan" class="btn btn-primary btn-pricing">
                                        Select Premium Plan
                                    </button>
                                </form>
                            <?php elseif (isLoggedIn() && !isProvider()): ?>
                                <button class="btn btn-secondary btn-pricing" disabled>
                                    For Providers Only
                                </button>
                            <?php else: ?>
                                <a href="register.php?type=provider" class="btn btn-primary btn-pricing">
                                    Sign Up as Provider
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Featured Listing -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="pricing-card">
                            <div class="plan-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="plan-name">Featured</div>
                            <div class="plan-price">RWF <?php echo number_format($featured_listing_price); ?></div>
                            <div class="plan-period">per month</div>
                            
                            <ul class="plan-features">
                                <li><i class="fas fa-check"></i> Top placement in search results</li>
                                <li><i class="fas fa-check"></i> Featured on homepage</li>
                                <li><i class="fas fa-check"></i> Premium badge display</li>
                                <li><i class="fas fa-check"></i> Increased booking conversion</li>
                                <li><i class="fas fa-check"></i> 30-day featured listing</li>
                                <li><i class="fas fa-check"></i> All Basic plan features included</li>
                                <li class="disabled"><i class="fas fa-times"></i> No commission fees</li>
                                <li class="disabled"><i class="fas fa-times"></i> Advanced analytics</li>
                            </ul>
                            
                            <?php if (isLoggedIn() && isProvider()): ?>
                                <form method="POST">
                                    <input type="hidden" name="plan_type" value="featured">
                                    <button type="submit" name="select_plan" class="btn btn-outline-primary btn-pricing">
                                        Get Featured
                                    </button>
                                </form>
                            <?php elseif (isLoggedIn() && !isProvider()): ?>
                                <button class="btn btn-outline-secondary btn-pricing" disabled>
                                    For Providers Only
                                </button>
                            <?php else: ?>
                                <a href="register.php?type=provider" class="btn btn-outline-primary btn-pricing">
                                    Sign Up as Provider
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Commission Structure -->
    <section class="commission-section">
        <div class="container">
            <h2 class="mb-4">Commission Structure</h2>
            <div class="commission-rate"><?php echo $commission_rate; ?>%</div>
            <p class="commission-description">
                For Basic plan subscribers, we charge a <?php echo $commission_rate; ?>% commission on completed jobs. 
                Premium plan subscribers enjoy <strong>0% commission</strong> on all jobs. 
                This helps us maintain the platform while you focus on growing your business.
            </p>
        </div>
    </section>

    <!-- Premium Categories -->
    <section class="categories-section">
        <div class="container">
            <div class="section-title">
                <h2>Premium Service Categories</h2>
                <p>Access exclusive service categories with higher earning potential</p>
            </div>

            <?php if (empty($categories)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    No premium categories available at the moment.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="category-card">
                                <div class="category-icon">
                                    <i class="fas <?php echo $category['icon']; ?>"></i>
                                </div>
                                <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                <div class="category-price">RWF <?php echo number_format($category['monthly_fee']); ?>/mo</div>
                                <div class="category-description">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </div>
                                
                                <?php if (isLoggedIn() && isProvider()): ?>
                                    <?php 
                                    $is_subscribed = false;
                                    foreach ($user_category_subscriptions as $sub) {
                                        if ($sub['category_id'] == $category['id']) {
                                            $is_subscribed = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($is_subscribed): ?>
                                        <button class="btn btn-success btn-pricing" disabled>
                                            <i class="fas fa-check me-2"></i> Subscribed
                                        </button>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" name="subscribe_category" class="btn btn-primary btn-pricing">
                                                Subscribe to Category
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif (isLoggedIn() && !isProvider()): ?>
                                    <button class="btn btn-outline-secondary btn-pricing" disabled>
                                        For Providers Only
                                    </button>
                                <?php else: ?>
                                    <a href="register.php?type=provider" class="btn btn-outline-primary btn-pricing">
                                        Become a Provider
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- User's Active Subscriptions -->
    <?php if (isLoggedIn() && isProvider() && (!empty($user_subscriptions) || !empty($user_category_subscriptions))): ?>
        <section class="pricing-section">
            <div class="container">
                <div class="user-subscriptions">
                    <h3 class="mb-4">Your Active Subscriptions</h3>
                    
                    <?php foreach ($user_subscriptions as $subscription): ?>
                        <div class="subscription-item">
                            <div class="subscription-info">
                                <h5><?php echo ucfirst($subscription['plan_type']); ?> Plan</h5>
                                <p>Expires on <?php echo date('M j, Y', strtotime($subscription['end_date'])); ?></p>
                            </div>
                            <div class="subscription-status status-active">Active</div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($user_category_subscriptions as $category_sub): ?>
                        <div class="subscription-item">
                            <div class="subscription-info">
                                <h5><i class="fas <?php echo $category_sub['icon']; ?> me-2"></i> <?php echo htmlspecialchars($category_sub['category_name']); ?></h5>
                                <p>Premium Category - Expires on <?php echo date('M j, Y', strtotime($category_sub['end_date'])); ?></p>
                            </div>
                            <div class="subscription-status status-active">Active</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="section-title">
                <h2>Frequently Asked Questions</h2>
                <p>Get answers to common questions about our pricing and plans</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="faq-item">
                        <div class="faq-question">What payment methods do you accept?</div>
                        <div class="faq-answer">
                            We accept mobile money (MTN, Airtel), bank transfers, and credit/debit cards through our secure payment partners.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Can I cancel my subscription anytime?</div>
                        <div class="faq-answer">
                            Yes, you can cancel your subscription at any time. Your subscription will remain active until the end of the current billing period.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">What's the difference between Basic and Premium plans?</div>
                        <div class="faq-answer">
                            The Premium plan offers enhanced visibility, no commission fees, advanced analytics, and premium features like direct client messaging and featured badges.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Do you offer refunds?</div>
                        <div class="faq-answer">
                            We offer a 7-day money-back guarantee for new subscribers. If you're not satisfied, contact our support team for a full refund.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Can I switch between plans?</div>
                        <div class="faq-answer">
                            Yes, you can upgrade or downgrade your plan at any time. The changes will take effect at the start of your next billing cycle.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($platform_description); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
                    <p class="text-muted mb-0">Contact: <?php echo htmlspecialchars($contact_email); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Plan comparison highlight
        document.addEventListener('DOMContentLoaded', function() {
            const pricingCards = document.querySelectorAll('.pricing-card');
            
            pricingCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.zIndex = '10';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.zIndex = '1';
                });
            });
        });
    </script>
</body>
</html>