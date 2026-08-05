<?php

require_once __DIR__ . '/../../repositories/client/ClientFavoritesRepository.php';

class ClientFavoritesService
{
    private ClientFavoritesRepository $repository;

    public function __construct(?ClientFavoritesRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientFavoritesRepository();
    }

    public function buildViewModel(PDO $db, int $userId): array
    {
        $this->repository->ensureFavoritesTable($db);

        $systemSettings = [
            'platform_name' => $this->repository->getSetting($db, 'platform_name', 'BII LocalFinder'),
            'contact_email' => $this->repository->getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
            'contact_phone' => $this->repository->getSetting($db, 'contact_phone', '+250 788 123 456'),
            'platform_description' => $this->repository->getSetting($db, 'platform_description', 'Connecting clients with trusted local service providers'),
        ];

        $client = $this->repository->getClientById($db, $userId);
        if ($client === null) {
            return [
                'client' => null,
                'system_settings' => $systemSettings,
                'favorite_providers' => [],
                'total_favorites' => 0,
                'recent_favorites' => [],
                'recommended_providers' => [],
            ];
        }

        $favoriteProviders = $this->repository->getFavoriteProviders($db, $userId);
        $recommendedProviders = $this->repository->getRecommendedProviders($db, $userId);

        return [
            'client' => $client,
            'system_settings' => $systemSettings,
            'favorite_providers' => $favoriteProviders,
            'total_favorites' => count($favoriteProviders),
            'recent_favorites' => array_slice($favoriteProviders, 0, 3),
            'recommended_providers' => $recommendedProviders,
        ];
    }

    public function handlePost(PDO $db, int $userId, array $post): array
    {
        $this->repository->ensureFavoritesTable($db);

        $success = '';
        $error = '';

        if (isset($post['add_to_favorites'])) {
            $providerId = intval($post['provider_id'] ?? 0);
            if ($providerId <= 0) {
                $error = 'Invalid provider ID';
            } else {
                $this->repository->addFavorite($db, $userId, $providerId);
                $success = 'Provider added to favorites successfully';
            }
        } elseif (isset($post['remove_from_favorites'])) {
            $providerId = intval($post['provider_id'] ?? 0);
            if ($providerId <= 0) {
                $error = 'Invalid provider ID';
            } else {
                $this->repository->removeFavorite($db, $userId, $providerId);
                $success = 'Provider removed from favorites successfully';
            }
        } elseif (isset($post['clear_all_favorites'])) {
            $this->repository->clearFavorites($db, $userId);
            $success = 'All favorites cleared successfully';
        }

        return ['success' => $success, 'error' => $error];
    }
}
