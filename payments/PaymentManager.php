<?php
require_once '../config/database.php';
require_once 'PaymentProcessor.php';
require_once 'PaymentGatewayFactory.php';

/**
 * Payment Manager
 * Handles payment creation and integration with booking system
 */
class PaymentManager
{
    private PDO $db;
    private PaymentProcessor $processor;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->processor = new PaymentProcessor();
    }

    /**
     * Create payment record when booking is created
     * Returns payment ID on success, or error message string on failure
     *
     * @param int $bookingId Booking ID
     * @return int|string Payment ID on success, error message on failure
     */
    public function createPaymentForBooking(int $bookingId)
    {
        try {
            // Get booking details
            $booking = $this->getBookingDetails($bookingId);
            if (!$booking) {
                error_log("Payment creation failed: Booking $bookingId not found");
                return 'booking_not_found';
            }

            // Skip if booking has no amount or amount is 0
            if (!$booking['amount'] || $booking['amount'] <= 0) {
                error_log("Payment creation skipped: Booking $bookingId has no amount (amount: {$booking['amount']})");
                return 'no_amount';
            }

            // Check if payment already exists for this booking
            if ($this->paymentExistsForBooking($bookingId)) {
                error_log("Payment creation skipped: Payment already exists for booking $bookingId");
                return 'payment_exists';
            }

            // Get default gateway
            $defaultGateway = $this->getDefaultGateway();
            if (!$defaultGateway) {
                error_log("Payment creation failed: No default gateway configured");
                return 'no_gateway';
            }

            // Create payment data
            $paymentData = [
                'booking_id' => $bookingId,
                'user_id' => $booking['client_id'],
                'provider_id' => $booking['provider_id'],
                'amount' => $booking['amount'],
                'currency' => 'RWF', // Default currency
                'payment_method' => null, // To be set by user
                'payment_provider' => $defaultGateway,
                'metadata' => [
                    'booking_created_at' => $booking['created_at'],
                    'service_description' => $booking['service_description']
                ]
            ];

            $payment_id = $this->processor->createPayment($paymentData);
            
            if (!$payment_id) {
                error_log("Payment creation failed: Processor returned null for booking $bookingId");
                return 'processor_failed';
            }

            return $payment_id;

        } catch (Exception $e) {
            error_log('Payment creation for booking error: ' . $e->getMessage());
            return 'exception_' . $e->getCode();
        }
    }

    /**
     * Prepare or update payment record before processing
     *
     * @param int $bookingId Booking ID
     * @param string|null $paymentProvider Selected gateway provider
     * @param string|null $paymentMethod Selected payment method label
     * @param array $metadata Additional metadata such as phone number
     * @return int|string Payment ID on success, error message on failure
     */
    public function preparePaymentForBooking(int $bookingId, ?string $paymentProvider = null, ?string $paymentMethod = null, array $metadata = [])
    {
        try {
            $booking = $this->getBookingDetails($bookingId);
            if (!$booking) {
                error_log("Payment preparation failed: Booking $bookingId not found");
                return 'booking_not_found';
            }

            if (!$booking['amount'] || $booking['amount'] <= 0) {
                error_log("Payment preparation skipped: Booking $bookingId has no amount (amount: {$booking['amount']})");
                return 'no_amount';
            }

            $paymentProvider = $paymentProvider ?: $this->getDefaultGateway();
            if (!PaymentGatewayFactory::isGatewayAvailable($paymentProvider)) {
                error_log("Payment preparation failed: Gateway $paymentProvider not available");
                return 'invalid_gateway';
            }

            $paymentMethod = $paymentMethod ?: $paymentProvider;
            $payment = $this->getPaymentForBooking($bookingId);
            $metadataPayload = array_merge([
                'booking_created_at' => $booking['created_at'],
                'service_description' => $booking['service_description']
            ], $metadata);

            if ($payment) {
                if ($payment['status'] === 'success') {
                    return 'payment_exists';
                }

                $stmt = $this->db->prepare("UPDATE payments SET payment_provider = ?, payment_method = ?, status = 'pending', transaction_id = NULL, metadata = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$paymentProvider, $paymentMethod, json_encode($metadataPayload), $payment['id']]);
                return $payment['id'];
            }

            $paymentData = [
                'booking_id' => $bookingId,
                'user_id' => $booking['client_id'],
                'provider_id' => $booking['provider_id'],
                'amount' => $booking['amount'],
                'currency' => 'RWF',
                'payment_method' => $paymentMethod,
                'payment_provider' => $paymentProvider,
                'metadata' => $metadataPayload
            ];

            $payment_id = $this->processor->createPayment($paymentData);
            if (!$payment_id) {
                error_log("Payment preparation failed: Processor returned null for booking $bookingId");
                return 'processor_failed';
            }

            return $payment_id;
        } catch (Exception $e) {
            error_log('Payment preparation error: ' . $e->getMessage());
            return 'exception_' . $e->getCode();
        }
    }

    /**
     * Get booking details
     *
     * @param int $bookingId Booking ID
     * @return array|null Booking data
     */
    private function getBookingDetails(int $bookingId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, client_id, provider_id, amount, service_description, created_at
            FROM bookings
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if payment already exists for booking
     *
     * @param int $bookingId Booking ID
     * @return bool True if payment exists
     */
    private function paymentExistsForBooking(int $bookingId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM payments WHERE booking_id = ?
        ");
        $stmt->execute([$bookingId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Get default payment gateway from settings
     *
     * @return string Gateway name
     */
    private function getDefaultGateway(): string
    {
        $stmt = $this->db->prepare("
            SELECT setting_value FROM system_settings
            WHERE setting_key = 'default_gateway'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : 'fake';
    }

    /**
     * Get payment for booking
     *
     * @param int $bookingId Booking ID
     * @return array|null Payment data
     */
    public function getPaymentForBooking(int $bookingId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment && $payment['metadata']) {
            $payment['metadata'] = json_decode($payment['metadata'], true);
        }

        return $payment ?: null;
    }

    /**
     * Check if payments are enabled
     *
     * @return bool True if payments are enabled
     */
    public function isPaymentsEnabled(): bool
    {
        $stmt = $this->db->prepare("
            SELECT setting_value FROM system_settings
            WHERE setting_key = 'payment_enabled'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['setting_value'] == '1';
    }

    /**
     * Get payment statistics
     *
     * @return array Payment statistics
     */
    public function getPaymentStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_amount
            FROM payments
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>