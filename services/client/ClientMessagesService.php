<?php

require_once __DIR__ . '/../../repositories/messages/MessageRepository.php';
require_once __DIR__ . '/../../includes/chat.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/functions.php';

class ClientMessagesService
{
    private MessageRepository $repository;

    public function __construct(?MessageRepository $repository = null)
    {
        $this->repository = $repository ?? new MessageRepository();
    }

    public function buildViewModel(PDO $db, int $userId, array $query): array
    {
        $with = isset($query['with']) ? intval($query['with']) : 0;
        $bookingId = isset($query['booking_id']) ? intval($query['booking_id']) : 0;

        $viewData = [
            'with' => $with,
            'booking_id' => $bookingId,
            'convs' => $this->repository->getConversationList($db, $userId),
            'messages' => [],
            'otherUser' => null,
            'bookingTimeline' => [],
        ];

        if ($with <= 0) {
            return $viewData;
        }

        $otherUser = $this->repository->getUserById($db, $with);
        if ($otherUser === null) {
            $viewData['with'] = 0;
            return $viewData;
        }

        $viewData['otherUser'] = $otherUser;
        $viewData['messages'] = $this->repository->getConversationMessages($db, $userId, $with);
        $this->repository->markMessagesRead($db, $with, $userId);

        if ($bookingId > 0) {
            $viewData['bookingTimeline'] = $this->repository->getBookingTimeline($db, $bookingId);
        }

        return $viewData;
    }

    public function getPollResponse(PDO $db, int $userId, int $with, int $bookingId): array
    {
        if ($userId <= 0 || $with <= 0) {
            return ['success' => false, 'message' => 'Invalid session or conversation'];
        }

        return [
            'success' => true,
            'messages' => $this->repository->getConversationMessages($db, $userId, $with),
            'booking_timeline' => $bookingId > 0 ? $this->repository->getBookingTimeline($db, $bookingId) : [],
        ];
    }

    public function handleSubmit(PDO $db, int $userId, array $post, array $files, array $server): array
    {
        $receiverId = isset($post['receiver_id']) ? intval($post['receiver_id']) : 0;
        $msg = sanitize($post['message'] ?? '');
        $bookingId = isset($post['booking_id']) ? intval($post['booking_id']) : 0;
        $messageType = sanitize($post['message_type'] ?? 'text');
        $isAjax = (!empty($server['HTTP_X_REQUESTED_WITH']) && strtolower($server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($post['ajax']) && $post['ajax'] === '1');

        $attachmentPath = null;
        $attachmentType = null;
        $errors = [];

        if (!empty($files['attachment']['name'])) {
            $attachmentPath = saveChatAttachment($files['attachment']);
            if ($attachmentPath === null) {
                $errors[] = 'Failed to upload attachment.';
            } else {
                $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
                if (in_array($ext, ['webm', 'ogg', 'mp3', 'wav'], true)) {
                    $attachmentType = 'audio';
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    $attachmentType = 'image';
                } else {
                    $attachmentType = 'file';
                }
            }
        }

        if ($receiverId <= 0) {
            $errors[] = 'Invalid recipient.';
        }

        if ($messageType === 'text' && $msg === '' && $attachmentPath === null) {
            $errors[] = 'Please enter a message or attach a file.';
        }

        $success = false;
        if (empty($errors)) {
            $result = sendMessage($userId, $receiverId, $msg, $attachmentPath, $attachmentType, $messageType);
            $success = $result !== false;

            if ($success) {
                $this->maybeNotifyProvider($db, $userId, $receiverId, $bookingId, $msg);
            } else {
                $errors[] = 'Failed to send message.';
            }
        }

        $messageData = null;
        if ($success) {
            $messageData = [
                'sender_id' => $userId,
                'receiver_id' => $receiverId,
                'message' => $msg,
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
                'created_at' => date('Y-m-d H:i:s'),
                'message_type' => $messageType,
            ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'message' => $messageData,
            'is_ajax' => $isAjax,
            'redirect' => 'messages.php?with=' . $receiverId . ($bookingId ? '&booking_id=' . $bookingId : ''),
        ];
    }

    private function maybeNotifyProvider(PDO $db, int $senderId, int $receiverId, int $bookingId, string $msg): void
    {
        if (!Mailer::isProviderNotificationEnabled($receiverId, 'chat_message_email')) {
            return;
        }

        $stmt = $db->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$receiverId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$provider || empty($provider['email'])) {
            return;
        }

        $stmt = $db->prepare('SELECT full_name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$senderId]);
        $sender = $stmt->fetch(PDO::FETCH_ASSOC);
        $senderName = $sender['full_name'] ?? 'A client';

        $serviceText = 'General inquiry';
        if ($bookingId > 0) {
            $bstmt = $db->prepare('SELECT service_description FROM bookings WHERE id = ? LIMIT 1');
            $bstmt->execute([$bookingId]);
            $booking = $bstmt->fetch(PDO::FETCH_ASSOC);
            if ($booking && !empty($booking['service_description'])) {
                $serviceText = trim($booking['service_description']);
            }
        }

        $body = "Hello,\n\n";
        $body .= "You have received a new message from {$senderName}.\n";
        $body .= "Service: {$serviceText}.\n\n";
        $body .= "Message:\n{$msg}\n\n";
        $body .= "Please log in to BII LocalFinder to reply.\n";

        Mailer::send(
            $provider['email'],
            "New message from {$senderName}",
            $body,
            false
        );
    }
}
