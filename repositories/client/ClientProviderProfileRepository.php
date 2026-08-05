<?php

class ClientProviderProfileRepository
{
    public function getPlatformSetting(PDO $db, string $key, string $default = ''): string
    {
        static $settings = null;
        if ($settings === null) {
            try {
                $stmt = $db->query('SELECT setting_key, setting_value FROM system_settings');
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                error_log('Settings load error: ' . $e->getMessage());
                $settings = [];
            }
        }
        return $settings[$key] ?? $default;
    }

    public function ensureBookingShareColumn(PDO $db): void
    {
        try {
            $colStmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'provider_share_id'");
            $colStmt->execute();
            if ((int) $colStmt->fetchColumn() === 0) {
                $db->exec("ALTER TABLE bookings ADD COLUMN provider_share_id INT NULL AFTER status");
            }
        } catch (Exception $e) {
            error_log('Booking share column check error: ' . $e->getMessage());
        }
    }

    public function getProviderById(PDO $db, int $providerId): ?array
    {
        $stmt = $db->prepare("
            SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.created_at as member_since,
                   u.is_verified as user_verified
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ? AND sp.is_active = 1 AND sp.is_banned = 0
        ");
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        return $provider === false ? null : $provider;
    }

    public function getVisibilitySettings(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider_id = ? AND setting_key LIKE 'visibility_%'");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getScheduleInfo(PDO $db, int $providerId): ?array
    {
        $stmt = $db->prepare("
            SELECT 
                working_days,
                working_hours_start,
                working_hours_end,
                break_start,
                break_end,
                availability,
                buffer_time,
                max_daily_bookings
            FROM service_providers 
            WHERE id = ?
        ");
        $stmt->execute([$providerId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        return $schedule === false ? null : $schedule;
    }

    public function getAvailabilityExceptions(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT date, is_available, start_time, end_time, notes FROM provider_availability WHERE provider_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 5");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTimeOffPeriods(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT start_date, end_date, reason FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE() AND is_approved = 1 ORDER BY start_date ASC LIMIT 3");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServices(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT ps.*, c.name as category_name, c.icon as category_icon FROM provider_services ps JOIN categories c ON ps.category_id = c.id WHERE ps.provider_id = ? AND ps.is_available = 1 ORDER BY ps.created_at DESC");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT DISTINCT c.* FROM categories c JOIN provider_services ps ON c.id = ps.category_id WHERE ps.provider_id = ? AND ps.is_available = 1");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPortfolioImages(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT * FROM portfolio_images WHERE provider_id = ? AND is_active = 1 ORDER BY display_order, uploaded_at DESC LIMIT 6");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPortfolioVideos(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT * FROM portfolio_videos WHERE provider_id = ? AND is_active = 1 ORDER BY display_order, uploaded_at DESC LIMIT 6");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentMethods(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT * FROM provider_payment_methods WHERE provider_id = ? ORDER BY is_default DESC");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServiceAreas(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("SELECT * FROM provider_service_areas WHERE provider_id = ? ORDER BY is_primary DESC");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isFavorite(PDO $db, int $userId, int $providerId): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM favorites WHERE client_id = ? AND provider_id = ?');
        $stmt->execute([$userId, $providerId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getSimilarProviders(PDO $db, int $providerId, array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT
                sp.id,
                sp.profession,
                sp.location,
                sp.average_rating,
                sp.total_reviews,
                sp.experience_years,
                sp.is_verified,
                u.full_name,
                u.profile_image,
                u.is_verified as user_verified,
                COUNT(DISTINCT ps.id) as service_count,
                AVG(ps.price) as avg_service_price
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN provider_services ps ON sp.id = ps.provider_id AND ps.is_available = 1
            JOIN provider_categories pc ON sp.id = pc.provider_id
            WHERE sp.id != ?
                AND sp.is_active = 1
                AND sp.is_banned = 0
                AND pc.category_id IN ($placeholders)
                AND sp.average_rating >= 3.5
            GROUP BY sp.id
            ORDER BY sp.average_rating DESC, sp.total_reviews DESC, sp.id
            LIMIT 6
        ");
        $params = array_merge([$providerId], $categoryIds);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
