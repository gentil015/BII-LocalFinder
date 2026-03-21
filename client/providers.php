<?php
session_start();
require_once '../config/database.php';
require_once '../includes/mailer.php';
require_once '../includes/functions.php';
require_once '../includes/ai_helpers.php';
require_once '../includes/event_tracking.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$aiHelper = new AIHelper($db);
$booking_success = '';
$booking_errors = [];

// Load platform settings from database
function getPlatformSetting($db, $key, $default = '') {
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

// Get all relevant platform settings
$platform_name = getPlatformSetting($db, 'platform_name', 'BII LocalFinder');
$contact_email = getPlatformSetting($db, 'contact_email', 'info@biilocalfinder.com');
$contact_phone = getPlatformSetting($db, 'contact_phone', '+250 788 000 000');
$copyright_text = getPlatformSetting($db, 'copyright_text', '© 2024 BII LocalFinder. All rights reserved.');
$provider_registration_enabled = getPlatformSetting($db, 'provider_registration', '1');
$client_registration = getPlatformSetting($db, 'client_registration', '1');
$timezone = getPlatformSetting($db, 'timezone', 'Africa/Kigali');
$max_file_size = intval(getPlatformSetting($db, 'max_file_size', '10'));
$enable_email_notifications = getPlatformSetting($db, 'enable_email_notifications', '1');
$enable_sms_notifications = getPlatformSetting($db, 'enable_sms_notifications', '0');
$auto_cancel_unconfirmed = getPlatformSetting($db, 'auto_cancel_unconfirmed', '1');
$max_cancellations_per_month = intval(getPlatformSetting($db, 'max_cancellations_per_month', '3'));
$require_rating_after_completion = getPlatformSetting($db, 'require_rating_after_completion', '0');
$enable_ai_features = getPlatformSetting($db, 'enable_ai_features', '1');

// Set timezone
date_default_timezone_set($timezone);

/**
 * Get user's location from IP address
 */
function getUserLocationFromIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // For localhost, use a default location (Kigali)
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return [
            'city' => 'Kigali',
            'country' => 'Rwanda',
            'latitude' => -1.9403,
            'longitude' => 29.8739
        ];
    }
    
    // Use ipapi.co free API (no key required, 1000 requests/day)
    $api_url = "https://ipapi.co/{$ip}/json/";
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['city'])) {
                return [
                    'city' => $data['city'] ?? 'Kigali',
                    'country' => $data['country_name'] ?? 'Rwanda',
                    'latitude' => $data['latitude'] ?? -1.9403,
                    'longitude' => $data['longitude'] ?? 29.8739
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Geolocation error: " . $e->getMessage());
    }
    
    // Default to Kigali if API fails
    return [
        'city' => 'Kigali',
        'country' => 'Rwanda',
        'latitude' => -1.9403,
        'longitude' => 29.8739
    ];
}

// Get user location
$userLocation = getUserLocationFromIP();
$userCity = $userLocation['city'];

/**
 * Calculate distance between two coordinates (Haversine formula)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $latDiff = deg2rad($lat2 - $lat1);
    $lonDiff = deg2rad($lon2 - $lon1);
    
    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDiff / 2) * sin($lonDiff / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

/**
 * Get approximate coordinates for Rwanda cities
 */
function getCityCoordinates($city) {
    $coordinates = [
        'Kigali' => [-1.9403, 29.8739],
        'Rubavu' => [-1.6778, 29.2664],
        'Musanze' => [-1.4997, 29.6384],
        'Huye' => [-2.5976, 29.7389],
        'Rusizi' => [-2.4889, 28.9078],
        'Muhanga' => [-2.0845, 29.7424],
        'Rwamagana' => [-1.9486, 30.4348],
        'Nyagatare' => [-1.2974, 30.3245],
        'Gisenyi' => [-1.7029, 29.2564],
        'Kibuye' => [-2.0606, 29.3475]
    ];
    
    $city = trim($city);
    
    // Try exact match
    if (isset($coordinates[$city])) {
        return $coordinates[$city];
    }
    
    // Try case-insensitive match
    foreach ($coordinates as $key => $value) {
        if (strcasecmp($key, $city) === 0) {
            return $value;
        }
    }
    
    // Default to Kigali
    return $coordinates['Kigali'];
}

// Handle quick booking from provider card
// Handle emergency report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emergency_report'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to report an emergency";
    } else {
        $provider_id = intval($_POST['provider_id']);
        $emergency_type = sanitize($_POST['emergency_type']);
        $emergency_description = sanitize($_POST['emergency_description']);
        
        // Check for toxicity if AI features enabled
        if ($enable_ai_features) {
            $toxicityCheck = $aiHelper->detectToxicity($emergency_description);
            
            if ($toxicityCheck['score'] > 0.9) {
                $booking_errors[] = "Please describe the emergency without inappropriate language.";
                
                // Log toxic content attempt
                try {
                    $logStmt = $db->prepare("
                        INSERT INTO toxic_content_logs (user_id, content_type, toxicity_score, original_text)
                        VALUES (?, 'emergency', ?, ?)
                    ");
                    $logStmt->execute([$_SESSION['user_id'], $toxicityCheck['score'], substr($emergency_description, 0, 500)]);
                } catch (Exception $e) {
                    error_log("Toxicity log error: " . $e->getMessage());
                }
            }
        }
        
        if (empty($booking_errors)) {
            try {
                // Log emergency report
                $stmt = $db->prepare("
                    INSERT INTO emergency_reports (user_id, provider_id, emergency_type, description, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                
                if ($stmt->execute([$_SESSION['user_id'], $provider_id, $emergency_type, $emergency_description])) {
                    // Notify admin
                    $admin_stmt = $db->prepare("SELECT email FROM users WHERE user_type = 'admin' AND is_active = 1");
                    $admin_stmt->execute();
                    $admins = $admin_stmt->fetchAll();
                    
                    foreach ($admins as $admin) {
                        if ($enable_email_notifications) {
                            Mailer::sendEmergencyReport(
                                $admin['email'],
                                $provider_id,
                                $emergency_type,
                                $emergency_description
                            );
                        }
                    }
                    
                    $booking_success = "Emergency report submitted! Our team will respond immediately.";
                }
            } catch (Exception $e) {
                $booking_errors[] = "Failed to submit emergency report. Please try again.";
                error_log("Emergency report error: " . $e->getMessage());
            }
        }
    }
}

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to submit a complaint";
    } else {
        $provider_id = intval($_POST['provider_id']);
        $complaint_type = sanitize($_POST['complaint_type']);
        $complaint_description = sanitize($_POST['complaint_description']);
        
        // Check for toxicity if AI features enabled
        if ($enable_ai_features) {
            $toxicityCheck = $aiHelper->detectToxicity($complaint_description);
            
            if ($toxicityCheck['is_toxic']) {
                $booking_errors[] = "Your complaint contains inappropriate language. Please revise your message.";
                
                // Log toxic content attempt
                try {
                    $logStmt = $db->prepare("
                        INSERT INTO toxic_content_logs (user_id, content_type, toxicity_score, original_text)
                        VALUES (?, 'complaint', ?, ?)
                    ");
                    $logStmt->execute([$_SESSION['user_id'], $toxicityCheck['score'], substr($complaint_description, 0, 500)]);
                } catch (Exception $e) {
                    error_log("Toxicity log error: " . $e->getMessage());
                }
            }
        }
        
        if (empty($booking_errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO complaints (user_id, provider_id, complaint_type, description, status, created_at)
                    VALUES (?, ?, ?, ?, 'open', NOW())
                ");
                
                if ($stmt->execute([$_SESSION['user_id'], $provider_id, $complaint_type, $complaint_description])) {
                    $booking_success = "Complaint submitted successfully. We'll investigate and get back to you.";
                }
            } catch (Exception $e) {
                $booking_errors[] = "Failed to submit complaint. Please try again.";
                error_log("Complaint error: " . $e->getMessage());
            }
        }
    }
}

// Get filters
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';
$locationQuery = isset($_GET['location']) ? trim($_GET['location']) : '';
$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
$minRating = isset($_GET['min_rating']) ? (float) $_GET['min_rating'] : 0;
$availability = isset($_GET['availability']) ? trim($_GET['availability']) : '';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'nearest';

// Use AI to classify search query if no category specified and AI is enabled
if ($enable_ai_features && !empty($searchQuery) && !$categoryId) {
    $aiResult = $aiHelper->classifyServiceFromQuery($searchQuery);
    if ($aiResult && is_array($aiResult) && isset($aiResult['id'])) {
        $categoryId = (int)$aiResult['id'];
        $_SESSION['ai_suggested_category'] = $categoryId;
    } elseif ($aiResult && is_int($aiResult)) {
        // Fallback if it returns just an ID
        $categoryId = (int)$aiResult;
        $_SESSION['ai_suggested_category'] = $categoryId;
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = isset($_GET['page_size']) ? min(48, max(6, (int)$_GET['page_size'])) : 12;
$offset = ($page - 1) * $perPage;

// Fetch all categories
$stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll();

// Build advanced search query
$sqlSelect = "
    SELECT
        u.full_name,
        u.email,
        u.profile_image,
        sp.*,
        GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS category_names,
        sp.verification_level,
        sp.is_featured,
        (
            CASE 
                WHEN sp.profession = :exactSearch THEN 100
                WHEN LOWER(sp.profession) = LOWER(:exactSearch) THEN 95
                WHEN u.full_name = :exactSearch THEN 90
                WHEN LOWER(u.full_name) = LOWER(:exactSearch) THEN 85
                WHEN sp.location = :exactSearch THEN 80
                WHEN LOWER(sp.location) = LOWER(:exactSearch) THEN 75
                WHEN sp.profession LIKE CONCAT(:searchStart, '%') THEN 70
                WHEN u.full_name LIKE CONCAT(:searchStart, '%') THEN 65
                WHEN sp.profession LIKE CONCAT('%', :searchLike, '%') THEN 50
                WHEN u.full_name LIKE CONCAT('%', :searchLike, '%') THEN 45
                WHEN sp.location LIKE CONCAT('%', :searchLike, '%') THEN 40
                WHEN sp.bio LIKE CONCAT('%', :searchLike, '%') THEN 30
                WHEN sp.district LIKE CONCAT('%', :searchLike, '%') THEN 25
                WHEN sp.sector LIKE CONCAT('%', :searchLike, '%') THEN 20
                ELSE 0
            END
            +
            (sp.average_rating * 5)
            +
            (CASE 
                WHEN sp.total_reviews > 20 THEN 15
                WHEN sp.total_reviews > 10 THEN 10
                WHEN sp.total_reviews > 5 THEN 5
                ELSE 0
            END)
            +
            (CASE 
                WHEN sp.availability = 'available' THEN 10
                WHEN sp.availability = 'busy' THEN 5
                ELSE 0
            END)
            +
            (CASE 
                WHEN sp.verification_level = 'premium' THEN 20
                WHEN sp.verification_level = 'gold' THEN 15
                WHEN sp.verification_level = 'verified' THEN 10
                ELSE 0
            END)
            +
            (CASE WHEN sp.is_featured = 1 THEN 25 ELSE 0 END)
        ) AS relevance_score
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN provider_services ps ON sp.id = ps.provider_id
    LEFT JOIN categories c ON ps.category_id = c.id
";

$where = [
    "u.is_verified = 1",
    "sp.is_active = 1",
    "sp.is_banned = 0",
    "u.is_active = 1"
];

$params = [
    ':exactSearch' => $searchQuery,
    ':searchStart' => $searchQuery,
    ':searchLike'  => $searchQuery
];

if ($categoryId) {
    $where[] = "ps.category_id = :categoryId";
    $params[':categoryId'] = $categoryId;
}

if (!empty($locationQuery)) {
    $where[] = "(sp.location LIKE :locationExact OR sp.location LIKE :locationLike OR sp.district LIKE :locationLike OR sp.sector LIKE :locationLike)";
    $params[':locationExact'] = $locationQuery;
    $params[':locationLike'] = "%$locationQuery%";
}

if ($minRating > 0) {
    $where[] = "sp.average_rating >= :minRating";
    $params[':minRating'] = $minRating;
}

if (!empty($availability)) {
    $where[] = "sp.availability = :availability";
    $params[':availability'] = $availability;
}

$sql = $sqlSelect . " WHERE " . implode(" AND ", $where) . " GROUP BY sp.id";

if (!empty($searchQuery)) {
    $sql .= " HAVING relevance_score > 0";
}

// dynamic ORDER BY
if ($sortBy === 'rating') {
    $sql .= " ORDER BY sp.average_rating DESC, sp.total_reviews DESC";
} elseif ($sortBy === 'reviews') {
    $sql .= " ORDER BY sp.total_reviews DESC, sp.average_rating DESC";
} else {
    $sql .= " ORDER BY relevance_score DESC, sp.average_rating DESC, sp.total_reviews DESC";
}

// Execute query
$useDbLimit = ($sortBy === 'rating' || $sortBy === 'reviews' || $sortBy === 'relevance' || $sortBy === '');
$sqlWithLimit = $sql . " LIMIT :offset, :perPage";

try {
    if ($useDbLimit) {
        $stmt = $db->prepare($sqlWithLimit);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
        $stmt->execute();
        $providers = $stmt->fetchAll();
    } else {
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $allProviders = $stmt->fetchAll();

        // compute distance and sort by distance
        $userCoords = [$userLocation['latitude'], $userLocation['longitude']];
        foreach ($allProviders as &$provider) {
            $providerCoords = getCityCoordinates($provider['location'] ?? '');
            $provider['distance'] = calculateDistance(
                $userCoords[0],
                $userCoords[1],
                $providerCoords[0],
                $providerCoords[1]
            );
        }
        usort($allProviders, function($a, $b) {
            return ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0);
        });

        $providers = array_slice($allProviders, $offset, $perPage);
    }
} catch (Exception $e) {
    error_log("Provider search error: " . $e->getMessage());
    $providers = [];
}

// Track search event if there was a search query
if (!empty($searchQuery)) {
    $filters = [
        'location' => $locationQuery,
        'category_id' => $categoryId,
        'min_rating' => $minRating,
        'availability' => $availability,
        'sort_by' => $sortBy
    ];

    // Remove empty filters
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '' && $value !== 0;
    });

    trackEvent('search', 'search', null, [
        'search_query' => $searchQuery,
        'search_type' => 'provider',
        'filters' => $filters,
        'results_count' => count($providers),
        'page' => $page,
        'per_page' => $perPage
    ]);
}

// Handle AJAX request for AI text improvement
if (isset($_GET['improve_text']) && $enable_ai_features) {
    header('Content-Type: application/json');
    $text = $_GET['text'] ?? '';
    
    if (!empty($text) && strlen($text) > 10) {
        $improved = $aiHelper->cleanBookingDescription($text);
        echo json_encode([
            'success' => true,
            'original' => $text,
            'improved' => $improved
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Text too short'
        ]);
    }
    exit;
}

// Handle AJAX request for tracking share
if (isset($_GET['track_share'])) {
    header('Content-Type: application/json');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $provider_id = intval($data['provider_id'] ?? 0);
        $platform = sanitize($data['platform'] ?? 'direct');
        
        if ($provider_id > 0) {
            // Create share_tracking table if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS provider_shares (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    provider_id INT NOT NULL,
                    user_id INT,
                    platform VARCHAR(50),
                    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    KEY(provider_id, shared_at)
                )
            ");
            
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            
            $stmt = $db->prepare("
                INSERT INTO provider_shares (provider_id, user_id, platform)
                VALUES (?, ?, ?)
            ");
            if ($stmt->execute([$provider_id, $user_id, $platform])) {
                $share_id = $db->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Share tracked', 'share_id' => $share_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to track share']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid provider ID']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request for getting share statistics
if (isset($_GET['get_share_stats'])) {
    header('Content-Type: application/json');
    
    try {
        $provider_id = intval($_GET['provider_id'] ?? 0);
        
        if ($provider_id > 0) {
            // Create provider_views table if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS provider_views (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    provider_id INT NOT NULL,
                    user_id INT,
                    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    KEY(provider_id, viewed_at)
                )
            ");
            
            // Get share count
            $share_stmt = $db->prepare("
                SELECT COUNT(*) as count FROM provider_shares 
                WHERE provider_id = ? AND shared_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $share_stmt->execute([$provider_id]);
            $share_count = $share_stmt->fetch()['count'] ?? 0;
            
            // Get view count
            $view_stmt = $db->prepare("
                SELECT COUNT(*) as count FROM provider_views 
                WHERE provider_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $view_stmt->execute([$provider_id]);
            $view_count = $view_stmt->fetch()['count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'share_count' => $share_count,
                'view_count' => $view_count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid provider ID']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request for sending share email
if (isset($_GET['send_share_email'])) {
    header('Content-Type: application/json');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $recipient_email = filter_var($data['recipient_email'] ?? '', FILTER_VALIDATE_EMAIL);
        $provider_id = intval($data['provider_id'] ?? 0);
        $personal_message = sanitize($data['personal_message'] ?? '');
        $share_link = $data['share_link'] ?? '';
        $provider_name = sanitize($data['provider_name'] ?? '');
        $profession = sanitize($data['profession'] ?? '');
        
        if (!$recipient_email || !$provider_id || !$share_link) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        // Prepare email content
        $subject = "Check out {$provider_name} on BII LocalFinder";
        $sender_name = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'A Friend';
        
        $emailBody = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #0d6efd;'>Hi there!</h2>
                    <p>{$sender_name} is recommending you check out a service provider on <strong>BII LocalFinder</strong></p>
                    
                    <div style='background: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <h3 style='margin-top: 0;'>{$provider_name}</h3>
                        <p><strong>Profession:</strong> {$profession}</p>
                        " . (!empty($personal_message) ? "<p><strong>Message:</strong> {$personal_message}</p>" : "") . "
                    </div>
                    
                    <p>
                        <a href='{$share_link}' style='display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                            View Provider Profile
                        </a>
                    </p>
                    
                    <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                    <p style='color: #6c757d; font-size: 12px;'>
                        This link was shared with you through BII LocalFinder. 
                        If you didn't expect this email, you can ignore it.
                    </p>
                </div>
            </body>
            </html>
        ";
        
        // Send email using existing mailer
        if ($enable_email_notifications) {
            try {
                $result = Mailer::sendCustomEmail(
                    $recipient_email,
                    $subject,
                    $emailBody
                );
                
                if ($result === true) {
                    // Log the share
                    $stmt = $db->prepare("
                        INSERT INTO provider_shares (provider_id, user_id, platform)
                        VALUES (?, ?, 'email')
                    ");
                    if ($stmt->execute([$provider_id, $_SESSION['user_id']])) {
                        $share_id = $db->lastInsertId();
                        echo json_encode(['success' => true, 'message' => 'Email sent successfully', 'share_id' => $share_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to log share after email']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
                }
            } catch (Exception $e) {
                error_log("Email send error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error sending email: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Email service is disabled']);
        }
    } catch (Exception $e) {
        error_log("Send share email handler error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Service Providers - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        /* Search Header */
        .search-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            margin-bottom: 2rem;
            padding: 2rem;
        }
        
        .search-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .search-header h1 {
            color: white;
            margin-bottom: 0.5rem;
            font-weight: 700;
            position: relative;
            z-index: 2;
        }
        
        .search-header p {
            color: rgba(255,255,255,0.9);
            position: relative;
            z-index: 2;
        }
        
        /* Search Box */
        .search-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            z-index: 2;
        }
        
        /* Provider Card */
        .provider-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .provider-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .provider-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .provider-card:hover::before {
            transform: scaleX(1);
        }
        
        .distance-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0,0,0,0.85);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .status-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .status-indicator.available {
            background: var(--success);
        }
        
        .status-indicator.busy {
            background: var(--warning);
        }
        
        .status-indicator.unavailable {
            background: var(--danger);
        }
        
        .provider-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .provider-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .provider-card:hover .provider-image img {
            transform: scale(1.05);
        }
        
        .avatar {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            font-weight: bold;
        }
        
        .verification-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            margin-left: 0.5rem;
            font-weight: 600;
            border: 1px solid;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }
        
        .badge-gold {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }
        
        .badge-premium {
            background: #e0e7ff;
            color: #3730a3;
            border-color: #c7d2fe;
        }
        
        .badge-featured {
            background: #fecaca;
            color: #991b1b;
            border-color: #fca5a5;
        }
        
        /* AI Badge */
        .ai-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
            background: none;
            border: none;
        }
        
        .close:hover {
            color: var(--dark);
        }

        /* Modern Share Modal Styles */
        .share-modal-container {
            animation: fadeInShare 0.3s ease;
        }

        @keyframes fadeInShare {
            from {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            to {
                opacity: 1;
                backdrop-filter: blur(5px);
            }
        }

        .share-modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #e9ecef;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUpShare 0.3s ease;
        }

        @keyframes slideUpShare {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .share-modal-header {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            border-bottom: none;
            padding: 1.5rem !important;
        }

        .share-modal-header h3 {
            color: white;
        }

        .share-modal-body {
            padding: 2rem 1.5rem;
        }

        .share-provider-info {
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .share-provider-info:hover {
            background: white;
            border-color: #0d6efd;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1);
        }

        .share-provider-avatar {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            flex-shrink: 0;
        }

        .share-options {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .share-btn {
            padding: 1rem 0.5rem !important;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .share-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .share-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .share-link-section {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .share-link-section .form-control {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }

        .share-link-section .input-group .btn {
            border-radius: 0 6px 6px 0;
        }

        .share-qrcode-container {
            border: 2px dashed #0d6efd;
            transition: all 0.3s ease;
        }

        .share-qrcode-container canvas {
            border-radius: 8px;
            max-width: 200px;
            height: auto;
        }

        .share-email-form {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .share-email-form .form-control {
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }

        .share-email-form textarea.form-control {
            resize: vertical;
        }

        .share-stats {
            border: 1px solid #e9ecef;
        }

        #shareSuccessMessage,
        #copyFeedback {
            animation: slideInAlert 0.3s ease;
        }

        @keyframes slideInAlert {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Filter Tags */
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 1rem;
        }
        
        .filter-tag {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-tag:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-tag.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Toxicity Warning */
        .toxicity-warning {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* AI Improvement Box */
        .ai-improvement-box {
            border-left: 4px solid #667eea;
            background: #f8f9ff;
            transition: all 0.3s ease;
        }
        
        .ai-improvement-box:hover {
            background: #f0f2ff;
            transform: translateX(5px);
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
        
        /* AI Suggestions */
        .ai-suggestion {
            background: linear-gradient(135deg, #f0f4ff, #e6f7ff);
            border: 1px dashed #667eea;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .ai-suggestion::before {
            content: '🤖 AI Suggestion';
            position: absolute;
            top: -10px;
            left: 15px;
            background: white;
            padding: 0 10px;
            font-size: 0.8rem;
            color: #667eea;
            font-weight: 600;
        }

        /* Modern Card Styles */
.modern-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.modern-card:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-4px);
    border-color: var(--primary);
}

/* Avatar Styles */
.avatar-small {
    background: linear-gradient(135deg, var(--primary), #0a58ca);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

/* Compact Badges */
.verification-badge {
    font-size: 0.65rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.badge-verified {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.badge-gold {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #fde68a;
}

.badge-premium {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #3730a3;
    border: 1px solid #c7d2fe;
}

.featured-badge {
    background: linear-gradient(135deg, #fecaca, #fca5a5);
    color: #991b1b;
    border: 1px solid #fca5a5;
    font-size: 0.65rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
}

/* Distance & Status Badges */
.distance-badge.small {
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.9);
}

.status-badge.available {
    color: var(--success);
    border: 1px solid var(--success);
}

.status-badge.busy {
    color: var(--warning);
    border: 1px solid var(--warning);
}

.status-badge.unavailable {
    color: var(--danger);
    border: 1px solid var(--danger);
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.available .status-dot {
    background-color: var(--success);
}

.status-badge.busy .status-dot {
    background-color: var(--warning);
}

.status-badge.unavailable .status-dot {
    background-color: var(--danger);
}

/* Gradient Overlay */
.bg-gradient {
    background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
}

/* Rate Badge */
.rate-badge {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    color: var(--dark);
    border: 1px solid #dee2e6;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
}

/* Button Styles */
.btn-group-sm > .btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
}

/* Rating Stars */
.rating.small .fa-star,
.rating.small .fa-star-half-alt {
    font-size: 0.8rem;
}

/* Hover Effects */
.provider-card .btn-outline-primary:hover {
    background-color: var(--primary);
    color: white;
}

.provider-card .btn-outline-warning:hover {
    background-color: var(--warning);
    color: var(--dark);
}

.provider-card .btn-outline-danger:hover {
    background-color: var(--danger);
    color: white;
}

.provider-card .btn-outline-info:hover {
    background-color: var(--info);
    color: var(--dark);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .col-md-6.col-lg-4.col-xl-3 {
        width: 50%;
    }
}

@media (max-width: 576px) {
    .col-md-6.col-lg-4.col-xl-3 {
        width: 100%;
    }
}
    </style>

    <!-- Shared User Behavior Tracking -->
    <?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
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
        <!-- Search Header -->
        <div class="search-header">
            <h1>
                <?php if (!empty($searchQuery)): ?>
                    Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"
                    <?php if (isset($_SESSION['ai_suggested_category'])): ?>
                        <span class="ai-badge">
                            <i class="fas fa-robot me-1"></i>AI Assisted
                        </span>
                    <?php endif; ?>
                <?php elseif ($categoryId): ?>
                    <?php
                        $catName = '';
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $categoryId) { $catName = $cat['name']; break; }
                        }
                    ?>
                    <?php echo htmlspecialchars($catName ?: 'Category'); ?> Providers
                <?php else: ?>
                    Find Service Providers
                <?php endif; ?>
            </h1>
            <p>Discover skilled professionals near you <?php if ($enable_ai_features): ?><i class="fas fa-robot ms-1" title="AI-Powered Search"></i><?php endif; ?></p>
        </div>

        <!-- AI Suggestion Alert -->
        <?php if (isset($_SESSION['ai_suggested_category'])): 
            $suggestedCat = $_SESSION['ai_suggested_category'];
            $catName = '';
            foreach ($categories as $cat) {
                if ($cat['id'] == $suggestedCat) {
                    $catName = $cat['name'];
                    break;
                }
            }
            unset($_SESSION['ai_suggested_category']);
        ?>
        <div class="ai-suggestion mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-lightbulb text-warning me-3 fs-4"></i>
                <div>
                    <strong>AI Detected:</strong> Your search appears to be related to 
                    <strong>"<?php echo htmlspecialchars($catName); ?>"</strong> services.
                    <div class="text-muted small mt-1">
                        <i class="fas fa-info-circle me-1"></i>
                        We've automatically filtered providers in this category. 
                        <a href="providers.php" class="text-primary ms-1">Show all categories</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search Box -->
        <div class="search-box">
            <form method="GET" action="providers.php" class="mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-search me-1"></i> What service do you need?
                                <?php if ($enable_ai_features): ?>
                                    <span class="badge bg-info ms-1">AI-Powered</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="query" class="form-control" placeholder="e.g., 'fix leaking tap' or 'install lights'" value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <small class="text-muted">Describe what you need - AI will find the right category</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-1"></i> Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g., Kigali, Rubavu" value="<?php echo htmlspecialchars($locationQuery); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-tag me-1"></i> Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($categoryId == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='providers.php'">
                                <i class="fas fa-redo me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Filter Tags -->
                <div class="filter-tags">
                    <div class="filter-tag <?php echo $minRating == 4 ? 'active' : ''; ?>" onclick="setMinRating(4)">
                        <i class="fas fa-star text-warning me-1"></i> 4+ Stars
                    </div>
                    <div class="filter-tag <?php echo $availability === 'available' ? 'active' : ''; ?>" onclick="setAvailability('available')">
                        <i class="fas fa-circle text-success me-1"></i> Available Now
                    </div>
                    <?php if ($enable_ai_features): ?>
                    <div class="filter-tag" onclick="enableAIFeatures()">
                        <i class="fas fa-robot me-1"></i> AI Assistant
                    </div>
                    <?php endif; ?>
                </div>
            </form>
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

        <!-- Providers List -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h3>
                    <?php echo count($providers); ?> provider<?php echo count($providers) != 1 ? 's' : ''; ?> found
                    <?php if ($enable_ai_features): ?>
                        <span class="badge bg-info ms-2" data-bs-toggle="tooltip" title="AI-enhanced results">
                            <i class="fas fa-brain me-1"></i>Smart Search
                        </span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="col-md-6 text-end">
                <label class="me-2">Sort by:</label>
                <select class="form-select form-select-sm d-inline-block w-auto" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => ''])); ?>&sort=' + this.value">
                    <option value="nearest" <?php echo $sortBy === 'nearest' ? 'selected' : ''; ?>>Nearest First</option>
                    <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                    <option value="reviews" <?php echo $sortBy === 'reviews' ? 'selected' : ''; ?>>Most Reviews</option>
                </select>
            </div>
        </div>

        <?php if (!empty($providers)): ?>
    <div class="row g-3">
        <?php foreach ($providers as $provider): ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card provider-card modern-card h-100">
                <!-- Card Header with Status Badges -->
                <div class="card-header p-3 border-bottom-0 bg-transparent">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1 me-2">
                            <h6 class="card-title mb-1 fw-bold text-truncate" style="font-size: 0.95rem;">
                                <?php echo htmlspecialchars($provider['full_name']); ?>
                            </h6>
                            <p class="text-muted mb-0 small text-truncate">
                                <i class="fas fa-briefcase me-1"></i>
                                <?php echo htmlspecialchars($provider['profession']); ?>
                            </p>
                        </div>
                        
                        <!-- Verification & Status Badges -->
                        <div class="d-flex flex-column align-items-end gap-1">
                            <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                                <span class="badge verification-badge small p-1 px-2">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    <?php echo substr(ucfirst($provider['verification_level']), 0, 1); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($provider['is_featured']): ?>
                                <span class="badge featured-badge small p-1 px-2">
                                    <i class="fas fa-star me-1"></i>F
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Image (Smaller) -->
                <div class="position-relative" style="height: 140px; overflow: hidden; border-radius: 8px; margin: 0 0.75rem;">
                    <?php 
                        $prov_img = $provider['profile_image'] ?? '';
                        $prov_initial = strtoupper(substr($provider['full_name'] ?? '', 0, 1)) ?: '?';
                    ?>
                    <?php if (!empty($prov_img)): ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($prov_img); ?>" 
                             alt="<?php echo htmlspecialchars($provider['full_name']); ?>"
                             class="w-100 h-100 object-fit-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <?php endif; ?>
                    
                    <!-- Avatar Fallback -->
                    <div class="avatar-small w-100 h-100" style="display: <?php echo empty($prov_img) ? 'flex' : 'none'; ?>">
                        <span class="text-white fw-bold fs-3"><?php echo $prov_initial; ?></span>
                    </div>
                    
                    <!-- Distance & Status Overlay -->
                    <div class="position-absolute top-0 start-0 end-0 p-2 d-flex justify-content-between">
                        <?php if (isset($provider['distance'])): ?>
                            <span class="distance-badge small">
                                <i class="fas fa-map-marker-alt"></i> <?php echo round($provider['distance']); ?>km
                            </span>
                        <?php endif; ?>
                        
                        <!-- Status Indicator -->
                        <div class="status-badge small <?php echo $provider['availability']; ?>">
                            <span class="status-dot"></span>
                            <?php echo ucfirst($provider['availability']); ?>
                        </div>
                    </div>
                    
                    <!-- Location Overlay at Bottom -->
                    <div class="position-absolute bottom-0 start-0 end-0 p-2 bg-gradient">
                        <p class="text-white mb-0 small d-flex align-items-center">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <span class="text-truncate"><?php echo htmlspecialchars($provider['location']); ?></span>
                        </p>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="card-body p-3 pt-2">
                    <!-- Rating & Reviews -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rating small">
                            <?php 
                            $rating = $provider['average_rating'];
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= floor($rating)): ?>
                                    <i class="fas fa-star text-warning"></i>
                                <?php elseif ($i <= ceil($rating) && (fmod($rating, 1) > 0.3)): ?>
                                    <i class="fas fa-star-half-alt text-warning"></i>
                                <?php else: ?>
                                    <i class="far fa-star text-warning"></i>
                                <?php endif;
                            endfor; ?>
                            <span class="text-muted ms-1 small"><?php echo number_format($rating, 1); ?></span>
                        </div>
                        <span class="text-muted small">(<?php echo $provider['total_reviews']; ?>)</span>
                    </div>
                    
                    <!-- Hourly rate removed; show per-service prices on provider profile -->
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="../client/provider-profile.php?id=<?php echo $provider['id']; ?>" 
                               class="btn btn-outline-primary btn-sm"
                               onclick="if(window.trackClick){window.trackClick('click_provider_view','provider',<?php echo $provider['id']; ?>);} ">
                                <i class="fas fa-eye"></i> View
                            </a>
                            
                            <?php if (isLoggedIn() && !isProvider()): ?>
                                <a href="booking.php?provider_id=<?php echo $provider['id']; ?>" class="btn btn-primary btn-sm"
                                   onclick="if(window.trackClick){window.trackClick('click_provider_book','provider',<?php echo $provider['id']; ?>);} ">
                                    <i class="fas fa-calendar-check"></i> Book
                                </a>
                            <?php else: ?>
                                <a href="../login.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quick Action Buttons -->
                        <div class="d-flex gap-1">
                            <button type="button" 
                                    class="btn btn-outline-warning btn-sm flex-fill" 
                                    onclick="openComplaintModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>')"
                                    data-bs-toggle="tooltip" 
                                    title="Report Issue">
                                <i class="fas fa-flag"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-outline-danger btn-sm flex-fill" 
                                    onclick="openEmergencyModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>')"
                                    data-bs-toggle="tooltip" 
                                    title="Emergency">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                                <button type="button" 
                                    class="btn btn-outline-info btn-sm flex-fill"
                                    data-bs-toggle="tooltip" 
                                    title="Share"
                                    onclick="openShareModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($provider['profession'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($prov_img ?? '', ENT_QUOTES); ?>')">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if (count($providers) >= $perPage): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            </li>
            <li class="page-item active">
                <span class="page-link">Page <?php echo $page; ?></span>
            </li>
            <li class="page-item">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-4x text-muted mb-4"></i>
                <h2 class="mb-3">No providers found</h2>
                <p class="text-muted mb-4">Try adjusting your search criteria or use simpler terms</p>
                <a href="providers.php" class="btn btn-primary"><i class="fas fa-users me-1"></i> Browse All Providers</a>
                <?php if ($enable_ai_features): ?>
                <button class="btn btn-outline-info ms-2" onclick="showAITips()">
                    <i class="fas fa-robot me-1"></i> Get AI Search Tips
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>


    <!-- Emergency Modal -->
    <div id="emergencyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Emergency Report</h3>
                <button type="button" class="close" onclick="closeEmergencyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="provider_id" id="emergencyProviderId">
                    
                    <div class="alert alert-danger mb-4">
                        <strong>Use this only for urgent safety concerns</strong>
                        <p class="mb-0 mt-2">This report goes directly to our safety team for immediate action.</p>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Emergency Type <span class="text-danger">*</span></label>
                        <select name="emergency_type" class="form-control" required>
                            <option value="">Select emergency type</option>
                            <option value="safety_concern">Safety Concern</option>
                            <option value="harassment">Harassment</option>
                            <option value="fraud">Fraud/Theft</option>
                            <option value="quality_emergency">Service Quality Emergency</option>
                            <option value="other">Other Emergency</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Emergency Description <span class="text-danger">*</span>
                            <?php if ($enable_ai_features): ?>
                                <span class="badge bg-danger ms-1">Content Monitored</span>
                            <?php endif; ?>
                        </label>
                        <textarea name="emergency_description" id="emergencyDescription" class="form-control" required rows="4" placeholder="Please describe the emergency situation in detail..."></textarea>
                        <!-- Toxicity warning will appear here -->
                        <div id="emergencyToxicityWarning" class="toxicity-warning alert alert-warning mt-2 d-none"></div>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" name="emergency_report" class="btn btn-danger">
                            <i class="fas fa-exclamation-circle me-1"></i> Submit Emergency Report
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeEmergencyModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complaint Modal -->
    <div id="complaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-warning"><i class="fas fa-flag me-2"></i>Submit Complaint</h3>
                <button type="button" class="close" onclick="closeComplaintModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="provider_id" id="complaintProviderId">
                    
                    <div class="alert alert-warning mb-4">
                        <strong>Help us improve our service</strong>
                        <p class="mb-0 mt-2">Your feedback helps us maintain quality standards.</p>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Complaint Type <span class="text-danger">*</span></label>
                        <select name="complaint_type" class="form-control" required>
                            <option value="">Select complaint type</option>
                            <option value="quality">Service Quality</option>
                            <option value="behavior">Professional Behavior</option>
                            <option value="pricing">Pricing Issues</option>
                            <option value="timing">Timing/Delays</option>
                            <option value="communication">Communication Problems</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Complaint Description <span class="text-danger">*</span>
                            <?php if ($enable_ai_features): ?>
                                <span class="badge bg-warning ms-1">Content Monitored</span>
                            <?php endif; ?>
                        </label>
                        <textarea name="complaint_description" id="complaintDescription" class="form-control" required rows="4" placeholder="Please describe your complaint in detail..."></textarea>
                        <!-- Toxicity warning will appear here -->
                        <div id="complaintToxicityWarning" class="toxicity-warning alert alert-warning mt-2 d-none"></div>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" name="submit_complaint" class="btn btn-warning">
                            <i class="fas fa-flag me-1"></i> Submit Complaint
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeComplaintModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modern Share Modal -->
    <div id="shareModal" class="modal share-modal-container">
        <div class="modal-content share-modal-content">
            <div class="modal-header share-modal-header">
                <h3 class="text-info mb-0"><i class="fas fa-share-alt me-2"></i>Share Provider</h3>
                <button type="button" class="close" onclick="closeShareModal()">&times;</button>
            </div>
            <div class="modal-body share-modal-body">
                <!-- Provider Info -->
                <div class="share-provider-info mb-4 p-3 bg-light rounded">
                    <div class="d-flex align-items-center">
                        <div class="share-provider-avatar me-3">
                            <img id="shareProviderImage" src="" alt="Provider" style="display:none; width:60px; height:60px; object-fit:cover; border-radius:8px;" />
                            <span id="shareProviderInitial" class="d-flex align-items-center justify-content-center fw-bold text-white" style="width:60px; height:60px; border-radius:8px;">?</span>
                        </div>
                        <div>
                            <h5 id="shareProviderName" class="mb-1">Provider Name</h5>
                            <p id="shareProviderProfession" class="text-muted mb-0">Profession</p>
                        </div>
                    </div>
                </div>

                <!-- Share Options -->
                <div class="share-options mb-4">
                    <h6 class="mb-3 fw-bold">Share via:</h6>
                    <div class="row g-2">
                        <!-- Whatsapp -->
                        <div class="col-6 col-md-4">
                            <button type="button" class="btn btn-outline-success w-100 share-btn" 
                                    onclick="shareVia('whatsapp')" data-bs-toggle="tooltip" title="Share on WhatsApp">
                                <i class="fab fa-whatsapp fs-5"></i>
                                <small class="d-block mt-1">WhatsApp</small>
                            </button>
                        </div>
                        <!-- Facebook -->
                        <div class="col-6 col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100 share-btn" 
                                    onclick="shareVia('facebook')" data-bs-toggle="tooltip" title="Share on Facebook">
                                <i class="fab fa-facebook-f fs-5"></i>
                                <small class="d-block mt-1">Facebook</small>
                            </button>
                        </div>
                        <!-- Twitter -->
                        <div class="col-6 col-md-4">
                            <button type="button" class="btn btn-outline-info w-100 share-btn" 
                                    onclick="shareVia('twitter')" data-bs-toggle="tooltip" title="Share on Twitter">
                                <i class="fab fa-twitter fs-5"></i>
                                <small class="d-block mt-1">Twitter</small>
                            </button>
                        </div>
                        <!-- Email -->
                        <div class="col-6 col-md-4">
                            <button type="button" class="btn btn-outline-danger w-100 share-btn" 
                                    onclick="shareVia('email')" data-bs-toggle="tooltip" title="Share via Email">
                                <i class="fas fa-envelope fs-5"></i>
                                <small class="d-block mt-1">Email</small>
                            </button>
                        </div>
                        <!-- Copy Link -->
                        <div class="col-6 col-md-4">
                            <button type="button" class="btn btn-outline-secondary w-100 share-btn" 
                                    onclick="shareVia('copy')" data-bs-toggle="tooltip" title="Copy Link">
                                <i class="fas fa-link fs-5"></i>
                                <small class="d-block mt-1">Copy Link</small>
                            </button>
                        </div>
                        <!-- QR Code -->
                        <div class="col-6 col-md-4">
                            <button type="button" class="btn btn-outline-warning w-100 share-btn" 
                                    onclick="shareVia('qrcode')" data-bs-toggle="tooltip" title="Show QR Code">
                                <i class="fas fa-qrcode fs-5"></i>
                                <small class="d-block mt-1">QR Code</small>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Share Link Input -->
                <div class="share-link-section mb-3">
                    <label class="form-label fw-bold">Share Link:</label>
                    <div class="input-group">
                        <input type="text" id="shareLink" class="form-control" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyShareLink()" id="copyLinkBtn">
                            <i class="fas fa-copy me-1"></i> Copy
                        </button>
                    </div>
                </div>

                <!-- QR Code Container (Hidden by default) -->
                <div id="qrCodeContainer" class="share-qrcode-container p-3 bg-light rounded d-none text-center mb-3">
                    <p class="text-muted mb-2 small">Scan with your phone:</p>
                    <canvas id="qrCanvas"></canvas>
                    <p class="text-muted mt-2 small">
                        <i class="fas fa-info-circle me-1"></i>
                        This QR code links directly to this provider's profile
                    </p>
                </div>

                <!-- Email Form (Hidden by default) -->
                <div id="emailShareForm" class="share-email-form d-none">
                    <form id="emailShareFormElement" onsubmit="submitEmailShare(event)">
                        <div class="mb-3">
                            <label for="recipientEmail" class="form-label">Recipient Email:</label>
                            <input type="email" class="form-control" id="recipientEmail" required 
                                   placeholder="recipient@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="personalMessage" class="form-label">Personal Message (Optional):</label>
                            <textarea class="form-control" id="personalMessage" rows="3" 
                                      placeholder="Add a personal message..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-paper-plane me-1"></i> Send Email
                        </button>
                    </form>
                </div>

                <!-- Share Statistics -->
                <div class="share-stats mt-4 p-3 bg-light rounded">
                    <small class="text-muted d-block mb-2"><i class="fas fa-chart-line me-1"></i> Share Statistics</small>
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <p class="mb-0"><strong id="shareCount">0</strong></p>
                            <small class="text-muted">Times Shared</small>
                        </div>
                        <div class="col-6">
                            <p class="mb-0"><strong id="viewCount">0</strong></p>
                            <small class="text-muted">Profile Views</small>
                        </div>
                    </div>
                </div>

                <!-- Success Message -->
                <div id="shareSuccessMessage" class="alert alert-success mt-3 d-none" role="alert">
                    <i class="fas fa-check-circle me-2"></i><span id="successText"></span>
                </div>

                <!-- Copy Feedback -->
                <div id="copyFeedback" class="alert alert-info mt-3 d-none" role="alert">
                    <i class="fas fa-clipboard me-2"></i>Link copied to clipboard!
                </div>
            </div>
        </div>
    </div>

    <!-- AI Tips Modal -->
    <div id="aiTipsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-info"><i class="fas fa-robot me-2"></i>AI Search Tips</h3>
                <button type="button" class="close" onclick="closeAITipsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <strong>Get better results with our AI assistant!</strong>
                </div>
                
                <div class="mb-4">
                    <h5><i class="fas fa-lightbulb text-warning me-2"></i> Better Search Examples:</h5>
                    <ul>
                        <li><strong>Instead of:</strong> "fix"</li>
                        <li><strong>Try:</strong> "fix leaking bathroom tap" or "repair broken window"</li>
                    </ul>
                    <ul>
                        <li><strong>Instead of:</strong> "clean"</li>
                        <li><strong>Try:</strong> "deep clean apartment before moving" or "office cleaning service"</li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <h5><i class="fas fa-shield-alt text-success me-2"></i> Safety Features:</h5>
                    <ul>
                        <li>Toxicity detection in complaints & emergencies</li>
                        <li>AI-improved booking descriptions</li>
                        <li>Smart category matching</li>
                    </ul>
                </div>
                
                <div class="text-center">
                    <button type="button" class="btn btn-info" onclick="closeAITipsModal()">
                        <i class="fas fa-check me-1"></i> Got it!
                    </button>
                </div>
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
        
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
            // Modal functions
        function openEmergencyModal(providerId, providerName) {
            document.getElementById('emergencyProviderId').value = providerId;
            document.getElementById('emergencyModal').style.display = 'block';
            
            // Setup toxicity check for emergency description
            setupToxicityCheck('emergencyDescription', 'emergencyToxicityWarning');
        }

        function closeEmergencyModal() {
            document.getElementById('emergencyModal').style.display = 'none';
            document.getElementById('emergencyToxicityWarning').classList.add('d-none');
        }

        function openComplaintModal(providerId, providerName) {
            document.getElementById('complaintProviderId').value = providerId;
            document.getElementById('complaintModal').style.display = 'block';
            
            // Setup toxicity check for complaint description
            setupToxicityCheck('complaintDescription', 'complaintToxicityWarning');
        }

        function closeComplaintModal() {
            document.getElementById('complaintModal').style.display = 'none';
            document.getElementById('complaintToxicityWarning').classList.add('d-none');
        }

        // Advanced Share Functions
        let currentShareProviderId = null;
        let currentShareProviderName = null;
        let currentShareProfession = null;
        let currentShareId = null;

        function openShareModal(providerId, providerName, profession, profileImage) {
            currentShareProviderId = providerId;
            currentShareProviderName = providerName;
            currentShareProfession = profession;

            // Update provider info in modal
            document.getElementById('shareProviderName').textContent = providerName;
            document.getElementById('shareProviderProfession').textContent = profession;
            document.getElementById('shareProviderInitial').textContent = providerName.charAt(0).toUpperCase();

            // Show uploaded profile image if provided, otherwise show initial
            const imgEl = document.getElementById('shareProviderImage');
            const initialEl = document.getElementById('shareProviderInitial');
            if (profileImage && profileImage.trim() !== '') {
                const src = window.location.origin + '/uploads/profiles/' + profileImage;
                imgEl.src = src;
                imgEl.style.display = 'block';
                initialEl.style.display = 'none';
                imgEl.onerror = function() {
                    this.style.display = 'none';
                    initialEl.style.display = 'flex';
                };
            } else {
                imgEl.style.display = 'none';
                initialEl.style.display = 'flex';
            }

            // Generate share link
            const shareLink = window.location.origin + '/client/provider-profile.php?id=' + providerId;
            document.getElementById('shareLink').value = shareLink;
            currentShareId = null;

            // Fetch share statistics
            fetchShareStats(providerId);

            // Reset forms
            document.getElementById('emailShareForm').classList.add('d-none');
            document.getElementById('qrCodeContainer').classList.add('d-none');
            document.getElementById('shareSuccessMessage').classList.add('d-none');
            document.getElementById('copyFeedback').classList.add('d-none');
            document.getElementById('emailShareFormElement').reset();

            // Ensure avatar reset when reopening
            if (!profileImage || profileImage.trim() === '') {
                document.getElementById('shareProviderImage').style.display = 'none';
                document.getElementById('shareProviderInitial').style.display = 'flex';
            }

            // Show modal with animation
            document.getElementById('shareModal').style.display = 'block';

            // Initialize tooltips
            setTimeout(() => {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(tooltip => {
                    new bootstrap.Tooltip(tooltip);
                });
            }, 100);
        }

        function closeShareModal() {
            document.getElementById('shareModal').style.display = 'none';
        }

        function shareVia(platform) {
            const shareLink = document.getElementById('shareLink').value;
            const providerName = currentShareProviderName;
            const profession = currentShareProfession;
            const shareText = `Check out ${providerName} - ${profession} on BII LocalFinder! ${shareLink}`;

            switch(platform) {
                case 'whatsapp':
                    window.open(`https://wa.me/?text=${encodeURIComponent(shareText)}`);
                    trackShare('WhatsApp');
                    break;
                case 'facebook':
                    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareLink)}`);
                    trackShare('Facebook');
                    break;
                case 'twitter':
                    window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(shareLink)}&text=${encodeURIComponent(shareText)}`);
                    trackShare('Twitter');
                    break;
                case 'email':
                    showEmailShareForm();
                    break;
                case 'copy':
                    copyShareLink();
                    trackShare('Link Copy');
                    break;
                case 'qrcode':
                    showQRCode(shareLink);
                    trackShare('QR Code');
                    break;
            }
        }

        function copyShareLink() {
            const shareLink = document.getElementById('shareLink').value;
            navigator.clipboard.writeText(shareLink).then(() => {
                // Show feedback
                document.getElementById('copyFeedback').classList.remove('d-none');
                
                // Change button text temporarily
                const copyBtn = document.getElementById('copyLinkBtn');
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
                copyBtn.classList.add('btn-success');

                setTimeout(() => {
                    document.getElementById('copyFeedback').classList.add('d-none');
                    copyBtn.innerHTML = originalText;
                    copyBtn.classList.remove('btn-success');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy link: ' + err);
            });
        }

        function showEmailShareForm() {
            document.getElementById('emailShareForm').classList.toggle('d-none');
            document.getElementById('qrCodeContainer').classList.add('d-none');
        }

        function submitEmailShare(e) {
            e.preventDefault();
            
            const email = document.getElementById('recipientEmail').value;
            const message = document.getElementById('personalMessage').value;
            const shareLink = document.getElementById('shareLink').value;
            const providerName = currentShareProviderName;
            const profession = currentShareProfession;

            // Send email via AJAX
            fetch('providers.php?send_share_email=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    recipient_email: email,
                    provider_id: currentShareProviderId,
                    personal_message: message,
                    share_link: shareLink,
                    provider_name: providerName,
                    profession: profession
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showShareSuccess('Email sent successfully!');
                    document.getElementById('emailShareFormElement').reset();
                    document.getElementById('emailShareForm').classList.add('d-none');
                } else {
                    alert('Failed to send email: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending email');
            });
        }

        function showQRCode(url) {
            const container = document.getElementById('qrCodeContainer');
            const canvas = document.getElementById('qrCanvas');

            container.classList.toggle('d-none');

            if (!container.classList.contains('d-none')) {
                // Check if QR code library is loaded
                if (typeof QRCode === 'undefined') {
                    // Load QR code library dynamically
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                    document.head.appendChild(script);
                    
                    script.onload = function() {
                        generateQRCode(canvas, url);
                    };
                } else {
                    generateQRCode(canvas, url);
                }
            }
        }

        function generateQRCode(canvas, url) {
            canvas.innerHTML = '';
            
            // Create QR code
            new QRCode(canvas, {
                text: url,
                width: 200,
                height: 200,
                correctLevel: QRCode.CorrectLevel.H
            });
        }

        function showShareSuccess(message) {
            const successDiv = document.getElementById('shareSuccessMessage');
            document.getElementById('successText').textContent = message;
            successDiv.classList.remove('d-none');
            
            setTimeout(() => {
                successDiv.classList.add('d-none');
            }, 3000);
        }

        function trackShare(platform) {
            // Track share via AJAX
            return fetch('providers.php?track_share=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider_id: currentShareProviderId,
                    platform: platform
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.share_id) {
                    currentShareId = data.share_id;

                    const shareLinkInput = document.getElementById('shareLink');
                    if (shareLinkInput) {
                        try {
                            const url = new URL(shareLinkInput.value);
                            url.searchParams.set('share_id', data.share_id);
                            shareLinkInput.value = url.toString();
                        } catch (e) {
                            console.error('Invalid share link format:', e);
                        }
                    }

                    fetchShareStats(currentShareProviderId);
                }
                return data;
            })
            .catch(error => {
                console.error('Error tracking share:', error);
                return { success: false, error };
            });
        }

        function fetchShareStats(providerId) {
            fetch(`providers.php?get_share_stats=1&provider_id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('shareCount').textContent = data.share_count || 0;
                        document.getElementById('viewCount').textContent = data.view_count || 0;
                    }
                })
                .catch(error => console.error('Error fetching stats:', error));
        }

        function showAITips() {
            document.getElementById('aiTipsModal').style.display = 'block';
        }

        function closeAITipsModal() {
            document.getElementById('aiTipsModal').style.display = 'none';
        }

        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Quick filter functions
        function setMinRating(rating) {
            const url = new URL(window.location.href);
            url.searchParams.set('min_rating', rating);
            window.location.href = url.toString();
        }

        function setAvailability(availability) {
            const url = new URL(window.location.href);
            url.searchParams.set('availability', availability);
            window.location.href = url.toString();
        }

        function enableAIFeatures() {
            // This would toggle AI features - for now just show info
            alert('AI features are already enabled! They help with:\n1. Smart search categorization\n2. Toxicity detection\n3. Text improvement');
        }

        // AI Text Improvement
        function setupAIImprovement() {
            const textarea = document.getElementById('serviceDescription');
            const aiBox = document.getElementById('aiImprovement');
            
            if (!textarea || !aiBox) return;
            
            let improvementTimeout;
            
            textarea.addEventListener('input', function() {
                clearTimeout(improvementTimeout);
                aiBox.classList.add('d-none');
                
                const text = this.value;
                if (text.length > 30 && text.length < 500) {
                    improvementTimeout = setTimeout(() => {
                        getAIImprovement(text);
                    }, 2000);
                }
            });
        }

        function getAIImprovement(text) {
            fetch(`providers.php?improve_text=1&text=${encodeURIComponent(text)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.improved && data.improved !== text) {
                        showAIImprovement(data.improved);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function showAIImprovement(improvedText) {
            const aiBox = document.getElementById('aiImprovement');
            const suggestionDiv = document.getElementById('aiSuggestion');
            
            suggestionDiv.textContent = improvedText;
            aiBox.classList.remove('d-none');
            
            // Smooth scroll to AI suggestion
            aiBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function useAISuggestion() {
            const suggestionDiv = document.getElementById('aiSuggestion');
            const textarea = document.getElementById('serviceDescription');
            
            if (textarea && suggestionDiv.textContent) {
                textarea.value = suggestionDiv.textContent;
                document.getElementById('aiImprovement').classList.add('d-none');
                
                // Show success feedback
                const originalBtn = textarea.parentNode.querySelector('button[type="submit"]');
                if (originalBtn) {
                    const originalText = originalBtn.innerHTML;
                    originalBtn.innerHTML = '<i class="fas fa-check me-1"></i> AI Text Applied!';
                    originalBtn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        originalBtn.innerHTML = originalText;
                        originalBtn.classList.remove('btn-success');
                    }, 2000);
                }
            }
        }

        // Toxicity Detection
        function setupToxicityCheck(textareaId, warningDivId) {
            const textarea = document.getElementById(textareaId);
            const warningDiv = document.getElementById(warningDivId);
            
            if (!textarea || !warningDiv) return;
            
            let toxicityTimeout;
            
            textarea.addEventListener('input', function() {
                clearTimeout(toxicityTimeout);
                warningDiv.classList.add('d-none');
                
                const text = this.value;
                if (text.length > 20) {
                    toxicityTimeout = setTimeout(() => {
                        checkToxicity(text, warningDiv);
                    }, 1500);
                }
            });
        }

        function checkToxicity(text, warningDiv) {
            fetch('../includes/check_toxicity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ text: text })
            })
            .then(response => response.json())
            .then(data => {
                if (data.is_toxic) {
                    let severity = 'Low';
                    if (data.score > 0.8) severity = 'High';
                    else if (data.score > 0.5) severity = 'Medium';
                    
                    warningDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Content Warning:</strong> Your message contains potentially inappropriate language (${severity} severity: ${(data.score * 100).toFixed(0)}%).
                        Please revise your message to be more professional.
                    `;
                    warningDiv.classList.remove('d-none');
                    
                    // Add shake animation
                    warningDiv.classList.add('toxicity-warning');
                    setTimeout(() => {
                        warningDiv.classList.remove('toxicity-warning');
                    }, 500);
                }
            })
            .catch(error => console.error('Error checking toxicity:', error));
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Initialize AI features if enabled
        <?php if ($enable_ai_features): ?>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('AI features enabled');
            
            // Add AI helper to search box
            const searchBox = document.querySelector('input[name="query"]');
            if (searchBox) {
                searchBox.addEventListener('focus', function() {
                    this.setAttribute('title', 'Tip: Describe what you need in plain language. AI will find the right category.');
                });
            }
        });
        <?php endif; ?>

        // Track search form submission
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.querySelector('form[action="providers.php"]');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    const searchQuery = searchForm.querySelector('input[name="query"]').value.trim();
                    const locationQuery = searchForm.querySelector('input[name="location"]').value.trim();
                    const categoryId = searchForm.querySelector('select[name="category"]').value;

                    if (searchQuery || locationQuery || categoryId) {
                        const filters = {};
                        if (locationQuery) filters.location = locationQuery;
                        if (categoryId) filters.category_id = categoryId;

                        // Track the search
                        if (typeof window.trackSearch === 'function') {
                            window.trackSearch(searchQuery || locationQuery, 'providers', filters, <?php echo count($providers); ?>);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>