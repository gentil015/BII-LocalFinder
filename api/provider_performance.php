<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/provider_performance.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'Unknown error'];

try {
    $db = Database::getInstance()->getConnection();
    $performanceManager = new ProviderPerformanceManager();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_provider_performance':
            $provider_id = intval($_GET['provider_id'] ?? 0);
            $period_start = $_GET['period_start'] ?? null;
            $period_end = $_GET['period_end'] ?? null;

            if ($provider_id <= 0) {
                $response['error'] = 'Invalid provider ID';
                break;
            }

            $metrics = $performanceManager->calculateProviderPerformance($provider_id, $period_start, $period_end);
            $response = ['success' => true, 'metrics' => $metrics];
            break;

        case 'update_provider_performance':
            // Admin only
            if (!isLoggedIn() || !isAdmin()) {
                $response['error'] = 'Unauthorized';
                break;
            }

            $provider_id = intval($_POST['provider_id'] ?? 0);
            $period_start = $_POST['period_start'] ?? null;
            $period_end = $_POST['period_end'] ?? null;

            if ($provider_id <= 0) {
                $response['error'] = 'Invalid provider ID';
                break;
            }

            $metrics = $performanceManager->updateProviderPerformance($provider_id, $period_start, $period_end);
            $response = ['success' => true, 'message' => 'Performance updated', 'metrics' => $metrics];
            break;

        case 'get_performance_insights':
            $provider_id = intval($_GET['provider_id'] ?? 0);

            if ($provider_id <= 0) {
                $response['error'] = 'Invalid provider ID';
                break;
            }

            $insights = $performanceManager->getPerformanceInsights($provider_id);
            $response = ['success' => true, 'insights' => $insights];
            break;

        case 'analyze_availability_patterns':
            $provider_id = intval($_POST['provider_id'] ?? 0);
            $days_back = intval($_POST['days_back'] ?? 30);

            if ($provider_id <= 0) {
                $response['error'] = 'Invalid provider ID';
                break;
            }

            $patterns = $performanceManager->analyzeAvailabilityPatterns($provider_id, $days_back);
            $response = ['success' => true, 'patterns' => $patterns];
            break;

        case 'get_top_performers':
            $limit = intval($_GET['limit'] ?? 10);
            $period_days = intval($_GET['period_days'] ?? 30);

            $period_start = date('Y-m-d', strtotime("-{$period_days} days"));
            $period_end = date('Y-m-d');

            $stmt = $db->prepare("
                SELECT
                    pp.*,
                    u.full_name,
                    sp.profession,
                    sp.location,
                    sp.verification_level
                FROM provider_performance pp
                JOIN service_providers sp ON pp.provider_id = sp.id
                JOIN users u ON sp.user_id = u.id
                WHERE pp.period_start = ? AND pp.period_end = ?
                ORDER BY pp.overall_performance_score DESC
                LIMIT ?
            ");
            $stmt->execute([$period_start, $period_end, $limit]);
            $performers = $stmt->fetchAll();

            $response = ['success' => true, 'performers' => $performers];
            break;

        case 'get_performance_summary':
            $period_days = intval($_GET['period_days'] ?? 30);
            $period_start = date('Y-m-d', strtotime("-{$period_days} days"));
            $period_end = date('Y-m-d');

            // Calculate average performance metrics across all providers
            $stmt = $db->prepare("
                SELECT
                    AVG(overall_performance_score) as avg_overall_score,
                    AVG(avg_rating) as avg_rating,
                    AVG(cancellation_rate) as avg_cancellation_rate,
                    AVG(avg_response_time_hours) as avg_response_time,
                    COUNT(*) as total_providers,
                    SUM(CASE WHEN performance_grade = 'excellent' THEN 1 ELSE 0 END) as excellent_count,
                    SUM(CASE WHEN performance_grade = 'good' THEN 1 ELSE 0 END) as good_count,
                    SUM(CASE WHEN performance_grade = 'average' THEN 1 ELSE 0 END) as average_count,
                    SUM(CASE WHEN performance_grade = 'needs_improvement' THEN 1 ELSE 0 END) as needs_improvement_count
                FROM provider_performance
                WHERE period_start = ? AND period_end = ?
            ");
            $stmt->execute([$period_start, $period_end]);
            $summary = $stmt->fetch();

            $response = ['success' => true, 'summary' => $summary];
            break;

        default:
            $response['error'] = 'Invalid action';
            break;
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>