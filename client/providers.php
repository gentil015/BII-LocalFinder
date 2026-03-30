<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/event_tracking.php';

if (!isLoggedIn()) { redirect('login.php'); }
if (isProvider())  { redirect('provider/dashboard.php'); }

$db  = Database::getInstance()->getConnection();
$uid = (int)$_SESSION['user_id'];

// ── Settings ─────────────────────────────────────────────────────────────────
function getSetting($db, $key, $default = '') {
    $s = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $s->execute([$key]);
    $r = $s->fetch(PDO::FETCH_COLUMN);
    return $r !== false ? $r : $default;
}
$platform_name = getSetting($db, 'platform_name', 'BII LocalFinder');

// ── Client profile ────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$client = $stmt->fetch();
$clientLocation = trim($client['location'] ?? '');
$clientName     = $client['full_name'] ?? 'there';

// ── Client behavior fingerprint ───────────────────────────────────────────────
// Previously booked professions (weighted)
$stmt = $db->prepare("
    SELECT sp.profession, COUNT(*) as cnt
    FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id
    WHERE b.client_id = ?
    GROUP BY sp.profession ORDER BY cnt DESC LIMIT 6
");
$stmt->execute([$uid]);
$bookedProfessions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);   // profession => cnt

// Favorited provider IDs
$favIds = [];
try {
    $stmt = $db->prepare("SELECT provider_id FROM favorites WHERE client_id = ?");
    $stmt->execute([$uid]);
    $favIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $favIds = []; }

// Recently viewed providers (click_logs)
$recentlyViewedIds = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT target_id FROM click_logs
        WHERE user_id = ? AND target_type = 'provider' AND target_id IS NOT NULL
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute([$uid]);
    $recentlyViewedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $recentlyViewedIds = []; }

// User booking stats for ML
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
$stmt->execute([$uid]);
$userTotalBookings = (int)$stmt->fetchColumn();

$userAvgPrice = 0.0; $userAvgResp = 24.0;
try {
    $s = $db->prepare("SELECT user_avg_price, user_avg_response_time FROM user_profiles WHERE user_id = ?");
    $s->execute([$uid]);
    $up = $s->fetch(PDO::FETCH_ASSOC);
    if ($up) { $userAvgPrice = (float)($up['user_avg_price'] ?? 0); $userAvgResp = (float)($up['user_avg_response_time'] ?? 24); }
} catch (Throwable $e) {}

// ── Filters ────────────────────────────────────────────────────────────────────
$search    = trim($_GET['search']    ?? '');
$category  = trim($_GET['category']  ?? '');
$location  = trim($_GET['location']  ?? '');
$sort      = trim($_GET['sort']      ?? 'ml');   // ml | rating | reviews | newest | price_asc | price_desc
$avail     = trim($_GET['avail']     ?? '');      // available | busy | ''
$minRating = (float)($_GET['min_rating'] ?? 0);
$verified  = isset($_GET['verified']) ? (bool)$_GET['verified'] : false;
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 12;
$offset    = ($page - 1) * $perPage;

// ── All categories for filter bar ─────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT sp.profession as cat, COUNT(DISTINCT sp.id) as cnt
    FROM service_providers sp WHERE sp.is_active=1 AND sp.is_banned=0
    GROUP BY sp.profession ORDER BY cnt DESC LIMIT 16
");
$stmt->execute();
$allCats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── All districts for filter ───────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT DISTINCT sp.location FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.is_active=1 AND sp.is_banned=0 AND sp.location != ''
    ORDER BY sp.location LIMIT 30
");
$stmt->execute();
$allLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ── Build provider query ───────────────────────────────────────────────────────
$where   = ["sp.is_active = 1", "sp.is_banned = 0", "u.user_type = 'provider'"];
$params  = [];

if ($search !== '') {
    $where[]  = "(u.full_name LIKE ? OR sp.profession LIKE ? OR sp.bio LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($category !== '') {
    $where[]  = "sp.profession = ?";
    $params[] = $category;
}
if ($location !== '') {
    $where[]  = "sp.location = ?";
    $params[] = $location;
}
if ($avail !== '') {
    $where[]  = "sp.availability = ?";
    $params[] = $avail;
}
if ($minRating > 0) {
    $where[]  = "sp.average_rating >= ?";
    $params[] = $minRating;
}
if ($verified) {
    $where[] = "(sp.is_verified = 1 OR u.is_verified = 1)";
}

$whereSQL = implode(' AND ', $where);

// Count total
$countSQL = "SELECT COUNT(DISTINCT sp.id) FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE $whereSQL";
$cStmt    = $db->prepare($countSQL);
$cStmt->execute($params);
$totalProviders = (int)$cStmt->fetchColumn();
$totalPages     = max(1, (int)ceil($totalProviders / $perPage));

// Fetch provider rows (fetch all for ML scoring, then paginate after)
$fetchLimit = ($sort === 'ml') ? min($totalProviders, 80) : $perPage;
$fetchOffset = ($sort === 'ml') ? 0 : $offset;

$orderBy = match($sort) {
    'rating'     => "sp.average_rating DESC, sp.total_reviews DESC",
    'reviews'    => "sp.total_reviews DESC, sp.average_rating DESC",
    'newest'     => "sp.created_at DESC",
    'price_asc'  => "avg_price ASC NULLS LAST",
    'price_desc' => "avg_price DESC",
    default      => "sp.average_rating DESC, sp.total_reviews DESC",
};

$favCheck   = count($favIds) > 0 ? "(sp.id IN (" . implode(',', array_fill(0, count($favIds), '?')) . ")) as is_fav" : "0 as is_fav";
$favParams  = count($favIds) > 0 ? $favIds : [];

$mainSQL = "
    SELECT sp.*, u.full_name, u.email, u.profile_image,
           sp.location as provider_location, u.is_verified as user_verified,
           $favCheck,
           (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) as avg_price,
           (SELECT COUNT(*) FROM bookings b WHERE b.provider_id=sp.id AND b.status='completed') as completed_jobs,
           (SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id=sp.id) as view_count
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE $whereSQL
    ORDER BY $orderBy
    LIMIT $fetchLimit OFFSET $fetchOffset
";

$mainParams = array_merge($favParams, $params);
$stmt = $db->prepare($mainSQL);
$stmt->execute($mainParams);
$rawProviders = $stmt->fetchAll();

// ── ML RANKING (only when sort=ml) ────────────────────────────────────────────
$mlApiStatus = 'fallback';
$mlScoreMap  = [];

if ($sort === 'ml' && !empty($rawProviders)) {
    // Build feature vectors
    $batchPayload = [];
    foreach ($rawProviders as $p) {
        $pid = (int)$p['id'];

        // clicks from click_logs
        $clicks = 0.0;
        try {
            $s = $db->prepare("SELECT COUNT(*) FROM click_logs WHERE target_type='provider' AND target_id=?");
            $s->execute([$pid]); $clicks = (float)$s->fetchColumn();
        } catch (Throwable $e) {}

        // messages
        $msgs = 0.0;
        try {
            $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=?");
            $s->execute([(int)($p['user_id'] ?? 0)]); $msgs = (float)$s->fetchColumn();
        } catch (Throwable $e) {}

        // avg response time
        $avgResp = 24.0;
        try {
            $s = $db->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,responded_at)) FROM bookings WHERE provider_id=? AND responded_at IS NOT NULL");
            $s->execute([$pid]);
            $ar = $s->fetchColumn();
            if ($ar !== null && $ar !== false) $avgResp = (float)$ar;
        } catch (Throwable $e) {}

        $batchPayload[] = [
            'provider_id' => $pid,
            'features' => [
                'views'                  => max(0.0, (float)($p['view_count'] ?? 0)),
                'clicks'                 => max(0.0, $clicks),
                'messages'               => max(0.0, $msgs),
                'rating'                 => min(5.0, max(0.0, (float)($p['average_rating'] ?? 0))),
                'price'                  => max(0.0, (float)($p['avg_price'] ?? 0)),
                'avg_response_time'      => max(0.0, $avgResp),
                'user_avg_price'         => max(0.0, $userAvgPrice),
                'user_avg_response_time' => max(0.0, $userAvgResp),
                'user_total_bookings'    => max(0, $userTotalBookings),
            ]
        ];
    }

    // Call FastAPI
    $ch = curl_init('http://localhost:8000/predict/batch');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => json_encode($batchPayload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $mlRaw   = curl_exec($ch);
    $mlErrno = curl_errno($ch);
    curl_close($ch);

    if (!$mlErrno && $mlRaw) {
        $mlResults = json_decode($mlRaw, true);
        if (is_array($mlResults) && !empty($mlResults)) {
            foreach ($mlResults as $r) {
                $mlScoreMap[(int)$r['provider_id']] = [
                    'score'      => (float)$r['probability'],
                    'prediction' => (int)$r['prediction'],
                    'confidence' => (string)$r['confidence'],
                ];
            }
            $mlApiStatus = 'ml';
        }
    }

    // Attach scores
    foreach ($rawProviders as &$p) {
        $pid = (int)$p['id'];
        $ml  = $mlScoreMap[$pid] ?? null;

        // Personalization boost (private to this client — not stored globally)
        $personalBoost = 0.0;
        if (isset($bookedProfessions[$p['profession']])) {
            $personalBoost += min(0.15, $bookedProfessions[$p['profession']] * 0.03);
        }
        if (in_array($pid, $favIds)) {
            $personalBoost += 0.12;
        }
        if (($p['provider_location'] ?? '') === $clientLocation && $clientLocation !== '') {
            $personalBoost += 0.08;
        }
        if (in_array($pid, $recentlyViewedIds)) {
            $personalBoost += 0.05;
        }

        $baseScore = $ml ? $ml['score'] : (
            (float)($p['average_rating'] ?? 0) / 5 * 0.5 +
            min(0.3, (int)($p['total_reviews'] ?? 0) / 100 * 0.3)
        );

        $p['ml_score']       = min(1.0, $baseScore + $personalBoost);
        $p['ml_raw_score']   = $ml ? $ml['score'] : $baseScore;
        $p['ml_confidence']  = $ml ? $ml['confidence'] : 'low';
        $p['personal_boost'] = $personalBoost;
    }
    unset($p);

    // Sort by personalized ML score
    usort($rawProviders, fn($a, $b) => $b['ml_score'] <=> $a['ml_score']);

    // Paginate after sorting
    $providers = array_slice($rawProviders, $offset, $perPage);

} else {
    // Non-ML sort: rows already limited by SQL
    foreach ($rawProviders as &$p) {
        $p['ml_score']      = 0.0;
        $p['ml_raw_score']  = 0.0;
        $p['ml_confidence'] = 'n/a';
        $p['personal_boost']= 0.0;
    }
    unset($p);
    $providers = $rawProviders;
}

// ── "For You" strip (top 6 personalized, different from main list) ─────────────
$forYouProviders = [];
if (!empty($bookedProfessions) || !empty($favIds)) {
    $profList = array_keys($bookedProfessions);
    $fyWhere  = ["sp.is_active=1","sp.is_banned=0","u.user_type='provider'","sp.average_rating>=3.5"];
    $fyParams = [];
    if (!empty($profList)) {
        $ph = implode(',', array_fill(0, count($profList), '?'));
        $fyWhere[]  = "sp.profession IN ($ph)";
        $fyParams   = array_merge($fyParams, $profList);
    }
    if (!empty($providers)) {
        $shownIds = array_column($providers, 'id');
        if (!empty($shownIds)) {
            $ph2 = implode(',', array_fill(0, count($shownIds), '?'));
            $fyWhere[] = "sp.id NOT IN ($ph2)";
            $fyParams  = array_merge($fyParams, $shownIds);
        }
    }
    $fySQL = "SELECT sp.*, u.full_name, u.profile_image, sp.location as provider_location,
                     (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) as avg_price
              FROM service_providers sp JOIN users u ON sp.user_id=u.id
              WHERE " . implode(' AND ', $fyWhere) . "
              ORDER BY sp.average_rating DESC LIMIT 6";
    try {
        $s = $db->prepare($fySQL); $s->execute($fyParams);
        $forYouProviders = $s->fetchAll();
    } catch (Throwable $e) { $forYouProviders = []; }
}

// ── Category icon map ─────────────────────────────────────────────────────────
$catIcons = [
    'Plumbing'=>'fa-wrench','Plumber'=>'fa-wrench','Electrical'=>'fa-bolt','Electrician'=>'fa-bolt',
    'Construction'=>'fa-hammer','Carpenter'=>'fa-hammer','Carpentry'=>'fa-hammer',
    'Cleaning'=>'fa-broom','Painting'=>'fa-paint-brush','Painter'=>'fa-paint-brush',
    'Landscaping'=>'fa-leaf','Gardener'=>'fa-leaf','HVAC'=>'fa-fan','Roofing'=>'fa-house-damage',
    'Welding'=>'fa-fire','Masonry'=>'fa-cube','Mechanic'=>'fa-tools','Hairdresser'=>'fa-scissors',
    'Tutoring'=>'fa-book','IT Support'=>'fa-laptop','Photography'=>'fa-camera',
    'default'=>'fa-star'
];

// Track page view
try { trackEvent('providers_page_view','page',0,['filters'=>compact('search','category','location','sort','avail')],$uid); } catch(Throwable $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Find Providers — <?php echo htmlspecialchars($platform_name); ?></title>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
<style>
/* ══════════════════════════════════════════════════════
   DESIGN SYSTEM  —  Uber-adjacent precision tool aesthetic
   Cool slate palette · Syne display · DM Sans body
   ══════════════════════════════════════════════════════ */
:root {
  --bg:          #f7f8fc;
  --bg-2:        #ffffff;
  --bg-3:        #f3f4f6;
  --surface:     #ffffff;
  --surface-2:   #f7f8fc;
  --border:      #e8eaf0;
  --border-light:#f0f2f7;
  --text-primary:#0f1117;
  --text-secondary:#6b7280;
  --text-muted:  #9ca3af;
  --accent:      #0d6efd;
  --accent-2:    #3d6fe8;
  --accent-glow: rgba(13,110,253,.12);
  --green:       #198754;
  --green-dim:   rgba(25,135,84,.15);
  --amber:       #f59e0b;
  --amber-dim:   rgba(245,158,11,.15);
  --red:         #dc3545;
  --red-dim:     rgba(220,53,69,.15);
  --text-1:      #0f1117;
  --text-2:      #6b7280;
  --text-3:      #9ca3af;
  --sidebar-w:   260px;
  --r-sm:        8px;
  --r-md:        12px;
  --r-lg:        16px;
  --r-xl:        22px;
  --shadow-glow: 0 0 0 1px rgba(13,110,253,.12), 0 8px 32px rgba(0,0,0,.08);
  --transition:  all .2s cubic-bezier(.4,0,.2,1);
}

*,*::before,*::after { box-sizing: border-box; margin:0; padding:0; }

body {
  background: var(--bg);
  font-family: 'DM Sans', system-ui, sans-serif;
  color: var(--text-1);
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}

h1,h2,h3,h4,h5 { font-family: 'Syne', sans-serif; }

/* ── SIDEBAR ─────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  background: var(--surface);
  border-right: 1px solid var(--border);
  position: fixed; height: 100vh; left:0; top:0;
  z-index: 1000; display: flex; flex-direction: column;
  transition: var(--transition);
}
.sidebar-header {
  padding: 1.5rem 1.25rem 1.25rem;
  border-bottom: 1px solid var(--border-light);
}
.sidebar-header h2 { font-size:1.1rem; font-weight:800; color: var(--accent); }
.sidebar-header p  { font-size:.75rem; color: var(--text-3); margin-top:.25rem; }
.sidebar-menu { list-style:none; padding:.75rem; flex:1; overflow-y:auto; }
.sidebar-menu li { margin:.1rem 0; }
.sidebar-menu a {
  color: var(--text-2); text-decoration:none;
  padding:.6rem .85rem; display:flex; align-items:center; gap:.65rem;
  border-radius: var(--r-sm); font-size:.875rem; font-weight:500;
  transition: var(--transition);
}
.sidebar-menu a:hover { background: rgba(13,110,253,.08); color: var(--accent); }
.sidebar-menu a.active { background: var(--accent); color:#fff; font-weight:600; }
.sidebar-menu i { width:18px; font-size:.875rem; flex-shrink:0; }

/* ── MAIN CONTENT ────────────────────────────────── */
.main-content { margin-left: var(--sidebar-w); min-height:100vh; }

/* ── TOP SEARCH BAR ──────────────────────────────── */
.search-header {
  background: var(--bg-2);
  border-bottom: 1px solid var(--border);
  padding: 1.25rem 2rem;
  position: sticky; top:0; z-index:100;
  backdrop-filter: blur(16px);
}
.search-bar-row {
  display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;
}
.search-input-wrap {
  flex: 1; min-width: 260px; position: relative;
}
.search-input-wrap i {
  position:absolute; left:.875rem; top:50%; transform:translateY(-50%);
  color: var(--text-3); font-size:.875rem;
}
.search-input {
  width:100%; background: var(--surface); border: 1px solid var(--border);
  color: var(--text-1); padding: .65rem .875rem .65rem 2.5rem;
  border-radius: var(--r-md); font-size:.9rem; font-family:inherit;
  transition: var(--transition); outline:none;
}
.search-input::placeholder { color: var(--text-3); }
.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }

.filter-select {
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-1); padding: .65rem 1rem;
  border-radius: var(--r-md); font-size:.85rem; font-family:inherit;
  cursor:pointer; outline:none; transition: var(--transition);
  appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238892aa' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right .75rem center;
  padding-right: 2.25rem;
}
.filter-select:focus { border-color: var(--accent); }

.btn-search {
  background: var(--accent); color:#fff; border:none;
  padding: .65rem 1.5rem; border-radius: var(--r-md);
  font-weight:700; font-size:.875rem; cursor:pointer;
  transition: var(--transition); font-family:inherit;
  display:flex; align-items:center; gap:.4rem; white-space:nowrap;
}
.btn-search:hover { background: var(--accent-2); transform:translateY(-1px); }

/* ── CATEGORY CHIPS BAR ──────────────────────────── */
.cat-bar {
  background: var(--bg-2); border-bottom: 1px solid var(--border);
  padding: .75rem 2rem; display:flex; gap:.5rem; overflow-x:auto;
  -webkit-overflow-scrolling: touch;
}
.cat-bar::-webkit-scrollbar { display:none; }
.cat-chip {
  display: inline-flex; align-items:center; gap:.4rem;
  padding: .4rem .875rem; border-radius: 100px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-2); font-size:.78rem; font-weight:600;
  cursor:pointer; text-decoration:none; transition: var(--transition);
  white-space:nowrap; flex-shrink:0;
}
.cat-chip:hover  { border-color: var(--accent); color: var(--accent); }
.cat-chip.active { background: var(--accent); border-color: var(--accent); color:#fff; }
.cat-chip i { font-size:.7rem; }

/* ── PAGE BODY ────────────────────────────────────── */
.page-body { padding: 1.75rem 2rem; }

/* ── PERSONALIZED BANNER ─────────────────────────── */
.pers-banner {
  background: linear-gradient(135deg, #1a2540 0%, #0f1829 100%);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  padding: 1.5rem 2rem;
  margin-bottom: 1.75rem;
  position: relative; overflow:hidden;
  display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;
}
.pers-banner::before {
  content:''; position:absolute; top:-40%; right:-5%;
  width:320px; height:320px;
  background: radial-gradient(circle, rgba(79,140,255,.12) 0%, transparent 65%);
}
.pers-banner-icon {
  width:52px; height:52px; border-radius: var(--r-md);
  background: rgba(79,140,255,.15); border: 1px solid rgba(79,140,255,.25);
  display:flex; align-items:center; justify-content:center;
  font-size:1.4rem; color: var(--accent); flex-shrink:0;
}
.pers-banner-text h3 { font-size:1.1rem; font-weight:800; margin-bottom:.2rem; }
.pers-banner-text p  { font-size:.83rem; color: var(--text-2); margin:0; }
.pers-pill {
  display:inline-flex; align-items:center; gap:.35rem;
  background: rgba(79,140,255,.12); border: 1px solid rgba(79,140,255,.25);
  color: var(--accent); border-radius:100px; padding:.25rem .7rem;
  font-size:.7rem; font-weight:700; margin:.2rem .2rem 0 0;
}

/* ── FOR-YOU STRIP ────────────────────────────────── */
.section-label {
  font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.09em;
  color: var(--text-3); margin-bottom:1rem;
  display:flex; align-items:center; gap:.5rem;
}
.section-label::after {
  content:''; flex:1; height:1px; background: var(--border);
}

.foryou-strip {
  display:flex; gap:1rem; overflow-x:auto; padding-bottom:.75rem;
  margin-bottom:2rem; -webkit-overflow-scrolling:touch;
}
.foryou-strip::-webkit-scrollbar { height:4px; }
.foryou-strip::-webkit-scrollbar-track { background: var(--border); border-radius:99px; }
.foryou-strip::-webkit-scrollbar-thumb { background: var(--border-light); border-radius:99px; }

.foryou-card {
  flex: 0 0 180px; background: var(--surface);
  border: 1px solid var(--border); border-radius: var(--r-lg);
  padding:1.1rem; text-align:center; text-decoration:none;
  color: var(--text-1); transition: var(--transition);
}
.foryou-card:hover {
  border-color: var(--accent); transform:translateY(-4px);
  box-shadow: var(--shadow-glow); color: var(--text-1);
}
.foryou-avatar {
  width:54px; height:54px; border-radius: var(--r-md);
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  margin:0 auto .75rem; display:flex; align-items:center;
  justify-content:center; font-size:1.3rem; font-weight:800;
  color:#fff; overflow:hidden;
}
.foryou-avatar img { width:100%; height:100%; object-fit:cover; border-radius:inherit; }
.foryou-name { font-size:.82rem; font-weight:700; margin-bottom:.2rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.foryou-prof { font-size:.7rem; color: var(--accent); font-weight:600; margin-bottom:.4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.foryou-rating { font-size:.72rem; color: var(--amber); }
.foryou-price { font-size:.75rem; font-weight:700; color: var(--text-2); margin-top:.4rem; }

/* ── RESULTS HEADER ──────────────────────────────── */
.results-header {
  display:flex; justify-content:space-between; align-items:center;
  flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem;
}
.results-count { font-size:.85rem; color: var(--text-2); }
.results-count strong { color: var(--text-1); font-weight:700; }

.ml-status-pill {
  display:inline-flex; align-items:center; gap:.4rem;
  padding:.3rem .8rem; border-radius:100px; font-size:.72rem; font-weight:700;
}
.ml-status-pill.live { background: var(--green-dim); border:1px solid rgba(34,197,94,.3); color: var(--green); }
.ml-status-pill.heur { background: var(--amber-dim); border:1px solid rgba(245,158,11,.3); color: var(--amber); }
.ml-dot { width:6px; height:6px; border-radius:50%; }
.ml-dot.live { background: var(--green); box-shadow:0 0 6px var(--green); animation: blink 2s infinite; }
.ml-dot.heur { background: var(--amber); }
@keyframes blink { 0%,100%{opacity:1}50%{opacity:.4} }

.sort-chips { display:flex; gap:.4rem; flex-wrap:wrap; }
.sort-chip {
  padding:.35rem .8rem; border-radius:100px; font-size:.72rem; font-weight:700;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-2); cursor:pointer; transition: var(--transition);
  text-decoration:none;
}
.sort-chip:hover  { border-color: var(--accent); color: var(--accent); }
.sort-chip.active { background: var(--accent); border-color: var(--accent); color:#fff; }

/* ── PROVIDER GRID ───────────────────────────────── */
.providers-grid {
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap:1.25rem;
}

.prov-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-xl); overflow:hidden; position:relative;
  transition: var(--transition); display:flex; flex-direction:column;
}
.prov-card:hover {
  border-color: var(--accent); transform:translateY(-4px);
  box-shadow: var(--shadow-glow);
}
.prov-card.top-pick { border-color: rgba(79,140,255,.45); }
.prov-card.top-pick::before {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
  background: linear-gradient(90deg, var(--accent), #a78bfa);
}

/* card banner */
.prov-banner {
  height:100px; position:relative;
  background: linear-gradient(135deg, #1a2540 0%, #0f1829 100%);
  display:flex; align-items:flex-end; padding:.875rem 1rem;
}
.prov-banner-pattern {
  position:absolute; inset:0; opacity:.08;
  background-image: repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);
  background-size:12px 12px;
}
.prov-avatar-wrap {
  position:relative; z-index:2;
  width:60px; height:60px; border-radius: var(--r-md);
  border: 2px solid rgba(255,255,255,.12); overflow:hidden;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  display:flex; align-items:center; justify-content:center;
  font-size:1.5rem; font-weight:800; color:#fff; flex-shrink:0;
}
.prov-avatar-wrap img { width:100%; height:100%; object-fit:cover; }

.prov-banner-meta {
  position:absolute; top:.6rem; right:.75rem; z-index:2;
  display:flex; flex-direction:column; align-items:flex-end; gap:.3rem;
}
.ml-score-badge {
  background: rgba(13,110,253,.12);
  border: 1px solid rgba(13,110,253,.2);
  color: var(--accent);
  padding:.2rem .55rem; border-radius:100px;
  font-size:.65rem; font-weight:800; letter-spacing:.02em;
  display:flex; align-items:center; gap:.3rem;
}
.ml-score-badge .dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.dot-green { background: var(--green); }
.dot-blue  { background: var(--accent); }
.dot-gray  { background: var(--text-3); }

.avail-badge {
  padding:.18rem .5rem; border-radius:100px; font-size:.62rem; font-weight:700;
}
.avail-badge.available { background: var(--green-dim); color: var(--green); border:1px solid rgba(34,197,94,.3); }
.avail-badge.busy      { background: var(--amber-dim); color: var(--amber); border:1px solid rgba(245,158,11,.3); }
.avail-badge.unavailable { background: var(--red-dim); color: var(--red); border:1px solid rgba(239,68,68,.3); }

/* card body */
.prov-body { padding:1rem 1.125rem; flex:1; display:flex; flex-direction:column; }
.prov-name-row { display:flex; align-items:center; gap:.5rem; margin-bottom:.15rem; }
.prov-name { font-size:.975rem; font-weight:800; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.prov-verified { color: var(--green); font-size:.8rem; flex-shrink:0; }
.prov-profession { font-size:.75rem; color: var(--accent); font-weight:600; margin-bottom:.6rem; }

.prov-stats { display:flex; gap:.75rem; margin-bottom:.75rem; }
.prov-stat { display:flex; align-items:center; gap:.3rem; font-size:.75rem; color: var(--text-2); }
.prov-stat i { font-size:.7rem; }
.prov-stat strong { color: var(--text-1); font-weight:700; }

/* ML bar */
.ml-bar-wrap { margin-bottom:.75rem; }
.ml-bar-label {
  display:flex; justify-content:space-between; font-size:.65rem;
  color: var(--text-3); margin-bottom:.25rem;
}
.ml-bar-label span:last-child { color: var(--text-1); font-weight:700; }
.ml-bar-track {
  height:3px; background: var(--border); border-radius:99px; overflow:hidden;
}
.ml-bar-fill {
  height:100%; border-radius:99px; transition: width 1.2s cubic-bezier(.4,0,.2,1);
}
.ml-fill-high   { background: linear-gradient(90deg, var(--green), #16a34a); }
.ml-fill-medium { background: linear-gradient(90deg, var(--accent), var(--accent-2)); }
.ml-fill-low    { background: var(--text-3); }

/* personalization tags */
.pers-tags { display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.75rem; }
.pers-tag {
  padding:.18rem .55rem; border-radius:100px; font-size:.62rem; font-weight:700;
}
.pers-tag.booked   { background:rgba(34,197,94,.12); color:var(--green); border:1px solid rgba(34,197,94,.2); }
.pers-tag.fav      { background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.2); }
.pers-tag.nearby   { background:rgba(79,140,255,.12); color:var(--accent); border:1px solid rgba(79,140,255,.2); }
.pers-tag.viewed   { background:rgba(245,158,11,.12); color:var(--amber); border:1px solid rgba(245,158,11,.2); }

.prov-location { font-size:.73rem; color: var(--text-3); display:flex; align-items:center; gap:.3rem; margin-bottom:.875rem; }

/* card footer */
.prov-footer {
  border-top: 1px solid var(--border); padding:.875rem 1.125rem;
  display:flex; gap:.5rem; background: var(--bg-2);
}
.btn-view-prof {
  flex:1; background: var(--accent); color:#fff; border:none;
  padding:.55rem; border-radius: var(--r-sm); font-size:.8rem; font-weight:700;
  cursor:pointer; text-decoration:none; text-align:center;
  display:flex; align-items:center; justify-content:center; gap:.35rem;
  transition: var(--transition); font-family:inherit;
}
.btn-view-prof:hover { background: var(--accent-2); color:#fff; transform:scale(1.02); }
.btn-fav {
  width:36px; height:36px; border: 1px solid var(--border); background: transparent;
  color: var(--text-3); border-radius: var(--r-sm); cursor:pointer;
  display:flex; align-items:center; justify-content:center; font-size:.875rem;
  transition: var(--transition);
}
.btn-fav:hover          { border-color:#ef4444; color:#ef4444; }
.btn-fav.favorited      { background:#ef4444; border-color:#ef4444; color:#fff; }
.btn-fav.favorited:hover{ background:#dc2626; }

/* price chip */
.prov-price {
  font-size:.82rem; font-weight:800; color: var(--text-1);
  margin-bottom:.875rem;
}
.prov-price span { color: var(--text-3); font-size:.7rem; font-weight:400; }

/* ── EMPTY STATE ─────────────────────────────────── */
.empty-state {
  text-align:center; padding:4rem 2rem; color: var(--text-3);
  grid-column:1/-1;
}
.empty-state i { font-size:2.5rem; margin-bottom:1rem; display:block; }
.empty-state h3 { color: var(--text-2); font-size:1.1rem; margin-bottom:.4rem; }
.empty-state p  { font-size:.85rem; margin-bottom:1.25rem; }
.btn-reset {
  display:inline-flex; align-items:center; gap:.4rem;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-1); padding:.6rem 1.25rem;
  border-radius: var(--r-md); font-size:.85rem; font-weight:600;
  text-decoration:none; transition: var(--transition);
}
.btn-reset:hover { border-color: var(--accent); color: var(--accent); }

/* ── ADVANCED FILTERS DRAWER ─────────────────────── */
.adv-filters-btn {
  display:flex; align-items:center; gap:.4rem;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-2); padding:.55rem 1rem; border-radius: var(--r-md);
  font-size:.82rem; font-weight:600; cursor:pointer; transition: var(--transition);
  font-family:inherit;
}
.adv-filters-btn:hover { border-color: var(--accent); color: var(--accent); }
.adv-filters-btn .badge-count {
  background: var(--accent); color:#fff; border-radius:100px;
  padding:0 .5rem; font-size:.65rem; font-weight:800;
}

.filter-drawer {
  display:none; background: var(--bg-2); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding:1.25rem;
  margin-bottom:1.25rem; animation: slideDown .2s ease;
}
.filter-drawer.open { display:block; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.filter-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; }
.filter-label { font-size:.72rem; font-weight:700; color: var(--text-3); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; display:block; }
.filter-check { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color: var(--text-2); cursor:pointer; }
.filter-check input { accent-color: var(--accent); }
.rating-pills { display:flex; gap:.4rem; flex-wrap:wrap; }
.rating-pill {
  padding:.3rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-2); cursor:pointer; text-decoration:none; transition: var(--transition);
}
.rating-pill:hover, .rating-pill.active { background: var(--amber); border-color: var(--amber); color:#000; }

/* ── PAGINATION ──────────────────────────────────── */
.pagination-wrap { display:flex; justify-content:center; gap:.4rem; margin-top:2.5rem; flex-wrap:wrap; }
.page-btn {
  padding:.45rem .9rem; border-radius: var(--r-sm); font-size:.8rem; font-weight:700;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-2); text-decoration:none; transition: var(--transition);
  display:inline-flex; align-items:center; gap:.35rem;
}
.page-btn:hover { border-color: var(--accent); color: var(--accent); }
.page-btn.active { background: var(--accent); border-color: var(--accent); color:#fff; }

/* ── MOBILE ──────────────────────────────────────── */
.mobile-toggle {
  display:none; position:fixed; top:1rem; left:1rem; z-index:1100;
  background: var(--accent); color:#fff; border:none;
  border-radius: var(--r-sm); width:42px; height:42px;
  align-items:center; justify-content:center; cursor:pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,.4); font-size:1.1rem;
}
.overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.65); z-index:999; backdrop-filter:blur(3px);
}
.overlay.active { display:block; }

@media (max-width:992px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); box-shadow:4px 0 24px rgba(0,0,0,.4); }
  .main-content { margin-left:0; }
  .mobile-toggle { display:flex !important; }
  .search-header, .cat-bar, .page-body { padding-left:1rem; padding-right:1rem; }
  .providers-grid { grid-template-columns: repeat(auto-fill, minmax(240px,1fr)); }
}
@media (max-width:640px) {
  .providers-grid { grid-template-columns:1fr; }
  .pers-banner { flex-direction:column; gap:1rem; }
  .results-header { flex-direction:column; align-items:flex-start; }
}
</style>
</head>
<body>

<!-- Mobile toggle -->
<button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Main -->
<div class="main-content">

  <!-- ── STICKY SEARCH HEADER ───────────────────────── -->
  <div class="search-header">
    <form method="GET" action="providers.php" id="searchForm">
      <div class="search-bar-row">
        <div class="search-input-wrap">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="search-input"
                 placeholder="Search providers, professions, skills…"
                 value="<?php echo htmlspecialchars($search); ?>"
                 autocomplete="off">
        </div>
        <select name="location" class="filter-select">
          <option value="">All locations</option>
          <?php foreach ($allLocations as $loc): ?>
            <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($loc); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="avail" class="filter-select" style="min-width:140px;">
          <option value="">Any status</option>
          <option value="available" <?php echo $avail==='available'?'selected':''; ?>>Available now</option>
          <option value="busy"      <?php echo $avail==='busy'     ?'selected':''; ?>>Busy</option>
        </select>
        <!-- preserve other params -->
        <input type="hidden" name="category"   value="<?php echo htmlspecialchars($category); ?>">
        <input type="hidden" name="sort"        value="<?php echo htmlspecialchars($sort); ?>">
        <input type="hidden" name="min_rating"  value="<?php echo htmlspecialchars($minRating); ?>">
        <?php if ($verified): ?><input type="hidden" name="verified" value="1"><?php endif; ?>
        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
        <?php if ($search||$category||$location||$avail||$minRating||$verified): ?>
          <a href="providers.php" class="btn-search" style="background:var(--surface);border:1px solid var(--border);color:var(--text-2);">
            <i class="fas fa-times"></i> Clear
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── CATEGORY CHIPS ─────────────────────────────── -->
  <div class="cat-bar">
    <a href="providers.php?sort=<?php echo urlencode($sort); ?>" class="cat-chip <?php echo $category===''?'active':''; ?>">
      <i class="fas fa-globe"></i> All
    </a>
    <?php foreach ($allCats as $c):
      $ic = $catIcons[$c['cat']] ?? $catIcons['default'];
    ?>
    <a href="providers.php?category=<?php echo urlencode($c['cat']); ?>&sort=<?php echo urlencode($sort); ?><?php echo $location?'&location='.urlencode($location):''; ?>"
       class="cat-chip <?php echo $category===$c['cat']?'active':''; ?>">
      <i class="fas <?php echo $ic; ?>"></i>
      <?php echo htmlspecialchars($c['cat']); ?>
      <span style="font-weight:400;color:inherit;opacity:.6;">(<?php echo $c['cnt']; ?>)</span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ── PAGE BODY ──────────────────────────────────── -->
  <div class="page-body">

    <!-- Personalized Banner (only if client has booking history) -->
    <?php if (!empty($bookedProfessions) && empty($search) && empty($category)): ?>
    <div class="pers-banner">
      <div class="pers-banner-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
      <div style="flex:1; position:relative; z-index:2;">
        <div class="pers-banner-text">
          <h3>Personalized for <?php echo htmlspecialchars($clientName); ?></h3>
          <p>Results ranked by our ML model using your booking history, preferences, and location.</p>
        </div>
        <div style="margin-top:.6rem;">
          <?php foreach (array_keys($bookedProfessions) as $prof): ?>
            <span class="pers-pill"><i class="fas fa-history"></i> <?php echo htmlspecialchars($prof); ?></span>
          <?php endforeach; ?>
          <?php if (!empty($favIds)): ?>
            <span class="pers-pill"><i class="fas fa-heart"></i> <?php echo count($favIds); ?> Favorites</span>
          <?php endif; ?>
          <?php if ($clientLocation): ?>
            <span class="pers-pill"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($clientLocation); ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- For You Strip -->
    <?php if (!empty($forYouProviders) && empty($search) && empty($category)): ?>
    <div class="section-label"><i class="fas fa-sparkles" style="color:var(--accent);"></i> Based on your history</div>
    <div class="foryou-strip">
      <?php foreach ($forYouProviders as $fy):
        $fyInit = strtoupper(substr($fy['full_name'] ?? '', 0, 1)) ?: '?';
      ?>
      <a href="provider-profile.php?id=<?php echo $fy['id']; ?>" class="foryou-card"
         onclick="trackClick('for_you_card_click','provider',<?php echo $fy['id']; ?>)">
        <div class="foryou-avatar">
          <?php if (!empty($fy['profile_image'])): ?>
            <img src="../uploads/profiles/<?php echo htmlspecialchars($fy['profile_image']); ?>"
                 alt="" onerror="this.style.display='none';this.parentNode.textContent='<?php echo addslashes($fyInit); ?>'">
          <?php else: ?><?php echo $fyInit; ?><?php endif; ?>
        </div>
        <div class="foryou-name"><?php echo htmlspecialchars($fy['full_name']); ?></div>
        <div class="foryou-prof"><?php echo htmlspecialchars($fy['profession']); ?></div>
        <div class="foryou-rating">
          <?php for ($i=1;$i<=5;$i++) echo $i<=(int)($fy['average_rating']??0)?'★':'☆'; ?>
          <span style="color:var(--text-3);margin-left:.25rem;font-size:.68rem;">(<?php echo $fy['total_reviews']??0; ?>)</span>
        </div>
        <?php if (!empty($fy['avg_price'])): ?>
          <div class="foryou-price">~RWF <?php echo number_format((float)$fy['avg_price'], 0); ?>/service</div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Results header -->
    <div class="results-header">
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
        <span class="results-count">
          <strong><?php echo number_format($totalProviders); ?></strong>
          provider<?php echo $totalProviders!==1?'s':''; ?> found
          <?php if ($search): ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
          <?php if ($category): ?> in <strong><?php echo htmlspecialchars($category); ?></strong><?php endif; ?>
        </span>
        <?php if ($sort === 'ml'): ?>
          <span class="ml-status-pill <?php echo $mlApiStatus==='ml'?'live':'heur'; ?>">
            <span class="ml-dot <?php echo $mlApiStatus==='ml'?'live':'heur'; ?>"></span>
            <?php echo $mlApiStatus==='ml'?'ML Ranked':'Heuristic Ranked'; ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Sort chips -->
      <div class="sort-chips">
        <?php
        $sorts = ['ml'=>'✦ Smart','rating'=>'⭐ Rating','reviews'=>'💬 Reviews','newest'=>'🆕 Newest','price_asc'=>'↑ Price','price_desc'=>'↓ Price'];
        foreach ($sorts as $sv => $sl):
          $href = 'providers.php?sort='.$sv
            .($search    ? '&search='.urlencode($search)    : '')
            .($category  ? '&category='.urlencode($category): '')
            .($location  ? '&location='.urlencode($location): '')
            .($avail     ? '&avail='.urlencode($avail)      : '')
            .($minRating ? '&min_rating='.$minRating        : '')
            .($verified  ? '&verified=1'                    : '');
        ?>
        <a href="<?php echo $href; ?>" class="sort-chip <?php echo $sort===$sv?'active':''; ?>"><?php echo $sl; ?></a>
        <?php endforeach; ?>

        <!-- Advanced filters toggle -->
        <?php $activeFiltersCount = (int)($minRating>0) + (int)$verified; ?>
        <button class="adv-filters-btn" onclick="toggleDrawer()">
          <i class="fas fa-sliders-h"></i> Filters
          <?php if ($activeFiltersCount>0): ?><span class="badge-count"><?php echo $activeFiltersCount; ?></span><?php endif; ?>
        </button>
      </div>
    </div>

    <!-- Advanced Filters Drawer -->
    <div class="filter-drawer" id="filterDrawer">
      <form method="GET" action="providers.php">
        <input type="hidden" name="search"   value="<?php echo htmlspecialchars($search); ?>">
        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
        <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
        <input type="hidden" name="avail"    value="<?php echo htmlspecialchars($avail); ?>">
        <input type="hidden" name="sort"     value="<?php echo htmlspecialchars($sort); ?>">
        <div class="filter-grid">
          <div>
            <span class="filter-label">Minimum Rating</span>
            <div class="rating-pills">
              <?php foreach ([0,3,3.5,4,4.5] as $r): ?>
                <a href="providers.php?min_rating=<?php echo $r; ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&avail=<?php echo urlencode($avail); ?><?php echo $verified?'&verified=1':''; ?>"
                   class="rating-pill <?php echo $minRating==$r?'active':''; ?>">
                  <?php echo $r==0?'Any':'⭐ '.$r.'+'; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <span class="filter-label">Provider Status</span>
            <label class="filter-check" style="cursor:pointer;">
              <input type="checkbox" name="verified" value="1" <?php echo $verified?'checked':''; ?>>
              Verified providers only
            </label>
          </div>
        </div>
        <button type="submit" class="btn-search" style="margin-top:1rem;">Apply Filters</button>
      </form>
    </div>

    <!-- ── PROVIDER GRID ──────────────────────────── -->
    <div class="providers-grid">
      <?php if (empty($providers)): ?>
        <div class="empty-state">
          <i class="fas fa-user-slash"></i>
          <h3>No providers found</h3>
          <p>Try adjusting your search or removing some filters</p>
          <a href="providers.php" class="btn-reset"><i class="fas fa-redo"></i> Reset search</a>
        </div>
      <?php else: ?>
        <?php foreach ($providers as $idx => $p):
          $pid      = (int)$p['id'];
          $init     = strtoupper(substr($p['full_name'] ?? '', 0, 1)) ?: '?';
          $hasImg   = !empty($p['profile_image']);
          $mlScore  = (float)($p['ml_score'] ?? 0);
          $mlRaw    = (float)($p['ml_raw_score'] ?? 0);
          $mlConf   = $p['ml_confidence'] ?? 'n/a';
          $pBoost   = (float)($p['personal_boost'] ?? 0);
          $isTopPick= $mlScore >= 0.6;
          $isFav    = in_array($pid, $favIds);
          $isBooked = isset($bookedProfessions[$p['profession']]);
          $isNearby = ($p['provider_location'] ?? '') === $clientLocation && $clientLocation !== '';
          $isViewed = in_array($pid, $recentlyViewedIds);
          $avStatus = $p['availability'] ?? 'available';
          $isVerif  = ($p['is_verified'] ?? false) || ($p['user_verified'] ?? false);
          $rating   = (float)($p['average_rating'] ?? 0);
          $reviews  = (int)($p['total_reviews'] ?? 0);
          $avgPrice = (float)($p['avg_price'] ?? 0);
          $jobs     = (int)($p['completed_jobs'] ?? 0);

          // Dot color for ML score badge
          $dotClass = $mlScore >= 0.6 ? 'dot-green' : ($mlScore >= 0.35 ? 'dot-blue' : 'dot-gray');
          // Bar fill class
          $fillClass = $mlScore >= 0.6 ? 'ml-fill-high' : ($mlScore >= 0.35 ? 'ml-fill-medium' : 'ml-fill-low');
        ?>
        <div class="prov-card <?php echo $isTopPick ? 'top-pick' : ''; ?>"
             id="pcard-<?php echo $pid; ?>">

          <!-- Banner -->
          <div class="prov-banner">
            <div class="prov-banner-pattern"></div>
            <div class="prov-avatar-wrap">
              <?php if ($hasImg): ?>
                <img src="../uploads/profiles/<?php echo htmlspecialchars($p['profile_image']); ?>"
                     alt="<?php echo htmlspecialchars($p['full_name']); ?>"
                     onerror="this.style.display='none';this.parentNode.textContent='<?php echo addslashes($init); ?>'">
              <?php else: ?><?php echo $init; ?><?php endif; ?>
            </div>
            <div class="prov-banner-meta">
              <?php if ($sort === 'ml'): ?>
              <div class="ml-score-badge">
                <span class="dot <?php echo $dotClass; ?>"></span>
                <?php echo round($mlScore * 100); ?>% match
              </div>
              <?php endif; ?>
              <span class="avail-badge <?php echo $avStatus; ?>">
                <?php echo ucfirst($avStatus); ?>
              </span>
            </div>
          </div>

          <!-- Body -->
          <div class="prov-body">
            <div class="prov-name-row">
              <span class="prov-name"><?php echo htmlspecialchars($p['full_name']); ?></span>
              <?php if ($isVerif): ?><i class="fas fa-circle-check prov-verified" title="Verified"></i><?php endif; ?>
            </div>
            <div class="prov-profession"><?php echo htmlspecialchars($p['profession']); ?></div>

            <div class="prov-stats">
              <div class="prov-stat">
                <i class="fas fa-star" style="color:var(--amber);"></i>
                <strong><?php echo number_format($rating, 1); ?></strong>
                <span style="color:var(--text-3);">(<?php echo $reviews; ?>)</span>
              </div>
              <?php if ($jobs > 0): ?>
              <div class="prov-stat">
                <i class="fas fa-briefcase" style="color:var(--green);"></i>
                <strong><?php echo $jobs; ?></strong> done
              </div>
              <?php endif; ?>
            </div>

            <?php if ($avgPrice > 0): ?>
            <div class="prov-price">~RWF <?php echo number_format($avgPrice, 0); ?> <span>/ service</span></div>
            <?php endif; ?>

            <!-- ML probability bar (only when ML sort active) -->
            <?php if ($sort === 'ml' && $mlScore > 0): ?>
            <div class="ml-bar-wrap">
              <div class="ml-bar-label">
                <span><i class="fas fa-robot" style="font-size:.6rem;margin-right:2px;"></i>Hire probability</span>
                <span><?php echo round($mlScore * 100); ?>%</span>
              </div>
              <div class="ml-bar-track">
                <div class="ml-bar-fill <?php echo $fillClass; ?>"
                     style="width:<?php echo round($mlScore * 100); ?>%"></div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Personalization tags (client-specific — not shown the same to all) -->
            <?php if ($isFav || $isBooked || $isNearby || $isViewed): ?>
            <div class="pers-tags">
              <?php if ($isFav):   ?><span class="pers-tag fav"><i class="fas fa-heart"></i> Favorite</span><?php endif; ?>
              <?php if ($isBooked):?><span class="pers-tag booked"><i class="fas fa-check-circle"></i> You've booked</span><?php endif; ?>
              <?php if ($isNearby):?><span class="pers-tag nearby"><i class="fas fa-map-pin"></i> Near you</span><?php endif; ?>
              <?php if ($isViewed):?><span class="pers-tag viewed"><i class="fas fa-eye"></i> Viewed</span><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($p['provider_location'])): ?>
            <div class="prov-location">
              <i class="fas fa-map-marker-alt" style="color:var(--accent);font-size:.65rem;"></i>
              <?php echo htmlspecialchars($p['provider_location']); ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Footer -->
          <div class="prov-footer">
            <a href="provider-profile.php?id=<?php echo $pid; ?>" class="btn-view-prof"
               onclick="trackClick('provider_card_view','provider',<?php echo $pid; ?>)">
              <i class="fas fa-arrow-right"></i> View Profile
            </a>
            <button class="btn-fav <?php echo $isFav ? 'favorited' : ''; ?>"
                    data-provider-id="<?php echo $pid; ?>"
                    title="<?php echo $isFav ? 'Remove from favorites' : 'Add to favorites'; ?>">
              <i class="<?php echo $isFav ? 'fas' : 'far'; ?> fa-heart"></i>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── PAGINATION ─────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">
          <i class="fas fa-chevron-left"></i> Prev
        </a>
      <?php endif; ?>
      <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a class="page-btn <?php echo $i===$page?'active':''; ?>"
           href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$i])); ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a class="page-btn" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">
          Next <i class="fas fa-chevron-right"></i>
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->
</div><!-- /main-content -->

<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// ── Sidebar ──────────────────────────────────────────
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');
document.getElementById('mobileToggle')?.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('active');
});
overlay?.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('active');
});

// Sidebar collapse (desktop)
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle && sidebar) {
  const stored = localStorage.getItem('sidebarCollapsed');
  if (stored === 'true') sidebar.classList.add('collapsed');
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
  });
}

// ── Advanced filters drawer ──────────────────────────
function toggleDrawer() {
  document.getElementById('filterDrawer').classList.toggle('open');
}
// Open drawer if filters are active
<?php if ($activeFiltersCount > 0): ?>
document.getElementById('filterDrawer')?.classList.add('open');
<?php endif; ?>

// ── Favourite toggle (AJAX) ──────────────────────────
function escapeHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
          .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function showToast(msg, type='success') {
  let wrap = document.getElementById('toastWrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'toastWrap';
    wrap.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
    document.body.appendChild(wrap);
  }
  const t = document.createElement('div');
  const bg = type==='success' ? '#22c55e' : type==='error' ? '#ef4444' : '#4f8cff';
  t.style.cssText = `background:${bg};color:#fff;padding:.75rem 1.125rem;border-radius:10px;font-size:.85rem;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.4);font-family:inherit;min-width:240px;animation:toastIn .22s ease;`;
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3200);
}

document.addEventListener('DOMContentLoaded', () => {
  // Animate ML bars
  document.querySelectorAll('.ml-bar-fill').forEach(bar => {
    const w = bar.style.width; bar.style.width = '0';
    requestAnimationFrame(() => { setTimeout(() => bar.style.width = w, 80); });
  });

  // Favourite buttons
  document.querySelectorAll('.btn-fav').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      const pid = btn.dataset.providerId;
      const isFav = btn.classList.contains('favorited');
      const fd = new FormData();
      fd.append('provider_id', pid);
      fd.append(isFav ? 'remove_from_favorites' : 'add_to_favorites', '1');
      fetch('../api/toggle_favorite.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            btn.classList.toggle('favorited');
            const ico = btn.querySelector('i');
            if (btn.classList.contains('favorited')) {
              ico.className = 'fas fa-heart';
              btn.title = 'Remove from favorites';
              showToast('Added to favorites ❤️');
            } else {
              ico.className = 'far fa-heart';
              btn.title = 'Add to favorites';
              showToast('Removed from favorites', 'info');
            }
          } else {
            showToast(data.error || 'Something went wrong', 'error');
          }
        })
        .catch(() => showToast('Network error', 'error'));
    });
  });

  // Live search with debounce
  let searchDebounce;
  document.querySelector('.search-input')?.addEventListener('input', e => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
      if (e.target.value.length === 0 || e.target.value.length >= 2) {
        document.getElementById('searchForm')?.submit();
      }
    }, 600);
  });
});
</script>
</body>
</html>