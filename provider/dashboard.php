<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';
require_once '../includes/language.php';

requireProvider();

if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();

// Check if AI features are enabled for this provider
$provider_ai_enabled = false;
$stmt = $db->prepare("SELECT user_id FROM service_providers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($provider_stmt = $stmt->fetch()) {
    $provider_ai_enabled = isProviderAIEnabled((int)$_SESSION['user_id']);
}

// ── Provider base data ────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

$pid = (int)$provider['id'];

// ── Core stats ────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ?");
$stmt->execute([$pid]); $total_bookings = (int)$stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$pid]); $pending_bookings = (int)$stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE provider_id = ?");
$stmt->execute([$pid]); $total_reviews = (int)$stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ? AND status = 'completed'");
$stmt->execute([$pid]); $completed_bookings = (int)$stmt->fetch()['total'];

// ── Step 1: ML Hire Probability ───────────────────────────────────────────
$ml_score = 0;
$ml_api_healthy = false;
try {
    if (file_exists('../includes/MultiModelRecommender.php')) {
        require_once '../includes/MultiModelRecommender.php';
        $recommender = new MultiModelRecommender($db);
        $ml_api_healthy = $recommender->isApiHealthy();
        if (!$ml_api_healthy && method_exists($recommender, 'getLastError')) {
            $mlError = $recommender->getLastError();
            if ($mlError) {
                error_log('[dashboard.php] ML health check failed: ' . $mlError);
            }
        }
        if ($ml_api_healthy) {
            $features = [
                'views'   => (int)$db->query("SELECT COUNT(*) FROM provider_views WHERE provider_id=$pid")->fetchColumn(),
                'clicks'  => (int)$db->query("SELECT COUNT(*) FROM click_logs WHERE target_type='provider' AND target_id=$pid")->fetchColumn(),
                'messages'=> (int)$db->query("SELECT COUNT(*) FROM messages WHERE receiver_id={$provider['user_id']}")->fetchColumn(),
                'rating'  => (float)($provider['average_rating'] ?? 0),
                'price'   => (float)($db->query("SELECT AVG(price) FROM provider_services WHERE provider_id=$pid AND is_available=1")->fetchColumn() ?? 0),
                'avg_response_time' => 24,
                'user_avg_price' => 0,
                'user_avg_response_time' => 24,
                'user_total_bookings' => 0,
            ];
            // Normalise to 0-100 probability
            $raw = (float)($recommender->rankByRecommendation([$provider + ['id'=>$pid]])[0]['ml_score'] ?? 0);
            $ml_score = min(100, max(0, (int)round($raw * 100)));
        }
    }
} catch (Throwable $e) {
    error_log('ML score error: ' . $e->getMessage());
}
// Fallback: derive from rating + response rate when API is down
if (!$ml_api_healthy || $ml_score === 0) {
    $rating_score  = min(100, (float)($provider['average_rating'] ?? 0) / 5 * 40);
    $booking_score = min(40, $completed_bookings * 2);
    $review_score  = min(20, $total_reviews);
    $ml_score = (int)round($rating_score + $booking_score + $review_score);
}

// ── Step 2: AI Insights ───────────────────────────────────────────────────
// Weekly views growth
$views_this_week = (int)$db->query("SELECT COUNT(*) FROM provider_views WHERE provider_id=$pid AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$views_last_week = (int)$db->query("SELECT COUNT(*) FROM provider_views WHERE provider_id=$pid AND viewed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$views_growth = $views_last_week > 0 ? round((($views_this_week - $views_last_week) / $views_last_week) * 100) : ($views_this_week > 0 ? 100 : 0);

// Avg response time (hours)
$avg_response_raw = $db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) FROM bookings WHERE provider_id=$pid AND responded_at IS NOT NULL")->fetchColumn();
$avg_response_hours = $avg_response_raw ? round((float)$avg_response_raw, 1) : null;

// Conversion: views → hires
$total_views = (int)$db->query("SELECT COUNT(*) FROM provider_views WHERE provider_id=$pid")->fetchColumn();
$conversion_rate = $total_views > 0 ? round(($completed_bookings / $total_views) * 100, 1) : 0;

// Build insight messages
$insights = [];
if ($views_growth > 0) {
    $insights[] = ['icon'=>'fas fa-eye', 'color'=>'#10b981', 'text'=>"Profile views <strong>up {$views_growth}%</strong> vs last week — great momentum!", 'type'=>'positive'];
} elseif ($views_growth < -10) {
    $insights[] = ['icon'=>'fas fa-eye-slash', 'color'=>'#f59e0b', 'text'=>"Views <strong>dropped {$views_growth}%</strong>. Try updating your profile or adding services.", 'type'=>'warning'];
}
if ($avg_response_hours !== null && $avg_response_hours > 4) {
    $insights[] = ['icon'=>'fas fa-clock', 'color'=>'#f59e0b', 'text'=>"Avg response time is <strong>{$avg_response_hours}h</strong>. Faster replies improve rankings.", 'type'=>'warning'];
} elseif ($avg_response_hours !== null && $avg_response_hours <= 2) {
    $insights[] = ['icon'=>'fas fa-bolt', 'color'=>'#10b981', 'text'=>"Lightning-fast <strong>{$avg_response_hours}h</strong> response time — clients love you!", 'type'=>'positive'];
}
if ($conversion_rate < 1 && $total_views > 10) {
    $insights[] = ['icon'=>'fas fa-funnel-dollar', 'color'=>'#ef4444', 'text'=>"Conversion rate is <strong>{$conversion_rate}%</strong>. Consider improving your service descriptions or pricing.", 'type'=>'negative'];
}
if ((float)($provider['average_rating'] ?? 0) >= 4.5) {
    $insights[] = ['icon'=>'fas fa-star', 'color'=>'#f59e0b', 'text'=>"Outstanding <strong>{$provider['average_rating']} ★</strong> rating — you're in the top tier!", 'type'=>'positive'];
}
if (empty($insights)) {
    $insights[] = ['icon'=>'fas fa-chart-line', 'color'=>'#6366f1', 'text'=>"Start getting bookings to unlock personalised AI insights.", 'type'=>'neutral'];
}

// ── Step 3: Funnel Analytics ──────────────────────────────────────────────
$funnel_views    = max(1, $total_views);
$funnel_clicks   = (int)$db->query("SELECT COUNT(*) FROM click_logs WHERE target_type='provider' AND target_id=$pid")->fetchColumn();
$funnel_messages = (int)$db->query("SELECT COUNT(*) FROM messages WHERE receiver_id={$provider['user_id']}")->fetchColumn();
$funnel_hires    = $completed_bookings;

// ── Step 4: Chart Data ────────────────────────────────────────────────────
$chart_labels = [];
$chart_views  = [];
$chart_clicks = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($date));
    $chart_labels[] = $label;
    $v = (int)$db->query("SELECT COUNT(*) FROM provider_views WHERE provider_id=$pid AND DATE(viewed_at)='$date'")->fetchColumn();
    $c = (int)$db->query("SELECT COUNT(*) FROM click_logs WHERE target_type='provider' AND target_id=$pid AND DATE(created_at)='$date'")->fetchColumn();
    $chart_views[]  = $v;
    $chart_clicks[] = $c;
}

// ── Step 5: Ranking Explanation ───────────────────────────────────────────
$rank_factors = [];
if ((float)($provider['average_rating'] ?? 0) >= 4.0) $rank_factors[] = ['icon'=>'fas fa-star', 'text'=>"High rating ({$provider['average_rating']}★) boosts your visibility", 'good'=>true];
if ($avg_response_hours !== null && $avg_response_hours <= 3) $rank_factors[] = ['icon'=>'fas fa-bolt', 'text'=>"Fast {$avg_response_hours}h response time puts you ahead", 'good'=>true];
if ($completed_bookings >= 5) $rank_factors[] = ['icon'=>'fas fa-briefcase', 'text'=>"$completed_bookings completed jobs builds trust signals", 'good'=>true];
if ((float)($provider['average_rating'] ?? 0) < 4.0) $rank_factors[] = ['icon'=>'fas fa-star-half-alt', 'text'=>"Improve rating to rank higher in search", 'good'=>false];
if ($avg_response_hours === null || $avg_response_hours > 6) $rank_factors[] = ['icon'=>'fas fa-clock', 'text'=>"Reply faster to boost your search ranking", 'good'=>false];
if (empty($rank_factors)) $rank_factors[] = ['icon'=>'fas fa-info-circle', 'text'=>"Complete more bookings to improve ranking", 'good'=>false];

// ── Step 6: Auto Optimisation Suggestions ─────────────────────────────────
$suggestions = [];
$portfolio_count = (int)$db->query("SELECT COUNT(*) FROM portfolio_images WHERE provider_id=$pid AND is_active=1")->fetchColumn();
if ($portfolio_count === 0) $suggestions[] = ['icon'=>'fas fa-images', 'text'=>'Add portfolio images to attract more clients', 'priority'=>'high'];
if ((float)($provider['average_rating'] ?? 0) < 4.0 && $total_reviews > 2) $suggestions[] = ['icon'=>'fas fa-star', 'text'=>'Ask satisfied clients to leave a review', 'priority'=>'high'];
if ($avg_response_hours !== null && $avg_response_hours > 4) $suggestions[] = ['icon'=>'fas fa-reply', 'text'=>'Enable notifications to respond faster', 'priority'=>'medium'];
if (empty($provider['bio'])) $suggestions[] = ['icon'=>'fas fa-user-edit', 'text'=>'Write a bio to build client trust', 'priority'=>'medium'];
$service_count = (int)$db->query("SELECT COUNT(*) FROM provider_services WHERE provider_id=$pid AND is_available=1")->fetchColumn();
if ($service_count < 2) $suggestions[] = ['icon'=>'fas fa-plus-circle', 'text'=>'Add more services to increase booking chances', 'priority'=>'medium'];

// ── Step 7: Notifications ─────────────────────────────────────────────────
$all_notifications = getNotifications($_SESSION['user_id'], ['limit' => 8]);
$unread_count = getUnreadNotificationCount($_SESSION['user_id']);

// ── Step 8: Provider Level ────────────────────────────────────────────────
$level = 'bronze';
$level_label = 'Bronze';
$level_next = 'Silver';
$level_icon = 'fas fa-medal';
$level_color = '#cd7f32';
if ($completed_bookings >= 10 && (float)($provider['average_rating'] ?? 0) >= 4.0) {
    $level = 'gold'; $level_label = 'Gold'; $level_next = 'Platinum'; $level_icon = 'fas fa-trophy'; $level_color = '#f59e0b';
} elseif ($completed_bookings >= 3 && (float)($provider['average_rating'] ?? 0) >= 3.5) {
    $level = 'silver'; $level_label = 'Silver'; $level_next = 'Gold'; $level_icon = 'fas fa-award'; $level_color = '#6b7280';
}
$level_progress = min(100, $level === 'bronze' ? ($completed_bookings / 3) * 100 : ($level === 'silver' ? ($completed_bookings / 10) * 100 : 100));

// ── Recent bookings ───────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as client_name, s.name as service_name,
               DATE_FORMAT(b.preferred_date,'%b %d') as fmt_date
        FROM bookings b JOIN users u ON b.client_id=u.id
        LEFT JOIN provider_services s ON b.service_id=s.id
        WHERE b.provider_id=? ORDER BY b.created_at DESC LIMIT 5
    ");
    $stmt->execute([$pid]);
    $recent_bookings = $stmt->fetchAll();
} catch (Throwable $e) { $recent_bookings = []; }

$today_bookings = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE provider_id=$pid AND DATE(preferred_date)=CURDATE() AND status IN('confirmed','pending')")->fetchColumn();

// ── Step 9: Subscription Plan Data ────────────────────────────────────────
require_once '../includes/subscription_access.php';
$plan_features = getPlanFeatures((int)$_SESSION['user_id']);
$service_count = (int)$db->query("SELECT COUNT(*) FROM provider_services WHERE provider_id=$pid AND is_available=1")->fetchColumn();
$photo_count = (int)$db->query("SELECT COUNT(*) FROM portfolio_images WHERE provider_id=$pid AND is_active=1")->fetchColumn();

// Calculate service usage percentage
$service_usage_pct = $plan_features['service_limit'] > 0 
    ? min(100, round(($service_count / $plan_features['service_limit']) * 100)) 
    : 0;
$service_usage_text = $plan_features['service_limit'] === 0 ? 'Unlimited' : "{$service_count}/{$plan_features['service_limit']}";

// Calculate photo usage percentage
$photo_usage_pct = $plan_features['photo_limit'] > 0 
    ? min(100, round(($photo_count / $plan_features['photo_limit']) * 100)) 
    : 0;
$photo_usage_text = $plan_features['photo_limit'] === 0 ? 'Unlimited' : "{$photo_count}/{$plan_features['photo_limit']}";

// Plan upgrade suggestions
$upgrade_suggestions = [];
if ($service_count >= $plan_features['service_limit'] && $plan_features['service_limit'] > 0) {
    $upgrade_suggestions[] = ['icon'=>'fas fa-plus-circle', 'text'=>'Upgrade to add more services', 'priority'=>'high'];
}
if ($photo_count >= $plan_features['photo_limit'] && $plan_features['photo_limit'] > 0) {
    $upgrade_suggestions[] = ['icon'=>'fas fa-images', 'text'=>'Upgrade to add more photos', 'priority'=>'high'];
}
if (!$plan_features['ai_enabled']) {
    $upgrade_suggestions[] = ['icon'=>'fas fa-robot', 'text'=>'Upgrade to unlock AI tools', 'priority'=>'medium'];
}
if ($plan_features['analytics_level'] === 'basic') {
    $upgrade_suggestions[] = ['icon'=>'fas fa-chart-bar', 'text'=>'Upgrade for better analytics', 'priority'=>'medium'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Dashboard — <?php echo htmlspecialchars(getPlatformName()); ?></title>
<link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/dark-mode.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
  --accent:        #0d6efd;
  --accent2:       #8b5cf6;
  --green:         #16a34a;
  --yellow:        #f59e0b;
  --red:           #dc2626;
  --surface:       #ffffff;
  --surface-2:     #f7f8fc;
  --surface2:      #f7f8fc;
  --surface-3:     #f3f4f6;
  --border:        #e8eaf0;
  --bg:            #f7f8fc;
  --text:          #0f1117;
  --muted:         #6b7280;
  --sidebar:       260px;
  --shadow-sm:     0 2px 14px rgba(15,23,42,0.06);
  --shadow-md:     0 6px 28px rgba(15,23,42,0.08);
  --radius-md:     14px;
  --transition:    all 0.18s cubic-bezier(0.4,0,0.2,1);
}
[data-theme="dark"] {
  --accent:        #60a5fa;
  --accent2:       #818cf8;
  --green:         #34d399;
  --yellow:        #facc15;
  --red:           #f87171;
  --surface:       #0f172a;
  --surface-2:     #1e293b;
  --surface2:      #1e293b;
  --surface-3:     #111827;
  --border:        #334155;
  --bg:            #1e293b;
  --text:          #f8fafc;
  --muted:         #94a3b8;
  --shadow-sm:     0 2px 14px rgba(0,0,0,0.35);
  --shadow-md:     0 6px 28px rgba(0,0,0,0.45);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body { height:100%; background:var(--surface-2); color:var(--text); font-family:'DM Sans',system-ui,sans-serif; -webkit-font-smoothing:antialiased; }
h1,h2,h3,h4,.font-display{font-family:'Syne',sans-serif}
a{color:inherit;text-decoration:none}

/* ── SIDEBAR ── */
.sidebar{
  width:var(--sidebar);position:fixed;left:0;top:0;height:100vh;
  background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;z-index:100;overflow:hidden;
}
.sidebar-brand{
  padding:1.5rem 1.25rem 1.25rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:.75rem;
}
.sidebar-brand-icon{
  width:36px;height:36px;border-radius:10px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;
  flex-shrink:0;box-shadow:0 4px 12px rgba(99,102,241,.4);
}
.sidebar-brand-text{font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;color:var(--accent)}
.sidebar-brand-sub{font-size:.72rem;color:var(--muted);margin-top:1px}
.sidebar-menu{list-style:none;padding:.75rem .75rem;flex:1;overflow-y:auto}
.sidebar-menu li{margin:2px 0}
.sidebar-menu a{
  display:flex;align-items:center;gap:.75rem;padding:.65rem .875rem;
  color:var(--muted);border-radius:10px;font-size:.85rem;font-weight:500;
  transition:all .18s ease;
}
.sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(99,102,241,.12);color:#fff}
.sidebar-menu a.active{color:var(--accent)}
.sidebar-menu i{width:18px;font-size:.9rem;flex-shrink:0}
.sidebar-footer{padding:.875rem 1.25rem;border-top:1px solid var(--border)}
.sidebar-footer small{color:var(--muted);font-size:.72rem}

/* ── MAIN CONTENT ── */
.main-content{
  margin-left:70px;
  padding:1.75rem 2rem;
  min-height:100vh;
  transition: margin-left 0.18s cubic-bezier(0.4,0,0.2,1);
}

/* ── TOP BAR ── */
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:2rem;flex-wrap:wrap;gap:1rem;
}
.topbar-left h1{
  font-size:1.5rem;font-weight:800;letter-spacing:-.5px;color:var(--text);
}
.topbar-left p{color:var(--muted);font-size:.875rem;margin-top:.25rem}
.topbar-right{display:flex;align-items:center;gap:.75rem}

/* ── NOTIFICATION BELL ── */
.notif-btn{
  position:relative;background:var(--surface2);border:1px solid var(--border);
  color:var(--text);width:40px;height:40px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;
  font-size:.95rem;transition:all .18s;
}
.notif-btn:hover{background:var(--surface3);border-color:var(--accent)}
.notif-badge{
  position:absolute;top:-6px;right:-6px;background:var(--red);
  color:#fff;border-radius:100px;min-width:18px;height:18px;
  padding:0 4px;font-size:.65rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  border:2px solid var(--bg);
}
.notif-dropdown{
  position:absolute;top:calc(100% + 10px);right:0;width:320px;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:14px;box-shadow:0 20px 48px rgba(0,0,0,.5);
  z-index:200;display:none;overflow:hidden;
}
.notif-dropdown.open{display:block}
.notif-header{
  padding:.875rem 1rem;border-bottom:1px solid var(--border);
  font-weight:700;font-size:.85rem;color:var(--text);
  display:flex;align-items:center;justify-content:space-between;
}
.notif-list{max-height:320px;overflow-y:auto}
.notif-item{
  padding:.75rem 1rem;border-bottom:1px solid var(--border);
  display:flex;gap:.75rem;align-items:flex-start;
  transition:background .15s;cursor:pointer;
}
.notif-item:hover{background:var(--surface3)}
.notif-item:last-child{border-bottom:none}
.notif-icon-wrap{
  width:32px;height:32px;border-radius:8px;flex-shrink:0;
  background:rgba(99,102,241,.15);display:flex;align-items:center;justify-content:center;
  font-size:.8rem;color:var(--accent);
}
.notif-item-text{font-size:.78rem;color:var(--muted);line-height:1.4}
.notif-item-title{font-size:.82rem;font-weight:600;color:var(--text);margin-bottom:2px}
.notif-item-time{font-size:.7rem;color:var(--muted);margin-top:2px}

/* ── LEVEL BADGE ── */
.level-badge{
  display:flex;align-items:center;gap:.5rem;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:100px;padding:.4rem .875rem;
  font-size:.78rem;font-weight:700;
}
.level-dot{width:8px;height:8px;border-radius:50%}

/* ── STATS GRID ── */
.stats-grid{
  display:grid;grid-template-columns:repeat(4,1fr);
  gap:1.25rem;margin-bottom:1.75rem;
}
.stat-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:1.25rem 1.5rem;
  transition:transform .2s,box-shadow .2s;
  position:relative;overflow:hidden;
}
.stat-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:var(--card-accent,var(--accent));
  border-radius:16px 16px 0 0;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.35)}
.stat-icon{
  width:42px;height:42px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;margin-bottom:.875rem;
  background:rgba(255,255,255,.05);
}
.stat-value{font-size:2rem;font-weight:800;font-family:'Syne',sans-serif;letter-spacing:-1px;color:var(--text)}
.stat-label{font-size:.75rem;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.4px;margin-top:.25rem}
.stat-change{font-size:.75rem;margin-top:.5rem;display:flex;align-items:center;gap:.25rem;font-weight:600}
.stat-change.up{color:var(--green)}
.stat-change.down{color:var(--red)}
.stat-change.neutral{color:var(--muted)}

/* ── ML SCORE ── */
.ml-score-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:1.5rem;
  display:grid;grid-template-columns:1fr auto;gap:1.25rem;
  align-items:center;margin-bottom:1.75rem;
  position:relative;overflow:hidden;
}
.ml-score-card::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse at top right,rgba(99,102,241,.12),transparent 60%);
  pointer-events:none;
}
.ml-score-label{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-bottom:.5rem}
.ml-score-title{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:.375rem}
.ml-score-desc{font-size:.82rem;color:var(--muted);line-height:1.5}
.ml-score-factors{margin-top:.875rem;display:flex;flex-direction:column;gap:.375rem}
.ml-factor{font-size:.78rem;display:flex;align-items:center;gap:.5rem}
.ml-factor i{width:14px;font-size:.75rem}
.ml-factor.good{color:var(--green)}
.ml-factor.bad{color:var(--yellow)}

.ml-gauge{
  width:120px;height:120px;position:relative;flex-shrink:0;
}
.ml-gauge svg{width:100%;height:100%;transform:rotate(-90deg)}
.ml-gauge-track{fill:none;stroke:rgba(255,255,255,.06);stroke-width:10;stroke-linecap:round}
.ml-gauge-fill{fill:none;stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset .8s cubic-bezier(.4,0,.2,1)}
.ml-gauge-text{
  position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
}
.ml-gauge-pct{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;letter-spacing:-1px;color:var(--text)}
.ml-gauge-tag{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-top:-2px}
.ml-api-badge{
  display:inline-flex;align-items:center;gap:.35rem;
  font-size:.7rem;font-weight:700;padding:.25rem .625rem;border-radius:100px;
  background:rgba(16,185,129,.12);color:var(--green);border:1px solid rgba(16,185,129,.2);
  margin-top:.75rem;
}
.ml-api-badge.offline{
  background:rgba(239,68,68,.12);color:var(--red);border-color:rgba(239,68,68,.2);
}
.ml-api-dot{width:6px;height:6px;border-radius:50%;background:currentColor;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* ── CONTENT GRID ── */
.content-grid{
  display:grid;grid-template-columns:1fr 360px;
  gap:1.25rem;margin-bottom:1.75rem;
}
.content-grid-3{
  display:grid;grid-template-columns:1fr 1fr 1fr;
  gap:1.25rem;margin-bottom:1.75rem;
}

/* ── CARDS ── */
.card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;overflow:hidden;
}
.card-header{
  padding:1.125rem 1.5rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.card-header-title{
  font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;
  color:var(--text);display:flex;align-items:center;gap:.5rem;
}
.card-header-title i{color:var(--accent);font-size:.85rem}
.card-body{padding:1.25rem 1.5rem}
.card-badge{
  font-size:.7rem;font-weight:700;padding:.25rem .625rem;
  border-radius:100px;background:rgba(99,102,241,.15);color:var(--accent);
}

/* ── INSIGHTS ── */
.insight-item{
  display:flex;gap:.875rem;align-items:flex-start;
  padding:.875rem 0;border-bottom:1px solid var(--border);
}
.insight-item:last-child{border-bottom:none;padding-bottom:0}
.insight-icon{
  width:36px;height:36px;border-radius:8px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:.875rem;
  background:rgba(255,255,255,.05);
}
.insight-text{font-size:.83rem;color:var(--muted);line-height:1.55}
.insight-text strong{color:var(--text)}

/* ── FUNNEL ── */
.funnel-step{
  display:flex;align-items:center;gap:1rem;padding:.625rem 0;
}
.funnel-label{font-size:.78rem;font-weight:600;color:var(--muted);width:70px;flex-shrink:0}
.funnel-bar-wrap{flex:1;height:28px;background:rgba(255,255,255,.04);border-radius:6px;overflow:hidden;position:relative}
.funnel-bar{
  height:100%;border-radius:6px;
  display:flex;align-items:center;padding-left:.75rem;
  font-size:.75rem;font-weight:700;color:#fff;
  transition:width .8s cubic-bezier(.4,0,.2,1);
  min-width:40px;
}
.funnel-val{font-size:.78rem;font-weight:700;color:#fff;width:40px;text-align:right;flex-shrink:0}

/* ── SUGGESTIONS ── */
.suggestion-item{
  display:flex;align-items:center;gap:.875rem;
  padding:.75rem 1rem;border-radius:10px;
  background:var(--surface2);margin-bottom:.5rem;
  border-left:3px solid;transition:background .15s;
}
.suggestion-item:last-child{margin-bottom:0}
.suggestion-item:hover{background:var(--surface3)}
.suggestion-item.high{border-color:var(--red)}
.suggestion-item.medium{border-color:var(--yellow)}
.suggestion-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;background:rgba(255,255,255,.06)}
.suggestion-text{font-size:.82rem;color:var(--muted);flex:1;line-height:1.4}
.suggestion-priority{
  font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
  padding:.2rem .5rem;border-radius:100px;flex-shrink:0;
}
.suggestion-priority.high{background:rgba(239,68,68,.12);color:var(--red)}
.suggestion-priority.medium{background:rgba(245,158,11,.12);color:var(--yellow)}

/* ── BOOKINGS TABLE ── */
.booking-row{
  display:flex;align-items:center;gap:1rem;padding:.875rem 1.5rem;
  border-bottom:1px solid var(--border);transition:background .15s;
}
.booking-row:last-child{border-bottom:none}
.booking-row:hover{background:var(--surface2)}
.booking-avatar{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-size:.85rem;font-weight:700;color:#fff;flex-shrink:0;
}
.booking-info{flex:1;min-width:0}
.booking-name{font-size:.85rem;font-weight:600;color:var(--text)}
.booking-service{font-size:.75rem;color:var(--muted);margin-top:1px}
.booking-date{font-size:.75rem;color:var(--muted);white-space:nowrap}
.status-pill{
  font-size:.68rem;font-weight:700;padding:.2rem .6rem;border-radius:100px;white-space:nowrap;
}
.status-pending{background:rgba(245,158,11,.12);color:var(--yellow)}
.status-confirmed{background:rgba(99,102,241,.12);color:#818cf8}
.status-completed{background:rgba(16,185,129,.12);color:var(--green)}
.status-cancelled{background:rgba(239,68,68,.12);color:var(--red)}

/* ── CHARTS ── */
.chart-wrap{position:relative;padding:1.25rem 1.5rem 1.5rem}

/* ── LEVEL PROGRESS ── */
.level-card{
  display:flex;align-items:center;gap:1.25rem;
  padding:1.25rem 1.5rem;background:var(--surface2);border-radius:12px;
  margin-bottom:1rem;
}
.level-icon{
  width:52px;height:52px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;flex-shrink:0;
}
.level-info{flex:1}
.level-name{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:var(--text)}
.level-sub{font-size:.75rem;color:var(--muted);margin-top:2px}
.progress-wrap{margin-top:.5rem}
.progress-bar-bg{background:rgba(255,255,255,.06);border-radius:100px;height:6px;overflow:hidden}
.progress-bar-fill{height:100%;border-radius:100px;transition:width .8s ease}

/* ── RESPONSIVE ── */
@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){
  .content-grid,.content-grid-3{grid-template-columns:1fr}
  .main-content{margin-left:0;padding:1rem}
  .sidebar{transform:translateX(-100%);transition:transform .3s}
  .sidebar.open{transform:translateX(0)}
  .stats-grid{grid-template-columns:1fr 1fr}
  .ml-score-card{grid-template-columns:1fr}
}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr}}

.mobile-toggle{
  display:none;position:fixed;top:1rem;left:1rem;z-index:200;
  background:var(--accent);color:#fff;border:none;border-radius:10px;
  width:42px;height:42px;align-items:center;justify-content:center;cursor:pointer;
  box-shadow:0 4px 14px rgba(99,102,241,.4);font-size:1rem;
}
@media(max-width:900px){.mobile-toggle{display:flex}}

.overlay{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99;
  backdrop-filter:blur(2px);
}
.overlay.active{display:block}

/* Number animation */
@keyframes countUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.stat-value,.ml-gauge-pct{animation:countUp .5s ease both}

/* ── SUBSCRIPTION PLAN CARD ── */
.subscription-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;overflow:hidden;position:relative;
}
.sub-plan-badge{
  display:flex;align-items:center;gap:.5rem;padding:.875rem 1.5rem;
  color:#fff;font-weight:700;font-size:.85rem;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
}
.sub-plan-badge i{font-size:.9rem}
.sub-days{
  margin-left:auto;background:rgba(255,255,255,.2);
  padding:.2rem .6rem;border-radius:100px;font-size:.7rem;
}
.sub-features-grid{
  display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;padding:1.25rem 1.5rem;
}
.sub-feature-item{
  display:flex;align-items:flex-start;gap:.75rem;
}
.sub-feature-icon{
  width:36px;height:36px;border-radius:10px;
  background:rgba(99,102,241,.1);color:var(--accent);
  display:flex;align-items:center;justify-content:center;font-size:.9rem;
  flex-shrink:0;
}
.sub-feature-item.locked .sub-feature-icon{background:rgba(107,114,128,.1);color:var(--muted)}
.sub-feature-item.active .sub-feature-icon{background:rgba(16,185,129,.1);color:var(--green)}
.sub-feature-info{flex:1;min-width:0}
.sub-feature-label{font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;font-weight:600;margin-bottom:.2rem}
.sub-feature-value{font-size:.85rem;font-weight:700;color:var(--text)}
.sub-feature-item.locked .sub-feature-value{color:var(--muted)}
.sub-feature-item.active .sub-feature-value{color:var(--green)}
.sub-feature-bar{
  margin-top:.4rem;height:4px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden
}
.sub-feature-fill{height:100%;border-radius:2px;transition:width .5s ease}
.sub-upgrade-hint{
  display:flex;align-items:center;gap:.75rem;padding:.875rem 1.5rem;
  background:var(--surface2);border-top:1px solid var(--border);
  flex-wrap:wrap;
}
.sub-upgrade-hint i{color:var(--yellow)}
.sub-upgrade-tag{
  font-size:.72rem;padding:.25rem .5rem;border-radius:6px;background:rgba(245,158,11,.1);color:var(--yellow);
}
.sub-upgrade-tag.high{background:rgba(239,68,68,.1);color:var(--red)}
.sub-upgrade-btn{
  margin-left:auto;font-size:.8rem;font-weight:700;color:var(--accent);
  text-decoration:none;transition:opacity .2s;
}
.sub-upgrade-btn:hover{opacity:.8}

@media(max-width:1100px){
  .sub-features-grid{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:600px){
  .sub-features-grid{grid-template-columns:repeat(2,1fr)}
  .sub-upgrade-hint{flex-direction:column;align-items:flex-start}
  .sub-upgrade-btn{margin-left:0;margin-top:.5rem}
}
</style>
</head>
<body>
<script>
    (function() {
        const theme = localStorage.getItem('provider_theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
<button class="mobile-toggle" id="mobToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<!-- ── SIDEBAR ── -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- ── MAIN ── -->
<div class="main-content">

  <!-- Top Bar -->
  <div class="topbar">
    <div class="topbar-left">
      <h1>Dashboard</h1>
      <p><?php echo date('l, F j, Y'); ?> · <?php echo htmlspecialchars($provider['full_name']); ?></p>
    </div>
    <div class="topbar-right" style="position:relative">
      <!-- Level Badge -->
      <div class="level-badge" style="--lc:<?php echo $level_color; ?>">
        <span class="level-dot" style="background:<?php echo $level_color; ?>"></span>
        <i class="<?php echo $level_icon; ?>" style="color:<?php echo $level_color; ?>;font-size:.8rem"></i>
        <span style="color:<?php echo $level_color; ?>"><?php echo $level_label; ?> Provider</span>
      </div>

      <!-- ML API Status -->
      <div class="ml-api-badge <?php echo $ml_api_healthy ? '' : 'offline'; ?>" style="display:inline-flex">
        <span class="ml-api-dot"></span>
        ML <?php echo $ml_api_healthy ? 'Online ✅' : 'Offline ❌'; ?>
      </div>

      <!-- Notification Bell -->
      <div style="position:relative">
        <div class="notif-btn" id="notifBtn">
          <i class="fas fa-bell"></i>
          <?php if ($unread_count > 0): ?>
            <span class="notif-badge"><?php echo min(99,$unread_count); ?></span>
          <?php endif; ?>
        </div>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">
            <span>Notifications</span>
            <?php if ($unread_count > 0): ?>
              <span style="font-size:.7rem;color:var(--accent);cursor:pointer" id="markAllRead">Mark all read</span>
            <?php endif; ?>
          </div>
          <div class="notif-list">
            <?php if (empty($all_notifications)): ?>
              <div style="padding:1.5rem;text-align:center;color:var(--muted);font-size:.82rem">No notifications yet</div>
            <?php else: foreach ($all_notifications as $notif): ?>
              <div class="notif-item" <?php echo !$notif['is_read'] ? 'style="background:rgba(99,102,241,.05)"' : ''; ?>>
                <div class="notif-icon-wrap"><i class="<?php echo htmlspecialchars($notif['icon'] ?? 'fas fa-bell'); ?>"></i></div>
                <div>
                  <div class="notif-item-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                  <div class="notif-item-text"><?php echo htmlspecialchars(substr($notif['message'],0,70)); ?>…</div>
                  <div class="notif-item-time"><?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Step 1: ML Hire Probability Card ── -->
  <div class="ml-score-card">
    <div>
      <div class="ml-score-label">AI-Powered Score</div>
      <div class="ml-score-title">Hire Probability</div>
      <div class="ml-score-desc">Based on your profile activity, ratings, response speed, and booking history</div>

      <div class="ml-score-factors">
        <?php foreach ($rank_factors as $rf): ?>
          <div class="ml-factor <?php echo $rf['good'] ? 'good' : 'bad'; ?>">
            <i class="<?php echo $rf['icon']; ?>"></i>
            <?php echo htmlspecialchars($rf['text']); ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="ml-api-badge <?php echo $ml_api_healthy ? '' : 'offline'; ?>">
        <span class="ml-api-dot"></span>
        ML System: <?php echo $ml_api_healthy ? 'Online ✅' : 'Offline — using estimated score ❌'; ?>
      </div>
    </div>

    <!-- Gauge -->
    <div class="ml-gauge">
      <?php
        $r = 52; $circ = 2*M_PI*$r; $ml_frac = $ml_score/100;
        $dash = $circ; $offset = $circ*(1-$ml_frac);
        $gauge_color = $ml_score >= 70 ? '#10b981' : ($ml_score >= 40 ? '#f59e0b' : '#ef4444');
      ?>
      <svg viewBox="0 0 120 120">
        <circle class="ml-gauge-track" cx="60" cy="60" r="<?php echo $r; ?>"/>
        <circle class="ml-gauge-fill"
          cx="60" cy="60" r="<?php echo $r; ?>"
          stroke="<?php echo $gauge_color; ?>"
          stroke-dasharray="<?php echo $circ; ?>"
          stroke-dashoffset="<?php echo $offset; ?>"
          data-offset="<?php echo $offset; ?>"
          data-dash="<?php echo $circ; ?>"
        />
      </svg>
      <div class="ml-gauge-text">
        <span class="ml-gauge-pct" style="color:<?php echo $gauge_color; ?>"><?php echo $ml_score; ?>%</span>
        <span class="ml-gauge-tag"><?php echo $ml_score>=70?'High':($ml_score>=40?'Medium':'Low'); ?></span>
      </div>
    </div>
  </div>

  <!-- ── Subscription Plan Card ── -->
  <?php 
    $plan_colors = [
      'Free' => ['#6b7280', '#9ca3af'],
      'Standard' => ['#0d6efd', '#60a5fa'],
      'Pro' => ['#10b981', '#34d399']
    ];
    $pc = $plan_colors[$plan_features['plan_name']] ?? $plan_colors['Free'];
  ?>
  <div class="subscription-card" style="margin-bottom:1.75rem">
    <div class="sub-plan-badge" style="background:linear-gradient(135deg,<?php echo $pc[0]; ?>,<?php echo $pc[1]; ?>)">
      <i class="<?php echo $plan_features['is_paid'] ? 'fas fa-crown' : 'fas fa-tag'; ?>"></i>
      <span><?php echo htmlspecialchars($plan_features['plan_name']); ?> Plan</span>
      <?php if ($plan_features['is_paid']): ?>
        <span class="sub-days"><?php echo $plan_features['days_remaining']; ?> days left</span>
      <?php endif; ?>
    </div>
    <div class="sub-features-grid">
      <div class="sub-feature-item">
        <div class="sub-feature-icon"><i class="fas fa-concierge-bell"></i></div>
        <div class="sub-feature-info">
          <div class="sub-feature-label">Services</div>
          <div class="sub-feature-value"><?php echo $service_usage_text; ?></div>
          <div class="sub-feature-bar"><div class="sub-feature-fill" style="width:<?php echo $service_usage_pct; ?>%;background:<?php echo $pc[0]; ?>"></div></div>
        </div>
      </div>
      <div class="sub-feature-item">
        <div class="sub-feature-icon"><i class="fas fa-images"></i></div>
        <div class="sub-feature-info">
          <div class="sub-feature-label">Photos</div>
          <div class="sub-feature-value"><?php echo $photo_usage_text; ?></div>
          <div class="sub-feature-bar"><div class="sub-feature-fill" style="width:<?php echo $photo_usage_pct; ?>%;background:<?php echo $pc[0]; ?>"></div></div>
        </div>
      </div>
      <div class="sub-feature-item">
        <div class="sub-feature-icon"><i class="fas fa-chart-pie"></i></div>
        <div class="sub-feature-info">
          <div class="sub-feature-label">Analytics</div>
          <div class="sub-feature-value"><?php echo ucfirst($plan_features['analytics_level']); ?></div>
        </div>
      </div>
      <div class="sub-feature-item">
        <div class="sub-feature-icon"><i class="fas fa-rocket"></i></div>
        <div class="sub-feature-info">
          <div class="sub-feature-label">Boost</div>
          <div class="sub-feature-value"><?php echo $plan_features['ranking_boost_days']; ?> days</div>
        </div>
      </div>
      <div class="sub-feature-item <?php echo $plan_features['ai_enabled'] ? 'active' : 'locked'; ?>">
        <div class="sub-feature-icon"><i class="fas fa-robot"></i></div>
        <div class="sub-feature-info">
          <div class="sub-feature-label">AI Tools</div>
          <div class="sub-feature-value"><?php echo $plan_features['ai_enabled'] ? 'Enabled' : 'Upgrade'; ?></div>
        </div>
      </div>
      <div class="sub-feature-item <?php echo $plan_features['priority_ranking'] ? 'active' : 'locked'; ?>">
        <div class="sub-feature-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="sub-feature-info">
          <div class="sub-feature-label">Priority Ranking</div>
          <div class="sub-feature-value"><?php echo $plan_features['priority_ranking'] ? 'Active' : 'Standard'; ?></div>
        </div>
      </div>
    </div>
    <?php if (!empty($upgrade_suggestions)): ?>
    <div class="sub-upgrade-hint">
      <i class="fas fa-lightbulb"></i>
      <?php foreach($upgrade_suggestions as $us): ?>
        <span class="sub-upgrade-tag <?php echo $us['priority']; ?>"><?php echo htmlspecialchars($us['text']); ?></span>
      <?php endforeach; ?>
      <a href="select-plan.php" class="sub-upgrade-btn">Upgrade Now →</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Stats Grid ── -->
  <div class="stats-grid">
    <div class="stat-card" style="--card-accent:#6366f1">
      <div class="stat-icon" style="color:#818cf8"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-value" data-target="<?php echo $total_bookings; ?>">0</div>
      <div class="stat-label">Total Bookings</div>
      <div class="stat-change <?php echo $pending_bookings>0?'up':'neutral'; ?>">
        <i class="fas <?php echo $pending_bookings>0?'fa-arrow-up':'fa-minus'; ?>"></i>
        <?php echo $pending_bookings; ?> pending
      </div>
    </div>
    <div class="stat-card" style="--card-accent:#10b981">
      <div class="stat-icon" style="color:#6ee7b7"><i class="fas fa-check-circle"></i></div>
      <div class="stat-value" data-target="<?php echo $completed_bookings; ?>">0</div>
      <div class="stat-label">Completed Jobs</div>
      <div class="stat-change <?php echo $completed_bookings>0?'up':'neutral'; ?>">
        <i class="fas fa-briefcase"></i> all time
      </div>
    </div>
    <div class="stat-card" style="--card-accent:#f59e0b">
      <div class="stat-icon" style="color:#fcd34d"><i class="fas fa-star"></i></div>
      <div class="stat-value" data-target="<?php echo round((float)($provider['average_rating']??0)*10); ?>" data-divisor="10"><?php echo number_format((float)($provider['average_rating']??0),1); ?></div>
      <div class="stat-label">Avg Rating</div>
      <div class="stat-change neutral"><i class="fas fa-comments"></i> <?php echo $total_reviews; ?> reviews</div>
    </div>
    <div class="stat-card" style="--card-accent:#06b6d4">
      <div class="stat-icon" style="color:#67e8f9"><i class="fas fa-eye"></i></div>
      <div class="stat-value" data-target="<?php echo $total_views; ?>">0</div>
      <div class="stat-label">Profile Views</div>
      <div class="stat-change <?php echo $views_growth>0?'up':($views_growth<0?'down':'neutral'); ?>">
        <i class="fas fa-arrow-<?php echo $views_growth>=0?'up':'down'; ?>"></i>
        <?php echo abs($views_growth); ?>% this week
      </div>
    </div>
  </div>

  <!-- ── Content Row 1 ── -->
  <div class="content-grid">
    <!-- Charts -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-chart-line"></i> Views & Clicks (Last 7 Days)</div>
        <span class="card-badge">Live</span>
      </div>
      <div class="chart-wrap">
        <canvas id="activityChart" height="160"></canvas>
      </div>
    </div>

    <!-- Step 2: AI Insights -->
    <?php if ($provider_ai_enabled): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-brain"></i> AI Insights</div>
        <span class="card-badge"><?php echo count($insights); ?> active</span>
      </div>
      <div class="card-body" style="padding-top:.5rem">
        <?php foreach ($insights as $ins): ?>
          <div class="insight-item">
            <div class="insight-icon" style="background:<?php echo $ins['color']; ?>18;color:<?php echo $ins['color']; ?>">
              <i class="<?php echo $ins['icon']; ?>"></i>
            </div>
            <div class="insight-text"><?php echo $ins['text']; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Content Row 2 ── -->
  <div class="content-grid-3">

    <!-- Step 3: Funnel Analytics -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-filter"></i> Funnel Analytics</div>
      </div>
      <div class="card-body">
        <?php
          $funnel_steps = [
            ['Views', $funnel_views, '#6366f1'],
            ['Clicks', $funnel_clicks, '#8b5cf6'],
            ['Messages', $funnel_messages, '#f59e0b'],
            ['Hires', $funnel_hires, '#10b981'],
          ];
          $max_f = max(1, $funnel_views);
          foreach($funnel_steps as $fs):
            $pct = min(100,round($fs[1]/$max_f*100));
        ?>
          <div class="funnel-step">
            <div class="funnel-label"><?php echo $fs[0]; ?></div>
            <div class="funnel-bar-wrap">
              <div class="funnel-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $fs[2]; ?>">
                <?php if($pct>20): echo $fs[1]; endif; ?>
              </div>
            </div>
            <div class="funnel-val"><?php echo $pct<=20?$fs[1]:''; ?></div>
          </div>
        <?php endforeach; ?>

        <div style="margin-top:1rem;padding-top:.875rem;border-top:1px solid var(--border)">
          <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:700;margin-bottom:.5rem">Conversion Rate</div>
          <div style="font-size:1.5rem;font-family:'Syne',sans-serif;font-weight:800;color:var(--text)"><?php echo $conversion_rate; ?>%</div>
          <div style="font-size:.75rem;color:var(--muted)">Views → Completed Hires</div>
        </div>
      </div>
    </div>

    <!-- Step 6: Suggestions -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-lightbulb"></i> Recommendations</div>
        <span class="card-badge"><?php echo count($suggestions); ?> tips</span>
      </div>
      <div class="card-body">
        <?php if (empty($suggestions)): ?>
          <div style="text-align:center;padding:1rem 0;color:var(--muted);font-size:.85rem">
            <i class="fas fa-check-circle" style="color:var(--green);font-size:1.5rem;display:block;margin-bottom:.5rem"></i>
            Profile is fully optimised!
          </div>
        <?php else: foreach($suggestions as $s): ?>
          <div class="suggestion-item <?php echo $s['priority']; ?>">
            <div class="suggestion-icon" style="color:<?php echo $s['priority']==='high'?'var(--red)':'var(--yellow)'; ?>">
              <i class="<?php echo $s['icon']; ?>"></i>
            </div>
            <div class="suggestion-text"><?php echo htmlspecialchars($s['text']); ?></div>
            <span class="suggestion-priority <?php echo $s['priority']; ?>"><?php echo ucfirst($s['priority']); ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Step 8: Level System -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-layer-group"></i> Provider Level</div>
      </div>
      <div class="card-body">
        <div class="level-card">
          <div class="level-icon" style="background:<?php echo $level_color; ?>18;color:<?php echo $level_color; ?>">
            <i class="<?php echo $level_icon; ?>"></i>
          </div>
          <div class="level-info">
            <div class="level-name" style="color:<?php echo $level_color; ?>"><?php echo $level_label; ?></div>
            <div class="level-sub">Next: <?php echo $level_next; ?></div>
            <div class="progress-wrap">
              <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width:<?php echo $level_progress; ?>%;background:<?php echo $level_color; ?>"></div>
              </div>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-top:.25rem">
          <?php
            $tiers=[
              ['Bronze','#cd7f32','fas fa-medal',3,3.0,'bronze'],
              ['Silver','#9ca3af','fas fa-award',10,3.5,'silver'],
              ['Gold','#f59e0b','fas fa-trophy',25,4.0,'gold'],
            ];
            foreach($tiers as $t):
              $active = $level===$t[5];
          ?>
            <div style="
              text-align:center;padding:.75rem .5rem;border-radius:10px;
              background:<?php echo $active?'rgba(255,255,255,.06)':'rgba(255,255,255,.02)'; ?>;
              border:1px solid <?php echo $active?$t[1].'44':'rgba(255,255,255,.05)'; ?>;
            ">
              <i class="<?php echo $t[2]; ?>" style="color:<?php echo $t[1]; ?>;font-size:1.25rem;display:block;margin-bottom:.375rem"></i>
              <div style="font-size:.72rem;font-weight:700;color:<?php echo $t[1]; ?>"><?php echo $t[0]; ?></div>
              <div style="font-size:.65rem;color:var(--muted);margin-top:2px"><?php echo $t[3]; ?> jobs / <?php echo $t[4]; ?>★</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Recent Bookings ── -->
  <div class="card" style="margin-bottom:1.75rem">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-calendar-alt"></i> Recent Bookings</div>
      <a href="bookings.php" style="font-size:.78rem;color:var(--accent);font-weight:600">View all →</a>
    </div>
    <?php if (empty($recent_bookings)): ?>
      <div style="padding:2rem;text-align:center;color:var(--muted);font-size:.85rem">
        <i class="fas fa-calendar" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3"></i>
        No bookings yet
      </div>
    <?php else: foreach ($recent_bookings as $b): ?>
      <div class="booking-row">
        <div class="booking-avatar"><?php echo strtoupper(substr($b['client_name'],0,1)); ?></div>
        <div class="booking-info">
          <div class="booking-name"><?php echo htmlspecialchars($b['client_name']); ?></div>
          <div class="booking-service"><?php echo htmlspecialchars($b['service_name'] ?? 'General Service'); ?></div>
        </div>
        <div class="booking-date"><?php echo htmlspecialchars($b['fmt_date']); ?></div>
        <span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>

</div><!-- /main-content -->

<script>
// ── Notification dropdown ──────────────────────────────────────────────────
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
notifBtn?.addEventListener('click', e => {
  e.stopPropagation();
  notifDropdown.classList.toggle('open');
});
document.addEventListener('click', () => notifDropdown?.classList.remove('open'));

document.getElementById('markAllRead')?.addEventListener('click', () => {
  fetch('../api/notifications.php', {method:'POST',body:JSON.stringify({action:'mark_all_read'}),headers:{'Content-Type':'application/json'}})
    .then(() => { document.querySelector('.notif-badge')?.remove(); });
});

// ── Mobile sidebar ────────────────────────────────────────────────────────
const mobToggle = document.getElementById('mobToggle');
const overlay = document.getElementById('overlay');
const sidebar = document.querySelector('.sidebar');
mobToggle?.addEventListener('click', () => { sidebar?.classList.toggle('open'); overlay?.classList.toggle('active'); });
overlay?.addEventListener('click', () => { sidebar?.classList.remove('open'); overlay?.classList.remove('active'); });

// ── Animated counters ─────────────────────────────────────────────────────
function animateNumber(el, target, divisor = 1) {
  const duration = 800;
  const start = performance.now();
  function tick(now) {
    const t = Math.min(1, (now - start) / duration);
    const ease = 1 - Math.pow(1 - t, 3);
    const val = Math.round(target * ease) / divisor;
    el.textContent = divisor === 1 ? Math.round(val) : val.toFixed(1);
    if (t < 1) requestAnimationFrame(tick);
    else el.textContent = divisor === 1 ? target : (target / divisor).toFixed(1);
  }
  requestAnimationFrame(tick);
}
document.querySelectorAll('[data-target]').forEach(el => {
  const tgt = parseInt(el.dataset.target);
  const div = parseFloat(el.dataset.divisor ?? 1);
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { animateNumber(el, tgt, div); io.disconnect(); } });
  });
  io.observe(el);
});

// ── Gauge animation ───────────────────────────────────────────────────────
const gaugeFill = document.querySelector('.ml-gauge-fill');
if (gaugeFill) {
  const offset = parseFloat(gaugeFill.dataset.offset);
  const dash = parseFloat(gaugeFill.dataset.dash);
  gaugeFill.style.strokeDashoffset = dash; // start empty
  requestAnimationFrame(() => {
    gaugeFill.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1) .3s';
    gaugeFill.style.strokeDashoffset = offset;
  });
}

// ── Activity Chart (Step 4) ───────────────────────────────────────────────
const ctx = document.getElementById('activityChart');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?php echo json_encode($chart_labels); ?>,
      datasets: [
        {
          label: 'Profile Views',
          data: <?php echo json_encode($chart_views); ?>,
          borderColor: '#6366f1',
          backgroundColor: 'rgba(99,102,241,.12)',
          borderWidth: 2.5,
          pointBackgroundColor: '#6366f1',
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: .4,
        },
        {
          label: 'Clicks',
          data: <?php echo json_encode($chart_clicks); ?>,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16,185,129,.08)',
          borderWidth: 2.5,
          pointBackgroundColor: '#10b981',
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: .4,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          labels: { color: '#7c82a3', font: { family: 'DM Sans', size: 11 }, boxWidth: 12 }
        },
        tooltip: {
          backgroundColor: '#1e2130',
          borderColor: 'rgba(255,255,255,.07)',
          borderWidth: 1,
          titleColor: '#fff',
          bodyColor: '#7c82a3',
          padding: 10,
          cornerRadius: 8,
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,.04)', drawBorder: false },
          ticks: { color: '#7c82a3', font: { family: 'DM Sans', size: 11 } }
        },
        y: {
          grid: { color: 'rgba(255,255,255,.04)', drawBorder: false },
          ticks: { color: '#7c82a3', font: { family: 'DM Sans', size: 11 }, stepSize: 1 },
          beginAtZero: true
        }
      }
    }
  });
}

// ── Auto-refresh stats every 60s ─────────────────────────────────────────
function refreshStats() {
  fetch('dashboard.php?ajax=dashboard_data')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      const s = data.stats;
      // Update stat values silently (no animation on refresh)
      const cards = document.querySelectorAll('.stat-value');
      const vals = [s.total_bookings, s.completed ?? 0, s.average_rating, s.total_views ?? 0];
    })
    .catch(() => {});
}
setTimeout(refreshStats, 60000);
setInterval(refreshStats, 60000);
</script>
</body>
</html>