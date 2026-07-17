<?php
/**
 * Simple chat helper functions for BII LocalFinder
 *
 * Provides wrappers for sending messages and fetching conversations.
 * This file is intentionally lightweight and does not produce any output,
 * it just exposes a few reusable functions which can be called from the
 * front end or other backend components (booking handlers, APIs, etc.).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/event_tracking.php';

function ensureMessagesAttachmentColumnExists(): void
{
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'attachment_path'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL");
        }

        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'attachment_type'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN attachment_type VARCHAR(50) DEFAULT NULL");
        }
    } catch (Throwable $e) {
        error_log('ensureMessagesAttachmentColumnExists error: ' . $e->getMessage());
    }
}

function ensureMessagesAudioColumnsExist(): void
{
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'message_type'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN message_type VARCHAR(50) DEFAULT NULL");
        }

        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'file_path'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN file_path VARCHAR(255) DEFAULT NULL");
        }

        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'file_size'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN file_size INT DEFAULT NULL");
        }

        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'audio_duration'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN audio_duration INT DEFAULT NULL");
        }
    } catch (Throwable $e) {
        error_log('ensureMessagesAudioColumnsExist error: ' . $e->getMessage());
    }
}

/**
 * Send a chat message from one user to another.
 *
 * @param int $sender_id
 * @param int $receiver_id
 * @param string $message
 * @return bool True on success
 */
function ensureChatMetaTablesExist(): void
{
    try {
        $db = Database::getInstance()->getConnection();

        $db->exec("CREATE TABLE IF NOT EXISTS blocked_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blocker_id INT NOT NULL,
            blocked_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_blocker_blocked (blocker_id, blocked_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS muted_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            muted_user_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_user_muted (user_id, muted_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL,
            reported_user_id INT NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('ensureChatMetaTablesExist error: ' . $e->getMessage());
    }
}

function isUserBlocked(int $userId, int $otherId): bool
{
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = ? LIMIT 1");
        $stmt->execute([$userId, $otherId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('isUserBlocked error: ' . $e->getMessage());
        return false;
    }
}

function isChatMuted(int $userId, int $otherId): bool
{
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT 1 FROM muted_chats WHERE user_id = ? AND muted_user_id = ? LIMIT 1");
        $stmt->execute([$userId, $otherId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('isChatMuted error: ' . $e->getMessage());
        return false;
    }
}

function clearChat(int $userA, int $userB): bool
{
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        return $stmt->execute([$userA, $userB, $userB, $userA]);
    } catch (Throwable $e) {
        error_log('clearChat error: ' . $e->getMessage());
        return false;
    }
}

function deleteConversation(int $userA, int $userB): bool
{
    return clearChat($userA, $userB);
}

function blockUser(int $blockerId, int $blockedId): bool
{
    if ($blockerId === $blockedId) {
        return false;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
        return $stmt->execute([$blockerId, $blockedId]);
    } catch (Throwable $e) {
        error_log('blockUser error: ' . $e->getMessage());
        return false;
    }
}

function reportUser(int $reporterId, int $reportedId, string $reason): bool
{
    if ($reporterId === $reportedId || trim($reason) === '') {
        return false;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO reports (reporter_id, reported_user_id, reason) VALUES (?, ?, ?)");
        return $stmt->execute([$reporterId, $reportedId, sanitize($reason)]);
    } catch (Throwable $e) {
        error_log('reportUser error: ' . $e->getMessage());
        return false;
    }
}

function toggleMuteChat(int $userId, int $otherId): bool
{
    try {
        $db = Database::getInstance()->getConnection();
        if (isChatMuted($userId, $otherId)) {
            $stmt = $db->prepare("DELETE FROM muted_chats WHERE user_id = ? AND muted_user_id = ?");
            return $stmt->execute([$userId, $otherId]);
        }

        $stmt = $db->prepare("INSERT IGNORE INTO muted_chats (user_id, muted_user_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $otherId]);
    } catch (Throwable $e) {
        error_log('toggleMuteChat error: ' . $e->getMessage());
        return false;
    }
}

function sendMessage(int $sender_id, int $receiver_id, string $message, string $attachment_path = null, string $attachment_type = null, string $message_type = 'text'): bool
{
    if (trim($message) === '' && empty($attachment_path) && $message_type === 'text') {
        return false;
    }

    try {
        $db = Database::getInstance()->getConnection();

        ensureChatMetaTablesExist();

        if (isUserBlocked($receiver_id, $sender_id) || isUserBlocked($sender_id, $receiver_id)) {
            return false;
        }

        if ($attachment_path !== null || $attachment_type !== null || $message_type !== 'text') {
            ensureMessagesAttachmentColumnExists();
            ensureMessagesAudioColumnsExist();
        }

        $sql = "INSERT INTO messages (sender_id, receiver_id, message";
        $params = [$sender_id, $receiver_id, sanitize($message)];

        if ($attachment_path !== null) {
            $sql .= ", attachment_path";
            $params[] = sanitize($attachment_path);
        }

        if ($attachment_type !== null) {
            $sql .= ", attachment_type";
            $params[] = sanitize($attachment_type);
        }

        if ($message_type !== 'text') {
            $sql .= ", message_type";
            $params[] = sanitize($message_type);
        }

        $sql .= ") VALUES (?, ?, ?";
        if ($attachment_path !== null) {
            $sql .= ", ?";
        }
        if ($attachment_type !== null) {
            $sql .= ", ?";
        }
        if ($message_type !== 'text') {
            $sql .= ", ?";
        }
        $sql .= ")";

        $stmt = $db->prepare($sql);
        if ($stmt->execute($params)) {
            $messageId = $db->lastInsertId();

            // Track message sent event
            trackEvent('send_message', 'message', $messageId, [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message_type' => $message_type,
                'has_attachment' => !empty($attachment_path)
            ], $sender_id);

            return $messageId;
        }
        return false;
    } catch (Throwable $e) {
        error_log('sendMessage error: ' . $e->getMessage());
        return false;
    }
}

function sendAudioMessage(int $sender_id, int $receiver_id, string $file_path, int $file_size, int $duration, string $message = ''): bool
{
    try {
        $db = Database::getInstance()->getConnection();
        ensureMessagesAudioColumnsExist();

        // Verify file actually exists on disk before saving to DB
        $fullPath = __DIR__ . '/../' . $file_path;
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            error_log("Audio file does not exist at path: " . $fullPath);
            return false;
        }

        error_log("Inserting audio message: sender=$sender_id, receiver=$receiver_id, file=$file_path, size=$file_size, duration=$duration");

        $sql = "INSERT INTO messages (sender_id, receiver_id, message, message_type, file_path, file_size, audio_duration) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        $result = $stmt->execute([
            $sender_id,
            $receiver_id,
            sanitize($message),
            'audio',
            sanitize($file_path),
            $file_size,
            $duration
        ]);

        error_log("Audio message insert result: " . ($result ? 'success' : 'failed'));

        return $result;
    } catch (Throwable $e) {
        error_log('sendAudioMessage error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Validate if a file path exists for a message
 */
function isMessageFileValid(string $file_path): bool
{
    if (empty($file_path)) {
        return false;
    }
    $fullPath = __DIR__ . '/../' . $file_path;
    return file_exists($fullPath) && is_file($fullPath) && is_readable($fullPath);
}

/**
 * Retrieve the list of conversation partners for a given user.
 *
 * @param int $user_id
 * @return array Array of user rows (id, full_name, profile_image) sorted by last message time
 */
function getConversationList(int $user_id): array
{
    $db = Database::getInstance()->getConnection();

    // Ensure optional columns exist before querying, to avoid SQL errors on older installs.
    ensureMessagesAudioColumnsExist();

    $stmt = $db->prepare(
        "SELECT u.id, u.full_name, u.profile_image,
                (SELECT m.created_at FROM messages m
                 WHERE (m.sender_id = u.id AND m.receiver_id = :me)
                    OR (m.sender_id = :me AND m.receiver_id = u.id)
                 ORDER BY m.created_at DESC
                 LIMIT 1) as last_message_time,
                (SELECT m.message FROM messages m
                 WHERE (m.sender_id = u.id AND m.receiver_id = :me)
                    OR (m.sender_id = :me AND m.receiver_id = u.id)
                 ORDER BY m.created_at DESC
                 LIMIT 1) as last_message,
                (SELECT m.message_type FROM messages m
                 WHERE (m.sender_id = u.id AND m.receiver_id = :me)
                    OR (m.sender_id = :me AND m.receiver_id = u.id)
                 ORDER BY m.created_at DESC
                 LIMIT 1) as last_message_type,
                (SELECT COUNT(*) FROM messages m2
                 WHERE m2.sender_id = u.id AND m2.receiver_id = :me AND m2.is_read = 0) as unread_count
         FROM users u
         WHERE u.id IN (
             SELECT CASE WHEN sender_id = :me THEN receiver_id ELSE sender_id END
             FROM messages
             WHERE sender_id = :me OR receiver_id = :me
         )
         ORDER BY last_message_time DESC
        "
    );
    $stmt->execute(['me' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all messages exchanged between two users, sorted ascending by time.
 *
 * @param int $userA
 * @param int $userB
 * @return array
 */
function getConversationMessages(int $userA, int $userB): array
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        "SELECT m.*, u.full_name as sender_name, u.profile_image as sender_image
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at ASC"
    );
    $stmt->execute([$userA, $userB, $userB, $userA]);
    $origin = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $origin;
}

/**
 * Mark all messages sent from one user to another as read.
 *
 * @param int $from
 * @param int $to
 * @return void
 */
function markMessagesRead(int $from, int $to): void
{
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $result = $stmt->execute([$from, $to]);

        if ($result && $stmt->rowCount() > 0) {
            // Track message read event
            trackEvent('message_read', 'user', $from, [
                'sender_id' => $from,
                'receiver_id' => $to,
                'messages_read_count' => $stmt->rowCount()
            ], $to);
        }
    } catch (Throwable $e) {
        error_log('markMessagesRead error: ' . $e->getMessage());
    }
}

/**
 * Build a booking timeline for chat display.
 *
 * @param int $booking_id
 * @return array
 */
function getBookingTimeline(int $booking_id): array
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        return [];
    }

    $timeline = [];

    $timeline[] = [
        'status' => 'created',
        'label' => 'Booking Created',
        'time' => $booking['created_at'] ?? null,
    ];

    if (!empty($booking['responded_at'])) {
        $timeline[] = [
            'status' => 'responded',
            'label' => 'Provider Responded',
            'time' => $booking['responded_at'],
        ];
    }

    switch ($booking['status']) {
        case 'pending':
            $timeline[] = [
                'status' => 'pending',
                'label' => 'Awaiting response',
                'time' => $booking['updated_at'] ?? null,
            ];
            break;
        case 'confirmed':
            $timeline[] = [
                'status' => 'confirmed',
                'label' => 'Booking Confirmed',
                'time' => $booking['updated_at'] ?? null,
            ];
            break;
        case 'completed':
            $timeline[] = [
                'status' => 'completed',
                'label' => 'Booking Completed',
                'time' => $booking['updated_at'] ?? null,
            ];
            break;
        case 'cancelled':
            $timeline[] = [
                'status' => 'cancelled',
                'label' => 'Booking Cancelled',
                'time' => $booking['updated_at'] ?? null,
            ];
            break;
    }

    if (!empty($booking['payment_status']) && $booking['payment_status'] !== 'pending') {
        $timeline[] = [
            'status' => 'payment',
            'label' => 'Payment ' . ucfirst($booking['payment_status']),
            'time' => $booking['updated_at'] ?? null,
        ];
    }

    return $timeline;
}
