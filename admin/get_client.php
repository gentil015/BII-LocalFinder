<?php
// DEBUG VERSION - remove / revert when issue is fixed
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!function_exists('isLoggedIn') || !function_exists('isAdmin')) {
        throw new Exception('Authentication helper functions not available.');
    }

    // Check auth (will return 403 if not logged in/admin)
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - not logged in or not admin']);
        exit;
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid client id']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    if ($db instanceof PDO) {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // fetch user (omit non-existing columns)
    $stmt = $db->prepare("SELECT id, full_name, email, phone, profile_image, is_verified, created_at, last_login FROM users WHERE id = ? AND user_type = 'client'");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Client not found', 'id' => $id]);
        exit;
    }

    // ensure compatibility keys
    $user['location'] = $user['location'] ?? null;

    // stats
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
    $stmt->execute([$id]);
    $total_bookings = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE client_id = ?");
    $stmt->execute([$id]);
    $total_reviews = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id = ?");
    $stmt->execute([$id]);
    $reports_filed = (int) $stmt->fetchColumn();

    // Attempt to find an activity/log table and load recent activities related to this user.
    $candidateTables = ['activity_log','activity_logs','user_activity','user_activities','user_logs','logs','audits','audit_logs'];
    $activity_logs = [];
    $foundTable = null;

    foreach ($candidateTables as $t) {
        $q = $db->prepare("SHOW TABLES LIKE ?");
        $q->execute([$t]);
        if ($q->fetch()) { $foundTable = $t; break; }
    }

    if ($foundTable) {
        // inspect columns
        $cols = $db->query("DESCRIBE `$foundTable`")->fetchAll(PDO::FETCH_COLUMN);
        $hasUserId = in_array('user_id', $cols);
        $hasActorId = in_array('actor_id', $cols);
        // pick an ordering column
        $orderCol = null;
        foreach (['created_at','logged_at','timestamp','date','time','created','created_on'] as $c) {
            if (in_array($c, $cols)) { $orderCol = $c; break; }
        }
        if (!$orderCol && count($cols)) { $orderCol = $cols[0]; }

        $whereParts = [];
        $params = [];
        if ($hasUserId) { $whereParts[] = "user_id = ?"; $params[] = $id; }
        if ($hasActorId) { $whereParts[] = "actor_id = ?"; $params[] = $id; }

        if (!empty($whereParts)) {
            $sql = "SELECT * FROM `$foundTable` WHERE " . implode(' OR ', $whereParts);
            if ($orderCol) {
                $sql .= " ORDER BY `$orderCol` DESC";
            }
            $sql .= " LIMIT 500";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'user' => $user,
        'stats' => [
            'total_bookings' => $total_bookings,
            'total_reviews' => $total_reviews,
            'reports_filed' => $reports_filed
        ],
        'activity_logs' => $activity_logs,
        'activity_table' => $foundTable
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>