<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$db = Database::getInstance()->getConnection();

$fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';

$whereClauses = ['1=1'];
$params = [];
if ($fromDate !== '') {
    $whereClauses[] = 'DATE(created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $whereClauses[] = 'DATE(created_at) <= ?';
    $params[] = $toDate;
}
$whereSql = implode(' AND ', $whereClauses);

function fetchScalar(PDO $db, string $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : 0;
}

$totalPredictions = (int) fetchScalar($db, "SELECT COUNT(*) FROM ml_predictions_log WHERE {$whereSql}", $params);
$totalSuccess = (int) fetchScalar($db, "SELECT COUNT(*) FROM ml_predictions_log WHERE {$whereSql} AND actual_outcome = 1", $params);
$totalFailures = $totalPredictions - $totalSuccess;
$averageScore = (float) fetchScalar($db, "SELECT AVG(predicted_score) FROM ml_predictions_log WHERE {$whereSql}", $params);
$correctPredictions = (int) fetchScalar($db, "SELECT COUNT(*) FROM ml_predictions_log WHERE {$whereSql} AND ((predicted_score >= 0.5 AND actual_outcome = 1) OR (predicted_score < 0.5 AND actual_outcome = 0))", $params);

$accuracy = $totalPredictions > 0 ? round($correctPredictions / $totalPredictions * 100, 1) : 0.0;
$successRate = $totalPredictions > 0 ? round($totalSuccess / $totalPredictions * 100, 1) : 0.0;

$dailyStmt = $db->prepare("SELECT DATE(created_at) AS day,
    COUNT(*) AS predictions,
    SUM(actual_outcome = 1) AS successes,
    SUM(actual_outcome = 0) AS failures,
    AVG(predicted_score) AS avg_score,
    SUM((predicted_score >= 0.5 AND actual_outcome = 1) OR (predicted_score < 0.5 AND actual_outcome = 0)) AS correct_count
FROM ml_predictions_log
WHERE {$whereSql}
GROUP BY day
ORDER BY day ASC");
$dailyStmt->execute($params);
$dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

$predictionsOverTime = [];
$successTrend = [];
$accuracyTrend = [];
$avgScoreTrend = [];
foreach ($dailyData as $row) {
    $predictionsOverTime[] = [
        'day' => $row['day'],
        'predictions' => (int) $row['predictions'],
        'successes' => (int) $row['successes'],
        'failures' => (int) $row['failures'],
        'avg_score' => round((float) $row['avg_score'], 3),
        'accuracy' => $row['predictions'] > 0 ? round((int) $row['correct_count'] / (int) $row['predictions'] * 100, 1) : 0,
    ];
}

$successFailureCounts = [
    'Success' => $totalSuccess,
    'Failure' => $totalFailures,
];

$topProvidersStmt = $db->prepare("SELECT pl.provider_id,
    u.full_name AS provider_name,
    COUNT(*) AS predictions,
    SUM(pl.actual_outcome = 1) AS successes,
    ROUND(AVG(pl.predicted_score), 3) AS avg_score,
    ROUND(SUM(pl.actual_outcome = 1) / COUNT(*) * 100, 1) AS success_rate
FROM ml_predictions_log pl
JOIN service_providers sp ON pl.provider_id = sp.id
JOIN users u ON sp.user_id = u.id
WHERE {$whereSql}
GROUP BY pl.provider_id
HAVING COUNT(*) >= 3
ORDER BY success_rate DESC, predictions DESC
LIMIT 7");
$topProvidersStmt->execute($params);
$topProviders = $topProvidersStmt->fetchAll(PDO::FETCH_ASSOC);

$worstPredictionsStmt = $db->prepare("SELECT pl.provider_id,
    u.full_name AS provider_name,
    pl.predicted_score,
    pl.actual_outcome,
    pl.created_at,
    CASE
        WHEN pl.predicted_score >= 0.5 AND pl.actual_outcome = 0 THEN 'False Positive'
        WHEN pl.predicted_score < 0.5 AND pl.actual_outcome = 1 THEN 'False Negative'
        ELSE 'Unclear'
    END AS result_type
FROM ml_predictions_log pl
JOIN service_providers sp ON pl.provider_id = sp.id
JOIN users u ON sp.user_id = u.id
WHERE {$whereSql} AND ((pl.predicted_score >= 0.5 AND pl.actual_outcome = 0) OR (pl.predicted_score < 0.5 AND pl.actual_outcome = 1))
ORDER BY ABS(pl.predicted_score - 0.5) DESC, pl.created_at DESC
LIMIT 7");
$worstPredictionsStmt->execute($params);
$worstPredictions = $worstPredictionsStmt->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = array_column($predictionsOverTime, 'day');
$chartPredictions = array_column($predictionsOverTime, 'predictions');
$chartSuccess = array_column($predictionsOverTime, 'successes');
$chartFailures = array_column($predictionsOverTime, 'failures');
$chartAccuracy = array_column($predictionsOverTime, 'accuracy');
$chartAvgScores = array_column($predictionsOverTime, 'avg_score');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML Performance Dashboard</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 250px;
        }
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            color: #212529;
        }
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        .stat-card .label {
            display: block;
            color: var(--secondary);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        .stat-card .tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .stat-primary { border-left-color: var(--primary); }
        .stat-success { border-left-color: var(--success); }
        .stat-warning { border-left-color: var(--warning); }
        .stat-info { border-left-color: var(--info); }
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        .card h3 {
            margin: 0 0 1.5rem 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.3rem;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fa;
            color: var(--dark);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .filter-panel {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }
        .filter-panel label {
            font-weight: 600;
            color: var(--secondary);
        }
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .overlay.active {
            display: block;
        }
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .mobile-menu-toggle {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="overlay" id="overlay"></div>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="page-header">
                <h1>Machine Learning Performance</h1>
                <p>Monitor model predictions, accuracy and provider-level outcomes.</p>
            </div>

            <div class="card p-4">
                <form method="get" class="filter-panel">
                    <div>
                        <label for="from_date">From</label>
                        <input type="date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" class="form-control">
                    </div>
                    <div>
                        <label for="to_date">To</label>
                        <input type="date" id="to_date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="ml_dashboard.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <span class="label">Total Predictions</span>
                    <span class="value"><?php echo number_format($totalPredictions); ?></span>
                    <span class="tag bg-light text-dark">Data from log</span>
                </div>
                <div class="stat-card stat-success">
                    <span class="label">Accuracy</span>
                    <span class="value"><?php echo number_format($accuracy, 1); ?>%</span>
                    <span class="tag bg-light text-dark">Threshold 0.5</span>
                </div>
                <div class="stat-card stat-warning">
                    <span class="label">Success Rate</span>
                    <span class="value"><?php echo number_format($successRate, 1); ?>%</span>
                    <span class="tag bg-light text-dark">Completed bookings</span>
                </div>
                <div class="stat-card stat-info">
                    <span class="label">Avg ML Score</span>
                    <span class="value"><?php echo number_format($averageScore, 3); ?></span>
                    <span class="tag bg-light text-dark">Prediction mean</span>
                </div>
            </div>

            <div class="row gx-4 gy-4">
                <div class="col-lg-8">
                    <div class="card p-4">
                        <h3>Predictions Over Time</h3>
                        <canvas id="predictionsChart" height="220"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card p-4 mb-4">
                        <h3>Success vs Failure</h3>
                        <canvas id="successFailureChart" height="220"></canvas>
                    </div>
                    <div class="card p-4">
                        <h3>Accuracy Trend</h3>
                        <canvas id="accuracyChart" height="220"></canvas>
                    </div>
                </div>
            </div>

            <div class="row gx-4 gy-4">
                <div class="col-lg-6">
                    <div class="card p-4">
                        <h3>Top Performing Providers</h3>
                        <?php if (empty($topProviders)): ?>
                            <p class="text-muted">No provider performance data available for this range.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm">
                                    <thead>
                                        <tr>
                                            <th>Provider</th>
                                            <th class="text-end">Predictions</th>
                                            <th class="text-end">Success Rate</th>
                                            <th class="text-end">Avg Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topProviders as $providerRow): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($providerRow['provider_name']); ?></td>
                                                <td class="text-end"><?php echo number_format($providerRow['predictions']); ?></td>
                                                <td class="text-end"><?php echo number_format($providerRow['success_rate'], 1); ?>%</td>
                                                <td class="text-end"><?php echo number_format($providerRow['avg_score'], 3); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card p-4">
                        <h3>Worst Predictions</h3>
                        <?php if (empty($worstPredictions)): ?>
                            <p class="text-muted">No poor predictions recorded in this range.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm">
                                    <thead>
                                        <tr>
                                            <th>Provider</th>
                                            <th class="text-end">Predicted</th>
                                            <th class="text-center">Result</th>
                                            <th class="text-end">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($worstPredictions as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['provider_name']); ?></td>
                                                <td class="text-end"><?php echo number_format($row['predicted_score'], 3); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($row['result_type']); ?></td>
                                                <td class="text-end"><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const chartPredictions = <?php echo json_encode($chartPredictions); ?>;
        const chartSuccess = <?php echo json_encode($chartSuccess); ?>;
        const chartFailures = <?php echo json_encode($chartFailures); ?>;
        const chartAccuracy = <?php echo json_encode($chartAccuracy); ?>;

        const predictionsCtx = document.getElementById('predictionsChart').getContext('2d');
        new Chart(predictionsCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Predictions',
                        data: chartPredictions,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.15)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Successes',
                        data: chartSuccess,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25,135,84,0.15)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Failures',
                        data: chartFailures,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220,53,69,0.15)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {legend: {position: 'top'}},
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        const successFailureCtx = document.getElementById('successFailureChart').getContext('2d');
        new Chart(successFailureCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(<?php echo json_encode($successFailureCounts); ?>),
                datasets: [{
                    label: 'Count',
                    data: Object.values(<?php echo json_encode($successFailureCounts); ?>),
                    backgroundColor: ['#198754', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {legend: {display: false}},
                scales: {x: {beginAtZero: true}}
            }
        });

        const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
        new Chart(accuracyCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Accuracy (%)',
                    data: chartAccuracy,
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13,202,240,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {legend: {display: false}},
                scales: {y: {beginAtZero: true, max: 100}}
            }
        });
    </script>
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    </script>
</body>
</html>
