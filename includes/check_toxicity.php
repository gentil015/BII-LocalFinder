<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ai_helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    
    if (empty($text)) {
        echo json_encode(['is_toxic' => false, 'score' => 0]);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    $aiHelper = new AIHelper($db);
    
    $result = $aiHelper->detectToxicity($text);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Toxicity check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error processing request',
        'is_toxic' => false,
        'score' => 0
    ]);
}