<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
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
$stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

if (empty($provider['id']) || !isProviderAIEnabled($provider['id'])) {
    echo json_encode(['success' => false, 'error' => 'AI features are disabled for this provider.']);
    exit;
}

// Check if AI description improvement sub-feature is enabled
if (getProviderSetting($provider['id'], 'ai_features_ai_description_improvement') != '1') {
    echo json_encode(['success' => false, 'error' => 'AI description improvement is disabled. Enable it in settings.']);
    exit;
}

$aiHelper = new AIHelper($db);

$improvedBio = $aiHelper->improveProfessionalBio($bio, $profession, $experience);

echo json_encode([
    'success' => !empty($improvedBio),
    'improved_bio' => $improvedBio,
    'original_length' => strlen($bio),
    'improved_length' => strlen($improvedBio)
]);
?>