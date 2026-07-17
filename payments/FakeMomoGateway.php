<?php
require_once 'PaymentGateway.php';

/**
 * Fake MTN Mobile Money Gateway
 * Simulates a real mobile money checkout and verification flow.
 */
class FakeMomoGateway implements PaymentGateway
{
    private string $name = 'mtn';

    public function pay(array $paymentData): array
    {
        // Simulate a realistic network/API delay
        usleep(rand(2000000, 3000000)); // 2-3 seconds

        $transactionId = 'MOMO_' . time() . '_' . rand(1000, 9999);
        $phone = $paymentData['phone_number'] ?? $paymentData['customer_phone'] ?? null;

        // Simulate an accepted payment request, but keep final settlement pending.
        $status = rand(1, 100) <= 80 ? 'pending' : 'success';

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => $status,
            'message' => 'MTN MoMo payment request submitted',
            'gateway_response' => [
                'transaction_id' => $transactionId,
                'phone_number' => $phone,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'RWF',
                'submitted_at' => date('Y-m-d H:i:s'),
                'status' => $status
            ]
        ];
    }

    public function verify(string $transactionId): array
    {
        usleep(rand(2000000, 3000000)); // 2-3 seconds

        if (strpos($transactionId, 'MOMO_') !== 0) {
            return [
                'success' => false,
                'verified' => false,
                'status' => 'not_found',
                'transaction_id' => $transactionId,
                'message' => 'MTN MoMo transaction not found',
                'gateway_response' => [
                    'error_code' => 'TRANSACTION_NOT_FOUND',
                    'error_message' => 'Invalid MTN MoMo transaction ID'
                ]
            ];
        }

        return [
            'success' => true,
            'verified' => true,
            'status' => 'success',
            'transaction_id' => $transactionId,
            'message' => 'MTN MoMo payment verified successfully',
            'gateway_response' => [
                'transaction_id' => $transactionId,
                'verified_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
