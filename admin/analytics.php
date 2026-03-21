<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$db = Database::getInstance()->getConnection();

// Date range parameters
$date_range = $_GET['date_range'] ?? '30days';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$category_filter = $_GET['category'] ?? '';
$district_filter = $_GET['district'] ?? '';

// Set date range based on selection
switch ($date_range) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'custom':
        // Use custom dates from input
        break;
}

// 🔵 1. User Analytics
function getUserAnalytics($db, $start_date, $end_date) {
    $analytics = [];
    
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $analytics['total_users'] = $stmt->fetchColumn();
    
    // Total clients
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'client'");
    $stmt->execute();
    $analytics['total_clients'] = $stmt->fetchColumn();
    
    // Total providers
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'provider'");
    $stmt->execute();
    $analytics['total_providers'] = $stmt->fetchColumn();
    
    // New users growth
    $stmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at BETWEEN ? AND ? 
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['user_growth'] = $stmt->fetchAll();
    
    // Active users (last 30 days)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as active_users 
        FROM (
            SELECT client_id as user_id FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION 
            SELECT sp.user_id FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ) as active
    ");
    $stmt->execute();
    $analytics['active_users'] = $stmt->fetchColumn();
    
    // Users by district
    $stmt = $db->prepare("
        SELECT location, COUNT(*) as count 
        FROM service_providers 
        GROUP BY location 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $analytics['users_by_district'] = $stmt->fetchAll();
    
    // Top providers by performance
    $stmt = $db->prepare("
        SELECT u.full_name, sp.profession, sp.average_rating, sp.total_reviews, sp.total_jobs,
               (SELECT COUNT(*) FROM bookings WHERE provider_id = sp.id AND status = 'completed') as completed_jobs
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.average_rating >= 4.0
        ORDER BY sp.average_rating DESC, completed_jobs DESC
        LIMIT 10
    ");
    $stmt->execute();
    $analytics['top_providers'] = $stmt->fetchAll();
    
    // Most active clients
    $stmt = $db->prepare("
        SELECT u.full_name, u.email, COUNT(b.id) as booking_count,
               (SELECT COUNT(*) FROM reviews WHERE client_id = u.id) as review_count
        FROM users u
        JOIN bookings b ON u.id = b.client_id
        WHERE u.user_type = 'client'
        GROUP BY u.id
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $analytics['active_clients'] = $stmt->fetchAll();
    
    return $analytics;
}

// 🔴 2. Booking Analytics
function getBookingAnalytics($db, $start_date, $end_date) {
    $analytics = [];
    
    // Total bookings
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings");
    $stmt->execute();
    $analytics['total_bookings'] = $stmt->fetchColumn();
    
    // Today's bookings
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $analytics['today_bookings'] = $stmt->fetchColumn();
    
    // Monthly bookings
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute();
    $analytics['monthly_bookings'] = $stmt->fetchColumn();
    
    // Booking status breakdown
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM bookings 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['status_breakdown'] = $stmt->fetchAll();
    
    // Booking growth
    $stmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM bookings 
        WHERE created_at BETWEEN ? AND ? 
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['booking_growth'] = $stmt->fetchAll();
    
    // Most requested categories
    $stmt = $db->prepare("
        SELECT c.name, COUNT(b.id) as booking_count
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        JOIN provider_services ps ON sp.id = ps.provider_id
        JOIN categories c ON ps.category_id = c.id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['top_categories'] = $stmt->fetchAll();
    
    // Bookings by district
    $stmt = $db->prepare("
        SELECT sp.location, COUNT(b.id) as booking_count
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY sp.location
        ORDER BY booking_count DESC
        LIMIT 15
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['bookings_by_district'] = $stmt->fetchAll();
    
    // Cancellation rate
    $stmt = $db->prepare("
        SELECT 
            ROUND((SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as cancellation_rate
        FROM bookings 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['cancellation_rate'] = $stmt->fetchColumn();
    
    return $analytics;
}

// 🟣 3. Financial Analytics
function getFinancialAnalytics($db, $start_date, $end_date) {
    $analytics = [];
    
    // Total commission (estimated based on hourly rates)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(sp.hourly_rate * 0.1), 0) as total_commission
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.status = 'completed' 
        AND b.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['total_commission'] = $stmt->fetchColumn();
    
    // Total platform value (all completed bookings)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(sp.hourly_rate), 0) as total_value
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.status = 'completed' 
        AND b.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['total_platform_value'] = $stmt->fetchColumn();
    
    // Revenue by category
    $stmt = $db->prepare("
        SELECT c.name, COALESCE(SUM(sp.hourly_rate), 0) as revenue
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        JOIN provider_services ps ON sp.id = ps.provider_id
        JOIN categories c ON ps.category_id = c.id
        WHERE b.status = 'completed' 
        AND b.created_at BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['revenue_by_category'] = $stmt->fetchAll();
    
    // Monthly revenue growth
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(b.created_at, '%Y-%m') as month,
            COALESCE(SUM(sp.hourly_rate), 0) as revenue
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.status = 'completed'
        AND b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $analytics['revenue_growth'] = $stmt->fetchAll();
    
    return $analytics;
}

// 🟠 4. Provider Performance Analytics
function getProviderPerformance($db, $start_date, $end_date) {
    $analytics = [];

    // First, ensure performance data is up to date for recent providers
    updateRecentProviderPerformance($db, $start_date, $end_date);

    // Get comprehensive performance metrics
    $stmt = $db->prepare("
        SELECT
            pp.*,
            u.full_name,
            sp.profession,
            sp.location,
            sp.verification_level,
            sp.is_active,
            sp.is_featured,
            (SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = sp.id AND pv.viewed_at BETWEEN ? AND ?) as profile_views,
            (SELECT COUNT(*) FROM provider_shares ps WHERE ps.provider_id = sp.id AND ps.shared_at BETWEEN ? AND ?) as shares_count
        FROM provider_performance pp
        JOIN service_providers sp ON pp.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE pp.period_start = ? AND pp.period_end = ?
        ORDER BY pp.overall_performance_score DESC
        LIMIT 50
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59', $start_date, $end_date . ' 23:59:59', $start_date, $end_date]);
    $analytics['provider_performance'] = $stmt->fetchAll();

    // Performance grade distribution
    $stmt = $db->prepare("
        SELECT performance_grade, COUNT(*) as count
        FROM provider_performance
        WHERE period_start = ? AND period_end = ?
        GROUP BY performance_grade
        ORDER BY
            CASE performance_grade
                WHEN 'excellent' THEN 1
                WHEN 'good' THEN 2
                WHEN 'average' THEN 3
                WHEN 'needs_improvement' THEN 4
            END
    ");
    $stmt->execute([$start_date, $end_date]);
    $analytics['grade_distribution'] = $stmt->fetchAll();

    // Top performers by different metrics
    $stmt = $db->prepare("
        SELECT 'Highest Rated' as category, u.full_name, sp.profession, pp.avg_rating as value, 'stars' as unit
        FROM provider_performance pp
        JOIN service_providers sp ON pp.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE pp.period_start = ? AND pp.period_end = ?
        ORDER BY pp.avg_rating DESC LIMIT 1
        UNION ALL
        SELECT 'Fastest Response' as category, u.full_name, sp.profession, pp.avg_response_time_hours as value, 'hours' as unit
        FROM provider_performance pp
        JOIN service_providers sp ON pp.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE pp.period_start = ? AND pp.period_end = ? AND pp.avg_response_time_hours IS NOT NULL
        ORDER BY pp.avg_response_time_hours ASC LIMIT 1
        UNION ALL
        SELECT 'Lowest Cancellation' as category, u.full_name, sp.profession, pp.cancellation_rate as value, '%' as unit
        FROM provider_performance pp
        JOIN service_providers sp ON pp.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE pp.period_start = ? AND pp.period_end = ?
        ORDER BY pp.cancellation_rate ASC LIMIT 1
        UNION ALL
        SELECT 'Most Reliable' as category, u.full_name, sp.profession, pp.on_time_completion_rate as value, '%' as unit
        FROM provider_performance pp
        JOIN service_providers sp ON pp.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE pp.period_start = ? AND pp.period_end = ?
        ORDER BY pp.on_time_completion_rate DESC LIMIT 1
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $analytics['top_performers'] = $stmt->fetchAll();

    // Average metrics across all providers
    $stmt = $db->prepare("
        SELECT
            AVG(avg_rating) as avg_rating,
            AVG(cancellation_rate) as avg_cancellation_rate,
            AVG(avg_response_time_hours) as avg_response_time,
            AVG(on_time_completion_rate) as avg_on_time_rate,
            AVG(client_satisfaction_score) as avg_satisfaction,
            AVG(overall_performance_score) as avg_overall_score
        FROM provider_performance
        WHERE period_start = ? AND period_end = ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $analytics['averages'] = $stmt->fetch();

    // Availability patterns analysis (booking patterns by day and time)
    $stmt = $db->prepare("
        SELECT
            DAYOFWEEK(b.created_at) - 1 as day_index,
            CASE
                WHEN HOUR(b.created_at) BETWEEN 6 AND 11 THEN 'morning'
                WHEN HOUR(b.created_at) BETWEEN 12 AND 17 THEN 'afternoon'
                WHEN HOUR(b.created_at) BETWEEN 18 AND 23 THEN 'evening'
                ELSE 'night'
            END as time_period,
            COUNT(*) as booking_count
        FROM bookings b
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY day_index, time_period
        ORDER BY day_index, time_period
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $patterns = $stmt->fetchAll();

    // Initialize availability patterns array
    $analytics['availability_patterns'] = [
        'morning' => array_fill(0, 7, 0),
        'afternoon' => array_fill(0, 7, 0),
        'evening' => array_fill(0, 7, 0),
        'night' => array_fill(0, 7, 0)
    ];

    // Fill in the booking counts
    foreach ($patterns as $pattern) {
        $day_index = (int)$pattern['day_index'];
        $time_period = $pattern['time_period'];
        $count = (int)$pattern['booking_count'];
        
        if (isset($analytics['availability_patterns'][$time_period][$day_index])) {
            $analytics['availability_patterns'][$time_period][$day_index] = $count;
        }
    }

    return $analytics;
}

// Helper function to update performance data for recent providers
function updateRecentProviderPerformance($db, $start_date, $end_date) {
    require_once '../includes/provider_performance.php';
    $performanceManager = new ProviderPerformanceManager();

    // Get providers with recent activity
    $stmt = $db->prepare("
        SELECT DISTINCT sp.id
        FROM service_providers sp
        JOIN bookings b ON sp.id = b.provider_id
        WHERE b.created_at BETWEEN ? AND ?
        ORDER BY sp.id
    ");
    $stmt->execute([$start_date, $end_date]);
    $active_providers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($active_providers as $provider_id) {
        try {
            $performanceManager->updateProviderPerformance($provider_id, $start_date, $end_date);
        } catch (Exception $e) {
            error_log("Failed to update performance for provider {$provider_id}: " . $e->getMessage());
        }
    }
}

// 🟢 5. Client Behavior Analytics
function getClientBehavior($db, $start_date, $end_date) {
    $analytics = [];
    
    // Client behavior metrics
    $stmt = $db->prepare("
        SELECT 
            u.full_name,
            u.email,
            COUNT(b.id) as total_bookings,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            (SELECT COUNT(*) FROM reviews r WHERE r.client_id = u.id) as reviews_given,
            (SELECT COUNT(*) FROM reports r WHERE r.reporter_id = u.id) as complaints_filed,
            ROUND((SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as cancellation_rate,
            CASE 
                WHEN COUNT(b.id) >= 10 AND (SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) < 0.1 THEN 'Loyal'
                WHEN COUNT(b.id) >= 5 THEN 'Active'
                WHEN COUNT(b.id) >= 1 THEN 'Occasional'
                ELSE 'New'
            END as loyalty_tier
        FROM users u
        LEFT JOIN bookings b ON u.id = b.client_id AND b.created_at BETWEEN ? AND ?
        WHERE u.user_type = 'client'
        GROUP BY u.id
        HAVING total_bookings > 0
        ORDER BY total_bookings DESC
        LIMIT 15
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $analytics['client_behavior'] = $stmt->fetchAll();
    
    return $analytics;
}

// Get all analytics data
$user_analytics = getUserAnalytics($db, $start_date, $end_date);
$booking_analytics = getBookingAnalytics($db, $start_date, $end_date);
$financial_analytics = getFinancialAnalytics($db, $start_date, $end_date);
$provider_performance = getProviderPerformance($db, $start_date, $end_date);
$client_behavior = getClientBehavior($db, $start_date, $end_date);

// Get categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Get unique districts
$districts = $db->query("SELECT DISTINCT location FROM service_providers WHERE location IS NOT NULL ORDER BY location")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - BII LocalFinder</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Admin Layout */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .sidebar-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }
        
        /* Analytics Header */
        .analytics-header {
            background: linear-gradient(135deg, var(--primary), #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Date Filters */
        .date-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid var(--primary);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .stat-trend {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .trend-up { background: #d1fae5; color: #065f46; }
        .trend-down { background: #fee2e2; color: #991b1b; }
        
        /* Chart Container */
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--secondary);
        }
        
        .tab-button:hover {
            background: #f8f9fa;
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Performance Badges */
        .performance-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-excellent { background: #d1fae5; color: #065f46; }
        .badge-good { background: #dbeafe; color: #1e40af; }
        .badge-average { background: #fef3c7; color: #92400e; }
        .badge-poor { background: #fee2e2; color: #991b1b; }
        
        /* Loyalty Badges */
        .loyalty-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-loyal { background: #d1fae5; color: #065f46; }
        .badge-active { background: #dbeafe; color: #1e40af; }
        .badge-occasional { background: #fef3c7; color: #92400e; }
        .badge-new { background: #e0e7ff; color: #3730a3; }
        
        /* Analytics Section */
        .analytics-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        /* Report Options */
        .report-option {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .tab-button {
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Admin Layout -->
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Analytics Header -->
            <div class="analytics-header">
                <h1><i class="fas fa-chart-line me-2"></i> Analytics Dashboard</h1>
                <p>Comprehensive platform analytics and business intelligence</p>
            </div>

            <!-- Date Range Filters -->
            <div class="date-filters">
                <form method="GET" id="analyticsForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" id="date_range" class="form-select" onchange="updateDateRange()">
                                <option value="7days" <?php echo $date_range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30days" <?php echo $date_range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90days" <?php echo $date_range === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2" id="custom_dates" style="display: <?php echo $date_range === 'custom' ? 'block' : 'none'; ?>">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="col-md-2" id="custom_dates_end" style="display: <?php echo $date_range === 'custom' ? 'block' : 'none'; ?>">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">District</label>
                            <select name="district" class="form-select">
                                <option value="">All Districts</option>
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo $district['location']; ?>" <?php echo $district_filter == $district['location'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($district['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-button active" onclick="switchTab('overview')">Overview</button>
                <button class="tab-button" onclick="switchTab('users')">User Analytics</button>
                <button class="tab-button" onclick="switchTab('bookings')">Booking Analytics</button>
                <button class="tab-button" onclick="switchTab('financial')">Financial Analytics</button>
                <button class="tab-button" onclick="switchTab('providers')">Provider Performance</button>
                <button class="tab-button" onclick="switchTab('clients')">Client Behavior</button>
                <button class="tab-button" onclick="switchTab('reports')">Export Reports</button>
            </div>

            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <!-- Key Metrics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $user_analytics['total_users']; ?></div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up me-1"></i> <?php echo count($user_analytics['user_growth']); ?> new
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $booking_analytics['total_bookings']; ?></div>
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up me-1"></i> <?php echo $booking_analytics['today_bookings']; ?> today
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">RWF <?php echo number_format($financial_analytics['total_platform_value']); ?></div>
                        <div class="stat-label">Platform Value</div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up me-1"></i> RWF <?php echo number_format($financial_analytics['total_commission']); ?> commission
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $user_analytics['active_users']; ?></div>
                        <div class="stat-label">Active Users (30 days)</div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-users me-1"></i> Engaged
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div class="chart-title">User Growth</div>
                            </div>
                            <canvas id="userGrowthChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div class="chart-title">Booking Status Distribution</div>
                            </div>
                            <canvas id="bookingStatusChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div class="chart-title">Most Requested Categories</div>
                            </div>
                            <canvas id="topCategoriesChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div class="chart-title">Revenue by Category</div>
                            </div>
                            <canvas id="revenueByCategoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Analytics Tab -->
            <div id="users" class="tab-content">
                <div class="analytics-section">
                    <h3 class="section-title">User Growth & Distribution</h3>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="chart-container">
                                <canvas id="detailedUserGrowthChart" height="300"></canvas>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="chart-container">
                                <h4>User Type Distribution</h4>
                                <canvas id="userTypeChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="analytics-section">
                    <h3 class="section-title">Top Performers</h3>
                    
                    <div class="row">
                        <!-- Top Providers -->
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Top 10 Providers by Rating</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Provider</th>
                                                <th>Profession</th>
                                                <th>Rating</th>
                                                <th>Jobs</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_analytics['top_providers'] as $provider): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($provider['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($provider['profession']); ?></td>
                                                    <td><?php echo number_format($provider['average_rating'], 1); ?> ⭐</td>
                                                    <td><?php echo $provider['completed_jobs']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Clients -->
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Most Active Clients</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Bookings</th>
                                                <th>Reviews</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_analytics['active_clients'] as $client): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                                                    <td><?php echo $client['booking_count']; ?></td>
                                                    <td><?php echo $client['review_count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Analytics Tab -->
            <div id="bookings" class="tab-content">
                <div class="analytics-section">
                    <h3 class="section-title">Booking Performance</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $booking_analytics['today_bookings']; ?></div>
                            <div class="stat-label">Today's Bookings</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $booking_analytics['monthly_bookings']; ?></div>
                            <div class="stat-label">This Month</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $booking_analytics['cancellation_rate']; ?>%</div>
                            <div class="stat-label">Cancellation Rate</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($booking_analytics['top_categories']); ?></div>
                            <div class="stat-label">Active Categories</div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Booking Growth Trend</h4>
                                <canvas id="bookingGrowthChart" height="250"></canvas>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Bookings by District</h4>
                                <canvas id="bookingsByDistrictChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Analytics Tab -->
            <div id="financial" class="tab-content">
                <div class="analytics-section">
                    <h3 class="section-title">Financial Overview</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">RWF <?php echo number_format($financial_analytics['total_platform_value']); ?></div>
                            <div class="stat-label">Total Platform Value</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value">RWF <?php echo number_format($financial_analytics['total_commission']); ?></div>
                            <div class="stat-label">Estimated Commission</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($financial_analytics['revenue_by_category']); ?></div>
                            <div class="stat-label">Revenue Categories</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value">6</div>
                            <div class="stat-label">Months Tracked</div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Revenue Growth</h4>
                                <canvas id="revenueGrowthChart" height="250"></canvas>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Revenue by Category</h4>
                                <canvas id="detailedRevenueChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Provider Performance Tab -->
            <div id="providers" class="tab-content">
                <div class="analytics-section">
                    <h3 class="section-title">Provider Performance Dashboard</h3>

                    <!-- Performance Summary Cards -->
                    <div class="stats-grid mb-4">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($provider_performance['averages']['avg_overall_score'] ?? 0, 1); ?>/100</div>
                            <div class="stat-label">Avg Performance Score</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($provider_performance['averages']['avg_rating'] ?? 0, 1); ?>⭐</div>
                            <div class="stat-label">Avg Rating</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($provider_performance['averages']['avg_cancellation_rate'] ?? 0, 1); ?>%</div>
                            <div class="stat-label">Avg Cancellation Rate</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($provider_performance['provider_performance']); ?></div>
                            <div class="stat-label">Active Providers</div>
                        </div>
                    </div>

                    <!-- Performance Grade Distribution -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Performance Grade Distribution</h4>
                                <canvas id="performanceGradeChart" height="200"></canvas>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="chart-container">
                                <h4>Top Performers</h4>
                                <div class="top-performers-list">
                                    <?php foreach ($provider_performance['top_performers'] as $performer): ?>
                                        <div class="performer-item">
                                            <div class="performer-category"><?php echo htmlspecialchars($performer['category']); ?></div>
                                            <div class="performer-name"><?php echo htmlspecialchars($performer['full_name']); ?> (<?php echo htmlspecialchars($performer['profession']); ?>)</div>
                                            <div class="performer-value"><?php echo htmlspecialchars($performer['value']); ?><?php echo htmlspecialchars($performer['unit']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <!-- Availability Patterns Analysis -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="chart-container">
                                <h4>Provider Availability Patterns</h4>
                                <p class="text-muted">Booking patterns by day of week and hour of day</p>
                                <canvas id="availabilityPatternsChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h4>Provider Performance Rankings</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Provider</th>
                                        <th>Profession</th>
                                        <th>Location</th>
                                        <th>Overall Score</th>
                                        <th>Rating</th>
                                        <th>Response Time</th>
                                        <th>Cancellation Rate</th>
                                        <th>On-Time Rate</th>
                                        <th>Grade</th>
                                        <th>Verification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($provider_performance['provider_performance'] as $provider): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($provider['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($provider['profession']); ?></td>
                                            <td><?php echo htmlspecialchars($provider['location']); ?></td>
                                            <td><strong><?php echo number_format($provider['overall_performance_score'], 1); ?>/100</strong></td>
                                            <td><?php echo number_format($provider['avg_rating'], 1); ?> ⭐</td>
                                            <td>
                                                <?php if ($provider['avg_response_time_hours'] !== null): ?>
                                                    <?php echo number_format($provider['avg_response_time_hours'], 1); ?>h
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($provider['cancellation_rate'], 1); ?>%</td>
                                            <td><?php echo number_format($provider['on_time_completion_rate'], 1); ?>%</td>
                                            <td>
                                                <span class="performance-badge badge-<?php echo $provider['performance_grade']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $provider['performance_grade'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="verification-badge badge-<?php echo strtolower($provider['verification_level']); ?>">
                                                    <?php echo ucfirst($provider['verification_level']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Client Behavior Tab -->
            <div id="clients" class="tab-content">
                <div class="analytics-section">
                    <h3 class="section-title">Client Behavior Analysis</h3>
                    
                    <div class="chart-container">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Total Bookings</th>
                                        <th>Completed</th>
                                        <th>Cancelled</th>
                                        <th>Cancellation Rate</th>
                                        <th>Reviews Given</th>
                                        <th>Complaints Filed</th>
                                        <th>Loyalty Tier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($client_behavior['client_behavior'] as $client): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                                            <td><?php echo $client['total_bookings']; ?></td>
                                            <td><?php echo $client['completed_bookings']; ?></td>
                                            <td><?php echo $client['cancelled_bookings']; ?></td>
                                            <td><?php echo $client['cancellation_rate']; ?>%</td>
                                            <td><?php echo $client['reviews_given']; ?></td>
                                            <td><?php echo $client['complaints_filed']; ?></td>
                                            <td>
                                                <span class="loyalty-badge badge-<?php echo strtolower($client['loyalty_tier']); ?>">
                                                    <?php echo $client['loyalty_tier']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Reports Tab -->
            <div id="reports" class="tab-content">
                <div class="analytics-section">
                    <h3 class="section-title">Export Reports</h3>
                    
                    <div class="chart-container">
                        <h4>Generate Custom Reports</h4>
                        <p>Select the type of report you want to generate and export:</p>
                        
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="report-option">
                                    <h5><i class="fas fa-users me-2"></i> User Reports</h5>
                                    <p>Export user growth, registration data, and activity reports</p>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-primary btn-sm" onclick="exportReport('users', 'csv')">
                                            <i class="fas fa-file-csv me-1"></i> CSV
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="exportReport('users', 'pdf')">
                                            <i class="fas fa-file-pdf me-1"></i> PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="report-option">
                                    <h5><i class="fas fa-calendar me-2"></i> Booking Reports</h5>
                                    <p>Export booking statistics, completion rates, and performance data</p>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-primary btn-sm" onclick="exportReport('bookings', 'csv')">
                                            <i class="fas fa-file-csv me-1"></i> CSV
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="exportReport('bookings', 'pdf')">
                                            <i class="fas fa-file-pdf me-1"></i> PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="report-option">
                                    <h5><i class="fas fa-chart-bar me-2"></i> Financial Reports</h5>
                                    <p>Export revenue data, commission reports, and financial analytics</p>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-primary btn-sm" onclick="exportReport('financial', 'csv')">
                                            <i class="fas fa-file-csv me-1"></i> CSV
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="exportReport('financial', 'pdf')">
                                            <i class="fas fa-file-pdf me-1"></i> PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Custom Report Builder -->
                        <div class="mt-4 pt-3 border-top">
                            <h5><i class="fas fa-cogs me-2"></i> Custom Report Builder</h5>
                            <p>Create a custom report with specific filters and data points:</p>
                            
                            <form id="customReportForm" class="mt-3">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Report Type</label>
                                        <select name="report_type" class="form-select" required>
                                            <option value="bookings">Bookings</option>
                                            <option value="users">Users</option>
                                            <option value="providers">Providers</option>
                                            <option value="financial">Financial</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Date Range</label>
                                        <select name="custom_date_range" class="form-select">
                                            <option value="7days">Last 7 Days</option>
                                            <option value="30days">Last 30 Days</option>
                                            <option value="90days">Last 90 Days</option>
                                            <option value="custom">Custom Range</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Category</label>
                                        <select name="custom_category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Additional Filters (JSON)</label>
                                    <textarea name="custom_filters" class="form-control" rows="3" placeholder='{"status": "completed", "min_rating": 4}'></textarea>
                                </div>
                                
                                <button type="button" class="btn btn-success" onclick="generateCustomReport()">
                                    <i class="fas fa-magic me-2"></i> Generate Custom Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart data preparation
        const userGrowthData = {
            labels: <?php echo json_encode(array_column($user_analytics['user_growth'], 'date')); ?>,
            datasets: [{
                label: 'New Users',
                data: <?php echo json_encode(array_column($user_analytics['user_growth'], 'count')); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            }]
        };

        const bookingStatusData = {
            labels: <?php echo json_encode(array_column($booking_analytics['status_breakdown'], 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($booking_analytics['status_breakdown'], 'count')); ?>,
                backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#dc3545']
            }]
        };

        const topCategoriesData = {
            labels: <?php echo json_encode(array_column($booking_analytics['top_categories'], 'name')); ?>,
            datasets: [{
                label: 'Bookings',
                data: <?php echo json_encode(array_column($booking_analytics['top_categories'], 'booking_count')); ?>,
                backgroundColor: '#0d6efd'
            }]
        };

        const revenueByCategoryData = {
            labels: <?php echo json_encode(array_column($financial_analytics['revenue_by_category'], 'name')); ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?php echo json_encode(array_column($financial_analytics['revenue_by_category'], 'revenue')); ?>,
                backgroundColor: '#198754'
            }]
        };

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Overview charts
            createLineChart('userGrowthChart', userGrowthData);
            createDoughnutChart('bookingStatusChart', bookingStatusData);
            createBarChart('topCategoriesChart', topCategoriesData);
            createBarChart('revenueByCategoryChart', revenueByCategoryData);
            
            // User analytics charts
            createLineChart('detailedUserGrowthChart', userGrowthData);
            
            const userTypeData = {
                labels: ['Clients', 'Providers'],
                datasets: [{
                    data: [<?php echo $user_analytics['total_clients']; ?>, <?php echo $user_analytics['total_providers']; ?>],
                    backgroundColor: ['#0d6efd', '#198754']
                }]
            };
            createDoughnutChart('userTypeChart', userTypeData);
            
            // Booking analytics charts
            const bookingGrowthData = {
                labels: <?php echo json_encode(array_column($booking_analytics['booking_growth'], 'date')); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode(array_column($booking_analytics['booking_growth'], 'count')); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4
                }]
            };
            createLineChart('bookingGrowthChart', bookingGrowthData);
            
            const bookingsByDistrictData = {
                labels: <?php echo json_encode(array_column($booking_analytics['bookings_by_district'], 'location')); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode(array_column($booking_analytics['bookings_by_district'], 'booking_count')); ?>,
                    backgroundColor: '#fd7e14'
                }]
            };
            createBarChart('bookingsByDistrictChart', bookingsByDistrictData);
            
            // Financial charts
            const revenueGrowthData = {
                labels: <?php echo json_encode(array_column($financial_analytics['revenue_growth'], 'month')); ?>,
                datasets: [{
                    label: 'Revenue (RWF)',
                    data: <?php echo json_encode(array_column($financial_analytics['revenue_growth'], 'revenue')); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4
                }]
            };
            createLineChart('revenueGrowthChart', revenueGrowthData);
            
            createBarChart('detailedRevenueChart', revenueByCategoryData);
            
            // Provider performance charts
            const performanceGradeData = {
                labels: <?php echo json_encode(array_column($provider_performance['grade_distribution'], 'performance_grade')); ?>,
                datasets: [{
                    label: 'Providers',
                    data: <?php echo json_encode(array_column($provider_performance['grade_distribution'], 'count')); ?>,
                    backgroundColor: ['#d1fae5', '#dbeafe', '#fef3c7', '#fee2e2'],
                    borderColor: ['#065f46', '#1e40af', '#92400e', '#991b1b'],
                    borderWidth: 1
                }]
            };
            createBarChart('performanceGradeChart', performanceGradeData);
            
            // Availability patterns chart
            const availabilityPatternsData = {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Morning (6-12)',
                    data: <?php echo json_encode($provider_performance['availability_patterns']['morning'] ?? array_fill(0, 7, 0)); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: '#0d6efd',
                    borderWidth: 1
                }, {
                    label: 'Afternoon (12-18)',
                    data: <?php echo json_encode($provider_performance['availability_patterns']['afternoon'] ?? array_fill(0, 7, 0)); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: '#198754',
                    borderWidth: 1
                }, {
                    label: 'Evening (18-24)',
                    data: <?php echo json_encode($provider_performance['availability_patterns']['evening'] ?? array_fill(0, 7, 0)); ?>,
                    backgroundColor: 'rgba(255, 193, 7, 0.7)',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            };
            createBarChart('availabilityPatternsChart', availabilityPatternsData);
        });

        // Chart creation functions
        function createLineChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true }
                    }
                }
            });
        }

        function createDoughnutChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true
                }
            });
        }

        function createBarChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });

        // Tab navigation
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Activate clicked button
            event.target.classList.add('active');
        }

        // Date range handling
        function updateDateRange() {
            const dateRange = document.getElementById('date_range').value;
            const customDates = document.getElementById('custom_dates');
            const customDatesEnd = document.getElementById('custom_dates_end');
            
            if (dateRange === 'custom') {
                customDates.style.display = 'block';
                customDatesEnd.style.display = 'block';
            } else {
                customDates.style.display = 'none';
                customDatesEnd.style.display = 'none';
            }
        }

        // Export functions
        function exportReport(type, format) {
            const params = new URLSearchParams(window.location.search);
            window.open(`api/export_${type}.php?${params.toString()}&format=${format}`, '_blank');
        }

        function generateCustomReport() {
            const form = document.getElementById('customReportForm');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            
            for (let [key, value] of formData) {
                params.append(key, value);
            }
            
            // Add current filter params
            const currentParams = new URLSearchParams(window.location.search);
            for (let [key, value] of currentParams) {
                if (!params.has(key)) {
                    params.append(key, value);
                }
            }
            
            window.open(`api/custom_report.php?${params.toString()}`, '_blank');
        }
    </script>
</body>
</html>