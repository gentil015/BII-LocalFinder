<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat.php';
require_once '../includes/mailer.php';

// API poll endpoint for live update
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    $me = $_SESSION['user_id'] ?? 0;
    $with = isset($_GET['with']) ? intval($_GET['with']) : 0;
    if ($me && $with) {
        $messages = getConversationMessages($me, $with);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'messages' => $messages]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    }
    exit;
}

// ensure only providers use this version
if (!isLoggedIn()) {
    redirect('login.php');
}
if (!isProvider()) {
    // clients should use client messages page
    redirect('../client/messages.php' . (isset($_GET['with']) ? '?with=' . intval($_GET['with']) : ''));
}

$me = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// handle new message form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'], $_POST['message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $msg = sanitize($_POST['message']);
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

    $result = false;
    if ($msg !== '') {
        $result = sendMessage($me, $receiver_id, $msg);
        // optionally notify client by email
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $cl = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cl && !empty($cl['email'])) {
            $providerStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $providerStmt->execute([$me]);
            $provider = $providerStmt->fetch(PDO::FETCH_ASSOC);
            $providerName = $provider['full_name'] ?? 'Your Provider';

            $serviceText = 'General chat';
            if ($booking_id) {
                $bstmt = $db->prepare("SELECT service_description, service_id FROM bookings WHERE id = ?");
                $bstmt->execute([$booking_id]);
                $booking = $bstmt->fetch(PDO::FETCH_ASSOC);
                if ($booking) {
                    $serviceText = trim($booking['service_description']) ?: 'Booked service';
                }
            }

            $body = "Hello,\n\n";
            $body .= "You have received a new message from provider {$providerName}.\n";
            $body .= "Service: {$serviceText}.\n\n";
            $body .= "Message:\n{$msg}\n\n";
            $body .= "Please log in to BII LocalFinder to reply.\n";

            Mailer::send(
                $cl['email'],
                "New message from provider {$providerName}",
                $body,
                false
            );
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result,
            'message' => $result ? ['sender_id' => $me, 'receiver_id' => $receiver_id, 'message' => $msg, 'created_at' => date('Y-m-d H:i:s')] : null
        ]);
        exit;
    }

    redirect('provider/messages.php?with=' . $receiver_id . ($booking_id ? '&booking_id=' . $booking_id : ''));
}

// determine active conversation partner
$with = isset($_GET['with']) ? intval($_GET['with']) : 0;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

$convs = getConversationList($me);
$messages = [];
$otherUser = null;
if ($with) {
    $stmt = $db->prepare("SELECT id, full_name FROM users WHERE id = ?");
    $stmt->execute([$with]);
    $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($otherUser) {
        markMessagesRead($with, $me);
        $messages = getConversationMessages($me, $with);
    } else {
        $with = 0;
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
            border-color: #1e3a8a;
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
            background: #e0e7ff;
        }

        .conversation-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
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
            background: #1e3a8a;
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
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
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
            color: #1e3a8a;
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
            background: #e0e7ff;
            border-bottom: 1px solid #c7d2fe;
            border-radius: 0;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #1e40af;
        }

        .booking-info i {
            font-size: 18px;
        }

        .booking-info a {
            color: #1e3a8a;
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
            background: #1e3a8a;
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
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .send-btn {
            background: #1e3a8a;
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
            background: #1e3050;
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
                <small>Clients will message you here</small>
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
                    <div>Booking <strong>#<?php echo intval($booking_id); ?></strong> active · <a href="../client/booking-details.php?id=<?php echo intval($booking_id); ?>">View details</a></div>
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
                                <div class="message-bubble"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                            </div>
                            <div class="message-time"><?php echo date('H:i', strtotime($m['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input Area -->
            <div class="typing-indicator" id="typingIndicator" style="display:none; padding: 8px 20px; color: #1e3a8a; font-size: 14px;">
                <i class="fas fa-ellipsis-h fa-fw"></i> Typing...
            </div>
            <div class="message-input-area">
                <form method="POST" class="input-form" id="messageForm">
                    <input type="hidden" name="receiver_id" value="<?php echo $with; ?>">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="hidden" name="ajax" value="1">
                    <input type="text" name="message" placeholder="Aa" required autocomplete="off" id="messageTextField">
                    <button type="submit" class="send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        <?php else: ?>
            <div class="chat-empty">
                <div>
                    <i class="fas fa-comments"></i>
                    <p style="font-size: 18px; margin-top: 12px;">Select a client to start chatting</p>
                    <small>Your clients' messages will appear here</small>
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
        event.preventDefault();
        const formData = new FormData(messageForm);
        const receiverId = formData.get('receiver_id');
        const messageText = formData.get('message').trim();
        if (!messageText) return;

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const messagesArea = document.getElementById('messagesArea');
                const group = document.createElement('div');
                group.className = 'message-group';
                group.innerHTML = `
                    <div class="message sent">
                        <div class="message-bubble">${messageText.replace(/\n/g,'<br>')}</div>
                    </div>
                    <div class="message-time">${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                `;
                messagesArea.appendChild(group);
                messagesArea.scrollTop = messagesArea.scrollHeight;
                messageForm.querySelector('input[name="message"]').value = '';
            }
        })
        .catch(console.error);
    });
}

// Polling for new messages every 3 seconds
const pollUser = <?php echo $with; ?>;
if (pollUser) {
    setInterval(function() {
        fetch('provider/messages.php?action=poll&with=' + pollUser + '&booking_id=' + <?php echo $booking_id; ?>, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const messagesArea = document.getElementById('messagesArea');
                const existing = Array.from(messagesArea.querySelectorAll('.message-bubble')).map(b=>b.textContent);
                let changed = false;
                messagesArea.innerHTML = '';
                data.messages.forEach(m => {
                    const group = document.createElement('div');
                    group.className = 'message-group';
                    group.innerHTML = `
                        <div class="message ${m.sender_id == <?php echo $me; ?> ? 'sent' : 'received'}">
                            <div class="message-bubble">${m.message.replace(/\n/g,'<br>')}</div>
                        </div>
                        <div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                    `;
                    messagesArea.appendChild(group);
                });
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        })
        .catch(err => console.error('Poll error', err));
    }, 3000);
}
</script>
</body>
</html>
