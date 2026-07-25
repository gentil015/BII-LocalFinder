<?php

class AdminDashboardRepository
{
    public function getPlatformSettings(PDO $db): array
    {
        $keys = [
            'platform_name',
            'maintenance_mode',
            'client_registration',
            'provider_registration',
        ];

        $stmt = $db->prepare('SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
        $stmt->execute($keys);

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return array_merge([
            'platform_name' => 'BII LocalFinder',
            'maintenance_mode' => '0',
            'client_registration' => '1',
            'provider_registration' => '1',
        ], $settings);
    }

    public function getOverviewStats(PDO $db): array
    {
        return [
            'total_users' => $this->fetchCount($db, 'SELECT COUNT(*) FROM users'),
            'total_clients' => $this->fetchCount($db, 'SELECT COUNT(*) FROM users WHERE user_type = ?', ['client']),
            'total_providers' => $this->fetchCount($db, 'SELECT COUNT(*) FROM users WHERE user_type = ?', ['provider']),
            'active_users' => $this->fetchCount($db, 'SELECT COUNT(*) FROM users WHERE is_verified = ?', [1]),
            'pending_users' => $this->fetchCount($db, 'SELECT COUNT(*) FROM users WHERE is_verified = ?', [0]),
            'total_bookings' => $this->fetchCount($db, 'SELECT COUNT(*) FROM bookings'),
            'pending_bookings' => $this->fetchCount($db, 'SELECT COUNT(*) FROM bookings WHERE status = ?', ['pending']),
            'completed_bookings' => $this->fetchCount($db, 'SELECT COUNT(*) FROM bookings WHERE status = ?', ['completed']),
            'cancelled_bookings' => $this->fetchCount($db, 'SELECT COUNT(*) FROM bookings WHERE status = ?', ['cancelled']),
            'total_reviews' => $this->fetchCount($db, 'SELECT COUNT(*) FROM reviews'),
            'pending_reports' => $this->fetchCount($db, 'SELECT COUNT(*) FROM reports WHERE status = ?', ['pending']),
            'total_categories' => $this->fetchCount($db, 'SELECT COUNT(*) FROM categories WHERE is_active = ?', [1]),
            'featured_providers' => $this->fetchCount($db, 'SELECT COUNT(*) FROM service_providers WHERE is_featured = ?', [1]),
            'banned_providers' => $this->fetchCount($db, 'SELECT COUNT(*) FROM service_providers WHERE is_banned = ?', [1]),
        ];
    }

    public function getRecentUsers(PDO $db): array
    {
        $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 6");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentBookings(PDO $db): array
    {
        $stmt = $db->query("\n            SELECT b.*, u.full_name as client_name, sp.profession, u2.full_name as provider_name\n            FROM bookings b\n            JOIN users u ON b.client_id = u.id\n            JOIN service_providers sp ON b.provider_id = sp.id\n            JOIN users u2 ON sp.user_id = u2.id\n            ORDER BY b.created_at DESC \n            LIMIT 5\n        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopProviders(PDO $db): array
    {
        $stmt = $db->query("\n            SELECT u.full_name, sp.profession, sp.average_rating, sp.total_reviews, sp.location, sp.verification_level\n            FROM service_providers sp\n            JOIN users u ON sp.user_id = u.id\n            WHERE u.is_verified = 1 AND sp.is_banned = 0\n            ORDER BY sp.average_rating DESC, sp.total_reviews DESC\n            LIMIT 5\n        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSystemHealth(PDO $db): array
    {
        return [
            'database_size' => $this->fetchCount($db, "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()"),
            'active_sessions' => $this->fetchCount($db, 'SELECT COUNT(*) FROM sessions WHERE last_activity > ?', [time() - 3600]),
            'pending_tasks' => $this->fetchCount($db, 'SELECT COUNT(*) FROM bookings WHERE status = ?', ['pending']),
            'unread_messages' => $this->fetchCount($db, 'SELECT COUNT(*) FROM messages WHERE is_read = ?', [0]),
        ];
    }

    public function calculateGrowth(PDO $db, string $table, string $dateColumn): float
    {
        $currentMonth = $this->fetchCount($db, "SELECT COUNT(*) FROM {$table} WHERE {$dateColumn} >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $previousMonth = $this->fetchCount($db, "SELECT COUNT(*) FROM {$table} WHERE {$dateColumn} BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)");

        if ($previousMonth > 0) {
            return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
        }

        return $currentMonth > 0 ? 100.0 : 0.0;
    }

    public function setSystemSetting(PDO $db, string $key, string $value): void
    {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }

    public function clearCacheFiles(string $cacheDir): void
    {
        $files = glob(rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function fetchCount(PDO $db, string $query, array $params = []): int
    {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
