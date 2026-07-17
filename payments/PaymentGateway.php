<?php
/**
 * Payment Gateway Interface
 * Defines the contract for all payment gateway implementations
 */
interface PaymentGateway
{
    /**
     * Process a payment
     *
     * @param array $paymentData Payment data including amount, currency, etc.
     * @return array Result with success status, transaction_id, and any additional data
     */
    public function pay(array $paymentData): array;

    /**
     * Verify a payment status
     *
     * @param string $transactionId Transaction ID to verify
     * @return array Verification result with status and details
     */
    public function verify(string $transactionId): array;

    /**
     * Get the gateway name
     *
     * @return string Gateway identifier
     */
    public function getName(): string;

    /**
     * Check if gateway is available/configured
     *
     * @return bool True if gateway can be used
     */
    public function isAvailable(): bool;
}
?>