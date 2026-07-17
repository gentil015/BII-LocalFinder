<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (isProvider()) {
    redirect('../provider/dashboard.php');
}

$db = Database::getInstance()->getConnection();

// ── Load platform name ──────────────────────────────────────────────────────
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}
$platform_name = getSetting($db, 'platform_name', 'BII LocalFinder');

// ── Get service_id ──────────────────────────────────────────────────────────
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
if (!$service_id) {
    header("Location: services.php");
    exit();
}

// ── Fetch service + provider details ───────────────────────────────────────
$stmt = $db->prepare("
    SELECT
        ps.*,
        c.name            AS category_name,
        c.icon            AS category_icon,
        sp.id             AS provider_id,
        sp.bio,
        sp.location,
        sp.district,
        sp.experience_years,
        sp.average_rating,
        sp.total_reviews,
        sp.total_jobs,
        sp.availability,
        sp.is_verified    AS sp_verified,
        sp.working_hours_start,
        sp.working_hours_end,
        sp.working_days,
        u.full_name       AS provider_name,
        u.profile_image   AS provider_image,
        u.email           AS provider_email,
        u.phone           AS provider_phone,
        u.is_verified     AS user_verified
    FROM provider_services ps
    JOIN categories        c  ON ps.category_id = c.id
    JOIN service_providers sp ON ps.provider_id  = sp.id
    JOIN users             u  ON sp.user_id      = u.id
    WHERE ps.id = ?
      AND ps.is_available = 1
      AND sp.is_active    = 1
      AND sp.is_banned    = 0
");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header("Location: services.php");
    exit();
}

// ── Optional extras ─────────────────────────────────────────────────────────
$optional_extras = [];
if (!empty($service['optional_extras'])) {
    $decoded = json_decode($service['optional_extras'], true);
    if (is_array($decoded)) $optional_extras = $decoded;
}

// ── Time slots ───────────────────────────────────────────────────────────────
$time_slots = [];
if (!empty($service['time_slots'])) {
    $decoded = json_decode($service['time_slots'], true);
    if (is_array($decoded)) $time_slots = $decoded;
}

// ── Availability days ─────────────────────────────────────────────────────────
$day_names    = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
$avail_days   = [];
if (!empty($service['availability_days'])) {
    foreach (explode(',', $service['availability_days']) as $d) {
        $d = intval(trim($d));
        if (isset($day_names[$d])) $avail_days[] = $day_names[$d];
    }
}

// ── Reviews for this service ──────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT r.*, u.full_name AS client_name, u.profile_image AS client_image
    FROM   reviews r
    JOIN   users   u ON r.client_id = u.id
    WHERE  r.provider_id = ?
    ORDER BY r.created_at DESC
    LIMIT 6
");
$stmt->execute([$service['provider_id']]);
$reviews = $stmt->fetchAll();

// ── Booking count for this service ───────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE service_id = ? AND provider_id = ?");
$stmt->execute([$service_id, $service['provider_id']]);
$service_bookings = (int) $stmt->fetchColumn();

// ── Other services by same provider ──────────────────────────────────────────
$stmt = $db->prepare("
    SELECT ps.id, ps.name, ps.price, ps.duration, ps.negotiable,
           ps.min_price, ps.max_price,
           c.name AS category_name, c.icon AS category_icon
    FROM   provider_services ps
    JOIN   categories c ON ps.category_id = c.id
    WHERE  ps.provider_id = ?
      AND  ps.id != ?
      AND  ps.is_available = 1
    ORDER BY ps.created_at DESC
    LIMIT 4
");
$stmt->execute([$service['provider_id'], $service_id]);
$other_services = $stmt->fetchAll();

// ── Check if already favorited ───────────────────────────────────────────────
$is_favorite = false;
$stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE client_id = ? AND provider_id = ?");
$stmt->execute([$_SESSION['user_id'], $service['provider_id']]);
$is_favorite = (int) $stmt->fetchColumn() > 0;

// ── Payment type labels ───────────────────────────────────────────────────────
$payment_labels = [
    'fixed_price'      => 'Fixed Price',
    'hourly_rate'      => 'Per Hour',
    'per_job_estimate' => 'Per Job Estimate',
    'per_day'          => 'Per Day',
    'base_price'       => 'Base Price',
    'per_service'      => 'Per Service',
];
$payment_label = $payment_labels[$service['payment_type']] ?? 'Per Service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['name']); ?> — <?php echo htmlspecialchars($platform_name); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --accent:        #0d6efd;
            --accent-dark:   #0a58ca;
            --accent-light:  #eff4ff;
            --success:       #16a34a;
            --success-light: #f0fdf4;
            --danger:        #dc2626;
            --danger-light:  #fef2f2;
            --warning:       #d97706;
            --warning-light: #fffbeb;
            --info:          #0891b2;
            --info-light:    #ecfeff;
            --purple:        #7c3aed;
            --purple-light:  #f5f3ff;
            --surface:       #ffffff;
            --surface-2:     #f7f8fc;
            --border:        #e8eaf0;
            --border-subtle: #f0f2f7;
            --text-primary:  #0f1117;
            --text-secondary:#6b7280;
            --text-muted:    #9ca3af;
            --sidebar-width: 260px;
            --radius-sm:  8px;
            --radius-md:  12px;
            --radius-lg:  16px;
            --radius-xl:  22px;
            --shadow-xs:  0 1px 3px rgba(0,0,0,0.06);
            --shadow-sm:  0 2px 8px rgba(0,0,0,0.07);
            --shadow-md:  0 4px 16px rgba(0,0,0,0.09);
            --shadow-lg:  0 8px 32px rgba(0,0,0,0.12);
            --transition: all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        body {
            background: var(--surface-2);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* ── SIDEBAR (matches app exactly) ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: fixed; height: 100vh; left: 0; top: 0;
            z-index: 1000; transition: var(--transition);
        }
        .sidebar-header { padding: 1.5rem 1.25rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
        .sidebar-header h2 { margin: 0; font-weight: 800; font-size: 1.1rem; color: var(--accent); }
        .sidebar-header p  { margin: .3rem 0 0; color: var(--text-muted); font-size: .78rem; }
        .sidebar-menu { list-style: none; padding: .75rem; margin: 0; }
        .sidebar-menu li { margin: 2px 0; }
        .sidebar-menu a {
            color: var(--text-secondary); text-decoration: none;
            padding: .6rem .85rem; display: flex; align-items: center; gap: .65rem;
            transition: var(--transition); border-radius: var(--radius-sm);
            font-size: .875rem; font-weight: 500;
        }
        .sidebar-menu a:hover  { background: var(--accent-light); color: var(--accent); }
        .sidebar-menu a.active { background: var(--accent); color: white; font-weight: 600; }
        .sidebar-menu i { width: 18px; font-size: .9rem; flex-shrink: 0; }

        /* ── MAIN ── */
        .main-content { margin-left: var(--sidebar-width); padding: 1.75rem 2rem; min-height: 100vh; }

        /* ── BREADCRUMB ── */
        .breadcrumb-bar {
            display: flex; align-items: center; gap: .5rem;
            font-size: .8rem; color: var(--text-muted);
            margin-bottom: 1.25rem;
        }
        .breadcrumb-bar a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .breadcrumb-bar a:hover { text-decoration: underline; }
        .breadcrumb-bar i { font-size: .65rem; }

        /* ── PAGE GRID ── */
        .page-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
        }

        /* ── CARD BASE ── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .card:last-child { margin-bottom: 0; }
        .card-title {
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .55px; color: var(--text-muted);
            display: flex; align-items: center; gap: .4rem;
            padding-bottom: .75rem; border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 1.125rem;
        }
        .card-title i { color: var(--accent); font-size: .8rem; }

        /* ── SERVICE HERO CARD ── */
        .service-hero {
            background: var(--surface);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.25rem;
        }
        .service-hero-banner {
            height: 8px;
            background: linear-gradient(90deg, var(--accent), #1e40af);
        }
        .service-hero-body { padding: 2rem; }

        .service-hero-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 1rem; flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        /* Category + negotiable badges */
        .badge-row { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .75rem; }
        .badge-cat {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
            background: var(--accent-light); color: var(--accent);
            padding: .28rem .7rem; border-radius: 100px;
        }
        .badge-negotiable {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .72rem; font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: .28rem .7rem; border-radius: 100px;
        }
        .badge-status {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .72rem; font-weight: 700;
            background: var(--success-light); color: var(--success);
            padding: .28rem .7rem; border-radius: 100px;
        }
        .badge-status::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }

        .service-name-main {
            font-size: 1.7rem; font-weight: 800; letter-spacing: -.5px;
            color: var(--text-primary); line-height: 1.2; margin-bottom: .5rem;
        }
        .service-desc-main {
            font-size: .97rem; color: var(--text-secondary);
            line-height: 1.7; margin-bottom: 0;
        }

        /* Price block */
        .price-block { text-align: right; flex-shrink: 0; }
        .price-main { font-size: 2rem; font-weight: 800; color: var(--accent); line-height: 1; font-variant-numeric: tabular-nums; }
        .price-range { font-size: 1rem; font-weight: 700; color: var(--purple); line-height: 1.2; }
        .price-sub { font-size: .72rem; color: var(--text-muted); font-weight: 600; margin-top: .2rem; }

        /* Meta chips row */
        .meta-chips { display: flex; flex-wrap: wrap; gap: .6rem; margin: 1.25rem 0; }
        .meta-chip {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: .45rem .85rem;
            font-size: .8rem; color: var(--text-secondary); font-weight: 600;
        }
        .meta-chip i { color: var(--accent); font-size: .78rem; }

        /* ── SECTION DIVIDER ── */
        .section-divider {
            border: none; border-top: 1px solid var(--border-subtle); margin: 1.5rem 0;
        }

        /* ── EXTRAS TABLE ── */
        .extras-list { display: flex; flex-direction: column; gap: .45rem; }
        .extra-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: .65rem .875rem;
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: .875rem;
        }
        .extra-item-name { font-weight: 600; color: var(--text-primary); }
        .extra-item-price { font-weight: 800; color: var(--success); font-size: .875rem; white-space: nowrap; }

        /* ── TIME SLOTS ── */
        .slots-grid { display: flex; flex-wrap: wrap; gap: .45rem; }
        .slot-pill {
            background: var(--info-light); color: var(--info);
            border: 1px solid #a5f3fc; border-radius: 100px;
            padding: .3rem .8rem; font-size: .78rem; font-weight: 700;
            display: flex; align-items: center; gap: .3rem;
        }
        .slot-pill i { font-size: .68rem; }

        /* ── DAYS GRID ── */
        .days-grid { display: flex; flex-wrap: wrap; gap: .4rem; }
        .day-chip {
            padding: .3rem .75rem; border-radius: 100px;
            font-size: .75rem; font-weight: 700;
            background: var(--success-light); color: var(--success);
            border: 1px solid #bbf7d0;
        }

        /* ── REVIEWS ── */
        .review-card {
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md); padding: 1rem 1.125rem;
            margin-bottom: .75rem; transition: var(--transition);
        }
        .review-card:hover { border-color: var(--accent); box-shadow: var(--shadow-xs); }
        .review-card:last-child { margin-bottom: 0; }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .6rem; }
        .reviewer-row { display: flex; align-items: center; gap: .7rem; }
        .reviewer-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--accent); color: white; font-weight: 700; font-size: .9rem;
            display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;
        }
        .reviewer-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .reviewer-name { font-weight: 700; font-size: .875rem; color: var(--text-primary); }
        .review-date { font-size: .72rem; color: var(--text-muted); white-space: nowrap; }
        .review-stars { color: #f59e0b; font-size: .8rem; margin: .15rem 0 .4rem; }
        .review-text { font-size: .875rem; color: var(--text-secondary); line-height: 1.6; margin: 0; }

        /* ── OTHER SERVICES GRID ── */
        .other-services-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
        .other-service-card {
            border: 1px solid var(--border); border-radius: var(--radius-md);
            padding: 1rem; transition: var(--transition); text-decoration: none; color: inherit;
            display: flex; flex-direction: column; gap: .3rem;
        }
        .other-service-card:hover { border-color: var(--accent); box-shadow: var(--shadow-sm); color: inherit; text-decoration: none; transform: translateY(-2px); }
        .other-service-cat { font-size: .68rem; font-weight: 700; text-transform: uppercase; color: var(--accent); display: flex; align-items: center; gap: .25rem; }
        .other-service-name { font-size: .875rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
        .other-service-price { font-size: .85rem; font-weight: 800; color: var(--success); }

        /* ── RIGHT COLUMN ── */
        .right-col { display: flex; flex-direction: column; gap: 1.25rem; position: sticky; top: 1.75rem; }

        /* ── BOOKING CTA CARD ── */
        .booking-cta-card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            border: 1.5px solid var(--accent);
            box-shadow: 0 4px 20px rgba(13,110,253,.12);
            overflow: hidden;
        }
        .booking-cta-header {
            background: linear-gradient(135deg, var(--accent), #1e40af);
            padding: 1.25rem 1.5rem; color: white;
        }
        .booking-cta-header h3 { margin: 0; font-size: 1.05rem; font-weight: 800; }
        .booking-cta-header p  { margin: .3rem 0 0; opacity: .88; font-size: .8rem; }
        .booking-cta-body { padding: 1.5rem; }

        .btn-book-primary {
            width: 100%; padding: .875rem 1.5rem;
            background: var(--accent); color: white; border: none;
            border-radius: var(--radius-md); font-size: .975rem; font-weight: 800;
            font-family: inherit; cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            text-decoration: none;
        }
        .btn-book-primary:hover { background: var(--accent-dark); color: white; transform: translateY(-2px); box-shadow: var(--shadow-md); text-decoration: none; }

        .btn-offer-primary {
            width: 100%; padding: .875rem 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none;
            border-radius: var(--radius-md); font-size: .975rem; font-weight: 800;
            font-family: inherit; cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            text-decoration: none;
        }
        .btn-offer-primary:hover { background: linear-gradient(135deg, #764ba2, #667eea); color: white; transform: translateY(-2px); box-shadow: var(--shadow-md); text-decoration: none; }

        .btn-outline-accent {
            width: 100%; padding: .65rem 1.5rem;
            background: transparent; color: var(--accent); border: 1.5px solid var(--accent);
            border-radius: var(--radius-md); font-size: .875rem; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            text-decoration: none; margin-top: .75rem;
        }
        .btn-outline-accent:hover { background: var(--accent-light); color: var(--accent-dark); text-decoration: none; }

        .cta-price-summary {
            background: var(--surface-2); border-radius: var(--radius-md);
            border: 1px solid var(--border); padding: 1rem 1.125rem;
            margin-bottom: 1.25rem;
        }
        .cta-price-row { display: flex; justify-content: space-between; align-items: center; padding: .3rem 0; }
        .cta-price-label { font-size: .82rem; color: var(--text-secondary); }
        .cta-price-value { font-size: .875rem; font-weight: 700; color: var(--text-primary); }
        .cta-price-total { font-size: 1.05rem; font-weight: 800; color: var(--accent); }
        .cta-divider { border: none; border-top: 1px dashed var(--border); margin: .5rem 0; }

        /* ── PROVIDER MINI CARD ── */
        .provider-mini-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            padding: 1.25rem;
        }
        .provider-mini-inner { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .provider-mini-avatar {
            width: 56px; height: 56px; border-radius: var(--radius-md);
            background: var(--accent); color: white; font-size: 1.35rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
            border: 2px solid var(--accent-light);
        }
        .provider-mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .provider-mini-name { font-size: 1rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: .3rem; }
        .provider-mini-name .verified-icon { color: var(--success); font-size: .75rem; }
        .provider-mini-job { font-size: .78rem; color: var(--accent); font-weight: 600; }
        .provider-mini-loc { font-size: .75rem; color: var(--text-muted); display: flex; align-items: center; gap: .25rem; margin-top: .15rem; }
        .provider-mini-loc i { font-size: .65rem; color: var(--accent); }

        .provider-stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-bottom: 1rem; }
        .provider-stat-box { text-align: center; background: var(--surface-2); border-radius: var(--radius-sm); padding: .6rem .4rem; border: 1px solid var(--border-subtle); }
        .provider-stat-num { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); line-height: 1; }
        .provider-stat-lbl { font-size: .62rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .3px; margin-top: .2rem; }

        .btn-view-profile {
            width: 100%; padding: .55rem; background: var(--surface-2);
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            font-size: .8rem; font-weight: 700; color: var(--text-secondary);
            font-family: inherit; cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            text-decoration: none;
        }
        .btn-view-profile:hover { background: var(--accent-light); color: var(--accent); border-color: var(--accent); text-decoration: none; }

        /* Availability pill */
        .avail-pill {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .25rem .65rem; border-radius: 100px; font-size: .72rem; font-weight: 700;
        }
        .avail-pill.available   { background: var(--success-light); color: var(--success); border: 1px solid #bbf7d0; }
        .avail-pill.busy        { background: var(--warning-light); color: var(--warning); border: 1px solid #fde68a; }
        .avail-pill.unavailable { background: var(--danger-light);  color: var(--danger);  border: 1px solid #fecaca; }
        .avail-pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }

        /* Favorite btn */
        .btn-fav {
            display: flex; align-items: center; gap: .4rem;
            background: transparent; border: 1.5px solid var(--border);
            border-radius: var(--radius-sm); padding: .45rem .875rem;
            font-size: .78rem; font-weight: 700; font-family: inherit;
            color: var(--text-secondary); cursor: pointer; transition: var(--transition);
            text-decoration: none;
        }
        .btn-fav:hover, .btn-fav.active { border-color: #f43f5e; color: #f43f5e; background: #fff1f2; }
        .btn-fav.active i { font-weight: 900; }

        /* ── EMPTY STATES ── */
        .empty-mini { text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: .82rem; }
        .empty-mini i { font-size: 1.5rem; display: block; margin-bottom: .4rem; color: var(--border); }

        /* ── STAR RATING DISPLAY ── */
        .stars { color: #f59e0b; font-size: .85rem; }
        .stars-sm { font-size: .75rem; }

        /* ── OFFER MODAL ── */
        .offer-modal-bg {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); backdrop-filter: blur(4px);
            z-index: 2000; align-items: center; justify-content: center; padding: 1rem;
        }
        .offer-modal-bg.active { display: flex; }
        .offer-modal-box {
            background: var(--surface); border-radius: var(--radius-xl);
            width: 100%; max-width: 480px; box-shadow: var(--shadow-lg);
            overflow: hidden; animation: modalPop .22s cubic-bezier(.4,0,.2,1);
        }
        @keyframes modalPop { from { transform: scale(.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .offer-modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 1.25rem 1.5rem; color: white;
            display: flex; justify-content: space-between; align-items: center;
        }
        .offer-modal-header h4 { margin: 0; font-size: 1rem; font-weight: 800; }
        .offer-modal-close { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: .9rem; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
        .offer-modal-close:hover { background: rgba(255,255,255,.28); }
        .offer-modal-body { padding: 1.5rem; }
        .offer-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); background: var(--surface-2); display: flex; gap: .75rem; justify-content: flex-end; }

        .form-label-sm { font-size: .8rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: .35rem; }
        .form-control-sm {
            width: 100%; padding: .6rem .875rem;
            border: 1.5px solid var(--border); border-radius: var(--radius-sm);
            font-family: inherit; font-size: .9rem; color: var(--text-primary);
            transition: var(--transition);
        }
        .form-control-sm:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(13,110,253,.08); }
        .price-range-hint { font-size: .72rem; color: var(--text-muted); margin-top: .3rem; }
        .price-validation-msg { font-size: .72rem; color: var(--danger); margin-top: .3rem; display: none; }

        .btn-submit-offer {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            border: none; border-radius: var(--radius-sm); padding: .6rem 1.5rem;
            font-size: .875rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: var(--transition);
        }
        .btn-submit-offer:hover { opacity: .9; transform: translateY(-1px); }
        .btn-cancel-modal {
            background: var(--surface); color: var(--text-secondary);
            border: 1px solid var(--border); border-radius: var(--radius-sm); padding: .6rem 1.25rem;
            font-size: .875rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: var(--transition);
        }
        .btn-cancel-modal:hover { background: var(--surface-2); }

        /* ── MOBILE ── */
        .mobile-menu-toggle {
            display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1100;
            background: var(--accent); color: white; border: none;
            border-radius: var(--radius-sm); width: 42px; height: 42px;
            align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-md);
        }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 999; }
        .overlay.active { display: block; }

        @media (max-width: 1100px) { .page-grid { grid-template-columns: 1fr; } .right-col { position: static; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,.12); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .service-hero-body { padding: 1.25rem; }
            .service-name-main { font-size: 1.3rem; }
            .price-block { text-align: left; width: 100%; }
            .other-services-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<button class="mobile-menu-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

    <!-- Breadcrumb -->
    <div class="breadcrumb-bar">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <a href="services.php">Services</a>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($service['name']); ?></span>
    </div>

    <div class="page-grid">

        <!-- ═══════════ LEFT COLUMN ═══════════ -->
        <div>

            <!-- Service Hero -->
            <div class="service-hero">
                <div class="service-hero-banner"></div>
                <div class="service-hero-body">

                    <div class="service-hero-top">
                        <div style="flex:1;">
                            <div class="badge-row">
                                <span class="badge-cat">
                                    <i class="fas <?php echo htmlspecialchars($service['category_icon'] ?? 'fa-briefcase'); ?>"></i>
                                    <?php echo htmlspecialchars($service['category_name']); ?>
                                </span>
                                <?php if ($service['negotiable']): ?>
                                    <span class="badge-negotiable"><i class="fas fa-handshake"></i> Negotiable</span>
                                <?php endif; ?>
                                <span class="badge-status">Available</span>
                            </div>
                            <h1 class="service-name-main"><?php echo htmlspecialchars($service['name']); ?></h1>
                        </div>
                        <div class="price-block">
                            <?php if ($service['negotiable'] && $service['min_price'] && $service['max_price']): ?>
                                <div class="price-range">
                                    RWF <?php echo number_format($service['min_price'], 0); ?> – <?php echo number_format($service['max_price'], 0); ?>
                                </div>
                                <div class="price-sub">Negotiable price range</div>
                            <?php else: ?>
                                <div class="price-main">RWF <?php echo number_format($service['price'], 0); ?></div>
                                <div class="price-sub"><?php echo htmlspecialchars($payment_label); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Meta chips -->
                    <div class="meta-chips">
                        <span class="meta-chip"><i class="fas fa-clock"></i> <?php echo (int)$service['duration']; ?> minutes</span>
                        <span class="meta-chip"><i class="fas fa-calendar-check"></i> <?php echo $service_bookings; ?> bookings</span>
                        <?php if ($service['working_hours_start'] && $service['working_hours_end']): ?>
                            <span class="meta-chip">
                                <i class="fas fa-business-time"></i>
                                <?php echo date('g:i A', strtotime($service['working_hours_start'])); ?> –
                                <?php echo date('g:i A', strtotime($service['working_hours_end'])); ?>
                            </span>
                        <?php endif; ?>
                        <span class="meta-chip"><i class="fas fa-credit-card"></i> <?php echo htmlspecialchars($payment_label); ?></span>
                        <?php if (!empty($service['booking_mode'])): ?>
                            <span class="meta-chip">
                                <i class="fas fa-<?php echo $service['booking_mode'] === 'instant' ? 'bolt' : 'hourglass-half'; ?>"></i>
                                <?php echo $service['booking_mode'] === 'instant' ? 'Instant Booking' : 'Request Approval'; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <p class="service-desc-main"><?php echo nl2br(htmlspecialchars($service['description'] ?: 'No description provided for this service.')); ?></p>
                </div>
            </div>

            <!-- Available Days -->
            <?php if (!empty($avail_days)): ?>
            <div class="card">
                <div class="card-title"><i class="fas fa-calendar-alt"></i> Available Days</div>
                <div class="days-grid">
                    <?php foreach ($avail_days as $day): ?>
                        <span class="day-chip"><?php echo $day; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Time Slots -->
            <?php if (!empty($time_slots)): ?>
            <div class="card">
                <div class="card-title"><i class="fas fa-clock"></i> Available Time Slots</div>
                <div class="slots-grid">
                    <?php foreach ($time_slots as $slot): ?>
                        <span class="slot-pill">
                            <i class="fas fa-clock"></i>
                            <?php echo htmlspecialchars(date('g:i A', strtotime($slot['start'])) . ' – ' . date('g:i A', strtotime($slot['end']))); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Optional Extras -->
            <?php if (!empty($optional_extras)): ?>
            <div class="card">
                <div class="card-title"><i class="fas fa-plus-circle"></i> Optional Add-ons</div>
                <div class="extras-list">
                    <?php foreach ($optional_extras as $extra): ?>
                        <div class="extra-item">
                            <span class="extra-item-name"><i class="fas fa-check-circle" style="color:var(--success);margin-right:.4rem;"></i><?php echo htmlspecialchars($extra['label']); ?></span>
                            <span class="extra-item-price">+ RWF <?php echo number_format((float)$extra['price'], 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reviews -->
            <div class="card">
                <div class="card-title"><i class="fas fa-star"></i> Client Reviews (<?php echo count($reviews); ?>)</div>

                <?php if (empty($reviews)): ?>
                    <div class="empty-mini">
                        <i class="fas fa-star"></i>
                        No reviews yet for this provider. Be the first!
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-row">
                                    <div class="reviewer-avatar">
                                        <?php if (!empty($review['client_image'])): ?>
                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($review['client_image']); ?>"
                                                 alt="<?php echo htmlspecialchars($review['client_name']); ?>"
                                                 onerror="this.style.display='none';this.parentNode.textContent='<?php echo strtoupper(substr($review['client_name'],0,1)); ?>';">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($review['client_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="reviewer-name"><?php echo htmlspecialchars($review['client_name']); ?></div>
                                        <div class="review-stars stars-sm">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                            </div>
                            <p class="review-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Other services by provider -->
            <?php if (!empty($other_services)): ?>
            <div class="card">
                <div class="card-title"><i class="fas fa-th-large"></i> More Services by <?php echo htmlspecialchars($service['provider_name']); ?></div>
                <div class="other-services-grid">
                    <?php foreach ($other_services as $os): ?>
                        <a href="service.php?service_id=<?php echo $os['id']; ?>" class="other-service-card"
                           onclick="trackClick('other_service_click','service',<?php echo $os['id']; ?>)">
                            <div class="other-service-cat">
                                <i class="fas <?php echo htmlspecialchars($os['category_icon'] ?? 'fa-briefcase'); ?>"></i>
                                <?php echo htmlspecialchars($os['category_name']); ?>
                            </div>
                            <div class="other-service-name"><?php echo htmlspecialchars($os['name']); ?></div>
                            <div class="other-service-price">
                                <?php if ($os['negotiable'] && $os['min_price'] && $os['max_price']): ?>
                                    RWF <?php echo number_format($os['min_price'],0); ?> – <?php echo number_format($os['max_price'],0); ?>
                                <?php else: ?>
                                    RWF <?php echo number_format($os['price'],0); ?>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:.7rem;color:var(--text-muted);"><?php echo (int)$os['duration']; ?> mins</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <!-- /LEFT COLUMN -->

        <!-- ═══════════ RIGHT COLUMN ═══════════ -->
        <div class="right-col">

            <!-- Booking CTA -->
            <div class="booking-cta-card">
                <div class="booking-cta-header">
                    <h3><i class="fas fa-calendar-plus me-2"></i> Book This Service</h3>
                    <p>Request a booking directly with the provider</p>
                </div>
                <div class="booking-cta-body">

                    <!-- Price summary -->
                    <div class="cta-price-summary">
                        <?php if ($service['negotiable'] && $service['min_price'] && $service['max_price']): ?>
                            <div class="cta-price-row">
                                <span class="cta-price-label">Price Range</span>
                                <span class="cta-price-value" style="color:var(--purple);">
                                    RWF <?php echo number_format($service['min_price'],0); ?> – <?php echo number_format($service['max_price'],0); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="cta-price-row">
                                <span class="cta-price-label">Service Price</span>
                                <span class="cta-price-value">RWF <?php echo number_format($service['price'],0); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="cta-price-row">
                            <span class="cta-price-label">Duration</span>
                            <span class="cta-price-value"><?php echo (int)$service['duration']; ?> minutes</span>
                        </div>
                        <div class="cta-price-row">
                            <span class="cta-price-label">Payment</span>
                            <span class="cta-price-value"><?php echo htmlspecialchars($payment_label); ?></span>
                        </div>
                        <?php if (!empty($optional_extras)): ?>
                            <hr class="cta-divider">
                            <div class="cta-price-row">
                                <span class="cta-price-label" style="color:var(--text-muted);font-size:.75rem;">Optional add-ons available</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($service['negotiable']): ?>
                        <!-- Negotiable: offer button -->
                        <button class="btn-offer-primary" onclick="trackClick('open_offer_modal','service',<?php echo $service_id; ?>); openOfferModal()">
                            <i class="fas fa-handshake"></i> Send a Price Offer
                        </button>
                        <a href="booking.php?provider_id=<?php echo $service['provider_id']; ?>&service_id=<?php echo $service_id; ?>"
                           class="btn-outline-accent"
                           onclick="trackClick('book_now','service',<?php echo $service_id; ?>)">
                            <i class="fas fa-calendar"></i> Standard Booking
                        </a>
                    <?php else: ?>
                        <!-- Fixed price: direct book -->
                        <a href="booking.php?provider_id=<?php echo $service['provider_id']; ?>&service_id=<?php echo $service_id; ?>"
                           class="btn-book-primary"
                           onclick="trackClick('book_now','service',<?php echo $service_id; ?>)">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    <?php endif; ?>

                    <!-- Favorite -->
                    <form method="POST" action="provider-profile.php?id=<?php echo $service['provider_id']; ?>" style="margin-top:.75rem;">
                        <input type="hidden" name="toggle_favorite" value="1">
                        <button type="submit" class="btn-fav <?php echo $is_favorite ? 'active' : ''; ?>"
                                onclick="trackClick('favorite_toggle','provider',<?php echo $service['provider_id']; ?>,{service_id:<?php echo $service_id; ?>})">
                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                            <?php echo $is_favorite ? 'Remove from Favorites' : 'Save to Favorites'; ?>
                        </button>
                    </form>

                </div>
            </div>

            <!-- Provider Card -->
            <div class="provider-mini-card">
                <div class="card-title" style="border:none;padding-bottom:.6rem;margin-bottom:.875rem;">
                    <i class="fas fa-user-circle"></i> About the Provider
                </div>

                <div class="provider-mini-inner">
                    <div class="provider-mini-avatar">
                        <?php if (!empty($service['provider_image'])): ?>
                            <img src="../uploads/profiles/<?php echo htmlspecialchars($service['provider_image']); ?>"
                                 alt="<?php echo htmlspecialchars($service['provider_name']); ?>"
                                 onerror="this.style.display='none';this.parentNode.textContent='<?php echo strtoupper(substr($service['provider_name'],0,1)); ?>';">
                        <?php else: ?>
                            <?php echo strtoupper(substr($service['provider_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="provider-mini-name">
                            <?php echo htmlspecialchars($service['provider_name']); ?>
                            <?php if ($service['sp_verified'] || $service['user_verified']): ?>
                                <i class="fas fa-check-circle verified-icon" title="Verified"></i>
                            <?php endif; ?>
                        </div>
                        <div class="provider-mini-job"><?php echo htmlspecialchars($service['category_name']); ?> Expert</div>
                        <div class="provider-mini-loc">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($service['location']); ?>
                            <?php if ($service['district']): ?>, <?php echo htmlspecialchars($service['district']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Provider stats -->
                <div class="provider-stat-row">
                    <div class="provider-stat-box">
                        <div class="provider-stat-num"><?php echo number_format($service['average_rating'], 1); ?></div>
                        <div class="provider-stat-lbl">Rating</div>
                    </div>
                    <div class="provider-stat-box">
                        <div class="provider-stat-num"><?php echo (int)$service['total_reviews']; ?></div>
                        <div class="provider-stat-lbl">Reviews</div>
                    </div>
                    <div class="provider-stat-box">
                        <div class="provider-stat-num"><?php echo (int)$service['total_jobs'] ?: (int)$service['experience_years'] . 'y'; ?></div>
                        <div class="provider-stat-lbl"><?php echo $service['total_jobs'] ? 'Jobs' : 'Exp'; ?></div>
                    </div>
                </div>

                <!-- Star display -->
                <div style="text-align:center;margin-bottom:.875rem;">
                    <span class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="<?php echo $i <= round($service['average_rating']) ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                    </span>
                    <span style="font-size:.78rem;color:var(--text-muted);margin-left:.3rem;">(<?php echo (int)$service['total_reviews']; ?> reviews)</span>
                </div>

                <!-- Availability -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.875rem;">
                    <span style="font-size:.78rem;font-weight:600;color:var(--text-secondary);">Status</span>
                    <span class="avail-pill <?php echo htmlspecialchars($service['availability'] ?? 'available'); ?>">
                        <?php echo ucfirst($service['availability'] ?? 'Available'); ?>
                    </span>
                </div>

                <a href="provider-profile.php?id=<?php echo $service['provider_id']; ?>" class="btn-view-profile"
                   onclick="trackClick('view_provider_profile','provider',<?php echo $service['provider_id']; ?>,{service_id:<?php echo $service_id; ?>})">
                    <i class="fas fa-eye"></i> View Full Profile
                </a>
            </div>

            <!-- Working hours card -->
            <?php if ($service['working_hours_start'] && $service['working_hours_end']): ?>
            <div class="card" style="margin-bottom:0;">
                <div class="card-title"><i class="fas fa-business-time"></i> Working Hours</div>
                <div style="font-size:.875rem;color:var(--text-secondary);">
                    <div style="display:flex;justify-content:space-between;padding:.3rem 0;border-bottom:1px solid var(--border-subtle);">
                        <span>Working Hours</span>
                        <strong style="color:var(--text-primary);">
                            <?php echo date('g:i A', strtotime($service['working_hours_start'])); ?> –
                            <?php echo date('g:i A', strtotime($service['working_hours_end'])); ?>
                        </strong>
                    </div>
                    <?php if (!empty($avail_days)): ?>
                    <div style="display:flex;justify-content:space-between;padding:.45rem 0;">
                        <span>Days Available</span>
                        <strong style="color:var(--text-primary);font-size:.78rem;text-align:right;max-width:55%;">
                            <?php echo implode(', ', array_map(fn($d) => substr($d,0,3), $avail_days)); ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <!-- /RIGHT COLUMN -->

    </div>
</div>

<!-- ═══════════ OFFER MODAL ═══════════ -->
<?php if ($service['negotiable']): ?>
<div class="offer-modal-bg" id="offerModal">
    <div class="offer-modal-box">
        <div class="offer-modal-header">
            <h4><i class="fas fa-handshake me-2"></i> Send a Price Offer</h4>
            <button class="offer-modal-close" onclick="closeOfferModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="offer-modal-body">
            <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:.875rem 1rem;margin-bottom:1.25rem;">
                <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.3rem;">Service</div>
                <div style="font-weight:800;font-size:.97rem;color:var(--text-primary);"><?php echo htmlspecialchars($service['name']); ?></div>
                <div style="font-size:.78rem;color:var(--purple);font-weight:700;margin-top:.25rem;">
                    Negotiable: RWF <?php echo number_format($service['min_price'],0); ?> – RWF <?php echo number_format($service['max_price'],0); ?>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="form-label-sm" for="offerPrice">Your Offer Price (RWF) <span style="color:var(--danger);">*</span></label>
                <div style="display:flex;align-items:center;border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;transition:var(--transition);" id="offerPriceWrap">
                    <span style="background:var(--surface-2);padding:.6rem .875rem;font-size:.85rem;font-weight:700;color:var(--text-muted);border-right:1px solid var(--border);">RWF</span>
                    <input type="number" id="offerPrice" class="form-control-sm"
                           style="border:none;border-radius:0;flex:1;"
                           min="<?php echo $service['min_price']; ?>"
                           max="<?php echo $service['max_price']; ?>"
                           step="100"
                           placeholder="<?php echo number_format($service['min_price'],0); ?>"
                           oninput="validateOfferPrice(this)">
                </div>
                <div class="price-range-hint">Must be between RWF <?php echo number_format($service['min_price'],0); ?> and RWF <?php echo number_format($service['max_price'],0); ?></div>
                <div class="price-validation-msg" id="offerPriceError"><i class="fas fa-exclamation-circle"></i> Price must be within the allowed range.</div>
            </div>

            <div>
                <label class="form-label-sm" for="offerNote">Message to Provider (Optional)</label>
                <textarea id="offerNote" class="form-control-sm" rows="3"
                          placeholder="E.g. I need this done urgently by Friday..."></textarea>
            </div>
        </div>
        <div class="offer-modal-footer">
            <button class="btn-cancel-modal" onclick="closeOfferModal()">Cancel</button>
            <button class="btn-submit-offer" onclick="submitOffer()">
                <i class="fas fa-paper-plane me-1"></i> Send Offer
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast container -->
<div id="toastContainer" style="position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;"></div>

<?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Mobile sidebar ─────────────────────────────────────────────────────
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar       = document.querySelector('.sidebar');
    const overlay       = document.getElementById('overlay');

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar && sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }

    // ── Toast helper ───────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const colors = { success:'#16a34a', danger:'#dc2626', info:'#0891b2', warning:'#d97706' };
        const icons  = { success:'check-circle', danger:'exclamation-circle', info:'info-circle', warning:'exclamation-triangle' };
        const t = document.createElement('div');
        t.style.cssText = `background:${colors[type]||colors.info};color:#fff;padding:.75rem 1.1rem;border-radius:10px;font-size:.875rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);min-width:260px;display:flex;align-items:center;gap:.5rem;animation:none;font-family:inherit;`;
        t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
    }

    // ── Offer modal ────────────────────────────────────────────────────────
    function openOfferModal() {
        trackClick('open_offer_modal', 'service', <?php echo $service_id; ?>);
        document.getElementById('offerModal')?.classList.add('active');
        document.body.style.overflow = 'hidden';
        document.getElementById('offerPrice')?.focus();
    }

    function closeOfferModal() {
        trackClick('close_offer_modal', 'service', <?php echo $service_id; ?>);
        document.getElementById('offerModal')?.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close on backdrop click
    document.getElementById('offerModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeOfferModal();
    });

    function validateOfferPrice(input) {
        const min     = <?php echo (float)($service['min_price'] ?? 0); ?>;
        const max     = <?php echo (float)($service['max_price'] ?? 0); ?>;
        const val     = parseFloat(input.value);
        const errEl   = document.getElementById('offerPriceError');
        const wrapEl  = document.getElementById('offerPriceWrap');

        if (!input.value) { errEl.style.display='none'; wrapEl.style.borderColor='var(--border)'; return; }

        if (val < min || val > max) {
            errEl.style.display = 'block';
            wrapEl.style.borderColor = 'var(--danger)';
        } else {
            errEl.style.display = 'none';
            wrapEl.style.borderColor = 'var(--success)';
        }
    }

    function submitOffer() {
        const price   = parseFloat(document.getElementById('offerPrice').value);
        const note    = document.getElementById('offerNote').value.trim();
        const min     = <?php echo (float)($service['min_price'] ?? 0); ?>;
        const max     = <?php echo (float)($service['max_price'] ?? 0); ?>;
        const svcId   = <?php echo $service_id; ?>;

        trackClick('submit_offer', 'service', svcId, {
            price: price || null,
            note: note ? note.substring(0, 100) : null
        });

        if (!price || price < min || price > max) {
            showToast('Please enter a valid price within the allowed range.', 'danger');
            return;
        }

        const body = new URLSearchParams({
            action:        'create_offer',
            service_id:    svcId,
            offered_price: price,
            notes:         note
        });

        fetch('../api/service_offers.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeOfferModal();
                showToast('✅ Offer sent! The provider will review it shortly.', 'success');
                // Reset
                document.getElementById('offerPrice').value = '';
                document.getElementById('offerNote').value  = '';
            } else {
                showToast(data.message || 'Failed to send offer. Please try again.', 'danger');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'danger'));
    }
</script>
</body>
</html>