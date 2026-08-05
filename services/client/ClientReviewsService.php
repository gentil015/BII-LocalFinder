<?php

require_once __DIR__ . '/../../repositories/client/ClientReviewsRepository.php';
require_once __DIR__ . '/../../includes/functions.php';

class ClientReviewsService
{
    private ClientReviewsRepository $repository;

    public function __construct(?ClientReviewsRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientReviewsRepository();
    }

    public function buildViewModel(PDO $db, int $userId, int $ratingFilter = 0, string $sort = 'recent'): array
    {
        $systemSettings = [
            'platform_name' => $this->repository->getSetting($db, 'platform_name', 'BII LocalFinder'),
            'contact_email' => $this->repository->getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
            'contact_phone' => $this->repository->getSetting($db, 'contact_phone', '+250 788 123 456'),
            'require_rating_after_completion' => $this->repository->getSetting($db, 'require_rating_after_completion', '0'),
            'allow_review_editing' => $this->repository->getSetting($db, 'allow_review_editing', '1'),
            'allow_review_deletion' => $this->repository->getSetting($db, 'allow_review_deletion', '1'),
            'min_review_length' => intval($this->repository->getSetting($db, 'min_review_length', '10')),
            'max_review_length' => intval($this->repository->getSetting($db, 'max_review_length', '500')),
            'auto_cancel_unconfirmed' => $this->repository->getSetting($db, 'auto_cancel_unconfirmed', '1'),
            'max_pending_time' => intval($this->repository->getSetting($db, 'max_pending_time', '15')),
        ];

        $reviews = $this->repository->getReviews($db, $userId, $ratingFilter, $sort);
        $stats = $this->repository->getReviewStats($db, $userId);
        $pendingReviews = $this->repository->getPendingReviews($db, $userId);

        return [
            'system_settings' => $systemSettings,
            'reviews' => $reviews,
            'stats' => $stats,
            'pending_reviews' => $pendingReviews,
            'rating_filter' => $ratingFilter,
            'sort' => $sort,
        ];
    }

    public function handlePost(PDO $db, int $userId, array $post, array $systemSettings): array
    {
        $success = '';
        $errors = [];

        if (isset($post['delete_review'])) {
            if (empty($systemSettings['allow_review_deletion'])) {
                $errors[] = 'Review deletion is currently disabled by system administrator';
                return ['success' => '', 'errors' => $errors];
            }

            $reviewId = intval($post['review_id'] ?? 0);
            if ($reviewId <= 0) {
                $errors[] = 'Invalid review';
                return ['success' => '', 'errors' => $errors];
            }

            $review = $this->repository->getReviewForClient($db, $reviewId, $userId);
            if ($review === null) {
                $errors[] = 'Invalid review';
                return ['success' => '', 'errors' => $errors];
            }

            $providerId = (int) $review['provider_id'];
            if ($this->repository->deleteReview($db, $reviewId, $userId)) {
                $success = 'Review deleted successfully';
                logActivity($db, $userId, 'review_deleted', "Deleted review for provider #{$providerId}");
            } else {
                $errors[] = 'Failed to delete review';
            }
        }

        if (isset($post['edit_review'])) {
            if (empty($systemSettings['allow_review_editing'])) {
                $errors[] = 'Review editing is currently disabled by system administrator';
                return ['success' => $success, 'errors' => $errors];
            }

            $reviewId = intval($post['review_id'] ?? 0);
            $newRating = intval($post['rating'] ?? 0);
            $newComment = sanitize($post['comment'] ?? '');

            if ($reviewId <= 0) {
                $errors[] = 'Invalid review';
                return ['success' => $success, 'errors' => $errors];
            }

            if (strlen($newComment) < intval($systemSettings['min_review_length'] ?? 10)) {
                $errors[] = 'Review comment must be at least ' . intval($systemSettings['min_review_length'] ?? 10) . ' characters';
                return ['success' => $success, 'errors' => $errors];
            }

            if (strlen($newComment) > intval($systemSettings['max_review_length'] ?? 500)) {
                $errors[] = 'Review comment cannot exceed ' . intval($systemSettings['max_review_length'] ?? 500) . ' characters';
                return ['success' => $success, 'errors' => $errors];
            }

            $review = $this->repository->getReviewForClient($db, $reviewId, $userId);
            if ($review === null) {
                $errors[] = 'Invalid review';
                return ['success' => $success, 'errors' => $errors];
            }

            $providerId = (int) $review['provider_id'];
            if ($this->repository->updateReview($db, $reviewId, $userId, $newRating, $newComment)) {
                $success = 'Review updated successfully';
                logActivity($db, $userId, 'review_updated', "Updated review for provider #{$providerId}");
            } else {
                $errors[] = 'Failed to update review';
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }
}
