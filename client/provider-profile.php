<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/provider_requirements.php';
require_once '../includes/service_negotiation.php';

// Load platform settings
function getPlatformSetting($key, $default = '') {
    global $db;
    static $settings = null;
    
    if ($settings === null) {
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            error_log("Settings load error: " . $e->getMessage());
            $settings = [];
        }
    }
    
    return $settings[$key] ?? $default;
}

$db = Database::getInstance()->getConnection();

// Get platform settings
$platform_name = getPlatformSetting('platform_name', 'BII LocalFinder');
$platform_description = getPlatformSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

// Get provider ID
$provider_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$share_id = isset($_GET['share_id']) ? intval($_GET['share_id']) : null;

if (!$provider_id) {
    header("Location: providers.php");
    exit();
}

// Ensure booking share link connection column exists
try {
    $colStmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'provider_share_id'");
    $colStmt->execute();
    if ($colStmt->fetchColumn() == 0) {
        $db->exec("ALTER TABLE bookings ADD COLUMN provider_share_id INT NULL AFTER status");
    }
} catch (Exception $e) {
    error_log('Booking share column check error: ' . $e->getMessage());
}

// Get provider details with verification status
$stmt = $db->prepare("
    SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.created_at as member_since,
           u.is_verified as user_verified
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.id = ? AND sp.is_active = 1 AND sp.is_banned = 0
");
$stmt->execute([$provider_id]);
$provider = $stmt->fetch();

if (!$provider) {
    header("Location: providers.php");
    exit();
}

// Initialize requirements checker to show profile completeness
$requirements = new ProviderRequirements($db, $provider_id);

// Get provider visibility settings
$stmt = $db->prepare("
    SELECT setting_key, setting_value FROM provider_settings 
    WHERE provider_id = ? AND setting_key LIKE 'visibility_%'
");
$stmt->execute([$provider_id]);
$visibilitySettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Parse visibility settings with defaults
$visibility = [
    'show_phone' => isset($visibilitySettings['visibility_show_phone']) ? (bool)$visibilitySettings['visibility_show_phone'] : true,
    'show_whatsapp' => isset($visibilitySettings['visibility_show_whatsapp']) ? (bool)$visibilitySettings['visibility_show_whatsapp'] : true,
    'show_exact_location' => isset($visibilitySettings['visibility_show_exact_location']) ? (bool)$visibilitySettings['visibility_show_exact_location'] : false,
    'profile_public' => isset($visibilitySettings['visibility_profile_public']) ? (bool)$visibilitySettings['visibility_profile_public'] : true,
    'appear_in_search' => isset($visibilitySettings['visibility_appear_in_search']) ? (bool)$visibilitySettings['visibility_appear_in_search'] : true,
    'appear_available' => isset($visibilitySettings['visibility_appear_available']) ? (bool)$visibilitySettings['visibility_appear_available'] : true,
    'emergency_service' => isset($visibilitySettings['visibility_emergency_service']) ? (bool)$visibilitySettings['visibility_emergency_service'] : false,
    'night_service' => isset($visibilitySettings['visibility_night_service']) ? (bool)$visibilitySettings['visibility_night_service'] : false,
    'weekend_service' => isset($visibilitySettings['visibility_weekend_service']) ? (bool)$visibilitySettings['visibility_weekend_service'] : true,
    'badge_verified' => isset($visibilitySettings['visibility_badge_verified']) ? (bool)$visibilitySettings['visibility_badge_verified'] : true,
    'badge_top_rated' => isset($visibilitySettings['visibility_badge_top_rated']) ? (bool)$visibilitySettings['visibility_badge_top_rated'] : true,
    'badge_fast_responder' => isset($visibilitySettings['visibility_badge_fast_responder']) ? (bool)$visibilitySettings['visibility_badge_fast_responder'] : true
];

// Check if profile should be hidden (redirect if profile_public is false)
if (!$visibility['profile_public']) {
    header("Location: providers.php");
    exit();
}

// Get provider's schedule information
$stmt = $db->prepare("
    SELECT 
        working_days,
        working_hours_start,
        working_hours_end,
        break_start,
        break_end,
        availability,
        buffer_time,
        max_daily_bookings
    FROM service_providers 
    WHERE id = ?
");
$stmt->execute([$provider_id]);
$schedule_info = $stmt->fetch();

// Parse working days
$working_days = $schedule_info['working_days'] ? explode(',', $schedule_info['working_days']) : [1,2,3,4,5];
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$formatted_working_days = [];
foreach ($working_days as $day_num) {
    if (isset($day_names[$day_num-1])) {
        $formatted_working_days[] = $day_names[$day_num-1];
    }
}

// Get upcoming availability exceptions
$stmt = $db->prepare("
    SELECT date, is_available, start_time, end_time, notes
    FROM provider_availability 
    WHERE provider_id = ? AND date >= CURDATE()
    ORDER BY date ASC
    LIMIT 5
");
$stmt->execute([$provider_id]);
$availability_exceptions = $stmt->fetchAll();

// Get upcoming time off
$stmt = $db->prepare("
    SELECT start_date, end_date, reason
    FROM provider_time_off 
    WHERE provider_id = ? AND end_date >= CURDATE() AND is_approved = 1
    ORDER BY start_date ASC
    LIMIT 3
");
$stmt->execute([$provider_id]);
$time_off_periods = $stmt->fetchAll();

// Get next available booking date
$next_available_date = null;
if (!empty($working_days)) {
    $today = date('Y-m-d');
    $check_date = $today;
    $days_checked = 0;
    
    while ($days_checked < 30) { // Check next 30 days
        $day_of_week = date('N', strtotime($check_date));
        
        // Check if it's a working day
        if (in_array($day_of_week, $working_days)) {
            $date_available = true;
            
            // Check time off
            foreach ($time_off_periods as $time_off) {
                if ($check_date >= $time_off['start_date'] && $check_date <= $time_off['end_date']) {
                    $date_available = false;
                    break;
                }
            }
            
            // Check availability exceptions
            if ($date_available) {
                foreach ($availability_exceptions as $exception) {
                    if ($exception['date'] == $check_date && $exception['is_available'] == 0) {
                        $date_available = false;
                        break;
                    }
                }
            }
            
            if ($date_available) {
                $next_available_date = $check_date;
                break;
            }
        }
        
        $check_date = date('Y-m-d', strtotime($check_date . ' +1 day'));
        $days_checked++;
    }
}

// Get provider services with detailed information
$stmt = $db->prepare("
    SELECT ps.*, c.name as category_name, c.icon as category_icon
    FROM provider_services ps
    JOIN categories c ON ps.category_id = c.id
    WHERE ps.provider_id = ? AND ps.is_available = 1
    ORDER BY ps.created_at DESC
");
$stmt->execute([$provider_id]);
$services = $stmt->fetchAll();

// Get provider categories for the services section
$stmt = $db->prepare("
    SELECT DISTINCT c.* 
    FROM categories c
    JOIN provider_services ps ON c.id = ps.category_id
    WHERE ps.provider_id = ? AND ps.is_available = 1
");
$stmt->execute([$provider_id]);
$categories = $stmt->fetchAll();

// Get portfolio images
$stmt = $db->prepare("
    SELECT * FROM portfolio_images 
    WHERE provider_id = ? AND is_active = 1 
    ORDER BY display_order, uploaded_at DESC
    LIMIT 6
");
$stmt->execute([$provider_id]);
$portfolio_images = $stmt->fetchAll();
$portfolio_count = count($portfolio_images);

// Get portfolio video (only 1)
$stmt = $db->prepare("
    SELECT * FROM portfolio_videos 
    WHERE provider_id = ? AND is_active = 1 
    ORDER BY uploaded_at DESC
    LIMIT 1
");
$stmt->execute([$provider_id]);
$portfolio_video = $stmt->fetch();

// Get reviews
$stmt = $db->prepare("
    SELECT r.*, u.full_name as client_name, u.profile_image
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$provider_id]);
$reviews = $stmt->fetchAll();

// Calculate rating breakdown
$rating_breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($reviews as $review) {
    $rating_breakdown[$review['rating']]++;
}

// Get provider statistics
$total_completed_jobs_stmt = $db->prepare("
    SELECT COUNT(*) FROM bookings 
    WHERE provider_id = ? AND status = 'completed'
");
$total_completed_jobs_stmt->execute([$provider_id]);
$total_completed_jobs = $total_completed_jobs_stmt->fetchColumn() ?? 0;

$total_bookings_stmt = $db->prepare("
    SELECT COUNT(*) FROM bookings WHERE provider_id = ?
");
$total_bookings_stmt->execute([$provider_id]);
$total_bookings = $total_bookings_stmt->fetchColumn() ?? 0;

// Calculate average service price
$avg_price_stmt = $db->prepare("
    SELECT AVG(price) FROM provider_services 
    WHERE provider_id = ? AND is_available = 1
");
$avg_price_stmt->execute([$provider_id]);
$avg_service_price = $avg_price_stmt->fetchColumn() ?? 0;

// Calculate response time statistics
$stmt = $db->prepare("
    SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) as avg_response_hours,
        COUNT(*) as total_responses
    FROM bookings 
    WHERE provider_id = ? AND responded_at IS NOT NULL
");
$stmt->execute([$provider_id]);
$response_stats = $stmt->fetch();
$avg_response_time = $response_stats['avg_response_hours'] ?? null;
$total_responses = $response_stats['total_responses'] ?? 0;

// Get verification documents and status
$stmt = $db->prepare("
    SELECT document_type, status, uploaded_at 
    FROM verification_documents 
    WHERE provider_id = ? 
    ORDER BY uploaded_at DESC
");
$stmt->execute([$provider_id]);
$verificationDocs = $stmt->fetchAll();

// Calculate verification progress
$verificationProgress = 0;
$verificationSteps = [
    'email_verified' => $provider['user_verified'] ? 20 : 0,
    'national_id' => 0,
    'selfie' => 0,
    'business_reg' => 0,
    'certificate' => 0
];

foreach ($verificationDocs as $doc) {
    if ($doc['status'] === 'approved') {
        switch ($doc['document_type']) {
            case 'national_id': $verificationSteps['national_id'] = 20; break;
            case 'selfie': $verificationSteps['selfie'] = 20; break;
            case 'business_registration': $verificationSteps['business_reg'] = 20; break;
            case 'certificate': $verificationSteps['certificate'] = 20; break;
        }
    }
}

$verificationProgress = array_sum($verificationSteps);

// Determine verification badges - respect visibility settings
$badges = [];
if ($verificationProgress >= 80 && $visibility['badge_verified']) {
    $badges[] = ['name' => 'Verified Provider', 'icon' => 'fa-shield-check', 'color' => 'success'];
}
if ($provider['average_rating'] >= 4.0 && $visibility['badge_top_rated']) {
    $badges[] = ['name' => 'Top Rated', 'icon' => 'fa-star', 'color' => 'warning'];
}
if ($total_completed_jobs >= 10) {
    $badges[] = ['name' => 'Experienced', 'icon' => 'fa-award', 'color' => 'primary'];
}
if ($total_responses > 0 && $avg_response_time !== null && $avg_response_time <= 2 && $visibility['badge_fast_responder']) {
    $badges[] = ['name' => 'Quick Response', 'icon' => 'fa-lightning-bolt', 'color' => 'info'];
}

// Get payment methods
$stmt = $db->prepare("
    SELECT * FROM provider_payment_methods 
    WHERE provider_id = ? AND is_active = 1
    ORDER BY is_default DESC
");
$stmt->execute([$provider_id]);
$paymentMethods = $stmt->fetchAll();

// Get service areas
$stmt = $db->prepare("
    SELECT * FROM provider_service_areas 
    WHERE provider_id = ? 
    ORDER BY is_primary DESC
");
$stmt->execute([$provider_id]);
$serviceAreas = $stmt->fetchAll();

// Check if provider is in user's favorites
$is_favorite = false;
if (isLoggedIn() && !isProvider()) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM favorites 
        WHERE client_id = ? AND provider_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $provider_id]);
    $is_favorite = $stmt->fetchColumn() > 0;
}

// Handle add/remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to add favorites";
    } elseif (isProvider()) {
        $booking_errors[] = "Providers cannot add favorites";
    } else {
        try {
            require_once '../includes/notifications.php';
            
            // Get client info
            $client_stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $client_stmt->execute([$_SESSION['user_id']]);
            $client_data = $client_stmt->fetch();
            $client_name = $client_data['full_name'] ?? 'A client';
            
            if ($is_favorite) {
                // Remove from favorites
                $stmt = $db->prepare("DELETE FROM favorites WHERE client_id = ? AND provider_id = ?");
                $stmt->execute([$_SESSION['user_id'], $provider_id]);
                $is_favorite = false;
                $booking_success = "Removed from favorites";
                
                // Notify provider about removal
                notifyFavoriteAction($provider_id, $_SESSION['user_id'], 'removed', $client_name);
            } else {
                // Add to favorites
                $stmt = $db->prepare("INSERT IGNORE INTO favorites (client_id, provider_id) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $provider_id]);
                $is_favorite = true;
                $booking_success = "Added to favorites";
                
                // Notify provider about addition
                notifyFavoriteAction($provider_id, $_SESSION['user_id'], 'added', $client_name);
            }
        } catch (Exception $e) {
            error_log('Favorites notification error: ' . $e->getMessage());
            $booking_errors[] = "Failed to update favorites";
        }
    }
}

// Initialize social links configuration
$social_links = [
    'website' => ['label' => 'Website', 'field' => 'website', 'icon' => 'fas fa-globe', 'color' => '#0d6efd'],
    'facebook' => ['label' => 'Facebook', 'field' => 'facebook', 'icon' => 'fab fa-facebook-f', 'color' => '#1877F2'],
    'twitter' => ['label' => 'Twitter', 'field' => 'twitter', 'icon' => 'fab fa-twitter', 'color' => '#1DA1F2'],
    'instagram' => ['label' => 'Instagram', 'field' => 'instagram', 'icon' => 'fab fa-instagram', 'color' => '#E4405F'],
    'linkedin' => ['label' => 'LinkedIn', 'field' => 'linkedin', 'icon' => 'fab fa-linkedin-in', 'color' => '#0A66C2'],
    'youtube' => ['label' => 'YouTube', 'field' => 'youtube', 'icon' => 'fab fa-youtube', 'color' => '#FF0000'],
    'whatsapp' => ['label' => 'WhatsApp', 'field' => 'whatsapp', 'icon' => 'fab fa-whatsapp', 'color' => '#25D366'],
    'tiktok' => ['label' => 'TikTok', 'field' => 'tiktok', 'icon' => 'fab fa-tiktok', 'color' => '#000000']
];

// Count active social links
$active_links = 0;
foreach ($social_links as $link) {
    if (!empty($provider[$link['field']])) {
        $active_links++;
    }
}
// Also count other_social if present
if (!empty($provider['other_social'])) {
    $active_links++;
}

// Get similar providers based on:
// 1. Same service categories
// 2. Similar services offered
// 3. Similar pricing (within 20% range)
// 4. High average rating (4+ stars)
$similar_providers = [];
if (!empty($categories)) {
    $category_ids = array_column($categories, 'id');
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    
    // Build query to find similar providers
    $stmt = $db->prepare("
        SELECT DISTINCT
            sp.id,
            sp.profession,
            sp.location,
            sp.average_rating,
            sp.total_reviews,
            sp.experience_years,
            sp.is_verified,
            u.full_name,
            u.profile_image,
            u.is_verified as user_verified,
            COUNT(DISTINCT ps.id) as service_count,
            AVG(ps.price) as avg_service_price
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN provider_services ps ON sp.id = ps.provider_id AND ps.is_available = 1
        JOIN provider_categories pc ON sp.id = pc.provider_id
        WHERE sp.id != ?
            AND sp.is_active = 1
            AND sp.is_banned = 0
            AND pc.category_id IN ($placeholders)
            AND sp.average_rating >= 3.5
        GROUP BY sp.id
        ORDER BY sp.average_rating DESC, sp.total_reviews DESC, sp.id
        LIMIT 6
    ");
    
    $params = [$provider_id];
    $params = array_merge($params, $category_ids);
    $stmt->execute($params);
    $similar_providers = $stmt->fetchAll();
}

// Handle booking request
$booking_success = '';
$booking_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to book a service";
    } elseif (isProvider()) {
        $booking_errors[] = "Providers cannot book services";
    } else {
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;
        // Normalize invalid or empty service IDs to null so we don't insert 0 (which violates FK)
        if (!$service_id || $service_id <= 0) {
            $service_id = null;
        }
        $service_description = sanitize($_POST['service_description']);
        $preferred_date = sanitize($_POST['preferred_date']);
        $preferred_time = sanitize($_POST['preferred_time'] ?? '');
        
        if (empty($service_description) || empty($preferred_date) || !$service_id) {
            $booking_errors[] = "Please fill all required fields";
        }
        
        // Validate date is not in the past
        $selected_date = new DateTime($preferred_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($selected_date < $today) {
            $booking_errors[] = "Please select a date in the future";
        }
        
        // Validate selected day is a working day
        $day_of_week = $selected_date->format('N'); // 1=Monday, 7=Sunday
        if (!in_array($day_of_week, $working_days)) {
            $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $booking_errors[] = "Provider is not available on " . $day_names[$day_of_week-1] . "s";
        }
        
        // Validate against time off
        foreach ($time_off_periods as $time_off) {
            $time_off_start = new DateTime($time_off['start_date']);
            $time_off_end = new DateTime($time_off['end_date']);
            
            if ($selected_date >= $time_off_start && $selected_date <= $time_off_end) {
                $booking_errors[] = "Provider is on time off from " . 
                    date('M d', strtotime($time_off['start_date'])) . " to " . 
                    date('M d, Y', strtotime($time_off['end_date']));
                break;
            }
        }
        
        // Validate against availability exceptions
        foreach ($availability_exceptions as $exception) {
            if ($exception['date'] == $preferred_date && $exception['is_available'] == 0) {
                $booking_errors[] = "Provider is not available on this date";
                break;
            }
        }
        
        // Validate time if provided
        if ($preferred_time && $schedule_info['working_hours_start'] && $schedule_info['working_hours_end']) {
            $time = strtotime($preferred_time);
            $start_time = strtotime($schedule_info['working_hours_start']);
            $end_time = strtotime($schedule_info['working_hours_end']);
            
            if ($time < $start_time || $time > $end_time) {
                $booking_errors[] = "Please select a time between " . 
                    date('g:i A', $start_time) . " and " . 
                    date('g:i A', $end_time);
            }
        }
        
        // Validate service belongs to provider (service is now required)
        if (empty($booking_errors)) {
            $stmt = $db->prepare("SELECT id FROM provider_services WHERE id = ? AND provider_id = ? AND is_available = 1");
            $stmt->execute([$service_id, $provider_id]);
            if (!$stmt->fetch()) {
                $booking_errors[] = "Invalid service selected";
            }
        }
        
        if (empty($booking_errors)) {
            $stmt = $db->prepare("
                INSERT INTO bookings (client_id, provider_id, service_id, service_description, preferred_date, preferred_time, status, provider_share_id)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");

            // Ensure we pass NULL for service_id when none selected to avoid FK constraint errors
            $params = [
                $_SESSION['user_id'],
                $provider_id,
                $service_id,
                $service_description,
                $preferred_date,
                $preferred_time,
                $share_id ? $share_id : null
            ];
            if ($stmt->execute($params)) {
                $booking_id = $db->lastInsertId();
                
                // Update user_profiles to track booking metrics
                $update_profile = $db->prepare("
                    INSERT INTO user_profiles (user_id, user_total_bookings, user_avg_price, user_avg_response_time) 
                    VALUES (?, 1, 0, 24) 
                    ON DUPLICATE KEY UPDATE 
                        user_total_bookings = user_total_bookings + 1,
                        updated_at = CURRENT_TIMESTAMP
                ");
                $update_profile->execute([$_SESSION['user_id']]);
                
                $booking_success = "Booking request sent successfully! The provider will contact you soon.";
                
                // Create notification for provider
                try {
                    require_once '../includes/notifications.php';
                    $client_name = '';
                    
                    // Get client info
                    $client_stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                    $client_stmt->execute([$_SESSION['user_id']]);
                    $client_data = $client_stmt->fetch();
                    if ($client_data) {
                        $client_name = $client_data['full_name'];
                    }
                    
                    notifyNewBooking($provider_id, $booking_id, [
                        'client_name' => $client_name,
                        'service_description' => $service_description
                    ]);
                } catch (Exception $e) {
                    error_log('Failed to create booking notification: ' . $e->getMessage());
                }
            } else {
                $booking_errors[] = "Failed to send booking request. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($provider['full_name']); ?> - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Provider Requirements CSS -->
    <link rel="stylesheet" href="../assets/css/provider-requirements.css">
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
            margin-left: 260px;
            padding: 1rem 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }
        
        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border-radius: 12px;
            padding: 2.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .profile-info {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 3rem;
            font-weight: bold;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-details {
            flex: 1;
            min-width: 300px;
        }
        
        .profile-details h1 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            font-size: 2.2rem;
        }
        
        .profile-title {
            font-size: 1.4rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }
        
        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.9;
        }
        
        .meta-item i {
            font-size: 1.1rem;
        }
        
        /* Rating Badge */
        .rating-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            min-width: 120px;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
        }
        
        .rating-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.1rem;
            margin: 0.5rem 0;
        }
        
        .rating-count {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Favorite Button */
        .favorite-form {
            margin: 0;
        }
        
        .btn-favorite {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            background: white;
            color: var(--danger);
            border: 2px solid var(--danger);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-favorite:hover {
            background: var(--danger);
            color: white;
            transform: scale(1.05);
        }
        
        .btn-favorite.active {
            background: var(--danger);
            color: white;
        }
        
        .btn-favorite.active:hover {
            background: #bb2d3b;
        }
        
        .btn-favorite i {
            font-size: 1.1rem;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .service-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .service-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.1);
        }
        
        .service-title {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            font-weight: 600;
        }
        
        .service-category {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .service-description {
            color: var(--secondary);
            line-height: 1.5;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        
        .service-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .service-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
        }
        
        .service-duration {
            color: var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Categories List */
        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .category-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #e0e7ff;
            color: var(--primary);
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Social Media Links */
        .social-links-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .social-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            border: 2px solid var(--social-color, #dee2e6);
            background: white;
            color: var(--social-color, var(--dark));
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .social-link-btn:hover {
            background: var(--social-color, #f0f0f0);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .social-link-btn i {
            font-size: 1.2rem;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .social-links-container {
                gap: 0.75rem;
            }
            
            .social-link-btn {
                flex: 1 1 calc(50% - 0.5rem);
                justify-content: center;
                padding: 0.6rem 0.75rem;
                font-size: 0.85rem;
            }
            
            .social-link-btn span {
                display: none;
            }
            
            .social-link-btn i {
                font-size: 1.5rem;
            }
        }
        
        /* Similar Providers Section */
        .similar-providers-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #e0e7ff;
        }
        
        .similar-providers-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.2rem;
        }
        
        .similar-provider-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .similar-provider-item:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1);
            transform: translateY(-2px);
        }
        
        .similar-header {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .similar-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .similar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .similar-info {
            flex: 1;
        }
        
        .similar-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }
        
        .similar-profession {
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .similar-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            padding: 0.75rem 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        
        .similar-stats .stat {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            flex: 1;
            justify-content: center;
        }
        
        .similar-stats .stat i {
            font-size: 0.95rem;
        }
        
        .similar-stats .stat span {
            font-weight: 600;
            color: var(--dark);
        }
        
        .similar-price {
            text-align: center;
            color: var(--success);
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }
        
        .similar-provider-item .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        
        /* Portfolio Section */
        .portfolio-section {
            margin: 1.5rem 0;
        }
        
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .portfolio-item {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
        }
        
        .portfolio-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .portfolio-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        
        .portfolio-content {
            padding: 1rem;
        }
        
        .portfolio-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .portfolio-description {
            color: var(--secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        /* ===== MODERN VIDEO SHOWCASE ===== */
        .video-showcase-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .video-showcase-container::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 1;
        }
        
        .video-showcase-container::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            z-index: 1;
        }
        
        .video-showcase-content {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .video-showcase-content {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
        
        .video-showcase-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .video-showcase-info p {
            font-size: 1rem;
            line-height: 1.6;
            opacity: 0.95;
            margin-bottom: 1rem;
        }
        
        .video-duration {
            display: inline-block;
            background: rgba(255, 255, 255, 0.25);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .video-player-wrapper {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            background: #000;
        }
        
        .video-player {
            width: 100%;
            height: 100%;
            display: block;
        }
        
        .video-player-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 10;
        }
        
        .video-player-wrapper:hover .video-player-overlay {
            opacity: 1;
            background: rgba(0, 0, 0, 0.2);
        }
        
        .play-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #667eea;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        .play-icon:hover {
            transform: scale(1.1);
            background: white;
        }
        
        .video-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.25rem;
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .video-badge i {
            font-size: 1.1rem;
        }
        
        .video-showcase-cta {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 0.75rem 1.75rem;
            border-radius: 25px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        
        .video-showcase-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            background: #f0f0f0;
        }
        
        /* Reviews Section */
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .rating-bar {
            flex: 1;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffc107, #ffb300);
        }
        
        .review-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .reviewer-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .reviewer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .review-stars {
            color: #ffc107;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .review-date {
            color: var(--secondary);
            font-size: 0.85rem;
        }
        
       /* Booking Form (non-sticky so schedule appears below it) */
.booking-form {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    position: static;
    top: auto;
    z-index: auto;
}
        
        /* Schedule & Availability Section */
.schedule-availability {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    position: static; /* keep it in normal flow below booking form */
    top: auto;
    margin-top: 1rem;
    z-index: auto;
}
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-box .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary);
            line-height: 1;
        }
        
        .stat-box .label {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        /* Availability Badge */
        .availability-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .availability-badge.available {
            background: #d1fae5;
            color: #065f46;
        }
        
        .availability-badge.busy {
            background: #fef3c7;
            color: #92400e;
        }
        
        .availability-badge.unavailable {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        /* Schedule & Availability Styles */
        .availability-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .availability-status.available {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .availability-status.busy {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .availability-status.unavailable {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .working-hours-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .working-hours-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .working-hours-table td {
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .time-off-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 4px;
            font-size: 0.8rem;
            margin: 0.125rem;
        }
        
        /* Schedule Card */
        .schedule-card {
            border-left: 4px solid var(--primary);
        }
        
        .next-available {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border: none;
        }
        
        /* Response Time Indicator */
        .response-indicator {
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .response-fill {
            height: 100%;
            border-radius: 4px;
        }
        
        /* Badges Grid Styles */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .badge-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            border-radius: 10px;
            text-align: center;
            color: white;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .badge-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .badge-item .badge-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .badge-item .badge-text {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .badge-item.bg-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .badge-item.bg-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .badge-item.bg-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .badge-item.bg-info {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }
        
        /* Payment Methods List */
        .payment-methods-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .payment-method-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            transition: background-color 0.3s;
        }
        
        .payment-method-item:hover {
            background-color: #f0f1f3;
        }
        
        .payment-method-item .method-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .payment-method-item .method-details {
            flex: 1;
        }
        
        .payment-method-item h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .payment-method-item p {
            margin: 0.25rem 0 0 0;
        }
        
        /* Service Areas List */
        .service-areas-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .service-area-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            transition: background-color 0.3s;
        }
        
        .service-area-item:hover {
            background-color: #f0f1f3;
        }
        
        .service-area-item .area-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .service-area-item .area-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .service-area-item .area-name i {
            color: var(--primary);
        }
        
        .service-area-item .area-coverage {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .service-area-item .area-districts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        
        .service-area-item .district-tag {
            display: inline-block;
            background: white;
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid var(--primary);
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
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .portfolio-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-form,
            .schedule-availability {
                position: static !important;
                top: auto !important;
                margin-top: 1.5rem !important;
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
        
        /* Verification Badges */
        .verification-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .badge-gold {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .badge-premium {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }
        
        .badge-featured {
            background: #fecaca;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1rem;
        }

        /* ===== NEGOTIATION DISPLAY STYLES ===== */
        .service-negotiable-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
            white-space: nowrap;
        }
        
        .service-price-range {
            font-size: 1.1rem;
            font-weight: 700;
            color: #667eea;
            margin: 0.5rem 0;
        }
        
        .price-range-label {
            font-size: 0.75rem;
            color: var(--secondary);
            display: block;
            margin-top: 0.25rem;
        }
        
        .offer-button-negotiable {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            width: 100%;
            margin-top: 1rem;
        }
        
        .offer-button-negotiable:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Right column — add vertical spacing and keep sections aligned */
.content-grid {
    align-items: start; /* ensure right column stacks at top */
}

/* Target right column (second child of .content-grid) */
.content-grid > div:nth-child(2) {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;           /* vertical space between stacked sections */
    padding-left: 0.75rem; /* a little breathing room from the column gap */
}

/* Make each major right-column block use consistent padding and stop double margins */
.content-grid > div:nth-child(2) .booking-form,
.content-grid > div:nth-child(2) .schedule-availability,
.content-grid > div:nth-child(2) .stats-grid,
.content-grid > div:nth-child(2) .card {
    margin-bottom: 0;
    padding: 1.25rem;
    box-sizing: border-box;
}

/* Slightly increase booking form spacing for readability */
.content-grid > div:nth-child(2) .booking-form {
    padding: 1.5rem;
}

/* Ensure mobile keeps default stacked spacing (already handled elsewhere) */
@media (max-width: 992px) {
    .content-grid > div:nth-child(2) {
        padding-left: 0;
        gap: 1rem;
    }
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

    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php 
                        $profile_image = $provider['profile_image'] ?? '';
                        $initials = strtoupper(substr($provider['full_name'] ?? 'U', 0, 1));
                        if (!empty($profile_image)): 
                    ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" alt="<?php echo htmlspecialchars($provider['full_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo addslashes($initials); ?>'; this.parentNode.style.color='white'; this.parentNode.style.fontSize='3rem'; this.parentNode.style.fontWeight='bold';">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                
                <div class="profile-details">
                    <h1><?php echo htmlspecialchars($provider['full_name']); ?></h1>
                    <p class="profile-title"><?php echo htmlspecialchars($provider['profession']); ?></p>
                    
                    <div class="profile-meta">
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>
                                <?php echo htmlspecialchars($provider['location']); ?>
                                <?php if ($provider['district']): ?>, <?php echo htmlspecialchars($provider['district']); ?><?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($provider['experience_years']): ?>
                        <div class="meta-item">
                            <i class="fas fa-briefcase"></i>
                            <span><?php echo $provider['experience_years']; ?> years experience</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Member since <?php echo date('M Y', strtotime($provider['member_since'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <span class="availability-badge <?php echo $provider['availability']; ?>">
                            <?php echo ucfirst($provider['availability']); ?>
                        </span>
                        
                        <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                            <span class="badge-<?php echo $provider['verification_level']; ?> verification-badge">
                                <i class="fas fa-shield-alt me-1"></i><?php echo ucfirst($provider['verification_level']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($provider['is_featured']): ?>
                            <span class="badge-featured verification-badge">
                                <i class="fas fa-star me-1"></i>Featured
                            </span>
                        <?php endif; ?>
                        
                        <!-- Profile Completion Badge -->
                        <?php 
                            $completion = $requirements->getCompletedCount();
                            $completion_pct = $requirements->getCompletionPercentage();
                            $is_complete = $requirements->isComplete();
                            $badge_class = $is_complete ? 'complete' : ($completion_pct >= 60 ? 'partial' : 'incomplete');
                        ?>
                        <span class="profile-completion-badge <?php echo $badge_class; ?>" title="Profile <?php echo $completion_pct; ?>% Complete">
                            <i class="fas <?php echo $is_complete ? 'fa-check-circle' : 'fa-info-circle'; ?>"></i>
                            <?php echo $completion['completed']; ?>/<?php echo $completion['total']; ?> Requirements
                        </span>
                    </div>
                </div>
                
                <div class="rating-badge">
                    <div class="rating-number"><?php echo number_format($provider['average_rating'], 1); ?></div>
                    <div class="rating-stars">
                        <?php 
                        $rating = $provider['average_rating'];
                        for ($i = 1; $i <= 5; $i++): 
                            if ($i <= $rating): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif;
                        endfor; ?>
                    </div>
                    <div class="rating-count"><?php echo $provider['total_reviews']; ?> reviews</div>
                    
                    <!-- Add to Favorites Button -->
                    <form method="POST" class="favorite-form mt-2">
                        <button type="submit" name="toggle_favorite" class="btn-favorite <?php echo $is_favorite ? 'active' : ''; ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                            <span><?php echo $is_favorite ? 'Favorited' : 'Add to Favorites'; ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($booking_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $booking_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($booking_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($booking_errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
                        <!-- Left Column -->
            <div>
                <!-- About Section -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user me-2"></i> About</h3>
                    </div>
                    <p class="lh-lg">
                        <?php echo nl2br(htmlspecialchars($provider['bio'] ?: 'No description provided yet.')); ?>
                    </p>
                </div>

                <!-- SCHEDULE & AVAILABILITY SECTION - MOVED FROM RIGHT COLUMN -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt me-2"></i> Schedule & Availability</h3>
                    </div>
                    <div class="card-body">
                        <!-- Next Available Date -->
                        <?php if ($next_available_date): ?>
                            <div class="alert next-available mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-check fa-2x me-3 text-success"></i>
                                    <div>
                                        <strong>Next Available:</strong><br>
                                        <?php echo date('l, F j, Y', strtotime($next_available_date)); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Working Hours -->
                        <?php if ($schedule_info['working_hours_start'] && $schedule_info['working_hours_end']): ?>
                            <div class="mb-3">
                                <h5 class="mb-2">Regular Working Hours</h5>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Days:</span>
                                    <strong><?php echo implode(', ', $formatted_working_days); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Hours:</span>
                                    <strong>
                                        <?php echo date('g:i A', strtotime($schedule_info['working_hours_start'])); ?> - 
                                        <?php echo date('g:i A', strtotime($schedule_info['working_hours_end'])); ?>
                                    </strong>
                                </div>
                                <?php if ($schedule_info['break_start'] && $schedule_info['break_end']): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Break Time:</span>
                                        <strong class="text-warning">
                                            <?php echo date('g:i A', strtotime($schedule_info['break_start'])); ?> - 
                                            <?php echo date('g:i A', strtotime($schedule_info['break_end'])); ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($schedule_info['buffer_time']): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Buffer Between Appointments:</span>
                                        <strong><?php echo $schedule_info['buffer_time']; ?> minutes</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Response Time -->
                        <?php if ($avg_response_time && $total_responses >= 3): ?>
                            <div class="mb-3">
                                <h5 class="mb-2">Response Time</h5>
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="response-indicator">
                                            <?php 
                                            $response_score = min(100, max(0, 100 - ($avg_response_time * 2)));
                                            $color = $response_score >= 80 ? 'bg-success' : ($response_score >= 60 ? 'bg-warning' : 'bg-danger');
                                            ?>
                                            <div class="response-fill <?php echo $color; ?>" style="width: <?php echo $response_score; ?>%"></div>
                                        </div>
                                    </div>
                                    <span class="ms-2 fw-bold">
                                        <?php 
                                        if ($avg_response_time < 1) {
                                            echo "Within " . ceil($avg_response_time * 60) . " minutes";
                                        } elseif ($avg_response_time < 24) {
                                            echo ceil($avg_response_time) . " hours";
                                        } else {
                                            echo ceil($avg_response_time / 24) . " days";
                                        }
                                        ?>
                                    </span>
                                </div>
                                <small class="text-muted">Average response time based on <?php echo $total_responses; ?> bookings</small>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upcoming Time Off -->
                        <?php if (!empty($time_off_periods)): ?>
                            <div class="mb-3">
                                <h5 class="mb-2">Upcoming Time Off</h5>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($time_off_periods as $time_off): ?>
                                        <div class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-calendar-times text-danger me-2"></i>
                                                    <?php echo date('M d', strtotime($time_off['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($time_off['end_date'])); ?>
                                                </span>
                                                <small class="text-muted"><?php echo htmlspecialchars($time_off['reason']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Availability Exceptions -->
                        <?php if (!empty($availability_exceptions)): ?>
                            <div>
                                <h5 class="mb-2">Special Availability</h5>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($availability_exceptions as $exception): ?>
                                        <div class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas <?php echo $exception['is_available'] ? 'fa-calendar-check text-success' : 'fa-calendar-times text-danger'; ?> me-2"></i>
                                                    <?php echo date('D, M d', strtotime($exception['date'])); ?>
                                                </span>
                                                <?php if ($exception['is_available'] && $exception['start_time'] && $exception['end_time']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($exception['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($exception['end_time'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($exception['notes'])): ?>
                                                <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($exception['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Service Options -->
                        <?php if ($visibility['emergency_service'] || $visibility['night_service'] || $visibility['weekend_service']): ?>
                        <div class="mb-3">
                            <h5 class="mb-2">Available Service Options</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($visibility['emergency_service']): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-ambulance me-1"></i> 24/7 Emergency Service
                                    </span>
                                <?php endif; ?>
                                <?php if ($visibility['night_service']): ?>
                                    <span class="badge bg-info">
                                        <i class="fas fa-moon me-1"></i> Night Service
                                    </span>
                                <?php endif; ?>
                                <?php if ($visibility['weekend_service']): ?>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-calendar-alt me-1"></i> Weekend Service
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Legend -->
                        <div class="mt-3 pt-3 border-top">
                            <small class="text-muted d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                Schedule details are based on provider's settings and may be subject to change.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Video Showcase Section -->
                <?php if ($portfolio_video): ?>
                <div class="video-showcase-container">
                    <div class="video-showcase-content">
                        <div class="video-showcase-info">
                            <div class="video-badge">
                                <i class="fas fa-film"></i>
                                Work Sample
                            </div>
                            <h3>Check Out My Work</h3>
                            <p>
                                Watch this portfolio video to see 
                                <?php echo htmlspecialchars($provider['full_name']); ?>'s skills and 
                                craftsmanship in action.
                            </p>
                            <?php if (!empty($portfolio_video['title'])): ?>
                                <p style="font-weight: 600; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($portfolio_video['title']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($portfolio_video['description'])): ?>
                                <p style="font-size: 0.95rem; opacity: 0.9;">
                                    <?php echo htmlspecialchars($portfolio_video['description']); ?>
                                </p>
                            <?php endif; ?>
                            <button class="video-showcase-cta" onclick="document.querySelector('.video-player').play()">
                                <i class="fas fa-play me-2"></i> Watch Video
                            </button>
                        </div>
                        
                        <div class="video-player-wrapper">
                            <video 
                                class="video-player" 
                                controls 
                                poster="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 675'%3E%3Crect fill='%23000' width='1200' height='675'/%3E%3Ccircle cx='600' cy='337.5' r='80' fill='%23fff' opacity='0.3'/%3E%3Cpolygon points='570,310 570,365 640,337.5' fill='%23fff'/%3E%3C/svg%3E">
                                <source src="../uploads/portfolio/<?php echo htmlspecialchars($portfolio_video['video_path']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            <div class="video-player-overlay">
                                <div class="play-icon">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Portfolio Section -->
                <?php if (!empty($portfolio_images)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-images me-2"></i> Portfolio (<?php echo $portfolio_count; ?>)</h3>
                    </div>
                    <div class="portfolio-section">
                        <p class="text-muted mb-3">View <?php echo htmlspecialchars($provider['full_name']); ?>'s previous work and completed projects.</p>
                        
                        <div class="portfolio-grid">
                            <?php foreach ($portfolio_images as $image): ?>
                                <div class="portfolio-item">
                                    <img src="../uploads/portfolio/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         class="portfolio-image" 
                                         alt="<?php echo htmlspecialchars($image['title'] ?: 'Portfolio Image'); ?>"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#portfolioModal"
                                         onclick="openPortfolioImage(this, '<?php echo htmlspecialchars($image['title'] ?: ''); ?>', '<?php echo htmlspecialchars($image['description'] ?: ''); ?>')"
                                         onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200/cccccc/969696?text=Image+Not+Available'">
                                    
                                    <?php if (!empty($image['title']) || !empty($image['description'])): ?>
                                    <div class="portfolio-content">
                                        <?php if (!empty($image['title'])): ?>
                                            <h5 class="portfolio-title"><?php echo htmlspecialchars($image['title']); ?></h5>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($image['description'])): ?>
                                            <p class="portfolio-description"><?php echo nl2br(htmlspecialchars($image['description'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($portfolio_count > 6): ?>
                            <div class="text-center mt-3">
                                <a href="portfolio.php?id=<?php echo $provider_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-images me-2"></i> View Full Portfolio (<?php echo $portfolio_count; ?> images)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Services Section -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-concierge-bell me-2"></i> Services (<?php echo count($services); ?>)</h3>
                    </div>
                    
                    <?php if (!empty($services)): ?>
                        <div class="services-grid">
                            <?php foreach ($services as $service): ?>
                                <div class="service-card" role="button" data-service-id="<?php echo $service['id']; ?>" data-negotiable="<?php echo $service['negotiable'] ? '1' : '0'; ?>" onclick="navigateToService(event, this)">
                                    <div>
                                        <h4 class="service-title">
                                            <?php echo htmlspecialchars($service['name']); ?>
                                            <?php if ($service['negotiable']): ?>
                                                <span class="service-negotiable-badge">
                                                    <i class="fas fa-handshake me-1"></i> Negotiable
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                    </div>
                                    
                                    <div class="service-category">
                                        <i class="fas <?php echo $service['category_icon']; ?>"></i>
                                        <?php echo htmlspecialchars($service['category_name']); ?>
                                    </div>
                                    
                                    <p class="service-description">
                                        <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                                    </p>
                                    
                                    <div class="service-footer">
                                        <div>
                                            <?php if ($service['negotiable'] && $service['min_price'] && $service['max_price']): ?>
                                                <div class="service-price-range">
                                                    RWF <?php echo number_format($service['min_price'], 0); ?> - RWF <?php echo number_format($service['max_price'], 0); ?>
                                                </div>
                                                <span class="price-range-label">
                                                    <i class="fas fa-info-circle me-1"></i> Negotiable range
                                                </span>
                                            <?php else: ?>
                                                <div class="service-price">
                                                    RWF <?php echo number_format($service['price'], 0); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="service-duration">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo $service['duration']; ?> mins
                                        </div>
                                    </div>

                                    <?php if ($service['negotiable'] && isLoggedIn() && !isProvider()): ?>
                                        <button type="button" 
                                                class="btn offer-button-negotiable"
                                                data-service-id="<?php echo $service['id']; ?>"
                                                data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                data-min-price="<?php echo $service['min_price']; ?>"
                                                data-max-price="<?php echo $service['max_price']; ?>"
                                                data-base-price="<?php echo $service['price']; ?>"
                                                onclick="openOfferModal(event, this)">
                                            <i class="fas fa-handshake me-2"></i> Send Offer
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-concierge-bell"></i>
                            <h4>No Services Available</h4>
                            <p>This provider hasn't added any services yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Service Categories -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-briefcase me-2"></i> Service Categories</h3>
                    </div>
                    <div class="categories-list">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-badge">
                                <i class="fas <?php echo $category['icon']; ?>"></i>
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Verification Badges -->
                <?php if (!empty($badges)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-certificate me-2"></i> Achievements & Badges</h3>
                    </div>
                    <div class="badges-grid">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-item bg-<?php echo $badge['color']; ?>">
                                <div class="badge-icon">
                                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                                </div>
                                <div class="badge-text">
                                    <?php echo htmlspecialchars($badge['name']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Methods -->
                <?php if (!empty($paymentMethods)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-credit-card me-2"></i> Payment Methods</h3>
                    </div>
                    <div class="payment-methods-list">
                        <?php foreach ($paymentMethods as $method): ?>
                            <div class="payment-method-item">
                                <div class="method-icon">
                                    <?php 
                                        $methodIcons = [
                                            'cash' => 'fa-money-bill-wave',
                                            'mobile_money' => 'fa-mobile-alt',
                                            'bank_transfer' => 'fa-university',
                                            'card' => 'fa-credit-card',
                                            'momo' => 'fa-money-bill',
                                            'orange_money' => 'fa-circle',
                                            'airtel_money' => 'fa-circle'
                                        ];
                                        $icon = $methodIcons[$method['method_type']] ?? 'fa-wallet';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="method-details">
                                    <h5><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $method['method_type']))); ?></h5>
                                </div>
                                <?php if ($method['is_default']): ?>
                                    <span class="badge bg-primary ms-auto">Default</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Service Areas -->
                <?php if (!empty($serviceAreas)): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3><i class="fas fa-map-marked-alt me-2"></i> Service Areas</h3>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="openLargeMapBtn">
                                <i class="fas fa-expand"></i> View large map
                            </button>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="showPrimaryToggle">
                                <label class="form-check-label" for="showPrimaryToggle">Show only primary area</label>
                            </div>
                        </div>
                    </div>
                    <div class="service-areas-list">
                                <?php foreach ($serviceAreas as $area): ?>
                            <div class="service-area-item" data-is-primary="<?php echo !empty($area['is_primary']) ? '1' : '0'; ?>">
                                <div class="area-header">
                                    <div class="area-name">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($area['area_name'] ?? 'Unnamed Area'); ?></span>
                                    </div>
                                    <?php if (!empty($area['is_primary'])): ?>
                                        <span class="badge bg-success">Primary</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($area['radius_km'])): ?>
                                    <p class="area-coverage text-muted mb-2">
                                        <i class="fas fa-expand-alt"></i> Coverage: <?php echo htmlspecialchars($area['radius_km']); ?> km radius
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($area['districts'])): ?>
                                    <div class="area-districts">
                                        <?php 
                                            $districts = explode(',', $area['districts']);
                                            foreach ($districts as $district): 
                                        ?>
                                            <span class="district-tag"><?php echo htmlspecialchars(trim($district)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="serviceAreaMap" style="height: 320px; border: 1px solid #dee2e6; border-radius: 8px; margin-top: 1rem;"></div>

                    <!-- Large Map Modal -->
                    <div class="modal fade" id="largeMapModal" tabindex="-1" aria-labelledby="largeMapModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="largeMapModalLabel">Service Areas (Large View)</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" style="padding: 0;">
                                    <div id="serviceAreaMapLarge" style="height: 80vh; min-height: 500px; width: 100%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Right Column -->
            <div>
                    <!-- Booking CTA (redirects to booking page) -->
                    <div class="card booking-cta-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-check me-2"></i> Book This Provider</h3>
                        </div>
                        <div class="card-body text-center">
                            <p class="mb-3 text-muted">To make a booking request, go to the full booking flow where you can select service, date and provide details.</p>
                            <a href="booking.php?provider_id=<?php echo $provider_id; ?><?php echo !empty($share_id) ? '&share_id=' . intval($share_id) : ''; ?>" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-arrow-right me-2"></i> Continue to Booking
                            </a>
                            <div class="mt-3 text-start">
                                <h5 class="h6">Contact</h5>
                                <?php if ($visibility['show_phone']): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-phone text-primary"></i>
                                        <span><?php echo htmlspecialchars($provider['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas fa-envelope text-primary"></i>
                                    <span><?php echo htmlspecialchars($provider['email']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Reviews Section (moved from left column) -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-star me-2"></i> Reviews (<?php echo count($reviews); ?>)</h3>
                    </div>

                    <?php if (!empty($reviews)): ?>
                        <!-- Rating Breakdown -->
                        <div class="bg-light rounded p-3 mb-4">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <div class="rating-bar-item">
                                    <span class="text-muted" style="width: 80px;"><?php echo $i; ?> stars</span>
                                    <div class="rating-bar">
                                        <?php 
                                        $percentage = $provider['total_reviews'] > 0 
                                            ? ($rating_breakdown[$i] / $provider['total_reviews']) * 100 
                                            : 0;
                                        ?>
                                        <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                    <span class="text-muted text-end" style="width: 40px;"><?php echo $rating_breakdown[$i]; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Review Items -->
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <div class="reviewer-avatar">
                                            <?php 
                                                $client_image = $review['profile_image'] ?? '';
                                                $client_initials = strtoupper(substr($review['client_name'] ?? 'C', 0, 1));
                                                if (!empty($client_image)): 
                                            ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($client_image); ?>" alt="<?php echo htmlspecialchars($review['client_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo addslashes($client_initials); ?>'; this.parentNode.style.color='white'; this.parentNode.style.fontSize='1rem'; this.parentNode.style.fontWeight='bold';">
                                            <?php else: ?>
                                                <?php echo $client_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                                            <div class="review-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $review['rating']): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="review-date">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="mb-0">
                                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h4>No Reviews Yet</h4>
                            <p>Be the first to review this provider!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Similar Providers Section -->
                <?php if (!empty($similar_providers)): ?>
                <div class="card similar-providers-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users me-2"></i> Similar Providers</h3>
                    </div>
                    <div class="similar-providers-list">
                        <?php foreach ($similar_providers as $similar): ?>
                            <div class="similar-provider-item">
                                <div class="similar-header">
                                    <div class="similar-avatar">
                                        <?php 
                                            $similar_image = $similar['profile_image'] ?? '';
                                            $similar_initial = strtoupper(substr($similar['full_name'] ?? '', 0, 1)) ?: '?';
                                        ?>
                                        <?php if (!empty($similar_image)): ?>
                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($similar_image); ?>" alt="<?php echo htmlspecialchars($similar['full_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($similar_initial); ?>';">
                                        <?php else: ?>
                                            <?php echo $similar_initial; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="similar-info">
                                        <div class="similar-name">
                                            <?php echo htmlspecialchars($similar['full_name']); ?>
                                            <?php if ($similar['is_verified'] || $similar['user_verified']): ?>
                                                <i class="fas fa-check-circle text-success ms-1" style="font-size: 0.8rem;" title="Verified"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="similar-profession"><?php echo htmlspecialchars($similar['profession']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="similar-stats">
                                    <div class="stat">
                                        <i class="fas fa-star text-warning"></i>
                                        <span><?php echo number_format($similar['average_rating'], 1); ?></span>
                                    </div>
                                    <div class="stat">
                                        <i class="fas fa-comments text-info"></i>
                                        <span><?php echo $similar['total_reviews']; ?></span>
                                    </div>
                                    <div class="stat">
                                        <i class="fas fa-briefcase text-primary"></i>
                                        <span><?php echo $similar['experience_years'] ?? 0; ?> yrs</span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($similar['avg_service_price'])): ?>
                                    <div class="similar-price">
                                        From RWF <?php echo number_format($similar['avg_service_price'], 0); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="provider-profile.php?id=<?php echo $similar['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-eye me-1"></i> View Profile
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                
                <!-- CONNECT SECTION - MOVED FROM LEFT COLUMN -->
                <?php if ($active_links > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-share-alt me-2"></i> Connect</h3>
                    </div>
                    <div class="social-links-container">
                        <?php foreach ($social_links as $key => $link): ?>
                            <?php 
                            // Check visibility setting for WhatsApp
                            if ($key === 'whatsapp' && !$visibility['show_whatsapp']) {
                                continue;
                            }
                            ?>
                            <?php if (!empty($provider[$link['field']])): ?>
                                <a href="<?php 
                                    $url = $provider[$link['field']];
                                    // Add protocol if missing for non-social URLs
                                    if ($key === 'website' && !preg_match('~^https?://~i', $url)) {
                                        $url = 'https://' . $url;
                                    } elseif ($key === 'whatsapp' && !preg_match('~^https?://~i', $url)) {
                                        // WhatsApp link format
                                        $phone = preg_replace('/[^0-9]/', '', $url);
                                        $url = 'https://wa.me/' . $phone;
                                    }
                                    echo htmlspecialchars($url);
                                ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="social-link-btn" 
                                   title="Visit on <?php echo $link['label']; ?>"
                                   style="--social-color: <?php echo $link['color']; ?>">
                                    <i class="<?php echo $link['icon']; ?>"></i>
                                    <span><?php echo $link['label']; ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Custom Social Media -->
                        <?php if (!empty($provider['other_social'])): ?>
                            <a href="<?php 
                                $url = $provider['other_social'];
                                if (!preg_match('~^https?://~i', $url)) {
                                    $url = 'https://' . $url;
                                }
                                echo htmlspecialchars($url);
                            ?>" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="social-link-btn" 
                               title="Visit <?php echo htmlspecialchars($provider['other_social_label'] ?? 'this link'); ?>"
                               style="--social-color: #6c757d">
                                <i class="fas fa-link"></i>
                                <span><?php echo htmlspecialchars($provider['other_social_label'] ?? 'More'); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="number"><?php echo $provider['total_reviews']; ?></div>
                        <div class="label">Total Reviews</div>
                    </div>
                    <div class="stat-box">
                        <div class="number"><?php echo $total_completed_jobs; ?></div>
                        <div class="label">Jobs Completed</div>
                    </div>
                    <div class="stat-box">
                        <div class="number"><?php echo count($services); ?></div>
                        <div class="label">Services Offered</div>
                    </div>
                    <?php if ($avg_service_price > 0): ?>
                        <div class="stat-box">
                            <div class="number">RWF <?php echo number_format($avg_service_price, 0); ?></div>
                            <div class="label">Avg Service Price</div>
                        </div>
                    <?php else: ?>
                        <div class="stat-box">
                            <div class="number"><?php echo $total_bookings; ?></div>
                            <div class="label">Total Bookings</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>



              

                

    <!-- Portfolio Modal -->
    <div class="modal fade" id="portfolioModal" tabindex="-1" aria-labelledby="portfolioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="portfolioModalLabel">Portfolio Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPortfolioImage" src="" alt="" class="img-fluid rounded" style="max-height: 60vh; object-fit: contain;">
                    <div id="modalPortfolioInfo" class="mt-3 text-start"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Sidebar collapse toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const clientSidebar = document.getElementById('clientSidebar');
        
        if (sidebarToggle && clientSidebar) {
            // Load sidebar state from localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                clientSidebar.classList.add('collapsed');
            }
            
            // Toggle sidebar on button click
            sidebarToggle.addEventListener('click', () => {
                clientSidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', clientSidebar.classList.contains('collapsed'));
            });
        }

        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (mobileToggle && sidebar && overlay) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
            });
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
        }

        // Service Area Map
        const providerServiceAreas = <?php echo json_encode($serviceAreas); ?>;
        let serviceAreaMap;
        let serviceAreaLayerGroup;
        let largeServiceAreaMap;
        let largeServiceAreaLayerGroup;

        function renderServiceAreaMapInto(targetMap, targetLayerGroup, showOnlyPrimary = false) {
            if (!targetMap || !Array.isArray(providerServiceAreas) || providerServiceAreas.length === 0) {
                return;
            }

            if (!targetLayerGroup) {
                targetLayerGroup = L.layerGroup().addTo(targetMap);
            } else {
                targetLayerGroup.clearLayers();
            }

            const bounds = L.latLngBounds();
            let added = false;

            providerServiceAreas.forEach(area => {
                const lat = parseFloat(area.latitude);
                const lng = parseFloat(area.longitude);
                const isPrimary = parseInt(area.is_primary || 0, 10) === 1;

                if (showOnlyPrimary && !isPrimary) {
                    return;
                }

                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    const radiusKm = parseFloat(area.radius_km) || 10;
                    L.circle([lat, lng], {
                        color: isPrimary ? '#007bff' : '#28a745',
                        fillColor: isPrimary ? '#3388ff' : '#33cc33',
                        fillOpacity: 0.2,
                        radius: radiusKm * 1000
                    }).addTo(targetLayerGroup);

                    const marker = L.marker([lat, lng]).addTo(targetLayerGroup);
                    marker.bindPopup(`<strong>${area.area_name || 'Service Area'}</strong><br>Radius: ${radiusKm} km${isPrimary ? '<br><span style="color:#007bff;font-weight:bold;">Primary</span>' : ''}`);

                    bounds.extend([lat, lng]);
                    added = true;
                }
            });

            if (added && bounds.isValid()) {
                if (bounds.getNorthEast().equals(bounds.getSouthWest())) {
                    targetMap.setView(bounds.getCenter(), 12);
                } else {
                    targetMap.fitBounds(bounds.pad(0.2));
                }
            }

            // Keep caller's layer group reference in sync (for large map separate variable)
            if (targetMap === serviceAreaMap) {
                serviceAreaLayerGroup = targetLayerGroup;
            } else if (targetMap === largeServiceAreaMap) {
                largeServiceAreaLayerGroup = targetLayerGroup;
            }

            return targetLayerGroup;
        }

        function renderServiceAreaMap(showOnlyPrimary = false) {
            serviceAreaLayerGroup = renderServiceAreaMapInto(serviceAreaMap, serviceAreaLayerGroup, showOnlyPrimary);
        }

        function renderLargeServiceAreaMap(showOnlyPrimary = false) {
            if (!largeServiceAreaMap) {
                return;
            }
            largeServiceAreaLayerGroup = renderServiceAreaMapInto(largeServiceAreaMap, largeServiceAreaLayerGroup, showOnlyPrimary);
            largeServiceAreaMap.invalidateSize();
        }

        function initializeLargeServiceAreaMap(showOnlyPrimary = false) {
            const largeMapContainer = document.getElementById('serviceAreaMapLarge');
            if (!largeMapContainer || !Array.isArray(providerServiceAreas) || providerServiceAreas.length === 0) {
                return;
            }

            if (!largeServiceAreaMap) {
                const firstArea = providerServiceAreas[0];
                const defaultLat = parseFloat(firstArea.latitude) || -1.9441;
                const defaultLng = parseFloat(firstArea.longitude) || 30.0619;

                largeServiceAreaMap = L.map('serviceAreaMapLarge').setView([defaultLat, defaultLng], 11);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(largeServiceAreaMap);
            }

            renderLargeServiceAreaMap(showOnlyPrimary);
            setTimeout(() => {
                largeServiceAreaMap.invalidateSize();
            }, 200);
        }

        function initializeServiceAreaMap() {
            const mapContainer = document.getElementById('serviceAreaMap');
            if (!mapContainer || !Array.isArray(providerServiceAreas) || providerServiceAreas.length === 0) {
                return;
            }

            const firstArea = providerServiceAreas[0];
            const defaultLat = parseFloat(firstArea.latitude) || -1.9441;
            const defaultLng = parseFloat(firstArea.longitude) || 30.0619;

            serviceAreaMap = L.map('serviceAreaMap').setView([defaultLat, defaultLng], 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(serviceAreaMap);

            const toggle = document.getElementById('showPrimaryToggle');
            const persistedPrimaryOnly = localStorage.getItem('showOnlyPrimaryArea') === 'true';

            if (toggle) {
                toggle.checked = persistedPrimaryOnly;
            }

            renderServiceAreaMap(persistedPrimaryOnly);

            const applyToggleFilter = (showOnlyPrimary) => {
                renderServiceAreaMap(showOnlyPrimary);
                renderLargeServiceAreaMap(showOnlyPrimary);

                document.querySelectorAll('.service-area-item').forEach(item => {
                    const isPrimary = item.getAttribute('data-is-primary') === '1';
                    item.style.display = showOnlyPrimary && !isPrimary ? 'none' : 'block';
                });
            };

            if (toggle) {
                toggle.addEventListener('change', function() {
                    localStorage.setItem('showOnlyPrimaryArea', this.checked);
                    applyToggleFilter(this.checked);
                });
            }

            applyToggleFilter(persistedPrimaryOnly);

            const openLargeMapBtn = document.getElementById('openLargeMapBtn');
            if (openLargeMapBtn) {
                openLargeMapBtn.addEventListener('click', function() {
                    const showOnlyPrimary = toggle ? toggle.checked : false;
                    initializeLargeServiceAreaMap(showOnlyPrimary);

                    const largeMapModalEl = document.getElementById('largeMapModal');
                    const largeMapModal = new bootstrap.Modal(largeMapModalEl);
                    largeMapModal.show();

                    largeMapModalEl.addEventListener('shown.bs.modal', () => {
                        if (largeServiceAreaMap) {
                            largeServiceAreaMap.invalidateSize();
                        }
                    }, { once: true });
                });
            }
        }

        window.addEventListener('load', initializeServiceAreaMap);

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation for booking
        document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
            const serviceDescriptionField = document.querySelector('textarea[name="service_description"]');
            const preferredDate = document.querySelector('input[name="preferred_date"]');
            const preferredTime = document.querySelector('input[name="preferred_time"]');
            const serviceDescription = serviceDescriptionField ? serviceDescriptionField.value.trim() : '';

            if (!serviceDescriptionField || !serviceDescription) {
                e.preventDefault();
                alert('Please provide a service description');
                serviceDescriptionField?.focus();
                return false;
            }

            if (!preferredDate || !preferredDate.value) {
                e.preventDefault();
                alert('Please select a preferred date');
                preferredDate?.focus();
                return false;
            }
            
            // Validate date is not in the past
            const selectedDate = new Date(preferredDate.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('Please select a date in the future');
                preferredDate.focus();
                return false;
            }
            
            // Check if selected day is a working day
            const dayOfWeek = selectedDate.getDay(); // 0=Sunday, 1=Monday, etc.
            // Adjust for PHP day numbers (1=Monday, 7=Sunday)
            const phpDayOfWeek = dayOfWeek === 0 ? 7 : dayOfWeek;
            
            // Get working days from data attribute
            const workingDaysStr = this.dataset.workingDays;
            const workingDays = workingDaysStr ? workingDaysStr.split(',') : ['1','2','3','4','5'];
            
            if (!workingDays.includes(phpDayOfWeek.toString())) {
                const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                alert(`Provider is not available on ${dayNames[dayOfWeek]}. Please select a different day.`);
                preferredDate.focus();
                return false;
            }
            
            // Validate time if provided
            const preferredTimeValue = document.querySelector('input[name="preferred_time"]').value;
            if (preferredTimeValue) {
                const time = preferredTimeValue.split(':');
                const hours = parseInt(time[0]);
                const minutes = parseInt(time[1]);
                
                // Check if time is within working hours
                const startTime = '<?php echo $schedule_info['working_hours_start'] ?? "08:00"; ?>';
                const endTime = '<?php echo $schedule_info['working_hours_end'] ?? "17:00"; ?>';
                
                const startParts = startTime.split(':');
                const endParts = endTime.split(':');
                
                const startHour = parseInt(startParts[0]);
                const startMinute = parseInt(startParts[1] || 0);
                const endHour = parseInt(endParts[0]);
                const endMinute = parseInt(endParts[1] || 0);
                
                const selectedTimeInMinutes = hours * 60 + minutes;
                const startTimeInMinutes = startHour * 60 + startMinute;
                const endTimeInMinutes = endHour * 60 + endMinute;
                
                if (selectedTimeInMinutes < startTimeInMinutes || selectedTimeInMinutes > endTimeInMinutes) {
                    e.preventDefault();
                    alert(`Provider's working hours are ${formatTime(startTime)} to ${formatTime(endTime)}. Please select a time within this range.`);
                    document.querySelector('input[name="preferred_time"]').focus();
                    return false;
                }
            }
        });

        // Helper function to format time
        function formatTime(timeStr) {
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours);
            const minute = minutes || '00';
            const period = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minute} ${period}`;
        }

        // Set minimum date to today for date input
        const preferredDateInput = document.querySelector('input[name="preferred_date"]');
        if (preferredDateInput) {
            preferredDateInput.min = new Date().toISOString().split('T')[0];
            preferredDateInput.addEventListener('change', updateTimeSuggestions);
        }

        // Auto-select service from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const serviceId = urlParams.get('service_id');
        if (serviceId) {
            const serviceSelect = document.getElementById('serviceSelect');
            if (serviceSelect) {
                serviceSelect.value = serviceId;
            }
        }

        // Handle service selection - check if negotiable
        document.getElementById('serviceSelect').addEventListener('change', function() {
            if (!this.value) return;
            
            const selectedOption = this.options[this.selectedIndex];
            const isNegotiable = selectedOption.getAttribute('data-negotiable') === '1';
            
            if (isNegotiable) {
                // Service supports negotiation - show offer modal instead of booking form
                const serviceName = selectedOption.getAttribute('data-name');
                const minPrice = parseFloat(selectedOption.getAttribute('data-min-price'));
                const maxPrice = parseFloat(selectedOption.getAttribute('data-max-price'));
                const basePrice = parseFloat(selectedOption.getAttribute('data-base-price'));
                
                // Create button element to trigger offer modal
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.serviceId = this.value;
                btn.dataset.serviceName = serviceName;
                btn.dataset.minPrice = minPrice;
                btn.dataset.maxPrice = maxPrice;
                btn.dataset.basePrice = basePrice;
                
                // Show offer modal for negotiable service
                openOfferModal({preventDefault: () => {}}, btn);
                
                // Reset the select dropdown
                this.value = '';
            } else {
                // Non-negotiable service - show normal booking form, focus on description
                const descriptionField = document.querySelector('textarea[name="service_description"]');
                if (descriptionField) {
                    descriptionField.focus();
                }
            }
        });

        // Show/hide time suggestions based on provider's working hours
        function updateTimeSuggestions() {
            const dateInput = document.querySelector('input[name="preferred_date"]');
            const timeInput = document.querySelector('input[name="preferred_time"]');
            
            if (dateInput.value && timeInput) {
                // Could add logic here to suggest times based on provider's schedule
                // For now, just enable the time input
                timeInput.disabled = false;
            }
        }

        const preferredDateInput2 = document.querySelector('input[name="preferred_date"]');
        if (preferredDateInput2) {
            preferredDateInput2.addEventListener('change', updateTimeSuggestions);
        }

        // Portfolio modal functionality
        function openPortfolioImage(imgElement, title, description) {
            const modalImage = document.getElementById('modalPortfolioImage');
            const modalInfo = document.getElementById('modalPortfolioInfo');
            
            modalImage.src = imgElement.src;
            modalImage.alt = title || 'Portfolio Image';
            
            let infoHTML = '';
            if (title) {
                infoHTML += `<h5>${title}</h5>`;
            }
            if (description) {
                infoHTML += `<p class="mb-0">${description}</p>`;
            }
            
            modalInfo.innerHTML = infoHTML;
        }

        function navigateToService(event, card) {
            if (event.target.closest('.offer-button-negotiable')) {
                return;
            }
            const serviceId = card.dataset.serviceId;
            if (!serviceId) {
                return;
            }
            window.location.href = 'service.php?service_id=' + encodeURIComponent(serviceId);
        }


        // Ensure booking form and schedule are placed in normal flow (no overlap)
function updateStickyPositions() {
    const bookingForm = document.querySelector('.booking-form');
    const scheduleSection = document.querySelector('.schedule-availability');

    if (bookingForm) {
        bookingForm.style.position = 'static';
        bookingForm.style.top = 'auto';
    }
    if (scheduleSection) {
        scheduleSection.style.position = 'static';
        scheduleSection.style.top = 'auto';
        scheduleSection.style.marginTop = '1rem';
    }
}


        // Update positions on load and resize
        window.addEventListener('load', updateStickyPositions);
        window.addEventListener('resize', updateStickyPositions);

        // Lazy load portfolio images
        document.addEventListener('DOMContentLoaded', function() {
            const portfolioImages = document.querySelectorAll('.portfolio-image');
            
            portfolioImages.forEach(img => {
                // Add lazy loading
                img.loading = 'lazy';
            });
        });

        // ===== NEGOTIATION OFFER MODAL =====
        function openOfferModal(event, btn) {
            event.preventDefault();
            const serviceId = btn.dataset.serviceId;
            const serviceName = btn.dataset.serviceName;
            const minPrice = parseFloat(btn.dataset.minPrice);
            const maxPrice = parseFloat(btn.dataset.maxPrice);
            const basePrice = parseFloat(btn.dataset.basePrice);
            
            // Create modal HTML
            const modalHtml = `
                <div class="modal fade" id="offerModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-handshake me-2"></i> Send Price Offer
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Service</label>
                                    <input type="text" class="form-control" value="${serviceName}" disabled>
                                </div>
                                
                                <div class="alert alert-info mb-3">
                                    <strong>Price Range:</strong> RWF ${formatNumber(minPrice)} - RWF ${formatNumber(maxPrice)}
                                </div>
                                
                                <form id="offerForm">
                                    <div class="mb-3">
                                        <label for="offerPrice" class="form-label fw-bold">Your Offer Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">RWF</span>
                                            <input type="number" id="offerPrice" class="form-control" min="${minPrice}" max="${maxPrice}" step="100" placeholder="Enter your offer price" required>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            Enter a price between RWF ${formatNumber(minPrice)} and RWF ${formatNumber(maxPrice)}
                                        </small>
                                        <div id="priceValidation" class="text-danger small mt-2" style="display:none;"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="offerNote" class="form-label">Additional Message (Optional)</label>
                                        <textarea id="offerNote" class="form-control" rows="3" placeholder="e.g., I need this service by next week..."></textarea>
                                    </div>
                                    
                                    <div class="alert alert-light">
                                        <strong>How it works:</strong>
                                        <ul class="mb-0 mt-2" style="font-size: 0.9rem;">
                                            <li>Provider reviews your offer</li>
                                            <li>Provider can accept, reject, or counter-offer</li>
                                            <li>You can negotiate up to 3 rounds</li>
                                            <li>Price locks once both agree</li>
                                        </ul>
                                    </div>
                                    
                                    <input type="hidden" id="serviceId" value="${serviceId}">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="submitOfferBtn">
                                    <i class="fas fa-paper-plane me-2"></i> Send Offer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove old modal if exists
            const oldModal = document.getElementById('offerModal');
            if (oldModal) oldModal.remove();
            
            // Add modal to DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('offerModal'));
            modal.show();
            
            // Price validation
            const priceInput = document.getElementById('offerPrice');
            const validationDiv = document.getElementById('priceValidation');
            
            priceInput.addEventListener('input', function() {
                const price = parseFloat(this.value);
                
                if (!price) {
                    validationDiv.style.display = 'none';
                    return;
                }
                
                if (price < minPrice || price > maxPrice) {
                    validationDiv.textContent = `⚠️ Price must be between RWF ${formatNumber(minPrice)} and RWF ${formatNumber(maxPrice)}`;
                    validationDiv.style.display = 'block';
                } else {
                    validationDiv.style.display = 'none';
                }
            });
            
            // Submit offer
            document.getElementById('submitOfferBtn').addEventListener('click', function() {
                const price = parseFloat(priceInput.value);
                const note = document.getElementById('offerNote').value;
                const svcId = document.getElementById('serviceId').value;
                
                if (!price || price < minPrice || price > maxPrice) {
                    alert('Please enter a valid price within the range');
                    return;
                }
                
                submitOffer(svcId, price, note);
                modal.hide();
            });
        }
        
        function formatNumber(num) {
            return new Intl.NumberFormat('en-US').format(Math.round(num));
        }
        
        function submitOffer(serviceId, offeredPrice, notes = '') {
            // Send to API
            fetch('../api/service_offers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=create_offer&service_id=' + serviceId + '&offered_price=' + offeredPrice + '&notes=' + encodeURIComponent(notes)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                    alertDiv.style.zIndex = '9999';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Offer Sent!</strong> The provider will review your offer soon.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    document.body.insertAdjacentElement('afterbegin', alertDiv);
                    
                    // Auto-dismiss after 5 seconds
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 5000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to send offer'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending offer. Please try again.');
            });
        }

        // Track provider profile view and page session
        (function() {
            let pageStartTime = Date.now();
            const pageUrl = window.location.href;
            const pageTitle = document.title;

            function trackPageView() {
                fetch('../api/track_user_behavior.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'track_page_view',
                        page_url: pageUrl,
                        page_title: pageTitle,
                        referrer: document.referrer
                    })
                }).catch(console.error);
            }

            function startPageSession() {
                const pageStart = new Date(pageStartTime).toISOString();
                fetch('../api/track_user_behavior.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'start_page_session',
                        page_url: pageUrl,
                        page_start: pageStart
                    })
                }).catch(console.error);
            }

            function endPageSession() {
                const pageEndTime = Date.now();
                const timeSpent = Math.floor((pageEndTime - pageStartTime) / 1000);
                const pageEnd = new Date(pageEndTime).toISOString();
                const pageStart = new Date(pageStartTime).toISOString();

                fetch('../api/track_user_behavior.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'end_page_session',
                        page_url: pageUrl,
                        time_spent_seconds: timeSpent,
                        page_start: pageStart,
                        page_end: pageEnd
                    })
                }).catch(console.error);
            }

            function trackProviderView() {
                fetch('../api/track_user_behavior.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'track_provider_view',
                        provider_id: <?php echo intval($provider_id); ?>
                    })
                }).catch(console.error);
            }

            document.addEventListener('DOMContentLoaded', function() {
                trackPageView();
                startPageSession();
                setTimeout(trackProviderView, 1000);
            });

            window.addEventListener('beforeunload', endPageSession);
            window.addEventListener('unload', endPageSession);

            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    endPageSession();
                } else {
                    pageStartTime = Date.now();
                    startPageSession();
                }
            });
        })();
    </script>
</body>
</html>