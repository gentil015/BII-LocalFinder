<?php
// includes/sms.php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/NotificationEngine.php';

class SMSNotifier
{
    /**
     * Send a generic SMS broadcast or provider notification.
     *
     * @param string $to recipient phone number
     * @param string $message message content
     * @param array $options [
     *    'provider_id' => int|null,
     *    'notification_type' => string|null,
     *    'subject' => string|null,
     *    'force_sms' => bool|null,
     *    'force_email' => bool|null,
     * ]
     *
     * @return array
     */
    public static function send(string $to, string $message, array $options = []): array
    {
        $user = [
            'phone' => $to,
            'email' => $options['email'] ?? '',
            'full_name' => $options['name'] ?? '',
            'user_type' => 'client',
        ];

        $engine = new NotificationEngine();
        $subject = $options['subject'] ?? 'Notification';

        return $engine->send($user, $subject, $message, [
            'force_sms' => $options['force_sms'] ?? null,
            'force_email' => $options['force_email'] ?? null,
            'provider_id' => $options['provider_id'] ?? null,
            'notification_type' => $options['notification_type'] ?? null,
        ]);
    }

    /**
     * Send a provider-specific alert using provider notification settings.
     *
     * @param int $providerId
     * @param string $notificationType
     * @param string $message
     * @return array
     */
    public static function sendProviderNotification(int $providerId, string $notificationType, string $message): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT u.email, u.phone, u.full_name, u.user_type FROM users u JOIN service_providers sp ON sp.user_id = u.id WHERE sp.id = ?');
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$provider) {
            return ['success' => false, 'message' => 'Provider not found'];
        }

        $notifySms = isset($notificationType) ? self::isProviderNotificationEnabled($providerId, $notificationType . '_sms') : true;
        $notifyEmail = isset($notificationType) ? self::isProviderNotificationEnabled($providerId, $notificationType . '_email') : true;

        $engine = new NotificationEngine();
        $subject = ucfirst(str_replace('_', ' ', $notificationType));

        return $engine->send($provider, $subject, $message, [
            'force_sms' => $notifySms,
            'force_email' => $notifyEmail,
            'provider_id' => $providerId,
            'notification_type' => $notificationType,
        ]);
    }

    /**
     * Determine provider notification preference for chat/new booking etc.
     */
    private static function isProviderNotificationEnabled(int $providerId, string $key): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT setting_value FROM provider_settings WHERE provider_id = ? AND setting_key = ? LIMIT 1');
            $stmt->execute([$providerId, 'notifications_' . $key]);
            $value = $stmt->fetchColumn();
            if ($value !== false) {
                return (int)$value === 1;
            }

            // fallback to defaults
            return true;
        } catch (Exception $e) {
            error_log('SMSNotifier preference lookup failed: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Send booking notification to the provider (SMS + fallback email). Same intent as Mailer::sendBookingNotification.
     */
    public static function sendBookingNotification($providerId, $clientName, $serviceDescription, $subject = null): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT u.email, u.phone, u.full_name, u.user_type FROM users u JOIN service_providers sp ON sp.user_id = u.id WHERE sp.id = ?');
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$provider) {
            return ['success' => false, 'message' => 'Provider not found'];
        }

        if (!$subject) {
            $subject = "🔔 New Booking Request from {$clientName}";
        }

        $message = "You have a new booking request from {$clientName}: {$serviceDescription}. Please respond as soon as possible.";

        return self::send($provider['phone'], $message, [
            'name' => $provider['full_name'],
            'email' => $provider['email'],
            'provider_id' => $providerId,
            'notification_type' => 'new_booking',
            'subject' => $subject,
            'force_sms' => self::isProviderNotificationEnabled($providerId, 'new_booking_sms'),
            'force_email' => self::isProviderNotificationEnabled($providerId, 'new_booking_email'),
        ]);
    }

    /**
     * Send booking status update (confirmed/completed/cancelled/reassigned) to client.
     */
    public static function sendBookingStatusUpdate($clientPhone, $providerName, $serviceDescription, $status, $additionalNotes = null): array
    {
        // Subject mapping like Mailer
        $subjectMap = [
            'confirmed' => '✅ Booking Confirmed',
            'completed' => '🎉 Service Completed',
            'cancelled' => '❌ Booking Cancelled',
            'reassigned' => '🔄 Provider Reassigned',
        ];
        $subject = $subjectMap[$status] ?? 'Booking Update';

        $statusMessageMap = [
            'confirmed' => "Your booking with {$providerName} has been confirmed.",
            'completed' => "Your booking with {$providerName} is completed. Thank you for using our service.",
            'cancelled' => "Your booking with {$providerName} has been cancelled. " . ($additionalNotes ?: ''),
            'reassigned' => "Your booking has been reassigned to another provider. {$additionalNotes}",
        ];

        $message = $statusMessageMap[$status] ?? "There's an update for your booking: {$serviceDescription}.";

        if ($additionalNotes && $status !== 'confirmed' && $status !== 'completed') {
            $message .= " Additional info: {$additionalNotes}";
        }

        return self::send($clientPhone, $message, [
            'subject' => $subject,
            'force_sms' => true,
            'force_email' => false,
            'notification_type' => 'booking_status',
        ]);
    }

    /**
     * Send review reminder to client.
     */
    public static function sendReviewReminder($clientPhone, $providerName, $serviceDescription, $reviewUrl): array
    {
        $subject = "⭐ Share Your Experience with {$providerName}";
        $message = "Your service for {$serviceDescription} is complete. Please leave your feedback at: {$reviewUrl}";

        return self::send($clientPhone, $message, [
            'subject' => $subject,
            'force_sms' => true,
            'force_email' => false,
            'notification_type' => 'review_reminder',
        ]);
    }

    /**
     * Send provider account notification.
     */
    public static function sendProviderAccountNotification($providerId, $notificationType, $customMessage = null, $additionalInfo = null): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT u.email, u.phone, u.full_name, u.user_type FROM users u JOIN service_providers sp ON sp.user_id = u.id WHERE sp.id = ?');
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$provider) {
            return ['success' => false, 'message' => 'Provider not found'];
        }

        $mapping = [
            'account_approved' => 'Your account has been approved and is now active.',
            'account_rejected' => 'Your application was rejected. Please review the reason in your dashboard.',
            'account_suspended' => 'Your account has been suspended due to policy issues.',
            'document_rejected' => 'Some documents need re-submission.',
            'verification_upgrade' => 'You are eligible for a verification level upgrade.',
            'warning' => 'Important notice regarding your account.',
        ];

        $subject = ucfirst(str_replace('_', ' ', $notificationType));
        $body = $customMessage ?? ($mapping[$notificationType] ?? 'Important account notification.');

        if ($additionalInfo) {
            $body .= " Additional info: {$additionalInfo}";
        }

        return self::send($provider['phone'], $body, [
            'name' => $provider['full_name'],
            'email' => $provider['email'],
            'subject' => $subject,
            'provider_id' => $providerId,
            'notification_type' => $notificationType,
            'force_sms' => self::isProviderNotificationEnabled($providerId, $notificationType . '_sms'),
            'force_email' => self::isProviderNotificationEnabled($providerId, $notificationType . '_email'),
        ]);
    }
}
