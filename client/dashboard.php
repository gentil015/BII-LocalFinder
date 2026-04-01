<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a client
if (!isLoggedIn()) {
    redirect('login.php');
}

if (isProvider()) {
    redirect('provider/dashboard.php');
}

$db = Database::getInstance()->getConnection();

// Load system settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

// Get all system settings
$system_settings = [
    'platform_name' => getSetting($db, 'platform_name', 'BII LocalFinder'),
    'contact_email' => getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
    'contact_phone' => getSetting($db, 'contact_phone', '+250 788 123 456'),
    'platform_description' => getSetting($db, 'platform_description', 'Connecting clients with trusted local service providers'),
    'timezone' => getSetting($db, 'timezone', 'Africa/Kigali'),
    
    // Booking settings
    'max_pending_time' => intval(getSetting($db, 'max_pending_time', '15')),
    'allow_booking_editing' => getSetting($db, 'allow_booking_editing', '1'),
    'auto_cancel_unconfirmed' => getSetting($db, 'auto_cancel_unconfirmed', '1'),
    'require_rating_after_completion' => getSetting($db, 'require_rating_after_completion', '0'),
    'max_cancellations_per_month' => intval(getSetting($db, 'max_cancellations_per_month', '3')),
    
    // Payment settings
    'enable_commission' => getSetting($db, 'enable_commission', '0'),
    'commission_rate' => floatval(getSetting($db, 'commission_rate', '10')),
    
    // Notification settings
    'enable_email_notifications' => getSetting($db, 'enable_email_notifications', '1'),
    'enable_sms_notifications' => getSetting($db, 'enable_sms_notifications', '0'),
];

// Get client information
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

// Get booking statistics
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'confirmed'");
$stmt->execute([$_SESSION['user_id']]);
$confirmed_bookings = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$completed_bookings = $stmt->fetch()['total'];

// Get monthly cancellation count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE client_id = ? AND status = 'cancelled' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stmt->execute([$_SESSION['user_id']]);
$monthly_cancellations = $stmt->fetch()['total'];

// Get recent bookings with system settings consideration
$stmt = $db->prepare("
    SELECT b.*, 
           sp.profession, sp.location, sp.availability, sp.hourly_rate,
           u.full_name as provider_name, u.phone as provider_phone, 
           u.email as provider_email, u.profile_image as provider_image
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE b.client_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_bookings = $stmt->fetchAll();

// Get reviews written by client
$stmt = $db->prepare("
    SELECT r.*, 
           sp.profession,
           u.full_name as provider_name, u.profile_image as provider_image
    FROM reviews r
    JOIN service_providers sp ON r.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE r.client_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$my_reviews = $stmt->fetchAll();

// Recommended providers with intelligent ranking (carousel rotation)
$recommended_providers = [];
try {
    // Check which columns exist to build dynamic query
    $cols = $db->query("SHOW COLUMNS FROM service_providers")->fetchAll(PDO::FETCH_COLUMN);
    
    $hasSearchBoost = in_array('search_boost', $cols, true);
    $hasFeatured = in_array('is_featured', $cols, true);
    $hasVerification = in_array('is_verified', $cols, true);
    
    // Check if favorites table exists
    $favoriteTableExists = false;
    try {
        $db->query("SELECT 1 FROM favorites LIMIT 1");
        $favoriteTableExists = true;
    } catch (Exception $e) {
        $favoriteTableExists = false;
    }
    
    // Build ranking score calculation based on available columns
    $rankingCase = "CASE ";
    if ($favoriteTableExists) {
        $rankingCase .= "WHEN f.id IS NOT NULL THEN 2000 ";
    }
    if ($hasFeatured) {
        $rankingCase .= "WHEN sp.is_featured = 1 THEN 1500 ";
    }
    if ($hasSearchBoost) {
        $rankingCase .= "WHEN sp.search_boost > 0 THEN (1000 + COALESCE(sp.search_boost, 0)) ";
    }
    $rankingCase .= "WHEN sp.average_rating >= 4.5 THEN 800 ";
    $rankingCase .= "WHEN sp.average_rating >= 4.0 THEN 700 ";
    $rankingCase .= "WHEN sp.average_rating >= 3.5 THEN 600 ";
    $rankingCase .= "ELSE 500 END as ranking_score";

    // Build intelligent query with multiple ranking factors
    $sql = "SELECT sp.*, u.full_name, u.profile_image, " . $rankingCase . ", ";
    if ($favoriteTableExists) {
        $sql .= "(f.id IS NOT NULL) as is_favorite ";
    } else {
        $sql .= "0 as is_favorite ";
    }
    $sql .= "FROM service_providers sp ";
    $sql .= "JOIN users u ON sp.user_id = u.id ";
    if ($favoriteTableExists) {
        $sql .= "LEFT JOIN favorites f ON sp.id = f.provider_id AND f.client_id = ? ";
    }
    $sql .= "WHERE sp.is_active = 1 AND sp.is_banned = 0 AND u.user_type = 'provider' ";
    $sql .= "ORDER BY ranking_score DESC, sp.average_rating DESC, sp.total_reviews DESC, sp.created_at DESC LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $params = $favoriteTableExists ? [$_SESSION['user_id']] : [];
    $stmt->execute($params);
    $recommended_providers = $stmt->fetchAll();
    
} catch (Throwable $e) {
    error_log('client dashboard ranking query error: ' . $e->getMessage());
    
    // Fallback: Simple query without advanced features
    try {
        $sql_fallback = "
            SELECT sp.*, u.full_name, u.profile_image, 500 as ranking_score
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.is_active = 1 AND sp.is_banned = 0 AND u.user_type = 'provider'
            ORDER BY sp.average_rating DESC, sp.total_reviews DESC, sp.created_at DESC 
            LIMIT 10
        ";
        
        $stmt = $db->prepare($sql_fallback);
        $stmt->execute();
        $recommended_providers = $stmt->fetchAll();
    } catch (Throwable $e2) {
        error_log('client dashboard fallback query error: ' . $e2->getMessage());
        $recommended_providers = [];
    }
}

// Get all categories with provider count
$categories = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT sp.profession as category, 
               COUNT(DISTINCT sp.id) as provider_count,
               AVG(sp.average_rating) as avg_rating
        FROM service_providers sp
        WHERE sp.is_active = 1 AND sp.is_banned = 0
        GROUP BY sp.profession
        ORDER BY provider_count DESC
        LIMIT 12
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Categories query error: ' . $e->getMessage());
    $categories = [];
}

// build list of services per category for display
$category_services = [];
if (!empty($categories)) {
    try {
        foreach ($categories as $cat) {
            $stmt = $db->prepare(
                "SELECT ps.*, sp.profession, u.full_name as provider_name, u.profile_image as provider_image,
                        CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
                 FROM provider_services ps
                 JOIN service_providers sp ON ps.provider_id = sp.id
                 JOIN users u ON sp.user_id = u.id
                 LEFT JOIN favorites f ON f.provider_id = sp.id AND f.client_id = ?
                 WHERE sp.is_active = 1 AND sp.is_banned = 0 AND sp.profession = ? AND ps.is_available = 1
                 ORDER BY is_favorite DESC, ps.created_at DESC
                 LIMIT 6"
            );
            $stmt->execute([$_SESSION['user_id'], $cat['category']]);
            $services = $stmt->fetchAll();
            if (!empty($services)) {
                $category_services[$cat['category']] = $services;
            }
        }
    } catch (Throwable $e) {
        error_log('Category services query error: ' . $e->getMessage());
    }
}

// Get featured/top services based on client's interests and booking history
$featured_services = [];
try {
    // First, get professions the client has already booked
    $stmt = $db->prepare("
        SELECT DISTINCT sp.profession
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.client_id = ?
        GROUP BY sp.profession
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $booked_professions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($booked_professions)) {
        // If client has bookings, show featured services from similar professions (lower threshold)
        $placeholders = implode(',', array_fill(0, count($booked_professions), '?'));
        $params = array_merge([$_SESSION['user_id']], $booked_professions, [$_SESSION['user_id']]);
        
        $stmt = $db->prepare("
            SELECT sp.*, u.full_name, u.profile_image, sp.profession, sp.hourly_rate,
                   CASE 
                       WHEN b.id IS NOT NULL THEN 1
                       ELSE 0
                   END as previously_booked,
                   CASE 
                       WHEN f.id IS NOT NULL THEN 1
                       ELSE 0
                   END as is_favorite
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN bookings b ON b.provider_id = sp.id AND b.client_id = ? AND b.status = 'completed'
            LEFT JOIN favorites f ON f.provider_id = sp.id AND f.client_id = ?
            WHERE sp.is_active = 1 AND sp.is_banned = 0 
            AND sp.profession IN ($placeholders)
            ORDER BY 
                is_favorite DESC,
                previously_booked DESC,
                sp.average_rating DESC, 
                sp.total_reviews DESC,
                sp.created_at DESC
            LIMIT 8
        ");
        $stmt->execute($params);
        $featured_services = $stmt->fetchAll();
    }
    
    // If no services found from booked professions, show top-rated general services (3.5+ rating)
    if (empty($featured_services)) {
        $stmt = $db->prepare("
            SELECT sp.*, u.full_name, u.profile_image, sp.profession, sp.hourly_rate,
                   0 as previously_booked,
                   CASE 
                       WHEN f.id IS NOT NULL THEN 1
                       ELSE 0
                   END as is_favorite
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN favorites f ON f.provider_id = sp.id AND f.client_id = ?
            WHERE sp.is_active = 1 AND sp.is_banned = 0 
            AND sp.average_rating >= 3.5
            ORDER BY 
                is_favorite DESC,
                sp.average_rating DESC, 
                sp.total_reviews DESC,
                sp.created_at DESC
            LIMIT 8
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $featured_services = $stmt->fetchAll();
    }
    
    // Final fallback: show any active providers if still no services found
    if (empty($featured_services)) {
        $stmt = $db->prepare("
            SELECT sp.*, u.full_name, u.profile_image, sp.profession, sp.hourly_rate,
                   0 as previously_booked,
                   CASE 
                       WHEN f.id IS NOT NULL THEN 1
                       ELSE 0
                   END as is_favorite
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN favorites f ON f.provider_id = sp.id AND f.client_id = ?
            WHERE sp.is_active = 1 AND sp.is_banned = 0 
            ORDER BY 
                is_favorite DESC,
                sp.average_rating DESC, 
                sp.total_reviews DESC,
                sp.created_at DESC
            LIMIT 8
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $featured_services = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    error_log('Featured services query error: ' . $e->getMessage());
    $featured_services = [];
}

// ==================== AI SERVICE RECOMMENDATIONS ====================
// Advanced recommendation algorithm based on: Previous bookings, Location, Popular services
$ai_recommendations = [];
try {
    // Get client's location
    $clientLocation = $client['location'] ?? '';

    // Step 1: Get professions from ANY previous bookings (not just completed/confirmed)
    // This allows recommendations even for clients with only pending bookings
    $stmt = $db->prepare("
        SELECT DISTINCT sp.profession
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.client_id = ?
        GROUP BY sp.profession
        ORDER BY COUNT(*) DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $all_booked_professions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Also get completed/confirmed professions for higher scoring
    $stmt = $db->prepare("
        SELECT DISTINCT sp.profession
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        WHERE b.client_id = ? AND b.status IN ('completed', 'confirmed')
        GROUP BY sp.profession
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completed_professions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build recommendation score using multiple ranking factors
    $recommendation_query = "
        SELECT
            sp.*,
            u.full_name,
            u.profile_image,
            u.location as provider_location,
            sp.profession,
            sp.hourly_rate,
            CASE
                WHEN f.id IS NOT NULL THEN 1
                ELSE 0
            END as is_favorite,
            -- Scoring algorithm for AI recommendations
            (
                -- Factor 1: Previously completed bookings in same profession (highest priority)
                CASE
                    WHEN sp.profession IN (" . (empty($completed_professions) ? "''" : implode(',', array_fill(0, count($completed_professions), '?'))) . ")
                    THEN 3000
                    ELSE 0
                END +

                -- Factor 2: Any previous bookings in same profession (medium priority)
                CASE
                    WHEN sp.profession IN (" . (empty($all_booked_professions) ? "''" : implode(',', array_fill(0, count($all_booked_professions), '?'))) . ")
                    THEN 2000
                    ELSE 0
                END +

                -- Factor 3: Location proximity (same location)
                CASE
                    WHEN u.location = ? AND u.location != ''
                    THEN 2500
                    ELSE 0
                END +

                -- Factor 4: Popular services (high rating and reviews)
                CASE
                    WHEN sp.average_rating >= 4.8 THEN 1500
                    WHEN sp.average_rating >= 4.5 THEN 1200
                    WHEN sp.average_rating >= 4.0 THEN 1000
                    WHEN sp.average_rating >= 3.5 THEN 700
                    ELSE 0
                END +

                -- Factor 5: Service popularity (review count)
                CASE
                    WHEN sp.total_reviews >= 100 THEN 800
                    WHEN sp.total_reviews >= 50 THEN 600
                    WHEN sp.total_reviews >= 20 THEN 400
                    WHEN sp.total_reviews >= 5 THEN 200
                    ELSE 0
                END +

                -- Factor 6: Recent activity (fresh providers)
                CASE
                    WHEN DATEDIFF(CURRENT_DATE(), DATE(sp.updated_at)) <= 7 THEN 300
                    WHEN DATEDIFF(CURRENT_DATE(), DATE(sp.updated_at)) <= 30 THEN 150
                    ELSE 0
                END +

                -- Factor 7: Availability
                CASE
                    WHEN sp.availability = 'available' THEN 200
                    ELSE 0
                END +

                -- Factor 8: Not previously booked (diversity)
                CASE
                    WHEN pb.id IS NULL THEN 100
                    ELSE 0
                END
            ) as recommendation_score

        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN favorites f ON f.provider_id = sp.id AND f.client_id = ?
        LEFT JOIN bookings pb ON pb.provider_id = sp.id AND pb.client_id = ? AND pb.status IN ('completed', 'confirmed')

        WHERE sp.is_active = 1
            AND sp.is_banned = 0
            AND u.user_type = 'provider'
            AND sp.average_rating >= 3.0

        ORDER BY recommendation_score DESC, sp.average_rating DESC, sp.total_reviews DESC
        LIMIT 12
    ";

    // Build parameters array dynamically
    $params = [];
    if (!empty($completed_professions)) {
        $params = array_merge($params, $completed_professions);
    }
    if (!empty($all_booked_professions)) {
        $params = array_merge($params, $all_booked_professions);
    }
    $params[] = $clientLocation;
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];

    $stmt = $db->prepare($recommendation_query);
    $stmt->execute($params);
    $ai_recommendations = $stmt->fetchAll();

    // If still no recommendations, provide fallback popular providers
    if (empty($ai_recommendations)) {
        $fallback_query = "
            SELECT sp.*, u.full_name, u.profile_image, u.location as provider_location,
                   sp.profession, sp.hourly_rate,
                   CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite,
                   (sp.average_rating * 100 + sp.total_reviews * 10) as recommendation_score
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN favorites f ON f.provider_id = sp.id AND f.client_id = ?
            WHERE sp.is_active = 1 AND sp.is_banned = 0 AND u.user_type = 'provider'
                  AND sp.average_rating >= 4.0
            ORDER BY sp.average_rating DESC, sp.total_reviews DESC
            LIMIT 8
        ";
        $stmt = $db->prepare($fallback_query);
        $stmt->execute([$_SESSION['user_id']]);
        $ai_recommendations = $stmt->fetchAll();
    }

} catch (Throwable $e) {
    error_log('AI Recommendations query error: ' . $e->getMessage());
    error_log('Error details: ' . $e->getFile() . ' - Line: ' . $e->getLine());
    $ai_recommendations = [];
}

// Get top testimonials/reviews from the platform
$testimonials = [];
try {
    $stmt = $db->prepare("
        SELECT r.comment, r.rating, u.full_name, u.profile_image, 
               sp.profession, r.created_at
        FROM reviews r
        JOIN users u ON r.client_id = u.id
        JOIN service_providers sp ON r.provider_id = sp.id
        WHERE r.rating >= 4
        ORDER BY r.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Testimonials query error: ' . $e->getMessage());
    $testimonials = [];
}

// Get platform statistics
$platform_stats = [
    'total_providers' => 0,
    'total_services_completed' => 0,
    'avg_rating' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM service_providers WHERE is_active = 1 AND is_banned = 0");
    $stmt->execute();
    $platform_stats['total_providers'] = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE status = 'completed'");
    $stmt->execute();
    $platform_stats['total_services_completed'] = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT AVG(average_rating) as avg FROM service_providers WHERE is_active = 1 AND is_banned = 0");
    $stmt->execute();
    $result = $stmt->fetch();
    $platform_stats['avg_rating'] = round($result['avg'] ?? 0, 1);
} catch (Throwable $e) {
    error_log('Platform stats query error: ' . $e->getMessage());
}

// Check if user needs to complete verification
$needs_email_verification = getSetting($db, 'email_verification', '1') && !$client['email_verified'];
$needs_phone_verification = getSetting($db, 'phone_verification', '0') && !$client['phone_verified'];

// Handle booking cancellation with system settings validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check monthly cancellation limit
    if ($monthly_cancellations >= $system_settings['max_cancellations_per_month']) {
        $error = "You have reached your monthly cancellation limit ({$system_settings['max_cancellations_per_month']}). Please contact support.";
    } else {
        // Verify booking belongs to client and is pending/confirmed
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND client_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
            if ($stmt->execute([$booking_id])) {
                $success = "Booking cancelled successfully";
                // Log activity
                logActivity($db, $_SESSION['user_id'], 'booking_cancelled', "Cancelled booking #{$booking_id}");
                // Refresh page
                header("Location: dashboard.php?cancelled=1");
                exit();
            }
        } else {
            $error = "Booking not found or cannot be cancelled";
        }
    }
}

// Handle booking editing if allowed by system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_booking'])) {
    if (!$system_settings['allow_booking_editing']) {
        $error = "Booking editing is currently disabled by system administrator";
    } else {
        $booking_id = intval($_POST['booking_id']);
        // Implementation for booking editing would go here
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - <?php echo $system_settings['platform_name']; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;

            /* Design tokens */
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --border: #e8eaf0;
            --border-subtle: #f0f2f7;
            --text-primary: #0f1117;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --accent: #0d6efd;
            --accent-light: #eff4ff;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --shadow-xs: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.10), 0 4px 12px rgba(0,0,0,0.06);
            --transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: var(--surface-2);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            border-right: 1px solid var(--border);
            color: var(--text-primary);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: var(--transition);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .sidebar-header h2 {
            margin: 0;
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--accent);
            letter-spacing: -0.3px;
        }

        .sidebar-header p {
            margin: 0.3rem 0 0;
            color: var(--text-muted);
            font-size: 0.78rem;
            font-weight: 500;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0.75rem 0.75rem;
            margin: 0;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-menu li { margin: 0.15rem 0; }

        .sidebar-menu a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            gap: 0.65rem;
        }

        .sidebar-menu a:hover {
            background: var(--accent-light);
            color: var(--accent);
        }

        .sidebar-menu a.active {
            background: var(--accent);
            color: white;
            font-weight: 600;
        }

        .sidebar-menu i {
            width: 18px;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.75rem 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed-width); }

        /* ── TOP BAR ── */
        .top-bar {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.75rem;
            margin-bottom: 1.75rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
        }

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-text h1 {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .welcome-text p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.85rem;
        }

        /* ── STATS GRID ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-xs);
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--primary);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0 1rem;
            font-size: 1.15rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 0.2rem;
            color: var(--text-primary);
            letter-spacing: -1px;
            font-variant-numeric: tabular-nums;
        }

        .stat-card p {
            color: var(--text-secondary);
            margin: 0;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── CARDS ── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-xs);
            border: 1px solid var(--border) !important;
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .card-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: -0.2px;
        }

        /* ── BOOKING ITEMS ── */
        .booking-item {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            margin-bottom: 0.875rem;
            transition: var(--transition);
            background: var(--surface);
        }

        .booking-item:hover {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.06);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.875rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .provider-info {
            display: flex;
            gap: 0.875rem;
            flex: 1;
            align-items: center;
        }

        .provider-avatar {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .provider-avatar img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        /* ── BADGES ── */
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 100px;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge.pending  { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .badge.confirmed { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge.completed { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .badge.cancelled { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* ── BUTTONS ── */
        .booking-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.875rem;
        }

        .btn-sm {
            padding: 0.4rem 0.875rem;
            font-size: 0.78rem;
            border-radius: var(--radius-sm);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: var(--transition);
            font-weight: 600;
            cursor: pointer;
        }

        .btn-view { background: var(--accent-light); color: var(--accent); }
        .btn-view:hover { background: var(--accent); color: white; text-decoration: none; }
        .btn-review { background: #fffbeb; color: #92400e; }
        .btn-review:hover { background: #ffc107; color: #000; text-decoration: none; }
        .btn-cancel { background: #fef2f2; color: #dc3545; }
        .btn-cancel:hover { background: #dc3545; color: white; }

        /* ── PROVIDER CARDS ── */
        .provider-card {
            text-align: center;
            padding: 1.5rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            background: var(--surface);
        }

        .provider-card-link { display: block; text-decoration: none; color: inherit; }

        .provider-card:hover {
            border-color: var(--accent);
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .provider-card .provider-avatar {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            font-size: 1.4rem;
            border-radius: var(--radius-md);
        }

        .provider-card h4, .provider-card h5 {
            margin-bottom: 0.3rem;
            color: var(--text-primary);
            font-weight: 700;
        }

        .rating { color: #f59e0b; margin: 0.4rem 0; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--border);
        }

        .empty-state h3 { color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 1rem; }

        /* ── MOBILE ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .booking-header { flex-direction: column; }
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }

        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 999;
            backdrop-filter: blur(2px);
        }

        .overlay.active { display: block; }

        /* ── SYSTEM NOTICE ── */
        .system-notice {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        /* ── PROVIDER CAROUSEL ── */
        .providers-carousel-wrapper {
            position: relative;
            width: 100%;
            margin: 0.5rem 0;
        }

        .providers-carousel {
            display: flex;
            flex-wrap: nowrap;
            gap: 1rem;
            overflow-x: auto;
            overflow-y: hidden;
            border-radius: var(--radius-md);
            padding: 1rem 0.25rem 1rem;
            min-height: 300px;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .provider-carousel-item {
            position: relative !important;
            flex: 0 0 240px !important;
            min-width: 240px !important;
            max-width: 260px !important;
            opacity: 1 !important;
            transition: var(--transition);
            scroll-snap-align: start;
        }

        .provider-carousel-item:hover { transform: translateY(-4px); }

        .providers-carousel::-webkit-scrollbar { height: 5px; }
        .providers-carousel::-webkit-scrollbar-track { background: var(--border-subtle); border-radius: 99px; }
        .providers-carousel::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 5;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .carousel-control:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            transform: translateY(-50%) scale(1.08);
        }

        .carousel-control.prev { left: 0; }
        .carousel-control.next { right: 0; }
        .carousel-control i { font-size: 0.8rem; }

        /* hide old carousel indicator classes */
        .carousel-indicators, .carousel-controls { display: none; }

        .carousel-btn {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-primary);
            cursor: pointer;
            transition: var(--transition);
            display: flex; align-items: center; justify-content: center;
        }

        .carousel-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }

        /* ── RANKING BADGES ── */
        .ranking-badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .ranking-badge.featured { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .ranking-badge.boosted  { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .ranking-badge.verified { background: linear-gradient(135deg, #10b981, #059669); color: white; }

        /* ── FAVORITE BUTTON ── */
        .favorite-btn {
            position: absolute;
            top: 0.875rem; right: 0.875rem;
            width: 34px; height: 34px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--surface);
            color: #ef4444;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
            font-size: 0.9rem;
            z-index: 10;
        }

        .favorite-btn:hover { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.1); transform: scale(1.1); }
        .favorite-btn.favorited { background: #ef4444; color: white; border-color: #ef4444; }

        /* ── HERO SECTION ── */
        .hero-section {
            background: linear-gradient(135deg, var(--accent) 0%, #1e40af 100%);
            border-radius: var(--radius-xl);
            padding: 2.5rem 2rem;
            color: white;
            margin-bottom: 1.75rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -60%; right: -8%;
            width: 320px; height: 320px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -40%; left: -5%;
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        .hero-content { position: relative; z-index: 2; }

        .hero-content h2 {
            font-size: 1.85rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .hero-content p {
            font-size: 0.95rem;
            opacity: 0.85;
            margin-bottom: 1.75rem;
            max-width: 520px;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.15);
        }

        .hero-stat { text-align: left; }

        .hero-stat h3 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -1px;
            font-variant-numeric: tabular-nums;
        }

        .hero-stat p { margin: 0.25rem 0 0; opacity: 0.75; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── CATEGORIES ── */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .category-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 180px;
        }

        .category-card:hover {
            border-color: var(--accent);
            background: var(--accent);
            color: white;
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .category-card:hover .category-icon { background: rgba(255,255,255,0.15); color: white; }
        .category-card:hover h5 { color: white; }
        .category-card:hover .category-details small { color: rgba(255,255,255,0.85); }
        .category-card:hover .category-rating { color: #fde68a; }

        .category-icon {
            font-size: 1.75rem;
            margin-bottom: 0.875rem;
            color: var(--accent);
            transition: var(--transition);
            display: flex; align-items: center; justify-content: center;
            width: 56px; height: 56px;
            background: var(--accent-light);
            border-radius: var(--radius-md);
        }

        .category-card h5 {
            margin: 0.25rem 0 0;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .category-details {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            margin-top: 0.75rem;
            opacity: 0.8;
            transition: var(--transition);
            width: 100%;
        }

        .category-details small {
            color: var(--text-secondary);
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .category-rating { color: #f59e0b; font-size: 0.75rem; font-weight: 600; }

        /* ── SERVICE CARDS ── */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .service-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            position: relative;
        }

        .service-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
            border-color: transparent;
        }

        .service-card-image {
            width: 100%;
            height: 110px;
            background: linear-gradient(135deg, var(--accent), #1e40af);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            position: relative;
        }

        .service-card-content { padding: 0.875rem 1rem 0.5rem; }

        .service-card h5 {
            margin: 0 0 0.2rem;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.875rem;
        }

        .service-card .rating { color: #f59e0b; font-size: 0.75rem; margin: 0.2rem 0; }

        .service-card .price {
            color: var(--accent);
            font-weight: 700;
            font-size: 0.9rem;
            margin: 0.2rem 0;
        }

        .service-card-footer { padding: 0 1rem 1rem; }

        .service-card-footer a {
            display: block;
            text-align: center;
            padding: 0.5rem;
            background: var(--accent-light);
            color: var(--accent);
            border-radius: var(--radius-sm);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .service-card-footer a:hover { background: var(--accent); color: white; }

        /* ── HOW IT WORKS ── */
        .how-it-works {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem 2rem;
            margin-bottom: 1.75rem;
        }

        .how-it-works h2 {
            text-align: center;
            margin-bottom: 2.5rem;
            color: var(--text-primary);
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.3px;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 2rem;
        }

        .step-card { text-align: center; position: relative; }

        .step-number {
            width: 52px; height: 52px;
            background: var(--accent);
            color: white;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 auto 1rem;
        }

        .step-card h4 { margin-bottom: 0.4rem; color: var(--text-primary); font-weight: 700; font-size: 0.9rem; }
        .step-card p { color: var(--text-muted); margin: 0; font-size: 0.82rem; }

        /* ── TESTIMONIALS ── */
        .testimonials-carousel {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .testimonial-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
        }

        .testimonial-card:hover { box-shadow: var(--shadow-sm); transform: translateY(-2px); }

        .testimonial-card .rating { color: #f59e0b; margin-bottom: 0.875rem; font-size: 0.9rem; }

        .testimonial-card .comment {
            color: var(--text-primary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            line-height: 1.65;
            font-style: italic;
        }

        .testimonial-author { display: flex; align-items: center; gap: 0.875rem; }

        .testimonial-avatar {
            width: 38px; height: 38px;
            border-radius: var(--radius-sm);
            background: var(--accent);
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            font-size: 0.85rem;
            overflow: hidden;
        }

        .testimonial-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .testimonial-info h6 { margin: 0 0 0.15rem; color: var(--text-primary); font-weight: 700; font-size: 0.85rem; }
        .testimonial-info small { color: var(--text-muted); display: block; font-size: 0.75rem; }

        /* ── OFFERS ── */
        .offers-section {
            background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            color: white;
            margin-bottom: 1.75rem;
            position: relative;
            overflow: hidden;
        }

        .offers-section::before {
            content: '';
            position: absolute;
            top: -50%; right: -8%;
            width: 280px; height: 280px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .offers-content { position: relative; z-index: 2; }

        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
        }

        .offer-card {
            background: rgba(255,255,255,0.12);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .offer-card:hover { background: rgba(255,255,255,0.2); }
        .offer-card .discount { font-size: 1.75rem; font-weight: 800; margin-bottom: 0.4rem; }
        .offer-card p { margin: 0; font-size: 0.82rem; opacity: 0.9; }

        /* ── CTA BANNER ── */
        .cta-banner {
            background: linear-gradient(135deg, var(--accent) 0%, #1e40af 100%);
            border-radius: var(--radius-xl);
            padding: 2.25rem;
            color: white;
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .cta-banner h3 { margin: 0 0 0.75rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.3px; }
        .cta-banner p { margin: 0 0 1.5rem; opacity: 0.85; font-size: 0.92rem; }

        .cta-banner-buttons { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }

        .cta-banner .btn {
            padding: 0.6rem 1.35rem;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .cta-banner .btn-light { background: white; color: var(--accent); }
        .cta-banner .btn-light:hover { background: #f1f5ff; transform: scale(1.04); }

        /* ── TRUST SECTION ── */
        .trust-section {
            background: var(--surface);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 1.75rem;
            border: 1px solid var(--border);
        }

        .trust-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 2rem;
            text-align: center;
        }

        .trust-item i { font-size: 1.75rem; color: var(--accent); margin-bottom: 0.75rem; display: block; }
        .trust-item h5 { color: var(--text-primary); font-weight: 700; margin-bottom: 0.35rem; font-size: 0.9rem; }
        .trust-item p { color: var(--text-muted); margin: 0; font-size: 0.8rem; }

        /* ── AI RECOMMENDATIONS ── */
        .ai-recommendations-header {
            background: linear-gradient(135deg, var(--accent) 0%, #1e40af 100%);
            border-radius: var(--radius-xl);
            padding: 2rem 1.75rem;
            margin-bottom: 1.75rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .ai-recommendations-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -5%;
            width: 240px; height: 240px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }

        .ai-recommendations-header::after {
            content: '';
            position: absolute;
            bottom: -30%; left: -5%;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .ai-header-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .ai-header-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .ai-header-text h2 { margin: 0 0 0.35rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.3px; }
        .ai-header-text p { margin: 0; opacity: 0.85; font-size: 0.875rem; line-height: 1.5; }

        .ai-badges { display: flex; gap: 0.6rem; margin-top: 0.875rem; flex-wrap: wrap; }

        .ai-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 100px;
            padding: 0.35rem 0.875rem;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* AI Recommendations Grid */
        .ai-recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .ai-recommendation-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .ai-recommendation-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
            border-color: var(--accent);
        }

        .ai-recommendation-banner {
            height: 110px;
            background: linear-gradient(135deg, var(--accent), #1e40af);
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 1rem;
        }

        .ai-provider-avatar-large {
            width: 80px; height: 80px;
            border-radius: var(--radius-md);
            background: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800;
            color: var(--accent);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            position: relative; z-index: 2;
            border: 3px solid white;
            overflow: hidden;
        }

        .ai-provider-avatar-large img { width: 100%; height: 100%; object-fit: cover; }

        .ai-recommendation-content {
            padding: 1.25rem 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            text-align: center;
        }

        .ai-recommendation-name {
            font-size: 1rem; font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .ai-recommendation-profession {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            font-weight: 500;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .ai-recommendation-stats {
            display: flex;
            justify-content: center;
            gap: 0.875rem;
            align-items: center;
            margin-bottom: 0.875rem;
            padding: 0.65rem 0;
            border-top: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
            font-size: 0.82rem;
        }

        .ai-stat-item { display: flex; align-items: center; gap: 0.25rem; color: var(--text-muted); }
        .ai-stat-item .ai-stat-value { font-weight: 700; color: var(--text-primary); }
        .ai-rating { color: #f59e0b; font-weight: 600; }

        .ai-recommendation-badges {
            display: flex; gap: 0.35rem;
            margin-bottom: 0.875rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .ai-badge-small {
            background: var(--accent-light);
            color: var(--accent);
            border-radius: 100px;
            padding: 0.2rem 0.6rem;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-flex; align-items: center; gap: 0.2rem;
        }

        .ai-badge-small.featured { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .ai-badge-small.verified { background: #f0fdf4; color: #166534; }
        .ai-badge-small.location { background: #eff6ff; color: #1d4ed8; }

        .ai-recommendation-location {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 0.875rem;
            display: flex; align-items: center; justify-content: center;
            gap: 0.25rem;
        }

        .ai-recommendation-actions { display: flex; gap: 0.5rem; margin-top: auto; }

        .ai-btn {
            flex: 1;
            padding: 0.55rem;
            border-radius: var(--radius-sm);
            border: none;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex; align-items: center; justify-content: center;
            gap: 0.3rem;
        }

        .ai-btn-primary { background: var(--accent); color: white; }
        .ai-btn-primary:hover { background: #0a58ca; color: white; transform: scale(1.02); }
        .ai-btn-secondary { background: var(--accent-light); color: var(--accent); border: 1px solid rgba(13,110,253,0.15); }
        .ai-btn-secondary:hover { background: var(--accent); color: white; }

        .ai-recommendation-score {
            position: absolute;
            top: 0.75rem; right: 0.75rem;
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(6px);
            color: white;
            width: 44px; height: 44px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            text-align: center;
            padding: 0.4rem;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .ai-recommendation-score-label { display: block; font-size: 0.55rem; opacity: 0.8; }
        .ai-recommendation-score-value { display: block; font-size: 0.9rem; }

        .ai-empty-state { text-align: center; padding: 3rem 2rem; color: var(--text-muted); }
        .ai-empty-state i { font-size: 2.5rem; margin-bottom: 1rem; color: var(--border); }
        .ai-empty-state h3 { color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 1rem; }

        @keyframes pulse-glow {
            0%   { box-shadow: 0 0 0 0 rgba(13,110,253,0.5); }
            70%  { box-shadow: 0 0 0 8px rgba(13,110,253,0); }
            100% { box-shadow: 0 0 0 0 rgba(13,110,253,0); }
        }

        .ai-recommendation-card.featured { animation: pulse-glow 2.5s infinite; }

        /* ── UTILITY CHIPS ── */
        .card-chip {
            position: absolute; top: 8px; right: 8px;
            color: white;
            padding: 0.2rem 0.55rem;
            border-radius: 100px;
            font-size: 0.65rem;
            font-weight: 700;
            backdrop-filter: blur(6px);
            z-index: 10;
            display: inline-flex; align-items: center; gap: 0.2rem;
        }
        .chip-used { background: rgba(16, 185, 129, 0.9); }
        .chip-fav  { background: rgba(239, 68, 68, 0.9); }

        /* ── CARD PROFESSION TEXT ── */
        .card-profession {
            font-size: 0.78rem;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        /* ── STAT ICON COLORS ── */
        .si-blue   { background: #eff4ff; color: #4f46e5; }
        .si-yellow { background: #fffbeb; color: #92400e; }
        .si-indigo { background: #eff6ff; color: #1e40af; }
        .si-green  { background: #f0fdf4; color: #065f46; }

        /* ── AI HOURLY RATE ── */
        .ai-hourly-rate {
            font-size: 1rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 0.875rem;
            font-variant-numeric: tabular-nums;
        }

        /* ── AI HEADER BODY ── */
        .ai-header-body { flex: 1; }

        /* ── RATING COUNT ── */
        .rating-count { margin-left: 0.25rem; font-size: 0.75rem; color: var(--text-muted); }

        /* ── RANKING BADGE FAVORITE ── */
        .ranking-badge.rb-favorite { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }

        /* ── CONTENT GRID ── */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.75rem;
        }

        @media (max-width: 992px) {
            .content-grid { grid-template-columns: 1fr; }
        }

        /* ── QUICK LINKS ── */
        .quick-links-grid { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.5rem; }

        .quick-link-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.75rem 0.875rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            background: var(--surface-2);
        }

        .quick-link-item:hover {
            border-color: var(--accent);
            background: var(--accent-light);
            color: var(--accent);
            text-decoration: none;
        }

        .quick-link-icon {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .ql-primary { background: var(--accent-light); color: var(--accent); }
        .ql-indigo  { background: #eff4ff; color: #4f46e5; }
        .ql-green   { background: #f0fdf4; color: #059669; }

        .quick-link-label { flex: 1; font-weight: 600; font-size: 0.875rem; }
        .quick-link-arrow { font-size: 0.65rem; color: var(--text-muted); }

        /* ── SYSINFO BLOCK ── */
        .sysinfo-block {
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-subtle);
        }

        .sysinfo-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 0.625rem;
        }

        .sysinfo-item {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sysinfo-item i { color: var(--accent); width: 14px; text-align: center; }

        /* ── REVIEW ITEMS ── */
        .review-item {
            padding: 0.875rem 0;
            border-bottom: 1px solid var(--border-subtle);
        }

        .review-item:last-child { border-bottom: none; }

        .review-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }

        .review-provider-name { font-size: 0.875rem; font-weight: 700; color: var(--text-primary); }
        .review-comment { font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.3rem; line-height: 1.5; }
        .review-date { font-size: 0.72rem; color: var(--text-muted); margin: 0; }

        /* ── AVATAR FALLBACK ── */
        .avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: white;
            font-size: 2rem;
            font-weight: 700;
        }

        /* ── ALERT OVERRIDES ── */
        .alert {
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            border: 1px solid transparent;
        }

        /* ── PROVIDER CARD VIEW PROFILE BTN ── */
        .provider-card .btn-outline-primary {
            border-color: var(--border);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.4rem 0.875rem;
        }

        .provider-card .btn-outline-primary:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-section">
                <div class="welcome-text">
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $client['full_name'] ?? 'client');?></h1>
                    <p><?php echo date('l, F j, Y'); ?> • <?php echo $system_settings['platform_description']; ?></p>
                </div>
                <div class="quick-actions">
                    <a href="../client/providers.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i> Find Providers
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Booking cancelled successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($needs_email_verification || $needs_phone_verification): ?>
            <div class="system-notice">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Account Verification Required</h5>
                <?php if ($needs_email_verification): ?>
                    <p class="mb-1">📧 Your email address needs to be verified. <a href="verify-email.php" class="alert-link">Verify now</a></p>
                <?php endif; ?>
                <?php if ($needs_phone_verification): ?>
                    <p class="mb-0">📱 Your phone number needs to be verified. <a href="verify-phone.php" class="alert-link">Verify now</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Hero Section with Platform Stats -->
        <div class="hero-section">
            <div class="hero-content">
                <h2>Your Trusted Service Marketplace</h2>
                <p>Connect with verified professionals and get the job done right. Browse thousands of services across multiple categories.</p>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <h3><?php echo $platform_stats['total_providers']; ?></h3>
                        <p>Verified Providers</p>
                    </div>
                    <div class="hero-stat">
                        <h3><?php echo number_format($platform_stats['total_services_completed']); ?></h3>
                        <p>Services Completed</p>
                    </div>
                    <div class="hero-stat">
                        <h3><?php echo $platform_stats['avg_rating']; ?> ⭐</h3>
                        <p>Average Rating</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== AI SERVICE RECOMMENDATIONS SECTION ==================== -->
        <div class="ai-recommendations-header">
            <div class="ai-header-content">
                <div class="ai-header-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="ai-header-body">
                    <div class="ai-header-text">
                        <h2>Recommended for You</h2>
                        <p>Our AI analyzes your booking history, location preferences, and service trends to find the perfect providers</p>
                    </div>
                    <div class="ai-badges">
                        <div class="ai-badge">
                            <i class="fas fa-history"></i>
                            Based on Your Bookings
                        </div>
                        <div class="ai-badge">
                            <i class="fas fa-map-marker-alt"></i>
                            Local Providers
                        </div>
                        <div class="ai-badge">
                            <i class="fas fa-fire"></i>
                            Trending Services
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Recommendations Grid -->
        <?php if (empty($ai_recommendations)): ?>
            <div class="card mb-4">
                <div class="ai-empty-state">
                    <i class="fas fa-sparkles"></i>
                    <h3>No Recommendations Yet</h3>
                    <p>Start exploring providers to get personalized AI-powered recommendations</p>
                    <a href="../client/providers.php" class="btn btn-primary mt-3">
                        <i class="fas fa-search me-2"></i> Explore Providers
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="ai-recommendations-grid">
                <?php
                // Sort recommendations and limit to top 12
                usort($ai_recommendations, function($a, $b) {
                    return ($b['recommendation_score'] ?? 0) - ($a['recommendation_score'] ?? 0);
                });

                foreach (array_slice($ai_recommendations, 0, 12) as $index => $rec):
                    $provider_initial = strtoupper(substr($rec['full_name'] ?? '', 0, 1)) ?: '?';
                    $has_image = !empty($rec['profile_image']);
                    $is_featured = $rec['recommendation_score'] >= 3000;
                    $is_verified = $rec['is_verified'] ?? false;
                    $location_match = ($rec['provider_location'] === $clientLocation && !empty($clientLocation));
                    $rating = $rec['average_rating'] ?? 0;
                    $score = intval($rec['recommendation_score'] ?? 0);
                ?>
                    <div class="ai-recommendation-card <?php echo $is_featured ? 'featured' : ''; ?>">
                        <!-- Recommendation Score Badge -->
                        <div class="ai-recommendation-score">
                            <span class="ai-recommendation-score-label">AI MATCH</span>
                            <span class="ai-recommendation-score-value"><?php echo min(100, round($score / 33)); ?>%</span>
                        </div>

                        <!-- Banner with Avatar -->
                        <div class="ai-recommendation-banner">
                            <a href="../client/provider-profile.php?id=<?php echo $rec['id']; ?>">
                                <div class="ai-provider-avatar-large">
                                    <?php if ($has_image): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($rec['profile_image']); ?>"
                                             alt="<?php echo htmlspecialchars($rec['full_name']); ?>"
                                             onerror="this.style.display='none';this.parentElement.textContent='<?php echo htmlspecialchars($provider_initial); ?>';">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($provider_initial); ?>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>

                        <!-- Content -->
                        <div class="ai-recommendation-content">
                            <h3 class="ai-recommendation-name" title="<?php echo htmlspecialchars($rec['full_name']); ?>">
                                <?php echo htmlspecialchars($rec['full_name']); ?>
                            </h3>
                            <p class="ai-recommendation-profession" title="<?php echo htmlspecialchars($rec['profession']); ?>">
                                <?php echo htmlspecialchars($rec['profession']); ?>
                            </p>

                            <!-- Badges -->
                            <div class="ai-recommendation-badges">
                                <?php if ($is_featured): ?>
                                    <span class="ai-badge-small featured">
                                        <i class="fas fa-star"></i> Top Match
                                    </span>
                                <?php endif; ?>
                                <?php if ($is_verified): ?>
                                    <span class="ai-badge-small verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php endif; ?>
                                <?php if ($location_match): ?>
                                    <span class="ai-badge-small location">
                                        <i class="fas fa-map-pin"></i> Your Area
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Stats -->
                            <div class="ai-recommendation-stats">
                                <div class="ai-stat-item">
                                    <span class="ai-rating">★</span>
                                    <span class="ai-rating"><?php echo number_format($rating, 1); ?></span>
                                </div>
                                <div class="ai-stat-item">
                                    <i class="fas fa-comment-dots text-muted"></i>
                                    <span class="ai-stat-value"><?php echo intval($rec['total_reviews'] ?? 0); ?></span>
                                </div>
                            </div>

                            <!-- Location -->
                            <?php if (!empty($rec['provider_location'])): ?>
                                <div class="ai-recommendation-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($rec['provider_location']); ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Hourly Rate -->
                            <?php if (!empty($rec['hourly_rate'])): ?>
                                <div class="ai-hourly-rate">
                                    <?php echo htmlspecialchars($rec['hourly_rate']); ?> / hour
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="ai-recommendation-actions">
                                <a href="../client/provider-profile.php?id=<?php echo $rec['id']; ?>" class="ai-btn ai-btn-primary">
                                    <i class="fas fa-arrow-right"></i> View
                                </a>
                                <button class="ai-btn ai-btn-secondary favorite-btn"
                                        data-provider-id="<?php echo $rec['id']; ?>"
                                        title="Add to favorites">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Browse Categories Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-th-large me-2 text-primary"></i>Browse by Category</h3>
                <a href="../client/providers.php" class="btn-sm btn-view text-decoration-none">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder"></i>
                    <h3>No Categories Available</h3>
                    <p>Check back soon for available service categories</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php 
                    $category_icons = [
                        'Plumbing' => 'fas fa-wrench',
                        'Electrical' => 'fas fa-bolt',
                        'Construction' => 'fas fa-hammer',
                        'Cleaning' => 'fas fa-broom',
                        'Painting' => 'fas fa-paint-brush',
                        'Landscaping' => 'fas fa-leaf',
                        'Carpentry' => 'fas fa-saw',
                        'HVAC' => 'fas fa-fan',
                        'Roofing' => 'fas fa-house-damage',
                        'Welding' => 'fas fa-fire',
                        'Masonry' => 'fas fa-cube',
                        'Doors' => 'fas fa-door-open',
                        'Plumber' => 'fas fa-wrench',
                        'Electrician' => 'fas fa-bolt',
                        'Painter' => 'fas fa-paint-brush',
                        'Carpenter' => 'fas fa-hammer',
                        'Mechanic' => 'fas fa-tools',
                        'Hairdresser' => 'fas fa-scissors',
                        'Gardener' => 'fas fa-leaf',
                        'Tutoring' => 'fas fa-book',
                        'IT Support' => 'fas fa-laptop',
                        'Photography' => 'fas fa-camera',
                        'default' => 'fas fa-star'
                    ];
                    
                    foreach ($categories as $category): 
                        $icon = $category_icons[$category['category']] ?? $category_icons['default'];
                        $rating = $category['avg_rating'] ?? 0;
                    ?>
                        <a href="../client/providers.php?category=<?php echo urlencode($category['category']); ?>" class="category-card" title="View <?php echo htmlspecialchars($category['category']); ?> providers">
                            <div class="category-icon">
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($category['category']); ?></h5>
                            <div class="category-details">
                                <small>
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $category['provider_count']; ?> provider<?php echo $category['provider_count'] != 1 ? 's' : ''; ?>
                                </small>
                                <div class="category-rating">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++): 
                                        echo $i <= floor($rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    endfor; 
                                    ?>
                                    <span class="rating-count"><?php echo number_format($rating, 1); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Category service sections with interleaved Featured Services -->
        <?php 
        $categoryIndex = 0;
        $totalCategories = count($category_services);
        foreach ($category_services as $catName => $services): 
            // Show featured services after every 2 categories
            if ($categoryIndex > 0 && $categoryIndex % 2 == 0 && !empty($featured_services)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-fire me-2"></i>Featured Services</h3>
                        <span class="badge bg-danger">Personalized for You</span>
                    </div>
                    <div class="services-grid">
                        <?php foreach (array_slice($featured_services, 0, 4) as $service): 
                            $provider_initial = strtoupper(substr($service['full_name'] ?? '', 0, 1)) ?: '?';
                            $has_image = !empty($service['profile_image']);
                        ?>
                            <div class="service-card">
                                <div class="service-card-image">
                                    <?php if ($has_image): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($service['profile_image']); ?>" 
                             alt="<?php echo htmlspecialchars($service['full_name']); ?>"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
                                        <div style="display: none; align-items: center; justify-content: center; width: 100%; height: 100%; color: white; font-size: 2rem; font-weight: bold; position: absolute; top: 0; left: 0;">
                                            <?php echo htmlspecialchars($provider_initial); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar-fallback">
                                            <?php echo htmlspecialchars($provider_initial); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($service['previously_booked']): ?>
                                        <div class="card-chip chip-used">
                                            <i class="fas fa-check-circle me-1"></i> Used
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="service-card-content">
                                    <h5><?php echo htmlspecialchars($service['full_name']); ?></h5>
                                    <p class="card-profession">
                                        <?php echo htmlspecialchars($service['profession']); ?>
                                    </p>
                                    <div class="rating">
                                        <?php 
                                        $rating = $service['average_rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++): 
                                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        endfor; 
                                        ?>
                                    </div>
                                </div>
                                <div class="service-card-footer">
                                    <a href="../client/provider-profile.php?id=<?php echo $service['id']; ?>">
                                        <i class="fas fa-arrow-right me-1"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        
            <!-- Category Services Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-folder-open me-2"></i><?php echo htmlspecialchars($catName); ?></h3>
                    <a href="../client/providers.php?category=<?php echo urlencode($catName); ?>" class="text-decoration-none fw-semibold text-primary">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="services-grid">
                    <?php foreach (array_slice($services, 0, 4) as $service): 
                        $provider_initial = strtoupper(substr($service['provider_name'] ?? '', 0, 1)) ?: '?';
                        $has_image = !empty($service['provider_image']);
                    ?>
                        <div class="service-card">
                            <div class="service-card-image">
                                <?php if ($has_image): ?>
                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($service['provider_image']); ?>" 
                         alt="<?php echo htmlspecialchars($service['provider_name']); ?>"
                         style="width: 100%; height: 100%; object-fit: cover;"
                         onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
                                    <div class="service-card-image-fallback" style="display: none; align-items: center; justify-content: center; width: 100%; height: 100%; color: white; font-size: 2rem; font-weight: bold; position: absolute; top: 0; left: 0;">
                                        <?php echo htmlspecialchars($provider_initial); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-fallback">
                                        <?php echo htmlspecialchars($provider_initial); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($service['is_favorite']): ?>
                                    <div class="card-chip chip-fav">
                                        <i class="fas fa-heart me-1"></i> Favorite
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="service-card-content">
                                <h5><?php echo htmlspecialchars($service['name'] ?? 'Service'); ?></h5>
                                <p class="card-profession">
                                    By <?php echo htmlspecialchars($service['provider_name']); ?>
                                </p>
                                <?php if ($service['price']): ?>
                                    <p class="price mb-1">RWF <?php echo number_format($service['price']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="service-card-footer">
                                <a href="../client/service.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                                <a href="../client/booking.php?provider_id=<?php echo $service['provider_id']; ?>&service_id=<?php echo $service['id']; ?>" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-arrow-right me-1"></i> Book
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php $categoryIndex++; endforeach; ?>

        <!-- Featured Services Section (shown at end if not already displayed) -->
        <?php if ($totalCategories <= 2 && !empty($featured_services)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-fire me-2"></i>Featured Services</h3>
                    <span class="badge bg-danger">Personalized for You</span>
                </div>
                <div class="services-grid">
                    <?php foreach ($featured_services as $service): 
                        $provider_initial = strtoupper(substr($service['full_name'] ?? '', 0, 1)) ?: '?';
                        $has_image = !empty($service['profile_image']);
                    ?>
                        <div class="service-card">
                            <div class="service-card-image">
                                <?php if ($has_image): ?>
                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($service['profile_image']); ?>" 
                         alt="<?php echo htmlspecialchars($service['full_name']); ?>"
                         style="width: 100%; height: 100%; object-fit: cover;"
                         onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
                                    <div class="featured-fallback" style="display: none; align-items: center; justify-content: center; width: 100%; height: 100%; color: white; font-size: 2rem; font-weight: bold; position: absolute; top: 0; left: 0;">
                                        <?php echo htmlspecialchars($provider_initial); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-fallback">
                                        <?php echo htmlspecialchars($provider_initial); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($service['previously_booked']): ?>
                                    <div class="card-chip chip-used">
                                        <i class="fas fa-check-circle me-1"></i> Used
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="service-card-content">
                                <h5><?php echo htmlspecialchars($service['full_name']); ?></h5>
                                <p class="card-profession">
                                    <?php echo htmlspecialchars($service['profession']); ?>
                                </p>
                                <div class="rating">
                                    <?php 
                                    $rating = $service['average_rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): 
                                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    endfor; 
                                    ?>
                                </div>
                                <?php if ($service['hourly_rate']): ?>
                                    <p class="price mb-1">
                                        RWF <?php echo number_format($service['hourly_rate']); ?>/hr
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="service-card-footer">
                                <a href="../client/provider-profile.php?id=<?php echo $service['id']; ?>">
                                    <i class="fas fa-arrow-right me-1"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- How It Works Section -->
        <div class="how-it-works">
            <h2><i class="fas fa-lightbulb me-2"></i>How It Works</h2>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h4>Browse Services</h4>
                    <p>Find the perfect service provider from our verified network across all categories.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h4>Compare & Select</h4>
                    <p>Review ratings, prices, availability, and reviews to make the best choice.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h4>Book Service</h4>
                    <p>Schedule your service with your preferred provider at a time that works for you.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h4>Get It Done</h4>
                    <p>Provider arrives and completes your service with professional quality and care.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">5</div>
                    <h4>Rate & Review</h4>
                    <p>Share your experience and help other clients find great providers. Build community trust.</p>
                </div>
            </div>
        </div>

        <!-- Testimonials Section -->
        <?php if (!empty($testimonials)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-comments me-2"></i>What Our Clients Say</h3>
                </div>

                <div class="testimonials-carousel">
                    <?php foreach ($testimonials as $testimonial): 
                        $client_initial = strtoupper(substr($testimonial['full_name'] ?? '', 0, 1)) ?: '?';
                    ?>
                        <div class="testimonial-card">
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $testimonial['rating']): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <p class="comment">"<?php echo htmlspecialchars(substr($testimonial['comment'], 0, 150)); ?>..."</p>
                            <div class="testimonial-author">
                                <div class="testimonial-avatar">
                                    <?php echo $client_initial; ?>
                                </div>
                                <div class="testimonial-info">
                                    <h6><?php echo htmlspecialchars($testimonial['full_name']); ?></h6>
                                    <small><?php echo htmlspecialchars($testimonial['profession']); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Special Offers Section -->
        <div class="offers-section">
            <div class="offers-content">
                <h3><i class="fas fa-gift me-2"></i>Special Offers This Week</h3>
                <p class="mb-4 opacity-75">Limited time promotions to help you save on your services</p>
                <div class="offers-grid">
                    <div class="offer-card">
                        <div class="discount">20%</div>
                        <p>New Customer Discount</p>
                    </div>
                    <div class="offer-card">
                        <div class="discount">15%</div>
                        <p>Off Bulk Services</p>
                    </div>
                    <div class="offer-card">
                        <div class="discount">FREE</div>
                        <p>First Consultation</p>
                    </div>
                    <div class="offer-card">
                        <div class="discount">25%</div>
                        <p>Weekend Services</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trust & Safety Section -->
        <div class="trust-section">
            <h3 class="text-center mb-4"><i class="fas fa-shield-alt me-2"></i>Why Choose Us</h3>
            <div class="trust-grid">
                <div class="trust-item">
                    <i class="fas fa-check-circle"></i>
                    <h5>Verified Providers</h5>
                    <p>All service providers are thoroughly vetted and verified</p>
                </div>
                <div class="trust-item">
                    <i class="fas fa-shield-alt"></i>
                    <h5>100% Secure</h5>
                    <p>Your data and payment information is fully protected</p>
                </div>
                <div class="trust-item">
                    <i class="fas fa-star"></i>
                    <h5>Quality Assured</h5>
                    <p>Only highly-rated professionals with proven track records</p>
                </div>
                <div class="trust-item">
                    <i class="fas fa-headset"></i>
                    <h5>24/7 Support</h5>
                    <p>Our customer support team is always available to help</p>
                </div>
                <div class="trust-item">
                    <i class="fas fa-handshake"></i>
                    <h5>Fair Pricing</h5>
                    <p>Transparent pricing with no hidden charges</p>
                </div>
                <div class="trust-item">
                    <i class="fas fa-medal"></i>
                    <h5>Best Guarantee</h5>
                    <p>Satisfaction guaranteed or your money back</p>
                </div>
            </div>
        </div>

        <!-- CTA Banner -->
        <div class="cta-banner">
            <h3>Ready to Find Your Next Service Provider?</h3>
            <p>Browse our extensive network of verified professionals and get connected today</p>
            <div class="cta-banner-buttons">
                <a href="../client/providers.php" class="btn btn-light">
                    <i class="fas fa-search me-2"></i>Explore Providers
                </a>
                <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-filter me-2"></i>Search by Category
                </button>
            </div>
        </div>

        <!-- Recommended Providers Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-star me-2"></i>Top Rated Providers</h3>
            </div>

            <?php if (empty($recommended_providers)): ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>No Providers Available</h3>
                    <p>Check back soon for recommended providers</p>
                    <a href="../client/providers.php" class="btn btn-primary mt-3">
                        <i class="fas fa-search me-2"></i> Browse All Providers
                    </a>
                </div>
            <?php else: ?>
                <!-- Providers Carousel Container -->
                <div class="providers-carousel-wrapper">
                    <button class="carousel-control prev" id="providersCarouselPrev" aria-label="Previous providers"><i class="fas fa-chevron-left"></i></button>
                    <div class="providers-carousel" id="providersCarousel">
                        <?php foreach ($recommended_providers as $index => $provider): ?>
                            <div class="provider-carousel-item <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>" data-provider-id="<?php echo $provider['id']; ?>">
                            <div class="provider-card">
                                <!-- Favorite Button -->
                                <button class="favorite-btn <?php echo $provider['is_favorite'] ? 'favorited' : ''; ?>" 
                                        data-provider-id="<?php echo $provider['id']; ?>" 
                                        title="<?php echo $provider['is_favorite'] ? 'Remove from favorites' : 'Add to favorites'; ?>">
                                    <i class="<?php echo $provider['is_favorite'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>

                                <!-- Make entire card clickable by wrapping content in a link -->
                                <a href="../client/provider-profile.php?id=<?php echo $provider['id']; ?>" class="provider-card-link d-block text-decoration-none text-reset">
                                <?php 
                                $prov_img = $provider['profile_image'] ?? '';
                                $prov_initial = strtoupper(substr($provider['full_name'] ?? '', 0, 1)) ?: '?';
                                $is_featured = $provider['is_featured'] ?? 0;
                                $search_boost = $provider['search_boost'] ?? 0;
                                $ranking_score = $provider['ranking_score'] ?? 0;
                                ?>
                                <div class="provider-avatar">
                                    <?php if (!empty($prov_img)): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($prov_img); ?>" alt="<?php echo htmlspecialchars($provider['full_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($prov_initial); ?>';">
                                    <?php else: ?>
                                        <?php echo $prov_initial; ?>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($provider['full_name']); ?></h5>
                                <p class="text-primary small mb-2">
                                    <?php echo htmlspecialchars($provider['profession']); ?>
                                </p>
                                
                                <!-- Ranking Badge -->
                                <div class="mb-2">
                                    <?php 
                                        if ($provider['is_favorite']) {
                                            echo '<div class="ranking-badge rb-favorite"><i class="fas fa-heart me-1"></i> Your Favorite</div>';
                                        } elseif ($ranking_score >= 1500) {
                                            echo '<div class="ranking-badge featured"><i class="fas fa-star me-1"></i> Featured</div>';
                                        } elseif ($ranking_score >= 1000) {
                                            echo '<div class="ranking-badge boosted"><i class="fas fa-rocket me-1"></i> Boosted</div>';
                                        } elseif ($ranking_score >= 800) {
                                            echo '<div class="ranking-badge verified"><i class="fas fa-check me-1"></i> Top Rated</div>';
                                        }
                                    ?>
                                </div>
                                
                                <div class="rating mb-2">
                                    <?php 
                                    $rating = $provider['average_rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): 
                                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    endfor; ?>
                                    <span class="text-muted small ms-1">
                                        (<?php echo $provider['total_reviews'] ?? 0; ?>)
                                    </span>
                                </div>
                                </a>
                                
                                <!-- keep view button for explicit link but styled smaller or hidden if desired -->
                                <a href="../client/provider-profile.php?id=<?php echo $provider['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-eye me-1"></i> View Profile
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <button class="carousel-control next" id="providersCarouselNext" aria-label="Next providers"><i class="fas fa-chevron-right"></i></button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <div class="stats-grid mb-4">
            <div class="stat-card">
                <div class="stat-icon si-blue">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3><?php echo $total_bookings; ?></h3>
                <p>Total Bookings</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon si-yellow">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $pending_bookings; ?></h3>
                <p>Pending Requests</p>
                <?php if ($system_settings['max_pending_time'] > 0): ?>
                    <small class="text-muted">Auto-cancels in <?php echo $system_settings['max_pending_time']; ?>min</small>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-icon si-indigo">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $confirmed_bookings; ?></h3>
                <p>Confirmed</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon si-green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $completed_bookings; ?></h3>
                <p>Completed</p>
                <?php if ($system_settings['require_rating_after_completion']): ?>
                    <small class="text-muted">Rating required</small>
                <?php endif; ?>
            </div>
        </div>



                <!-- Recent Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Bookings</h3>
                        <a href="my-bookings.php" class="text-decoration-none fw-semibold text-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <?php if (empty($recent_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <h3>No bookings yet</h3>
                            <p>Start by finding a service provider</p>
                            <a href="../client/providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-header">
                                    <div class="provider-info">
                                        <?php 
                                        $provider_image = $booking['provider_image'] ?? '';
                                        $provider_initial = strtoupper(substr($booking['provider_name'] ?? '', 0, 1)) ?: '?';
                                        ?>
                                        <div class="provider-avatar">
                                            <?php if (!empty($provider_image)): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($provider_image); ?>" alt="<?php echo htmlspecialchars($booking['provider_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($provider_initial); ?>';">
                                            <?php else: ?>
                                                <?php echo $provider_initial; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($booking['provider_name']); ?></h5>
                                            <p class="text-primary fw-semibold small mb-1">
                                                <?php echo htmlspecialchars($booking['profession']); ?>
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($booking['location']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
 
                                <p class="mb-2">
                                    <strong>Service:</strong> <?php echo htmlspecialchars($booking['service_description']); ?>
                                </p>

                                <p class="text-muted small mb-2">
                                    <i class="fas fa-calendar me-1"></i> Preferred: <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?>
                                    <span class="ms-3">
                                        <i class="fas fa-clock me-1"></i> Booked: <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                    </span>
                                </p>

                                <?php if ($booking['hourly_rate']): ?>
                                    <p class="text-primary fw-semibold mb-2">
                                        <i class="fas fa-money-bill-wave me-1"></i> RWF <?php echo number_format($booking['hourly_rate']); ?>/hour
                                        <?php if ($system_settings['enable_commission']): ?>
                                            <small class="text-muted">(incl. <?php echo $system_settings['commission_rate']; ?>% commission)</small>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>

                                <div class="booking-actions">
                                    <a href="../client/provider-profile.php?id=<?php echo $booking['provider_id']; ?>" class="btn-sm btn-view">
                                        <i class="fas fa-user me-1"></i> View Provider
                                    </a>
                                    
                                    <?php if ($system_settings['allow_booking_editing'] && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                        <a href="edit-booking.php?id=<?php echo $booking['id']; ?>" class="btn-sm btn-view">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['status'] === 'completed'): ?>
                                        <?php
                                        // Prefer checking by booking_id so each completed booking can get its own review.
                                        $review_check = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND booking_id = ? LIMIT 1");
                                        $review_check->execute([$_SESSION['user_id'], $booking['id']]);
                                        $has_review = $review_check->fetch();

                                        // Fallback: if no booking-specific review exists, check provider-level (older behaviour)
                                        if (!$has_review) {
                                            $review_check2 = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND provider_id = ? LIMIT 1");
                                            $review_check2->execute([$_SESSION['user_id'], $booking['provider_id']]);
                                            $has_review = $review_check2->fetch();
                                        }
                                        ?>
                                        <?php if (!$has_review): ?>
                                            <a href="write-review.php?provider_id=<?php echo $booking['provider_id']; ?>&booking_id=<?php echo $booking['id']; ?>" class="btn-sm btn-review">
                                                <i class="fas fa-star me-1"></i> Write Review
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-success">Reviewed</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                        <?php if ($monthly_cancellations < $system_settings['max_cancellations_per_month']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?')">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn-sm btn-cancel">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancellation Limit Reached</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Reviews -->
                <?php if (!empty($my_reviews)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>My Recent Reviews</h3>
                            <a href="my-reviews.php" class="btn-sm btn-view text-decoration-none">View All</a>
                        </div>

                        <?php foreach ($my_reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-item-header">
                                    <strong class="review-provider-name"><?php echo htmlspecialchars($review['provider_name']); ?></strong>
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></p>
                                <p class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="quick-links-grid">
                        <a href="../client/providers.php" class="quick-link-item">
                            <span class="quick-link-icon ql-primary"><i class="fas fa-search"></i></span>
                            <span class="quick-link-label">Find Providers</span>
                            <i class="fas fa-chevron-right quick-link-arrow"></i>
                        </a>
                        <a href="my-bookings.php" class="quick-link-item">
                            <span class="quick-link-icon ql-indigo"><i class="fas fa-calendar"></i></span>
                            <span class="quick-link-label">All Bookings</span>
                            <i class="fas fa-chevron-right quick-link-arrow"></i>
                        </a>
                        <a href="profile.php" class="quick-link-item">
                            <span class="quick-link-icon ql-green"><i class="fas fa-user"></i></span>
                            <span class="quick-link-label">Edit Profile</span>
                            <i class="fas fa-chevron-right quick-link-arrow"></i>
                        </a>
                    </div>

                    <!-- System Information -->
                    <div class="sysinfo-block">
                        <p class="sysinfo-label">System Info</p>
                        <div class="sysinfo-item">
                            <i class="fas fa-clock"></i> <?php echo $system_settings['timezone']; ?>
                        </div>
                        <div class="sysinfo-item">
                            <i class="fas fa-phone"></i> <?php echo $system_settings['contact_phone']; ?>
                        </div>
                        <div class="sysinfo-item">
                            <i class="fas fa-envelope"></i> <?php echo $system_settings['contact_email']; ?>
                        </div>
                        <?php if ($system_settings['max_cancellations_per_month'] > 0): ?>
                            <div class="sysinfo-item">
                                <i class="fas fa-times-circle"></i>
                                Cancellations: <?php echo $monthly_cancellations; ?>/<?php echo $system_settings['max_cancellations_per_month']; ?> this month
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar collapse toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const clientSidebar = document.getElementById('clientSidebar');

        if (sidebarToggle && clientSidebar) {
            // Load sidebar state from localStorage; default to collapsed for modern icon-only UI
            const storedState = localStorage.getItem('sidebarCollapsed');
            const sidebarCollapsed = storedState === null ? true : storedState === 'true';

            if (sidebarCollapsed) {
                clientSidebar.classList.add('collapsed');
            } else {
                clientSidebar.classList.remove('collapsed');
            }

            // Toggle sidebar on button click
            sidebarToggle.addEventListener('click', () => {
                clientSidebar.classList.toggle('collapsed');
                if (clientSidebar.classList.contains('collapsed')) {
                    clientSidebar.classList.remove('expanded-on-hover');
                }
                localStorage.setItem('sidebarCollapsed', clientSidebar.classList.contains('collapsed'));
            });

            // Expand temporarily on hover when collapsed (icon-only mini mode)
            clientSidebar.addEventListener('mouseenter', () => {
                if (clientSidebar.classList.contains('collapsed')) {
                    clientSidebar.classList.add('expanded-on-hover');
                }
            });
            clientSidebar.addEventListener('mouseleave', () => {
                clientSidebar.classList.remove('expanded-on-hover');
            });
        }


        function markNotificationAsRead(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', notificationId);
            
            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (card) {
                        card.classList.remove('unread');
                        card.style.background = '#f8f9fa';
                        const markBtn = card.querySelector('.mark-read-btn');
                        if (markBtn) {
                            markBtn.remove();
                        }
                    }
                    updateUnreadCount();
                    showToast('Notification marked as read', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error marking as read', 'error');
            });
        }

        // Delete notification
        function deleteNotification(notificationId) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('notification_id', notificationId);
            
            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (card) {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(100%)';
                        setTimeout(() => card.remove(), 300);
                    }
                    updateUnreadCount();
                    showToast('Notification deleted', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting notification', 'error');
            });
        }

        // Mark all notifications as read
        document.getElementById('markAllReadBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            formData.append('client_mode', '1');
            

        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.style.cssText = 'position: fixed; top: 1rem; right: 1rem; z-index: 9999;';
                document.body.appendChild(container);
            }

            // Create toast element
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
            toast.innerHTML = `
                <div class="${bgClass} text-white p-3 rounded mb-2" style="box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-width: 300px; animation: slideIn 0.3s ease;">
                    <i class="fas ${icon} me-2"></i> ${escapeHtml(message)}
                </div>
            `;
            
            container.appendChild(toast);
            
            // Auto-remove after 4 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Escape HTML to prevent XSS
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Add toast animations to CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

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
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Carousel auto-rotation removed - using horizontal scroll list
        document.addEventListener('DOMContentLoaded', () => {
            
            // Handle favorite button clicks
            document.querySelectorAll('.favorite-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const providerId = this.getAttribute('data-provider-id');
                    const isFavorited = this.classList.contains('favorited');
                    
                    // Create FormData for AJAX request
                    const formData = new FormData();
                    formData.append('provider_id', providerId);
                    
                    if (isFavorited) {
                        formData.append('remove_from_favorites', '1');
                    } else {
                        formData.append('add_to_favorites', '1');
                    }
                    
                    // Send AJAX request
                    fetch('../api/toggle_favorite.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Toggle the favorited class
                            this.classList.toggle('favorited');
                            
                            // Update heart icon
                            const icon = this.querySelector('i');
                            if (this.classList.contains('favorited')) {
                                icon.classList.remove('far');
                                icon.classList.add('fas');
                                this.title = 'Remove from favorites';
                            } else {
                                icon.classList.remove('fas');
                                icon.classList.add('far');
                                this.title = 'Add to favorites';
                            }
                            
                            // Show success message (optional)
                            if (data.message) {
                                console.log(data.message);
                            }
                        } else {
                            console.error('Error:', data.error || 'Unknown error');
                            alert('Error: ' + (data.error || 'Failed to update favorite'));
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Error: ' + error.message);
                    });
                });

                // Carousel nav logic is handled once outside the favorites loop below.
            });

            // Provider horizontal carousel nav buttons
            const providersCarousel = document.getElementById('providersCarousel');
            const prevBtn = document.getElementById('providersCarouselPrev');
            const nextBtn = document.getElementById('providersCarouselNext');

            if (providersCarousel && prevBtn && nextBtn) {
                const scrollStep = providersCarousel.clientWidth * 0.7;

                prevBtn.addEventListener('click', () => {
                    providersCarousel.scrollBy({ left: -scrollStep, behavior: 'smooth' });
                });

                nextBtn.addEventListener('click', () => {
                    providersCarousel.scrollBy({ left: scrollStep, behavior: 'smooth' });
                });

                const updateCarouselButtons = () => {
                    const maxScrollLeft = providersCarousel.scrollWidth - providersCarousel.clientWidth;
                    prevBtn.style.opacity = providersCarousel.scrollLeft > 20 ? '1' : '0.4';
                    nextBtn.style.opacity = providersCarousel.scrollLeft < maxScrollLeft - 20 ? '1' : '0.4';
                };

                providersCarousel.addEventListener('scroll', updateCarouselButtons);
                updateCarouselButtons();
            }


        });
    </script>
</body>
</html>