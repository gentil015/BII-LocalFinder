<?php

class ClientDashboardRepository
{
    public function getPlatformSetting(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : $default;
    }

    public function getClient(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getClientBookingStats(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
        $stmt->execute([$userId]);
        $totalBookings = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'completed'");
        $stmt->execute([$userId]);
        $completedBookings = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $pendingBookings = (int) $stmt->fetchColumn();

        return [
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'pending_bookings' => $pendingBookings,
        ];
    }

    public function getFavoritesCount(PDO $db, int $userId): int
    {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE client_id = ?");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function getReviewCount(PDO $db, int $userId): int
    {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE client_id = ?");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function getRecentBookings(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("\n            SELECT b.*, u.full_name as provider_name, u.profile_image as provider_image, sp.profession\n            FROM bookings b\n            JOIN service_providers sp ON b.provider_id = sp.id\n            JOIN users u ON sp.user_id = u.id\n            WHERE b.client_id = ?\n            ORDER BY b.created_at DESC LIMIT 5\n        ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getFavoriteProviders(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("\n            SELECT sp.*, u.full_name, u.profile_image, u.is_verified as user_verified\n            FROM favorites f\n            JOIN service_providers sp ON f.provider_id = sp.id\n            JOIN users u ON sp.user_id = u.id\n            WHERE f.client_id = ?\n            ORDER BY f.created_at DESC LIMIT 6\n        ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getRecommendedProviders(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("\n            SELECT sp.*, u.full_name, u.profile_image,\n                   (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) as avg_price\n            FROM service_providers sp\n            JOIN users u ON sp.user_id = u.id\n            WHERE sp.is_active=1 AND sp.is_banned=0\n              AND sp.id NOT IN (SELECT provider_id FROM favorites WHERE client_id=?)\n            ORDER BY sp.average_rating DESC, sp.total_reviews DESC\n            LIMIT 4\n        ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getPendingReviews(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("\n            SELECT b.id as booking_id, b.service_description, b.preferred_date,\n                   sp.profession, u.full_name as provider_name, u.profile_image as provider_image\n            FROM bookings b\n            JOIN service_providers sp ON b.provider_id = sp.id\n            JOIN users u ON sp.user_id = u.id\n            WHERE b.client_id = ? AND b.status='completed'\n              AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id=b.id AND r.client_id=?)\n            ORDER BY b.preferred_date DESC LIMIT 3\n        ");
            $stmt->execute([$userId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getBookedProfessions(PDO $db, int $userId): array
    {
        try {
            $stmt = $db->prepare("SELECT sp.profession, COUNT(*) as cnt FROM bookings b JOIN service_providers sp ON b.provider_id=sp.id WHERE b.client_id=? GROUP BY sp.profession ORDER BY cnt DESC LIMIT 3");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
            return [];
        }
    }
}
