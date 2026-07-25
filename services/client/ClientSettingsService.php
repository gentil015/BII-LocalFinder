<?php

require_once __DIR__ . '/../../repositories/client/ClientSettingsRepository.php';

class ClientSettingsService
{
    private ClientSettingsRepository $repository;

    public function __construct(?ClientSettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientSettingsRepository();
    }

    public function buildViewModel(PDO $db, int $userId, string $section = 'account'): array
    {
        $user = $this->repository->getUserById($db, $userId);
        $systemSettings = $this->repository->getSystemSettings($db);
        $notifications = $this->repository->getUserNotifications($db, $userId);
        $privacy = $this->repository->getUserPrivacy($db, $userId);
        $bookingPrefs = $this->repository->getBookingPreferences($db, $userId);

        $stats = $this->repository->getBookingStats($db, $userId);

        return [
            'user' => $user,
            'settings_section' => $section,
            'system_settings' => $systemSettings,
            'user_notifications' => $notifications,
            'user_privacy' => $privacy,
            'user_booking_prefs' => $bookingPrefs,
            'needs_email_verification' => (bool) ($systemSettings['email_verification'] ?? 0) && empty($user['email_verified']),
            'needs_phone_verification' => (bool) ($systemSettings['phone_verification'] ?? 0) && empty($user['phone_verified']),
            'total_bookings' => (int) ($stats['total_bookings'] ?? 0),
            'completed_bookings' => (int) ($stats['completed_bookings'] ?? 0),
            'total_reviews' => (int) ($stats['total_reviews'] ?? 0),
        ];
    }
}
