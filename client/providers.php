<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/event_tracking.php';
require_once '../includes/geolocation.php';
require_once '../includes/final_ranking.php';
require_once '../controllers/pages/client/ClientProvidersController.php';
require_once '../controllers/pages/client/ProviderDiscoveryController.php';

$guestMode = !isLoggedIn();
if (!$guestMode && isProvider())  { redirect('provider/dashboard.php'); }

$db  = Database::getInstance()->getConnection();
$uid = $guestMode ? 0 : (int)$_SESSION['user_id'];

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'category' => trim($_GET['category'] ?? ''),
    'location' => trim($_GET['location'] ?? ''),
    'sort' => trim($_GET['sort'] ?? 'ml'),
    'avail' => trim($_GET['avail'] ?? ''),
    'min_rating' => (float)($_GET['min_rating'] ?? 0),
    'verified' => isset($_GET['verified']),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
];

$controller = new ClientProvidersController();
$viewData = $controller->index($db, $uid, $filters);

$platform_name = $viewData['platform_name'] ?? 'BII LocalFinder';
$clientName = $guestMode ? 'Guest' : ($viewData['client_name'] ?? 'there');
$clientLocation = $viewData['client_location'] ?? '';
$search = $viewData['search'] ?? '';
$category = $viewData['category'] ?? '';
$location = $viewData['location'] ?? '';
$sort = $viewData['sort'] ?? 'ml';
$avail = $viewData['avail'] ?? '';
$minRating = (float) ($viewData['min_rating'] ?? 0);
$verified = (bool) ($viewData['verified'] ?? false);
$page = (int) ($viewData['page'] ?? 1);
$perPage = (int) ($viewData['per_page'] ?? 12);
$offset = ($page - 1) * $perPage;
$allCats = $viewData['all_cats'] ?? [];
$allLocations = $viewData['all_locations'] ?? [];
$totalProviders = (int) ($viewData['total_providers'] ?? 0);
$totalPages = max(1, (int) ($viewData['total_pages'] ?? 1));
$providers = $viewData['providers'] ?? [];
$forYouProviders = $viewData['for_you_providers'] ?? [];
$bookedProfessions = $viewData['booked_professions'] ?? [];
$favIds = $viewData['fav_ids'] ?? [];
$recentlyViewedIds = $viewData['recently_viewed_ids'] ?? [];
$userTotalBookings = (int) ($viewData['user_total_bookings'] ?? 0);
$userAvgPrice = (float) ($viewData['user_avg_price'] ?? 0.0);
$userAvgResp = (float) ($viewData['user_avg_response_time'] ?? 24.0);
$mlApiStatus = $viewData['ml_api_status'] ?? 'fallback';
$catIcons = $viewData['cat_icons'] ?? [
    'Plumbing' => 'fa-wrench', 'Plumbers' => 'fa-wrench', 'Electrical' => 'fa-bolt', 'Electricians' => 'fa-bolt',
    'Construction' => 'fa-hard-hat', 'Carpenter' => 'fa-hammer', 'Carpenters' => 'fa-hammer',
    'Cleaner' => 'fa-broom', 'Cleaning' => 'fa-broom', 'Painter' => 'fa-paint-roller', 'Painting' => 'fa-paint-roller',
    'Gardeners' => 'fa-leaf', 'Landscaping' => 'fa-leaf', 'HVAC' => 'fa-fan', 'Roofer' => 'fa-house-damage',
    'Welders' => 'fa-fire', 'Mason' => 'fa-cubes', 'Mechanics' => 'fa-car', 'Barber' => 'fa-scissors',
    'Drivers' => 'fa-id-card', 'Tailor / Fashion Designer' => 'fa-scissors',
    'default' => 'fa-star'
];

/* ────────────────────────────────────────────────────────────────────────
   Small helper: read a system setting safely (never throws)
   ──────────────────────────────────────────────────────────────────────── */
function bii_get_setting(PDO $db, string $key, string $default = ''): string {
    try {
        $s = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $s->execute([$key]);
        $r = $s->fetchColumn();
        return ($r !== false && $r !== null && $r !== '') ? (string)$r : $default;
    } catch (Throwable $e) { return $default; }
}

$contactEmail = bii_get_setting($db, 'contact_email', 'info@biilocalfinder.com');
$contactPhone = bii_get_setting($db, 'contact_phone', '+250 788 123 456');
$platformDescription = bii_get_setting($db, 'platform_description', 'Connecting skilled professionals with clients across Rwanda');

/* ────────────────────────────────────────────────────────────────────────
   Homepage-only data: platform stats, district ledger, trending pros,
   recently-viewed (full rows), real testimonials. All wrapped so a
   missing table/column never breaks the page — it just hides that block.
   ──────────────────────────────────────────────────────────────────────── */
$platformStats = ['providers' => 0, 'categories' => 0, 'districts' => 0, 'avg_rating' => 0.0, 'verified' => 0];
try { $platformStats['providers'] = (int)$db->query("SELECT COUNT(*) FROM service_providers WHERE is_active=1 AND is_banned=0")->fetchColumn(); } catch (Throwable $e) {}
try { $platformStats['categories'] = (int)$db->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn(); } catch (Throwable $e) {}
try { $platformStats['districts'] = (int)$db->query("SELECT COUNT(DISTINCT name) FROM districts")->fetchColumn(); } catch (Throwable $e) {}
try { $platformStats['avg_rating'] = (float)$db->query("SELECT AVG(average_rating) FROM service_providers WHERE is_active=1 AND average_rating>0")->fetchColumn(); } catch (Throwable $e) {}
try { $platformStats['verified'] = (int)$db->query("SELECT COUNT(*) FROM service_providers WHERE is_active=1 AND is_banned=0 AND (is_verified=1 OR verification_level IN ('verified','gold','premium'))")->fetchColumn(); } catch (Throwable $e) {}

$districtLedger = [];
try {
    $stmt = $db->prepare("
        SELECT d.name AS district, COUNT(sp.id) AS cnt
        FROM districts d
        LEFT JOIN service_providers sp ON sp.district = d.name AND sp.is_active=1 AND sp.is_banned=0
        GROUP BY d.name
        ORDER BY cnt DESC, d.name ASC
        LIMIT 24
    ");
    $stmt->execute();
    $districtLedger = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $districtLedger = []; }

$trendingProviders = [];
try {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.profile_image, sp.location AS provider_location, u.is_verified AS user_verified,
               (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) AS avg_price,
               (SELECT COUNT(*) FROM bookings b WHERE b.provider_id=sp.id AND b.status='completed') AS completed_jobs
        FROM service_providers sp JOIN users u ON sp.user_id=u.id
        WHERE sp.is_active=1 AND sp.is_banned=0
        ORDER BY sp.average_rating DESC, sp.total_reviews DESC, sp.is_featured DESC
        LIMIT 8
    ");
    $stmt->execute();
    $trendingProviders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $trendingProviders = []; }

$recentlyViewedProviders = [];
if (!empty($recentlyViewedIds)) {
    try {
        $ids = array_slice(array_map('intval', $recentlyViewedIds), 0, 6);
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("
                SELECT sp.*, u.full_name, u.profile_image, sp.location AS provider_location, u.is_verified AS user_verified,
                       (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) AS avg_price
                FROM service_providers sp JOIN users u ON sp.user_id=u.id
                WHERE sp.id IN ($ph) AND sp.is_active=1 AND sp.is_banned=0
            ");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $byId = [];
            foreach ($rows as $r) { $byId[(int)$r['id']] = $r; }
            foreach ($ids as $rid) { if (isset($byId[$rid])) $recentlyViewedProviders[] = $byId[$rid]; }
        }
    } catch (Throwable $e) { $recentlyViewedProviders = []; }
}

$testimonials = [];
try {
    $stmt = $db->prepare("
        SELECT r.rating, r.comment, r.created_at, uc.full_name AS client_name,
               up.full_name AS provider_name, sp.profession
        FROM reviews r
        JOIN users uc ON r.client_id = uc.id
        JOIN service_providers sp ON r.provider_id = sp.id
        JOIN users up ON sp.user_id = up.id
        WHERE r.rating >= 4 AND r.comment IS NOT NULL AND TRIM(r.comment) <> ''
        ORDER BY r.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $testimonials = []; }

function buildQuery(array $params): string {
    unset($params['ajax']);
    return http_build_query($params);
}

/* ────────────────────────────────────────────────────────────────────────
   Provider card renderer — shared by the initial page render AND the
   AJAX grid refresh, so both are always visually identical.
   ──────────────────────────────────────────────────────────────────────── */
function renderProviderCardHtml(array $p, string $sort, string $clientLocation, array $bookedProfessions, array $favIds, array $recentlyViewedIds): string {
    $pid = (int)$p['id'];
    $init = strtoupper(substr($p['full_name'] ?? '', 0, 1)) ?: '?';
    $hasImg = !empty($p['profile_image']);
    $finalScore = round((float)($p['final_score'] ?? ((float)($p['ml_score'] ?? 0) * 100)), 1);
    $displayScore = $finalScore;
    $isTopPick = $displayScore >= 60;
    $isFav = in_array($pid, $favIds, true);
    $isBooked = isset($p['profession']) && isset($bookedProfessions[$p['profession']]);
    $isNearby = ($p['provider_location'] ?? '') === $clientLocation && $clientLocation !== '';
    $isViewed = in_array($pid, $recentlyViewedIds, true);
    $avStatus = strtolower($p['availability'] ?? 'available');
    $isVerif = ($p['is_verified'] ?? false) || ($p['user_verified'] ?? false);
    $rating = (float)($p['average_rating'] ?? 0);
    $reviews = (int)($p['total_reviews'] ?? 0);
    $avgPrice = (float)($p['avg_price'] ?? 0);
    $jobs = (int)($p['completed_jobs'] ?? 0);
    $dotClass = $displayScore >= 60 ? 'dot-green' : ($displayScore >= 35 ? 'dot-blue' : 'dot-gray');
    $fillClass = $displayScore >= 60 ? 'ml-fill-high' : ($displayScore >= 35 ? 'ml-fill-medium' : 'ml-fill-low');
    $loc = htmlspecialchars($p['provider_location'] ?? '');
    $name = htmlspecialchars($p['full_name'] ?? 'Provider');
    $prof = htmlspecialchars($p['profession'] ?? '');
    ob_start();
    ?>
    <div class="prov-card <?php echo $isTopPick ? 'top-pick' : ''; ?>" id="pcard-<?php echo $pid; ?>"
         data-compare-name="<?php echo $name; ?>" data-compare-prof="<?php echo $prof; ?>"
         data-compare-rating="<?php echo number_format($rating, 1); ?>" data-compare-reviews="<?php echo $reviews; ?>"
         data-compare-price="<?php echo $avgPrice > 0 ? number_format($avgPrice, 0) . ' RWF' : 'Ask for quote'; ?>"
         data-compare-jobs="<?php echo $jobs; ?>" data-compare-location="<?php echo $loc ?: 'Not specified'; ?>"
         data-compare-avail="<?php echo htmlspecialchars(ucfirst($avStatus)); ?>">

      <label class="prov-compare-check" title="Add to compare">
        <input type="checkbox" class="compare-toggle" data-pid="<?php echo $pid; ?>">
        <span><i class="fas fa-check"></i></span>
      </label>

      <div class="prov-banner">
        <div class="prov-banner-pattern"></div>
        <div class="prov-avatar-wrap">
          <?php if ($hasImg): ?>
            <img src="../uploads/profiles/<?php echo htmlspecialchars($p['profile_image']); ?>"
                 alt="<?php echo $name; ?>"
                 onerror="this.style.display='none';this.parentNode.textContent='<?php echo addslashes($init); ?>'">
          <?php else: ?><?php echo $init; ?><?php endif; ?>
        </div>
        <div class="prov-banner-meta">
          <?php if ($sort === 'ml'): ?>
          <div class="ml-score-badge">
            <span class="dot <?php echo $dotClass; ?>"></span>
            <?php echo round($displayScore); ?>% match
          </div>
          <?php endif; ?>
          <span class="avail-badge <?php echo htmlspecialchars($avStatus); ?>">
            <?php echo ucfirst(htmlspecialchars($avStatus)); ?>
          </span>
        </div>
      </div>

      <div class="prov-body">
        <div class="prov-name-row">
          <span class="prov-name"><?php echo $name; ?></span>
          <?php if ($isVerif): ?><i class="fas fa-circle-check prov-verified" title="Verified"></i><?php endif; ?>
        </div>
        <div class="prov-profession"><?php echo $prof; ?></div>

        <div class="prov-stats">
          <div class="prov-stat">
            <i class="fas fa-star"></i>
            <strong><?php echo number_format($rating, 1); ?></strong>
            <span class="dim">(<?php echo $reviews; ?>)</span>
          </div>
          <?php if ($jobs > 0): ?>
          <div class="prov-stat">
            <i class="fas fa-briefcase"></i>
            <strong><?php echo $jobs; ?></strong> done
          </div>
          <?php endif; ?>
        </div>

        <?php if ($avgPrice > 0): ?>
        <div class="prov-price">~RWF <?php echo number_format($avgPrice, 0); ?> <span>/ service</span></div>
        <?php else: ?>
        <div class="prov-price prov-price-quote">Price on request <span>· negotiable</span></div>
        <?php endif; ?>

        <?php if ($sort === 'ml' && $displayScore > 0): ?>
        <div class="ml-bar-wrap">
          <div class="ml-bar-label">
            <span><i class="fas fa-wand-magic-sparkles"></i> Smart score</span>
            <span><?php echo round($displayScore); ?>%</span>
          </div>
          <div class="ml-bar-track">
            <div class="ml-bar-fill <?php echo $fillClass; ?>" style="width:<?php echo round($displayScore); ?>%"></div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($isFav || $isBooked || $isNearby || $isViewed): ?>
        <div class="pers-tags">
          <?php if ($isFav):    ?><span class="pers-tag fav"><i class="fas fa-heart"></i> Favorite</span><?php endif; ?>
          <?php if ($isBooked): ?><span class="pers-tag booked"><i class="fas fa-check-circle"></i> Booked before</span><?php endif; ?>
          <?php if ($isNearby): ?><span class="pers-tag nearby"><i class="fas fa-map-pin"></i> Near you</span><?php endif; ?>
          <?php if ($isViewed): ?><span class="pers-tag viewed"><i class="fas fa-eye"></i> Viewed</span><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($loc): ?>
        <div class="prov-location"><i class="fas fa-location-dot"></i> <?php echo $loc; ?></div>
        <?php endif; ?>
      </div>

      <div class="prov-footer">
        <a href="provider-profile.php?id=<?php echo $pid; ?>" class="btn-view-prof"
           onclick="trackClick('provider_card_view','provider',<?php echo $pid; ?>)">
          View profile <i class="fas fa-arrow-right"></i>
        </a>
        <button class="btn-fav <?php echo $isFav ? 'favorited' : ''; ?>"
                data-provider-id="<?php echo $pid; ?>"
                title="<?php echo $isFav ? 'Remove from favorites' : 'Add to favorites'; ?>">
          <i class="<?php echo $isFav ? 'fas' : 'far'; ?> fa-heart"></i>
        </button>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderProvidersGridHtml(array $providers, string $sort, string $clientLocation, array $bookedProfessions, array $favIds, array $recentlyViewedIds): string {
    if (empty($providers)) {
        return '<div class="empty-state">'
             . '<i class="fas fa-user-magnifying-glass"></i>'
             . '<h3>No providers match yet</h3>'
             . '<p>Try a wider search, a different district, or clear a filter or two.</p>'
             . '<a href="providers.php" class="btn-reset"><i class="fas fa-rotate-left"></i> Reset search</a>'
             . '</div>';
    }
    $html = '';
    foreach ($providers as $p) {
        $html .= renderProviderCardHtml($p, $sort, $clientLocation, $bookedProfessions, $favIds, $recentlyViewedIds);
    }
    return $html;
}

function renderResultsCountHtml(int $totalProviders, string $search, string $category): string {
    $text = '<strong>' . number_format($totalProviders) . '</strong> provider' . ($totalProviders !== 1 ? 's' : '') . ' found';
    if ($search)   { $text .= ' for &ldquo;<strong>' . htmlspecialchars($search) . '</strong>&rdquo;'; }
    if ($category) { $text .= ' in <strong>' . htmlspecialchars($category) . '</strong>'; }
    return $text;
}

function renderMlStatusHtml(string $sort, string $mlApiStatus): string {
    if ($sort === 'ml') {
        $classes = $mlApiStatus === 'ml' ? 'live' : 'heur';
        $label = $mlApiStatus === 'ml' ? 'Live smart ranking' : 'Heuristic smart ranking';
        return '<span class="ml-status-pill ' . $classes . '" id="mlStatusPill"><span class="ml-dot ' . $classes . '"></span>' . $label . '</span>';
    }
    if ($sort === 'system') {
        return '<span class="ml-status-pill heur" id="mlStatusPill"><span class="ml-dot heur"></span>System ranked</span>';
    }
    return '<span id="mlStatusPill"></span>';
}

function renderPaginationHtml(int $page, int $totalPages, array $queryParams): string {
    if ($totalPages <= 1) { return ''; }
    $html = '';
    $baseParams = $queryParams;
    unset($baseParams['ajax']);
    $html .= '<div class="pagination-wrap">';
    if ($page > 1) {
        $q = buildQuery(array_merge($baseParams, ['page' => $page - 1]));
        $html .= '<a class="page-btn" href="?'.$q.'"><i class="fas fa-chevron-left"></i> Prev</a>';
    }
    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $q = buildQuery(array_merge($baseParams, ['page' => $i]));
        $active = $i === $page ? ' active' : '';
        $html .= '<a class="page-btn'.$active.'" href="?'.$q.'">'.$i.'</a>';
    }
    if ($page < $totalPages) {
        $q = buildQuery(array_merge($baseParams, ['page' => $page + 1]));
        $html .= '<a class="page-btn" href="?'.$q.'">Next <i class="fas fa-chevron-right"></i></a>';
    }
    $html .= '</div>';
    return $html;
}

$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'providers_html' => renderProvidersGridHtml($providers, $sort, $clientLocation, $bookedProfessions, $favIds, $recentlyViewedIds),
        'results_count_html' => renderResultsCountHtml($totalProviders, $search, $category),
        'ml_status_html' => renderMlStatusHtml($sort, $mlApiStatus),
        'pagination_html' => renderPaginationHtml($page, $totalPages, $_GET),
        'current_page' => $page,
        'total_pages' => $totalPages,
    ]);
    exit;
}

try { trackEvent('providers_page_view','page',0,['filters'=>compact('search','category','location','sort','avail')],$uid); } catch (Throwable $e) {}

$activeFiltersCount = (int)($minRating > 0) + (int)$verified;
$isHome = ($search === '' && $category === '' && $location === '' && $avail === '' && $minRating === 0.0 && !$verified);
$isDiscoveryView = !$guestMode
    && $search === '' && $category === '' && $location === ''
    && $avail === '' && $minRating <= 0 && !$verified && $page === 1
    && in_array($sort, ['ml', 'system'], true);

$discovery = null;
if ($isDiscoveryView) {
    $discovery = (new ProviderDiscoveryController())->index($db, $uid, [
        'location' => $clientLocation,
    ]);
}

$clientInitial = strtoupper(substr(trim((string)$clientName), 0, 1)) ?: ($guestMode ? 'G' : 'U');
$navLinks = [
    ['href' => 'home.php',         'icon' => 'fa-house',          'label' => 'Home'],
    ['href' => 'providers.php',    'icon' => 'fa-magnifying-glass','label' => 'Find providers', 'active' => true],
    ['href' => 'my-bookings.php',  'icon' => 'fa-calendar-check', 'label' => 'Bookings'],
    ['href' => 'messages.php',     'icon' => 'fa-comment-dots',   'label' => 'Messages'],
    ['href' => 'favorites.php',    'icon' => 'fa-heart',          'label' => 'Favorites'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Find trusted local pros — <?php echo htmlspecialchars($platform_name); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($platformDescription); ?>">
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/providers-discovery.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════════════════════
   BII LocalFinder — "Market Ledger" design system
   Ink-green hero · warm paper canvas · brass accent · mono data
   ══════════════════════════════════════════════════════════════ */
:root {
  --ink:        #0B1F17;
  --ink-2:      #12291F;
  --ink-3:      #1B382A;
  --paper:      #F6F3EC;
  --paper-2:    #FFFFFF;
  --card:       #FFFFFF;
  --line:       #E7E2D6;
  --line-soft:  #EFEBE0;
  --brass:      #B9822E;
  --brass-2:    #D9A64E;
  --moss:       #3F6B4A;
  --moss-2:     #2E5038;
  --clay:       #A8432E;
  --clay-dim:   rgba(168,67,46,.12);
  --green-dim:  rgba(63,107,74,.14);
  --brass-dim:  rgba(185,130,46,.16);
  --text-1:     #10201A;
  --text-2:     #5B685F;
  --text-3:     #94A092;
  --text-inv:   #F6F3EC;
  --text-inv-2: rgba(246,243,236,.66);
  --header-h:   68px;
  --r-sm: 10px; --r-md: 16px; --r-lg: 22px; --r-xl: 30px;
  --shadow-card: 0 1px 2px rgba(16,32,26,.04), 0 12px 28px rgba(16,32,26,.07);
  --shadow-pop:  0 20px 55px rgba(11,31,23,.28);
  --ease: cubic-bezier(.16,.8,.24,1);
  --font-display: 'Syne', sans-serif;
  --font-body: 'DM Sans', system-ui, sans-serif;
  --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
}
*,*::before,*::after { box-sizing: border-box; margin:0; padding:0; }
html { scroll-behavior: smooth; }
body {
  background: var(--paper);
  font-family: var(--font-body);
  color: var(--text-1);
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}
.auth-pill {
  display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .8rem; border-radius:999px; border:1px solid var(--line); color:var(--text-2); text-decoration:none; font-size:.8rem; font-weight:700; background:var(--paper-2);
}
.auth-pill.primary {
  background:var(--ink); color:var(--text-inv); border-color:var(--ink);
}
.guest-banner {
  margin-top:1rem; display:inline-flex; align-items:center; gap:.55rem; padding:.7rem .9rem; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:rgba(246,243,236,.95); font-size:.84rem; font-weight:600;
}
h1,h2,h3,h4 { font-family: var(--font-display); letter-spacing:-.01em; }
a { color: inherit; }
:focus-visible { outline: 2px solid var(--brass); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) { *,*::before,*::after { animation-duration:.001ms !important; transition-duration:.001ms !important; } }

/* ── HEADER NAVIGATION (replaces sidebar) ────────────────────── */
.main-content { min-height:100vh; }
.site-header {
  position: sticky; top:0; z-index: 1000; background: rgba(246,243,236,.86); backdrop-filter: blur(14px);
  border-bottom: 1px solid var(--line); height: var(--header-h);
}
.site-header-inner {
  max-width: 1320px; margin:0 auto; height:100%; padding: 0 2rem;
  display:flex; align-items:center; justify-content:space-between; gap:1.5rem;
}
.brand { display:flex; align-items:center; gap:.6rem; text-decoration:none; color: var(--text-1); flex-shrink:0; }
.brand-mark {
  width:36px; height:36px; border-radius:10px; background: var(--ink);
  display:flex; align-items:center; justify-content:center; color: var(--brass-2); font-size:1rem; flex-shrink:0;
}
.brand-word { font-family: var(--font-display); font-weight:800; font-size:1.05rem; line-height:1.1; }
.brand-word small { display:block; font-family: var(--font-mono); font-weight:400; font-size:.6rem; color: var(--text-3); letter-spacing:.06em; text-transform:uppercase; }

.main-nav { display:flex; align-items:center; gap:.15rem; flex:1; justify-content:center; }
.main-nav a {
  text-decoration:none; color: var(--text-2); font-size:.86rem; font-weight:600; padding:.55rem .9rem;
  border-radius: var(--r-sm); transition:.15s var(--ease); position:relative;
}
.main-nav a:hover { color: var(--text-1); background: var(--line-soft); }
.main-nav a.active { color: var(--ink); }
.main-nav a.active::after { content:''; position:absolute; left:.9rem; right:.9rem; bottom:.2rem; height:2px; background: var(--brass); border-radius:2px; }

.header-actions { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
.header-icon-btn {
  width:38px; height:38px; border-radius:10px; border:1px solid var(--line); background: var(--paper-2);
  color: var(--text-2); display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none;
  transition:.15s var(--ease); position:relative; font-size:.9rem;
}
.header-icon-btn:hover { border-color: var(--brass); color: var(--brass); }
.header-icon-btn .ping { position:absolute; top:6px; right:6px; width:7px; height:7px; border-radius:50%; background: var(--clay); border:1.5px solid var(--paper-2); }

.user-menu { position:relative; }
.user-menu-btn {
  display:flex; align-items:center; gap:.55rem; background: var(--paper-2); border:1px solid var(--line);
  border-radius: 100px; padding:.3rem .8rem .3rem .3rem; cursor:pointer; font-family:inherit; transition:.15s var(--ease);
}
.user-menu-btn:hover { border-color: var(--brass); }
.user-menu-avatar {
  width:30px; height:30px; border-radius:50%; background: linear-gradient(135deg, var(--brass), var(--brass-2));
  color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.78rem; flex-shrink:0;
}
.user-menu-name { font-size:.82rem; font-weight:700; color: var(--text-1); max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.user-menu-btn i.chev { font-size:.6rem; color: var(--text-3); transition:.15s var(--ease); }
.user-menu.open .chev { transform: rotate(180deg); }
.user-menu-dropdown {
  position:absolute; top:calc(100% + .6rem); right:0; background: var(--paper-2); border:1px solid var(--line);
  border-radius: var(--r-lg); box-shadow: var(--shadow-card); min-width:200px; padding:.5rem; display:none; z-index:1200;
}
.user-menu.open .user-menu-dropdown { display:block; animation: slideDown .16s ease; }
.user-menu-dropdown a { display:flex; align-items:center; gap:.6rem; padding:.6rem .7rem; border-radius: var(--r-sm); text-decoration:none; color: var(--text-2); font-size:.84rem; font-weight:600; transition:.15s var(--ease); }
.user-menu-dropdown a:hover { background: var(--brass-dim); color: var(--brass); }
.user-menu-dropdown a i { width:16px; text-align:center; color: var(--text-3); }
.user-menu-dropdown a:hover i { color: var(--brass); }
.user-menu-dropdown .divider { height:1px; background: var(--line-soft); margin:.4rem .2rem; }
.user-menu-dropdown a.logout { color: var(--clay); }
.user-menu-dropdown a.logout i { color: var(--clay); }

/* Mobile nav */
.mobile-nav-toggle { display:none; width:38px; height:38px; border-radius:10px; border:1px solid var(--line); background: var(--paper-2); align-items:center; justify-content:center; cursor:pointer; font-size:1rem; color: var(--text-1); }
.mobile-nav-panel {
  display:none; background: var(--paper-2); border-bottom: 1px solid var(--line); padding: .5rem 1.1rem 1rem;
}
.mobile-nav-panel.open { display:block; animation: slideDown .18s ease; }
.mobile-nav-panel a { display:flex; align-items:center; gap:.65rem; padding:.75rem .5rem; text-decoration:none; color: var(--text-2); font-size:.9rem; font-weight:600; border-bottom:1px solid var(--line-soft); }
.mobile-nav-panel a:last-child { border-bottom:none; }
.mobile-nav-panel a.active { color: var(--brass); }
.mobile-nav-panel a i { width:18px; color: var(--text-3); }

/* ── Top utility bar ───────────────────────────────────────────── */
.util-bar {
  background: var(--ink); color: var(--text-inv-2); font-family: var(--font-mono);
  font-size:.72rem; padding:.5rem 2rem; display:flex; justify-content:space-between; align-items:center;
  gap:1rem; flex-wrap:wrap;
}
.util-bar span { display:inline-flex; align-items:center; gap:.4rem; }
.util-bar .dot-live { width:6px; height:6px; border-radius:50%; background:#7CD68C; box-shadow:0 0 8px #7CD68C; animation: blink 2s infinite; }

/* ── HERO ──────────────────────────────────────────────────────── */
.hero {
  background:
    radial-gradient(560px 320px at 88% -10%, rgba(217,166,78,.16), transparent 60%),
    radial-gradient(500px 380px at 6% 110%, rgba(63,107,74,.28), transparent 65%),
    linear-gradient(165deg, var(--ink) 0%, var(--ink-2) 60%, var(--ink-3) 100%);
  color: var(--text-inv);
  padding: 3.25rem 2rem 6.5rem;
  position: relative; overflow: hidden;
}
.hero::before {
  content:''; position:absolute; inset:0; opacity:.5; pointer-events:none;
  background-image: radial-gradient(rgba(246,243,236,.09) 1px, transparent 1px);
  background-size: 22px 22px;
  mask-image: radial-gradient(circle at 75% 20%, black, transparent 70%);
}
.hero-inner { max-width: 1180px; margin: 0 auto; position:relative; z-index:2; }
.hero-eyebrow {
  display:inline-flex; align-items:center; gap:.5rem; font-family: var(--font-mono);
  font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; color: var(--brass-2);
  background: rgba(217,166,78,.1); border:1px solid rgba(217,166,78,.28);
  padding:.35rem .8rem; border-radius:100px; margin-bottom:1.4rem;
}
.hero-title {
  font-size: clamp(2.1rem, 4.4vw, 3.4rem); font-weight:800; line-height:1.08;
  max-width: 15ch; margin-bottom: 1rem;
}
.hero-title em { font-style:normal; color: var(--brass-2); }
.hero-sub { font-size:1.02rem; color: var(--text-inv-2); max-width: 46ch; line-height:1.6; margin-bottom: 2rem; }
.hero-quick-stats { display:flex; gap:1.75rem; flex-wrap:wrap; margin-bottom: 2.25rem; }
.hero-quick-stats .qs { display:flex; flex-direction:column; }
.hero-quick-stats .qs b { font-family: var(--font-mono); font-size:1.35rem; font-weight:600; color:#fff; }
.hero-quick-stats .qs span { font-size:.72rem; color: var(--text-inv-2); margin-top:.15rem; }

/* Floating search card */
.search-card {
  background: var(--paper-2); border-radius: var(--r-xl); padding: 1.35rem;
  box-shadow: var(--shadow-pop); border: 1px solid rgba(255,255,255,.06);
  position: relative; z-index: 3;
}
.search-bar-row { display:flex; gap:.65rem; flex-wrap:wrap; align-items:stretch; }
.search-input-wrap { flex: 2 1 260px; position: relative; }
.search-input-wrap i.field-icon { position:absolute; left:.9rem; top:50%; transform:translateY(-50%); color: var(--text-3); font-size:.85rem; }
.search-input {
  width:100%; background: var(--paper); border: 1px solid var(--line); color: var(--text-1);
  padding: .8rem .9rem .8rem 2.5rem; border-radius: var(--r-md); font-size:.92rem; font-family:inherit;
  transition: .15s var(--ease); outline:none;
}
.search-input::placeholder { color: var(--text-3); }
.search-input:focus { border-color: var(--brass); box-shadow: 0 0 0 3px var(--brass-dim); background:#fff; }
.filter-select {
  flex: 1 1 160px; background: var(--paper); border: 1px solid var(--line); color: var(--text-1);
  padding: .8rem 2.1rem .8rem 1rem; border-radius: var(--r-md); font-size:.85rem; font-family:inherit;
  cursor:pointer; outline:none; transition:.15s var(--ease); appearance:none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235B685F' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right .8rem center;
}
.filter-select:focus { border-color: var(--brass); }
.btn-search {
  background: var(--ink); color: var(--text-inv); border:none; padding:.8rem 1.6rem; border-radius: var(--r-md);
  font-weight:700; font-size:.88rem; cursor:pointer; transition:.15s var(--ease); font-family:inherit;
  display:flex; align-items:center; gap:.45rem; white-space:nowrap;
}
.btn-search:hover { background: var(--moss-2); transform: translateY(-1px); }
.btn-search.ghost { background: transparent; border:1px solid var(--line); color: var(--text-2); }
.btn-search.ghost:hover { border-color: var(--brass); color: var(--brass); background:transparent; }
.saved-searches { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.85rem; }
.saved-chip {
  font-family: var(--font-mono); font-size:.68rem; background: var(--paper); border:1px solid var(--line);
  color: var(--text-2); padding:.3rem .65rem; border-radius:100px; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem;
}
.saved-chip button { background:none; border:none; color: var(--text-3); cursor:pointer; font-size:.7rem; line-height:1; }
.saved-chip:hover { border-color: var(--brass); }

/* ── District ledger marquee (signature element) ─────────────── */
.ledger {
  background: var(--ink-2); border-top:1px solid rgba(255,255,255,.06); border-bottom:1px solid rgba(255,255,255,.06);
  overflow:hidden; white-space:nowrap; position:relative; z-index:2;
}
.ledger::before, .ledger::after {
  content:''; position:absolute; top:0; bottom:0; width:60px; z-index:2; pointer-events:none;
}
.ledger::before { left:0; background: linear-gradient(90deg, var(--ink-2), transparent); }
.ledger::after  { right:0; background: linear-gradient(270deg, var(--ink-2), transparent); }
.ledger-track { display:inline-flex; align-items:center; padding:.65rem 0; animation: ledgerScroll 42s linear infinite; }
.ledger:hover .ledger-track { animation-play-state: paused; }
.ledger-item {
  font-family: var(--font-mono); font-size:.74rem; color: var(--text-inv-2); padding:0 1.1rem;
  display:inline-flex; align-items:center; gap:.5rem; border-right:1px solid rgba(255,255,255,.09);
}
.ledger-item b { color: var(--brass-2); font-weight:600; }
.ledger-item .cnt { color:#fff; }
@keyframes ledgerScroll { from{ transform:translateX(0);} to{ transform:translateX(-50%);} }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

/* ── PAGE BODY ─────────────────────────────────────────────────── */
.page-body { padding: 0 2rem 3rem; max-width: 1320px; margin: 0 auto; }
.section { padding: 3rem 0; }
.section.tight { padding: 2rem 0; }
.section-head { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.section-eyebrow { font-family: var(--font-mono); font-size:.7rem; text-transform:uppercase; letter-spacing:.09em; color: var(--brass); margin-bottom:.35rem; }
.section-title { font-size:1.45rem; font-weight:800; }
.section-desc { font-size:.86rem; color: var(--text-2); margin-top:.3rem; max-width:56ch; }
.section-link { font-size:.8rem; font-weight:700; color: var(--moss-2); text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; white-space:nowrap; }
.section-link:hover { color: var(--brass); }

/* Quick action tiles */
.quick-actions { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-top: -3.5rem; position:relative; z-index:4; }
.qa-tile {
  background: var(--card); border:1px solid var(--line); border-radius: var(--r-lg); padding:1.1rem 1.15rem;
  text-decoration:none; color: var(--text-1); box-shadow: var(--shadow-card); transition:.18s var(--ease);
  display:flex; flex-direction:column; gap:.6rem;
}
.qa-tile:hover { transform: translateY(-3px); border-color: var(--brass); color: var(--text-1); }
.qa-tile .qa-icon {
  width:36px; height:36px; border-radius: var(--r-sm); display:flex; align-items:center; justify-content:center;
  background: var(--green-dim); color: var(--moss-2); font-size:.95rem;
}
.qa-tile:nth-child(2) .qa-icon { background: var(--brass-dim); color: var(--brass); }
.qa-tile:nth-child(3) .qa-icon { background: var(--clay-dim); color: var(--clay); }
.qa-tile:nth-child(4) .qa-icon { background: rgba(63,107,74,.14); color: var(--moss); }
.qa-tile h4 { font-size:.9rem; font-weight:700; }
.qa-tile p { font-size:.74rem; color: var(--text-2); line-height:1.4; }

/* Category grid */
.cat-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(150px,1fr)); gap:.9rem; }
.cat-tile {
  background: var(--card); border:1px solid var(--line); border-radius: var(--r-lg); padding:1.1rem .9rem;
  text-align:center; text-decoration:none; color: var(--text-1); transition:.18s var(--ease); position:relative;
}
.cat-tile:hover { border-color: var(--moss); transform: translateY(-3px); box-shadow: var(--shadow-card); color: var(--text-1); }
.cat-tile.active { border-color: var(--ink); background: var(--ink); color: var(--text-inv); }
.cat-tile .ci {
  width:44px; height:44px; margin:0 auto .65rem; border-radius: var(--r-md); display:flex; align-items:center; justify-content:center;
  background: var(--green-dim); color: var(--moss-2); font-size:1.1rem;
}
.cat-tile.active .ci { background: rgba(217,166,78,.18); color: var(--brass-2); }
.cat-tile .cn { font-size:.82rem; font-weight:700; }
.cat-tile .cc { font-size:.68rem; color: var(--text-3); margin-top:.15rem; font-family: var(--font-mono); }
.cat-tile.active .cc { color: var(--text-inv-2); }
.cat-tile-all { background: var(--ink); color: var(--text-inv); }
.cat-tile-all .ci { background: rgba(217,166,78,.18); color: var(--brass-2); }
.cat-tile-all .cc { color: var(--text-inv-2); }

/* Personalized banner */
.pers-banner {
  background: linear-gradient(135deg, var(--ink) 0%, var(--ink-3) 100%); color: var(--text-inv);
  border-radius: var(--r-xl); padding: 1.65rem 2rem; margin-bottom: .5rem; position:relative; overflow:hidden;
  display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;
}
.pers-banner::before { content:''; position:absolute; top:-40%; right:-5%; width:320px; height:320px; background: radial-gradient(circle, rgba(217,166,78,.16) 0%, transparent 65%); }
.pers-banner-icon {
  width:50px; height:50px; border-radius: var(--r-md); background: rgba(217,166,78,.16); border:1px solid rgba(217,166,78,.3);
  display:flex; align-items:center; justify-content:center; font-size:1.3rem; color: var(--brass-2); flex-shrink:0;
}
.pers-banner-text h3 { font-size:1.05rem; font-weight:800; margin-bottom:.2rem; }
.pers-banner-text p  { font-size:.82rem; color: var(--text-inv-2); margin:0; }
.pers-pill {
  display:inline-flex; align-items:center; gap:.35rem; background: rgba(217,166,78,.14); border:1px solid rgba(217,166,78,.28);
  color: var(--brass-2); border-radius:100px; padding:.25rem .7rem; font-size:.68rem; font-weight:700; margin:.35rem .35rem 0 0;
}

/* Horizontal scroll strips (for-you / trending / recently viewed) */
.h-strip { display:flex; gap:1rem; overflow-x:auto; padding-bottom:.75rem; -webkit-overflow-scrolling:touch; scroll-snap-type:x proximity; }
.h-strip::-webkit-scrollbar { height:5px; }
.h-strip::-webkit-scrollbar-track { background: var(--line-soft); border-radius:99px; }
.h-strip::-webkit-scrollbar-thumb { background: var(--line); border-radius:99px; }
.mini-card {
  flex: 0 0 190px; scroll-snap-align:start; background: var(--card); border:1px solid var(--line); border-radius: var(--r-lg);
  padding:1.1rem; text-align:center; text-decoration:none; color: var(--text-1); transition:.18s var(--ease);
}
.mini-card:hover { border-color: var(--brass); transform: translateY(-4px); box-shadow: var(--shadow-card); color: var(--text-1); }
.mini-avatar {
  width:52px; height:52px; border-radius: var(--r-md); background: linear-gradient(135deg, var(--moss), var(--moss-2));
  margin:0 auto .7rem; display:flex; align-items:center; justify-content:center; font-size:1.2rem; font-weight:800; color:#fff; overflow:hidden;
}
.mini-avatar img { width:100%; height:100%; object-fit:cover; }
.mini-name { font-size:.8rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mini-prof { font-size:.68rem; color: var(--moss-2); font-weight:600; margin:.2rem 0 .4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mini-rating { font-size:.7rem; color: var(--brass); }
.mini-price { font-size:.72rem; font-weight:700; color: var(--text-2); margin-top:.4rem; font-family: var(--font-mono); }

/* Stats band */
.stats-band {
  background: var(--card); border:1px solid var(--line); border-radius: var(--r-xl);
  display:grid; grid-template-columns:repeat(4,1fr); overflow:hidden;
}
.stat-cell { padding:1.6rem 1.4rem; border-right:1px solid var(--line); }
.stat-cell:last-child { border-right:none; }
.stat-cell b { font-family: var(--font-mono); font-size:2rem; font-weight:600; color: var(--ink); display:block; }
.stat-cell span { font-size:.76rem; color: var(--text-2); margin-top:.3rem; display:block; }

/* How it works */
.steps { display:grid; grid-template-columns:repeat(3,1fr); gap:1.25rem; }
.step-card { background: var(--card); border:1px solid var(--line); border-radius: var(--r-lg); padding:1.5rem; position:relative; }
.step-num { font-family: var(--font-mono); font-size:.75rem; color: var(--brass); font-weight:600; margin-bottom:.75rem; }
.step-card h4 { font-size:1rem; font-weight:800; margin-bottom:.5rem; }
.step-card p { font-size:.83rem; color: var(--text-2); line-height:1.55; }

/* Trust band */
.trust-band { display:grid; grid-template-columns: 1.1fr 1fr; gap:1.75rem; background: var(--ink); color: var(--text-inv); border-radius: var(--r-xl); padding:2.25rem; align-items:center; }
.trust-band h3 { font-size:1.3rem; font-weight:800; margin-bottom:.6rem; }
.trust-band p { font-size:.88rem; color: var(--text-inv-2); line-height:1.6; margin-bottom:1rem; }
.trust-points { list-style:none; display:flex; flex-direction:column; gap:.65rem; }
.trust-points li { display:flex; gap:.6rem; font-size:.84rem; color: var(--text-inv-2); align-items:flex-start; }
.trust-points i { color: var(--brass-2); margin-top:.2rem; flex-shrink:0; }
.trust-side { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius: var(--r-lg); padding:1.4rem; }
.trust-side-row { display:flex; justify-content:space-between; align-items:center; padding:.7rem 0; border-bottom:1px dashed rgba(255,255,255,.12); }
.trust-side-row:last-child { border-bottom:none; }
.trust-side-row span:first-child { font-size:.8rem; color: var(--text-inv-2); }
.trust-side-row b { font-family: var(--font-mono); color:#fff; }

/* Results header + provider grid (search results) */
.results-shell { background: var(--card); border:1px solid var(--line); border-radius: var(--r-xl); padding:1.75rem; }
.results-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem; }
.results-count { font-size:.85rem; color: var(--text-2); }
.results-count strong { color: var(--text-1); font-weight:700; }
.ml-status-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.3rem .8rem; border-radius:100px; font-size:.7rem; font-weight:700; font-family: var(--font-mono); }
.ml-status-pill.live { background: var(--green-dim); border:1px solid rgba(63,107,74,.35); color: var(--moss-2); }
.ml-status-pill.heur { background: var(--brass-dim); border:1px solid rgba(185,130,46,.35); color: var(--brass); }
.ml-dot { width:6px; height:6px; border-radius:50%; }
.ml-dot.live { background: var(--moss); box-shadow:0 0 6px var(--moss); animation: blink 2s infinite; }
.ml-dot.heur { background: var(--brass); }

.sort-chips { display:flex; gap:.4rem; flex-wrap:wrap; }
.sort-chip {
  padding:.4rem .85rem; border-radius:100px; font-size:.74rem; font-weight:700; border:1px solid var(--line);
  background: var(--paper); color: var(--text-2); cursor:pointer; transition:.15s var(--ease); text-decoration:none;
}
.sort-chip:hover { border-color: var(--brass); color: var(--brass); }
.sort-chip.active { background: var(--ink); border-color: var(--ink); color: var(--text-inv); }

.adv-filters-btn {
  display:flex; align-items:center; gap:.4rem; background: var(--paper); border:1px solid var(--line);
  color: var(--text-2); padding:.55rem 1rem; border-radius: var(--r-md); font-size:.8rem; font-weight:700;
  cursor:pointer; transition:.15s var(--ease); font-family:inherit;
}
.adv-filters-btn:hover { border-color: var(--brass); color: var(--brass); }
.adv-filters-btn .badge-count { background: var(--clay); color:#fff; border-radius:100px; padding:0 .5rem; font-size:.65rem; font-weight:800; }

.filter-drawer { display:none; background: var(--paper); border:1px solid var(--line); border-radius: var(--r-lg); padding:1.25rem; margin-bottom:1.25rem; animation: slideDown .2s ease; }
.filter-drawer.open { display:block; }
@keyframes slideDown { from{opacity:0; transform:translateY(-8px)} to{opacity:1; transform:translateY(0)} }
.filter-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; }
.filter-label { font-size:.7rem; font-weight:700; color: var(--text-3); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.45rem; display:block; }
.filter-check { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color: var(--text-2); cursor:pointer; }
.filter-check input { accent-color: var(--brass); }
.rating-pills { display:flex; gap:.4rem; flex-wrap:wrap; }
.rating-pill { padding:.3rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700; border:1px solid var(--line); background:#fff; color: var(--text-2); cursor:pointer; text-decoration:none; transition:.15s var(--ease); }
.rating-pill:hover, .rating-pill.active { background: var(--brass); border-color: var(--brass); color:#fff; }

.providers-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:1.15rem; }

.prov-card { background: var(--card); border:1px solid var(--line); border-radius: var(--r-xl); overflow:hidden; position:relative; transition:.18s var(--ease); display:flex; flex-direction:column; }
.prov-card:hover { border-color: var(--brass); transform: translateY(-4px); box-shadow: var(--shadow-card); }
.prov-card.top-pick { border-color: rgba(217,166,78,.55); }
.prov-card.top-pick::after { content:''; position:absolute; top:0; left:0; right:0; height:3px; background: linear-gradient(90deg, var(--brass), var(--moss)); z-index:3; }

.prov-compare-check { position:absolute; top:.7rem; left:.7rem; z-index:5; cursor:pointer; }
.prov-compare-check input { position:absolute; opacity:0; width:1px; height:1px; }
.prov-compare-check span {
  width:22px; height:22px; border-radius:6px; background: rgba(11,31,23,.35); border:1px solid rgba(255,255,255,.4);
  display:flex; align-items:center; justify-content:center; color:transparent; font-size:.6rem; transition:.15s;
  backdrop-filter: blur(2px);
}
.prov-compare-check input:checked + span { background: var(--brass); border-color: var(--brass); color:#fff; }

.prov-banner { height:96px; position:relative; background: linear-gradient(135deg, var(--ink) 0%, var(--ink-3) 100%); display:flex; align-items:flex-end; padding:.85rem 1rem; }
.prov-banner-pattern { position:absolute; inset:0; opacity:.08; background-image: repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%); background-size:12px 12px; }
.prov-avatar-wrap {
  position:relative; z-index:2; width:58px; height:58px; border-radius: var(--r-md); border:2px solid rgba(255,255,255,.14);
  overflow:hidden; background: linear-gradient(135deg, var(--brass), var(--brass-2)); display:flex; align-items:center; justify-content:center;
  font-size:1.45rem; font-weight:800; color:#fff; flex-shrink:0;
}
.prov-avatar-wrap img { width:100%; height:100%; object-fit:cover; }
.prov-banner-meta { position:absolute; top:.6rem; right:.75rem; z-index:2; display:flex; flex-direction:column; align-items:flex-end; gap:.3rem; }
.ml-score-badge { background: rgba(217,166,78,.16); border:1px solid rgba(217,166,78,.3); color: var(--brass-2); padding:.2rem .55rem; border-radius:100px; font-size:.63rem; font-weight:800; font-family: var(--font-mono); display:flex; align-items:center; gap:.3rem; }
.ml-score-badge .dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.dot-green { background:#6fcf87; } .dot-blue { background: var(--brass-2); } .dot-gray { background: rgba(255,255,255,.4); }
.avail-badge { padding:.18rem .5rem; border-radius:100px; font-size:.62rem; font-weight:700; }
.avail-badge.available { background: rgba(111,207,135,.18); color:#8fe3a3; border:1px solid rgba(111,207,135,.35); }
.avail-badge.busy { background: rgba(217,166,78,.2); color: var(--brass-2); border:1px solid rgba(217,166,78,.35); }
.avail-badge.unavailable { background: rgba(168,67,46,.22); color:#e4a291; border:1px solid rgba(168,67,46,.4); }

.prov-body { padding:1rem 1.1rem; flex:1; display:flex; flex-direction:column; }
.prov-name-row { display:flex; align-items:center; gap:.5rem; margin-bottom:.15rem; }
.prov-name { font-size:.96rem; font-weight:800; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.prov-verified { color: var(--moss); font-size:.8rem; flex-shrink:0; }
.prov-profession { font-size:.74rem; color: var(--moss-2); font-weight:700; margin-bottom:.6rem; }
.prov-stats { display:flex; gap:.85rem; margin-bottom:.7rem; }
.prov-stat { display:flex; align-items:center; gap:.3rem; font-size:.74rem; color: var(--text-2); }
.prov-stat i { color: var(--brass); font-size:.7rem; }
.prov-stat strong { color: var(--text-1); font-weight:700; }
.prov-stat .dim { color: var(--text-3); }
.ml-bar-wrap { margin-bottom:.75rem; }
.ml-bar-label { display:flex; justify-content:space-between; font-size:.64rem; color: var(--text-3); margin-bottom:.25rem; font-family: var(--font-mono); }
.ml-bar-label span:last-child { color: var(--text-1); font-weight:700; }
.ml-bar-track { height:4px; background: var(--line); border-radius:99px; overflow:hidden; }
.ml-bar-fill { height:100%; border-radius:99px; transition: width 1.1s var(--ease); }
.ml-fill-high { background: linear-gradient(90deg, var(--moss), var(--moss-2)); }
.ml-fill-medium { background: linear-gradient(90deg, var(--brass), var(--brass-2)); }
.ml-fill-low { background: var(--text-3); }
.pers-tags { display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.7rem; }
.pers-tag { padding:.18rem .55rem; border-radius:100px; font-size:.62rem; font-weight:700; }
.pers-tag.booked { background: var(--green-dim); color: var(--moss-2); border:1px solid rgba(63,107,74,.25); }
.pers-tag.fav { background: var(--clay-dim); color: var(--clay); border:1px solid rgba(168,67,46,.25); }
.pers-tag.nearby { background: var(--brass-dim); color: var(--brass); border:1px solid rgba(185,130,46,.25); }
.pers-tag.viewed { background: rgba(91,104,95,.12); color: var(--text-2); border:1px solid rgba(91,104,95,.2); }
.prov-location { font-size:.72rem; color: var(--text-3); display:flex; align-items:center; gap:.35rem; margin-bottom:.85rem; }
.prov-price { font-size:.85rem; font-weight:800; color: var(--text-1); margin-bottom:.85rem; font-family: var(--font-mono); }
.prov-price span { color: var(--text-3); font-size:.68rem; font-weight:400; font-family: var(--font-body); }
.prov-price-quote { color: var(--text-2); font-size:.76rem; }

.prov-footer { border-top:1px solid var(--line); padding:.85rem 1.1rem; display:flex; gap:.5rem; background: var(--paper); }
.btn-view-prof { flex:1; background: var(--ink); color: var(--text-inv); border:none; padding:.55rem; border-radius: var(--r-sm); font-size:.8rem; font-weight:700; cursor:pointer; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center; gap:.35rem; transition:.15s var(--ease); font-family:inherit; }
.btn-view-prof:hover { background: var(--moss-2); color: var(--text-inv); transform: scale(1.02); }
.btn-fav { width:36px; height:36px; border:1px solid var(--line); background: transparent; color: var(--text-3); border-radius: var(--r-sm); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.875rem; transition:.15s var(--ease); }
.btn-fav:hover { border-color: var(--clay); color: var(--clay); }
.btn-fav.favorited { background: var(--clay); border-color: var(--clay); color:#fff; }
.btn-fav.favorited:hover { background:#8f3826; }

.empty-state { text-align:center; padding:4rem 2rem; color: var(--text-3); grid-column:1/-1; }
.empty-state i { font-size:2.5rem; margin-bottom:1rem; display:block; color: var(--line); }
.empty-state h3 { color: var(--text-2); font-size:1.1rem; margin-bottom:.4rem; font-family: var(--font-display); }
.empty-state p { font-size:.85rem; margin-bottom:1.25rem; }
.btn-reset { display:inline-flex; align-items:center; gap:.4rem; background: var(--paper); border:1px solid var(--line); color: var(--text-1); padding:.6rem 1.25rem; border-radius: var(--r-md); font-size:.85rem; font-weight:600; text-decoration:none; transition:.15s var(--ease); }
.btn-reset:hover { border-color: var(--brass); color: var(--brass); }

.pagination-wrap { display:flex; justify-content:center; gap:.4rem; margin-top:2rem; flex-wrap:wrap; }
.page-btn { padding:.45rem .9rem; border-radius: var(--r-sm); font-size:.8rem; font-weight:700; border:1px solid var(--line); background: var(--paper); color: var(--text-2); text-decoration:none; transition:.15s var(--ease); display:inline-flex; align-items:center; gap:.35rem; }
.page-btn:hover { border-color: var(--brass); color: var(--brass); }
.page-btn.active { background: var(--ink); border-color: var(--ink); color: var(--text-inv); }

/* Testimonials */
.testi-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:1.1rem; }
.testi-card { background: var(--card); border:1px solid var(--line); border-radius: var(--r-lg); padding:1.4rem; }
.testi-stars { color: var(--brass); font-size:.8rem; margin-bottom:.6rem; }
.testi-text { font-size:.86rem; color: var(--text-1); line-height:1.6; margin-bottom:1rem; }
.testi-who { display:flex; justify-content:space-between; align-items:center; font-size:.75rem; color: var(--text-2); }
.testi-who b { color: var(--text-1); }

/* Become-a-pro CTA */
.pro-cta { background: linear-gradient(120deg, var(--moss) 0%, var(--moss-2) 100%); color:#fff; border-radius: var(--r-xl); padding:2.5rem; display:flex; align-items:center; justify-content:space-between; gap:1.5rem; flex-wrap:wrap; }
.pro-cta h3 { font-size:1.4rem; font-weight:800; margin-bottom:.5rem; }
.pro-cta p { font-size:.88rem; color: rgba(255,255,255,.82); max-width:44ch; }
.pro-cta .btn-search { background:#fff; color: var(--moss-2); }
.pro-cta .btn-search:hover { background: var(--brass-2); color:#fff; }

/* Footer */
.site-footer { background: var(--ink); color: var(--text-inv-2); padding:3rem 2rem 1.5rem; margin-top:2rem; }
.footer-inner { max-width:1180px; margin:0 auto; display:grid; grid-template-columns:1.4fr 1fr 1fr 1fr; gap:2rem; }
.footer-brand h4 { font-family: var(--font-display); font-size:1.15rem; color:#fff; margin-bottom:.6rem; }
.footer-brand p { font-size:.82rem; line-height:1.6; max-width:34ch; }
.footer-col h5 { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color: var(--brass-2); margin-bottom:.85rem; }
.footer-col ul { list-style:none; display:flex; flex-direction:column; gap:.55rem; }
.footer-col a { color: var(--text-inv-2); text-decoration:none; font-size:.84rem; }
.footer-col a:hover { color: var(--brass-2); }
.footer-bottom { max-width:1180px; margin:2rem auto 0; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,.09); display:flex; justify-content:space-between; flex-wrap:wrap; gap:.75rem; font-size:.75rem; }

/* Compare bar + modal */
.compare-bar {
  position:fixed; left:50%; bottom:1.25rem; transform: translateX(-50%) translateY(120%); z-index:1200;
  background: var(--ink); color: var(--text-inv); border-radius: var(--r-lg); padding:.85rem 1.1rem;
  display:flex; align-items:center; gap:1rem; box-shadow: var(--shadow-pop); transition: transform .25s var(--ease);
}
.compare-bar.show { transform: translateX(-50%) translateY(0); }
.compare-bar span { font-size:.82rem; font-family: var(--font-mono); }
.compare-bar button { border:none; border-radius: var(--r-sm); padding:.5rem .9rem; font-size:.78rem; font-weight:700; cursor:pointer; font-family:inherit; }
.compare-bar .btn-do { background: var(--brass); color:#fff; }
.compare-bar .btn-clear { background: transparent; color: var(--text-inv-2); border:1px solid rgba(255,255,255,.2) !important; }
.compare-modal-overlay { display:none; position:fixed; inset:0; background: rgba(11,31,23,.6); backdrop-filter: blur(3px); z-index:1300; align-items:center; justify-content:center; padding:1.5rem; }
.compare-modal-overlay.open { display:flex; }
.compare-modal { background:#fff; border-radius: var(--r-xl); max-width:840px; width:100%; max-height:82vh; overflow:auto; padding:1.75rem; }
.compare-modal h3 { font-size:1.2rem; margin-bottom:1rem; }
.compare-modal table { width:100%; border-collapse:collapse; font-size:.83rem; }
.compare-modal th, .compare-modal td { padding:.65rem .5rem; border-bottom:1px solid var(--line); text-align:left; }
.compare-modal th { color: var(--text-3); font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; }
.compare-modal-close { float:right; background:none; border:none; font-size:1.1rem; cursor:pointer; color: var(--text-3); }

/* Toasts */
#toastWrap { position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:.5rem; }
@keyframes toastIn { from{opacity:0; transform:translateY(10px)} to{opacity:1; transform:translateY(0)} }

/* Mobile */
@media (max-width: 900px) {
  .main-nav { display:none; }
  .mobile-nav-toggle { display:flex; }
  .user-menu-name { display:none; }
  .site-header-inner { padding: 0 1.1rem; }
  .hero, .page-body, .util-bar { padding-left:1.1rem; padding-right:1.1rem; }
  .quick-actions { grid-template-columns: repeat(2,1fr); margin-top:1.25rem; }
  .stats-band { grid-template-columns: repeat(2,1fr); }
  .stat-cell:nth-child(2) { border-right:none; }
  .steps { grid-template-columns:1fr; }
  .trust-band { grid-template-columns:1fr; }
  .footer-inner { grid-template-columns:1fr 1fr; }
}
@media (max-width: 768px) {
  .providers-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .prov-card {
    border: none;
    border-bottom: 1px solid var(--line);
    border-radius: 0;
    box-shadow: none;
    background: transparent;
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 0.9rem;
    padding: 1rem 0;
    margin: 0;
    min-height: auto;
  }
  .prov-card:hover { transform: none; }
  .prov-card.top-pick::after { height: 0; }

  .prov-banner {
    position: static;
    height: auto;
    background: transparent;
    padding: 0;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
  }
  .prov-banner-pattern { display: none; }
  .prov-avatar-wrap {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    border: 1px solid rgba(16,32,26,.08);
    background: linear-gradient(135deg, var(--brass), var(--brass-2));
    box-shadow: 0 10px 24px rgba(16,32,26,.08);
  }

  .prov-banner-meta {
    position: static;
    align-items: flex-start;
    justify-content: flex-start;
    width: 100%;
    gap: 0.45rem;
  }
  .ml-score-badge {
    padding: .3rem .65rem;
    font-size: .72rem;
  }
  .avail-badge {
    font-size: .7rem;
    padding: .25rem .65rem;
  }

  .prov-body {
    padding: 0;
  }
  .prov-name-row { gap: .35rem; }
  .prov-name { font-size: 1rem; white-space: normal; }
  .prov-profession { font-size:.78rem; margin-bottom:.5rem; }
  .prov-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap:.75rem;
    margin-bottom:.6rem;
  }
  .prov-stat { gap:.35rem; font-size:.78rem; }
  .prov-price { font-size:.82rem; margin-bottom:.6rem; }
  .ml-bar-wrap,
  .pers-tags { display:none; }
  .prov-location { font-size:.72rem; margin-bottom:.6rem; }

  .prov-footer {
    border-top:none;
    padding-top:0;
    display:flex;
    flex-direction:column;
    gap:.75rem;
    background: transparent;
  }
  .btn-view-prof {
    width:100%;
    padding:.85rem 1rem;
    border-radius: var(--r-md);
    font-size:.9rem;
  }
  .btn-fav {
    width: 100%;
    max-width: none;
    border-radius: var(--r-md);
    justify-content: flex-start;
    padding:.75rem 1rem;
    background: rgba(255,255,255,.92);
    border:1px solid var(--line);
  }
  .btn-fav i { margin-right:.55rem; }
  .prov-compare-check { display: none; }
}

@media (max-width: 640px) {
  .results-header { flex-direction:column; align-items:flex-start; gap:1rem; }
  .sort-chips { justify-content:flex-start; }
  .providers-grid { gap: .85rem; }
  .prov-card { grid-template-columns: 60px minmax(0, 1fr); gap:.75rem; }
  .prov-avatar-wrap { width: 52px; height: 52px; }
  .prov-name { font-size: .98rem; }
  .prov-profession { font-size:.76rem; }
  .prov-stat { font-size:.76rem; }
  .btn-view-prof, .btn-fav { font-size:.85rem; padding:.75rem; }
  .filter-drawer { padding:1rem; }
  .adv-filters-btn { width:100%; justify-content:center; }
  .quick-actions { grid-template-columns:1fr; margin-top:1rem; }
  .stats-band { grid-template-columns:1fr; }
  .stat-cell { border-right:none; border-bottom:1px solid var(--line); }
  .footer-inner { grid-template-columns:1fr; }
  .hero { padding-bottom: 8.5rem; }
  .search-bar-row { flex-direction:column; }
}
</style>
</head>
<body data-guest-mode="<?php echo $guestMode ? '1' : '0'; ?>">

<div class="main-content">

  <header class="site-header">
    <div class="site-header-inner">
      <a href="home.php" class="brand">
        <span class="brand-mark"><i class="fas fa-map-location-dot"></i></span>
        <span class="brand-word"><?php echo htmlspecialchars($platform_name); ?><small>Rwanda · local services</small></span>
      </a>

      <nav class="main-nav">
        <?php foreach ($navLinks as $nl): ?>
          <a href="<?php echo htmlspecialchars($nl['href']); ?>" class="<?php echo !empty($nl['active']) ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($nl['label']); ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="header-actions">
        <?php if ($guestMode): ?>
          <a href="../login.php" class="auth-pill"><i class="fas fa-right-to-bracket"></i> Sign in</a>
          <a href="../register.php" class="auth-pill primary"><i class="fas fa-user-plus"></i> Join now</a>
        <?php else: ?>
          <a href="favorites.php" class="header-icon-btn" title="Favorites"><i class="fas fa-heart"></i></a>
          <a href="messages.php" class="header-icon-btn" title="Messages"><i class="fas fa-comment-dots"></i></a>
          <a href="notifications.php" class="header-icon-btn" title="Notifications"><i class="fas fa-bell"></i><span class="ping"></span></a>

          <div class="user-menu" id="userMenu">
            <button class="user-menu-btn" id="userMenuBtn" type="button">
              <span class="user-menu-avatar"><?php echo htmlspecialchars($clientInitial); ?></span>
              <span class="user-menu-name"><?php echo htmlspecialchars($clientName); ?></span>
              <i class="fas fa-chevron-down chev"></i>
            </button>
            <div class="user-menu-dropdown">
              <a href="profile.php"><i class="fas fa-user"></i> My profile</a>
              <a href="my-bookings.php"><i class="fas fa-calendar-check"></i> My bookings</a>
              <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
              <div class="divider"></div>
              <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
            </div>
          </div>
        <?php endif; ?>

        <button class="mobile-nav-toggle" id="mobileNavToggle" type="button"><i class="fas fa-bars"></i></button>
      </div>
    </div>

    <nav class="mobile-nav-panel" id="mobileNavPanel">
      <?php foreach ($navLinks as $nl): ?>
        <a href="<?php echo htmlspecialchars($nl['href']); ?>" class="<?php echo !empty($nl['active']) ? 'active' : ''; ?>">
          <i class="fas <?php echo htmlspecialchars($nl['icon']); ?>"></i> <?php echo htmlspecialchars($nl['label']); ?>
        </a>
      <?php endforeach; ?>
      <?php if ($guestMode): ?>
        <a href="../login.php"><i class="fas fa-right-to-bracket"></i> Sign in</a>
        <a href="../register.php"><i class="fas fa-user-plus"></i> Join now</a>
      <?php else: ?>
        <a href="profile.php"><i class="fas fa-user"></i> My profile</a>
        <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
        <a href="../logout.php" style="color:var(--clay);"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
      <?php endif; ?>
    </nav>
  </header>

  <div class="util-bar">
    <span><span class="dot-live"></span> <?php echo $platformStats['providers']; ?> active pros online across <?php echo $platformStats['districts'] ?: 30; ?> districts</span>
    <span><i class="fas fa-clock"></i> <?php echo date('D, j M · H:i'); ?>, Kigali time</span>
  </div>

  <!-- ══════════════ HERO ══════════════ -->
  <section class="hero">
    <div class="hero-inner">
      <div class="hero-eyebrow"><i class="fas fa-map-location-dot"></i> <?php echo htmlspecialchars($platform_name); ?> · all 30 districts of Rwanda</div>
      <h1 class="hero-title">Find someone who <em>actually shows up.</em></h1>
      <p class="hero-sub">
        Welcome, <?php echo htmlspecialchars($clientName); ?> — compare verified drivers, electricians, cleaners and more near
        <?php echo $clientLocation ? htmlspecialchars($clientLocation) : 'you'; ?>, negotiate a fair price, and book with confidence.
      </p>
      <?php if ($guestMode): ?>
      <div class="guest-banner"><i class="fas fa-circle-info"></i> You’re browsing as a guest. Create an account to save favorites, message providers, and book services.</div>
      <?php endif; ?>

      <div class="hero-quick-stats">
        <div class="qs"><b><?php echo number_format($platformStats['providers']); ?></b><span>Active providers</span></div>
        <div class="qs"><b><?php echo number_format($platformStats['categories'] ?: count($allCats)); ?></b><span>Service categories</span></div>
        <div class="qs"><b><?php echo number_format($platformStats['verified']); ?></b><span>Verified pros</span></div>
        <div class="qs"><b><?php echo $platformStats['avg_rating'] > 0 ? number_format($platformStats['avg_rating'],1) : '—'; ?></b><span>Average rating</span></div>
      </div>

      <div class="search-card">
        <form method="GET" action="providers.php" id="searchForm">
          <div class="search-bar-row">
            <div class="search-input-wrap">
              <i class="fas fa-magnifying-glass field-icon"></i>
              <input type="text" name="search" class="search-input"
                     placeholder="A driver, a plumber, a cleaner…"
                     value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            </div>
            <select name="location" class="filter-select">
              <option value="">All locations</option>
              <?php foreach ($allLocations as $loc): ?>
                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($loc); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select name="avail" class="filter-select" style="flex:0 1 150px;">
              <option value="">Any status</option>
              <option value="available" <?php echo $avail==='available'?'selected':''; ?>>Available now</option>
              <option value="busy" <?php echo $avail==='busy'?'selected':''; ?>>Busy</option>
            </select>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="hidden" name="min_rating" value="<?php echo htmlspecialchars((string)$minRating); ?>">
            <?php if ($verified): ?><input type="hidden" name="verified" value="1"><?php endif; ?>
            <button type="submit" class="btn-search"><i class="fas fa-magnifying-glass"></i> Search</button>
            <?php if ($search||$category||$location||$avail||$minRating||$verified): ?>
              <a href="providers.php" class="btn-search ghost"><i class="fas fa-xmark"></i> Clear</a>
            <?php endif; ?>
          </div>
        </form>
        <div class="saved-searches" id="savedSearches" style="display:none;"></div>
      </div>
    </div>
  </section>

  <!-- ══════════════ DISTRICT LEDGER (signature) ══════════════ -->
  <?php if (!empty($districtLedger)): ?>
  <div class="ledger" aria-label="Providers by district">
    <div class="ledger-track">
      <?php for ($rep = 0; $rep < 2; $rep++): foreach ($districtLedger as $d): ?>
        <a class="ledger-item" href="providers.php?location=<?php echo urlencode($d['district']); ?>">
          <b><?php echo htmlspecialchars($d['district']); ?></b>
          <span class="cnt"><?php echo (int)$d['cnt']; ?> pro<?php echo (int)$d['cnt']===1?'':'s'; ?></span>
        </a>
      <?php endforeach; endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="page-body">

    <!-- ══════════════ QUICK ACTIONS ══════════════ -->
    <div class="quick-actions">
      <a class="qa-tile" href="providers.php?avail=available&sort=ml">
        <div class="qa-icon"><i class="fas fa-bolt"></i></div>
        <h4>Available now</h4>
        <p>Only pros marked free today — for when it can't wait.</p>
      </a>
      <a class="qa-tile" href="providers.php?verified=1&sort=rating">
        <div class="qa-icon"><i class="fas fa-shield-check"></i></div>
        <h4>Verified pros</h4>
        <p>ID-checked and document-reviewed by our team.</p>
      </a>
      <a class="qa-tile" href="providers.php?sort=rating&min_rating=4.5">
        <div class="qa-icon"><i class="fas fa-star"></i></div>
        <h4>Top rated (4.5★+)</h4>
        <p>Consistently excellent reviews from real clients.</p>
      </a>
      <a class="qa-tile" href="providers.php?sort=newest">
        <div class="qa-icon"><i class="fas fa-sparkles"></i></div>
        <h4>Newly joined</h4>
        <p>Fresh talent that just completed onboarding.</p>
      </a>
    </div>

    <!-- ══════════════ CATEGORIES ══════════════ -->
    <section class="section tight" id="categories">
      <div class="section-head">
        <div>
          <div class="section-eyebrow">Browse</div>
          <h2 class="section-title">What do you need done?</h2>
        </div>
      </div>
      <div class="cat-grid">
        <a href="providers.php?sort=<?php echo urlencode($sort); ?>" class="cat-tile <?php echo $category==='' ? 'cat-tile-all' : ''; ?>">
          <div class="ci"><i class="fas fa-grip"></i></div>
          <div class="cn">All services</div>
          <div class="cc"><?php echo number_format($platformStats['providers']); ?> pros</div>
        </a>
        <?php foreach ($allCats as $c):
          $ic = $catIcons[$c['cat']] ?? $catIcons['default'];
        ?>
        <a href="providers.php?category=<?php echo urlencode($c['cat']); ?>&sort=<?php echo urlencode($sort); ?><?php echo $location?'&location='.urlencode($location):''; ?>"
           class="cat-tile <?php echo $category===$c['cat'] ? 'active' : ''; ?>">
          <div class="ci"><i class="fas <?php echo $ic; ?>"></i></div>
          <div class="cn"><?php echo htmlspecialchars($c['cat']); ?></div>
          <div class="cc"><?php echo (int)$c['cnt']; ?> pros</div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ══════════════ PERSONALIZED BANNER + FOR YOU ══════════════ -->
    <?php if ($isHome && !empty($bookedProfessions)): ?>
    <section class="section tight">
      <div class="pers-banner">
        <div class="pers-banner-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
        <div style="flex:1; position:relative; z-index:2;">
          <div class="pers-banner-text">
            <h3>Picked for <?php echo htmlspecialchars($clientName); ?></h3>
            <p>Ranked using your booking history, favorites, and location by our smart-match model.</p>
          </div>
          <div style="margin-top:.6rem;">
            <?php foreach (array_keys($bookedProfessions) as $prof): ?>
              <span class="pers-pill"><i class="fas fa-clock-rotate-left"></i> <?php echo htmlspecialchars($prof); ?></span>
            <?php endforeach; ?>
            <?php if (!empty($favIds)): ?><span class="pers-pill"><i class="fas fa-heart"></i> <?php echo count($favIds); ?> favorites</span><?php endif; ?>
            <?php if ($clientLocation): ?><span class="pers-pill"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($clientLocation); ?></span><?php endif; ?>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($isHome && !empty($forYouProviders)): ?>
    <section class="section tight">
      <div class="section-head">
        <div><div class="section-eyebrow">For you</div><h2 class="section-title">Based on your history</h2></div>
      </div>
      <div class="h-strip">
        <?php foreach ($forYouProviders as $fy):
          $fyInit = strtoupper(substr($fy['full_name'] ?? '', 0, 1)) ?: '?';
        ?>
        <a href="provider-profile.php?id=<?php echo $fy['id']; ?>" class="mini-card"
           onclick="trackClick('for_you_card_click','provider',<?php echo $fy['id']; ?>)">
          <div class="mini-avatar">
            <?php if (!empty($fy['profile_image'])): ?>
              <img src="../uploads/profiles/<?php echo htmlspecialchars($fy['profile_image']); ?>" alt=""
                   onerror="this.style.display='none';this.parentNode.textContent='<?php echo addslashes($fyInit); ?>'">
            <?php else: ?><?php echo $fyInit; ?><?php endif; ?>
          </div>
          <div class="mini-name"><?php echo htmlspecialchars($fy['full_name']); ?></div>
          <div class="mini-prof"><?php echo htmlspecialchars($fy['profession']); ?></div>
          <div class="mini-rating">
            <?php for ($i=1;$i<=5;$i++) echo $i<=(int)round($fy['average_rating']??0)?'★':'☆'; ?>
            <span style="color:var(--text-3);margin-left:.2rem;">(<?php echo $fy['total_reviews']??0; ?>)</span>
          </div>
          <?php if (!empty($fy['avg_price'])): ?><div class="mini-price">~<?php echo number_format((float)$fy['avg_price'],0); ?> RWF</div><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════ RECENTLY VIEWED ══════════════ -->
    <?php if ($isHome && !empty($recentlyViewedProviders)): ?>
    <section class="section tight">
      <div class="section-head">
        <div><div class="section-eyebrow">Pick up where you left off</div><h2 class="section-title">Recently viewed</h2></div>
      </div>
      <div class="h-strip">
        <?php foreach ($recentlyViewedProviders as $rv):
          $rvInit = strtoupper(substr($rv['full_name'] ?? '', 0, 1)) ?: '?';
        ?>
        <a href="provider-profile.php?id=<?php echo $rv['id']; ?>" class="mini-card">
          <div class="mini-avatar">
            <?php if (!empty($rv['profile_image'])): ?>
              <img src="../uploads/profiles/<?php echo htmlspecialchars($rv['profile_image']); ?>" alt=""
                   onerror="this.style.display='none';this.parentNode.textContent='<?php echo addslashes($rvInit); ?>'">
            <?php else: ?><?php echo $rvInit; ?><?php endif; ?>
          </div>
          <div class="mini-name"><?php echo htmlspecialchars($rv['full_name']); ?></div>
          <div class="mini-prof"><?php echo htmlspecialchars($rv['profession']); ?></div>
          <div class="mini-rating">
            <?php for ($i=1;$i<=5;$i++) echo $i<=(int)round($rv['average_rating']??0)?'★':'☆'; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════ TRENDING / TOP RATED ══════════════ -->
    <?php if ($isHome && !empty($trendingProviders)): ?>
    <section class="section tight">
      <div class="section-head">
        <div><div class="section-eyebrow">Community favorites</div><h2 class="section-title">Highest rated across the platform</h2></div>
        <a class="section-link" href="providers.php?sort=rating">See all top rated <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="h-strip">
        <?php foreach ($trendingProviders as $tp):
          $tpInit = strtoupper(substr($tp['full_name'] ?? '', 0, 1)) ?: '?';
        ?>
        <a href="provider-profile.php?id=<?php echo $tp['id']; ?>" class="mini-card">
          <div class="mini-avatar">
            <?php if (!empty($tp['profile_image'])): ?>
              <img src="../uploads/profiles/<?php echo htmlspecialchars($tp['profile_image']); ?>" alt=""
                   onerror="this.style.display='none';this.parentNode.textContent='<?php echo addslashes($tpInit); ?>'">
            <?php else: ?><?php echo $tpInit; ?><?php endif; ?>
          </div>
          <div class="mini-name"><?php echo htmlspecialchars($tp['full_name']); ?></div>
          <div class="mini-prof"><?php echo htmlspecialchars($tp['profession']); ?></div>
          <div class="mini-rating">
            <?php for ($i=1;$i<=5;$i++) echo $i<=(int)round($tp['average_rating']??0)?'★':'☆'; ?>
            <span style="color:var(--text-3);margin-left:.2rem;">(<?php echo $tp['total_reviews']??0; ?>)</span>
          </div>
          <?php if (!empty($tp['avg_price'])): ?><div class="mini-price">~<?php echo number_format((float)$tp['avg_price'],0); ?> RWF</div><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════ PLATFORM STATS ══════════════ -->
    <section class="section tight">
      <div class="stats-band">
        <div class="stat-cell"><b><?php echo $platformStats['districts'] ?: 30; ?></b><span>Districts covered nationwide</span></div>
        <div class="stat-cell"><b><?php echo number_format($platformStats['categories'] ?: count($allCats)); ?></b><span>Service categories to choose from</span></div>
        <div class="stat-cell"><b><?php echo number_format($platformStats['verified']); ?></b><span>Providers with a verified badge</span></div>
        <div class="stat-cell"><b><?php echo $platformStats['avg_rating']>0 ? number_format($platformStats['avg_rating'],1).'★' : '—'; ?></b><span>Average rating across the platform</span></div>
      </div>
    </section>

    <!-- ══════════════ HOW IT WORKS ══════════════ -->
    <section class="section tight" id="how-it-works">
      <div class="section-head">
        <div><div class="section-eyebrow">The process</div><h2 class="section-title">From search to a job well done</h2></div>
      </div>
      <div class="steps">
        <div class="step-card">
          <div class="step-num">STEP 01</div>
          <h4><i class="fas fa-magnifying-glass" style="color:var(--brass);margin-right:.4rem;"></i>Search &amp; compare</h4>
          <p>Filter by district, category, rating and availability. Every profile shows real reviews, completed jobs and response time — no guessing.</p>
        </div>
        <div class="step-card">
          <div class="step-num">STEP 02</div>
          <h4><i class="fas fa-handshake" style="color:var(--brass);margin-right:.4rem;"></i>Negotiate or book instantly</h4>
          <p>Accept the listed price, or send your own offer on negotiable services — the provider can accept or counter, right in your chat.</p>
        </div>
        <div class="step-card">
          <div class="step-num">STEP 03</div>
          <h4><i class="fas fa-star" style="color:var(--brass);margin-right:.4rem;"></i>Rate after the job</h4>
          <p>Your review helps the next client choose well, and keeps every provider on the platform accountable.</p>
        </div>
      </div>
    </section>

    <!-- ══════════════ TRUST & SAFETY ══════════════ -->
    <section class="section tight">
      <div class="trust-band">
        <div>
          <h3>Trust is the whole product.</h3>
          <p>Every provider on <?php echo htmlspecialchars($platform_name); ?> goes through a verification step before the badge shows on their profile — and every completed job feeds their public track record.</p>
          <ul class="trust-points">
            <li><i class="fas fa-id-card"></i> ID and document review before the verified badge is granted.</li>
            <li><i class="fas fa-comments"></i> Reviews are tied to real, completed bookings — not posted freely.</li>
            <li><i class="fas fa-flag"></i> Report a problem in one tap; our team follows up on every complaint.</li>
            <li><i class="fas fa-coins"></i> Negotiate openly in chat — the agreed price is logged on the booking.</li>
          </ul>
        </div>
        <div class="trust-side">
          <div class="trust-side-row"><span>Verified providers</span><b><?php echo number_format($platformStats['verified']); ?></b></div>
          <div class="trust-side-row"><span>Districts with active pros</span><b><?php echo count(array_filter($districtLedger, fn($d) => (int)$d['cnt'] > 0)) ?: '—'; ?></b></div>
          <div class="trust-side-row"><span>Average platform rating</span><b><?php echo $platformStats['avg_rating']>0?number_format($platformStats['avg_rating'],1).'★':'—'; ?></b></div>
          <div class="trust-side-row"><span>Support contact</span><b style="font-size:.78rem;"><?php echo htmlspecialchars($contactPhone); ?></b></div>
        </div>
      </div>
    </section>

    <!-- ══════════════ SEARCH RESULTS / MAIN DIRECTORY ══════════════ -->
    <section class="section" id="results">
      <div class="results-shell">
        <div class="results-header">
          <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <span class="results-count" id="resultsCountText">
              <strong><?php echo number_format($totalProviders); ?></strong>
              provider<?php echo $totalProviders!==1?'s':''; ?> found
              <?php if ($search): ?> for &ldquo;<strong><?php echo htmlspecialchars($search); ?></strong>&rdquo;<?php endif; ?>
              <?php if ($category): ?> in <strong><?php echo htmlspecialchars($category); ?></strong><?php endif; ?>
            </span>
            <?php if ($sort === 'ml'): ?>
              <span class="ml-status-pill <?php echo $mlApiStatus==='ml'?'live':'heur'; ?>" id="mlStatusPill">
                <span class="ml-dot <?php echo $mlApiStatus==='ml'?'live':'heur'; ?>"></span>
                <?php echo $mlApiStatus==='ml'?'Live smart ranking':'Heuristic smart ranking'; ?>
              </span>
            <?php elseif ($sort === 'system'): ?>
              <span class="ml-status-pill heur" id="mlStatusPill"><span class="ml-dot heur"></span>System ranked</span>
            <?php else: ?>
              <span id="mlStatusPill"></span>
            <?php endif; ?>
          </div>

          <div class="sort-chips">
            <?php
            $sorts = ['ml'=>'✦ Smart','system'=>'⚙ System','rating'=>'★ Rating','reviews'=>'💬 Reviews','newest'=>'🆕 Newest','price_asc'=>'↑ Price','price_desc'=>'↓ Price'];
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
            <button class="adv-filters-btn" onclick="toggleDrawer()">
              <i class="fas fa-sliders"></i> Filters
              <?php if ($activeFiltersCount>0): ?><span class="badge-count"><?php echo $activeFiltersCount; ?></span><?php endif; ?>
            </button>
          </div>
        </div>

        <div class="filter-drawer" id="filterDrawer">
          <form method="GET" action="providers.php" id="filterForm">
            <input type="hidden" name="search"   value="<?php echo htmlspecialchars($search); ?>">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
            <input type="hidden" name="avail"    value="<?php echo htmlspecialchars($avail); ?>">
            <input type="hidden" name="sort"     value="<?php echo htmlspecialchars($sort); ?>">
            <div class="filter-grid">
              <div>
                <span class="filter-label">Minimum rating</span>
                <div class="rating-pills">
                  <?php foreach ([0,3,3.5,4,4.5] as $r): ?>
                    <a href="providers.php?min_rating=<?php echo $r; ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&avail=<?php echo urlencode($avail); ?><?php echo $verified?'&verified=1':''; ?>"
                       class="rating-pill <?php echo $minRating==$r?'active':''; ?>">
                      <?php echo $r==0?'Any':'★ '.$r.'+'; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
              <div>
                <span class="filter-label">Provider status</span>
                <label class="filter-check" style="cursor:pointer;">
                  <input type="checkbox" name="verified" value="1" <?php echo $verified?'checked':''; ?>>
                  Verified providers only
                </label>
              </div>
            </div>
            <button type="submit" class="btn-search" style="margin-top:1rem;">Apply filters</button>
          </form>
        </div>

        <?php if ($isDiscoveryView && $discovery && !empty($discovery['sections'])): ?>
          <?php include __DIR__ . '/partials/discovery_sections.php'; ?>
        <?php else: ?>
        <div class="providers-grid" id="providersGrid">
          <?php echo renderProvidersGridHtml($providers, $sort, $clientLocation, $bookedProfessions, $favIds, $recentlyViewedIds); ?>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrap" id="paginationWrap">
          <?php echo renderPaginationHtml($page, $totalPages, $_GET); ?>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ══════════════ TESTIMONIALS ══════════════ -->
    <?php if (!empty($testimonials)): ?>
    <section class="section tight">
      <div class="section-head">
        <div><div class="section-eyebrow">Real feedback</div><h2 class="section-title">What clients are saying</h2></div>
      </div>
      <div class="testi-grid">
        <?php foreach ($testimonials as $t): ?>
        <div class="testi-card">
          <div class="testi-stars"><?php for($i=1;$i<=5;$i++) echo $i<=(int)$t['rating']?'★':'☆'; ?></div>
          <p class="testi-text">&ldquo;<?php echo htmlspecialchars(mb_strimwidth($t['comment'], 0, 220, '…')); ?>&rdquo;</p>
          <div class="testi-who">
            <span><b><?php echo htmlspecialchars($t['client_name']); ?></b> on <?php echo htmlspecialchars($t['provider_name']); ?> (<?php echo htmlspecialchars($t['profession']); ?>)</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════ BECOME A PRO CTA ══════════════ -->
    <section class="section tight">
      <div class="pro-cta">
        <div>
          <h3>Skilled at something? Get booked for it.</h3>
          <p>Join as a provider, set your own prices, and start receiving requests from clients in your district — no middleman.</p>
        </div>
        <a href="../register.php?type=provider" class="btn-search"><i class="fas fa-arrow-right"></i> Become a provider</a>
      </div>
    </section>

  </div><!-- /page-body -->

  <!-- ══════════════ FOOTER ══════════════ -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-brand">
        <h4><?php echo htmlspecialchars($platform_name); ?></h4>
        <p><?php echo htmlspecialchars($platformDescription); ?></p>
      </div>
      <div class="footer-col">
        <h5>Explore</h5>
        <ul>
          <li><a href="providers.php">All providers</a></li>
          <li><a href="providers.php?verified=1">Verified pros</a></li>
          <li><a href="providers.php?sort=rating">Top rated</a></li>
          <li><a href="favorites.php">My favorites</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Account</h5>
        <ul>
          <li><a href="home.php">Home</a></li>
          <li><a href="my-bookings.php">My bookings</a></li>
          <li><a href="messages.php">Messages</a></li>
          <li><a href="profile.php">Profile settings</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Support</h5>
        <ul>
          <li><a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><i class="fas fa-envelope" style="width:14px;"></i> <?php echo htmlspecialchars($contactEmail); ?></a></li>
          <li><a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $contactPhone)); ?>"><i class="fas fa-phone" style="width:14px;"></i> <?php echo htmlspecialchars($contactPhone); ?></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($platform_name); ?>. All rights reserved.</span>
      <span>Made for every district, every trade.</span>
    </div>
  </footer>

</div><!-- /main-content -->

<!-- Compare bar + modal -->
<div class="compare-bar" id="compareBar">
  <span id="compareCount">0 selected</span>
  <button class="btn-do" id="compareDoBtn">Compare</button>
  <button class="btn-clear" id="compareClearBtn">Clear</button>
</div>
<div class="compare-modal-overlay" id="compareModalOverlay">
  <div class="compare-modal">
    <button class="compare-modal-close" id="compareModalClose"><i class="fas fa-xmark"></i></button>
    <h3>Comparing providers</h3>
    <div id="compareModalBody"></div>
  </div>
</div>

<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/providers-discovery.js"></script>
<script>
// ── Header navigation: mobile panel + user dropdown ──
const mobileNavToggle = document.getElementById('mobileNavToggle');
const mobileNavPanel = document.getElementById('mobileNavPanel');
mobileNavToggle?.addEventListener('click', () => {
  mobileNavPanel.classList.toggle('open');
  const icon = mobileNavToggle.querySelector('i');
  icon.className = mobileNavPanel.classList.contains('open') ? 'fas fa-xmark' : 'fas fa-bars';
});

const userMenu = document.getElementById('userMenu');
const userMenuBtn = document.getElementById('userMenuBtn');
userMenuBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  userMenu.classList.toggle('open');
});
document.addEventListener('click', (e) => {
  if (userMenu && !userMenu.contains(e.target)) userMenu.classList.remove('open');
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    userMenu?.classList.remove('open');
    mobileNavPanel?.classList.remove('open');
    if (mobileNavToggle) mobileNavToggle.querySelector('i').className = 'fas fa-bars';
  }
});

// ── Advanced filters drawer ──────────────────────────
function toggleDrawer() { document.getElementById('filterDrawer').classList.toggle('open'); }
<?php if ($activeFiltersCount > 0): ?>
document.getElementById('filterDrawer')?.classList.add('open');
<?php endif; ?>

// ── Toasts ────────────────────────────────────────────
function showToast(msg, type='success') {
  let wrap = document.getElementById('toastWrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'toastWrap';
    document.body.appendChild(wrap);
  }
  const t = document.createElement('div');
  const bg = type==='success' ? '#3F6B4A' : type==='error' ? '#A8432E' : '#0B1F17';
  t.style.cssText = `background:${bg};color:#fff;padding:.75rem 1.125rem;border-radius:10px;font-size:.85rem;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.28);font-family:inherit;min-width:220px;animation:toastIn .22s ease;`;
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

// ── Results / status swap helpers (used by AJAX) ─────
function updateResultsCount(html) {
  const el = document.getElementById('resultsCountText');
  if (el && html) el.innerHTML = html;
}
function updateMlStatus(html) {
  const existing = document.getElementById('mlStatusPill');
  if (!html) { existing?.remove(); return; }
  if (existing) { existing.outerHTML = html; return; }
  const header = document.querySelector('.results-header > div');
  if (header) header.insertAdjacentHTML('beforeend', html);
}
function updateFilterControlsFromUrl(url) {
  try {
    const params = new URL(url, window.location.origin).searchParams;
    const form = document.getElementById('searchForm');
    if (!form) return;
    form.querySelector('input[name="search"]').value = params.get('search') || '';
    form.querySelector('select[name="location"]').value = params.get('location') || '';
    form.querySelector('select[name="avail"]').value = params.get('avail') || '';
    form.querySelector('input[name="category"]').value = params.get('category') || '';
    form.querySelector('input[name="sort"]').value = params.get('sort') || 'ml';
    form.querySelector('input[name="min_rating"]').value = params.get('min_rating') || '';
    const verifiedInput = form.querySelector('input[name="verified"]');
    if (verifiedInput) verifiedInput.remove();
    if (params.get('verified')) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = 'verified'; hidden.value = '1';
      form.appendChild(hidden);
    }
  } catch (e) { console.warn('Unable to update filters from URL', e); }
}
function updateActiveControlsFromUrl(url) {
  try {
    const params = new URL(url, window.location.origin).searchParams;
    document.querySelectorAll('.cat-tile').forEach(link => {
      const linkParams = new URL(link.href, window.location.origin).searchParams;
      const cur = params.get('category') || '';
      const lnk = linkParams.get('category') || '';
      link.classList.toggle('active', cur === lnk && cur !== '');
    });
    document.querySelectorAll('.sort-chip').forEach(link => {
      const linkParams = new URL(link.href, window.location.origin).searchParams;
      link.classList.toggle('active', linkParams.get('sort') === params.get('sort'));
    });
    document.querySelectorAll('.rating-pill').forEach(link => {
      const linkParams = new URL(link.href, window.location.origin).searchParams;
      link.classList.toggle('active', linkParams.get('min_rating') === params.get('min_rating'));
    });
  } catch (e) { console.warn('Unable to update active controls from URL', e); }
}

const isGuestMode = document.body.dataset.guestMode === '1';

// ── Favourite toggle + compare checkboxes (rebound after AJAX swap) ──
function bindDynamicEvents() {
  document.querySelectorAll('.btn-fav').forEach(btn => btn.replaceWith(btn.cloneNode(true)));
  document.querySelectorAll('.btn-fav').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      if (isGuestMode) {
        showToast('Sign in to save favorites', 'info');
        setTimeout(() => window.location.href = '../login.php', 800);
        return;
      }
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
              ico.className = 'fas fa-heart'; btn.title = 'Remove from favorites';
              showToast('Added to favorites');
            } else {
              ico.className = 'far fa-heart'; btn.title = 'Add to favorites';
              showToast('Removed from favorites', 'info');
            }
          } else {
            showToast(data.error || 'Something went wrong', 'error');
          }
        })
        .catch(() => showToast('Network error', 'error'));
    });
  });

  document.querySelectorAll('.cat-tile, .sort-chip, .page-btn, .rating-pill, .btn-reset').forEach(link => {
    if (!(link instanceof HTMLAnchorElement)) return;
    link.addEventListener('click', e => { e.preventDefault(); loadProviders(link.href); });
  });

  document.querySelectorAll('.compare-toggle').forEach(cb => cb.addEventListener('change', onCompareToggle));

  const searchForm = document.getElementById('searchForm');
  searchForm?.addEventListener('submit', e => {
    e.preventDefault();
    const url = new URL(searchForm.action, window.location.origin);
    new FormData(searchForm).forEach((v,k) => v!=='' ? url.searchParams.set(k,v) : url.searchParams.delete(k));
    url.searchParams.delete('ajax');
    saveSearchChip(url.href);
    loadProviders(url.href);
  });

  const filterForm = document.getElementById('filterForm');
  filterForm?.addEventListener('submit', e => {
    e.preventDefault();
    const url = new URL(filterForm.action, window.location.origin);
    new FormData(filterForm).forEach((v,k) => v!=='' ? url.searchParams.set(k,v) : url.searchParams.delete(k));
    url.searchParams.delete('ajax');
    loadProviders(url.href);
  });

  document.querySelector('.search-input')?.addEventListener('input', e => {
    clearTimeout(window.__providersSearchDebounce);
    window.__providersSearchDebounce = setTimeout(() => {
      const value = e.target.value;
      if (value.length === 0 || value.length >= 2) {
        searchForm?.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    }, 600);
  });
}

let isProvidersLoading = false;
function loadProviders(url, push=true) {
  if (isProvidersLoading) return;
  const requestUrl = url.includes('ajax=1') ? url : url + (url.includes('?') ? '&ajax=1' : '?ajax=1');
  const historyUrl = requestUrl.replace(/([?&])ajax=1(&|$)/, '$1').replace(/([?&])$/, '');
  isProvidersLoading = true;
  fetch(requestUrl, { credentials: 'same-origin' })
    .then(res => res.json())
    .then(data => {
      const grid = document.getElementById('providersGrid');
      if (grid) grid.innerHTML = data.providers_html;
      updateResultsCount(data.results_count_html);
      updateMlStatus(data.ml_status_html);
      const pagination = document.getElementById('paginationWrap');
      if (pagination) pagination.innerHTML = data.pagination_html;
      if (push && historyUrl) window.history.pushState({}, '', historyUrl);
      updateFilterControlsFromUrl(historyUrl);
      updateActiveControlsFromUrl(historyUrl);
      bindDynamicEvents();
      resetCompareSelection();
      document.getElementById('results')?.scrollIntoView({ behavior:'smooth', block:'start' });
    })
    .catch(() => showToast('Unable to load providers. Check your connection.', 'error'))
    .finally(() => { isProvidersLoading = false; });
}
window.addEventListener('popstate', () => loadProviders(window.location.href, false));

// ── Saved searches (localStorage, client-side only) ──
function getSavedSearches() {
  try { return JSON.parse(localStorage.getItem('bii_saved_searches') || '[]'); } catch(e) { return []; }
}
function saveSearchChip(url) {
  try {
    const u = new URL(url, window.location.origin);
    const term = u.searchParams.get('search') || '';
    if (!term) return;
    const loc = u.searchParams.get('location') || '';
    const label = loc ? `${term} · ${loc}` : term;
    let list = getSavedSearches().filter(s => s.url !== u.href);
    list.unshift({ label, url: u.href });
    list = list.slice(0, 6);
    localStorage.setItem('bii_saved_searches', JSON.stringify(list));
    renderSavedSearches();
  } catch(e) {}
}
function renderSavedSearches() {
  const wrap = document.getElementById('savedSearches');
  if (!wrap) return;
  const list = getSavedSearches();
  if (!list.length) { wrap.style.display = 'none'; wrap.innerHTML = ''; return; }
  wrap.style.display = 'flex';
  wrap.innerHTML = '<span style="font-size:.68rem;color:var(--text-3);align-self:center;">Recent searches:</span>' +
    list.map((s,i) => `<span class="saved-chip" data-url="${s.url}"><i class="fas fa-clock-rotate-left" style="font-size:.6rem;"></i>${escapeHtml(s.label)}<button data-remove="${i}" title="Remove">&times;</button></span>`).join('');
  wrap.querySelectorAll('.saved-chip').forEach(chip => {
    chip.addEventListener('click', e => {
      if (e.target.closest('button[data-remove]')) return;
      loadProviders(chip.dataset.url);
    });
  });
  wrap.querySelectorAll('button[data-remove]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const idx = parseInt(btn.dataset.remove, 10);
      const list2 = getSavedSearches(); list2.splice(idx,1);
      localStorage.setItem('bii_saved_searches', JSON.stringify(list2));
      renderSavedSearches();
    });
  });
}
function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ── Compare providers (client-side only) ─────────────
let compareSet = new Set();
function resetCompareSelection() {
  compareSet.clear();
  updateCompareBar();
}
function onCompareToggle(e) {
  const pid = e.target.dataset.pid;
  if (e.target.checked) {
    if (compareSet.size >= 3) { e.target.checked = false; showToast('You can compare up to 3 providers', 'info'); return; }
    compareSet.add(pid);
  } else {
    compareSet.delete(pid);
  }
  updateCompareBar();
}
function updateCompareBar() {
  const bar = document.getElementById('compareBar');
  const countEl = document.getElementById('compareCount');
  countEl.textContent = `${compareSet.size} selected`;
  bar.classList.toggle('show', compareSet.size > 0);
  document.getElementById('compareDoBtn').disabled = compareSet.size < 2;
}
document.getElementById('compareClearBtn')?.addEventListener('click', () => {
  document.querySelectorAll('.compare-toggle:checked').forEach(cb => cb.checked = false);
  resetCompareSelection();
});
document.getElementById('compareDoBtn')?.addEventListener('click', () => {
  if (compareSet.size < 2) { showToast('Pick at least 2 providers to compare', 'info'); return; }
  const rows = [];
  compareSet.forEach(pid => {
    const card = document.getElementById('pcard-' + pid);
    if (card) rows.push(card.dataset);
  });
  const fields = [
    ['Name','compareName'], ['Profession','compareProf'], ['Rating','compareRating'],
    ['Reviews','compareReviews'], ['Price','comparePrice'], ['Jobs done','compareJobs'],
    ['Location','compareLocation'], ['Status','compareAvail']
  ];
  let html = '<table><thead><tr><th></th>' + rows.map(r => `<th>${escapeHtml(r.compareName)}</th>`).join('') + '</tr></thead><tbody>';
  fields.slice(1).forEach(([label, key]) => {
    html += `<tr><th>${label}</th>` + rows.map(r => `<td>${escapeHtml(r[key] || '—')}</td>`).join('') + '</tr>';
  });
  html += '</tbody></table>';
  document.getElementById('compareModalBody').innerHTML = html;
  document.getElementById('compareModalOverlay').classList.add('open');
});
document.getElementById('compareModalClose')?.addEventListener('click', () => document.getElementById('compareModalOverlay').classList.remove('open'));
document.getElementById('compareModalOverlay')?.addEventListener('click', (e) => { if (e.target.id === 'compareModalOverlay') e.currentTarget.classList.remove('open'); });

// ── Init ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.ml-bar-fill').forEach(bar => {
    const w = bar.style.width; bar.style.width = '0';
    requestAnimationFrame(() => setTimeout(() => bar.style.width = w, 80));
  });
  bindDynamicEvents();
  renderSavedSearches();
  updateCompareBar();
});
</script>
</body>
</html>