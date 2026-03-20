<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$action = strtolower(trim($_POST['action'] ?? ''));
$withId = intval($_POST['with'] ?? 0);

if ($withId <= 0 || $withId === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid target user']);
    exit;
}

ensureChatMetaTablesExist();

switch ($action) {
    case 'view_offers':
        $redirect = isProvider() ? '../provider/bookings.php?user=' . $withId : '../client/my-bookings.php?user=' . $withId;
        echo json_encode(['success' => true, 'redirect' => $redirect]);
        break;

    case 'mute_notifications':
        $muted = toggleMuteChat($userId, $withId);
        if ($muted) {
            $status = isChatMuted($userId, $withId) ? 'muted' : 'unmuted';
            echo json_encode(['success' => true, 'status' => $status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to update mute setting']);
        }
        break;

    case 'clear_chat':
        if (clearChat($userId, $withId)) {
            echo json_encode(['success' => true, 'message' => 'Chat cleared']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not clear chat']);
        }
        break;

    case 'delete_conversation':
        if (deleteConversation($userId, $withId)) {
            echo json_encode(['success' => true, 'message' => 'Conversation deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not delete conversation']);
        }
        break;

    case 'report_user':
        $reason = sanitize($_POST['reason'] ?? '');
        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Report reason is required']);
            break;
        }
        if (reportUser($userId, $withId, $reason)) {
            echo json_encode(['success' => true, 'message' => 'User reported']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not submit report']);
        }
        break;

    case 'block_user':
        if (blockUser($userId, $withId)) {
            echo json_encode(['success' => true, 'message' => 'User blocked']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not block user']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
