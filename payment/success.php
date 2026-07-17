<?php
/**
 * Payment Success Page
 * Shown after successful subscription payment
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/subscription_access.php';

requireProvider();

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

if (!$payment_id) {
    header('Location: ../provider/select-plan.php');
    exit;
}

$provider_id = (int)$_SESSION['user_id'];

// Get payment and subscription details
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT sp.*, p.name as plan_name, p.monthly_price, p.service_limit, p.photo_limit
    FROM subscription_payments sp
    JOIN plans p ON sp.amount = p.monthly_price
    WHERE sp.id = ? AND sp.provider_id = ?
");
$stmt->execute([$payment_id, $provider_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: ../provider/select-plan.php');
    exit;
}

$current_plan = getProviderPlan($provider_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - BII LocalFinder</title>
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .success-container { max-width: 600px; margin: 0 auto; text-align: center; }
        .success-icon { 
            font-size: 5rem; 
            color: #198754; 
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .success-box { 
            background: #f8f9fa; 
            border-radius: 16px; 
            padding: 40px; 
            margin-top: 30px;
        }
        .plan-details { 
            text-align: left; 
            background: #fff; 
            border-radius: 10px; 
            padding: 20px; 
            margin: 20px 0;
        }
        .detail-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 10px 0; 
            border-bottom: 1px solid #eee; 
        }
        .detail-row:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h2 class="text-success">Payment Successful!</h2>
            <p class="lead text-muted">Thank you for your subscription</p>
            
            <div class="success-box">
                <h4><?php echo htmlspecialchars($payment['plan_name']); ?> Plan Activated</h4>
                
                <div class="plan-details">
                    <div class="detail-row">
                        <span><i class="fas fa-calendar me-2"></i>Start Date</span>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span><i class="fas fa-calendar-times me-2"></i>End Date</span>
                        <span><?php echo date('F j, Y', strtotime('+30 days')); ?></span>
                    </div>
                    <div class="detail-row">
                        <span><i class="fas fa-money-bill-wave me-2"></i>Amount Paid</span>
                        <span class="text-success"><?php echo number_format($payment['amount'], 0); ?> FRW</span>
                    </div>
                    <div class="detail-row">
                        <span><i class="fas fa-file-invoice me-2"></i>Transaction ID</span>
                        <span><small><?php echo htmlspecialchars($payment['transaction_ref'] ?? 'N/A'); ?></small></span>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="../provider/dashboard.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-home me-2"></i>Go to Dashboard
                    </a>
                    <a href="../provider/services.php" class="btn btn-outline-primary">
                        <i class="fas fa-tools me-2"></i>Manage Services
                    </a>
                </div>
            </div>
            
            <div class="alert alert-info mt-4">
                <small><i class="fas fa-info-circle me-1"></i>Your subscription will automatically renew in 30 days. You can cancel anytime from your billing settings.</small>
            </div>
        </div>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>