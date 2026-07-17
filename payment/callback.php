<?php
/**
 * Payment Callback Handler
 * Handles payment gateway callbacks (webhooks)
 * In production, this would receive callbacks from MTN/Airtel
 */
session_start();
require_once '../config/database.php';
require_once '../includes/subscription_access.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get callback data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log callback for debugging
error_log("Payment callback received: " . print_r($data, true));

// Validate callback signature in production
// $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// For demo/simulation, accept test callbacks
$transaction_ref = $data['transaction_ref'] ?? $data['transaction_id'] ?? '';
$status = $data['status'] ?? 'pending';
$amount = $data['amount'] ?? 0;

if (empty($transaction_ref)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing transaction reference']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Find the payment by transaction reference
    $stmt = $db->prepare("SELECT * FROM subscription_payments WHERE transaction_ref = ? AND status = 'pending'");
    $stmt->execute([$transaction_ref]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        // For demo: create a new pending payment if not found
        // In production, this would be handled differently
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    // Update payment status
    $new_status = ($status === 'success' || $status === 'paid') ? 'paid' : 'failed';
    $stmt = $db->prepare("UPDATE subscription_payments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $payment['id']]);
    
    if ($new_status === 'paid' && $payment['subscription_id']) {
        // Activate subscription if not already done
        // (Usually done during checkout, but this is a fallback)
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Payment processed successfully',
            'payment_id' => $payment['id'],
            'status' => $new_status
        ]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Callback processed',
        'payment_id' => $payment['id'],
        'status' => $new_status
    ]);
    
} catch (Exception $e) {
    error_log("Payment callback error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}