<?php

class ClientWriteReviewRepository
{
    public function getSetting(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (string) $result : $default;
    }

    public function getProviderById(PDO $db, int $providerId): ?array
    {
        $stmt = $db->prepare('
            SELECT sp.*, u.full_name, u.profile_image, u.email, u.phone
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ?
        ');
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        return $provider === false ? null : $provider;
    }

    public function hasExistingReview(PDO $db, int $userId, int $providerId, int $bookingId = 0): bool
    {
        if ($bookingId > 0) {
            $stmt = $db->prepare('SELECT id FROM reviews WHERE client_id = ? AND booking_id = ? LIMIT 1');
            $stmt->execute([$userId, $bookingId]);
            if ($stmt->fetch()) {
                return true;
            }
        }

        $stmt = $db->prepare('SELECT id FROM reviews WHERE client_id = ? AND provider_id = ? LIMIT 1');
        $stmt->execute([$userId, $providerId]);
        return (bool) $stmt->fetch();
    }

    public function getBookingForReview(PDO $db, int $bookingId, int $userId, int $providerId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ? AND client_id = ? AND provider_id = ? AND status = "completed"');
        $stmt->execute([$bookingId, $userId, $providerId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        return $booking === false ? null : $booking;
    }

    public function insertReview(PDO $db, int $userId, int $providerId, int $bookingId, int $rating, string $comment): bool
    {
        $stmt = $db->prepare('INSERT INTO reviews (client_id, provider_id, booking_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
        return $stmt->execute([$userId, $providerId, $bookingId ?: null, $rating, $comment]);
    }

    public function updateProviderRating(PDO $db, int $providerId): void
    {
        $stmt = $db->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE provider_id = ?');
        $stmt->execute([$providerId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $avg = isset($stats['avg_rating']) && $stats['avg_rating'] !== null ? round((float) $stats['avg_rating'], 2) : 0.0;
        $total = isset($stats['total_reviews']) ? (int) $stats['total_reviews'] : 0;

        $update = $db->prepare('UPDATE service_providers SET average_rating = ?, total_reviews = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$avg, $total, $providerId]);
    }

    public function markBookingReviewed(PDO $db, int $bookingId): void
    {
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM bookings LIKE 'is_reviewed'");
            $colStmt->execute();
            if ($colStmt->fetch()) {
                $stmt = $db->prepare('UPDATE bookings SET is_reviewed = 1 WHERE id = ?');
                $stmt->execute([$bookingId]);
            }
        } catch (Throwable $e) {
            error_log('write-review: bookings.is_reviewed column missing, skip update for booking_id=' . $bookingId);
        }
    }

    public function getRecentReviews(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare('
            SELECT r.*, u.full_name as client_name, u.profile_image
            FROM reviews r
            JOIN users u ON r.client_id = u.id
            WHERE r.provider_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ');
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
