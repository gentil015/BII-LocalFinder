<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/live_location.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conversationId = trim($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? '');
if ($conversationId === '') {
    echo json_encode(['success' => false, 'error' => 'Missing conversation_id']);
    exit;
}

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

$history = isset($_GET['history']) && in_array($_GET['history'], ['1', 'true', 'yes'], true);
$result = [
    'success' => true,
    'conversation_id' => $normalized,
    'live_locations' => getLiveLocations($normalized),
];

if ($history) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $result['history'] = getLocationHistory($normalized, max(1, min(500, $limit)));
}

echo json_encode($result);
