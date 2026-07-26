<?php
/**
 * AI Service Insights Class
 * Provides service insights and recommendations for the MVP
 * Uses rule-based fallbacks without the removed ML service
 */

class AIServiceInsights {
    private $db;
    private $provider_id;

    public function __construct($db, $provider_id) {
        $this->db = $db;
        $this->provider_id = $provider_id;
    }

    /**
     * Calculate comprehensive service performance score (0-100)
     * Uses ML predictions when available, falls back to rule-based calculation
     */
    public function calculatePerformanceScore($service_id) {
        try {
            // Use rule-based calculation for the MVP
            // Get all relevant metrics for the service
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(DISTINCT pv.id) as view_count,
                    COUNT(DISTINCT b.id) as booking_count,
                    COUNT(DISTINCT CASE WHEN b.status = 'completed' THEN b.id END) as completed_count,
                    AVG(r.rating) as avg_rating,
                    COUNT(r.id) as review_count
                FROM provider_services ps
                LEFT JOIN provider_views pv ON ps.id = pv.service_id
                LEFT JOIN bookings b ON ps.id = b.service_id AND b.provider_id = ?
                LEFT JOIN reviews r ON b.id = r.booking_id
                WHERE ps.id = ? AND ps.provider_id = ?
            ");
            $stmt->execute([$this->provider_id, $service_id, $this->provider_id]);
            $metrics = $stmt->fetch();

            $view_count = (int)$metrics['view_count'] ?? 0;
            $booking_count = (int)$metrics['booking_count'] ?? 0;
            $completed_count = (int)$metrics['completed_count'] ?? 0;
            $avg_rating = (float)$metrics['avg_rating'] ?? 0;
            $review_count = (int)$metrics['review_count'] ?? 0;

            // Calculate component scores
            $view_score = min(100, $view_count * 2); // Scale: 50 views = 100 points
            $booking_score = min(100, $booking_count * 5); // Scale: 20 bookings = 100 points
            $completion_score = $booking_count > 0 ? ($completed_count / $booking_count) * 100 : 50; // Default 50 if no bookings
            $rating_score = $avg_rating * 20; // Scale: 5-star = 100 points
            $review_score = min(100, $review_count * 10); // Scale: 10 reviews = 100 points

            // Weighted average (normalized)
            $performance_score = (
                ($view_score * 0.15) +
                ($booking_score * 0.25) +
                ($completion_score * 0.20) +
                ($rating_score * 0.25) +
                ($review_score * 0.15)
            );

            return min(100, max(0, round($performance_score, 1)));
        } catch (Exception $e) {
            error_log("Error calculating performance score: " . $e->getMessage());
            return 0;
        }
    }

    public function getDemandIndicator($service_id) {
        try {
            // Use rule-based calculation for the MVP
            // Get views and bookings from last 30 days
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(DISTINCT CASE WHEN pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN pv.id END) as views_30d,
                    COUNT(DISTINCT CASE WHEN b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN b.id END) as bookings_30d,
                    COUNT(DISTINCT pv.id) as total_views,
                    COUNT(DISTINCT b.id) as total_bookings
                FROM provider_services ps
                LEFT JOIN provider_views pv ON ps.id = pv.service_id
                LEFT JOIN bookings b ON ps.id = b.service_id AND b.provider_id = ?
                WHERE ps.id = ? AND ps.provider_id = ?
            ");
            $stmt->execute([$this->provider_id, $service_id, $this->provider_id]);
            $data = $stmt->fetch();

            $views_30d = (int)$data['views_30d'] ?? 0;
            $bookings_30d = (int)$data['bookings_30d'] ?? 0;
            $conversion_rate = $views_30d > 0 ? ($bookings_30d / $views_30d) * 100 : 0;

            // Determine demand level
            if ($views_30d >= 50 && $conversion_rate >= 10) {
                return ['level' => 'high', 'label' => 'High Demand', 'color' => '#10b981', 'icon' => 'fas fa-arrow-up'];
            } elseif ($views_30d >= 20 || $conversion_rate >= 5) {
                return ['level' => 'medium', 'label' => 'Medium Demand', 'color' => '#f59e0b', 'icon' => 'fas fa-minus'];
            } else {
                return ['level' => 'low', 'label' => 'Low Demand', 'color' => '#ef4444', 'icon' => 'fas fa-arrow-down'];
            }
        } catch (Exception $e) {
            error_log("Error getting demand indicator: " . $e->getMessage());
            return ['level' => 'unknown', 'label' => 'No Data', 'color' => '#9ca3af', 'icon' => 'fas fa-question'];
        }
    }

    /**
     * Generate conversion rate from service metrics
     */
    public function getConversionRate($service_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT pv.id) as view_count,
                    COUNT(DISTINCT b.id) as booking_count
                FROM provider_services ps
                LEFT JOIN provider_views pv ON ps.id = pv.service_id
                LEFT JOIN bookings b ON ps.id = b.service_id AND b.provider_id = ?
                WHERE ps.id = ? AND ps.provider_id = ?
            ");
            $stmt->execute([$this->provider_id, $service_id, $this->provider_id]);
            $metrics = $stmt->fetch();

            $view_count = (int)$metrics['view_count'] ?? 0;
            $booking_count = (int)$metrics['booking_count'] ?? 0;

            if ($view_count === 0) {
                return 0;
            }

            return round(($booking_count / $view_count) * 100, 1);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Suggest optimal price based on similar services in same category
     */
    public function suggestOptimalPrice($category_id, $current_price) {
        try {
            // Try ML-based price prediction first
            if ($this->ml_recommender) {
                // Get service data for the current service (we need service_id, but it's not passed here)
                // For now, we'll use category-level ML insights when available
                // TODO: Pass service_id to this method for more accurate ML predictions
            }

            // Fallback to market analysis
            // Get average price of similar services in same category
            $stmt = $this->db->prepare("
                SELECT
                    AVG(ps.price) as avg_price,
                    COUNT(*) as service_count,
                    MIN(ps.price) as min_price,
                    MAX(ps.price) as max_price,
                    COUNT(DISTINCT b.id) as total_bookings,
                    AVG(CASE WHEN b.status = 'completed' THEN ps.price END) as avg_completed_price
                FROM provider_services ps
                LEFT JOIN bookings b ON ps.id = b.service_id AND b.status = 'completed'
                WHERE ps.category_id = ? AND ps.is_available = 1
                GROUP BY ps.category_id
            ");
            $stmt->execute([$category_id]);
            $category_data = $stmt->fetch();

            if (!$category_data || $category_data['service_count'] < 3) {
                return null; // Not enough data to suggest
            }

            $avg_price = (float)$category_data['avg_price'];
            $min_price = (float)$category_data['min_price'];
            $max_price = (float)$category_data['max_price'];
            $avg_completed_price = (float)($category_data['avg_completed_price'] ?? $avg_price);
            $market_position = (($current_price - $min_price) / ($max_price - $min_price)) * 100;

            // ML-enhanced price suggestion logic
            $suggested_price = $avg_price;

            // Use completed booking prices as a better indicator of market value
            if ($avg_completed_price > 0 && abs($avg_completed_price - $avg_price) < $avg_price * 0.3) {
                $suggested_price = $avg_completed_price;
            }

            // Adjust based on market position and booking success
            if ($current_price > $suggested_price * 1.2) {
                // Currently overpriced - suggest market alignment
                $suggested_price = $suggested_price * 1.05;
            } elseif ($current_price < $suggested_price * 0.8) {
                // Currently underpriced - suggest market alignment
                $suggested_price = $suggested_price * 0.95;
            }

            return [
                'suggested_price' => round($suggested_price),
                'market_avg' => round($avg_price),
                'market_min' => round($min_price),
                'market_max' => round($max_price),
                'position' => round($market_position),
                'recommendation' => $this->getPriceRecommendation($current_price, $suggested_price)
            ];
        } catch (Exception $e) {
            error_log("Error suggesting price: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get price recommendation message
     */
    private function getPriceRecommendation($current, $suggested) {
        if (abs($current - $suggested) < 100) {
            return 'Your price is competitive';
        } elseif ($current > $suggested) {
            $diff = (($current - $suggested) / $current) * 100;
            return "Consider reducing by ~" . round($diff) . "% to match market";
        } else {
            $diff = (($suggested - $current) / $current) * 100;
            return "You could increase by ~" . round($diff) . "% for market alignment";
        }
    }

    /**
     * Generate AI optimization tips based on service metrics
     */
    public function generateOptimizationTips($service, $performance_score, $conversion_rate, $demand) {
        $tips = [];

        // Try ML-based recommendations first
        if ($this->ml_recommender) {
            $service_data = $this->getServiceDataForML($service['id']);
            if ($service_data) {
                // Get ML-based performance predictions
                $ml_performance = $this->ml_recommender->predictProviderPerformance($service_data);
                if ($ml_performance) {
                    // ML-driven tips based on predicted performance factors
                    if (isset($ml_performance['predicted_completion_rate']) && $ml_performance['predicted_completion_rate'] < 0.8) {
                        $tips[] = [
                            'title' => 'Improve Completion Rate',
                            'description' => 'ML analysis suggests your completion rate could be higher. Focus on timely delivery and client communication.',
                            'priority' => 'high',
                            'icon' => 'fas fa-check-circle'
                        ];
                    }

                    if (isset($ml_performance['predicted_response_time']) && $ml_performance['predicted_response_time'] > 24) {
                        $tips[] = [
                            'title' => 'Reduce Response Time',
                            'description' => 'ML predicts faster response times would improve your booking rate. Aim to respond within 24 hours.',
                            'priority' => 'medium',
                            'icon' => 'fas fa-clock'
                        ];
                    }
                }

                // ML-based demand analysis
                $ml_engagement = $this->ml_recommender->predictUserEngagement(
                    $this->provider_id,
                    $service_data,
                    ['context' => 'optimization']
                );

                if ($ml_engagement !== null && $ml_engagement < 0.3) {
                    $tips[] = [
                        'title' => 'Increase Service Appeal',
                        'description' => 'ML analysis indicates low user engagement. Consider improving your service description and adding more details.',
                        'priority' => 'high',
                        'icon' => 'fas fa-magic'
                    ];
                }
            }
        }

        // Rule-based tips (fallback or additional)
        // Tip 1: Low conversion rate
        if ($conversion_rate < 5 && $conversion_rate > 0) {
            $tips[] = [
                'title' => 'Improve Conversion Rate',
                'description' => 'Your conversion rate is below market average. Consider adding more details or images to your service listing.',
                'priority' => 'high',
                'icon' => 'fas fa-chart-line'
            ];
        }

        // Tip 2: Low demand
        if ($demand['level'] === 'low') {
            $tips[] = [
                'title' => 'Boost Visibility',
                'description' => 'Add more images, tags, and detailed description to improve searchability and attract more clients.',
                'priority' => 'high',
                'icon' => 'fas fa-eye'
            ];
        }

        // Tip 3: No reviews
        if (empty($service['total_reviews']) || $service['total_reviews'] == 0) {
            $tips[] = [
                'title' => 'Build Credibility',
                'description' => 'Ask completed clients to leave reviews. Services with reviews get 3x more bookings.',
                'priority' => 'medium',
                'icon' => 'fas fa-star'
            ];
        }

        // Tip 4: High demand - leverage
        if ($demand['level'] === 'high' && $service['negotiable']) {
            $tips[] = [
                'title' => 'Adjust Pricing',
                'description' => 'High demand for your service! Consider adjusting your price or reducing negotiation range.',
                'priority' => 'medium',
                'icon' => 'fas fa-tag'
            ];
        }

        // Tip 5: No portfolio images
        if (empty($service['service_image'])) {
            $tips[] = [
                'title' => 'Add Service Images',
                'description' => 'Services with images receive 3x more engagement. Upload quality photos of your work.',
                'priority' => 'high',
                'icon' => 'fas fa-camera'
            ];
        }

        // Tip 6: Instant booking opportunity
        if (!$service['booking_mode'] == 'instant' && $performance_score > 70) {
            $tips[] = [
                'title' => 'Enable Instant Booking',
                'description' => 'Your strong performance suggests clients want quick bookings. Enable instant booking to increase conversion.',
                'priority' => 'medium',
                'icon' => 'fas fa-bolt'
            ];
        }

        // Tip 7: Paused service
        if ($service['service_status'] === 'paused') {
            $tips[] = [
                'title' => 'Publish Your Service',
                'description' => 'This service is currently paused. Publish it to make it visible to clients.',
                'priority' => 'high',
                'icon' => 'fas fa-play'
            ];
        }

        // Default tip if no other issues
        if (count($tips) === 0) {
            $tips[] = [
                'title' => 'Maintain Quality',
                'description' => 'Your service is performing well! Continue maintaining quality and customer satisfaction.',
                'priority' => 'low',
                'icon' => 'fas fa-thumbs-up'
            ];
        }

        return array_slice($tips, 0, 3); // Return top 3 tips
    }

    /**
     * Generate AI-powered service title suggestions
     */
    public function generateTitleSuggestions($service) {
        $suggestions = [];
        $base_name = $service['name'];
        $category = $service['category_name'] ?? 'Service';

        // Template-based suggestions
        $templates = [
            "Professional {category} - {duration}min - Quality Guaranteed",
            "Expert {category} Services - Fast & Reliable",
            "Premium {category} by Certified Professional",
            "Affordable {category} - {duration}min Sessions",
            "Trusted {category} Services - {rating}⭐ Rated"
        ];

        foreach ($templates as $template) {
            $suggestion = str_replace(
                ['{category}', '{duration}', '{rating}'],
                [
                    $category,
                    $service['duration'],
                    $service['avg_rating'] ? round($service['avg_rating'], 1) : '4.8'
                ],
                $template
            );
            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Generate AI-powered description suggestions
     */
    public function generateDescriptionSuggestions($service) {
        $suggestions = [];
        $category = $service['category_name'] ?? 'service';

        $templates = [
            "Professional {category} service with {duration}+ minutes of dedicated attention. Quality assured, fast turnaround, and excellent customer satisfaction. Perfect for clients looking for reliable, high-quality {category}.",

            "Looking for expert {category}? Offering {duration} minutes of professional service with years of experience. Guaranteed satisfaction with attention to detail and commitment to your specific needs.",

            "Premium {category} by a dedicated professional. {duration}-minute sessions delivering outstanding results. Completed {bookings}+ successful bookings with an average rating of {rating}⭐.",

            "Affordable yet professional {category} services. Whether you're a first-time client or returning customer, you'll receive the same high-quality service and attention. Flexible scheduling available.",

            "Specialized in {category} with a focus on customer satisfaction. {duration}-minute sessions tailored to your specific requirements. Quick response time and professional approach guaranteed."
        ];

        foreach ($templates as $template) {
            $suggestion = str_replace(
                ['{category}', '{duration}', '{bookings}', '{rating}'],
                [
                    $category,
                    $service['duration'],
                    $service['completed_bookings'] ?? '100',
                    $service['avg_rating'] ? round($service['avg_rating'], 1) : '4.8'
                ],
                $template
            );
            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }
}

?>
