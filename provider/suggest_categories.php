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
$profession = $input['profession'] ?? '';
$bio = $input['bio'] ?? '';

if (empty($profession)) {
    echo json_encode(['success' => false, 'error' => 'Profession required']);
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

$aiHelper = new AIHelper($db);

$suggestedCategories = $aiHelper->suggestCategoriesFromProfession($profession, $bio);

echo json_encode([
    'success' => !empty($suggestedCategories),
    'suggestions' => $suggestedCategories,
    'count' => count($suggestedCategories)
]);
?>