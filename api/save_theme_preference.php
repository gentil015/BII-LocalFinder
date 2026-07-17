<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    ensureProviderSettingsSchema($db);

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $theme = $input['theme'] ?? 'light';
    $provider_id = $input['provider_id'] ?? null;

    // Validate theme
    if (!in_array($theme, ['light', 'dark'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid theme']);
        exit;
    }

    // Validate provider_id
    if (!$provider_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Provider ID required']);
        exit;
    }

    // Verify provider belongs to current user
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE id = ? AND user_id = ?");
    $stmt->execute([$provider_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Save theme preference
    $stmt = $db->prepare(
        "INSERT INTO provider_settings (provider_id, setting_key, setting_value, created_at) VALUES (?, 'appearance_theme', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([$provider_id, $theme]);

    echo json_encode(['success' => true, 'message' => 'Theme preference saved']);

} catch (Exception $e) {
    error_log("Theme preference save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save theme preference']);
}
?>