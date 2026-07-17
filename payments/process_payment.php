<?php
/**
 * Payment Processing API Endpoint
 * Handles payment processing requests from the frontend
 */

session_start();
require_once '../config/database.php';
require_once '../payments/PaymentProcessor.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$paymentId = $input['payment_id'] ?? null;
$gateway = $input['gateway'] ?? 'fake_momo'; // Default gateway
$phoneNumber = $input['phone_number'] ?? null;

if (!$paymentId || !is_numeric($paymentId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid payment ID required'
    ]);
    exit;
}

try {
    $processor = new PaymentProcessor();
    $result = $processor->processPayment(
        (int)$paymentId,
        $gateway,
        $phoneNumber
    );

    // Log the payment attempt
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent)
        VALUES (?, 'payment_attempt', ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'Payment ' . ($result['success'] ? 'initiated' : 'failed') . ' for payment ID: ' . $paymentId . ' via ' . $gateway,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    echo json_encode($result);

} catch (Exception $e) {
    error_log('Payment API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>