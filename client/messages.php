<?php
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors, log them instead
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat.php';
require_once '../includes/mailer.php';

// API poll endpoint for live update
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    try {
        $me = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $with = isset($_GET['with']) ? intval($_GET['with']) : 0;
        
        if ($me > 0 && $with > 0) {
            $messages = getConversationMessages($me, $with);
            $bookingTimelineData = [];
            $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
            if ($booking_id > 0) {
                $bookingTimelineData = getBookingTimeline($booking_id);
            }
            echo json_encode(['success' => true, 'messages' => $messages, 'booking_timeline' => $bookingTimelineData]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid session or conversation']);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('Poll error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

// ensure only clients use this version
if (!isLoggedIn()) {
    redirect('login.php');
}
if (isProvider()) {
    // providers should use their own messaging page
    redirect('../provider/messages.php' . (isset($_GET['with']) ? '?with=' . intval($_GET['with']) : ''));
}

$me = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

function saveChatAttachment(array $file): ?string
{
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // File size limits (in bytes)
    $maxSizeAudio = 10 * 1024 * 1024; // 10MB for audio
    $maxSizeImage = 5 * 1024 * 1024;  // 5MB for images
    $maxSizeDocument = 5 * 1024 * 1024; // 5MB for documents

    $allowedExtensions = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','txt','webm'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return null;
    }

    // Validate file size based on type
    $fileSize = $file['size'] ?? 0;
    if (in_array($ext, ['webm','ogg','mp3','wav'], true)) {
        if ($fileSize > $maxSizeAudio) {
            return null; // File too large
        }
    } elseif (in_array($ext, ['jpg','jpeg','png','gif'], true)) {
        if ($fileSize > $maxSizeImage) {
            return null; // File too large
        }
    } else {
        if ($fileSize > $maxSizeDocument) {
            return null; // File too large
        }
    }

    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'audio/webm'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/chat/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('chat_', true) . '.' . $ext;
    $target = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return 'uploads/chat/' . $filename;
    }

    return null;
}

// handle new message form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id']) && (isset($_POST['message']) || !empty($_FILES['attachment']['name']))) {
    $receiver_id = intval($_POST['receiver_id']);
    $msg = sanitize($_POST['message']);
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

    $attachmentPath = null;
    $attachmentType = null;
    if (!empty($_FILES['attachment']['name'])) {
        $attachmentPath = saveChatAttachment($_FILES['attachment']);
        if ($attachmentPath !== null) {
            $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['webm','ogg','mp3','wav'], true)) {
                $attachmentType = 'audio';
            } elseif (in_array($ext, ['jpg','jpeg','png','gif'], true)) {
                $attachmentType = 'image';
            } else {
                $attachmentType = 'file';
            }
        }
    }

    $result = false;
    if ($msg !== '' || $attachmentPath !== null) {
        $result = sendMessage($me, $receiver_id, $msg, $attachmentPath, $attachmentType);

        // optionally notify provider via email/push/sms
        if (Mailer::isProviderNotificationEnabled($receiver_id, 'chat_message_email')) {
            $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$receiver_id]);
            $prov = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($prov && !empty($prov['email'])) {
                $clientStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                $clientStmt->execute([$me]);
                $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
                $clientName = $client['full_name'] ?? 'A client';

                $serviceText = 'General inquiry';
                if ($booking_id) {
                    $bstmt = $db->prepare("SELECT service_description FROM bookings WHERE id = ?");
                    $bstmt->execute([$booking_id]);
                    $booking = $bstmt->fetch(PDO::FETCH_ASSOC);
                    if ($booking) {
                        $serviceText = trim($booking['service_description']) ?: 'Booked service';
                    }
                }

                $body = "Hello,\n\n";
                $body .= "You have received a new message from {$clientName}.\n";
                $body .= "Service: {$serviceText}.\n\n";
                $body .= "Message:\n{$msg}\n\n";
                $body .= "Please log in to BII LocalFinder to reply.\n";

                Mailer::send(
                    $prov['email'],
                    "New message from {$clientName}",
                    $body,
                    false
                );
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result,
            'message' => $result ? ['sender_id' => $me, 'receiver_id' => $receiver_id, 'message' => $msg, 'attachment_path' => $attachmentPath, 'attachment_type' => $attachmentType, 'created_at' => date('Y-m-d H:i:s')] : null
        ]);
        exit;
    }

    // redirect to avoid form resubmission
    redirect('messages.php?with=' . $receiver_id . ($booking_id ? '&booking_id=' . $booking_id : ''));
}

// determine active conversation partner
$with = isset($_GET['with']) ? intval($_GET['with']) : 0;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

try {
    $convs = getConversationList($me) ?: [];
    if (!is_array($convs)) {
        $convs = [];
    }
} catch (Throwable $e) {
    error_log('getConversationList error: ' . $e->getMessage());
    $convs = [];
}

$messages = [];
$otherUser = null;
$bookingTimeline = [];
if ($with) {
    // fetch other user info
    $stmt = $db->prepare("SELECT id, full_name FROM users WHERE id = ?");
    $stmt->execute([$with]);
    $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($otherUser) {
        // mark incoming as read
        markMessagesRead($with, $me);
        // load conversation
        $messages = getConversationMessages($me, $with);
        if ($booking_id) {
            $bookingTimeline = getBookingTimeline($booking_id);
        }
    } else {
        $with = 0; // invalid user
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Chat</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            overflow: hidden;
        }

        .main-content {
            margin-left: 80px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .sidebar:not(.collapsed) + .main-content,
        .sidebar:not(.collapsed) ~ .main-content {
            margin-left: 260px;
        }

        .chat-container {
            display: flex;
            height: 100vh;
            background: white;
        }

        /* Conversations Panel */
        .conversations-panel {
            width: 360px;
            border-right: 1px solid #e5e5e5;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .conversations-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e5e5;
        }

        .conversations-header h2 {
            font-size: 32px;
            font-weight: 600;
            margin: 0 0 16px 0;
            color: #111;
        }

        .search-box {
            position: relative;
            margin-bottom: 0;
        }

        .search-box input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1px solid #e5e5e5;
            border-radius: 24px;
            font-size: 15px;
            background: #f0f0f0;
        }

        .search-box input:focus {
            outline: none;
            background: white;
            border-color: #0a58ca;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            list-style: none;
        }

        .conversation-item {
            padding: 8px 8px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 8px;
            border-radius: 12px;
            position: relative;
        }

        .conversation-item:hover {
            background: #f0f0f0;
        }

        .conversation-item.active {
            background: #e7f3ff;
        }

        .conversation-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0a58ca, #0d6efd);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 600;
            flex-shrink: 0;
            position: relative;
        }

        .conversation-avatar.online::after {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            background: #31a24c;
            border: 3px solid white;
            border-radius: 50%;
            bottom: 0;
            right: 0;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-size: 15px;
            font-weight: 500;
            color: #111;
            margin-bottom: 4px;
        }

        .conversation-preview {
            font-size: 13px;
            color: #65676b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: #0a58ca;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #65676b;
            padding: 20px;
            text-align: center;
        }

        /* Chat Panel */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #65676b;
        }

        .chat-empty i {
            font-size: 80px;
            color: #e5e5e5;
            margin-bottom: 16px;
        }

        /* Chat Header */
        .chat-header {
            padding: 12px 20px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
        }

        .chat-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0a58ca, #0d6efd);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            position: relative;
        }

        .chat-header-avatar.online::after {
            content: '';
            position: absolute;
            width: 12px;
            height: 12px;
            background: #31a24c;
            border: 2px solid white;
            border-radius: 50%;
            bottom: 0;
            right: 0;
        }

        .chat-header-name {
            font-weight: 500;
            color: #111;
        }

        .chat-header-status {
            font-size: 12px;
            color: #65676b;
        }

        .chat-header-actions {
            display: flex;
            gap: 12px;
        }

        .header-btn {
            background: none;
            border: none;
            color: #0a58ca;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .header-btn:hover {
            background: #f0f0f0;
        }

        /* Booking Info */
        .booking-info {
            padding: 12px 20px;
            background: #e7f3ff;
            border-bottom: 1px solid #b3d9ff;
            border-radius: 0;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #0c5aa0;
        }

        .booking-info i {
            font-size: 18px;
        }

        .booking-timeline {
            padding: 12px 20px;
            border: 1px solid #d7e8ff;
            background: #f2f8ff;
            margin: 0 0 8px 0;
            border-radius: 10px;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
        }

        .timeline-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 6px;
            background: #0a58ca;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-size: 13px;
            font-weight: 600;
            color: #0a58ca;
        }

        .timeline-time {
            font-size: 12px;
            color: #666;
        }

        .attachment-link {
            margin-top: 8px;
            font-size: 13px;
        }

        .voice-badge {
            display: inline-block;
            font-size: 12px;
            color: #ffffff;
            background: #1e40af;
            border-radius: 12px;
            padding: 2px 8px;
            margin-bottom: 6px;
            margin-top: 4px;
            letter-spacing: 0.2px;
        }

        .attachment-link {
            margin-top: 8px;
            font-size: 13px;
        }

        .attachment-link a {
            color: #0a58ca;
            text-decoration: none;
        }

        .attachment-link a:hover {
            text-decoration: underline;
        }

        .booking-info a {
            color: #0a58ca;
            text-decoration: none;
            font-weight: 600;
        }

        .booking-info a:hover {
            text-decoration: underline;
        }

        /* Messages Area */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: white;
        }

        .message-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 8px;
        }

        .message {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            margin-bottom: 2px;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.sent .message-bubble {
            background: #0a58ca;
            color: white;
            border-radius: 18px 4px 18px 18px;
        }

        .message.received .message-bubble {
            background: #e5e5ea;
            color: #111;
            border-radius: 4px 18px 18px 18px;
        }

        .message-bubble {
            max-width: 55%;
            padding: 8px 12px;
            border-radius: 18px;
            font-size: 15px;
            line-height: 1.4;
            word-wrap: break-word;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-time {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
            padding: 0 8px;
        }

        .message.sent .message-time {
            text-align: right;
        }

        /* Message Input Area */
        .message-input-area {
            padding: 12px 20px 16px 20px;
            border-top: 1px solid #e5e5e5;
            display: flex;
            gap: 12px;
            align-items: flex-end;
            background: white;
        }

        .input-form {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            flex: 1;
        }

        input[name="message"] {
            flex: 1;
            border: 1px solid #e5e5e5;
            border-radius: 24px;
            padding: 10px 16px;
            font-size: 15px;
            font-family: inherit;
            resize: none;
            max-height: 100px;
        }

        input[name="message"]:focus {
            outline: none;
            border-color: #0a58ca;
            box-shadow: 0 0 0 3px rgba(10, 88, 202, 0.1);
        }

        .send-btn {
            background: #0a58ca;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 18px;
        }

        .send-btn:hover {
            background: #0856c1;
        }

        .send-btn:active {
            transform: scale(0.95);
        }

        /* Scrollbar */
        .conversations-list::-webkit-scrollbar,
        .messages-area::-webkit-scrollbar {
            width: 8px;
        }

        .conversations-list::-webkit-scrollbar-track,
        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .conversations-list::-webkit-scrollbar-thumb,
        .messages-area::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 4px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover,
        .messages-area::-webkit-scrollbar-thumb:hover {
            background: #999;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .conversations-panel {
                width: 100%;
                position: absolute;
                height: 100%;
                z-index: 10;
            }
            .conversations-panel.hidden {
                display: none;
            }
            .chat-panel {
                width: 100%;
            }
            .message-bubble {
                max-width: 85%;
            }
            .chat-header {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="chat-container">
            <!-- Conversations Panel -->
    <div class="conversations-panel" id="conversationsPanel">
        <div class="conversations-header">
            <h2>Messages</h2>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search conversations..." id="searchInput">
            </div>
        </div>

        <?php if (!empty($convs)): ?>
            <ul class="conversations-list">
                <?php foreach ($convs as $c): ?>
                    <li class="conversation-item <?php if ($with === (int)$c['id']) echo 'active'; ?>" onclick="loadConversation(<?php echo $c['id']; ?>)">
                        <div class="conversation-avatar online">
                            <?php echo strtoupper(substr($c['full_name'], 0, 1)); ?>
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                            <div class="conversation-preview">
                                <?php 
                                    if ($c['unread_count'] > 0) {
                                        echo '📨 New messages';
                                    } elseif (!empty($c['last_message_type']) && $c['last_message_type'] === 'audio') {
                                        echo '🎤 Voice note';
                                    } elseif (!empty($c['last_message'])) {
                                        echo htmlspecialchars($c['last_message']);
                                    } else {
                                        echo 'Active now';
                                    }
                                ?>
                            </div>
                        </div>
                        <?php if ($c['unread_count'] > 0): ?>
                            <div class="unread-badge"><?php echo $c['unread_count']; ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <p>No conversations yet</p>
                <small>Create a booking to start chatting</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chat Panel -->
    <div class="chat-panel">
        <?php if ($with && $otherUser): ?>
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-header-info">
                    <div class="chat-header-avatar online">
                        <?php echo strtoupper(substr($otherUser['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="chat-header-name"><?php echo htmlspecialchars($otherUser['full_name']); ?></div>
                        <div class="chat-header-status">Active now</div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button class="header-btn" title="Call"><i class="fas fa-phone"></i></button>
                    <button class="header-btn" title="Video call"><i class="fas fa-video"></i></button>
                    <button class="header-btn" title="More"><i class="fas fa-ellipsis-v"></i></button>
                </div>
            </div>

            <!-- Booking Info Banner -->
            <?php if ($booking_id): ?>
                <div class="booking-info">
                    <i class="fas fa-info-circle"></i>
                    <div>Booking <strong>#<?php echo intval($booking_id); ?></strong> active · <a href="booking-details.php?id=<?php echo intval($booking_id); ?>">View details</a></div>
                </div>
            <?php endif; ?>

            <!-- Booking Timeline -->
            <?php if (!empty($bookingTimeline)): ?>
                <div class="booking-timeline" id="bookingTimeline">
                    <?php foreach ($bookingTimeline as $item): ?>
                        <div class="timeline-item timeline-<?php echo htmlspecialchars($item['status']); ?>">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-title"><?php echo htmlspecialchars($item['label']); ?></div>
                                <?php if (!empty($item['time'])): ?>
                                    <div class="timeline-time"><?php echo date('d M Y H:i', strtotime($item['time'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Messages Area -->
            <div class="messages-area" id="messagesArea">
                <?php if (empty($messages)): ?>
                    <div style="margin: auto; text-align: center; color: #999;">
                        <i class="fas fa-comment-dots" style="font-size: 48px; margin-bottom: 12px; opacity: 0.3;"></i>
                        <p>No messages yet. Say hello!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="message-group">
                            <div class="message <?php echo $m['sender_id'] == $me ? 'sent' : 'received'; ?>">
                                <div class="message-bubble">
                                <?php echo nl2br(htmlspecialchars($m['message'])); ?>
                                <?php
                                    $isAudioMessage = (!empty($m['message_type']) && $m['message_type'] === 'audio')
                                        || (!empty($m['attachment_type']) && $m['attachment_type'] === 'audio');
                                    $attachmentPath = !empty($m['file_path']) ? $m['file_path'] : ($m['attachment_path'] ?? null);
                                ?>
                                <?php if ($isAudioMessage): ?>
                                    <div class="voice-badge">Voice note</div>
                                <?php endif; ?>
                                <?php if (!empty($attachmentPath)): ?>
                                    <?php $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION)); ?>
                                    <?php if (in_array($ext, ['jpg','jpeg','png','gif'], true)): ?>
                                        <div class="attachment-preview">
                                            <img src="../<?php echo htmlspecialchars($attachmentPath); ?>" alt="Attachment preview" style="max-width: 180px; max-height: 180px; border-radius: 8px; margin-top: 6px;" />
                                        </div>
                                    <?php elseif (in_array($ext, ['webm','ogg','mp3','wav'], true)): ?>
                                        <div class="attachment-preview" style="margin-top: 6px;">
                                            <audio controls style="width: 100%; max-width: 250px;">
                                                <source src="../<?php echo htmlspecialchars($attachmentPath); ?>" type="audio/<?php echo htmlspecialchars($ext === 'webm' ? 'webm' : ($ext === 'ogg' ? 'ogg' : ($ext === 'mp3' ? 'mpeg' : 'wav'))); ?>">
                                                Your browser does not support the audio element.
                                            </audio>
                                        </div>
                                    <?php endif; ?>
                                    <div class="attachment-link">
                                        <a href="../<?php echo htmlspecialchars($attachmentPath); ?>" target="_blank" rel="noopener noreferrer">
                                            📎 View attachment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            </div>
                            <div class="message-time"><?php echo date('H:i', strtotime($m['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input Area -->
            <div class="typing-indicator" id="typingIndicator" style="display:none; padding: 8px 20px; color: #0a58ca; font-size: 14px;">
                <i class="fas fa-ellipsis-h fa-fw"></i> Typing...
            </div>
            <div class="message-input-area">
                <form method="POST" class="input-form" id="messageForm" enctype="multipart/form-data">
                    <input type="hidden" name="receiver_id" value="<?php echo $with; ?>">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="hidden" name="ajax" value="1">

                    <div class="input-row" style="display:flex; align-items:center; gap:8px; width:100%;">
                        <button type="button" id="attachButton" class="header-btn" title="Attach file"><i class="fas fa-paperclip"></i></button>
                        <input type="file" name="attachment" id="attachmentInput" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.webm" style="display:none;" title="Attach file">
                        <input type="text" name="message" placeholder="Aa" autocomplete="off" id="messageTextField" style="flex:1;">
                        <button type="button" id="recordButton" class="header-btn" title="Record voice message"><i class="fas fa-microphone"></i></button>
                        <button type="submit" class="send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>
                    </div>
                    <div id="recordStatus" style="font-size: 12px; color: #0a58ca; margin-top: 4px;">Hold to record</div>
                </form>
            </div>
        <?php else: ?>
            <div class="chat-empty">
                <div>
                    <i class="fas fa-comments"></i>
                    <p style="font-size: 18px; margin-top: 12px;">Select a conversation to start chatting</p>
                    <small>Choose a provider from the list or create a new booking</small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function loadConversation(userId) {
    window.location.href = 'messages.php?with=' + userId;
}

// Auto-scroll to bottom on load
window.addEventListener('load', function() {
    const messagesArea = document.getElementById('messagesArea');
    if (messagesArea) {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
});

// Focus input on load
window.addEventListener('load', function() {
    const input = document.querySelector('input[name="message"]');
    if (input) input.focus();
});

// Typing indicator state
let typingTimer;
const typingIndicatorEl = document.getElementById('typingIndicator');
const messageTextField = document.getElementById('messageTextField');
if (messageTextField) {
    messageTextField.addEventListener('input', function() {
        if (typingIndicatorEl) {
            typingIndicatorEl.style.display = 'block';
        }
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            if (typingIndicatorEl) {
                typingIndicatorEl.style.display = 'none';
            }
        }, 1200);
    });
}

// Voice recording support (click to start, click send to finish)
let mediaRecorder = null;
let mediaStream = null;
let audioChunks = [];
let isRecording = false;
let recordingStartTime = 0;
let recordingMaxDuration = 60000; // 60 seconds in milliseconds
let recordingTimer = null;
let recordingInterval = null;
const recordButton = document.getElementById('recordButton');
const recordStatus = document.getElementById('recordStatus');
const attachButton = document.getElementById('attachButton');
const attachmentInput = document.getElementById('attachmentInput');
const sendButton = document.querySelector('.send-btn');

function formatDuration(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function updateRecordingStatus() {
    if (!recordStatus) return;
    if (!isRecording) {
        recordStatus.textContent = 'Hold to record';
        return;
    }

    const elapsed = Date.now() - recordingStartTime;
    recordStatus.textContent = `Recording ${formatDuration(elapsed)} — tap send to finish`;
}

function setRecordingState(state) {
    isRecording = state;
    if (recordStatus) {
        recordStatus.textContent = state ? 'Recording 0:00 — tap send to finish' : 'Hold to record';
    }
    if (recordButton) {
        recordButton.style.background = state ? '#e53e3e' : 'transparent';
        recordButton.style.color = state ? 'white' : '#1e3a8a';
        recordButton.innerHTML = state ? '<i class="fas fa-stop"></i>' : '<i class="fas fa-microphone"></i>';
    }
    if (sendButton) {
        sendButton.title = state ? 'Stop recording and send' : 'Send';
    }
}

function sendVoiceMessage(blob, durationSec) {
    const maxVoiceSize = 10 * 1024 * 1024; // 10MB
    if (blob.size > maxVoiceSize) {
        alert('Voice message is too large (max 10MB). Recording was too long.');
        return;
    }

    const filename = 'voice_' + Date.now() + '.webm';
    const file = new File([blob], filename, { type: 'audio/webm' });

    const formData = new FormData();
    formData.append('receiver_id', '<?php echo $with; ?>');
    formData.append('duration', durationSec);
    formData.append('voice', file);

    fetch('../upload-voice.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.message) {
            const messagesArea = document.getElementById('messagesArea');
            const group = document.createElement('div');
            group.className = 'message-group';

            const audioHtml = `<div class="attachment-preview" style="margin-top:6px;"><audio controls style="width:100%; max-width:250px;"><source src="../${data.message.file_path || data.message.attachment_path}" type="audio/webm">Your browser does not support audio playback.</audio></div>`;
            const voiceBadgeHtml = `<div class="voice-badge">Voice note</div>`;
            const attachmentLink = `<div class="attachment-link"><a href="../${data.message.file_path || data.message.attachment_path}" target="_blank" rel="noopener noreferrer">📎 View attachment</a></div>`;

            group.innerHTML = `
                <div class="message sent">
                    <div class="message-bubble">
                        ${voiceBadgeHtml}
                        ${audioHtml}
                        ${attachmentLink}
                    </div>
                </div>
                <div class="message-time">${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
            `;
            if (messagesArea) {
                messagesArea.appendChild(group);
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }
    })
    .catch(console.error);
}

function startRecording() {
    if (isRecording) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Audio recording is not supported by your browser.');
        return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true })
    .then(stream => {
        mediaStream = stream;
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        recordingStartTime = Date.now();

        mediaRecorder.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) audioChunks.push(e.data);
        };

        mediaRecorder.onstop = function() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                mediaStream = null;
            }
            clearTimeout(recordingTimer);
            clearInterval(recordingInterval);
            if (audioChunks.length > 0) {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const durationSec = mediaRecorder._recordingDuration || Math.round((Date.now() - recordingStartTime) / 1000);
                sendVoiceMessage(audioBlob, durationSec);
            }
            setRecordingState(false);
        };

        mediaRecorder.start();
        setRecordingState(true);

        recordingInterval = setInterval(updateRecordingStatus, 500);

        // Auto-stop recording after max duration
        recordingTimer = setTimeout(function() {
            if (isRecording && mediaRecorder) {
                mediaRecorder._recordingDuration = Math.round(recordingMaxDuration / 1000);
                mediaRecorder.stop();
                alert('Maximum recording duration (60 seconds) reached. Recording stopped.');
            }
        }, recordingMaxDuration);
    })
    .catch(err => {
        console.error('Could not start recording', err);
        alert('Unable to access microphone. Please allow access and try again.');
    });
}

function stopRecordingAndSend() {
    if (!isRecording || !mediaRecorder) return;
    const durationSec = Math.round((Date.now() - recordingStartTime) / 1000);
    mediaRecorder.stop();
    // send will happen in onstop handler
    // pass duration via global variable so handler can use it
    mediaRecorder._recordingDuration = durationSec;
}

function cancelRecording() {
    if (!isRecording) return;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    audioChunks = [];
    clearTimeout(recordingTimer);
    clearInterval(recordingInterval);
    setRecordingState(false);
}

if (attachButton && attachmentInput) {
    attachButton.addEventListener('click', function() {
        attachmentInput.click();
    });

    attachmentInput.addEventListener('change', function() {
        if (attachmentInput.files && attachmentInput.files.length > 0) {
            const fileName = attachmentInput.files[0].name;
            if (recordStatus) {
                recordStatus.textContent = `Selected: ${fileName}`;
            }
        }
    });
}

if (recordButton) {
    recordButton.addEventListener('click', function() {
        if (isRecording) {
            stopRecordingAndSend();
        } else {
            startRecording();
        }
    });
}

// Search conversations
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('.conversation-item').forEach(item => {
        const name = item.querySelector('.conversation-name').textContent.toLowerCase();
        item.style.display = name.includes(search) ? '' : 'none';
    });
});

// AJAX send message (no redirect)
const messageForm = document.getElementById('messageForm');
if (messageForm) {
    messageForm.addEventListener('submit', function(event) {
        // If we're recording, clicking send should stop + send the voice note.
        if (isRecording) {
            event.preventDefault();
            stopRecordingAndSend();
            return;
        }

        event.preventDefault();
        const formData = new FormData(messageForm);
        const messageText = (formData.get('message') || '').trim();
        const attachmentFile = formData.get('attachment');
        
        if (!messageText && (!attachmentFile || attachmentFile.size === 0)) return;

        // Validate file size client-side
        if (attachmentFile && attachmentFile.size > 0) {
            const maxSizeAudio = 10 * 1024 * 1024; // 10MB
            const maxSizeImage = 5 * 1024 * 1024;  // 5MB
            const maxSizeDoc = 5 * 1024 * 1024;    // 5MB
            const filename = attachmentFile.name.toLowerCase();
            let maxSize = maxSizeDoc;
            
            if (/\.(webm|ogg|mp3|wav)$/i.test(filename)) {
                maxSize = maxSizeAudio;
            } else if (/\.(jpg|jpeg|png|gif)$/i.test(filename)) {
                maxSize = maxSizeImage;
            }
            
            if (attachmentFile.size > maxSize) {
                alert('File is too large. Maximum size: ' + (maxSize / (1024 * 1024)).toFixed(1) + 'MB');
                return;
            }
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(data => {
            if (data.success) {
                const messagesArea = document.getElementById('messagesArea');
                const group = document.createElement('div');
                group.className = 'message-group';

                let attachmentHtml = '';
                let voiceBadgeHtml = '';
                const attachmentPath = data.message ? (data.message.file_path || data.message.attachment_path) : null;
                const messageType = data.message ? (data.message.message_type || data.message.attachment_type) : null;

                if (attachmentPath) {
                    const ext = attachmentPath.split('.').pop().toLowerCase();
                    if (['jpg','jpeg','png','gif'].includes(ext)) {
                        attachmentHtml += `<div class="attachment-preview"><img src="../${attachmentPath}" alt="Attachment preview" style="max-width:180px;max-height:180px;border-radius:8px;margin-top:6px;" /></div>`;
                    } else if (['webm','ogg','mp3','wav'].includes(ext)) {
                        const audioType = ext === 'mp3' ? 'mpeg' : ext;
                        attachmentHtml += `<div class="attachment-preview" style="margin-top:6px;"><audio controls style="width:100%; max-width:250px;"><source src="../${attachmentPath}" type="audio/${audioType}">Your browser does not support audio playback.</audio></div>`;
                    }
                    if (messageType === 'audio') {
                        voiceBadgeHtml = '<div class="voice-badge">Voice note</div>';
                    }
                    attachmentHtml += `<div class="attachment-link"><a href="../${attachmentPath}" target="_blank" rel="noopener noreferrer">📎 View attachment</a></div>`;
                }

                group.innerHTML = `
                    <div class="message sent">
                        <div class="message-bubble">
                            ${messageText.replace(/\n/g,'<br>')}
                            ${voiceBadgeHtml}
                            ${attachmentHtml}
                        </div>
                    </div>
                    <div class="message-time">${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                `;
                messagesArea.appendChild(group);
                messagesArea.scrollTop = messagesArea.scrollHeight;
                messageForm.querySelector('input[name="message"]').value = '';
                messageForm.querySelector('input[name="attachment"]').value = '';
            }
        })
        .catch(console.error);
    });
}

// Polling for new messages every 3 seconds
const pollUser = <?php echo $with; ?>;
if (pollUser) {
    setInterval(function() {
        fetch('messages.php?action=poll&with=' + pollUser + '&booking_id=' + <?php echo $booking_id; ?>, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Poll failed: ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.success) {
                const messagesArea = document.getElementById('messagesArea');
                messagesArea.innerHTML = '';
                data.messages.forEach(m => {
                    const group = document.createElement('div');
                    group.className = 'message-group';

                    let attachmentHtml = '';
                    let voiceBadgeHtml = '';
                    const attachmentPath = m.file_path || m.attachment_path;
                    const messageType = m.message_type || m.attachment_type;
                    if (attachmentPath) {
                        const isImage = /\.(jpg|jpeg|png|gif)$/i.test(attachmentPath);
                        const isAudio = /\.(webm|ogg|mp3|wav)$/i.test(attachmentPath);
                        if (isImage) {
                            attachmentHtml += `<div class="attachment-preview"><img src="../${attachmentPath}" alt="Attachment preview" style="max-width:180px;max-height:180px;border-radius:8px;margin-top:6px;" /></div>`;
                        } else if (isAudio) {
                            const mimeType = attachmentPath.match(/\.(webm|ogg|mp3|wav)$/i)[1].toLowerCase();
                            const audioType = mimeType === 'mp3' ? 'mpeg' : mimeType;
                            attachmentHtml += `<div class="attachment-preview" style="margin-top:6px;"><audio controls style="width:100%; max-width:250px;"><source src="../${attachmentPath}" type="audio/${audioType}">Your browser does not support audio playback.</audio></div>`;
                        }
                        if (messageType === 'audio') {
                            voiceBadgeHtml = '<div class="voice-badge">Voice note</div>';
                        }
                        attachmentHtml += `<div class="attachment-link"><a href="../${attachmentPath}" target="_blank" rel="noopener noreferrer">📎 View attachment</a></div>`;
                    }

                    group.innerHTML = `
                        <div class="message ${m.sender_id == <?php echo $me; ?> ? 'sent' : 'received'}">
                            <div class="message-bubble">
                                ${m.message.replace(/\n/g,'<br>')}
                                ${voiceBadgeHtml}
                                ${attachmentHtml}
                            </div>
                        </div>
                        <div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                    `;
                    messagesArea.appendChild(group);
                });
                messagesArea.scrollTop = messagesArea.scrollHeight;

                if (Array.isArray(data.booking_timeline) && data.booking_timeline.length) {
                    const timelineArea = document.getElementById('bookingTimeline');
                    if (timelineArea) {
                        timelineArea.innerHTML = '';
                        data.booking_timeline.forEach(item => {
                            const itemEl = document.createElement('div');
                            itemEl.className = 'timeline-item timeline-' + (item.status || '');
                            itemEl.innerHTML = `
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <div class="timeline-title">${item.label}</div>
                                    ${item.time ? `<div class="timeline-time">${new Date(item.time).toLocaleString()}</div>` : ''}
                                </div>
                            `;
                            timelineArea.appendChild(itemEl);
                        });
                    }
                }
            }
        })
        .catch(err => console.error('Poll error', err));
    }, 3000);
}
</script>
    </div> <!-- .main-content -->
</body>
</html>
