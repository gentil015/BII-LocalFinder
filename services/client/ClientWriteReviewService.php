<?php

require_once __DIR__ . '/../../repositories/client/ClientWriteReviewRepository.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';

class ClientWriteReviewService
{
    private ClientWriteReviewRepository $repository;

    public function __construct(?ClientWriteReviewRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientWriteReviewRepository();
    }

    public function buildViewModel(PDO $db, int $userId, int $providerId, int $bookingId): array
    {
        $systemSettings = [
            'platform_name' => $this->repository->getSetting($db, 'platform_name', 'BII LocalFinder'),
            'contact_email' => $this->repository->getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
            'contact_phone' => $this->repository->getSetting($db, 'contact_phone', '+250 788 123 456'),
            'require_rating_after_completion' => $this->repository->getSetting($db, 'require_rating_after_completion', '0'),
            'allow_review_editing' => $this->repository->getSetting($db, 'allow_review_editing', '1'),
            'allow_review_deletion' => $this->repository->getSetting($db, 'allow_review_deletion', '1'),
            'min_review_length' => intval($this->repository->getSetting($db, 'min_review_length', '10')),
            'max_review_length' => intval($this->repository->getSetting($db, 'max_review_length', '1000')),
            'enable_email_notifications' => $this->repository->getSetting($db, 'enable_email_notifications', '1'),
            'enable_sms_notifications' => $this->repository->getSetting($db, 'enable_sms_notifications', '0'),
            'auto_cancel_unconfirmed' => $this->repository->getSetting($db, 'auto_cancel_unconfirmed', '1'),
        ];

        if ($providerId <= 0) {
            return [
                'system_settings' => $systemSettings,
                'provider' => null,
                'booking' => null,
                'existing_reviews' => [],
                'existing_review' => null,
                'is_review_mandatory' => false,
                'error' => 'Invalid provider id',
                'success' => '',
                'errors' => [],
            ];
        }

        $provider = $this->repository->getProviderById($db, $providerId);
        if ($provider === null) {
            return [
                'system_settings' => $systemSettings,
                'provider' => null,
                'booking' => null,
                'existing_reviews' => [],
                'existing_review' => null,
                'is_review_mandatory' => false,
                'error' => 'Provider not found',
                'success' => '',
                'errors' => [],
            ];
        }

        $booking = null;
        if ($bookingId > 0) {
            $booking = $this->repository->getBookingForReview($db, $bookingId, $userId, $providerId);
        }

        return [
            'system_settings' => $systemSettings,
            'provider' => $provider,
            'booking' => $booking,
            'existing_reviews' => $this->repository->getRecentReviews($db, $providerId),
            'existing_review' => $this->repository->hasExistingReview($db, $userId, $providerId, $bookingId) ? ['provider_id' => $providerId, 'booking_id' => $bookingId] : null,
            'is_review_mandatory' => !empty($systemSettings['require_rating_after_completion']) && $bookingId > 0,
            'error' => null,
            'success' => '',
            'errors' => [],
        ];
    }

    public function handleReviewSubmission(PDO $db, int $userId, array $post, array $systemSettings): array
    {
        $providerId = isset($post['provider_id']) ? intval($post['provider_id']) : 0;
        $bookingId = isset($post['booking_id']) ? intval($post['booking_id']) : 0;
        $rating = intval($post['rating'] ?? 0);
        $comment = sanitize($post['comment'] ?? '');
        $errors = [];

        if ($providerId <= 0) {
            $errors[] = 'Invalid provider id';
            return ['success' => '', 'errors' => $errors];
        }

        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Please select a rating between 1 and 5 stars';
        }

        if (trim($comment) === '') {
            $errors[] = 'Please write a comment about your experience';
        }

        if (strlen($comment) < intval($systemSettings['min_review_length'] ?? 10)) {
            $errors[] = 'Review comment must be at least ' . intval($systemSettings['min_review_length'] ?? 10) . ' characters long';
        }

        if (strlen($comment) > intval($systemSettings['max_review_length'] ?? 1000)) {
            $errors[] = 'Review comment must not exceed ' . intval($systemSettings['max_review_length'] ?? 1000) . ' characters';
        }

        if (empty($errors) && $this->repository->hasExistingReview($db, $userId, $providerId, $bookingId)) {
            $errors[] = $bookingId > 0 ? 'You have already reviewed this booking.' : 'You have already reviewed this provider';
        }

        if ($bookingId > 0 && !empty($systemSettings['require_rating_after_completion'])) {
            $booking = $this->repository->getBookingForReview($db, $bookingId, $userId, $providerId);
            if ($booking === null) {
                $errors[] = 'Invalid booking or booking not completed';
            }
        }

        if (!empty($errors)) {
            return ['success' => '', 'errors' => $errors];
        }

        try {
            $db->beginTransaction();
            if (!$this->repository->insertReview($db, $userId, $providerId, $bookingId, $rating, $comment)) {
                throw new RuntimeException('Failed to insert review');
            }
            $this->repository->updateProviderRating($db, $providerId);
            if ($bookingId > 0) {
                $this->repository->markBookingReviewed($db, $bookingId);
            }
            $db->commit();

            $provider = $this->repository->getProviderById($db, $providerId);
            if (!empty($systemSettings['enable_email_notifications']) && $provider && !empty($provider['email'])) {
                try {
                    $subject = 'New Review Received - ' . ($systemSettings['platform_name'] ?? 'BII LocalFinder');
                    $message = "
                        <p>Hello {$provider['full_name']},</p>
                        <p>You have received a new {$rating}-star review from {$_SESSION['user_name']}!</p>
                        <p><strong>Review:</strong> {$comment}</p>
                        <p>View your reviews in your dashboard to see all feedback.</p>
                        <p>Best regards,<br>{$systemSettings['platform_name']} Team</p>
                    ";
                    Mailer::send($provider['email'], $subject, $message, true);
                } catch (Throwable $e) {
                    error_log('Review email send error: ' . $e->getMessage());
                }
            }

            return ['success' => 'Thank you! Your review has been submitted successfully.', 'errors' => []];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Review submission error: ' . $e->getMessage());
            return ['success' => '', 'errors' => ['Failed to submit review. Please try again.']];
        }
    }
}
