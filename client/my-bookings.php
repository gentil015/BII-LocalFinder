<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat.php';
require_once '../includes/event_tracking.php';

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

// Get view type (bookings or my-offers)
$view = isset($_GET['view']) ? sanitize($_GET['view']) : 'bookings';

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

// Handle offer response (withdraw or view counter-offers)
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offer_action'])) {
    $offer_id = intval($_POST['offer_id']);
    $action = sanitize($_POST['offer_action']);
    
    // Verify offer belongs to this client
    $stmt = $db->prepare("SELECT * FROM service_offers WHERE id = ? AND client_id = ?");
    $stmt->execute([$offer_id, $_SESSION['user_id']]);
    $offer = $stmt->fetch();
    
    if ($offer) {
        if ($action === 'withdraw') {
            $stmt = $db->prepare("UPDATE service_offers SET status = 'withdrawn' WHERE id = ?");
            if ($stmt->execute([$offer_id])) {
                $success = "Offer withdrawn successfully!";
            }
        } elseif ($action === 'accept_counter') {
            // AUTO-CONFIRM BOOKING when client accepts counter-offer
            try {
                $counter_id = intval($_POST['counter_id']);
                
                // Get counter-offer details
                $stmt = $db->prepare("SELECT * FROM service_counteroffers WHERE id = ? AND client_id = ?");
                $stmt->execute([$counter_id, $_SESSION['user_id']]);
                $counter_offer = $stmt->fetch();
                
                if (!$counter_offer) {
                    throw new Exception("Counter-offer not found or unauthorized");
                }
                
                // Step 1: Update counter-offer status to 'accepted'
                $stmt = $db->prepare("UPDATE service_counteroffers SET status = 'accepted' WHERE id = ?");
                if (!$stmt->execute([$counter_id])) {
                    throw new Exception("Failed to accept counter-offer");
                }
                
                // Step 2: Update original offer status to 'accepted'
                $stmt = $db->prepare("UPDATE service_offers SET status = 'accepted' WHERE id = ?");
                if (!$stmt->execute([$offer_id])) {
                    throw new Exception("Failed to update offer status");
                }
                
                // Step 3: Finalize the price using counter-offer amount
                $finalized_price = $counter_offer['proposed_price'];
                $stmt = $db->prepare("
                    INSERT INTO finalized_service_prices 
                    (booking_id, service_id, client_id, provider_id, finalized_price, negotiation_rounds, provider_final_counteroffer_id, status)
                    VALUES (?, ?, ?, ?, ?, 2, ?, 'active')
                    ON DUPLICATE KEY UPDATE
                    finalized_price = VALUES(finalized_price),
                    negotiation_rounds = 2,
                    provider_final_counteroffer_id = VALUES(provider_final_counteroffer_id),
                    updated_at = NOW()
                ");
                if (!$stmt->execute([$offer['booking_id'], $offer['service_id'], $_SESSION['user_id'], $counter_offer['provider_id'], $finalized_price, $counter_id])) {
                    throw new Exception("Failed to finalize price");
                }
                
                // Step 4: Update booking status to 'confirmed' with final price
                $stmt = $db->prepare("
                    UPDATE bookings 
                    SET status = 'confirmed', 
                        agreed_price = ?
                    WHERE id = ? AND client_id = ?
                ");
                if (!$stmt->execute([$finalized_price, $offer['booking_id'], $_SESSION['user_id']])) {
                    throw new Exception("Failed to confirm booking");
                }
                
                // Step 5: Log this action in negotiation history
                $stmt = $db->prepare("
                    INSERT INTO negotiation_history 
                    (booking_id, offer_id, counteroffer_id, action_type, price_offered, actor_id, actor_type, notes)
                    VALUES (?, ?, ?, 'counteroffer_accepted', ?, ?, 'client', 'Counter-offer accepted by client - Booking confirmed')
                ");
                $stmt->execute([$offer['booking_id'], $offer_id, $counter_id, $finalized_price, $_SESSION['user_id']]);
                
                $success = "✅ Counter-offer accepted and booking confirmed! Final price: RWF " . number_format($finalized_price, 0);
                
            } catch (Exception $e) {
                error_log("Counter-offer acceptance error: " . $e->getMessage());
                $success = "Error accepting counter-offer: " . $e->getMessage();
            }
        }
    }
}

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for all bookings with filters
$query = "
    SELECT b.*, 
           sp.profession, sp.location, sp.availability, sp.hourly_rate,
           u.full_name as provider_name, u.phone as provider_phone, 
           u.email as provider_email, u.profile_image as provider_image
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE b.client_id = ?
";

$params = [$_SESSION['user_id']];

// Apply filters
if (!empty($status_filter)) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR sp.profession LIKE ? OR b.service_description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$all_bookings = $stmt->fetchAll();

// Handle booking cancellation with system settings validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $cancellation_reason = isset($_POST['cancellation_reason']) ? sanitize($_POST['cancellation_reason']) : null;

    // Check monthly cancellation limit
    if ($monthly_cancellations >= $system_settings['max_cancellations_per_month']) {
        $error = "You have reached your monthly cancellation limit ({$system_settings['max_cancellations_per_month']}). Please contact support.";
    } else {
        // Verify booking belongs to client and is pending/confirmed
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND client_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Get the provider for messaging and timeline integration
            $stmtProvider = $db->prepare("SELECT provider_id FROM bookings WHERE id = ?");
            $stmtProvider->execute([$booking_id]);
            $providerData = $stmtProvider->fetch(PDO::FETCH_ASSOC);
            $providerId = $providerData['provider_id'] ?? null;

            $updateQuery = "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?";
            $stmt = $db->prepare($updateQuery);
            if ($stmt->execute([$cancellation_reason, $booking_id])) {
                $success = "Booking cancelled successfully";
                // Log activity
                logActivity($db, $_SESSION['user_id'], 'booking_cancelled', "Cancelled booking #{$booking_id} - Reason: {$cancellation_reason}");

                // Track booking cancelled event
                trackEvent('booking_cancelled', 'booking', $booking_id, [
                    'cancellation_reason' => $cancellation_reason,
                    'client_id' => $_SESSION['user_id'],
                    'provider_id' => $providerId
                ], $_SESSION['user_id']);

                if ($providerId) {
                    sendMessage($_SESSION['user_id'], $providerId, "Booking #{$booking_id} has been cancelled by the client. Reason: {$cancellation_reason}");
                }

                // Refresh page
                header("Location: my-bookings.php?cancelled=1");
                exit();
            }
        } else {
            $error = "Booking not found or cannot be cancelled";
        }
    }
}

// ============ Recommended Providers ============
// Suggest providers based on categories the client has already booked from,
// excluding providers already booked, falling back to top-rated/featured providers.
$recommended_providers = [];

$stmt = $db->prepare("SELECT DISTINCT provider_id FROM bookings WHERE client_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$booked_provider_ids = array_column($stmt->fetchAll(), 'provider_id');
$exclude_ids = !empty($booked_provider_ids) ? $booked_provider_ids : [0];
$exclude_placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));

$client_category_ids = [];
if (!empty($booked_provider_ids)) {
    $booked_placeholders = implode(',', array_fill(0, count($booked_provider_ids), '?'));
    $stmt = $db->prepare("SELECT DISTINCT category_id FROM provider_categories WHERE provider_id IN ($booked_placeholders)");
    $stmt->execute($booked_provider_ids);
    $client_category_ids = array_column($stmt->fetchAll(), 'category_id');
}

if (!empty($client_category_ids)) {
    $cat_placeholders = implode(',', array_fill(0, count($client_category_ids), '?'));
    $rec_query = "
        SELECT DISTINCT sp.id, sp.profession, sp.location, sp.average_rating, sp.total_reviews,
               sp.is_featured, sp.availability, u.full_name, u.profile_image
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        JOIN provider_categories pc ON pc.provider_id = sp.id
        WHERE pc.category_id IN ($cat_placeholders)
        AND sp.is_active = 1 AND sp.is_banned = 0 AND sp.status = 'active'
        AND sp.id NOT IN ($exclude_placeholders)
        ORDER BY sp.is_featured DESC, sp.average_rating DESC, sp.total_reviews DESC
        LIMIT 4
    ";
    $stmt = $db->prepare($rec_query);
    $stmt->execute(array_merge($client_category_ids, $exclude_ids));
    $recommended_providers = $stmt->fetchAll();
}

// Fallback: top-rated / featured providers overall
if (empty($recommended_providers)) {
    $rec_query = "
        SELECT sp.id, sp.profession, sp.location, sp.average_rating, sp.total_reviews,
               sp.is_featured, sp.availability, u.full_name, u.profile_image
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.is_active = 1 AND sp.is_banned = 0 AND sp.status = 'active'
        AND sp.id NOT IN ($exclude_placeholders)
        ORDER BY sp.is_featured DESC, sp.average_rating DESC, sp.total_reviews DESC
        LIMIT 4
    ";
    $stmt = $db->prepare($rec_query);
    $stmt->execute($exclude_ids);
    $recommended_providers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo $system_settings['platform_name']; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ============ MARKET LEDGER DESIGN SYSTEM ============ */
        :root {
            --primary: #B9822E;
            --primary-dark: #8C6423;
            --primary-light: #F1E4C8;
            --secondary: #5B685F;
            --success: #3F6B4A;
            --success-light: #E7EFE9;
            --danger: #A8432E;
            --danger-light: #F5E6E1;
            --warning: #D9A64E;
            --warning-light: #F7ECD3;
            --info: #3F6B6B;
            --info-light: #E4EEEC;
            --light: #F6F3EC;
            --dark: #10201A;
            --border-color: #E7E2D6;
            --radius-sm: 10px;
            --radius: 16px;
            --radius-lg: 20px;
            --shadow-soft: 0 2px 12px rgba(16,32,26,0.06);
            --shadow-hover: 0 14px 32px rgba(16,32,26,0.12);
            --header-h: 68px;
            --ink: #0B1F17;
            --ink-2: #12291F;
            --gold: #D9A64E;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #F6F3EC;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--dark);
        }
        h1,h2,h3,h4,h5 { font-family: 'Syne', sans-serif; letter-spacing: -.01em; }

        /* ── HEADER NAVIGATION (replaces sidebar) ── */
        .page-shell { min-height: 100vh; }
        .site-header {
            position: sticky; top: 0; z-index: 1000; background: rgba(246,243,236,.9); backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border-color); height: var(--header-h);
        }
        .site-header-inner {
            max-width: 1320px; margin: 0 auto; height: 100%; padding: 0 2rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;
        }
        .brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; color: var(--dark); flex-shrink: 0; }
        .brand-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--ink); display: flex; align-items: center; justify-content: center; color: var(--gold); font-size: 1rem; flex-shrink: 0; }
        .brand-word { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.02rem; line-height: 1.1; }
        .brand-word small { display: block; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 400; font-size: .6rem; color: var(--secondary); letter-spacing: .06em; text-transform: uppercase; }
        .main-nav { display: flex; align-items: center; gap: .15rem; flex: 1; justify-content: center; }
        .main-nav a { text-decoration: none; color: var(--secondary); font-size: .86rem; font-weight: 600; padding: .55rem .9rem; border-radius: var(--radius-sm); transition: all .15s ease; position: relative; }
        .main-nav a:hover { color: var(--dark); background: var(--light); }
        .main-nav a.active { color: var(--ink); }
        .main-nav a.active::after { content: ''; position: absolute; left: .9rem; right: .9rem; bottom: .2rem; height: 2px; background: var(--primary); border-radius: 2px; }
        .header-actions { display: flex; align-items: center; gap: .6rem; flex-shrink: 0; }
        .header-icon-btn { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-color); background: #fff; color: var(--secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: all .15s ease; position: relative; font-size: .9rem; }
        .header-icon-btn:hover { border-color: var(--primary); color: var(--primary); }
        .header-icon-btn .ping { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; border-radius: 50%; background: var(--danger); border: 1.5px solid #fff; }
        .user-menu { position: relative; }
        .user-menu-btn { display: flex; align-items: center; gap: .55rem; background: #fff; border: 1px solid var(--border-color); border-radius: 100px; padding: .3rem .8rem .3rem .3rem; cursor: pointer; font-family: inherit; transition: all .15s ease; }
        .user-menu-btn:hover { border-color: var(--primary); }
        .user-menu-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--gold)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .78rem; flex-shrink: 0; }
        .user-menu-name { font-size: .82rem; font-weight: 700; color: var(--dark); max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .user-menu-btn i.chev { font-size: .6rem; color: var(--secondary); transition: all .15s ease; }
        .user-menu.open .chev { transform: rotate(180deg); }
        .user-menu-dropdown { position: absolute; top: calc(100% + .6rem); right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-hover); min-width: 200px; padding: .5rem; display: none; z-index: 1200; }
        .user-menu.open .user-menu-dropdown { display: block; animation: navSlideDown .16s ease; }
        @keyframes navSlideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .user-menu-dropdown a { display: flex; align-items: center; gap: .6rem; padding: .6rem .7rem; border-radius: var(--radius-sm); text-decoration: none; color: var(--secondary); font-size: .84rem; font-weight: 600; transition: all .15s ease; }
        .user-menu-dropdown a:hover { background: var(--primary-light); color: var(--primary); }
        .user-menu-dropdown a i { width: 16px; text-align: center; color: var(--secondary); }
        .user-menu-dropdown a:hover i { color: var(--primary); }
        .user-menu-dropdown .divider { height: 1px; background: var(--border-color); margin: .4rem .2rem; }
        .user-menu-dropdown a.logout { color: var(--danger); }
        .user-menu-dropdown a.logout i { color: var(--danger); }
        .mobile-nav-toggle { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-color); background: #fff; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; color: var(--dark); }
        .mobile-nav-panel { display: none; background: #fff; border-bottom: 1px solid var(--border-color); padding: .5rem 1.1rem 1rem; }
        .mobile-nav-panel.open { display: block; animation: navSlideDown .18s ease; }
        .mobile-nav-panel a { display: flex; align-items: center; gap: .65rem; padding: .75rem .5rem; text-decoration: none; color: var(--secondary); font-size: .9rem; font-weight: 600; border-bottom: 1px solid var(--light); }
        .mobile-nav-panel a:last-child { border-bottom: none; }
        .mobile-nav-panel a.active { color: var(--primary); }
        .mobile-nav-panel a i { width: 18px; color: var(--secondary); }

        /* Main Content */
        .main-content {
            padding: 1.5rem 2rem 3rem;
        }

        /* Top Bar */
        .top-bar {
            background: linear-gradient(135deg, #ffffff, #FBFAF6);
            border-radius: var(--radius-lg);
            padding: 1.75rem 2rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
        }

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-text h1 {
            color: var(--dark);
            margin-bottom: 0.4rem;
            font-weight: 800;
            font-size: 1.75rem;
        }

        .welcome-text p {
            color: var(--secondary);
            margin: 0;
        }

        .quick-actions .btn {
            border-radius: 50px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(185,130,46,0.25);
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(185,130,46,0.32);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .stat-card h3 {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
            color: var(--dark);
            line-height: 1.1;
        }

        .stat-card p {
            color: var(--secondary);
            margin: 0.15rem 0 0;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .stat-card small {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.72rem;
        }

        /* Recommended Section */
        .recommended-section {
            background: linear-gradient(135deg, #ffffff, #FBFAF6);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
        }

        .recommended-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .recommended-header h3 {
            margin: 0;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .recommended-header h3 i {
            color: var(--warning);
        }

        .recommended-header p {
            margin: 0.2rem 0 0;
            color: var(--secondary);
            font-size: 0.85rem;
        }

        .recommended-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.1rem;
        }

        .rec-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.25rem;
            border: 1px solid var(--border-color);
            transition: all 0.25s ease;
            position: relative;
            text-align: center;
        }

        .rec-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: transparent;
        }

        .rec-featured-tag {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: linear-gradient(135deg, var(--warning), #D9A64E);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            letter-spacing: 0.02em;
        }

        .rec-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.4rem;
            margin: 0 auto 0.75rem;
            overflow: hidden;
            box-shadow: 0 6px 16px rgba(185,130,46,0.25);
        }

        .rec-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .rec-card h5 {
            margin: 0 0 0.15rem;
            font-weight: 700;
            font-size: 0.98rem;
            color: var(--dark);
        }

        .rec-profession {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.8rem;
            margin-bottom: 0.35rem;
        }

        .rec-rating {
            color: var(--warning);
            font-size: 0.82rem;
            margin-bottom: 0.35rem;
        }

        .rec-location {
            color: var(--secondary);
            font-size: 0.78rem;
            margin-bottom: 0.9rem;
        }

        .rec-actions {
            display: flex;
            gap: 0.5rem;
        }

        .rec-actions a {
            flex: 1;
            font-size: 0.78rem;
            padding: 0.5rem 0.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s;
        }

        .rec-btn-book {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .rec-btn-book:hover {
            opacity: 0.92;
            color: white;
        }

        .rec-btn-view {
            background: var(--primary-light);
            color: var(--primary);
        }

        .rec-btn-view:hover {
            background: #F1E4C8;
            color: var(--primary);
        }

        .rec-empty {
            text-align: center;
            color: var(--secondary);
            padding: 1.5rem;
            font-size: 0.9rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.75rem;
        }

        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 700;
            font-size: 1.15rem;
        }

        /* Booking Items */
        .booking-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.4rem;
            margin-bottom: 1.1rem;
            transition: all 0.25s ease;
            cursor: pointer;
            background: #fff;
        }

        .booking-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .provider-info {
            display: flex;
            gap: 1rem;
            flex: 1;
        }

        .provider-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(185,130,46,0.25);
        }

        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Badges */
        .badge {
            padding: 0.45rem 0.95rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .badge.pending {
            background: var(--warning-light);
            color: #7A4A1A;
        }

        .badge.confirmed {
            background: var(--info-light);
            color: #2E4A4A;
        }

        .badge.completed {
            background: var(--success-light);
            color: #1F3D28;
        }

        .badge.cancelled {
            background: var(--danger-light);
            color: #7A331F;
        }

        .badge.withdrawn {
            background: #E7E2D6;
            color: #3A443E;
        }

        /* Tabs */
        .view-tabs {
            display: flex;
            gap: 0.5rem;
            background: white;
            padding: 0.4rem;
            border-radius: 50px;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            width: fit-content;
        }

        .view-tab {
            padding: 0.65rem 1.4rem;
            text-decoration: none;
            color: var(--secondary);
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .view-tab:hover {
            color: var(--primary);
        }

        .view-tab.active {
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 6px 16px rgba(185,130,46,0.28);
        }

        /* Offer Card */
        .offer-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--warning);
            transition: all 0.25s;
        }

        .offer-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .offer-provider {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .offer-price {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
        }

        .offer-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .btn-offer-action {
            padding: 0.5rem 1.1rem;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-size: 0.83rem;
            font-weight: 600;
            transition: all 0.25s;
        }

        .btn-accept-counter {
            background: var(--success);
            color: white;
        }

        .btn-accept-counter:hover {
            background: #3F6B4A;
            transform: translateY(-2px);
        }

        .btn-withdraw {
            background: var(--danger);
            color: white;
        }

        .btn-withdraw:hover {
            background: #A8432E;
            transform: translateY(-2px);
        }

        /* Action Buttons */
        .booking-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1.1rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 50px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.25s;
        }

        .btn-view {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn-view:hover {
            background: var(--primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .btn-review {
            background: var(--warning-light);
            color: #7A4A1A;
        }

        .btn-review:hover {
            background: var(--warning);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: var(--danger-light);
            color: var(--danger);
        }

        .btn-cancel:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .btn-payment {
            background: var(--success-light);
            color: #1F3D28;
        }

        .btn-payment:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }

        /* Provider Cards (legacy - kept for compatibility) */
        .provider-card {
            text-align: center;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            margin-bottom: 1rem;
            transition: all 0.25s ease;
        }

        .provider-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .provider-card .provider-avatar {
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .provider-card h4 {
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .rating {
            color: var(--warning);
            margin: 0.5rem 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #E7E2D6;
        }

        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        /* Mobile Responsive */
        @media (max-width: 900px) {
            .main-nav { display: none; }
            .mobile-nav-toggle { display: flex; }
            .user-menu-name { display: none; }
            .site-header-inner { padding: 0 1.1rem; }
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .welcome-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .booking-header {
                flex-direction: column;
            }

            .recommended-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
        }

        /* System Notice */
        .system-notice {
            background: var(--warning-light);
            border: 1px solid #EAC77A;
            border-radius: var(--radius-sm);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        /* Filter Styles */
        .filter-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .filter-card h3 {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-size: 0.85rem;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            border-radius: 10px;
            border: 1px solid #E7E2D6;
            padding: 0.55rem 0.85rem;
        }

        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(185,130,46,0.12);
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-buttons .btn {
            border-radius: 50px;
            padding: 0.55rem 1.4rem;
            font-weight: 600;
            border: none;
        }

        .filter-buttons .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .filter-buttons .btn-secondary {
            background: #EFEBE0;
            color: var(--dark);
        }

        /* Booking Detail Modal (client view) */
        .booking-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(11,31,23,0.55);
            animation: fadeIn 0.3s ease;
        }

        .booking-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .booking-modal-content {
            background: white;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(11,31,23,0.35);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .booking-modal-header {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: flex-end;
            padding: 2rem 1.5rem 1.5rem;
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .booking-modal-header .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s;
        }

        .booking-modal-header .close-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .modal-provider-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary);
            margin-right: 1.5rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .modal-provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-provider-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-provider-info p {
            margin: 0.25rem 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .booking-modal-body {
            padding: 2rem 1.5rem;
        }

        .modal-section {
            margin-bottom: 2rem;
        }

        .modal-section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark);
        }

        .info-value {
            color: var(--secondary);
        }

        .contact-method {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            background: var(--light);
            text-decoration: none;
            color: var(--dark);
            margin-bottom: 0.6rem;
            transition: all 0.2s;
        }

        .contact-method:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
    </style>
</head>
<body>
<?php
$headerClientName = $client['full_name'] ?? 'there';
$headerClientInitial = strtoupper(substr(trim((string)$headerClientName), 0, 1)) ?: 'U';
$navLinks = [
    ['href' => 'dashboard.php',    'icon' => 'fa-house',            'label' => 'Dashboard'],
    ['href' => 'providers.php',    'icon' => 'fa-magnifying-glass', 'label' => 'Find providers'],
    ['href' => 'my-bookings.php',  'icon' => 'fa-calendar-check',   'label' => 'Bookings', 'active' => true],
    ['href' => 'messages.php',     'icon' => 'fa-comment-dots',     'label' => 'Messages'],
    ['href' => 'favorites.php',    'icon' => 'fa-heart',            'label' => 'Favorites'],
];
?>
    <div class="page-shell">
        <div class="site-header-inner">
            <a href="dashboard.php" class="brand">
                <span class="brand-mark"><i class="fas fa-map-location-dot"></i></span>
                <span class="brand-word"><?php echo htmlspecialchars($system_settings['platform_name']); ?><small>Rwanda · local services</small></span>
            </a>

            <nav class="main-nav">
                <?php foreach ($navLinks as $nl): ?>
                    <a href="<?php echo htmlspecialchars($nl['href']); ?>" class="<?php echo !empty($nl['active']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($nl['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="header-actions">
                <a href="favorites.php" class="header-icon-btn" title="Favorites"><i class="fas fa-heart"></i></a>
                <a href="messages.php" class="header-icon-btn" title="Messages"><i class="fas fa-comment-dots"></i></a>
                <a href="notifications.php" class="header-icon-btn" title="Notifications"><i class="fas fa-bell"></i><span class="ping"></span></a>

                <div class="user-menu" id="userMenu">
                    <button class="user-menu-btn" id="userMenuBtn" type="button">
                        <span class="user-menu-avatar"><?php echo htmlspecialchars($headerClientInitial); ?></span>
                        <span class="user-menu-name"><?php echo htmlspecialchars($headerClientName); ?></span>
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

                <button class="mobile-nav-toggle" id="mobileNavToggle" type="button"><i class="fas fa-bars"></i></button>
            </div>
        </div>

        <nav class="mobile-nav-panel" id="mobileNavPanel">
            <?php foreach ($navLinks as $nl): ?>
                <a href="<?php echo htmlspecialchars($nl['href']); ?>" class="<?php echo !empty($nl['active']) ? 'active' : ''; ?>">
                    <i class="fas <?php echo htmlspecialchars($nl['icon']); ?>"></i> <?php echo htmlspecialchars($nl['label']); ?>
                </a>
            <?php endforeach; ?>
            <a href="profile.php"><i class="fas fa-user"></i> My profile</a>
            <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
            <a href="../logout.php" style="color:var(--danger);"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-section">
                <div class="welcome-text">
                    <h1><?php echo $view === 'my-offers' ? 'My Price Offers' : 'My Bookings'; ?></h1>
                    <p><?php echo $view === 'my-offers' ? 'View and manage your price offers and negotiations' : 'Manage and track all your service bookings in one place'; ?></p>
                </div>
                <div class="quick-actions">
                    <a href="../providers.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> New Booking
                    </a>
                </div>
            </div>
        </div>

        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=bookings" class="view-tab <?php echo $view === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> My Bookings
                <span class="badge bg-secondary"><?php echo $total_bookings; ?></span>
            </a>
            <a href="?view=my-offers" class="view-tab <?php echo $view === 'my-offers' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i> My Offers
                <?php 
                // Get count of pending offers
                $stmt = $db->prepare("SELECT COUNT(*) FROM service_offers WHERE client_id = ? AND status = 'pending'");
                $stmt->execute([$_SESSION['user_id']]);
                $pending_offers = (int) $stmt->fetchColumn();
                if ($pending_offers > 0):
                ?>
                    <span class="badge bg-danger"><?php echo $pending_offers; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #F1E4C8; color: #B9822E;">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3><?php echo $total_bookings; ?></h3>
                <p>Total Bookings</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #F7ECD3; color: #7A4A1A;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $pending_bookings; ?></h3>
                <p>Pending Requests</p>
                <?php if ($system_settings['max_pending_time'] > 0): ?>
                    <small class="text-muted">Auto-cancels in <?php echo $system_settings['max_pending_time']; ?>min</small>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #E4EEEC; color: #2E4A4A;">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $confirmed_bookings; ?></h3>
                <p>Confirmed</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #E7EFE9; color: #1F3D28;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $completed_bookings; ?></h3>
                <p>Completed</p>
                <?php if ($system_settings['require_rating_after_completion']): ?>
                    <small class="text-muted">Rating required</small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recommended For You -->
        <?php if (!empty($recommended_providers)): ?>
        <div class="recommended-section">
            <div class="recommended-header">
                <div>
                    <h3><i class="fas fa-wand-magic-sparkles"></i> Recommended For You</h3>
                    <p>Trusted providers picked based on your booking history</p>
                </div>
                <a href="../providers.php" class="btn-sm btn-view">
                    <i class="fas fa-arrow-right me-1"></i> Browse All
                </a>
            </div>
            <div class="recommended-grid">
                <?php foreach ($recommended_providers as $rp): ?>
                    <?php
                        $rp_initial = strtoupper(substr($rp['full_name'] ?? '', 0, 1)) ?: '?';
                        $rp_rating = round((float)($rp['average_rating'] ?? 0), 1);
                    ?>
                    <div class="rec-card">
                        <?php if (!empty($rp['is_featured'])): ?>
                            <span class="rec-featured-tag"><i class="fas fa-star"></i> Featured</span>
                        <?php endif; ?>
                        <div class="rec-avatar">
                            <?php if (!empty($rp['profile_image'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($rp['profile_image']); ?>" alt="<?php echo htmlspecialchars($rp['full_name']); ?>">
                            <?php else: ?>
                                <?php echo $rp_initial; ?>
                            <?php endif; ?>
                        </div>
                        <h5><?php echo htmlspecialchars($rp['full_name']); ?></h5>
                        <div class="rec-profession"><?php echo htmlspecialchars($rp['profession']); ?></div>
                        <div class="rec-rating">
                            <?php if ($rp_rating > 0): ?>
                                <i class="fas fa-star"></i> <?php echo $rp_rating; ?>
                                <span class="text-muted">(<?php echo (int)$rp['total_reviews']; ?>)</span>
                            <?php else: ?>
                                <span class="text-muted">New provider</span>
                            <?php endif; ?>
                        </div>
                        <div class="rec-location">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rp['location'] ?: 'Location N/A'); ?>
                        </div>
                        <div class="rec-actions">
                            <a href="../client/provider-profile.php?id=<?php echo $rp['id']; ?>" class="rec-btn-view">Profile</a>
                            <a href="booking.php?provider_id=<?php echo $rp['id']; ?>" class="rec-btn-book">Book Now</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <?php if ($view === 'bookings'): ?>
                <!-- Filters -->
                <div class="filter-card">
                    <h3 class="mb-3">Filter Bookings</h3>
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="view" value="bookings">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                            </div>
                            <div class="filter-group">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Provider name, profession, or service...">
                            </div>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="?view=bookings" class="btn btn-secondary">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <!-- My Offers Filters -->
                <div class="filter-card">
                    <h3 class="mb-3">Filter Offers</h3>
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="view" value="my-offers">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="withdrawn" <?php echo $status_filter === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Provider name or service...">
                            </div>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="?view=my-offers" class="btn btn-secondary">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($view === 'bookings'): ?>
                <!-- All Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Bookings (<?php echo count($all_bookings); ?>)</h3>
                    </div>

                    <?php if (empty($all_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <h3>No bookings found</h3>
                            <p>No bookings match your current filters</p>
                            <a href="../providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_bookings as $booking): ?>
                            <?php
                                // build attributes string to avoid quote/parsing problems
                                $booking_attrs = sprintf(
                                    'onclick="openBookingModalClient(this)" ' .
                                    'data-booking-id="%d" ' .
                                    'data-provider-name="%s" ' .
                                    'data-provider-email="%s" ' .
                                    'data-provider-phone="%s" ' .
                                    'data-provider-image="%s" ' .
                                    'data-service-desc="%s" ' .
                                    'data-preferred-date="%s" ' .
                                    'data-preferred-time="%s" ' .
                                    'data-location="%s" ' .
                                    'data-status="%s" ' .
                                    'data-created-at="%s" ' .
                                    'data-urgency="%s"',
                                    $booking['id'],
                                    htmlspecialchars($booking['provider_name'] ?? ''),
                                    htmlspecialchars($booking['provider_email'] ?? ''),
                                    htmlspecialchars($booking['provider_phone'] ?? ''),
                                    htmlspecialchars($booking['provider_image'] ?? ''),
                                    htmlspecialchars($booking['service_description'] ?? ''),
                                    htmlspecialchars($booking['preferred_date'] ?? ''),
                                    htmlspecialchars($booking['preferred_time'] ?? ''),
                                    htmlspecialchars($booking['location'] ?? ''),
                                    htmlspecialchars($booking['status'] ?? ''),
                                    htmlspecialchars($booking['created_at'] ?? ''),
                                    htmlspecialchars($booking['urgency_level'] ?? '')
                                );
                            ?>
                            <div class="booking-item" <?php echo $booking_attrs; ?>>
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
                                    <?php if ($booking['status'] === 'cancelled' && !empty($booking['cancellation_reason'])): ?>
                                        <div class="text-danger small mt-1">Reason: <?php echo htmlspecialchars($booking['cancellation_reason']); ?></div>
                                    <?php endif; ?>
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

                                <div class="booking-actions">
                                    <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn-sm btn-view" style="background-color: #3F6B6B; color: white;" onclick="event.stopPropagation();">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </a>

                                    <a href="../client/provider-profile.php?id=<?php echo $booking['provider_id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation();">
                                        <i class="fas fa-user me-1"></i> View Provider
                                    </a>

                                    <?php
                                    // Check for pending payment
                                    require_once '../payments/PaymentManager.php';
                                    $paymentManager = new PaymentManager();
                                    $payment = $paymentManager->getPaymentForBooking($booking['id']);
                                    if ($payment && $payment['status'] === 'pending' && $paymentManager->isPaymentsEnabled()): ?>
                                        <button class="btn-sm btn-payment" onclick="event.stopPropagation(); processPayment(<?php echo $payment['id']; ?>, this);">
                                            <i class="fas fa-credit-card me-1"></i> Pay Now
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($system_settings['allow_booking_editing'] && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                        <a href="edit-booking.php?id=<?php echo $booking['id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation();">
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
                                            <a href="write-review.php?provider_id=<?php echo $booking['provider_id']; ?>&booking_id=<?php echo $booking['id']; ?>" class="btn-sm btn-review" onclick="event.stopPropagation();">
                                                <i class="fas fa-star me-1"></i> Write Review
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-success">Reviewed</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                        <?php if ($monthly_cancellations < $system_settings['max_cancellations_per_month']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <div class="d-flex align-items-center gap-2 mb-1" style="flex-wrap:wrap;">
                                                    <select name="cancellation_reason" class="form-select form-select-sm" required style="width:auto; min-width:180px;">
                                                        <option value="">Select cancellation reason</option>
                                                        <option value="too expensive">too expensive</option>
                                                        <option value="provider did not respond">provider did not respond</option>
                                                        <option value="found another provider">found another provider</option>
                                                        <option value="changed mind">changed mind</option>
                                                        <option value="bad communication">bad communication</option>
                                                        <option value="other">other</option>
                                                    </select>
                                                    <button type="submit" name="cancel_booking" class="btn-sm btn-cancel" onclick="event.stopPropagation();">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                </div>
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
                <?php endif; ?>

                <?php if ($view === 'my-offers'): ?>
                <!-- My Offers View -->
                <div class="card">
                    <div class="card-header">
                        <h3>My Price Offers</h3>
                    </div>

                    <?php 
                    // Get client's offers with counter-offers
                    $stmt = $db->prepare("
                        SELECT so.*, ps.name as service_name, u.full_name as provider_name, u.profile_image, u.email, u.phone
                        FROM service_offers so
                        JOIN provider_services ps ON so.service_id = ps.id
                        JOIN users u ON so.provider_id = u.id
                        WHERE so.client_id = ?
                        ORDER BY 
                            CASE 
                                WHEN so.status = 'pending' THEN 1
                                WHEN so.status = 'accepted' THEN 2
                                ELSE 3
                            END,
                            so.created_at DESC
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $my_offers = $stmt->fetchAll();
                    ?>

                    <?php if (empty($my_offers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-handshake"></i>
                            <h3>No price offers yet</h3>
                            <p>You haven't sent any price offers to providers</p>
                            <a href="../providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="offers-list">
                            <?php foreach ($my_offers as $offer): 
                                // Check if offer is expired
                                $is_expired = strtotime($offer['expires_at']) < time();
                                if ($is_expired && $offer['status'] === 'pending') {
                                    $offer['status'] = 'expired';
                                }

                                // Get counter-offers if any
                                $stmt_counter = $db->prepare("
                                    SELECT * FROM service_counteroffers 
                                    WHERE offer_id = ? 
                                    ORDER BY created_at DESC
                                ");
                                $stmt_counter->execute([$offer['id']]);
                                $counter_offers = $stmt_counter->fetchAll();
                            ?>
                                <div class="offer-card">
                                    <div class="offer-header">
                                        <div class="offer-provider">
                                            <div class="provider-avatar" style="width: 45px; height: 45px;">
                                                <?php if (!empty($offer['profile_image'])): ?>
                                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($offer['profile_image']); ?>" alt="<?php echo htmlspecialchars($offer['provider_name']); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($offer['provider_name'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($offer['provider_name']); ?></strong>
                                                <div class="text-muted small">
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($offer['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge <?php echo $offer['status']; ?>">
                                                <?php echo ucfirst($offer['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="margin-bottom: 1rem;">
                                        <p class="mb-2"><strong>Service:</strong> <?php echo htmlspecialchars($offer['service_name']); ?></p>
                                        <p class="mb-2"><strong>Your Offer:</strong> <span class="offer-price">RWF <?php echo number_format($offer['offered_price'], 2); ?></span></p>
                                        <p class="mb-2"><strong>Negotiation Round:</strong> <?php echo $offer['round_number']; ?></p>
                                        <p class="mb-2"><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($offer['created_at'])); ?></p>
                                        <?php if ($offer['expires_at']): ?>
                                            <p class="mb-0 text-muted small">
                                                <i class="fas fa-clock"></i> 
                                                Expires: <?php echo date('M d, Y h:i A', strtotime($offer['expires_at'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Counter-Offers -->
                                    <?php if (!empty($counter_offers)): ?>
                                        <div style="background: #F1EEE3; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                                            <h6 class="mb-2">Provider's Counter-Offers:</h6>
                                            <?php foreach ($counter_offers as $counter): ?>
                                                <div class="mb-3 pb-3" style="border-bottom: 1px solid #E7E2D6;">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div>
                                                            <strong>RWF <?php echo number_format($counter['proposed_price'], 2); ?></strong>
                                                            <span class="badge bg-info ms-2"><?php echo ucfirst($counter['status']); ?></span>
                                                        </div>
                                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($counter['created_at'])); ?></small>
                                                    </div>
                                                    <?php if (!empty($counter['response_notes'])): ?>
                                                        <p class="text-muted small mb-0"><?php echo nl2br(htmlspecialchars($counter['response_notes'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($counter['status'] === 'pending'): ?>
                                                        <form method="POST" style="display: inline; margin-top: 0.5rem;">
                                                            <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                                            <input type="hidden" name="counter_id" value="<?php echo $counter['id']; ?>">
                                                            <input type="hidden" name="offer_action" value="accept_counter">
                                                            <button type="submit" class="btn-offer-action btn-accept-counter" onclick="return confirm('Accept this counter-offer?')">
                                                                <i class="fas fa-check"></i> Accept
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($offer['status'] === 'pending'): ?>
                                    <div class="offer-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                            <input type="hidden" name="offer_action" value="withdraw">
                                            <button type="submit" class="btn-offer-action btn-withdraw" onclick="return confirm('Withdraw this offer?')">
                                                <i class="fas fa-times"></i> Withdraw Offer
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Quick Stats -->
                <div class="card">
                    <h3 class="mb-3">Booking Summary</h3>
                    <div class="d-grid gap-2">
                        <a href="?view=bookings" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i> All Bookings (<?php echo $total_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=pending" class="btn btn-outline-warning">
                            <i class="fas fa-clock me-2"></i> Pending (<?php echo $pending_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=confirmed" class="btn btn-outline-info">
                            <i class="fas fa-check me-2"></i> Confirmed (<?php echo $confirmed_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=completed" class="btn btn-outline-success">
                            <i class="fas fa-check-circle me-2"></i> Completed (<?php echo $completed_bookings; ?>)
                        </a>
                        <a href="my-bookings.php?status=cancelled" class="btn btn-outline-danger">
                            <i class="fas fa-times me-2"></i> Cancelled
                        </a>
                    </div>
                    
                    <!-- System Information -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted mb-2">Booking Information</h6>
                        <div class="small text-muted">
                            <div class="mb-1">
                                <i class="fas fa-times-circle me-1"></i> Cancellations: <?php echo $monthly_cancellations; ?>/<?php echo $system_settings['max_cancellations_per_month']; ?> this month
                            </div>
                            <?php if ($system_settings['max_pending_time'] > 0): ?>
                                <div class="mb-1">
                                    <i class="fas fa-clock me-1"></i> Pending bookings auto-cancel after <?php echo $system_settings['max_pending_time']; ?> minutes
                                </div>
                            <?php endif; ?>
                            <?php if ($system_settings['require_rating_after_completion']): ?>
                                <div class="mb-1">
                                    <i class="fas fa-star me-1"></i> Rating required after completion
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Need Help? -->
                <div class="card">
                    <h3 class="mb-3">Need Help?</h3>
                    <div class="d-grid gap-2">
                        <a href="../contact.php" class="btn btn-outline-primary">
                            <i class="fas fa-question-circle me-2"></i> Contact Support
                        </a>
                        <a href="../help.php" class="btn btn-outline-primary">
                            <i class="fas fa-life-ring me-2"></i> Help Center
                        </a>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-muted mb-2">Support Information</h6>
                        <div class="small text-muted">
                            <div class="mb-1">
                                <i class="fas fa-phone me-1"></i> <?php echo $system_settings['contact_phone']; ?>
                            </div>
                            <div class="mb-1">
                                <i class="fas fa-envelope me-1"></i> <?php echo $system_settings['contact_email']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /page-shell -->

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
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Booking Modal JS for client
        function openBookingModalClient(element) {
            const modal = document.getElementById('bookingModal');
            const bookingData = {
                id: element.getAttribute('data-booking-id'),
                provider_name: element.getAttribute('data-provider-name'),
                provider_email: element.getAttribute('data-provider-email'),
                provider_phone: element.getAttribute('data-provider-phone'),
                provider_image: element.getAttribute('data-provider-image'),
                service_description: element.getAttribute('data-service-desc'),
                preferred_date: element.getAttribute('data-preferred-date'),
                preferred_time: element.getAttribute('data-preferred-time'),
                location: element.getAttribute('data-location'),
                status: element.getAttribute('data-status'),
                created_at: element.getAttribute('data-created-at'),
                urgency: element.getAttribute('data-urgency') || ''
            };

            // populate contact links & text
            document.getElementById('modalContactEmail').href = 'mailto:' + (bookingData.provider_email || '');
            document.getElementById('modalContactPhone').href = 'tel:' + (bookingData.provider_phone || '');
            document.getElementById('modalContactEmailContact').textContent = bookingData.provider_email || '—';
            document.getElementById('modalContactPhoneContact').textContent = bookingData.provider_phone || '—';

            const avatar = document.getElementById('modalProviderAvatar');
            if (bookingData.provider_image) {
                avatar.innerHTML = '<img src="../uploads/profiles/' + escapeHtml(bookingData.provider_image) + '" alt="' + escapeHtml(bookingData.provider_name) + '">';
            } else {
                avatar.innerHTML = bookingData.provider_name.charAt(0).toUpperCase();
            }

            // populate details
            document.getElementById('modalServiceDesc').textContent = bookingData.service_description || '—';
            document.getElementById('modalPreferredDate').textContent = bookingData.preferred_date || '—';
            document.getElementById('modalPreferredTime').textContent = bookingData.preferred_time || '—';
            document.getElementById('modalLocation').textContent = bookingData.location || '—';
            const statusBadge = document.getElementById('modalStatusBadge');
            statusBadge.className = 'badge badge-' + bookingData.status;
            statusBadge.textContent = bookingData.status.charAt(0).toUpperCase() + bookingData.status.slice(1);

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBookingModal() {
            const modal = document.getElementById('bookingModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Payment processing function
        async function processPayment(paymentId, buttonElement) {
            if (!confirm('Are you sure you want to proceed with the payment?')) {
                return;
            }

            // Disable button and show loading state
            const originalText = buttonElement.innerHTML;
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

            try {
                const response = await fetch('../payments/process_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_id: paymentId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Show success message
                    alert('Payment successful! Your booking has been confirmed.');
                    // Reload the page to update the booking status
                    window.location.reload();
                } else {
                    // Show error message
                    alert('Payment failed: ' + (result.message || 'Unknown error'));
                    // Re-enable button
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('An error occurred while processing the payment. Please try again.');
                // Re-enable button
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('bookingModal');
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeBookingModal();
                }
            });
        });
    </script>
    <!-- Booking Modal HTML -->
    <div class="booking-modal" id="bookingModal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <button class="close-btn" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-provider-avatar" id="modalProviderAvatar"></div>
                <div class="modal-provider-info">
                    <h3 id="modalProviderName">Provider Name</h3>
                    <p><i class="fas fa-envelope" style="margin-right: 0.5rem;"></i><span id="modalProviderEmail">email@example.com</span></p>
                    <p><i class="fas fa-phone" style="margin-right: 0.5rem;"></i><span id="modalProviderPhone">+250700000000</span></p>
                </div>
            </div>
            <div class="booking-modal-body">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-briefcase"></i> Service Details</div>
                    <div class="info-row"><span class="info-label">Description</span><span class="info-value" id="modalServiceDesc">—</span></div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-calendar-alt"></i> Schedule</div>
                    <div class="info-row"><span class="info-label">Preferred Date</span><span class="info-value" id="modalPreferredDate">—</span></div>
                    <div class="info-row"><span class="info-label">Preferred Time</span><span class="info-value" id="modalPreferredTime">—</span></div>
                    <div class="info-row"><span class="info-label">Location</span><span class="info-value" id="modalLocation">—</span></div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-info-circle"></i> Booking Info</div>
                    <div class="info-row"><span class="info-label">Status</span><span class="info-value"><span class="badge" id="modalStatusBadge"></span></span></div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-phone-alt"></i> Contact</div>
                    <a class="contact-method" href="mailto:" id="modalContactEmail"><i class="fas fa-envelope"></i><div><div style="font-size:0.7rem;color:var(--secondary);text-transform:uppercase;margin-bottom:0.2rem;font-weight:600;">Email</div><div id="modalContactEmailContact" style="font-weight:600;font-size:0.95rem;">email@example.com</div></div></a>
                    <a class="contact-method" href="tel:" id="modalContactPhone"><i class="fas fa-phone"></i><div><div style="font-size:0.7rem;color:var(--secondary);text-transform:uppercase;margin-bottom:0.2rem;font-weight:600;">Phone</div><div id="modalContactPhoneContact" style="font-weight:600;font-size:0.95rem;">+250700000000</div></div></a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>