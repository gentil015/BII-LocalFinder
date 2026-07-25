<?php

class ClientSettingsRepository
{
    public function getUserById(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSystemSettings(PDO $db): array
    {
        $settings = [];
        $keys = [
            'platform_name' => 'BII LocalFinder',
            'contact_email' => 'support@biilocalfinder.com',
            'contact_phone' => '+250 788 123 456',
            'timezone' => 'Africa/Kigali',
            'email_verification' => '1',
            'phone_verification' => '0',
            'min_password_length' => '8',
            'require_special_chars' => '0',
            'enable_email_notifications' => '1',
            'enable_sms_notifications' => '0',
            'max_pending_time' => '15',
            'allow_booking_editing' => '1',
            'max_cancellations_per_month' => '3',
            'allow_account_deletion' => '1',
            'archive_deleted_accounts' => '1',
            'data_retention_days' => '30',
        ];

        foreach ($keys as $key => $default) {
            $settings[$key] = $this->getSettingValue($db, $key, $default);
        }

        return $settings;
    }

    public function getUserNotifications(PDO $db, int $userId): array
    {
        return [
            'email_notifications' => $this->getUserSettingValue($db, $userId, 'email_notifications', '1'),
            'sms_notifications' => $this->getUserSettingValue($db, $userId, 'sms_notifications', '0'),
            'booking_notifications' => $this->getUserSettingValue($db, $userId, 'booking_notifications', '1'),
            'review_notifications' => $this->getUserSettingValue($db, $userId, 'review_notifications', '1'),
            'marketing_notifications' => $this->getUserSettingValue($db, $userId, 'marketing_notifications', '0'),
        ];
    }

    public function getUserPrivacy(PDO $db, int $userId): array
    {
        return [
            'profile_visibility' => $this->getUserSettingValue($db, $userId, 'profile_visibility', 'public'),
            'show_contact_info' => $this->getUserSettingValue($db, $userId, 'show_contact_info', '1'),
            'data_sharing' => $this->getUserSettingValue($db, $userId, 'data_sharing', '0'),
        ];
    }

    public function getBookingPreferences(PDO $db, int $userId): array
    {
        return [
            'auto_confirm_bookings' => $this->getUserSettingValue($db, $userId, 'auto_confirm_bookings', '0'),
            'advance_booking_days' => (int) $this->getUserSettingValue($db, $userId, 'advance_booking_days', '7'),
            'preferred_notice_hours' => (int) $this->getUserSettingValue($db, $userId, 'preferred_notice_hours', '24'),
        ];
    }

    public function getBookingStats(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM bookings WHERE client_id = ?');
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'completed'");
        $stmt->execute([$userId]);
        $completed = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE client_id = ?');
        $stmt->execute([$userId]);
        $reviews = (int) $stmt->fetchColumn();

        return [
            'total_bookings' => $total,
            'completed_bookings' => $completed,
            'total_reviews' => $reviews,
        ];
    }

    private function getSettingValue(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }

    private function getUserSettingValue(PDO $db, int $userId, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?');
        $stmt->execute([$userId, $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }
}
