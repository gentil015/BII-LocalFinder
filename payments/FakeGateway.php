<?php
require_once 'PaymentGateway.php';

/**
 * Fake Payment Gateway for testing and development
 * Simulates payment processing without real transactions
 */
class FakeGateway implements PaymentGateway
{
    private string $name = 'fake';

    /**
     * Process a fake payment
     *
     * @param array $paymentData Payment data
     * @return array Result with simulated success
     */
    public function pay(array $paymentData): array
    {
        // Simulate processing delay
        usleep(rand(500000, 2000000)); // 0.5-2 seconds

        // Generate fake transaction ID
        $transactionId = 'SIM_' . time() . '_' . rand(1000, 9999);

        // Simulate occasional failures (5% chance)
        $success = rand(1, 100) > 5;

        if ($success) {
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'gateway_response' => [
                    'transaction_id' => $transactionId,
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency'] ?? 'RWF',
                    'processed_at' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            return [
                'success' => false,
                'transaction_id' => null,
                'status' => 'failed',
                'message' => 'Payment failed: Insufficient funds',
                'gateway_response' => [
                    'error_code' => 'INSUFFICIENT_FUNDS',
                    'error_message' => 'Simulated payment failure'
                ]
            ];
        }
    }

    /**
     * Verify a fake payment
     *
     * @param string $transactionId Transaction ID to verify
     * @return array Verification result
     */
    public function verify(string $transactionId): array
    {
        // Simulate verification delay
        usleep(rand(200000, 800000)); // 0.2-0.8 seconds

        // Check if it's a fake transaction ID
        if (strpos($transactionId, 'SIM_') === 0) {
            return [
                'success' => true,
                'verified' => true,
                'status' => 'success',
                'transaction_id' => $transactionId,
                'message' => 'Payment verified successfully',
                'gateway_response' => [
                    'transaction_id' => $transactionId,
                    'verification_time' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            return [
                'success' => false,
                'verified' => false,
                'status' => 'not_found',
                'message' => 'Transaction not found',
                'gateway_response' => [
                    'error_code' => 'TRANSACTION_NOT_FOUND',
                    'error_message' => 'Invalid transaction ID'
                ]
            ];
        }
    }

    /**
     * Get the gateway name
     *
     * @return string Gateway identifier
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if gateway is available
     *
     * @return bool Always true for fake gateway
     */
    public function isAvailable(): bool
    {
        return true;
    }
}
?>