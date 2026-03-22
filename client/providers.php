<?php
/**
 * providers.php  —  BII LocalFinder
 * ===================================
 * Modern provider discovery page with:
 *  • Advanced ML-powered recommendations
 *  • Real-time search and filtering
 *  • Interactive provider cards
 *  • Location-based discovery
 *  • AI-enhanced search capabilities
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';
require_once '../includes/event_tracking.php';

// Conditionally load AI helper (non-fatal if missing)
if (file_exists(__DIR__ . '/../includes/ai_helpers.php')) {
    require_once '../includes/ai_helpers.php';
}

// ─────────────────────────────────────────────────────────────────────────────
// GUARD: login required
// ─────────────────────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 — Platform settings (cached static)
// ─────────────────────────────────────────────────────────────────────────────
function getPlatformSetting(PDO $db, string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = $db->query("SELECT setting_key, setting_value FROM system_settings")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return (string)($cache[$key] ?? $default);
}

$cfg = [
    'platform_name'    => getPlatformSetting($db, 'platform_name',    'BII LocalFinder'),
    'contact_email'    => getPlatformSetting($db, 'contact_email',     'info@biilocalfinder.com'),
    'contact_phone'    => getPlatformSetting($db, 'contact_phone',     '+250 788 000 000'),
    'timezone'         => getPlatformSetting($db, 'timezone',          'Africa/Kigali'),
    'enable_email'     => getPlatformSetting($db, 'enable_email_notifications', '1'),
    'enable_ai'        => getPlatformSetting($db, 'enable_ai_features', '1'),
    'enable_ml'        => getPlatformSetting($db, 'enable_ml_recommendations', '1'),
    'ml_api_url'       => getPlatformSetting($db, 'ml_api_base_url',   'http://localhost:8000'),
    'provider_reg'     => getPlatformSetting($db, 'provider_registration', '1'),
];

date_default_timezone_set($cfg['timezone']);

$aiHelper = null;
if ($cfg['enable_ai'] === '1' && class_exists('AIHelper')) {
    $aiHelper = new AIHelper($db);
}

// Load ML Recommender
$mlRecommender = null;
if (file_exists(__DIR__ . '/../includes/MLRecommender.php')) {
    require_once '../includes/MLRecommender.php';
    $mlRecommender = new MLRecommender($db, $cfg['ml_api_url']);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 — Geolocation helpers
// ─────────────────────────────────────────────────────────────────────────────
function getUserLocation(): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return ['city' => 'Kigali', 'country' => 'Rwanda', 'lat' => -1.9403, 'lng' => 29.8739];
    }
    try {
        $ch = curl_init("https://ipapi.co/{$ip}/json/");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw) {
            $d = json_decode($raw, true);
            if (!empty($d['city'])) {
                return ['city' => $d['city'], 'country' => $d['country_name'] ?? 'Rwanda',
                        'lat'  => (float)($d['latitude']  ?? -1.9403),
                        'lng'  => (float)($d['longitude'] ?? 29.8739)];
            }
        }
    } catch (Throwable $e) {}
    return ['city' => 'Kigali', 'country' => 'Rwanda', 'lat' => -1.9403, 'lng' => 29.8739];
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R   = 6371;
    $dLa = deg2rad($lat2 - $lat1);
    $dLo = deg2rad($lon2 - $lon1);
    $a   = sin($dLa/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLo/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function cityCoords(string $city): array {
    static $map = [
        'kigali'    => [-1.9403, 29.8739], 'rubavu'    => [-1.6778, 29.2664],
        'musanze'   => [-1.4997, 29.6384], 'huye'      => [-2.5976, 29.7389],
        'rusizi'    => [-2.4889, 28.9078], 'muhanga'   => [-2.0845, 29.7424],
        'rwamagana' => [-1.9486, 30.4348], 'nyagatare' => [-1.2974, 30.3245],
        'gisenyi'   => [-1.7029, 29.2564], 'kibuye'    => [-2.0606, 29.3475],
    ];
    $key = strtolower(trim($city));
    return $map[$key] ?? $map['kigali'];
}

$userLoc  = getUserLocation();
$userCity = $userLoc['city'];

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 — ML Recommender (uses MLRecommender class)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fetch top-N ML recommended providers for the hero section.
 * Independent from the search results.
 */
function fetchMLRecommendations(PDO $db, $mlRecommender, array $cfg, int $limit = 6, int $userId = null): array {
    if (!$mlRecommender || $cfg['enable_ml'] !== '1') return [];

    try {
        $stmt = $db->query("
            SELECT sp.id, sp.profession, sp.location, sp.availability,
                   sp.average_rating, sp.total_reviews, sp.is_featured,
                   sp.verification_level, sp.user_id,
                   u.full_name, u.profile_image
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.is_active=1 AND sp.is_banned=0 AND u.is_active=1 AND u.is_verified=1
            ORDER BY sp.average_rating DESC, sp.total_reviews DESC
            LIMIT 40
        ");
        $pool = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }

    if (empty($pool)) return [];

    // Use MLRecommender to rank providers with user personalization
    $rankedProviders = $mlRecommender->rankProviders($pool, $userId);

    return array_slice($rankedProviders, 0, $limit);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 — POST handlers (emergency / complaint)
// ─────────────────────────────────────────────────────────────────────────────
$flash_success = '';
$flash_errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // — Emergency report ——————————————————————————————————————————————————————
    if (isset($_POST['emergency_report'])) {
        $pid   = intval($_POST['provider_id']);
        $type  = sanitize($_POST['emergency_type'] ?? '');
        $desc  = sanitize($_POST['emergency_description'] ?? '');

        if ($aiHelper) {
            $tc = $aiHelper->detectToxicity($desc);
            if (($tc['score'] ?? 0) > 0.9) {
                $flash_errors[] = "Please describe the emergency without inappropriate language.";
                try {
                    $db->prepare("INSERT INTO toxic_content_logs(user_id,content_type,toxicity_score,original_text) VALUES(?,?,?,?)")
                       ->execute([$_SESSION['user_id'], 'emergency', $tc['score'], substr($desc, 0, 500)]);
                } catch (Throwable $e) {}
            }
        }

        if (empty($flash_errors)) {
            try {
                $db->prepare("INSERT INTO emergency_reports(user_id,provider_id,emergency_type,description,status,created_at) VALUES(?,?,?,?,'pending',NOW())")
                   ->execute([$_SESSION['user_id'], $pid, $type, $desc]);

                if ($cfg['enable_email'] === '1') {
                    $admins = $db->query("SELECT email FROM users WHERE user_type='admin' AND is_active=1")->fetchAll();
                    foreach ($admins as $admin) {
                        Mailer::sendEmergencyReport($admin['email'], $pid, $type, $desc);
                    }
                }
                $flash_success = "Emergency report submitted. Our team will respond immediately.";
            } catch (Throwable $e) {
                $flash_errors[] = "Failed to submit emergency report.";
            }
        }
    }

    // — Complaint ——————————————————————————————————————————————————————————————
    if (isset($_POST['submit_complaint'])) {
        $pid  = intval($_POST['provider_id']);
        $type = sanitize($_POST['complaint_type'] ?? '');
        $desc = sanitize($_POST['complaint_description'] ?? '');

        if ($aiHelper) {
            $tc = $aiHelper->detectToxicity($desc);
            if ($tc['is_toxic'] ?? false) {
                $flash_errors[] = "Your complaint contains inappropriate language. Please revise.";
            }
        }

        if (empty($flash_errors)) {
            try {
                $db->prepare("INSERT INTO complaints(user_id,provider_id,complaint_type,description,status,created_at) VALUES(?,?,?,?,'open',NOW())")
                   ->execute([$_SESSION['user_id'], $pid, $type, $desc]);
                $flash_success = "Complaint submitted. We'll investigate and respond soon.";
            } catch (Throwable $e) {
                $flash_errors[] = "Failed to submit complaint.";
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 — AJAX endpoints (return JSON early)
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['improve_text']) && $aiHelper) {
    header('Content-Type: application/json');
    $text = $_GET['text'] ?? '';
    if (!empty($text) && strlen($text) > 10) {
        $improved = $aiHelper->cleanBookingDescription($text);
        echo json_encode(['success' => true, 'improved' => $improved]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Text too short']);
    }
    exit;
}

if (isset($_GET['track_share'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $pid  = intval($data['provider_id'] ?? 0);
    $plat = sanitize($data['platform'] ?? 'direct');
    if ($pid > 0) {
        try {
            $db->prepare("INSERT IGNORE INTO provider_shares(provider_id,user_id,platform) VALUES(?,?,?)")
               ->execute([$pid, $_SESSION['user_id'], $plat]);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) { echo json_encode(['success' => false]); }
    } else { echo json_encode(['success' => false]); }
    exit;
}

if (isset($_GET['get_share_stats'])) {
    header('Content-Type: application/json');
    $pid = intval($_GET['provider_id'] ?? 0);
    if ($pid > 0) {
        try {
            $sc = fetchScalar($db, "SELECT COUNT(*) FROM provider_shares WHERE provider_id=? AND shared_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$pid]);
            $vc = fetchScalar($db, "SELECT COUNT(*) FROM provider_views  WHERE provider_id=? AND viewed_at >=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$pid]);
            echo json_encode(['success' => true, 'share_count' => (int)$sc, 'view_count' => (int)$vc]);
        } catch (Throwable $e) { echo json_encode(['success' => false]); }
    } else { echo json_encode(['success' => false]); }
    exit;
}

// ML health ping (used by front-end status indicator)
if (isset($_GET['ml_health'])) {
    header('Content-Type: application/json');
    $online = $mlRecommender ? $mlRecommender->isApiHealthy() : false;
    echo json_encode(['online' => $online]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6 — Search & filter parameters
// ─────────────────────────────────────────────────────────────────────────────
$searchQuery   = trim($_GET['query']        ?? '');
$locationQuery = trim($_GET['location']     ?? '');
$categoryId    = isset($_GET['category'])   ? (int)$_GET['category'] : null;
$minRating     = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0;
$availability  = trim($_GET['availability'] ?? '');
$sortBy        = trim($_GET['sort']         ?? 'recommended');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = min(48, max(6, (int)($_GET['page_size'] ?? 12)));
$offset        = ($page - 1) * $perPage;

// AI category classification
if ($aiHelper && !empty($searchQuery) && !$categoryId) {
    $aiResult = $aiHelper->classifyServiceFromQuery($searchQuery);
    if (is_array($aiResult) && isset($aiResult['id'])) {
        $categoryId = (int)$aiResult['id'];
        $_SESSION['ai_suggested_category'] = $categoryId;
    } elseif (is_int($aiResult)) {
        $categoryId = $aiResult;
        $_SESSION['ai_suggested_category'] = $categoryId;
    }
}

// Fetch categories for filter dropdown
$categories = $db->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7 — Provider search query
// ─────────────────────────────────────────────────────────────────────────────
$sqlBase = "
    SELECT sp.*, u.full_name, u.email, u.profile_image, u.user_id AS user_row_id,
           GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS category_names,
           (
               CASE
                   WHEN sp.profession = :es           THEN 100
                   WHEN LOWER(sp.profession) = LOWER(:es) THEN 95
                   WHEN u.full_name = :es             THEN 90
                   WHEN sp.profession LIKE CONCAT(:ss,'%') THEN 70
                   WHEN u.full_name   LIKE CONCAT(:ss,'%') THEN 65
                   WHEN sp.profession LIKE CONCAT('%',:sl,'%') THEN 50
                   WHEN u.full_name   LIKE CONCAT('%',:sl,'%') THEN 45
                   WHEN sp.location   LIKE CONCAT('%',:sl,'%') THEN 40
                   WHEN sp.bio        LIKE CONCAT('%',:sl,'%') THEN 30
                   ELSE 0
               END
               + (sp.average_rating  * 5)
               + (CASE WHEN sp.total_reviews > 20 THEN 15 WHEN sp.total_reviews > 5 THEN 8 ELSE 0 END)
               + (CASE WHEN sp.availability = 'available' THEN 10 ELSE 0 END)
               + (CASE WHEN sp.verification_level IN ('premium','gold') THEN 20 WHEN sp.verification_level='verified' THEN 10 ELSE 0 END)
               + (CASE WHEN sp.is_featured = 1 THEN 25 ELSE 0 END)
           ) AS relevance_score
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN provider_services ps ON sp.id = ps.provider_id
    LEFT JOIN categories c ON ps.category_id = c.id
";

$where  = ["u.is_verified=1", "sp.is_active=1", "sp.is_banned=0", "u.is_active=1"];
$params = [':es' => $searchQuery, ':ss' => $searchQuery, ':sl' => $searchQuery];

if ($categoryId)          { $where[] = "ps.category_id = :catId";   $params[':catId']     = $categoryId; }
if (!empty($locationQuery)) {
    $where[] = "(sp.location LIKE :locE OR sp.location LIKE :locL OR sp.district LIKE :locL OR sp.sector LIKE :locL)";
    $params[':locE'] = $locationQuery;
    $params[':locL'] = "%$locationQuery%";
}
if ($minRating > 0)       { $where[] = "sp.average_rating >= :mr";  $params[':mr']        = $minRating; }
if (!empty($availability)){ $where[] = "sp.availability = :avail";  $params[':avail']     = $availability; }

$sql  = $sqlBase . " WHERE " . implode(" AND ", $where) . " GROUP BY sp.id";
if (!empty($searchQuery)) $sql .= " HAVING relevance_score > 0";

// Determine sort
$isSortNearest    = in_array($sortBy, ['nearest', 'recommended'], true);
$isSortML         = $sortBy === 'ml' || $sortBy === 'recommended';
if ($sortBy === 'rating')        $sql .= " ORDER BY sp.average_rating DESC, sp.total_reviews DESC";
elseif ($sortBy === 'reviews')   $sql .= " ORDER BY sp.total_reviews DESC, sp.average_rating DESC";
elseif ($sortBy === 'newest')    $sql .= " ORDER BY sp.created_at DESC";
else                             $sql .= " ORDER BY relevance_score DESC, sp.average_rating DESC";

try {
    if ($isSortNearest) {
        // Fetch all, compute distance, sort, then slice
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $allProviders = $stmt->fetchAll();

        foreach ($allProviders as &$p) {
            [$la, $lo]      = cityCoords($p['location'] ?? '');
            $p['distance']  = haversine($userLoc['lat'], $userLoc['lng'], $la, $lo);
        }
        unset($p);
        usort($allProviders, fn($a,$b) => ($a['distance']??0) <=> ($b['distance']??0));
        $totalProviders = count($allProviders);
        $providers      = array_slice($allProviders, $offset, $perPage);

    } else {
        // Count for pagination
        $cntSql  = preg_replace('/^SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(DISTINCT sp.id) FROM ', $sqlBase);
        $cntSql .= " WHERE " . implode(" AND ", $where);
        $cntStmt = $db->prepare($cntSql);
        foreach ($params as $k => $v) $cntStmt->bindValue($k, $v);
        $cntStmt->execute();
        $totalProviders = (int)$cntStmt->fetchColumn();

        $stmt = $db->prepare($sql . " LIMIT :off, :pp");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':pp',  $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $providers = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    error_log("Provider search error: " . $e->getMessage());
    $providers      = [];
    $totalProviders = 0;
}

$totalPages = max(1, (int)ceil($totalProviders / $perPage));

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 8 — ML scoring for search results
// ─────────────────────────────────────────────────────────────────────────────
$mlApiOnline = false;

if ($mlRecommender && $cfg['enable_ml'] === '1' && !empty($providers)) {
    // Use MLRecommender to rank providers with user personalization (includes fallback logic)
    $providers = $mlRecommender->rankProviders($providers, $_SESSION['user_id'] ?? null);
    $mlApiOnline = true; // Assume online if class exists and we got results

    // Re-sort by ML score if sort=recommended or sort=ml
    if ($isSortML) {
        // Already sorted by MLRecommender->rankProviders(), but ensure proper order
        usort($providers, function($a, $b) {
            $sa = $a['ml_score'] ?? 0;
            $sb = $b['ml_score'] ?? 0;
            if (abs($sa - $sb) < 0.001) {
                // Fallback: sort by rating and response time
                $a_rating = $a['average_rating'] ?? 0;
                $b_rating = $b['average_rating'] ?? 0;
                if (abs($a_rating - $b_rating) < 0.1) {
                    // If ratings are similar, prefer faster response time
                    $a_response = $a['avg_response_time'] ?? 24;
                    $b_response = $b['avg_response_time'] ?? 24;
                    return $a_response <=> $b_response; // Lower response time first
                }
                return $b_rating <=> $a_rating; // Higher rating first
            }
            return $sb <=> $sa; // Higher ML score first
        });
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 9 — ML Recommendation strip (top picks, independent of search)
// ─────────────────────────────────────────────────────────────────────────────
$mlRecommended = [];
$showMLStrip   = (empty($searchQuery) && empty($locationQuery) && !$categoryId);
if ($showMLStrip) {
    $mlRecommended = fetchMLRecommendations($db, $mlRecommender, $cfg, 6, $_SESSION['user_id'] ?? null);
    $mlApiOnline   = $mlApiOnline || !empty($mlRecommended);
}

// Track search events
if (!empty($searchQuery)) {
    try {
        trackEvent('search', 'search', null, [
            'query'         => $searchQuery,
            'location'      => $locationQuery,
            'category_id'   => $categoryId,
            'results_count' => $totalProviders,
            'sort'          => $sortBy,
        ]);
    } catch (Throwable $e) {}
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 10 — AI category suggestion label
// ─────────────────────────────────────────────────────────────────────────────
$aiCatName = '';
if (isset($_SESSION['ai_suggested_category'])) {
    $acid = (int)$_SESSION['ai_suggested_category'];
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $acid) { $aiCatName = $cat['name']; break; }
    }
    unset($_SESSION['ai_suggested_category']);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS for view layer
// ─────────────────────────────────────────────────────────────────────────────
function renderStars(float $rating, string $size = '0.85rem'): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating))            $out .= '<i class="fas fa-star"></i>';
        elseif ($i <= ceil($rating) && fmod($rating, 1) > 0.3) $out .= '<i class="fas fa-star-half-alt"></i>';
        else                                  $out .= '<i class="far fa-star"></i>';
    }
    return "<span class='stars' style='font-size:{$size};color:#f59e0b;'>{$out}</span>";
}

function mlScoreColor(float $prob): string {
    if ($prob >= 0.75) return '#10b981';
    if ($prob >= 0.50) return '#3b82f6';
    if ($prob >= 0.30) return '#f59e0b';
    return '#6b7280';
}

function mlScoreLabel(float $prob): string {
    if ($prob >= 0.80) return 'Top Match';
    if ($prob >= 0.60) return 'Great Fit';
    if ($prob >= 0.40) return 'Good Pick';
    return 'Suggested';
}

function avatarInitial(string $name): string {
    return strtoupper(substr(trim($name), 0, 1)) ?: '?';
}

function verificationBadge(string $level): string {
    $badges = [
        'premium'  => ['bg:#4f46e5', 'Premium'],
        'gold'     => ['bg:#d97706', 'Gold'],
        'verified' => ['bg:#059669', 'Verified'],
    ];
    $b = $badges[$level] ?? null;
    if (!$b) return '';
    [$bg, $label] = $b;
    return "<span class='vbadge' style='{$bg}'><i class='fas fa-shield-alt'></i> {$label}</span>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Find Providers — <?= htmlspecialchars($cfg['platform_name']) ?></title>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">

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
    --sidebar-width: 260px;
    --sidebar-collapsed-width: 72px;
    --ml-green: #10b981;
    --ml-blue: #3b82f6;
    --ml-amber: #f59e0b;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-body);
    -webkit-font-smoothing: antialiased;
    min-height: 100vh;
    overflow-x: hidden;
}

/* ── Background noise texture ── */
body::before {
    content: '';
    position: fixed; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='400'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='400' height='400' filter='url(%23n)' opacity='0.025'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
}

h1,h2,h3,h4,h5 { font-family: var(--font-head); letter-spacing: -0.02em; }

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

.sidebar.collapsed { width: var(--sidebar-collapsed-width); }

.sidebar-brand {
    padding: 1.5rem 1.25rem 1.25rem;
    border-bottom: 1px solid var(--border-subtle);
    display: flex;
    align-items: center;
    gap: 0.875rem;
}

.sidebar-brand-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 800;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.sidebar-brand-text {
    flex: 1;
    min-width: 0;
}

.sidebar-brand-name {
    display: block;
    font-weight: 800;
    font-size: 1.15rem;
    color: var(--accent);
    letter-spacing: -0.3px;
    margin-bottom: 0.125rem;
}

.sidebar-brand-role {
    display: block;
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 500;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: var(--text-secondary);
    padding: 0.5rem;
    border-radius: var(--radius-sm);
    transition: var(--transition);
    cursor: pointer;
    flex-shrink: 0;
}

.sidebar-toggle:hover {
    background: var(--accent-light);
    color: var(--accent);
}

.sidebar-nav {
    flex: 1;
    padding: 0.75rem;
    overflow-y: auto;
}

.nav-section-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin: 1rem 0 0.5rem;
    padding: 0 0.5rem;
}

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
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

.sidebar-menu a:hover { background: var(--accent-light); color: var(--accent); }
.sidebar-menu a.active { background: var(--accent); color: white; font-weight: 600; }

.sidebar-menu i {
    width: 18px;
    font-size: 0.9rem;
    flex-shrink: 0;
}
}
.sidebar-nav a.active::before {
    content: '';
    position: absolute; left: 0; top: 20%; bottom: 20%;
    width: 2px; background: var(--accent);
    border-radius: 99px; box-shadow: 0 0 8px var(--accent-glow);
}
.sidebar-nav i { width: 18px; font-size: .85rem; flex-shrink: 0; opacity: .8; }
.sidebar-nav a.active i { opacity: 1; color: var(--accent); }

.nav-badge {
    margin-left: auto;
    background: var(--accent); color: #fff;
    font-size: .6rem; font-weight: 800;
    padding: 1px 6px; border-radius: 99px;
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
.topbar {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.75rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-xs);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.topbar-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.5px;
    margin: 0;
}

.topbar-subtitle {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0.25rem 0 0;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.ml-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.ml-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ml-green);
    box-shadow: 0 0 6px var(--ml-green);
    animation: pulse-dot 2s infinite;
}

.ml-dot.offline { background: #ef4444; box-shadow: none; animation: none; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
    display: flex; align-items: center;
    padding: .45rem .45rem .45rem 1rem;
    gap: .5rem;
    transition: border-color .2s;
}
.topbar-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
.topbar-search input {
    flex: 1; background: none; border: none; outline: none;
    color: var(--text); font-size: .875rem; font-family: var(--font-body);
}
.topbar-search input::placeholder { color: var(--text-3); }
.topbar-search button {
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    border: none; border-radius: 99px;
    padding: .4rem .85rem; color: #fff;
    font-size: .78rem; font-weight: 700; cursor: pointer;
    transition: opacity .18s;
}
.topbar-search button:hover { opacity: .88; }

.ml-status {
    display: flex; align-items: center; gap: .4rem;
    font-size: .72rem; color: var(--text-3); font-weight: 500;
}
.ml-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--text-3);
    box-shadow: 0 0 0 2px rgba(255,255,255,.06);
    transition: background .4s;
}
.ml-dot.online  { background: var(--ml-green); box-shadow: 0 0 6px var(--ml-green); animation: pulse-dot 2s infinite; }
.ml-dot.offline { background: #ef4444; }

@keyframes pulse-dot {
    0%,100% { box-shadow: 0 0 6px var(--ml-green); }
    50%      { box-shadow: 0 0 14px var(--ml-green); }
}

/* ── Page content ── */
.page-body { padding: 1.75rem 2rem 4rem; }

/* ── Section header ── */
.section-head {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 1.25rem; flex-wrap: wrap; gap: .75rem;
}
.section-head h2 {
    font-size: 1.25rem; font-weight: 800; color: var(--text);
    display: flex; align-items: center; gap: .5rem;
}
.section-head h2 .ico {
    width: 30px; height: 30px;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; color: #fff;
    box-shadow: 0 4px 12px var(--accent-glow);
}
.section-sub { font-size: .82rem; color: var(--text-3); }

/* ════════════════════════════════════════════════════════
   ML RECOMMENDATION STRIP
   ════════════════════════════════════════════════════════ */
.ml-strip {
    background: linear-gradient(135deg, rgba(99,102,241,.07), rgba(139,92,246,.04));
    border: 1px solid rgba(99,102,241,.18);
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.ml-strip::before {
    content: '';
    position: absolute; top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(99,102,241,.15) 0%, transparent 70%);
    pointer-events: none;
}

.ml-strip-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem; flex-wrap: wrap; gap: .75rem;
}

.ml-strip-title {
    display: flex; align-items: center; gap: .65rem;
}

.ml-brain-icon {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; color: #fff;
    box-shadow: 0 4px 16px var(--accent-glow);
    animation: brain-pulse 3s ease-in-out infinite;
}

@keyframes brain-pulse {
    0%,100% { box-shadow: 0 4px 16px rgba(99,102,241,.35); }
    50%      { box-shadow: 0 4px 28px rgba(99,102,241,.65); }
}

.ml-strip-title h3 { font-size: 1.05rem; font-weight: 800; }
.ml-strip-title p  { font-size: .78rem; color: var(--text-3); margin-top: 1px; }

.ml-strip-badge {
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    color: #fff; font-size: .68rem; font-weight: 800;
    padding: .3rem .75rem; border-radius: 99px;
    letter-spacing: .05em;
    box-shadow: 0 2px 12px var(--accent-glow);
}

/* ML card grid */
.ml-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 1rem;
}

.ml-card {
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: transform .22s cubic-bezier(.34,1.56,.64,1), border-color .2s, box-shadow .2s;
    position: relative;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
    animation: card-in .5s cubic-bezier(.34,1.56,.64,1) both;
}

.ml-card:nth-child(1) { animation-delay: .05s }
.ml-card:nth-child(2) { animation-delay: .10s }
.ml-card:nth-child(3) { animation-delay: .15s }
.ml-card:nth-child(4) { animation-delay: .20s }
.ml-card:nth-child(5) { animation-delay: .25s }
.ml-card:nth-child(6) { animation-delay: .30s }

@keyframes card-in {
    from { opacity: 0; transform: translateY(18px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.ml-card:hover {
    transform: translateY(-6px) scale(1.01);
    border-color: rgba(99,102,241,.4);
    box-shadow: 0 12px 32px rgba(0,0,0,.5), 0 0 0 1px rgba(99,102,241,.25);
    text-decoration: none; color: inherit;
}

/* ML score arc at top of card */
.ml-card-score {
    position: absolute; top: .6rem; right: .6rem;
    z-index: 2;
}

.score-pill {
    display: flex; align-items: center; gap: .3rem;
    padding: .22rem .55rem;
    border-radius: 99px;
    font-size: .65rem; font-weight: 800;
    backdrop-filter: blur(12px);
    background: rgba(0,0,0,.55);
    border: 1px solid rgba(255,255,255,.12);
    letter-spacing: .03em;
}

.ml-card-img {
    height: 130px; overflow: hidden; position: relative;
}

.ml-card-img img {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform .4s ease;
}
.ml-card:hover .ml-card-img img { transform: scale(1.06); }

.ml-card-avatar {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; font-weight: 800; color: rgba(255,255,255,.85);
    font-family: var(--font-head);
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
}

.ml-avail-dot {
    position: absolute; bottom: .5rem; left: .5rem;
    display: flex; align-items: center; gap: .3rem;
    background: rgba(0,0,0,.7); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 99px; padding: .2rem .55rem;
    font-size: .65rem; font-weight: 600;
}
.ml-avail-dot .dot {
    width: 6px; height: 6px; border-radius: 50%;
}
.dot-available { background: var(--ml-green); box-shadow: 0 0 6px var(--ml-green); }
.dot-busy      { background: var(--ml-amber); }
.dot-unavailable{background: #ef4444; }

.ml-card-body { padding: .85rem; }
.ml-card-name  { font-size: .88rem; font-weight: 700; color: var(--text); margin-bottom: .15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ml-card-prof  { font-size: .72rem; color: var(--accent-2); font-weight: 600; margin-bottom: .5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.ml-card-meta {
    display: flex; align-items: center; justify-content: space-between;
}
.ml-card-rating { display: flex; align-items: center; gap: .3rem; font-size: .72rem; color: var(--text-2); }
.ml-card-reviews{ font-size: .68rem; color: var(--text-3); }

/* ML score bar */
.ml-score-bar {
    height: 3px; border-radius: 99px;
    background: var(--border);
    margin-top: .6rem; overflow: hidden;
}
.ml-score-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    transition: width .6s cubic-bezier(.4,0,.2,1);
    box-shadow: 0 0 8px var(--accent-glow);
}

.ml-fallback {
    text-align: center; padding: 2rem;
    color: var(--text-3); font-size: .875rem;
}
.ml-fallback i { font-size: 2rem; display: block; margin-bottom: .75rem; color: var(--text-3); }

/* ════════════════════════════════════════════════════════
   FILTERS BAR
   ════════════════════════════════════════════════════════ */
.filters-bar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.125rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end;
}

.fgroup { display: flex; flex-direction: column; gap: .35rem; min-width: 140px; flex: 1; }
.fgroup label { font-size: .72rem; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: .06em; }
.fgroup input, .fgroup select {
    background: var(--bg-2); border: 1px solid var(--border);
    color: var(--text); border-radius: var(--radius-sm);
    padding: .5rem .75rem; font-size: .875rem; font-family: var(--font-body);
    outline: none; transition: border-color .18s;
    -webkit-appearance: none;
}
.fgroup input:focus, .fgroup select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
.fgroup select option { background: #1a1a2e; }

.filter-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .5rem; }
.chip {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .75rem; border-radius: 99px;
    border: 1px solid var(--border); background: var(--surface);
    font-size: .75rem; font-weight: 600; color: var(--text-2);
    cursor: pointer; transition: all .18s; white-space: nowrap;
}
.chip:hover { border-color: var(--accent); color: var(--accent); background: rgba(99,102,241,.08); }
.chip.active { border-color: var(--accent); background: rgba(99,102,241,.15); color: var(--accent); }

.btn-filter {
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    color: #fff; border: none; border-radius: var(--radius-sm);
    padding: .55rem 1.25rem; font-size: .875rem; font-weight: 700;
    cursor: pointer; transition: opacity .18s, transform .18s;
    white-space: nowrap; font-family: var(--font-body);
    box-shadow: 0 4px 16px var(--accent-glow);
}
.btn-filter:hover { opacity: .9; transform: translateY(-1px); }

.btn-reset {
    background: var(--surface-2); color: var(--text-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: .55rem 1rem; font-size: .875rem; font-weight: 600;
    cursor: pointer; transition: all .18s; white-space: nowrap; font-family: var(--font-body);
    text-decoration: none;
}
.btn-reset:hover { border-color: var(--border-hi); color: var(--text); text-decoration: none; }

/* ════════════════════════════════════════════════════════
   RESULTS HEADER
   ════════════════════════════════════════════════════════ */
.results-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem;
}
.results-count {
    font-size: .875rem; color: var(--text-2);
}
.results-count strong { color: var(--text); font-weight: 700; }

.sort-row { display: flex; align-items: center; gap: .5rem; }
.sort-row label { font-size: .78rem; color: var(--text-3); font-weight: 600; }
.sort-row select {
    background: var(--surface-2); border: 1px solid var(--border);
    color: var(--text); border-radius: var(--radius-sm);
    padding: .35rem .75rem; font-size: .8rem; font-family: var(--font-body);
    outline: none; cursor: pointer;
}

/* ════════════════════════════════════════════════════════
   PROVIDER GRID
   ════════════════════════════════════════════════════════ */
.provider-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.125rem;
}

.pcard {
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .2s, border-color .2s;
    position: relative;
    display: flex; flex-direction: column;
}

.pcard:hover {
    transform: translateY(-5px);
    border-color: rgba(99,102,241,.3);
    box-shadow: var(--shadow-card), var(--shadow-glow);
}

.pcard-img {
    height: 150px; overflow: hidden; position: relative;
    background: linear-gradient(135deg, #1a1a2e, #16162a);
}
.pcard-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
.pcard:hover .pcard-img img { transform: scale(1.05); }
.pcard-initial {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.8rem; font-weight: 800; color: rgba(255,255,255,.75);
    font-family: var(--font-head);
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
}

.pcard-featured {
    position: absolute; top: .6rem; left: .6rem;
    background: linear-gradient(90deg, #f59e0b, #ef4444);
    color: #fff; font-size: .62rem; font-weight: 800;
    padding: .22rem .6rem; border-radius: 99px;
    letter-spacing: .04em;
    box-shadow: 0 2px 8px rgba(239,68,68,.4);
}

.pcard-avail {
    position: absolute; bottom: .5rem; right: .5rem;
    display: flex; align-items: center; gap: .3rem;
    background: rgba(0,0,0,.7); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 99px; padding: .2rem .55rem;
    font-size: .65rem; font-weight: 600;
}

.pcard-ml {
    position: absolute; top: .6rem; right: .6rem;
}

.pcard-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; gap: .5rem; }

.pcard-name {
    font-size: .93rem; font-weight: 700;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    color: var(--text);
}

.pcard-prof {
    font-size: .75rem; color: var(--accent-2); font-weight: 600;
    display: flex; align-items: center; gap: .3rem;
}

.pcard-loc {
    font-size: .75rem; color: var(--text-3);
    display: flex; align-items: center; gap: .3rem;
}

.pcard-row {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: .35rem; margin-top: auto;
}

.pcard-rating { display: flex; align-items: center; gap: .4rem; }
.pcard-rnum { font-size: .78rem; font-weight: 700; color: var(--text); }
.pcard-rcount { font-size: .7rem; color: var(--text-3); }

/* vbadge (inline verification) */
.vbadge {
    display: inline-flex; align-items: center; gap: .25rem;
    font-size: .62rem; font-weight: 700;
    padding: .18rem .5rem; border-radius: 99px; color: #fff;
    background: var(--accent);
}

/* pcard actions */
.pcard-actions {
    display: flex; gap: .4rem; padding: .75rem 1rem;
    border-top: 1px solid var(--border);
}

.btn-view {
    flex: 1; background: linear-gradient(135deg, var(--accent), var(--accent-2));
    color: #fff; border: none; border-radius: var(--radius-sm);
    padding: .5rem; font-size: .78rem; font-weight: 700;
    cursor: pointer; transition: opacity .18s;
    text-decoration: none; text-align: center;
    display: flex; align-items: center; justify-content: center; gap: .3rem;
    font-family: var(--font-body);
}
.btn-view:hover { opacity: .9; color: #fff; text-decoration: none; }

.btn-icon {
    width: 34px; height: 34px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; color: var(--text-2);
    cursor: pointer; transition: all .18s;
    text-decoration: none;
}
.btn-icon:hover { border-color: var(--border-hi); color: var(--text); text-decoration: none; }
.btn-icon.danger:hover { border-color: #ef4444; color: #ef4444; }
.btn-icon.warn:hover   { border-color: var(--ml-amber); color: var(--ml-amber); }

/* ML score bar on provider card */
.pcard-score-bar { height: 3px; background: var(--border); border-radius: 99px; overflow: hidden; }
.pcard-score-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--ml-green), #34d399); transition: width .8s cubic-bezier(.4,0,.2,1); }

/* ════════════════════════════════════════════════════════
   EMPTY & PAGINATION
   ════════════════════════════════════════════════════════ */
.empty-state {
    text-align: center; padding: 4rem 2rem; color: var(--text-3);
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.empty-state i { font-size: 3rem; display: block; margin-bottom: 1rem; opacity: .4; }
.empty-state h3 { font-size: 1.15rem; color: var(--text-2); margin-bottom: .4rem; }
.empty-state p { font-size: .875rem; }

.pagination-wrap { display: flex; justify-content: center; gap: .4rem; margin-top: 2rem; flex-wrap: wrap; }

.page-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .35rem;
    min-width: 36px; height: 36px; padding: 0 .65rem;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-sm); color: var(--text-2);
    font-size: .8rem; font-weight: 600; text-decoration: none;
    transition: all .18s;
}
.page-btn:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
.page-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

/* ════════════════════════════════════════════════════════
   ALERTS
   ════════════════════════════════════════════════════════ */
.flash {
    border-radius: var(--radius-sm); padding: .875rem 1.125rem;
    margin-bottom: 1.25rem; font-size: .875rem;
    border: 1px solid transparent;
    display: flex; align-items: flex-start; gap: .5rem;
}
.flash.success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.25); color: #6ee7b7; }
.flash.danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.25);  color: #fca5a5; }

/* ════════════════════════════════════════════════════════
   MODAL (dark)
   ════════════════════════════════════════════════════════ */
.dmodal {
    display: none; position: fixed; inset: 0; z-index: 2000;
    background: rgba(0,0,0,.72); backdrop-filter: blur(8px);
    align-items: center; justify-content: center; padding: 1rem;
}
.dmodal.open { display: flex; animation: modal-in .2s ease; }

@keyframes modal-in {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.dmodal-box {
    background: var(--bg-2); border: 1px solid var(--border-hi);
    border-radius: var(--radius-lg);
    width: 100%; max-width: 440px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 32px 64px rgba(0,0,0,.7);
    animation: modal-up .25s cubic-bezier(.34,1.56,.64,1);
}

@keyframes modal-up {
    from { transform: translateY(24px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}

.dmodal-head {
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.dmodal-head h4 { font-size: 1rem; font-weight: 800; }
.dmodal-close {
    width: 30px; height: 30px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-2); font-size: .8rem;
    transition: all .18s;
}
.dmodal-close:hover { background: rgba(239,68,68,.15); border-color: #ef4444; color: #ef4444; }

.dmodal-body { padding: 1.25rem 1.5rem; }
.dmodal-body .form-group { margin-bottom: 1rem; }
.dmodal-body label { display: block; font-size: .78rem; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .4rem; }
.dmodal-body input,
.dmodal-body select,
.dmodal-body textarea {
    width: 100%; background: var(--bg);
    border: 1px solid var(--border); border-radius: var(--radius-sm);
    color: var(--text); padding: .6rem .85rem;
    font-family: var(--font-body); font-size: .875rem; outline: none;
    transition: border-color .18s;
}
.dmodal-body input:focus,
.dmodal-body select:focus,
.dmodal-body textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.dmodal-body select option { background: #1a1a2e; }
.dmodal-body textarea { resize: vertical; min-height: 100px; }

.dmodal-foot {
    padding: 1rem 1.5rem; border-top: 1px solid var(--border);
    display: flex; gap: .5rem; justify-content: flex-end;
}

.btn-submit {
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    color: #fff; border: none; border-radius: var(--radius-sm);
    padding: .6rem 1.25rem; font-size: .875rem; font-weight: 700;
    cursor: pointer; font-family: var(--font-body);
    box-shadow: 0 4px 14px var(--accent-glow);
    transition: opacity .18s;
}
.btn-submit:hover { opacity: .9; }
.btn-submit.danger { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 4px 14px rgba(239,68,68,.35); }

.btn-cancel-m {
    background: var(--surface-2); color: var(--text-2);
    border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: .6rem 1rem; font-size: .875rem; font-weight: 600;
    cursor: pointer; font-family: var(--font-body);
    transition: all .18s;
}
.btn-cancel-m:hover { border-color: var(--border-hi); color: var(--text); }

/* AI suggestion box */
.ai-box {
    background: rgba(99,102,241,.08); border: 1px solid rgba(99,102,241,.2);
    border-radius: var(--radius-sm); padding: .875rem 1rem;
    margin-bottom: 1.25rem; font-size: .875rem; color: var(--text-2);
    display: flex; align-items: flex-start; gap: .6rem;
}
.ai-box i { color: var(--accent); margin-top: 2px; flex-shrink: 0; }

/* ════════════════════════════════════════════════════════
   RESPONSIVE
   ════════════════════════════════════════════════════════ */
.mobile-toggle {
    display: none;
    position: fixed; top: .875rem; left: .875rem; z-index: 1100;
    width: 40px; height: 40px;
    background: var(--accent); color: #fff; border: none;
    border-radius: var(--radius-sm); cursor: pointer; font-size: 1rem;
    box-shadow: 0 4px 14px var(--accent-glow);
    align-items: center; justify-content: center;
}

.overlay-mob {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.6); z-index: 999; backdrop-filter: blur(4px);
}

@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,.5); }
    .main { margin-left: 0; }
    .mobile-toggle { display: flex; }
    .overlay-mob.active { display: block; }
    .page-body { padding: 1.25rem 1rem 3rem; }
    .topbar { padding: .75rem 1rem .75rem 3.5rem; }
    .ml-cards { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
    .provider-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
    .filters-bar { gap: .5rem; }
    .fgroup { min-width: 100%; }
}

@media (max-width: 480px) {
    .ml-cards { grid-template-columns: 1fr 1fr; }
    .provider-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div>
                <h1 class="topbar-title">Find Providers</h1>
                <p class="topbar-subtitle">Discover verified professionals for your needs</p>
            </div>
            <div class="topbar-actions">
                <div class="ml-status">
                    <div class="ml-dot" id="mlDot"></div>
                    <span id="mlStatusText">ML Engine</span>
                </div>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="search-section">
            <form method="GET" action="providers.php" class="search-form">
                <div class="search-input-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="query" class="search-input"
                           placeholder="Search by skill, name, or service..."
                           value="<?= htmlspecialchars($searchQuery) ?>"
                           autocomplete="off">
                </div>

                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Location</label>
                        <input type="text" name="location" class="filter-input"
                               placeholder="Enter location..."
                               value="<?= htmlspecialchars($locationQuery) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Min Rating</label>
                        <select name="min_rating" class="filter-select">
                            <option value="0">Any Rating</option>
                            <option value="3" <?= $minRating >= 3 ? 'selected' : '' ?>>3+ Stars</option>
                            <option value="4" <?= $minRating >= 4 ? 'selected' : '' ?>>4+ Stars</option>
                            <option value="4.5" <?= $minRating >= 4.5 ? 'selected' : '' ?>>4.5+ Stars</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Availability</label>
                        <select name="availability" class="filter-select">
                            <option value="">Any Status</option>
                            <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Available Now</option>
                            <option value="busy" <?= $availability === 'busy' ? 'selected' : '' ?>>Busy</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search" style="margin-right: 0.5rem;"></i>Search
                        </button>
                    </div>
                </div>

                <!-- Active Filters -->
                <?php
                $activeFilters = [];
                if ($searchQuery) $activeFilters[] = "Query: {$searchQuery}";
                if ($locationQuery) $activeFilters[] = "Location: {$locationQuery}";
                if ($categoryId) $activeFilters[] = "Category: " . ($categories[array_search($categoryId, array_column($categories, 'id'))]['name'] ?? 'Unknown');
                if ($minRating > 0) $activeFilters[] = "Rating: {$minRating}+";
                if ($availability) $activeFilters[] = "Status: " . ucfirst($availability);
                ?>

                <?php if (!empty($activeFilters)): ?>
                <div class="filter-chips">
                    <?php foreach ($activeFilters as $filter): ?>
                        <span class="chip active"><?= htmlspecialchars($filter) ?></span>
                    <?php endforeach; ?>
                    <a href="providers.php" class="chip">Clear All</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ML Recommendations -->
        <?php if ($showMLStrip && !empty($mlRecommended)): ?>
        <div class="ml-recommendations">
            <div class="ml-header">
                <div class="ml-title-section">
                    <div class="ml-brain-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div>
                        <h2 class="ml-title">AI-Powered Recommendations</h2>
                        <p class="ml-subtitle">Personalized suggestions based on your preferences and booking history</p>
                    </div>
                </div>
                <span class="ml-badge">
                    <i class="fas fa-microchip" style="margin-right: 0.375rem;"></i>ML Powered
                </span>
            </div>

            <div class="ml-grid">
                <?php foreach (array_slice($mlRecommended, 0, 6) as $rec):
                    $pid = (int)$rec['id'];
                    $name = htmlspecialchars($rec['full_name'] ?? '');
                    $prof = htmlspecialchars($rec['profession'] ?? '');
                    $img = $rec['profile_image'] ?? '';
                    $rating = $rec['average_rating'] ?? 0;
                    $reviews = $rec['total_reviews'] ?? 0;
                    $score = $rec['ml_score'] ?? null;
                ?>
                <div class="ml-card">
                    <div class="ml-card-header">
                        <div class="ml-card-avatar">
                            <?php if ($img): ?>
                                <img src="../uploads/profiles/<?= htmlspecialchars($img) ?>" alt="<?= $name ?>">
                            <?php else: ?>
                                <?= substr($name, 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div class="ml-card-info">
                            <div class="ml-card-name"><?= $name ?></div>
                            <div class="ml-card-profession"><?= $prof ?></div>
                        </div>
                        <?php if ($score !== null): ?>
                        <span class="ml-score-badge"><?= round($score * 100) ?>%</span>
                        <?php endif; ?>
                    </div>

                    <div class="ml-card-meta">
                        <div class="ml-rating">
                            <div class="ml-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $rating ? '' : '-half-alt' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="ml-rating-text"><?= number_format($rating, 1) ?></span>
                        </div>
                        <span class="ml-reviews">(<?= $reviews ?> reviews)</span>
                    </div>

                    <p class="ml-card-description">
                        Professional <?= $prof ?> with excellent track record and high customer satisfaction.
                    </p>

                    <div class="ml-card-actions">
                        <a href="provider-profile.php?id=<?= $pid ?>" class="btn-ml-primary">
                            View Profile
                        </a>
                        <button class="btn-ml-secondary" onclick="trackProviderClick(<?= $pid ?>)">
                            <i class="fas fa-heart" style="margin-right: 0.375rem;"></i>Save
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Providers Grid -->
        <div class="providers-section">
            <div class="providers-header">
                <div>
                    <h2 class="providers-title">
                        <?php if ($searchQuery): ?>
                            Search Results
                        <?php elseif ($categoryId): ?>
                            <?= htmlspecialchars($categories[array_search($categoryId, array_column($categories, 'id'))]['name'] ?? 'Category') ?> Providers
                        <?php else: ?>
                            All Providers
                        <?php endif; ?>
                    </h2>
                    <p class="providers-count">
                        <?php if ($totalProviders > 0): ?>
                            Showing <?= min($perPage, $totalProviders) ?> of <?= $totalProviders ?> providers
                        <?php else: ?>
                            No providers found matching your criteria
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($totalProviders > 0): ?>
                <div class="sort-controls">
                    <label class="sort-label">Sort by:</label>
                    <select class="sort-select" onchange="changeSort(this.value)">
                        <option value="recommended" <?= $sortBy === 'recommended' ? 'selected' : '' ?>>Recommended</option>
                        <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        <option value="reviews" <?= $sortBy === 'reviews' ? 'selected' : '' ?>>Most Reviews</option>
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="nearest" <?= $sortBy === 'nearest' ? 'selected' : '' ?>>Nearest</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($totalProviders > 0): ?>
            <div class="providers-grid">
                <?php foreach ($providers as $provider):
                    $pid = (int)$provider['id'];
                    $name = htmlspecialchars($provider['full_name'] ?? '');
                    $prof = htmlspecialchars($provider['profession'] ?? '');
                    $loc = htmlspecialchars($provider['location'] ?? '');
                    $img = $provider['profile_image'] ?? '';
                    $rating = $provider['average_rating'] ?? 0;
                    $reviews = $provider['total_reviews'] ?? 0;
                    $rate = $provider['hourly_rate'] ?? 0;
                    $avail = $provider['availability'] ?? 'available';
                    $mlScore = $mlScores[$pid] ?? null;
                ?>
                <div class="provider-card">
                    <div class="provider-image">
                        <?php if ($img): ?>
                            <img src="../uploads/profiles/<?= htmlspecialchars($img) ?>" alt="<?= $name ?>">
                        <?php else: ?>
                            <div class="provider-avatar">
                                <?= substr($name, 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <div class="provider-status <?= $avail !== 'available' ? 'offline' : '' ?>"></div>
                    </div>

                    <div class="provider-content">
                        <div class="provider-header">
                            <div class="provider-info">
                                <h3 class="provider-name"><?= $name ?></h3>
                                <p class="provider-profession"><?= $prof ?></p>
                            </div>
                        </div>

                        <div class="provider-rating">
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $rating ? '' : ($i - 0.5 <= $rating ? '-half-alt' : '') ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text"><?= number_format($rating, 1) ?> (<?= $reviews ?>)</span>
                        </div>

                        <div class="provider-details">
                            <?php if ($loc): ?>
                            <div class="detail-row">
                                <i class="fas fa-map-marker-alt detail-icon"></i>
                                <span><?= $loc ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($rate > 0): ?>
                            <div class="detail-row">
                                <i class="fas fa-dollar-sign detail-icon"></i>
                                <span>$<?= number_format($rate, 0) ?>/hour</span>
                            </div>
                            <?php endif; ?>

                            <div class="detail-row">
                                <i class="fas fa-clock detail-icon"></i>
                                <span class="availability-<?= $avail ?>">
                                    <?= ucfirst($avail) ?>
                                </span>
                            </div>
                        </div>

                        <div class="provider-actions">
                            <a href="provider-profile.php?id=<?= $pid ?>" class="btn-primary">
                                View Profile
                            </a>
                            <a href="#" class="btn-secondary" onclick="toggleFavorite(<?= $pid ?>)">
                                <i class="fas fa-heart"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination" style="text-align: center; margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-secondary" style="margin-right: 0.5rem;">Previous</a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if ($startPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="btn-secondary" style="margin-right: 0.5rem;">1</a>
                    <?php if ($startPage > 2): ?>
                        <span style="margin-right: 0.5rem;">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                       class="btn-<?= $i === $page ? 'primary' : 'secondary' ?>"
                       style="margin-right: 0.5rem;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span style="margin-right: 0.5rem;">...</span>
                    <?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="btn-secondary" style="margin-right: 0.5rem;"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-secondary">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="text-center" style="padding: 3rem;">
                <i class="fas fa-search" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--text-secondary); margin-bottom: 0.5rem;">No providers found</h3>
                <p style="color: var(--text-muted);">Try adjusting your search criteria or browse all providers.</p>
                <a href="providers.php" class="btn-primary" style="margin-top: 1rem;">Browse All Providers</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // ML Status Indicator
        function updateMLStatus() {
            const dot = document.getElementById('mlDot');
            const text = document.getElementById('mlStatusText');

            fetch('providers.php?ml_health=1')
                .then(response => response.json())
                .then(data => {
                    if (data.online) {
                        dot.classList.remove('offline');
                        text.textContent = 'ML Online';
                    } else {
                        dot.classList.add('offline');
                        text.textContent = 'ML Offline';
                    }
                })
                .catch(() => {
                    dot.classList.add('offline');
                    text.textContent = 'ML Offline';
                });
        }

        // Sort change handler
        function changeSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            url.searchParams.set('page', '1');
            window.location = url;
        }

        // Favorite toggle
        function toggleFavorite(providerId) {
            // Implementation for favorite toggle
            console.log('Toggle favorite for provider:', providerId);
        }

        // Track provider clicks
        function trackProviderClick(providerId) {
            // Implementation for tracking
            console.log('Track click for provider:', providerId);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateMLStatus();
            setInterval(updateMLStatus, 30000); // Update every 30 seconds
        });
    </script>
</body>
</html>
