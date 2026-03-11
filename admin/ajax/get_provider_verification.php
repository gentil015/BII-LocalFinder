<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$provider_id = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT verification_level 
    FROM service_providers 
    WHERE id = ?
");

$stmt->execute([$provider_id]);
$provider = $stmt->fetch();

if ($provider) {
    echo json_encode([
        'success' => true,
        'verification_level' => $provider['verification_level'] ?? 'none',
        'verification_notes' => ''
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Provider not found']);
}
?>