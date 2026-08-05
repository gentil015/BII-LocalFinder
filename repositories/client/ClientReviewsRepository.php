<?php

class ClientReviewsRepository
{
    public function getSetting(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (string) $result : $default;
    }

    public function getReviews(PDO $db, int $userId, int $ratingFilter = 0, string $sort = 'recent'): array
    {
        $sql = "
            SELECT r.*,
                   sp.profession, sp.location, sp.average_rating as provider_rating,
                   u.full_name as provider_name, u.profile_image as provider_image
            FROM reviews r
            JOIN service_providers sp ON r.provider_id = sp.id
            JOIN users u ON sp.user_id = u.id
            WHERE r.client_id = ?
        ";

        $params = [$userId];
        if ($ratingFilter > 0) {
            $sql .= ' AND r.rating = ?';
            $params[] = $ratingFilter;
        }

        switch ($sort) {
            case 'oldest':
                $sql .= ' ORDER BY r.created_at ASC';
                break;
            case 'highest':
                $sql .= ' ORDER BY r.rating DESC, r.created_at DESC';
                break;
            case 'lowest':
                $sql .= ' ORDER BY r.rating ASC, r.created_at DESC';
                break;
            default:
                $sql .= ' ORDER BY r.created_at DESC';
                break;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReviewStats(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT 
                COUNT(*) as total,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
             FROM reviews WHERE client_id = ?'
        );
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        return $stats === false ? [
            'total' => 0,
            'avg_rating' => 0,
            'five_star' => 0,
            'four_star' => 0,
            'three_star' => 0,
            'two_star' => 0,
            'one_star' => 0,
        ] : $stats;
    }

    public function getPendingReviews(PDO $db, int $userId): array
    {
        try {
            $cols = $db->query('SHOW COLUMNS FROM bookings')->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('completed_at', $cols, true)) {
                $orderCol = 'completed_at';
            } elseif (in_array('updated_at', $cols, true)) {
                $orderCol = 'updated_at';
            } elseif (in_array('preferred_date', $cols, true)) {
                $orderCol = 'preferred_date';
            } else {
                $orderCol = 'created_at';
            }

            $stmt = $db->prepare(
                'SELECT b.id as booking_id, b.service_description, b.preferred_date,
                        sp.profession, u.full_name as provider_name, u.profile_image as provider_image
                 FROM bookings b
                 JOIN service_providers sp ON b.provider_id = sp.id
                 JOIN users u ON sp.user_id = u.id
                 WHERE b.client_id = ?
                   AND b.status = "completed"
                   AND NOT EXISTS (
                       SELECT 1 FROM reviews r WHERE r.booking_id = b.id AND r.client_id = ?
                   )
                 ORDER BY b.' . $orderCol . ' DESC
                 LIMIT 5'
            );
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('my-reviews: failed to load pending reviews: ' . $e->getMessage());
            return [];
        }
    }

    public function getReviewForClient(PDO $db, int $reviewId, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT provider_id, rating, comment FROM reviews WHERE id = ? AND client_id = ?');
        $stmt->execute([$reviewId, $userId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        return $review === false ? null : $review;
    }

    public function deleteReview(PDO $db, int $reviewId, int $userId): bool
    {
        $review = $this->getReviewForClient($db, $reviewId, $userId);
        if ($review === null) {
            return false;
        }

        $stmt = $db->prepare('DELETE FROM reviews WHERE id = ? AND client_id = ?');
        $deleted = $stmt->execute([$reviewId, $userId]);
        if (!$deleted) {
            return false;
        }

        $providerId = (int) $review['provider_id'];
        $statsStmt = $db->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE provider_id = ?');
        $statsStmt->execute([$providerId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        $avg = isset($stats['avg_rating']) && $stats['avg_rating'] !== null ? round((float) $stats['avg_rating'], 2) : 0.0;
        $total = isset($stats['total_reviews']) ? (int) $stats['total_reviews'] : 0;

        $updateStmt = $db->prepare('UPDATE service_providers SET average_rating = ?, total_reviews = ? WHERE id = ?');
        $updateStmt->execute([$avg, $total, $providerId]);

        return true;
    }

    public function updateReview(PDO $db, int $reviewId, int $userId, int $rating, string $comment): bool
    {
        $review = $this->getReviewForClient($db, $reviewId, $userId);
        if ($review === null) {
            return false;
        }

        $providerId = (int) $review['provider_id'];
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM reviews LIKE 'updated_at'");
            $colStmt->execute();
            $hasUpdatedAt = (bool) $colStmt->fetch();
        } catch (Throwable $e) {
            $hasUpdatedAt = false;
        }

        if ($hasUpdatedAt) {
            $stmt = $db->prepare('UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ? AND client_id = ?');
            $ok = $stmt->execute([$rating, $comment, $reviewId, $userId]);
        } else {
            $stmt = $db->prepare('UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND client_id = ?');
            $ok = $stmt->execute([$rating, $comment, $reviewId, $userId]);
        }

        if (!$ok) {
            return false;
        }

        $avgStmt = $db->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE provider_id = ?');
        $avgStmt->execute([$providerId]);
        $avgRating = $avgStmt->fetchColumn();
        $avg = $avgRating !== false && $avgRating !== null ? round((float) $avgRating, 2) : 0.0;

        $providerStmt = $db->prepare('UPDATE service_providers SET average_rating = ? WHERE id = ?');
        $providerStmt->execute([$avg, $providerId]);

        return true;
    }
}
