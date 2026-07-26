<?php

require_once __DIR__ . '/../services/providers/ClientProvidersService.php';

class TestPDOStatement extends PDOStatement
{
    public function __construct()
    {
    }

    public function execute($params = null): bool
    {
        return true;
    }

    public function fetch($mode = null, $cursorOrientation = null, $cursorOffset = null)
    {
        return false;
    }

    public function fetchColumn($column = 0)
    {
        return false;
    }
}

class TestPDO extends PDO
{
    public function __construct()
    {
    }

    public function prepare($query, $options = null): PDOStatement
    {
        return new TestPDOStatement();
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return new TestPDOStatement();
    }
}

class FakeClientProvidersRepository extends ClientProvidersRepository
{
    public function getPlatformSetting(PDO $db, string $key, string $default = ''): string
    {
        return $default;
    }

    public function getClient(PDO $db, int $userId): array
    {
        return ['full_name' => 'Test User', 'location' => 'Kigali'];
    }

    public function getBookedProfessions(PDO $db, int $userId): array
    {
        return ['Plumbing' => 1];
    }

    public function getFavoriteProviderIds(PDO $db, int $userId): array
    {
        return [];
    }

    public function getRecentlyViewedProviderIds(PDO $db, int $userId): array
    {
        return [];
    }

    public function getUserBookingStats(PDO $db, int $userId): array
    {
        return ['user_total_bookings' => 0, 'user_avg_price' => 0, 'user_avg_response_time' => 0];
    }

    public function getFilterOptions(PDO $db): array
    {
        return ['categories' => [], 'locations' => []];
    }

    public function countProviders(PDO $db, array $where, array $params): int
    {
        return 2;
    }

    public function fetchProviders(PDO $db, array $where, array $params, array $favIds, string $sort, int $limit, int $offset, array $providerColumns): array
    {
        return [
            [
                'id' => 1,
                'user_id' => 10,
                'full_name' => 'Alpha',
                'profession' => 'Plumbing',
                'category' => 'Plumbing',
                'provider_location' => 'Kigali',
                'location' => 'Kigali',
                'availability' => 'available',
                'avg_response_hours' => 1,
                'total_jobs' => 10,
                'completed_jobs' => 8,
                'average_rating' => 4.5,
                'total_reviews' => 20,
                'latitude' => null,
                'longitude' => null,
                'is_verified' => 1,
                'user_verified' => 1,
                'is_featured' => 0,
                'experience_years' => 5,
                'view_count' => 20,
                'avg_price' => 30000,
            ],
            [
                'id' => 2,
                'user_id' => 11,
                'full_name' => 'Beta',
                'profession' => 'Electrical',
                'category' => 'Electrical',
                'provider_location' => 'Kigali',
                'location' => 'Kigali',
                'availability' => 'available',
                'avg_response_hours' => 2,
                'total_jobs' => 10,
                'completed_jobs' => 7,
                'average_rating' => 4.2,
                'total_reviews' => 15,
                'latitude' => null,
                'longitude' => null,
                'is_verified' => 0,
                'user_verified' => 0,
                'is_featured' => 0,
                'experience_years' => 3,
                'view_count' => 10,
                'avg_price' => 20000,
            ],
        ];
    }

    public function getForYouProviders(PDO $db, array $providers, array $bookedProfessions): array
    {
        return [];
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new ClientProvidersService(new FakeClientProvidersRepository());
$db = new TestPDO();

$baseFilters = [
    'search' => 'plumbing',
    'category' => 'Plumbing',
    'location' => '',
    'sort' => 'system',
    'page' => 1,
];

$first = $service->buildViewModel($db, 1, $baseFilters);
$second = $service->buildViewModel($db, 2, $baseFilters);

$firstIds = array_map(fn($provider) => (int)($provider['id'] ?? 0), $first['providers']);
$secondIds = array_map(fn($provider) => (int)($provider['id'] ?? 0), $second['providers']);

assertTrue($firstIds !== $secondIds, 'Providers should reorder for different clients when the ranking engine is active');
assertTrue($first['providers'][0]['id'] == 1 || $second['providers'][0]['id'] == 1, 'Expected at least one ranking result to be influenced by the client context');

echo "ProviderRankingServiceIntegrationTest passed\n";
