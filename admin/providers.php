<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';
require_once '../includes/provider_requirements.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$verification_filter = $_GET['verification'] ?? '';
$availability_filter = $_GET['availability'] ?? '';

// Build query for providers with filters
$query = "
    SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.is_verified as user_verified,
           u.created_at as user_created, u.updated_at as user_updated,
           sp.is_featured, sp.is_banned, sp.ban_reason, sp.featured_until,
           GROUP_CONCAT(DISTINCT c.name) as categories,
           COUNT(DISTINCT b.id) as total_bookings,
           COUNT(DISTINCT r.id) as total_reviews,
           AVG(r.rating) as average_rating,
           -- Scheduling fields
           sp.working_days, sp.working_hours_start, sp.working_hours_end,
           sp.break_start, sp.break_end, sp.slot_duration, sp.buffer_time, sp.max_daily_bookings
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN provider_services ps ON sp.id = ps.provider_id
    LEFT JOIN categories c ON ps.category_id = c.id
    LEFT JOIN bookings b ON sp.id = b.provider_id
    LEFT JOIN reviews r ON sp.id = r.provider_id
    WHERE u.user_type = 'provider'
";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR sp.profession LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $query .= " AND sp.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND sp.is_active = 0";
    } elseif ($status_filter === 'banned') {
        $query .= " AND sp.is_banned = 1";
    } elseif ($status_filter === 'pending') {
        $query .= " AND u.is_verified = 0";
    }
}

if (!empty($category_filter)) {
    $query .= " AND c.id = ?";
    $params[] = $category_filter;
}

if (!empty($verification_filter)) {
    $query .= " AND sp.verification_level = ?";
    $params[] = $verification_filter;
}

if (!empty($availability_filter)) {
    $query .= " AND sp.availability = ?";
    $params[] = $availability_filter;
}

$query .= " GROUP BY sp.id ORDER BY u.created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🔴 Provider Account Lifecycle Management
    if (isset($_POST['approve_provider'])) {
        $id = intval($_POST['provider_id']);
        try {
            // Update BOTH users.is_active AND service_providers.is_active
            $db->prepare("UPDATE users SET is_verified = 1, is_active = 1 WHERE id = (SELECT user_id FROM service_providers WHERE id = ?)")->execute([$id]);
            $db->prepare("UPDATE service_providers SET is_active = 1 WHERE id = ?")->execute([$id]);
            $success = "Provider approved and activated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to approve provider: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject_provider'])) {
        $id = intval($_POST['provider_id']);
        $reason = sanitize($_POST['rejection_reason'] ?? '');
        try {
            $db->prepare("UPDATE service_providers SET application_status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
            $success = "Provider application rejected";
        } catch (Exception $e) {
            $errors[] = "Failed to reject provider";
        }
    }
    
    if (isset($_POST['toggle_activation'])) {
        $id = intval($_POST['provider_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        try {
            // Update BOTH service_providers AND users table
            $db->prepare("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM service_providers WHERE id = ?)")->execute([$new_status, $id]);
            $db->prepare("UPDATE service_providers SET is_active = ? WHERE id = ?")->execute([$new_status, $id]);
            $action = $new_status ? 'activated' : 'deactivated';
            $success = "Provider {$action} successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update provider status";
        }
    }
    
    if (isset($_POST['ban_provider'])) {
        $id = intval($_POST['provider_id']);
        $reason = sanitize($_POST['ban_reason'] ?? '');

        // Fetch provider user info (email, name) so we can notify them
        $userStmt = $db->prepare("SELECT u.email, u.full_name FROM users u JOIN service_providers sp ON sp.user_id = u.id WHERE sp.id = ?");
        $userStmt->execute([$id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        try {
            $db->prepare("UPDATE service_providers SET is_banned = 1, ban_reason = ?, is_active = 0 WHERE id = ?")->execute([$reason, $id]);
            $success = "Provider banned permanently";

            // Notify provider by email (best-effort — failures logged but do not block)
            if (!empty($user['email'])) {
                $subject = "Account Banned — BII LocalFinder";
                $body = "
                    <p>Hello " . htmlspecialchars($user['full_name'] ?? 'User') . ",</p>
                    <p>Your provider account on <strong>BII LocalFinder</strong> has been banned by the administration.</p>
                    <p><strong>Reason:</strong><br>" . nl2br(htmlspecialchars($reason ?: 'No reason provided')) . "</p>
                    <p>If you believe this is an error or you would like to appeal, please reply to this email or contact support at <a href='mailto:support@biilocalfinder.example'>support@biilocalfinder.example</a>.</p>
                    <p>Regards,<br/>BII LocalFinder Team</p>
                ";

                try {
                    Mailer::sendAnnouncement($user['email'], $user['full_name'] ?? '', $subject, $body);
                    $success .= " — provider notified by email.";
                } catch (\Throwable $e) {
                    error_log("Provider ban notification failed for provider_id {$id}: " . $e->getMessage());
                    // keep $success intact; don't surface mail errors to admin UI
                }
            }

        } catch (Exception $e) {
            $errors[] = "Failed to ban provider";
        }
    }
    
    if (isset($_POST['unban_provider'])) {
        $id = intval($_POST['provider_id']);
        try {
            $db->prepare("UPDATE service_providers SET is_banned = 0, ban_reason = NULL WHERE id = ?")->execute([$id]);
            $success = "Provider unbanned successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to unban provider";
        }
    }
    
    // 🔵 Provider Profile Editing (INCLUDING SCHEDULING)
    if (isset($_POST['update_provider_profile'])) {
        $id = intval($_POST['provider_id']);
        $profession = sanitize($_POST['profession']);
        $bio = sanitize($_POST['bio']);
        $location = sanitize($_POST['location']);
        $district = sanitize($_POST['district']);
        $sector = sanitize($_POST['sector']);
        $experience_years = intval($_POST['experience_years']);
        $hourly_rate = floatval($_POST['hourly_rate']);
        $availability = sanitize($_POST['availability']);
        
        // Scheduling fields
        $working_days = isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : '';
        $working_hours_start = sanitize($_POST['working_hours_start']);
        $working_hours_end = sanitize($_POST['working_hours_end']);
        $break_start = sanitize($_POST['break_start'] ?? '');
        $break_end = sanitize($_POST['break_end'] ?? '');
        $slot_duration = intval($_POST['slot_duration']);
        $buffer_time = intval($_POST['buffer_time']);
        $max_daily_bookings = intval($_POST['max_daily_bookings']);
        
        try {
            $stmt = $db->prepare("
                UPDATE service_providers SET 
                    profession = ?, bio = ?, location = ?, district = ?, sector = ?, 
                    experience_years = ?, hourly_rate = ?, availability = ?,
                    working_days = ?, working_hours_start = ?, working_hours_end = ?,
                    break_start = ?, break_end = ?, slot_duration = ?, buffer_time = ?, max_daily_bookings = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $profession, $bio, $location, $district, $sector, 
                $experience_years, $hourly_rate, $availability,
                $working_days, $working_hours_start, $working_hours_end,
                $break_start, $break_end, $slot_duration, $buffer_time, $max_daily_bookings,
                $id
            ]);
            $success = "Provider profile updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update provider profile: " . $e->getMessage();
        }
    }
    
    // 🟣 Provider Verification Management
    if (isset($_POST['update_verification'])) {
        $id = intval($_POST['provider_id']);
        $verification_level = sanitize($_POST['verification_level']);
        $verification_notes = sanitize($_POST['verification_notes'] ?? '');
        
        try {
            $db->prepare("UPDATE service_providers SET verification_level = ?, verification_notes = ? WHERE id = ?")->execute([$verification_level, $verification_notes, $id]);
            $success = "Verification level updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update verification level";
        }
    }
    
    // 🟠 Provider Search Ranking Controls
    if (isset($_POST['update_featured_status'])) {
        $id = intval($_POST['provider_id']);
        $is_featured = intval($_POST['is_featured']);
        $featured_until = !empty($_POST['featured_until']) ? $_POST['featured_until'] : null;
        
        try {
            $stmt = $db->prepare("UPDATE service_providers SET is_featured = ?, featured_until = ? WHERE id = ?");
            $stmt->execute([$is_featured, $featured_until, $id]);
            $action = $is_featured ? 'featured' : 'unfeatured';
            $success = "Provider {$action} successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update featured status";
        }
    }
    
    if (isset($_POST['update_search_boost'])) {
        $id = intval($_POST['provider_id']);
        $search_boost = intval($_POST['search_boost']);
        
        try {
            $db->prepare("UPDATE service_providers SET search_boost = ? WHERE id = ?")->execute([$search_boost, $id]);
            $success = "Search ranking boost updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update search ranking";
        }
    }
    
    // 🔵 Provider Financial Settings
    if (isset($_POST['update_financial_settings'])) {
        $id = intval($_POST['provider_id']);
        $commission_rate = floatval($_POST['commission_rate']);
        $subscription_plan = sanitize($_POST['subscription_plan']);
        $can_receive_jobs = intval($_POST['can_receive_jobs']);
        
        try {
            $stmt = $db->prepare("UPDATE service_providers SET commission_rate = ?, subscription_plan = ?, can_receive_jobs = ? WHERE id = ?");
            $stmt->execute([$commission_rate, $subscription_plan, $can_receive_jobs, $id]);
            $success = "Financial settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update financial settings";
        }
    }
    
    // Update provider categories
    if (isset($_POST['update_categories'])) {
        $id = intval($_POST['provider_id']);
        $categories = $_POST['categories'] ?? [];
        
        try {
            $db->beginTransaction();
            
            // Remove existing categories
            $db->prepare("DELETE FROM provider_services WHERE provider_id = ?")->execute([$id]);
            
            // Add new categories
            $stmt = $db->prepare("INSERT INTO provider_services (provider_id, category_id) VALUES (?, ?)");
            foreach ($categories as $category_id) {
                $stmt->execute([$id, intval($category_id)]);
            }
            
            $db->commit();
            $success = "Provider categories updated successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to update categories: " . $e->getMessage();
        }
    }
    
    // 🟢 Manage Scheduling (Admin override)
    if (isset($_POST['update_scheduling_settings'])) {
        $id = intval($_POST['provider_id']);
        $working_days = isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : '';
        $working_hours_start = sanitize($_POST['working_hours_start']);
        $working_hours_end = sanitize($_POST['working_hours_end']);
        $break_start = sanitize($_POST['break_start'] ?? '');
        $break_end = sanitize($_POST['break_end'] ?? '');
        $slot_duration = intval($_POST['slot_duration']);
        $buffer_time = intval($_POST['buffer_time']);
        $max_daily_bookings = intval($_POST['max_daily_bookings']);
        $booking_lead_time = intval($_POST['booking_lead_time']);
        $cancellation_cutoff = intval($_POST['cancellation_cutoff']);
        
        try {
            $stmt = $db->prepare("
                UPDATE service_providers SET 
                    working_days = ?, working_hours_start = ?, working_hours_end = ?,
                    break_start = ?, break_end = ?, slot_duration = ?, buffer_time = ?, 
                    max_daily_bookings = ?, booking_lead_time = ?, cancellation_cutoff = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $working_days, $working_hours_start, $working_hours_end,
                $break_start, $break_end, $slot_duration, $buffer_time,
                $max_daily_bookings, $booking_lead_time, $cancellation_cutoff,
                $id
            ]);
            $success = "Scheduling settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update scheduling settings: " . $e->getMessage();
        }
    }
}

// Function to get provider detailed stats
function getProviderStats($db, $provider_id) {
    $stats = [];
    
    // Completed jobs
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'completed'");
    $stmt->execute([$provider_id]);
    $stats['completed_jobs'] = $stmt->fetchColumn();
    
    // Pending jobs
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'");
    $stmt->execute([$provider_id]);
    $stats['pending_jobs'] = $stmt->fetchColumn();
    
    // Total earnings (estimated)
    $stmt = $db->prepare("SELECT SUM(hourly_rate) as total_earnings FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id WHERE b.provider_id = ? AND b.status = 'completed'");
    $stmt->execute([$provider_id]);
    $stats['total_earnings'] = $stmt->fetchColumn() ?? 0;
    
    // Complaints received
    $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reported_user_id = (SELECT user_id FROM service_providers WHERE id = ?)");
    $stmt->execute([$provider_id]);
    $stats['complaints_received'] = $stmt->fetchColumn();
    
    // Upcoming bookings
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status IN ('confirmed', 'pending') AND preferred_date >= CURDATE()");
    $stmt->execute([$provider_id]);
    $stats['upcoming_bookings'] = $stmt->fetchColumn();
    
    // Cancelled bookings
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'cancelled'");
    $stmt->execute([$provider_id]);
    $stats['cancelled_bookings'] = $stmt->fetchColumn();
    
    // Time off days
    $stmt = $db->prepare("SELECT COUNT(*) FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE()");
    $stmt->execute([$provider_id]);
    $stats['time_off_days'] = $stmt->fetchColumn();
    
    return $stats;
}

// Function to get provider details for modals
function getProviderDetails($db, $provider_id) {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.is_verified as user_verified,
               u.created_at as user_created, u.updated_at as user_updated,
               GROUP_CONCAT(DISTINCT c.id) as category_ids,
               GROUP_CONCAT(DISTINCT c.name) as category_names
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN provider_services ps ON sp.id = ps.provider_id
        LEFT JOIN categories c ON ps.category_id = c.id
        WHERE sp.id = ?
        GROUP BY sp.id
    ");
    $stmt->execute([$provider_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get provider scheduling data
function getProviderSchedulingData($db, $provider_id) {
    $data = [];
    
    // Get working hours
    $stmt = $db->prepare("SELECT working_days, working_hours_start, working_hours_end, break_start, break_end, slot_duration, buffer_time, max_daily_bookings FROM service_providers WHERE id = ?");
    $stmt->execute([$provider_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get time off
    $stmt = $db->prepare("SELECT * FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE() ORDER BY start_date ASC LIMIT 10");
    $stmt->execute([$provider_id]);
    $data['time_off'] = $stmt->fetchAll();
    
    // Get availability exceptions
    $stmt = $db->prepare("SELECT * FROM provider_availability WHERE provider_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 10");
    $stmt->execute([$provider_id]);
    $data['availability_exceptions'] = $stmt->fetchAll();
    
    // Get upcoming bookings
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as client_name 
        FROM bookings b
        JOIN users u ON b.client_id = u.id
        WHERE b.provider_id = ? AND b.status IN ('confirmed', 'pending') 
        AND DATE(b.preferred_date) >= CURDATE()
        ORDER BY b.preferred_date ASC
        LIMIT 10
    ");
    $stmt->execute([$provider_id]);
    $data['upcoming_bookings'] = $stmt->fetchAll();
    
    return $data;
}

// Function to get days of week from working_days string
function getWorkingDaysArray($working_days) {
    if (empty($working_days)) {
        return [1, 2, 3, 4, 5]; // Default: Mon-Fri
    }
    return explode(',', $working_days);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Management - BII LocalFinder</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Admin Layout */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .sidebar-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .welcome-text p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
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
            font-weight: 600;
        }
        
        /* Filters Card */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
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
        }
        
        /* Provider Cards */
        .provider-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .provider-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .provider-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .provider-info {
            flex: 1;
        }
        
        .provider-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .provider-profession {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .provider-location {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        /* Provider Stats Grid */
        .provider-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: #e2e8f0;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        /* Scheduling Stats */
        .scheduling-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f0f9ff;
            border-radius: 8px;
            border: 1px solid #e0f2fe;
        }
        
        .schedule-item {
            text-align: center;
            padding: 0.75rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .schedule-label {
            font-size: 0.75rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }
        
        .schedule-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Working Hours Display */
        .working-hours-display {
            background: #f1f5f9;
            padding: 0.75rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }
        
        .hours-range {
            font-weight: 600;
            color: var(--primary);
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.active {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge.inactive {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .badge.banned {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.available {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.busy {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.unavailable {
            background: #f8d7da;
            color: #991b1b;
        }
        
        /* Verification Badges */
        .verification-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-gold {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-premium {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .badge-featured {
            background: #fecaca;
            color: #991b1b;
        }
        
        .badge-banned {
            background: #374151;
            color: white;
        }
        
        /* Status Indicators */
        .status-indicators {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-sm:hover {
            transform: translateY(-1px);
        }
        
        /* Scheduling Action Button */
        .btn-schedule {
            background: #0dcaf0;
            color: white;
        }
        
        .btn-schedule:hover {
            background: #0aa2c0;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-lg {
            max-width: 800px;
        }
        
        .modal-xl {
            max-width: 1000px;
        }
        
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .close:hover {
            color: var(--dark);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Days of week selector */
        .days-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 0.5rem 0;
        }
        
        .day-checkbox {
            position: relative;
        }
        
        .day-checkbox input {
            display: none;
        }
        
        .day-checkbox label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .day-checkbox input:checked + label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
            color: #dee2e6;
        }
        
        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .welcome-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .provider-header {
                flex-direction: column;
            }
            
            .provider-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .scheduling-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-sm {
                width: 100%;
                justify-content: center;
            }
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .overlay.active {
            display: block;
        }
        
        /* Price Display */
        .price-display {
            text-align: right;
        }
        
        .hourly-rate {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--success);
        }
        
        .rate-label {
            color: var(--secondary);
            font-size: 0.8rem;
        }
        
        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .detail-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Scheduling Details */
        .scheduling-details {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
        }
        
        .time-slot {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin: 0.2rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Admin Layout -->
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1><i class="fas fa-tools me-2"></i> Provider Management</h1>
                        <p>Comprehensive provider management and verification system</p>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-success" onclick="exportProviders()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                    </div>
                </div>
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

            <!-- Search and Filters -->
            <div class="filters-card">
                <h3 class="mb-3">Search & Filter Providers</h3>
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="form-control" placeholder="Search by name, profession, email...">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Verification</label>
                            <select name="verification" class="form-select">
                                <option value="">All Levels</option>
                                <option value="none" <?php echo $verification_filter === 'none' ? 'selected' : ''; ?>>Not Verified</option>
                                <option value="verified" <?php echo $verification_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="gold" <?php echo $verification_filter === 'gold' ? 'selected' : ''; ?>>Gold Verified</option>
                                <option value="premium" <?php echo $verification_filter === 'premium' ? 'selected' : ''; ?>>Premium Verified</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Availability</label>
                            <select name="availability" class="form-select">
                                <option value="">All Availability</option>
                                <option value="available" <?php echo $availability_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="busy" <?php echo $availability_filter === 'busy' ? 'selected' : ''; ?>>Busy</option>
                                <option value="unavailable" <?php echo $availability_filter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                            </select>
                        </div>
                        <div class="filter-group d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Apply Filters
                            </button>
                            <a href="providers.php" class="btn btn-secondary w-100">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Providers List -->
            <div class="card">
                <div class="card-header">
                    <h3>Service Providers (<?php echo count($providers); ?>)</h3>
                </div>

                <?php if (empty($providers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h3>No providers found</h3>
                        <p>No providers found matching your criteria</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($providers as $provider): 
                        $provider_stats = getProviderStats($db, $provider['id']);
                        $working_days = getWorkingDaysArray($provider['working_days']);
                        $days_of_week = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        $req = new ProviderRequirements($db, $provider['id']);
                        $completion_pct = $req->getCompletionPercentage();
                        $is_complete = $req->isComplete();
                    ?>
                        <div class="provider-card">
                            <div class="provider-header">
                                <div class="provider-info">
                                    <div class="provider-name">
                                        <?php echo htmlspecialchars($provider['full_name']); ?>
                                        <?php if ($provider['is_featured']): ?>
                                            <span class="badge-featured verification-badge">FEATURED</span>
                                        <?php endif; ?>
                                        <?php if ($provider['is_banned']): ?>
                                            <span class="badge-banned verification-badge">BANNED</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="provider-profession">
                                        <i class="fas fa-briefcase me-1"></i> <?php echo htmlspecialchars($provider['profession']); ?>
                                    </div>
                                    <div class="provider-location">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($provider['location']); ?>
                                        <?php if ($provider['district']): ?>
                                            , <?php echo htmlspecialchars($provider['district']); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="status-indicators">
                                        <span class="badge <?php echo $provider['availability']; ?>">
                                            <?php echo ucfirst($provider['availability']); ?>
                                        </span>
                                        <span class="badge <?php echo $provider['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $provider['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if ($provider['verification_level'] && $provider['verification_level'] !== 'none'): ?>
                                            <span class="badge-<?php echo $provider['verification_level']; ?> verification-badge">
                                                <?php echo strtoupper($provider['verification_level']); ?> VERIFIED
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="price-display">
                                    <div class="hourly-rate">
                                        RWF <?php echo number_format($provider['hourly_rate'] ?? 0); ?>
                                    </div>
                                    <div class="rate-label">per hour</div>
                                </div>
                            </div>
                            
                            <?php if ($provider['categories']): ?>
                                <div class="mb-2">
                                    <strong>Categories:</strong>
                                    <span class="text-muted"><?php echo htmlspecialchars($provider['categories']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($provider['bio']): ?>
                                <div class="mb-2 text-muted fst-italic">
                                    "<?php echo htmlspecialchars(substr($provider['bio'], 0, 150)); ?>..."
                                </div>
                            <?php endif; ?>
                            
                            <!-- Scheduling Information -->
                            <?php if ($provider['working_hours_start'] && $provider['working_hours_end']): ?>
                                <div class="scheduling-stats">
                                    <div class="schedule-item">
                                        <div class="schedule-label">Working Hours</div>
                                        <div class="schedule-value hours-range">
                                            <?php echo date('g:i A', strtotime($provider['working_hours_start'])); ?> - 
                                            <?php echo date('g:i A', strtotime($provider['working_hours_end'])); ?>
                                        </div>
                                    </div>
                                    <div class="schedule-item">
                                        <div class="schedule-label">Slot Duration</div>
                                        <div class="schedule-value"><?php echo $provider['slot_duration'] ?? 30; ?> min</div>
                                    </div>
                                    <div class="schedule-item">
                                        <div class="schedule-label">Max Daily</div>
                                        <div class="schedule-value"><?php echo $provider['max_daily_bookings'] ?? 8; ?> bookings</div>
                                    </div>
                                    <div class="schedule-item">
                                        <div class="schedule-label">Buffer Time</div>
                                        <div class="schedule-value"><?php echo $provider['buffer_time'] ?? 15; ?> min</div>
                                    </div>
                                    <div class="schedule-item">
                                        <div class="schedule-label">Working Days</div>
                                        <div class="schedule-value">
                                            <?php 
                                            $day_labels = [];
                                            foreach ($working_days as $day) {
                                                $day_labels[] = $days_of_week[$day-1] ?? $day;
                                            }
                                            echo implode(', ', $day_labels);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="provider-stats">
                                <div class="stat-item" style="background: <?php echo $completion_pct >= 80 ? '#d4edda' : ($completion_pct >= 50 ? '#fff3cd' : '#f8fafc'); ?>; border-left: 3px solid <?php echo $is_complete ? '#198754' : ($completion_pct >= 50 ? '#ffc107' : '#dee2e6'); ?>;">
                                    <div class="stat-value" style="color: <?php echo $is_complete ? '#155724' : ($completion_pct >= 50 ? '#856404' : '#6c757d'); ?>;"><?php echo $completion_pct; ?>%</div>
                                    <div class="stat-label">Profile Complete</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $provider_stats['completed_jobs']; ?></div>
                                    <div class="stat-label">Jobs Done</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($provider['average_rating'] ?? 0, 1); ?> ⭐</div>
                                    <div class="stat-label">Rating (<?php echo $provider['total_reviews']; ?> reviews)</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $provider['experience_years']; ?>y</div>
                                    <div class="stat-label">Experience</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">RWF <?php echo number_format($provider_stats['total_earnings']); ?></div>
                                    <div class="stat-label">Total Earnings</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $provider_stats['upcoming_bookings']; ?></div>
                                    <div class="stat-label">Upcoming</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $provider_stats['time_off_days']; ?></div>
                                    <div class="stat-label">Time Off Days</div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <!-- 🔴 Account Lifecycle -->
                                <?php if (!$provider['user_verified']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                        <button type="submit" name="approve_provider" class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="showRejectionForm(<?php echo $provider['id']; ?>)">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($provider['user_verified'] && !$provider['is_banned']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $provider['is_active']; ?>">
                                        <button type="submit" name="toggle_activation" class="btn btn-<?php echo $provider['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                            <i class="fas fa-<?php echo $provider['is_active'] ? 'pause' : 'play'; ?> me-1"></i>
                                            <?php echo $provider['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showBanForm(<?php echo $provider['id']; ?>)">
                                        <i class="fas fa-ban me-1"></i> Ban
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($provider['is_banned']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                        <button type="submit" name="unban_provider" class="btn btn-success btn-sm">
                                            <i class="fas fa-unlock me-1"></i> Unban
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <!-- 🔵 Profile Management -->
                                <button type="button" class="btn btn-info btn-sm" onclick="editProviderProfile(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-edit me-1"></i> Edit Profile
                                </button>
                                
                                <!-- 🟢 Scheduling Management -->
                                <button type="button" class="btn btn-schedule btn-sm" onclick="manageScheduling(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-calendar-alt me-1"></i> Schedule
                                </button>
                                
                                <!-- 🟣 Verification -->
                                <button type="button" class="btn btn-primary btn-sm" onclick="manageVerification(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-shield-alt me-1"></i> Verification
                                </button>
                                
                                <!-- 🟠 Search Ranking -->
                                <button type="button" class="btn btn-secondary btn-sm" onclick="manageSearchRanking(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-chart-line me-1"></i> Ranking
                                </button>
                                
                                <!-- 🔵 Financial -->
                                <button type="button" class="btn btn-warning btn-sm" onclick="manageFinancial(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-money-bill me-1"></i> Financial
                                </button>
                                
                                <!-- Categories -->
                                <button type="button" class="btn btn-dark btn-sm" onclick="manageCategories(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-tags me-1"></i> Categories
                                </button>
                                
                                <!-- View Details -->
                                <button type="button" class="btn btn-dark btn-sm" onclick="viewProviderDetails(<?php echo $provider['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i> Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    
    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('rejectionModal')">&times;</span>
            <h3>Reject Provider Application</h3>
            <form method="POST" id="rejectionForm">
                <input type="hidden" name="provider_id" id="reject_provider_id">
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Explain why the provider application is being rejected..." required></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="reject_provider" class="btn btn-danger">Reject Application</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectionModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ban Modal -->
    <div id="banModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('banModal')">&times;</span>
            <h3>Ban Provider</h3>
            <form method="POST" id="banForm">
                <input type="hidden" name="provider_id" id="ban_provider_id">
                <div class="form-group">
                    <label>Ban Reason</label>
                    <textarea name="ban_reason" class="form-control" rows="4" placeholder="Explain why the provider is being banned..." required></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="ban_provider" class="btn btn-danger">Ban Provider</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('banModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Edit Modal (WITH SCHEDULING) -->
    <div id="profileEditModal" class="modal">
        <div class="modal-content modal-lg">
            <span class="close" onclick="closeModal('profileEditModal')">&times;</span>
            <h3>Edit Provider Profile & Schedule</h3>
            <form method="POST" id="profileEditForm">
                <input type="hidden" name="provider_id" id="edit_provider_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Profession</label>
                            <input type="text" name="profession" class="form-control" id="edit_profession" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Hourly Rate (RWF)</label>
                            <input type="number" name="hourly_rate" class="form-control" id="edit_hourly_rate" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" class="form-control" id="edit_bio" rows="3"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control" id="edit_location">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>District</label>
                            <input type="text" name="district" class="form-control" id="edit_district">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Sector</label>
                            <input type="text" name="sector" class="form-control" id="edit_sector">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Experience (Years)</label>
                            <input type="number" name="experience_years" class="form-control" id="edit_experience" min="0" max="50">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Availability</label>
                            <select name="availability" class="form-control" id="edit_availability">
                                <option value="available">Available</option>
                                <option value="busy">Busy</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Scheduling Section -->
                <div class="scheduling-details">
                    <h5 class="mb-3">Scheduling Settings</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Working Days</label>
                                <div class="days-selector" id="edit_working_days">
                                    <?php 
                                    $days = [
                                        1 => 'Mon',
                                        2 => 'Tue',
                                        3 => 'Wed',
                                        4 => 'Thu',
                                        5 => 'Fri',
                                        6 => 'Sat',
                                        7 => 'Sun'
                                    ];
                                    foreach ($days as $value => $label): ?>
                                        <div class="day-checkbox">
                                            <input type="checkbox" name="working_days[]" value="<?php echo $value; ?>" 
                                                   id="edit_day<?php echo $value; ?>">
                                            <label for="edit_day<?php echo $value; ?>"><?php echo $label; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Slot Duration (minutes)</label>
                                <select class="form-control" name="slot_duration" id="edit_slot_duration">
                                    <option value="15">15 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="45">45 minutes</option>
                                    <option value="60">60 minutes</option>
                                    <option value="90">90 minutes</option>
                                    <option value="120">2 hours</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" class="form-control" name="working_hours_start" id="edit_start_time">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" class="form-control" name="working_hours_end" id="edit_end_time">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Break Start (Optional)</label>
                                <input type="time" class="form-control" name="break_start" id="edit_break_start">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Break End (Optional)</label>
                                <input type="time" class="form-control" name="break_end" id="edit_break_end">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Buffer Time Between Appointments (minutes)</label>
                                <input type="number" class="form-control" name="buffer_time" id="edit_buffer_time" min="0" max="60" step="5">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Maximum Daily Bookings</label>
                                <input type="number" class="form-control" name="max_daily_bookings" id="edit_max_daily" min="1" max="20">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_provider_profile" class="btn btn-primary">Update Profile & Schedule</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('profileEditModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scheduling Management Modal -->
    <div id="schedulingModal" class="modal">
        <div class="modal-content modal-lg">
            <span class="close" onclick="closeModal('schedulingModal')">&times;</span>
            <h3>Manage Provider Scheduling</h3>
            <form method="POST" id="schedulingForm">
                <input type="hidden" name="provider_id" id="scheduling_provider_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Working Days</label>
                            <div class="days-selector" id="scheduling_working_days">
                                <?php foreach ($days as $value => $label): ?>
                                    <div class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="<?php echo $value; ?>" 
                                               id="schedule_day<?php echo $value; ?>">
                                        <label for="schedule_day<?php echo $value; ?>"><?php echo $label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Slot Duration (minutes)</label>
                            <select class="form-control" name="slot_duration" id="scheduling_slot_duration">
                                <option value="15">15 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">60 minutes</option>
                                <option value="90">90 minutes</option>
                                <option value="120">2 hours</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" class="form-control" name="working_hours_start" id="scheduling_start_time">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" class="form-control" name="working_hours_end" id="scheduling_end_time">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Max Daily Bookings</label>
                            <input type="number" class="form-control" name="max_daily_bookings" id="scheduling_max_daily" min="1" max="50">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Break Start (Optional)</label>
                            <input type="time" class="form-control" name="break_start" id="scheduling_break_start">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Break End (Optional)</label>
                            <input type="time" class="form-control" name="break_end" id="scheduling_break_end">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Buffer Time (minutes)</label>
                            <input type="number" class="form-control" name="buffer_time" id="scheduling_buffer_time" min="0" max="120" step="5">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Booking Lead Time (hours)</label>
                            <input type="number" class="form-control" name="booking_lead_time" id="scheduling_lead_time" min="0" max="168" value="24">
                            <small class="form-text text-muted">Minimum hours before a booking can be made</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Cancellation Cutoff (hours)</label>
                            <input type="number" class="form-control" name="cancellation_cutoff" id="scheduling_cutoff" min="0" max="72" value="12">
                            <small class="form-text text-muted">Hours before appointment when cancellation is no longer allowed</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_scheduling_settings" class="btn btn-primary">Update Scheduling Settings</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('schedulingModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Verification Modal -->
    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('verificationModal')">&times;</span>
            <h3>Manage Provider Verification</h3>
            <form method="POST" id="verificationForm">
                <input type="hidden" name="provider_id" id="verify_provider_id">
                <div class="form-group">
                    <label>Verification Level</label>
                    <select name="verification_level" class="form-control" id="verification_level">
                        <option value="none">Not Verified</option>
                        <option value="verified">Verified</option>
                        <option value="gold">Gold Verified</option>
                        <option value="premium">Premium Verified</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Verification Notes</label>
                    <textarea name="verification_notes" class="form-control" id="verification_notes" rows="4" placeholder="Add any notes about the verification process..."></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="update_verification" class="btn btn-primary">Update Verification</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('verificationModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Ranking Modal -->
    <div id="searchRankingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('searchRankingModal')">&times;</span>
            <h3>Manage Search Ranking</h3>
            <form method="POST" id="searchRankingForm">
                <input type="hidden" name="provider_id" id="ranking_provider_id">
                
                <div class="form-group">
                    <label>Featured Status</label>
                    <select name="is_featured" class="form-control" id="is_featured">
                        <option value="0">Not Featured</option>
                        <option value="1">Featured</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Featured Until</label>
                    <input type="datetime-local" name="featured_until" class="form-control" id="featured_until">
                </div>
                
                <div class="form-group">
                    <label>Search Boost (0-100)</label>
                    <input type="range" name="search_boost" class="form-control" id="search_boost" min="0" max="100" value="0">
                    <div class="d-flex justify-content-between">
                        <small>0 (Normal)</small>
                        <small id="search_boost_value">0</small>
                        <small>100 (Highest)</small>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_featured_status" class="btn btn-primary">Update Featured Status</button>
                    <button type="submit" name="update_search_boost" class="btn btn-secondary">Update Search Boost</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('searchRankingModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Financial Settings Modal -->
    <div id="financialModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('financialModal')">&times;</span>
            <h3>Manage Financial Settings</h3>
            <form method="POST" id="financialForm">
                <input type="hidden" name="provider_id" id="financial_provider_id">
                
                <div class="form-group">
                    <label>Commission Rate (%)</label>
                    <input type="number" name="commission_rate" class="form-control" id="commission_rate" step="0.1" min="0" max="50" required>
                </div>
                
                <div class="form-group">
                    <label>Subscription Plan</label>
                    <select name="subscription_plan" class="form-control" id="subscription_plan">
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Can Receive Jobs</label>
                    <select name="can_receive_jobs" class="form-control" id="can_receive_jobs">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_financial_settings" class="btn btn-primary">Update Financial Settings</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('financialModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories Modal -->
    <div id="categoriesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('categoriesModal')">&times;</span>
            <h3>Manage Provider Categories</h3>
            <form method="POST" id="categoriesForm">
                <input type="hidden" name="provider_id" id="categories_provider_id">
                
                <div class="form-group">
                    <label>Select Categories</label>
                    <div class="category-checkboxes" style="max-height: 300px; overflow-y: auto; border: 1px solid #e9ecef; padding: 1rem; border-radius: 6px;">
                        <?php foreach ($categories as $category): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input category-checkbox" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category_<?php echo $category['id']; ?>">
                                <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                    <i class="fas <?php echo $category['icon'] ?? 'fa-tag'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_categories" class="btn btn-primary">Update Categories</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('categoriesModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Provider Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content modal-xl">
            <span class="close" onclick="closeModal('detailsModal')">&times;</span>
            <h3>Provider Details</h3>
            <div id="providerDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Modal functions
        function showRejectionForm(providerId) {
            document.getElementById('reject_provider_id').value = providerId;
            document.getElementById('rejectionModal').style.display = 'block';
        }
        
        function showBanForm(providerId) {
            document.getElementById('ban_provider_id').value = providerId;
            document.getElementById('banModal').style.display = 'block';
        }
        
        function editProviderProfile(providerId) {
            // Fetch provider data and populate form
            fetch(`get_provider_data.php?action=profile&id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_provider_id').value = providerId;
                    document.getElementById('edit_profession').value = data.profession || '';
                    document.getElementById('edit_bio').value = data.bio || '';
                    document.getElementById('edit_location').value = data.location || '';
                    document.getElementById('edit_district').value = data.district || '';
                    document.getElementById('edit_sector').value = data.sector || '';
                    document.getElementById('edit_experience').value = data.experience_years || '';
                    document.getElementById('edit_hourly_rate').value = data.hourly_rate || '';
                    document.getElementById('edit_availability').value = data.availability || 'available';
                    
                    // Scheduling data
                    const workingDays = data.working_days ? data.working_days.split(',') : [];
                    workingDays.forEach(day => {
                        const checkbox = document.getElementById(`edit_day${day}`);
                        if (checkbox) checkbox.checked = true;
                    });
                    
                    document.getElementById('edit_slot_duration').value = data.slot_duration || '30';
                    document.getElementById('edit_start_time').value = data.working_hours_start || '08:00';
                    document.getElementById('edit_end_time').value = data.working_hours_end || '17:00';
                    document.getElementById('edit_break_start').value = data.break_start || '';
                    document.getElementById('edit_break_end').value = data.break_end || '';
                    document.getElementById('edit_buffer_time').value = data.buffer_time || '15';
                    document.getElementById('edit_max_daily').value = data.max_daily_bookings || '8';
                    
                    document.getElementById('profileEditModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching provider data:', error);
                    alert('Error loading provider data');
                });
        }
        
        function manageScheduling(providerId) {
            // Fetch provider scheduling data
            fetch(`get_provider_data.php?action=scheduling&id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('scheduling_provider_id').value = providerId;
                    
                    // Working days
                    const workingDays = data.working_days ? data.working_days.split(',') : [];
                    workingDays.forEach(day => {
                        const checkbox = document.getElementById(`schedule_day${day}`);
                        if (checkbox) checkbox.checked = true;
                    });
                    
                    // Time settings
                    document.getElementById('scheduling_slot_duration').value = data.slot_duration || '30';
                    document.getElementById('scheduling_start_time').value = data.working_hours_start || '08:00';
                    document.getElementById('scheduling_end_time').value = data.working_hours_end || '17:00';
                    document.getElementById('scheduling_break_start').value = data.break_start || '';
                    document.getElementById('scheduling_break_end').value = data.break_end || '';
                    document.getElementById('scheduling_buffer_time').value = data.buffer_time || '15';
                    document.getElementById('scheduling_max_daily').value = data.max_daily_bookings || '8';
                    document.getElementById('scheduling_lead_time').value = data.booking_lead_time || '24';
                    document.getElementById('scheduling_cutoff').value = data.cancellation_cutoff || '12';
                    
                    document.getElementById('schedulingModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching scheduling data:', error);
                    alert('Error loading scheduling data');
                });
        }
        
        function manageVerification(providerId) {
            // Fetch provider data and populate form
            fetch(`get_provider_data.php?action=verification&id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('verify_provider_id').value = providerId;
                    document.getElementById('verification_level').value = data.verification_level || 'none';
                    document.getElementById('verification_notes').value = data.verification_notes || '';
                    
                    document.getElementById('verificationModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching provider data:', error);
                    alert('Error loading provider data');
                });
        }
        
        function manageSearchRanking(providerId) {
            // Fetch provider data and populate form
            fetch(`get_provider_data.php?action=ranking&id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('ranking_provider_id').value = providerId;
                    document.getElementById('is_featured').value = data.is_featured || '0';
                    document.getElementById('featured_until').value = data.featured_until || '';
                    document.getElementById('search_boost').value = data.search_boost || '0';
                    document.getElementById('search_boost_value').textContent = data.search_boost || '0';
                    
                    document.getElementById('searchRankingModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching provider data:', error);
                    alert('Error loading provider data');
                });
        }
        
        function manageFinancial(providerId) {
            // Fetch provider data and populate form
            fetch(`get_provider_data.php?action=financial&id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('financial_provider_id').value = providerId;
                    document.getElementById('commission_rate').value = data.commission_rate || '10';
                    document.getElementById('subscription_plan').value = data.subscription_plan || 'basic';
                    document.getElementById('can_receive_jobs').value = data.can_receive_jobs || '1';
                    
                    document.getElementById('financialModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching provider data:', error);
                    alert('Error loading provider data');
                });
        }
        
        function manageCategories(providerId) {
            // Fetch provider categories and populate form
            fetch(`get_provider_data.php?action=categories&id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('categories_provider_id').value = providerId;
                    
                    // Uncheck all checkboxes first
                    document.querySelectorAll('.category-checkbox').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    
                    // Check the provider's current categories
                    if (data.category_ids) {
                        const categoryIds = data.category_ids.split(',');
                        categoryIds.forEach(categoryId => {
                            const checkbox = document.getElementById(`category_${categoryId}`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }
                    
                    document.getElementById('categoriesModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching provider data:', error);
                    alert('Error loading provider data');
                });
        }
        
        function viewProviderDetails(providerId) {
            // Fetch provider details
            fetch(`get_provider_data.php?action=details&id=${providerId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('providerDetailsContent').innerHTML = html;
                    document.getElementById('detailsModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching provider details:', error);
                    document.getElementById('providerDetailsContent').innerHTML = '<p>Error loading provider details</p>';
                    document.getElementById('detailsModal').style.display = 'block';
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function exportProviders() {
            alert('Export feature would be implemented here');
        }

        // Search boost slider value display
        document.getElementById('search_boost').addEventListener('input', function() {
            document.getElementById('search_boost_value').textContent = this.value;
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Close modals with close button
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = 'none';
            };
        });
    </script>
</body>
</html>