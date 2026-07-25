<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat.php';


$db = Database::getInstance()->getConnection();

$provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$share_id = isset($_GET['share_id']) ? intval($_GET['share_id']) : null;

if (!$provider_id) {
    header('Location: providers.php');
    exit();
}

// If service_id is provided, we'll auto-select it and skip to step 2
$auto_selected_service = null;

// Fetch provider details
$stmt = $db->prepare("SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.created_at as member_since, u.is_verified as user_verified FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ? AND sp.is_active = 1 AND sp.is_banned = 0");
$stmt->execute([$provider_id]);
$provider = $stmt->fetch();
if (!$provider) {
    header('Location: providers.php');
    exit();
}

// Ensure provider share id exists on bookings table for share-to-booking attribution
try {
    $colStmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'provider_share_id'");
    $colStmt->execute();
    if ($colStmt->fetchColumn() == 0) {
        $db->exec("ALTER TABLE bookings ADD COLUMN provider_share_id INT NULL AFTER status");
    }
} catch (Exception $e) {
    error_log('Booking table share column check failed: ' . $e->getMessage());
}

// Fetch services
$stmt = $db->prepare("SELECT ps.*, c.name as category_name, c.icon as category_icon FROM provider_services ps JOIN categories c ON ps.category_id = c.id WHERE ps.provider_id = ? AND ps.is_available = 1 ORDER BY ps.created_at DESC");
$stmt->execute([$provider_id]);
$services = $stmt->fetchAll();

// If service_id provided, find and auto-select it
if ($service_id) {
    foreach ($services as $svc) {
        if ($svc['id'] == $service_id) {
            $auto_selected_service = $svc;
            break;
        }
    }
}

// Fetch schedule info
$stmt = $db->prepare("SELECT working_days, working_hours_start, working_hours_end, break_start, break_end, slot_duration, buffer_time, max_daily_bookings FROM service_providers WHERE id = ?");
$stmt->execute([$provider_id]);
$schedule = $stmt->fetch();
// Parse working_days properly - handle spaces and ensure integers
$working_days = [];if (!empty($schedule['working_days'])) {
    $working_days = array_map('intval', array_filter(array_map('trim', explode(',', $schedule['working_days']))));
}
// If still empty after parsing, use default
if (empty($working_days)) {
    $working_days = [1,2,3,4,5]; // Default: Mon-Fri
}
// Debug: log what we got
error_log("Provider {$provider_id} working_days from DB: " . var_export($schedule['working_days'], true));
error_log("Parsed working_days array: " . var_export($working_days, true));

// get availability exceptions and time‑off for validation
$stmt = $db->prepare("SELECT date, is_available FROM provider_availability WHERE provider_id = ? AND date >= CURDATE()");
$stmt->execute([$provider_id]);
$availability_exceptions = $stmt->fetchAll();

$stmt = $db->prepare("SELECT start_date, end_date, reason FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE() AND is_approved = 1");
$stmt->execute([$provider_id]);
$time_off_periods = $stmt->fetchAll();

// Gather daily booking counts to detect fully booked dates
$fully_booked_dates = [];
$bookings_per_day = [];
$slot_duration = !empty($schedule['slot_duration']) ? intval($schedule['slot_duration']) : 60;
$buffer_minutes = !empty($schedule['buffer_time']) ? intval($schedule['buffer_time']) : 0;
$max_daily_bookings = !empty($schedule['max_daily_bookings']) ? intval($schedule['max_daily_bookings']) : 0;
$slots_per_day = 0;

if (!empty($schedule['working_hours_start']) && !empty($schedule['working_hours_end'])) {
    $start_ts = strtotime($schedule['working_hours_start']);
    $end_ts = strtotime($schedule['working_hours_end']);
    if ($end_ts > $start_ts) {
        $total_minutes = intval(($end_ts - $start_ts) / 60);
        if (!empty($schedule['break_start']) && !empty($schedule['break_end'])) {
            $break_start_ts = strtotime($schedule['break_start']);
            $break_end_ts = strtotime($schedule['break_end']);
            if ($break_end_ts > $break_start_ts) {
                $total_minutes -= intval(($break_end_ts - $break_start_ts) / 60);
            }
        }

        $chunk = max(15, $slot_duration + $buffer_minutes);
        $slots_per_day = intval(floor($total_minutes / $chunk));

        if ($max_daily_bookings > 0) {
            $slots_per_day = min($slots_per_day, $max_daily_bookings);
        }
    }
}

if ($max_daily_bookings > 0) {
    $slots_per_day = $max_daily_bookings;
}

if ($slots_per_day > 0) {
        $stmt = $db->prepare("SELECT preferred_date, COUNT(*) as cnt FROM bookings WHERE provider_id = ? AND preferred_date >= CURDATE() AND status IN ('pending','confirmed') GROUP BY preferred_date");
        $stmt->execute([$provider_id]);
        $bookings_per_day = $stmt->fetchAll();

    foreach ($bookings_per_day as $row) {
        if (!empty($row['preferred_date']) && intval($row['cnt']) >= $slots_per_day) {
            $fully_booked_dates[] = $row['preferred_date'];
        }
    }
}

// handle booking submission via standard POST
$booking_errors = [];
$booking_success = '';
$booking_ref = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_submit'])) {
    // Only validate on final form submission from step 4
    // grab fields
    $service_id = intval($_POST['service_id'] ?? 0);
    $service_desc = trim($_POST['serviceDesc'] ?? '');
    $preferred_date = trim($_POST['preferred_date'] ?? '');
    $preferred_time = trim($_POST['preferred_time'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_location = trim($_POST['client_location'] ?? '');
    $urgency_level = trim($_POST['urgency_level'] ?? 'normal');
    $client_proposed_price = !empty($_POST['client_proposed_price']) ? floatval($_POST['client_proposed_price']) : null;

    if (empty($_SESSION['user_id'])) {
        $booking_errors[] = "Please log in to submit a booking.";
    }

    // Validate all required fields
    if (empty($service_desc) || empty($preferred_date) || !$service_id) {
        $booking_errors[] = "Please fill all required fields";
    }
    if (empty($client_name) || empty($client_phone) || empty($client_location)) {
        $booking_errors[] = "Please enter your name, phone number, and location.";
    }

    // validate date
    if ($preferred_date) {
        $selected_date = new DateTime($preferred_date);
        $today = new DateTime(); $today->setTime(0,0,0);
        if ($selected_date < $today) {
            $booking_errors[] = "Please select a date in the future";
        }

        // working day check
        $day_of_week = (int)$selected_date->format('N'); // 1=Mon, 7=Sun
        if (!in_array($day_of_week, $working_days, true)) {
            $day_names = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $booking_errors[] = "Provider is not available on " . $day_names[$day_of_week-1] . "s";
        }

        // time off
        foreach ($time_off_periods as $time_off) {
            $time_off_start = new DateTime($time_off['start_date']);
            $time_off_end = new DateTime($time_off['end_date']);
            if ($selected_date >= $time_off_start && $selected_date <= $time_off_end) {
                $booking_errors[] = "Provider is on time off from " . date('M d', strtotime($time_off['start_date'])) . " to " . date('M d, Y', strtotime($time_off['end_date']));
                break;
            }
        }

        // availability exceptions
        foreach ($availability_exceptions as $ex) {
            if ($ex['date'] == $preferred_date && $ex['is_available'] == 0) {
                $booking_errors[] = "Provider is not available on this date";
                break;
            }
        }
    }

    // validate time bounds
    if ($preferred_time && $schedule['working_hours_start'] && $schedule['working_hours_end']) {
        $time = strtotime($preferred_time);
        $start_time = strtotime($schedule['working_hours_start']);
        $end_time = strtotime($schedule['working_hours_end']);
        if ($time < $start_time || $time > $end_time) {
            $booking_errors[] = "Please select a time between " . date('g:i A', $start_time) . " and " . date('g:i A', $end_time);
        }
    }

    // service belongs check
    $selectedService = null;
    foreach ($services as $svc) {
        if ((int)$svc['id'] === $service_id) {
            $selectedService = $svc;
            break;
        }
    }

    if (empty($booking_errors)) {
        if (!$selectedService || !$selectedService['is_available']) {
            $booking_errors[] = "Invalid service selected";
        }
    }

    if (empty($booking_errors) && $selectedService) {
        $serviceAvailabilityDays = [];
        if (!empty($selectedService['availability_days'])) {
            $serviceAvailabilityDays = array_map('intval', array_filter(array_map('trim', explode(',', $selectedService['availability_days']))));
        }
        if (empty($serviceAvailabilityDays)) {
            $serviceAvailabilityDays = $working_days;
        }

        if ($preferred_date) {
            if (!in_array($day_of_week, $serviceAvailabilityDays, true)) {
                $day_names = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                $booking_errors[] = "Selected service is not available on " . $day_names[$day_of_week-1] . "s";
            }
        }

        if ($preferred_time && !empty($selectedService['time_slots'])) {
            $availableServiceSlots = [];
            $rawSlots = $selectedService['time_slots'];
            $decoded = json_decode($rawSlots, true);
            if (is_array($decoded) && $decoded !== null) {
                foreach ($decoded as $slot) {
                    if (is_string($slot)) {
                        $availableServiceSlots[] = trim($slot);
                    } elseif (is_array($slot) && isset($slot['start'], $slot['end'])) {
                        $availableServiceSlots[] = trim($slot['start']) . '-' . trim($slot['end']);
                    }
                }
            } else {
                foreach (preg_split('/[\r\n]+/', $rawSlots) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $availableServiceSlots[] = $line;
                    }
                }
            }

            if (!empty($availableServiceSlots)) {
                $validTime = false;
                $serviceDuration = intval($selectedService['duration']) ?: 60;
                foreach ($availableServiceSlots as $slotRange) {
                    $parts = array_map('trim', explode('-', $slotRange));
                    if (count($parts) !== 2) {
                        continue;
                    }
                    $slotStart = strtotime($parts[0]);
                    $slotEnd = strtotime($parts[1]);
                    $selectedTime = strtotime($preferred_time);
                    if ($slotStart !== false && $slotEnd !== false && $selectedTime !== false) {
                        if ($selectedTime >= $slotStart && ($selectedTime + ($serviceDuration * 60)) <= $slotEnd) {
                            $validTime = true;
                            break;
                        }
                    }
                }
                if (!$validTime) {
                    $booking_errors[] = "Selected time is outside the service's available time slots.";
                }
            }
        }
    }

    if (empty($booking_errors)) {
        $bookingStatus = 'pending';
        if ($selectedService && $selectedService['booking_mode'] === 'instant' && empty($selectedService['negotiable'])) {
            $bookingStatus = 'confirmed';
        }

        $booking_amount = $client_proposed_price !== null
            ? $client_proposed_price
            : (isset($selectedService['price']) ? floatval($selectedService['price']) : null);

        $stmt = $db->prepare("INSERT INTO bookings (client_id, provider_id, service_id, service_description, preferred_date, preferred_time, location, amount, status, provider_share_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $provider_id, $service_id, $service_desc, $preferred_date, $preferred_time, $client_location, $booking_amount, $bookingStatus, $share_id ? $share_id : null])) {
            $booking_id = $db->lastInsertId();
            $booking_ref = '#BK-' . date('Y') . '-' . str_pad($booking_id,5,'0',STR_PAD_LEFT);

            // Create payment record if amount > 0
            if ($client_proposed_price > 0) {
                require_once '../payments/PaymentManager.php';
                $paymentManager = new PaymentManager();
                $payment_id = $paymentManager->createPaymentForBooking($booking_id);
                if ($payment_id) {
                    // Log payment creation
                    $stmt = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) VALUES (?, 'payment_created', ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], "Payment created for booking {$booking_ref}", $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                }
            }

            // send email/notification first
            if (!empty($provider['email'])) {
                require_once '../includes/mailer.php';
                Mailer::sendBookingNotification(
                    $provider['email'],
                    $provider['full_name'],
                    $_SESSION['user_name'] ?? '',
                    $service_desc
                );
            }
            try {
                require_once '../includes/notifications.php';
                notifyNewBooking($provider_id, $booking_id, [
                    'client_name' => $_SESSION['user_name'] ?? '',
                    'service_description' => $service_desc
                ]);
            } catch (Exception $e) {
                error_log('Booking notification error: '.$e->getMessage());
            }

            // automatically start chat by inserting initial message
            $provider_user_id = $provider['user_id'] ?? $provider_id;
            sendMessage($_SESSION['user_id'], $provider_user_id, "New booking created: " . $booking_ref);
            // redirect client straight to conversation
            header('Location: messages.php?with=' . $provider_user_id . '&booking_id=' . $booking_id);
            exit;

            // backup success message (will only appear if redirect is disabled)
            $booking_success = "Booking request sent successfully! The provider will contact you soon.";
        } else {
            $booking_errors[] = 'Failed to save booking. Please try again.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            const pageUrl = window.location.href;
            const pageTitle = document.title;
            let pageStartTime = Date.now();

            function sendTrack(action, data = {}) {
                const payload = new URLSearchParams({ action, page_url: pageUrl, page_title: pageTitle, ...data });
                fetch('../api/track_user_behavior.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                }).catch(console.error);
            }

            function trackPageView() {
                sendTrack('track_page_view', { referrer: document.referrer || '' });
            }

            function startPageSession() {
                sendTrack('start_page_session', { page_start: new Date(pageStartTime).toISOString() });
            }

            function endPageSession() {
                const pageEnd = Date.now();
                const timeSpent = Math.floor((pageEnd - pageStartTime) / 1000);
                sendTrack('end_page_session', {
                    page_start: new Date(pageStartTime).toISOString(),
                    page_end: new Date(pageEnd).toISOString(),
                    time_spent_seconds: timeSpent
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                trackPageView();
                startPageSession();
            });

            window.addEventListener('beforeunload', endPageSession);
            window.addEventListener('unload', endPageSession);

            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    endPageSession();
                } else {
                    pageStartTime = Date.now();
                    startPageSession();
                }
            });
        })();
    </script>
    <title>Book a Service — BII LocalFinder</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Design Tokens ─────────────────────────────────────── */
        :root {
            --ink:       #0B1F17;
            --ink-light: #5B685F;
            --surface:   #F6F3EC;
            --card:      #ffffff;
            --accent:    #B9822E;
            --accent-2:  #3F6B4A;
            --accent-3:  #A8432E;
            --gold:      #D9A64E;
            --border:    rgba(11,31,23,.12);
            --shadow-sm: 0 2px 8px rgba(11,31,23,.06);
            --shadow-md: 0 8px 32px rgba(11,31,23,.10);
            --shadow-lg: 0 20px 60px rgba(11,31,23,.14);
            --r-sm: 10px;
            --r-md: 18px;
            --r-lg: 28px;
            --transition: .28s cubic-bezier(.4,0,.2,1);
        }

        /* ─── Reset & Base ───────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ─── Animated Background ─────────────────────────────────── */
        .bg-grid {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                radial-gradient(ellipse 80% 60% at 10% -10%, rgba(11,31,23,.07) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 110%, rgba(63,107,74,.06) 0%, transparent 60%);
        }
        .bg-grid::after {
            content: '';
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(185,130,46,.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(185,130,46,.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ─── Page Shell ─────────────────────────────────────────── */
        .page-wrapper {
            position: relative; z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ─── Top Nav ────────────────────────────────────────────── */
        .top-nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 2.5rem;
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand {
            display: flex; align-items: center; gap: .65rem;
            text-decoration: none;
        }
        .nav-brand-mark {
            width: 36px; height: 36px; border-radius: 10px;
            background: var(--ink);
            display: flex; align-items: center; justify-content: center;
            color: var(--gold); font-size: 1rem; flex-shrink: 0;
        }
        .nav-brand-word {
            font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.02rem;
            line-height: 1.1; color: var(--ink);
        }
        .nav-brand-word small {
            display: block; font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-weight: 400; font-size: .6rem; color: var(--ink-light);
            letter-spacing: .06em; text-transform: uppercase;
        }
        .nav-back {
            display: flex; align-items: center; gap: .5rem;
            font-size: .9rem; font-weight: 600; color: var(--ink-light);
            text-decoration: none; padding: .5rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            transition: var(--transition);
        }
        .nav-back:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* ─── Hero Strip ──────────────────────────────────────────── */
        .booking-hero {
            background: linear-gradient(135deg, var(--ink) 0%, #12291F 55%, #1B382A 100%);
            padding: 3rem 2.5rem 4rem;
            position: relative; overflow: hidden;
        }
        .booking-hero::before {
            content: '';
            position: absolute; top: -60px; right: -60px;
            width: 320px; height: 320px;
            background: rgba(217,166,78,.14);
            border-radius: 50%;
        }
        .booking-hero::after {
            content: '';
            position: absolute; bottom: -80px; left: 40%;
            width: 200px; height: 200px;
            background: rgba(63,107,74,.15);
            border-radius: 50%;
        }
        .hero-inner {
            max-width: 900px; margin: 0 auto;
            display: flex; align-items: center; gap: 2rem;
            flex-wrap: wrap; position: relative; z-index: 2;
        }
        .provider-snap {
            display: flex; align-items: center; gap: 1.5rem; flex: 1;
        }
        .provider-avatar {
            width: 90px; height: 90px; border-radius: 50%;
            border: 3px solid rgba(255,255,255,.4);
            background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 700;
            color: #fff; overflow: hidden; flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(0,0,0,.2);
        }
        .provider-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .provider-meta h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.7rem; font-weight: 700; color: #fff; line-height: 1.2;
        }
        .provider-meta p { color: rgba(255,255,255,.8); font-size: 1rem; margin-top: .3rem; }
        .provider-badges { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .8rem; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            background: rgba(255,255,255,.18); backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.25);
            color: #fff; font-size: .78rem; font-weight: 600;
            padding: .3rem .75rem; border-radius: 50px;
        }
        .hero-rating {
            background: rgba(255,255,255,.15); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: var(--r-md);
            padding: 1.25rem 1.75rem; text-align: center; color: #fff;
            flex-shrink: 0;
        }
        .hero-rating .r-num { font-family: 'Syne', sans-serif; font-size: 2.8rem; font-weight: 800; line-height: 1; }
        .hero-rating .r-stars { color: var(--gold); font-size: 1rem; margin: .35rem 0; letter-spacing: 1px; }
        .hero-rating .r-label { font-size: .8rem; opacity: .8; }

        /* ─── Main Grid ───────────────────────────────────────────── */
        .booking-main {
            max-width: 1100px; margin: -2.5rem auto 3rem;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 1.75rem;
            position: relative; z-index: 3;
        }

        /* ─── Cards ───────────────────────────────────────────────── */
        .card {
            background: var(--card);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            overflow: hidden;
            animation: fadeUp .5s ease both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card:nth-child(2) { animation-delay: .08s; }
        .card:nth-child(3) { animation-delay: .16s; }

        .card-head {
            padding: 1.5rem 1.75rem 0;
            display: flex; align-items: center; gap: .75rem;
        }
        .card-head-icon {
            width: 40px; height: 40px; border-radius: var(--r-sm);
            background: linear-gradient(135deg, var(--accent), #12291F);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem;
        }
        .card-head h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem; font-weight: 700; color: var(--ink);
        }
        .card-body { padding: 1.25rem 1.75rem 1.75rem; }

        /* ─── Step Progress ───────────────────────────────────────── */
        .steps-bar {
            display: flex; gap: 0; background: var(--card);
            border-bottom: 1px solid var(--border);
        }
        .step-tab {
            flex: 1; display: flex; align-items: center; gap: .6rem;
            padding: 1.1rem 1.2rem; cursor: pointer;
            position: relative; transition: var(--transition);
            border-bottom: 3px solid transparent;
        }
        .step-tab.active { border-bottom-color: var(--accent); }
        .step-tab.done  { border-bottom-color: var(--accent-2); }
        .step-num {
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700;
            background: #F1E4C8; color: var(--accent);
            transition: var(--transition);
            flex-shrink: 0;
        }
        .step-tab.active .step-num { background: var(--accent); color: #fff; }
        .step-tab.done  .step-num  { background: var(--accent-2); color: #fff; }
        .step-label { font-size: .82rem; font-weight: 600; color: var(--ink-light); }
        .step-tab.active .step-label,
        .step-tab.done  .step-label { color: var(--ink); }

        /* ─── Form Steps ──────────────────────────────────────────── */
        .step-panel { display: none; }
        .step-panel.active { display: block; }

        /* ─── Service Cards ───────────────────────────────────────── */
        .service-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .service-option {
            border: 2px solid var(--border);
            border-radius: var(--r-md);
            padding: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            background: #fff;
        }
        .service-option:hover { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(185,130,46,.08); }
        .service-option.selected {
            border-color: var(--accent);
            background: linear-gradient(135deg, rgba(185,130,46,.04), rgba(185,130,46,.08));
            box-shadow: 0 0 0 4px rgba(185,130,46,.1);
        }
        .service-option input[type="radio"] { display: none; }
        .service-option-check {
            position: absolute; top: .85rem; right: .85rem;
            width: 22px; height: 22px; border-radius: 50%;
            border: 2px solid var(--border);
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .service-option.selected .service-option-check {
            background: var(--accent); border-color: var(--accent);
        }
        .service-option.selected .service-option-check::after {
            content: '✓'; font-size: .7rem; color: #fff; font-weight: 700;
        }
        .service-cat {
            font-size: .72rem; font-weight: 700; color: var(--accent);
            text-transform: uppercase; letter-spacing: .7px;
            display: flex; align-items: center; gap: .3rem; margin-bottom: .4rem;
        }
        .service-name {
            font-family: 'Syne', sans-serif;
            font-size: .97rem; font-weight: 700; color: var(--ink);
            margin-bottom: .4rem; line-height: 1.3;
        }
        .service-desc { font-size: .83rem; color: var(--ink-light); line-height: 1.5; margin-bottom: .85rem; }
        .service-footer { display: flex; align-items: center; justify-content: space-between; }
        .service-price { font-family: 'Syne', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--accent-2); }
        .service-dur { font-size: .78rem; color: var(--ink-light); display: flex; align-items: center; gap: .3rem; }
        .negotiable-chip {
            display: inline-flex; align-items: center; gap: .3rem;
            background: linear-gradient(135deg,#B9822E,#8C6423);
            color: #fff; font-size: .7rem; font-weight: 700;
            padding: .2rem .55rem; border-radius: 50px;
        }

        /* ─── Price Input Field ───────────────────────────────── */
        .price-input-field {
            display: none; margin-top: 1.25rem; padding: 1.25rem;
            background: linear-gradient(135deg, rgba(185,130,46,.05), rgba(185,130,46,.08));
            border: 2px solid rgba(185,130,46,.2);
            border-radius: var(--r-md);
            animation: slideDown .3s ease;
        }
        .price-input-field.show { display: block; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .price-input-field label { display: block; font-weight: 600; color: var(--ink); margin-bottom: .5rem; }
        .price-input-field .price-range-hint { font-size: .78rem; color: var(--ink-light); margin-bottom: .75rem; }
        .price-input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: .75rem; }
        .price-input-row label { font-size: .82rem; }
        .price-suggested { font-size: .75rem; color: var(--ink-light); margin-top: .3rem; }
        .price-proposed-field { position: relative; }
        .price-proposed-field .input { padding-left: 2.2rem; }
        .price-proposed-field::before { content: 'RWF'; position: absolute; left: .95rem; top: 2.7rem; font-weight: 700; color: var(--ink-light); font-size: .85rem; pointer-events: none; }

        /* ─── Form Fields ─────────────────────────────────────────── */
        .field { margin-bottom: 1.2rem; }
        .field label {
            display: block; font-size: .85rem; font-weight: 600;
            color: var(--ink); margin-bottom: .45rem;
        }
        .field label .req { color: var(--accent-3); margin-left: .2rem; }
        .field-hint { font-size: .78rem; color: var(--ink-light); margin-top: .3rem; }

        .input, .textarea, .select {
            width: 100%; padding: .8rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--r-sm); background: #FBFAF6;
            font-family: 'DM Sans', sans-serif;
            font-size: .92rem; color: var(--ink);
            transition: var(--transition); outline: none;
            appearance: none;
        }
        .input:focus, .textarea:focus, .select:focus {
            border-color: var(--accent);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(185,130,46,.1);
        }
        .input.error, .textarea.error { border-color: var(--accent-3); }
        .textarea { resize: vertical; min-height: 120px; }
        .select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%235B685F'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right .8rem center; background-size: 20px; padding-right: 2.2rem; cursor: pointer; }

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        /* ─── Date Picker Enhancement ─────────────────────────────── */
        .date-icon-wrap { position: relative; }
        .date-icon-wrap .input { padding-left: 2.8rem; }
        .date-icon-wrap .di {
            position: absolute; left: .95rem; top: 50%; transform: translateY(-50%);
            color: var(--accent); font-size: 1rem; pointer-events: none;
        }

        .calendar-widget {
            margin-top: .8rem;
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,.07);
            overflow: hidden;
            max-width: 380px;
        }
        .calendar-header {
            display: flex; justify-content: space-between; align-items: center;
            background: #F1EEE3; color: var(--ink);
            padding: .55rem .75rem; font-weight: 700; font-size: .9rem;
        }
        .calendar-header button {
            width: 30px; height: 30px; border: none; border-radius: 8px;
            background: #F1E4C8; color: var(--accent); cursor: pointer;
        }
        .calendar-weekdays {
            display: grid; grid-template-columns: repeat(7, 1fr); text-align: center;
            background: #F1EEE3; color: #5B685F; font-size: .75rem; font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        .calendar-weekdays div { padding: .4rem 0; }
        .calendar-grid {
            display: grid; grid-template-columns: repeat(7, 1fr); gap: .25rem;
            padding: .6rem .65rem .8rem;
            background: #fff;
            min-height: 260px;
            border: 1px solid rgba(185,130,46,.1);
            border-radius: 12px;
        }
        .calendar-day {
            width: 100%; aspect-ratio: 1/1;
            display: flex; align-items: center; justify-content: center;
            border-radius: .6rem;
            cursor: pointer; transition: var(--transition);
            color: var(--ink); background: #F1EEE3;
            border: 1px solid rgba(91,104,95,.2);
            min-height: 42px;
            user-select: none;
        }
        .calendar-day-number {
            font-size: 0.95rem; font-weight: 800;
            color: var(--ink);
            line-height: 1;
        }
        .calendar-day.available { color: var(--ink); background: #E7EFE9; }
        .calendar-day.available:hover { background: rgba(63,107,74,.18); color: var(--accent); }
        .calendar-day.selected { background: var(--accent); color: #fff; }
        .calendar-day.disabled { opacity: .35; cursor: not-allowed; background: #F1EEE3; }

        /* ─── Time Slots ──────────────────────────────────────────── */
        .time-slots {
            display: grid; grid-template-columns: repeat(4,1fr); gap: .6rem;
        }
        .time-slot {
            border: 2px solid var(--border);
            border-radius: var(--r-sm); padding: .55rem .2rem;
            text-align: center; cursor: pointer;
            font-size: .82rem; font-weight: 600;
            color: var(--ink-light); transition: var(--transition);
            background: #FBFAF6;
        }
        .time-slot:hover { border-color: var(--accent); color: var(--accent); background: #F1E4C8; }
        .time-slot.selected { background: var(--accent); border-color: var(--accent); color: #fff; }
        .time-slot.unavailable { opacity: .4; cursor: not-allowed; background: #EFEBE0; }

        /* ─── Working Days ────────────────────────────────────────── */
        .day-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
        .day-chip {
            padding: .4rem 1rem; border-radius: 50px;
            font-size: .82rem; font-weight: 600;
            background: #F1E4C8; color: var(--accent);
        }
        .day-chip.off { background: #fef2f2; color: #ef4444; }

        /* ─── Character Counter ───────────────────────────────────── */
        .char-count { font-size: .75rem; color: var(--ink-light); text-align: right; margin-top: .3rem; }
        .char-count.warn { color: var(--gold); }
        .char-count.limit { color: var(--accent-3); }

        /* ─── Navigation Buttons ──────────────────────────────────── */
        .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; gap: 1rem; }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .8rem 1.75rem; border-radius: 50px;
            font-family: 'DM Sans', sans-serif;
            font-size: .92rem; font-weight: 700;
            cursor: pointer; border: none; transition: var(--transition);
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #12291F);
            color: #fff; box-shadow: 0 4px 16px rgba(185,130,46,.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(185,130,46,.45); }
        .btn-secondary {
            background: #EFEBE0; color: var(--ink-light); border: 1.5px solid var(--border);
        }
        .btn-secondary:hover { background: #E7E2D6; }
        .btn-success {
            background: linear-gradient(135deg, var(--accent-2), #2E5038);
            color: #fff; box-shadow: 0 4px 16px rgba(63,107,74,.35);
            width: 100%; justify-content: center; padding: 1rem;
            font-size: 1.02rem;
        }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(63,107,74,.45); }
        .btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }

        /* ─── Sidebar Cards ───────────────────────────────────────── */
        .sidebar { display: flex; flex-direction: column; gap: 1.25rem; }

        /* ─── Summary Card ────────────────────────────────────────── */
        .summary-card { position: sticky; top: 88px; }
        .summary-head {
            background: linear-gradient(135deg, var(--accent), #12291F);
            padding: 1.5rem 1.75rem; color: #fff;
        }
        .summary-head h3 { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; }
        .summary-head p { font-size: .85rem; opacity: .8; margin-top: .2rem; }

        .summary-body { padding: 1.5rem 1.75rem; }
        .summary-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: .75rem 0; border-bottom: 1px solid var(--border);
            gap: .5rem;
        }
        .summary-row:last-child { border-bottom: none; }
        .s-label { font-size: .83rem; color: var(--ink-light); font-weight: 500; }
        .s-val { font-size: .88rem; font-weight: 700; color: var(--ink); text-align: right; }
        .s-val.price { color: var(--accent-2); font-size: 1.1rem; }
        .s-val.price-range { color: #B9822E; font-size: .95rem; }
        .summary-placeholder {
            font-size: .82rem; color: var(--ink-light); font-style: italic;
            padding: .5rem 0; display: flex; align-items: center; gap: .4rem;
        }

        /* ─── Provider Detail Card ────────────────────────────────── */
        .provider-info-card .pi-row {
            display: flex; align-items: center; gap: .75rem;
            padding: .65rem 0; border-bottom: 1px solid var(--border);
        }
        .provider-info-card .pi-row:last-child { border-bottom: none; }
        .pi-icon {
            width: 34px; height: 34px; border-radius: 8px;
            background: #F1E4C8; color: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem; flex-shrink: 0;
        }
        .pi-text { flex: 1; }
        .pi-text .pi-lbl { font-size: .73rem; color: var(--ink-light); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
        .pi-text .pi-val { font-size: .88rem; font-weight: 600; color: var(--ink); }

        /* ─── Contact Card ────────────────────────────────────────── */
        .contact-pill {
            display: flex; align-items: center; gap: .75rem;
            padding: .85rem 1rem; border-radius: var(--r-sm);
            background: var(--surface); margin-bottom: .6rem;
            border: 1px solid var(--border); text-decoration: none; color: inherit;
            transition: var(--transition);
        }
        .contact-pill:hover { background: #F1E4C8; border-color: var(--accent); }
        .contact-pill:last-child { margin-bottom: 0; }
        .contact-pill .cp-icon {
            width: 36px; height: 36px; border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), #12291F);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: .95rem; flex-shrink: 0;
        }
        .contact-pill .cp-label { font-size: .72rem; color: var(--ink-light); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .contact-pill .cp-val { font-size: .9rem; font-weight: 600; color: var(--ink); }

        /* ─── Trust Badges ────────────────────────────────────────── */
        .trust-row { display: flex; flex-direction: column; gap: .6rem; }
        .trust-item {
            display: flex; align-items: center; gap: .75rem;
            font-size: .82rem; font-weight: 500; color: var(--ink-light);
        }
        .trust-item i { color: var(--accent-2); font-size: .95rem; }

        /* ─── Confirm Screen ──────────────────────────────────────── */
        .confirm-grid {
            display: grid; gap: .75rem;
        }
        .confirm-item {
            display: flex; gap: 1rem; padding: 1rem 1.25rem;
            background: var(--surface); border-radius: var(--r-md);
            border: 1px solid var(--border);
        }
        .confirm-icon {
            width: 42px; height: 42px; border-radius: var(--r-sm);
            background: linear-gradient(135deg, var(--accent), #12291F);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem; flex-shrink: 0;
        }
        .confirm-icon.green  { background: linear-gradient(135deg, var(--accent-2), #2E5038); }
        .confirm-icon.purple { background: linear-gradient(135deg, #B9822E, #8C6423); }
        .confirm-icon.gold   { background: linear-gradient(135deg, var(--gold), #d97706); }
        .confirm-text .ct-label { font-size: .75rem; font-weight: 700; color: var(--ink-light); text-transform: uppercase; letter-spacing: .6px; }
        .confirm-text .ct-val { font-size: .95rem; font-weight: 600; color: var(--ink); margin-top: .15rem; }

        .terms-check { display: flex; align-items: flex-start; gap: .75rem; padding: .85rem 0; }
        .terms-check input { margin-top: .15rem; accent-color: var(--accent); width: 16px; height: 16px; flex-shrink: 0; }
        .terms-check label { font-size: .85rem; color: var(--ink-light); line-height: 1.5; }
        .terms-check label a { color: var(--accent); font-weight: 600; text-decoration: none; }

        /* ─── Success Screen ──────────────────────────────────────── */
        .success-screen {
            display: none; text-align: center; padding: 3rem 2rem;
            animation: fadeUp .6s ease both;
        }
        .success-icon {
            width: 90px; height: 90px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-2), #2E5038);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem; color: #fff;
            box-shadow: 0 8px 32px rgba(63,107,74,.35);
            animation: popIn .6s cubic-bezier(.34,1.56,.64,1) both .2s;
        }
        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .success-screen h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.75rem; font-weight: 800; color: var(--ink);
        }
        .success-screen p { color: var(--ink-light); margin: .75rem 0 1.5rem; line-height: 1.6; }
        .booking-ref {
            display: inline-block; background: #F1E4C8; color: var(--accent);
            font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.1rem;
            padding: .6rem 1.5rem; border-radius: 50px; letter-spacing: 1px;
            margin-bottom: 1.5rem;
        }
        .success-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

        /* ─── Validation Errors ───────────────────────────────────── */
        .field-error { font-size: .78rem; color: var(--accent-3); margin-top: .3rem; display: none; }
        .field.has-error .field-error { display: block; }
        .field.has-error .input,
        .field.has-error .textarea,
        .field.has-error .select { border-color: var(--accent-3); background: #fff8f8; }

        /* ─── Alert ───────────────────────────────────────────────── */
        .alert {
            border-radius: var(--r-sm); padding: 1rem 1.25rem;
            font-size: .88rem; line-height: 1.5; display: flex; gap: .75rem;
            margin-bottom: 1.25rem;
        }
        .alert.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert.error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .alert i { margin-top: .1rem; flex-shrink: 0; }

        /* ─── Responsive ──────────────────────────────────────────── */
        @media (max-width: 900px) {
            .booking-main { grid-template-columns: 1fr; }
            .summary-card { position: static; }
            .service-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .top-nav { padding: 1rem 1.25rem; }
            .booking-hero { padding: 2rem 1.25rem 3.5rem; }
            .booking-main { padding: 0 1rem; margin-top: -2rem; }
            .card-body { padding: 1rem 1.25rem 1.25rem; }
            .row-2 { grid-template-columns: 1fr; }
            .time-slots { grid-template-columns: repeat(3,1fr); }
            .hero-inner { flex-direction: column; align-items: flex-start; }
            .hero-rating { align-self: flex-start; }
        }

        /* ─── Custom Scrollbar ────────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(185,130,46,.25); border-radius: 3px; }
    </style>
    <!-- Shared User Behavior Tracking -->
    <?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
</head>
<body>

<div class="bg-grid" aria-hidden="true"></div>

<div class="page-wrapper">

    <!-- ── Top Navigation ─────────────────────────────────────── -->
    <nav class="top-nav">
        <a href="dashboard.php" class="nav-brand">
            <span class="nav-brand-mark"><i class="fas fa-map-location-dot"></i></span>
            <span class="nav-brand-word">BII LocalFinder<small>Rwanda · local services</small></span>
        </a>
        <a href="provider-profile.php?id=<?php echo $provider_id; ?>" class="nav-back">
            <i class="fas fa-arrow-left"></i> Back to profile
        </a>
    </nav>

    <!-- ── Hero Strip ─────────────────────────────────────────── -->
    <div class="booking-hero">
        <div class="hero-inner">
            <div class="provider-snap">
                <div class="provider-avatar" id="heroAvatar">
                    <?php if (!empty($provider['profile_image'])): ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($provider['profile_image']); ?>" alt="<?php echo htmlspecialchars($provider['full_name']); ?>">
                    <?php else: ?>
                        <?php echo strtoupper(substr($provider['full_name'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="provider-meta">
                    <h1 id="heroName"><?php echo htmlspecialchars($provider['full_name']); ?></h1>
                    <p id="heroProfession"><?php echo htmlspecialchars($provider['profession'] ?? ''); ?></p>
                    <div class="provider-badges">
                        <?php if ($provider['is_verified'] || $provider['user_verified']): ?>
                            <span class="hero-badge"><i class="fas fa-shield-check"></i> Verified</span>
                        <?php endif; ?>
                        <?php if (!empty($provider['district']) || !empty($provider['location'])): ?>
                            <span class="hero-badge"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($provider['district'] ?: $provider['location']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($provider['experience_years'])): ?>
                            <span class="hero-badge"><i class="fas fa-briefcase"></i> <?php echo (int)$provider['experience_years']; ?> yrs experience</span>
                        <?php endif; ?>
                        <span class="hero-badge" style="background:rgba(63,107,74,.2);border-color:rgba(63,107,74,.3);">
                            <i class="fas fa-circle" style="font-size:.5rem;color:#3F6B4A;"></i>
                            <?php echo htmlspecialchars(ucfirst($provider['availability'] ?? 'available')); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="hero-rating">
                <div class="r-num"><?php echo number_format($provider['average_rating'] ?? 0, 1); ?></div>
                <div class="r-stars"><?php echo str_repeat('★', round($provider['average_rating'] ?? 0)); ?></div>
                <div class="r-label"><?php echo (int)($provider['total_reviews'] ?? 0); ?> reviews</div>
            </div>
        </div>
    </div>

    <!-- ── Main Content ────────────────────────────────────────── -->
    <main class="booking-main">

        <!-- Left Column -->
        <div id="formCol">

            <?php if (!empty($booking_success)): ?>
                <!-- show success immediately -->
                <div class="card" id="bookingCard">
                    <div class="success-screen" style="display:block; text-align:center; padding:3rem 2rem;">
                        <div class="success-icon"><i class="fas fa-check"></i></div>
                        <h2>Booking Request Sent!</h2>
                        <p><?php echo htmlspecialchars($booking_success); ?></p>
                        <?php if (!empty($booking_ref)): ?>
                            <div class="booking-ref"><?php echo htmlspecialchars($booking_ref); ?></div>
                        <?php endif; ?>
                        <div class="success-actions">
                            <a href="#" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> My Bookings</a>
                            <a href="providers.php" class="btn btn-secondary"><i class="fas fa-search"></i> Find More Providers</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <form id="bookingForm" method="post" action="?provider_id=<?php echo $provider_id; ?>">
                <input type="hidden" name="current_step" id="currentStepHidden" value="1">
                <!-- Multi-Step Booking Form -->
                <div class="card" id="bookingCard">

                <!-- Step Progress Bar -->
                <?php if (!empty($booking_errors)): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo implode('<br>', $booking_errors); ?>
                    </div>
                <?php endif; ?>
                <div class="steps-bar" id="stepsBar">
                    <div class="step-tab active" data-step="1" onclick="goToStep(1)">
                        <div class="step-num">1</div>
                        <div class="step-label">Select Service</div>
                    </div>
                    <div class="step-tab" data-step="2">
                        <div class="step-num">2</div>
                        <div class="step-label">Date & Time</div>
                    </div>
                    <div class="step-tab" data-step="3">
                        <div class="step-num">3</div>
                        <div class="step-label">Your Details</div>
                    </div>
                    <div class="step-tab" data-step="4">
                        <div class="step-num">4</div>
                        <div class="step-label">Confirm</div>
                    </div>
                </div>

                <!-- ── STEP 1: Select Service ─────────────────────── -->
                <div class="step-panel active" id="step1">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-concierge-bell"></i></div>
                        <h2>Choose a Service</h2>
                    </div>
                    <div class="card-body">

                        <div class="alert info">
                            <i class="fas fa-info-circle"></i>
                            <span>Select the service you need. Services marked <strong>Negotiable</strong> allow you to propose your own price.</span>
                        </div>

                        <div class="service-grid" id="serviceGrid">
                            <?php if (!empty($services)): ?>
                                <?php foreach ($services as $svc): ?>
                                    <?php
                                        $isNegotiable = !empty($svc['negotiable']);
                                        if ($isNegotiable && !empty($svc['min_price']) && !empty($svc['max_price'])) {
                                            $priceStr = 'RWF ' . number_format($svc['min_price']) . '–' . 'RWF ' . number_format($svc['max_price']);
                                        } else {
                                            $priceStr = 'RWF ' . number_format($svc['price']);
                                        }
                                    ?>
                                    <label class="service-option"
                                           data-service-name="<?php echo htmlspecialchars($svc['name']); ?>"
                                           data-service-price="<?php echo htmlspecialchars($priceStr); ?>"
                                           data-service-duration="<?php echo htmlspecialchars($svc['duration'] ?? '60'); ?>"
                                           data-service-negotiable="<?php echo $isNegotiable ? '1' : '0'; ?>"
                                           data-booking-mode="<?php echo htmlspecialchars($svc['booking_mode'] ?? 'request_approval'); ?>"
                                           data-availability-days="<?php echo htmlspecialchars($svc['availability_days'] ?? '1,2,3,4,5'); ?>"
                                           data-time-slots="<?php echo htmlspecialchars(json_encode($svc['time_slots']), ENT_QUOTES, 'UTF-8'); ?>"
                                           onclick="selectService(this, '<?php echo (int)$svc['id']; ?>', '<?php echo addslashes($svc['name']); ?>', '<?php echo addslashes($priceStr); ?>', <?php echo $isNegotiable ? 'true' : 'false'; ?>, '<?php echo addslashes((string)($svc['duration'] ?? '60')); ?>')">
                                        <input type="radio" name="service_id" value="<?php echo (int)$svc['id']; ?>" <?php echo (isset($_POST['service_id']) && $_POST['service_id']==$svc['id'])?'checked':''; ?>>
                                        <div class="service-option-check"></div>
                                        <div class="service-cat"><i class="fas <?php echo htmlspecialchars($svc['category_icon'] ?? 'fa-concierge-bell'); ?>"></i> <?php echo htmlspecialchars($svc['category_name'] ?? 'Service'); ?></div>
                                        <div class="service-name"><?php echo htmlspecialchars($svc['name']); ?></div>
                                        <div class="service-desc"><?php echo nl2br(htmlspecialchars($svc['description'] ?? '')); ?></div>
                                        <div class="service-footer">
                                            <div>
                                                <div class="service-price" style="font-size:<?php echo $isNegotiable ? '.95rem' : '1.15rem'; ?>;">
                                                    <?php echo $priceStr; ?>
                                                </div>
                                                <?php if ($isNegotiable): ?>
                                                    <span class="negotiable-chip"><i class="fas fa-handshake"></i> Negotiable</span>
                                                <?php endif; ?>
                                                <div class="mt-1">
                                                    <span class="badge bg-<?php echo (($svc['booking_mode'] ?? 'request_approval') === 'instant') ? 'success' : 'secondary'; ?>">
                                                        <?php echo (($svc['booking_mode'] ?? 'request_approval') === 'instant') ? 'Instant Booking' : 'Request Approval'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="service-dur"><i class="far fa-clock"></i> <?php echo htmlspecialchars($svc['duration'] ?? '60'); ?> min</div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-concierge-bell"></i>
                                    <h4>No Services Available</h4>
                                    <p>This provider hasn't added any services yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Negotiable Price Input - STEP 1b: Enter Price for Negotiable Services -->
                        <div class="price-input-field" id="priceInputField" style="display:none;">
                            <div style="background: linear-gradient(135deg, #B9822E22, #8C642322); border: 2px solid #B9822E; border-radius: var(--r-md); padding: 1.5rem; margin-bottom: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,#B9822E,#8C6423); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">📊</div>
                                    <div>
                                        <h3 style="font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; color: var(--ink); margin: 0;">This Service is Negotiable</h3>
                                        <p style="font-size: 0.85rem; color: var(--ink-light); margin: 0.25rem 0 0 0;">Next: Enter your proposed price (optional)</p>
                                    </div>
                                </div>

                                <div class="field" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; font-size: 0.95rem;">What price would you like to propose?</label>
                                    <div class="price-range-hint" id="priceRangeHint" style="margin-bottom: 0.75rem; font-weight: 600;"></div>
                                    <div class="price-proposed-field">
                                        <input type="number" class="input" id="clientProposedPrice" name="client_proposed_price" placeholder="e.g., 50000" min="0" step="100" value="<?php echo htmlspecialchars($_POST['client_proposed_price'] ?? ''); ?>" style="font-size: 1.1rem; font-weight: 600;">
                                    </div>
                                    <div class="field-hint" style="margin-top: 0.5rem;">💡 <strong>Tip:</strong> Enter your budget within the range, or leave blank to negotiate with the provider after they accept your request.</div>
                                    <div class="field-error" id="priceError" style="margin-top: 0.5rem;">Please enter a valid price if you want to propose one.</div>
                                </div>
                            </div>
                        </div>

                        <div id="step1Error" class="field-error" style="display:none;font-size:.85rem;margin-top:.75rem;">
                            <i class="fas fa-exclamation-circle"></i> Please select a service to continue.
                        </div>

                        <div class="step-nav" style="justify-content:flex-end;">
                            <button type="button" class="btn btn-primary" onclick="nextStep(1)">
                                Next: Choose Date & Time <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 2: Date & Time ─────────────────────────── -->
                <div class="step-panel" id="step2">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-calendar-alt"></i></div>
                        <h2>Pick Date & Time</h2>
                    </div>
                    <div class="card-body">

                        <div class="field" id="dateField">
                            <label>Preferred Date <span class="req">*</span></label>
                            <div class="day-chips" id="workingDayChips">
                                <span class="day-chip">Mon</span>
                                <span class="day-chip">Tue</span>
                                <span class="day-chip">Wed</span>
                                <span class="day-chip">Thu</span>
                                <span class="day-chip">Fri</span>
                                <span class="day-chip off">Sat</span>
                                <span class="day-chip off">Sun</span>
                            </div>
                            <div class="calendar-widget" id="calendarWidget">
                                <div class="calendar-header">
                                    <button type="button" id="prevMonthBtn" aria-label="Previous month">&lt;</button>
                                    <span id="calendarMonthLabel">March 2026</span>
                                    <button type="button" id="nextMonthBtn" aria-label="Next month">&gt;</button>
                                </div>
                                <div class="calendar-weekdays">
                                    <div>Mon</div><div>Tue</div><div>Wed</div>
                                    <div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                                </div>
                                <div class="calendar-grid" id="calendarGrid"></div>
                            </div>
                            <input type="hidden" id="preferredDate" name="preferred_date" value="<?php echo htmlspecialchars($_POST['preferred_date'] ?? ''); ?>">
                            <div class="field-error" id="dateError">Please select a valid working day.</div>
                        </div>

                        <div class="field" id="timeSlotField">
                            <label>Preferred Time Slot <span class="req">*</span></label>
                            <p class="field-hint" style="margin-bottom:.6rem;"><i class="fas fa-clock text-primary" style="color:var(--accent);"></i> Working hours: <strong id="availableHours">Loading...</strong></p>
                            <div class="time-slots" id="timeSlots"></div>
                            <div class="field-error" id="timeError">Please select a time slot.</div>
                        </div>

                        <input type="hidden" id="selectedTime" name="preferred_time" value="<?php echo htmlspecialchars($_POST['preferred_time'] ?? ''); ?>">

                        <div class="alert info" style="margin-top:.75rem;">
                            <i class="fas fa-info-circle"></i>
                            <span>The provider confirms exact timing after reviewing your request.</span>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                                Next: Your Details <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 3: Your Details ────────────────────────── -->
                <div class="step-panel" id="step3">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-user"></i></div>
                        <h2>Your Details</h2>
                    </div>
                    <div class="card-body">

                        <div class="row-2">
                            <div class="field" id="nameField">
                                <label>Full Name <span class="req">*</span></label>
                                <input type="text" class="input" id="clientName" name="client_name" placeholder="e.g. Alice Mutoni" value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>">
                                <div class="field-error">Please enter your full name.</div>
                            </div>
                            <div class="field" id="phoneField">
                                <label>Phone Number <span class="req">*</span></label>
                                <input type="tel" class="input" id="clientPhone" name="client_phone" placeholder="e.g. 0788 123 456" value="<?php echo htmlspecialchars($_POST['client_phone'] ?? ''); ?>">
                                <div class="field-error">Please enter a valid phone number.</div>
                            </div>
                        </div>

                        <div class="field" id="locationField">
                            <label>Service Location <span class="req">*</span></label>
                            <input type="text" class="input" id="clientLocation" name="client_location" placeholder="Street / District / City" value="<?php echo htmlspecialchars($_POST['client_location'] ?? ''); ?>">
                            <div class="field-hint">Where should the provider come to?</div>
                            <div class="field-error">Please enter your location.</div>
                        </div>

                        <div class="field" id="descField">
                            <label>Describe Your Needs <span class="req">*</span></label>
                            <textarea class="textarea" id="serviceDesc" name="serviceDesc" maxlength="500"
                                      placeholder="Describe the problem or job in detail — e.g., lights flickering in the bedroom, need full rewire of house…"
                                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($_POST['serviceDesc'] ?? ''); ?></textarea>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div class="field-error">Please describe what you need.</div>
                                <div class="char-count" id="charCount">0 / 500</div>
                            </div>
                        </div>

                        <div class="field">
                            <label>Urgency Level</label>
                            <select class="select" id="urgencyLevel" name="urgency_level">
                                <option value="">Select urgency…</option>
                                <option value="flexible" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='flexible')?'selected':''; ?>>Flexible – anytime</option>
                                <option value="standard" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='standard')?'selected':''; ?>>Standard – within a week</option>
                                <option value="urgent" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='urgent')?'selected':''; ?>>Urgent – within 48 hrs</option>
                                <option value="emergency" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='emergency')?'selected':''; ?>>Emergency – today / ASAP</option>
                            </select>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                                Review Booking <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 4: Confirm ────────────────────────────── -->
                <div class="step-panel" id="step4">
                    <div class="card-head">
                        <div class="card-head-icon" style="background:linear-gradient(135deg,var(--accent-2),#2E5038);">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h2>Review & Confirm</h2>
                    </div>
                    <div class="card-body">

                        <div class="confirm-grid" id="confirmGrid">
                            <div class="confirm-item">
                                <div class="confirm-icon"><i class="fas fa-concierge-bell"></i></div>
                                <div class="confirm-text">
                                    <div class="ct-label">Selected Service</div>
                                    <div class="ct-val" id="confService">—</div>
                                </div>
                            </div>
                            <div class="confirm-item">
                                <div class="confirm-icon green"><i class="fas fa-calendar-check"></i></div>
                                <div class="confirm-text">
                                    <div class="ct-label">Date & Time</div>
                                    <div class="ct-val" id="confDateTime">—</div>
                                </div>
                            </div>
                            <div class="confirm-item">
                                <div class="confirm-icon purple"><i class="fas fa-user"></i></div>
                                <div class="confirm-text">
                                    <div class="ct-label">Client Name</div>
                                    <div class="ct-val" id="confName">—</div>
                                </div>
                            </div>
                            <div class="confirm-item">
                                <div class="confirm-icon gold"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="confirm-text">
                                    <div class="ct-label">Service Location</div>
                                    <div class="ct-val" id="confLocation">—</div>
                                </div>
                            </div>
                            <div class="confirm-item" style="grid-column:1/-1;">
                                <div class="confirm-icon" style="background:linear-gradient(135deg,#475569,#1e293b);"><i class="fas fa-align-left"></i></div>
                                <div class="confirm-text">
                                    <div class="ct-label">Your Description</div>
                                    <div class="ct-val" id="confDesc" style="font-size:.85rem;font-weight:500;color:var(--ink-light);"></div>
                                </div>
                            </div>
                        </div>

                        <div class="terms-check">
                            <input type="checkbox" id="termsAgree">
                            <label for="termsAgree">
                                I agree to the <a href="#">Terms of Service</a> and understand that this is a booking request — the provider will confirm the appointment.
                            </label>
                        </div>

                        <div id="termsError" class="field-error" style="font-size:.82rem;">
                            Please accept the terms to submit.
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(3)">
                                <i class="fas fa-arrow-left"></i> Edit Details
                            </button>
                            <button type="button" class="btn btn-success submit-booking-btn" onclick="submitBooking()" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Send Booking Request
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── SUCCESS SCREEN ─────────────────────────────── -->
                <div class="success-screen" id="successScreen">
                    <div class="success-icon"><i class="fas fa-check"></i></div>
                    <h2>Booking Request Sent!</h2>
                    <p>Your request has been successfully submitted.<br>
                    The provider will review and contact you shortly.</p>
                    <div class="booking-ref" id="bookingRef">#BK-2026-00478</div>
                    <div class="success-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i> My Bookings
                        </a>
                        <a href="providers.php" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Find More Providers
                        </a>
                    </div>
                </div>

            </div><!-- /bookingCard -->
                </form>
            <?php endif; ?>
        </div><!-- /formCol -->

        <!-- ── Right Sidebar ──────────────────────────────────── -->
        <aside class="sidebar">

            <!-- Booking Summary -->
            <div class="card summary-card">
                <div class="summary-head">
                    <h3><i class="fas fa-receipt" style="margin-right:.5rem;"></i> Booking Summary</h3>
                    <p>Review before you submit</p>
                </div>
                <div class="summary-body">

                    <div class="summary-row">
                        <span class="s-label">Provider</span>
                        <span class="s-val"><?php echo htmlspecialchars($provider['full_name']); ?></span>
                    </div>

                    <div class="summary-row">
                        <span class="s-label">Service</span>
                        <span class="s-val" id="sumService">
                            <?php if (!empty($_POST['service_id'])):
                                // find service name
                                foreach ($services as $svc) {
                                    if ($svc['id'] == $_POST['service_id']) { echo htmlspecialchars($svc['name']); break; }
                                }
                            else: ?>
                                <span class="summary-placeholder"><i class="far fa-hand-pointer"></i> Not selected</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span class="s-label">Date</span>
                        <span class="s-val" id="sumDate">
                            <?php if (!empty($_POST['preferred_date'])) {
                                echo htmlspecialchars(date('D, M j', strtotime($_POST['preferred_date'])));
                            } else { ?>
                                <span class="summary-placeholder"><i class="far fa-calendar"></i> Not chosen</span>
                            <?php } ?>
                        </span>
                    </div>

                    <div style="margin-top:1rem;border-top:1px solid var(--border);padding-top:1rem;">
                        <div class="terms-check" style="margin:0 0 .5rem 0;">
                            <input type="checkbox" id="termsAgreeSummary">
                            <label for="termsAgreeSummary" style="font-size:.9rem;color:var(--ink-light);">I accept the <a href="terms.php">terms & conditions</a> for this booking.</label>
                        </div>
                        <div id="termsErrorSummary" class="field-error" style="display:none;font-size:.82rem;margin-bottom:.6rem;">Please accept the terms to submit.</div>

                        <button type="button" id="submitBtnSummary" class="btn btn-success submit-booking-btn" style="width:100%;" onclick="submitBooking()">
                            <i class="fas fa-paper-plane"></i> Submit Booking Request
                        </button>
                    </div>

                </div>
            </div>

            <!-- Provider details and contact moved below main content -->


        </aside>
    </main>

</div><!-- /page-wrapper -->

<!-- Provider details and contact placed under all sections -->
<div style="max-width:1100px;margin:1.5rem auto;padding:0 1.5rem;">
    <div class="card provider-info-card" style="margin-bottom:1rem;">
        <div class="card-head">
            <div class="card-head-icon"><i class="fas fa-id-card"></i></div>
            <h2>Provider Details</h2>
        </div>
        <div class="card-body">
            <div class="pi-row">
                <div class="pi-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="pi-text">
                    <div class="pi-lbl">Location</div>
                    <div class="pi-val"><?php echo htmlspecialchars($provider['district'] ?: $provider['location'] ?: '—'); ?></div>
                </div>
            </div>
            <div class="pi-row">
                <div class="pi-icon"><i class="fas fa-star"></i></div>
                <div class="pi-text">
                    <div class="pi-lbl">Rating</div>
                    <div class="pi-val"><?php echo number_format($provider['average_rating'] ?? 0, 1); ?> ★ &nbsp;·&nbsp; <?php echo (int)($provider['total_reviews'] ?? 0); ?> reviews</div>
                </div>
            </div>
            <div class="pi-row">
                <div class="pi-icon"><i class="fas fa-check-circle"></i></div>
                <div class="pi-text">
                    <div class="pi-lbl">Jobs Completed</div>
                    <div class="pi-val"><?php echo (int)($provider['completed_jobs'] ?? 0); ?> jobs</div>
                </div>
            </div>
            <div class="pi-row">
                <div class="pi-icon"><i class="fas fa-bolt"></i></div>
                <div class="pi-text">
                    <div class="pi-lbl">Response Time</div>
                    <div class="pi-val"><?php echo htmlspecialchars($provider['avg_response_time_text'] ?? '~1 hour avg'); ?></div>
                </div>
            </div>
            <div class="pi-row">
                <div class="pi-icon"><i class="fas fa-calendar"></i></div>
                <div class="pi-text">
                    <div class="pi-lbl">Available Days</div>
                    <div class="pi-val">
                        <?php
                        $day_names = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        $available_days = implode(', ', array_map(fn($d) => $day_names[$d-1], $working_days));
                        echo htmlspecialchars($available_days);
                        ?>
                    </div>
                </div>
            </div>
            <div class="pi-row">
                <div class="pi-icon"><i class="fas fa-clock"></i></div>
                <div class="pi-text">
                    <div class="pi-lbl">Working Hours</div>
                    <div class="pi-val"><?php echo htmlspecialchars(($schedule['working_hours_start'] && $schedule['working_hours_end']) ? (date('g:i A', strtotime($schedule['working_hours_start'])) . ' – ' . date('g:i A', strtotime($schedule['working_hours_end']))) : '—'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:2rem;">
        <div class="card-head">
            <div class="card-head-icon"><i class="fas fa-phone-alt"></i></div>
            <h2>Contact Directly</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($provider['phone'])): ?>
            <a class="contact-pill" href="tel:<?php echo htmlspecialchars($provider['phone']); ?>">
                <div class="cp-icon"><i class="fas fa-phone"></i></div>
                <div>
                    <div class="cp-label">Phone</div>
                    <div class="cp-val"><?php echo htmlspecialchars($provider['phone']); ?></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (!empty($provider['whatsapp'])): ?>
            <a class="contact-pill" href="<?php echo (strpos($provider['whatsapp'],'http')===0) ? htmlspecialchars($provider['whatsapp']) : 'https://wa.me/' . preg_replace('/[^0-9]/','',$provider['whatsapp']); ?>" target="_blank">
                <div class="cp-icon" style="background:linear-gradient(135deg,#25D366,#128C7E);">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <div class="cp-label">WhatsApp</div>
                    <div class="cp-val">Chat Now</div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (!empty($provider['email'])): ?>
            <a class="contact-pill" href="mailto:<?php echo htmlspecialchars($provider['email']); ?>">
                <div class="cp-icon" style="background:linear-gradient(135deg,#EA4335,#c0392b);">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <div class="cp-label">Email</div>
                    <div class="cp-val"><?php echo htmlspecialchars($provider['email']); ?></div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
/* ─── State ─────────────────────────────────────────────── */
const state = {
    serviceId: null,
    service:   null,
    price:     null,
    duration:  null,
    date:      null,
    time:      null,
    name:      null,
    phone:     null,
    location:  null,
    desc:      null,
    urgency:   null,
    isNegotiable: false,
    proposedPrice: null
};

// expose scheduling rules for client-side validation
const workingDays = <?php echo json_encode($working_days); ?>; // e.g. [1,2,3]
const providerAvailability = <?php echo json_encode($availability_exceptions); ?>; // [{date,is_available,start_time,end_time}...]
const availabilityExceptions = providerAvailability.filter(a => parseInt(a.is_available,10) === 0).map(a => a.date);
const timeOffPeriods = <?php echo json_encode($time_off_periods); ?>; // [{start_date,end_date},...]
const fullyBookedDates = <?php echo json_encode($fully_booked_dates); ?>; // candidate blocked dates
const workingHoursStart = '<?php echo $schedule['working_hours_start'] ?? ''; ?>';
const workingHoursEnd = '<?php echo $schedule['working_hours_end'] ?? ''; ?>';
const breakStart = '<?php echo $schedule['break_start'] ?? ''; ?>';
const breakEnd = '<?php echo $schedule['break_end'] ?? ''; ?>';
const providerDefaultSlotDuration = <?php echo intval($slot_duration); ?>;
const bufferMinutes = <?php echo intval($buffer_minutes); ?>;
const maxDailyBookings = <?php echo intval($max_daily_bookings); ?>;
const serviceDurations = <?php echo json_encode(array_column($services, 'duration', 'id')); ?>;
let selectedServiceDuration = providerDefaultSlotDuration;
let effectiveWorkingDays = Array.isArray(workingDays) ? [...workingDays] : [];
let selectedServiceTimeSlots = null;
let selectedServiceBookingMode = 'request_approval';
let currentStep = 1;

// Debug output
console.log('Working Days (numeric):', workingDays);
console.log('Working Hours:', workingHoursStart, 'to', workingHoursEnd);
if (workingDays.length === 0) {
    console.warn('⚠️ WARNING: No working days configured for this provider!');
}

// Auto-select service if provided in URL
const autoServiceId = <?php echo $service_id; ?>;
const autoService = <?php echo $auto_selected_service ? json_encode($auto_selected_service) : 'null'; ?>;

/* ─── Step Navigation ────────────────────────────────────── */
function goToStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');

    document.querySelectorAll('.step-tab').forEach((t, i) => {
        t.classList.remove('active', 'done');
        if (i + 1 < n)  t.classList.add('done');
        if (i + 1 === n) t.classList.add('active');
    });

    currentStep = n;
    document.getElementById('currentStepHidden').value = n;
    if (n === 4) populateConfirm();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function nextStep(from) {
    if (!validateStep(from)) return;
    captureStep(from);
    goToStep(from + 1);
}

/* ─── Service Selection ──────────────────────────────────── */
function selectService(el, id, name, price, negotiable, duration) {
    document.querySelectorAll('.service-option').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
    state.serviceId = id;
    state.service  = name;
    state.price    = price;
    state.duration = duration + ' min';
    state.isNegotiable = negotiable;

    const serviceBookingMode = el.dataset.bookingMode || 'request_approval';
    const availabilityDaysStr = el.dataset.availabilityDays || '';
    const serviceTimeSlotsRaw = el.dataset.timeSlots || '';

    selectedServiceBookingMode = serviceBookingMode;
    state.bookingMode = serviceBookingMode;

    effectiveWorkingDays = availabilityDaysStr
        ? availabilityDaysStr.split(',').map(d => parseInt(d, 10)).filter(n => !Number.isNaN(n))
        : [...workingDays];

    try {
        selectedServiceTimeSlots = serviceTimeSlotsRaw ? JSON.parse(serviceTimeSlotsRaw) : null;
    } catch (error) {
        selectedServiceTimeSlots = serviceTimeSlotsRaw ? serviceTimeSlotsRaw.split('\n').map(item => item.trim()).filter(Boolean) : null;
    }
    state.availabilityDays = effectiveWorkingDays;
    state.timeSlots = selectedServiceTimeSlots;

    // Update slot duration from service setting (fallback to provider default)
    const serviceDurationValue = parseInt(duration, 10);
    if (!isNaN(serviceDurationValue) && serviceDurationValue > 0) {
        selectedServiceDuration = serviceDurationValue;
    } else if (serviceDurations[id]) {
        selectedServiceDuration = parseInt(serviceDurations[id], 10) || providerDefaultSlotDuration;
    } else {
        selectedServiceDuration = providerDefaultSlotDuration;
    }

    // Update sidebar (defensive - element may not exist in some render modes)
    const sumServiceNode = document.getElementById('sumService');
    const sumPriceNode = document.getElementById('sumPrice');
    const sumDurationNode = document.getElementById('sumDuration');
    const step1ErrorNode = document.getElementById('step1Error');

    if (sumServiceNode) sumServiceNode.textContent = name;
    if (sumPriceNode) sumPriceNode.textContent   = 'RWF ' + price;
    if (sumDurationNode) sumDurationNode.textContent = duration + ' minutes';
    if (step1ErrorNode) step1ErrorNode.style.display = 'none';

    // Recompute availability and times when a date is already selected
    const selectedDate = document.getElementById('preferredDate').value;
    renderDateOptions();
    if (selectedDate) {
        setSelectedDate(selectedDate, true);
        renderTimeSlots(selectedDate);
    }

    // Show/hide price input field if negotiable
    const priceField = document.getElementById('priceInputField');
    const priceInput = document.getElementById('clientProposedPrice');
    if (negotiable) {
        priceField.style.display = 'block';  // Show the price box
        priceInput.focus();
        // Extract range from price string (e.g., "RWF 5,000–RWF 15,000")
        const priceMatch = price.match(/RWF ([\d,]+).*?RWF ([\d,]+)/);
        if (priceMatch) {
            const minPrice = parseInt(priceMatch[1].replace(/,/g, ''), 10);
            const maxPrice = parseInt(priceMatch[2].replace(/,/g, ''), 10);
            document.getElementById('priceRangeHint').innerHTML = `<strong>Suggested range: RWF ${minPrice.toLocaleString()} – RWF ${maxPrice.toLocaleString()}</strong>`;
            priceInput.min = minPrice;
            priceInput.max = maxPrice;
        }
    } else {
        priceField.style.display = 'none';  // Hide the price box
        priceInput.value = '';
    }
}

/* ─── Time Selection ─────────────────────────────────────── */
function selectTime(el, val) {
    if (el.classList.contains('unavailable')) return;
    document.querySelectorAll('.time-slot').forEach(t => t.classList.remove('selected'));
    el.classList.add('selected');
    const selectedTimeInput = document.getElementById('selectedTime');
    if (selectedTimeInput) selectedTimeInput.value = val;
    state.time = el.textContent.trim();
    const sumTimeNode = document.getElementById('sumTime');
    if (sumTimeNode) sumTimeNode.textContent = state.time;
    const timeErrorNode = document.getElementById('timeError');
    if (timeErrorNode) timeErrorNode.style.display = 'none';
}

/* ─── Character Count ────────────────────────────────────── */
function updateCharCount(el) {
    const n = el.value.length;
    const div = document.getElementById('charCount');
    div.textContent = n + ' / 500';
    div.className = 'char-count' + (n > 450 ? ' limit' : n > 350 ? ' warn' : '');
}

/* ─── Time Slot Utilities ────────────────────────────────── */
function formatTime(raw) {
    const parts = raw.split(':');
    if (parts.length < 2) return raw;
    let h = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    const suffix = h >= 12 ? 'PM' : 'AM';
    const hour = ((h + 11) % 12) + 1;
    return `${hour}:${m.toString().padStart(2,'0')} ${suffix}`;
}

function parseMinutes(raw) {
    const parts = raw.split(':');
    if (parts.length < 2) return 0;
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
}

function parseServiceTimeSlots() {
    if (!selectedServiceTimeSlots) return null;
    if (Array.isArray(selectedServiceTimeSlots)) {
        return selectedServiceTimeSlots.map(slot => {
            if (typeof slot === 'string') return slot.trim();
            if (slot && slot.start && slot.end) return `${slot.start}-${slot.end}`;
            return null;
        }).filter(Boolean);
    }
    if (typeof selectedServiceTimeSlots === 'string') {
        try {
            const parsed = JSON.parse(selectedServiceTimeSlots);
            if (Array.isArray(parsed)) {
                return parsed.map(slot => {
                    if (typeof slot === 'string') return slot.trim();
                    if (slot && slot.start && slot.end) return `${slot.start}-${slot.end}`;
                    return null;
                }).filter(Boolean);
            }
        } catch (error) {
            return selectedServiceTimeSlots.split('\n').map(item => item.trim()).filter(Boolean);
        }
    }
    return null;
}

function toISODate(year, month, day) {
    const m = (month + 1).toString().padStart(2, '0');
    const d = day.toString().padStart(2, '0');
    return `${year}-${m}-${d}`;
}

function isDateDisabled(dateObj) {
    const today = new Date();
    today.setHours(0,0,0,0);
    if (dateObj < today) return true;

    const iso = toISODate(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());

    const dow = dateObj.getDay() === 0 ? 7 : dateObj.getDay();
    if (!effectiveWorkingDays.includes(dow)) return true;
    const availabilityEntry = providerAvailability.find(item => item.date === iso);
    if (availabilityEntry && parseInt(availabilityEntry.is_available, 10) === 0) return true;
    if (fullyBookedDates.includes(iso)) return true;

    for (const period of timeOffPeriods) {
        if (iso >= period.start_date && iso <= period.end_date) return true;
    }
    return false;
}

function setSelectedDate(dateString, suppressSlotRender = false) {
    const hidden = document.getElementById('preferredDate');
    if (!hidden) return;
    hidden.value = dateString;

    const sumDate = document.getElementById('sumDate');
    if (sumDate) {
        const dt = new Date(dateString + 'T00:00:00');
        if (!Number.isNaN(dt.getTime())) {
            sumDate.textContent = dt.toLocaleDateString('en-RW', { weekday:'short', month:'short', day:'numeric' });
        }
    }

    const dateError = document.getElementById('dateError');
    if (dateError) dateError.style.display = 'none';

    if (!suppressSlotRender) {
        renderTimeSlots(dateString);
    }
}

let calendarBaseDate = new Date();

function getDaysInMonth(year, month) {
    return new Date(year, month + 1, 0).getDate();
}

function renderDateOptions() {
    const container = document.getElementById('calendarGrid');
    const label = document.getElementById('calendarMonthLabel');
    if (!container || !label) {
        console.error('renderDateOptions: calendarGrid or calendarMonthLabel not found');
        return;
    }

    container.innerHTML = '';

    const year = calendarBaseDate.getFullYear();
    const month = calendarBaseDate.getMonth();
    label.textContent = calendarBaseDate.toLocaleDateString('en-RW', { month: 'long', year: 'numeric' });

    if (!effectiveWorkingDays || effectiveWorkingDays.length === 0) {
        container.innerHTML = '<div style="grid-column:1/-1;padding:.7rem; text-align:center; color:#bf0000;">No working days are configured for this service or provider.</div>';
        return;
    }

    const firstDayOfMonth = new Date(year, month, 1);
    const firstDayIndex = firstDayOfMonth.getDay() === 0 ? 6 : firstDayOfMonth.getDay() - 1; // Monday-first
    for (let i = 0; i < firstDayIndex; i++) {
        const blankCell = document.createElement('div');
        blankCell.className = 'calendar-day disabled';
        blankCell.style.visibility = 'hidden';
        container.appendChild(blankCell);
    }

    const daysInMonth = getDaysInMonth(year, month);
    let firstAvailableIso = null;
    const selectedIso = document.getElementById('preferredDate')?.value || '';
    let visibleDays = 0;

    for (let day = 1; day <= daysInMonth; day++) {
        const dayDate = new Date(year, month, day);
        const iso = toISODate(year, month, day);
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        dayCell.dataset.date = iso;

        // Force readable number content in each calendar tile
        const numberSpan = document.createElement('span');
        numberSpan.className = 'calendar-day-number';
        numberSpan.textContent = day.toString();
        dayCell.appendChild(numberSpan);

        if (selectedIso === iso) {
            dayCell.classList.add('selected');
        }

        if (isDateDisabled(dayDate)) {
            dayCell.classList.add('disabled');
            dayCell.title = 'Unavailable';
        } else {
            dayCell.classList.add('available');
            if (!firstAvailableIso) {
                firstAvailableIso = iso;
            }
            dayCell.addEventListener('click', () => {
                document.querySelectorAll('.calendar-day.selected').forEach(n => n.classList.remove('selected'));
                dayCell.classList.add('selected');
                setSelectedDate(iso);
            });
            visibleDays += 1;
        }

        container.appendChild(dayCell);
    }

    // fill trailing empty cells to maintain consistent grid rows
    const totalCells = firstDayIndex + daysInMonth;
    const trailing = (7 - (totalCells % 7)) % 7;
    for (let i = 0; i < trailing; i++) {
        const blankCell = document.createElement('div');
        blankCell.className = 'calendar-day disabled';
        blankCell.style.visibility = 'hidden';
        container.appendChild(blankCell);
    }

    if (!selectedIso && firstAvailableIso) {
        setSelectedDate(firstAvailableIso, true);
        const candidateEl = container.querySelector(`.calendar-day[data-date="${firstAvailableIso}"]`);
        if (candidateEl) {
            candidateEl.classList.add('selected');
        }
        renderTimeSlots(firstAvailableIso);
    }

    if (visibleDays === 0) {
        const msg = document.createElement('div');
        msg.style.gridColumn = '1 / -1';
        msg.style.padding = '.7rem';
        msg.style.textAlign = 'center';
        msg.style.color = '#9f1239';
        msg.textContent = 'No available dates in this month.';
        container.appendChild(msg);
    }
}

function previousMonth() {
    calendarBaseDate.setMonth(calendarBaseDate.getMonth() - 1);
    renderDateOptions();
}

function nextMonth() {
    calendarBaseDate.setMonth(calendarBaseDate.getMonth() + 1);
    renderDateOptions();
}


function buildTimeSlots(dateString) {
    const slots = [];

    if (!dateString || !workingHoursStart || !workingHoursEnd) {
        return slots;
    }

    const d = new Date(dateString + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return slots;

    let weekday = d.getUTCDay();
    weekday = weekday === 0 ? 7 : weekday;

    if (!effectiveWorkingDays.includes(weekday)) {
        return slots;
    }

    const availabilityEntry = providerAvailability.find(item => item.date === dateString);

    if (availabilityEntry && parseInt(availabilityEntry.is_available, 10) === 0) {
        return slots;
    }

    if (fullyBookedDates.includes(dateString)) {
        return slots;
    }

    for (const off of timeOffPeriods) {
        if (dateString >= off.start_date && dateString <= off.end_date) {
            return slots;
        }
    }

    // Decide slot bounds for the selected date
    let dateStart = workingHoursStart;
    let dateEnd = workingHoursEnd;

    if (availabilityEntry && availabilityEntry.start_time && availabilityEntry.end_time) {
        dateStart = availabilityEntry.start_time;
        dateEnd = availabilityEntry.end_time;
    }

    const serviceSlotRanges = parseServiceTimeSlots();
    if (Array.isArray(serviceSlotRanges) && serviceSlotRanges.length > 0) {
        for (const range of serviceSlotRanges) {
            const parts = range.split('-').map(part => part.trim());
            if (parts.length !== 2) continue;
            const rangeStart = parts[0];
            const rangeEnd = parts[1];
            const startRange = parseMinutes(rangeStart);
            const endRange = parseMinutes(rangeEnd);
            if (endRange <= startRange) continue;

            const effectiveDuration = (selectedServiceDuration && selectedServiceDuration > 0) ? selectedServiceDuration : providerDefaultSlotDuration;
            const step = Math.max(15, effectiveDuration);
            const buffer = Math.max(0, bufferMinutes);
            let t = startRange;

            while (t + step <= endRange) {
                const slotEnd = t + step;
                const hours = Math.floor(t / 60);
                const minutes = t % 60;
                slots.push(`${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}`);
                t = slotEnd + buffer;
            }
        }
        return slots;
    }

    const start = parseMinutes(dateStart);
    const end = parseMinutes(dateEnd);
    const effectiveDuration = (selectedServiceDuration && selectedServiceDuration > 0) ? selectedServiceDuration : providerDefaultSlotDuration;
    const step = Math.max(15, effectiveDuration);
    const buffer = Math.max(0, bufferMinutes);
    const breakStartMin = breakStart ? parseMinutes(breakStart) : null;
    const breakEndMin = breakEnd ? parseMinutes(breakEnd) : null;

    let t = start;
    while (t + step <= end) {
        const slotEnd = t + step;

        let inBreak = false;
        if (breakStartMin !== null && breakEndMin !== null) {
            if ((t < breakEndMin && slotEnd > breakStartMin)) {
                inBreak = true;
            }
        }

        if (!inBreak) {
            const hours = Math.floor(t / 60);
            const minutes = t % 60;
            slots.push(`${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}`);
        }

        t = slotEnd + buffer;
    }

    return slots;
}

function renderTimeSlots(dateValue) {
    const container = document.getElementById('timeSlots');
    container.innerHTML = '';

    if (fullyBookedDates.includes(dateValue)) {
        container.innerHTML = '<div class="time-slot unavailable">Fully booked for selected date</div>';
        document.getElementById('selectedTime').value = '';
        return;
    }

    const available = buildTimeSlots(dateValue);

    if (available.length === 0) {
        container.innerHTML = '<div class="time-slot unavailable">No available slots for selected date</div>';
        document.getElementById('selectedTime').value = '';
        return;
    }

    available.forEach(time => {
        const el = document.createElement('div');
        el.className = 'time-slot';
        el.textContent = formatTime(time);
        el.addEventListener('click', () => selectTime(el, time));
        container.appendChild(el);
    });

    const selected = document.getElementById('selectedTime').value;
    if (selected) {
        const matched = Array.from(container.children).find(item => item.textContent.startsWith(formatTime(selected)));
        if (matched) {
            matched.classList.add('selected');
        }
    }
}

/* ─── Validation ─────────────────────────────────────────── */
function validateStep(n) {
    if (n === 1) {
        if (!state.service) {
            document.getElementById('step1Error').style.display = 'block';
            return false;
        }
        // Validate price if service is negotiable - OPTIONAL, can be filled later
        if (state.isNegotiable) {
            const priceInput = document.getElementById('clientProposedPrice');
            const priceVal = priceInput.value.trim() ? parseInt(priceInput.value, 10) : null;
            
            // Only validate if a price was actually entered
            if (priceInput.value.trim() && (isNaN(priceVal) || priceVal <= 0)) {
                document.getElementById('priceError').style.display = 'block';
                return false;
            }
            document.getElementById('priceError').style.display = 'none';
            state.proposedPrice = priceVal;
        } else {
            state.proposedPrice = null;
        }
        return true;
    }
    if (n === 2) {
        let ok = true;
        const d = document.getElementById('preferredDate').value;
        const t = document.getElementById('selectedTime').value;
        if (!d) {
            document.getElementById('dateError').textContent = 'Please select a date.';
            document.getElementById('dateError').style.display = 'block'; ok = false;
        } else {
            // check working day
            const dow = new Date(d).getUTCDay(); // 0=Sun,6=Sat
            const dayNum = dow === 0 ? 7 : dow; // convert to 1-7 (1=Mon)
            const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            console.log(`Date: ${d}, Day of week (numeric): ${dayNum} (${dayNames[dow]}), Working days: ${workingDays}`);
            if (!effectiveWorkingDays.includes(dayNum)) {
                document.getElementById('dateError').textContent = 'Service is not available on the selected day.';
                document.getElementById('dateError').style.display = 'block'; ok = false;
            } else if (fullyBookedDates.includes(d)) {
                document.getElementById('dateError').textContent = 'Selected date is fully booked. Please pick another date.';
                document.getElementById('dateError').style.display = 'block'; ok = false;
            } else if (availabilityExceptions.includes(d)) {
                document.getElementById('dateError').textContent = 'Provider is not available on this date.';
                document.getElementById('dateError').style.display = 'block'; ok = false;
            } else {
                // check time off
                for (const period of timeOffPeriods) {
                    if (d >= period.start_date && d <= period.end_date) {
                        document.getElementById('dateError').textContent = 'Provider is on time off.';
                        document.getElementById('dateError').style.display = 'block'; ok = false;
                        break;
                    }
                }
            }
            if (ok) document.getElementById('dateError').style.display = 'none';
        }
        if (!t) { document.getElementById('timeError').textContent = 'Please select a time slot.'; document.getElementById('timeError').style.display = 'block'; ok = false; }
        else {
            const available = buildTimeSlots(d);
            if (!available.includes(t)) {
                document.getElementById('timeError').textContent = 'The selected time is not available for this date.';
                document.getElementById('timeError').style.display = 'block'; ok = false;
            } else {
                document.getElementById('timeError').style.display = 'none';
            }
        }
        return ok;
    }
    if (n === 3) {
        let ok = true;
        const checks = [
            { id: 'clientName',     fid: 'nameField',     test: v => v.trim().length >= 2 },
            { id: 'clientPhone',    fid: 'phoneField',     test: v => /^[0-9\s\+\-]{7,}$/.test(v.trim()) },
            { id: 'clientLocation', fid: 'locationField',  test: v => v.trim().length >= 3 },
            { id: 'serviceDesc',    fid: 'descField',      test: v => v.trim().length >= 10 }
        ];
        checks.forEach(c => {
            const val = document.getElementById(c.id).value;
            const field = document.getElementById(c.fid);
            if (!c.test(val)) { field.classList.add('has-error'); ok = false; }
            else              { field.classList.remove('has-error'); }
        });
        return ok;
    }
    return true;
}

/* ─── Capture Step State ─────────────────────────────────── */
function captureStep(n) {
    if (n === 2) {
        const d = document.getElementById('preferredDate').value;
        if (d) {
            const dt = new Date(d + 'T00:00:00');
            state.date = dt.toLocaleDateString('en-RW', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
            document.getElementById('sumDate').textContent = state.date;
        }
    }
    if (n === 3) {
        state.name     = document.getElementById('clientName').value.trim();
        state.phone    = document.getElementById('clientPhone').value.trim();
        state.location = document.getElementById('clientLocation').value.trim();
        state.desc     = document.getElementById('serviceDesc').value.trim();
        state.urgency  = document.getElementById('urgencyLevel').value;
    }
}

/* ─── Populate Confirm Screen ────────────────────────────── */
function populateConfirm() {
    document.getElementById('confService').textContent  = state.service   || '—';
    document.getElementById('confDateTime').textContent = (state.date ? state.date : '—') + (state.time ? ' · ' + state.time : '');
    document.getElementById('confName').textContent     = state.name      || '—';
    document.getElementById('confLocation').textContent = state.location  || '—';
    document.getElementById('confDesc').textContent     = state.desc      || '—';
}

/* ─── Submit Booking ─────────────────────────────────────── */
function submitBooking() {
    const terms = document.getElementById('termsAgree') || document.getElementById('termsAgreeSummary');
    const err   = document.getElementById('termsError') || document.getElementById('termsErrorSummary');
    if (!terms || !terms.checked) { if (err) err.style.display = 'block'; return; }
    if (err) err.style.display = 'none';

    document.querySelectorAll('.submit-booking-btn').forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
    });

    // Add final_submit flag to indicate this is the actual form submission
    const finalSubmitField = document.createElement('input');
    finalSubmitField.type = 'hidden';
    finalSubmitField.name = 'final_submit';
    finalSubmitField.value = '1';
    document.getElementById('bookingForm').appendChild(finalSubmitField);

    // submit the form normally; hidden inputs already carry values
    const form = document.getElementById('bookingForm');
    if (form) {
        form.submit();
    }
}

/* ─── Init ───────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    // Set current step from POST data
    currentStep = parseInt('<?php echo $_POST['current_step'] ?? 1; ?>');

    // Populate form fields from POST data
    document.getElementById('clientName').value = '<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>';
    document.getElementById('clientPhone').value = '<?php echo htmlspecialchars($_POST['client_phone'] ?? ''); ?>';
    document.getElementById('clientLocation').value = '<?php echo htmlspecialchars($_POST['client_location'] ?? ''); ?>';
    document.getElementById('serviceDesc').value = '<?php echo htmlspecialchars($_POST['serviceDesc'] ?? ''); ?>';
    document.getElementById('preferredDate').value = '<?php echo htmlspecialchars($_POST['preferred_date'] ?? ''); ?>';
    document.getElementById('selectedTime').value = '<?php echo htmlspecialchars($_POST['preferred_time'] ?? ''); ?>';
    document.getElementById('urgencyLevel').value = '<?php echo htmlspecialchars($_POST['urgency_level'] ?? 'normal'); ?>';
    <?php if (!empty($_POST['client_proposed_price'])): ?>
    document.getElementById('clientProposedPrice').value = '<?php echo htmlspecialchars($_POST['client_proposed_price']); ?>';
    <?php endif; ?>

    // Auto-select service if provided via URL parameter
    if (autoServiceId && autoService) {
        // Find the service element and click it to auto-select
        const serviceElement = document.querySelector(`input[value="${autoServiceId}"]`);
        if (serviceElement) {
            const serviceCard = serviceElement.closest('.service-option');
            if (serviceCard) {
                // Simulate click to select the service
                selectService(
                    serviceCard, 
                    autoService.id, 
                    autoService.name, 
                    'RWF ' + autoService.price, 
                    autoService.negotiable, 
                    autoService.duration || 60
                );
                
                // Move to step 2 after a brief delay to ensure selection is processed
                setTimeout(() => {
                    goToStep(2);
                }, 300);
            }
        }
    }

    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    const dateField = document.getElementById('preferredDate');
    dateField.min = today;

    // Display working hours text
    const hoursText = document.getElementById('availableHours');
    if (workingHoursStart && workingHoursEnd) {
        let text = `${formatTime(workingHoursStart)} – ${formatTime(workingHoursEnd)}`;
        const durationForLabel = (selectedServiceDuration && selectedServiceDuration > 0) ? selectedServiceDuration : providerDefaultSlotDuration;
        if (durationForLabel > 0) {
            text += ` · Slot ${durationForLabel} min`;
        }
        if (bufferMinutes > 0) {
            text += ` + ${bufferMinutes} min cleanup`; 
        }
        if (maxDailyBookings > 0) {
            text += ` · Max ${maxDailyBookings} bookings/day`;
        }
        hoursText.textContent = text;
    } else {
        hoursText.textContent = 'Not configured';
    }

    // Hide native date field and render mini calendar UI
    dateField.style.display = 'none';

    const prevButton = document.getElementById('prevMonthBtn');
    const nextButton = document.getElementById('nextMonthBtn');
    if (prevButton) prevButton.addEventListener('click', previousMonth);
    if (nextButton) nextButton.addEventListener('click', nextMonth);

    renderDateOptions();

    // Initial render for prefilled date
    if (dateField.value) {
        setSelectedDate(dateField.value, true);
        renderDateOptions();
        renderTimeSlots(dateField.value);
    }


    // restore selection if post values exist
    const prevTime = document.getElementById('selectedTime').value;
    if (prevTime) {
        document.querySelectorAll('.time-slot').forEach(ts => {
            if (ts.textContent.trim().includes(prevTime)) {
                ts.classList.add('selected');
            }
        });
    }
    const prevServiceId = '<?php echo $_POST['service_id'] ?? ''; ?>';
    if (prevServiceId) {
        const radio = document.querySelector('input[name="service_id"][value="' + prevServiceId + '"]');
        if (radio) {
            radio.checked = true;
            const label = radio.closest('.service-option');
            if (label) {
                label.classList.add('selected');
                selectService(
                    label,
                    prevServiceId,
                    label.dataset.serviceName || '',
                    label.dataset.servicePrice || '',
                    label.dataset.serviceNegotiable === '1',
                    label.dataset.serviceDuration || '60'
                );
            }
        }
    }

    // Go to the current step
    goToStep(currentStep);

});
</script>
</body>
</html>