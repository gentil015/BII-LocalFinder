<?php
/**
 * AI Booking Functions for BII Brain
 */

class AIBookingFunctions {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get AI booking statistics
     */
    public function getAIBookingStats($user_id = null) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_bookings,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_processing_time,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                    COUNT(CASE WHEN ai_generated = 1 THEN 1 END) as ai_generated_bookings,
                    DATE(created_at) as date
                FROM ai_bookings_log
            ";
            
            $params = [];
            
            if ($user_id) {
                $query .= " WHERE user_id = ?";
                $params[] = $user_id;
            }
            
            $query .= " GROUP BY DATE(created_at) ORDER BY date DESC LIMIT 30";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("AI stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get popular AI search terms
     */
    public function getPopularSearchTerms($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    prompt,
                    COUNT(*) as search_count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM ai_processing_log
                WHERE prompt IS NOT NULL
                GROUP BY LOWER(TRIM(prompt))
                ORDER BY search_count DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Popular terms error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get AI booking success rate
     */
    public function getAISuccessRate() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_ai_bookings,
                    COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled,
                    AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.updated_at)) as avg_response_time
                FROM ai_bookings_log abl
                JOIN bookings b ON abl.booking_id = b.id
                WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Success rate error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Suggest improvements for AI prompts
     */
    public function suggestPromptImprovements($prompt) {
        $improvements = [];
        
        // Check prompt length
        if (strlen($prompt) < 10) {
            $improvements[] = "Please provide more details about what you need.";
        }
        
        // Check for location
        if (!preg_match('/(in|at|near|around)\s+([A-Za-z\s]+)/i', $prompt)) {
            $improvements[] = "Include your location (e.g., 'in Kigali', 'at Remera') for better matches.";
        }
        
        // Check for service type
        if (!preg_match('/(electrician|plumber|cleaner|mechanic|carpenter|painter)/i', $prompt)) {
            $improvements[] = "Specify the service type (e.g., electrician, plumber, cleaner) for faster results.";
        }
        
        // Check for time reference
        if (!preg_match('/(today|tomorrow|week|date)/i', $prompt)) {
            $improvements[] = "Mention when you need the service (e.g., today, tomorrow, next week).";
        }
        
        return $improvements;
    }
    
    /**
     * Train AI with user feedback
     */
    public function trainAIWithFeedback($prompt, $correct_category, $user_rating) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_training_data (
                    original_prompt,
                    correct_category,
                    user_rating,
                    processed_at
                ) VALUES (?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $prompt,
                $correct_category,
                $user_rating
            ]);
            
        } catch (Exception $e) {
            error_log("AI training error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get AI recommendations for providers
     */
    public function getProviderAIRecommendations($provider_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    abl.service_type,
                    COUNT(*) as request_count,
                    AVG(abl.urgency = 'high') as high_urgency_percentage,
                    GROUP_CONCAT(DISTINCT abl.location) as locations
                FROM ai_bookings_log abl
                JOIN bookings b ON abl.booking_id = b.id
                WHERE b.provider_id = ?
                AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY abl.service_type
                ORDER BY request_count DESC
                LIMIT 5
            ");
            $stmt->execute([$provider_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Provider AI recs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate AI summary for dashboard
     */
    public function generateAISummary($user_id) {
        $summary = [
            'ai_bookings' => 0,
            'avg_response_time' => 0,
            'popular_services' => [],
            'suggestions' => []
        ];
        
        try {
            // Get AI booking count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM ai_bookings_log
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $summary['ai_bookings'] = $stmt->fetchColumn();
            
            // Get average response time
            $stmt = $this->db->prepare("
                SELECT AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)) as avg_time
                FROM ai_bookings_log abl
                JOIN bookings b ON abl.booking_id = b.id
                WHERE abl.user_id = ?
                AND b.responded_at IS NOT NULL
            ");
            $stmt->execute([$user_id]);
            $summary['avg_response_time'] = round($stmt->fetchColumn() ?? 0, 1);
            
            // Get popular services
            $stmt = $this->db->prepare("
                SELECT service_type, COUNT(*) as count
                FROM ai_bookings_log
                WHERE user_id = ?
                GROUP BY service_type
                ORDER BY count DESC
                LIMIT 3
            ");
            $stmt->execute([$user_id]);
            $summary['popular_services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate suggestions
            if ($summary['ai_bookings'] > 0) {
                if ($summary['avg_response_time'] > 24) {
                    $summary['suggestions'][] = [
                        'type' => 'warning',
                        'message' => 'Providers are taking over 24 hours to respond. Try booking during business hours.'
                    ];
                }
                
                if (count($summary['popular_services']) > 0) {
                    $top_service = $summary['popular_services'][0];
                    $summary['suggestions'][] = [
                        'type' => 'info',
                        'message' => "You frequently book {$top_service['service_type']}s. Save time by using 'Book again' feature."
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("AI summary error: " . $e->getMessage());
        }
        
        return $summary;
    }
}
?>