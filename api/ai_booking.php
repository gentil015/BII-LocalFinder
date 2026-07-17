<?php
// api/ai-booking.php
// RESTful API endpoint for AI booking system

header('Content-Type: application/json');
session_start();

// Prevent PHP errors breaking JSON responses; log to file instead
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');
// Ensure logs directory exists
if (!is_dir(__DIR__ . '/../logs')) {
    @mkdir(__DIR__ . '/../logs', 0755, true);
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';
require_once '../includes/ai_booking.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isProvider()) {
    http_response_code(403);
    echo json_encode(['error' => 'Providers cannot create bookings']);
    exit();
}

$db = Database::getInstance()->getConnection();
$aiBooking = new AIBookingHandler($db);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'process':
            // Process natural language booking request
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $prompt = trim($input['prompt'] ?? '');
            
            if (empty($prompt)) {
                throw new Exception('Prompt is required');
            }
            
            $result = $aiBooking->processBookingRequest($prompt, $_SESSION['user_id']);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'create':
            // Create booking from extracted data
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = getJsonInput();
            $providerId = intval($input['provider_id'] ?? 0);
            $extractedData = $input['extracted_data'] ?? null;
            
            if (!$providerId || !$extractedData) {
                throw new Exception('Invalid booking data');
            }
            
            $result = $aiBooking->createBooking(
                $extractedData,
                $providerId,
                $_SESSION['user_id']
            );
            
            echo json_encode($result);
            break;
            
        case 'extract':
            // Extract specific information from text
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = getJsonInput();
            $text = trim((string) ($input['text'] ?? ''));
            $type = $input['type'] ?? 'all'; // service, location, date, time, all
            
            if (empty($text)) {
                throw new Exception('Text is required');
            }
            
            $extracted = [];
            
            if ($type === 'all' || $type === 'service') {
                $extracted['service'] = $aiBooking->extractService($text);
            }
            if ($type === 'all' || $type === 'location') {
                $extracted['location'] = $aiBooking->extractLocation($text);
            }
            if ($type === 'all' || $type === 'date') {
                $extracted['date'] = $aiBooking->extractDate($text);
            }
            if ($type === 'all' || $type === 'time') {
                $extracted['time'] = $aiBooking->extractTime($text);
            }
            
            echo json_encode([
                'success' => true,
                'extracted' => $extracted
            ]);
            break;
            
        case 'providers':
            // Get matching providers for criteria
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = getJsonInput();
            $service = $input['service'] ?? null;
            $location = $input['location'] ?? null;
            
            if (!$service) {
                throw new Exception('Service is required');
            }
            
            $extracted = [
                'service' => ['profession' => $service, 'confidence' => 1],
                'location' => ['location' => $location, 'confidence' => 1]
            ];
            
            $providers = $aiBooking->findMatchingProviders($extracted);
            
            echo json_encode([
                'success' => true,
                'providers' => $providers,
                'count' => count($providers)
            ]);
            break;
            
        case 'validate':
            // Validate booking data
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = getJsonInput();
            $errors = [];
            
            if (empty($input['service'])) {
                $errors[] = 'Service is required';
            }
            if (empty($input['location'])) {
                $errors[] = 'Location is required';
            }
            if (empty($input['date'])) {
                $errors[] = 'Date is required';
            } else {
                $date = strtotime($input['date']);
                if ($date < strtotime('today')) {
                    $errors[] = 'Date cannot be in the past';
                }
            }
            if (empty($input['provider_id'])) {
                $errors[] = 'Provider is required';
            }
            
            echo json_encode([
                'valid' => empty($errors),
                'errors' => $errors
            ]);
            break;
            
        case 'suggestions':
            // Get AI suggestions for improving booking description
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $prompt = trim($input['prompt'] ?? '');
            
            if (empty($prompt)) {
                throw new Exception('Prompt is required');
            }
            
            $suggestions = [];
            
            // Check if service is mentioned
            $serviceInfo = $aiBooking->extractService($prompt);
            if ($serviceInfo['confidence'] < 0.7) {
                $suggestions[] = [
                    'type' => 'service',
                    'message' => 'Try specifying the type of service you need (e.g., plumber, electrician, cleaner)'
                ];
            }
            
            // Check if location is mentioned
            $locationInfo = $aiBooking->extractLocation($prompt);
            if ($locationInfo['confidence'] < 0.7) {
                $suggestions[] = [
                    'type' => 'location',
                    'message' => 'Include your location for better provider matches (e.g., Kimironko, Remera)'
                ];
            }
            
            // Check if date/time is mentioned
            $dateInfo = $aiBooking->extractDate($prompt);
            if ($dateInfo['confidence'] < 0.5) {
                $suggestions[] = [
                    'type' => 'date',
                    'message' => 'Mention when you need the service (e.g., tomorrow, next Monday, 2pm)'
                ];
            }
            
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions,
                'has_suggestions' => !empty($suggestions)
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}