<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/client_header.php';
require_once '../includes/provider_requirements.php';
require_once '../includes/service_negotiation.php';
require_once '../controllers/pages/client/ClientProviderProfileController.php';

$db = Database::getInstance()->getConnection();
$provider_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$share_id = isset($_GET['share_id']) ? intval($_GET['share_id']) : null;

if (!$provider_id) {
    header('Location: providers.php');
    exit();
}

$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;
$viewModel = (new ClientProviderProfileController())->index($db, $provider_id, $share_id, $userId);

$platform_name = $viewModel['platform_name'] ?? 'BII LocalFinder';
$platform_description = $viewModel['platform_description'] ?? 'Connecting skilled professionals with clients across Rwanda';
$provider = $viewModel['provider'] ?? null;

if ($provider === null) {
    header('Location: providers.php');
    exit();
}

$requirements = new ProviderRequirements($db, $provider_id);
$visibility = $viewModel['visibility'] ?? [];

if (empty($visibility) || !($visibility['profile_public'] ?? true)) {
    header('Location: providers.php');
    exit();
}

$day_names = $viewModel['day_names'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$formatted_working_days = $viewModel['formatted_working_days'] ?? [];
$schedule_info = $viewModel['schedule_info'] ?? null;
$working_days = $viewModel['working_days'] ?? [];
$next_available_date = $viewModel['next_available_date'] ?? null;
$services = $viewModel['services'] ?? [];
$categories = $viewModel['categories'] ?? [];
$portfolio_images = $viewModel['portfolio_images'] ?? [];
$portfolio_videos = $viewModel['portfolio_videos'] ?? [];
$paymentMethods = $viewModel['payment_methods'] ?? [];
$serviceAreas = $viewModel['service_areas'] ?? [];
$is_favorite = $viewModel['is_favorite'] ?? false;
$similar_providers = $viewModel['similar_providers'] ?? [];
$active_links = $viewModel['active_links'] ?? 0;
$social_links = $viewModel['social_links'] ?? [];
$booking_success = $viewModel['booking_success'] ?? '';
$booking_errors = $viewModel['booking_errors'] ?? [];

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
foreach ($social_links as $key => $link) {
    if ($key === 'whatsapp' && !$visibility['show_whatsapp']) {
        continue;
    }
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
    <title><?php echo htmlspecialchars($provider['full_name']); ?> — <?php echo htmlspecialchars($platform_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Design Tokens (matches booking.php) ──────────────────── */
        :root {
            --ink:       #0B1F17;
            --ink-light: #5B685F;
            --surface:   #F6F3EC;
            --card:      #ffffff;
            --accent:    #B9822E;
            --accent-2:  #3F6B4A;
            --accent-3:  #A8432E;
            --gold:      #D9A64E;
            --border:    rgba(11,31,23,.12);
            --shadow-sm: 0 2px 8px rgba(11,31,23,.06);
            --shadow-md: 0 8px 32px rgba(11,31,23,.10);
            --shadow-lg: 0 20px 60px rgba(11,31,23,.14);
            --r-sm: 10px;
            --r-md: 18px;
            --r-lg: 28px;
            --transition: .28s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 0;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; }

        .bg-grid {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                radial-gradient(ellipse 80% 60% at 10% -10%, rgba(11,31,23,.07) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 110%, rgba(63,107,74,.06) 0%, transparent 60%);
        }
        .bg-grid::after {
            content: '';
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(185,130,46,.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(185,130,46,.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .page-wrapper { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; }

        /* ─── Top Nav ────────────────────────────────────────────── */
        .top-nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 2.5rem;
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
        .nav-brand-mark {
            width: 36px; height: 36px; border-radius: 10px; background: var(--ink);
            display: flex; align-items: center; justify-content: center;
            color: var(--gold); font-size: 1rem; flex-shrink: 0;
        }
        .nav-brand-word { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.02rem; line-height: 1.1; color: var(--ink); }
        .nav-brand-word small {
            display: block; font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-weight: 400; font-size: .6rem; color: var(--ink-light);
            letter-spacing: .06em; text-transform: uppercase;
        }
        .nav-actions { display: flex; align-items: center; gap: .6rem; }
        .nav-back {
            display: flex; align-items: center; gap: .5rem;
            font-size: .9rem; font-weight: 600; color: var(--ink-light);
            text-decoration: none; padding: .5rem 1rem;
            border: 1.5px solid var(--border); border-radius: 50px;
            transition: var(--transition);
        }
        .nav-back:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
        .nav-icon-btn {
            width: 40px; height: 40px; border-radius: 50%;
            border: 1.5px solid var(--border); background: #fff;
            display: flex; align-items: center; justify-content: center;
            color: var(--ink); text-decoration: none; transition: var(--transition);
            font-size: .95rem; position: relative;
        }
        .nav-icon-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
        .nav-icon-btn.active { background: var(--accent-3); color: #fff; border-color: var(--accent-3); }

        /* ─── Hero ───────────────────────────────────────────────── */
        .profile-hero {
            background: linear-gradient(135deg, var(--ink) 0%, #12291F 55%, #1B382A 100%);
            padding: 2.75rem 2.5rem 5rem;
            position: relative; overflow: hidden;
        }
        .profile-hero::before {
            content: ''; position: absolute; top: -60px; right: -60px;
            width: 320px; height: 320px; background: rgba(217,166,78,.14); border-radius: 50%;
        }
        .profile-hero::after {
            content: ''; position: absolute; bottom: -90px; left: 35%;
            width: 220px; height: 220px; background: rgba(63,107,74,.15); border-radius: 50%;
        }
        .hero-inner {
            max-width: 1100px; margin: 0 auto;
            display: flex; align-items: flex-start; justify-content: space-between; gap: 2rem;
            flex-wrap: wrap; position: relative; z-index: 2;
        }
        .provider-snap { display: flex; align-items: center; gap: 1.5rem; flex: 1; min-width: 280px; }
        .provider-avatar {
            width: 96px; height: 96px; border-radius: 26px;
            border: 3px solid rgba(255,255,255,.4); background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif; font-size: 2.1rem; font-weight: 700;
            color: #fff; overflow: hidden; flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(0,0,0,.2);
        }
        .provider-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .provider-meta h1 { font-family: 'Syne', sans-serif; font-size: 1.65rem; font-weight: 700; color: #fff; line-height: 1.2; display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .provider-meta h1 .verify-tick { color: var(--gold); font-size: 1.1rem; }
        .provider-meta p.profession { color: rgba(255,255,255,.82); font-size: 1rem; margin-top: .3rem; font-weight: 500; }
        .provider-badges { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .9rem; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            background: rgba(255,255,255,.18); backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.25);
            color: #fff; font-size: .76rem; font-weight: 600;
            padding: .32rem .75rem; border-radius: 50px;
        }
        .hero-badge.dot i { font-size: .5rem; color: #7FE0A0; }

        .hero-right { display: flex; flex-direction: column; gap: .85rem; align-items: stretch; flex-shrink: 0; }
        .hero-rating {
            background: rgba(255,255,255,.15); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.25); border-radius: var(--r-md);
            padding: 1.1rem 1.65rem; text-align: center; color: #fff; min-width: 150px;
        }
        .hero-rating .r-num { font-family: 'Syne', sans-serif; font-size: 2.4rem; font-weight: 800; line-height: 1; }
        .hero-rating .r-stars { color: var(--gold); font-size: .95rem; margin: .3rem 0; letter-spacing: 1px; }
        .hero-rating .r-label { font-size: .78rem; opacity: .8; }
        .hero-btn-row { display: flex; gap: .6rem; }
        .hero-icon-btn {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: .45rem;
            padding: .65rem .9rem; border-radius: 50px; font-size: .82rem; font-weight: 700;
            border: 1.5px solid rgba(255,255,255,.3); background: rgba(255,255,255,.12);
            color: #fff; cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .hero-icon-btn:hover { background: rgba(255,255,255,.24); }
        .hero-icon-btn.is-fav { background: var(--accent-3); border-color: var(--accent-3); }

        /* ─── Quick Nav Pills ────────────────────────────────────── */
        .quick-nav-wrap {
            position: sticky; top: 66px; z-index: 90;
            background: rgba(246,243,236,.92); backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }
        .quick-nav {
            max-width: 1100px; margin: 0 auto; padding: .7rem 1.5rem;
            display: flex; gap: .55rem; overflow-x: auto; scrollbar-width: none;
        }
        .quick-nav::-webkit-scrollbar { display: none; }
        .quick-nav a {
            flex-shrink: 0; padding: .45rem 1rem; border-radius: 50px;
            font-size: .8rem; font-weight: 700; text-decoration: none;
            background: #fff; border: 1.5px solid var(--border); color: var(--ink-light);
            transition: var(--transition);
        }
        .quick-nav a:hover, .quick-nav a.active { background: var(--ink); border-color: var(--ink); color: #fff; }

        /* ─── Shell / Grid ───────────────────────────────────────── */
        .profile-shell {
            max-width: 1100px;
            margin: -0.25rem auto 0;
            padding: 1.75rem 1.5rem 3rem;
            position: relative;
            z-index: 3;
        }

        /* ─── Booking CTA Banner ─────────────────────────────────── */
        .cta-banner {
            background: var(--card); border-radius: var(--r-lg); box-shadow: var(--shadow-md);
            border: 1px solid var(--border); padding: 1.5rem 1.75rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;
            flex-wrap: wrap; margin-bottom: 2.25rem;
            animation: fadeUp .5s ease both;
        }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .cta-left { display: flex; align-items: center; gap: 1rem; }
        .cta-icon {
            width: 52px; height: 52px; border-radius: var(--r-sm); flex-shrink: 0;
            background: linear-gradient(135deg, var(--accent), #12291F);
            display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;
        }
        .cta-left h3 { font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
        .cta-left p { font-size: .85rem; color: var(--ink-light); margin-top: .2rem; }
        .cta-contacts { display: flex; gap: .5rem; flex-wrap: wrap; }
        .cta-contact-chip {
            display: flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600;
            color: var(--ink-light); background: var(--surface); border: 1px solid var(--border);
            padding: .4rem .75rem; border-radius: 50px;
        }
        .cta-contact-chip i { color: var(--accent); }

        /* ─── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .8rem 1.75rem; border-radius: 50px;
            font-family: 'DM Sans', sans-serif; font-size: .92rem; font-weight: 700;
            cursor: pointer; border: none; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: linear-gradient(135deg, var(--accent), #12291F); color: #fff; box-shadow: 0 4px 16px rgba(185,130,46,.35); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(185,130,46,.45); }
        .btn-secondary { background: #EFEBE0; color: var(--ink-light); border: 1.5px solid var(--border); }
        .btn-secondary:hover { background: #E7E2D6; }
        .btn-success { background: linear-gradient(135deg, var(--accent-2), #2E5038); color: #fff; box-shadow: 0 4px 16px rgba(63,107,74,.35); }
        .btn-success:hover { transform: translateY(-2px); }
        .btn-outline { background: #fff; color: var(--ink); border: 1.5px solid var(--border); }
        .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
        .btn-block { width: 100%; }
        .btn-sm { padding: .55rem 1.15rem; font-size: .8rem; }

        /* ─── Main Grid ───────────────────────────────────────────── */
        .profile-main { display: grid; grid-template-columns: 1fr 340px; gap: 1.75rem; align-items: start; }
        .main-col { display: flex; flex-direction: column; gap: 1.75rem; min-width: 0; }
        .side-col { display: flex; flex-direction: column; gap: 1.5rem; position: sticky; top: 128px; }

        /* ─── Card System ─────────────────────────────────────────── */
        .card {
            background: var(--card); border-radius: var(--r-lg); box-shadow: var(--shadow-md);
            border: 1px solid var(--border); overflow: hidden; animation: fadeUp .5s ease both;
        }
        .card-head { padding: 1.5rem 1.75rem 0; display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
        .card-head-left { display: flex; align-items: center; gap: .75rem; }
        .card-head-icon {
            width: 40px; height: 40px; border-radius: var(--r-sm);
            background: linear-gradient(135deg, var(--accent), #12291F);
            display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; flex-shrink: 0;
        }
        .card-head h2 { font-family: 'Syne', sans-serif; font-size: 1.08rem; font-weight: 700; color: var(--ink); }
        .card-head .count-chip { background: var(--surface); border: 1px solid var(--border); color: var(--ink-light); font-size: .75rem; font-weight: 700; padding: .25rem .65rem; border-radius: 50px; }
        .card-body { padding: 1.25rem 1.75rem 1.75rem; }
        .card-body p.lead-text { color: var(--ink-light); line-height: 1.7; font-size: .93rem; }

        /* ─── Sidebar Widgets ─────────────────────────────────────── */
        .side-card { background: var(--card); border-radius: var(--r-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border); padding: 1.5rem; animation: fadeUp .5s ease both; }
        .side-card h3 { font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .5rem; margin-bottom: 1rem; }
        .side-card h3 i { color: var(--accent); }

        .trust-row { display: flex; flex-direction: column; gap: .65rem; }
        .trust-item { display: flex; align-items: center; gap: .7rem; font-size: .82rem; font-weight: 600; color: var(--ink-light); }
        .trust-item i { color: var(--accent-2); font-size: 1rem; width: 20px; text-align: center; }

        .stats-grid-side { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .stat-box-mini { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); padding: .85rem .7rem; text-align: center; }
        .stat-box-mini .num { font-family: 'Syne', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--ink); line-height: 1; }
        .stat-box-mini .lbl { font-size: .68rem; color: var(--ink-light); font-weight: 600; margin-top: .3rem; text-transform: uppercase; letter-spacing: .3px; }

        .connect-grid { display: flex; flex-wrap: wrap; gap: .55rem; }
        .social-btn {
            display: flex; align-items: center; gap: .4rem; padding: .55rem .85rem; border-radius: 50px;
            background: var(--surface); border: 1px solid var(--border); font-size: .8rem; font-weight: 700;
            color: var(--ink); text-decoration: none; transition: var(--transition);
        }
        .social-btn:hover { background: var(--social-color, var(--accent)); color: #fff; border-color: var(--social-color, var(--accent)); }

        /* ─── About / Schedule ───────────────────────────────────── */
        .schedule-alert {
            display: flex; align-items: center; gap: .9rem; padding: 1rem 1.15rem;
            background: linear-gradient(135deg, rgba(63,107,74,.08), rgba(63,107,74,.04));
            border: 1px solid rgba(63,107,74,.2); border-radius: var(--r-md); margin-bottom: 1.1rem;
        }
        .schedule-alert i { font-size: 1.4rem; color: var(--accent-2); }
        .schedule-alert strong { display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .4px; color: var(--accent-2); }
        .schedule-alert span { font-size: .92rem; font-weight: 700; color: var(--ink); }

        .sched-block { margin-bottom: 1.2rem; }
        .sched-block:last-child { margin-bottom: 0; }
        .sched-block h5 { font-size: .82rem; font-weight: 700; color: var(--ink); margin-bottom: .6rem; text-transform: uppercase; letter-spacing: .4px; }
        .sched-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem 0; font-size: .87rem; border-bottom: 1px dashed var(--border); }
        .sched-row:last-child { border-bottom: none; }
        .sched-row span { color: var(--ink-light); }
        .sched-row strong { color: var(--ink); font-weight: 700; }
        .sched-row strong.warn { color: var(--accent); }

        .response-indicator { height: 8px; border-radius: 50px; background: var(--surface); overflow: hidden; flex: 1; }
        .response-fill { height: 100%; border-radius: 50px; background: linear-gradient(90deg, var(--accent-2), #5b9c6c); }

        .sched-list-item { display: flex; justify-content: space-between; align-items: center; padding: .55rem 0; border-bottom: 1px solid var(--border); font-size: .85rem; }
        .sched-list-item:last-child { border-bottom: none; }
        .sched-list-item i { margin-right: .5rem; }
        .sched-list-item i.off { color: var(--accent-3); }
        .sched-list-item i.on { color: var(--accent-2); }

        .option-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
        .option-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem .85rem; border-radius: 50px; font-size: .78rem; font-weight: 700; color: #fff; }
        .option-chip.red { background: linear-gradient(135deg, var(--accent-3), #7a2f20); }
        .option-chip.blue { background: linear-gradient(135deg, #3F6B4A, #2E5038); }
        .option-chip.gold { background: linear-gradient(135deg, var(--gold), #b9822e); }

        .sched-footnote { display: flex; align-items: center; gap: .5rem; font-size: .76rem; color: var(--ink-light); margin-top: 1.1rem; padding-top: 1rem; border-top: 1px solid var(--border); }

        /* ─── Portfolio ───────────────────────────────────────────── */
        .portfolio-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: .85rem; }
        .portfolio-tile { position: relative; border-radius: var(--r-sm); overflow: hidden; cursor: pointer; aspect-ratio: 1/1; background: var(--surface); border: 1px solid var(--border); }
        .portfolio-tile img { width: 100%; height: 100%; object-fit: cover; transition: var(--transition); }
        .portfolio-tile:hover img { transform: scale(1.08); }
        .portfolio-tile .p-overlay {
            position: absolute; inset: 0; background: linear-gradient(0deg, rgba(11,31,23,.75) 0%, transparent 55%);
            display: flex; align-items: flex-end; padding: .65rem; opacity: 0; transition: var(--transition);
        }
        .portfolio-tile:hover .p-overlay { opacity: 1; }
        .portfolio-tile .p-title { color: #fff; font-size: .78rem; font-weight: 700; line-height: 1.3; }
        .portfolio-tile .p-zoom {
            position: absolute; top: .5rem; right: .5rem; width: 28px; height: 28px; border-radius: 50%;
            background: rgba(255,255,255,.9); display: flex; align-items: center; justify-content: center;
            color: var(--ink); font-size: .7rem; opacity: 0; transition: var(--transition);
        }
        .portfolio-tile:hover .p-zoom { opacity: 1; }
        .portfolio-more { text-align: center; margin-top: 1.1rem; }

        /* ─── Video Showcase ──────────────────────────────────────── */
        .video-card { position: relative; border-radius: var(--r-lg); overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border); animation: fadeUp .5s ease both; }
        .video-card .vc-info { background: linear-gradient(135deg, var(--ink) 0%, #12291F 100%); color: #fff; padding: 1.5rem 1.75rem; }
        .video-badge { display: inline-flex; align-items: center; gap: .4rem; background: rgba(217,166,78,.2); border: 1px solid rgba(217,166,78,.35); color: var(--gold); font-size: .72rem; font-weight: 700; padding: .3rem .75rem; border-radius: 50px; margin-bottom: .75rem; }
        .video-card .vc-info h3 { font-family: 'Syne', sans-serif; font-size: 1.15rem; font-weight: 700; margin-bottom: .5rem; }
        .video-card .vc-info p { font-size: .85rem; color: rgba(255,255,255,.78); line-height: 1.6; }
        .video-player-wrap { position: relative; background: #000; }
        .video-player-wrap video { width: 100%; display: block; max-height: 420px; }

        /* ─── Services ────────────────────────────────────────────── */
        .service-grid-p { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .service-card-p {
            border: 2px solid var(--border); border-radius: var(--r-md); padding: 1.25rem;
            background: #fff; transition: var(--transition); display: flex; flex-direction: column; gap: .65rem;
        }
        .service-card-p:hover { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(185,130,46,.08); }
        .service-cat-p { font-size: .72rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: .6px; display: flex; align-items: center; gap: .35rem; }
        .service-name-p { font-family: 'Syne', sans-serif; font-size: .98rem; font-weight: 700; color: var(--ink); line-height: 1.3; display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
        .service-desc-p { font-size: .83rem; color: var(--ink-light); line-height: 1.5; flex: 1; }
        .negotiable-chip { display: inline-flex; align-items: center; gap: .3rem; background: linear-gradient(135deg,#B9822E,#8C6423); color: #fff; font-size: .66rem; font-weight: 700; padding: .18rem .55rem; border-radius: 50px; }
        .service-footer-p { display: flex; align-items: center; justify-content: space-between; padding-top: .5rem; border-top: 1px dashed var(--border); }
        .service-price-p { font-family: 'Syne', sans-serif; font-size: 1.08rem; font-weight: 800; color: var(--accent-2); }
        .service-price-p.range { font-size: .88rem; }
        .service-dur-p { font-size: .76rem; color: var(--ink-light); display: flex; align-items: center; gap: .3rem; }
        .service-btn-row { display: flex; gap: .5rem; }
        .service-btn-row .btn { flex: 1; padding: .6rem .8rem; font-size: .8rem; }

        /* ─── Categories / Badges / Payment chips ────────────────── */
        .chips-wrap { display: flex; flex-wrap: wrap; gap: .6rem; }
        .cat-chip { display: flex; align-items: center; gap: .5rem; background: var(--surface); border: 1px solid var(--border); color: var(--ink); font-size: .82rem; font-weight: 700; padding: .55rem 1rem; border-radius: 50px; }
        .cat-chip i { color: var(--accent); }

        .badges-grid-p { display: grid; grid-template-columns: repeat(2,1fr); gap: .85rem; }
        .badge-tile { display: flex; align-items: center; gap: .75rem; padding: .9rem 1rem; border-radius: var(--r-md); background: var(--surface); border: 1px solid var(--border); }
        .badge-tile .b-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; flex-shrink: 0; }
        .badge-tile .b-icon.success { background: linear-gradient(135deg, var(--accent-2), #2E5038); }
        .badge-tile .b-icon.warning { background: linear-gradient(135deg, var(--gold), #b9822e); }
        .badge-tile .b-icon.primary { background: linear-gradient(135deg, var(--accent), #12291F); }
        .badge-tile .b-icon.info    { background: linear-gradient(135deg, #4a7fa8, #2e5c7a); }
        .badge-tile span { font-size: .82rem; font-weight: 700; color: var(--ink); }

        .payment-list { display: flex; flex-wrap: wrap; gap: .65rem; }
        .payment-chip { display: flex; align-items: center; gap: .6rem; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); padding: .7rem 1rem; }
        .payment-chip .pm-icon { width: 34px; height: 34px; border-radius: 8px; background: #F1E4C8; color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: .9rem; }
        .payment-chip strong { font-size: .82rem; color: var(--ink); }
        .payment-chip .default-tag { font-size: .65rem; background: var(--accent-2); color: #fff; padding: .1rem .5rem; border-radius: 50px; font-weight: 700; margin-left: .3rem; }

        /* ─── Service Areas ───────────────────────────────────────── */
        .areas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
        .area-card { border: 1px solid var(--border); border-radius: var(--r-md); padding: 1rem 1.1rem; background: var(--surface); }
        .area-card.primary { border-color: rgba(63,107,74,.35); background: linear-gradient(135deg, rgba(63,107,74,.07), rgba(63,107,74,.03)); }
        .area-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .4rem; }
        .area-name { display: flex; align-items: center; gap: .4rem; font-size: .88rem; font-weight: 700; color: var(--ink); }
        .area-name i { color: var(--accent); }
        .primary-tag { font-size: .65rem; font-weight: 700; background: var(--accent-2); color: #fff; padding: .18rem .55rem; border-radius: 50px; }
        .area-coverage { font-size: .78rem; color: var(--ink-light); display: flex; align-items: center; gap: .35rem; }

        /* ─── Reviews ─────────────────────────────────────────────── */
        .rating-summary { display: grid; grid-template-columns: 130px 1fr; gap: 1.5rem; align-items: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-md); padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
        .rs-score { text-align: center; }
        .rs-score .n { font-family: 'Syne', sans-serif; font-size: 2.6rem; font-weight: 800; color: var(--ink); line-height: 1; }
        .rs-score .stars { color: var(--gold); font-size: 1rem; margin: .35rem 0; }
        .rs-score .count { font-size: .76rem; color: var(--ink-light); }
        .rs-bars { display: flex; flex-direction: column; gap: .4rem; }
        .rs-bar-row { display: flex; align-items: center; gap: .6rem; font-size: .76rem; color: var(--ink-light); }
        .rs-bar-row .lbl { width: 44px; flex-shrink: 0; }
        .rs-bar-track { flex: 1; height: 7px; border-radius: 50px; background: #EFEBE0; overflow: hidden; }
        .rs-bar-fill { height: 100%; border-radius: 50px; background: linear-gradient(90deg, var(--gold), var(--accent)); }
        .rs-bar-row .ct { width: 24px; text-align: right; flex-shrink: 0; }

        .review-card { padding: 1.1rem 0; border-bottom: 1px solid var(--border); }
        .review-card:last-child { border-bottom: none; }
        .review-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: .55rem; }
        .reviewer { display: flex; align-items: center; gap: .75rem; }
        .reviewer-avatar {
            width: 42px; height: 42px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
            background: linear-gradient(135deg, var(--accent), #12291F); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .95rem;
        }
        .reviewer-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .reviewer-name { font-size: .87rem; font-weight: 700; color: var(--ink); }
        .review-stars-mini { color: var(--gold); font-size: .78rem; margin-top: .15rem; }
        .review-date { font-size: .74rem; color: var(--ink-light); white-space: nowrap; flex-shrink: 0; }
        .review-comment { font-size: .87rem; color: var(--ink-light); line-height: 1.6; }

        /* ─── Similar Providers ───────────────────────────────────── */
        .similar-scroll { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: .5rem; scroll-snap-type: x proximity; }
        .similar-scroll::-webkit-scrollbar { height: 6px; }
        .similar-card {
            flex: 0 0 220px; scroll-snap-align: start; border: 1px solid var(--border); border-radius: var(--r-md);
            padding: 1.1rem; background: var(--surface); display: flex; flex-direction: column; gap: .65rem;
        }
        .similar-top { display: flex; align-items: center; gap: .65rem; }
        .similar-avatar {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
            background: linear-gradient(135deg, var(--accent), #12291F); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 700;
        }
        .similar-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .similar-name { font-size: .85rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .3rem; }
        .similar-prof { font-size: .74rem; color: var(--ink-light); }
        .similar-stats-row { display: flex; gap: .75rem; font-size: .75rem; color: var(--ink-light); font-weight: 600; }
        .similar-stats-row i { color: var(--accent); margin-right: .2rem; }
        .similar-price-tag { font-size: .78rem; font-weight: 700; color: var(--accent-2); }

        /* ─── Empty State ─────────────────────────────────────────── */
        .empty-state-p { text-align: center; padding: 2.25rem 1rem; color: var(--ink-light); }
        .empty-state-p i { font-size: 2rem; color: var(--border); margin-bottom: .75rem; }
        .empty-state-p h4 { font-family: 'Syne', sans-serif; font-size: 1rem; color: var(--ink); margin-bottom: .3rem; }
        .empty-state-p p { font-size: .84rem; }

        /* ─── Alerts ──────────────────────────────────────────────── */
        .alert-p { border-radius: var(--r-sm); padding: .9rem 1.1rem; font-size: .84rem; line-height: 1.5; display: flex; gap: .65rem; margin-bottom: 1.1rem; }
        .alert-p.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-p.error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        /* ─── Modals (custom, no bootstrap) ──────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 1000;
            background: rgba(11,31,23,.6); backdrop-filter: blur(4px);
            align-items: center; justify-content: center; padding: 1.25rem;
        }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; border-radius: var(--r-lg); max-width: 480px; width: 100%; max-height: 88vh; overflow-y: auto; box-shadow: var(--shadow-lg); animation: popIn .35s cubic-bezier(.34,1.56,.64,1) both; }
        @keyframes popIn { from { transform: scale(.92); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-box.wide { max-width: 640px; }
        .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-head h4 { font-family: 'Syne', sans-serif; font-size: 1.02rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .5rem; }
        .modal-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--surface); color: var(--ink-light); cursor: pointer; font-size: .9rem; }
        .modal-close:hover { background: var(--accent-3); color: #fff; }
        .modal-body-p { padding: 1.5rem; }
        .modal-foot { display: flex; justify-content: flex-end; gap: .75rem; padding: 1.1rem 1.5rem; border-top: 1px solid var(--border); }

        .field-p { margin-bottom: 1.1rem; }
        .field-p label { display: block; font-size: .82rem; font-weight: 700; color: var(--ink); margin-bottom: .4rem; }
        .field-p .hint { font-size: .76rem; color: var(--ink-light); margin-top: .3rem; }
        .field-p .input-p, .field-p textarea {
            width: 100%; padding: .75rem 1rem; border: 2px solid var(--border); border-radius: var(--r-sm);
            background: #FBFAF6; font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--ink);
            transition: var(--transition); outline: none;
        }
        .field-p .input-p:focus, .field-p textarea:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 4px rgba(185,130,46,.1); }
        .field-p textarea { resize: vertical; min-height: 90px; }
        .price-prefix { position: relative; }
        .price-prefix .input-p { padding-left: 3.4rem; }
        .price-prefix::before { content: 'RWF'; position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--ink-light); font-size: .82rem; }
        .info-box-p { background: rgba(63,107,74,.06); border: 1px solid rgba(63,107,74,.18); border-radius: var(--r-sm); padding: .85rem 1rem; font-size: .8rem; color: var(--ink-light); margin-bottom: 1rem; }
        .info-box-p strong { color: var(--ink); }
        .info-box-p ul { margin: .4rem 0 0 1.1rem; }

        .lightbox-img { width: 100%; max-height: 62vh; object-fit: contain; background: #0B1F17; }
        .lightbox-caption { padding: 1.25rem 1.5rem; }
        .lightbox-caption h5 { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; color: var(--ink); margin-bottom: .35rem; }
        .lightbox-caption p { font-size: .85rem; color: var(--ink-light); line-height: 1.6; }

        /* ─── Toast ───────────────────────────────────────────────── */
        .toast-p {
            position: fixed; top: 1.25rem; left: 50%; transform: translateX(-50%) translateY(-30px);
            background: var(--ink); color: #fff; padding: .85rem 1.4rem; border-radius: 50px;
            font-size: .85rem; font-weight: 600; display: flex; align-items: center; gap: .6rem;
            box-shadow: var(--shadow-lg); z-index: 1200; opacity: 0; pointer-events: none;
            transition: all .35s cubic-bezier(.4,0,.2,1);
        }
        .toast-p.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast-p i { color: #7FE0A0; }
        .toast-p.error i { color: #f38b7a; }

        /* ─── Mobile Sticky Bar ───────────────────────────────────── */
        .mobile-cta-bar { display: none; }

        /* ─── Responsive ──────────────────────────────────────────── */
        @media (max-width: 980px) {
            .profile-main { grid-template-columns: 1fr; }
            .side-col { position: static; }
            .service-grid-p { grid-template-columns: 1fr; }
            .badges-grid-p { grid-template-columns: 1fr; }
            .areas-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .top-nav { padding: .85rem 1.1rem; }
            .nav-brand-word { font-size: .9rem; }
            .profile-hero { padding: 1.9rem 1.1rem 4rem; }
            .hero-inner { flex-direction: column; align-items: flex-start; }
            .hero-right { width: 100%; flex-direction: row; }
            .hero-rating { flex: 1; }
            .hero-btn-row { flex: 1; flex-direction: column; }
            .quick-nav { padding: .6rem 1.1rem; }
            .profile-shell { padding: 1.5rem 1rem 6.5rem; margin-top: 0; }
            .cta-banner { flex-direction: column; align-items: flex-start; padding: 1.25rem; margin-bottom: 1.75rem; }
            .cta-contacts { width: 100%; }
            .card-head { padding: 1.25rem 1.25rem 0; }
            .card-body { padding: 1rem 1.25rem 1.25rem; }
            .portfolio-grid { grid-template-columns: repeat(2,1fr); }
            .rating-summary { grid-template-columns: 1fr; text-align: center; }
            .mobile-cta-bar {
                display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 200;
                background: rgba(255,255,255,.96); backdrop-filter: blur(14px);
                border-top: 1px solid var(--border); padding: .75rem 1rem;
                gap: .65rem; box-shadow: 0 -8px 24px rgba(11,31,23,.12);
            }
            .mobile-cta-bar .btn { flex: 1; padding: .8rem; font-size: .85rem; }
        }
        @media (max-width: 480px) {
            .hero-badge { font-size: .7rem; }
            .provider-avatar { width: 80px; height: 80px; border-radius: 20px; }
            .provider-meta h1 { font-size: 1.35rem; }
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(185,130,46,.25); border-radius: 3px; }
    </style>
</head>
<body>

<div class="bg-grid" aria-hidden="true"></div>

<div class="page-wrapper">

    <!-- ── Top Navigation ─────────────────────────────────────── -->
    <nav class="top-nav">
        <a href="dashboard.php" class="nav-brand">
            <span class="nav-brand-mark"><i class="fas fa-map-location-dot"></i></span>
            <span class="nav-brand-word"><?php echo htmlspecialchars($platform_name); ?><small>Rwanda · local services</small></span>
        </a>
        <div class="nav-actions">
            <?php if (isLoggedIn() && !isProvider()): ?>
                <a href="messages.php?with=<?php echo (int)$provider['user_id']; ?>" class="nav-icon-btn" title="Message provider">
                    <i class="fas fa-comment-dots"></i>
                </a>
                <form method="POST" style="display:inline;" id="favForm">
                    <button type="submit" name="toggle_favorite" class="nav-icon-btn <?php echo $is_favorite ? 'active' : ''; ?>" title="<?php echo $is_favorite ? 'Remove favorite' : 'Add to favorites'; ?>">
                        <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                    </button>
                </form>
            <?php endif; ?>
            <a href="providers.php" class="nav-back"><i class="fas fa-arrow-left"></i> <span>Back</span></a>
        </div>
    </nav>

    <?php if (!empty($booking_success)): ?>
        <div id="serverToastSuccess" style="display:none;"><?php echo htmlspecialchars($booking_success); ?></div>
    <?php endif; ?>
    <?php if (!empty($booking_errors)): ?>
        <div id="serverToastError" style="display:none;"><?php echo htmlspecialchars(implode(' ', $booking_errors)); ?></div>
    <?php endif; ?>

    <!-- ── Hero ────────────────────────────────────────────────── -->
    <div class="profile-hero">
        <div class="hero-inner">
            <div class="provider-snap">
                <div class="provider-avatar">
                    <?php
                        $profile_image = $provider['profile_image'] ?? '';
                        $initials = strtoupper(substr($provider['full_name'] ?? 'U', 0, 1));
                        if (!empty($profile_image)):
                    ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" alt="<?php echo htmlspecialchars($provider['full_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($initials); ?>';">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="provider-meta">
                    <h1>
                        <?php echo htmlspecialchars($provider['full_name']); ?>
                        <?php if ($provider['is_verified'] || $provider['user_verified']): ?>
                            <i class="fas fa-circle-check verify-tick" title="Verified provider"></i>
                        <?php endif; ?>
                    </h1>
                    <p class="profession"><?php echo htmlspecialchars($provider['profession']); ?></p>
                    <div class="provider-badges">
                        <span class="hero-badge dot"><i class="fas fa-circle"></i> <?php echo htmlspecialchars(ucfirst($provider['availability'] ?? 'available')); ?></span>
                        <span class="hero-badge">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php if ($visibility['show_exact_location']): ?>
                                <?php echo htmlspecialchars($provider['location']); ?><?php echo $provider['district'] ? ', ' . htmlspecialchars($provider['district']) : ''; ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($provider['district'] ?? 'General area'); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($provider['experience_years']): ?>
                            <span class="hero-badge"><i class="fas fa-briefcase"></i> <?php echo (int)$provider['experience_years']; ?> yrs experience</span>
                        <?php endif; ?>
                        <span class="hero-badge"><i class="fas fa-calendar"></i> Since <?php echo date('M Y', strtotime($provider['member_since'])); ?></span>
                        <?php if ($provider['is_featured']): ?>
                            <span class="hero-badge" style="background:rgba(217,166,78,.25);border-color:rgba(217,166,78,.4);"><i class="fas fa-star"></i> Featured</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="hero-right">
                <div class="hero-rating">
                    <div class="r-num"><?php echo number_format($provider['average_rating'] ?? 0, 1); ?></div>
                    <div class="r-stars"><?php echo str_repeat('★', round($provider['average_rating'] ?? 0)) . str_repeat('☆', 5 - round($provider['average_rating'] ?? 0)); ?></div>
                    <div class="r-label"><?php echo (int)($provider['total_reviews'] ?? 0); ?> reviews</div>
                </div>
                <?php if (isLoggedIn() && !isProvider()): ?>
                <div class="hero-btn-row">
                    <button type="submit" form="favForm" name="toggle_favorite" class="hero-icon-btn <?php echo $is_favorite ? 'is-fav' : ''; ?>">
                        <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i> <?php echo $is_favorite ? 'Favorited' : 'Favorite'; ?>
                    </button>
                    <a href="messages.php?with=<?php echo (int)$provider['user_id']; ?>" class="hero-icon-btn">
                        <i class="fas fa-comment-dots"></i> Message
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Quick Nav ───────────────────────────────────────────── -->
    <div class="quick-nav-wrap">
        <div class="quick-nav" id="quickNav">
            <a href="#about" class="active">About</a>
            <?php if (!empty($services)): ?><a href="#services">Services</a><?php endif; ?>
            <?php if (!empty($portfolio_images)): ?><a href="#portfolio">Portfolio</a><?php endif; ?>
            <a href="#schedule">Schedule</a>
            <a href="#reviews">Reviews (<?php echo count($reviews); ?>)</a>
            <?php if (!empty($serviceAreas)): ?><a href="#areas">Areas</a><?php endif; ?>
        </div>
    </div>

    <!-- ── Shell ───────────────────────────────────────────────── -->
    <div class="profile-shell">

        <!-- Booking CTA Banner -->
        <div class="cta-banner">
            <div class="cta-left">
                <div class="cta-icon"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <h3>Ready to get started?</h3>
                    <p>Book <?php echo htmlspecialchars(explode(' ', $provider['full_name'])[0]); ?> in a few quick steps.</p>
                </div>
            </div>
            <div class="cta-contacts">
                <?php if ($visibility['show_phone'] && !empty($provider['phone'])): ?>
                    <span class="cta-contact-chip"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($provider['phone']); ?></span>
                <?php endif; ?>
                <span class="cta-contact-chip"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($provider['email']); ?></span>
            </div>
            <a href="booking.php?provider_id=<?php echo $provider_id; ?><?php echo !empty($share_id) ? '&share_id=' . intval($share_id) : ''; ?>" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Continue to Booking
            </a>
        </div>

        <div class="profile-main">
            <!-- ══ MAIN COLUMN ══ -->
            <div class="main-col">

                <!-- About -->
                <div class="card" id="about">
                    <div class="card-head">
                        <div class="card-head-left">
                            <div class="card-head-icon"><i class="fas fa-user"></i></div>
                            <h2>About</h2>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="lead-text"><?php echo nl2br(htmlspecialchars($provider['bio'] ?: 'No description provided yet.')); ?></p>
                    </div>
                </div>

                <!-- Services -->
                <?php if (!empty($services)): ?>
                <div class="card" id="services">
                    <div class="card-head">
                        <div class="card-head-left">
                            <div class="card-head-icon"><i class="fas fa-concierge-bell"></i></div>
                            <h2>Services</h2>
                        </div>
                        <span class="count-chip"><?php echo count($services); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="service-grid-p">
                            <?php foreach ($services as $service): ?>
                                <div class="service-card-p">
                                    <div class="service-cat-p"><i class="fas <?php echo htmlspecialchars($service['category_icon']); ?>"></i> <?php echo htmlspecialchars($service['category_name']); ?></div>
                                    <div class="service-name-p">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                        <?php if ($service['negotiable']): ?><span class="negotiable-chip"><i class="fas fa-handshake"></i> Negotiable</span><?php endif; ?>
                                    </div>
                                    <p class="service-desc-p"><?php echo nl2br(htmlspecialchars(mb_strimwidth($service['description'] ?? '', 0, 140, '…'))); ?></p>
                                    <div class="service-footer-p">
                                        <?php if ($service['negotiable'] && $service['min_price'] && $service['max_price']): ?>
                                            <div class="service-price-p range">RWF <?php echo number_format($service['min_price']); ?>–<?php echo number_format($service['max_price']); ?></div>
                                        <?php else: ?>
                                            <div class="service-price-p">RWF <?php echo number_format($service['price']); ?></div>
                                        <?php endif; ?>
                                        <div class="service-dur-p"><i class="fas fa-clock"></i> <?php echo (int)$service['duration']; ?> mins</div>
                                    </div>
                                    <div class="service-btn-row">
                                        <a href="booking.php?provider_id=<?php echo $provider_id; ?>&service_id=<?php echo (int)$service['id']; ?>" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Book</a>
                                        <?php if ($service['negotiable'] && isLoggedIn() && !isProvider()): ?>
                                            <button type="button" class="btn btn-outline"
                                                data-service-id="<?php echo (int)$service['id']; ?>"
                                                data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                data-min-price="<?php echo (float)$service['min_price']; ?>"
                                                data-max-price="<?php echo (float)$service['max_price']; ?>"
                                                onclick="openOfferModal(this)">
                                                <i class="fas fa-handshake"></i> Offer
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Portfolio -->
                <?php if (!empty($portfolio_images)): ?>
                <div class="card" id="portfolio">
                    <div class="card-head">
                        <div class="card-head-left">
                            <div class="card-head-icon"><i class="fas fa-images"></i></div>
                            <h2>Portfolio</h2>
                        </div>
                        <span class="count-chip"><?php echo $portfolio_count; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="portfolio-grid">
                            <?php foreach ($portfolio_images as $image): ?>
                                <div class="portfolio-tile" onclick="openLightbox('<?php echo htmlspecialchars($image['image_path']); ?>','<?php echo htmlspecialchars(addslashes($image['title'] ?: '')); ?>','<?php echo htmlspecialchars(addslashes($image['description'] ?: '')); ?>')">
                                    <img src="../uploads/portfolio/<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($image['title'] ?: 'Portfolio image'); ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/300x300/EFEBE0/5B685F?text=No+Image'">
                                    <div class="p-zoom"><i class="fas fa-expand"></i></div>
                                    <?php if (!empty($image['title'])): ?>
                                        <div class="p-overlay"><div class="p-title"><?php echo htmlspecialchars($image['title']); ?></div></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($portfolio_count > 6): ?>
                            <div class="portfolio-more">
                                <a href="portfolio.php?id=<?php echo $provider_id; ?>" class="btn btn-outline btn-sm"><i class="fas fa-images"></i> View Full Portfolio (<?php echo $portfolio_count; ?>)</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Video Showcase -->
                <?php if ($portfolio_video): ?>
                <div class="video-card">
                    <div class="vc-info">
                        <div class="video-badge"><i class="fas fa-film"></i> Work Sample</div>
                        <h3><?php echo !empty($portfolio_video['title']) ? htmlspecialchars($portfolio_video['title']) : 'Check out my work'; ?></h3>
                        <p><?php echo !empty($portfolio_video['description']) ? htmlspecialchars($portfolio_video['description']) : 'Watch this short clip to see ' . htmlspecialchars($provider['full_name']) . '\'s skills in action.'; ?></p>
                    </div>
                    <div class="video-player-wrap">
                        <video controls style="width:100%;display:block;" poster="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 675'%3E%3Crect fill='%230B1F17' width='1200' height='675'/%3E%3Ccircle cx='600' cy='337.5' r='70' fill='%23fff' opacity='0.25'/%3E%3Cpolygon points='575,310 575,365 635,337.5' fill='%23fff'/%3E%3C/svg%3E">
                            <source src="../uploads/portfolio/<?php echo htmlspecialchars($portfolio_video['video_path']); ?>" type="video/mp4">
                        </video>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Schedule & Availability -->
                <div class="card" id="schedule">
                    <div class="card-head">
                        <div class="card-head-left">
                            <div class="card-head-icon"><i class="fas fa-calendar-alt"></i></div>
                            <h2>Schedule &amp; Availability</h2>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($next_available_date): ?>
                            <div class="schedule-alert">
                                <i class="fas fa-calendar-check"></i>
                                <div><strong>Next Available</strong><span><?php echo date('l, F j, Y', strtotime($next_available_date)); ?></span></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($schedule_info['working_hours_start'] && $schedule_info['working_hours_end']): ?>
                            <div class="sched-block">
                                <h5>Regular Working Hours</h5>
                                <div class="sched-row"><span>Days</span><strong><?php echo implode(', ', $formatted_working_days); ?></strong></div>
                                <div class="sched-row"><span>Hours</span><strong><?php echo date('g:i A', strtotime($schedule_info['working_hours_start'])); ?> – <?php echo date('g:i A', strtotime($schedule_info['working_hours_end'])); ?></strong></div>
                                <?php if ($schedule_info['break_start'] && $schedule_info['break_end']): ?>
                                    <div class="sched-row"><span>Break Time</span><strong class="warn"><?php echo date('g:i A', strtotime($schedule_info['break_start'])); ?> – <?php echo date('g:i A', strtotime($schedule_info['break_end'])); ?></strong></div>
                                <?php endif; ?>
                                <?php if ($schedule_info['buffer_time']): ?>
                                    <div class="sched-row"><span>Buffer Between Bookings</span><strong><?php echo (int)$schedule_info['buffer_time']; ?> min</strong></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($avg_response_time && $total_responses >= 3): ?>
                            <div class="sched-block">
                                <h5>Response Time</h5>
                                <div style="display:flex;align-items:center;gap:.75rem;">
                                    <div class="response-indicator">
                                        <?php $response_score = min(100, max(0, 100 - ($avg_response_time * 2))); ?>
                                        <div class="response-fill" style="width:<?php echo $response_score; ?>%;"></div>
                                    </div>
                                    <span style="font-weight:700;font-size:.85rem;white-space:nowrap;">
                                        <?php
                                            if ($avg_response_time < 1) echo "Within " . ceil($avg_response_time * 60) . " min";
                                            elseif ($avg_response_time < 24) echo ceil($avg_response_time) . " hrs";
                                            else echo ceil($avg_response_time / 24) . " days";
                                        ?>
                                    </span>
                                </div>
                                <p class="hint" style="margin-top:.5rem;">Based on <?php echo (int)$total_responses; ?> past bookings</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($time_off_periods)): ?>
                            <div class="sched-block">
                                <h5>Upcoming Time Off</h5>
                                <?php foreach ($time_off_periods as $time_off): ?>
                                    <div class="sched-list-item">
                                        <span><i class="fas fa-calendar-times off"></i> <?php echo date('M d', strtotime($time_off['start_date'])); ?> – <?php echo date('M d, Y', strtotime($time_off['end_date'])); ?></span>
                                        <span class="hint"><?php echo htmlspecialchars($time_off['reason']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($availability_exceptions)): ?>
                            <div class="sched-block">
                                <h5>Special Availability</h5>
                                <?php foreach ($availability_exceptions as $exception): ?>
                                    <div class="sched-list-item">
                                        <span><i class="fas <?php echo $exception['is_available'] ? 'fa-calendar-check on' : 'fa-calendar-times off'; ?>"></i> <?php echo date('D, M d', strtotime($exception['date'])); ?></span>
                                        <?php if ($exception['is_available'] && $exception['start_time'] && $exception['end_time']): ?>
                                            <span class="hint"><?php echo date('g:i A', strtotime($exception['start_time'])); ?> – <?php echo date('g:i A', strtotime($exception['end_time'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($visibility['emergency_service'] || $visibility['night_service'] || $visibility['weekend_service']): ?>
                        <div class="sched-block">
                            <h5>Service Options</h5>
                            <div class="option-chips">
                                <?php if ($visibility['emergency_service']): ?><span class="option-chip red"><i class="fas fa-truck-medical"></i> 24/7 Emergency</span><?php endif; ?>
                                <?php if ($visibility['night_service']): ?><span class="option-chip blue"><i class="fas fa-moon"></i> Night Service</span><?php endif; ?>
                                <?php if ($visibility['weekend_service']): ?><span class="option-chip gold"><i class="fas fa-calendar-alt"></i> Weekend Service</span><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="sched-footnote"><i class="fas fa-circle-info"></i> Schedule details are based on the provider's settings and may change.</div>
                    </div>
                </div>

                <!-- Service Categories -->
                <?php if (!empty($categories)): ?>
                <div class="card">
                    <div class="card-head">
                        <div class="card-head-left"><div class="card-head-icon"><i class="fas fa-briefcase"></i></div><h2>Service Categories</h2></div>
                    </div>
                    <div class="card-body">
                        <div class="chips-wrap">
                            <?php foreach ($categories as $category): ?>
                                <span class="cat-chip"><i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i> <?php echo htmlspecialchars($category['name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Achievements -->
                <?php if (!empty($badges)): ?>
                <div class="card">
                    <div class="card-head">
                        <div class="card-head-left"><div class="card-head-icon"><i class="fas fa-certificate"></i></div><h2>Achievements &amp; Badges</h2></div>
                    </div>
                    <div class="card-body">
                        <div class="badges-grid-p">
                            <?php foreach ($badges as $badge): ?>
                                <div class="badge-tile">
                                    <div class="b-icon <?php echo htmlspecialchars($badge['color']); ?>"><i class="fas <?php echo htmlspecialchars($badge['icon']); ?>"></i></div>
                                    <span><?php echo htmlspecialchars($badge['name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Methods -->
                <?php if (!empty($paymentMethods)): ?>
                <div class="card">
                    <div class="card-head">
                        <div class="card-head-left"><div class="card-head-icon"><i class="fas fa-credit-card"></i></div><h2>Payment Methods</h2></div>
                    </div>
                    <div class="card-body">
                        <div class="payment-list">
                            <?php
                                $methodIcons = ['cash'=>'fa-money-bill-wave','mobile_money'=>'fa-mobile-alt','bank_transfer'=>'fa-university','card'=>'fa-credit-card','momo'=>'fa-money-bill','orange_money'=>'fa-circle','airtel_money'=>'fa-circle'];
                            ?>
                            <?php foreach ($paymentMethods as $method): ?>
                                <div class="payment-chip">
                                    <div class="pm-icon"><i class="fas <?php echo $methodIcons[$method['method_type']] ?? 'fa-wallet'; ?>"></i></div>
                                    <strong><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$method['method_type']))); ?></strong>
                                    <?php if ($method['is_default']): ?><span class="default-tag">Default</span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Service Areas -->
                <?php if (!empty($serviceAreas)): ?>
                <div class="card" id="areas">
                    <div class="card-head">
                        <div class="card-head-left"><div class="card-head-icon"><i class="fas fa-map-marked-alt"></i></div><h2>Service Areas</h2></div>
                        <span class="count-chip"><?php echo count($serviceAreas); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="areas-grid">
                            <?php foreach ($serviceAreas as $area): ?>
                                <div class="area-card <?php echo !empty($area['is_primary']) ? 'primary' : ''; ?>">
                                    <div class="area-head">
                                        <div class="area-name"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($area['area_name'] ?? 'Unnamed area'); ?></div>
                                        <?php if (!empty($area['is_primary'])): ?><span class="primary-tag">Primary</span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($area['radius_km'])): ?>
                                        <div class="area-coverage"><i class="fas fa-expand-alt"></i> <?php echo htmlspecialchars($area['radius_km']); ?> km coverage radius</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reviews -->
                <div class="card" id="reviews">
                    <div class="card-head">
                        <div class="card-head-left"><div class="card-head-icon"><i class="fas fa-star"></i></div><h2>Reviews</h2></div>
                        <span class="count-chip"><?php echo count($reviews); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($reviews)): ?>
                            <div class="rating-summary">
                                <div class="rs-score">
                                    <div class="n"><?php echo number_format($provider['average_rating'], 1); ?></div>
                                    <div class="stars"><?php echo str_repeat('★', round($provider['average_rating'])) . str_repeat('☆', 5 - round($provider['average_rating'])); ?></div>
                                    <div class="count"><?php echo (int)$provider['total_reviews']; ?> reviews</div>
                                </div>
                                <div class="rs-bars">
                                    <?php for ($i = 5; $i >= 1; $i--):
                                        $percentage = $provider['total_reviews'] > 0 ? ($rating_breakdown[$i] / $provider['total_reviews']) * 100 : 0;
                                    ?>
                                        <div class="rs-bar-row">
                                            <span class="lbl"><?php echo $i; ?> star</span>
                                            <div class="rs-bar-track"><div class="rs-bar-fill" style="width:<?php echo $percentage; ?>%;"></div></div>
                                            <span class="ct"><?php echo $rating_breakdown[$i]; ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-top">
                                        <div class="reviewer">
                                            <div class="reviewer-avatar">
                                                <?php $client_image = $review['profile_image'] ?? ''; $client_initials = strtoupper(substr($review['client_name'] ?? 'C', 0, 1)); ?>
                                                <?php if (!empty($client_image)): ?>
                                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($client_image); ?>" alt="" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($client_initials); ?>';">
                                                <?php else: ?><?php echo $client_initials; ?><?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="reviewer-name"><?php echo htmlspecialchars($review['client_name']); ?></div>
                                                <div class="review-stars-mini"><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></div>
                                            </div>
                                        </div>
                                        <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-p">
                                <i class="fas fa-star"></i>
                                <h4>No Reviews Yet</h4>
                                <p>Be the first to book and review this provider.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Similar Providers -->
                <?php if (!empty($similar_providers)): ?>
                <div class="card">
                    <div class="card-head">
                        <div class="card-head-left"><div class="card-head-icon"><i class="fas fa-users"></i></div><h2>Similar Providers</h2></div>
                    </div>
                    <div class="card-body">
                        <div class="similar-scroll">
                            <?php foreach ($similar_providers as $similar): ?>
                                <a href="provider-profile.php?id=<?php echo (int)$similar['id']; ?>" class="similar-card" style="text-decoration:none;">
                                    <div class="similar-top">
                                        <div class="similar-avatar">
                                            <?php $simg = $similar['profile_image'] ?? ''; $sinit = strtoupper(substr($similar['full_name'] ?? '', 0, 1)) ?: '?'; ?>
                                            <?php if (!empty($simg)): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($simg); ?>" alt="" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($sinit); ?>';">
                                            <?php else: ?><?php echo $sinit; ?><?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="similar-name">
                                                <?php echo htmlspecialchars($similar['full_name']); ?>
                                                <?php if ($similar['is_verified'] || $similar['user_verified']): ?><i class="fas fa-circle-check" style="color:var(--accent-2);font-size:.7rem;"></i><?php endif; ?>
                                            </div>
                                            <div class="similar-prof"><?php echo htmlspecialchars($similar['profession']); ?></div>
                                        </div>
                                    </div>
                                    <div class="similar-stats-row">
                                        <span><i class="fas fa-star"></i><?php echo number_format($similar['average_rating'], 1); ?></span>
                                        <span><i class="fas fa-comment"></i><?php echo (int)$similar['total_reviews']; ?></span>
                                        <span><i class="fas fa-briefcase"></i><?php echo (int)($similar['experience_years'] ?? 0); ?>y</span>
                                    </div>
                                    <?php if (!empty($similar['avg_service_price'])): ?>
                                        <div class="similar-price-tag">From RWF <?php echo number_format($similar['avg_service_price'], 0); ?></div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- ══ SIDEBAR ══ -->
            <div class="side-col">
                <div class="side-card">
                    <h3><i class="fas fa-shield-halved"></i> Trust &amp; Safety</h3>
                    <div class="trust-row">
                        <div class="trust-item"><i class="fas fa-circle-check"></i> Identity verified</div>
                        <div class="trust-item"><i class="fas fa-lock"></i> Secure booking process</div>
                        <div class="trust-item"><i class="fas fa-headset"></i> Platform support included</div>
                        <?php if ($total_completed_jobs > 0): ?>
                            <div class="trust-item"><i class="fas fa-award"></i> <?php echo (int)$total_completed_jobs; ?> jobs completed</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="side-card">
                    <h3><i class="fas fa-chart-simple"></i> At a Glance</h3>
                    <div class="stats-grid-side">
                        <div class="stat-box-mini"><div class="num"><?php echo (int)$provider['total_reviews']; ?></div><div class="lbl">Reviews</div></div>
                        <div class="stat-box-mini"><div class="num"><?php echo (int)$total_completed_jobs; ?></div><div class="lbl">Completed</div></div>
                        <div class="stat-box-mini"><div class="num"><?php echo count($services); ?></div><div class="lbl">Services</div></div>
                        <?php if ($avg_service_price > 0): ?>
                            <div class="stat-box-mini"><div class="num" style="font-size:.95rem;">RWF <?php echo number_format($avg_service_price, 0); ?></div><div class="lbl">Avg Price</div></div>
                        <?php else: ?>
                            <div class="stat-box-mini"><div class="num"><?php echo (int)$total_bookings; ?></div><div class="lbl">Bookings</div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($active_links > 0): ?>
                <div class="side-card">
                    <h3><i class="fas fa-share-nodes"></i> Connect</h3>
                    <div class="connect-grid">
                        <?php foreach ($social_links as $key => $link):
                            if ($key === 'whatsapp' && !$visibility['show_whatsapp']) continue;
                            if (empty($provider[$link['field']])) continue;
                            $url = $provider[$link['field']];
                            if ($key === 'website' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
                            elseif ($key === 'whatsapp' && !preg_match('~^https?://~i', $url)) $url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $url);
                        ?>
                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" class="social-btn" style="--social-color: <?php echo htmlspecialchars($link['color']); ?>;">
                                <i class="<?php echo htmlspecialchars($link['icon']); ?>"></i> <?php echo htmlspecialchars($link['label']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!empty($provider['other_social'])): $url = $provider['other_social']; if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url; ?>
                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" class="social-btn">
                                <i class="fas fa-link"></i> <?php echo htmlspecialchars($provider['other_social_label'] ?? 'More'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="side-card" style="background:linear-gradient(135deg, var(--ink), #12291F); border:none;">
                    <h3 style="color:#fff;"><i class="fas fa-calendar-check" style="color:var(--gold);"></i> Book Now</h3>
                    <p style="font-size:.82rem;color:rgba(255,255,255,.75);line-height:1.6;margin-bottom:1rem;">Secure your spot with <?php echo htmlspecialchars(explode(' ', $provider['full_name'])[0]); ?> today.</p>
                    <a href="booking.php?provider_id=<?php echo $provider_id; ?><?php echo !empty($share_id) ? '&share_id=' . intval($share_id) : ''; ?>" class="btn btn-primary btn-block">
                        <i class="fas fa-arrow-right"></i> Start Booking
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Mobile Sticky CTA ───────────────────────────────────────── -->
<div class="mobile-cta-bar">
    <?php if (isLoggedIn() && !isProvider()): ?>
        <a href="messages.php?with=<?php echo (int)$provider['user_id']; ?>" class="btn btn-secondary"><i class="fas fa-comment-dots"></i></a>
    <?php endif; ?>
    <a href="booking.php?provider_id=<?php echo $provider_id; ?><?php echo !empty($share_id) ? '&share_id=' . intval($share_id) : ''; ?>" class="btn btn-primary btn-block"><i class="fas fa-calendar-check"></i> Book Now</a>
</div>

<!-- ── Lightbox Modal ──────────────────────────────────────────── -->
<div class="modal-overlay" id="lightboxOverlay" onclick="if(event.target===this) closeLightbox()">
    <div class="modal-box wide">
        <div class="modal-head">
            <h4><i class="fas fa-image"></i> Portfolio</h4>
            <button class="modal-close" onclick="closeLightbox()"><i class="fas fa-xmark"></i></button>
        </div>
        <img src="" alt="" class="lightbox-img" id="lightboxImg">
        <div class="lightbox-caption" id="lightboxCaption"></div>
    </div>
</div>

<!-- ── Offer Modal ─────────────────────────────────────────────── -->
<div class="modal-overlay" id="offerOverlay" onclick="if(event.target===this) closeOfferModal()">
    <div class="modal-box">
        <div class="modal-head">
            <h4><i class="fas fa-handshake"></i> Send Price Offer</h4>
            <button class="modal-close" onclick="closeOfferModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body-p">
            <div class="field-p">
                <label>Service</label>
                <input type="text" class="input-p" id="offerServiceName" disabled>
            </div>
            <div class="info-box-p" id="offerRangeBox"><strong>Price Range:</strong> <span id="offerRangeText"></span></div>
            <div class="field-p price-prefix">
                <label>Your Offer Price <span style="color:var(--accent-3)">*</span></label>
                <input type="number" class="input-p" id="offerPriceInput" step="100">
                <div class="hint" id="offerPriceHint"></div>
            </div>
            <div class="field-p">
                <label>Additional Message (optional)</label>
                <textarea id="offerNoteInput" placeholder="e.g. I need this service by next week..."></textarea>
            </div>
            <div class="info-box-p">
                <strong>How it works:</strong>
                <ul>
                    <li>The provider reviews your offer</li>
                    <li>They can accept, decline, or counter-offer</li>
                    <li>You can negotiate up to 3 rounds</li>
                    <li>Price locks once both sides agree</li>
                </ul>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" onclick="closeOfferModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitOfferNow()"><i class="fas fa-paper-plane"></i> Send Offer</button>
        </div>
    </div>
</div>

<div class="toast-p" id="toastP"><i class="fas fa-circle-check"></i><span id="toastPText"></span></div>

<script>
    /* ── Toast helper ─────────────────────────────────────────── */
    function showToast(msg, isError) {
        const t = document.getElementById('toastP');
        const icon = t.querySelector('i');
        document.getElementById('toastPText').textContent = msg;
        t.classList.toggle('error', !!isError);
        icon.className = isError ? 'fas fa-circle-exclamation' : 'fas fa-circle-check';
        t.classList.add('show');
        clearTimeout(window.__toastTimer);
        window.__toastTimer = setTimeout(() => t.classList.remove('show'), 4200);
    }
    document.addEventListener('DOMContentLoaded', function () {
        const s = document.getElementById('serverToastSuccess');
        const e = document.getElementById('serverToastError');
        if (s && s.textContent.trim()) showToast(s.textContent.trim(), false);
        if (e && e.textContent.trim()) showToast(e.textContent.trim(), true);
    });

    /* ── Quick nav active state on scroll ─────────────────────── */
    (function () {
        const links = Array.from(document.querySelectorAll('#quickNav a'));
        const targets = links.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);
        if (!targets.length) return;
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = '#' + entry.target.id;
                    links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === id));
                }
            });
        }, { rootMargin: '-40% 0px -50% 0px' });
        targets.forEach(t => obs.observe(t));
    })();

    /* ── Lightbox ──────────────────────────────────────────────── */
    function openLightbox(imgPath, title, description) {
        document.getElementById('lightboxImg').src = '../uploads/portfolio/' + imgPath;
        let html = '';
        if (title) html += '<h5>' + title + '</h5>';
        if (description) html += '<p>' + description + '</p>';
        document.getElementById('lightboxCaption').innerHTML = html;
        document.getElementById('lightboxOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        document.getElementById('lightboxOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    /* ── Offer Modal ───────────────────────────────────────────── */
    let __offerCtx = { serviceId: null, min: 0, max: 0 };
    function fmtNum(n) { return new Intl.NumberFormat('en-US').format(Math.round(n)); }
    function openOfferModal(btn) {
        __offerCtx.serviceId = btn.dataset.serviceId;
        __offerCtx.min = parseFloat(btn.dataset.minPrice) || 0;
        __offerCtx.max = parseFloat(btn.dataset.maxPrice) || 0;
        document.getElementById('offerServiceName').value = btn.dataset.serviceName || '';
        document.getElementById('offerRangeText').textContent = 'RWF ' + fmtNum(__offerCtx.min) + ' – RWF ' + fmtNum(__offerCtx.max);
        const priceInput = document.getElementById('offerPriceInput');
        priceInput.min = __offerCtx.min; priceInput.max = __offerCtx.max; priceInput.value = '';
        document.getElementById('offerPriceHint').textContent = 'Enter a price between RWF ' + fmtNum(__offerCtx.min) + ' and RWF ' + fmtNum(__offerCtx.max);
        document.getElementById('offerNoteInput').value = '';
        document.getElementById('offerOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeOfferModal() {
        document.getElementById('offerOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }
    function submitOfferNow() {
        const price = parseFloat(document.getElementById('offerPriceInput').value);
        const note = document.getElementById('offerNoteInput').value;
        if (!price || price < __offerCtx.min || price > __offerCtx.max) {
            showToast('Please enter a valid price within the range', true);
            return;
        }
        fetch('../api/service_offers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=create_offer&service_id=' + __offerCtx.serviceId + '&offered_price=' + price + '&notes=' + encodeURIComponent(note)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Offer sent! The provider will review it soon.', false);
                closeOfferModal();
            } else {
                showToast(data.message || 'Failed to send offer', true);
            }
        })
        .catch(() => showToast('Error sending offer. Please try again.', true));
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeLightbox(); closeOfferModal(); }
    });

    /* ── Behaviour tracking (kept from original) ──────────────── */
    (function () {
        let pageStartTime = Date.now();
        const pageUrl = window.location.href;
        const pageTitle = document.title;

        function trackPageView() {
            fetch('../api/track_user_behavior.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'track_page_view', page_url: pageUrl, page_title: pageTitle, referrer: document.referrer })
            }).catch(() => {});
        }
        function startPageSession() {
            fetch('../api/track_user_behavior.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'start_page_session', page_url: pageUrl, page_start: new Date(pageStartTime).toISOString() })
            }).catch(() => {});
        }
        function endPageSession() {
            const pageEndTime = Date.now();
            fetch('../api/track_user_behavior.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'end_page_session', page_url: pageUrl,
                    time_spent_seconds: Math.floor((pageEndTime - pageStartTime) / 1000),
                    page_start: new Date(pageStartTime).toISOString(), page_end: new Date(pageEndTime).toISOString()
                })
            }).catch(() => {});
        }
        function trackProviderView() {
            fetch('../api/track_user_behavior.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'track_provider_view', provider_id: '<?php echo (int)$provider_id; ?>' })
            }).catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', function () {
            trackPageView();
            startPageSession();
            setTimeout(trackProviderView, 1000);
        });
        window.addEventListener('beforeunload', endPageSession);
        window.addEventListener('unload', endPageSession);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { endPageSession(); }
            else { pageStartTime = Date.now(); startPageSession(); }
        });
    })();
</script>
</body>
</html>