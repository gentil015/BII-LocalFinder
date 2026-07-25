<?php

class ProviderSettingsRepository
{
    public function getProviderProfile(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name,
                   u.is_verified as email_verified, u.is_active, u.created_at as join_date,
                   u.two_factor_enabled, u.login_notifications
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProviderSettings(PDO $db, int $providerId): array
    {
        $providerSettings = [
            'visibility' => [],
            'communication' => [],
            'notifications' => [],
            'ai_features' => [],
            'payment' => [],
            'security' => [],
            'pricing' => [],
            'location' => [],
            'availability' => [],
            'reviews' => [],
            'appearance' => [],
            'language' => [],
        ];

        $stmt = $db->prepare('SELECT setting_key, setting_value FROM provider_settings WHERE provider_id = ?');
        $stmt->execute([$providerId]);
        $settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($settingsData as $key => $value) {
            if (strpos($key, 'ai_features_') === 0) {
                $section = 'ai_features';
                $setting = substr($key, strlen('ai_features_'));
            } else {
                $parts = explode('_', $key, 2);
                $section = $parts[0] ?? '';
                $setting = $parts[1] ?? '';
            }

            if (isset($providerSettings[$section]) && $setting) {
                $providerSettings[$section][$setting] = $value;
            }
        }

        return $providerSettings;
    }

    public function getVerificationDocuments(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare('SELECT * FROM verification_documents WHERE provider_id = ? ORDER BY uploaded_at DESC');
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentMethods(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare('SELECT * FROM provider_payment_methods WHERE provider_id = ? AND is_active = 1 ORDER BY is_default DESC');
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("
            SELECT c.id, c.name, c.icon, pc.category_id as selected
            FROM categories c
            LEFT JOIN provider_categories pc ON c.id = pc.category_id AND pc.provider_id = ?
            WHERE c.is_active = 1
            ORDER BY c.name
        ");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSelectedCategories(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare('SELECT category_id FROM provider_categories WHERE provider_id = ?');
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getServiceAreas(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare('SELECT * FROM provider_service_areas WHERE provider_id = ? ORDER BY is_primary DESC');
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnalytics(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                COUNT(DISTINCT client_id) as unique_clients,
                COUNT(DISTINCT CASE WHEN status = 'cancelled' THEN id END) as cancelled_jobs,
                COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN id END) as weekly_bookings
            FROM bookings 
            WHERE provider_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$providerId]);
        $analytics = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $ratingStmt = $db->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE provider_id = ?');
        $ratingStmt->execute([$providerId]);
        $ratingRow = $ratingStmt->fetch(PDO::FETCH_ASSOC);
        $analytics['avg_rating'] = isset($ratingRow['avg_rating']) && $ratingRow['avg_rating'] !== null ? floatval($ratingRow['avg_rating']) : 0;
        return $analytics;
    }

    public function getSessionHistory(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT device, ip_address, login_time, logout_time, user_agent FROM user_sessions WHERE user_id = ? ORDER BY login_time DESC LIMIT 10');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentReviews(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("
            SELECT r.*, u.full_name as client_name, u.profile_image as client_image
            FROM reviews r
            JOIN users u ON r.client_id = u.id
            WHERE r.provider_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlatformSettings(PDO $db): array
    {
        $settings = [];
        $stmt = $db->query('SELECT setting_key, setting_value FROM system_settings');
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
            $settings[$key] = $value;
        }
        return $settings;
    }
}
