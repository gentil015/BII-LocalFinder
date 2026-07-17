<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$db = Database::getInstance()->getConnection();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'client'");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        header('Content-Type: application/json');
        echo json_encode($client);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Client not found']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Client ID required']);
}
?>