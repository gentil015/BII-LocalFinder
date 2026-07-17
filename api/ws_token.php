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

$token = createWebsocketToken($userId, $normalized, 900);

echo json_encode([
    'success' => true,
    'token' => $token,
    'conversation_id' => $normalized,
    'user_id' => $userId,
]);
