<?php

require_once __DIR__ . '/../../includes/chat.php';

class MessageRepository
{
    public function getConversationList(PDO $db, int $userId): array
    {
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
             ORDER BY last_message_time DESC"
        );
        $stmt->execute(['me' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConversationMessages(PDO $db, int $userA, int $userB): array
    {
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

    public function markMessagesRead(PDO $db, int $from, int $to): void
    {
        try {
            $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $stmt->execute([$from, $to]);

            if ($stmt->rowCount() > 0) {
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

    public function getUserById(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT id, full_name, profile_image FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user === false ? null : $user;
    }

    public function getProviderByUserId(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare('SELECT id, user_id FROM service_providers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        return $provider === false ? null : $provider;
    }

    public function getServiceByIdForProvider(PDO $db, int $serviceId, int $providerId): ?array
    {
        $stmt = $db->prepare(
            'SELECT id, name as service_name, description as service_description, price, min_price, max_price, negotiable
             FROM provider_services
             WHERE id = ? AND provider_id = ?
             LIMIT 1'
        );
        $stmt->execute([$serviceId, $providerId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        return $service === false ? null : $service;
    }

    public function getBookingTimeline(PDO $db, int $bookingId): array
    {
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
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

        return $timeline;
    }
}
