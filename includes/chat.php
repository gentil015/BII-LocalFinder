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

/**
 * Send a chat message from one user to another.
 *
 * @param int $sender_id
 * @param int $receiver_id
 * @param string $message
 * @return bool True on success
 */
function sendMessage(int $sender_id, int $receiver_id, string $message): bool
{
    if (trim($message) === '') {
        return false;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        return $stmt->execute([$sender_id, $receiver_id, sanitize($message)]);
    } catch (Throwable $e) {
        error_log('sendMessage error: ' . $e->getMessage());
        return false;
    }
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
    $stmt = $db->prepare(
        "SELECT u.id, u.full_name, u.profile_image,
                (SELECT m.created_at FROM messages m
                 WHERE (m.sender_id = u.id AND m.receiver_id = :me)
                    OR (m.sender_id = :me AND m.receiver_id = u.id)
                 ORDER BY m.created_at DESC
                 LIMIT 1) as last_message_time,
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt->execute([$from, $to]);
    } catch (Throwable $e) {
        error_log('markMessagesRead error: ' . $e->getMessage());
    }
}
