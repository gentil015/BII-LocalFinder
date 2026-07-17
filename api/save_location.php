<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/live_location.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$action = trim($_POST['action'] ?? 'update');
$conversationId = trim($_POST['conversation_id'] ?? '');
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
$saveHistory = isset($_POST['save_history']) && in_array($_POST['save_history'], ['1', 'true', 'yes'], true);
$userId = intval($_SESSION['user_id']);

$normalized = normalizeConversationKeyFromInput($conversationId, $userId);
if (!$normalized) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation_id']);
    exit;
}

if (!validateConversationAccess($userId, $normalized)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized conversation access']);
    exit;
}

if ($action === 'stop') {
    $success = clearLiveLocation($userId, $normalized);
    echo json_encode(['success' => $success]);
    exit;
}

if ($latitude === null || $longitude === null) {
    echo json_encode(['success' => false, 'error' => 'Missing latitude or longitude']);
    exit;
}

$success = updateLiveLocation($userId, $normalized, $latitude, $longitude, $saveHistory);

echo json_encode(['success' => $success]);
