<?php

require_once __DIR__ . '/../services/providers/AdminProvidersService.php';

class TestStatement extends PDOStatement
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
        return new TestStatement();
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }
}

class FakeAdminProvidersRepository extends AdminProvidersRepository
{
    public function listProviders(PDO $db, array $params, string $search, string $statusFilter, string $categoryFilter, string $verificationFilter, string $availabilityFilter): array
    {
        return [];
    }

    public function getCategories(PDO $db): array
    {
        return [];
    }

    public function getProviderDetails(PDO $db, int $providerId): array
    {
        return ['id' => $providerId];
    }

    public function getProviderStats(PDO $db, int $providerId): array
    {
        return ['completed_jobs' => 1];
    }

    public function getProviderSchedulingData(PDO $db, int $providerId): array
    {
        return ['upcoming_bookings' => []];
    }

    public function getWorkingDaysArray(string $workingDays): array
    {
        return ['mon', 'tue'];
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new AdminProvidersService(new FakeAdminProvidersRepository());
$db = new TestPDO();
$result = $service->handlePostAction($db, [
    'approve_provider' => '1',
    'provider_id' => '42',
]);

assertTrue($result['success'] === true, 'Approve action should succeed');
assertTrue($result['message'] === 'Provider approved and activated successfully', 'Approve action should return the expected message');

echo "AdminProvidersServiceTest passed\n";
