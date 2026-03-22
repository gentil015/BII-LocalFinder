<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/chat.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    // Allow providers to access but show maintenance warning
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$total_bookings = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$provider['id']]);
$pending_bookings = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$total_reviews = (int) $stmt->fetchColumn();

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = sanitize($_POST['status']);
    
    // Check if provider is allowed to reject bookings
    if ($new_status === 'cancelled' && !isProviderRejectionAllowed()) {
        $errors[] = "Booking rejection is currently disabled by admin.";
    } else {
        // Verify booking belongs to provider
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND provider_id = ?");
        $stmt->execute([$booking_id, $provider['id']]);
        
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE bookings SET status = ?, responded_at = NOW(), updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_status, $booking_id])) {
                // Update total_jobs if completed and mark payment as completed
                if ($new_status === 'completed') {
                    $stmt = $db->prepare("UPDATE service_providers SET total_jobs = total_jobs + 1 WHERE id = ?");
                    $stmt->execute([$provider['id']]);
                    
                    // Auto-mark payment as completed when booking is completed
                    $stmt = $db->prepare("UPDATE bookings SET payment_status = 'completed' WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    
                    // Check if rating is required after completion
                    if (isRatingRequiredAfterCompletion()) {
                        // You could trigger a review reminder here
                        error_log("Review reminder should be sent for booking: " . $booking_id);
                    }
                }

                // Notify client in chat about booking status change
                $clientRecipient = null;
                $stmtClient = $db->prepare("SELECT client_id FROM bookings WHERE id = ?");
                $stmtClient->execute([$booking_id]);
                $bookingTarget = $stmtClient->fetch(PDO::FETCH_ASSOC);
                if ($bookingTarget && !empty($bookingTarget['client_id'])) {
                    $clientRecipient = (int)$bookingTarget['client_id'];
                }

                if ($clientRecipient) {
                    sendMessage($_SESSION['user_id'], $clientRecipient, "Booking #{$booking_id} status changed to " . ucfirst($new_status));
                }
                
                $success = "Booking status updated successfully!";
                
                // Send notification if enabled
                if (isEmailNotificationsEnabled()) {
                    require_once '../includes/mailer.php';
                    
                    // Get booking details for notification
                    $stmt = $db->prepare("
                        SELECT u.email, u.full_name, b.service_description 
                        FROM bookings b 
                        JOIN users u ON b.client_id = u.id 
                        WHERE b.id = ?
                    ");
                    $stmt->execute([$booking_id]);
                    $booking_details = $stmt->fetch();
                    
                    if ($booking_details) {
                        Mailer::sendBookingStatusUpdate(
                            $booking_details['email'],
                            $booking_details['full_name'],
                            $provider['full_name'],
                            $booking_details['service_description'],
                            $new_status
                        );
                    }
                }
            } else {
                $errors[] = "Failed to update booking status";
            }
        } else {
            $errors[] = "Invalid booking";
        }
    }
}

// Handle bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = sanitize($_POST['bulk_action_type']);
    $booking_ids = $_POST['booking_ids'] ?? [];
    
    if (!empty($booking_ids) && in_array($action, ['confirmed', 'cancelled'])) {
        // Check if provider is allowed to reject bookings
        if ($action === 'cancelled' && !isProviderRejectionAllowed()) {
            $errors[] = "Booking rejection is currently disabled by admin.";
        } else {
            $placeholders = str_repeat('?,', count($booking_ids) - 1) . '?';
            $params = array_merge($booking_ids, [$provider['id']]);
            
            $stmt = $db->prepare("
                UPDATE bookings 
                SET status = '$action', responded_at = NOW(), updated_at = NOW()
                WHERE id IN ($placeholders) AND provider_id = ? AND status = 'pending'
            ");
            
            if ($stmt->execute($params)) {
                $success = count($booking_ids) . " booking(s) updated successfully!";
                
                // Send notifications if enabled
                if (isEmailNotificationsEnabled() && !empty($booking_ids)) {
                    require_once '../includes/mailer.php';
                    
                    foreach ($booking_ids as $booking_id) {
                        $stmt = $db->prepare("
                            SELECT u.email, u.full_name, b.service_description 
                            FROM bookings b 
                            JOIN users u ON b.client_id = u.id 
                            WHERE b.id = ?
                        ");
                        $stmt->execute([$booking_id]);
                        $booking_details = $stmt->fetch();
                        
                        if ($booking_details) {
                            Mailer::sendBookingStatusUpdate(
                                $booking_details['email'],
                                $booking_details['full_name'],
                                $provider['full_name'],
                                $booking_details['service_description'],
                                $action
                            );
                        }
                    }
                }
            } else {
                $errors[] = "Failed to update bookings";
            }
        }
    } else {
        $errors[] = "Please select bookings to update";
    }
}

// Get view type (bookings or offers)
$view = isset($_GET['view']) ? sanitize($_GET['view']) : 'bookings';

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle offer response (accept/reject/counter-offer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offer_action'])) {
    $offer_id = intval($_POST['offer_id']);
    $action = sanitize($_POST['offer_action']);
    
    // Verify offer belongs to this provider
    $stmt = $db->prepare("SELECT * FROM service_offers WHERE id = ? AND provider_id = ?");
    $stmt->execute([$offer_id, $_SESSION['user_id']]);
    $offer = $stmt->fetch();
    
    if ($offer) {
        if ($action === 'accept') {
            // OPTION 1: AUTO-CONFIRM BOOKING
            // When provider accepts offer, automatically:
            // 1. Accept the offer
            // 2. Finalize the price in finalized_service_prices
            // 3. Update booking status to 'confirmed'
            
            try {
                // Step 1: Update offer status to 'accepted'
                $stmt = $db->prepare("UPDATE service_offers SET status = 'accepted', responded_at = NOW() WHERE id = ?");
                if (!$stmt->execute([$offer_id])) {
                    throw new Exception("Failed to accept offer");
                }
                
                // Step 2: Create/finalize the price record
                $finalized_price = $offer['offered_price']; // Use the client's offered price
                $stmt = $db->prepare("
                    INSERT INTO finalized_service_prices 
                    (booking_id, service_id, client_id, provider_id, finalized_price, negotiation_rounds, client_final_offer_id, status)
                    VALUES (?, ?, ?, ?, ?, 1, ?, 'active')
                    ON DUPLICATE KEY UPDATE
                    finalized_price = VALUES(finalized_price),
                    updated_at = NOW()
                ");
                if (!$stmt->execute([$offer['booking_id'], $offer['service_id'], $offer['client_id'], $_SESSION['user_id'], $finalized_price, $offer_id])) {
                    throw new Exception("Failed to finalize price");
                }
                
                // Step 3: Update booking status to 'confirmed' with final price
                $stmt = $db->prepare("
                    UPDATE bookings 
                    SET status = 'confirmed', 
                        responded_at = NOW(),
                        amount = ?
                    WHERE id = ? AND provider_id = ?
                ");
                if (!$stmt->execute([$finalized_price, $offer['booking_id'], $provider['id']])) {
                    throw new Exception("Failed to confirm booking");
                }
                
                // Step 4: Log this action in negotiation history
                $stmt = $db->prepare("
                    INSERT INTO negotiation_history 
                    (booking_id, offer_id, action_type, price_offered, actor_id, actor_type, notes)
                    VALUES (?, ?, 'offer_accepted', ?, ?, 'provider', 'Offer accepted by provider - Booking confirmed')
                ");
                $stmt->execute([$offer['booking_id'], $offer_id, $finalized_price, $_SESSION['user_id']]);
                
                // Send notification to client
                if (isEmailNotificationsEnabled()) {
                    require_once '../includes/mailer.php';
                    
                    // Notify client
                    $stmt = $db->prepare("
                        SELECT u.email, u.full_name FROM users u WHERE u.id = ?
                    ");
                    $stmt->execute([$offer['client_id']]);
                    $client_info = $stmt->fetch();
                    
                    if ($client_info) {
                        Mailer::sendOfferAcceptedNotification(
                            $client_info['email'],
                            $client_info['full_name'],
                            $provider['full_name'],
                            $finalized_price,
                            $offer['booking_id']
                        );
                    }
                    
                    // Notify provider of confirmation (optional but good UX)
                    Mailer::sendOfferAcceptanceConfirmation(
                        $_SESSION['user_email'] ?? $provider['email'],
                        $provider['full_name'],
                        $client_info['full_name'],
                        $finalized_price,
                        $offer['booking_id']
                    );
                }
                
                $success = "✅ Offer accepted and booking confirmed! Final price: RWF " . number_format($finalized_price, 0);

                // Add chat event for booking confirmation
                sendMessage($_SESSION['user_id'], $offer['client_id'], "Booking #{$offer['booking_id']} has been confirmed by provider at RWF " . number_format($finalized_price, 0));
                
            } catch (Exception $e) {
                error_log("Offer acceptance error: " . $e->getMessage());
                $errors[] = "Failed to accept offer: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("UPDATE service_offers SET status = 'rejected', responded_at = NOW() WHERE id = ?");
            if ($stmt->execute([$offer_id])) {
                // Update booking responded_at for ML tracking
                $stmt = $db->prepare("UPDATE bookings SET responded_at = NOW() WHERE id = (SELECT booking_id FROM service_offers WHERE id = ?)");
                $stmt->execute([$offer_id]);
                
                $success = "Offer rejected successfully!";
            } else {
                $errors[] = "Failed to reject offer";
            }
        } elseif ($action === 'counter') {
            $counter_price = isset($_POST['counter_price']) ? floatval($_POST['counter_price']) : 0;
            $counter_notes = isset($_POST['counter_notes']) ? sanitize($_POST['counter_notes']) : '';
            
            if ($counter_price > 0) {
                // Get offer details to access service_id and client_id
                $stmt = $db->prepare("SELECT service_id, client_id FROM service_offers WHERE id = ?");
                $stmt->execute([$offer_id]);
                $offer_details = $stmt->fetch();
                
                if ($offer_details) {
                    // Create counter offer
                    $expires_at = date('Y-m-d H:i:s', strtotime('+3 days'));
                    $stmt = $db->prepare("
                        INSERT INTO service_counteroffers (offer_id, service_id, provider_id, client_id, proposed_price, response_notes, expires_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$offer_id, $offer_details['service_id'], $_SESSION['user_id'], $offer_details['client_id'], $counter_price, $counter_notes, $expires_at])) {
                        $success = "Counter-offer sent successfully!";
                    } else {
                        $errors[] = "Failed to send counter-offer";
                    }
                } else {
                    $errors[] = "Could not find offer details";
                }
            } else {
                $errors[] = "Counter-offer price must be greater than 0";
            }
        }
    } else {
        $errors[] = "Invalid offer or unauthorized";
    }
}

// Determine which column (if any) in bookings references provider_services
if ($view === 'bookings') {
    $joinColumn = null;
    try {
        $cols = $db->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
        $candidates = ['service_id', 'provider_service_id', 'provider_services_id', 'service', 'provider_service'];
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                $joinColumn = $c;
                break;
            }
        }
    } catch (Throwable $e) {
        // If SHOW COLUMNS fails, log and continue without join
        error_log('Bookings: failed to inspect bookings columns: ' . $e->getMessage());
        $joinColumn = null;
    }
    
    // Build query using detected join column (if any)
    $serviceSelect = $joinColumn ? "s.name as service_name" : "NULL as service_name";
    $serviceJoin = $joinColumn ? "LEFT JOIN provider_services s ON b.{$joinColumn} = s.id" : "";
    
    $sql = "
        SELECT b.*, 
               u.full_name as client_name, u.phone as client_phone, 
               u.email as client_email, u.profile_image as client_image,
               {$serviceSelect}
        FROM bookings b
        JOIN users u ON b.client_id = u.id
        {$serviceJoin}
        WHERE b.provider_id = ?
    ";
    
    $params = [$provider['id']];
    
    if (!empty($status_filter)) {
        $sql .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $sql .= " AND DATE(b.preferred_date) = ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.full_name LIKE ? OR b.service_description LIKE ? OR " . ($joinColumn ? "s.name LIKE ?" : "b.service_description LIKE ?") . ")";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count safely
    $count_sql = preg_replace('/^SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql);
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_bookings = (int) $count_stmt->fetchColumn();
    
    // add pagination total pages to avoid undefined variable
    $total_pages = ($per_page > 0) ? (int) max(1, ceil($total_bookings / $per_page)) : 1;
    
    // Get bookings with pagination
    $sql .= " ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'confirmed' THEN 2
            WHEN b.status = 'completed' THEN 3
            ELSE 4
        END,
        b.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} else {
    // OFFERS VIEW
    $sql = "
        SELECT so.*, 
               u.full_name as client_name, u.phone as client_phone, 
               u.email as client_email, u.profile_image as client_image,
               ps.name as service_name
        FROM service_offers so
        JOIN users u ON so.client_id = u.id
        JOIN provider_services ps ON so.service_id = ps.id
        WHERE so.provider_id = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    if (!empty($status_filter)) {
        $sql .= " AND so.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.full_name LIKE ? OR ps.name LIKE ? OR so.offered_price LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count
    $count_sql = preg_replace('/^SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql);
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = (int) $count_stmt->fetchColumn();
    
    // add pagination total pages
    $total_pages = ($per_page > 0) ? (int) max(1, ceil($total_items / $per_page)) : 1;
    
    // Get offers with pagination
    $sql .= " ORDER BY 
        CASE 
            WHEN so.status = 'pending' THEN 1
            WHEN so.status = 'accepted' THEN 2
            WHEN so.status = 'rejected' THEN 3
            ELSE 4
        END,
        so.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo getPlatformName(); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
            --surface:       #ffffff;
            --surface-2:     #f7f8fc;
            --border:        #e8eaf0;
            --border-subtle: #f0f2f7;
            --text-primary:  #0f1117;
            --text-secondary:#6b7280;
            --text-muted:    #9ca3af;
            --sidebar-width: 260px;
            --radius-sm:     8px;
            --radius-md:     12px;
            --radius-lg:     16px;
            --radius-xl:     20px;
            --shadow-xs:     0 1px 3px rgba(0,0,0,0.06);
            --shadow-sm:     0 2px 8px rgba(0,0,0,0.07);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.09);
            --shadow-lg:     0 8px 32px rgba(0,0,0,0.12);
            --transition:    all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        body {
            background: var(--surface-2);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* ── APP SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            left: 0; top: 0;
            transition: var(--transition);
            z-index: 1000;
        }

        .sidebar-header { padding: 1.5rem 1.25rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
        .sidebar-header h2 { margin: 0; font-weight: 800; font-size: 1.1rem; color: var(--accent); }
        .sidebar-header p  { margin: 0.3rem 0 0; color: var(--text-muted); font-size: 0.78rem; }
        .sidebar-menu { list-style: none; padding: 0.75rem; margin: 0; }
        .sidebar-menu li { margin: 2px 0; }
        .sidebar-menu a {
            color: var(--text-secondary); text-decoration: none;
            padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.65rem;
            transition: var(--transition); border-radius: var(--radius-sm);
            font-size: 0.875rem; font-weight: 500;
        }
        .sidebar-menu a:hover { background: var(--accent-light); color: var(--accent); }
        .sidebar-menu a.active { background: var(--accent); color: white; font-weight: 600; }
        .sidebar-menu i { width: 18px; font-size: 0.9rem; flex-shrink: 0; }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.75rem 2rem;
            min-height: 100vh;
        }

        /* ── MAINTENANCE WARNING ── */
        .maintenance-warning {
            background: var(--warning-light);
            border: 1px solid #fde68a;
            color: var(--warning);
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            color: var(--text-primary);
            margin: 0;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h1 i { color: var(--accent); font-size: 1.1rem; }
        .page-header p { color: var(--text-muted); margin: 0.2rem 0 0; font-size: 0.82rem; }

        /* ── STATS GRID ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 1.125rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            text-decoration: none !important;
            color: inherit;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--accent);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
            color: inherit;
        }

        .stat-card.active { border-color: var(--accent); }

        .stat-icon {
            width: 42px; height: 42px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 0.875rem;
            font-size: 1.05rem;
        }

        .stat-card h3 {
            font-size: 1.9rem;
            font-weight: 800;
            margin: 0 0 0.2rem;
            color: var(--text-primary);
            letter-spacing: -1px;
            font-variant-numeric: tabular-nums;
        }

        .stat-card p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ── ALERTS ── */
        .alert {
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            padding: 0.875rem 1.125rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
        }

        .alert-success { background: var(--success-light); color: var(--success); border-color: #bbf7d0; }
        .alert-danger  { background: var(--danger-light);  color: var(--danger);  border-color: #fecaca; }
        .alert-warning { background: var(--warning-light); color: var(--warning); border-color: #fde68a; }

        /* ── VIEW TABS ── */
        .view-tabs {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1.5rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.3rem;
            width: fit-content;
            box-shadow: var(--shadow-xs);
        }

        .view-tab {
            padding: 0.55rem 1.25rem;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-tab:hover { color: var(--accent); text-decoration: none; }
        .view-tab.active { background: var(--accent); color: white; }

        .view-tab .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.25);
            color: inherit;
            border-radius: 100px;
            min-width: 18px; height: 18px;
            padding: 0 5px;
            font-size: 0.68rem;
            font-weight: 800;
        }

        .view-tab:not(.active) .tab-count { background: var(--border); color: var(--text-muted); }
        .view-tab:not(.active).has-alert .tab-count { background: var(--danger); color: white; }

        /* ── FILTERS CARD ── */
        .filters-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-label { font-weight: 600; color: var(--text-primary); margin-bottom: 0.35rem; display: block; font-size: 0.8rem; }

        .form-control, .form-select {
            padding: 0.575rem 0.875rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 0.875rem;
            color: var(--text-primary);
            background: var(--surface);
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.08);
            outline: none;
        }

        /* ── TABLE CARD ── */
        .table-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0.875rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .table-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.975rem;
        }

        .bulk-actions { display: flex; gap: 0.5rem; align-items: center; }

        /* ── BOOKING CARDS GRID ── */
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .booking-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .booking-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
        }

        /* Card banner / avatar area */
        .booking-card-banner {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, var(--accent), #1e40af);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }

        .booking-card-banner img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .booking-card-banner .avatar-initials {
            font-size: 3rem;
            font-weight: 800;
            color: rgba(255,255,255,0.9);
            letter-spacing: -2px;
            font-family: inherit;
        }

        .booking-card-badge {
            position: absolute;
            top: 10px; right: 10px;
        }

        /* Card body */
        .booking-card-body {
            padding: 1.125rem 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .booking-card-name {
            font-weight: 800;
            font-size: 0.975rem;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
            letter-spacing: -0.2px;
        }

        .booking-card-service {
            font-size: 0.78rem;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .booking-card-meta {
            margin-top: auto;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .booking-meta-row {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .booking-meta-row i {
            width: 14px;
            color: var(--accent);
            font-size: 0.72rem;
        }

        /* ── STATUS BADGES ── */
        .badge {
            padding: 0.28rem 0.65rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .badge.pending,   .badge-pending   { background: var(--warning-light); color: var(--warning); border: 1px solid #fde68a; }
        .badge.confirmed, .badge-confirmed { background: var(--info-light);    color: var(--info);    border: 1px solid #a5f3fc; }
        .badge.completed, .badge-completed { background: var(--success-light); color: var(--success); border: 1px solid #bbf7d0; }
        .badge.cancelled, .badge-cancelled { background: var(--danger-light);  color: var(--danger);  border: 1px solid #fecaca; }
        .badge.accepted   { background: var(--success-light); color: var(--success); border: 1px solid #bbf7d0; }
        .badge.rejected   { background: var(--danger-light);  color: var(--danger);  border: 1px solid #fecaca; }
        .badge.expired    { background: var(--surface-2);     color: var(--text-muted); border: 1px solid var(--border); }

        /* ── OFFER CARDS ── */
        .offer-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            border-left: 3px solid var(--warning);
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
        }

        .offer-card:hover { box-shadow: var(--shadow-sm); }

        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .offer-client { display: flex; align-items: center; gap: 0.875rem; }

        .client-avatar {
            width: 42px; height: 42px;
            border-radius: var(--radius-sm);
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; overflow: hidden;
        }

        .client-avatar img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; }

        .offer-price {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--accent);
            font-variant-numeric: tabular-nums;
        }

        .offer-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; }

        .btn-offer-action {
            padding: 0.4rem 0.875rem;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 700;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: var(--transition);
        }

        .btn-accept-offer  { background: var(--success-light); color: var(--success); }
        .btn-accept-offer:hover  { background: var(--success); color: white; }
        .btn-reject-offer  { background: var(--danger-light);  color: var(--danger); }
        .btn-reject-offer:hover  { background: var(--danger);  color: white; }
        .btn-counter-offer { background: var(--info-light); color: var(--info); }
        .btn-counter-offer:hover { background: var(--info); color: white; }
        .btn-cancel-action { background: var(--surface-2); color: var(--text-secondary); }
        .btn-cancel-action:hover { background: var(--border); color: var(--text-primary); }

        /* Counter offer form */
        .counter-form {
            display: none;
            margin-top: 1rem;
            padding: 1.125rem;
            background: var(--surface-2);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            animation: fadeSlideIn 0.2s ease;
        }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── ACTION BUTTONS ── */
        .btn-sm {
            padding: 0.38rem 0.75rem;
            font-size: 0.75rem;
            border-radius: var(--radius-sm);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: var(--transition);
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-accept  { background: var(--success-light); color: var(--success); }
        .btn-accept:hover  { background: var(--success); color: white; }
        .btn-reject  { background: var(--danger-light);  color: var(--danger); }
        .btn-reject:hover  { background: var(--danger);  color: white; }
        .btn-complete { background: var(--info-light); color: var(--info); }
        .btn-complete:hover { background: var(--info); color: white; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 3.5rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; display: block; color: var(--border); }
        .empty-state h3 { color: var(--text-secondary); margin-bottom: 0.4rem; font-size: 1rem; font-weight: 700; }
        .empty-state p { font-size: 0.82rem; margin-bottom: 1.25rem; }

        /* ── PAGINATION ── */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.35rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .page-btn {
            padding: 0.45rem 0.875rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.4rem;
        }

        .page-btn:hover { background: var(--accent-light); color: var(--accent); border-color: var(--accent); text-decoration: none; }
        .page-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

        /* ── MOBILE ── */
        .mobile-menu-toggle {
            display: none;
            position: fixed; top: 1rem; left: 1rem;
            z-index: 1100;
            background: var(--accent); color: white; border: none;
            border-radius: var(--radius-sm); width: 42px; height: 42px;
            align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-md);
        }

        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; backdrop-filter: blur(2px); }
        .overlay.active { display: block; }

        /* ── BOOKING DETAIL MODAL ── */
        .booking-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(4px);
            animation: modalFadeIn 0.2s ease;
        }

        @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .booking-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .booking-modal-content {
            background: var(--surface);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 640px;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0,0,0,0.25);
            animation: modalSlideUp 0.25s cubic-bezier(0.4,0,0.2,1);
        }

        @keyframes modalSlideUp {
            from { transform: translateY(24px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        .booking-modal-header {
            position: relative;
            height: 180px;
            background: linear-gradient(135deg, var(--accent), #1e40af);
            display: flex;
            align-items: flex-end;
            padding: 1.5rem 1.5rem 1.25rem;
            color: white;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }

        .booking-modal-header .close-btn {
            position: absolute;
            top: 1rem; right: 1rem;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            width: 36px; height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }

        .booking-modal-header .close-btn:hover { background: rgba(255,255,255,0.28); }

        .modal-client-avatar {
            width: 72px; height: 72px;
            border-radius: var(--radius-md);
            border: 3px solid rgba(255,255,255,0.6);
            background: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--accent);
            margin-right: 1.125rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .modal-client-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: calc(var(--radius-md) - 3px); }

        .modal-client-info h3 { margin: 0 0 0.35rem; font-size: 1.2rem; font-weight: 800; letter-spacing: -0.3px; }
        .modal-client-info p { margin: 0.2rem 0; opacity: 0.85; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem; }

        .booking-modal-body { padding: 1.5rem; }

        .modal-section { margin-bottom: 1.5rem; }
        .modal-section:last-child { margin-bottom: 0; }

        .modal-section-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .modal-section-title i { color: var(--accent); }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.55rem 0;
            border-bottom: 1px solid var(--border-subtle);
        }

        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-size: 0.8rem; font-weight: 500; flex-shrink: 0; }
        .info-value { color: var(--text-primary); font-weight: 600; font-size: 0.875rem; text-align: right; flex: 1; margin-left: 1rem; }

        .contact-method {
            display: flex;
            align-items: center;
            padding: 0.75rem 0.875rem;
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .contact-method:hover { background: var(--accent-light); color: var(--accent); border-color: var(--accent); text-decoration: none; }
        .contact-method:last-child { margin-bottom: 0; }

        .contact-method i {
            width: 32px; height: 32px;
            background: var(--accent);
            color: white;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            margin-right: 0.875rem;
            flex-shrink: 0;
            font-size: 0.875rem;
        }

        .contact-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        .contact-value { font-weight: 700; font-size: 0.875rem; color: var(--text-primary); }

        .modal-action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
        }

        .modal-action-buttons .btn {
            padding: 0.7rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            font-family: inherit;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            width: 100%;
            margin-bottom: 0;
        }

        .modal-action-buttons .btn-accept { background: var(--success); color: white; }
        .modal-action-buttons .btn-accept:hover { background: #15803d; transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .modal-action-buttons .btn-reject { background: var(--danger); color: white; }
        .modal-action-buttons .btn-reject:hover { background: #b91c1c; transform: translateY(-1px); box-shadow: var(--shadow-sm); }

        /* ── FORM CHECK ── */
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.12); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .filters-grid { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; }
            .bulk-actions { width: 100%; }
            .bookings-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .booking-modal-content { border-radius: var(--radius-lg); }
            .booking-modal-header { flex-direction: column; align-items: center; text-align: center; padding: 1.25rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0; height: auto; min-height: 160px; }
            .modal-client-avatar { margin-right: 0; margin-bottom: 0.875rem; }
            .modal-action-buttons { grid-template-columns: 1fr; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
        }

        /* ── STAT ICON COLORS ── */
        .si-total     { background: #eff4ff; color: #4f46e5; }
        .si-pending   { background: var(--warning-light); color: var(--warning); }
        .si-confirmed { background: var(--info-light); color: var(--info); }
        .si-completed { background: var(--success-light); color: var(--success); }
        .si-cancelled { background: var(--danger-light); color: var(--danger); }

        /* ── BOOKING CARD UTILITY ── */
        .booking-card-banner-wrap { position: relative; flex-grow: 1; }

        .booking-card-footer {
            margin-top: 0.875rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-card-amount {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--accent);
            font-variant-numeric: tabular-nums;
        }

        .booking-card-chevron {
            font-size: 0.875rem;
            color: var(--text-muted);
            transition: transform 0.2s ease;
        }

        .booking-card:hover .booking-card-chevron { transform: translateX(3px); color: var(--accent); }

        /* ── OFFER DETAILS ── */
        .offer-details { margin-bottom: 1rem; }
        .offer-details p { font-size: 0.875rem; margin-bottom: 0.4rem; color: var(--text-secondary); }
        .offer-details p:last-child { margin-bottom: 0; }
        .offer-details strong { color: var(--text-primary); font-weight: 700; }

        /* ── INFO VALUE BLOCK ── */
        .info-value-block { text-align: left; display: block; margin-top: 0.35rem; }
    </style>
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
        <!-- Maintenance Warning -->
        <?php if (isset($maintenance_warning)): ?>
            <div class="alert maintenance-warning">
                <i class="fas fa-tools me-2"></i>
                <strong>Maintenance Mode Active</strong>
                <p class="mb-0 mt-2">The platform is currently under maintenance. Some features may be limited.</p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> <?php echo $view === 'offers' ? __('bookings.offers.title', [], 'dashboard') : __('bookings.title', [], 'dashboard'); ?></h1>
            <p><?php echo $view === 'offers' ? __('bookings.offers.subtitle', [], 'dashboard') : __('bookings.subtitle', [], 'dashboard'); ?></p>
        </div>

        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=bookings" class="view-tab <?php echo $view === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> <?php echo __('bookings.tab_bookings', [], 'dashboard'); ?>
                <span class="badge bg-secondary"><?php echo isset($total_bookings) ? $total_bookings : 0; ?></span>
            </a>
            <a href="?view=offers" class="view-tab <?php echo $view === 'offers' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i> <?php echo __('bookings.tab_offers', [], 'dashboard'); ?>
                <?php 
                // Get count of pending offers
                $stmt = $db->prepare("SELECT COUNT(*) FROM service_offers WHERE provider_id = ? AND status = 'pending'");
                $stmt->execute([$_SESSION['user_id']]);
                $pending_offers = (int) $stmt->fetchColumn();
                if ($pending_offers > 0):
                ?>
                    <span class="badge bg-danger"><?php echo $pending_offers; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($view === 'bookings'): ?>
        <!-- Statistics (Only show for Bookings view) -->
        <?php 
        // Get booking statistics
        $stats_sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM bookings
            WHERE provider_id = ?
        ";
        $stats_stmt = $db->prepare($stats_sql);
        $stats_stmt->execute([$provider['id']]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_bookings = isset($stats['total']) ? $stats['total'] : 0;
        $pending_bookings = isset($stats['pending']) ? $stats['pending'] : 0;
        $confirmed_bookings = isset($stats['confirmed']) ? $stats['confirmed'] : 0;
        $completed_bookings = isset($stats['completed']) ? $stats['completed'] : 0;
        $cancelled_bookings = isset($stats['cancelled']) ? $stats['cancelled'] : 0;
        ?>
        <div class="stats-grid">
            <a href="?view=bookings&status=" class="stat-card <?php echo empty($status_filter) ? 'active' : ''; ?>">
                <div class="stat-icon si-total">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3><?php echo $total_bookings; ?></h3>
                <p><?php echo __('bookings.stat_total', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=pending" class="stat-card <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                <div class="stat-icon si-pending">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $pending_bookings; ?></h3>
                <p><?php echo __('bookings.stat_pending', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=confirmed" class="stat-card <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                <div class="stat-icon si-confirmed">
                    <i class="fas fa-check"></i>
                </div>
                <h3><?php echo $confirmed_bookings; ?></h3>
                <p><?php echo __('bookings.stat_confirmed', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=completed" class="stat-card <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                <div class="stat-icon si-completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $completed_bookings; ?></h3>
                <p><?php echo __('bookings.stat_completed', [], 'dashboard'); ?></p>
            </a>

            <a href="?view=bookings&status=cancelled" class="stat-card <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                <div class="stat-icon si-cancelled">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3><?php echo $cancelled_bookings; ?></h3>
                <p><?php echo __('bookings.stat_cancelled', [], 'dashboard'); ?></p>
            </a>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <?php if ($view === 'bookings'): ?>
        <div class="filters-card">
            <form method="GET" action="bookings.php">
                <input type="hidden" name="view" value="bookings">
                <div class="filters-grid">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_search', [], 'dashboard'); ?></label>
                        <input type="text" name="search" class="form-control" placeholder="<?php echo __('bookings.search_placeholder', [], 'dashboard'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_status', [], 'dashboard'); ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?php echo __('bookings.all_status', [], 'dashboard'); ?></option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('bookings.status_pending', [], 'dashboard'); ?></option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>><?php echo __('bookings.status_confirmed', [], 'dashboard'); ?></option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>><?php echo __('bookings.status_completed', [], 'dashboard'); ?></option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo __('bookings.status_cancelled', [], 'dashboard'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_date', [], 'dashboard'); ?></label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> <?php echo __('bookings.apply_filters', [], 'dashboard'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="filters-card">
            <form method="GET" action="bookings.php">
                <input type="hidden" name="view" value="offers">
                <div class="filters-grid">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_search', [], 'dashboard'); ?></label>
                        <input type="text" name="search" class="form-control" placeholder="<?php echo __('bookings.search_placeholder', [], 'dashboard'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo __('bookings.filter_status', [], 'dashboard'); ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?php echo __('bookings.all_status', [], 'dashboard'); ?></option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('bookings.status_pending', [], 'dashboard'); ?></option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>><?php echo __('bookings.status_accepted', [], 'dashboard'); ?></option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>><?php echo __('bookings.status_rejected', [], 'dashboard'); ?></option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>><?php echo __('bookings.status_expired', [], 'dashboard'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> <?php echo __('bookings.apply_filters', [], 'dashboard'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Bookings/Offers Display -->
        <?php if ($view === 'bookings'): ?>
        <!-- Bookings Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo __('bookings.table_title', [], 'dashboard') . ' (' . $total_bookings . ')'; ?></h3>
                <?php if (!empty($items)): ?>
                    <form method="POST" class="bulk-actions">
                        <select name="bulk_action_type" class="form-select" style="width:auto;">
                            <option value=""><?php echo __('bookings.bulk_actions', [], 'dashboard'); ?></option>
                            <option value="confirmed"><?php echo __('bookings.accept_selected', [], 'dashboard'); ?></option>
                            <?php if (isProviderRejectionAllowed()): ?>
                                <option value="cancelled"><?php echo __('bookings.reject_selected', [], 'dashboard'); ?></option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" name="bulk_action" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()"><?php echo __('bookings.apply', [], 'dashboard'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar"></i>
                    <h3><?php echo __('bookings.no_bookings', [], 'dashboard'); ?></h3>
                    <p>
                        <?php if (!empty($status_filter) || !empty($search) || !empty($date_filter)): ?>
                            <?php echo __('bookings.adjust_filters', [], 'dashboard'); ?>
                        <?php else: ?>
                            <?php echo __('bookings.no_bookings_yet', [], 'dashboard'); ?>
                        <?php endif; ?>
                    </p>
                    <?php if (empty($status_filter) && empty($search) && empty($date_filter)): ?>
                        <a href="services.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i> <?php echo __('bookings.add_services', [], 'dashboard'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Bookings Grid View -->
                <div class="bookings-grid">
                    <?php foreach ($items as $booking): ?>
                        <div class="booking-card" onclick="openBookingModal(this)" 
                             data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>"
                             data-client-name="<?php echo htmlspecialchars($booking['client_name']); ?>"
                             data-client-email="<?php echo htmlspecialchars($booking['client_email']); ?>"
                             data-client-phone="<?php echo htmlspecialchars($booking['client_phone']); ?>"
                             data-client-image="<?php echo htmlspecialchars($booking['client_image']); ?>"
                             data-service-name="<?php echo htmlspecialchars($booking['service_name'] ?? ''); ?>"
                             data-service-desc="<?php echo htmlspecialchars($booking['service_description'] ?? ''); ?>"
                             data-preferred-date="<?php echo htmlspecialchars($booking['preferred_date']); ?>"
                             data-preferred-time="<?php echo htmlspecialchars($booking['preferred_time'] ?? ''); ?>"
                             data-location="<?php echo htmlspecialchars($booking['location']); ?>"
                             data-amount="<?php echo htmlspecialchars($booking['amount']); ?>"
                             data-status="<?php echo htmlspecialchars($booking['status']); ?>"
                             data-created-at="<?php echo htmlspecialchars($booking['created_at']); ?>"
                             data-urgency="<?php echo htmlspecialchars($booking['urgency_level'] ?? ''); ?>"
                             data-can-respond="<?php echo intval($booking['status'] === 'pending' ? 1 : 0); ?>">
                            <div class="booking-card-banner-wrap">
                                <div class="client-avatar-large">
                                    <?php if (!empty($booking['client_image'])): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($booking['client_image']); ?>" alt="<?php echo htmlspecialchars($booking['client_name']); ?>">
                                    <?php else: ?>
                                        <span class="avatar-initials"><?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge badge-<?php echo htmlspecialchars($booking['status']); ?> booking-card-badge">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                            <div class="booking-card-body">
                                <div class="booking-card-name">
                                    <?php echo htmlspecialchars($booking['client_name']); ?>
                                </div>
                                <div class="booking-card-service">
                                    <i class="fas fa-concierge-bell"></i>
                                    <?php echo htmlspecialchars($booking['service_name'] ?? 'Service'); ?>
                                </div>
                                <div class="booking-card-meta">
                                    <div class="booking-meta-row"><i class="fas fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?></div>
                                    <div class="booking-meta-row"><i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars(strlen($booking['location']) > 25 ? substr($booking['location'], 0, 25) . '...' : $booking['location']); ?></div>
                                    <div class="booking-meta-row"><i class="fas fa-clock"></i>
                                        <?php echo __('bookings.requested', [], 'dashboard'); ?>: <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></div>
                                </div>
                                <div class="booking-card-footer">
                                    <div>
                                        <?php if (!empty($booking['amount'])): ?>
                                            <span class="booking-card-amount">RWF <?php echo number_format($booking['amount'], 0); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-card-chevron"><i class="fas fa-chevron-right"></i></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: none;">
                    <!-- Hidden table for pagination and compatibility -->
                    <form method="POST" id="bulkForm">
                        <div class="table-responsive">
                            <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleSelectAll()">
                                    </th>
                                    <th><?php echo __('bookings.client_name', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.service', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.date_time', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.status', [], 'dashboard'); ?></th>
                                    <th><?php echo __('bookings.actions', [], 'dashboard'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $booking): ?>
                                    <tr>
                                        <td>
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <input type="checkbox" name="booking_ids[]" value="<?php echo $booking['id']; ?>" class="form-check-input booking-checkbox">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="client-info">
                                                <div class="client-avatar">
                                                    <?php if (!empty($booking['client_image'])): ?>
                                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($booking['client_image']); ?>" alt="<?php echo htmlspecialchars($booking['client_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>';">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($booking['client_name'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($booking['client_name']); ?></strong>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['client_phone']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($booking['client_email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="booking-details">
                                                <?php if (!empty($booking['service_name'])): ?>
                                                    <div class="service-name"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                                                <?php endif; ?>
                                                <div class="service-description">
                                                    <?php echo nl2br(htmlspecialchars($booking['service_description'])); ?>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    <i class="fas fa-clock"></i> <?php echo __('bookings.requested', [], 'dashboard'); ?>: <?php echo date('M d, Y h:i A', strtotime($booking['created_at'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?></strong>
                                            <?php if (!empty($booking['preferred_time'])): ?>
                                                <div class="text-muted small">
                                                    <?php echo date('h:i A', strtotime($booking['preferred_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" name="update_status" class="btn-sm btn-accept" title="<?php echo __('bookings.confirm', [], 'dashboard'); ?>">
                                                            <i class="fas fa-check"></i> <?php echo __('bookings.confirm', [], 'dashboard'); ?>
                                                        </button>
                                                    </form>
                                                    <?php if (isProviderRejectionAllowed()): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo __('bookings.confirm_reject', [], 'dashboard'); ?>')">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" name="update_status" class="btn-sm btn-reject" title="<?php echo __('bookings.reject', [], 'dashboard'); ?>">
                                                                <i class="fas fa-times"></i> <?php echo __('bookings.reject', [], 'dashboard'); ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" name="update_status" class="btn-sm btn-complete" title="<?php echo __('bookings.complete', [], 'dashboard'); ?>">
                                                            <i class="fas fa-check-circle"></i> <?php echo __('bookings.complete', [], 'dashboard'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?view=bookings&page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'date' => $date_filter, 'search' => $search])); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> <?php echo __('bookings.previous', [], 'dashboard'); ?>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?view=bookings&page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'date' => $date_filter, 'search' => $search])); ?>" 
                               class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?view=bookings&page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'date' => $date_filter, 'search' => $search])); ?>" class="page-btn">
                                <?php echo __('bookings.next', [], 'dashboard'); ?> <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Offers Display -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo __('bookings.offers.title', [], 'dashboard') . ' (' . count($items) . ')'; ?></h3>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-handshake"></i>
                    <h3><?php echo __('bookings.no_offers', [], 'dashboard'); ?></h3>
                    <p>
                        <?php if (!empty($status_filter) || !empty($search)): ?>
                            <?php echo __('bookings.adjust_filters', [], 'dashboard'); ?>
                        <?php else: ?>
                            <?php echo __('bookings.no_offers_yet', [], 'dashboard'); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="offers-list">
                    <?php foreach ($items as $offer): 
                        // Check if offer is expired
                        $is_expired = strtotime($offer['expires_at']) < time();
                        if ($is_expired && $offer['status'] === 'pending') {
                            $offer['status'] = 'expired';
                        }
                    ?>
                        <div class="offer-card">
                            <div class="offer-header">
                                <div class="offer-client">
                                    <div class="client-avatar">
                                        <?php if (!empty($offer['client_image'])): ?>
                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($offer['client_image']); ?>" alt="<?php echo htmlspecialchars($offer['client_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo strtoupper(substr($offer['client_name'], 0, 1)); ?>';">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($offer['client_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($offer['client_name']); ?></strong>
                                        <div class="text-muted small">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($offer['client_email']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($offer['client_phone']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge <?php echo $offer['status']; ?>">
                                        <?php echo ucfirst($offer['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="offer-details">
                                <p class="mb-2"><strong><?php echo __('bookings.service', [], 'dashboard'); ?>:</strong> <?php echo htmlspecialchars($offer['service_name']); ?></p>
                                <p class="mb-2"><strong><?php echo __('bookings.offer_price', [], 'dashboard'); ?>:</strong> <span class="offer-price">RWF <?php echo number_format($offer['offered_price'], 2); ?></span></p>
                                <p class="mb-2"><strong><?php echo __('bookings.negotiation_round', [], 'dashboard'); ?>:</strong> <?php echo $offer['round_number']; ?></p>
                                <p class="mb-2"><strong><?php echo __('bookings.submitted', [], 'dashboard'); ?>:</strong> <?php echo date('M d, Y h:i A', strtotime($offer['created_at'])); ?></p>
                                <?php if ($offer['status'] !== 'pending' && !empty($offer['responded_at'])): ?>
                                    <p class="mb-2"><strong><?php echo __('bookings.response_date', [], 'dashboard'); ?>:</strong> <?php echo date('M d, Y h:i A', strtotime($offer['responded_at'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($offer['response_notes'])): ?>
                                    <p class="mb-0"><strong><?php echo __('bookings.your_response', [], 'dashboard'); ?>:</strong> <?php echo nl2br(htmlspecialchars($offer['response_notes'])); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if ($offer['status'] === 'pending'): ?>
                            <div class="offer-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                    <input type="hidden" name="offer_action" value="accept">
                                    <button type="submit" class="btn-offer-action btn-accept-offer" onclick="return confirm('<?php echo __('bookings.confirm_accept_offer', [], 'dashboard'); ?>')">
                                        <i class="fas fa-check"></i> <?php echo __('bookings.accept_offer', [], 'dashboard'); ?>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                    <input type="hidden" name="offer_action" value="reject">
                                    <button type="submit" class="btn-offer-action btn-reject-offer" onclick="return confirm('<?php echo __('bookings.confirm_reject_offer', [], 'dashboard'); ?>')">
                                        <i class="fas fa-times"></i> <?php echo __('bookings.reject_offer', [], 'dashboard'); ?>
                                    </button>
                                </form>
                                <button type="button" class="btn-offer-action btn-counter-offer" onclick="toggleCounterForm(<?php echo $offer['id']; ?>)">
                                    <i class="fas fa-hand-paper"></i> <?php echo __('bookings.send_counter_offer', [], 'dashboard'); ?>
                                </button>
                            </div>

                            <!-- Counter-Offer Form (Hidden by default) -->
                            <form method="POST" id="counter-form-<?php echo $offer['id']; ?>" class="counter-form">
                                <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                <input type="hidden" name="offer_action" value="counter">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?php echo __('bookings.counter_price', [], 'dashboard'); ?> (RWF)</label>
                                    <input type="number" name="counter_price" class="form-control" step="0.01" min="0" required placeholder="<?php echo __('bookings.enter_counter_price', [], 'dashboard'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?php echo __('bookings.notes_optional', [], 'dashboard'); ?></label>
                                    <textarea name="counter_notes" class="form-control" rows="3" placeholder="<?php echo __('bookings.add_notes', [], 'dashboard'); ?>"></textarea>
                                </div>
                                <div class="offer-actions">
                                    <button type="submit" class="btn-offer-action btn-counter-offer">
                                        <i class="fas fa-paper-plane"></i> <?php echo __('bookings.send_counter_offer', [], 'dashboard'); ?>
                                    </button>
                                    <button type="button" class="btn-offer-action btn-cancel-action" onclick="toggleCounterForm(<?php echo $offer['id']; ?>)">
                                        <i class="fas fa-times"></i> <?php echo __('bookings.cancel', [], 'dashboard'); ?>
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination for Offers -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?view=offers&page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> <?php echo __('bookings.previous', [], 'dashboard'); ?>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?view=offers&page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>" 
                               class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?view=offers&page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>" class="page-btn">
                                <?php echo __('bookings.next', [], 'dashboard'); ?> <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── Mobile sidebar toggle ──────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            const mobileToggle = document.getElementById('mobileToggle');
            const sidebar      = document.getElementById('providerSidebar');
            const overlay      = document.getElementById('overlay');

            if (mobileToggle && sidebar) {
                mobileToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('mobile-open');
                    overlay.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
                });
            }

            if (overlay) {
                overlay.addEventListener('click', () => {
                    if (sidebar) sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // Select-all checkbox behaviour
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.booking-checkbox');

            if (selectAll) {
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        selectAll.checked = [...checkboxes].every(c => c.checked);
                        selectAll.indeterminate = !selectAll.checked && [...checkboxes].some(c => c.checked);
                    });
                });
            }

            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(el => {
                    try { new bootstrap.Alert(el).close(); } catch (e) {}
                });
            }, 5000);

            // Close modal when clicking backdrop
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.addEventListener('click', e => { if (e.target === modal) closeBookingModal(); });
            }
        });

        // ── Toast notification ─────────────────────────────────────────────
        function showToast(message, type = 'success') {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.style.cssText = 'position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;';
                document.body.appendChild(container);
            }
            const colors = { success: '#16a34a', danger: '#dc2626', warning: '#d97706', info: '#0891b2' };
            const toast = document.createElement('div');
            toast.style.cssText = `background:${colors[type]||colors.info};color:#fff;padding:0.75rem 1.125rem;border-radius:10px;font-size:0.875rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,0.15);min-width:260px;display:flex;align-items:center;gap:0.5rem;animation:toastIn 0.22s ease;font-family:inherit;`;
            toast.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':type==='danger'?'exclamation-circle':'info-circle'}"></i> ${escapeHtml(message)}`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 3500);
        }

        // ── Bulk action ────────────────────────────────────────────────────
        function toggleSelectAll() {
            const selectAll  = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.booking-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        function confirmBulkAction() {
            const selected = document.querySelectorAll('.booking-checkbox:checked');
            if (selected.length === 0) {
                showToast('Please select at least one booking.', 'warning');
                return false;
            }
            return confirm(`Update ${selected.length} booking(s)?`);
        }

        function confirmReject() {
            return confirm('Are you sure you want to reject this booking?');
        }

        // ── Toggle counter-offer form ──────────────────────────────────────
        function toggleCounterForm(offerId) {
            const form = document.getElementById('counter-form-' + offerId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                form.style.display = 'none';
            }
        }

        // Booking Modal Functions
        function openBookingModal(element) {
            const modal = document.getElementById('bookingModal');
            
            // Extract data from data attributes
            const bookingData = {
                id: element.getAttribute('data-booking-id'),
                client_name: element.getAttribute('data-client-name'),
                client_email: element.getAttribute('data-client-email'),
                client_phone: element.getAttribute('data-client-phone'),
                client_image: element.getAttribute('data-client-image'),
                service_name: element.getAttribute('data-service-name'),
                service_description: element.getAttribute('data-service-desc'),
                preferred_date: element.getAttribute('data-preferred-date'),
                preferred_time: element.getAttribute('data-preferred-time'),
                location: element.getAttribute('data-location'),
                amount: element.getAttribute('data-amount'),
                status: element.getAttribute('data-status'),
                created_at: element.getAttribute('data-created-at'),
                urgency_level: element.getAttribute('data-urgency')
            };
            
            const canRespond = element.getAttribute('data-can-respond') === '1';

            // Populate modal with booking data
            document.getElementById('modalClientName').textContent = bookingData.client_name;
            document.getElementById('modalClientEmail').textContent = bookingData.client_email;
            document.getElementById('modalClientEmailContact').textContent = bookingData.client_email;
            document.getElementById('modalClientPhone').textContent = bookingData.client_phone;
            document.getElementById('modalClientPhoneContact').textContent = bookingData.client_phone;
            
            // Set contact links
            document.getElementById('modalContactEmail').href = 'mailto:' + bookingData.client_email;
            document.getElementById('modalContactPhone').href = 'tel:' + bookingData.client_phone;
            
            // Set client avatar
            const clientAvatar = document.getElementById('modalClientAvatar');
            if (bookingData.client_image) {
                clientAvatar.innerHTML = '<img src="../uploads/profiles/' + escapeHtml(bookingData.client_image) + '" alt="' + escapeHtml(bookingData.client_name) + '">';
            } else {
                clientAvatar.innerHTML = bookingData.client_name.charAt(0).toUpperCase();
            }

            // Populate booking details
            document.getElementById('modalServiceName').textContent = bookingData.service_name || '—';
            document.getElementById('modalServiceDesc').textContent = bookingData.service_description || '—';
            
            try {
                const dateObj = new Date(bookingData.preferred_date + 'T00:00:00');
                document.getElementById('modalPreferredDate').textContent = dateObj.toLocaleDateString('en-RW', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            } catch (e) {
                document.getElementById('modalPreferredDate').textContent = bookingData.preferred_date;
            }
            
            if (bookingData.preferred_time) {
                try {
                    const timeObj = new Date('2000-01-01T' + bookingData.preferred_time);
                    document.getElementById('modalPreferredTime').textContent = timeObj.toLocaleTimeString('en-RW', { hour: '2-digit', minute: '2-digit' });
                } catch (e) {
                    document.getElementById('modalPreferredTime').textContent = bookingData.preferred_time;
                }
            } else {
                document.getElementById('modalPreferredTime').textContent = '—';
            }
            
            document.getElementById('modalLocation').textContent = bookingData.location || '—';
            document.getElementById('modalAmount').textContent = bookingData.amount && parseFloat(bookingData.amount) > 0 ? 'RWF ' + parseInt(bookingData.amount).toLocaleString() : '—';
            document.getElementById('modalUrgency').textContent = bookingData.urgency_level || '—';

            // Set status badge color
            const statusBadge = document.getElementById('modalStatusBadge');
            statusBadge.className = 'badge badge-' + bookingData.status;
            statusBadge.textContent = bookingData.status.charAt(0).toUpperCase() + bookingData.status.slice(1);

            // Show/hide action buttons based on booking status and permissions
            const actionButtons = document.querySelector('.modal-action-buttons');
            if (canRespond && bookingData.status === 'pending') {
                actionButtons.style.display = 'grid';
                
                // Set up form submissions
                const confirmForm = document.getElementById('confirmBookingForm');
                const rejectForm = document.getElementById('rejectBookingForm');
                
                const bookingIdInput1 = confirmForm.querySelector('input[name="booking_id"]');
                const bookingIdInput2 = rejectForm.querySelector('input[name="booking_id"]');
                
                if (bookingIdInput1) bookingIdInput1.value = bookingData.id;
                if (bookingIdInput2) bookingIdInput2.value = bookingData.id;
            } else if (bookingData.status === 'confirmed') {
                actionButtons.innerHTML = `
                    <form method="POST" style="grid-column:1/-1;">
                        <input type="hidden" name="booking_id" value="${bookingData.id}">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" name="update_status" class="btn btn-accept">
                            <i class="fas fa-check-circle"></i> Mark as Completed
                        </button>
                    </form>
                `;
                actionButtons.style.display = 'grid';
            } else {
                actionButtons.style.display = 'none';
            }

            // Show modal
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

        // Close modal when clicking outside of it
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('bookingModal');
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeBookingModal();
                }
            });
        });
    </script>

    <!-- Booking Detail Modal -->
    <div class="booking-modal" id="bookingModal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <button class="close-btn" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-client-avatar" id="modalClientAvatar"></div>
                <div class="modal-client-info">
                    <h3 id="modalClientName">Client Name</h3>
                    <p><i class="fas fa-envelope"></i><span id="modalClientEmail">email@example.com</span></p>
                    <p><i class="fas fa-phone"></i><span id="modalClientPhone">+250700000000</span></p>
                </div>
            </div>

            <div class="booking-modal-body">
                <!-- Booking Details Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-briefcase"></i> Service Details
                    </div>
                    <div class="info-row">
                        <span class="info-label">Service Name</span>
                        <span class="info-value" id="modalServiceName">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Description</span>
                        <span class="info-value info-value-block" id="modalServiceDesc">—</span>
                    </div>
                </div>

                <!-- Booking Schedule Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-calendar-alt"></i> Schedule
                    </div>
                    <div class="info-row">
                        <span class="info-label">Preferred Date</span>
                        <span class="info-value" id="modalPreferredDate">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Preferred Time</span>
                        <span class="info-value" id="modalPreferredTime">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Service Location</span>
                        <span class="info-value" id="modalLocation">—</span>
                    </div>
                </div>

                <!-- Booking Info Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-info-circle"></i> Booking Information
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            <span class="badge" id="modalStatusBadge"></span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Amount</span>
                        <span class="info-value" id="modalAmount">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Urgency Level</span>
                        <span class="info-value" id="modalUrgency">—</span>
                    </div>
                </div>

                <!-- Contact Methods Section -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-phone-alt"></i> Contact Methods
                    </div>
                    <a class="contact-method" href="mailto:" id="modalContactEmail">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <div class="contact-label">Email</div><div class="contact-value" id="modalClientEmailContact">email@example.com</div>
                        </div>
                    </a>
                    <a class="contact-method" href="tel:" id="modalContactPhone">
                        <i class="fas fa-phone"></i>
                        <div>
                            <div class="contact-label">Phone</div><div class="contact-value" id="modalClientPhoneContact">+250700000000</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="modal-action-buttons">
                <form method="POST" id="confirmBookingForm">
                    <input type="hidden" name="booking_id">
                    <input type="hidden" name="status" value="confirmed">
                    <button type="submit" name="update_status" class="btn btn-accept">
                        <i class="fas fa-check"></i> Confirm Booking
                    </button>
                </form>
                <form method="POST" id="rejectBookingForm" onsubmit="return confirmReject();">
                    <input type="hidden" name="booking_id">
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" name="update_status" class="btn btn-reject">
                        <i class="fas fa-times"></i> Reject Booking
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>