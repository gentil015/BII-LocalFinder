<?php
/**
 * Booking Details Page
 * Shows booking information with dynamic payment logic based on booking mode
 * Supports both request approval and instant booking modes
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/client_header.php';
require_once '../payments/PaymentManager.php';
require_once '../controllers/pages/client/ClientBookingDetailsController.php';

if (!isLoggedIn() || isProvider()) {
    redirect('../login.php');
}

$db = Database::getInstance()->getConnection();
$payment_manager = new PaymentManager();
$controller = new ClientBookingDetailsController();

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$viewModel = $controller->index($db, $_SESSION['user_id'], $booking_id, $payment_manager);

$booking = $viewModel['booking'];
$payment = $viewModel['payment'];
$error = $viewModel['error'];
$success = $viewModel['success'];
$booking_mode = $viewModel['booking_mode'] ?? 'request_approval';

// Helper function to determine payment button visibility
function shouldShowPayButton($booking_mode, $booking_status, $payment_status) {
    // Show pay button when:
    // - booking is pending AND payment pending (request approval or negotiable instant)
    // - booking is confirmed AND payment pending (in request approval: provider accepted)
    return $payment_status === 'pending' && ($booking_status === 'pending' || $booking_status === 'confirmed');
}

// Helper function to get payment button label
function getPaymentButtonLabel($booking_mode) {
    return $booking_mode === 'instant'
        ? 'Confirm & Pay to Secure'
        : 'Pay Now';
}

// Helper function to get main status message
function getStatusMessage($booking_mode, $booking_status, $payment_status) {
    // Completed bookings
    if ($booking_status === 'completed') {
        return [
            'title' => 'Service Completed',
            'message' => 'The service has been completed. Thank you!',
            'icon' => 'fa-thumbs-up',
            'badge_class' => 'badge-success'
        ];
    }
    
    // Cancelled bookings
    if ($booking_status === 'cancelled') {
        return [
            'title' => 'Booking Cancelled',
            'message' => 'This booking has been cancelled',
            'icon' => 'fa-times-circle',
            'badge_class' => 'badge-danger'
        ];
    }
    
    // Confirmed with pending payment (provider accepted, awaiting payment)
    if ($booking_status === 'confirmed' && $payment_status === 'pending') {
        return [
            'title' => 'Accepted',
            'message' => 'Provider accepted your request - Please complete payment to confirm',
            'icon' => 'fa-check-circle',
            'badge_class' => 'badge-info'
        ];
    }
    
    // Confirmed bookings (payment successful or instant booking)
    if ($booking_status === 'confirmed' && $payment_status === 'completed') {
        return [
            'title' => 'Service Scheduled',
            'message' => 'Your service is confirmed and ready to go',
            'icon' => 'fa-check-circle',
            'badge_class' => 'badge-success'
        ];
    }
    
    // Pending bookings (waiting for payment)
    if ($booking_status === 'pending' && $payment_status === 'pending') {
        if ($booking_mode === 'instant') {
            return [
                'title' => 'Ready to Pay',
                'message' => 'Confirm and pay to secure this service immediately',
                'icon' => 'fa-bolt',
                'badge_class' => 'badge-info'
            ];
        } else {
            return [
                'title' => 'Awaiting Your Confirmation',
                'message' => 'Review the details and proceed with payment to book',
                'icon' => 'fa-hourglass-half',
                'badge_class' => 'badge-warning'
            ];
        }
    }
    
    // Fallback for any other state combinations
    return [
        'title' => 'Booking Pending',
        'message' => 'Your booking is being processed',
        'icon' => 'fa-clock',
        'badge_class' => 'badge-secondary'
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - <?php echo htmlspecialchars($booking ? 'Booking #' . $booking['id'] : 'Loading'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --momo-color: #fbbf24;
            --airtel-color: #ef4444;
            --card-color: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f9fafb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* SIDEBAR STYLES */
        .page-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 30px 0;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }

        .sidebar-section {
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .sidebar-section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            margin-bottom: 12px;
        }

        .sidebar-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar-item i {
            width: 20px;
            text-align: center;
        }

        .sidebar-badge {
            margin-left: auto;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
        }

        /* MODAL STYLES */
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .payment-modal.active {
            display: flex;
        }

        .payment-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-close-btn:hover {
            color: #1f2937;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .payment-methods-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-method-card {
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .payment-method-card:hover {
            border-color: var(--primary-color);
            background-color: #f0f9ff;
            transform: translateY(-4px);
        }

        .payment-method-card.active {
            border-color: var(--primary-color);
            background-color: #eff6ff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .payment-method-icon {
            font-size: 32px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background-color: #f3f4f6;
            transition: all 0.3s ease;
        }

        .payment-method-card.active .payment-method-icon {
            transform: scale(1.1);
        }

        .payment-method-name {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .payment-method-desc {
            font-size: 11px;
            color: #6b7280;
        }

        .momo-method .payment-method-icon {
            color: white;
            background-color: var(--momo-color);
        }

        .airtel-method .payment-method-icon {
            color: white;
            background-color: var(--airtel-color);
        }

        .card-method .payment-method-icon {
            color: white;
            background-color: var(--card-color);
        }

        /* PAYMENT FORM STYLES */
        .payment-form {
            display: none;
        }

        .payment-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .phone-input-wrapper {
            display: flex;
            gap: 8px;
        }

        .phone-country-code {
            flex-shrink: 0;
            padding: 12px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background-color: #f9fafb;
            color: #1f2937;
            font-weight: 600;
        }

        .phone-number-input {
            flex: 1;
        }

        .field-hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
        }

        .payment-info-box {
            background-color: #f9fafb;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .payment-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .payment-info-row:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-weight: 600;
            color: #1f2937;
        }

        .payment-info-label {
            color: #6b7280;
        }

        .payment-info-value {
            color: #1f2937;
            font-weight: 500;
        }

        /* STATUS MESSAGES */
        .status-message {
            display: none;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }

        .status-message.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .status-message.pending {
            background-color: #fef3c7;
            border-left: 4px solid var(--warning-color);
            color: #92400e;
        }

        .status-message.success {
            background-color: #ecfdf5;
            border-left: 4px solid var(--success-color);
            color: #065f46;
        }

        .status-message.error {
            background-color: #fee2e2;
            border-left: 4px solid var(--danger-color);
            color: #991b1b;
        }

        .status-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .status-text {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .status-subtext {
            font-size: 12px;
            opacity: 0.8;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
        }

        .btn-primary-modal {
            flex: 1;
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary-modal:hover:not(:disabled) {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-primary-modal:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary-modal {
            flex: 1;
            padding: 12px 20px;
            background-color: #e5e7eb;
            color: #374151;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary-modal:hover {
            background-color: #d1d5db;
        }

        .booking-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .booking-header {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 30px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }

        .booking-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .booking-ref {
            font-size: 14px;
            opacity: 0.9;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 15px;
        }

        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .badge-info {
            background-color: var(--info-color);
            color: white;
        }

        .badge-success {
            background-color: var(--success-color);
            color: white;
        }

        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .badge-secondary {
            background-color: #6b7280;
            color: white;
        }

        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .booking-body {
            background: white;
            padding: 30px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 500;
        }

        .provider-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 8px;
        }

        .provider-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .provider-info {
            flex: 1;
        }

        .provider-name {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 3px;
        }

        .provider-profession {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .provider-rating {
            font-size: 12px;
            color: #f59e0b;
        }

        .service-description {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .service-description p {
            margin: 0;
            color: #374151;
            line-height: 1.6;
        }

        .amount-section {
            background: linear-gradient(135deg, #fef3c7, #fef08a);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--warning-color);
        }

        .amount-label {
            font-size: 12px;
            font-weight: 600;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .amount-value {
            font-size: 32px;
            font-weight: 700;
            color: #b45309;
        }

        .amount-currency {
            font-size: 16px;
        }

        .payment-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background-color: #ecfdf5;
            border-radius: 6px;
            margin-top: 10px;
            color: #065f46;
            font-size: 13px;
            font-weight: 500;
        }

        .payment-status.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .payment-status.failed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-pay {
            background-color: var(--success-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
        }

        .btn-pay:hover:not(:disabled) {
            background-color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #d1d5db;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state-icon {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }

            .sidebar-header {
                display: none;
            }

            .sidebar-section {
                padding: 0 10px;
            }

            .sidebar-item {
                justify-content: center;
                padding: 12px;
            }

            .sidebar-item span {
                display: none;
            }

            .sidebar-badge {
                display: none;
            }

            .main-content {
                margin-left: 70px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .booking-header h1 {
                font-size: 22px;
            }

            .amount-value {
                font-size: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .payment-methods-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .booking-header h1 {
                font-size: 22px;
            }

            .amount-value {
                font-size: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .payment-modal-content {
                width: 95%;
            }

            .payment-methods-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<?php client_header_render_styles(); ?>
</head>
<body>
    <?php client_header_render_markup(basename($_SERVER['PHP_SELF'])); ?>
    <div class="page-wrapper">
        <main class="main-content">
    <div class="booking-container">
        <?php if ($error): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3><?php echo htmlspecialchars($error); ?></h3>
                <a href="my-bookings.php" class="btn btn-secondary" style="display: inline-flex; margin-top: 20px;">
                    <i class="fas fa-arrow-left me-2"></i> Back to Bookings
                </a>
            </div>
        <?php else: ?>
            <!-- Header -->
            <div class="booking-header">
                <h1>Booking Details</h1>
                <div class="booking-ref">Reference: #BK-<?php echo htmlspecialchars(str_pad($booking['id'], 5, '0', STR_PAD_LEFT)); ?></div>
                <?php 
                    $status_msg = getStatusMessage($booking_mode, $booking['status'], $booking['payment_status']);
                ?>
                <div class="status-badge <?php echo htmlspecialchars($status_msg['badge_class']); ?>">
                    <i class="fas <?php echo htmlspecialchars($status_msg['icon']); ?> me-2"></i>
                    <?php echo htmlspecialchars($status_msg['title']); ?>
                </div>
            </div>

            <!-- Body -->
            <div class="booking-body">
                <!-- Status Message -->
                <div style="background-color: #f0f9ff; border-left: 4px solid var(--info-color); padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas <?php echo htmlspecialchars($status_msg['icon']); ?>" style="font-size: 20px; color: var(--info-color);"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($status_msg['title']); ?></strong>
                            <div style="font-size: 13px; color: #0c4a6e; margin-top: 3px;">
                                <?php echo htmlspecialchars($status_msg['message']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Mode Badge -->
                <div style="text-align: center; margin-bottom: 20px;">
                    <span class="badge" style="background-color: <?php echo $booking_mode === 'instant' ? '#fbbf24' : '#93c5fd'; ?>; color: <?php echo $booking_mode === 'instant' ? '#78350f' : '#1e3a8a'; ?>; padding: 6px 12px; font-size: 12px;">
                        <i class="fas <?php echo $booking_mode === 'instant' ? 'fa-bolt' : 'fa-hourglass-half'; ?> me-1"></i>
                        <?php echo $booking_mode === 'instant' ? 'Instant Booking' : 'Request Approval'; ?>
                    </span>
                </div>

                <!-- Service Info -->
                <div class="section">
                    <div class="section-title">Service Details</div>
                    <div class="service-description">
                        <p><?php echo htmlspecialchars($booking['service_description']); ?></p>
                    </div>
                    <?php if ($booking['service_name']): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Service</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['service_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Category</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['category_name'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Provider Info -->
                <div class="section">
                    <div class="section-title">Service Provider</div>
                    <div class="provider-card">
                        <div class="provider-avatar">
                            <?php if (!empty($booking['provider_image'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($booking['provider_image']); ?>" alt="<?php echo htmlspecialchars($booking['provider_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($booking['provider_name'] ?? '?', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="provider-info">
                            <div class="provider-name"><?php echo htmlspecialchars($booking['provider_name'] ?? 'Provider'); ?></div>
                            <div class="provider-profession"><?php echo htmlspecialchars($booking['profession'] ?? ''); ?></div>
                            <?php if ($booking['average_rating']): ?>
                                <div class="provider-rating">
                                    <i class="fas fa-star me-1"></i>
                                    <?php echo number_format($booking['average_rating'], 1); ?> 
                                    (<?php echo htmlspecialchars($booking['total_reviews']); ?> reviews)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Date & Time -->
                <div class="section">
                    <div class="section-title">Schedule</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Preferred Date</span>
                            <span class="info-value">
                                <?php 
                                    $date = strtotime($booking['preferred_date']);
                                    echo $date ? date('M d, Y', $date) : 'Not specified';
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Preferred Time</span>
                            <span class="info-value">
                                <?php 
                                    echo !empty($booking['preferred_time']) 
                                        ? date('H:i', strtotime($booking['preferred_time']))
                                        : 'Not specified';
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Location</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['location'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Booked On</span>
                            <span class="info-value"><?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <?php if ($booking['amount'] > 0): ?>
                    <div class="section">
                        <div class="amount-section">
                            <div class="amount-label">Total Amount</div>
                            <div class="amount-value">
                                <span class="amount-currency">₦</span><?php echo number_format($booking['amount'], 0); ?>
                            </div>
                            <div class="payment-status <?php echo $booking['payment_status']; ?>">
                                <i class="fas <?php echo $booking['payment_status'] === 'completed' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                Payment Status: <strong><?php echo ucfirst($booking['payment_status']); ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Payment Messages & Actions -->
                <div class="section">
                    <?php
                        $show_pay_button = shouldShowPayButton($booking_mode, $booking['status'], $booking['payment_status']);
                        $payment_button_label = getPaymentButtonLabel($booking_mode);
                    ?>

                    <?php if ($show_pay_button && !$payment): ?>
                        <?php
                        // Try to create missing payment record
                        $payment_created = false;
                        $creation_error = null;
                        if ($booking['amount'] > 0) {
                            $result = $payment_manager->createPaymentForBooking($booking_id);
                            if (is_numeric($result) && $result > 0) {
                                $payment_created = true;
                                $payment = $payment_manager->getPaymentForBooking($booking_id);
                            } else {
                                $creation_error = $result;
                            }
                        } else {
                            $creation_error = 'no_amount';
                        }
                        ?>

                        <?php if ($payment_created && $payment): ?>
                            <!-- Payment record was created successfully, continue with payment button -->
                            <div class="action-buttons">
                                <button
                                    type="button"
                                    class="btn-pay"
                                    onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)"
                                    id="payButton">
                                    <i class="fas fa-credit-card"></i>
                                    <?php echo htmlspecialchars($payment_button_label); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Could not create payment record - show specific error -->
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <div>
                                    <?php 
                                    $error_msg = "Unable to process payment. Please contact support.";
                                    if ($creation_error === 'no_amount') {
                                        $error_msg = "This booking has no amount specified. Please review your booking details.";
                                    } elseif ($creation_error === 'booking_not_found') {
                                        $error_msg = "Booking details could not be found. Please refresh and try again.";
                                    } elseif ($creation_error === 'payment_exists') {
                                        $error_msg = "Payment record already exists. Please reload the page.";
                                    } elseif ($creation_error === 'no_gateway') {
                                        $error_msg = "Payment gateway is not configured. Please contact support.";
                                    } elseif ($creation_error === 'processor_failed') {
                                        $error_msg = "Payment processor error. Please try again later.";
                                    } elseif (strpos($creation_error, 'exception_') === 0) {
                                        $error_msg = "An unexpected error occurred. Please refresh the page and try again.";
                                    }
                                    echo htmlspecialchars($error_msg);
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($show_pay_button && $payment): ?>
                        <div class="action-buttons">
                            <button 
                                type="button" 
                                class="btn-pay" 
                                id="payButton"
                                onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)"
                            >
                                <i class="fas fa-credit-card"></i>
                                <span id="payButtonText"><?php echo htmlspecialchars($payment_button_label); ?></span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($booking['status'] === 'completed' && !$payment): ?>
                        <div class="action-buttons">
                            <a href="write-review.php?provider_id=<?php echo $booking['provider_id']; ?>&booking_id=<?php echo $booking['id']; ?>" class="btn-pay" style="text-decoration: none; background-color: var(--primary-color);">
                                <i class="fas fa-star"></i>
                                Leave Review
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Back to Bookings Button -->
                    <div class="action-buttons" style="margin-top: 10px;">
                        <a href="my-bookings.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to All Bookings
                        </a>
                    </div>
                </div>

                <!-- Info Message for Completed Payments -->
                <?php if ($booking['payment_status'] === 'completed' && $booking['status'] === 'confirmed'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Payment Confirmed!</strong> Your service is scheduled. You'll receive updates from the provider.
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </div>
        </main>
    </div>

    <!-- PAYMENT MODAL -->
    <div class="payment-modal" id="paymentModal">
        <div class="payment-modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h2>Select Payment Method</h2>
                <button class="modal-close-btn" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Step 1: Gateway Selection -->
                <div id="gatewaySelectionForm">
                    <div class="payment-info-box">
                        <div class="payment-info-row">
                            <span class="payment-info-label">Amount to Pay</span>
                            <span class="payment-info-value">₦<?php echo isset($booking) && $booking['amount'] ? number_format($booking['amount'], 0) : '0'; ?></span>
                        </div>
                    </div>

                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #1f2937;">Choose Payment Gateway</h3>

                    <div class="payment-methods-grid">
                        <!-- MTN MoMo -->
                        <div class="payment-method-card momo-method" onclick="selectPaymentGateway('mtn', 'MTN MoMo')">
                            <div class="payment-method-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="payment-method-name">MTN MoMo</div>
                            <div class="payment-method-desc">Fast & Secure</div>
                        </div>

                        <!-- Airtel Money -->
                        <div class="payment-method-card airtel-method" onclick="selectPaymentGateway('airtel', 'Airtel Money')">
                            <div class="payment-method-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="payment-method-name">Airtel Money</div>
                            <div class="payment-method-desc">Quick & Easy</div>
                        </div>

                        <!-- Card Payment -->
                        <div class="payment-method-card card-method" onclick="selectPaymentGateway('card', 'Card Payment')">
                            <div class="payment-method-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="payment-method-name">Card Payment</div>
                            <div class="payment-method-desc">Visa & Mastercard</div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Mobile Money Payment Form -->
                <div id="mobilemoneyPaymentForm" class="payment-form">
                    <div id="mobilemoneyStatusMessage" class="status-message"></div>

                    <div class="payment-info-box">
                        <div class="payment-info-row">
                            <span class="payment-info-label">Gateway</span>
                            <span class="payment-info-value" id="selectedGatewayName">MTN MoMo</span>
                        </div>
                        <div class="payment-info-row">
                            <span class="payment-info-label">Amount</span>
                            <span class="payment-info-value">₦<?php echo isset($booking) && $booking['amount'] ? number_format($booking['amount'], 0) : '0'; ?></span>
                        </div>
                    </div>

                    <form id="mobilemoneyForm" onsubmit="submitMobilemoneyPayment(event)">
                        <div class="form-group">
                            <label class="form-label">Mobile Money Phone Number</label>
                            <div class="phone-input-wrapper">
                                <div class="phone-country-code">+250</div>
                                <input 
                                    type="tel" 
                                    class="form-input phone-number-input" 
                                    id="phoneNumber"
                                    placeholder="781234567"
                                    required
                                    pattern="[0-9]{9}"
                                >
                            </div>
                            <div class="field-hint">Enter 9-digit phone number (without country code)</div>
                        </div>
                    </form>
                </div>

                <!-- Step 3: Verification Waiting -->
                <div id="verificationWaitingForm" class="payment-form">
                    <div class="status-message pending active">
                        <div class="status-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="status-text">Check Your Phone</div>
                        <div class="status-subtext">You'll receive a payment prompt on your phone at<br><span id="verificationPhoneDisplay">250781234567</span></div>
                    </div>

                    <div class="payment-info-box">
                        <div style="text-align: center; padding: 20px 0;">
                            <div style="font-size: 24px; margin-bottom: 10px;"><span class="spinner"></span></div>
                            <div style="font-size: 14px; color: #6b7280;">Waiting for your confirmation...</div>
                            <div style="font-size: 12px; color: #9ca3af; margin-top: 10px;">This may take up to 2 minutes</div>
                        </div>
                    </div>

                    <div style="background-color: #f0f9ff; border-left: 4px solid var(--info-color); padding: 12px; border-radius: 6px; font-size: 12px; color: #0c4a6e; line-height: 1.6;">
                        <strong>What to do:</strong><br>
                        • Look for payment prompt on your phone<br>
                        • Enter your PIN when asked<br>
                        • Wait for confirmation
                    </div>
                </div>

                <!-- Step 4: Success/Error -->
                <div id="paymentResultForm" class="payment-form">
                    <div id="paymentResultMessage" class="status-message"></div>
                    <div class="payment-info-box">
                        <div class="payment-info-row">
                            <span class="payment-info-label">Transaction ID</span>
                            <span class="payment-info-value" id="transactionIdDisplay">-</span>
                        </div>
                        <div class="payment-info-row">
                            <span class="payment-info-label">Status</span>
                            <span class="payment-info-value" id="statusDisplay">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn-secondary-modal" onclick="closePaymentModal()" id="footerCloseBtn">
                    <i class="fas fa-times"></i> Close
                </button>
                <button 
                    type="button" 
                    class="btn-primary-modal" 
                    id="footerActionBtn"
                    onclick="handleModalAction()"
                    style="display: none;">
                    <i class="fas fa-arrow-right"></i> <span id="actionBtnText">Continue</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment Modal State
        let paymentState = {
            paymentId: null,
            gateway: null,
            gatewayName: null,
            phoneNumber: null,
            transactionId: null,
            verificationAttempts: 0,
            maxVerificationAttempts: 60 // 2 minutes with 2-second intervals
        };

        /**
         * Open payment modal
         */
        function openPaymentModal(paymentData) {
            paymentState.paymentId = paymentData.id;
            document.getElementById('paymentModal').classList.add('active');
            resetPaymentForm();
        }

        /**
         * Close payment modal
         */
        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
            resetPaymentForm();
        }

        /**
         * Reset payment form to initial state
         */
        function resetPaymentForm() {
            paymentState = {
                ...paymentState,
                gateway: null,
                gatewayName: null,
                phoneNumber: null,
                transactionId: null,
                verificationAttempts: 0
            };
            
            // Show gateway selection
            document.getElementById('gatewaySelectionForm').style.display = 'block';
            document.getElementById('mobilemoneyPaymentForm').style.display = 'none';
            document.getElementById('verificationWaitingForm').style.display = 'none';
            document.getElementById('paymentResultForm').style.display = 'none';
            document.getElementById('footerActionBtn').style.display = 'none';
        }

        /**
         * Select payment gateway
         */
        function selectPaymentGateway(gateway, gatewayName) {
            paymentState.gateway = gateway;
            paymentState.gatewayName = gatewayName;

            // Update UI
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            // Show appropriate form based on gateway
            if (gateway === 'card') {
                // For card, redirect to card payment
                showCardPaymentForm();
            } else if (gateway === 'mtn' || gateway === 'airtel') {
                // Show mobile money form
                showMobilemmoneyForm(gatewayName);
            }
        }

        /**
         * Show mobile money payment form
         */
        function showMobilemmoneyForm(gatewayName) {
            document.getElementById('gatewaySelectionForm').style.display = 'none';
            document.getElementById('mobilemoneyPaymentForm').style.display = 'block';
            document.getElementById('selectedGatewayName').textContent = gatewayName;
            document.getElementById('footerActionBtn').style.display = 'flex';
            document.getElementById('actionBtnText').textContent = 'Proceed to Pay';
            document.getElementById('mobilemoneyStatusMessage').classList.remove('active');
        }

        /**
         * Show card payment form
         */
        function showCardPaymentForm() {
            // For card payment, we can redirect or open a card payment interface
            // For now, let's show a message
            document.getElementById('gatewaySelectionForm').style.display = 'none';
            
            const statusMsg = document.createElement('div');
            statusMsg.className = 'status-message pending active';
            statusMsg.innerHTML = `
                <div class="status-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="status-text">Card Payment</div>
                <div class="status-subtext">Redirecting to secure payment gateway...</div>
            `;
            
            document.getElementById('mobilemoneyPaymentForm').innerHTML = '';
            document.getElementById('mobilemoneyPaymentForm').appendChild(statusMsg);
            document.getElementById('mobilemoneyPaymentForm').style.display = 'block';
            document.getElementById('footerActionBtn').style.display = 'none';

            // Simulate redirect to card payment
            setTimeout(() => {
                closePaymentModal();
                alert('Card payment gateway integration coming soon. Use MTN MoMo or Airtel Money for now.');
            }, 2000);
        }

        /**
         * Submit mobile money payment
         */
        async function submitMobilemoneyPayment(event) {
            event.preventDefault();
            
            const phoneInput = document.getElementById('phoneNumber');
            let phone = phoneInput.value.trim();

            if (!phone || phone.length !== 9) {
                showMobilemoneyStatus('error', 'Invalid phone number', 'Please enter a valid 9-digit phone number');
                return;
            }

            paymentState.phoneNumber = '+250' + phone;

            // Show verification waiting form
            document.getElementById('mobilemoneyPaymentForm').style.display = 'none';
            document.getElementById('verificationWaitingForm').style.display = 'block';
            document.getElementById('verificationPhoneDisplay').textContent = paymentState.phoneNumber;
            document.getElementById('footerActionBtn').style.display = 'none';

            try {
                // Step 1: Initiate payment
                const payResponse = await fetch('../payments/process_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        payment_id: paymentState.paymentId,
                        gateway: paymentState.gateway,
                        phone_number: paymentState.phoneNumber
                    })
                });

                const payResult = await payResponse.json();

                if (!payResult.success) {
                    showPaymentResult('error', 'Payment initiation failed', payResult.message);
                    return;
                }

                paymentState.transactionId = payResult.transaction_id;

                // Step 2: Verify payment with polling
                verifyPaymentStatus();

            } catch (error) {
                console.error('Payment error:', error);
                showPaymentResult('error', 'Payment Error', 'An error occurred while processing your payment');
            }
        }

        /**
         * Verify payment status with polling
         */
        async function verifyPaymentStatus() {
            if (paymentState.verificationAttempts >= paymentState.maxVerificationAttempts) {
                showPaymentResult('error', 'Verification Timeout', 'Payment verification timed out. Please check your account or try again.');
                return;
            }

            paymentState.verificationAttempts++;

            try {
                const verifyResponse = await fetch('../payments/verify_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        payment_id: paymentState.paymentId,
                        transaction_id: paymentState.transactionId
                    })
                });

                const verifyResult = await verifyResponse.json();

                if (verifyResult.success && verifyResult.verified) {
                    // Payment successful
                    showPaymentResult('success', 'Payment Successful', 'Your payment has been confirmed. Service is now scheduled.');
                    
                    // Update page after 3 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else if (verifyResult.status === 'pending') {
                    // Still pending, continue polling
                    setTimeout(verifyPaymentStatus, 2000);
                } else {
                    // Payment failed
                    showPaymentResult('error', 'Payment Failed', verifyResult.message || 'Payment could not be completed');
                }
            } catch (error) {
                console.error('Verification error:', error);
                // Retry on error
                setTimeout(verifyPaymentStatus, 2000);
            }
        }

        /**
         * Show mobile money status message
         */
        function showMobilemoneyStatus(type, title, message) {
            const statusDiv = document.getElementById('mobilemoneyStatusMessage');
            statusDiv.className = 'status-message active ' + type;
            statusDiv.innerHTML = `
                <div class="status-icon">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                </div>
                <div class="status-text">${title}</div>
                <div class="status-subtext">${message}</div>
            `;
        }

        /**
         * Show payment result
         */
        function showPaymentResult(type, title, message) {
            document.getElementById('verificationWaitingForm').style.display = 'none';
            document.getElementById('paymentResultForm').style.display = 'block';
            
            const resultDiv = document.getElementById('paymentResultMessage');
            resultDiv.className = 'status-message active ' + type;
            resultDiv.innerHTML = `
                <div class="status-icon">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                </div>
                <div class="status-text">${title}</div>
                <div class="status-subtext">${message}</div>
            `;

            document.getElementById('transactionIdDisplay').textContent = paymentState.transactionId || '-';
            document.getElementById('statusDisplay').textContent = type === 'success' ? 'Completed' : 'Failed';
            document.getElementById('footerActionBtn').style.display = 'flex';
            document.getElementById('actionBtnText').textContent = type === 'success' ? 'Done' : 'Try Again';
        }

        /**
         * Handle modal footer action button
         */
        async function handleModalAction() {
            const activeForm = document.querySelector('.payment-form:not([style*="display: none"])');
            
            if (activeForm && activeForm.id === 'mobilemoneyPaymentForm') {
                const form = document.getElementById('mobilemoneyForm');
                if (form) {
                    submitMobilemoneyPayment(new Event('submit'));
                }
            } else if (activeForm && activeForm.id === 'paymentResultForm') {
                const statusDiv = document.getElementById('paymentResultMessage');
                if (statusDiv.classList.contains('error')) {
                    resetPaymentForm();
                } else {
                    closePaymentModal();
                }
            }
        }

        // Close modal when clicking outside
        document.getElementById('paymentModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closePaymentModal();
            }
        });

        // Handle ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePaymentModal();
            }
        });
    </script>
<?php client_header_render_scripts(); ?>
</body>
</html>
