<?php

require_once __DIR__ . '/../../repositories/provider/ProviderSettingsRepository.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/language.php';

class ProviderSettingsService
{
    private ProviderSettingsRepository $repository;

    public function __construct(?ProviderSettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new ProviderSettingsRepository();
    }

    public function buildViewModel(PDO $db, int $userId, string $section = 'identity'): array
    {
        $provider = $this->repository->getProviderProfile($db, $userId);
        $providerId = (int) ($provider['id'] ?? 0);

        $providerSettings = $this->repository->getProviderSettings($db, $providerId);
        $verificationDocs = $this->repository->getVerificationDocuments($db, $providerId);
        $paymentMethods = $this->repository->getPaymentMethods($db, $providerId);
        $allCategories = $this->repository->getCategories($db, $providerId);
        $selectedCategories = $this->repository->getSelectedCategories($db, $providerId);
        $serviceAreas = $this->repository->getServiceAreas($db, $providerId);
        $analytics = $this->repository->getAnalytics($db, $providerId);
        $sessionHistory = $this->repository->getSessionHistory($db, $userId);
        $recentReviews = $this->repository->getRecentReviews($db, $providerId);
        $platformSettings = $this->repository->getPlatformSettings($db);

        return [
            'provider' => $provider,
            'settings_section' => $section,
            'providerSettings' => $providerSettings,
            'verificationDocs' => $verificationDocs,
            'paymentMethods' => $paymentMethods,
            'allCategories' => $allCategories,
            'selectedCategories' => $selectedCategories,
            'serviceAreas' => $serviceAreas,
            'analytics' => $analytics,
            'sessionHistory' => $sessionHistory,
            'recentReviews' => $recentReviews,
            'platformSettings' => $platformSettings,
        ];
    }
}
