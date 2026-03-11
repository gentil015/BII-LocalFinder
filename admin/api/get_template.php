<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Template ID required']);
    exit;
}

$db = Database::getInstance()->getConnection();
$template_id = intval($_GET['id']);

try {
    $stmt = $db->prepare("SELECT * FROM notification_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        echo json_encode($template);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>