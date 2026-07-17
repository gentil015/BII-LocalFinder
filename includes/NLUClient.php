<?php
/**
 * Multilingual NLU Service Client
 * PHP wrapper for the FastAPI NLU service
 * 
 * Usage:
 *   $nlu = new NLUClient('http://localhost:8001');
 *   $result = $nlu->classify('I need a plumber');
 */

class NLUClient
{
    private $base_url;
    private $timeout = 30;
    
    /**
     * Constructor
     * 
     * @param string $base_url Base URL of the NLU API (default: http://localhost:8001)
     */
    public function __construct($base_url = 'http://localhost:8001')
    {
        $this->base_url = rtrim($base_url, '/');
    }
    
    /**
     * Classify a single text
     * 
     * @param string $text Input text to classify
     * @param string $language Optional language code ('en', 'rw')
     * @return array|null Classification result with label and score
     */
    public function classify($text, $language = null)
    {
        if (empty($text)) {
            return null;
        }
        
        try {
            $payload = [
                'text' => $text
            ];
            
            if (!empty($language)) {
                $payload['language'] = $language;
            }
            
            $response = $this->makeRequest('POST', '/nlu', $payload);
            
            return $response;
        } catch (Exception $e) {
            error_log('NLU Classification Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Classify multiple texts in batch
     * 
     * @param array $texts List of texts to classify
     * @param string $language Optional language code
     * @return array|null Batch classification results
     */
    public function classifyBatch($texts, $language = null)
    {
        if (empty($texts) || !is_array($texts)) {
            return null;
        }
        
        try {
            $payload = [
                'texts' => $texts
            ];
            
            if (!empty($language)) {
                $payload['language'] = $language;
            }
            
            $response = $this->makeRequest('POST', '/nlu/batch', $payload);
            
            return $response;
        } catch (Exception $e) {
            error_log('NLU Batch Classification Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available service categories
     * 
     * @return array|null List of available categories
     */
    public function getCategories()
    {
        try {
            $response = $this->makeRequest('GET', '/categories');
            return $response;
        } catch (Exception $e) {
            error_log('NLU Get Categories Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get model information
     * 
     * @return array|null Model information
     */
    public function getModelInfo()
    {
        try {
            $response = $this->makeRequest('GET', '/model/info');
            return $response;
        } catch (Exception $e) {
            error_log('NLU Get Model Info Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Health check
     * 
     * @return bool True if service is healthy
     */
    public function healthCheck()
    {
        try {
            $response = $this->makeRequest('GET', '/health');
            return isset($response['status']) && $response['status'] === 'healthy';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Test predictions with sample data
     * 
     * @return array|null Test results
     */
    public function testPredictions()
    {
        try {
            $response = $this->makeRequest('POST', '/nlu/test');
            return $response;
        } catch (Exception $e) {
            error_log('NLU Test Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Make HTTP request to NLU API
     * 
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint API endpoint
     * @param array $data Optional request data
     * @return array Response data
     * @throws Exception
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->base_url . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'user_agent' => 'PHP-NLUClient/1.0',
                'timeout' => $this->timeout
            ]
        ];
        
        if ($data !== null) {
            $json_data = json_encode($data);
            $options['http']['header'] = "Content-Type: application/json\r\n" .
                                        "Content-Length: " . strlen($json_data) . "\r\n";
            $options['http']['content'] = $json_data;
        }
        
        $context = stream_context_create($options);
        
        try {
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception("Failed to connect to NLU service at {$url}");
            }
            
            $decoded = json_decode($response, true);
            
            if ($decoded === null) {
                throw new Exception("Invalid JSON response from NLU service");
            }
            
            return $decoded;
        } catch (Exception $e) {
            throw $e;
        }
    }
}


/**
 * Shorthand function for quick classification
 * 
 * @param string $text Text to classify
 * @param string $nlu_url NLU service URL
 * @return array|null Classification result
 */
function classify_service($text, $nlu_url = 'http://localhost:8001')
{
    $nlu = new NLUClient($nlu_url);
    return $nlu->classify($text);
}


/**
 * Shorthand function for batch classification
 * 
 * @param array $texts Texts to classify
 * @param string $nlu_url NLU service URL
 * @return array|null Batch classification results
 */
function classify_services_batch($texts, $nlu_url = 'http://localhost:8001')
{
    $nlu = new NLUClient($nlu_url);
    return $nlu->classifyBatch($texts);
}
