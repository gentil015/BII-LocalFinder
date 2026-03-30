<?php
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors, log them instead
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/live_location.php';
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

function decodeMessageJson(string $message): string {
    $decoded = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
    while (strpos($decoded, '&quot;') !== false || strpos($decoded, '&amp;') !== false) {
        $previous = $decoded;
        $decoded = html_entity_decode($decoded, ENT_QUOTES, 'UTF-8');
        if ($decoded === $previous) {
            break;
        }
    }
    return $decoded;
}

// Function to render message content (handles service offers, services, and regular messages)
function renderMessageContent($m) {
    $messageType = $m['message_type'] ?? $m['attachment_type'] ?? '';
    $attachmentPath = $m['file_path'] ?? $m['attachment_path'] ?? '';
    
    // Handle service offer messages
    if ($messageType === 'service_offer') {
        $jsonData = decodeMessageJson($m['message']);
        $serviceData = json_decode($jsonData, true);
        if ($serviceData) {
            $negotiableBadge = $serviceData['negotiable'] ? '<span class="badge negotiable">Negotiable</span>' : '';
            $priceRange = $serviceData['min_price'] && $serviceData['max_price'] && $serviceData['min_price'] != $serviceData['max_price'] 
                ? number_format($serviceData['min_price']) . ' - ' . number_format($serviceData['max_price']) . ' RWF'
                : number_format($serviceData['price'] ?? 0) . ' RWF';
            
            return '
                <div class="service-card offer-card">
                    <div class="service-card-header">
                        <i class="fas fa-gift service-icon"></i>
                        <div class="service-title">' . htmlspecialchars($serviceData['service_name']) . '</div>
                        ' . $negotiableBadge . '
                    </div>
                    <div class="service-description">' . htmlspecialchars($serviceData['description']) . '</div>
                    <div class="service-price">' . $priceRange . '</div>
                    <div class="service-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="negotiateOfferPrice(this, ' . intval($serviceData['service_id']) . ')">Negotiate</button>
                        <button class="btn btn-sm btn-primary" onclick="acceptOfferDirect(this, ' . intval($serviceData['service_id']) . ')">Accept Offer</button>
                    </div>
                </div>';
        }
    }
    // Handle service messages
    elseif ($messageType === 'service') {
        $jsonData = decodeMessageJson($m['message']);
        $serviceData = json_decode($jsonData, true);
        if ($serviceData) {
            $priceRange = $serviceData['min_price'] && $serviceData['max_price'] && $serviceData['min_price'] != $serviceData['max_price'] 
                ? number_format($serviceData['min_price']) . ' - ' . number_format($serviceData['max_price']) . ' RWF'
                : number_format($serviceData['price'] ?? 0) . ' RWF';
            
            return '
                <div class="service-card service-card-basic">
                    <div class="service-card-header">
                        <i class="fas fa-briefcase service-icon"></i>
                        <div class="service-title">' . htmlspecialchars($serviceData['service_name']) . '</div>
                    </div>
                    <div class="service-description">' . htmlspecialchars($serviceData['description']) . '</div>
                    <div class="service-price">' . $priceRange . '</div>
                    <div class="service-actions">
                        <button class="btn btn-sm btn-primary" onclick="bookService(this, ' . intval($serviceData['service_id']) . ', \'' . addslashes($serviceData['service_name']) . '\')">Book Now</button>
                    </div>
                </div>';
        }
    }
    // Handle location messages
    elseif ($messageType === 'location') {
        $jsonData = decodeMessageJson($m['message']);
        $locationData = json_decode($jsonData, true);
        if ($locationData && isset($locationData['latitude'], $locationData['longitude'])) {
            $latitude = htmlspecialchars($locationData['latitude']);
            $longitude = htmlspecialchars($locationData['longitude']);
            $label = htmlspecialchars($locationData['label'] ?? 'Shared live location');
            $mapUrl = 'https://www.openstreetmap.org/?mlat=' . $latitude . '&mlon=' . $longitude . '#map=18/' . $latitude . '/' . $longitude;
            $latitudeFloat = floatval($locationData['latitude']);
            $longitudeFloat = floatval($locationData['longitude']);
            $bbox = ($longitudeFloat - 0.007) . ',' . ($latitudeFloat - 0.005) . ',' . ($longitudeFloat + 0.007) . ',' . ($latitudeFloat + 0.005);
            $mapIframeUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $latitudeFloat . ',' . $longitudeFloat;

            return '
                <div class="location-card">
                    <div class="location-card-header"><i class="fas fa-map-marker-alt"></i> ' . $label . '</div>
                    <div class="location-card-body">
                        <iframe src="' . $mapIframeUrl . '" class="location-map" loading="lazy" style="border:none; width:100%; height:180px; border-radius:10px;"></iframe>
                    </div>
                    <div class="location-card-actions"><a href="' . $mapUrl . '" target="_blank" rel="noopener noreferrer">Open in map</a></div>
                </div>';
        }
    }

    // Handle location JSON inside plain text when message_type is text or missing
    if ($messageType === 'text' || $messageType === '') {
        $jsonData = decodeMessageJson($m['message']);
        $locationData = json_decode($jsonData, true);
        if ($locationData && isset($locationData['latitude'], $locationData['longitude'])) {
            $latitude = htmlspecialchars($locationData['latitude']);
            $longitude = htmlspecialchars($locationData['longitude']);
            $label = htmlspecialchars($locationData['label'] ?? 'Shared live location');
            $mapUrl = 'https://www.openstreetmap.org/?mlat=' . $latitude . '&mlon=' . $longitude . '#map=18/' . $latitude . '/' . $longitude;
            $latitudeFloat = floatval($locationData['latitude']);
            $longitudeFloat = floatval($locationData['longitude']);
            $bbox = ($longitudeFloat - 0.007) . ',' . ($latitudeFloat - 0.005) . ',' . ($longitudeFloat + 0.007) . ',' . ($latitudeFloat + 0.005);
            $mapIframeUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $latitudeFloat . ',' . $longitudeFloat;

            return '
                <div class="location-card">
                    <div class="location-card-header"><i class="fas fa-map-marker-alt"></i> ' . $label . '</div>
                    <div class="location-card-body">
                        <iframe src="' . $mapIframeUrl . '" class="location-map" loading="lazy" style="border:none; width:100%; height:180px; border-radius:10px;"></iframe>
                    </div>
                    <div class="location-card-actions"><a href="' . $mapUrl . '" target="_blank" rel="noopener noreferrer">Open in map</a></div>
                </div>';
        }
    }
    
    // Handle regular messages with attachments
    $content = nl2br(htmlspecialchars($m['message']));
    
    $isAudioMessage = (!empty($m['message_type']) && $m['message_type'] === 'audio')
        || (!empty($m['attachment_type']) && $m['attachment_type'] === 'audio');
    
    if ($isAudioMessage) {
        $content .= '<div class="voice-badge">Voice note</div>';
    }
    
    if (!empty($attachmentPath)) {
        $ext = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'], true)) {
            $content .= '<div class="attachment-preview">
                <img src="../' . htmlspecialchars($attachmentPath) . '" alt="Attachment preview" class="attach-img" />
            </div>';
        } elseif (in_array($ext, ['webm','ogg','mp3','wav'], true)) {
            $content .= '<div class="attachment-preview">
                <audio controls class="attach-audio">
                    <source src="../' . htmlspecialchars($attachmentPath) . '" type="audio/' . htmlspecialchars($ext === 'webm' ? 'webm' : ($ext === 'ogg' ? 'ogg' : ($ext === 'mp3' ? 'mpeg' : 'wav'))) . '">
                    Your browser does not support the audio element.
                </audio>
            </div>';
        }
        $content .= '<div class="attachment-link">
            <a href="../' . htmlspecialchars($attachmentPath) . '" target="_blank" rel="noopener noreferrer">
                📎 View attachment
            </a>
        </div>';
    }
    
    return $content;
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id']) && (isset($_POST['message']) || !empty($_FILES['attachment']['name']) || isset($_POST['message_type']))) {
    $receiver_id = intval($_POST['receiver_id']);
    $msg = sanitize($_POST['message'] ?? '');
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $messageType = sanitize($_POST['message_type'] ?? 'text');
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

    $attachmentPath = null;
    $attachmentType = null;
    $offerInfo = null;
    $serviceInfo = null;
    
    // Handle file attachment
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
    
    // Handle service offer message
    if ($messageType === 'service_offer' && isset($_POST['offer_service_id'])) {
        $serviceId = intval($_POST['offer_service_id']);
        $isNegotiable = isset($_POST['offer_negotiable']) && $_POST['offer_negotiable'] === '1';
        
        // Get provider ID first
        $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
        $stmt->execute([$me]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($provider) {
            // Get service details from provider_services
            $stmt = $db->prepare("SELECT id, name as service_name, description as service_description, price, min_price, max_price, negotiable FROM provider_services WHERE id = ? AND provider_id = ? LIMIT 1");
            $stmt->execute([$serviceId, $provider['id']]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($service) {
                $offerInfo = [
                    'service_name' => $service['service_name'],
                    'description' => $service['service_description'],
                    'price' => $service['price'],
                    'min_price' => $service['min_price'],
                    'max_price' => $service['max_price'],
                    'negotiable' => $isNegotiable,
                    'service_id' => $serviceId
                ];
                
                // Send offer message with JSON data
                $offerData = json_encode($offerInfo);
                $result = sendMessage($me, $receiver_id, $offerData, null, null, 'service_offer');
            }
        }
    }
    // Handle service message
    elseif ($messageType === 'service' && isset($_POST['service_id'])) {
        $serviceId = intval($_POST['service_id']);
        
        // Get provider ID first
        $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
        $stmt->execute([$me]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($provider) {
            // Get service details from provider_services
            $stmt = $db->prepare("SELECT id, name as service_name, description as service_description, price FROM provider_services WHERE id = ? AND provider_id = ? LIMIT 1");
            $stmt->execute([$serviceId, $provider['id']]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($service) {
                $serviceInfo = [
                    'service_name' => $service['service_name'],
                    'description' => $service['service_description'],
                    'price' => $service['price'],
                    'service_id' => $serviceId
                ];
                
                // Send service message with JSON data
                $serviceData = json_encode($serviceInfo);
                $result = sendMessage($me, $receiver_id, $serviceData, null, null, 'service');
            }
        }
    }
    // Handle regular message with optional attachment
    elseif ($msg !== '' || $attachmentPath !== null) {
        $result = sendMessage($me, $receiver_id, $msg, $attachmentPath, $attachmentType);

        // optionally notify provider via email/push/sms
        if ($result && $attachmentPath === null) {
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
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        $messageData = null;
        if ($result) {
            $messageData = [
                'sender_id' => $me,
                'receiver_id' => $receiver_id,
                'message' => $msg,
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
                'created_at' => date('Y-m-d H:i:s'),
                'message_type' => $messageType
            ];
            if ($offerInfo) {
                $messageData['offer_info'] = $offerInfo;
            }
            if ($serviceInfo) {
                $messageData['service_info'] = $serviceInfo;
            }
        }
        echo json_encode([
            'success' => $result,
            'message' => $messageData
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
    $stmt = $db->prepare("SELECT id, full_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$with]);
    $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($otherUser) {
        // link provider profile for client page, or client profile for provider page
        $profileHref = isProvider() ? '../client/profile.php?id=' . $with : '../provider/profile.php?id=' . $with;
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
    <title>Messages</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:       #0d6efd;
            --accent-dark:  #0a58ca;
            --accent-light: #eff4ff;
            --surface:      #ffffff;
            --surface-2:    #f7f8fc;
            --border:       #e8eaf0;
            --border-subtle:#f0f2f7;
            --text-primary: #0f1117;
            --text-secondary:#6b7280;
            --text-muted:   #9ca3af;
            --sent-bg:      #0d6efd;
            --recv-bg:      #f0f2f7;
            --online:       #22c55e;
            --radius-sm:    8px;
            --radius-md:    12px;
            --radius-lg:    16px;
            --shadow-sm:    0 1px 4px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:    0 4px 16px rgba(0,0,0,0.08);
            --transition:   all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--surface-2);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            color: var(--text-primary);
        }

        /* ── SIDEBAR OFFSET ── */
        .main-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        /* ── CHAT SHELL ── */
        .chat-container {
            display: flex;
            height: 100vh;
            background: var(--surface);
            overflow: hidden;
        }

        /* ══ CONVERSATIONS PANEL ══ */
        .conversations-panel {
            width: 320px;
            min-width: 320px;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            background: var(--surface);
        }

        .conversations-header {
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .conversations-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.4px;
            color: var(--text-primary);
            margin-bottom: 0.875rem;
        }

        .search-box { position: relative; }

        .search-box input {
            width: 100%;
            padding: 0.55rem 1rem 0.55rem 2.25rem;
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 0.85rem;
            font-family: inherit;
            background: var(--surface-2);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            background: var(--surface);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.08);
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8rem;
            pointer-events: none;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            list-style: none;
            padding: 0.5rem 0.625rem;
        }

        .conversation-item {
            padding: 0.625rem 0.75rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: var(--radius-md);
            position: relative;
            margin-bottom: 2px;
        }

        .conversation-item:hover { background: var(--surface-2); }
        .conversation-item.active { background: var(--accent-light); }

        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .conversation-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .conversation-avatar.online::after {
            content: '';
            position: absolute;
            width: 11px; height: 11px;
            background: var(--online);
            border: 2px solid var(--surface);
            border-radius: 50%;
            bottom: 1px; right: 1px;
        }

        .conversation-info { flex: 1; min-width: 0; }

        .conversation-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-preview {
            font-size: 0.78rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-item.active .conversation-name { color: var(--accent); }
        .conversation-item.active .conversation-preview { color: var(--accent); opacity: 0.75; }

        .unread-badge {
            background: var(--accent);
            color: white;
            border-radius: 100px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            padding: 2rem;
            text-align: center;
            gap: 0.5rem;
        }

        .empty-state i { font-size: 2.5rem; opacity: 0.3; }
        .empty-state p { font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); }
        .empty-state small { font-size: 0.78rem; }

        /* ══ CHAT PANEL ══ */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--surface-2);
            min-width: 0;
        }

        .chat-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: var(--text-muted);
            background: var(--surface-2);
        }

        .chat-empty i { font-size: 3.5rem; opacity: 0.15; margin-bottom: 1rem; display: block; }
        .chat-empty p { font-size: 1rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .chat-empty small { font-size: 0.8rem; }

        /* Chat Header */
        .chat-header {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
            position: relative;
            z-index: 5;
        }

        .chat-header-info { display: flex; align-items: center; gap: 0.75rem; }
        .chat-header-avatar-link { text-decoration: none; }

        .chat-header-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .chat-header-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .chat-header-avatar.online::after {
            content: '';
            position: absolute;
            width: 10px; height: 10px;
            background: var(--online);
            border: 2px solid var(--surface);
            border-radius: 50%;
            bottom: 0; right: 0;
        }

        .chat-header-name { font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
        .chat-header-status { font-size: 0.72rem; color: var(--online); font-weight: 500; }

        .chat-header-actions { display: flex; gap: 0.25rem; position: relative; }

        .header-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px;
        }

        .header-btn:hover { background: var(--surface-2); color: var(--accent); }

        /* Options dropdown */
        .chat-options-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 200px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            z-index: 50;
            display: none;
            flex-direction: column;
            padding: 0.375rem 0;
            overflow: hidden;
        }

        .chat-options-dropdown.visible { display: flex; }

        .chat-options-dropdown button {
            text-align: left;
            border: none;
            background: transparent;
            width: 100%;
            padding: 0.625rem 1rem;
            font-size: 0.82rem;
            font-family: inherit;
            color: var(--text-primary);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .chat-options-dropdown button:hover { background: var(--surface-2); color: var(--accent); }

        /* Confirm modal */
        .chat-confirm-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .chat-confirm-modal.show { display: flex; }

        .chat-confirm-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            max-width: 400px;
            width: calc(100% - 2rem);
            box-shadow: 0 20px 48px rgba(0,0,0,0.18);
        }

        .chat-confirm-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary); }
        .chat-confirm-card p  { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem; }

        .chat-confirm-card textarea {
            width: 100%;
            min-height: 80px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.625rem 0.75rem;
            margin-bottom: 1rem;
            resize: vertical;
            font-family: inherit;
            font-size: 0.85rem;
            color: var(--text-primary);
        }

        .chat-confirm-card textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.08);
        }

        .chat-confirm-actions { display: flex; justify-content: flex-end; gap: 0.5rem; }

        /* Toast */
        .chat-action-toast {
            position: fixed;
            bottom: 1.5rem; right: 1.5rem;
            background: var(--text-primary);
            color: white;
            padding: 0.625rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.82rem;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.22s ease;
            z-index: 110;
            pointer-events: none;
            box-shadow: var(--shadow-md);
        }

        .chat-action-toast.error   { background: #dc2626; }
        .chat-action-toast.success { background: #16a34a; }

        /* Booking info banner */
        .booking-info {
            padding: 0.625rem 1.25rem;
            background: var(--accent-light);
            border-bottom: 1px solid #c7d9ff;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.8rem;
            color: var(--accent-dark);
            font-weight: 500;
        }

        .booking-info i { font-size: 0.9rem; flex-shrink: 0; }
        .booking-info a { color: var(--accent); text-decoration: none; font-weight: 700; }
        .booking-info a:hover { text-decoration: underline; }

        .live-location-panel {
            margin: 0.75rem 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .live-location-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.9rem 1rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            gap: 0.75rem;
        }

        .live-location-title {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .live-location-status {
            font-size: 0.82rem;
            color: var(--text-secondary);
        }

        .live-location-map {
            width: 100%;
            height: 240px;
            min-height: 240px;
        }

        .live-location-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            padding: 0.9rem 1rem;
            background: var(--surface-2);
        }

        .live-location-controls button,
        .live-location-controls select {
            min-height: 40px;
        }

        .live-location-message .message-bubble {
            max-width: 70%;
            background: rgba(13, 110, 253, 0.12);
            border: 1px solid rgba(13, 110, 253, 0.18);
            color: var(--text-primary);
        }

        .live-location-message .message {
            justify-content: flex-start;
        }

        /* Booking timeline */
        .booking-timeline {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            display: flex;
            gap: 0;
            flex-wrap: wrap;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            flex: 1;
            min-width: 120px;
        }

        .timeline-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-top: 5px;
            background: var(--accent);
            flex-shrink: 0;
        }

        .timeline-title { font-size: 0.78rem; font-weight: 600; color: var(--accent-dark); }
        .timeline-time  { font-size: 0.7rem;  color: var(--text-muted); margin-top: 1px; }

        /* Messages area */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            background: var(--surface-2);
        }

        .msg-empty { margin: auto; text-align: center; color: var(--text-muted); }
        .msg-empty i { font-size: 2.5rem; opacity: 0.2; display: block; margin-bottom: 0.75rem; }
        .msg-empty p { font-size: 0.875rem; font-weight: 500; }

        .message-group { display: flex; flex-direction: column; gap: 2px; }

        .message { display: flex; align-items: flex-end; gap: 0.5rem; }
        .message.sent { justify-content: flex-end; }

        .message-bubble {
            max-width: 58%;
            padding: 0.6rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.5;
            word-wrap: break-word;
            animation: msgIn 0.2s ease-out;
        }

        @keyframes msgIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .message.sent .message-bubble {
            background: var(--sent-bg);
            color: white;
            border-radius: 18px 4px 18px 18px;
        }

        .message.received .message-bubble {
            background: var(--surface);
            color: var(--text-primary);
            border-radius: 4px 18px 18px 18px;
            border: 1px solid var(--border);
        }

        .message-time {
            font-size: 0.68rem;
            color: var(--text-muted);
            padding: 0 0.5rem;
        }

        .message-group > .message-time { text-align: right; }

        /* Attachments */
        .attachment-preview { margin-top: 0.4rem; }
        .attach-img { max-width: 180px; max-height: 180px; border-radius: var(--radius-sm); display: block; }
        .attach-audio { width: 100%; max-width: 240px; margin-top: 0.375rem; }

        .attachment-link { margin-top: 0.4rem; font-size: 0.78rem; }
        .attachment-link a { color: inherit; opacity: 0.85; text-decoration: none; }
        .attachment-link a:hover { opacity: 1; text-decoration: underline; }
        .message.sent .attachment-link a { color: white; }
        .message.received .attachment-link a { color: var(--accent); }

        /* Service cards in messages */
        .service-card {
            background: rgba(255,255,255,0.1);
            border-radius: var(--radius-md);
            padding: 0.875rem;
            margin: 0.25rem 0;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .location-card {
            background: rgba(255,255,255,0.12);
            border-radius: var(--radius-md);
            padding: 0.875rem;
            margin: 0.25rem 0;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .message.received .service-card,
        .message.received .location-card {
            background: var(--surface);
            border: 1px solid var(--border);
        }

        .service-card-header,
        .location-card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .service-icon {
            font-size: 1rem;
            color: var(--accent);
        }

        .message.received .service-icon {
            color: var(--accent);
        }

        .service-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: white;
        }

        .message.received .service-title {
            color: var(--text-primary);
        }

        .badge.negotiable {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            border-radius: 8px;
            font-weight: 600;
            margin-left: auto;
        }

        .message.received .badge.negotiable {
            background: rgba(255,193,7,0.1);
            color: #856404;
        }

        .service-description {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.8);
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }

        .message.received .service-description {
            color: var(--text-secondary);
        }

        .location-card-body {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.9);
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }

        .location-map {
            width: 100%;
            border-radius: 12px;
            display: block;
            max-height: 180px;
            object-fit: cover;
        }

        .message.received .location-card-body {
            color: var(--text-secondary);
        }

        .location-card-actions a {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8rem;
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }

        .location-card-actions a:hover {
            text-decoration: underline;
        }

        .service-price {
            font-weight: 600;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.95);
            margin-bottom: 0.75rem;
        }

        .message.received .service-price {
            color: var(--text-primary);
        }

        .service-actions {
            display: flex;
            gap: 0.5rem;
        }

        .service-actions .btn {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: none;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .service-actions .btn-outline-primary {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }

        .service-actions .btn-outline-primary:hover {
            background: rgba(255,255,255,0.2);
        }

        .service-actions .btn-primary {
            background: var(--accent);
            color: white;
        }

        .service-actions .btn-primary:hover {
            background: var(--accent-dark);
        }

        .message.received .service-actions .btn-outline-primary {
            background: var(--surface-2);
            border: 1px solid var(--border);
            color: var(--text-primary);
        }

        .message.received .service-actions .btn-outline-primary:hover {
            background: var(--border);
        }

        .message.received .service-actions .btn-primary {
            background: var(--accent);
            color: white;
        }

        /* Input menu dropdown */
        .input-menu-dropdown {
            position: absolute;
            bottom: 100%;
            left: 0.5rem;
            min-width: 200px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            z-index: 50;
            display: none;
            flex-direction: column;
            padding: 0.375rem 0;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .input-menu-dropdown.visible { display: flex; }

        .input-menu-dropdown button {
            text-align: left;
            border: none;
            background: transparent;
            width: 100%;
            padding: 0.625rem 1rem;
            font-size: 0.82rem;
            font-family: inherit;
            color: var(--text-primary);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .input-menu-dropdown button:hover { background: var(--surface-2); color: var(--accent); }
        .input-menu-dropdown button i { width: 1rem; }

        /* Service modal */
        .service-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .service-modal.show { display: flex; }

        .service-modal-content {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            max-width: 500px;
            width: calc(100% - 2rem);
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 0 20px 48px rgba(0,0,0,0.18);
        }

        .service-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
        .service-modal-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
        .service-modal-close { background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; }
        .service-modal-close:hover { color: var(--text-primary); }

        .service-list { display: flex; flex-direction: column; gap: 0.625rem; margin-bottom: 1rem; }
        .service-item { border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem; cursor: pointer; transition: var(--transition); }
        .service-item:hover { background: var(--surface-2); border-color: var(--accent); }
        .service-item.selected { background: var(--accent-light); border-color: var(--accent); }
        .service-name { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 0.25rem; }
        .service-desc { font-size: 0.78rem; color: var(--text-secondary); }
        .service-price { font-size: 0.85rem; font-weight: 600; color: var(--accent); margin-top: 0.5rem; }

        .negotiable-toggle { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--surface-2); border-radius: var(--radius-sm); }
        .negotiable-toggle label { flex: 1; font-size: 0.85rem; font-weight: 500; color: var(--text-primary); margin: 0; cursor: pointer; }
        .negotiable-toggle input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }

        .service-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
        .service-modal-actions button { padding: 0.625rem 1.125rem; border-radius: var(--radius-sm); border: none; font-family: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: var(--transition); }
        .service-modal-actions .btn-cancel { background: var(--surface-2); color: var(--text-primary); }
        .service-modal-actions .btn-cancel:hover { background: var(--border); }
        .service-modal-actions .btn-send { background: var(--accent); color: white; }
        .service-modal-actions .btn-send:hover { background: var(--accent-dark); }
        .service-modal-actions .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }

        .voice-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            background: rgba(255,255,255,0.2);
            border-radius: 100px;
            padding: 0.15rem 0.5rem;
            margin-bottom: 0.25rem;
        }

        .message.received .voice-badge { background: var(--accent-light); color: var(--accent); }

        /* Typing indicator */
        .typing-indicator {
            padding: 0.375rem 1.5rem;
            font-size: 0.78rem;
            color: var(--accent);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            background: var(--surface-2);
        }

        /* Message input area */
        .message-input-area {
            padding: 0.75rem 1.25rem 1rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .input-form { width: 100%; }

        .input-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 100px;
            padding: 0.375rem 0.5rem 0.375rem 0.75rem;
            transition: var(--transition);
        }

        .input-row:focus-within {
            border-color: var(--accent);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.07);
        }

        input[name="message"] {
            flex: 1;
            border: none;
            background: transparent;
            padding: 0.3rem 0.25rem;
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
        }

        input[name="message"]::placeholder { color: var(--text-muted); }

        .record-status {
            font-size: 0.72rem;
            color: var(--text-muted);
            padding: 0.3rem 0.75rem 0;
            font-weight: 500;
            min-height: 1.2rem;
        }

        .record-status.recording { color: #dc2626; }

        .send-btn {
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 50%;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .send-btn:hover  { background: var(--accent-dark); }
        .send-btn:active { transform: scale(0.93); }

        /* Scrollbars */
        .conversations-list::-webkit-scrollbar,
        .messages-area::-webkit-scrollbar { width: 4px; }
        .conversations-list::-webkit-scrollbar-track,
        .messages-area::-webkit-scrollbar-track { background: transparent; }
        .conversations-list::-webkit-scrollbar-thumb,
        .messages-area::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

        /* Utilities */
        .conv-avatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

        .header-btn.recording { background: #fef2f2; color: #dc2626; }

        /* Responsive */
        @media (max-width: 768px) {
            .conversations-panel {
                width: 100%;
                min-width: unset;
                position: absolute;
                height: 100%;
                z-index: 10;
            }
            .conversations-panel.hidden { display: none; }
            .chat-panel { width: 100%; }
            .message-bubble { max-width: 82%; }
            .chat-header { padding: 0.75rem 1rem; }
            .main-content { margin-left: 0 !important; }
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
                            <?php if (!empty($c['profile_image'])): ?>
                                <?php $convProfile = '../uploads/profiles/' . htmlspecialchars($c['profile_image']); ?>
                                <img src="<?php echo $convProfile; ?>" alt="<?php echo htmlspecialchars($c['full_name']); ?>" class="conv-avatar-img" onerror="this.onerror=null;this.src='../uploads/<?php echo htmlspecialchars($c['profile_image']); ?>';" />
                            <?php else: ?>
                                <?php echo strtoupper(substr($c['full_name'], 0, 1)); ?>
                            <?php endif; ?>
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
                    <a href="<?php echo htmlspecialchars($profileHref ?? '../provider/profile.php?id=' . (int)$with); ?>" class="chat-header-avatar-link" title="View provider profile">
                        <div class="chat-header-avatar online">
                            <?php if (!empty($otherUser['profile_image'])): ?>
                                <?php $profilePath = '../uploads/profiles/' . htmlspecialchars($otherUser['profile_image']); ?>
                                <img src="<?php echo $profilePath; ?>" alt="<?php echo htmlspecialchars($otherUser['full_name']); ?>" class="conv-avatar-img" onerror="this.onerror=null;this.src='../uploads/<?php echo htmlspecialchars($otherUser['profile_image']); ?>';" />
                            <?php else: ?>
                                <?php echo strtoupper(substr($otherUser['full_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div>
                        <div class="chat-header-name"><?php echo htmlspecialchars($otherUser['full_name']); ?></div>
                        <div class="chat-header-status">Active now</div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button class="header-btn" id="chatOptionsBtn" title="Chat options"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="chat-options-dropdown" id="chatOptionsDropdown" aria-hidden="true">
                        <button id="chatOptViewOffers">View Offers</button>
                        <button id="chatOptMute">Mute Notifications</button>
                        <button id="chatOptClear">Clear Chat</button>
                        <button id="chatOptDelete">Delete Conversation</button>
                        <button id="chatOptReport">Report User</button>
                        <button id="chatOptBlock">Block User</button>
                    </div>
                </div>
            </div>
            <div class="chat-confirm-modal" id="chatConfirmModal" aria-hidden="true">
                <div class="chat-confirm-card">
                    <h3 id="chatConfirmTitle">Confirm</h3>
                    <p id="chatConfirmText">Are you sure?</p>
                    <div id="chatConfirmInputWrapper" style="display:none;" class="mt-2">
                        <textarea id="chatConfirmReason" placeholder="Report reason"></textarea>
                    </div>
                    <div class="chat-confirm-actions">
                        <button class="btn btn-secondary" id="chatConfirmCancel">Cancel</button>
                        <button class="btn btn-danger" id="chatConfirmAction">Confirm</button>
                    </div>
                </div>
            </div>
            <div class="chat-action-toast" id="chatActionToast" role="status" aria-live="polite"></div>

            <!-- Service Offer Modal -->
            <div class="service-modal" id="serviceOfferModal" aria-hidden="true">
                <div class="service-modal-content">
                    <div class="service-modal-header">
                        <h3>Send Offer</h3>
                        <button type="button" class="service-modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="service-list" id="offerServiceList"></div>
                    <div class="negotiable-toggle">
                        <label for="offerNegotiableCheck">Allow negotiation on price</label>
                        <input type="checkbox" id="offerNegotiableCheck" checked>
                    </div>
                    <div class="service-modal-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="button" class="btn-send" id="sendOfferBtn" disabled>Send Offer</button>
                    </div>
                </div>
            </div>

            <!-- Service Modal -->
            <div class="service-modal" id="serviceModal" aria-hidden="true">
                <div class="service-modal-content">
                    <div class="service-modal-header">
                        <h3>Send Service</h3>
                        <button type="button" class="service-modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="service-list" id="serviceList"></div>
                    <div class="service-modal-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="button" class="btn btn-secondary" id="shareLocationTriggerModal">Share Location</button>
                        <button type="button" class="btn-send" id="sendServiceBtn" disabled>Send Service</button>
                    </div>
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

            <div class="live-location-panel" id="liveLocationPanel" style="display:none;">
                <div class="live-location-header">
                    <div class="live-location-title">Live Location</div>
                    <div class="live-location-status" id="liveLocationStatus">Ready to share</div>
                </div>
                <div id="liveLocationMap" class="live-location-map"></div>
                <div class="live-location-controls">
                    <button type="button" id="shareLocationBtn" class="btn btn-outline-primary">Share Live Location</button>
                    <button type="button" id="stopLocationBtn" class="btn btn-danger" style="display:none;">Stop Sharing</button>
                    <select id="shareDurationSelect" class="form-select" style="max-width: 140px;">
                        <option value="15">15 min</option>
                        <option value="60">1 hour</option>
                        <option value="120">2 hours</option>
                    </select>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="messages-area" id="messagesArea">
                <?php if (empty($messages)): ?>
                    <div class="msg-empty">
                        <i class="fas fa-comment-dots"></i>
                        <p>No messages yet. Say hello!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="message-group" data-message-id="<?php echo intval($m['id']); ?>">
                            <div class="message <?php echo $m['sender_id'] == $me ? 'sent' : 'received'; ?>">
                                <div class="message-bubble">
                                <?php echo renderMessageContent($m); ?>
                            </div>
                            </div>
                            <div class="message-time"><?php echo date('H:i', strtotime($m['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input Area -->
            <div class="typing-indicator" id="typingIndicator" style="display:none;">
                <i class="fas fa-ellipsis-h fa-fw"></i> Typing...
            </div>
            <div class="message-input-area">
                <form method="POST" class="input-form" id="messageForm" enctype="multipart/form-data">
                    <input type="hidden" name="receiver_id" value="<?php echo $with; ?>">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="hidden" name="ajax" value="1">

                    <div class="input-row" style="position: relative;">
                        <button type="button" id="attachButton" class="header-btn" title="Add content"><i class="fas fa-plus"></i></button>
                        <div class="input-menu-dropdown" id="inputMenuDropdown" aria-hidden="true">
                            <button type="button" id="menuAttachFile"><i class="fas fa-file"></i> Send File</button>
                            <button type="button" id="menuSendOffer"><i class="fas fa-gift"></i> Send Offer</button>
                        <button type="button" id="menuSendService"><i class="fas fa-briefcase"></i> Send Service</button>
                        <button type="button" id="shareLocationTrigger"><i class="fas fa-map-marker-alt"></i> Share Location</button>
                        </div>
                        <input type="file" name="attachment" id="attachmentInput" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.webm" style="display:none;" title="Attach file">
                        <input type="text" name="message" placeholder="Type a message…" autocomplete="off" id="messageTextField">
                        <button type="button" id="recordButton" class="header-btn" title="Record voice message"><i class="fas fa-microphone"></i></button>
                        <button type="submit" class="send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>
                    </div>
                    <div id="recordStatus" class="record-status">Hold mic to record</div>
                </form>
            </div>
        <?php else: ?>
            <div class="chat-empty">
                <div>
                    <i class="fas fa-comments"></i>
                    <p>Select a conversation to start chatting</p>
                    <small>Choose a booking conversation from the list</small>
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
        recordButton.classList.toggle('recording', state);
        // color handled by CSS
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
    .then(r => {
        console.log('Voice upload response status:', r.status);
        return r.json();
    })
    .then(data => {
        console.log('Voice upload response data:', data);
        if (data.success && data.message) {
            const messagesArea = document.getElementById('messagesArea');
            const group = document.createElement('div');
            group.className = 'message-group';

            const audioHtml = `<div class="attachment-preview"><audio controls class="attach-audio"><source src="../${data.message.file_path || data.message.attachment_path}" type="audio/webm">Your browser does not support audio playback.</audio></div>`;
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
    if (!window.MediaRecorder) {
        alert('MediaRecorder is not supported by your browser.');
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
                console.log('Audio blob size:', audioBlob.size, 'duration:', durationSec);
                sendVoiceMessage(audioBlob, durationSec);
            } else {
                console.log('No audio chunks recorded');
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

if (attachmentInput) {
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
                        attachmentHtml += `<div class="attachment-preview"><img src="../${attachmentPath}" alt="Attachment preview" class="attach-img" /></div>`;
                    } else if (['webm','ogg','mp3','wav'].includes(ext)) {
                        const audioType = ext === 'mp3' ? 'mpeg' : ext;
                        attachmentHtml += `<div class="attachment-preview"><audio controls class="attach-audio"><source src="../${attachmentPath}" type="audio/${audioType}">Your browser does not support audio playback.</audio></div>`;
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

// ══ PROFESSIONAL REAL-TIME MESSAGE POLLING ══
// Fetches new messages every 2 seconds without page reload (like WhatsApp/Messenger)
let lastMessageId = 0;
let pollInterval = null;
const pollUser = <?php echo $with; ?>;
const pollBookingId = <?php echo $booking_id; ?>;
const currentUserId = <?php echo $me; ?>;

// Get initial message count to set starting point
const messagesArea = document.getElementById('messagesArea');
if (messagesArea && pollUser) {
    const lastMsg = messagesArea.querySelector('.message-group:last-child');
    if (lastMsg) {
        const msgId = lastMsg.getAttribute('data-message-id');
        if (msgId) lastMessageId = parseInt(msgId);
    }
}

// Helper function to create and append a new message element
function decodeHtmlEntities(input) {
    const txt = document.createElement('textarea');
    txt.innerHTML = input;
    return txt.value;
}

function renderNewMessage(m, messagesArea) {
    const group = document.createElement('div');
    group.className = 'message-group';
    group.setAttribute('data-message-id', m.id);
    
    const isSent = m.sender_id == currentUserId;
    let attachmentHtml = '';
    let voiceBadgeHtml = '';
    const attachmentPath = m.file_path || m.attachment_path;
    const messageType = m.message_type || m.attachment_type;
    
    // Handle service offer messages
    if (messageType === 'service_offer') {
        try {
            const serviceData = JSON.parse(decodeHtmlEntities(m.message || '{}'));
            const negotiableTag = serviceData.negotiable ? '<span style="background: rgba(255,255,255,0.2); padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.7rem; display: inline-block; margin-top: 0.5rem;">NEGOTIABLE</span>' : '';
            const minPrice = serviceData.min_price ? parseFloat(serviceData.min_price).toFixed(0) : parseFloat(serviceData.price).toFixed(0);
            const maxPrice = serviceData.max_price ? parseFloat(serviceData.max_price).toFixed(0) : parseFloat(serviceData.price).toFixed(0);
            const priceText = minPrice === maxPrice ? `RWF ${minPrice}` : `RWF ${minPrice} - ${maxPrice}`;
            const offerBtn = isSent ? '' : `<div style="margin-top: 0.75rem; display: flex; gap: 0.5rem;"><button onclick="negotiateOfferPrice(this, '${serviceData.service_id}');" style="flex: 1; padding: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: white; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">Make Offer</button><button onclick="acceptOfferDirect(this, '${serviceData.service_id}');" style="flex: 1; padding: 0.5rem; background: rgba(255,255,255,0.3); border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">Accept</button></div>`;
            group.innerHTML = `<div class="message ${isSent ? 'sent' : 'received'}"><div class="message-bubble" style="padding: 0.875rem;"><div style="font-weight: 700; font-size: 1rem; margin-bottom: 0.5rem;"><i class="fas fa-gift"></i> ${serviceData.service_name}</div>${serviceData.description ? `<div style="font-size: 0.85rem; margin-bottom: 0.5rem; opacity: 0.95;">${serviceData.description}</div>` : ''}<div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.5rem; color: ${isSent ? 'rgba(255,255,255,0.95)' : 'var(--text-primary)'};">Base Price: ${priceText}</div>${negotiableTag}${offerBtn}</div></div><div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>`;
        } catch (e) {
            console.error('Service offer parse error:', e);
            return;
        }
    } 
    // Handle service messages
    else if (messageType === 'service') {
        try {
            const serviceData = JSON.parse(decodeHtmlEntities(m.message || '{}'));
            const bookBtn = isSent ? '' : `<button onclick="bookService(this, '${serviceData.service_id}');" style="width: 100%; padding: 0.65rem; background: rgba(255,255,255,0.25); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">Book Service</button>`;
            group.innerHTML = `<div class="message ${isSent ? 'sent' : 'received'}"><div class="message-bubble" style="padding: 0.875rem;"><div style="font-weight: 700; font-size: 1rem; margin-bottom: 0.5rem;"><i class="fas fa-briefcase"></i> ${serviceData.service_name}</div>${serviceData.description ? `<div style="font-size: 0.85rem; margin-bottom: 0.5rem; opacity: 0.95;">${serviceData.description}</div>` : ''}<div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.75rem; color: ${isSent ? 'rgba(255,255,255,0.95)' : 'var(--text-primary)'};">Starting: RWF ${parseFloat(serviceData.price).toFixed(0)}</div>${bookBtn}</div></div><div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>`;
        } catch (e) {
            console.error('Service message parse error:', e);
            return;
        }
    }
    // Handle location messages
    else if (messageType === 'location') {
        try {
            const locationData = JSON.parse(decodeHtmlEntities(m.message || '{}'));
            const mapUrl = `https://www.openstreetmap.org/?mlat=${locationData.latitude}&mlon=${locationData.longitude}#map=18/${locationData.latitude}/${locationData.longitude}`;
            const mapIframeUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${locationData.longitude - 0.007},${locationData.latitude - 0.005},${locationData.longitude + 0.007},${locationData.latitude + 0.005}&layer=mapnik&marker=${locationData.latitude},${locationData.longitude}`;
            group.innerHTML = `
                <div class="message ${isSent ? 'sent' : 'received'}">
                    <div class="message-bubble">
                        <div class="location-card">
                            <div class="location-card-header"><i class="fas fa-map-marker-alt"></i> ${locationData.label || 'Shared live location'}</div>
                            <div class="location-card-body"><iframe src="${mapIframeUrl}" class="location-map" loading="lazy" style="border:none; width:100%; height:180px; border-radius:10px;"></iframe></div>
                            <div class="location-card-actions"><a href="${mapUrl}" target="_blank" rel="noopener noreferrer">Open in map</a></div>
                        </div>
                    </div>
                </div>
                <div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
            `;
        } catch (e) {
            console.error('Failed to parse location message', e);
            return;
        }
    } else if (!messageType || messageType === 'text') {
        try {
            const locationData = JSON.parse(decodeHtmlEntities(m.message || '{}'));
            if (locationData && typeof locationData.latitude !== 'undefined' && typeof locationData.longitude !== 'undefined') {
                const mapUrl = `https://www.openstreetmap.org/?mlat=${locationData.latitude}&mlon=${locationData.longitude}#map=18/${locationData.latitude}/${locationData.longitude}`;
                const mapIframeUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${locationData.longitude - 0.007},${locationData.latitude - 0.005},${locationData.longitude + 0.007},${locationData.latitude + 0.005}&layer=mapnik&marker=${locationData.latitude},${locationData.longitude}`;
                group.innerHTML = `
                    <div class="message ${isSent ? 'sent' : 'received'}">
                        <div class="message-bubble">
                            <div class="location-card">
                                <div class="location-card-header"><i class="fas fa-map-marker-alt"></i> ${locationData.label || 'Shared live location'}</div>
                                <div class="location-card-body"><iframe src="${mapIframeUrl}" class="location-map" loading="lazy" style="border:none; width:100%; height:180px; border-radius:10px;"></iframe></div>
                                <div class="location-card-actions"><a href="${mapUrl}" target="_blank" rel="noopener noreferrer">Open in map</a></div>
                            </div>
                        </div>
                    </div>
                    <div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                `;
                messagesArea.appendChild(group);
                const isScrolledToBottom = (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight) < 100;
                if (isScrolledToBottom) {
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }
                return;
            }
        } catch (e) {
            // ignore invalid JSON fallback
        }
    }
    // Handle regular messages with attachments
    else {
        if (attachmentPath) {
            const isImage = /\.(jpg|jpeg|png|gif)$/i.test(attachmentPath);
            const isAudio = /\.(webm|ogg|mp3|wav)$/i.test(attachmentPath);
            if (isImage) {
                attachmentHtml += `<div class="attachment-preview"><img src="../${attachmentPath}" alt="Attachment" class="attach-img" style="max-width: 280px; border-radius: 8px;" /></div>`;
            } else if (isAudio) {
                const ext = attachmentPath.split('.').pop().toLowerCase();
                const audioType = ext === 'mp3' ? 'mpeg' : ext;
                attachmentHtml += `<div class="attachment-preview"><audio controls class="attach-audio" style="width: 100%;"><source src="../${attachmentPath}" type="audio/${audioType}">Your browser does not support audio playback.</audio></div>`;
            }
            if (messageType === 'audio') {
                voiceBadgeHtml = '<div class="voice-badge" style="display: block; font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.9); margin-bottom: 0.5rem;"><i class="fas fa-microphone"></i> Voice message</div>';
            }
            attachmentHtml += `<div class="attachment-link" style="margin-top: 0.5rem;"><a href="../${attachmentPath}" target="_blank" rel="noopener noreferrer" style="color: ${isSent ? 'rgba(255,255,255,0.8)' : 'var(--accent)'}; text-decoration: none; font-size: 0.85rem;"><i class="fas fa-download"></i> View attachment</a></div>`;
        }
        
        group.innerHTML = `
            <div class="message ${isSent ? 'sent' : 'received'}" style="animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
                <div class="message-bubble">
                    ${voiceBadgeHtml}
                    <div>${decodeHtmlEntities(m.message || '').replace(/\n/g,'<br>')}</div>
                    ${attachmentHtml}
                </div>
            </div>
            <div class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
        `;
    }
    
    messagesArea.appendChild(group);
    
    // Auto-scroll only if user is already scrolled to bottom
    const isScrolledToBottom = (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight) < 100;
    if (isScrolledToBottom) {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
}

// Update conversation item in the list
function updateConversationPreview(userId, newPreview) {
    const convItem = document.querySelector(`.conversation-item[onclick*="loadConversation(${userId})"]`);
    if (convItem) {
        const preview = convItem.querySelector('.conversation-preview');
        if (preview) {
            preview.textContent = newPreview;
        }
        const unreadBadge = convItem.querySelector('.unread-badge');
        if (unreadBadge && newPreview === 'No messages yet') {
            unreadBadge.remove();
        }
    }
}

function clearConversationMessages() {
    const messagesArea = document.getElementById('messagesArea');
    if (!messagesArea) return;

    messagesArea.querySelectorAll('.message-group').forEach(el => el.remove());
    const emptyState = messagesArea.querySelector('.msg-empty');
    if (emptyState) {
        emptyState.style.display = 'block';
    } else {
        const placeholder = document.createElement('div');
        placeholder.className = 'msg-empty';
        placeholder.innerHTML = '<i class="fas fa-comments"></i><p>No messages in this conversation.</p>';
        messagesArea.appendChild(placeholder);
    }
    messagesArea.scrollTop = messagesArea.scrollHeight;
    lastMessageId = 0;
}

function renderConversationHistory(messages) {
    const messagesArea = document.getElementById('messagesArea');
    if (!messagesArea) return;

    const emptyState = messagesArea.querySelector('.msg-empty');
    if (emptyState) emptyState.style.display = 'none';
    messagesArea.querySelectorAll('.message-group').forEach(el => el.remove());

    let maxId = 0;
    messages.forEach(m => {
        renderNewMessage(m, messagesArea);
        maxId = Math.max(maxId, Number(m.id) || 0);
    });
    lastMessageId = maxId;
}

function formatLastMessagePreview(message) {
    if (!message) return 'No messages yet';
    if (message.message_type === 'audio' || message.attachment_type === 'audio') {
        return '🎤 Voice note';
    }
    if (typeof message.message === 'string') {
        return message.message.substring(0, 50);
    }
    return 'No messages yet';
}

// Professional polling function  
function pollForNewMessages() {
    if (!pollUser) return; // No conversation selected
    
    fetch('messages.php?action=poll&with=' + pollUser + (pollBookingId ? '&booking_id=' + pollBookingId : ''), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store'
    })
    .then(r => {
        if (!r.ok) throw new Error('Poll failed: ' + r.status);
        return r.json();
    })
    .then(data => {
        if (!data.success || !Array.isArray(data.messages)) return;

        const messagesArea = document.getElementById('messagesArea');
        const existingGroups = messagesArea ? messagesArea.querySelectorAll('.message-group') : [];
        const incomingIds = data.messages.map(m => Number(m.id) || 0);
        const hasCurrentLastId = existingGroups.length === 0 || incomingIds.includes(lastMessageId);

        if (data.messages.length === 0) {
            if (existingGroups.length > 0) {
                clearConversationMessages();
                updateConversationPreview(pollUser, 'No messages yet');
            }
        } else {
            if (!hasCurrentLastId) {
                renderConversationHistory(data.messages);
            } else {
                const newMessages = data.messages.filter(m => Number(m.id) > lastMessageId);
                if (newMessages.length > 0 && messagesArea) {
                    const emptyState = messagesArea.querySelector('.msg-empty');
                    if (emptyState) emptyState.style.display = 'none';

                    newMessages.forEach(m => {
                        renderNewMessage(m, messagesArea);
                    });
                    lastMessageId = Math.max(lastMessageId, ...newMessages.map(m => Number(m.id) || 0));
                }
            }
            updateConversationPreview(pollUser, formatLastMessagePreview(data.messages[data.messages.length - 1]));
        }
        
        // Update booking timeline if present
        if (Array.isArray(data.booking_timeline) && data.booking_timeline.length) {
            const timelineArea = document.getElementById('bookingTimeline');
            if (timelineArea) {
                // Only update if content changed
                const newHtml = data.booking_timeline.map(item => `
                    <div class="timeline-item timeline-${item.status || ''}">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">${item.label}</div>
                            ${item.time ? `<div class="timeline-time">${new Date(item.time).toLocaleString()}</div>` : ''}
                        </div>
                    </div>
                `).join('');
                
                if (timelineArea.innerHTML !== newHtml) {
                    timelineArea.innerHTML = newHtml;
                }
            }
        }
    })
    .catch(err => {
        // Silent fail - normal for polling, don't spam console
        if (err.message && !err.message.includes('Poll failed')) {
            console.debug('Poll: Network check failed', err.message);
        }
    });
}

function handleChatCleared() {
    clearConversationMessages();
    updateConversationPreview(pollUser, 'No messages yet');
}

function handleConversationDeleted() {
    window.location.href = window.location.pathname;
}

// Start polling when a conversation is open
if (pollUser) {
    // Initial poll immediately
    pollForNewMessages();
    
    // Then poll every 2 seconds for new messages
    pollInterval = setInterval(pollForNewMessages, 2000);
    
    // Clean up polling when user leaves the page
    window.addEventListener('beforeunload', () => {
        if (pollInterval) clearInterval(pollInterval);
    });
}

// Add fade-in animation for new messages
if (!document.querySelector('style[data-animation]')) {
    const style = document.createElement('style');
    style.setAttribute('data-animation', '1');
    style.textContent = `
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
}

// Input menu dropdown functionality
const inputMenuDropdown = document.getElementById('inputMenuDropdown');
const menuAttachFile = document.getElementById('menuAttachFile');
const menuSendOffer = document.getElementById('menuSendOffer');
const menuSendService = document.getElementById('menuSendService');

// Prevent file input from being triggered except through Send File button
let allowFileInput = false;
if (attachmentInput) {
    attachmentInput.addEventListener('click', function(e) {
        if (!allowFileInput) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
}

if (attachButton) {
    attachButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const isVisible = inputMenuDropdown.classList.contains('visible');
        inputMenuDropdown.classList.toggle('visible', !isVisible);
    });
}

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.input-row')) {
        inputMenuDropdown.classList.remove('visible');
    }
});

if (menuAttachFile) {
    menuAttachFile.addEventListener('click', function() {
        inputMenuDropdown.classList.remove('visible');
        allowFileInput = true;
        document.getElementById('attachmentInput')?.click();
        // Reset after a brief delay
        setTimeout(() => { allowFileInput = false; }, 100);
    });
}

// Service Offer Modal
const serviceOfferModal = document.getElementById('serviceOfferModal');
const offerServiceList = document.getElementById('offerServiceList');
const sendOfferBtn = document.getElementById('sendOfferBtn');
const offerNegotiableCheck = document.getElementById('offerNegotiableCheck');

if (menuSendOffer) {
    menuSendOffer.addEventListener('click', function() {
        inputMenuDropdown.classList.remove('visible');
        openServiceOfferModal();
    });
}

function openServiceOfferModal() {
    serviceOfferModal.classList.add('show');
    serviceOfferModal.setAttribute('aria-hidden', 'false');
    
    // Load services
    fetch('../api/service_offers.php?action=get_services')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.services) {
                renderOfferServices(data.services);
            }
        })
        .catch(console.error);
}

function renderOfferServices(services) {
    offerServiceList.innerHTML = '';
    
    if (!services || services.length === 0) {
        offerServiceList.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-muted);">No services available</div>';
        return;
    }
    
    services.forEach(service => {
        const div = document.createElement('div');
        div.className = 'service-item';
        const minPrice = service.min_price ? parseFloat(service.min_price).toFixed(2) : parseFloat(service.price).toFixed(2);
        const maxPrice = service.max_price ? parseFloat(service.max_price).toFixed(2) : parseFloat(service.price).toFixed(2);
        const priceRange = minPrice === maxPrice ? `RWF ${minPrice}` : `RWF ${minPrice} - ${maxPrice}`;
        
        div.innerHTML = `
            <div class="service-name">${service.name || 'Unnamed Service'}</div>
            <div class="service-desc">${service.description || ''}</div>
            <div class="service-price">${priceRange}</div>
        `;
        div.addEventListener('click', function() {
            document.querySelectorAll('#offerServiceList .service-item').forEach(el => el.classList.remove('selected'));
            div.classList.add('selected');
            div.dataset.serviceId = service.id;
            div.dataset.serviceName = service.name;
            div.dataset.servicePrice = service.price;
            sendOfferBtn.disabled = false;
        });
        offerServiceList.appendChild(div);
    });
}

// Service Modal
const serviceModal = document.getElementById('serviceModal');
const serviceList = document.getElementById('serviceList');
const sendServiceBtn = document.getElementById('sendServiceBtn');

if (menuSendService) {
    menuSendService.addEventListener('click', function() {
        inputMenuDropdown.classList.remove('visible');
        openServiceModal();
    });
}

function openServiceModal() {
    serviceModal.classList.add('show');
    serviceModal.setAttribute('aria-hidden', 'false');
    
    // Load services
    fetch('../api/service_offers.php?action=get_services')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.services) {
                renderServices(data.services);
            }
        })
        .catch(console.error);
}

function renderServices(services) {
    serviceList.innerHTML = '';
    
    if (!services || services.length === 0) {
        serviceList.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-muted);">No services available</div>';
        return;
    }
    
    services.forEach(service => {
        const div = document.createElement('div');
        div.className = 'service-item';
        div.innerHTML = `
            <div class="service-name">${service.name || 'Unnamed Service'}</div>
            <div class="service-desc">${service.description || ''}</div>
            <div class="service-price">RWF ${parseFloat(service.price).toFixed(2)}</div>
        `;
        div.addEventListener('click', function() {
            document.querySelectorAll('#serviceList .service-item').forEach(el => el.classList.remove('selected'));
            div.classList.add('selected');
            div.dataset.serviceId = service.id;
            div.dataset.serviceName = service.name;
            div.dataset.servicePrice = service.price;
            sendServiceBtn.disabled = false;
        });
        serviceList.appendChild(div);
    });
}

// Close modals
function closeServiceOfferModal() {
    serviceOfferModal.classList.remove('show');
    serviceOfferModal.setAttribute('aria-hidden', 'true');
    sendOfferBtn.disabled = true;
}

function closeServiceModal() {
    serviceModal.classList.remove('show');
    serviceModal.setAttribute('aria-hidden', 'true');
    sendServiceBtn.disabled = true;
}

// Close buttons
document.querySelectorAll('.service-modal .service-modal-close').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.closest('#serviceOfferModal')) {
            closeServiceOfferModal();
        } else if (this.closest('#serviceModal')) {
            closeServiceModal();
        }
    });
});

// Cancel buttons
document.querySelectorAll('.service-modal .btn-cancel').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.closest('#serviceOfferModal')) {
            closeServiceOfferModal();
        } else if (this.closest('#serviceModal')) {
            closeServiceModal();
        }
    });
});

// Send Offer button
if (sendOfferBtn) {
    sendOfferBtn.addEventListener('click', function() {
        const selectedService = document.querySelector('#offerServiceList .service-item.selected');
        if (!selectedService || !selectedService.dataset.serviceId) {
            alert('Please select a service');
            return;
        }
        
        const serviceId = selectedService.dataset.serviceId;
        const isNegotiable = offerNegotiableCheck.checked;
        
        sendOfferMessage(serviceId, isNegotiable);
        closeServiceOfferModal();
    });
}

// Send Service button
if (sendServiceBtn) {
    sendServiceBtn.addEventListener('click', function() {
        const selectedService = document.querySelector('#serviceList .service-item.selected');
        if (!selectedService || !selectedService.dataset.serviceId) {
            alert('Please select a service');
            return;
        }
        
        const serviceId = selectedService.dataset.serviceId;
        sendServiceMessage(serviceId);
        closeServiceModal();
    });
}

function sendOfferMessage(serviceId, isNegotiable) {
    const formData = new FormData();
    formData.append('receiver_id', <?php echo $with; ?>);
    formData.append('booking_id', <?php echo $booking_id; ?>);
    formData.append('ajax', '1');
    formData.append('offer_service_id', serviceId);
    formData.append('offer_negotiable', isNegotiable ? '1' : '0');
    formData.append('message_type', 'service_offer');
    
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
            
            const offerInfo = data.message?.offer_info || {};
            const serviceName = offerInfo.service_name || 'Service Offer';
            const negotiableTag = offerInfo.negotiable ? '<span style="background: rgba(255,255,255,0.2); padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.7rem; margin-top: 0.5rem; display: inline-block;">NEGOTIABLE</span>' : '';
            const minPrice = offerInfo.min_price ? parseFloat(offerInfo.min_price).toFixed(0) : parseFloat(offerInfo.price).toFixed(0);
            const maxPrice = offerInfo.max_price ? parseFloat(offerInfo.max_price).toFixed(0) : parseFloat(offerInfo.price).toFixed(0);
            const priceText = minPrice === maxPrice ? `RWF ${minPrice}` : `RWF ${minPrice} - ${maxPrice}`;
            
            group.innerHTML = `
                <div class="message sent">
                    <div class="message-bubble" style="padding: 0.875rem;">
                        <div style="font-weight: 700; font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-gift"></i> ${serviceName}
                        </div>
                        ${offerInfo.description ? `<div style="font-size: 0.85rem; opacity: 0.95; margin-bottom: 0.5rem;">${offerInfo.description}</div>` : ''}
                        <div style="font-weight: 600; font-size: 0.95rem; color: rgba(255,255,255,0.95);">Base Price: ${priceText}</div>
                        ${negotiableTag}
                        <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem; justify-content: space-between;">
                            <button onclick="negotiateOfferPrice(this, '${serviceId}');" style="flex: 1; padding: 0.5rem; background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">Make Offer</button>
                            <button onclick="acceptOfferDirect(this, '${serviceId}');" style="flex: 1; padding: 0.5rem; background: rgba(255,255,255,0.3); border: none; color: white; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">Accept</button>
                        </div>
                    </div>
                </div>
                <div class="message-time">${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
            `;
            messagesArea.appendChild(group);
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    })
    .catch(console.error);
}

function sendServiceMessage(serviceId) {
    const formData = new FormData();
    formData.append('receiver_id', <?php echo $with; ?>);
    formData.append('booking_id', <?php echo $booking_id; ?>);
    formData.append('ajax', '1');
    formData.append('service_id', serviceId);
    formData.append('message_type', 'service');
    
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
            
            const serviceInfo = data.message?.service_info || {};
            const serviceName = serviceInfo.service_name || 'Service';
            
            group.innerHTML = `
                <div class="message sent">
                    <div class="message-bubble" style="padding: 0.875rem;">
                        <div style="font-weight: 700; font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-briefcase"></i> ${serviceName}
                        </div>
                        ${serviceInfo.description ? `<div style="font-size: 0.85rem; opacity: 0.95; margin-bottom: 0.5rem; line-height: 1.4;">${serviceInfo.description}</div>` : ''}
                        ${serviceInfo.price ? `<div style="font-weight: 600; font-size: 0.95rem; color: rgba(255,255,255,0.95); margin-bottom: 0.75rem;">Starting Price: RWF ${parseFloat(serviceInfo.price).toFixed(0)}</div>` : ''}
                        <button onclick="bookService(this, '${serviceId}', '${serviceName}');" style="width: 100%; padding: 0.65rem; background: rgba(255,255,255,0.25); border: none; color: white; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: all 0.2s ease;">Book This Service</button>
                    </div>
                </div>
                <div class="message-time">${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
            `;
            messagesArea.appendChild(group);
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    })
    .catch(console.error);
}

// Helper functions for booking and negotiation
function bookService(btn, serviceId, serviceName) {
    const clientId = <?php echo $with; ?>;
    window.location.href = `../client/booking.php?provider_id=${clientId}&service_id=${serviceId}`;
}

function negotiateOfferPrice(btn, serviceId) {
    // Open negotiation dialog
    const clientId = <?php echo $with; ?>;
    const priceInput = prompt('Enter your counter-offer price (RWF):', '');
    if (priceInput && !isNaN(parseFloat(priceInput)) && parseFloat(priceInput) > 0) {
        const formData = new FormData();
        formData.append('action', 'create_offer');
        formData.append('service_id', serviceId);
        formData.append('offered_price', parseFloat(priceInput));
        formData.append('notes', 'Counter-offer from messaging');
        
        fetch('../api/service_offers.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const toast = document.getElementById('chatActionToast');
                if (toast) {
                    toast.textContent = 'Counter-offer sent!';
                    toast.className = 'chat-action-toast success';
                    toast.style.opacity = '1';
                    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
                }
            }
        })
        .catch(console.error);
    }
}

function acceptOfferDirect(btn, serviceId) {
    const clientId = <?php echo $with; ?>;
    window.location.href = `../client/booking.php?provider_id=${clientId}&service_id=${serviceId}`;
}
</script>
<script>
    window.chatContext = {
        withId: <?php echo (int) $with; ?>,
        bookingId: <?php echo (int) $booking_id; ?>,
        isProvider: <?php echo isProvider() ? 'true' : 'false'; ?>
    };
    window.chatLocationRoom = '<?php echo htmlspecialchars(normalizeConversationKey($me, $with)); ?>';
    window.chatUserId = <?php echo (int) $me; ?>;
    window.chatPartnerId = <?php echo (int) $with; ?>;
    window.chatBookingId = <?php echo (int) $booking_id; ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/live-location-chat.js"></script>
<script src="../assets/js/chat-dropdown.js"></script>
    </div> <!-- .main-content -->
</body>
</html>