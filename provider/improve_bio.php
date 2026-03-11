<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ai_helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isProvider()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$bio = $input['bio'] ?? '';
$profession = $input['profession'] ?? '';
$experience = $input['experience'] ?? 0;

if (empty($bio) || strlen($bio) < 20) {
    echo json_encode(['success' => false, 'error' => 'Bio too short']);
    exit;
}

$db = Database::getInstance()->getConnection();
$aiHelper = new AIHelper($db);

$improvedBio = $aiHelper->improveProfessionalBio($bio, $profession, $experience);

echo json_encode([
    'success' => !empty($improvedBio),
    'improved_bio' => $improvedBio,
    'original_length' => strlen($bio),
    'improved_length' => strlen($improvedBio)
]);
?>