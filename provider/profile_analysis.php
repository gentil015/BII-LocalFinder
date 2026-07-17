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

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$providerRow = $stmt->fetch();

if (empty($providerRow['id']) || !isProviderAIEnabled($providerRow['id'])) {
    echo json_encode(['success' => false, 'error' => 'AI features are disabled for this provider.']);
    exit;
}

$aiHelper = new AIHelper($db);

// Get current provider data
$stmt = $db->prepare("
    SELECT sp.*, u.full_name, u.phone, u.profile_image
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get provider categories
$stmt = $db->prepare("
    SELECT c.* 
    FROM categories c
    JOIN provider_services ps ON c.id = ps.category_id
    WHERE ps.provider_id = ?
");
$stmt->execute([$provider['id']]);
$categories = $stmt->fetchAll();

// Analyze profile
$analysis = $aiHelper->analyzeProfileQuality($provider, $categories);

echo json_encode([
    'success' => true,
    'analysis' => $analysis
]);
?>