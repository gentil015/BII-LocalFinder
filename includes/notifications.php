<?php
/**
 * Notification System Helper Functions
 * Handles creation, retrieval, and management of provider notifications
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Create a new notification
 *
 * @param int $user_id Provider/User ID
 * @param string $type Notification type (booking, offer, favorite, service_update, etc.)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param array $options Additional options
 * @return int|false Notification ID or false on failure
 */
function createNotification($user_id, $type, $title, $message, $options = []) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $related_id = $options['related_id'] ?? null;
        $related_type = $options['related_type'] ?? null;
        $icon = $options['icon'] ?? null;
        $icon_color = $options['icon_color'] ?? null;
        $priority = $options['priority'] ?? 'medium';
        $action_url = $options['action_url'] ?? null;
        $action_label = $options['action_label'] ?? null;
        $data = $options['data'] ? json_encode($options['data']) : null;
        
        $stmt = $db->prepare("
            INSERT INTO notifications (
                user_id, notification_type, title, message, 
                related_id, related_type, icon, icon_color, 
                priority, action_url, action_label, data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id, $type, $title, $message,
            $related_id, $related_type, $icon, $icon_color,
            $priority, $action_url, $action_label, $data
        ]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log('Failed to create notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a provider
 *
 * @param int $user_id Provider ID
 * @param array $filters Filter options (type, is_read, limit, offset, priority)
 * @return array List of notifications
 */
function getNotifications($user_id, $filters = []) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        // Apply filters
        if (isset($filters['type'])) {
            $query .= " AND notification_type = ?";
            $params[] = $filters['type'];
        }
        
        if (isset($filters['is_read']) && $filters['is_read'] !== null) {
            $query .= " AND is_read = ?";
            $params[] = $filters['is_read'] ? 1 : 0;
        }
        
        if (isset($filters['priority'])) {
            $query .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        // Order and limit
        $query .= " ORDER BY created_at DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Failed to get notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count
 *
 * @param int $user_id Provider ID
 * @param string|null $type Optional filter by notification type
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($user_id, $type = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $params = [$user_id];
        
        if ($type) {
            $query .= " AND notification_type = ?";
            $params[] = $type;
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log('Failed to get unread notification count: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 *
 * @param int $notification_id Notification ID
 * @param int|null $user_id Provider ID (for security)
 * @return bool Success status
 */
function markNotificationAsRead($notification_id, $user_id = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if ($user_id) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } else {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
            return $stmt->execute([$notification_id]);
        }
    } catch (Exception $e) {
        error_log('Failed to mark notification as read: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read
 *
 * @param int $user_id Provider ID
 * @param string|null $type Optional filter by type
 * @return bool Success status
 */
function markAllNotificationsAsRead($user_id, $type = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
        $params = [$user_id];
        
        if ($type) {
            $query .= " AND notification_type = ?";
            $params[] = $type;
        }
        
        $stmt = $db->prepare($query);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log('Failed to mark all notifications as read: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete notification
 *
 * @param int $notification_id Notification ID
 * @param int|null $user_id Provider ID (for security)
 * @return bool Success status
 */
function deleteNotification($notification_id, $user_id = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if ($user_id) {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } else {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
            return $stmt->execute([$notification_id]);
        }
    } catch (Exception $e) {
        error_log('Failed to delete notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete all notifications
 *
 * @param int $user_id Provider ID
 * @param string|null $type Optional filter by type
 * @return bool Success status
 */
function deleteAllNotifications($user_id, $type = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $query = "DELETE FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        if ($type) {
            $query .= " AND notification_type = ?";
            $params[] = $type;
        }
        
        $stmt = $db->prepare($query);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log('Failed to delete all notifications: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get notification statistics for provider
 *
 * @param int $user_id Provider ID
 * @return array Statistics
 */
function getNotificationStats($user_id) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                notification_type,
                priority
            FROM notifications
            WHERE user_id = ?
            GROUP BY notification_type, priority
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Failed to get notification statistics: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get grouped notifications by type
 *
 * @param int $user_id Provider ID
 * @param bool $unread_only Get only unread notifications
 * @return array Grouped notifications
 */
function getNotificationsGrouped($user_id, $unread_only = false) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY priority DESC, created_at DESC LIMIT 100";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll();
        
        // Group by type
        $grouped = [];
        foreach ($notifications as $notif) {
            $type = $notif['notification_type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $notif;
        }
        
        return $grouped;
    } catch (Exception $e) {
        error_log('Failed to get grouped notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has notification preferences
 *
 * @param int $user_id Provider ID
 * @return bool Has preferences
 */
function hasNotificationPreferences($user_id) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get notification preferences
 *
 * @param int $user_id Provider ID
 * @return array|null Preferences or null if not found
 */
function getNotificationPreferences($user_id) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log('Failed to get notification preferences: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create default notification preferences for new user
 *
 * @param int $user_id Provider ID
 * @return bool Success status
 */
function createDefaultNotificationPreferences($user_id) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO notification_preferences (user_id) VALUES (?)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ");
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log('Failed to create notification preferences: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update notification preferences
 *
 * @param int $user_id Provider ID
 * @param array $preferences Preferences to update
 * @return bool Success status
 */
function updateNotificationPreferences($user_id, $preferences = []) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $allowed_fields = [
            'booking_notifications', 'offer_notifications', 'favorite_notifications',
            'service_notifications', 'review_notifications', 'complaint_notifications',
            'system_notifications', 'email_notifications', 'push_notifications',
            'sms_notifications', 'notification_digest_frequency'
        ];
        
        $updates = [];
        $values = [];
        
        foreach ($preferences as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $values[] = $user_id;
        $query = "UPDATE notification_preferences SET " . implode(', ', $updates) . " WHERE user_id = ?";
        
        $stmt = $db->prepare($query);
        return $stmt->execute($values);
    } catch (Exception $e) {
        error_log('Failed to update notification preferences: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if notification type is enabled for user
 *
 * @param int $user_id Provider ID
 * @param string $type Notification type
 * @return bool Is enabled
 */
function isNotificationTypeEnabled($user_id, $type) {
    try {
        $preferences = getNotificationPreferences($user_id);
        
        if (!$preferences) {
            return true; // Default to enabled if no preferences set
        }
        
        $field_map = [
            'booking' => 'booking_notifications',
            'offer' => 'offer_notifications',
            'favorite' => 'favorite_notifications',
            'service_update' => 'service_notifications',
            'service_added' => 'service_notifications',
            'review' => 'review_notifications',
            'complaint' => 'complaint_notifications',
            'system' => 'system_notifications'
        ];
        
        $field = $field_map[$type] ?? null;
        
        if (!$field) {
            return true;
        }
        
        return (bool) $preferences[$field];
    } catch (Exception $e) {
        error_log('Failed to check notification type: ' . $e->getMessage());
        return true;
    }
}

/**
 * Create notification for new booking
 *
 * @param int $provider_id Provider ID
 * @param int $booking_id Booking ID
 * @param array $booking_data Additional booking data
 * @return int|false Notification ID
 */
function notifyNewBooking($provider_id, $booking_id, $booking_data = []) {
    if (!isNotificationTypeEnabled($provider_id, 'booking')) {
        return false;
    }
    
    $client_name = $booking_data['client_name'] ?? 'A client';
    $service_desc = $booking_data['service_description'] ?? 'Service request';
    
    return createNotification(
        $provider_id,
        'booking',
        'New Booking Request',
        "New booking from $client_name: " . substr($service_desc, 0, 100),
        [
            'related_id' => $booking_id,
            'related_type' => 'booking',
            'icon' => 'fa-calendar-plus',
            'icon_color' => '#007bff',
            'priority' => 'high',
            'action_url' => 'bookings.php?id=' . $booking_id,
            'action_label' => 'View Booking',
            'data' => [
                'booking_id' => $booking_id,
                'client_name' => $client_name,
                'service_description' => $service_desc,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}

/**
 * Create notification for offer received
 *
 * @param int $provider_id Provider ID
 * @param int $offer_id Offer ID
 * @param array $offer_data Additional offer data
 * @return int|false Notification ID
 */
function notifyOfferReceived($provider_id, $offer_id, $offer_data = []) {
    if (!isNotificationTypeEnabled($provider_id, 'offer')) {
        return false;
    }
    
    $client_name = $offer_data['client_name'] ?? 'A client';
    $amount = $offer_data['amount'] ?? 'N/A';
    
    return createNotification(
        $provider_id,
        'offer',
        'New Offer Received',
        "New offer from $client_name for RWF $amount",
        [
            'related_id' => $offer_id,
            'related_type' => 'offer',
            'icon' => 'fa-gift',
            'icon_color' => '#28a745',
            'priority' => 'high',
            'action_url' => 'negotiations.php?offer_id=' . $offer_id,
            'action_label' => 'Review Offer',
            'data' => [
                'offer_id' => $offer_id,
                'client_name' => $client_name,
                'amount' => $amount,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}

/**
 * Create notification for favorite action
 *
 * @param int $provider_id Provider ID
 * @param int $client_id Client ID
 * @param string $action 'added' or 'removed'
 * @param string $client_name Client name
 * @return int|false Notification ID
 */
function notifyFavoriteAction($provider_id, $client_id, $action, $client_name = '') {
    if (!isNotificationTypeEnabled($provider_id, 'favorite')) {
        return false;
    }
    
    if ($action === 'added') {
        $title = 'Added to Favorites';
        $message = "$client_name added you to their favorites";
        $icon = 'fa-heart';
        $color = '#dc3545';
        $priority = 'medium';
    } else {
        $title = 'Removed from Favorites';
        $message = "$client_name removed you from their favorites";
        $icon = 'fa-heart-broken';
        $color = '#6c757d';
        $priority = 'low';
    }
    
    return createNotification(
        $provider_id,
        'favorite',
        $title,
        $message,
        [
            'related_id' => $client_id,
            'related_type' => 'user',
            'icon' => $icon,
            'icon_color' => $color,
            'priority' => $priority,
            'action_url' => 'profile.php',
            'action_label' => 'View Profile',
            'data' => [
                'client_id' => $client_id,
                'client_name' => $client_name,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}

/**
 * Create notification for service update
 *
 * @param int $provider_id Provider ID
 * @param int $service_id Service ID
 * @param string $action 'added' or 'updated'
 * @param string $service_name Service name
 * @return int|false Notification ID
 */
function notifyServiceUpdate($provider_id, $service_id, $action, $service_name = '') {
    if (!isNotificationTypeEnabled($provider_id, 'service_update')) {
        return false;
    }
    
    if ($action === 'added') {
        $title = 'Service Added';
        $message = "Your new service \"$service_name\" has been added";
        $icon = 'fa-plus-circle';
        $priority = 'medium';
    } else {
        $title = 'Service Updated';
        $message = "Your service \"$service_name\" has been updated";
        $icon = 'fa-sync';
        $priority = 'low';
    }
    
    return createNotification(
        $provider_id,
        'service_' . $action,
        $title,
        $message,
        [
            'related_id' => $service_id,
            'related_type' => 'service',
            'icon' => $icon,
            'icon_color' => '#ffc107',
            'priority' => $priority,
            'action_url' => 'services.php?id=' . $service_id,
            'action_label' => 'View Service',
            'data' => [
                'service_id' => $service_id,
                'service_name' => $service_name,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}

/**
 * Create notification for profile view
 *
 * @param int $provider_id Provider ID
 * @param int $viewer_id Client ID viewing profile
 * @param string $viewer_name Client name
 * @return int|false Notification ID
 */
function notifyProfileView($provider_id, $viewer_id, $viewer_name = '') {
    if (!isNotificationTypeEnabled($provider_id, 'system')) {
        return false;
    }
    
    return createNotification(
        $provider_id,
        'profile_view',
        'Profile Viewed',
        "$viewer_name viewed your profile",
        [
            'related_id' => $viewer_id,
            'related_type' => 'user',
            'icon' => 'fa-eye',
            'icon_color' => '#17a2b8',
            'priority' => 'low',
            'action_url' => 'profile.php',
            'action_label' => 'View Your Profile',
            'data' => [
                'viewer_id' => $viewer_id,
                'viewer_name' => $viewer_name,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}

/**
 * Create notification for review received
 *
 * @param int $provider_id Provider ID
 * @param int $review_id Review ID
 * @param string $client_name Client who left review
 * @param float $rating Rating given
 * @return int|false Notification ID
 */
function notifyReviewReceived($provider_id, $review_id, $client_name = '', $rating = 0) {
    if (!isNotificationTypeEnabled($provider_id, 'review')) {
        return false;
    }
    
    $stars = str_repeat('⭐', round($rating));
    
    return createNotification(
        $provider_id,
        'review',
        'New Review Received',
        "New " . round($rating) . "-star review from $client_name",
        [
            'related_id' => $review_id,
            'related_type' => 'review',
            'icon' => 'fa-star',
            'icon_color' => '#ffc107',
            'priority' => 'medium',
            'action_url' => 'reviews.php#review-' . $review_id,
            'action_label' => 'View Review',
            'data' => [
                'review_id' => $review_id,
                'client_name' => $client_name,
                'rating' => $rating,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}

/**
 * Create notification for complaint received
 *
 * @param int $provider_id Provider ID
 * @param int $complaint_id Complaint ID
 * @param string $client_name Client who filed complaint
 * @return int|false Notification ID
 */
function notifyComplaintReceived($provider_id, $complaint_id, $client_name = '') {
    if (!isNotificationTypeEnabled($provider_id, 'complaint')) {
        return false;
    }
    
    return createNotification(
        $provider_id,
        'complaint',
        'New Complaint Filed',
        "New complaint received from $client_name",
        [
            'related_id' => $complaint_id,
            'related_type' => 'complaint',
            'icon' => 'fa-exclamation-triangle',
            'icon_color' => '#dc3545',
            'priority' => 'urgent',
            'action_url' => 'complaints.php?id=' . $complaint_id,
            'action_label' => 'View Complaint',
            'data' => [
                'complaint_id' => $complaint_id,
                'client_name' => $client_name,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]
    );
}
