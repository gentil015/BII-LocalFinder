<?php
/**
 * Subscription Payment Checkout Page
 * Handles payment for subscription plan upgrades
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/subscription_access.php';

requireProvider();

// Check if plan is selected
if (!isset($_SESSION['selected_plan_id']) || !isset($_SESSION['selected_plan_price'])) {
    header('Location: ../provider/select-plan.php');
    exit;
}

$provider_id = (int)$_SESSION['user_id'];
$plan_id = (int)$_SESSION['selected_plan_id'];
$plan_price = (float)$_SESSION['selected_plan_price'];
$plan_name = $_SESSION['selected_plan_name'] ?? 'Plan';

$error = '';
$success = false;

// Get provider details
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT u.full_name, u.phone, sp.id as provider_db_id FROM users u JOIN service_providers sp ON u.id = sp.user_id WHERE u.id = ?");
$stmt->execute([$provider_id]);
$provider = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$provider) {
    header('Location: ../login.php');
    exit;
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $phone = sanitize($_POST['phone_number'] ?? '');
    
    if (empty($phone)) {
        $error = 'Please enter your mobile money phone number.';
    } elseif (!preg_match('/^07[0-9]{8}$/', $phone)) {
        $error = 'Please enter a valid Rwandan phone number (07XXXXXXXX).';
    } else {
        // Create pending payment record
        $payment_id = createPendingPayment($provider_id, $plan_id, $plan_price, 'FRW');
        
        if ($payment_id) {
            // Simulate payment processing
            // In production, this would integrate with real MTN/Airtel API
            $transaction_ref = 'SUB_' . time() . '_' . rand(1000, 9999);
            
            // Simulate successful payment (for demo)
            // In production, this would be handled by callback
            $payment_success = true; // Simulated success
            
            if ($payment_success) {
                // Update payment status
                updatePaymentStatus($payment_id, 'paid', $transaction_ref);
                
                // Activate subscription
                $subscription_id = activateSubscription($provider_id, $plan_id, 30);
                
                if ($subscription_id) {
                    // Update payment with subscription ID
                    $stmt = $db->prepare("UPDATE subscription_payments SET subscription_id = ? WHERE id = ?");
                    $stmt->execute([$subscription_id, $payment_id]);
                    
                    // Clear session
                    unset($_SESSION['selected_plan_id'], $_SESSION['selected_plan_price'], $_SESSION['selected_plan_name']);
                    
                    // Redirect to success
                    header('Location: success.php?payment_id=' . $payment_id);
                    exit;
                } else {
                    $error = 'Error activating subscription. Please contact support.';
                }
            } else {
                updatePaymentStatus($payment_id, 'failed', $transaction_ref);
                $error = 'Payment failed. Please try again.';
            }
        } else {
            $error = 'Error creating payment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BII LocalFinder</title>
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkout-container { max-width: 600px; margin: 0 auto; }
        .order-summary { background: #f8f9fa; border-radius: 12px; padding: 25px; margin-bottom: 25px; }
        .order-summary h4 { margin-bottom: 20px; color: #212529; }
        .summary-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #dee2e6; }
        .summary-row:last-child { border-bottom: none; font-weight: bold; font-size: 1.2rem; }
        .payment-methods { margin: 25px 0; }
        .payment-method { 
            border: 2px solid #dee2e6; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 10px; 
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover, .payment-method.selected { border-color: #0d6efd; background: #f8f9ff; }
        .payment-method input { display: none; }
        .payment-icon { font-size: 2rem; margin-right: 15px; color: #6c757d; }
        .phone-input { font-size: 1.1rem; padding: 12px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="checkout-container">
            <div class="text-center mb-4">
                <a href="../provider/select-plan.php" class="btn btn-link">&larr; Back to Plans</a>
                <h2 class="mt-3"><i class="fas fa-shopping-cart me-2"></i>Checkout</h2>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="order-summary">
                <h4><i class="fas fa-receipt me-2"></i>Order Summary</h4>
                <div class="summary-row">
                    <span>Plan</span>
                    <span><?php echo htmlspecialchars($plan_name); ?></span>
                </div>
                <div class="summary-row">
                    <span>Billing Period</span>
                    <span>30 days</span>
                </div>
                <div class="summary-row">
                    <span>Total</span>
                    <span class="text-success"><?php echo number_format($plan_price, 0); ?> FRW</span>
                </div>
            </div>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold">Payment Method</label>
                    <div class="payment-methods">
                        <label class="payment-method selected">
                            <input type="radio" name="payment_method" value="momo" checked>
                            <span class="payment-icon">📱</span>
                            <span><strong>Mobile Money</strong> - Pay with MTN MoMo or Airtel Money</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="phone_number" class="form-label fw-bold">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text">🇷🇼</span>
                        <input type="tel" class="form-control phone-input" id="phone_number" name="phone_number" 
                               placeholder="07XXXXXXXX" value="<?php echo htmlspecialchars($provider['phone'] ?? ''); ?>" 
                               pattern="07[0-9]{8}" required>
                    </div>
                    <div class="form-text">Enter the phone number registered with your mobile money account</div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="pay_now" class="btn btn-primary btn-lg">
                        <i class="fas fa-lock me-2"></i>Pay <?php echo number_format($plan_price, 0); ?> FRW
                    </button>
                </div>
                
                <div class="text-center mt-3 text-muted">
                    <small><i class="fas fa-shield-alt me-1"></i>Secure payment powered by BII LocalFinder</small>
                </div>
            </form>
            
            <div class="alert alert-info mt-4">
                <small><strong>Demo Mode:</strong> This is a simulated payment. In production, this would integrate with MTN MoMo or Airtel Money APIs.</small>
            </div>
        </div>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>