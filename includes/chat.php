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

function ensureMessagesAttachmentColumnExists(): void
{
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SHOW COLUMNS FROM messages LIKE 'attachment_path'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL");
        }
    } catch (Throwable $e) {
        error_log('ensureMessagesAttachmentColumnExists error: ' . $e->getMessage());
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
function sendMessage(int $sender_id, int $receiver_id, string $message, string $attachment_path = null): bool
{
    if (trim($message) === '' && empty($attachment_path)) {
        return false;
    }

    try {
        $db = Database::getInstance()->getConnection();

        if ($attachment_path !== null) {
            ensureMessagesAttachmentColumnExists();
        }

        $sql = "INSERT INTO messages (sender_id, receiver_id, message";
        $params = [$sender_id, $receiver_id, sanitize($message)];

        if ($attachment_path !== null) {
            $sql .= ", attachment_path";
            $params[] = sanitize($attachment_path);
        }

        $sql .= ") VALUES (?, ?, ?";
        if ($attachment_path !== null) {
            $sql .= ", ?";
        }
        $sql .= ")";

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
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
        $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt->execute([$from, $to]);
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
