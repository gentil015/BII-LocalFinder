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

try {
    // Get pending verification count
    $stmt = $db->prepare("SELECT COUNT(*) as pending FROM verification_documents WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pending' => intval($result['pending'])
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
