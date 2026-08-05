<?php

require_once __DIR__ . '/../../repositories/client/ClientBookingDetailsRepository.php';

class ClientBookingDetailsService
{
    private ClientBookingDetailsRepository $repository;

    public function __construct(?ClientBookingDetailsRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientBookingDetailsRepository();
    }

    public function buildViewModel(PDO $db, int $userId, int $bookingId, $paymentManager): array
    {
        if ($bookingId <= 0) {
            return [
                'booking' => null,
                'payment' => null,
                'error' => 'Invalid booking ID',
                'success' => null,
                'booking_mode' => 'request_approval',
            ];
        }

        $booking = $this->repository->getBookingByIdForClient($db, $bookingId, $userId);
        if ($booking === null) {
            return [
                'booking' => null,
                'payment' => null,
                'error' => 'Booking not found or access denied',
                'success' => null,
                'booking_mode' => 'request_approval',
            ];
        }

        $bookingMode = $booking['booking_mode'] ?? 'request_approval';
        $payment = $paymentManager->getPaymentForBooking($bookingId);

        return [
            'booking' => $booking,
            'payment' => $payment,
            'error' => null,
            'success' => null,
            'booking_mode' => $bookingMode,
        ];
    }
}
