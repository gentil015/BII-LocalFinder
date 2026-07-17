<?php
/**
 * Provider Billing Dashboard
 * Shows subscription status, payment history, and plan management
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/subscription_access.php';

requireProvider();

$provider_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = '';

// Get current plan
$current_plan = getProviderPlan($provider_id);
$days_remaining = getDaysRemaining($provider_id);
$service_count = getProviderServiceCount($provider_id);
$service_limit = getServiceLimit($provider_id);

// Get payment history
$payments = getPaymentHistory($provider_id, 10);

// Get all available plans for upgrade
$all_plans = getAllPlans();

// Handle upgrade request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_plan_id'])) {
    $upgrade_plan_id = (int)$_POST['upgrade_plan_id'];
    $upgrade_plan = getPlanById($upgrade_plan_id);
    
    if ($upgrade_plan && (float)$upgrade_plan['monthly_price'] > 0) {
        $_SESSION['selected_plan_id'] = $upgrade_plan_id;
        $_SESSION['selected_plan_price'] = $upgrade_plan['monthly_price'];
        $_SESSION['selected_plan_name'] = $upgrade_plan['name'];
        header('Location: ../payment/checkout.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Subscription - BII LocalFinder</title>
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .billing-card { 
            background: #fff; 
            border-radius: 16px; 
            padding: 25px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .current-plan-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .current-plan-card .plan-name { font-size: 2rem; font-weight: 700; }
        .current-plan-card .plan-price { font-size: 1.5rem; opacity: 0.9; }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .stat-box .number { font-size: 2rem; font-weight: 700; color: #212529; }
        .stat-box .label { color: #6c757d; font-size: 0.9rem; }
        .payment-history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .payment-history-item:last-child { border-bottom: none; }
        .status-badge { 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 600;
        }
        .status-badge.paid { background: #d4edda; color: #155724; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.failed { background: #f8d7da; color: #721c24; }
        .upgrade-card {
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upgrade-card:hover { border-color: #0d6efd; transform: translateY(-3px); }
        .upgrade-card.current { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-2"><i class="fas fa-credit-card me-2"></i>Billing & Subscription</h2>
                    <p class="text-muted">Manage your subscription and view payment history</p>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Current Plan -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="billing-card current-plan-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="plan-name"><?php echo htmlspecialchars($current_plan['name'] ?? 'Free'); ?> Plan</div>
                                <div class="plan-price">
                                    <?php echo $current_plan ? number_format($current_plan['monthly_price'], 0) . ' FRW / month' : 'Free forever'; ?>
                                </div>
                                <div class="mt-3">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo $current_plan ? 'Expires: ' . date('M j, Y', strtotime($current_plan['end_date'])) : 'No expiration'; ?>
                                    </span>
                                    <?php if ($days_remaining > 0 && $days_remaining <= 7): ?>
                                    <span class="badge bg-warning text-dark ms-2">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php echo $days_remaining; ?> days remaining
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="select-plan.php" class="btn btn-light">
                                    <i class="fas fa-exchange-alt me-2"></i>Change Plan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="billing-card">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="stat-box">
                                    <div class="number"><?php echo $service_count; ?></div>
                                    <div class="label">Services</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box">
                                    <div class="number"><?php echo $service_limit == 0 ? '∞' : $service_limit; ?></div>
                                    <div class="label">Limit</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Plan Benefits -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="billing-card">
                        <h5 class="mb-3"><i class="fas fa-star me-2"></i>Your Plan Features</h5>
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <div class="text-center">
                                    <i class="fas fa-tools fa-2x text-primary mb-2"></i>
                                    <div><strong><?php echo $service_limit == 0 ? 'Unlimited' : $service_limit; ?></strong></div>
                                    <small class="text-muted">Services</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="text-center">
                                    <i class="fas fa-image fa-2x text-primary mb-2"></i>
                                    <div><strong><?php echo ($current_plan['photo_limit'] ?? 3) == 0 ? 'Unlimited' : $current_plan['photo_limit']; ?></strong></div>
                                    <small class="text-muted">Photos/Service</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="text-center">
                                    <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                                    <div><strong><?php echo ucfirst($current_plan['analytics_level'] ?? 'basic'); ?></strong></div>
                                    <small class="text-muted">Analytics</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="text-center">
                                    <i class="fas fa-rocket fa-2x text-primary mb-2"></i>
                                    <div><strong><?php echo ($current_plan['boost_days_cooldown'] ?? 14) == 0 ? 'Anytime' : 'Every ' . ($current_plan['boost_days_cooldown'] ?? 14) . ' days'; ?></strong></div>
                                    <small class="text-muted">Boost</small>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 col-6 mb-2">
                                <small><i class="fas fa-<?php echo ($current_plan['ai_enabled'] ?? 0) ? 'check text-success' : 'times text-muted'; ?> me-1"></i> AI Features</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <small><i class="fas fa-<?php echo ($current_plan['verified_request'] ?? 0) ? 'check text-success' : 'times text-muted'; ?> me-1"></i> Verified Request</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <small><i class="fas fa-<?php echo ($current_plan['reports_enabled'] ?? 0) ? 'check text-success' : 'times text-muted'; ?> me-1"></i> Export Reports</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <small><i class="fas fa-<?php echo ($current_plan['featured_badge'] ?? 0) ? 'check text-success' : 'times text-muted'; ?> me-1"></i> Featured Badge</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upgrade Options -->
            <?php if (!$current_plan || (float)($current_plan['monthly_price'] ?? 0) === 0): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="billing-card">
                        <h5 class="mb-3"><i class="fas fa-arrow-up me-2"></i>Upgrade Your Plan</h5>
                        <div class="row">
                            <?php foreach ($all_plans as $plan): 
                                $is_current = $current_plan && $current_plan['id'] == $plan['id'];
                                if ($is_current || (float)$plan['monthly_price'] === 0) continue;
                            ?>
                            <div class="col-md-4">
                                <div class="upgrade-card">
                                    <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                                    <div class="text-success fw-bold"><?php echo number_format($plan['monthly_price'], 0); ?> FRW/mo</div>
                                    <ul class="text-start small mt-3">
                                        <li><?php echo $plan['service_limit'] == 0 ? 'Unlimited' : $plan['service_limit']; ?> services</li>
                                        <li><?php echo $plan['boost_days_cooldown'] == 0 ? 'Anytime boost' : 'Boost every ' . $plan['boost_days_cooldown'] . ' days'; ?></li>
                                        <li><?php echo ucfirst($plan['analytics_level']); ?> analytics</li>
                                        <?php if ($plan['ai_enabled']): ?><li>AI features</li><?php endif; ?>
                                    </ul>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="upgrade_plan_id" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" class="btn btn-primary w-100">Upgrade</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment History -->
            <div class="row">
                <div class="col-12">
                    <div class="billing-card">
                        <h5 class="mb-3"><i class="fas fa-history me-2"></i>Payment History</h5>
                        <?php if (empty($payments)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-receipt fa-3x mb-3"></i>
                            <p>No payment history yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Transaction ID</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                        <td><?php echo number_format($payment['amount'], 0); ?> FRW</td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td><small><?php echo htmlspecialchars($payment['transaction_ref'] ?? '-'); ?></small></td>
                                        <td>
                                            <span class="status-badge <?php echo $payment['status']; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>