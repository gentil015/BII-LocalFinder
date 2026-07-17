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

$stats = [
    'pending' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents WHERE status = ?", ['pending']),
    'approved' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents WHERE status = ?", ['approved']),
    'rejected' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents WHERE status = ?", ['rejected']),
    'providers_pending' => fetchCount($db, "
        SELECT COUNT(DISTINCT provider_id) 
        FROM verification_documents 
        WHERE status = 'pending'
    ", [])
];

echo json_encode(['success' => true, 'stats' => $stats]);
?>