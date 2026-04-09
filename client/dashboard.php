<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/event_tracking.php';
require_once '../includes/geolocation.php';
require_once '../includes/final_ranking.php';

if (!isLoggedIn()) { redirect('login.php'); }
if (isProvider())  { redirect('provider/dashboard.php'); }

$db  = Database::getInstance()->getConnection();
$uid = (int)$_SESSION['user_id'];

function getSetting($db, $key, $default = '') {
    $s = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $s->execute([$key]);
    $r = $s->fetch(PDO::FETCH_COLUMN);
    return $r !== false ? $r : $default;
}
$platform_name = getSetting($db, 'platform_name', 'BII LocalFinder');

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$client = $stmt->fetch();
$clientLocation = trim($client['location'] ?? '');
$clientName     = $client['full_name'] ?? 'there';
$firstName      = explode(' ', $clientName)[0];

// Greeting based on time
$hour = (int)date('H');
if ($hour < 12)       $greeting = 'Good morning';
elseif ($hour < 17)   $greeting = 'Good afternoon';
else                  $greeting = 'Good evening';

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
$stmt->execute([$uid]); $totalBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'completed'");
$stmt->execute([$uid]); $completedBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'pending'");
$stmt->execute([$uid]); $pendingBookings = (int)$stmt->fetchColumn();

$favCount = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE client_id = ?");
    $stmt->execute([$uid]); $favCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {}

$reviewCount = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE client_id = ?");
    $stmt->execute([$uid]); $reviewCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {}

// Recent bookings
$recentBookings = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as provider_name, u.profile_image as provider_image, sp.profession
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE b.client_id = ?
        ORDER BY b.created_at DESC LIMIT 5
    ");
    $stmt->execute([$uid]);
    $recentBookings = $stmt->fetchAll();
} catch (Throwable $e) {}

// Favorites
$favoriteProviders = [];
try {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.profile_image, u.is_verified as user_verified
        FROM favorites f
        JOIN service_providers sp ON f.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE f.client_id = ?
        ORDER BY f.created_at DESC LIMIT 6
    ");
    $stmt->execute([$uid]);
    $favoriteProviders = $stmt->fetchAll();
} catch (Throwable $e) {}

// Recommended providers
$recommendedProviders = [];
try {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.profile_image,
               (SELECT AVG(price) FROM provider_services ps WHERE ps.provider_id=sp.id AND ps.is_available=1) as avg_price
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.is_active=1 AND sp.is_banned=0
          AND sp.id NOT IN (SELECT provider_id FROM favorites WHERE client_id=?)
        ORDER BY sp.average_rating DESC, sp.total_reviews DESC
        LIMIT 4
    ");
    $stmt->execute([$uid]);
    $recommendedProviders = $stmt->fetchAll();
} catch (Throwable $e) {}

// Pending reviews
$pendingReviews = [];
try {
    $stmt = $db->prepare("
        SELECT b.id as booking_id, b.service_description, b.preferred_date,
               sp.profession, u.full_name as provider_name, u.profile_image as provider_image
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE b.client_id = ? AND b.status='completed'
          AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id=b.id AND r.client_id=?)
        ORDER BY b.preferred_date DESC LIMIT 3
    ");
    $stmt->execute([$uid, $uid]);
    $pendingReviews = $stmt->fetchAll();
} catch (Throwable $e) {}

$bookedProfessions = [];
try {
    $stmt = $db->prepare("SELECT sp.profession, COUNT(*) as cnt FROM bookings b JOIN service_providers sp ON b.provider_id=sp.id WHERE b.client_id=? GROUP BY sp.profession ORDER BY cnt DESC LIMIT 3");
    $stmt->execute([$uid]);
    $bookedProfessions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}

$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if (!$ajax) {
    try { trackEvent('client_dashboard_view','page',0,[],$uid); } catch(Throwable $e){}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?php echo htmlspecialchars($platform_name); ?></title>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Cabinet+Grotesk:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
<style>
/* ═══════════════════════════════════════════════════════════════
   DASHBOARD DESIGN SYSTEM  —  Warm editorial · Refined luxury
   Syne headings · DM Sans body · Cream/slate/gold palette
   ═══════════════════════════════════════════════════════════════ */
:root {
  /* Core palette */
  --ink:         #0e0f13;
  --ink-2:       #1c1e27;
  --ink-3:       #2e3144;
  --slate:       #64748b;
  --muted:       #94a3b8;
  --border:      #e4e7f0;
  --border-2:    #f0f2f8;
  --surface:     #ffffff;
  --surface-2:   #f8f9fc;
  --surface-3:   #f2f4f9;

  /* Accents */
  --blue:        #3b5bdb;
  --blue-dim:    rgba(59,91,219,.09);
  --blue-glow:   rgba(59,91,219,.22);
  --teal:        #0d9488;
  --teal-dim:    rgba(13,148,136,.1);
  --gold:        #d97706;
  --gold-dim:    rgba(217,119,6,.1);
  --rose:        #e11d48;
  --rose-dim:    rgba(225,29,72,.1);
  --green:       #15803d;
  --green-dim:   rgba(21,128,61,.1);
  --violet:      #7c3aed;
  --violet-dim:  rgba(124,58,237,.1);

  /* Layout */
  --sidebar-w:   268px;
  --r-xs:        6px;
  --r-sm:        10px;
  --r-md:        14px;
  --r-lg:        18px;
  --r-xl:        24px;
  --r-2xl:       32px;

  /* Shadows */
  --s-xs:  0 1px 3px rgba(14,15,19,.05);
  --s-sm:  0 2px 8px rgba(14,15,19,.07);
  --s-md:  0 4px 20px rgba(14,15,19,.09);
  --s-lg:  0 8px 40px rgba(14,15,19,.12);
  --s-blue: 0 4px 24px rgba(59,91,219,.28);

  --transition: all .2s cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }

html { scroll-behavior: smooth; }

body {
  background: var(--surface-2);
  font-family: 'DM Sans', system-ui, sans-serif;
  color: var(--ink);
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}

h1,h2,h3,h4,h5 {
  font-family: 'Syne', sans-serif;
  letter-spacing: -0.02em;
}

/* ── SIDEBAR ─────────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  background: var(--ink-2);
  position: fixed; height: 100vh; left:0; top:0;
  z-index: 1000; display: flex; flex-direction: column;
  transition: var(--transition);
  border-right: 1px solid rgba(255,255,255,.05);
}

.sidebar-header {
  padding: 1.5rem 1.25rem 1.125rem;
  border-bottom: 1px solid rgba(255,255,255,.06);
}

.sidebar-logo {
  display: flex; align-items: center; gap: .75rem;
}

.sidebar-logo-icon {
  width: 36px; height: 36px; border-radius: var(--r-sm);
  background: linear-gradient(135deg, var(--blue), #5b86f5);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: .9rem; flex-shrink: 0;
  box-shadow: 0 2px 12px rgba(59,91,219,.4);
}

.sidebar-logo-text { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.sidebar-logo-sub  { font-size: .68rem; color: rgba(255,255,255,.4); font-weight: 500; letter-spacing: .04em; text-transform: uppercase; }

.sidebar-menu { list-style: none; padding: .75rem .625rem; flex: 1; overflow-y: auto; }
.sidebar-menu::-webkit-scrollbar { width: 3px; }
.sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 99px; }

.sidebar-section-label {
  font-size: .58rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .1em; color: rgba(255,255,255,.25);
  padding: .85rem .75rem .35rem; display: block;
}

.sidebar-menu li { margin: .1rem 0; }
.sidebar-menu a {
  color: rgba(255,255,255,.55); text-decoration: none;
  padding: .6rem .875rem; display: flex; align-items: center; gap: .7rem;
  border-radius: var(--r-sm); font-size: .84rem; font-weight: 500;
  transition: var(--transition); position: relative;
}
.sidebar-menu a:hover  { background: rgba(255,255,255,.07); color: rgba(255,255,255,.9); }
.sidebar-menu a.active {
  background: linear-gradient(135deg, rgba(59,91,219,.35), rgba(59,91,219,.18));
  color: #fff; font-weight: 700;
  border: 1px solid rgba(59,91,219,.3);
}
.sidebar-menu a.active::before {
  content: ''; position: absolute; left: 0; top: 20%; bottom: 20%;
  width: 3px; background: var(--blue); border-radius: 0 3px 3px 0; left: -.625rem;
}
.sidebar-menu i { width: 16px; font-size: .82rem; flex-shrink: 0; opacity: .8; }
.sidebar-menu a.active i, .sidebar-menu a:hover i { opacity: 1; }

.sidebar-badge {
  margin-left: auto; background: var(--rose); color: #fff;
  padding: .1rem .45rem; border-radius: 100px; font-size: .6rem; font-weight: 800;
}

.sidebar-footer {
  padding: .875rem 1.25rem;
  border-top: 1px solid rgba(255,255,255,.06);
}

.sidebar-user {
  display: flex; align-items: center; gap: .75rem;
}

.sidebar-user-avatar {
  width: 36px; height: 36px; border-radius: var(--r-sm);
  background: linear-gradient(135deg, var(--blue), #5b86f5);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 700; font-size: .9rem; flex-shrink: 0;
  overflow: hidden;
}
.sidebar-user-avatar img { width:100%; height:100%; object-fit:cover; }
.sidebar-user-name { font-size: .8rem; font-weight: 700; color: rgba(255,255,255,.9); }
.sidebar-user-role { font-size: .65rem; color: rgba(255,255,255,.35); text-transform: uppercase; letter-spacing: .04em; }

/* ── MAIN CONTENT ────────────────────────────────────── */
.main-content { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }

/* ── TOP BAR ─────────────────────────────────────────── */
.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: .875rem 2rem;
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; position: sticky; top: 0; z-index: 100;
  backdrop-filter: blur(20px);
}

.topbar-left { display: flex; align-items: center; gap: .75rem; }
.topbar-breadcrumb {
  font-size: .78rem; color: var(--muted); display: flex; align-items: center; gap: .4rem;
}
.topbar-breadcrumb .sep { opacity: .5; }
.topbar-breadcrumb strong { color: var(--ink); font-weight: 700; }

.topbar-right { display: flex; align-items: center; gap: .625rem; }

.topbar-action {
  display: flex; align-items: center; gap: .4rem;
  padding: .5rem 1rem; border-radius: var(--r-sm);
  font-size: .8rem; font-weight: 700; cursor: pointer;
  text-decoration: none; transition: var(--transition); border: none;
  font-family: inherit;
}

.topbar-action.primary {
  background: var(--blue); color: #fff;
  box-shadow: var(--s-blue);
}
.topbar-action.primary:hover { background: #2f4cc5; transform: translateY(-1px); color: #fff; }

.topbar-action.ghost {
  background: var(--surface-3); color: var(--slate);
  border: 1px solid var(--border);
}
.topbar-action.ghost:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }

.topbar-notif {
  position: relative; width: 36px; height: 36px;
  background: var(--surface-3); border: 1px solid var(--border);
  border-radius: var(--r-sm); display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--slate); font-size: .85rem; transition: var(--transition);
  text-decoration: none;
}
.topbar-notif:hover { border-color: var(--blue); color: var(--blue); }
.notif-dot {
  position: absolute; top: 6px; right: 6px;
  width: 7px; height: 7px; background: var(--rose);
  border-radius: 50%; border: 1.5px solid var(--surface);
}

/* ── PAGE CONTENT ────────────────────────────────────── */
.page-content { padding: 1.75rem 2rem; flex: 1; }

/* ── WELCOME HERO ────────────────────────────────────── */
.welcome-hero {
  background: linear-gradient(135deg, var(--ink-2) 0%, #111827 55%, #0f1b36 100%);
  border-radius: var(--r-xl);
  padding: 2rem 2.5rem;
  margin-bottom: 1.5rem;
  position: relative; overflow: hidden;
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;
}

.welcome-hero::before {
  content: '';
  position: absolute; top: -60px; right: -60px;
  width: 280px; height: 280px;
  background: radial-gradient(circle, rgba(59,91,219,.18) 0%, transparent 65%);
  pointer-events: none;
}

.welcome-hero::after {
  content: '';
  position: absolute; bottom: -40px; left: 180px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(13,148,136,.12) 0%, transparent 65%);
  pointer-events: none;
}

.welcome-grid-pattern {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

.welcome-info { position: relative; z-index: 2; }

.welcome-greeting {
  font-size: .78rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .12em; color: rgba(255,255,255,.45); margin-bottom: .35rem;
  display: flex; align-items: center; gap: .5rem;
}

.greeting-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #10b981; box-shadow: 0 0 8px #10b981;
  animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

.welcome-name {
  font-size: 2rem; font-weight: 800; color: #fff;
  margin-bottom: .5rem; line-height: 1.15;
}

.welcome-sub {
  font-size: .88rem; color: rgba(255,255,255,.5);
  margin-bottom: 1.25rem; line-height: 1.6; max-width: 420px;
}

.welcome-cta-group { display: flex; gap: .625rem; flex-wrap: wrap; }

.btn-hero {
  padding: .65rem 1.35rem; border-radius: var(--r-sm);
  font-size: .85rem; font-weight: 700; cursor: pointer;
  text-decoration: none; transition: var(--transition);
  display: inline-flex; align-items: center; gap: .45rem; border: none;
  font-family: inherit;
}

.btn-hero-primary {
  background: var(--blue); color: #fff;
  box-shadow: 0 4px 20px rgba(59,91,219,.4);
}
.btn-hero-primary:hover { background: #2f4cc5; color: #fff; transform: translateY(-2px); box-shadow: 0 8px 28px rgba(59,91,219,.45); }

.btn-hero-ghost {
  background: rgba(255,255,255,.07); color: rgba(255,255,255,.8);
  border: 1px solid rgba(255,255,255,.12);
}
.btn-hero-ghost:hover { background: rgba(255,255,255,.12); color: #fff; }

/* welcome stats strip */
.welcome-stats-strip {
  position: relative; z-index: 2;
  display: flex; gap: 1rem; flex-direction: column; flex-shrink: 0;
}

.welcome-stat-pill {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: var(--r-md);
  padding: .875rem 1.25rem;
  text-align: center; min-width: 110px;
  backdrop-filter: blur(8px);
}
.welcome-stat-pill .num {
  font-family: 'Syne', sans-serif;
  font-size: 1.75rem; font-weight: 800; color: #fff;
  line-height: 1; margin-bottom: .2rem;
  letter-spacing: -.04em;
}
.welcome-stat-pill .lbl { font-size: .65rem; color: rgba(255,255,255,.4); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }

/* ── STATS GRID ──────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem; margin-bottom: 1.5rem;
}

.stat-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: 1.25rem 1.5rem;
  box-shadow: var(--s-xs); transition: var(--transition);
  text-decoration: none; color: inherit; display: block;
  position: relative; overflow: hidden;
}

.stat-card::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  background: var(--stat-color, var(--blue)); opacity: 0; transition: opacity .25s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--s-md); color: inherit; }
.stat-card:hover::after { opacity: 1; }

.stat-icon-wrap {
  width: 44px; height: 44px; border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; margin-bottom: .875rem;
  background: var(--stat-bg, var(--blue-dim));
  color: var(--stat-color, var(--blue));
}

.stat-num {
  font-family: 'Syne', sans-serif;
  font-size: 1.9rem; font-weight: 800; color: var(--ink);
  line-height: 1; margin-bottom: .2rem; letter-spacing: -.04em;
}

.stat-label { font-size: .72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

.stat-trend {
  font-size: .7rem; font-weight: 700; margin-top: .35rem;
  display: flex; align-items: center; gap: .25rem;
}
.stat-trend.up   { color: var(--green); }
.stat-trend.idle { color: var(--muted); }

/* ── TWO-COLUMN LAYOUT ───────────────────────────────── */
.dash-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  align-items: start;
}

/* ── SECTION CARD ────────────────────────────────────── */
.section-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-lg); overflow: hidden;
  box-shadow: var(--s-xs); margin-bottom: 1.25rem;
}

.section-head {
  padding: 1.125rem 1.5rem;
  border-bottom: 1px solid var(--border-2);
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
}

.section-title-group { display: flex; align-items: center; gap: .625rem; }
.section-icon {
  width: 32px; height: 32px; border-radius: var(--r-xs);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem;
}

.section-h3 { font-size: .92rem; font-weight: 800; color: var(--ink); }

.section-link {
  font-size: .75rem; font-weight: 700; color: var(--blue);
  text-decoration: none; display: flex; align-items: center; gap: .25rem;
  transition: var(--transition); white-space: nowrap;
}
.section-link:hover { color: #2f4cc5; gap: .4rem; }

.section-body { padding: 1.125rem 1.5rem; }

/* ── PENDING ALERTS BAR ──────────────────────────────── */
.alert-strip {
  background: linear-gradient(135deg, rgba(217,119,6,.06), rgba(217,119,6,.02));
  border: 1px solid rgba(217,119,6,.2);
  border-radius: var(--r-lg);
  padding: 1rem 1.5rem;
  margin-bottom: 1.25rem;
  display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
}
.alert-strip-icon {
  width: 38px; height: 38px; border-radius: var(--r-sm);
  background: var(--gold-dim); color: var(--gold);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
}
.alert-strip-text h4 { font-size: .85rem; font-weight: 800; color: var(--ink); margin-bottom: .1rem; }
.alert-strip-text p  { font-size: .77rem; color: var(--slate); margin: 0; }

/* ── BOOKING ROWS ────────────────────────────────────── */
.booking-row {
  display: flex; align-items: center; gap: 1rem;
  padding: .875rem 0;
  border-bottom: 1px solid var(--border-2);
  transition: var(--transition);
}
.booking-row:last-child { border-bottom: none; padding-bottom: 0; }
.booking-row:first-child { padding-top: 0; }
.booking-row:hover .brow-name { color: var(--blue); }

.brow-avatar {
  width: 42px; height: 42px; border-radius: var(--r-sm);
  background: linear-gradient(135deg, var(--blue), #5b86f5);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 800; font-size: .95rem; flex-shrink: 0;
  overflow: hidden;
}
.brow-avatar img { width:100%; height:100%; object-fit:cover; border-radius:inherit; }

.brow-info { flex: 1; min-width: 0; }
.brow-name { font-size: .85rem; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color .2s; }
.brow-profession { font-size: .72rem; color: var(--muted); margin-top: .1rem; }
.brow-date { font-size: .7rem; color: var(--muted); margin-top: .2rem; display: flex; align-items: center; gap: .3rem; }

.brow-right { text-align: right; flex-shrink: 0; }
.booking-status {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .2rem .65rem; border-radius: 100px;
  font-size: .65rem; font-weight: 800; letter-spacing: .02em;
}
.status-pending   { background: rgba(217,119,6,.12); color: var(--gold); border: 1px solid rgba(217,119,6,.2); }
.status-confirmed { background: rgba(13,148,136,.1); color: var(--teal); border: 1px solid rgba(13,148,136,.2); }
.status-completed { background: var(--green-dim);  color: var(--green); border: 1px solid rgba(21,128,61,.2); }
.status-cancelled { background: var(--rose-dim);   color: var(--rose);  border: 1px solid rgba(225,29,72,.2); }

/* ── FAVORITES ROW ───────────────────────────────────── */
.fav-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: .75rem;
}

.fav-card {
  border: 1px solid var(--border);
  border-radius: var(--r-md); padding: .875rem;
  display: flex; align-items: center; gap: .75rem;
  text-decoration: none; color: inherit; transition: var(--transition);
}
.fav-card:hover { border-color: var(--blue); box-shadow: var(--s-sm); transform: translateY(-2px); color: inherit; }

.fav-avatar {
  width: 40px; height: 40px; border-radius: var(--r-sm);
  background: linear-gradient(135deg, var(--violet), #9f58f5);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 800; font-size: .9rem; flex-shrink: 0; overflow: hidden;
}
.fav-avatar img { width:100%; height:100%; object-fit:cover; border-radius:inherit; }

.fav-name { font-size: .8rem; font-weight: 700; color: var(--ink); margin-bottom: .1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fav-prof { font-size: .68rem; color: var(--blue); font-weight: 600; }
.fav-rating { font-size: .68rem; color: var(--gold); margin-top: .15rem; }

/* ── QUICK ACTIONS ───────────────────────────────────── */
.quick-actions-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .625rem; }

.qa-btn {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: .45rem; padding: 1.125rem .875rem; border-radius: var(--r-md);
  text-decoration: none; color: inherit; transition: var(--transition);
  border: 1px solid var(--border); background: var(--surface-2);
  text-align: center; cursor: pointer;
}
.qa-btn:hover { border-color: var(--qa-color, var(--blue)); background: var(--qa-dim, var(--blue-dim)); color: var(--qa-color, var(--blue)); transform: translateY(-2px); box-shadow: var(--s-sm); }

.qa-icon {
  width: 40px; height: 40px; border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center; font-size: 1rem;
  background: var(--qa-dim, var(--blue-dim)); color: var(--qa-color, var(--blue));
  margin-bottom: .1rem;
}
.qa-label { font-size: .73rem; font-weight: 700; color: var(--slate); transition: color .2s; }
.qa-btn:hover .qa-label { color: var(--qa-color, var(--blue)); }

/* ── RECOMMENDED ─────────────────────────────────────── */
.rec-list { display: flex; flex-direction: column; gap: 0; }

.rec-item {
  display: flex; align-items: center; gap: .875rem;
  padding: .875rem 0;
  border-bottom: 1px solid var(--border-2);
}
.rec-item:last-child { border-bottom: none; padding-bottom: 0; }
.rec-item:first-child { padding-top: 0; }

.rec-avatar {
  width: 46px; height: 46px; border-radius: var(--r-sm); flex-shrink: 0;
  background: linear-gradient(135deg, var(--teal), #0fbcae);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 800; font-size: 1rem; overflow: hidden;
}
.rec-avatar img { width:100%; height:100%; object-fit:cover; border-radius:inherit; }
.rec-info { flex: 1; min-width: 0; }
.rec-name { font-size: .86rem; font-weight: 700; color: var(--ink); }
.rec-prof { font-size: .71rem; color: var(--teal); font-weight: 600; margin-top: .1rem; }
.rec-meta { display: flex; align-items: center; gap: .75rem; margin-top: .25rem; }
.rec-stars { color: var(--gold); font-size: .68rem; }
.rec-reviews { font-size: .68rem; color: var(--muted); }
.rec-price { font-size: .72rem; font-weight: 700; color: var(--green); }

.btn-rec {
  padding: .4rem .875rem; border-radius: var(--r-xs);
  background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,91,219,.2);
  font-size: .73rem; font-weight: 700; text-decoration: none; transition: var(--transition);
  white-space: nowrap;
}
.btn-rec:hover { background: var(--blue); color: #fff; }

/* ── REVIEW NUDGE ────────────────────────────────────── */
.review-nudge {
  background: linear-gradient(135deg, rgba(124,58,237,.05), rgba(124,58,237,.02));
  border: 1px solid rgba(124,58,237,.15);
  border-radius: var(--r-md); padding: .875rem 1.125rem;
  display: flex; align-items: center; gap: .875rem;
  margin-bottom: .75rem; text-decoration: none; color: inherit;
  transition: var(--transition);
}
.review-nudge:hover { border-color: var(--violet); box-shadow: var(--s-sm); color: inherit; }
.review-nudge-avatar {
  width: 40px; height: 40px; border-radius: var(--r-sm); flex-shrink: 0;
  background: var(--violet-dim); color: var(--violet);
  display: flex; align-items: center; justify-content: center; overflow: hidden;
  font-weight: 800; font-size: .9rem;
}
.review-nudge-avatar img { width:100%; height:100%; object-fit:cover; border-radius:inherit; }
.review-nudge-info { flex: 1; min-width: 0; }
.review-nudge-name { font-size: .8rem; font-weight: 700; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.review-nudge-cta  { font-size: .7rem; color: var(--violet); font-weight: 600; margin-top: .1rem; display: flex; align-items: center; gap: .3rem; }

/* ── PROFESSIONS TAGS ────────────────────────────────── */
.prof-tags { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
.prof-tag {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .3rem .75rem; border-radius: 100px; font-size: .72rem; font-weight: 700;
  background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,91,219,.18);
  text-decoration: none; transition: var(--transition);
}
.prof-tag:hover { background: var(--blue); color: #fff; }

/* ── EMPTY STATE ─────────────────────────────────────── */
.empty-inline {
  text-align: center; padding: 2rem 1rem; color: var(--muted);
}
.empty-inline i { font-size: 1.75rem; margin-bottom: .6rem; display: block; opacity: .4; }
.empty-inline p { font-size: .8rem; line-height: 1.5; }

/* ── MOBILE TOGGLE ───────────────────────────────────── */
.mobile-toggle {
  display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1100;
  background: var(--blue); color: #fff; border: none;
  border-radius: var(--r-sm); width: 40px; height: 40px;
  align-items: center; justify-content: center; cursor: pointer;
  box-shadow: var(--s-blue); font-size: 1rem;
}
.overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(14,15,19,.7); z-index: 999; backdrop-filter: blur(4px);
}
.overlay.active { display: block; }

/* ── ANIMATE IN ──────────────────────────────────────── */
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

.animate-in { animation: fadeUp .45s cubic-bezier(.16,1,.3,1) both; }
.animate-in:nth-child(1) { animation-delay: .05s; }
.animate-in:nth-child(2) { animation-delay: .10s; }
.animate-in:nth-child(3) { animation-delay: .15s; }
.animate-in:nth-child(4) { animation-delay: .20s; }

/* ── RESPONSIVE ──────────────────────────────────────── */
@media (max-width: 1200px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .dash-grid  { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); box-shadow: 4px 0 40px rgba(0,0,0,.5); }
  .main-content { margin-left: 0; }
  .mobile-toggle { display: flex !important; }
  .page-content { padding: 1rem; }
  .topbar { padding: .75rem 1rem; }
  .welcome-hero { padding: 1.5rem; }
  .welcome-name { font-size: 1.5rem; }
  .welcome-stats-strip { flex-direction: row; flex-wrap: wrap; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .fav-grid, .quick-actions-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Mobile toggle -->
<button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<!-- ── SIDEBAR ──────────────────────────────────────────────────── -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- ── MAIN ─────────────────────────────────────────────────────── -->
<div class="main-content">

  <!-- TOP BAR -->
  <div class="topbar">
    <div class="topbar-left">
      <div class="topbar-breadcrumb">
        <i class="fas fa-home" style="color:var(--muted);font-size:.7rem;"></i>
        <span class="sep">/</span>
        <strong>Dashboard</strong>
      </div>
    </div>
    <div class="topbar-right">
      <?php if (!empty($pendingReviews)): ?>
      <a href="my-reviews.php" class="topbar-action ghost">
        <i class="fas fa-star"></i> <?php echo count($pendingReviews); ?> Review<?php echo count($pendingReviews)>1?'s':''; ?> pending
      </a>
      <?php endif; ?>
      <a href="my-bookings.php" class="topbar-notif" title="Bookings">
        <i class="fas fa-calendar"></i>
        <?php if ($pendingBookings > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </a>
      <a href="providers.php" class="topbar-action primary">
        <i class="fas fa-search"></i> Find Providers
      </a>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="page-content">

    <!-- ── WELCOME HERO ─────────────────────────────── -->
    <div class="welcome-hero animate-in">
      <div class="welcome-grid-pattern"></div>
      <div class="welcome-info">
        <div class="welcome-greeting">
          <span class="greeting-dot"></span>
          <?php echo htmlspecialchars($greeting); ?>
        </div>
        <h1 class="welcome-name">Hello, <?php echo htmlspecialchars($firstName); ?> 👋</h1>
        <p class="welcome-sub">
          <?php if ($totalBookings === 0): ?>
            Welcome to <?php echo htmlspecialchars($platform_name); ?>! You're all set to find trusted service providers near you.
          <?php elseif ($pendingBookings > 0): ?>
            You have <?php echo $pendingBookings; ?> pending booking<?php echo $pendingBookings>1?'s':''; ?>. Keep track of your service requests below.
          <?php else: ?>
            You've completed <?php echo $completedBookings; ?> booking<?php echo $completedBookings!==1?'s':''; ?>. Ready to find more great service providers?
          <?php endif; ?>
        </p>
        <div class="welcome-cta-group">
          <a href="providers.php" class="btn-hero btn-hero-primary">
            <i class="fas fa-search"></i> Find Providers
          </a>
          <a href="my-bookings.php" class="btn-hero btn-hero-ghost">
            <i class="fas fa-calendar-check"></i> My Bookings
          </a>
          <?php if ($totalBookings === 0): ?>
          <a href="about.php" class="btn-hero btn-hero-ghost">
            <i class="fas fa-info-circle"></i> How it works
          </a>
          <?php endif; ?>
        </div>

        <?php if (!empty($bookedProfessions)): ?>
        <div class="prof-tags">
          <?php foreach (array_keys($bookedProfessions) as $prof): ?>
          <a href="providers.php?category=<?php echo urlencode($prof); ?>" class="prof-tag">
            <i class="fas fa-history" style="font-size:.6rem;"></i> <?php echo htmlspecialchars($prof); ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="welcome-stats-strip">
        <div class="welcome-stat-pill">
          <div class="num"><?php echo $totalBookings; ?></div>
          <div class="lbl">Bookings</div>
        </div>
        <div class="welcome-stat-pill">
          <div class="num"><?php echo $completedBookings; ?></div>
          <div class="lbl">Completed</div>
        </div>
        <div class="welcome-stat-pill">
          <div class="num"><?php echo $favCount; ?></div>
          <div class="lbl">Favorites</div>
        </div>
      </div>
    </div>

    <!-- ── PENDING REVIEW ALERT ──────────────────────── -->
    <?php if (!empty($pendingReviews)): ?>
    <div class="alert-strip animate-in">
      <div class="alert-strip-icon"><i class="fas fa-star"></i></div>
      <div class="alert-strip-text" style="flex:1;">
        <h4>Share your experience</h4>
        <p>You have <?php echo count($pendingReviews); ?> completed booking<?php echo count($pendingReviews)>1?'s':''; ?> waiting for a review.</p>
      </div>
      <?php foreach ($pendingReviews as $pr):
        $prInit = strtoupper(substr($pr['provider_name'] ?? '', 0, 1)) ?: '?';
      ?>
        <a href="write-review.php?booking_id=<?php echo $pr['booking_id']; ?>&provider_id=<?php echo $pr['booking_id']; ?>" class="review-nudge" style="margin-bottom:0;flex-shrink:0;max-width:260px;">
          <div class="review-nudge-avatar">
            <?php if (!empty($pr['provider_image'])): ?>
              <img src="../uploads/profiles/<?php echo htmlspecialchars($pr['provider_image']); ?>" alt="">
            <?php else: ?><?php echo $prInit; ?><?php endif; ?>
          </div>
          <div class="review-nudge-info">
            <div class="review-nudge-name"><?php echo htmlspecialchars($pr['provider_name']); ?></div>
            <div class="review-nudge-cta"><i class="fas fa-pen-nib" style="font-size:.6rem;"></i> Write a review</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── STATS GRID ────────────────────────────────── -->
    <div class="stats-grid">
      <a href="my-bookings.php" class="stat-card animate-in" style="--stat-color:var(--blue);--stat-bg:var(--blue-dim);">
        <div class="stat-icon-wrap"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-num"><?php echo $totalBookings; ?></div>
        <div class="stat-label">Total Bookings</div>
        <?php if ($pendingBookings > 0): ?>
        <div class="stat-trend idle"><i class="fas fa-clock"></i> <?php echo $pendingBookings; ?> pending</div>
        <?php endif; ?>
      </a>

      <a href="my-bookings.php?status=completed" class="stat-card animate-in" style="--stat-color:var(--green);--stat-bg:var(--green-dim);">
        <div class="stat-icon-wrap"><i class="fas fa-circle-check"></i></div>
        <div class="stat-num"><?php echo $completedBookings; ?></div>
        <div class="stat-label">Completed</div>
        <?php if ($completedBookings > 0): ?>
        <div class="stat-trend up"><i class="fas fa-arrow-up"></i> Great track record!</div>
        <?php endif; ?>
      </a>

      <a href="favorites.php" class="stat-card animate-in" style="--stat-color:var(--rose);--stat-bg:var(--rose-dim);">
        <div class="stat-icon-wrap"><i class="fas fa-heart"></i></div>
        <div class="stat-num"><?php echo $favCount; ?></div>
        <div class="stat-label">Favorites</div>
        <div class="stat-trend idle"><i class="fas fa-user-check"></i> Saved providers</div>
      </a>

      <a href="my-reviews.php" class="stat-card animate-in" style="--stat-color:var(--gold);--stat-bg:var(--gold-dim);">
        <div class="stat-icon-wrap"><i class="fas fa-star"></i></div>
        <div class="stat-num"><?php echo $reviewCount; ?></div>
        <div class="stat-label">Reviews Given</div>
        <?php if (count($pendingReviews) > 0): ?>
        <div class="stat-trend idle"><i class="fas fa-pen"></i> <?php echo count($pendingReviews); ?> pending</div>
        <?php endif; ?>
      </a>
    </div>

    <!-- ── MAIN DASH GRID ────────────────────────────── -->
    <div class="dash-grid">

      <!-- LEFT COLUMN -->
      <div>

        <!-- Recent Bookings -->
        <div class="section-card animate-in">
          <div class="section-head">
            <div class="section-title-group">
              <div class="section-icon" style="background:var(--blue-dim);color:var(--blue);">
                <i class="fas fa-calendar-alt"></i>
              </div>
              <h3 class="section-h3">Recent Bookings</h3>
            </div>
            <a href="my-bookings.php" class="section-link">View all <i class="fas fa-chevron-right" style="font-size:.6rem;"></i></a>
          </div>
          <div class="section-body">
            <?php if (empty($recentBookings)): ?>
              <div class="empty-inline">
                <i class="fas fa-calendar-plus"></i>
                <p>No bookings yet.<br>Find a provider and make your first booking!</p>
              </div>
            <?php else: ?>
              <?php foreach ($recentBookings as $bk):
                $bInit = strtoupper(substr($bk['provider_name'] ?? '', 0, 1)) ?: '?';
                $stClass = 'status-' . ($bk['status'] ?? 'pending');
              ?>
              <div class="booking-row">
                <div class="brow-avatar">
                  <?php if (!empty($bk['provider_image'])): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($bk['provider_image']); ?>" alt="">
                  <?php else: ?><?php echo $bInit; ?><?php endif; ?>
                </div>
                <div class="brow-info">
                  <div class="brow-name"><?php echo htmlspecialchars($bk['provider_name']); ?></div>
                  <div class="brow-profession"><?php echo htmlspecialchars($bk['profession']); ?></div>
                  <div class="brow-date">
                    <i class="fas fa-calendar" style="font-size:.6rem;"></i>
                    <?php echo date('M d, Y', strtotime($bk['preferred_date'])); ?>
                  </div>
                </div>
                <div class="brow-right">
                  <span class="booking-status <?php echo $stClass; ?>">
                    <?php echo ucfirst(htmlspecialchars($bk['status'])); ?>
                  </span>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Favorites -->
        <?php if (!empty($favoriteProviders)): ?>
        <div class="section-card animate-in">
          <div class="section-head">
            <div class="section-title-group">
              <div class="section-icon" style="background:var(--rose-dim);color:var(--rose);">
                <i class="fas fa-heart"></i>
              </div>
              <h3 class="section-h3">My Favorites</h3>
            </div>
            <a href="favorites.php" class="section-link">View all <i class="fas fa-chevron-right" style="font-size:.6rem;"></i></a>
          </div>
          <div class="section-body">
            <div class="fav-grid">
              <?php foreach ($favoriteProviders as $fv):
                $fvInit = strtoupper(substr($fv['full_name'] ?? '', 0, 1)) ?: '?';
              ?>
              <a href="provider-profile.php?id=<?php echo $fv['id']; ?>" class="fav-card">
                <div class="fav-avatar">
                  <?php if (!empty($fv['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($fv['profile_image']); ?>" alt="">
                  <?php else: ?><?php echo $fvInit; ?><?php endif; ?>
                </div>
                <div style="min-width:0;">
                  <div class="fav-name"><?php echo htmlspecialchars($fv['full_name']); ?></div>
                  <div class="fav-prof"><?php echo htmlspecialchars($fv['profession']); ?></div>
                  <div class="fav-rating">
                    <?php $fr = round($fv['average_rating'] ?? 0);
                    for ($i=1;$i<=5;$i++) echo $i<=$fr?'★':'☆'; ?>
                    <span style="color:var(--muted);font-size:.63rem;margin-left:.2rem;">(<?php echo (int)($fv['total_reviews']??0); ?>)</span>
                  </div>
                </div>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Recommended Providers -->
        <?php if (!empty($recommendedProviders)): ?>
        <div class="section-card animate-in">
          <div class="section-head">
            <div class="section-title-group">
              <div class="section-icon" style="background:var(--teal-dim);color:var(--teal);">
                <i class="fas fa-wand-magic-sparkles"></i>
              </div>
              <h3 class="section-h3">Recommended For You</h3>
            </div>
            <a href="providers.php" class="section-link">Browse all <i class="fas fa-chevron-right" style="font-size:.6rem;"></i></a>
          </div>
          <div class="section-body">
            <div class="rec-list">
              <?php foreach ($recommendedProviders as $rec):
                $recInit = strtoupper(substr($rec['full_name'] ?? '', 0, 1)) ?: '?';
              ?>
              <div class="rec-item">
                <div class="rec-avatar">
                  <?php if (!empty($rec['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($rec['profile_image']); ?>" alt="">
                  <?php else: ?><?php echo $recInit; ?><?php endif; ?>
                </div>
                <div class="rec-info">
                  <div class="rec-name"><?php echo htmlspecialchars($rec['full_name']); ?></div>
                  <div class="rec-prof"><?php echo htmlspecialchars($rec['profession']); ?></div>
                  <div class="rec-meta">
                    <span class="rec-stars"><?php $rr=round($rec['average_rating']??0); for($i=1;$i<=5;$i++) echo $i<=$rr?'★':'☆'; ?></span>
                    <span class="rec-reviews">(<?php echo (int)($rec['total_reviews']??0); ?>)</span>
                    <?php if (!empty($rec['avg_price'])): ?>
                      <span class="rec-price">~RWF <?php echo number_format((float)$rec['avg_price'],0); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <a href="provider-profile.php?id=<?php echo $rec['id']; ?>" class="btn-rec">View</a>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <!-- RIGHT COLUMN -->
      <div>

        <!-- Quick Actions -->
        <div class="section-card animate-in">
          <div class="section-head">
            <div class="section-title-group">
              <div class="section-icon" style="background:var(--violet-dim);color:var(--violet);">
                <i class="fas fa-bolt"></i>
              </div>
              <h3 class="section-h3">Quick Actions</h3>
            </div>
          </div>
          <div class="section-body">
            <div class="quick-actions-grid">
              <a href="providers.php" class="qa-btn" style="--qa-color:var(--blue);--qa-dim:var(--blue-dim);">
                <div class="qa-icon"><i class="fas fa-search"></i></div>
                <div class="qa-label">Find Providers</div>
              </a>
              <a href="my-bookings.php" class="qa-btn" style="--qa-color:var(--teal);--qa-dim:var(--teal-dim);">
                <div class="qa-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="qa-label">My Bookings</div>
              </a>
              <a href="favorites.php" class="qa-btn" style="--qa-color:var(--rose);--qa-dim:var(--rose-dim);">
                <div class="qa-icon"><i class="fas fa-heart"></i></div>
                <div class="qa-label">Favorites</div>
              </a>
              <a href="my-reviews.php" class="qa-btn" style="--qa-color:var(--gold);--qa-dim:var(--gold-dim);">
                <div class="qa-icon"><i class="fas fa-star"></i></div>
                <div class="qa-label">My Reviews</div>
              </a>
              <a href="profile.php" class="qa-btn" style="--qa-color:var(--violet);--qa-dim:var(--violet-dim);">
                <div class="qa-icon"><i class="fas fa-user-circle"></i></div>
                <div class="qa-label">My Profile</div>
              </a>
              <a href="settings.php" class="qa-btn" style="--qa-color:var(--slate);--qa-dim:var(--surface-3);">
                <div class="qa-icon"><i class="fas fa-cog"></i></div>
                <div class="qa-label">Settings</div>
              </a>
            </div>
          </div>
        </div>

        <!-- Pending Reviews Card -->
        <?php if (!empty($pendingReviews)): ?>
        <div class="section-card animate-in">
          <div class="section-head">
            <div class="section-title-group">
              <div class="section-icon" style="background:var(--violet-dim);color:var(--violet);">
                <i class="fas fa-pen-nib"></i>
              </div>
              <h3 class="section-h3">Pending Reviews</h3>
            </div>
            <span style="font-size:.65rem;font-weight:800;background:var(--violet-dim);color:var(--violet);padding:.15rem .5rem;border-radius:100px;"><?php echo count($pendingReviews); ?></span>
          </div>
          <div class="section-body">
            <?php foreach ($pendingReviews as $pr):
              $prInit = strtoupper(substr($pr['provider_name'] ?? '', 0, 1)) ?: '?';
            ?>
            <a href="write-review.php?booking_id=<?php echo $pr['booking_id']; ?>" class="review-nudge">
              <div class="review-nudge-avatar">
                <?php if (!empty($pr['provider_image'])): ?>
                  <img src="../uploads/profiles/<?php echo htmlspecialchars($pr['provider_image']); ?>" alt="">
                <?php else: ?><?php echo $prInit; ?><?php endif; ?>
              </div>
              <div class="review-nudge-info">
                <div class="review-nudge-name"><?php echo htmlspecialchars($pr['provider_name']); ?></div>
                <div class="review-nudge-cta"><i class="fas fa-star" style="font-size:.6rem;"></i> Leave a review</div>
              </div>
              <i class="fas fa-chevron-right" style="color:var(--muted);font-size:.7rem;flex-shrink:0;"></i>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Member Info Card -->
        <div class="section-card animate-in">
          <div class="section-head">
            <div class="section-title-group">
              <div class="section-icon" style="background:var(--green-dim);color:var(--green);">
                <i class="fas fa-user-circle"></i>
              </div>
              <h3 class="section-h3">Account</h3>
            </div>
            <a href="profile.php" class="section-link">Edit <i class="fas fa-chevron-right" style="font-size:.6rem;"></i></a>
          </div>
          <div class="section-body">
            <div style="display:flex;align-items:center;gap:.875rem;margin-bottom:1rem;">
              <div style="width:52px;height:52px;border-radius:var(--r-md);background:linear-gradient(135deg,var(--blue),#5b86f5);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.2rem;overflow:hidden;flex-shrink:0;">
                <?php if (!empty($client['profile_image'])): ?>
                  <img src="../uploads/profiles/<?php echo htmlspecialchars($client['profile_image']); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <?php else: ?>
                  <?php echo strtoupper(substr($clientName, 0, 1)); ?>
                <?php endif; ?>
              </div>
              <div>
                <div style="font-size:.92rem;font-weight:800;color:var(--ink);"><?php echo htmlspecialchars($clientName); ?></div>
                <div style="font-size:.73rem;color:var(--muted);margin-top:.1rem;"><?php echo htmlspecialchars($client['email'] ?? ''); ?></div>
                <?php if (!empty($clientLocation)): ?>
                <div style="font-size:.71rem;color:var(--blue);margin-top:.15rem;display:flex;align-items:center;gap:.25rem;font-weight:600;">
                  <i class="fas fa-map-pin" style="font-size:.6rem;"></i> <?php echo htmlspecialchars($clientLocation); ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.73rem;">
              <div style="background:var(--surface-3);border-radius:var(--r-sm);padding:.625rem;text-align:center;">
                <div style="font-weight:800;color:var(--ink);font-size:1rem;"><?php echo $totalBookings; ?></div>
                <div style="color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;font-size:.6rem;">Bookings</div>
              </div>
              <div style="background:var(--surface-3);border-radius:var(--r-sm);padding:.625rem;text-align:center;">
                <div style="font-weight:800;color:var(--ink);font-size:1rem;"><?php echo $reviewCount; ?></div>
                <div style="color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;font-size:.6rem;">Reviews</div>
              </div>
            </div>
            <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border-2);font-size:.72rem;color:var(--muted);">
              <i class="fas fa-calendar-plus" style="margin-right:.3rem;"></i>
              Member since <?php echo date('F Y', strtotime($client['created_at'] ?? 'now')); ?>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /page-content -->
</div><!-- /main-content -->

<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const mobileToggle = document.getElementById('mobileToggle');
const overlay      = document.getElementById('overlay');

// Find sidebar — support both id patterns
const sidebar = document.getElementById('clientSidebar') || document.getElementById('sidebar') || document.querySelector('.sidebar');

if (mobileToggle && sidebar) {
  mobileToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
  });
}
if (overlay) {
  overlay.addEventListener('click', () => {
    sidebar?.classList.remove('open');
    overlay.classList.remove('active');
  });
}

// Animate stat numbers
document.querySelectorAll('.stat-num,.num').forEach(el => {
  const target = parseInt(el.textContent, 10);
  if (isNaN(target) || target === 0) return;
  let start = 0;
  const duration = 800;
  const step = target / (duration / 16);
  const timer = setInterval(() => {
    start = Math.min(start + step, target);
    el.textContent = Math.floor(start);
    if (start >= target) clearInterval(timer);
  }, 16);
});

// Scroll reveal
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.section-card,.stat-card,.alert-strip').forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(18px)';
  el.style.transition = `opacity .45s cubic-bezier(.16,1,.3,1) ${i * 0.06}s, transform .45s cubic-bezier(.16,1,.3,1) ${i * 0.06}s`;
  observer.observe(el);
});
</script>
</body>
</html>