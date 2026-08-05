<?php

require_once __DIR__ . '/../../repositories/client/ClientBookingsRepository.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/chat.php';
require_once __DIR__ . '/../../includes/event_tracking.php';

class ClientBookingsService
{
    private ClientBookingsRepository $repository;

    public function __construct(?ClientBookingsRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientBookingsRepository();
    }

    public function buildViewModel(PDO $db, int $userId, array $filters): array
    {
        $systemSettings = [
            'platform_name' => $this->repository->getSetting($db, 'platform_name', 'BII LocalFinder'),
            'contact_email' => $this->repository->getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
            'contact_phone' => $this->repository->getSetting($db, 'contact_phone', '+250 788 123 456'),
            'platform_description' => $this->repository->getSetting($db, 'platform_description', 'Connecting clients with trusted local service providers'),
            'timezone' => $this->repository->getSetting($db, 'timezone', 'Africa/Kigali'),
            'max_pending_time' => intval($this->repository->getSetting($db, 'max_pending_time', '15')),
            'allow_booking_editing' => $this->repository->getSetting($db, 'allow_booking_editing', '1'),
            'auto_cancel_unconfirmed' => $this->repository->getSetting($db, 'auto_cancel_unconfirmed', '1'),
            'require_rating_after_completion' => $this->repository->getSetting($db, 'require_rating_after_completion', '0'),
            'max_cancellations_per_month' => intval($this->repository->getSetting($db, 'max_cancellations_per_month', '3')),
            'enable_commission' => $this->repository->getSetting($db, 'enable_commission', '0'),
            'commission_rate' => floatval($this->repository->getSetting($db, 'commission_rate', '10')),
            'enable_email_notifications' => $this->repository->getSetting($db, 'enable_email_notifications', '1'),
            'enable_sms_notifications' => $this->repository->getSetting($db, 'enable_sms_notifications', '0'),
        ];

        $stats = $this->repository->getBookingStats($db, $userId);
        $allBookings = $this->repository->getBookings($db, $userId, $filters);

        $bookedProviderIds = $this->repository->getBookedProviderIds($db, $userId);
        $categoryIds = $this->repository->getClientCategoryIds($db, $bookedProviderIds);
        $recommendedProviders = $this->repository->getRecommendedProviders($db, $userId, $bookedProviderIds, $categoryIds);

        return [
            'system_settings' => $systemSettings,
            'client' => $this->repository->getClientById($db, $userId),
            'view' => $filters['view'] ?? 'bookings',
            'status_filter' => $filters['status'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'search' => $filters['search'] ?? '',
            'total_bookings' => $stats['total'],
            'pending_bookings' => $stats['pending'],
            'confirmed_bookings' => $stats['confirmed'],
            'completed_bookings' => $stats['completed'],
            'monthly_cancellations' => $stats['monthly_cancellations'],
            'all_bookings' => $allBookings,
            'recommended_providers' => $recommendedProviders,
            'booked_provider_ids' => $bookedProviderIds,
        ];
    }

    public function handlePost(PDO $db, int $userId, array $post, array $systemSettings): array
    {
        $success = '';
        $error = '';

        if (isset($post['offer_action'])) {
            $offerId = intval($post['offer_id'] ?? 0);
            $action = sanitize($post['offer_action'] ?? '');
            $offer = $this->repository->getOfferByIdForClient($db, $offerId, $userId);

            if (!$offer) {
                return ['success' => '', 'error' => 'Offer not found or unauthorized'];
            }

            if ($action === 'withdraw') {
                if ($this->repository->updateOfferStatus($db, $offerId, 'withdrawn')) {
                    $success = 'Offer withdrawn successfully!';
                } else {
                    $error = 'Unable to withdraw offer';
                }
            } elseif ($action === 'accept_counter') {
                try {
                    $counterId = intval($post['counter_id'] ?? 0);
                    $counterOffer = $this->repository->getCounterOfferByIdForClient($db, $counterId, $userId);
                    if (!$counterOffer) {
                        throw new RuntimeException('Counter-offer not found or unauthorized');
                    }

                    if (!$this->repository->updateCounterOfferStatus($db, $counterId, 'accepted')) {
                        throw new RuntimeException('Failed to accept counter-offer');
                    }
                    if (!$this->repository->updateOfferStatus($db, $offerId, 'accepted')) {
                        throw new RuntimeException('Failed to update offer status');
                    }

                    $finalizedPrice = (float) $counterOffer['proposed_price'];
                    if (!$this->repository->insertFinalizedServicePrice($db, [
                        'booking_id' => (int) $offer['booking_id'],
                        'service_id' => (int) $offer['service_id'],
                        'client_id' => $userId,
                        'provider_id' => (int) $counterOffer['provider_id'],
                        'finalized_price' => $finalizedPrice,
                        'provider_final_counteroffer_id' => $counterId,
                    ])) {
                        throw new RuntimeException('Failed to finalize price');
                    }

                    if (!$this->repository->confirmBooking($db, (int) $offer['booking_id'], $userId, $finalizedPrice)) {
                        throw new RuntimeException('Failed to confirm booking');
                    }

                    $this->repository->logNegotiationHistory($db, (int) $offer['booking_id'], $offerId, $counterId, $finalizedPrice, $userId);
                    $success = '✅ Counter-offer accepted and booking confirmed! Final price: RWF ' . number_format($finalizedPrice, 0);
                } catch (Exception $e) {
                    error_log('Counter-offer acceptance error: ' . $e->getMessage());
                    $success = 'Error accepting counter-offer: ' . $e->getMessage();
                }
            }
        }

        if (isset($post['cancel_booking'])) {
            $bookingId = intval($post['booking_id'] ?? 0);
            $cancellationReason = isset($post['cancellation_reason']) ? sanitize($post['cancellation_reason']) : null;
            $stats = $this->repository->getBookingStats($db, $userId);

            if ($stats['monthly_cancellations'] >= intval($systemSettings['max_cancellations_per_month'] ?? 3)) {
                $error = 'You have reached your monthly cancellation limit (' . intval($systemSettings['max_cancellations_per_month'] ?? 3) . '). Please contact support.';
                return ['success' => $success, 'error' => $error];
            }

            if ($this->repository->cancelBooking($db, $bookingId, $userId, $cancellationReason)) {
                $success = 'Booking cancelled successfully';
                logActivity($db, $userId, 'booking_cancelled', "Cancelled booking #{$bookingId} - Reason: {$cancellationReason}");

                $providerId = $db->prepare('SELECT provider_id FROM bookings WHERE id = ?');
                $providerId->execute([$bookingId]);
                $providerData = $providerId->fetch(PDO::FETCH_ASSOC);
                $provider = $providerData['provider_id'] ?? null;
                if ($provider) {
                    trackEvent('booking_cancelled', 'booking', $bookingId, [
                        'cancellation_reason' => $cancellationReason,
                        'client_id' => $userId,
                        'provider_id' => $provider,
                    ], $userId);
                    sendMessage($userId, $provider, "Booking #{$bookingId} has been cancelled by the client. Reason: {$cancellationReason}");
                }
            } else {
                $error = 'Booking not found or cannot be cancelled';
            }
        }

        return ['success' => $success, 'error' => $error];
    }
}
