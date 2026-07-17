<?php
/**
 * Notification API Endpoints
 * Handles AJAX requests for notification management
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

requireProvider();

header('Content-Type: application/json');

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {
    case 'get_notifications':
        getNotificationsApi();
        break;
    
    case 'get_unread_count':
        getUnreadCountApi();
        break;
    
    case 'mark_as_read':
        markAsReadApi();
        break;
    
    case 'mark_all_read':
        markAllReadApi();
        break;
    
    case 'delete':
        deleteNotificationApi();
        break;
    
    case 'delete_all':
        deleteAllApi();
        break;
    
    case 'get_stats':
        getStatsApi();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

/**
 * Get notifications API
 */
function getNotificationsApi() {
    $type = sanitize($_GET['type'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $options = [
        'limit' => $limit,
        'offset' => $offset
    ];
    
    if ($type && $type !== 'all') {
        $options['type'] = $type;
    }
    
    $notifications = getNotifications($_SESSION['user_id'], $options);
    
    // Format notifications for API response
    foreach ($notifications as &$notif) {
        $notif['data'] = $notif['data'] ? json_decode($notif['data'], true) : null;
        $notif['time_ago'] = timeAgo($notif['created_at']);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

/**
 * Get unread count API
 */
function getUnreadCountApi() {
    $type = sanitize($_GET['type'] ?? '');
    
    if ($type && $type !== 'all') {
        $count = getUnreadNotificationCount($_SESSION['user_id'], $type);
    } else {
        $count = getUnreadNotificationCount($_SESSION['user_id']);
    }
    
    echo json_encode([
        'success' => true,
        'unread_count' => $count
    ]);
}

/**
 * Mark as read API
 */
function markAsReadApi() {
    $notif_id = intval($_POST['notification_id'] ?? 0);
    
    if (!$notif_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification ID']);
        return;
    }
    
    $result = markNotificationAsRead($notif_id, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notification as read']);
    }
}

/**
 * Mark all read API
 */
function markAllReadApi() {
    $type = sanitize($_POST['type'] ?? '');
    
    if ($type && $type !== 'all') {
        $result = markAllNotificationsAsRead($_SESSION['user_id'], $type);
    } else {
        $result = markAllNotificationsAsRead($_SESSION['user_id']);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notifications as read']);
    }
}

/**
 * Delete notification API
 */
function deleteNotificationApi() {
    $notif_id = intval($_POST['notification_id'] ?? 0);
    
    if (!$notif_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification ID']);
        return;
    }
    
    $result = deleteNotification($notif_id, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete notification']);
    }
}

/**
 * Delete all notifications API
 */
function deleteAllApi() {
    $type = sanitize($_POST['type'] ?? '');
    
    if ($type && $type !== 'all') {
        $result = deleteAllNotifications($_SESSION['user_id'], $type);
    } else {
        $result = deleteAllNotifications($_SESSION['user_id']);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'All notifications deleted'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete notifications']);
    }
}

/**
 * Get statistics API
 */
function getStatsApi() {
    $stats = getNotificationStats($_SESSION['user_id']);
    $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'stats' => $stats
    ]);
}
?>
