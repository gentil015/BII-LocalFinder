<?php
require_once 'config/database.php';

class ProviderPerformanceManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Calculate comprehensive performance metrics for a provider
     */
    public function calculateProviderPerformance($provider_id, $period_start = null, $period_end = null) {
        if (!$period_start) {
            $period_start = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$period_end) {
            $period_end = date('Y-m-d');
        }

        $metrics = [];

        // 1. Basic rating and review metrics
        $stmt = $this->db->prepare("
            SELECT
                sp.average_rating as avg_rating,
                sp.total_reviews,
                sp.total_jobs,
                sp.verification_level
            FROM service_providers sp
            WHERE sp.id = ?
        ");
        $stmt->execute([$provider_id]);
        $basic_info = $stmt->fetch();

        $metrics['avg_rating'] = $basic_info['avg_rating'] ?? 0;
        $metrics['total_reviews'] = $basic_info['total_reviews'] ?? 0;
        $metrics['total_jobs'] = $basic_info['total_jobs'] ?? 0;
        $metrics['verification_level'] = $basic_info['verification_level'] ?? 'none';

        // 2. Booking statistics for the period
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
            FROM bookings
            WHERE provider_id = ? AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$provider_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
        $booking_stats = $stmt->fetch();

        $metrics['total_bookings'] = $booking_stats['total_bookings'] ?? 0;
        $metrics['completed_bookings'] = $booking_stats['completed_bookings'] ?? 0;
        $metrics['cancelled_bookings'] = $booking_stats['cancelled_bookings'] ?? 0;
        $metrics['cancellation_rate'] = $metrics['total_bookings'] > 0 ?
            round(($metrics['cancelled_bookings'] / $metrics['total_bookings']) * 100, 2) : 0;

        // 3. Response time analysis
        $stmt = $this->db->prepare("
            SELECT
                AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)) as avg_response_hours,
                COUNT(*) as total_responses
            FROM bookings b
            WHERE b.provider_id = ?
                AND b.responded_at IS NOT NULL
                AND b.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$provider_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
        $response_stats = $stmt->fetch();

        $metrics['avg_response_time_hours'] = $response_stats['avg_response_hours'] ?
            round($response_stats['avg_response_hours'], 2) : null;
        $metrics['total_responses'] = $response_stats['total_responses'] ?? 0;

        // 4. On-time completion rate (bookings completed before preferred date/time)
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_completed,
                SUM(CASE WHEN b.updated_at <= CONCAT(b.preferred_date, ' ', b.preferred_time) THEN 1 ELSE 0 END) as on_time_completions
            FROM bookings b
            WHERE b.provider_id = ?
                AND b.status = 'completed'
                AND b.preferred_date IS NOT NULL
                AND b.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$provider_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
        $completion_stats = $stmt->fetch();

        $metrics['on_time_completion_rate'] = $completion_stats['total_completed'] > 0 ?
            round(($completion_stats['on_time_completions'] / $completion_stats['total_completed']) * 100, 2) : 0;

        // 5. Client satisfaction score (based on reviews in period)
        $stmt = $this->db->prepare("
            SELECT AVG(r.rating) as avg_review_rating, COUNT(*) as review_count
            FROM reviews r
            JOIN bookings b ON r.booking_id = b.id
            WHERE b.provider_id = ? AND r.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$provider_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
        $review_stats = $stmt->fetch();

        $metrics['client_satisfaction_score'] = $review_stats['review_count'] > 0 ?
            round($review_stats['avg_review_rating'], 2) : $metrics['avg_rating'];

        // 6. Availability score (based on working hours and booking patterns)
        $availability_score = $this->calculateAvailabilityScore($provider_id);
        $metrics['availability_score'] = $availability_score;

        // 7. Overall performance score (weighted calculation)
        $metrics['overall_performance_score'] = $this->calculateOverallScore($metrics);

        // 8. Performance grade
        $metrics['performance_grade'] = $this->calculatePerformanceGrade($metrics);

        return $metrics;
    }

    /**
     * Calculate availability score based on working hours and patterns
     */
    private function calculateAvailabilityScore($provider_id) {
        $stmt = $this->db->prepare("
            SELECT
                working_days,
                working_hours_start,
                working_hours_end,
                slot_duration,
                max_daily_bookings
            FROM service_providers
            WHERE id = ?
        ");
        $stmt->execute([$provider_id]);
        $schedule = $stmt->fetch();

        if (!$schedule) return 0;

        // Calculate available hours per week
        $working_days = $schedule['working_days'] ? explode(',', $schedule['working_days']) : [];
        $days_count = count($working_days);

        if ($days_count === 0) return 0;

        $start_time = strtotime($schedule['working_hours_start']);
        $end_time = strtotime($schedule['working_hours_end']);
        $hours_per_day = ($end_time - $start_time) / 3600;

        $weekly_hours = $days_count * $hours_per_day;

        // Score based on availability (more hours = higher score, max 100)
        $availability_score = min(100, ($weekly_hours / 40) * 100); // 40 hours/week is perfect score

        // Factor in slot duration (shorter slots = more responsive)
        $slot_duration = $schedule['slot_duration'] ?? 30;
        if ($slot_duration <= 30) {
            $availability_score *= 1.1; // 10% bonus for shorter slots
        } elseif ($slot_duration >= 120) {
            $availability_score *= 0.9; // 10% penalty for long slots
        }

        return round(min(100, $availability_score), 2);
    }

    /**
     * Calculate overall performance score
     */
    private function calculateOverallScore($metrics) {
        $weights = [
            'avg_rating' => 0.25,
            'cancellation_rate' => 0.20,
            'avg_response_time_hours' => 0.15,
            'on_time_completion_rate' => 0.15,
            'client_satisfaction_score' => 0.15,
            'availability_score' => 0.10
        ];

        $score = 0;

        // Rating score (0-100)
        $score += ($metrics['avg_rating'] / 5) * 100 * $weights['avg_rating'];

        // Cancellation rate score (lower is better, 0-100)
        $cancellation_score = max(0, 100 - ($metrics['cancellation_rate'] * 2)); // 50% cancellation = 0 score
        $score += $cancellation_score * $weights['cancellation_rate'];

        // Response time score (lower is better, 0-100)
        if ($metrics['avg_response_time_hours'] !== null) {
            $response_score = max(0, 100 - ($metrics['avg_response_time_hours'] * 10)); // 10 hours = 0 score
            $score += $response_score * $weights['avg_response_time_hours'];
        }

        // On-time completion score
        $score += $metrics['on_time_completion_rate'] * $weights['on_time_completion_rate'];

        // Client satisfaction score
        $score += ($metrics['client_satisfaction_score'] / 5) * 100 * $weights['client_satisfaction_score'];

        // Availability score
        $score += $metrics['availability_score'] * $weights['availability_score'];

        return round($score, 2);
    }

    /**
     * Calculate performance grade based on overall score
     */
    private function calculatePerformanceGrade($metrics) {
        $score = $metrics['overall_performance_score'];

        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'average';
        return 'needs_improvement';
    }

    /**
     * Update or insert performance metrics for a provider
     */
    public function updateProviderPerformance($provider_id, $period_start = null, $period_end = null) {
        $metrics = $this->calculateProviderPerformance($provider_id, $period_start, $period_end);

        if (!$period_start) {
            $period_start = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$period_end) {
            $period_end = date('Y-m-d');
        }

        $stmt = $this->db->prepare("
            INSERT INTO provider_performance (
                provider_id, period_start, period_end,
                avg_rating, total_reviews, total_bookings, completed_bookings, cancelled_bookings,
                avg_response_time_hours, cancellation_rate, on_time_completion_rate,
                client_satisfaction_score, availability_score, overall_performance_score, performance_grade
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                avg_rating = VALUES(avg_rating),
                total_reviews = VALUES(total_reviews),
                total_bookings = VALUES(total_bookings),
                completed_bookings = VALUES(completed_bookings),
                cancelled_bookings = VALUES(cancelled_bookings),
                avg_response_time_hours = VALUES(avg_response_time_hours),
                cancellation_rate = VALUES(cancellation_rate),
                on_time_completion_rate = VALUES(on_time_completion_rate),
                client_satisfaction_score = VALUES(client_satisfaction_score),
                availability_score = VALUES(availability_score),
                overall_performance_score = VALUES(overall_performance_score),
                performance_grade = VALUES(performance_grade),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            $provider_id, $period_start, $period_end,
            $metrics['avg_rating'], $metrics['total_reviews'], $metrics['total_bookings'],
            $metrics['completed_bookings'], $metrics['cancelled_bookings'],
            $metrics['avg_response_time_hours'], $metrics['cancellation_rate'], $metrics['on_time_completion_rate'],
            $metrics['client_satisfaction_score'], $metrics['availability_score'],
            $metrics['overall_performance_score'], $metrics['performance_grade']
        ]);

        return $metrics;
    }

    /**
     * Analyze availability patterns for a provider
     */
    public function analyzeAvailabilityPatterns($provider_id, $days_back = 30) {
        $start_date = date('Y-m-d', strtotime("-{$days_back} days"));

        // Get booking patterns by day and hour
        $stmt = $this->db->prepare("
            SELECT
                DAYOFWEEK(b.created_at) as day_of_week,
                HOUR(b.created_at) as hour_of_day,
                COUNT(*) as booking_count
            FROM bookings b
            WHERE b.provider_id = ? AND b.created_at >= ?
            GROUP BY DAYOFWEEK(b.created_at), HOUR(b.created_at)
            ORDER BY day_of_week, hour_of_day
        ");
        $stmt->execute([$provider_id, $start_date]);
        $booking_patterns = $stmt->fetchAll();

        // Get response patterns
        $stmt = $this->db->prepare("
            SELECT
                DAYOFWEEK(b.created_at) as day_of_week,
                HOUR(b.created_at) as hour_of_day,
                AVG(TIMESTAMPDIFF(MINUTE, b.created_at, b.responded_at)) as avg_response_minutes,
                COUNT(*) as response_count
            FROM bookings b
            WHERE b.provider_id = ?
                AND b.responded_at IS NOT NULL
                AND b.created_at >= ?
            GROUP BY DAYOFWEEK(b.created_at), HOUR(b.created_at)
        ");
        $stmt->execute([$provider_id, $start_date]);
        $response_patterns = $stmt->fetchAll();

        // Update availability patterns table
        foreach ($booking_patterns as $pattern) {
            $response_data = array_filter($response_patterns, function($r) use ($pattern) {
                return $r['day_of_week'] == $pattern['day_of_week'] && $r['hour_of_day'] == $pattern['hour_of_day'];
            });

            $response_info = $response_data ? reset($response_data) : null;

            $stmt = $this->db->prepare("
                INSERT INTO provider_availability_patterns (
                    provider_id, day_of_week, hour_of_day, booking_count, response_count, avg_response_time_minutes
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    booking_count = VALUES(booking_count),
                    response_count = VALUES(response_count),
                    avg_response_time_minutes = VALUES(avg_response_time_minutes),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $provider_id,
                $pattern['day_of_week'],
                $pattern['hour_of_day'],
                $pattern['booking_count'],
                $response_info['response_count'] ?? 0,
                $response_info['avg_response_minutes'] ? round($response_info['avg_response_minutes']) : null
            ]);
        }

        return ['booking_patterns' => $booking_patterns, 'response_patterns' => $response_patterns];
    }

    /**
     * Get performance insights and recommendations
     */
    public function getPerformanceInsights($provider_id) {
        $metrics = $this->calculateProviderPerformance($provider_id);

        $insights = [
            'strengths' => [],
            'weaknesses' => [],
            'recommendations' => []
        ];

        // Analyze strengths
        if ($metrics['avg_rating'] >= 4.5) {
            $insights['strengths'][] = "Excellent client ratings ({$metrics['avg_rating']} stars)";
        }
        if ($metrics['cancellation_rate'] <= 5) {
            $insights['strengths'][] = "Low cancellation rate ({$metrics['cancellation_rate']}%)";
        }
        if ($metrics['avg_response_time_hours'] !== null && $metrics['avg_response_time_hours'] <= 2) {
            $insights['strengths'][] = "Fast response time (" . round($metrics['avg_response_time_hours'], 1) . " hours average)";
        }
        if ($metrics['on_time_completion_rate'] >= 90) {
            $insights['strengths'][] = "High on-time completion rate ({$metrics['on_time_completion_rate']}%)";
        }

        // Analyze weaknesses
        if ($metrics['avg_rating'] < 3.5) {
            $insights['weaknesses'][] = "Low client ratings ({$metrics['avg_rating']} stars)";
        }
        if ($metrics['cancellation_rate'] > 15) {
            $insights['weaknesses'][] = "High cancellation rate ({$metrics['cancellation_rate']}%)";
        }
        if ($metrics['avg_response_time_hours'] !== null && $metrics['avg_response_time_hours'] > 24) {
            $insights['weaknesses'][] = "Slow response time (" . round($metrics['avg_response_time_hours'], 1) . " hours average)";
        }
        if ($metrics['on_time_completion_rate'] < 70) {
            $insights['weaknesses'][] = "Low on-time completion rate ({$metrics['on_time_completion_rate']}%)";
        }

        // Generate recommendations
        if ($metrics['cancellation_rate'] > 10) {
            $insights['recommendations'][] = "Review booking acceptance criteria to reduce cancellations";
        }
        if ($metrics['avg_response_time_hours'] !== null && $metrics['avg_response_time_hours'] > 12) {
            $insights['recommendations'][] = "Respond to booking requests more quickly to improve client satisfaction";
        }
        if ($metrics['on_time_completion_rate'] < 80) {
            $insights['recommendations'][] = "Focus on completing jobs by the agreed schedule";
        }
        if ($metrics['total_reviews'] < 5) {
            $insights['recommendations'][] = "Complete more jobs to build a review history";
        }

        return $insights;
    }
}
?>