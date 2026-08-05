<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$response = ['success' => false, 'error' => 'Unknown error'];

try {
    $db = Database::getInstance()->getConnection();

    // Check if favorites table exists, if not create it
    try {
        $db->query("SELECT 1 FROM favorites LIMIT 1");
    } catch (Exception $e) {
        // Create favorites table if it doesn't exist
        $createTable = "
            CREATE TABLE IF NOT EXISTS favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                provider_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                UNIQUE KEY unique_favorite (client_id, provider_id)
            )
        ";
        $db->exec($createTable);
    }

    $client_id = (int) $_SESSION['user_id'];
    $provider_id = intval($_POST['provider_id'] ?? 0);

    if ($provider_id <= 0) {
        $response['error'] = 'Invalid provider ID';
        echo json_encode($response);
        exit;
    }

    $existingStmt = $db->prepare("SELECT 1 FROM favorites WHERE client_id = ? AND provider_id = ? LIMIT 1");
    $existingStmt->execute([$client_id, $provider_id]);
    $isFavorite = (bool) $existingStmt->fetchColumn();

    if (isset($_POST['add_to_favorites'])) {
        $stmt = $db->prepare("INSERT IGNORE INTO favorites (client_id, provider_id) VALUES (?, ?)");
        if ($stmt->execute([$client_id, $provider_id])) {
            $response['success'] = true;
            $response['message'] = 'Added to favorites';
            $response['action'] = 'added';
            $response['is_favorite'] = true;
        } else {
            $response['error'] = 'Failed to add favorite';
        }
    } elseif (isset($_POST['remove_from_favorites'])) {
        $stmt = $db->prepare("DELETE FROM favorites WHERE client_id = ? AND provider_id = ?");
        if ($stmt->execute([$client_id, $provider_id])) {
            $response['success'] = true;
            $response['message'] = 'Removed from favorites';
            $response['action'] = 'removed';
            $response['is_favorite'] = false;
        } else {
            $response['error'] = 'Failed to remove favorite';
        }
    } elseif ($isFavorite) {
        $stmt = $db->prepare("DELETE FROM favorites WHERE client_id = ? AND provider_id = ?");
        if ($stmt->execute([$client_id, $provider_id])) {
            $response['success'] = true;
            $response['message'] = 'Removed from favorites';
            $response['action'] = 'removed';
            $response['is_favorite'] = false;
        } else {
            $response['error'] = 'Failed to remove favorite';
        }
    } else {
        $stmt = $db->prepare("INSERT INTO favorites (client_id, provider_id) VALUES (?, ?)");
        if ($stmt->execute([$client_id, $provider_id])) {
            $response['success'] = true;
            $response['message'] = 'Added to favorites';
            $response['action'] = 'added';
            $response['is_favorite'] = true;
        } else {
            $response['error'] = 'Failed to add favorite';
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
