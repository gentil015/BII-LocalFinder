<?php
require_once 'PaymentGateway.php';

/**
 * Fake Airtel Money Gateway
 * Simulates a real Airtel checkout and verification flow.
 */
class FakeAirtelGateway implements PaymentGateway
{
    private string $name = 'airtel';

    public function pay(array $paymentData): array
    {
        usleep(rand(2000000, 3000000)); // 2-3 seconds

        $transactionId = 'AIRTEL_' . time() . '_' . rand(1000, 9999);
        $phone = $paymentData['phone_number'] ?? $paymentData['customer_phone'] ?? null;

        $status = rand(1, 100) <= 80 ? 'pending' : 'success';

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => $status,
            'message' => 'Airtel Money payment request submitted',
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

        if (strpos($transactionId, 'AIRTEL_') !== 0) {
            return [
                'success' => false,
                'verified' => false,
                'status' => 'not_found',
                'transaction_id' => $transactionId,
                'message' => 'Airtel transaction not found',
                'gateway_response' => [
                    'error_code' => 'TRANSACTION_NOT_FOUND',
                    'error_message' => 'Invalid Airtel transaction ID'
                ]
            ];
        }

        return [
            'success' => true,
            'verified' => true,
            'status' => 'success',
            'transaction_id' => $transactionId,
            'message' => 'Airtel payment verified successfully',
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
