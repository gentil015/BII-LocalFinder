<?php

class AdminSettingsRepository
{
    public function getCategories(PDO $db): array
    {
        return $db->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDistricts(PDO $db): array
    {
        return $db->query('SELECT * FROM districts ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBlockedIps(PDO $db): array
    {
        return $db->query('SELECT ip_address FROM blocked_ips')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getPlans(PDO $db): array
    {
        return $db->query('SELECT * FROM plans ORDER BY monthly_price ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSystemInfo(PDO $db): array
    {
        return [
            'php_version' => phpversion(),
            'mysql_version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'disk_free_space' => round(disk_free_space('.') / 1024 / 1024 / 1024, 2),
            'disk_total_space' => round(disk_total_space('.') / 1024 / 1024 / 1024, 2),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    public function getSettings(PDO $db): array
    {
        $settings = [];
        $stmt = $db->query('SELECT setting_key, setting_value FROM system_settings');
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
            $settings[$key] = $value;
        }
        return $settings;
    }
}
