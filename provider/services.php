<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/service_negotiation.php';
require_once '../includes/language.php';
require_once '../includes/service_ai_insights.php';
require_once '../includes/subscription_access.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Image upload configuration
$upload_dir = '../uploads/service_images/';
$max_file_size = 4 * 1024 * 1024; // 4MB
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Function to handle image upload
function handleImageUpload($file_input_name, $existing_image = null) {
    global $upload_dir, $max_file_size, $allowed_types;

    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing_image; // Keep existing image if no new upload
    }

    $file = $_FILES[$file_input_name];

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and WebP images are allowed.');
    }

    // Validate file size
    if ($file['size'] > $max_file_size) {
        throw new Exception('File size too large. Maximum size is 4MB.');
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('service_', true) . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload image.');
    }

    // Delete old image if exists
    if ($existing_image && file_exists($upload_dir . $existing_image)) {
        unlink($upload_dir . $existing_image);
    }

    return $filename;
}

// Function to handle multiple image uploads
function handleMultipleImageUploads($file_input_name, $existing_images = []) {
    global $upload_dir, $max_file_size, $allowed_types;

    $uploaded_files = [];

    if (!isset($_FILES[$file_input_name])) {
        return $existing_images;
    }

    $files = $_FILES[$file_input_name];

    // Handle single file upload (when only one file is uploaded)
    if (!is_array($files['name'])) {
        if ($files['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $filename = handleImageUpload($file_input_name, null);
                $uploaded_files[] = $filename;
            } catch (Exception $e) {
                throw $e;
            }
        }
    } else {
        // Handle multiple file uploads
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_NO_FILE) continue;

            // Create a temporary single file array for processing
            $single_file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];

            // Temporarily set $_FILES for the single file
            $_FILES['temp_single_file'] = $single_file;

            try {
                $filename = handleImageUpload('temp_single_file', null);
                $uploaded_files[] = $filename;
            } catch (Exception $e) {
                // Clean up any uploaded files on error
                foreach ($uploaded_files as $uploaded_file) {
                    if (file_exists($upload_dir . $uploaded_file)) {
                        unlink($upload_dir . $uploaded_file);
                    }
                }
                throw $e;
            }
        }
    }

    // Delete old images that are no longer needed
    if (!empty($existing_images)) {
        foreach ($existing_images as $old_image) {
            if (!in_array($old_image, $uploaded_files) && file_exists($upload_dir . $old_image)) {
                unlink($upload_dir . $old_image);
            }
        }
    }

    return array_merge($existing_images, $uploaded_files);
}

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

// Check if AI features are enabled for this provider
$provider_ai_enabled = isProviderAIEnabled($provider['id']);

// Check if specific AI sub-features are enabled
$ai_pricing_suggestions_enabled = getProviderSetting($provider['id'], 'ai_features_ai_pricing_suggestions') == '1';
$ai_description_improvement_enabled = getProviderSetting($provider['id'], 'ai_features_ai_description_improvement') == '1';

// Get provider's categories (from provider_categories mapping)
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.name, c.icon 
    FROM categories c
    JOIN provider_categories pc ON c.id = pc.category_id
    WHERE pc.provider_id = ?
");
$stmt->execute([$provider['id']]);
$provider_categories = $stmt->fetchAll();

// Check subscription limits for service creation
$provider_id = (int)$_SESSION['user_id'];
$can_add_service = canCreateService($provider_id);
$service_limit = getServiceLimit($provider_id);
$current_service_count = getProviderServiceCount($provider_id);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new service
    if (isset($_POST['add_service'])) {
        // Check subscription limit first
        if (!$can_add_service) {
            $errors[] = "Service limit reached. Upgrade your plan to create more services.";
        } else {
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
                // Handle main image upload
                $service_image = '';
                if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $service_image = handleImageUpload('service_image');
                }

                // Handle additional images upload
                $service_images = [];
                if (isset($_FILES['service_images'])) {
                    $service_images = handleMultipleImageUploads('service_images');
                }

                $stmt = $db->prepare("INSERT INTO provider_services
                    (provider_id, category_id, name, description, price, duration, is_available, payment_type, negotiable, min_price, max_price, base_price, optional_extras, availability_days, time_slots, booking_mode, service_status, service_image, service_images, image_alt_text, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $service_images_json = !empty($service_images) ? json_encode($service_images) : null;
                $image_alt_text = sanitize($_POST['image_alt_text'] ?? '');

                $stmt->execute([
                    $provider['id'], $category_id, $name, $description, $price, $duration, $is_available, $payment_type, $negotiable, $min_price, $max_price, $price, $optional_extras_json, $availability_days_str, $time_slots_json, $booking_mode, $service_status, $service_image, $service_images_json, $image_alt_text
                ]);
                $success = "Service added successfully";
            } catch (Exception $e) {
                $errors[] = "Failed to add service: " . $e->getMessage();
                error_log("Add service error: ".$e->getMessage());
            }
        } // End if (empty($errors))
    } // End else (subscription check)
    } // End add_service
    
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
                // Get existing images
                $stmt = $db->prepare("SELECT service_image, service_images FROM provider_services WHERE id = ? AND provider_id = ?");
                $stmt->execute([$service_id, $provider['id']]);
                $existing = $stmt->fetch();

                $existing_main_image = $existing['service_image'] ?? '';
                $existing_images = !empty($existing['service_images']) ? json_decode($existing['service_images'], true) : [];

                // Handle main image upload
                $service_image = $existing_main_image;
                if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $service_image = handleImageUpload('service_image', $existing_main_image);
                }

                // Handle additional images upload
                $service_images = $existing_images;
                if (isset($_FILES['service_images'])) {
                    $service_images = handleMultipleImageUploads('service_images', $existing_images);
                }

                $stmt = $db->prepare("UPDATE provider_services
                    SET name = ?, description = ?, price = ?, duration = ?, is_available = ?, payment_type = ?, negotiable = ?, min_price = ?, max_price = ?, base_price = ?, optional_extras = ?, availability_days = ?, time_slots = ?, booking_mode = ?, service_status = ?, service_image = ?, service_images = ?, image_alt_text = ?, updated_at = NOW()
                    WHERE id = ? AND provider_id = ?");

                $service_images_json = !empty($service_images) ? json_encode($service_images) : null;
                $image_alt_text = sanitize($_POST['image_alt_text'] ?? '');

                $stmt->execute([
                    $name, $description, $price, $duration, $is_available, $payment_type, $negotiable, $min_price, $max_price, $price, $optional_extras_json, $availability_days_str, $time_slots_json, $booking_mode, $service_status, $service_image, $service_images_json, $image_alt_text, $service_id, $provider['id']
                ]);
                $success = "Service updated successfully";
            } catch (Exception $e) {
                $errors[] = "Failed to update service: " . $e->getMessage();
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

// Initialize AI Insights (only if AI is enabled)
$ai_insights = null;
if ($provider_ai_enabled) {
    $ai_insights = new AIServiceInsights($db, $provider['id']);
}

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
$service_ai_data = [];
if (!empty($services)) {
    foreach ($services as $service) {
        // Calculate AI insights for this service (only if AI is enabled)
        $performance_score = null;
        $demand_indicator = null;
        $conversion_rate = null;
        $price_suggestion = null;
        if ($provider_ai_enabled && $ai_insights) {
            $performance_score = $ai_insights->calculatePerformanceScore($service['id']);
            $demand_indicator = $ai_insights->getDemandIndicator($service['id']);
            $conversion_rate = $ai_insights->getConversionRate($service['id']);
            // Only calculate price suggestion if the sub-toggle is enabled
            if ($ai_pricing_suggestions_enabled) {
                $price_suggestion = $ai_insights->suggestOptimalPrice($service['category_id'], $service['price']);
            }
        }
        
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

        // Prepare service data for AI tips generation (only if AI is enabled)
        if ($provider_ai_enabled && $ai_insights) {
            $service_with_stats = array_merge($service, [
                'total_reviews' => $review_stats['total_reviews'] ?? 0,
                'avg_rating' => $review_stats['avg_rating'] ?? 0,
                'completed_bookings' => $booking_stats['completed_bookings'] ?? 0
            ]);

            // Generate optimization tips
            $optimization_tips = $ai_insights->generateOptimizationTips($service_with_stats, $performance_score, $conversion_rate, $demand_indicator);

            // Store AI data for easy access in templates
            $service_ai_data[$service['id']] = [
                'performance_score' => $performance_score,
                'demand_indicator' => $demand_indicator,
                'conversion_rate' => $conversion_rate,
                'price_suggestion' => $price_suggestion,
                'optimization_tips' => $optimization_tips
            ];
        }
    }
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('title', [], 'services_page'); ?> - <?php echo getPlatformName(); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Dark Mode CSS -->
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --primary: #0d6efd;
            --primary-dark: #0a58ca;
            --primary-light: #eff6ff;
            --secondary: #6c757d;
            --success: #198754;
            --success-light: #f0fdf4;
            --danger: #dc3545;
            --danger-light: #fef2f2;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --info: #0891b2;
            --info-light: #ecfeff;
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --border: #e8eaf0;
            --border-subtle: #f0f2f7;
            --text-primary: #0f1117;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --sidebar-width: 260px;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --shadow-xs: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.07);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.09);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
            --transition: all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #1e3a8a;
            --secondary: #64748b;
            --success: #10b981;
            --success-light: #064e3b;
            --danger: #ef4444;
            --danger-light: #7f1d1d;
            --warning: #f59e0b;
            --warning-light: #78350f;
            --info: #06b6d4;
            --info-light: #164e63;
            --surface: #0f172a;
            --surface-2: #1e293b;
            --border: #334155;
            --border-subtle: #475569;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --shadow-xs: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.4);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.5);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.6);
        }

        body {
            background: var(--surface-2);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* ── SIDEBAR (unchanged from existing style) ── */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0; top: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar-header { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .sidebar-header h2 { margin: 0; font-weight: 700; font-size: 1.3rem; }
        .sidebar-header p { margin: 0.5rem 0 0 0; opacity: 0.8; font-size: 0.9rem; }
        .sidebar-menu { list-style: none; padding: 1rem 0; margin: 0; }
        .sidebar-menu li { margin: 0.2rem 0; }
        .sidebar-menu a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 0.8rem 1.5rem; display: flex; align-items: center; transition: all 0.3s; border-left: 3px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; border-left-color: white; }
        .sidebar-menu i { width: 25px; margin-right: 10px; font-size: 1.1rem; }

        /* ── MAIN CONTENT ── */
        .main-content { margin-left: var(--sidebar-width); padding: 1.75rem 2rem; min-height: 100vh; }

        /* ── MOBILE ── */
        .mobile-menu-toggle {
            display: none;
            position: fixed; top: 1rem; left: 1rem;
            z-index: 1100;
            background: var(--primary); color: white; border: none;
            border-radius: var(--radius-sm); width: 42px; height: 42px;
            align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-md);
        }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; }
        .overlay.active { display: block; }

        /* ── PAGE HEADER ── */
        .page-header {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
        }
        .page-header h1 { margin: 0; font-weight: 800; font-size: 1.5rem; letter-spacing: -0.4px; display: flex; align-items: center; gap: 0.5rem; }
        .page-header h1 i { color: var(--primary); }
        .page-header p { color: var(--text-muted); margin: 0.2rem 0 0; font-size: 0.82rem; }

        /* ── KPI STATS ROW ── */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            position: relative; overflow: hidden;
            transition: var(--transition);
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .kpi-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: var(--kpi-color, var(--primary));
        }
        .kpi-icon {
            width: 40px; height: 40px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; margin-bottom: 0.75rem;
            background: var(--kpi-bg, var(--primary-light));
            color: var(--kpi-color, var(--primary));
        }
        .kpi-value { font-size: 1.85rem; font-weight: 800; color: var(--text-primary); letter-spacing: -1px; line-height: 1; margin-bottom: 0.2rem; }
        .kpi-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-muted); }
        .kpi-trend { font-size: 0.72rem; font-weight: 600; margin-top: 0.4rem; display: flex; align-items: center; gap: 0.25rem; }
        .kpi-trend.up { color: var(--success); }
        .kpi-trend.neutral { color: var(--text-muted); }

        /* ── VIEW CONTROLS ── */
        .controls-bar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 1rem; flex-wrap: wrap;
            background: var(--surface); border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            border: 1px solid var(--border); box-shadow: var(--shadow-xs);
            margin-bottom: 1.5rem;
        }
        .controls-left { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; flex: 1; }
        .controls-right { display: flex; gap: 0.5rem; align-items: center; }

        .search-wrap { position: relative; min-width: 220px; }
        .search-wrap input {
            padding: 0.55rem 0.875rem 0.55rem 2.2rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.83rem; font-family: inherit;
            background: var(--surface-2);
            color: var(--text-primary);
            transition: var(--transition); width: 100%;
        }
        .search-wrap input:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(13,110,253,0.07); }
        .search-wrap .search-icon { position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; pointer-events: none; }

        .filter-select {
            padding: 0.55rem 0.875rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.83rem; font-family: inherit;
            background: var(--surface-2); color: var(--text-primary);
            transition: var(--transition); cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: var(--primary); }

        .view-toggle { display: flex; gap: 2px; background: var(--surface-2); border-radius: var(--radius-sm); padding: 3px; border: 1px solid var(--border); }
        .view-btn {
            padding: 0.38rem 0.6rem; border: none; border-radius: 6px;
            cursor: pointer; font-size: 0.8rem; color: var(--text-muted);
            background: transparent; transition: var(--transition);
        }
        .view-btn.active { background: var(--primary); color: white; }
        .view-btn:hover:not(.active) { background: var(--border); color: var(--text-primary); }

        /* ── SERVICE CARDS (Grid View) ── */
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px,1fr)); gap: 1.25rem; }
        .services-list-view .service-card { display: grid; grid-template-columns: 60px 1fr auto; gap: 1.25rem; align-items: center; padding: 1rem 1.25rem; border-radius: var(--radius-md); }

        .service-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
        }
        .service-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--primary); }
        .service-card.status-paused { opacity: 0.72; }
        .service-card.status-draft { border-style: dashed; }

        .sc-accent-bar { height: 4px; width: 100%; flex-shrink: 0; }
        .sc-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }

        .sc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; gap: 0.5rem; }
        .sc-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.2px; margin: 0 0 0.25rem; line-height: 1.3; }
        .sc-category { font-size: 0.75rem; color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 0.3rem; }
        .sc-badges { display: flex; gap: 0.35rem; flex-wrap: wrap; flex-shrink: 0; }

        .sc-description { font-size: 0.82rem; color: var(--text-secondary); line-height: 1.55; margin-bottom: 1rem; flex: 1;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* Mini stats inside card */
        .sc-mini-stats {
            display: grid; grid-template-columns: repeat(3,1fr);
            gap: 0.5rem; margin-bottom: 1rem;
            background: var(--surface-2); border-radius: var(--radius-sm);
            padding: 0.75rem; border: 1px solid var(--border-subtle);
        }
        .sc-stat { text-align: center; }
        .sc-stat-num { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; }
        .sc-stat-lbl { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: var(--text-muted); margin-top: 1px; }

        /* Rating stars */
        .mini-stars { color: #f59e0b; font-size: 0.7rem; letter-spacing: 1px; }

        .sc-footer { border-top: 1px solid var(--border-subtle); padding-top: 0.875rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
        .sc-price { font-size: 1.15rem; font-weight: 800; color: var(--success); font-variant-numeric: tabular-nums; }
        .sc-price-sub { font-size: 0.7rem; color: var(--text-muted); font-weight: 500; }
        .sc-duration { font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.3rem; }

        .sc-actions { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; margin-top: 0.875rem; }

        /* ── BADGES ── */
        .badge-pill {
            padding: 0.22rem 0.6rem;
            border-radius: 100px;
            font-size: 0.67rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            display: inline-flex; align-items: center; gap: 0.25rem;
            white-space: nowrap;
        }
        .badge-published { background: var(--success-light); color: var(--success); border: 1px solid #bbf7d0; }
        .badge-paused    { background: var(--warning-light); color: var(--warning); border: 1px solid #fde68a; }
        .badge-draft     { background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border); }
        .badge-neg       { background: var(--info-light); color: var(--info); border: 1px solid #a5f3fc; }
        .badge-instant   { background: #fdf4ff; color: #9333ea; border: 1px solid #e9d5ff; }

        /* ── ACTION BUTTONS ── */
        .btn-act {
            padding: 0.35rem 0.7rem;
            border-radius: var(--radius-sm);
            border: 1.5px solid transparent;
            font-size: 0.74rem; font-weight: 700; font-family: inherit;
            cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.3rem;
            text-decoration: none;
        }
        .btn-act-view    { background: var(--info-light); color: var(--info); border-color: #a5f3fc; }
        .btn-act-view:hover    { background: var(--info); color: white; }
        .btn-act-edit    { background: var(--primary-light); color: var(--primary); border-color: #bfdbfe; }
        .btn-act-edit:hover    { background: var(--primary); color: white; }
        .btn-act-publish { background: var(--success-light); color: var(--success); border-color: #bbf7d0; }
        .btn-act-publish:hover { background: var(--success); color: white; }
        .btn-act-pause   { background: var(--warning-light); color: var(--warning); border-color: #fde68a; }
        .btn-act-pause:hover   { background: var(--warning); color: white; }
        .btn-act-del     { background: var(--danger-light); color: var(--danger); border-color: #fecaca; }
        .btn-act-del:hover     { background: var(--danger); color: white; }

        /* ── RIGHT SIDEBAR PANELS ── */
        .side-panel {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            margin-bottom: 1.25rem;
            overflow: hidden;
        }
        .side-panel-header {
            padding: 1rem 1.25rem 0.875rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex; justify-content: space-between; align-items: center;
        }
        .side-panel-header h3 { margin: 0; font-size: 0.875rem; font-weight: 700; display: flex; align-items: center; gap: 0.45rem; }
        .side-panel-body { padding: 1rem 1.25rem; }

        /* Popular services list */
        .pop-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.6rem 0; border-bottom: 1px solid var(--border-subtle);
        }
        .pop-item:last-child { border-bottom: none; }
        .pop-rank {
            width: 24px; height: 24px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 800; flex-shrink: 0;
            background: var(--primary-light); color: var(--primary);
        }
        .pop-rank.r1 { background: #fef3c7; color: #d97706; }
        .pop-rank.r2 { background: #f1f5f9; color: #64748b; }
        .pop-rank.r3 { background: #fff7ed; color: #c2410c; }
        .pop-name { flex: 1; font-size: 0.82rem; font-weight: 600; color: var(--text-primary); }
        .pop-count { font-size: 0.72rem; font-weight: 700; background: var(--primary); color: white; padding: 0.18rem 0.5rem; border-radius: 100px; }

        /* Category groups */
        .cat-group { margin-bottom: 0.875rem; }
        .cat-group-header {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.78rem; font-weight: 700; color: var(--text-secondary);
            padding: 0.4rem 0.6rem; background: var(--surface-2);
            border-radius: var(--radius-sm); margin-bottom: 0.4rem;
            border: 1px solid var(--border-subtle);
        }
        .cat-group-header i { color: var(--primary); }
        .cat-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.4rem 0.6rem; font-size: 0.79rem;
            border-radius: var(--radius-sm); transition: var(--transition);
            cursor: pointer;
        }
        .cat-item:hover { background: var(--primary-light); }
        .cat-item-name { color: var(--text-primary); font-weight: 500; }
        .cat-item-price { color: var(--success); font-weight: 700; }

        /* Quick tips */
        .tip-item { display: flex; gap: 0.75rem; padding: 0.6rem 0; border-bottom: 1px solid var(--border-subtle); }
        .tip-item:last-child { border-bottom: none; }
        .tip-icon { width: 30px; height: 30px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
        .tip-text { font-size: 0.78rem; color: var(--text-secondary); line-height: 1.45; }
        .tip-text strong { color: var(--text-primary); font-size: 0.8rem; display: block; margin-bottom: 0.15rem; }

        /* Progress bar */
        .progress-wrap { margin-bottom: 0.6rem; }
        .progress-label { display: flex; justify-content: space-between; font-size: 0.73rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600; }
        .progress-bar-bg { height: 6px; background: var(--border); border-radius: 100px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 100px; background: var(--primary); transition: width 0.6s ease; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 3.5rem 2rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; color: var(--border); display: block; margin-bottom: 1rem; }
        .empty-state h4 { color: var(--text-secondary); font-weight: 700; margin-bottom: 0.4rem; font-size: 1.05rem; }
        .empty-state p { font-size: 0.83rem; margin-bottom: 1.25rem; }

        /* ── ALERTS ── */
        .alert { border-radius: var(--radius-md); border: 1px solid transparent; padding: 0.875rem 1.125rem; margin-bottom: 1.25rem; font-size: 0.875rem; }
        .alert-success { background: var(--success-light); color: var(--success); border-color: #bbf7d0; }
        .alert-danger  { background: var(--danger-light);  color: var(--danger);  border-color: #fecaca; }
        .alert-warning { background: var(--warning-light); color: var(--warning); border-color: #fde68a; }

        /* ── MODAL OVERRIDES ── */
        .modal-content { border-radius: var(--radius-lg); border: none; box-shadow: 0 24px 64px rgba(0,0,0,0.18); }
        .modal-header { border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: 1px solid var(--border); padding: 1rem 1.5rem; }
        .modal-title { font-weight: 700; font-size: 1rem; }
        .form-label { font-weight: 600; font-size: 0.83rem; color: var(--text-primary); margin-bottom: 0.35rem; display: block; }
        .form-control, .form-select {
            border-radius: var(--radius-sm); border: 1.5px solid var(--border);
            font-family: inherit; font-size: 0.875rem; padding: 0.55rem 0.875rem;
            transition: var(--transition);
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13,110,253,0.08); outline: none; }
        .form-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        .required { color: var(--danger); }

        /* section tab inside modal */
        .modal-tabs { display: flex; gap: 2px; background: var(--surface-2); border-radius: var(--radius-sm); padding: 3px; border: 1px solid var(--border); margin-bottom: 1.25rem; }
        .modal-tab-btn {
            flex: 1; padding: 0.45rem 0.5rem; border: none; border-radius: 6px;
            font-size: 0.75rem; font-weight: 700; font-family: inherit;
            cursor: pointer; color: var(--text-muted); background: transparent; transition: var(--transition);
        }
        .modal-tab-btn.active { background: var(--primary); color: white; }
        .modal-tab-btn:hover:not(.active) { background: var(--border); color: var(--text-primary); }

        .tab-pane-custom { display: none; }
        .tab-pane-custom.active { display: block; }

        /* day pill */
        .day-pill input { display: none; }
        .day-pill label {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 50%;
            border: 2px solid var(--border); cursor: pointer;
            font-size: 0.72rem; font-weight: 700; color: var(--text-secondary);
            transition: var(--transition); user-select: none;
        }
        .day-pill input:checked + label { border-color: var(--primary); background: var(--primary); color: white; }
        .day-pill label:hover { border-color: var(--primary); color: var(--primary); }

        /* service detail modal */
        .detail-stat-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 0.75rem; margin: 1rem 0; }
        .detail-stat { background: var(--surface-2); border-radius: var(--radius-sm); padding: 0.875rem; text-align: center; border: 1px solid var(--border-subtle); }
        .detail-stat-val { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; }
        .detail-stat-lbl { font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: var(--text-muted); margin-top: 2px; }

        .info-row-modal { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.55rem 0; border-bottom: 1px solid var(--border-subtle); }
        .info-row-modal:last-child { border-bottom: none; }
        .info-label-modal { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; }
        .info-val-modal { font-size: 0.83rem; font-weight: 600; color: var(--text-primary); text-align: right; max-width: 60%; }

        /* ── IMAGE UPLOAD ── */
        .image-upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            position: relative;
            background: var(--surface-2);
            cursor: pointer;
        }
        .image-upload-area:hover, .image-upload-area.dragover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .image-upload-area.multiple { min-height: 120px; }
        .upload-placeholder { color: var(--text-muted); }
        .upload-placeholder i { font-size: 2rem; margin-bottom: 0.5rem; display: block; }
        .upload-text strong { display: block; color: var(--text-primary); margin-bottom: 0.25rem; }
        .upload-hint { font-size: 0.75rem; opacity: 0.8; }

        .image-preview {
            position: relative;
            display: inline-block;
            max-width: 100%;
            margin-top: 1rem;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
        }
        .btn-remove-image {
            position: absolute;
            top: -8px; right: -8px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: var(--danger);
            color: white;
            border: none;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; cursor: pointer;
            box-shadow: var(--shadow-sm);
        }
        .btn-remove-image:hover { background: #c82333; }

        .additional-images-preview {
            display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;
        }
        .additional-image-item {
            position: relative;
            width: 80px; height: 80px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 2px solid var(--border);
        }
        .additional-image-item img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .additional-image-item .btn-remove-image {
            top: -6px; right: -6px; width: 20px; height: 20px; font-size: 0.6rem;
        }
        @media (max-width: 992px) {
            .layout-cols { flex-direction: column !important; }
            .side-col { width: 100% !important; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.12); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .kpi-grid { grid-template-columns: repeat(2,1fr); }
            .services-grid { grid-template-columns: 1fr; }
            .controls-bar { flex-direction: column; align-items: stretch; }
            .controls-left { flex-direction: column; }
        }

        /* btn primary */
        .btn-primary-cta {
            background: var(--primary); color: white; border: none;
            padding: 0.6rem 1.25rem; border-radius: var(--radius-sm);
            font-size: 0.875rem; font-weight: 700; font-family: inherit;
            cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .btn-primary-cta:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .btn-primary-cta:active { transform: none; }
        .btn-secondary-cta {
            background: var(--surface); color: var(--text-secondary); border: 1.5px solid var(--border);
            padding: 0.6rem 1.25rem; border-radius: var(--radius-sm);
            font-size: 0.875rem; font-weight: 700; font-family: inherit;
            cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .btn-secondary-cta:hover { border-color: var(--primary); color: var(--primary); }

        /* toast */
        .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; }
        .toast-item {
            background: var(--text-primary); color: white; padding: 0.75rem 1.125rem;
            border-radius: var(--radius-md); font-size: 0.83rem; font-weight: 600;
            box-shadow: var(--shadow-lg); min-width: 260px; pointer-events: all;
            display: flex; align-items: center; gap: 0.5rem;
            animation: toastIn 0.22s ease;
        }
        @keyframes toastIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
        .toast-success { background: var(--success); }
        .toast-danger  { background: var(--danger); }
        .toast-warning { background: var(--warning); }

        /* confirmation dialog */
        .confirm-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.45); z-index: 2000;
            align-items: center; justify-content: center; padding: 1rem;
        }
        .confirm-overlay.active { display: flex; }
        .confirm-box {
            background: white; border-radius: var(--radius-xl);
            padding: 2rem; max-width: 400px; width: 100%;
            box-shadow: var(--shadow-lg); text-align: center;
        }
        .confirm-box .icon { font-size: 2.5rem; margin-bottom: 0.875rem; }
        .confirm-box h4 { font-weight: 800; margin-bottom: 0.5rem; }
        .confirm-box p { color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1.5rem; }
        .confirm-btns { display: flex; gap: 0.75rem; justify-content: center; }
    </style>
</head>
<body>
    <script>
        // Initialize theme from localStorage
        (function() {
            const theme = localStorage.getItem('provider_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
<button class="mobile-menu-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

<?php if (isset($maintenance_warning)): ?>
<div class="alert alert-warning"><i class="fas fa-tools me-2"></i><strong>Maintenance Mode Active</strong> — Some features may be limited.</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php foreach ($errors as $e): ?><p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo $e; ?></p><?php endforeach; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-concierge-bell"></i> Service Management</h1>
        <p>Create, manage and optimise your service offerings</p>
    </div>
    <?php if ($can_add_service): ?>
    <button class="btn-primary-cta" data-bs-toggle="modal" data-bs-target="#addServiceModal">
        <i class="fas fa-plus-circle"></i> Add New Service
    </button>
    <?php else: ?>
    <button class="btn-primary-cta" data-bs-toggle="modal" data-bs-target="#upgradePlanModal">
        <i class="fas fa-lock"></i> Upgrade to Add More Services
    </button>
    <?php endif; ?>
</div>

<!-- KPI STATS -->
<?php
    $total_services_count = $stats['total_services'] ?? 0;
    $available_count = $stats['available_services'] ?? 0;
    $avg_price_val = $stats['avg_price'] ?? 0;
    $total_bookings_sum = 0;
    $total_revenue = 0;
    foreach ($service_details as $d) {
        $total_bookings_sum += $d['bookings']['total_bookings'] ?? 0;
        $total_revenue += ($d['bookings']['completed_bookings'] ?? 0);
    }
    $published_count = 0;
    $draft_count = 0;
    $paused_count = 0;
    foreach ($services as $sv) {
        $st = $sv['service_status'] ?? 'draft';
        if ($st === 'published') $published_count++;
        elseif ($st === 'paused') $paused_count++;
        else $draft_count++;
    }
?>
<div class="kpi-grid">
    <div class="kpi-card" style="--kpi-color:#0d6efd;--kpi-bg:#eff6ff;">
        <div class="kpi-icon"><i class="fas fa-briefcase"></i></div>
        <div class="kpi-value"><?php echo $total_services_count; ?></div>
        <div class="kpi-label">Total Services</div>
        <div class="kpi-trend neutral"><i class="fas fa-info-circle"></i> <?php echo $published_count; ?> published</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#198754;--kpi-bg:#f0fdf4;">
        <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-value"><?php echo $available_count; ?></div>
        <div class="kpi-label">Available Now</div>
        <div class="kpi-trend <?php echo $paused_count > 0 ? 'neutral' : 'up'; ?>">
            <i class="fas fa-pause"></i> <?php echo $paused_count; ?> paused
        </div>
    </div>
    <div class="kpi-card" style="--kpi-color:#d97706;--kpi-bg:#fffbeb;">
        <div class="kpi-icon"><i class="fas fa-tag"></i></div>
        <div class="kpi-value">RWF <?php echo number_format($avg_price_val, 0); ?></div>
        <div class="kpi-label">Avg. Service Price</div>
        <div class="kpi-trend neutral"><i class="fas fa-wallet"></i> across all services</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#0891b2;--kpi-bg:#ecfeff;">
        <div class="kpi-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="kpi-value"><?php echo $total_bookings_sum; ?></div>
        <div class="kpi-label">Total Bookings</div>
        <div class="kpi-trend up"><i class="fas fa-check"></i> <?php echo $total_revenue; ?> completed</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#9333ea;--kpi-bg:#fdf4ff;">
        <div class="kpi-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="kpi-value"><?php echo $draft_count; ?></div>
        <div class="kpi-label">Draft Services</div>
        <div class="kpi-trend neutral"><i class="fas fa-arrow-right"></i> ready to publish</div>
    </div>
</div>

<!-- LAYOUT: Main + Sidebar -->
<div style="display:flex;gap:1.5rem;align-items:flex-start;" class="layout-cols">

    <!-- MAIN COLUMN -->
    <div style="flex:1;min-width:0;">

        <!-- CONTROLS BAR -->
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="serviceSearch" placeholder="Search services…">
                </div>
                <select class="filter-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="paused">Paused</option>
                    <option value="draft">Draft</option>
                </select>
                <select class="filter-select" id="filterCategory">
                    <option value="">All Categories</option>
                    <?php foreach ($provider_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter-select" id="sortServices">
                    <option value="default">Sort: Default</option>
                    <option value="name">Name A–Z</option>
                    <option value="price-low">Price ↑</option>
                    <option value="price-high">Price ↓</option>
                    <option value="bookings">Most Booked</option>
                </select>
            </div>
            <div class="controls-right">
                <div class="view-toggle">
                    <button class="view-btn active" id="viewGrid" title="Grid"><i class="fas fa-grip"></i></button>
                    <button class="view-btn" id="viewList" title="List"><i class="fas fa-list"></i></button>
                </div>
            </div>
        </div>

        <!-- SERVICES GRID -->
        <div id="servicesContainer">
        <?php if (empty($services)): ?>
            <div class="empty-state" style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);">
                <i class="fas fa-concierge-bell"></i>
                <h4>No Services Yet</h4>
                <p>Start by adding your first service to attract clients</p>
                <button class="btn-primary-cta" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="fas fa-plus-circle"></i> Create First Service
                </button>
            </div>
        <?php else: ?>
            <div class="services-grid" id="servicesGrid">
            <?php foreach ($services as $service):
                $sd = $service_details[$service['id']] ?? null;
                $bookings_count = $sd['bookings']['total_bookings'] ?? 0;
                $completed_count = $sd['bookings']['completed_bookings'] ?? 0;
                $avg_rating = $sd['reviews']['avg_rating'] ?? 0;
                $total_reviews = $sd['reviews']['total_reviews'] ?? 0;
                $sStatus = $service['service_status'] ?? 'draft';
                $statusColors = ['published'=>'#198754','paused'=>'#d97706','draft'=>'#9ca3af'];
                $accentColor = $statusColors[$sStatus] ?? '#9ca3af';
                $extraItems = !empty($service['optional_extras']) ? json_decode($service['optional_extras'],true) : [];
                $isNegotiable = !empty($service['negotiable']);
                $isInstant = ($service['booking_mode'] ?? 'request_approval') === 'instant';
                // Star rendering
                $stars = '';
                for ($si=1;$si<=5;$si++) $stars .= ($si <= round($avg_rating)) ? '★' : '☆';
            ?>
            <div class="service-card status-<?php echo $sStatus; ?>"
                 data-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>"
                 data-status="<?php echo $sStatus; ?>"
                 data-category="<?php echo $service['category_id']; ?>"
                 data-price="<?php echo $service['price']; ?>"
                 data-bookings="<?php echo $bookings_count; ?>">
                <div class="sc-accent-bar" style="background:<?php echo $accentColor; ?>;"></div>
                <div class="sc-body">
                    <div class="sc-header">
                        <div>
                            <h3 class="sc-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <div class="sc-category">
                                <i class="fas <?php echo $service['category_icon']; ?>"></i>
                                <?php echo htmlspecialchars($service['category_name']); ?>
                            </div>
                        </div>
                        <div class="sc-badges">
                            <?php if ($sStatus === 'published'): ?><span class="badge-pill badge-published"><i class="fas fa-circle" style="font-size:0.45rem;"></i> Live</span>
                            <?php elseif ($sStatus === 'paused'): ?><span class="badge-pill badge-paused"><i class="fas fa-pause" style="font-size:0.55rem;"></i> Paused</span>
                            <?php else: ?><span class="badge-pill badge-draft"><i class="fas fa-pencil-alt" style="font-size:0.55rem;"></i> Draft</span>
                            <?php endif; ?>
                            <?php if ($isNegotiable): ?><span class="badge-pill badge-neg"><i class="fas fa-handshake" style="font-size:0.55rem;"></i> Neg.</span><?php endif; ?>
                            <?php if ($isInstant): ?><span class="badge-pill badge-instant"><i class="fas fa-bolt" style="font-size:0.55rem;"></i> Instant</span><?php endif; ?>
                        </div>
                    </div>

                    <p class="sc-description"><?php echo htmlspecialchars($service['description']); ?></p>

                    <!-- AI INSIGHTS ROW -->
                    <?php if (isset($service_ai_data[$service['id']])): 
                        $ai = $service_ai_data[$service['id']];
                        $perf = $ai['performance_score'];
                        $demand = $ai['demand_indicator'];
                        $conv = $ai['conversion_rate'];
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:1rem;padding:0.75rem;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
                        <div style="text-align:center;">
                            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Performance</div>
                            <div style="font-size:1.35rem;font-weight:800;color:<?php echo $perf >= 70 ? '#10b981' : ($perf >= 40 ? '#f59e0b' : '#ef4444'); ?>;"><?php echo $perf; ?></div>
                            <div style="font-size:0.6rem;color:var(--text-muted);">Score</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Demand</div>
                            <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;">
                                <i class="<?php echo $demand['icon']; ?>" style="color:<?php echo $demand['color']; ?>;font-size:0.9rem;"></i>
                                <span style="font-size:0.75rem;font-weight:700;color:<?php echo $demand['color']; ?>"><?php echo $demand['label']; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- AI INSIGHTS ROW -->
                    <?php if (isset($service_ai_data[$service['id']])): 
                        $ai = $service_ai_data[$service['id']];
                        $perf = $ai['performance_score'];
                        $demand = $ai['demand_indicator'];
                        $conv = $ai['conversion_rate'];
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:1rem;padding:0.75rem;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
                        <div style="text-align:center;">
                            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Performance</div>
                            <div style="font-size:1.35rem;font-weight:800;color:<?php echo $perf >= 70 ? '#10b981' : ($perf >= 40 ? '#f59e0b' : '#ef4444'); ?>;"><?php echo $perf; ?></div>
                            <div style="font-size:0.6rem;color:var(--text-muted);">Score</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Demand</div>
                            <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;">
                                <i class="<?php echo $demand['icon']; ?>" style="color:<?php echo $demand['color']; ?>;font-size:0.9rem;"></i>
                                <span style="font-size:0.75rem;font-weight:700;color:<?php echo $demand['color']; ?>"><?php echo $demand['label']; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="sc-mini-stats">
                        <div class="sc-stat">
                            <div class="sc-stat-num"><?php echo $bookings_count; ?></div>
                            <div class="sc-stat-lbl">Bookings</div>
                        </div>
                        <div class="sc-stat">
                            <div class="sc-stat-num" style="color:#f59e0b;"><?php echo $avg_rating > 0 ? number_format($avg_rating,1) : '—'; ?></div>
                            <div class="sc-stat-lbl" title="<?php echo $total_reviews; ?> reviews">Rating</div>
                        </div>
                        <div class="sc-stat">
                            <div class="sc-stat-num" style="color:#198754;"><?php echo $completed_count; ?></div>
                            <div class="sc-stat-lbl">Done</div>
                        </div>
                    </div>

                    <?php if (!empty($extraItems)): ?>
                    <div style="margin-bottom:0.75rem;">
                        <span class="badge-pill" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">
                            <i class="fas fa-plus-circle" style="font-size:0.55rem;"></i> <?php echo count($extraItems); ?> extras
                        </span>
                        <span style="font-size:0.72rem;color:var(--text-muted);margin-left:0.4rem;">
                            <?php echo htmlspecialchars($extraItems[0]['label']); ?>…
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="sc-footer">
                        <div>
                            <div class="sc-price">RWF <?php echo number_format($service['price'],0); ?></div>
                            <div class="sc-price-sub"><?php
                                $ptLabels=['fixed_price'=>'Fixed','hourly_rate'=>'/hr','per_job_estimate'=>'Per job','per_day'=>'/day','base_price'=>'Base','per_service'=>'Per service'];
                                echo $ptLabels[$service['payment_type']] ?? $service['payment_type'];
                            ?></div>
                        </div>
                        <div class="sc-duration"><i class="fas fa-clock"></i> <?php echo $service['duration']; ?> min</div>
                    </div>

                    <div class="sc-actions">
                        <!-- Details -->
                        <button class="btn-act btn-act-view" type="button"
                            data-bs-toggle="modal" data-bs-target="#serviceDetailsModal"
                            data-service-id="<?php echo $service['id']; ?>"
                            data-service-name="<?php echo htmlspecialchars($service['name'],ENT_QUOTES); ?>"
                            data-service-description="<?php echo htmlspecialchars($service['description'],ENT_QUOTES); ?>"
                            data-service-price="<?php echo $service['price']; ?>"
                            data-service-duration="<?php echo $service['duration']; ?>"
                            data-service-category="<?php echo htmlspecialchars($service['category_name'],ENT_QUOTES); ?>"
                            data-service-optional-extras="<?php echo htmlspecialchars($service['optional_extras'] ?? '',ENT_QUOTES,'UTF-8'); ?>"
                            data-service-bookings="<?php echo $bookings_count; ?>"
                            data-service-completed="<?php echo $completed_count; ?>"
                            data-service-rating="<?php echo number_format($avg_rating,1); ?>"
                            data-service-reviews="<?php echo $total_reviews; ?>"
                            data-service-negotiable="<?php echo $isNegotiable ? 'Yes':'No'; ?>"
                            data-service-min-price="<?php echo $service['min_price'] ?? ''; ?>"
                            data-service-max-price="<?php echo $service['max_price'] ?? ''; ?>"
                            data-service-booking-mode="<?php echo htmlspecialchars($service['booking_mode'] ?? 'request_approval',ENT_QUOTES); ?>"
                            data-service-status="<?php echo $sStatus; ?>"
                            data-service-payment-type="<?php echo htmlspecialchars($service['payment_type'],ENT_QUOTES); ?>"
                            data-service-ai="<?php echo isset($service_ai_data[$service['id']]) ? htmlspecialchars(json_encode($service_ai_data[$service['id']]),ENT_QUOTES) : ''; ?>">
                            <i class="fas fa-eye"></i> Details
                        </button>
                        <!-- Edit -->
                        <button class="btn-act btn-act-edit" type="button"
                            data-bs-toggle="modal" data-bs-target="#editServiceModal"
                            data-service-id="<?php echo $service['id']; ?>"
                            data-service-name="<?php echo htmlspecialchars($service['name'],ENT_QUOTES); ?>"
                            data-service-description="<?php echo htmlspecialchars($service['description'],ENT_QUOTES); ?>"
                            data-service-price="<?php echo $service['price']; ?>"
                            data-service-duration="<?php echo $service['duration']; ?>"
                            data-service-available="<?php echo $service['is_available']; ?>"
                            data-service-status="<?php echo $sStatus; ?>"
                            data-service-payment-type="<?php echo htmlspecialchars($service['payment_type'],ENT_QUOTES); ?>"
                            data-service-booking-mode="<?php echo htmlspecialchars($service['booking_mode'] ?? 'request_approval',ENT_QUOTES); ?>"
                            data-service-availability-days="<?php echo htmlspecialchars($service['availability_days'] ?? '',ENT_QUOTES); ?>"
                            data-service-time-slots="<?php echo htmlspecialchars($service['time_slots'] ?? '',ENT_QUOTES,'UTF-8'); ?>"
                            data-service-optional-extras="<?php echo htmlspecialchars($service['optional_extras'] ?? '',ENT_QUOTES,'UTF-8'); ?>"
                            data-service-negotiable="<?php echo $service['negotiable'] ?? 0; ?>"
                            data-service-min-price="<?php echo $service['min_price'] ?? ''; ?>"
                            data-service-max-price="<?php echo $service['max_price'] ?? ''; ?>">
                            <i class="fas fa-pencil-alt"></i> Edit
                        </button>
                        <!-- Toggle -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                            <?php if ($sStatus === 'published'): ?>
                            <button type="submit" name="toggle_availability" class="btn-act btn-act-pause">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <?php else: ?>
                            <button type="submit" name="toggle_availability" class="btn-act btn-act-publish">
                                <i class="fas fa-play"></i> Publish
                            </button>
                            <?php endif; ?>
                        </form>
                        <!-- Delete -->
                        <button class="btn-act btn-act-del" type="button"
                            onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo addslashes($service['name']); ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div><!-- /services-grid -->
            <div id="noResults" style="display:none;" class="empty-state" style="background:var(--surface);border-radius:var(--radius-lg);">
                <i class="fas fa-search"></i>
                <h4>No Results</h4>
                <p>Try adjusting your search or filters</p>
            </div>
        <?php endif; ?>
        </div><!-- /servicesContainer -->
    </div><!-- /main col -->

    <!-- RIGHT SIDEBAR -->
    <div class="side-col" style="width:300px;flex-shrink:0;">

        <!-- POPULAR SERVICES -->
        <div class="side-panel">
            <div class="side-panel-header">
                <h3><i class="fas fa-fire" style="color:#ef4444;"></i> Most Booked</h3>
            </div>
            <div class="side-panel-body">
                <?php if (empty($popular_services) || max(array_column($popular_services,'booking_count')) == 0): ?>
                <div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.82rem;">
                    <i class="fas fa-chart-bar" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                    No booking data yet
                </div>
                <?php else: foreach ($popular_services as $ri => $popular): ?>
                <div class="pop-item">
                    <div class="pop-rank <?php echo 'r'.($ri+1); ?>">#<?php echo $ri+1; ?></div>
                    <div class="pop-name"><?php echo htmlspecialchars($popular['name']); ?></div>
                    <span class="pop-count"><?php echo $popular['booking_count']; ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- COMPLETION HEALTH -->
        <?php if (!empty($services)): ?>
        <div class="side-panel">
            <div class="side-panel-header">
                <h3><i class="fas fa-heartbeat" style="color:#0d6efd;"></i> Service Health</h3>
            </div>
            <div class="side-panel-body">
                <?php
                $pub_pct = $total_services_count > 0 ? round($published_count / $total_services_count * 100) : 0;
                ?>
                <div class="progress-wrap">
                    <div class="progress-label"><span>Published</span><span><?php echo $pub_pct; ?>%</span></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?php echo $pub_pct; ?>%;background:#198754;"></div></div>
                </div>
                <?php
                $neg_count = array_sum(array_column($services, 'negotiable'));
                $neg_pct = $total_services_count > 0 ? round($neg_count / $total_services_count * 100) : 0;
                ?>
                <div class="progress-wrap" style="margin-top:0.75rem;">
                    <div class="progress-label"><span>Negotiable</span><span><?php echo $neg_pct; ?>%</span></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?php echo $neg_pct; ?>%;background:#0891b2;"></div></div>
                </div>
                <?php
                $inst_count = count(array_filter($services, fn($s) => ($s['booking_mode'] ?? '') === 'instant'));
                $inst_pct = $total_services_count > 0 ? round($inst_count / $total_services_count * 100) : 0;
                ?>
                <div class="progress-wrap" style="margin-top:0.75rem;">
                    <div class="progress-label"><span>Instant Booking</span><span><?php echo $inst_pct; ?>%</span></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?php echo $inst_pct; ?>%;background:#9333ea;"></div></div>
                </div>
                <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-subtle);">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;text-align:center;">
                        <div style="background:var(--surface-2);border-radius:var(--radius-sm);padding:0.6rem;border:1px solid var(--border-subtle);">
                            <div style="font-size:1.2rem;font-weight:800;color:var(--text-primary);"><?php echo $published_count; ?></div>
                            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--success);">Published</div>
                        </div>
                        <div style="background:var(--surface-2);border-radius:var(--radius-sm);padding:0.6rem;border:1px solid var(--border-subtle);">
                            <div style="font-size:1.2rem;font-weight:800;color:var(--text-primary);"><?php echo $draft_count; ?></div>
                            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Drafts</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SERVICES BY CATEGORY -->
        <?php if (!empty($related_services_by_category)): ?>
        <div class="side-panel">
            <div class="side-panel-header">
                <h3><i class="fas fa-layer-group" style="color:#0d6efd;"></i> By Category</h3>
            </div>
            <div class="side-panel-body">
                <?php foreach ($related_services_by_category as $catGroup): ?>
                <div class="cat-group">
                    <div class="cat-group-header">
                        <i class="fas <?php echo $catGroup['category_icon']; ?>"></i>
                        <?php echo htmlspecialchars($catGroup['category_name']); ?>
                        <span class="pop-count" style="margin-left:auto;"><?php echo count($catGroup['services']); ?></span>
                    </div>
                    <?php foreach ($catGroup['services'] as $cs): ?>
                    <div class="cat-item">
                        <span class="cat-item-name"><?php echo htmlspecialchars($cs['name']); ?></span>
                        <span class="cat-item-price">RWF <?php echo number_format($cs['price'],0); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- TIPS -->
        <div class="side-panel">
            <div class="side-panel-header">
                <h3><i class="fas fa-lightbulb" style="color:#d97706;"></i> Pro Tips</h3>
            </div>
            <div class="side-panel-body">
                <div class="tip-item">
                    <div class="tip-icon" style="background:#fffbeb;color:#d97706;"><i class="fas fa-camera"></i></div>
                    <div class="tip-text"><strong>Add Portfolio Images</strong>Services with photos get 3× more bookings</div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon" style="background:#f0fdf4;color:#198754;"><i class="fas fa-handshake"></i></div>
                    <div class="tip-text"><strong>Enable Negotiation</strong>Attract more clients with flexible pricing</div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon" style="background:#fdf4ff;color:#9333ea;"><i class="fas fa-bolt"></i></div>
                    <div class="tip-text"><strong>Instant Booking</strong>Services with instant booking see 40% more conversions</div>
                </div>
                <div class="tip-item" style="border:none;">
                    <div class="tip-icon" style="background:#ecfeff;color:#0891b2;"><i class="fas fa-star"></i></div>
                    <div class="tip-text"><strong>Request Reviews</strong>Ask completed clients to leave a rating</div>
                </div>
            </div>
        </div>

    </div><!-- /side col -->
</div><!-- /layout -->
</div><!-- /main-content -->

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- CONFIRM DELETE DIALOG -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="icon" style="color:var(--danger);">🗑️</div>
        <h4>Delete Service?</h4>
        <p id="confirmMsg">This service will be permanently deleted and cannot be recovered.</p>
        <div class="confirm-btns">
            <button class="btn-secondary-cta" onclick="closeConfirm()">Cancel</button>
            <form method="POST" id="deleteForm" style="display:inline;">
                <input type="hidden" name="service_id" id="deleteServiceId">
                <button type="submit" name="delete_service" class="btn-primary-cta" style="background:var(--danger);">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ======================= SERVICE DETAILS MODAL ======================= -->
<div class="modal fade" id="serviceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2" style="color:var(--info);"></i><span id="detailServiceName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- AI Insights Section -->
                <div id="detailAIInsights" style="background:linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(0, 184, 148, 0.05) 100%);padding:1rem;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);margin-bottom:1rem;display:none;">
                    <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:var(--primary);margin-bottom:0.75rem;">
                        <i class="fas fa-brain" style="margin-right:0.25rem;"></i> AI-Powered Insights
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                        <div style="text-align:center;padding:0.5rem;background:var(--surface);border-radius:4px;">
                            <div style="font-size:0.6rem;color:var(--text-muted);margin-bottom:0.25rem;">Performance Score</div>
                            <div style="font-size:1.5rem;font-weight:800;" id="detailPerfScore">—</div>
                        </div>
                        <div style="text-align:center;padding:0.5rem;background:var(--surface);border-radius:4px;">
                            <div style="font-size:0.6rem;color:var(--text-muted);margin-bottom:0.25rem;">Demand Level</div>
                            <div style="font-size:0.9rem;font-weight:700;" id="detailDemandBadge">—</div>
                        </div>
                        <div style="text-align:center;padding:0.5rem;background:var(--surface);border-radius:4px;">
                            <div style="font-size:0.6rem;color:var(--text-muted);margin-bottom:0.25rem;">Conversion Rate</div>
                            <div style="font-size:1.5rem;font-weight:800;" id="detailConvRate">—</div>
                        </div>
                    </div>
                    <div style="padding:0.75rem;background:var(--surface);border-radius:4px;">
                        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.5rem;">Top Recommendations</div>
                        <div id="detailTips" style="font-size:0.825rem;color:var(--text-secondary);line-height:1.5;"></div>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="detail-stat-row">
                    <div class="detail-stat">
                        <div class="detail-stat-val" id="detailBookings">0</div>
                        <div class="detail-stat-lbl">Bookings</div>
                    </div>
                    <div class="detail-stat">
                        <div class="detail-stat-val" style="color:#f59e0b;" id="detailRating">—</div>
                        <div class="detail-stat-lbl">Avg Rating</div>
                    </div>
                    <div class="detail-stat">
                        <div class="detail-stat-val" style="color:var(--success);" id="detailCompleted">0</div>
                        <div class="detail-stat-lbl">Completed</div>
                    </div>
                </div>

                <div style="margin-bottom:1rem;">
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-muted);margin-bottom:0.5rem;">Description</div>
                    <p id="detailDescription" style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;margin:0;"></p>
                </div>

                <div>
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-muted);margin-bottom:0.5rem;">Service Details</div>
                    <div class="info-row-modal"><span class="info-label-modal">Category</span><span class="info-val-modal" id="detailCategory"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Price</span><span class="info-val-modal" id="detailPrice"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Payment Type</span><span class="info-val-modal" id="detailPaymentType"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Duration</span><span class="info-val-modal" id="detailDuration"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Status</span><span class="info-val-modal" id="detailStatus"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Negotiable</span><span class="info-val-modal" id="detailNegotiable"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Price Range</span><span class="info-val-modal" id="detailPriceRange"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Booking Mode</span><span class="info-val-modal" id="detailBookingMode"></span></div>
                    <div class="info-row-modal"><span class="info-label-modal">Reviews</span><span class="info-val-modal" id="detailReviews"></span></div>
                    <div class="info-row-modal" id="detailExtrasRow"><span class="info-label-modal">Optional Extras</span><span class="info-val-modal" id="detailExtras"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-cta" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-primary-cta" id="detailPriceCompareBtn" style="display:none;" onclick="showPriceComparison(event)">
                    <i class="fas fa-chart-bar"></i> Compare Price
                </button>
                <button type="button" class="btn-primary-cta" id="detailGetSuggestionsBtn" style="display:none;" onclick="showAISuggestions(event)">
                    <i class="fas fa-lightbulb"></i> AI Suggestions
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================= ADD SERVICE MODAL ======================= -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="serviceForm" enctype="multipart/form-data">
                <div class="modal-header" style="background:var(--primary-light);">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color:var(--primary);"></i> Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Tab navigation -->
                    <div class="modal-tabs" id="addTabs">
                        <button type="button" class="modal-tab-btn active" data-tab="add-basic">Basic Info</button>
                        <button type="button" class="modal-tab-btn" data-tab="add-pricing">Pricing</button>
                        <button type="button" class="modal-tab-btn" data-tab="add-images">Images</button>
                        <button type="button" class="modal-tab-btn" data-tab="add-schedule">Schedule</button>
                        <button type="button" class="modal-tab-btn" data-tab="add-advanced">Advanced</button>
                    </div>

                    <!-- Basic Info -->
                    <div class="tab-pane-custom active" id="add-basic">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Service Name <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="e.g., Electrical Installation, House Cleaning…" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category <span class="required">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($provider_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($provider_categories)): ?>
                                <div class="form-text text-warning"><i class="fas fa-exclamation-triangle"></i> No categories. Update your profile first.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description <span class="required">*</span></label>
                                <textarea name="description" class="form-control" rows="3" required placeholder="Describe what this service includes, what clients can expect…"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="form-text">A clear description helps clients understand and choose your service.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Service Status</label>
                                <select name="service_status" class="form-select" id="addServiceStatus">
                                    <option value="draft">Draft (not visible)</option>
                                    <option value="published">Published (live)</option>
                                    <option value="paused">Paused</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Booking Mode</label>
                                <select name="booking_mode" class="form-select">
                                    <option value="request_approval">Request Approval</option>
                                    <option value="instant">Instant Booking ⚡</option>
                                </select>
                                <div class="form-text">Instant booking requires no confirmation from you.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="tab-pane-custom" id="add-pricing">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Base Price (RWF) <span class="required">*</span></label>
                                <input type="number" name="price" class="form-control" required min="0" step="100" placeholder="5000" id="addBasePrice" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Type</label>
                                <select name="payment_type" class="form-select">
                                    <option value="fixed_price">Fixed Price</option>
                                    <option value="hourly_rate">Per Hour</option>
                                    <option value="per_job_estimate">Per Job (estimate)</option>
                                    <option value="per_day">Per Day</option>
                                    <option value="per_service">Per Service</option>
                                    <option value="base_price">Base Price</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (minutes) <span class="required">*</span></label>
                                <input type="number" name="duration" class="form-control" required min="15" step="15" placeholder="60" value="<?php echo htmlspecialchars($_POST['duration'] ?? '60'); ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="negotiable" id="addNegotiableCheck" value="1" <?php echo isset($_POST['negotiable']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="addNegotiableCheck">
                                        <strong>Allow Price Negotiation</strong>
                                        <span style="font-size:0.78rem;color:var(--text-muted);display:block;">Clients can offer prices within your range</span>
                                    </label>
                                </div>
                            </div>
                            <div id="addNegotiableFields" style="display:none;" class="col-12">
                                <div style="background:var(--primary-light);padding:1rem;border-radius:var(--radius-md);border:1px solid #bfdbfe;">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Minimum Price (RWF)</label>
                                            <input type="number" name="min_price" class="form-control" min="0" step="100" placeholder="4000" value="<?php echo htmlspecialchars($_POST['min_price'] ?? ''); ?>">
                                            <div class="form-text">Lowest offer you'll accept</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Maximum Price (RWF)</label>
                                            <input type="number" name="max_price" class="form-control" min="0" step="100" placeholder="10000" value="<?php echo htmlspecialchars($_POST['max_price'] ?? ''); ?>">
                                            <div class="form-text">Highest clients can offer</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="tab-pane-custom" id="add-schedule">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Available Days</label>
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.35rem;">
                                    <?php $weekdays=[1=>'Mo',2=>'Tu',3=>'We',4=>'Th',5=>'Fr',6=>'Sa',7=>'Su']; ?>
                                    <?php foreach ($weekdays as $dn => $dl): ?>
                                    <div class="day-pill">
                                        <input type="checkbox" name="availability_days[]" value="<?php echo $dn; ?>" id="addDay<?php echo $dn; ?>" <?php echo in_array($dn,[1,2,3,4,5]) ? 'checked' : ''; ?>>
                                        <label for="addDay<?php echo $dn; ?>"><?php echo $dl; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select the days clients can book this service.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Time Slots (optional)</label>
                                <textarea name="time_slots" class="form-control" rows="3" placeholder="08:00-12:00&#10;14:00-18:00"></textarea>
                                <div class="form-text">One time slot per line in HH:MM-HH:MM format. Leave blank for default provider schedule.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="tab-pane-custom" id="add-images">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Main Service Image</label>
                                <div class="image-upload-area" id="mainImageUpload">
                                    <div class="upload-placeholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div class="upload-text">
                                            <strong>Click to upload</strong> or drag and drop
                                            <div class="upload-hint">JPG, PNG, WebP up to 4MB</div>
                                        </div>
                                    </div>
                                    <input type="file" name="service_image" id="addServiceImage" accept="image/*" style="display: none;">
                                    <div class="image-preview" id="addMainImagePreview" style="display: none;">
                                        <img src="" alt="Preview" id="addMainImageImg">
                                        <button type="button" class="btn-remove-image" onclick="removeImage('addMainImagePreview', 'addServiceImage')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Image Alt Text</label>
                                <input type="text" name="image_alt_text" class="form-control" placeholder="Describe the image for accessibility" value="<?php echo htmlspecialchars($_POST['image_alt_text'] ?? ''); ?>">
                                <div class="form-text">Help screen readers understand your image</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Additional Images (Optional)</label>
                                <div class="image-upload-area multiple" id="additionalImagesUpload">
                                    <div class="upload-placeholder">
                                        <i class="fas fa-images"></i>
                                        <div class="upload-text">
                                            <strong>Click to upload</strong> or drag and drop multiple images
                                            <div class="upload-hint">JPG, PNG, WebP up to 4MB each</div>
                                        </div>
                                    </div>
                                    <input type="file" name="service_images[]" id="addServiceImages" accept="image/*" multiple style="display: none;">
                                    <div class="additional-images-preview" id="addAdditionalImagesPreview"></div>
                                </div>
                                <div class="form-text">Upload up to 5 additional images to showcase your work</div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-cta" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_service" class="btn-primary-cta"><i class="fas fa-plus-circle"></i> Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================= EDIT SERVICE MODAL ======================= -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="editServiceForm" enctype="multipart/form-data">
                <input type="hidden" name="service_id" id="editServiceId">
                <div class="modal-header" style="background:var(--primary-light);">
                    <h5 class="modal-title"><i class="fas fa-pencil-alt me-2" style="color:var(--primary);"></i> Edit Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-tabs" id="editTabs">
                        <button type="button" class="modal-tab-btn active" data-tab="edit-basic">Basic Info</button>
                        <button type="button" class="modal-tab-btn" data-tab="edit-pricing">Pricing</button>
                        <button type="button" class="modal-tab-btn" data-tab="edit-images">Images</button>
                        <button type="button" class="modal-tab-btn" data-tab="edit-schedule">Schedule</button>
                        <button type="button" class="modal-tab-btn" data-tab="edit-advanced">Advanced</button>
                    </div>

                    <!-- Basic -->
                    <div class="tab-pane-custom active" id="edit-basic">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Service Name <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" required id="editServiceName" placeholder="Service name">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description <span class="required">*</span></label>
                                <textarea name="description" class="form-control" rows="3" required id="editServiceDescription" placeholder="Describe this service…"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Service Status</label>
                                <select name="service_status" class="form-select" id="editServiceStatus">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published (live)</option>
                                    <option value="paused">Paused</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Booking Mode</label>
                                <select name="booking_mode" class="form-select" id="editServiceBookingMode">
                                    <option value="request_approval">Request Approval</option>
                                    <option value="instant">Instant Booking ⚡</option>
                                </select>
                            </div>
                            <div class="d-none">
                                <input class="form-check-input" type="checkbox" name="is_available" id="editServiceAvailable" value="1">
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="tab-pane-custom" id="edit-pricing">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Base Price (RWF) <span class="required">*</span></label>
                                <input type="number" name="price" class="form-control" required min="0" step="100" id="editServicePrice">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (minutes) <span class="required">*</span></label>
                                <input type="number" name="duration" class="form-control" required min="15" step="15" id="editServiceDuration">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Payment Type</label>
                                <select name="payment_type" class="form-select" id="editServicePaymentType">
                                    <option value="fixed_price">Fixed Price</option>
                                    <option value="hourly_rate">Per Hour</option>
                                    <option value="per_job_estimate">Per Job (estimate)</option>
                                    <option value="per_day">Per Day</option>
                                    <option value="per_service">Per Service</option>
                                    <option value="base_price">Base Price</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="negotiable" id="editNegotiableCheck" value="1">
                                    <label class="form-check-label" for="editNegotiableCheck">
                                        <strong>Allow Price Negotiation</strong>
                                        <span style="font-size:0.78rem;color:var(--text-muted);display:block;">Clients can offer prices within your range</span>
                                    </label>
                                </div>
                            </div>
                            <div id="editNegotiableFields" style="display:none;" class="col-12">
                                <div style="background:var(--primary-light);padding:1rem;border-radius:var(--radius-md);border:1px solid #bfdbfe;">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Minimum Price (RWF)</label>
                                            <input type="number" name="min_price" class="form-control" min="0" step="100" id="editMinPrice">
                                            <div class="form-text">Lowest offer you'll accept</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Maximum Price (RWF)</label>
                                            <input type="number" name="max_price" class="form-control" min="0" step="100" id="editMaxPrice">
                                            <div class="form-text">Highest clients can offer</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="tab-pane-custom" id="edit-images">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Main Service Image</label>
                                <div class="image-upload-area" id="editMainImageUpload">
                                    <div class="upload-placeholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div class="upload-text">
                                            <strong>Click to upload</strong> or drag and drop
                                            <div class="upload-hint">JPG, PNG, WebP up to 4MB</div>
                                        </div>
                                    </div>
                                    <input type="file" name="service_image" id="editServiceImage" accept="image/*" style="display: none;">
                                    <div class="image-preview" id="editMainImagePreview" style="display: none;">
                                        <img src="" alt="Preview" id="editMainImageImg">
                                        <button type="button" class="btn-remove-image" onclick="removeImage('editMainImagePreview', 'editServiceImage')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Image Alt Text</label>
                                <input type="text" name="image_alt_text" class="form-control" id="editImageAltText" placeholder="Describe the image for accessibility">
                                <div class="form-text">Help screen readers understand your image</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Additional Images (Optional)</label>
                                <div class="image-upload-area multiple" id="editAdditionalImagesUpload">
                                    <div class="upload-placeholder">
                                        <i class="fas fa-images"></i>
                                        <div class="upload-text">
                                            <strong>Click to upload</strong> or drag and drop multiple images
                                            <div class="upload-hint">JPG, PNG, WebP up to 4MB each</div>
                                        </div>
                                    </div>
                                    <input type="file" name="service_images[]" id="editServiceImages" accept="image/*" multiple style="display: none;">
                                    <div class="additional-images-preview" id="editAdditionalImagesPreview"></div>
                                </div>
                                <div class="form-text">Upload up to 5 additional images to showcase your work</div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="tab-pane-custom" id="edit-schedule">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Available Days</label>
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.35rem;">
                                    <?php foreach ($weekdays as $dn => $dl): ?>
                                    <div class="day-pill">
                                        <input type="checkbox" name="availability_days[]" value="<?php echo $dn; ?>" id="editDay<?php echo $dn; ?>" class="edit-availability-day">
                                        <label for="editDay<?php echo $dn; ?>"><?php echo $dl; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Time Slots (optional)</label>
                                <textarea name="time_slots" class="form-control" rows="3" id="editTimeSlots" placeholder="08:00-12:00&#10;14:00-18:00"></textarea>
                                <div class="form-text">One slot per line: HH:MM-HH:MM</div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced -->
                    <div class="tab-pane-custom" id="edit-advanced">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Optional Extras</label>
                                <textarea name="optional_extras" class="form-control" rows="4" id="editOptionalExtras" placeholder="Emergency service (+10000)&#10;Weekend surcharge (+5000)"></textarea>
                                <div class="form-text">One extra per line: <code>Label (+price)</code></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-cta" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_service" class="btn-primary-cta"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// ── Mobile sidebar ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('providerSidebar') || document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => { try { new bootstrap.Alert(el).close(); } catch(e){} });
    }, 5000);
});

// ── TOAST ───────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast-item toast-' + type;
    const icons = {success:'check-circle',danger:'exclamation-circle',warning:'exclamation-triangle'};
    t.innerHTML = '<i class="fas fa-' + (icons[type]||'info-circle') + '"></i> ' + msg;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

// ── VIEW TOGGLE ─────────────────────────────────────────────────────────
document.getElementById('viewGrid')?.addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('viewList').classList.remove('active');
    const g = document.getElementById('servicesGrid');
    if(g) { g.classList.remove('services-list-view'); g.classList.add('services-grid'); }
});
document.getElementById('viewList')?.addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('viewGrid').classList.remove('active');
    const g = document.getElementById('servicesGrid');
    if(g) { g.classList.remove('services-grid'); g.classList.add('services-list-view'); }
});

// ── SEARCH / FILTER / SORT ───────────────────────────────────────────────
function applyFilters() {
    const search = (document.getElementById('serviceSearch').value || '').toLowerCase().trim();
    const status = document.getElementById('filterStatus').value;
    const cat    = document.getElementById('filterCategory').value;
    const sort   = document.getElementById('sortServices').value;

    const grid = document.getElementById('servicesGrid');
    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.service-card'));
    let visible = 0;

    cards.forEach(card => {
        const name   = card.dataset.name || '';
        const cStatus= card.dataset.status || '';
        const cCat   = card.dataset.category || '';
        const matches = (!search || name.includes(search))
                     && (!status || cStatus === status)
                     && (!cat    || cCat === cat);
        card.style.display = matches ? '' : 'none';
        if (matches) visible++;
    });

    document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';

    // Sort
    if (sort !== 'default') {
        const visCards = cards.filter(c => c.style.display !== 'none');
        visCards.sort((a, b) => {
            if (sort === 'name') return (a.dataset.name||'').localeCompare(b.dataset.name||'');
            if (sort === 'price-low') return parseFloat(a.dataset.price||0) - parseFloat(b.dataset.price||0);
            if (sort === 'price-high') return parseFloat(b.dataset.price||0) - parseFloat(a.dataset.price||0);
            if (sort === 'bookings') return parseInt(b.dataset.bookings||0) - parseInt(a.dataset.bookings||0);
            return 0;
        });
        visCards.forEach(c => grid.appendChild(c));
    }
}

['serviceSearch','filterStatus','filterCategory','sortServices'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', applyFilters);
    if (el && el.tagName === 'SELECT') el.addEventListener('change', applyFilters);
});

// ── MODAL TABS ─────────────────────────────────────────────────────────
function initModalTabs(tabsEl) {
    if (!tabsEl) return;
    tabsEl.querySelectorAll('.modal-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            tabsEl.querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            tabsEl.closest('.modal-body').querySelectorAll('.tab-pane-custom').forEach(p => {
                p.classList.toggle('active', p.id === target);
            });
        });
    });
}
initModalTabs(document.getElementById('addTabs'));
initModalTabs(document.getElementById('editTabs'));

// Reset tabs when modal opens
document.getElementById('addServiceModal')?.addEventListener('show.bs.modal', () => {
    const tabs = document.getElementById('addTabs');
    tabs?.querySelectorAll('.modal-tab-btn').forEach((b,i) => b.classList.toggle('active',i===0));
    document.querySelectorAll('#add-basic,#add-pricing,#add-images,#add-schedule,#add-advanced').forEach((p,i) => p.classList.toggle('active',i===0));
});

// ── NEGOTIABLE TOGGLE ───────────────────────────────────────────────────
function wireNegotiable(checkId, fieldsId) {
    const chk = document.getElementById(checkId);
    const flds = document.getElementById(fieldsId);
    if (!chk || !flds) return;
    chk.addEventListener('change', () => { flds.style.display = chk.checked ? 'block' : 'none'; });
}
wireNegotiable('addNegotiableCheck', 'addNegotiableFields');
wireNegotiable('editNegotiableCheck', 'editNegotiableFields');

// ── CONFIRM DELETE ───────────────────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('deleteServiceId').value = id;
    document.getElementById('confirmMsg').textContent = 'Permanently delete "' + name + '"? This cannot be undone.';
    document.getElementById('confirmOverlay').classList.add('active');
}
function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('active');
}
document.getElementById('confirmOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

// ── SERVICE DETAILS MODAL ───────────────────────────────────────────────
document.getElementById('serviceDetailsModal')?.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    if (!btn) return;
    const get = attr => btn.getAttribute('data-service-' + attr) || '';

    document.getElementById('detailServiceName').textContent = get('name');
    document.getElementById('detailDescription').textContent = get('description');
    document.getElementById('detailBookings').textContent = get('bookings');
    document.getElementById('detailCompleted').textContent = get('completed');
    const rating = parseFloat(get('rating'));
    document.getElementById('detailRating').textContent = rating > 0 ? rating.toFixed(1) + '★' : '—';
    document.getElementById('detailCategory').textContent = get('category');
    document.getElementById('detailPrice').textContent = 'RWF ' + parseInt(get('price')).toLocaleString();
    document.getElementById('detailDuration').textContent = get('duration') + ' minutes';
    document.getElementById('detailStatus').textContent = get('status').charAt(0).toUpperCase() + get('status').slice(1);
    document.getElementById('detailNegotiable').textContent = get('negotiable');
    const minP = get('min-price'), maxP = get('max-price');
    document.getElementById('detailPriceRange').textContent = (minP && maxP) ? 'RWF ' + parseInt(minP).toLocaleString() + ' – RWF ' + parseInt(maxP).toLocaleString() : 'N/A';
    document.getElementById('detailBookingMode').textContent = get('booking-mode') === 'instant' ? '⚡ Instant Booking' : 'Request Approval';
    document.getElementById('detailReviews').textContent = get('reviews') + ' reviews';
    const ptMap = {fixed_price:'Fixed Price',hourly_rate:'Per Hour',per_job_estimate:'Per Job (estimate)',per_day:'Per Day',per_service:'Per Service',base_price:'Base Price'};
    document.getElementById('detailPaymentType').textContent = ptMap[get('payment-type')] || get('payment-type');

    // Extras
    const extrasRaw = get('optional-extras');
    const extrasEl = document.getElementById('detailExtras');
    const extrasRow = document.getElementById('detailExtrasRow');
    if (extrasRaw) {
        try {
            const extras = JSON.parse(extrasRaw);
            extrasEl.textContent = extras.map(e => e.label + ' (+RWF ' + parseInt(e.price).toLocaleString() + ')').join(', ');
            extrasRow.style.display = '';
        } catch { extrasRow.style.display = 'none'; }
    } else { extrasRow.style.display = 'none'; }

    // AI Insights
    const aiData = btn.getAttribute('data-service-ai');
    if (aiData) {
        try {
            const ai = JSON.parse(aiData);
            const aiSection = document.getElementById('detailAIInsights');
            aiSection.style.display = 'block';

            // Performance Score Color
            const perfScore = ai.performance_score || 0;
            const perfEl = document.getElementById('detailPerfScore');
            let perfColor = '#ef4444'; // red
            if (perfScore >= 70) perfColor = '#10b981'; // green
            else if (perfScore >= 40) perfColor = '#f59e0b'; // yellow
            perfEl.textContent = perfScore.toFixed(1);
            perfEl.style.color = perfColor;

            // Demand Badge
            const demand = ai.demand_indicator || {};
            const demandEl = document.getElementById('detailDemandBadge');
            demandEl.innerHTML = '<i class="' + (demand.icon || 'fas fa-circle') + '" style="color:' + (demand.color || '#666') + ';margin-right:0.25rem;"></i>' + (demand.label || '—');
            demandEl.style.color = demand.color || '#666';

            // Conversion Rate
            const convEl = document.getElementById('detailConvRate');
            const convRate = ai.conversion_rate !== undefined ? ai.conversion_rate.toFixed(1) : '—';
            convEl.textContent = convRate;
            if (convRate !== '—') convEl.textContent = convRate + '%';

            // Tips
            const tips = ai.optimization_tips || [];
            const tipsEl = document.getElementById('detailTips');
            if (tips.length > 0) {
                const tipPriority = {high:'🔴',medium:'🟡',low:'🟢'};
                tipsEl.innerHTML = tips.slice(0, 3).map(t => '<div style="margin-bottom:0.5rem;"><span style="font-weight:700;">' + (tipPriority[t.priority] || '•') + ' ' + t.title + ':</span> ' + t.description + '</div>').join('');
            } else {
                tipsEl.textContent = 'No optimization tips available yet. Gather more bookings to unlock insights.';
            }
        } catch (e) { console.error('AI data parse error:', e); }
    } else {
        document.getElementById('detailAIInsights').style.display = 'none';
    }
});

// ── EDIT MODAL POPULATE ─────────────────────────────────────────────────
document.getElementById('editServiceModal')?.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    if (!btn) return;
    const get = attr => btn.getAttribute('data-service-' + attr) || '';

    document.getElementById('editServiceId').value = get('id');
    document.getElementById('editServiceName').value = get('name');
    document.getElementById('editServiceDescription').value = get('description');
    document.getElementById('editServicePrice').value = get('price');
    document.getElementById('editServiceDuration').value = get('duration');
    document.getElementById('editServiceStatus').value = get('status') || 'draft';
    document.getElementById('editServicePaymentType').value = get('payment-type') || 'fixed_price';
    document.getElementById('editServiceBookingMode').value = get('booking-mode') || 'request_approval';
    document.getElementById('editServiceAvailable').checked = get('available') === '1';
    document.getElementById('editImageAltText').value = get('image-alt-text') || '';

    // Negotiable
    const neg = get('negotiable') === '1';
    document.getElementById('editNegotiableCheck').checked = neg;
    document.getElementById('editNegotiableFields').style.display = neg ? 'block' : 'none';
    document.getElementById('editMinPrice').value = get('min-price');
    document.getElementById('editMaxPrice').value = get('max-price');

    // Days
    const days = (get('availability-days') || '1,2,3,4,5').split(',').map(d => parseInt(d.trim()));
    document.querySelectorAll('.edit-availability-day').forEach(cb => {
        cb.checked = days.includes(parseInt(cb.value));
    });

    // Time slots
    const slotsRaw = get('time-slots');
    const slotsEl = document.getElementById('editTimeSlots');
    slotsEl.value = '';
    if (slotsRaw) {
        try {
            const slots = JSON.parse(slotsRaw);
            if (Array.isArray(slots)) slotsEl.value = slots.map(s => s.start + '-' + s.end).join('\n');
        } catch { slotsEl.value = slotsRaw; }
    }

    // Extras
    const extrasRaw = get('optional-extras');
    const extrasEl = document.getElementById('editOptionalExtras');
    extrasEl.value = '';
    if (extrasRaw) {
        try {
            const extras = JSON.parse(extrasRaw);
            if (Array.isArray(extras)) extrasEl.value = extras.map(e => e.label + ' (+' + parseInt(e.price) + ')').join('\n');
        } catch {}
    }

    // Reset to first tab
    const editTabs = document.getElementById('editTabs');
    editTabs?.querySelectorAll('.modal-tab-btn').forEach((b,i) => b.classList.toggle('active',i===0));
    document.querySelectorAll('#edit-basic,#edit-pricing,#edit-images,#edit-schedule,#edit-advanced').forEach((p,i) => p.classList.toggle('active',i===0));

    // Load existing images if any
    const mainImageField = document.getElementById('editServiceImage');
    const mainImagePreview = document.getElementById('editMainImagePreview');
    const mainImageImg = document.getElementById('editMainImageImg');

    // Note: Image paths would need to be loaded from data attributes
    // This is a placeholder for where you'd load existing service images
});

// ── AI RECOMMENDATIONS ─────────────────────────────────────────────────
let currentAIData = null;

function showPriceComparison(e) {
    e?.preventDefault();
    if (!currentAIData?.price_suggestion) {
        showToast('Price data not available', 'info');
        return;
    }
    const ps = currentAIData.price_suggestion;
    const msg = 'Your Price: RWF ' + parseInt(ps.suggested_price).toLocaleString() + '\n' +
                'Market Average: RWF ' + parseInt(ps.market_avg).toLocaleString() + '\n' +
                'Range: RWF ' + parseInt(ps.market_min).toLocaleString() + ' – RWF ' + parseInt(ps.market_max).toLocaleString() + '\n\n' +
                ps.recommendation;
    showToast(msg.replace(/\n/g, '<br>'), 'info');
}

function showAISuggestions(e) {
    e?.preventDefault();
    if (!currentAIData) {
        showToast('AI suggestions not available', 'info');
        return;
    }
    const ai = currentAIData;
    const tips = (ai.optimization_tips || []).slice(0, 3);
    if (tips.length === 0) {
        showToast('No optimization tips available yet', 'info');
        return;
    }
    let html = '<div style="text-align:left;">';
    tips.forEach((tip, i) => {
        const priority = {'high':'🔴','medium':'🟡','low':'🟢'}[tip.priority] || '•';
        html += '<div style="margin-bottom:0.75rem;padding:0.75rem;background:rgba(0,0,0,0.05);border-radius:4px;">' +
                '<div style="font-weight:700;margin-bottom:0.25rem;">' + priority + ' ' + tip.title + '</div>' +
                '<div style="font-size:0.85rem;color:#666;">' + tip.description + '</div></div>';
    });
    html += '</div>';
    showToast(html, 'info');
}

// Update currentAIData when details modal is shown
document.getElementById('serviceDetailsModal')?.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    if (btn) {
        const aiStr = btn.getAttribute('data-service-ai');
        if (aiStr) {
            try {
                currentAIData = JSON.parse(aiStr);
                document.getElementById('detailPriceCompareBtn').style.display =
                    (currentAIData?.price_suggestion) ? 'inline-block' : 'none';
                document.getElementById('detailGetSuggestionsBtn').style.display =
                    (currentAIData?.optimization_tips?.length > 0) ? 'inline-block' : 'none';
            } catch(e) { console.error('Failed to parse AI data:', e); }
        }
    }
});

// ── FORM VALIDATION ────────────────────────────────────────────────────
document.getElementById('serviceForm')?.addEventListener('submit', function(e) {
    const price = parseFloat(this.querySelector('input[name="price"]').value);
    const duration = parseInt(this.querySelector('input[name="duration"]').value);
    if (price < 0) { e.preventDefault(); showToast('Price cannot be negative','warning'); return; }
    if (duration < 15) { e.preventDefault(); showToast('Duration must be at least 15 minutes','warning'); return; }
});

// ── IMAGE UPLOAD HANDLING ──────────────────────────────────────────────
function initializeImageUploads() {
    // Main image uploads
    ['mainImageUpload', 'editMainImageUpload'].forEach(id => {
        const area = document.getElementById(id);
        const input = document.getElementById(id.replace('Upload', ''));
        const preview = document.getElementById(id.replace('Upload', 'Preview'));
        const img = document.getElementById(id.replace('Upload', 'Img'));

        if (area && input && preview && img) {
            // Click to upload
            area.addEventListener('click', () => input.click());

            // Drag and drop
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });
            area.addEventListener('dragleave', () => area.classList.remove('dragover'));
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelection(files[0], input, preview, img);
                }
            });

            // File input change
            input.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelection(e.target.files[0], input, preview, img);
                }
            });
        }
    });

    // Additional images uploads
    ['additionalImagesUpload', 'editAdditionalImagesUpload'].forEach(id => {
        const area = document.getElementById(id);
        const input = document.getElementById(id.replace('Upload', ''));
        const preview = document.getElementById(id.replace('Upload', 'Preview'));

        if (area && input && preview) {
            // Click to upload
            area.addEventListener('click', () => input.click());

            // Drag and drop
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });
            area.addEventListener('dragleave', () => area.classList.remove('dragover'));
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                const files = Array.from(e.dataTransfer.files);
                handleMultipleFileSelection(files, input, preview);
            });

            // File input change
            input.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                handleMultipleFileSelection(files, input, preview);
            });
        }
    });
}

function handleFileSelection(file, input, preview, img) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showToast('Please select a valid image file (JPG, PNG, or WebP)', 'warning');
        return;
    }

    // Validate file size (4MB)
    const maxSize = 4 * 1024 * 1024;
    if (file.size > maxSize) {
        showToast('File size must be less than 4MB', 'warning');
        return;
    }

    // Show preview
    const reader = new FileReader();
    reader.onload = (e) => {
        img.src = e.target.result;
        preview.style.display = 'block';
        // Hide placeholder
        preview.parentElement.querySelector('.upload-placeholder').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function handleMultipleFileSelection(files, input, preview) {
    const maxFiles = 5;
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const maxSize = 4 * 1024 * 1024;

    // Filter valid files
    const validFiles = files.filter(file => {
        if (!allowedTypes.includes(file.type)) {
            showToast(`Skipping ${file.name}: Invalid file type`, 'warning');
            return false;
        }
        if (file.size > maxSize) {
            showToast(`Skipping ${file.name}: File too large (max 4MB)`, 'warning');
            return false;
        }
        return true;
    });

    if (validFiles.length > maxFiles) {
        showToast(`You can upload up to ${maxFiles} images`, 'warning');
        validFiles.splice(maxFiles);
    }

    // Clear existing previews
    preview.innerHTML = '';

    // Create previews
    validFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const item = document.createElement('div');
            item.className = 'additional-image-item';
            item.innerHTML = `
                <img src="${e.target.result}" alt="Preview ${index + 1}">
                <button type="button" class="btn-remove-image" onclick="removeAdditionalImage(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    });

    // Update input files
    const dt = new DataTransfer();
    validFiles.forEach(file => dt.items.add(file));
    input.files = dt.files;
}

function removeImage(previewId, inputId) {
    const preview = document.getElementById(previewId);
    const input = document.getElementById(inputId);
    const placeholder = preview.parentElement.querySelector('.upload-placeholder');

    preview.style.display = 'none';
    placeholder.style.display = 'block';
    input.value = '';
}

function removeAdditionalImage(button) {
    const item = button.parentElement;
    const preview = item.parentElement;
    const input = document.getElementById(preview.id.replace('Preview', ''));

    item.remove();

    // Update input files
    const remainingItems = preview.querySelectorAll('.additional-image-item');
    const dt = new DataTransfer();

    // We can't easily reconstruct the FileList from remaining previews,
    // so we'll just clear the input and let user re-select
    input.value = '';
}

// Initialize image uploads when page loads
document.addEventListener('DOMContentLoaded', initializeImageUploads);
</script>

<!-- Upgrade Plan Modal -->
<?php if (!$can_add_service): ?>
<div class="modal fade" id="upgradePlanModal" tabindex="-1" aria-labelledby="upgradePlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="upgradePlanModalLabel">Upgrade Your Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-4">
                    <i class="fas fa-lock" style="font-size: 3rem; color: #dc3545;"></i>
                </div>
                <h4>Service Limit Reached</h4>
                <p class="text-muted">
                    You have reached your limit of <strong><?php echo $service_limit; ?></strong> services 
                    (currently using <?php echo $current_service_count; ?>).
                </p>
                <p>Upgrade your plan to create more services and unlock additional features.</p>
                <a href="select-plan.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-arrow-up"></i> View Plans
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>