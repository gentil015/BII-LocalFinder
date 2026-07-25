<?php
session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';
require_once 'includes/functions.php';
require_once 'includes/provider_sections.php';
require_once 'includes/chat.php';

$db = Database::getInstance()->getConnection();
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

// Set timezone
date_default_timezone_set($timezone);

// --- PERF: gzip + session non-blocking + lightweight request-scoped session flags ---
if (!headers_sent()) {
    ob_start('ob_gzhandler');
}

// capture session flags early, then release lock so other requests aren't blocked
$isLoggedIn = false;
$isProvider = false;
$currentUserId = null;
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isLoggedIn = isLoggedIn();
$isProvider = isProvider();
$currentUserId = $_SESSION['user_id'] ?? null;
session_write_close();

// --- simple file cache helper (TTL seconds) ---
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
function cache_get($key, $ttl = 60) {
    global $cacheDir;
    $file = "$cacheDir/".hash('sha256',$key).".cache";
    if (!file_exists($file)) return false;
    if (filemtime($file) + $ttl < time()) { unlink($file); return false; }
    return unserialize(file_get_contents($file));
}
function cache_set($key, $value) {
    global $cacheDir;
    $file = "$cacheDir/".hash('sha256',$key).".cache";
    file_put_contents($file, serialize($value), LOCK_EX);
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_book'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to book a service";
    } elseif (isProvider()) {
        $booking_errors[] = "Providers cannot book services";
    } else {
        $provider_id = intval($_POST['provider_id']);
        $service_description = sanitize($_POST['service_description']);
        $preferred_date = sanitize($_POST['preferred_date']);
        
        if (empty($service_description) || empty($preferred_date)) {
            $booking_errors[] = "Please fill all required fields";
        }
        
        // Check if provider exists and is active
        $provider_check = $db->prepare("
            SELECT sp.id, u.id AS user_id, u.full_name, u.email 
            FROM service_providers sp 
            JOIN users u ON sp.user_id = u.id 
            WHERE sp.id = ? AND sp.is_active = 1 AND u.is_verified = 1
        ");
        $provider_check->execute([$provider_id]);
        $provider = $provider_check->fetch();
        
        if (!$provider) {
            $booking_errors[] = "Provider not available or not verified";
        }
        
        if (empty($booking_errors)) {
            try {
                // Insert booking
                $stmt = $db->prepare("
                    INSERT INTO bookings (client_id, provider_id, service_description, preferred_date, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                
                if ($stmt->execute([$_SESSION['user_id'], $provider_id, $service_description, $preferred_date])) {
                    $booking_id = $db->lastInsertId();
                    $booking_ref = '#BK-' . date('Y') . '-' . str_pad($booking_id,5,'0',STR_PAD_LEFT);
                    
                    // Update user_profiles to track booking metrics
                    $update_profile = $db->prepare("
                        INSERT INTO user_profiles (user_id, user_total_bookings, user_avg_price, user_avg_response_time) 
                        VALUES (?, 1, 0, 24) 
                        ON DUPLICATE KEY UPDATE 
                            user_total_bookings = user_total_bookings + 1,
                            updated_at = CURRENT_TIMESTAMP
                    ");
                    $update_profile->execute([$_SESSION['user_id']]);
                    
                    $provider_user_id = $provider['user_id'] ?? $provider_id;
                    sendMessage($_SESSION['user_id'], $provider_user_id, "New booking created: " . $booking_ref);
                    header('Location: client/messages.php?with=' . $provider_user_id . '&booking_id=' . $booking_id);
                    exit;

                    // Get provider details for email
                    if ($provider && $enable_email_notifications) {
                        // Send email notification
                        Mailer::sendBookingNotification(
                            $provider['email'],
                            $provider['full_name'],
                            $_SESSION['user_name'],
                            $service_description
                        );
                    }
                    
                    $booking_success = "Booking request sent successfully! The provider will contact you soon.";
                } else {
                    $booking_errors[] = "Failed to send booking request. Please try again.";
                }
            } catch (Exception $e) {
                $booking_errors[] = "An error occurred. Please try again.";
                error_log("Booking error: " . $e->getMessage());
            }
        }
    }
}

// Handle emergency report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emergency_report'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to report an emergency";
    } else {
        $provider_id = intval($_POST['provider_id']);
        $emergency_type = sanitize($_POST['emergency_type']);
        $emergency_description = sanitize($_POST['emergency_description']);
        
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

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    if (!isLoggedIn()) {
        $booking_errors[] = "Please login to submit a complaint";
    } else {
        $provider_id = intval($_POST['provider_id']);
        $complaint_type = sanitize($_POST['complaint_type']);
        $complaint_description = sanitize($_POST['complaint_description']);
        
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

// Get filters
$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
$serviceId = isset($_GET['service_id']) ? (int) $_GET['service_id'] : null;
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';
$locationQuery = isset($_GET['location']) ? trim($_GET['location']) : '';
$districtFilter = isset($_GET['district']) ? trim($_GET['district']) : '';
$minRating = isset($_GET['min_rating']) ? (float) $_GET['min_rating'] : 0;
$priceRange = isset($_GET['price_range']) ? trim($_GET['price_range']) : '';
$experienceLevel = isset($_GET['experience']) ? trim($_GET['experience']) : '';
$verificationLevel = isset($_GET['verification']) ? trim($_GET['verification']) : '';
$availability = isset($_GET['availability']) ? trim($_GET['availability']) : '';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'nearest'; // nearest, rating, reviews

// Pagination (limit result set to avoid heavy load)
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = isset($_GET['page_size']) ? min(48, max(6, (int)$_GET['page_size'])) : 12;
$offset = ($page - 1) * $perPage;

// Fetch all categories (cache for 5 minutes)
$categories = cache_get('categories_list', 300);
if ($categories === false) {
    $stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
    cache_set('categories_list', $categories);
}

// Fetch districts (cache for 10 minutes)
$districts = cache_get('districts_list', 600);
if ($districts === false) {
    $stmt = $db->query("SELECT DISTINCT district FROM service_providers WHERE district IS NOT NULL AND district != '' ORDER BY district");
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    cache_set('districts_list', $districts);
}

// Fetch popular services (cache for 5 minutes)
$popular_services_data = cache_get('popular_individual_services', 300);
if ($popular_services_data === false) {
    try {
        $providerWhere = ["u.is_verified = 1", "sp.availability = 'available'", "ps.is_available = 1"];
        $providerWhere[] = "sp.is_active = 1";
        $providerWhere[] = "sp.is_banned = 0";
        $providerWhereSql = implode(' AND ', $providerWhere);
        
        // Get most popular individual services based on bookings or provider count
        $stmt = $db->prepare("
            SELECT 
                ps.id as service_id,
                ps.name as service_name,
                ps.description as service_description,
                ps.price as service_price,
                ps.payment_type,
                ps.duration,
                c.name as category_name,
                c.icon as category_icon,
                COUNT(DISTINCT sp.id) as provider_count,
                COUNT(DISTINCT b.id) as booking_count,
                AVG(sp.average_rating) as avg_rating
            FROM provider_services ps
            JOIN service_providers sp ON ps.provider_id = sp.id
            JOIN users u ON sp.user_id = u.id
            JOIN categories c ON ps.category_id = c.id
            LEFT JOIN bookings b ON ps.id = b.service_id AND b.status = 'completed'
            WHERE {$providerWhereSql}
            AND c.is_active = 1
            GROUP BY ps.id, ps.name, ps.description, ps.price, ps.payment_type
            HAVING provider_count > 0
            ORDER BY booking_count DESC, provider_count DESC
            LIMIT 8
        ");
        $stmt->execute();
        $popular_services_data = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Popular services fetch error: " . $e->getMessage());
        $popular_services_data = [];
    }
    cache_set('popular_individual_services', $popular_services_data);
}

// If no data from actual services, show default services
if (empty($popular_services_data)) {
    $default_services = [
        [
            'service_name' => 'Plumbing Repair', 
            'service_description' => 'Fix leaks, install pipes, unclog drains',
            'service_price' => 15000, 
            'payment_type' => 'per_service',
            'category_icon' => 'fa-wrench',
            'provider_count' => 23
        ],
        [
            'service_name' => 'Electrical Wiring', 
            'service_description' => 'Install outlets, fix circuits, lighting',
            'service_price' => 12000, 
            'payment_type' => 'per_hour',
            'category_icon' => 'fa-bolt',
            'provider_count' => 18
        ],
        [
            'service_name' => 'Phone Screen Repair', 
            'service_description' => 'Fix cracked screens, replace batteries',
            'service_price' => 25000, 
            'payment_type' => 'per_service',
            'category_icon' => 'fa-mobile-alt',
            'provider_count' => 15
        ],
        [
            'service_name' => 'House Cleaning', 
            'service_description' => 'Deep cleaning, regular maintenance',
            'service_price' => 8000, 
            'payment_type' => 'per_hour',
            'category_icon' => 'fa-broom',
            'provider_count' => 27
        ],
        [
            'service_name' => 'Car Maintenance', 
            'service_description' => 'Oil change, brake repair, diagnostics',
            'service_price' => 20000, 
            'payment_type' => 'per_service',
            'category_icon' => 'fa-car',
            'provider_count' => 12
        ],
        [
            'service_name' => 'Interior Painting', 
            'service_description' => 'Wall painting, color consultation',
            'service_price' => 10000, 
            'payment_type' => 'per_day',
            'category_icon' => 'fa-paint-roller',
            'provider_count' => 14
        ],
        [
            'service_name' => 'Furniture Making', 
            'service_description' => 'Custom furniture, repairs, installations',
            'service_price' => 18000, 
            'payment_type' => 'per_service',
            'category_icon' => 'fa-hammer',
            'provider_count' => 11
        ],
        [
            'service_name' => 'Garden Maintenance', 
            'service_description' => 'Lawn care, planting, landscaping',
            'service_price' => 7000, 
            'payment_type' => 'per_hour',
            'category_icon' => 'fa-leaf',
            'provider_count' => 9
        ]
    ];
    $popular_services_data = $default_services;
}

// Build advanced search query with scoring (replaced for correct WHERE / GROUP BY order and dynamic ordering)
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

// build WHERE clauses correctly (before GROUP BY)
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
    // keep LEFT JOIN but filter by provider_services (this makes it act like an inner filter)
    $where[] = "ps.category_id = :categoryId";
    $params[':categoryId'] = $categoryId;
}

if ($serviceId) {
    // Filter by specific service
    $where[] = "ps.id = :serviceId";
    $params[':serviceId'] = $serviceId;
}

if (!empty($locationQuery)) {
    $where[] = "(sp.location LIKE :locationExact OR sp.location LIKE :locationLike OR sp.district LIKE :locationLike OR sp.sector LIKE :locationLike)";
    $params[':locationExact'] = $locationQuery;
    $params[':locationLike'] = "%$locationQuery%";
}

if (!empty($districtFilter)) {
    $where[] = "sp.district = :districtFilter";
    $params[':districtFilter'] = $districtFilter;
}

if ($minRating > 0) {
    $where[] = "sp.average_rating >= :minRating";
    $params[':minRating'] = $minRating;
}

if (!empty($priceRange)) {
    switch ($priceRange) {
        case '0-5000':
            $where[] = "(sp.hourly_rate IS NULL OR sp.hourly_rate <= 5000)";
            break;
        case '5000-10000':
            $where[] = "(sp.hourly_rate >= 5000 AND sp.hourly_rate <= 10000)";
            break;
        case '10000-20000':
            $where[] = "(sp.hourly_rate >= 10000 AND sp.hourly_rate <= 20000)";
            break;
        case '20000+':
            $where[] = "sp.hourly_rate >= 20000";
            break;
    }
}

if (!empty($experienceLevel)) {
    switch ($experienceLevel) {
        case '5+':
            $where[] = "sp.years_experience >= 5";
            break;
        case '3+':
            $where[] = "sp.years_experience >= 3";
            break;
        case '1+':
            $where[] = "sp.years_experience >= 1";
            break;
    }
}

if (!empty($verificationLevel)) {
    $where[] = "sp.verification_level = :verificationLevel";
    $params[':verificationLevel'] = $verificationLevel;
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
    // default: relevance (nearest handled in PHP)
    $sql .= " ORDER BY relevance_score DESC, sp.average_rating DESC, sp.total_reviews DESC";
}

// Add LIMIT only when we let DB sort (rating/reviews or default relevance)
// For 'nearest' we fetch all, compute distances in PHP and then paginate
$useDbLimit = ($sortBy === 'rating' || $sortBy === 'reviews' || $sortBy === 'relevance' || $sortBy === '');
$sqlWithLimit = $sql . " LIMIT :offset, :perPage";

// Build cache key (include sort so cache entries differ)
$cacheKey = 'providers_' . hash('sha256', json_encode([
    'q' => $searchQuery,
    'loc' => $locationQuery,
    'dist' => $districtFilter,
    'cat' => $categoryId,
    'service' => $serviceId,
    'minRating' => $minRating,
    'price' => $priceRange,
    'exp' => $experienceLevel,
    'verify' => $verificationLevel,
    'avail' => $availability,
    'sort' => $sortBy,
    'page' => $page,
    'perPage' => $perPage,
    'userCity' => $userCity ?? ''
]));

// Try cache (short TTL)
$providers = cache_get($cacheKey, 45);
if ($providers === false) {
    if ($useDbLimit) {
        // DB does ordering and pagination — bind ints for LIMIT
        $stmt = $db->prepare($sqlWithLimit);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
        $stmt->execute();
        $providers = $stmt->fetchAll();
    } else {
        // nearest: fetch full resultset, compute distances in PHP, then sort & slice for pagination
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

    cache_set($cacheKey, $providers);
}

// Get search suggestions if no results found
$suggestions = [];
if (empty($providers) && !empty($searchQuery)) {
    $suggestionSql = "
        SELECT DISTINCT sp.profession 
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.profession LIKE :searchWild
        AND u.is_verified = 1
        AND sp.is_active = 1
        LIMIT 5
        
        UNION
        
        SELECT DISTINCT c.name
        FROM categories c
        WHERE c.name LIKE :searchWild
        AND c.is_active = 1
        LIMIT 5
        
        UNION
        
        SELECT DISTINCT sp.location
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.location LIKE :searchWild
        AND u.is_verified = 1
        AND sp.is_active = 1
        LIMIT 5
    ";
    
    $suggestionStmt = $db->prepare($suggestionSql);
    $suggestionStmt->execute([':searchWild' => "%$searchQuery%"]);
    $suggestions = $suggestionStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Load smart provider sections for the landing results page
$recommendedProviders = getProviderSection($db, 'recommended', $currentUserId ?: 0, $userCity, 6);
$featuredProviders = getProviderSection($db, 'featured', $currentUserId ?: 0, $userCity, 6);
$nearYouProviders = getProviderSection($db, 'near_you', $currentUserId ?: 0, $userCity, 6);
$availableNowProviders = getProviderSection($db, 'available_now', $currentUserId ?: 0, $userCity, 6);
$trendingProviders = getProviderSection($db, 'trending', $currentUserId ?: 0, $userCity, 6);
$bestOverallProviders = getProviderSection($db, 'best_overall', $currentUserId ?: 0, $userCity, 6);
$verifiedProviders = getProviderSection($db, 'verified', $currentUserId ?: 0, $userCity, 6);

function renderProviderCard(array $provider): void {
    global $isLoggedIn, $isProvider;
    $prov_img = $provider['profile_image'] ?? '';
    $prov_initial = strtoupper(substr($provider['full_name'] ?? '', 0, 1)) ?: '?';
    $distance = $provider['distance_km'] ?? ($provider['distance'] ?? null);
    $distanceLabel = $distance !== null ? round($distance, 1) : null;
    $currentRequest = $_SERVER['REQUEST_URI'] ?? '/providers.php';
    $returnUrl = $currentRequest . (strpos($currentRequest, '?') === false ? '?' : '&') . 'open_booking=' . urlencode($provider['id']);
    $loginUrl = 'login.php?next=' . urlencode($returnUrl);
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card provider-card h-100">
            <div class="card-body p-0">
                <div class="provider-image position-relative">
                    <?php if ($distanceLabel !== null): ?>
                        <span class="distance-badge">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($distanceLabel); ?> km
                        </span>
                    <?php endif; ?>
                    <div class="status-indicator <?php echo htmlspecialchars($provider['availability'] ?? 'unavailable'); ?>"></div>
                    <?php if (!empty($prov_img)): ?>
                        <img src="uploads/profiles/<?php echo htmlspecialchars($prov_img); ?>" 
                             class="card-img-top provider-img" 
                             alt="<?php echo htmlspecialchars($provider['full_name']); ?>"
                             onerror="this.style.display='none'; var av = this.parentNode.querySelector('.avatar'); if(av) av.style.display='flex';">
                        <div class="avatar" style="display:none;">
                            <span class="text-white fw-bold"><?php echo $prov_initial; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="avatar">
                            <span class="text-white fw-bold"><?php echo $prov_initial; ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="action-buttons">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="provider_id" value="<?php echo intval($provider['id']); ?>">
                            <button type="submit" name="add_to_favorites" class="action-btn btn-favorite" title="Add to favorites">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                        <button class="action-btn btn-complaint" title="Report issue" onclick="openComplaintModal(<?php echo intval($provider['id']); ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-flag"></i>
                        </button>
                        <button class="action-btn btn-emergency" title="Emergency report" onclick="openEmergencyModal(<?php echo intval($provider['id']); ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-exclamation-triangle"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body-inner">
                    <h5 class="card-title">
                        <?php echo htmlspecialchars($provider['full_name']); ?>
                        <?php if (!empty($provider['verification_level']) && $provider['verification_level'] !== 'none'): ?>
                            <span class="badge-<?php echo htmlspecialchars($provider['verification_level']); ?> verification-badge">
                                <i class="fas fa-shield-alt"></i><?php echo ucfirst(htmlspecialchars($provider['verification_level'])); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($provider['is_featured'])): ?>
                            <span class="badge-featured verification-badge">
                                <i class="fas fa-star"></i>Featured
                            </span>
                        <?php endif; ?>
                    </h5>
                    <p class="text-muted mb-1" style="font-size:0.87rem;font-weight:500;">
                        <?php echo htmlspecialchars($provider['profession'] ?? ''); ?>
                    </p>
                    <p class="text-muted mb-2" style="font-size:0.82rem;">
                        <i class="fas fa-map-marker-alt me-1" style="color:var(--primary);font-size:0.78rem;"></i>
                        <?php echo htmlspecialchars($provider['location'] ?? $provider['provider_location'] ?? ''); ?>
                    </p>
                    <div class="rating mb-2">
                        <?php 
                        $rating = (float)($provider['average_rating'] ?? 0);
                        for ($i = 1; $i <= 5; $i++): 
                            if ($i <= $rating): ?>
                                <i class="fas fa-star text-warning" style="font-size:0.8rem;"></i>
                            <?php else: ?>
                                <i class="far fa-star text-warning" style="font-size:0.8rem;"></i>
                            <?php endif;
                        endfor; ?>
                        <span class="text-muted ms-1" style="font-size:0.8rem;">(<?php echo intval($provider['total_reviews'] ?? 0); ?>)</span>
                    </div>
                    <?php if (!empty($provider['hourly_rate'])): ?>
                        <p class="provider-rate mb-3">
                            <?php echo number_format((float)$provider['hourly_rate']); ?> <span style="font-size:0.78rem;font-weight:500;color:var(--secondary);">RWF/hr</span>
                        </p>
                    <?php endif; ?>
                    <a href="provider-profile.php?id=<?php echo intval($provider['id']); ?>" class="btn-view-profile d-block text-center text-decoration-none">
                        <i class="fas fa-eye me-1"></i> View Profile
                    </a>
                    <?php if ($isLoggedIn && !$isProvider): ?>
                        <button class="quick-book-btn" onclick="openBookingModal(<?php echo intval($provider['id']); ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($provider['profession'] ?? '', ENT_QUOTES); ?>')">
                            <i class="fas fa-calendar-check me-1"></i> Quick Book
                        </button>
                    <?php else: ?>
                        <a href="<?php echo $loginUrl; ?>" class="quick-book-btn d-block text-center text-decoration-none">
                            <i class="fas fa-sign-in-alt me-1"></i> Login to Book
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderProviderSection(string $title, string $subtitle, string $badge, array $providers): void {
    if (empty($providers)) {
        return;
    }
    ?>
    <section class="provider-section mb-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="section-badge"><?php echo htmlspecialchars($badge); ?></div>
                <h2 class="mb-2"><?php echo htmlspecialchars($title); ?></h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($subtitle); ?></p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($providers as $provider): renderProviderCard($provider); endforeach; ?>
        </div>
    </section>
    <?php
}

// Handle add to favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_favorites'])) {
    // require DB connection and $currentUserId (already captured above)
    if (empty($currentUserId)) {
        // redirect to login and return
        $return = $_SERVER['REQUEST_URI'] ?? 'providers.php';
        header('Location: login.php?next=' . urlencode($return));
        exit;
    }

    $provider_id = (int)($_POST['provider_id'] ?? 0);
    if ($provider_id > 0) {
        try {
            // ensure table exists (safe no-op if already present)
            $db->exec("
                CREATE TABLE IF NOT EXISTS favorites (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    provider_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_favorite (client_id, provider_id)
                )
            ");
            // insert favorite (IGNORE prevents duplicate error)
            $stmt = $db->prepare("INSERT IGNORE INTO favorites (client_id, provider_id) VALUES (?, ?)");
            $stmt->execute([$currentUserId, $provider_id]);
        } catch (Exception $e) {
            error_log("Add favorite error: " . $e->getMessage());
        }
    }

    // redirect to avoid form resubmission
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'providers.php'));
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-..." crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --text-muted: #94a3b8;
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
            --shadow-sm: 0 1px 3px rgba(15,23,42,0.06), 0 1px 2px rgba(15,23,42,0.04);
            --shadow-md: 0 4px 16px rgba(15,23,42,0.08), 0 2px 6px rgba(15,23,42,0.05);
            --shadow-lg: 0 12px 40px rgba(15,23,42,0.10), 0 4px 12px rgba(15,23,42,0.06);
            --shadow-primary: 0 8px 24px rgba(37,99,235,0.22);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--dark);
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }

        h1,h2,h3,h4,h5,h6,.navbar-brand {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

        /* ── NAVBAR ── */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226,232,240,0.8);
            padding: 0.85rem 0;
        }

        .navbar-brand {
            font-size: 1.35rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
            text-decoration: none;
        }

        .navbar .nav-link {
            font-weight: 500;
            font-size: 0.93rem;
            color: var(--secondary) !important;
            padding: 0.5rem 0.9rem !important;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .navbar .nav-link:hover, .navbar .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-light);
        }

        .navbar .btn-primary {
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.5rem 1.3rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-primary);
            border: none;
            transition: all 0.25s ease;
        }

        .navbar .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.30);
        }

        .navbar-toggler {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.65rem;
        }

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 32px 32px;
            z-index: -1;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: -100px; right: -100px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 65%);
            z-index: -1;
        }

        .hero h1 {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .hero .lead {
            font-size: 1.05rem;
            opacity: 0.88;
            line-height: 1.65;
        }

        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.22);
            backdrop-filter: blur(8px);
            color: rgba(255,255,255,0.95);
            padding: 0.5rem 1.1rem;
            border-radius: 100px;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .real-time-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.78rem;
            padding: 3px 8px;
            border-radius: 100px;
            background: rgba(16,185,129,0.2);
            border: 1px solid rgba(16,185,129,0.35);
            color: #6ee7b7;
        }

        /* ── SEARCH SECTION ── */
        .search-filter-section {
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
        }

        .search-filter-section form {
            background: var(--surface) !important;
            border: 1.5px solid var(--border) !important;
            border-radius: var(--radius-lg) !important;
            box-shadow: var(--shadow-md) !important;
        }

        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--secondary);
            margin-bottom: 0.4rem;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            background: var(--surface-2);
            color: var(--dark);
            transition: all 0.22s ease;
        }

        .form-control:hover, .form-select:hover { border-color: #cbd5e1; background: #fff; }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
            background: #fff;
            outline: none;
        }

        /* search suggestions */
        .search-suggestions {
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-top: none;
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .search-suggestions.active { display: block; }

        .suggestion-item {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.18s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .suggestion-item::before { content: '🔍'; font-size: 0.75rem; }
        .suggestion-item:hover { background: var(--primary-light); color: var(--primary); }
        .suggestion-item:last-child { border-bottom: none; }

        /* quick filter chips */
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.9rem;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 100px;
            color: var(--secondary);
            text-decoration: none;
            margin: 0.2rem;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.22s ease;
        }

        .filter-chip:hover, .filter-chip.active {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ── RESULTS BAR ── */
        .results-bar {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .results-count {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .results-count span {
            color: var(--primary);
        }

        /* ── PROVIDER CARDS ── */
        .provider-card {
            transition: all 0.32s cubic-bezier(0.34,1.56,0.64,1);
            border: 1.5px solid var(--border) !important;
            border-radius: var(--radius-lg) !important;
            overflow: hidden;
            background: var(--surface) !important;
            box-shadow: var(--shadow-sm) !important;
            position: relative;
        }

        .provider-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg) !important;
            border-color: var(--primary) !important;
        }

        .provider-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            transform: scaleX(0);
            transition: transform 0.3s ease;
            z-index: 2;
        }

        .provider-card:hover::before { transform: scaleX(1); }

        .provider-image {
            height: 200px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #334155, #475569);
        }

        .provider-image img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.45s cubic-bezier(0.25,0.46,0.45,0.94);
        }

        .provider-card:hover .provider-image img { transform: scale(1.08); }

        .avatar {
            width: 100%; height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-size: 3.5rem;
            font-weight: 800;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: transform 0.45s ease;
        }

        .provider-card:hover .avatar { transform: scale(1.06); }

        .distance-badge {
            position: absolute;
            top: 10px; left: 10px;
            background: rgba(15,23,42,0.82);
            color: white;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 0.72rem;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; gap: 0.3rem;
        }

        .status-indicator {
            position: absolute;
            top: 10px; right: 10px;
            width: 11px; height: 11px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }

        .status-indicator.available {
            background: var(--success);
            animation: pulse-green 2s infinite;
        }

        .status-indicator.busy {
            background: var(--warning);
            animation: pulse-orange 2s infinite;
        }

        .status-indicator.unavailable { background: var(--danger); }

        @keyframes pulse-green {
            0%   { box-shadow: 0 0 0 0 rgba(16,185,129,0.7); }
            70%  { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }

        @keyframes pulse-orange {
            0%   { box-shadow: 0 0 0 0 rgba(245,158,11,0.7); }
            70%  { box-shadow: 0 0 0 8px rgba(245,158,11,0); }
            100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        }

        /* hover action buttons */
        .action-buttons {
            position: absolute;
            bottom: 10px; right: 10px;
            display: flex; gap: 6px;
            opacity: 0;
            transform: translateY(8px);
            transition: all 0.28s ease;
        }

        .provider-card:hover .action-buttons {
            opacity: 1;
            transform: translateY(0);
        }

        .action-btn {
            width: 34px; height: 34px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-size: 0.8rem;
            transition: all 0.22s ease;
            backdrop-filter: blur(10px);
            cursor: pointer;
        }

        .action-btn:hover { transform: scale(1.12); }

        .btn-emergency { background: rgba(239,68,68,0.88); }
        .btn-complaint  { background: rgba(245,158,11,0.88); }
        .btn-favorite   { background: rgba(100,116,139,0.88); }

        /* card body */
        .provider-card .card-body-inner {
            padding: 1.2rem;
        }

        .provider-card .card-title {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.3rem;
            color: var(--dark);
            display: flex; align-items: center; flex-wrap: wrap; gap: 0.35rem;
        }

        .provider-card .text-muted {
            font-size: 0.87rem;
            color: var(--secondary) !important;
        }

        .provider-card .rating { display: flex; align-items: center; gap: 0.35rem; }

        .provider-rate {
            font-size: 1rem;
            font-weight: 800;
            color: var(--success);
            letter-spacing: -0.01em;
        }

        /* verification badges */
        .verification-badge {
            font-size: 0.67rem;
            padding: 0.2rem 0.55rem;
            border-radius: 100px;
            font-weight: 700;
            letter-spacing: 0.02em;
            display: inline-flex; align-items: center; gap: 0.2rem;
        }

        .badge-verified { background: #d1fae5; color: #065f46; }
        .badge-gold     { background: #fef3c7; color: #92400e; }
        .badge-premium  { background: #e0e7ff; color: #3730a3; }
        .badge-featured { background: #fee2e2; color: #991b1b; }

        /* buttons inside card */
        .btn-view-profile {
            width: 100%;
            padding: 0.6rem;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.87rem;
            transition: all 0.22s ease;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-view-profile:hover {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow-primary);
            transform: translateY(-1px);
        }

        .quick-book-btn {
            width: 100%;
            margin-top: 8px;
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 0.6rem;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.87rem;
            transition: all 0.25s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .quick-book-btn::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
            transition: left 0.5s;
        }

        .quick-book-btn:hover::after { left: 100%; }

        .quick-book-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(16,185,129,0.32);
        }

        /* ── SECTION HEADERS ── */
        .section-eyebrow {
            display: inline-flex;
            align-items: center; gap: 0.5rem;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            margin-bottom: 0.85rem;
        }

        .section-header { text-align: center; margin-bottom: 2.5rem; }

        .provider-section {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .provider-section .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.95rem;
            border-radius: 999px;
            background: rgba(37,99,235,0.08);
            color: var(--primary);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.85rem;
        }

        .provider-section h2 {
            font-size: clamp(1.25rem, 2.4vw, 1.65rem);
            margin-bottom: 0.35rem;
            color: var(--dark);
        }

        .provider-section p {
            margin-bottom: 0;
            color: var(--secondary);
        }

        .provider-sections { margin-bottom: 2rem; }

        .provider-section .row.g-4 { margin: 0; }

        .section-title {
            font-size: clamp(1.5rem, 3vw, 2.1rem);
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.6rem;
            letter-spacing: -0.03em;
            line-height: 1.2;
            display: block;
        }

        .section-title::after { display: none; }

        .section-subtitle {
            font-size: 1rem;
            color: var(--secondary);
            margin-top: 0;
            line-height: 1.65;
        }

        /* ── POPULAR SERVICES CARDS ── */
        .service-card-popular {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            position: relative;
            display: flex; flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        .service-card-popular::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .service-card-popular:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .service-card-popular:hover::before { opacity: 1; }

        .service-card-header {
            padding: 1.4rem 1.4rem 0;
            display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
        }

        .service-icon-popular {
            width: 52px; height: 52px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
            font-size: 1.4rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .service-card-popular:hover .service-icon-popular {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.08) rotate(-3deg);
            box-shadow: 0 8px 20px rgba(37,99,235,0.28);
        }

        .service-badge-popular { display: flex; gap: 0.4rem; }

        .service-badge-popular .badge {
            font-size: 0.67rem;
            padding: 0.28rem 0.65rem;
            font-weight: 700;
            border-radius: 100px;
            letter-spacing: 0.04em;
        }

        .service-card-body {
            padding: 1.1rem 1.4rem 0.8rem;
            flex-grow: 1;
        }

        .service-title-popular {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.45rem;
            letter-spacing: -0.01em;
            line-height: 1.35;
        }

        .service-description-popular {
            font-size: 0.85rem;
            color: var(--secondary);
            line-height: 1.6;
            margin-bottom: 0;
        }

        .service-card-footer {
            padding: 0.9rem 1.4rem;
            border-top: 1px solid var(--border-light);
            display: flex; flex-direction: column; gap: 0.8rem;
        }

        .price-amount {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--success);
            letter-spacing: -0.02em;
        }

        .price-unit { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }

        .service-availability { display: flex; gap: 0.8rem; font-size: 0.82rem; flex-wrap: wrap; }

        .provider-count-popular, .service-rating-popular {
            display: inline-flex; align-items: center; gap: 0.35rem;
            color: var(--secondary); font-weight: 600;
        }

        .provider-count-popular i { color: var(--primary); }

        .service-view-link {
            display: none;
            padding: 0.65rem 1.25rem;
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.87rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.25s ease;
        }

        .service-card-popular:hover .service-view-link { display: block; }

        .service-view-link:hover {
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16,185,129,0.3);
        }

        /* ── MODALS ── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1050;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--surface);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.92);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            box-shadow: var(--shadow-lg);
        }

        .modal-overlay.active .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.4rem 1.6rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .modal-header h5 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0;
        }

        .modal-body { padding: 1.5rem; }

        .close-modal {
            background: none;
            border: none;
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            color: var(--secondary);
            transition: all 0.2s ease;
            border: 1.5px solid var(--border);
        }

        .close-modal:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        /* ── FAB ── */
        .floating-action {
            position: fixed;
            bottom: 28px; right: 28px;
            z-index: 1000;
        }

        .fab-btn {
            width: 56px; height: 56px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-size: 1.3rem;
            box-shadow: var(--shadow-primary);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .fab-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 32px rgba(37,99,235,0.38);
        }

        .fab-menu {
            position: absolute;
            bottom: 68px; right: 0;
            display: flex; flex-direction: column; gap: 10px;
            opacity: 0;
            transform: translateY(16px);
            transition: all 0.28s cubic-bezier(0.34,1.56,0.64,1);
            pointer-events: none;
        }

        .fab-menu.active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: all;
        }

        .fab-menu-item {
            width: 46px; height: 46px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--dark);
            font-size: 1.1rem;
            box-shadow: var(--shadow-md);
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .fab-menu-item:hover {
            transform: scale(1.1);
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* ── FOOTER ── */
        .footer {
            background: var(--dark) !important;
            padding: 4.5rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: white;
            margin-bottom: 1.2rem;
        }

        .footer a.text-muted, .footer .text-muted { color: #64748b !important; }
        .footer a.text-muted:hover { color: white !important; }

        .footer .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.07) !important;
            color: #475569;
            font-size: 0.88rem;
        }

        .social-links { display: flex; gap: 0.6rem; margin-top: 1rem; }

        .social-links a {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-sm);
            color: #94a3b8;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none;
            font-size: 0.88rem;
            transition: all 0.25s ease;
        }

        .social-links a:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* ── SKELETON ── */
        .skeleton-loader {
            background: linear-gradient(90deg, var(--border-light) 25%, var(--border) 50%, var(--border-light) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: var(--radius-sm);
        }

        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .service-view-link { display: block !important; }
            .service-card-popular { margin-bottom: 0.5rem; }
        }

        @media (max-width: 576px) {
            .hero h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <span style="width:32px;height:32px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:9px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-map-marked-alt text-white" style="font-size:0.85rem;"></i>
                </span>
                <?php echo htmlspecialchars($platform_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-1">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link active" href="providers.php">Find Providers</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $isProvider ? 'provider/dashboard.php' : 'client/home.php'; ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Sign In</a></li>
                        <?php if ($client_registration): ?>
                        <li class="nav-item ms-1">
                            <a class="btn btn-primary" href="register.php">Get Started &rarr;</a>
                        </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="hero text-white py-5">
        <div class="container py-3">
            <div class="row justify-content-center text-center">
                <div class="col-lg-9">
                    <div class="d-inline-flex align-items-center gap-2 mb-3" style="background:rgba(255,255,255,0.13);border:1px solid rgba(255,255,255,0.22);border-radius:100px;padding:0.38rem 1rem;backdrop-filter:blur(8px);">
                        <span style="width:7px;height:7px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 0 3px rgba(16,185,129,0.28);"></span>
                        <span style="font-size:0.78rem;font-weight:700;letter-spacing:0.05em;color:rgba(255,255,255,0.92);">LIVE SEARCH</span>
                    </div>
                    <h1 class="fw-bold mb-2">
                        <?php if (!empty($searchQuery)): ?>
                            Results for &ldquo;<?php echo htmlspecialchars($searchQuery); ?>&rdquo;
                        <?php elseif ($categoryId): ?>
                            <?php
                                $catName = '';
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $categoryId) { $catName = $cat['name']; break; }
                                }
                            ?>
                            <?php echo htmlspecialchars($catName ?: 'Category'); ?> Providers
                        <?php else: ?>
                            Service Providers Near You
                        <?php endif; ?>
                    </h1>
                    <p class="lead mb-4">Find skilled professionals near you — plumbers, electricians, cleaners, and more</p>
                    <div class="location-badge">
                        <i class="fas fa-map-marker-alt"></i>
                        Your location: <strong><?php echo htmlspecialchars($userCity); ?></strong>
                        <span class="real-time-indicator">
                            <i class="fas fa-circle" style="font-size:7px;color:#6ee7b7;"></i>
                            Live
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-filter-section py-4 bg-light">
        <div class="container">
            <form method="GET" action="providers.php" id="advancedSearchForm" class="bg-white rounded-3 p-4 shadow-sm">
                <div class="row g-3">
                    <!-- Service Search -->
                    <div class="col-md-4">
                        <div class="mb-3 position-relative">
                            <label class="form-label"><i class="fas fa-search me-1"></i> What service do you need?</label>
                            <input type="text" name="query" class="form-control" 
                                   placeholder="What service do you need?" 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>" id="searchInput">
                            <div class="search-suggestions" id="searchSuggestions"></div>
                        </div>
                    </div>
                    
                    <!-- District Filter -->
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-1"></i> District</label>
                            <select name="district" class="form-select" id="districtFilter">
                                <option value="">All Districts</option>
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo htmlspecialchars($district); ?>" 
                                        <?php echo ($districtFilter === $district) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($district); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-th-large me-1"></i> Category</label>
                            <select name="category" class="form-select" id="categoryFilter">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($categoryId == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Filters (Collapsible) -->
                <div class="collapse mt-3" id="advancedFilters">
                    <div class="row g-3">
                        <!-- Rating Filter -->
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-star me-1"></i> Rating</label>
                            <select name="min_rating" class="form-select">
                                <option value="">Any Rating</option>
                                <option value="4.5" <?php echo $minRating == 4.5 ? 'selected' : ''; ?>>4.5+ Stars</option>
                                <option value="4.0" <?php echo $minRating == 4.0 ? 'selected' : ''; ?>>4.0+ Stars</option>
                                <option value="3.5" <?php echo $minRating == 3.5 ? 'selected' : ''; ?>>3.5+ Stars</option>
                                <option value="3.0" <?php echo $minRating == 3.0 ? 'selected' : ''; ?>>3.0+ Stars</option>
                            </select>
                        </div>
                        
                        <!-- Price Range Filter -->
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-tag me-1"></i> Price Range</label>
                            <select name="price_range" class="form-select">
                                <option value="">Any Price</option>
                                <option value="0-5000" <?php echo $priceRange === '0-5000' ? 'selected' : ''; ?>>Under 5,000 RWF/hr</option>
                                <option value="5000-10000" <?php echo $priceRange === '5000-10000' ? 'selected' : ''; ?>>5,000 - 10,000 RWF/hr</option>
                                <option value="10000-20000" <?php echo $priceRange === '10000-20000' ? 'selected' : ''; ?>>10,000 - 20,000 RWF/hr</option>
                                <option value="20000+" <?php echo $priceRange === '20000+' ? 'selected' : ''; ?>>20,000+ RWF/hr</option>
                            </select>
                        </div>
                        
                        <!-- Experience Filter -->
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-briefcase me-1"></i> Experience</label>
                            <select name="experience" class="form-select">
                                <option value="">Any Experience</option>
                                <option value="5+" <?php echo $experienceLevel === '5+' ? 'selected' : ''; ?>>5+ Years</option>
                                <option value="3+" <?php echo $experienceLevel === '3+' ? 'selected' : ''; ?>>3+ Years</option>
                                <option value="1+" <?php echo $experienceLevel === '1+' ? 'selected' : ''; ?>>1+ Years</option>
                            </select>
                        </div>
                        
                        <!-- Verification Filter -->
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-shield-alt me-1"></i> Verification</label>
                            <select name="verification" class="form-select">
                                <option value="">Any Verification</option>
                                <option value="premium" <?php echo $verificationLevel === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                <option value="gold" <?php echo $verificationLevel === 'gold' ? 'selected' : ''; ?>>Gold</option>
                                <option value="verified" <?php echo $verificationLevel === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12 d-flex justify-content-center gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary px-4" style="font-weight:700;border-radius:var(--radius-md);padding:0.75rem 1.5rem;">
                            <i class="fas fa-search me-2"></i>Search Providers
                        </button>
                        <button type="button" class="btn btn-outline-secondary" style="font-weight:600;border-radius:var(--radius-md);padding:0.75rem 1.25rem;border-width:1.5px;" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                            <i class="fas fa-sliders-h me-2"></i>Advanced Filters
                        </button>
                        <button type="button" class="btn btn-outline-danger" style="font-weight:600;border-radius:var(--radius-md);padding:0.75rem 1.25rem;border-width:1.5px;" onclick="window.location.href='providers.php'">
                            <i class="fas fa-redo me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Quick Filter Chips -->
            <div class="quick-filters text-center mt-3">
                <small class="text-muted d-block mb-2">Quick filters:</small>
                <a href="providers.php?min_rating=4.5" class="filter-chip">
                    <i class="fas fa-star me-1"></i>4.5+ Stars
                </a>
                <a href="providers.php?verification=premium" class="filter-chip">
                    <i class="fas fa-star me-1"></i>Premium
                </a>
                <a href="providers.php?experience=5%2B" class="filter-chip">
                    <i class="fas fa-briefcase me-1"></i>5+ Years
                </a>
                <a href="providers.php?availability=available" class="filter-chip">
                    <i class="fas fa-circle text-success me-1"></i>Available Now
                </a>
            </div>
        </div>
    </section>

    <!-- Providers List -->
    <section class="providers-section py-5">
        <div class="container">
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

            <?php if (!empty($recommendedProviders) || !empty($featuredProviders) || !empty($nearYouProviders) || !empty($availableNowProviders) || !empty($trendingProviders) || !empty($bestOverallProviders) || !empty($verifiedProviders)): ?>
                <section class="provider-sections py-4">
                    <?php
                        renderProviderSection('Recommended for You', 'Top providers personalized by ML and final score.', 'Recommended', $recommendedProviders);
                        renderProviderSection('Featured Providers', 'Providers with admin promotion and trusted listings.', 'Featured', $featuredProviders);
                        renderProviderSection('Near You', 'Closest providers based on your location.', 'Near You', $nearYouProviders);
                        renderProviderSection('Available Now', 'Providers who are currently available for quick booking.', 'Available Now', $availableNowProviders);
                        renderProviderSection('Trending Providers', 'Providers with strong recent activity and interest.', 'Trending', $trendingProviders);
                        renderProviderSection('Best Overall', 'Top-rated providers across the platform.', 'Best Overall', $bestOverallProviders);
                        renderProviderSection('Verified Providers', 'Trusted providers with verified status.', 'Verified', $verifiedProviders);
                    ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($providers)): ?>
                <div class="results-bar d-flex justify-content-between align-items-center mb-4">
                    <div class="results-count">
                        <i class="fas fa-users me-2" style="color:var(--primary);"></i>
                        <span><?php echo count($providers); ?></span> provider<?php echo count($providers) != 1 ? 's' : ''; ?> found
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0" style="white-space:nowrap;font-size:0.78rem;">Sort by:</label>
                        <select class="form-select form-select-sm" style="width:auto;min-width:150px;" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => ''])); ?>&sort=' + this.value">
                            <option value="nearest" <?php echo $sortBy === 'nearest' ? 'selected' : ''; ?>>Nearest First</option>
                            <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            <option value="reviews" <?php echo $sortBy === 'reviews' ? 'selected' : ''; ?>>Most Reviews</option>
                        </select>
                    </div>
                </div>

                <div class="row g-4">
                    <?php foreach ($providers as $provider): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card provider-card h-100">
                            <div class="card-body p-0">
                                <div class="provider-image position-relative">
                                    <?php if (isset($provider['distance'])): ?>
                                        <span class="distance-badge">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo round($provider['distance']); ?> km
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="status-indicator <?php echo $provider['availability']; ?>"></div>
                                    
                                    <?php 
                                        $prov_img = $provider['profile_image'] ?? '';
                                        $prov_initial = strtoupper(substr($provider['full_name'] ?? '', 0, 1)) ?: '?';
                                    ?>
                                    <?php if (!empty($prov_img)): ?>
                                        <img src="uploads/profiles/<?php echo htmlspecialchars($prov_img); ?>" 
                                             class="card-img-top provider-img" 
                                             alt="<?php echo htmlspecialchars($provider['full_name']); ?>"
                                             onerror="this.style.display='none'; var av = this.parentNode.querySelector('.avatar'); if(av) av.style.display='flex';">
                                        <div class="avatar" style="display:none;">
                                            <span class="text-white fw-bold"><?php echo $prov_initial; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar">
                                            <span class="text-white fw-bold"><?php echo $prov_initial; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                            <button type="submit" name="add_to_favorites" class="action-btn btn-favorite" title="Add to favorites">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </form>

                                        <button class="action-btn btn-complaint" title="Report issue" onclick="openComplaintModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-flag"></i>
                                        </button>
                                        <button class="action-btn btn-emergency" title="Emergency report" onclick="openEmergencyModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body-inner">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($provider['full_name']); ?>
                                        <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                                            <span class="badge-<?php echo $provider['verification_level']; ?> verification-badge">
                                                <i class="fas fa-shield-alt"></i><?php echo ucfirst($provider['verification_level']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($provider['is_featured']): ?>
                                            <span class="badge-featured verification-badge">
                                                <i class="fas fa-star"></i>Featured
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="text-muted mb-1" style="font-size:0.87rem;font-weight:500;"><?php echo htmlspecialchars($provider['profession']); ?></p>
                                    <p class="text-muted mb-2" style="font-size:0.82rem;">
                                        <i class="fas fa-map-marker-alt me-1" style="color:var(--primary);font-size:0.78rem;"></i>
                                        <?php echo htmlspecialchars($provider['location']); ?>
                                    </p>
                                    <div class="rating mb-2">
                                        <?php 
                                        $rating = $provider['average_rating'];
                                        for ($i = 1; $i <= 5; $i++): 
                                            if ($i <= $rating): ?>
                                                <i class="fas fa-star text-warning" style="font-size:0.8rem;"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-warning" style="font-size:0.8rem;"></i>
                                            <?php endif;
                                        endfor; ?>
                                        <span class="text-muted ms-1" style="font-size:0.8rem;">(<?php echo $provider['total_reviews']; ?>)</span>
                                    </div>
                                    
                                    <?php if ($provider['hourly_rate']): ?>
                                        <p class="provider-rate mb-3">
                                            <?php echo number_format($provider['hourly_rate']); ?> <span style="font-size:0.78rem;font-weight:500;color:var(--secondary);">RWF/hr</span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <a href="provider-profile.php?id=<?php echo $provider['id']; ?>" class="btn-view-profile d-block text-center text-decoration-none">
                                        <i class="fas fa-eye me-1"></i> View Profile
                                    </a>
                                    
                                    <?php if ($isLoggedIn && !$isProvider): ?>
                                        <button class="quick-book-btn" onclick="openBookingModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($provider['profession'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-calendar-check me-1"></i> Quick Book
                                        </button>
                                    <?php else: ?>
                                        <?php
                                            $currentRequest = $_SERVER['REQUEST_URI'] ?? '/providers.php';
                                            $returnUrl = $currentRequest . (strpos($currentRequest, '?') === false ? '?' : '&') . 'open_booking=' . urlencode($provider['id']);
                                            $loginUrl = 'login.php?next=' . urlencode($returnUrl);
                                        ?>
                                        <a href="<?php echo $loginUrl; ?>" class="quick-book-btn d-block text-center text-decoration-none">
                                            <i class="fas fa-sign-in-alt me-1"></i> Login to Book
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div style="width:90px;height:90px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                        <i class="fas fa-search fa-2x" style="color:var(--primary);"></i>
                    </div>
                    <h2 class="fw-bold mb-2" style="letter-spacing:-0.02em;">No providers found</h2>
                    <p class="text-muted mb-4" style="max-width:380px;margin:0 auto 1.5rem;">Try adjusting your search criteria or browse all available providers.</p>
                    <?php if (!empty($suggestions)): ?>
                        <div class="mb-4">
                            <p class="fw-semibold mb-2" style="font-size:0.9rem;">Did you mean:</p>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <?php foreach ($suggestions as $suggestion): ?>
                                    <a href="providers.php?query=<?php echo urlencode($suggestion); ?>" class="filter-chip">
                                        <?php echo htmlspecialchars($suggestion); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="providers.php" class="btn btn-primary px-5" style="font-weight:700;border-radius:var(--radius-md);">
                        <i class="fas fa-users me-2"></i>Browse All Providers
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Popular Services Near You -->
    <section class="py-5" style="background:var(--surface-2);">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow"><i class="fas fa-fire"></i> Trending Near You</div>
                <h2 class="section-title">Popular Services Near You</h2>
                <p class="section-subtitle">Most requested services in your area</p>
            </div>
            
            <div class="row g-4">
                <?php 
                foreach ($popular_services_data as $service): 
                    // Format payment type for display
                    $payment_types = [
                        'fixed_price' => 'Fixed Price',
                        'hourly_rate' => 'Hourly Rate',
                        'per_job_estimate' => 'Per Job Estimate',
                        'per_day' => 'Per Day',
                        'per_service' => 'Per Service', 
                        'base_price' => 'Base Price'
                    ];
                    $payment_type_display = $payment_types[$service['payment_type']] ?? 'Per Service';
                    
                    // Shorten description if too long
                    $short_description = strlen($service['service_description']) > 70 ? 
                        substr($service['service_description'], 0, 70) . '...' : 
                        $service['service_description'];
                ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="service-card-popular">
                        <div class="service-card-header">
                            <div class="service-icon-popular">
                                <i class="fas <?php echo $service['category_icon'] ?? 'fa-tools'; ?>"></i>
                            </div>
                            <div class="service-badge-popular">
                                <span class="badge bg-primary"><?php echo $payment_type_display; ?></span>
                            </div>
                        </div>
                        
                        <div class="service-card-body">
                            <h5 class="service-title-popular">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                            </h5>
                            <p class="service-description-popular">
                                <?php echo htmlspecialchars($short_description); ?>
                            </p>
                        </div>
                        
                        <div class="service-card-footer">
                            <div class="service-price-popular">
                                <span class="price-amount">
                                    RWF <?php echo number_format($service['service_price']); ?>
                                </span>
                                <small class="price-unit">
                                    <?php 
                                        echo strtolower(str_replace('Per ', '', $payment_type_display));
                                        if (isset($service['duration'])) {
                                            echo ' • ' . $service['duration'] . ' mins';
                                        }
                                    ?>
                                </small>
                            </div>
                            <div class="service-availability">
                                <span class="provider-count-popular">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $service['provider_count']; ?> available
                                </span>
                                <?php if (isset($service['avg_rating']) && $service['avg_rating'] > 0): ?>
                                <span class="service-rating-popular">
                                    <i class="fas fa-star text-warning me-1"></i>
                                    <?php echo number_format($service['avg_rating'], 1); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <a href="providers.php?service_id=<?php echo isset($service['service_id']) ? intval($service['service_id']) : 0; ?>&query=<?php echo urlencode($service['service_name']); ?>" 
                           class="stretched-link service-view-link">
                            Find Providers <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($popular_services_data) >= 8): ?>
            <div class="text-center mt-4">
                <a href="services.php" class="btn btn-outline-primary btn-lg">
                    Browse All Services <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Floating Action Button -->
    <div class="floating-action">
        <div class="fab-menu" id="fabMenu">
            <button class="fab-menu-item" onclick="scrollToTop()" title="Scroll to top">
                <i class="fas fa-arrow-up"></i>
            </button>
            <button class="fab-menu-item" onclick="openComplaintCenter()" title="<?php echo $isLoggedIn ? 'Complaint Center' : 'Login to Report'; ?>">
                <i class="fas fa-flag"></i>
            </button>
            <button class="fab-menu-item" onclick="openEmergencyCenter()" title="<?php echo $isLoggedIn ? 'Emergency Help' : 'Login for Emergency'; ?>">
                <i class="fas fa-life-ring"></i>
            </button>
        </div>
        <button class="fab-btn" onclick="toggleFabMenu()">
            <i class="fas fa-plus" id="fabIcon"></i>
        </button>
    </div>

    <!-- Booking Modal -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Booking</h5>
                <button type="button" class="close-modal" onclick="closeBookingModal()">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="provider_id" id="modalProviderId">
                    
                    <div class="mb-4 p-3" style="background:var(--primary-light);border-radius:var(--radius-md);border:1.5px solid rgba(37,99,235,0.18);">
                        <p class="fw-bold mb-0" style="font-size:1rem;letter-spacing:-0.01em;" id="modalProviderName"></p>
                        <p class="mb-0" style="font-size:0.87rem;color:var(--primary);font-weight:600;" id="modalProviderProfession"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Service Description <span class="text-danger">*</span></label>
                        <textarea name="service_description" class="form-control" required rows="4" placeholder="Describe what you need help with..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
                        <input type="date" name="preferred_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <button type="submit" name="quick_book" class="btn btn-primary w-100 py-3">
                        <i class="fas fa-paper-plane me-2"></i> Send Booking Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Emergency Modal -->
    <div class="modal-overlay" id="emergencyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Emergency Report</h5>
                <button type="button" class="close-modal" onclick="closeEmergencyModal()">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="provider_id" id="emergencyProviderId">
                    
                    <div class="alert alert-danger mb-4">
                        <strong>Use this only for urgent safety concerns</strong>
                        <p class="mb-0 mt-2">This report goes directly to our safety team for immediate action.</p>
                    </div>
                    
                    <div class="mb-4">
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

                    <div class="mb-4">
                        <label class="form-label">Emergency Description <span class="text-danger">*</span></label>
                        <textarea name="emergency_description" class="form-control" required rows="4" placeholder="Please describe the emergency situation in detail..."></textarea>
                    </div>

                    <button type="submit" name="emergency_report" class="btn btn-danger w-100 py-3">
                        <i class="fas fa-exclamation-triangle me-2"></i> Submit Emergency Report
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Complaint Modal -->
    <div class="modal-overlay" id="complaintModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning"><i class="fas fa-flag me-2"></i>Submit Complaint</h5>
                <button type="button" class="close-modal" onclick="closeComplaintModal()">&times;</button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="provider_id" id="complaintProviderId">
                    
                    <div class="alert alert-warning mb-4">
                        <strong>Help us improve our service</strong>
                        <p class="mb-0 mt-2">Your feedback helps us maintain quality standards.</p>
                    </div>
                    
                    <div class="mb-4">
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

                    <div class="mb-4">
                        <label class="form-label">Complaint Description <span class="text-danger">*</span></label>
                        <textarea name="complaint_description" class="form-control" required rows="4" placeholder="Please describe your complaint in detail..."></textarea>
                    </div>

                    <button type="submit" name="submit_complaint" class="btn btn-warning w-100 py-3">
                        <i class="fas fa-flag me-2"></i> Submit Complaint
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <h5><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-muted" style="font-size:0.9rem;line-height:1.65;">Connecting skilled professionals with clients across Rwanda</p>
                    <div class="social-links">
                        <a href="facebook.com"><i class="fab fa-facebook-f"></i></a>
                        <a href="x.com"><i class="fab fa-twitter"></i></a>
                        <a href="instagram.com"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="about.php" class="text-muted text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="services.php" class="text-muted text-decoration-none">Services</a></li>
                        <li class="mb-2"><a href="providers.php" class="text-muted text-decoration-none">Find Providers</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>For Providers</h5>
                    <ul class="list-unstyled">
                        <?php if ($provider_registration_enabled): ?>
                            <li class="mb-2"><a href="register.php?type=provider" class="text-muted text-decoration-none">Register</a></li>
                        <?php endif; ?>
                        <li class="mb-2"><a href="login.php" class="text-muted text-decoration-none">Login</a></li>
                        <li class="mb-2"><a href="how-it-works.php" class="text-muted text-decoration-none">How It Works</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5>Contact Us</h5>
                    <p class="text-muted mb-2" style="font-size:0.9rem;"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($contact_email); ?></p>
                    <p class="text-muted" style="font-size:0.9rem;"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($contact_phone); ?></p>
                </div>
            </div>
            <div class="footer-bottom border-top pt-4 mt-4 text-center">
                <p class="mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
    <script>
        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 20
                ? '0 4px 24px rgba(15,23,42,0.10)'
                : 'none';
        }, { passive: true });

        // Scroll-reveal for provider cards
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.provider-card, .service-card-popular').forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(16px)';
                el.style.transition = `opacity 0.45s cubic-bezier(0.16,1,0.3,1) ${(i % 4) * 0.07}s, transform 0.45s cubic-bezier(0.16,1,0.3,1) ${(i % 4) * 0.07}s`;
                observer.observe(el);
            });
        });

        // Modal functions
        function openBookingModal(providerId, providerName, profession) {
            document.getElementById('modalProviderId').value = providerId;
            document.getElementById('modalProviderName').textContent = providerName;
            document.getElementById('modalProviderProfession').textContent = profession;
            document.getElementById('bookingModal').classList.add('active');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }

        function openEmergencyModal(providerId, providerName) {
            document.getElementById('emergencyProviderId').value = providerId;
            document.getElementById('emergencyModal').classList.add('active');
        }

        function closeEmergencyModal() {
            document.getElementById('emergencyModal').classList.remove('active');
        }

        function openComplaintModal(providerId, providerName) {
            document.getElementById('complaintProviderId').value = providerId;
            document.getElementById('complaintModal').classList.add('active');
        }

        function closeComplaintModal() {
            document.getElementById('complaintModal').classList.remove('active');
        }

        // Close modal on outside click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Floating Action Button
        let fabMenuOpen = false;
        function toggleFabMenu() {
            const fabMenu = document.getElementById('fabMenu');
            const fabIcon = document.getElementById('fabIcon');
            
            if (fabMenuOpen) {
                fabMenu.classList.remove('active');
                fabIcon.className = 'fas fa-plus';
            } else {
                fabMenu.classList.add('active');
                fabIcon.className = 'fas fa-times';
            }
            fabMenuOpen = !fabMenuOpen;
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openComplaintCenter() {
            <?php if ($isLoggedIn): ?>
                window.location.href = 'complaints.php';
            <?php else: ?>
                window.location.href = 'login.php?next=' + encodeURIComponent('complaints.php');
            <?php endif; ?>
        }

        function openEmergencyCenter() {
            <?php if ($isLoggedIn): ?>
                window.location.href = 'emergency.php';
            <?php else: ?>
                window.location.href = 'login.php?next=' + encodeURIComponent('emergency.php');
            <?php endif; ?>
        }

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

        function setVerification(level) {
            // This would need backend support for verification level filtering
            alert('Verification filtering coming soon!');
        }

        function setFeatured() {
            // This would need backend support for featured filtering
            alert('Featured providers filtering coming soon!');
        }

        // Search suggestions
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                if (query.length > 2) {
                    // In a real implementation, you'd fetch these from the server
                    const suggestions = [
                        'Electrician',
                        'Plumber',
                        'Cleaner',
                        'Carpenter',
                        'Painter',
                        'Mechanic'
                    ].filter(item => 
                        item.toLowerCase().includes(query.toLowerCase())
                    );
                    
                    showSuggestions(suggestions);
                } else {
                    hideSuggestions();
                }
            });

            searchInput.addEventListener('blur', function() {
                setTimeout(hideSuggestions, 200);
            });
        }

        function showSuggestions(suggestions) {
            if (suggestions.length === 0) {
                hideSuggestions();
                return;
            }

            searchSuggestions.innerHTML = '';
            suggestions.forEach(suggestion => {
                const div = document.createElement('div');
                div.className = 'suggestion-item';
                div.textContent = suggestion;
                div.addEventListener('click', function() {
                    searchInput.value = suggestion;
                    hideSuggestions();
                    searchInput.focus();
                });
                searchSuggestions.appendChild(div);
            });
            
            searchSuggestions.classList.add('active');
        }

        function hideSuggestions() {
            searchSuggestions.classList.remove('active');
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Real-time availability updates (simulated)
        function updateProviderStatus() {
            document.querySelectorAll('.status-indicator.available').forEach(indicator => {
                // Simulate random status changes for demo
                if (Math.random() < 0.1) { // 10% chance to change status
                    indicator.classList.remove('available');
                    indicator.classList.add('busy');
                }
            });
            
            document.querySelectorAll('.status-indicator.busy').forEach(indicator => {
                if (Math.random() < 0.2) { // 20% chance to become available
                    indicator.classList.remove('busy');
                    indicator.classList.add('available');
                }
            });
        }

        // Update status every 30 seconds
        setInterval(updateProviderStatus, 30000);

        var providerMap = <?php
    $map = [];
    if (!empty($providers)) {
        foreach ($providers as $p) {
            $map[(int)$p['id']] = [
                'name' => $p['full_name'] ?? '',
                'profession' => $p['profession'] ?? ''
            ];
        }
    }
    echo json_encode($map);
?>;

document.addEventListener('DOMContentLoaded', function(){
    try {
        const params = new URLSearchParams(window.location.search);
        const openId = params.get('open_booking');
        if (openId && providerMap[openId]) {
            // open booking modal for the provider
            openBookingModal(openId, providerMap[openId].name, providerMap[openId].profession);

            // remove open_booking from URL bar (clean up)
            params.delete('open_booking');
            const q = params.toString();
            const newUrl = window.location.pathname + (q ? '?' + q : '');
            history.replaceState(null, '', newUrl);
        }
    } catch (e) {
        console.error('Auto-open booking error', e);
    }
});
    </script>
</body>
</html>