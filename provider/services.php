<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/service_negotiation.php';
require_once '../includes/language.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

if (!function_exists('parseOptionalExtrasInput')) {
    function parseOptionalExtrasInput($input) {
        $items = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($input));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(.+?)\s*\(\+\$?([\d,]+(?:\.\d{1,2})?)\)$/', $line, $matches)
                || preg_match('/^(.+?)\s*\+\$?([\d,]+(?:\.\d{1,2})?)$/', $line, $matches)) {
                $label = trim($matches[1]);
                $price = floatval(str_replace(',', '', $matches[2]));
                if ($label !== '' && $price >= 0) {
                    $items[] = ['label' => $label, 'price' => $price];
                    continue;
                }
            }

            return null;
        }

        return $items;
    }
}

if (!function_exists('parseServiceTimeSlots')) {
    function parseServiceTimeSlots($input) {
        $slots = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($input));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:([01]\d|2[0-3]):([0-5]\d))\s*-\s*((?:[01]\d|2[0-3])):([0-5]\d)$/', $line, $matches)) {
                $start = $matches[1] . ':' . $matches[2];
                $end = $matches[3] . ':' . $matches[4];
                if (strtotime($start) >= strtotime($end)) {
                    return null;
                }
                $slots[] = ['start' => $start, 'end' => $end];
                continue;
            }
            return null;
        }
        return $slots;
    }
}

if (!function_exists('normalizeAvailabilityDays')) {
    function normalizeAvailabilityDays($input) {
        $days = [];
        if (is_array($input)) {
            $source = $input;
        } else {
            $source = preg_split('/[\s,]+/', trim($input));
        }
        foreach ($source as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $day = intval($value);
            if ($day >= 1 && $day <= 7) {
                $days[] = $day;
            }
        }
        if (empty($days)) {
            return [1,2,3,4,5];
        }
        return array_values(array_unique($days));
    }
}

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get provider's categories (from provider_categories mapping)
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.name, c.icon 
    FROM categories c
    JOIN provider_categories pc ON c.id = pc.category_id
    WHERE pc.provider_id = ?
");
$stmt->execute([$provider['id']]);
$provider_categories = $stmt->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new service
    if (isset($_POST['add_service'])) {
        $name = sanitize($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $description = sanitize($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Validate category belongs to provider
        $valid_category = false;
        foreach ($provider_categories as $cat) {
            if ($cat['id'] == $category_id) {
                $valid_category = true;
                break;
            }
        }
        
        if (!$valid_category) {
            $errors[] = "Invalid service category selected";
        }
        
        if (empty($name) || empty($description)) {
            $errors[] = "Service name and description are required";
        }
        
        if ($price < 0) {
            $errors[] = "Price cannot be negative";
        }
        
        if ($duration < 15) {
            $errors[] = "Duration must be at least 15 minutes";
        }
        
        // When handling POST -> Add new service (validate payment_type)
        $allowedPaymentTypes = ['fixed_price','hourly_rate','per_job_estimate','per_day','per_service','base_price'];
        $payment_type = in_array($_POST['payment_type'] ?? '', $allowedPaymentTypes, true) ? $_POST['payment_type'] : 'fixed_price';
        
        // Get negotiation settings
        $negotiable = isset($_POST['negotiable']) ? 1 : 0;
        $min_price = $negotiable ? floatval($_POST['min_price'] ?? $price) : null;
        $max_price = $negotiable ? floatval($_POST['max_price'] ?? $price) : null;

        $availability_days = normalizeAvailabilityDays($_POST['availability_days'] ?? '1,2,3,4,5');
        $availability_days_str = implode(',', $availability_days);

        $time_slots_raw = trim($_POST['time_slots'] ?? '');
        $time_slots_json = null;
        if ($time_slots_raw !== '') {
            $parsedSlots = parseServiceTimeSlots($time_slots_raw);
            if ($parsedSlots === null) {
                $errors[] = "Time slots must be entered one per line in HH:MM-HH:MM format.";
            } else {
                $time_slots_json = json_encode($parsedSlots);
            }
        }

        $booking_mode = in_array($_POST['booking_mode'] ?? 'request_approval', ['instant', 'request_approval'], true) ? $_POST['booking_mode'] : 'request_approval';
        $service_status = in_array($_POST['service_status'] ?? 'draft', ['draft', 'published', 'paused'], true) ? $_POST['service_status'] : 'draft';
        $is_available = ($service_status === 'published') ? 1 : 0;

        $optional_extras_raw = trim($_POST['optional_extras'] ?? '');
        $optional_extras_json = null;
        if ($optional_extras_raw !== '') {
            $parsedExtras = parseOptionalExtrasInput($optional_extras_raw);
            if ($parsedExtras === null) {
                $errors[] = "Optional extras must be listed one per line in the format: Emergency service (+10000)";
            } else {
                $optional_extras_json = json_encode($parsedExtras);
            }
        }
        
        // Validate min/max prices
        if ($negotiable) {
            if ($min_price <= 0 || $max_price <= 0) {
                $errors[] = "Min and max prices must be positive";
            } elseif ($min_price > $max_price) {
                $errors[] = "Minimum price cannot exceed maximum price";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("INSERT INTO provider_services 
                    (provider_id, category_id, name, description, price, duration, is_available, payment_type, negotiable, min_price, max_price, base_price, optional_extras, availability_days, time_slots, booking_mode, service_status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $provider['id'], $category_id, $name, $description, $price, $duration, $is_available, $payment_type, $negotiable, $min_price, $max_price, $price, $optional_extras_json, $availability_days_str, $time_slots_json, $booking_mode, $service_status
                ]);
                $success = "Service added successfully";
            } catch (Exception $e) {
                $errors[] = "Failed to add service";
                error_log("Add service error: ".$e->getMessage());
            }
        }
    }
    
    // Update service
    if (isset($_POST['update_service'])) {
        $service_id = intval($_POST['service_id']);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Verify service belongs to provider
        $stmt = $db->prepare("SELECT id FROM provider_services WHERE id = ? AND provider_id = ?");
        $stmt->execute([$service_id, $provider['id']]);
        
        if (!$stmt->fetch()) {
            $errors[] = "Service not found or access denied";
        }
        
        if (empty($name) || empty($description)) {
            $errors[] = "Service name and description are required";
        }
        
        if ($price < 0) {
            $errors[] = "Price cannot be negative";
        }
        
        if ($duration < 15) {
            $errors[] = "Duration must be at least 15 minutes";
        }
        
        // Update service (include payment_type)
        $allowedPaymentTypes = ['fixed_price','hourly_rate','per_job_estimate','per_day','per_service','base_price'];
        $payment_type = in_array($_POST['payment_type'] ?? '', $allowedPaymentTypes, true) ? $_POST['payment_type'] : 'fixed_price';
        
        // Get negotiation settings
        $negotiable = isset($_POST['negotiable']) ? 1 : 0;
        $min_price = $negotiable ? floatval($_POST['min_price'] ?? $price) : null;
        $max_price = $negotiable ? floatval($_POST['max_price'] ?? $price) : null;

        $availability_days = normalizeAvailabilityDays($_POST['availability_days'] ?? '1,2,3,4,5');
        $availability_days_str = implode(',', $availability_days);

        $time_slots_raw = trim($_POST['time_slots'] ?? '');
        $time_slots_json = null;
        if ($time_slots_raw !== '') {
            $parsedSlots = parseServiceTimeSlots($time_slots_raw);
            if ($parsedSlots === null) {
                $errors[] = "Time slots must be entered one per line in HH:MM-HH:MM format.";
            } else {
                $time_slots_json = json_encode($parsedSlots);
            }
        }

        $booking_mode = in_array($_POST['booking_mode'] ?? 'request_approval', ['instant', 'request_approval'], true) ? $_POST['booking_mode'] : 'request_approval';
        $service_status = in_array($_POST['service_status'] ?? 'draft', ['draft', 'published', 'paused'], true) ? $_POST['service_status'] : 'draft';
        $is_available = ($service_status === 'published') ? 1 : 0;

        $optional_extras_raw = trim($_POST['optional_extras'] ?? '');
        $optional_extras_json = null;
        if ($optional_extras_raw !== '') {
            $parsedExtras = parseOptionalExtrasInput($optional_extras_raw);
            if ($parsedExtras === null) {
                $errors[] = "Optional extras must be listed one per line in the format: Emergency service (+10000)";
            } else {
                $optional_extras_json = json_encode($parsedExtras);
            }
        }
        
        // Validate min/max prices
        if ($negotiable) {
            if ($min_price <= 0 || $max_price <= 0) {
                $errors[] = "Min and max prices must be positive";
            } elseif ($min_price > $max_price) {
                $errors[] = "Minimum price cannot exceed maximum price";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("UPDATE provider_services
                    SET name = ?, description = ?, price = ?, duration = ?, is_available = ?, payment_type = ?, negotiable = ?, min_price = ?, max_price = ?, base_price = ?, optional_extras = ?, availability_days = ?, time_slots = ?, booking_mode = ?, service_status = ?, updated_at = NOW()
                    WHERE id = ? AND provider_id = ?");
                $stmt->execute([
                    $name, $description, $price, $duration, $is_available, $payment_type, $negotiable, $min_price, $max_price, $price, $optional_extras_json, $availability_days_str, $time_slots_json, $booking_mode, $service_status, $service_id, $provider['id']
                ]);
                $success = "Service updated successfully";
            } catch (Exception $e) {
                $errors[] = "Failed to update service";
                error_log("Update service error: ".$e->getMessage());
            }
        }
    }
    
    // Delete service
    if (isset($_POST['delete_service'])) {
        $service_id = intval($_POST['service_id']);
        
        // Verify service belongs to provider
        $stmt = $db->prepare("SELECT id FROM provider_services WHERE id = ? AND provider_id = ?");
        $stmt->execute([$service_id, $provider['id']]);
        
        if (!$stmt->fetch()) {
            $errors[] = "Service not found or access denied";
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM provider_services WHERE id = ? AND provider_id = ?");
                $stmt->execute([$service_id, $provider['id']]);
                
                $success = "Service deleted successfully!";
                
            } catch (Exception $e) {
                $errors[] = "Failed to delete service: " . $e->getMessage();
                error_log("Delete service error: " . $e->getMessage());
            }
        }
    }
    
    // Toggle service availability
    if (isset($_POST['toggle_availability'])) {
        $service_id = intval($_POST['service_id']);
        
        // Verify service belongs to provider and get current status
        $stmt = $db->prepare("SELECT id, service_status FROM provider_services WHERE id = ? AND provider_id = ?");
        $stmt->execute([$service_id, $provider['id']]);
        $service = $stmt->fetch();

        if (!$service) {
            $errors[] = "Service not found or access denied";
        } else {
            try {
                $new_status = ($service['service_status'] === 'published') ? 'paused' : 'published';
                $new_availability = ($new_status === 'published') ? 1 : 0;
                $stmt = $db->prepare("UPDATE provider_services SET service_status = ?, is_available = ? WHERE id = ? AND provider_id = ?");
                $stmt->execute([$new_status, $new_availability, $service_id, $provider['id']]);
                
                $success = "Service status updated!";
            } catch (Exception $e) {
                $errors[] = "Failed to update service availability: " . $e->getMessage();
                error_log("Toggle availability error: " . $e->getMessage());
            }
        }
    }
}

// Get provider's services with category info
$stmt = $db->prepare("
    SELECT ps.*, c.name as category_name, c.icon as category_icon
    FROM provider_services ps
    JOIN categories c ON ps.category_id = c.id
    WHERE ps.provider_id = ?
    ORDER BY ps.created_at DESC
");
$stmt->execute([$provider['id']]);
$services = $stmt->fetchAll();

// Get service statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_services,
        SUM(is_available) as available_services,
        AVG(price) as avg_price
    FROM provider_services 
    WHERE provider_id = ?
");
$stmt->execute([$provider['id']]);
$stats = $stmt->fetch();

// Get popular services (based on bookings)
$stmt = $db->prepare("
    SELECT ps.name, COUNT(b.id) as booking_count
    FROM provider_services ps
    LEFT JOIN bookings b ON ps.id = b.service_id
    WHERE ps.provider_id = ?
    GROUP BY ps.id
    ORDER BY booking_count DESC
    LIMIT 5
");
$stmt->execute([$provider['id']]);
$popular_services = $stmt->fetchAll();

// Get related services organized by category
$related_services_by_category = [];
foreach ($provider_categories as $category) {
    $stmt = $db->prepare("
        SELECT ps.id, ps.name, ps.price, ps.category_id
        FROM provider_services ps
        WHERE ps.provider_id = ? AND ps.category_id = ?
        ORDER BY ps.name ASC
    ");
    $stmt->execute([$provider['id'], $category['id']]);
    $category_services = $stmt->fetchAll();
    
    if (!empty($category_services)) {
        $related_services_by_category[] = [
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'category_icon' => $category['icon'],
            'services' => $category_services
        ];
    }
}

// Get service details view (service statistics)
$service_details = [];
if (!empty($services)) {
    foreach ($services as $service) {
        // Get service bookings count and revenue
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
            FROM bookings b
            WHERE b.service_id = ? AND b.provider_id = ?
        ");
        $stmt->execute([$service['id'], $provider['id']]);
        $booking_stats = $stmt->fetch();
        
        // Get service reviews/ratings
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                AVG(r.rating) as avg_rating
            FROM reviews r
            JOIN bookings b ON r.booking_id = b.id
            WHERE b.service_id = ? AND b.provider_id = ?
        ");
        $stmt->execute([$service['id'], $provider['id']]);
        $review_stats = $stmt->fetch();
        
        // Get service inquiries
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_inquiries
            FROM bookings b
            WHERE b.service_id = ? AND b.provider_id = ? AND b.status = 'inquiry'
        ");
        $stmt->execute([$service['id'], $provider['id']]);
        $inquiry_stats = $stmt->fetch();
        
        $service_details[$service['id']] = [
            'bookings' => $booking_stats,
            'reviews' => $review_stats,
            'inquiries' => $inquiry_stats
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('title', [], 'services_page'); ?> - <?php echo getPlatformName(); ?></title>
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
        }
        
        /* Maintenance Warning */
        .maintenance-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #856404;
            border: none;
            margin-bottom: 1rem;
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
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--secondary);
            margin: 0.5rem 0 0 0;
            font-weight: 500;
        }
        
        .stat-card.available {
            border-left-color: var(--success);
        }
        
        .stat-card.earnings {
            border-left-color: var(--warning);
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
        }
        
        .card h2 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.3rem;
        }
        
        /* Service Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .required {
            color: var(--danger);
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .form-text {
            color: var(--secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .service-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .service-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .service-card.unavailable {
            opacity: 0.7;
            background: #f8f9fa;
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .service-title {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            font-weight: 600;
        }
        
        .service-category {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .service-description {
            color: var(--dark);
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        
        .service-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .service-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
        }
        
        .service-duration {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .service-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Popular Services */
        .popular-services {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .popular-service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s;
        }
        
        .popular-service-item:hover {
            background: var(--light);
        }
        
        .popular-service-item:last-child {
            border-bottom: none;
        }
        
        .booking-count {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* Related Services by Category */
        .related-services-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .category-group {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--primary);
        }
        
        .category-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .category-header i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .category-header strong {
            flex: 1;
            color: var(--dark);
        }
        
        .category-header .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .category-services {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .related-service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 0.8rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .related-service-item:hover {
            border-color: var(--primary);
            background: #f0f8ff;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
        }
        
        .related-service-item .service-name {
            color: var(--dark);
            font-weight: 500;
            line-height: 1.3;
            max-width: 65%;
            word-break: break-word;
        }
        
        .related-service-item .service-price-tag {
            color: var(--success);
            font-weight: 600;
            white-space: nowrap;
            margin-left: 0.5rem;
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
        
        .empty-state h4 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
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
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        /* Service Card Hover Animation */
        .service-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
        
        /* Professional Action Buttons */
        .service-actions {
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .service-actions .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            transition: all 0.2s;
        }
        
        .service-actions .btn:hover {
            transform: scale(1.05);
        }
        
        /* Statistics Grid Animation */
        .service-card {
            position: relative;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
            border-radius: 12px 12px 0 0;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .service-card:hover::before {
            opacity: 1;
        }
        
        /* Quick Stats */
        .service-card [style*="background: #f8f9fa"] {
            transition: all 0.3s;
        }
        
        .service-card:hover [style*="background: #f8f9fa"] {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
        }
        
        /* Modal Enhancements */
        .modal-dialog {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Add Service Button */
        .btn-success {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
            transition: all 0.3s;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25, 135, 84, 0.4);
        }
        
        /* Service Card Stats Grid */
        .service-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            transform: scale(1.05);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <h1><i class="fas fa-concierge-bell"></i> <?php echo __('page_header', [], 'services_page'); ?></h1>
                    <p><?php echo __('page_description', [], 'services_page'); ?></p>
                </div>
                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="fas fa-plus-circle me-2"></i> <?php echo __('add_service_form.add_button', [], 'services_page'); ?>
                </button>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_services'] ?? 0; ?></h3>
                <p><?php echo __('stats.total_services', [], 'services_page'); ?></p>
            </div>
            <div class="stat-card available">
                <h3><?php echo $stats['available_services'] ?? 0; ?></h3>
                <p><?php echo __('stats.available_services', [], 'services_page'); ?></p>
            </div>
            <div class="stat-card earnings">
                <h3>RWF <?php echo number_format($stats['avg_price'] ?? 0, 0); ?></h3>
                <p><?php echo __('stats.average_price', [], 'services_page'); ?></p>
            </div>
            <div class="stat-card" style="border-left-color: #0dcaf0;">
                <h3><?php 
                    $total_bookings = 0;
                    foreach ($service_details as $details) {
                        if ($details['bookings']) {
                            $total_bookings += $details['bookings']['total_bookings'] ?? 0;
                        }
                    }
                    echo $total_bookings;
                ?></h3>
                <p><i class="fas fa-calendar-check me-1"></i> Total Bookings</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Services List -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <h2 style="margin: 0;"><i class="fas fa-list"></i> <?php echo __('services_list.service_name', [], 'services_page'); ?> (<?php echo count($services); ?>)</h2>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="serviceSearch" class="form-control" placeholder="Search services..." style="max-width: 250px;">
                            <select id="serviceSort" class="form-select" style="max-width: 150px;">
                                <option value="default">Sort By</option>
                                <option value="name">Name (A-Z)</option>
                                <option value="price-low">Price (Low-High)</option>
                                <option value="price-high">Price (High-Low)</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($services)): ?>
                        <div class="empty-state">
                            <i class="fas fa-concierge-bell"></i>
                            <h4><?php echo __('services_list.empty_title', [], 'services_page'); ?></h4>
                            <p><?php echo __('services_list.empty_message', [], 'services_page'); ?></p>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                                <i class="fas fa-plus-circle me-2"></i> Create Your First Service
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="services-grid">
                            <?php foreach ($services as $service): ?>
                                <div class="service-card <?php echo !$service['is_available'] ? 'unavailable' : ''; ?>">
                                    <div class="service-header">
                                        <h3 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <?php
                                                $serviceStatusKey = $service['service_status'] ?? 'draft';
                                                $serviceStatusLabels = [
                                                    'published' => ['class' => 'success', 'icon' => 'check-circle', 'label' => 'Published'],
                                                    'paused' => ['class' => 'warning', 'icon' => 'pause', 'label' => 'Paused'],
                                                    'draft' => ['class' => 'secondary', 'icon' => 'pencil-alt', 'label' => 'Draft'],
                                                ];
                                                $statusInfo = $serviceStatusLabels[$serviceStatusKey] ?? $serviceStatusLabels['draft'];
                                            ?>
                                            <span class="status-badge status-<?php echo $statusInfo['class']; ?>">
                                                <i class="fas fa-<?php echo $statusInfo['icon']; ?> me-1"></i>
                                                <?php echo htmlspecialchars($statusInfo['label']); ?>
                                            </span>
                                            <?php if ($service['negotiable']): ?>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-handshake me-1"></i> Negotiable
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="service-category">
                                        <i class="fas <?php echo $service['category_icon']; ?>"></i>
                                        <?php echo htmlspecialchars($service['category_name']); ?>
                                    </div>
                                    
                                    <div class="service-description">
                                        <?php echo htmlspecialchars($service['description']); ?>
                                    </div>
                                    
                                    <!-- Service Statistics -->
                                    <?php $stats = $service_details[$service['id']] ?? null; ?>
                                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; text-align: center;">
                                        <div>
                                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">
                                                <?php echo $stats && $stats['bookings'] ? $stats['bookings']['total_bookings'] ?? 0 : 0; ?>
                                            </div>
                                            <small style="color: var(--secondary); font-weight: 600;">Bookings</small>
                                        </div>
                                        <div>
                                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--success);">
                                                <?php echo $stats && $stats['reviews'] ? round($stats['reviews']['avg_rating'] ?? 0, 1) : '0'; ?>★
                                            </div>
                                            <small style="color: var(--secondary); font-weight: 600;">Rating</small>
                                        </div>
                                        <div>
                                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--warning);">
                                                <?php echo $stats && $stats['inquiries'] ? $stats['inquiries']['total_inquiries'] ?? 0 : 0; ?>
                                            </div>
                                            <small style="color: var(--secondary); font-weight: 600;">Inquiries</small>
                                        </div>
                                    </div>
                                    
                                    <div class="service-meta">
                                        <div>
                                            <span class="service-price">RWF <?php echo number_format($service['price']); ?></span>
                                            <span class="service-duration"><?php echo intval($service['duration']); ?> <?php echo __('services_list.minutes', [], 'services_page'); ?></span>
                                        </div>
                                        <div>
                                            <?php
                                                $labels = [
                                                    'fixed_price' => __('add_service_form.payment_fixed_price', [], 'services_page'),
                                                    'hourly_rate' => __('add_service_form.payment_per_hour', [], 'services_page'),
                                                    'per_job_estimate' => __('add_service_form.payment_per_job_estimate', [], 'services_page'),
                                                    'per_day' => __('add_service_form.payment_per_day', [], 'services_page'),
                                                    'base_price' => __('add_service_form.payment_base_price', [], 'services_page'),
                                                    'per_service' => __('add_service_form.payment_per_service', [], 'services_page'),
                                                ];
                                            ?>
                                            <span class="badge bg-secondary"><?php echo $labels[$service['payment_type']] ?? __('add_service_form.payment_per_service', [], 'services_page'); ?></span>
                                        </div>
                                    </div>

                                    <?php
                                        $extraItems = !empty($service['optional_extras']) ? json_decode($service['optional_extras'], true) : [];
                                    ?>
                                    <?php if (!empty($extraItems)): ?>
                                        <div class="mb-3">
                                            <span class="badge bg-warning text-dark">Optional extras: <?php echo count($extraItems); ?></span>
                                            <small class="text-muted ms-2">
                                                <?php echo htmlspecialchars($extraItems[0]['label'] . ' (+RWF ' . number_format($extraItems[0]['price']) . ')'); ?><?php if (count($extraItems) > 1) echo ' + ' . (count($extraItems) - 1) . ' more'; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="service-actions">
                                        <button type="button" class="btn btn-info btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#serviceDetailsModal"
                                                data-service-id="<?php echo $service['id']; ?>"
                                                data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                data-service-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                data-service-price="<?php echo $service['price']; ?>"
                                                data-service-duration="<?php echo $service['duration']; ?>"
                                                data-service-category="<?php echo htmlspecialchars($service['category_name']); ?>"
                                                data-service-optional-extras="<?php echo htmlspecialchars($service['optional_extras'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#editServiceModal"
                                                data-service-id="<?php echo $service['id']; ?>"
                                                data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                data-service-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                data-service-price="<?php echo $service['price']; ?>"
                                                data-service-duration="<?php echo $service['duration']; ?>"
                                                data-service-available="<?php echo $service['is_available']; ?>"
                                                data-service-status="<?php echo htmlspecialchars($service['service_status'] ?? 'draft'); ?>"
                                                data-service-payment-type="<?php echo htmlspecialchars($service['payment_type']); ?>"
                                                data-service-booking-mode="<?php echo htmlspecialchars($service['booking_mode'] ?? 'request_approval'); ?>"
                                                data-service-availability-days="<?php echo htmlspecialchars($service['availability_days'] ?? ''); ?>"
                                                data-service-time-slots="<?php echo htmlspecialchars($service['time_slots'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-service-optional-extras="<?php echo htmlspecialchars($service['optional_extras'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-edit"></i> <?php echo __('services_list.action_edit', [], 'services_page'); ?>
                                        </button>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <?php $statusToggleLabel = ($service['service_status'] ?? 'draft') === 'published' ? 'Pause' : 'Publish'; ?>
                                            <button type="submit" name="toggle_availability" class="btn btn-<?php echo ($service['service_status'] ?? 'draft') === 'published' ? 'warning' : 'success'; ?> btn-sm">
                                                <i class="fas fa-<?php echo ($service['service_status'] ?? 'draft') === 'published' ? 'pause' : 'play'; ?>"></i> 
                                                <?php echo $statusToggleLabel; ?>
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#deleteServiceModal"
                                                data-service-id="<?php echo $service['id']; ?>"
                                                data-service-name="<?php echo htmlspecialchars($service['name']); ?>">
                                            <i class="fas fa-trash"></i> <?php echo __('services_list.action_delete', [], 'services_page'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Popular Services -->
                <div class="card">
                    <h2><i class="fas fa-chart-line"></i> <?php echo __('popular_services.title', [], 'services_page'); ?></h2>
                    <div class="popular-services">
                        <?php if (empty($popular_services)): ?>
                            <div class="empty-state" style="padding: 1rem;">
                                <i class="fas fa-chart-bar"></i>
                                <p><?php echo __('popular_services.no_bookings', [], 'services_page'); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($popular_services as $popular): ?>
                                <div class="popular-service-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($popular['name']); ?></strong>
                                        <div class="text-muted small"><?php echo $popular['booking_count']; ?> <?php echo __('popular_services.bookings', [], 'services_page'); ?></div>
                                    </div>
                                    <span class="booking-count"><?php echo $popular['booking_count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Related Services by Category -->
                <?php if (!empty($related_services_by_category)): ?>
                    <div class="card">
                        <h2><i class="fas fa-folder-open"></i> <?php echo __('related_services.title', [], 'services_page'); ?></h2>
                        <div class="related-services-list">
                            <?php foreach ($related_services_by_category as $category_group): ?>
                                <div class="category-group">
                                    <div class="category-header">
                                        <i class="fas <?php echo $category_group['category_icon']; ?>"></i>
                                        <strong><?php echo htmlspecialchars($category_group['category_name']); ?></strong>
                                        <span class="badge bg-primary"><?php echo count($category_group['services']); ?></span>
                                    </div>
                                    <div class="category-services">
                                        <?php foreach ($category_group['services'] as $service): ?>
                                            <div class="related-service-item">
                                                <div>
                                                    <small class="service-name"><?php echo htmlspecialchars($service['name']); ?></small>
                                                </div>
                                                <small class="service-price-tag">RWF <?php echo number_format($service['price']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Tips -->
                <div class="card">
                    <h2><i class="fas fa-lightbulb"></i> <?php echo 'Service Tips'; ?></h2>
                    <div class="tips-list">
                        <div class="popular-service-item">
                            <i class="fas fa-check text-success"></i>
                            <div class="ms-2">
                                <small><?php echo 'Use clear, descriptive service names'; ?></small>
                            </div>
                        </div>
                        <div class="popular-service-item">
                            <i class="fas fa-check text-success"></i>
                            <div class="ms-2">
                                <small><?php echo 'Set competitive but fair pricing'; ?></small>
                            </div>
                        </div>
                        <div class="popular-service-item">
                            <i class="fas fa-check text-success"></i>
                            <div class="ms-2">
                                <small><?php echo 'Be specific about what\'s included'; ?></small>
                            </div>
                        </div>
                        <div class="popular-service-item">
                            <i class="fas fa-check text-success"></i>
                            <div class="ms-2">
                                <small><?php echo 'Keep availability status updated'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editServiceForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo __('edit_service.title', [], 'services_page'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="editServiceId">
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('edit_service.name', [], 'services_page'); ?></label>
                            <input type="text" name="name" class="form-control" id="editServiceName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('edit_service.description', [], 'services_page'); ?></label>
                            <textarea name="description" class="form-control" id="editServiceDescription" required rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('edit_service.price', [], 'services_page'); ?> (RWF)</label>
                                    <input type="number" name="price" class="form-control" id="editServicePrice" required min="0" step="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('edit_service.duration', [], 'services_page'); ?></label>
                                    <input type="number" name="duration" class="form-control" id="editServiceDuration" required min="15" step="15">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Type</label>
                            <select name="payment_type" class="form-select" id="editServicePaymentType">
                                <option value="fixed_price"><?php echo __('add_service_form.payment_fixed_price', [], 'services_page'); ?></option>
                                <option value="hourly_rate"><?php echo __('add_service_form.payment_per_hour', [], 'services_page'); ?></option>
                                <option value="per_job_estimate"><?php echo __('add_service_form.payment_per_job_estimate', [], 'services_page'); ?></option>
                                <option value="per_day"><?php echo __('add_service_form.payment_per_day', [], 'services_page'); ?></option>
                                <option value="base_price"><?php echo __('add_service_form.payment_base_price', [], 'services_page'); ?></option>
                                <option value="per_service"><?php echo __('add_service_form.payment_per_service', [], 'services_page'); ?></option>
                            </select>
                            <div class="form-text">How you charge for this service</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Booking Mode</label>
                            <select name="booking_mode" class="form-select" id="editServiceBookingMode">
                                <option value="request_approval">Request Approval</option>
                                <option value="instant">Instant Booking</option>
                            </select>
                            <div class="form-text">Choose whether this service is confirmed instantly or requires provider approval.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Service Status</label>
                            <select name="service_status" class="form-select" id="editServiceStatus">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="paused">Paused</option>
                            </select>
                            <div class="form-text">Set whether the service is draft, published for booking, or paused.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Available Days</label>
                            <div class="row g-2">
                                <?php $weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; ?>
                                <?php foreach ($weekdays as $dayNumber => $dayLabel): ?>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input edit-availability-day" type="checkbox" name="availability_days[]" value="<?php echo $dayNumber; ?>" id="editAvailabilityDay<?php echo $dayNumber; ?>">
                                            <label class="form-check-label" for="editAvailabilityDay<?php echo $dayNumber; ?>"><?php echo $dayLabel; ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Select which days this service can be booked.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Available Time Slots</label>
                            <textarea name="time_slots" class="form-control" id="editTimeSlots" rows="3" placeholder="08:00-12:00\n14:00-18:00"></textarea>
                            <div class="form-text">Enter one time slot per line in HH:MM-HH:MM format. Leave blank to use the provider's default schedule.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __('add_service_form.optional_extras', [], 'services_page'); ?></label>
                            <textarea name="optional_extras" id="editOptionalExtras" class="form-control" rows="3" placeholder="<?php echo __('add_service_form.optional_extras_placeholder', [], 'services_page'); ?>"></textarea>
                            <div class="form-text"><?php echo __('add_service_form.optional_extras_help', [], 'services_page'); ?></div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="negotiable" id="editNegotiableCheck" value="1">
                                <label class="form-check-label" for="editNegotiableCheck">
                                    <strong><?php echo __('edit_service.negotiable', [], 'services_page'); ?></strong>
                                </label>
                            </div>
                            <div class="form-text"><?php echo 'Let clients offer prices within a range you set'; ?></div>
                        </div>

                        <div id="editNegotiableFields" style="display: none; background: #f0f8ff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <small class="text-muted mb-2 d-block"><i class="fas fa-info-circle"></i> <?php echo 'Set the price range clients can offer'; ?></small>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __('edit_service.min_price', [], 'services_page'); ?> (RWF)</label>
                                    <input type="number" name="min_price" class="form-control" id="editMinPrice"
                                           min="0" step="100" placeholder="4000">
                                    <div class="form-text"><?php echo 'Lowest clients can offer'; ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __('edit_service.max_price', [], 'services_page'); ?> (RWF)</label>
                                    <input type="number" name="max_price" class="form-control" id="editMaxPrice"
                                           min="0" step="100" placeholder="6000">
                                    <div class="form-text"><?php echo 'Highest clients can offer'; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check d-none">
                            <input class="form-check-input" type="checkbox" name="is_available" id="editServiceAvailable" value="1">
                            <label class="form-check-label" for="editServiceAvailable">
                                <?php echo __('edit_service.is_available', [], 'services_page'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('edit_service.cancel_button', [], 'services_page'); ?></button>
                        <button type="submit" name="update_service" class="btn btn-primary"><?php echo __('edit_service.save_button', [], 'services_page'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Service Modal -->
    <div class="modal fade" id="deleteServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteServiceForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo __('delete_service.title', [], 'services_page'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="deleteServiceId">
                        <p><?php echo __('delete_service.confirm_message', [], 'services_page'); ?></p>
                        <p class="text-danger"><small><?php echo 'This action cannot be undone. Any future bookings for this service will be affected.'; ?></small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('delete_service.cancel_button', [], 'services_page'); ?></button>
                        <button type="submit" name="delete_service" class="btn btn-danger"><?php echo __('delete_service.delete_button', [], 'services_page'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="serviceForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> <?php echo __('add_service_form.title', [], 'services_page'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('add_service_form.name', [], 'services_page'); ?> <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" required 
                                       value="<?php echo $_POST['name'] ?? ''; ?>" 
                                       placeholder="e.g., Electrical Repair, Plumbing Service">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __('add_service_form.category', [], 'services_page'); ?> <span class="required">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value=""><?php echo __('add_service_form.category', [], 'services_page'); ?></option>
                                    <?php foreach ($provider_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                            <i class="fas <?php echo $category['icon']; ?>"></i> 
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($provider_categories)): ?>
                                    <div class="form-text text-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <?php echo 'No categories assigned. Please update your profile first.'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __('add_service_form.price', [], 'services_page'); ?> (RWF) <span class="required">*</span></label>
                                <input type="number" name="price" class="form-control" required 
                                       value="<?php echo $_POST['price'] ?? ''; ?>" 
                                       min="0" step="100" placeholder="5000" id="basePrice">
                                <div class="form-text"><?php echo 'Standard price for this service'; ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __('add_service_form.duration', [], 'services_page'); ?> (minutes) <span class="required">*</span></label>
                                <input type="number" name="duration" class="form-control" required 
                                       value="<?php echo $_POST['duration'] ?? '60'; ?>" 
                                       min="15" step="15" placeholder="60">
                                <div class="form-text"><?php echo 'Estimated time to complete this service'; ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __('add_service_form.payment_type', [], 'services_page'); ?></label>
                                <select name="payment_type" class="form-select">
                                    <option value="fixed_price"><?php echo __('add_service_form.payment_fixed_price', [], 'services_page'); ?></option>
                                    <option value="hourly_rate"><?php echo __('add_service_form.payment_per_hour', [], 'services_page'); ?></option>
                                    <option value="per_job_estimate"><?php echo __('add_service_form.payment_per_job_estimate', [], 'services_page'); ?></option>
                                    <option value="per_day"><?php echo __('add_service_form.payment_per_day', [], 'services_page'); ?></option>
                                    <option value="base_price"><?php echo __('add_service_form.payment_base_price', [], 'services_page'); ?></option>
                                    <option value="per_service"><?php echo __('add_service_form.payment_per_service', [], 'services_page'); ?></option>
                                </select>
                                <div class="form-text"><?php echo 'How you charge for this service'; ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Booking Mode</label>
                                <select name="booking_mode" class="form-select">
                                    <option value="request_approval" <?php echo (($_POST['booking_mode'] ?? '') === 'request_approval') ? 'selected' : ''; ?>>Request Approval</option>
                                    <option value="instant" <?php echo (($_POST['booking_mode'] ?? '') === 'instant') ? 'selected' : ''; ?>>Instant Booking</option>
                                </select>
                                <div class="form-text">Choose whether this service is confirmed instantly or requires provider approval.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Service Status</label>
                                <select name="service_status" class="form-select">
                                    <option value="draft" <?php echo (($_POST['service_status'] ?? '') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo (($_POST['service_status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                                    <option value="paused" <?php echo (($_POST['service_status'] ?? '') === 'paused') ? 'selected' : ''; ?>>Paused</option>
                                </select>
                                <div class="form-text">Set whether the service is draft, published for booking, or paused.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Available Days</label>
                                <div class="row g-2">
                                    <?php $weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; ?>
                                    <?php foreach ($weekdays as $dayNumber => $dayLabel): ?>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="availability_days[]" value="<?php echo $dayNumber; ?>" id="availabilityDay<?php echo $dayNumber; ?>"
                                                    <?php echo in_array($dayNumber, $_POST['availability_days'] ?? [1,2,3,4,5]) ? 'checked' : ''; ?> >
                                                <label class="form-check-label" for="availabilityDay<?php echo $dayNumber; ?>"><?php echo $dayLabel; ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select which days this service can be booked.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Available Time Slots</label>
                                <textarea name="time_slots" class="form-control" rows="3" placeholder="08:00-12:00\n14:00-18:00"><?php echo htmlspecialchars($_POST['time_slots'] ?? ''); ?></textarea>
                                <div class="form-text">Enter one time slot per line in HH:MM-HH:MM format. Leave blank to use the provider's default schedule.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __('add_service_form.optional_extras', [], 'services_page'); ?></label>
                            <textarea name="optional_extras" id="optionalExtras" class="form-control" rows="3" placeholder="<?php echo __('add_service_form.optional_extras_placeholder', [], 'services_page'); ?>"><?php echo $_POST['optional_extras'] ?? ''; ?></textarea>
                            <div class="form-text"><?php echo __('add_service_form.optional_extras_help', [], 'services_page'); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('add_service_form.description', [], 'services_page'); ?> <span class="required">*</span></label>
                            <textarea name="description" class="form-control" required rows="4" 
                                      placeholder="Describe what this service includes, your expertise, materials used, etc."><?php echo $_POST['description'] ?? ''; ?></textarea>
                            <div class="form-text">Be specific about what clients can expect</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="negotiable" id="negotiableCheck" value="1"
                                       <?php echo isset($_POST['negotiable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="negotiableCheck">
                                    <strong><?php echo __('add_service_form.negotiable', [], 'services_page'); ?></strong>
                                </label>
                            </div>
                            <div class="form-text"><?php echo 'Let clients offer prices within a range you set'; ?></div>
                        </div>

                        <div id="negotiableFields" style="display: <?php echo isset($_POST['negotiable']) ? 'block' : 'none'; ?>; background: #f0f8ff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <small class="text-muted mb-2 d-block"><i class="fas fa-info-circle"></i> <?php echo 'Set the price range clients can offer'; ?></small>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __('add_service_form.min_price', [], 'services_page'); ?> (RWF)</label>
                                    <input type="number" name="min_price" class="form-control" 
                                           value="<?php echo $_POST['min_price'] ?? ''; ?>" 
                                           min="0" step="100" placeholder="4000" id="minPrice">
                                    <div class="form-text"><?php echo 'Lowest clients can offer'; ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __('add_service_form.max_price', [], 'services_page'); ?> (RWF)</label>
                                    <input type="number" name="max_price" class="form-control" 
                                           value="<?php echo $_POST['max_price'] ?? ''; ?>" 
                                           min="0" step="100" placeholder="6000" id="maxPrice">
                                    <div class="form-text"><?php echo 'Highest clients can offer'; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3 d-none">
                            <input class="form-check-input" type="checkbox" name="is_available" id="is_available" value="1" 
                                   <?php echo isset($_POST['is_available']) ? 'checked' : 'checked'; ?>>
                            <label class="form-check-label" for="is_available">
                                <?php echo __('add_service_form.is_available', [], 'services_page'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo 'Cancel'; ?></button>
                        <button type="submit" name="add_service" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i> <?php echo __('add_service_form.add_button', [], 'services_page'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Service Details Modal -->
    <div class="modal fade" id="serviceDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), #0a58ca); color: white;">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i> Service Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-1">Service Name</h6>
                            <h5 id="detailServiceName" class="mb-3"></h5>
                            
                            <h6 class="text-muted mb-1">Category</h6>
                            <p id="detailServiceCategory" class="mb-3"></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-1">Price</h6>
                            <h5 id="detailServicePrice" class="mb-3 text-success"></h5>
                            
                            <h6 class="text-muted mb-1">Duration</h6>
                            <p id="detailServiceDuration" class="mb-3"></p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Description</h6>
                        <p id="detailServiceDescription" style="line-height: 1.6; color: #555;"></p>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Optional Extras</h6>
                        <ul id="detailServiceExtras" class="list-group mb-0"></ul>
                        <p id="detailServiceExtrasNone" class="text-muted mb-0">No optional extras listed.</p>
                    </div>

                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px;">
                        <h6 class="mb-3">Service Performance</h6>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
                            <div style="padding: 1rem; background: white; border-radius: 8px; border-left: 3px solid var(--primary);">
                                <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary);" id="detailBookings">0</div>
                                <small style="color: var(--secondary); font-weight: 600;">Total Bookings</small>
                            </div>
                            <div style="padding: 1rem; background: white; border-radius: 8px; border-left: 3px solid var(--success);">
                                <div style="font-size: 1.8rem; font-weight: 700; color: var(--success);" id="detailRating">0★</div>
                                <small style="color: var(--secondary); font-weight: 600;">Avg Rating</small>
                            </div>
                            <div style="padding: 1rem; background: white; border-radius: 8px; border-left: 3px solid var(--warning);">
                                <div style="font-size: 1.8rem; font-weight: 700; color: var(--warning);" id="detailInquiries">0</div>
                                <small style="color: var(--secondary); font-weight: 600;">Inquiries</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Service Details Modal
        const serviceDetailsModal = document.getElementById('serviceDetailsModal');
        if (serviceDetailsModal) {
            serviceDetailsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const serviceId = button.getAttribute('data-service-id');
                const serviceName = button.getAttribute('data-service-name');
                const serviceDescription = button.getAttribute('data-service-description');
                const servicePrice = button.getAttribute('data-service-price');
                const serviceDuration = button.getAttribute('data-service-duration');
                const serviceCategory = button.getAttribute('data-service-category');
                const serviceExtras = button.getAttribute('data-service-optional-extras');
                
                document.getElementById('detailServiceName').textContent = serviceName;
                document.getElementById('detailServiceCategory').textContent = serviceCategory;
                document.getElementById('detailServicePrice').textContent = 'RWF ' + parseInt(servicePrice).toLocaleString();
                document.getElementById('detailServiceDuration').textContent = serviceDuration + ' minutes';
                document.getElementById('detailServiceDescription').textContent = serviceDescription;

                const extrasList = document.getElementById('detailServiceExtras');
                const extrasNone = document.getElementById('detailServiceExtrasNone');
                extrasList.innerHTML = '';
                if (serviceExtras) {
                    try {
                        const extras = JSON.parse(serviceExtras);
                        if (Array.isArray(extras) && extras.length) {
                            extras.forEach(extra => {
                                const li = document.createElement('li');
                                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                                li.textContent = extra.label;

                                const badge = document.createElement('span');
                                badge.className = 'badge bg-secondary';
                                badge.textContent = '+RWF ' + parseFloat(extra.price).toLocaleString();

                                li.appendChild(badge);
                                extrasList.appendChild(li);
                            });
                        }
                    } catch (error) {
                        console.warn('Unable to parse service extras', error);
                    }
                }
                extrasNone.style.display = extrasList.children.length ? 'none' : 'block';
                
                // You can fetch stats via AJAX here if needed
            });
        }
        
        // Negotiable toggle for Add Form
        const negotiableCheck = document.getElementById('negotiableCheck');
        const negotiableFields = document.getElementById('negotiableFields');
        
        if (negotiableCheck) {
            negotiableCheck.addEventListener('change', function() {
                negotiableFields.style.display = this.checked ? 'block' : 'none';
            });
        }

        // Negotiable toggle for Edit Modal
        const editNegotiableCheck = document.getElementById('editNegotiableCheck');
        const editNegotiableFields = document.getElementById('editNegotiableFields');
        
        if (editNegotiableCheck) {
            editNegotiableCheck.addEventListener('change', function() {
                editNegotiableFields.style.display = this.checked ? 'block' : 'none';
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
        
        // Edit Service Modal
        const editServiceModal = document.getElementById('editServiceModal');
        if (editServiceModal) {
            editServiceModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const serviceId = button.getAttribute('data-service-id');
                const serviceName = button.getAttribute('data-service-name');
                const serviceDescription = button.getAttribute('data-service-description');
                const servicePrice = button.getAttribute('data-service-price');
                const serviceDuration = button.getAttribute('data-service-duration');
                const serviceAvailable = button.getAttribute('data-service-available');
                const serviceBookingMode = button.getAttribute('data-service-booking-mode');
                const serviceAvailabilityDays = button.getAttribute('data-service-availability-days');
                const serviceTimeSlots = button.getAttribute('data-service-time-slots');
                
                document.getElementById('editServiceId').value = serviceId;
                document.getElementById('editServiceName').value = serviceName;
                document.getElementById('editServiceDescription').value = serviceDescription;
                document.getElementById('editServicePrice').value = servicePrice;
                document.getElementById('editServiceDuration').value = serviceDuration;
                document.getElementById('editServiceAvailable').checked = serviceAvailable === '1';
                
                // Set payment type
                const paymentType = button.getAttribute('data-service-payment-type');
                const paymentTypeSelect = document.getElementById('editServicePaymentType');
                paymentTypeSelect.value = paymentType;

                const bookingModeSelect = document.getElementById('editServiceBookingMode');
                if (bookingModeSelect) {
                    bookingModeSelect.value = serviceBookingMode || 'request_approval';
                }

                const serviceStatus = button.getAttribute('data-service-status');
                const editServiceStatus = document.getElementById('editServiceStatus');
                if (editServiceStatus) {
                    editServiceStatus.value = serviceStatus || 'draft';
                }

                const availabilityDays = serviceAvailabilityDays ? serviceAvailabilityDays.split(',').map(day => parseInt(day, 10)) : [1,2,3,4,5];
                document.querySelectorAll('.edit-availability-day').forEach(checkbox => {
                    checkbox.checked = availabilityDays.includes(parseInt(checkbox.value, 10));
                });

                const editTimeSlots = document.getElementById('editTimeSlots');
                if (editTimeSlots) {
                    editTimeSlots.value = '';
                    if (serviceTimeSlots) {
                        try {
                            const slots = JSON.parse(serviceTimeSlots);
                            if (Array.isArray(slots)) {
                                editTimeSlots.value = slots.map(slot => `${slot.start}-${slot.end}`).join('\n');
                            }
                        } catch (error) {
                            editTimeSlots.value = serviceTimeSlots;
                        }
                    }
                }

                const serviceExtrasRaw = button.getAttribute('data-service-optional-extras');
                const editOptionalExtras = document.getElementById('editOptionalExtras');
                if (editOptionalExtras) {
                    editOptionalExtras.value = '';
                    if (serviceExtrasRaw) {
                        try {
                            const extras = JSON.parse(serviceExtrasRaw);
                            if (Array.isArray(extras)) {
                                editOptionalExtras.value = extras.map(extra => `${extra.label} (+${parseFloat(extra.price).toFixed(0)})`).join('\n');
                            }
                        } catch (error) {
                            console.warn('Unable to parse optional extras for edit modal', error);
                        }
                    }
                }
            });
        }
        
        // Delete Service Modal
        const deleteServiceModal = document.getElementById('deleteServiceModal');
        if (deleteServiceModal) {
            deleteServiceModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const serviceId = button.getAttribute('data-service-id');
                const serviceName = button.getAttribute('data-service-name');
                
                document.getElementById('deleteServiceId').value = serviceId;
                document.getElementById('deleteServiceName').textContent = serviceName;
            });
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Form validation
        document.getElementById('serviceForm')?.addEventListener('submit', function(e) {
            const price = parseFloat(this.querySelector('input[name="price"]').value);
            const duration = parseInt(this.querySelector('input[name="duration"]').value);
            
            if (price < 0) {
                e.preventDefault();
                alert('Price cannot be negative');
                return false;
            }
            
            if (duration < 15) {
                e.preventDefault();
                alert('Duration must be at least 15 minutes');
                return false;
            }
        });
        
        // Confirm delete action
        document.getElementById('deleteServiceForm')?.addEventListener('submit', function(e) {
            if (!confirm('Are you absolutely sure you want to delete this service? This cannot be undone.')) {
                e.preventDefault();
            }
        });
        
        // Price formatting
        document.querySelectorAll('input[name="price"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.value = parseFloat(this.value).toFixed(0);
                }
            });
        });
        
        // Add Service Analytics
        document.addEventListener('DOMContentLoaded', function() {
            // Update service statistics dynamically
            const serviceCards = document.querySelectorAll('.service-card');
            serviceCards.forEach((card, index) => {
                // Add staggered animation
                card.style.animation = `slideIn 0.3s ease-out ${index * 0.1}s both`;
            });
            
            // Search functionality
            const serviceSearch = document.getElementById('serviceSearch');
            if (serviceSearch) {
                serviceSearch.addEventListener('input', function() {
                    filterServices(this.value);
                });
            }
            
            // Sort functionality
            const serviceSort = document.getElementById('serviceSort');
            if (serviceSort) {
                serviceSort.addEventListener('change', function() {
                    if (this.value !== 'default') {
                        sortServices(this.value);
                    }
                });
            }
        });
        
        // Search/Filter Services (Optional feature)
        function filterServices(searchTerm) {
            const cards = document.querySelectorAll('.service-card');
            cards.forEach(card => {
                const title = card.querySelector('.service-title')?.textContent || '';
                const description = card.querySelector('.service-description')?.textContent || '';
                const category = card.querySelector('.service-category')?.textContent || '';
                
                const matches = title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                                description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                                category.toLowerCase().includes(searchTerm.toLowerCase());
                
                card.parentElement.style.display = matches ? 'block' : 'none';
            });
        }
        
        // Sort Services
        function sortServices(sortBy) {
            const servicesGrid = document.querySelector('.services-grid');
            const cards = Array.from(document.querySelectorAll('.service-card'));
            
            cards.sort((a, b) => {
                let aVal, bVal;
                
                switch(sortBy) {
                    case 'name':
                        aVal = a.querySelector('.service-title')?.textContent || '';
                        bVal = b.querySelector('.service-title')?.textContent || '';
                        return aVal.localeCompare(bVal);
                    case 'price-low':
                        aVal = parseInt(a.querySelector('.service-price')?.textContent.replace(/\D/g, '') || 0);
                        bVal = parseInt(b.querySelector('.service-price')?.textContent.replace(/\D/g, '') || 0);
                        return aVal - bVal;
                    case 'price-high':
                        aVal = parseInt(a.querySelector('.service-price')?.textContent.replace(/\D/g, '') || 0);
                        bVal = parseInt(b.querySelector('.service-price')?.textContent.replace(/\D/g, '') || 0);
                        return bVal - aVal;
                    default:
                        return 0;
                }
            });
            
            servicesGrid.innerHTML = '';
            cards.forEach(card => servicesGrid.appendChild(card));
        }
    </script>
</body>
</html>