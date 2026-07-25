<?php

require_once __DIR__ . '/../../repositories/admin/AdminSettingsRepository.php';

class AdminSettingsService
{
    private AdminSettingsRepository $repository;

    public function __construct(?AdminSettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminSettingsRepository();
    }

    public function buildViewModel(PDO $db): array
    {
        return [
            'categories' => $this->repository->getCategories($db),
            'districts' => $this->repository->getDistricts($db),
            'blocked_ips' => $this->repository->getBlockedIps($db),
            'plans' => $this->repository->getPlans($db),
            'system_info' => $this->repository->getSystemInfo($db),
            'settings' => $this->repository->getSettings($db),
        ];
    }

    public function updateGeneralSettings(PDO $db, array $post, array $files): string
    {
        $platform_name = sanitize($post['platform_name'] ?? '');
        $contact_email = sanitize($post['contact_email'] ?? '');
        $contact_phone = sanitize($post['contact_phone'] ?? '');
        $platform_description = sanitize($post['platform_description'] ?? '');
        $copyright_text = sanitize($post['copyright_text'] ?? '');
        $timezone = sanitize($post['timezone'] ?? '');
        $maintenance_mode = isset($post['maintenance_mode']) ? 1 : 0;

        $settings = [
            'platform_name' => $platform_name,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'platform_description' => $platform_description,
            'copyright_text' => $copyright_text,
            'timezone' => $timezone,
            'maintenance_mode' => $maintenance_mode,
            'admin_theme' => isset($post['admin_theme']) ? 'dark' : 'light'
        ];

        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        // Handle logo upload
        if (!empty($files['platform_logo']['tmp_name']) && $files['platform_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../assets/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($files['platform_logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo.' . $file_extension;
            $file_path = $upload_dir . $filename;

            if (move_uploaded_file($files['platform_logo']['tmp_name'], $file_path)) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('platform_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$filename, $filename]);
            }
        }

        return 'General settings updated successfully';
    }

    public function updateUserSettings(PDO $db, array $post): string
    {
        $user_settings = [
            'client_registration' => isset($post['client_registration']) ? 1 : 0,
            'provider_registration' => isset($post['provider_registration']) ? 1 : 0,
            'provider_verification_required' => isset($post['provider_verification_required']) ? 1 : 0,
            'email_verification' => isset($post['email_verification']) ? 1 : 0,
            'phone_verification' => isset($post['phone_verification']) ? 1 : 0,
            'min_password_length' => intval($post['min_password_length'] ?? 8),
            'require_special_chars' => isset($post['require_special_chars']) ? 1 : 0,
            'session_timeout' => intval($post['session_timeout'] ?? 60)
        ];

        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($user_settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        return 'User account settings updated successfully';
    }

    public function updateBookingSettings(PDO $db, array $post): string
    {
        $booking_settings = [
            'max_pending_time' => intval($post['max_pending_time'] ?? 15),
            'auto_assign_providers' => isset($post['auto_assign_providers']) ? 1 : 0,
            'allow_booking_editing' => isset($post['allow_booking_editing']) ? 1 : 0,
            'allow_provider_rejection' => isset($post['allow_provider_rejection']) ? 1 : 0,
            'auto_cancel_unconfirmed' => isset($post['auto_cancel_unconfirmed']) ? 1 : 0,
            'require_rating_after_completion' => isset($post['require_rating_after_completion']) ? 1 : 0,
            'max_cancellations_per_month' => intval($post['max_cancellations_per_month'] ?? 3)
        ];

        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($booking_settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        return 'Booking system settings updated successfully';
    }

    public function updateNotificationSettings(PDO $db, array $post): string
    {
        $notification_settings = [
            'enable_email_notifications' => isset($post['enable_email_notifications']) ? 1 : 0,
            'enable_sms_notifications' => isset($post['enable_sms_notifications']) ? 1 : 0,
            'smtp_host' => sanitize($post['smtp_host'] ?? ''),
            'smtp_port' => intval($post['smtp_port'] ?? 587),
            'smtp_username' => sanitize($post['smtp_username'] ?? ''),
            'smtp_password' => sanitize($post['smtp_password'] ?? ''),
            'smtp_encryption' => sanitize($post['smtp_encryption'] ?? ''),
            'sms_provider' => sanitize($post['sms_provider'] ?? ''),
            'sms_api_key' => sanitize($post['sms_api_key'] ?? ''),
            'sms_api_url' => sanitize($post['sms_api_url'] ?? '')
        ];

        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($notification_settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        return 'Notification settings updated successfully';
    }

    public function updatePaymentSettings(PDO $db, array $post): string
    {
        $payment_settings = [
            'enable_commission' => isset($post['enable_commission']) ? 1 : 0,
            'enable_subscriptions' => isset($post['enable_subscriptions']) ? 1 : 0,
            'enable_payouts' => isset($post['enable_payouts']) ? 1 : 0,
            'payment_gateway' => sanitize($post['payment_gateway'] ?? ''),
            'payment_enabled' => isset($post['payment_enabled']) ? 1 : 0,
            'default_gateway' => sanitize($post['default_gateway'] ?? 'fake'),
            'mtn_api_key' => sanitize($post['mtn_api_key'] ?? ''),
            'stripe_api_key' => sanitize($post['stripe_api_key'] ?? ''),
            'visa_merchant_id' => sanitize($post['visa_merchant_id'] ?? '')
        ];

        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($payment_settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        $this->saveSettings($db, $payment_settings);

        return 'Payment settings updated successfully';
    }

    private function saveSettings(PDO $db, array $settings): void
    {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
    }

    public function addCategory(PDO $db, array $post): string
    {
        $stmt = $db->prepare("INSERT INTO categories (name, icon, description, is_premium, monthly_fee) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitize($post['category_name'] ?? ''),
            sanitize($post['category_icon'] ?? ''),
            sanitize($post['category_description'] ?? ''),
            isset($post['is_premium']) ? 1 : 0,
            floatval($post['monthly_fee'] ?? 0)
        ]);

        return 'Category added successfully';
    }

    public function addDistrict(PDO $db, array $post): string
    {
        $stmt = $db->prepare("INSERT INTO districts (name, code) VALUES (?, ?)");
        $stmt->execute([
            sanitize($post['district_name'] ?? ''),
            sanitize($post['district_code'] ?? '')
        ]);

        return 'District added successfully';
    }

    public function addPlan(PDO $db, array $post): string
    {
        $stmt = $db->prepare("INSERT INTO plans (name, monthly_price, service_limit, photo_limit, analytics_level, ai_enabled) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitize($post['plan_name'] ?? ''),
            floatval($post['plan_price'] ?? 0),
            intval($post['service_limit'] ?? 0),
            intval($post['photo_limit'] ?? 0),
            sanitize($post['analytics_level'] ?? ''),
            isset($post['ai_enabled']) ? 1 : 0
        ]);

        return 'Plan added successfully';
    }

    public function updatePlan(PDO $db, array $post): string
    {
        $stmt = $db->prepare("UPDATE plans SET name = ?, monthly_price = ?, service_limit = ?, photo_limit = ?, analytics_level = ?, ai_enabled = ? WHERE id = ?");
        $stmt->execute([
            sanitize($post['plan_name'] ?? ''),
            floatval($post['plan_price'] ?? 0),
            intval($post['service_limit'] ?? 0),
            intval($post['photo_limit'] ?? 0),
            sanitize($post['analytics_level'] ?? ''),
            isset($post['ai_enabled']) ? 1 : 0,
            intval($post['plan_id'] ?? 0)
        ]);

        return 'Plan updated successfully';
    }

    public function deletePlan(PDO $db, array $post): string
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM provider_subscriptions WHERE plan_id = ? AND status = 'active'");
        $stmt->execute([intval($post['plan_id'] ?? 0)]);
        $active_count = $stmt->fetchColumn();

        if ($active_count > 0) {
            throw new Exception('Cannot delete plan with active subscriptions');
        }

        $stmt = $db->prepare("DELETE FROM plans WHERE id = ?");
        $stmt->execute([intval($post['plan_id'] ?? 0)]);

        return 'Plan deleted successfully';
    }

    public function updateSecuritySettings(PDO $db, array $post): string
    {
        $security_settings = [
            'allowed_file_types' => sanitize($post['allowed_file_types'] ?? ''),
            'max_file_size' => intval($post['max_file_size'] ?? 0),
            'enable_2fa_admin' => isset($post['enable_2fa_admin']) ? 1 : 0,
            'auto_backup' => isset($post['auto_backup']) ? 1 : 0,
            'backup_frequency' => sanitize($post['backup_frequency'] ?? ''),
            'cookie_consent' => isset($post['cookie_consent']) ? 1 : 0
        ];

        $this->saveSettings($db, $security_settings);

        if (isset($post['blocked_ips'])) {
            $blocked_ips = array_filter(array_map('trim', explode(',', sanitize($post['blocked_ips']))));
            $stmt = $db->prepare("DELETE FROM blocked_ips");
            $stmt->execute();

            $stmt = $db->prepare("INSERT INTO blocked_ips (ip_address) VALUES (?)");
            foreach ($blocked_ips as $ip) {
                $stmt->execute([$ip]);
            }
        }

        return 'Security settings updated successfully';
    }

    public function updateDeveloperSettings(PDO $db, array $post): string
    {
        $developer_settings = [
            'debug_mode' => isset($post['debug_mode']) ? 1 : 0,
            'api_rate_limit' => intval($post['api_rate_limit'] ?? 0),
            'cache_duration' => intval($post['cache_duration'] ?? 0),
            'cron_auto_cleanup' => isset($post['cron_auto_cleanup']) ? 1 : 0,
            'cron_notifications' => isset($post['cron_notifications']) ? 1 : 0
        ];

        $this->saveSettings($db, $developer_settings);

        $webhooks = [
            'payment_webhook' => sanitize($post['payment_webhook'] ?? ''),
            'sms_webhook' => sanitize($post['sms_webhook'] ?? ''),
            'email_webhook' => sanitize($post['email_webhook'] ?? '')
        ];

        $this->saveSettings($db, $webhooks);

        return 'Developer settings updated successfully';
    }

    public function optimizeDatabase(PDO $db): string
    {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $db->query("OPTIMIZE TABLE `{$table}`");
        }

        return 'Database optimized successfully';
    }

    public function clearCache(PDO $db): string
    {
        $session_files = glob(session_save_path() . '/*');
        foreach ($session_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $temp_files = glob(__DIR__ . '/../../tmp/*');
        foreach ($temp_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return 'Cache cleared successfully';
    }
}
