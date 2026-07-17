<?php
/**
 * Provider Plan Selection Page
 * Allows providers to select a subscription plan with payment integration
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/subscription_access.php';

requireProvider();

$provider_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = '';

// Check if provider already has an active subscription
$current_plan = getProviderPlan($provider_id);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
    $plan_id = (int)$_POST['plan_id'];
    $plan = getPlanById($plan_id);
    
    if (!$plan) {
        $message = 'Invalid plan selected.';
        $message_type = 'danger';
    } else {
        // Free plan - activate immediately
        if ((float)$plan['monthly_price'] === 0) {
            if (activateSubscription($provider_id, $plan_id, 365)) { // Free plan lasts 1 year
                $message = 'Free plan activated successfully!';
                $message_type = 'success';
                $current_plan = getProviderPlan($provider_id);
            } else {
                $message = 'Error activating subscription. Please try again.';
                $message_type = 'danger';
            }
        } else {
            // Paid plan - redirect to payment
            $_SESSION['selected_plan_id'] = $plan_id;
            $_SESSION['selected_plan_price'] = $plan['monthly_price'];
            $_SESSION['selected_plan_name'] = $plan['name'];
            header('Location: ../payment/checkout.php');
            exit;
        }
    }
}

// Get all plans
$plans = getAllPlans();

// Ensure monthly_price exists (fallback to price if not)
foreach ($plans as &$plan) {
    if (!isset($plan['monthly_price'])) {
        $plan['monthly_price'] = $plan['price'] ?? 0;
    }
    // Map new schema columns to old schema names for display compatibility
    if (!isset($plan['ranking_level'])) {
        $plan['ranking_level'] = ($plan['priority_ranking'] ?? 0) ? 'priority' : 'standard';
    }
    if (!isset($plan['payout_priority'])) {
        $plan['payout_priority'] = ($plan['instant_payout_priority'] ?? 0) ? 'instant' : (($plan['faster_payout'] ?? 0) ? 'faster' : 'standard');
    }
    if (!isset($plan['verified_request'])) {
        $plan['verified_request'] = $plan['verified_badge_request'] ?? 0;
    }
    if (!isset($plan['lead_insights'])) {
        $plan['lead_insights'] = $plan['basic_lead_insight'] ?? 0;
    }
    if (!isset($plan['featured_badge'])) {
        $plan['featured_badge'] = $plan['higher_search_ranking'] ?? 0;
    }
    if (!isset($plan['customer_feedback'])) {
        $plan['customer_feedback'] = $plan['customer_repeat_insight'] ?? 0;
    }
    if (!isset($plan['reports_enabled'])) {
        $plan['reports_enabled'] = $plan['export_reports'] ?? 0;
    }
    if (!isset($plan['boost_days_cooldown'])) {
        $plan['boost_days_cooldown'] = $plan['boost_fair_limit'] ?? 14;
    }
}
unset($plan);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Plan - BII LocalFinder</title>
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .plan-card {
            border: 2px solid #dee2e6;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        .plan-card.selected {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        .plan-card.free { border-color: #6c757d; }
        .plan-card.standard { border-color: #0d6efd; }
        .plan-card.pro { border-color: #198754; }
        
        .plan-badge {
            position: absolute;
            top: 20px;
            right: -35px;
            background: #ffc107;
            color: #000;
            padding: 5px 40px;
            font-size: 0.75rem;
            font-weight: bold;
            transform: rotate(45deg);
        }
        .plan-badge.popular { background: #0d6efd; color: #fff; }
        .plan-badge.best { background: #198754; color: #fff; }
        
        .plan-name { font-size: 1.75rem; font-weight: 700; margin-bottom: 15px; color: #212529; }
        .plan-price { font-size: 2.5rem; font-weight: 800; color: #212529; margin-bottom: 5px; }
        .plan-price span { font-size: 1rem; font-weight: 500; color: #6c757d; }
        .plan-period { color: #6c757d; font-size: 0.9rem; margin-bottom: 20px; }
        
        .plan-features { list-style: none; padding: 0; margin: 25px 0; text-align: left; }
        .plan-features li { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; }
        .plan-features li:last-child { border-bottom: none; }
        .plan-features li i { margin-right: 10px; width: 20px; text-align: center; }
        .plan-features li i.fa-check { color: #198754; }
        .plan-features li i.fa-times { color: #dc3545; }
        .plan-features li i.fa-star { color: #ffc107; }
        
        .current-plan-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #198754;
            color: white;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-2"><i class="fas fa-crown me-2"></i>Choose Your Plan</h2>
                    <p class="text-muted">Select a subscription plan that fits your business needs</p>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($current_plan): ?>
            <div class="alert alert-info d-flex align-items-center justify-content-between mb-4">
                <div>
                    <strong><i class="fas fa-info-circle me-2"></i>Current Plan:</strong> 
                    <?php echo htmlspecialchars($current_plan['name']); ?> 
                    (Expires: <?php echo htmlspecialchars($current_plan['end_date']); ?>)
                </div>
                <a href="billing.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-credit-card me-1"></i> Manage Billing
                </a>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <?php foreach ($plans as $plan): 
                    $plan_class = strtolower($plan['name']);
                    $service_limit = $plan['service_limit'] == 0 ? 'Unlimited' : $plan['service_limit'];
                    $photo_limit = $plan['photo_limit'] == 0 ? 'Unlimited' : $plan['photo_limit'];
                    $is_current = $current_plan && $current_plan['id'] == $plan['id'];
                    
                    $badge = '';
                    $badge_class = '';
                    if ($plan['name'] === 'Standard') { $badge = 'Most Popular'; $badge_class = 'popular'; }
                    elseif ($plan['name'] === 'Pro') { $badge = 'Best for Growth'; $badge_class = 'best'; }
                ?>
                <div class="col-md-4 mb-4">
                    <div class="plan-card <?php echo $plan_class; ?><?php echo $is_current ? ' selected' : ''; ?>">
                        <?php if ($badge): ?>
                        <div class="plan-badge <?php echo $badge_class; ?>"><?php echo $badge; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($is_current): ?>
                        <div class="current-plan-badge">Current Plan</div>
                        <?php endif; ?>
                        
                        <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                        
                        <div class="plan-price">
                            <?php if ((float)$plan['monthly_price'] == 0): ?>
                            FREE
                            <?php else: ?>
                            <?php echo number_format($plan['monthly_price'], 0); ?> FRW
                            <span>/mo</span>
                            <?php endif; ?>
                        </div>
                        <div class="plan-period">
                            <?php echo (float)$plan['monthly_price'] == 0 ? 'Forever' : 'Billed monthly'; ?>
                        </div>
                        
                        <ul class="plan-features">
                            <li><i class="fas fa-check"></i> <strong><?php echo $service_limit; ?></strong> active services</li>
                            <li><i class="fas fa-check"></i> <strong><?php echo $photo_limit; ?></strong> photos per service</li>
                            <li><i class="fas fa-<?php echo in_array($plan['analytics_level'], ['basic', 'better', 'advanced']) ? 'check' : 'times'; ?>"></i> <?php echo ucfirst($plan['analytics_level']); ?> analytics</li>
                            <li><i class="fas fa-check"></i> Receive booking requests</li>
                            <li><i class="fas fa-<?php echo $plan['ranking_level'] !== 'standard' ? 'star' : 'times'; ?>"></i> <?php echo ucfirst($plan['ranking_level']); ?> ranking</li>
                            <li><i class="fas fa-check"></i> Appear in search results</li>
                            <li><i class="fas fa-<?php echo $plan['ai_enabled'] ? 'check' : 'times'; ?>"></i> AI features <?php echo $plan['ai_enabled'] ? '(Title, Pricing, Description)' : '(Not available)'; ?></li>
                            <li><i class="fas fa-<?php echo $plan['boost_days_cooldown'] < 14 ? 'check' : 'times'; ?>"></i> Boost every <?php echo $plan['boost_days_cooldown'] == 0 ? 'day' : $plan['boost_days_cooldown'] . ' days'; ?></li>
                            <li><i class="fas fa-<?php echo $plan['payout_priority'] !== 'standard' ? 'check' : 'times'; ?>"></i> <?php echo ucfirst($plan['payout_priority']); ?> payout</li>
                            <li><i class="fas fa-<?php echo $plan['verified_request'] ? 'check' : 'times'; ?>"></i> Verified request</li>
                            <li><i class="fas fa-<?php echo $plan['lead_insights'] ? 'check' : 'times'; ?>"></i> Lead insights</li>
                            <li><i class="fas fa-<?php echo $plan['featured_badge'] ? 'check' : 'times'; ?>"></i> Featured badge</li>
                            <li><i class="fas fa-<?php echo $plan['customer_feedback'] ? 'check' : 'times'; ?>"></i> Customer feedback</li>
                            <li><i class="fas fa-<?php echo $plan['reports_enabled'] ? 'check' : 'times'; ?>"></i> Export reports</li>
                        </ul>
                        
                        <?php if ($is_current): ?>
                        <button class="btn btn-secondary w-100 py-2" disabled>Current Plan</button>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <?php if ((float)$plan['monthly_price'] == 0): ?>
                            <button type="submit" class="btn btn-outline-secondary w-100 py-2"><i class="fas fa-check me-2"></i>Select Free</button>
                            <?php elseif ($plan['name'] == 'Standard'): ?>
                            <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-shopping-cart me-2"></i>Select Standard</button>
                            <?php else: ?>
                            <button type="submit" class="btn btn-success w-100 py-2"><i class="fas fa-rocket me-2"></i>Select Pro</button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-light border">
                        <h5><i class="fas fa-info-circle me-2"></i>Note</h5>
                        <p class="mb-0">This is a foundation version. Future updates will include:</p>
                        <ul class="mb-0 mt-2">
                            <li>Real payment gateway integration (MTN MoMo, Airtel Money)</li>
                            <li>Automatic subscription renewals</li>
                            <li>Plan upgrade/downgrade with proration</li>
                            <li>Detailed analytics dashboard</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>