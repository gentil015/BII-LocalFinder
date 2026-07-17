<?php
/**
 * NLU Integration Example
 * Shows how to integrate NLU classification into your application
 * 
 * This example demonstrates:
 * 1. Classifying search queries
 * 2. Auto-suggesting service categories
 * 3. Routing users to relevant providers
 * 4. Logging classification results
 */

require_once 'config/database.php';
require_once 'includes/NLUClient.php';

class NLUIntegration
{
    private $nlu;
    private $db;
    private $nlu_url = 'http://localhost:8001';
    
    public function __construct($database = null)
    {
        $this->nlu = new NLUClient($this->nlu_url);
        $this->db = $database;
    }
    
    /**
     * Process user search query with NLU
     * 
     * @param string $query User's search query
     * @return array Search results with classification
     */
    public function processSearchQuery($query)
    {
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Empty query'
            ];
        }
        
        // Classify the query
        $classification = $this->nlu->classify($query);
        
        if (!$classification) {
            return [
                'success' => false,
                'error' => 'NLU service unavailable',
                'query' => $query
            ];
        }
        
        // Log the classification
        $this->logClassification($query, $classification);
        
        // Get high-confidence predictions
        if ($classification['score'] > 0.7) {
            $service_category = $classification['label'];
            $providers = $this->getProvidersByCategory($service_category);
            
            return [
                'success' => true,
                'query' => $query,
                'detected_service' => $service_category,
                'confidence' => $classification['score'],
                'providers' => $providers,
                'source' => 'nlu'
            ];
        } else {
            // Low confidence - return all categories
            return [
                'success' => true,
                'query' => $query,
                'detected_service' => $classification['label'],
                'confidence' => $classification['score'],
                'note' => 'Low confidence - showing all options',
                'source' => 'fallback'
            ];
        }
    }
    
    /**
     * Classify booking request and suggest providers
     * 
     * @param string $description Booking description
     * @param string $location User location (optional)
     * @return array Classification and suggested providers
     */
    public function classifyBookingRequest($description, $location = null)
    {
        $classification = $this->nlu->classify($description);
        
        if (!$classification) {
            return null;
        }
        
        // Log booking classification
        $this->logBookingClassification($description, $classification, $location);
        
        // Get providers
        $providers = $this->getProvidersByCategory(
            $classification['label'],
            $location
        );
        
        return [
            'service' => $classification['label'],
            'confidence' => $classification['score'],
            'suggested_providers' => $providers,
            'auto_filled' => $classification['score'] > 0.85
        ];
    }
    
    /**
     * Batch classify multiple text inputs
     * 
     * @param array $texts Array of texts to classify
     * @return array Results for each text
     */
    public function batchClassify($texts)
    {
        if (empty($texts) || count($texts) > 50) {
            return null;
        }
        
        $batch_result = $this->nlu->classifyBatch($texts);
        
        if (!$batch_result) {
            return null;
        }
        
        $results = [];
        foreach ($batch_result['predictions'] as $prediction) {
            $results[] = [
                'text' => $prediction['text'],
                'service' => $prediction['label'],
                'confidence' => $prediction['score'],
                'language' => $prediction['language']
            ];
        }
        
        return $results;
    }
    
    /**
     * Get service categories from NLU model
     * 
     * @return array Available service categories
     */
    public function getServiceCategories()
    {
        $response = $this->nlu->getCategories();
        return $response ? $response['categories'] : [];
    }
    
    /**
     * Check if NLU service is available
     * 
     * @return bool True if service is healthy
     */
    public function isServiceAvailable()
    {
        return $this->nlu->healthCheck();
    }
    
    /**
     * Private helper methods
     */
    
    private function getProvidersByCategory($category, $location = null)
    {
        if (!$this->db) {
            return [];
        }
        
        // Basic query - customize based on your database schema
        $query = "SELECT * FROM providers 
                  WHERE service_category = ? 
                  AND is_active = 1 
                  ORDER BY rating DESC 
                  LIMIT 10";
        
        $params = [$category];
        
        if ($location) {
            $query .= " AND location LIKE ?";
            $params[] = "%{$location}%";
        }
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    private function logClassification($query, $classification)
    {
        if (!$this->db) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO nlu_classifications 
                (query, service_category, confidence, language, created_at) 
                VALUES (?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([
                $query,
                $classification['label'],
                $classification['score'],
                $classification['language'] ?? 'en'
            ]);
        } catch (Exception $e) {
            error_log("Logging error: " . $e->getMessage());
        }
    }
    
    private function logBookingClassification($description, $classification, $location)
    {
        if (!$this->db) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO nlu_booking_classifications 
                (description, service_category, confidence, location, created_at) 
                VALUES (?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([
                $description,
                $classification['label'],
                $classification['score'],
                $location
            ]);
        } catch (Exception $e) {
            error_log("Booking logging error: " . $e->getMessage());
        }
    }
}


/**
 * Usage Examples
 */

// Example 1: Process search query
/*
$integration = new NLUIntegration($pdo);
$result = $integration->processSearchQuery('I need someone to fix my pipes');

if ($result['success']) {
    echo "Service: " . $result['detected_service'];
    echo "Providers: " . count($result['providers']);
}
*/

// Example 2: Classify booking request
/*
$integration = new NLUIntegration($pdo);
$booking = $integration->classifyBookingRequest(
    'My kitchen sink is leaking and needs repair',
    'Kigali'
);

if ($booking['auto_filled']) {
    // Pre-fill booking form
    $_SESSION['service_type'] = $booking['service'];
}
*/

// Example 3: Check service status
/*
$integration = new NLUIntegration($pdo);
if ($integration->isServiceAvailable()) {
    echo "NLU service is online";
} else {
    echo "Using fallback search";
}
*/

// Example 4: Get available categories
/*
$integration = new NLUIntegration($pdo);
$categories = $integration->getServiceCategories();
echo "Services: " . implode(', ', $categories);
*/

return $integration = new NLUIntegration($pdo ?? null);
?>
