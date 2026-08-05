<?php

require_once __DIR__ . '/../../repositories/client/ClientProviderProfileRepository.php';

class ClientProviderProfileService
{
    private ClientProviderProfileRepository $repository;

    public function __construct(?ClientProviderProfileRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientProviderProfileRepository();
    }

    public function buildViewModel(PDO $db, int $providerId, ?int $shareId = null, ?int $userId = null): array
    {
        $this->repository->ensureBookingShareColumn($db);

        $platformName = $this->repository->getPlatformSetting($db, 'platform_name', 'BII LocalFinder');
        $platformDescription = $this->repository->getPlatformSetting($db, 'platform_description', 'Connecting skilled professionals with clients across Rwanda');

        $provider = $this->repository->getProviderById($db, $providerId);
        if ($provider === null) {
            return [
                'provider' => null,
                'platform_name' => $platformName,
                'platform_description' => $platformDescription,
                'share_id' => $shareId,
                'is_favorite' => false,
                'is_logged_in' => !empty($userId),
                'similar_providers' => [],
                'booking_success' => '',
                'booking_errors' => [],
                'services' => [],
                'categories' => [],
                'next_available_date' => null,
                'working_days' => [],
                'day_names' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'schedule_info' => null,
                'visibility' => [],
                'portfolio_images' => [],
                'portfolio_videos' => [],
                'payment_methods' => [],
                'service_areas' => [],
                'active_links' => 0,
                'social_links' => [],
            ];
        }

        $visibilitySettings = $this->repository->getVisibilitySettings($db, $providerId);
        $visibility = [
            'show_phone' => isset($visibilitySettings['visibility_show_phone']) ? (bool) $visibilitySettings['visibility_show_phone'] : true,
            'show_whatsapp' => isset($visibilitySettings['visibility_show_whatsapp']) ? (bool) $visibilitySettings['visibility_show_whatsapp'] : true,
            'show_exact_location' => isset($visibilitySettings['visibility_show_exact_location']) ? (bool) $visibilitySettings['visibility_show_exact_location'] : false,
            'profile_public' => isset($visibilitySettings['visibility_profile_public']) ? (bool) $visibilitySettings['visibility_profile_public'] : true,
            'appear_in_search' => isset($visibilitySettings['visibility_appear_in_search']) ? (bool) $visibilitySettings['visibility_appear_in_search'] : true,
            'appear_available' => isset($visibilitySettings['visibility_appear_available']) ? (bool) $visibilitySettings['visibility_appear_available'] : true,
            'emergency_service' => isset($visibilitySettings['visibility_emergency_service']) ? (bool) $visibilitySettings['visibility_emergency_service'] : false,
            'night_service' => isset($visibilitySettings['visibility_night_service']) ? (bool) $visibilitySettings['visibility_night_service'] : false,
            'weekend_service' => isset($visibilitySettings['visibility_weekend_service']) ? (bool) $visibilitySettings['visibility_weekend_service'] : true,
            'badge_verified' => isset($visibilitySettings['visibility_badge_verified']) ? (bool) $visibilitySettings['visibility_badge_verified'] : true,
            'badge_top_rated' => isset($visibilitySettings['visibility_badge_top_rated']) ? (bool) $visibilitySettings['visibility_badge_top_rated'] : true,
            'badge_fast_responder' => isset($visibilitySettings['visibility_badge_fast_responder']) ? (bool) $visibilitySettings['visibility_badge_fast_responder'] : true,
        ];

        if (!$visibility['profile_public']) {
            return [
                'provider' => null,
                'platform_name' => $platformName,
                'platform_description' => $platformDescription,
                'share_id' => $shareId,
                'is_favorite' => false,
                'is_logged_in' => !empty($userId),
                'similar_providers' => [],
                'booking_success' => '',
                'booking_errors' => ['Profile not available'],
                'services' => [],
                'categories' => [],
                'next_available_date' => null,
                'working_days' => [],
                'day_names' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'schedule_info' => null,
                'visibility' => $visibility,
                'portfolio_images' => [],
                'portfolio_videos' => [],
                'payment_methods' => [],
                'service_areas' => [],
                'active_links' => 0,
                'social_links' => [],
            ];
        }

        $scheduleInfo = $this->repository->getScheduleInfo($db, $providerId) ?? [];
        $workingDays = $scheduleInfo['working_days'] ? explode(',', $scheduleInfo['working_days']) : [1, 2, 3, 4, 5];

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $formattedWorkingDays = [];
        foreach ($workingDays as $dayNum) {
            if (isset($dayNames[(int) $dayNum - 1])) {
                $formattedWorkingDays[] = $dayNames[(int) $dayNum - 1];
            }
        }

        $timeOffPeriods = $this->repository->getTimeOffPeriods($db, $providerId);
        $availabilityExceptions = $this->repository->getAvailabilityExceptions($db, $providerId);
        $nextAvailableDate = $this->calculateNextAvailableDate($workingDays, $timeOffPeriods, $availabilityExceptions);

        $services = $this->repository->getServices($db, $providerId);
        $categories = $this->repository->getCategories($db, $providerId);
        $portfolioImages = $this->repository->getPortfolioImages($db, $providerId);
        $portfolioVideos = $this->repository->getPortfolioVideos($db, $providerId);
        $paymentMethods = $this->repository->getPaymentMethods($db, $providerId);
        $serviceAreas = $this->repository->getServiceAreas($db, $providerId);
        $isFavorite = !empty($userId) ? $this->repository->isFavorite($db, $userId, $providerId) : false;

        $socialLinks = [
            'website' => ['label' => 'Website', 'field' => 'website', 'icon' => 'fas fa-globe', 'color' => '#0d6efd'],
            'facebook' => ['label' => 'Facebook', 'field' => 'facebook', 'icon' => 'fab fa-facebook-f', 'color' => '#1877F2'],
            'twitter' => ['label' => 'Twitter', 'field' => 'twitter', 'icon' => 'fab fa-twitter', 'color' => '#1DA1F2'],
            'instagram' => ['label' => 'Instagram', 'field' => 'instagram', 'icon' => 'fab fa-instagram', 'color' => '#E4405F'],
            'linkedin' => ['label' => 'LinkedIn', 'field' => 'linkedin', 'icon' => 'fab fa-linkedin-in', 'color' => '#0A66C2'],
            'youtube' => ['label' => 'YouTube', 'field' => 'youtube', 'icon' => 'fab fa-youtube', 'color' => '#FF0000'],
            'whatsapp' => ['label' => 'WhatsApp', 'field' => 'whatsapp', 'icon' => 'fab fa-whatsapp', 'color' => '#25D366'],
            'tiktok' => ['label' => 'TikTok', 'field' => 'tiktok', 'icon' => 'fab fa-tiktok', 'color' => '#000000'],
        ];

        $activeLinks = 0;
        foreach ($socialLinks as $key => $link) {
            if ($key === 'whatsapp' && !$visibility['show_whatsapp']) {
                continue;
            }
            if (!empty($provider[$link['field']])) {
                $activeLinks++;
            }
        }
        if (!empty($provider['other_social'])) {
            $activeLinks++;
        }

        $categoryIds = array_column($categories, 'id');
        $similarProviders = $this->repository->getSimilarProviders($db, $providerId, $categoryIds);

        return [
            'provider' => $provider,
            'platform_name' => $platformName,
            'platform_description' => $platformDescription,
            'share_id' => $shareId,
            'is_favorite' => $isFavorite,
            'is_logged_in' => !empty($userId),
            'similar_providers' => $similarProviders,
            'booking_success' => '',
            'booking_errors' => [],
            'services' => $services,
            'categories' => $categories,
            'next_available_date' => $nextAvailableDate,
            'working_days' => $workingDays,
            'day_names' => $dayNames,
            'schedule_info' => $scheduleInfo,
            'visibility' => $visibility,
            'portfolio_images' => $portfolioImages,
            'portfolio_videos' => $portfolioVideos,
            'payment_methods' => $paymentMethods,
            'service_areas' => $serviceAreas,
            'active_links' => $activeLinks,
            'social_links' => $socialLinks,
            'formatted_working_days' => $formattedWorkingDays,
        ];
    }

    private function calculateNextAvailableDate(array $workingDays, array $timeOffPeriods, array $availabilityExceptions): ?string
    {
        if (empty($workingDays)) {
            return null;
        }

        $today = new DateTime('today');
        $checkDate = clone $today;
        for ($daysChecked = 0; $daysChecked < 30; $daysChecked++) {
            $dayOfWeek = (int) $checkDate->format('N');
            if (in_array($dayOfWeek, $workingDays, true)) {
                $dateStr = $checkDate->format('Y-m-d');
                $dateAvailable = true;

                foreach ($timeOffPeriods as $timeOff) {
                    if ($dateStr >= $timeOff['start_date'] && $dateStr <= $timeOff['end_date']) {
                        $dateAvailable = false;
                        break;
                    }
                }

                if ($dateAvailable) {
                    foreach ($availabilityExceptions as $exception) {
                        if (($exception['date'] ?? '') === $dateStr && !empty($exception['is_available']) && (int) $exception['is_available'] === 0) {
                            $dateAvailable = false;
                            break;
                        }
                    }
                }

                if ($dateAvailable) {
                    return $dateStr;
                }
            }
            $checkDate->modify('+1 day');
        }

        return null;
    }
}
