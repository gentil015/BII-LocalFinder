<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if provider is logged in
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
    $section = $input['section'] ?? null;
    $key = $input['key'] ?? null;
    $value = $input['value'] ?? null;

    if (!$section || !$key) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Section and key are required']);
        exit;
    }

    // Get provider profile
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $provider = $stmt->fetch();

    if (!$provider) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        exit;
    }

    $provider_id = $provider['id'];

    // Sanitize section and key
    $section = sanitize($section);
    $key = sanitize($key);
    
    // Convert value to 0 or 1 for boolean settings
    if ($value === 'true' || $value === 1 || $value === '1') {
        $value = 1;
    } else {
        $value = 0;
    }

    // Build the full setting key
    $setting_key = $section . '_' . $key;

    // Save the setting to database
    $stmt = $db->prepare(
        "INSERT INTO provider_settings (provider_id, setting_key, setting_value, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?"
    );
    $stmt->execute([$provider_id, $setting_key, $value, $value]);

    echo json_encode([
        'success' => true,
        'message' => 'Setting saved successfully',
        'data' => [
            'section' => $section,
            'key' => $key,
            'value' => $value
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("Provider setting save error: " . $e->getMessage());
}
?>
