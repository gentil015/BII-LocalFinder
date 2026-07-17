<?php
// includes/geolocation.php
// Geospatial utilities for provider sorting

class GeolocationHelper
{
    /**
     * Earth's radius in kilometers
     */
    private const EARTH_RADIUS_KM = 6371;
    
    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1 Client's latitude
     * @param float $lon1 Client's longitude
     * @param float $lat2 Provider's latitude
     * @param float $lon2 Provider's longitude
     * @return float Distance in kilometers
     */
    public static function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);
        
        // Haversine formula
        $dLat = $lat2Rad - $lat1Rad;
        $dLon = $lon2Rad - $lon1Rad;
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = self::EARTH_RADIUS_KM * $c;
        
        return round($distance, 2);
    }
    
    /**
     * Get coordinates for a location name
     * 
     * @param PDO $db Database connection
     * @param string $locationName Location name/district/sector
     * @return array|null Coordinates array ['latitude', 'longitude'] or null
     */
    public static function getLocationCoordinates($db, $locationName)
    {
        if (empty($locationName)) {
            return null;
        }
        
        try {
            // Try exact match first
            $stmt = $db->prepare("
                SELECT latitude, longitude 
                FROM location_coordinates 
                WHERE LOWER(location_name) = LOWER(?) 
                   OR LOWER(district) = LOWER(?) 
                   OR LOWER(sector) = LOWER(?)
                LIMIT 1
            ");
            $stmt->execute([$locationName, $locationName, $locationName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'latitude' => floatval($result['latitude']),
                    'longitude' => floatval($result['longitude'])
                ];
            }
            
            // Try partial match
            $like = '%' . strtolower($locationName) . '%';
            $stmt = $db->prepare("
                SELECT latitude, longitude 
                FROM location_coordinates 
                WHERE LOWER(location_name) LIKE ? 
                   OR LOWER(district) LIKE ? 
                   OR LOWER(sector) LIKE ?
                LIMIT 1
            ");
            $stmt->execute([$like, $like, $like]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'latitude' => floatval($result['latitude']),
                    'longitude' => floatval($result['longitude'])
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting location coordinates: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate distance scoring (0-10 points)
     * Closer providers get higher scores
     * 
     * @param float $distanceKm Distance in kilometers
     * @return float Score from 0 to 10
     */
    public static function calculateDistanceScore($distanceKm)
    {
        // Perfect score for very close
        if ($distanceKm <= 1) return 10.0;
        if ($distanceKm <= 3) return 9.0;
        if ($distanceKm <= 5) return 8.0;
        if ($distanceKm <= 10) return 7.0;
        if ($distanceKm <= 20) return 5.0;
        if ($distanceKm <= 50) return 3.0;
        
        // Minimum score for very far
        return 1.0;
    }
    
    /**
     * Convert rating (0-5 stars) to score (0-10)
     * 
     * @param float $rating Rating from 0 to 5
     * @return float Score from 0 to 10
     */
    public static function calculateRatingScore($rating)
    {
        $rating = floatval($rating);
        if ($rating < 0) $rating = 0;
        if ($rating > 5) $rating = 5;
        
        // Convert 0-5 scale to 0-10 scale
        return ($rating / 5) * 10;
    }
    
    /**
     * Calculate combined score for provider ranking
     * 
     * Uses weighted formula:
     * Score = (Distance × 0.40) + (Rating × 0.35) + (Reviews × 0.15) + (Availability × 0.10)
     * 
     * @param float $distanceScore Distance score (0-10)
     * @param float $ratingScore Rating score (0-10)
     * @param int $reviewCount Number of reviews
     * @param bool $isAvailable Whether provider is available
     * @return float Combined score (0-10)
     */
    public static function calculateCombinedScore($distanceScore, $ratingScore, $reviewCount = 0, $isAvailable = true)
    {
        // Review count score (max 10 at 50+ reviews)
        $reviewScore = min(($reviewCount / 50) * 10, 10);
        
        // Availability bonus
        $availabilityScore = $isAvailable ? 10 : 5;
        
        // Weighted combination
        $score = ($distanceScore * 0.40) +
                 ($ratingScore * 0.35) +
                 ($reviewScore * 0.15) +
                 ($availabilityScore * 0.10);
        
        return round($score, 2);
    }
}
?>