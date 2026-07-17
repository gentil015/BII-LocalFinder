<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Check if commission system is enabled
if (!isCommissionEnabled()) {
    $_SESSION['error_message'] = "Commission system is currently disabled by admin.";
    header("Location: dashboard.php");
    exit();
}

// Get date filters
$filter_month = isset($_GET['month']) ? sanitize($_GET['month']) : date('Y-m');
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'completed';

// Get earnings statistics
$earnings_summary = [
    'total_earnings' => 0,
    'pending_earnings' => 0,
    'this_month_earnings' => 0,
    'last_month_earnings' => 0,
    'total_withdrawn' => 0,
    'available_balance' => 0
];

// Total earnings (completed bookings with payment completed)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM bookings 
    WHERE provider_id = ? 
    AND status = 'completed' 
    AND payment_status = 'completed'
");
$stmt->execute([$provider['id']]);
$earnings_summary['total_earnings'] = $stmt->fetch()['total'] ?? 0;

// Pending earnings (completed bookings but payment pending)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM bookings 
    WHERE provider_id = ? 
    AND status = 'completed' 
    AND payment_status = 'pending'
");
$stmt->execute([$provider['id']]);
$earnings_summary['pending_earnings'] = $stmt->fetch()['total'] ?? 0;

// This month earnings
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM bookings 
    WHERE provider_id = ? 
    AND status = 'completed' 
    AND payment_status = 'completed'
    AND YEAR(updated_at) = YEAR(CURDATE())
    AND MONTH(updated_at) = MONTH(CURDATE())
");
$stmt->execute([$provider['id']]);
$earnings_summary['this_month_earnings'] = $stmt->fetch()['total'] ?? 0;

// Last month earnings
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM bookings 
    WHERE provider_id = ? 
    AND status = 'completed' 
    AND payment_status = 'completed'
    AND YEAR(updated_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND MONTH(updated_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
");
$stmt->execute([$provider['id']]);
$earnings_summary['last_month_earnings'] = $stmt->fetch()['total'] ?? 0;

// Withdrawals are not supported on this platform
    $earnings_summary['total_withdrawn'] = 0;
// Available balance equals total earnings (no withdrawals)
    $earnings_summary['available_balance'] = $earnings_summary['total_earnings'];

// Get earnings by month for chart
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(b.updated_at, '%Y-%m') as month,
        COUNT(*) as total_bookings,
        COALESCE(SUM(b.amount), 0) as total_earnings
    FROM bookings b
    WHERE b.provider_id = ? 
    AND b.status = 'completed'
    AND b.payment_status = 'completed'
    AND b.updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(b.updated_at, '%Y-%m')
    ORDER BY month DESC
");
$stmt->execute([$provider['id']]);
$monthly_earnings = $stmt->fetchAll();

// Get recent transactions
// Recent transactions (bookings only — payouts not supported)
$transactions_sql = "
    SELECT 
        b.id,
        b.service_description,
        b.amount,
        b.payment_status,
        b.updated_at,
        u.full_name as client_name,
        'booking' as type
    FROM bookings b
    JOIN users u ON b.client_id = u.id
    WHERE b.provider_id = ? 
    AND b.status = 'completed'
    ORDER BY b.updated_at DESC
    LIMIT 20
";

$stmt = $db->prepare($transactions_sql);
$stmt->execute([$provider['id']]);
$recent_transactions = $stmt->fetchAll();

// Get payout history
$stmt = $db->prepare("
    SELECT 
        p.*,
        pm.method_name,
        pm.account_details
    FROM payout_history p
    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
    WHERE p.provider_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$provider['id']]);
$payout_history = $stmt->fetchAll();

// Get payment methods
$stmt = $db->prepare("
    SELECT * FROM payment_methods 
    WHERE provider_id = ? 
    AND is_active = 1
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$provider['id']]);
$payment_methods = $stmt->fetchAll();

// Get current withdrawal settings
$min_withdrawal = getSetting('min_withdrawal_amount', 5000); // Default 5000 RWF
$max_withdrawal = getSetting('max_withdrawal_amount', 1000000); // Default 1,000,000 RWF

// Withdrawals are not supported on this platform; server-side withdrawal handler removed.
// Handle add payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_method'])) {
    $method_name = sanitize($_POST['method_name']);
    $account_type = sanitize($_POST['account_type']);
    $account_details = json_encode([
        'account_name' => sanitize($_POST['account_name']),
        'account_number' => sanitize($_POST['account_number']),
        'bank_name' => isset($_POST['bank_name']) ? sanitize($_POST['bank_name']) : '',
        'mobile_network' => isset($_POST['mobile_network']) ? sanitize($_POST['mobile_network']) : ''
    ]);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    try {
        // If setting as default, remove default from others
        if ($is_default) {
            $stmt = $db->prepare("
                UPDATE payment_methods 
                SET is_default = 0 
                WHERE provider_id = ?
            ");
            $stmt->execute([$provider['id']]);
        }
        
        // Insert new payment method
        $stmt = $db->prepare("
            INSERT INTO payment_methods 
            (provider_id, method_name, account_type, account_details, is_default, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$provider['id'], $method_name, $account_type, $account_details, $is_default]);
        
        $_SESSION['success_message'] = "Payment method added successfully!";
        header("Location: earnings.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Failed to add payment method: " . $e->getMessage();
        error_log("Payment method error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - <?php echo getPlatformName(); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --sidebar-width: 250px;
            --card-radius: 12px;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-hover: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Maintenance Warning */
        .maintenance-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #856404;
            border: none;
            margin-bottom: 1rem;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .sidebar-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-card.balance {
            border-left-color: var(--success);
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }
        
        .stat-card.pending {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        }
        
        .stat-card.withdrawn {
            border-left-color: var(--info);
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: white;
            background: var(--primary);
        }
        
        .stat-card.balance .stat-icon {
            background: var(--success);
        }
        
        .stat-card.pending .stat-icon {
            background: var(--warning);
        }
        
        .stat-card.withdrawn .stat-icon {
            background: var(--info);
        }
        
        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: var(--dark);
        }
        
        .stat-content p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
        }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        /* Transactions */
        .transactions-section {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s;
        }
        
        .transaction-item:hover {
            background: #f8f9fa;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-info h5 {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-weight: 600;
        }
        
        .transaction-info p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .transaction-amount {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .amount-positive {
            color: var(--success);
        }
        
        .amount-negative {
            color: var(--danger);
        }
        
        /* Status Badges */
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-pending {
            background: #cce5ff;
            color: #004085;
        }
        
        /* Withdrawal styles removed - withdrawals not supported */
        
        /* Payment Methods */
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .payment-method-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .payment-method-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .payment-method-card.default {
            border-color: var(--success);
            background: #f8fff9;
        }
        
        .method-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .method-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .method-type {
            background: #e9ecef;
            color: var(--dark);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .method-details {
            margin-top: 1rem;
        }
        
        .method-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .method-detail:last-child {
            border-bottom: none;
        }
        
        .method-detail-label {
            color: var(--secondary);
            font-weight: 500;
        }
        
        .method-detail-value {
            color: var(--dark);
            font-weight: 600;
        }
        
        /* Payout History */
        .payout-history-table {
            background: white;
            border-radius: var(--card-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--dark);
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        .empty-state h4 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .overlay.active {
            display: block;
        }
        
        /* Withdraw button styles removed - withdrawals not supported */
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--success);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Maintenance Warning -->
        <?php if (isset($maintenance_warning)): ?>
            <div class="alert maintenance-warning">
                <i class="fas fa-tools me-2"></i>
                <strong>Maintenance Mode Active</strong>
                <p class="mb-0 mt-2">The platform is currently under maintenance. Some features may be limited.</p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-money-bill-wave"></i> My Earnings</h1>
            <p>Track your earnings and view transaction history</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Earnings Statistics -->
        <div class="stats-grid">
            <div class="stat-card balance">
                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-content">
                    <h3>RWF <?php echo number_format($earnings_summary['available_balance'], 0); ?></h3>
                    <p>Total Earnings</p>
                    <small class="text-muted">Withdrawals are not supported on this platform</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>RWF <?php echo number_format($earnings_summary['total_earnings'], 0); ?></h3>
                    <p>Total Earnings</p>
                    <small class="text-muted">All time earnings</small>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>RWF <?php echo number_format($earnings_summary['pending_earnings'], 0); ?></h3>
                    <p>Pending Earnings</p>
                    <small class="text-muted">Awaiting payment clearance</small>
                </div>
            </div>
            
            <div class="stat-card withdrawn">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-content">
                    <h3>RWF <?php echo number_format($earnings_summary['total_withdrawn'], 0); ?></h3>
                    <p>Total Withdrawn</p>
                    <small class="text-muted">Amount withdrawn to date</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>RWF <?php echo number_format($earnings_summary['this_month_earnings'], 0); ?></h3>
                    <p>This Month</p>
                    <small class="text-muted">
                        <?php 
                        $change = $earnings_summary['last_month_earnings'] > 0 
                            ? (($earnings_summary['this_month_earnings'] - $earnings_summary['last_month_earnings']) / $earnings_summary['last_month_earnings'] * 100) 
                            : 0;
                        ?>
                        <?php if ($change >= 0): ?>
                            <span class="text-success">↑ <?php echo number_format($change, 1); ?>% from last month</span>
                        <?php else: ?>
                            <span class="text-danger">↓ <?php echo number_format(abs($change), 1); ?>% from last month</span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Withdrawal Section removed: withdrawals are not supported on this platform -->

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Earnings Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar me-2"></i> Earnings Overview (Last 6 Months)</h3>
                </div>
                <div style="height: 300px;">
                    <canvas id="earningsChart"></canvas>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-bullseye me-2"></i> Quick Stats</h3>
                </div>
                <div class="quick-stats">
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <h5>Average Earnings per Job</h5>
                            <p><i class="fas fa-calculator"></i> Based on completed jobs</p>
                        </div>
                        <div class="transaction-amount amount-positive">
                            <?php
                            $stmt = $db->prepare("
                                SELECT COUNT(*) as count, AVG(amount) as avg_earnings
                                FROM bookings 
                                WHERE provider_id = ? 
                                AND status = 'completed' 
                                AND payment_status = 'completed'
                            ");
                            $stmt->execute([$provider['id']]);
                            $avg_data = $stmt->fetch();
                            ?>
                            RWF <?php echo number_format($avg_data['avg_earnings'] ?? 0, 0); ?>
                        </div>
                    </div>
                    
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <h5>Completed Jobs</h5>
                            <p><i class="fas fa-check-circle"></i> Total successful jobs</p>
                        </div>
                        <div class="transaction-amount">
                            <?php echo number_format($avg_data['count'] ?? 0); ?>
                        </div>
                    </div>
                    
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <h5>Success Rate</h5>
                            <p><i class="fas fa-percentage"></i> Completed vs total bookings</p>
                        </div>
                        <div class="transaction-amount">
                            <?php
                            $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ?");
                            $stmt->execute([$provider['id']]);
                            $total_bookings = $stmt->fetch()['total'];
                            
                            $success_rate = $total_bookings > 0 ? ($avg_data['count'] / $total_bookings * 100) : 0;
                            ?>
                            <?php echo number_format($success_rate, 1); ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="transactions-section" id="paymentMethods">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="fas fa-credit-card me-2"></i> Payment Methods</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentMethodModal">
                    <i class="fas fa-plus me-2"></i> Add New Method
                </button>
            </div>
            
            <?php if (empty($payment_methods)): ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card"></i>
                    <h4>No Payment Methods</h4>
                    <p>Add a payment method to withdraw your earnings</p>
                </div>
            <?php else: ?>
                <div class="payment-methods-grid">
                    <?php foreach ($payment_methods as $method): 
                        $details = json_decode($method['account_details'], true);
                    ?>
                        <div class="payment-method-card <?php echo $method['is_default'] ? 'default' : ''; ?>">
                            <div class="method-header">
                                <div class="method-icon">
                                    <?php if ($method['account_type'] === 'bank'): ?>
                                        <i class="fas fa-university"></i>
                                    <?php elseif ($method['account_type'] === 'mobile_money'): ?>
                                        <i class="fas fa-mobile-alt"></i>
                                    <?php else: ?>
                                        <i class="fas fa-wallet"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($method['is_default']): ?>
                                        <span class="badge badge-success">Default</span>
                                    <?php endif; ?>
                                    <span class="method-type"><?php echo ucfirst(str_replace('_', ' ', $method['account_type'])); ?></span>
                                </div>
                            </div>
                            
                            <h5><?php echo htmlspecialchars($method['method_name']); ?></h5>
                            
                            <div class="method-details">
                                <?php if (!empty($details['account_name'])): ?>
                                    <div class="method-detail">
                                        <span class="method-detail-label">Account Name</span>
                                        <span class="method-detail-value"><?php echo htmlspecialchars($details['account_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($details['account_number'])): ?>
                                    <div class="method-detail">
                                        <span class="method-detail-label">
                                            <?php echo $method['account_type'] === 'bank' ? 'Account Number' : 'Phone Number'; ?>
                                        </span>
                                        <span class="method-detail-value"><?php echo htmlspecialchars($details['account_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($details['bank_name'])): ?>
                                    <div class="method-detail">
                                        <span class="method-detail-label">Bank Name</span>
                                        <span class="method-detail-value"><?php echo htmlspecialchars($details['bank_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($details['mobile_network'])): ?>
                                    <div class="method-detail">
                                        <span class="method-detail-label">Mobile Network</span>
                                        <span class="method-detail-value"><?php echo htmlspecialchars($details['mobile_network']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions -->
        <div class="transactions-section">
            <h3 class="mb-3"><i class="fas fa-exchange-alt me-2"></i> Recent Transactions</h3>
            
            <?php if (empty($recent_transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt"></i>
                    <h4>No Transactions Yet</h4>
                    <p>Your transaction history will appear here</p>
                </div>
            <?php else: ?>
                <div class="transactions-list">
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <h5>
                                    <?php echo htmlspecialchars($transaction['service_description']); ?>
                                    <?php if ($transaction['type'] === 'payout'): ?>
                                        <span class="badge badge-warning">Withdrawal</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Earning</span>
                                    <?php endif; ?>
                                </h5>
                                <p>
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($transaction['client_name']); ?> • 
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($transaction['updated_at'])); ?>
                                </p>
                            </div>
                            <div class="transaction-amount <?php echo $transaction['amount'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo $transaction['amount'] >= 0 ? '+' : ''; ?>RWF <?php echo number_format(abs($transaction['amount']), 0); ?>
                                <div class="text-muted small mt-1">
                                    <span class="badge badge-<?php echo $transaction['payment_status']; ?>">
                                        <?php echo ucfirst($transaction['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payout History -->
        <div class="transactions-section">
            <h3 class="mb-3"><i class="fas fa-history me-2"></i> Payout History</h3>
            
            <?php if (empty($payout_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h4>No Payout History</h4>
                    <p>Your withdrawal requests will appear here</p>
                </div>
            <?php else: ?>
                <div class="payout-history-table">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payout_history as $payout): 
                                $method_details = json_decode($payout['account_details'], true);
                            ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo str_pad($payout['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td>
                                        <strong>RWF <?php echo number_format($payout['amount'], 0); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($payout['method_name']); ?><br>
                                        <small class="text-muted">
                                            <?php 
                                            if ($method_details) {
                                                echo $method_details['account_number'] ?? $method_details['account_name'] ?? 'N/A';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($payout['created_at'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payout['status']; ?>">
                                            <?php echo ucfirst($payout['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($payout['notes'] ?? '-'); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Payment Method Modal -->
    <div class="modal fade" id="addPaymentMethodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="paymentMethodForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Payment Method</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Method Name</label>
                            <input type="text" name="method_name" class="form-control" required placeholder="e.g., Personal Bank Account, MTN Mobile Money">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Type</label>
                            <select name="account_type" class="form-select" id="accountType" required>
                                <option value="">Select Type</option>
                                <option value="bank">Bank Account</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        
                        <div id="bankFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="e.g., Bank of Kigali">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account Name</label>
                                <input type="text" name="account_name" class="form-control" placeholder="Account holder name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" class="form-control" placeholder="e.g., 0012345678">
                            </div>
                        </div>
                        
                        <div id="mobileMoneyFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Mobile Network</label>
                                <select name="mobile_network" class="form-select">
                                    <option value="mtn">MTN Rwanda</option>
                                    <option value="airtel">Airtel Rwanda</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account Name</label>
                                <input type="text" name="account_name" class="form-control" placeholder="Your name as registered">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="account_number" class="form-control" placeholder="e.g., 0788123456">
                            </div>
                        </div>
                        
                        <div id="paypalFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">PayPal Email</label>
                                <input type="email" name="account_name" class="form-control" placeholder="your.email@example.com">
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1">
                            <label class="form-check-label" for="is_default">
                                Set as default payment method
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_payment_method" class="btn btn-primary">Add Payment Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
        
        // Toggle account type fields
        document.getElementById('accountType').addEventListener('change', function() {
            const type = this.value;
            document.getElementById('bankFields').style.display = 'none';
            document.getElementById('mobileMoneyFields').style.display = 'none';
            document.getElementById('paypalFields').style.display = 'none';
            
            if (type === 'bank') {
                document.getElementById('bankFields').style.display = 'block';
            } else if (type === 'mobile_money') {
                document.getElementById('mobileMoneyFields').style.display = 'block';
            } else if (type === 'paypal') {
                document.getElementById('paypalFields').style.display = 'block';
            }
        });
        
        // Earnings Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('earningsChart').getContext('2d');
            
            // Prepare data from PHP
            const monthlyData = <?php echo json_encode($monthly_earnings); ?>;
            
            // Get last 6 months
            const months = [];
            const earnings = [];
            const bookings = [];
            
            for (let i = 5; i >= 0; i--) {
                const date = new Date();
                date.setMonth(date.getMonth() - i);
                const monthKey = date.toISOString().slice(0, 7);
                const monthName = date.toLocaleString('default', { month: 'short', year: '2-digit' });
                
                months.push(monthName);
                
                // Find data for this month
                const monthData = monthlyData.find(m => m.month === monthKey);
                earnings.push(monthData ? parseFloat(monthData.total_earnings) : 0);
                bookings.push(monthData ? parseInt(monthData.total_bookings) : 0);
            }
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Earnings (RWF)',
                        data: earnings,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Completed Bookings',
                        data: bookings,
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Earnings (RWF)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'RWF ' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Bookings'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Earnings')) {
                                        return label + ': RWF ' + context.parsed.y.toLocaleString();
                                    } else {
                                        return label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                }
            });
        });
        
        // Payment method form validation
        document.getElementById('paymentMethodForm').addEventListener('submit', function(e) {
            const accountType = this.querySelector('#accountType').value;
            const accountNumber = this.querySelector('[name="account_number"]')?.value || '';
            const accountName = this.querySelector('[name="account_name"]')?.value || '';
            
            if (!accountType) {
                e.preventDefault();
                alert('Please select an account type');
                return false;
            }
            
            if ((accountType === 'bank' || accountType === 'mobile_money') && (!accountNumber || !accountName)) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            if (accountType === 'paypal' && !accountName.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid PayPal email address');
                return false;
            }
            
            return true;
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Update available balance removed (withdrawals unsupported)
        
        // Copy account number on click
        document.querySelectorAll('.method-detail-value').forEach(element => {
            element.style.cursor = 'pointer';
            element.addEventListener('click', function() {
                const text = this.textContent.trim();
                navigator.clipboard.writeText(text).then(() => {
                    const original = this.textContent;
                    this.textContent = 'Copied!';
                    setTimeout(() => {
                        this.textContent = original;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>