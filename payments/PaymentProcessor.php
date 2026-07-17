<?php
require_once '../config/database.php';
require_once 'PaymentGatewayFactory.php';

/**
 * Payment Processor
 * Handles payment processing workflow including loading payments,
 * calling gateways, and updating database records
 */
class PaymentProcessor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Process a payment by ID
     *
     * @param int $paymentId Payment ID to process
     * @param string $gateway Gateway name (optional)
     * @param string $phoneNumber Phone number for mobile money (optional)
     * @return array Processing result
     */
    public function processPayment(int $paymentId, ?string $gateway = null, ?string $phoneNumber = null): array
    {
        try {
            $payment = $this->loadPayment($paymentId);
            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment not found'
                ];
            }

            if ($payment['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'Payment already processed or cannot be retried'
                ];
            }

            // Use provided gateway or fallback to payment provider
            $gatewayName = $gateway ?? ($payment['payment_provider'] ?? 'fake');
            $gateway = PaymentGatewayFactory::create($gatewayName);
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway not available'
                ];
            }

            // Prepare payment data
            $paymentData = [
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'booking_id' => $payment['booking_id'],
                'user_id' => $payment['user_id'],
                'provider_id' => $payment['provider_id'],
                'phone_number' => $phoneNumber ?? ($payment['metadata']['phone_number'] ?? null),
                'payment_method' => $payment['payment_method'] ?? null,
                'customer_phone' => $phoneNumber
            ];

            // Call gateway to initiate payment
            $result = $gateway->pay($paymentData);

            // Update payment record with transaction details
            $this->updatePayment($paymentId, $result, $phoneNumber);

            return $result;
        } catch (Exception $e) {
            error_log('Payment processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Load payment details from database
     *
     * @param int $paymentId Payment ID
     * @return array|null Payment data or null if not found
     */
    private function loadPayment(int $paymentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payments WHERE id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment && !empty($payment['metadata'])) {
            $payment['metadata'] = json_decode($payment['metadata'], true) ?: [];
        }

        return $payment;
    }

    /**
     * Update payment record with gateway response
     *
     * @param int $paymentId Payment ID
     * @param array $gatewayResult Gateway response
     * @param string $phoneNumber Phone number used (optional)
     * @return bool Update success
     */
    private function updatePayment(int $paymentId, array $gatewayResult, ?string $phoneNumber = null): bool
    {
        $transactionId = $gatewayResult['transaction_id'] ?? null;
        $status = isset($gatewayResult['success']) && $gatewayResult['success'] === false
            ? 'failed'
            : 'pending';

        $metadata = $this->mergeMetadata($paymentId, $gatewayResult['gateway_response'] ?? [], $phoneNumber);

        $stmt = $this->db->prepare(" 
            UPDATE payments
            SET status = ?, transaction_id = ?, metadata = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        return $stmt->execute([$status, $transactionId, $metadata, $paymentId]);
    }

    /**
     * Merge new gateway response data into existing payment metadata
     *
     * @param int $paymentId
     * @param array $gatewayResponse
     * @param string $phoneNumber
     * @return string
     */
    private function mergeMetadata(int $paymentId, array $gatewayResponse, ?string $phoneNumber = null): string
    {
        $payment = $this->loadPayment($paymentId);
        $existingMetadata = [];

        if ($payment && $payment['metadata']) {
            $existingMetadata = json_decode($payment['metadata'], true) ?: [];
        }

        $existingMetadata['gateway_response'] = $gatewayResponse;
        
        if ($phoneNumber) {
            $existingMetadata['phone_number'] = $phoneNumber;
        }

        return json_encode($existingMetadata);
    }

    /**
     * Update booking status after successful payment
     *
     * @param int $bookingId Booking ID
     * @param string $status New status
     * @return bool Update success
     */
    private function updateBookingStatus(int $bookingId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bookings
            SET payment_status = 'completed', status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        return $stmt->execute([$status, $bookingId]);
    }

    /**
     * Load payment by transaction ID
     *
     * @param string $transactionId
     * @return array|null
     */
    private function loadPaymentByTransactionId(string $transactionId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Finalize verification and update database state
     *
     * @param int $paymentId
     * @param array $verifyResult
     * @return array
     */
    private function finalizeVerification(int $paymentId, array $verifyResult): array
    {
        $payment = $this->loadPayment($paymentId);

        if (!$payment) {
            return [
                'success' => false,
                'message' => 'Payment not found for verification'
            ];
        }

        $status = isset($verifyResult['success']) && $verifyResult['success'] === true ? 'success' : 'failed';
        $metadata = $this->mergeMetadata($paymentId, $verifyResult['gateway_response'] ?? []);

        $stmt = $this->db->prepare(" 
            UPDATE payments
            SET status = ?, metadata = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$status, $metadata, $paymentId]);

        if ($status === 'success') {
            $this->updateBookingStatus($payment['booking_id'], 'confirmed');
        }

        return $verifyResult;
    }

    /**
     * Verify a payment by transaction id
     *
     * @param string $transactionId
     * @return array Verification result
     */
    public function verifyPaymentByTransactionId(string $transactionId): array
    {
        $payment = $this->loadPaymentByTransactionId($transactionId);
        if (!$payment) {
            return [
                'success' => false,
                'message' => 'Payment not found for the supplied transaction id'
            ];
        }

        return $this->verifyPayment((int)$payment['id']);
    }

    /**
     * Create a new payment record
     *
     * @param array $paymentData Payment data
     * @return int|null Payment ID or null on failure
     */
    public function createPayment(array $paymentData): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    booking_id, user_id, provider_id, amount, currency,
                    payment_method, payment_provider, status, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");

            $metadata = json_encode($paymentData['metadata'] ?? []);

            $result = $stmt->execute([
                $paymentData['booking_id'],
                $paymentData['user_id'],
                $paymentData['provider_id'],
                $paymentData['amount'],
                $paymentData['currency'] ?? 'RWF',
                $paymentData['payment_method'] ?? null,
                $paymentData['payment_provider'] ?? 'fake',
                $metadata
            ]);

            if ($result) {
                return $this->db->lastInsertId();
            }

            return null;

        } catch (Exception $e) {
            error_log('Payment creation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get payment details
     *
     * @param int $paymentId Payment ID
     * @return array|null Payment data
     */
    public function getPayment(int $paymentId): ?array
    {
        $payment = $this->loadPayment($paymentId);
        if ($payment && $payment['metadata']) {
            $payment['metadata'] = json_decode($payment['metadata'], true);
        }
        return $payment;
    }

    /**
     * Verify a payment transaction
     *
     * @param int $paymentId Payment ID
     * @return array Verification result
     */
    public function verifyPayment(int $paymentId): array
    {
        $payment = $this->loadPayment($paymentId);
        if (!$payment) {
            return [
                'success' => false,
                'message' => 'Payment not found'
            ];
        }

        if (!$payment['transaction_id']) {
            return [
                'success' => false,
                'message' => 'No transaction ID found'
            ];
        }

        $gateway = PaymentGatewayFactory::create($payment['payment_provider'] ?? 'fake');
        if (!$gateway) {
            return [
                'success' => false,
                'message' => 'Payment gateway not available'
            ];
        }

        $verifyResult = $gateway->verify($payment['transaction_id']);
        $result = $this->finalizeVerification($paymentId, $verifyResult);
        
        // Add transaction_id to response
        $result['transaction_id'] = $payment['transaction_id'];
        
        return $result;
    }
}
?>