/**
 * Enhanced Search with NLU Classification
 * Add this to your client/providers.php or create a new endpoint
 */

<?php
session_start();
require_once '../config/database.php';
require_once '../includes/NLUClient.php';
require_once '../includes/NLUIntegration.php';

// Get search query
$search_query = $_GET['q'] ?? $_POST['q'] ?? '';
$filters = [
    'location' => $_GET['location'] ?? '',
    'min_rating' => $_GET['min_rating'] ?? 0,
    'availability' => $_GET['availability'] ?? 'any'
];

// Initialize NLU
$nlu_integration = new NLUIntegration($pdo);

// Check if NLU service is available
$nlu_available = $nlu_integration->isServiceAvailable();

$results = [];
$classification = null;

if ($nlu_available && !empty($search_query)) {
    // Use NLU to classify the search
    $classification = $nlu_integration->processSearchQuery($search_query);
    
    if ($classification['success'] && isset($classification['detected_service'])) {
        $service_category = $classification['detected_service'];
        $confidence = $classification['confidence'];
        
        // Add to filters
        $filters['service_category'] = $service_category;
        $filters['nlu_classified'] = true;
        $filters['nlu_confidence'] = $confidence;
    }
}

// Build query based on filters (with or without NLU)
$query = "SELECT p.*, s.name as service_name, 
          AVG(r.rating) as avg_rating, COUNT(r.id) as total_reviews
          FROM providers p
          LEFT JOIN services s ON p.id = s.provider_id
          LEFT JOIN reviews r ON p.id = r.provider_id
          WHERE p.is_verified = 1 AND p.is_active = 1";

$params = [];

if (isset($filters['service_category'])) {
    $query .= " AND s.category = ?";
    $params[] = $filters['service_category'];
}

if (!empty($filters['location'])) {
    $query .= " AND p.location LIKE ?";
    $params[] = "%{$filters['location']}%";
}

if ($filters['min_rating'] > 0) {
    $query .= " HAVING AVG(r.rating) >= ?";
    $params[] = $filters['min_rating'];
}

$query .= " GROUP BY p.id ORDER BY avg_rating DESC LIMIT 20";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'search_query' => $search_query,
    'nlu_classification' => $classification,
    'nlu_available' => $nlu_available,
    'results_count' => count($results),
    'results' => $results,
    'filters' => $filters
]);
?>
