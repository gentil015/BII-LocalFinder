<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chat.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$sender_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;

if ($sender_id <= 0 || $receiver_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid sender or receiver']);
    exit;
}

if (empty($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No audio file uploaded']);
    exit;
}

$file = $_FILES['voice'];

// Validate size (10MB max)
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Audio file too large']);
    exit;
}

$allowedExt = ['webm', 'ogg', 'mp3', 'wav'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid audio format']);
    exit;
}

// Validate mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = [
    'audio/webm',
    'audio/ogg',
    'audio/mpeg',
    'audio/wav',
    'audio/x-wav',
];
if (!in_array($mime, $allowedMime, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid audio MIME type']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/chat/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = uniqid('voice_', true) . '.' . $ext;
$target = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save file']);
    exit;
}

$filePath = 'uploads/chat/' . $filename;
$fileSize = filesize($target);

$inserted = sendAudioMessage($sender_id, $receiver_id, $filePath, $fileSize, $duration);

if (!$inserted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save message']);
    exit;
}

// Return the message data for client-side rendering
echo json_encode([
    'success' => true,
    'message' => [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'message' => '',
        'message_type' => 'audio',
        'attachment_path' => $filePath,
        'file_path' => $filePath,
        'file_size' => $fileSize,
        'audio_duration' => $duration,
        'created_at' => date('Y-m-d H:i:s'),
    ]
]);
