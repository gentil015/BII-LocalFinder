<?php

require_once __DIR__ . '/../repositories/HomePageRepository.php';

class HomePageService
{
    private HomePageRepository $repository;

    public function __construct(?HomePageRepository $repository = null)
    {
        $this->repository = $repository ?? new HomePageRepository();
    }

    public function buildViewModel(PDO $db): array
    {
        $cacheKey = 'homepage_view_model';
        $cached = $this->getCache($cacheKey, 300);
        if ($cached !== false) {
            return $cached;
        }

        $schemaFlags = $this->repository->getSchemaFlags($db);
        $platformSettings = $this->repository->getPlatformSettings($db);

        $viewModel = [
            'schema_flags' => $schemaFlags,
            'maintenance_mode' => $platformSettings['maintenance_mode'] ?? '0',
            'platform_name' => $platformSettings['platform_name'] ?? 'BII LocalFinder',
            'contact_email' => $platformSettings['contact_email'] ?? 'support@biilocalfinder.com',
            'contact_phone' => $platformSettings['contact_phone'] ?? '+250 788 123 456',
            'platform_description' => $platformSettings['platform_description'] ?? 'Connecting clients with trusted local service providers',
            'copyright_text' => $platformSettings['copyright_text'] ?? '© 2024 BII LocalFinder. All rights reserved.',
            'categories' => $this->repository->getCategories($db),
            'featured_providers' => $this->repository->getFeaturedProviders($db, $schemaFlags),
            'nearby_providers' => $this->repository->getNearbyProviders($db, $schemaFlags),
            'recent_providers' => $this->repository->getRecentProviders($db, $schemaFlags),
            'districts' => $this->repository->getDistricts($db),
        ];

        $this->setCache($cacheKey, $viewModel);

        return $viewModel;
    }

    private function getCache(string $key, int $ttl = 60): array|false
    {
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) {
            return false;
        }

        $file = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
        if (!is_file($file)) {
            return false;
        }

        $mtime = @filemtime($file);
        if ($mtime === false) {
            @unlink($file);
            return false;
        }

        if ($mtime + $ttl < time()) {
            @unlink($file);
            return false;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return false;
        }

        $value = @unserialize($data);
        return $value === false ? false : $value;
    }

    private function setCache(string $key, array $value): bool
    {
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
        $tmp = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($tmp, serialize($value), LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }

        @rename($tmp, $file);
        @chmod($file, 0644);
        return true;
    }
}
