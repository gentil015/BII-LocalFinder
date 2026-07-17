<?php
/**
 * Payment Verification API Endpoint
 * Verifies payment status for a pending transaction.
 */
session_start();
require_once '../config/database.php';
require_once '../payments/PaymentProcessor.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$paymentId = isset($input['payment_id']) ? intval($input['payment_id']) : null;
$transactionId = isset($input['transaction_id']) ? trim($input['transaction_id']) : null;

if (!$paymentId && !$transactionId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment ID or transaction ID is required'
    ]);
    exit;
}

try {
    $processor = new PaymentProcessor();
    if ($paymentId) {
        $result = $processor->verifyPayment($paymentId);
    } else {
        $result = $processor->verifyPaymentByTransactionId($transactionId);
    }

    echo json_encode($result);
} catch (Exception $e) {
    error_log('Payment verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
