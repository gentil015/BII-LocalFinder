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
$document_id = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT 
        vd.*,
        u.full_name as provider_name,
        u.email as provider_email,
        sp.profession
    FROM verification_documents vd
    JOIN service_providers sp ON vd.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE vd.id = ?
");

$stmt->execute([$document_id]);
$document = $stmt->fetch();

if ($document) {
    echo json_encode([
        'success' => true,
        'document_path' => $document['document_path'],
        'document_type' => $document['document_type'],
        'provider_name' => $document['provider_name'],
        'provider_email' => $document['provider_email'],
        'profession' => $document['profession'],
        'status' => $document['status'],
        'uploaded_at' => date('M d, Y h:i A', strtotime($document['uploaded_at']))
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Document not found']);
}
?>