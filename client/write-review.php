<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/client_header.php';
require_once '../includes/mailer.php';

// Check if user is logged in and is a client
if (!isLoggedIn()) {
    redirect('../login.php');
}

if (isProvider()) {
    redirect('../provider/dashboard.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

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
    
    // Review system settings
    'require_rating_after_completion' => getSetting($db, 'require_rating_after_completion', '0'),
    'allow_review_editing' => getSetting($db, 'allow_review_editing', '1'),
    'allow_review_deletion' => getSetting($db, 'allow_review_deletion', '1'),
    'min_review_length' => intval(getSetting($db, 'min_review_length', '10')),
    'max_review_length' => intval(getSetting($db, 'max_review_length', '1000')),
    
    // Notification settings
    'enable_email_notifications' => getSetting($db, 'enable_email_notifications', '1'),
    'enable_sms_notifications' => getSetting($db, 'enable_sms_notifications', '0'),
    
    // Booking settings
    'auto_cancel_unconfirmed' => getSetting($db, 'auto_cancel_unconfirmed', '1'),
];

// Get provider ID from query string
$provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$provider_id) {
    redirect('client/home.php');
}

// Get provider details
$stmt = $db->prepare("
    SELECT sp.*, u.full_name, u.profile_image, u.email, u.phone
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.id = ?
");
$stmt->execute([$provider_id]);
$provider = $stmt->fetch();

if (!$provider) {
    redirect('client/home.php');
}

// Check if user has already reviewed this provider
// REPLACE the old provider-level-only check with booking-aware logic
if ($booking_id) {
    $stmt = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND booking_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $booking_id]);
    $existing_review = $stmt->fetch();
    if (!$existing_review) {
        // fallback to provider-level if you still want to prevent multiple reviews across bookings
        $stmt = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND provider_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'], $provider_id]);
        $existing_review = $stmt->fetch();
    }
} else {
    $stmt = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND provider_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $provider_id]);
    $existing_review = $stmt->fetch();
}

// Get booking details if booking_id provided
$booking = null;
if ($booking_id) {
    $stmt = $db->prepare("
        SELECT * FROM bookings 
        WHERE id = ? AND client_id = ? AND provider_id = ? AND status = 'completed'
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id'], $provider_id]);
    $booking = $stmt->fetch();
    
    // Check if review is required for this booking
    if ($system_settings['require_rating_after_completion'] && !$booking) {
        $errors[] = "Invalid booking or booking not completed";
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    // Ensure we have provider_id / booking_id from POST (in case query string wasn't preserved)
    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : $provider_id;
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : $booking_id;

    $rating = intval($_POST['rating']);
    $comment = sanitize($_POST['comment']);
    
    // Validation
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating between 1 and 5 stars";
    }
    
    if (empty($comment)) {
        $errors[] = "Please write a comment about your experience";
    }
    
    if (strlen($comment) < $system_settings['min_review_length']) {
        $errors[] = "Review comment must be at least {$system_settings['min_review_length']} characters long";
    }
    
    if (strlen($comment) > $system_settings['max_review_length']) {
        $errors[] = "Review comment must not exceed {$system_settings['max_review_length']} characters";
    }
    
    // Re-check duplicate review by booking if booking_id present
    if (empty($errors)) {
        if ($booking_id) {
            $stmt = $db->prepare("SELECT id FROM reviews WHERE client_id = ? AND booking_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $booking_id]);
            if ($stmt->fetch()) {
                $errors[] = "You have already reviewed this booking.";
            }
        } elseif ($existing_review) {
            $errors[] = "You have already reviewed this provider";
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert review
            $stmt = $db->prepare("
                INSERT INTO reviews (client_id, provider_id, booking_id, rating, comment)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $provider_id, 
                $booking_id ?: null, 
                $rating, 
                $comment
            ]);
            
            // Update provider's average rating and total reviews
            $stmt = $db->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                FROM reviews
                WHERE provider_id = ?
            ");
            $stmt->execute([$provider_id]);
            $stats = $stmt->fetch();
            
            $stmt = $db->prepare("
                UPDATE service_providers 
                SET average_rating = ?, total_reviews = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                round($stats['avg_rating'], 2),
                $stats['total_reviews'],
                $provider_id
            ]);
            
            // Mark booking as reviewed if applicable
            if ($booking_id) {
                // Only attempt to update if column exists to avoid SQL errors on older schemas
                try {
                    $colStmt = $db->prepare("SHOW COLUMNS FROM bookings LIKE 'is_reviewed'");
                    $colStmt->execute();
                    $hasIsReviewed = (bool) $colStmt->fetch();
                } catch (Throwable $e) {
                    $hasIsReviewed = false;
                }

                if ($hasIsReviewed) {
                    $stmt = $db->prepare("UPDATE bookings SET is_reviewed = 1 WHERE id = ?");
                    $stmt->execute([$booking_id]);
                } else {
                    // Optional: log that the column is missing for future migration
                    error_log("write-review: bookings.is_reviewed column missing, skip update for booking_id={$booking_id}");
                }
            }
            
            $db->commit();
            
            $success = "Thank you! Your review has been submitted successfully.";
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'review_created', "Submitted review for provider #{$provider_id}");
            
            // Send email notification to provider if enabled
            if ($system_settings['enable_email_notifications']) {
                require_once '../includes/mailer.php';
                try {
                    $subject = "New Review Received - {$system_settings['platform_name']}";
                    $message = "
                        <p>Hello {$provider['full_name']},</p>
                        <p>You have received a new {$rating}-star review from {$_SESSION['user_name']}!</p>
                        <p><strong>Review:</strong> {$comment}</p>
                        <p>View your reviews in your dashboard to see all feedback.</p>
                        <p>Best regards,<br>{$system_settings['platform_name']} Team</p>
                    ";
                    // Use Mailer::send (static helper) from includes/mailer.php
                    Mailer::send($provider['email'], $subject, $message, true);
                } catch (Throwable $e) {
                    error_log("Review email send error: " . $e->getMessage());
                }
            }
            
            // Send SMS notification if enabled (placeholder for SMS integration)
            if ($system_settings['enable_sms_notifications'] && !empty($provider['phone'])) {
                // SMS integration would go here
                // sendSMS($provider['phone'], "You received a new {$rating}-star review on {$system_settings['platform_name']}!");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to submit review. Please try again.";
            error_log("Review submission error: " . $e->getMessage());
        }
    }
}

// Get provider's existing reviews
$stmt = $db->prepare("
    SELECT r.*, u.full_name as client_name, u.profile_image
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$provider_id]);
$existing_reviews = $stmt->fetchAll();

// Check if review is mandatory
$is_review_mandatory = $system_settings['require_rating_after_completion'] && $booking_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Review - <?php echo $system_settings['platform_name']; ?></title>
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
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border-radius: 12px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .card h3 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card h3 i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        /* Review Container */
        .review-container {
            max-width: 900px;
        }
        
        /* Provider Card */
        .provider-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .provider-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
        
        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .provider-info h2 {
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .provider-info p {
            color: #64748b;
            margin: 0.5rem 0;
        }
        
        .provider-info .profession {
            font-size: 1.05rem;
            color: var(--primary);
            font-weight: 600;
        }
        
        .provider-rating {
            color: #fbbf24;
            margin-top: 0.5rem;
        }
        
        /* Form Sections */
        .rating-input {
            margin-bottom: 2rem;
        }
        
        .rating-input label {
            display: block;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 0.75rem;
            font-size: 2.5rem;
        }
        
        .star-rating input[type="radio"] {
            display: none;
        }
        
        .star-rating label {
            cursor: pointer;
            color: #e5e7eb;
            transition: all 0.2s;
            margin: 0;
        }
        
        .star-rating label:hover,
        .star-rating input[type="radio"]:checked ~ label {
            color: #fbbf24;
            transform: scale(1.1);
        }
        
        .rating-description {
            margin-top: 1rem;
            font-size: 1.05rem;
            color: #64748b;
            font-weight: 600;
            min-height: 30px;
        }
        
        .comment-group {
            margin-bottom: 1.5rem;
        }
        
        .comment-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .comment-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 150px;
            transition: border-color 0.3s;
        }
        
        .comment-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        .char-count {
            text-align: right;
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .submit-section {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        
        .btn-submit, .btn-cancel {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-cancel {
            background: white;
            color: #64748b;
            border: 2px solid #e5e7eb;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f8f9fa;
        }
        
        /* Existing Reviews */
        .existing-reviews h3 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .existing-reviews h3 i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .review-item {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }
        
        .reviewer-info {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .reviewer-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .reviewer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .reviewer-details strong {
            display: block;
            color: var(--dark);
        }
        
        .review-stars {
            color: #fbbf24;
            font-size: 0.95rem;
            margin-top: 0.25rem;
        }
        
        .review-date {
            color: #94a3b8;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .review-item p {
            color: #64748b;
            margin-top: 1rem;
            margin-bottom: 0;
            line-height: 1.6;
        }
        
        /* Success Message */
        .success-message {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.5rem;
        }
        
        .success-message h2 {
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .success-message p {
            color: #64748b;
            margin: 1rem 0;
        }
        
        /* Booking Context */
        .booking-context {
            background: #f9fafb;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .booking-context strong {
            display: block;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .booking-context p {
            margin: 0.5rem 0 0 0;
            color: #64748b;
            font-size: 0.95rem;
        }
        
        /* System Notices */
        .system-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #856404;
        }

        .system-notice strong {
            display: block;
            margin-bottom: 0.5rem;
        }

        .system-notice ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.5rem;
        }

        .system-notice li {
            margin: 0.25rem 0;
        }

        .mandatory-notice {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #991b1b;
        }

        .mandatory-notice strong {
            display: block;
            margin-bottom: 0.25rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-danger a {
            color: inherit;
            font-weight: 600;
        }
        
        .alert p {
            margin: 0.5rem 0;
        }
        
        .alert p:first-child {
            margin-top: 0;
        }
        
        .alert p:last-child {
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .provider-card {
                flex-direction: column;
                text-align: center;
            }
            
            .provider-avatar {
                width: 100px;
                height: 100px;
            }
            
            .star-rating {
                justify-content: center;
            }
            
            .submit-section {
                flex-direction: column;
            }
            
            .btn-submit, .btn-cancel {
                width: 100%;
            }
            
            .review-header {
                flex-direction: column;
            }
            
            .page-header {
                padding: 1.5rem 1rem;
            }
            
            .page-header h1 {
                font-size: 1.4rem;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
        }
<?php client_header_render_styles(); ?>
</head>
<body>
    <?php client_header_render_markup(basename($_SERVER['PHP_SELF'])); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <i class="fas fa-star"></i>
            <h1>Write Review</h1>
        </div>

        <div class="review-container">
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="card success-message">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Review Submitted Successfully!</h2>
                <p>Thank you for taking the time to share your experience. Your feedback helps others make informed decisions.</p>
                
                <?php if ($system_settings['enable_email_notifications']): ?>
                    <p>
                        <i class="fas fa-envelope"></i> The provider has been notified of your review.
                    </p>
                <?php endif; ?>
                
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn btn-primary" style="background: linear-gradient(135deg, var(--primary), #0a58ca); border: none;">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                    <a href="provider-profile.php?id=<?php echo $provider_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-user"></i> View Provider
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Provider Info Card -->
            <div class="provider-card">
                <div class="provider-avatar">
                    <?php if ($provider['profile_image']): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($provider['profile_image']); ?>" alt="">
                    <?php else: ?>
                        <?php echo strtoupper(substr($provider['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="provider-info">
                    <h2><?php echo htmlspecialchars($provider['full_name']); ?></h2>
                    <p class="profession">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($provider['profession']); ?>
                    </p>
                    <p>
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($provider['location']); ?>
                    </p>
                    <div class="provider-rating">
                        <?php 
                        $rating = $provider['average_rating'];
                        for ($i = 1; $i <= 5; $i++): 
                            echo $i <= $rating ? '<i class="fas fa-star"></i> ' : '<i class="far fa-star"></i> ';
                        endfor; ?>
                        <span style="color: #64748b; margin-left: 0.5rem;">
                            <?php echo number_format($provider['average_rating'], 1); ?> 
                            (<?php echo $provider['total_reviews']; ?> reviews)
                        </span>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($existing_review): ?>
                <div class="alert alert-danger">
                    <p>
                        <i class="fas fa-info-circle"></i> You have already reviewed this provider. 
                        <?php if ($system_settings['allow_review_editing']): ?>
                            <a href="my-reviews.php" style="color: inherit; text-decoration: underline;">Edit your review</a>
                        <?php else: ?>
                            <a href="my-reviews.php" style="color: inherit; text-decoration: underline;">View your reviews</a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Review Form -->
                <div class="card">
                    <h3><i class="fas fa-star"></i> Share Your Experience</h3>

                    <?php if ($is_review_mandatory): ?>
                        <div class="mandatory-notice">
                            <strong><i class="fas fa-exclamation-circle"></i> Review Required</strong>
                            You must submit a review for this completed booking.
                        </div>
                    <?php endif; ?>

                    <?php if ($booking): ?>
                        <div class="booking-context">
                            <strong><i class="fas fa-info-circle"></i> Service Details</strong>
                            <p>
                                <?php echo htmlspecialchars($booking['service_description']); ?>
                            </p>
                            <p style="font-size: 0.9rem;">
                                Completed on: <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Review Guidelines -->
                    <div class="system-notice">
                        <strong><i class="fas fa-info-circle"></i> Review Guidelines</strong>
                        <ul>
                            <li>Minimum <?php echo $system_settings['min_review_length']; ?> characters required</li>
                            <li>Maximum <?php echo $system_settings['max_review_length']; ?> characters allowed</li>
                            <li>Be honest and constructive in your feedback</li>
                            <?php if (!$system_settings['allow_review_editing']): ?>
                                <li>Reviews cannot be edited after submission</li>
                            <?php endif; ?>
                            <?php if (!$system_settings['allow_review_deletion']): ?>
                                <li>Reviews cannot be deleted after submission</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <form method="POST" id="reviewForm">
                        <input type="hidden" name="provider_id" value="<?php echo $provider_id; ?>">
                        <?php if ($booking_id): ?>
                            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        <?php endif; ?>

                        <!-- Rating -->
                        <div class="rating-input">
                            <label>How would you rate this service provider? <span style="color: var(--danger);">*</span></label>
                            <div class="star-rating">
                                <input type="radio" name="rating" value="5" id="star5" required>
                                <label for="star5"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="4" id="star4">
                                <label for="star4"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="3" id="star3">
                                <label for="star3"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="2" id="star2">
                                <label for="star2"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="1" id="star1">
                                <label for="star1"><i class="fas fa-star"></i></label>
                            </div>
                            <div class="rating-description" id="ratingDescription">
                                Select a rating
                            </div>
                        </div>

                        <!-- Comment -->
                        <div class="comment-group">
                            <label>Share your experience <span style="color: var(--danger);">*</span></label>
                            <textarea 
                                name="comment" 
                                id="reviewComment"
                                required
                                placeholder="Tell us about your experience with this provider. What did you like? What could be improved?"
                                minlength="<?php echo $system_settings['min_review_length']; ?>"
                                maxlength="<?php echo $system_settings['max_review_length']; ?>"
                            ></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> / <?php echo $system_settings['max_review_length']; ?> characters
                                (minimum <?php echo $system_settings['min_review_length']; ?>)
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="submit-section">
                            <?php if (!$is_review_mandatory): ?>
                                <a href="dashboard.php" class="btn-cancel">Cancel</a>
                            <?php endif; ?>
                            <button type="submit" name="submit_review" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> 
                                <?php echo $is_review_mandatory ? 'Submit Required Review' : 'Submit Review'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Existing Reviews -->
            <?php if (!empty($existing_reviews)): ?>
                <div class="card existing-reviews">
                    <h3><i class="fas fa-comments"></i> Recent Reviews</h3>
                    <?php foreach ($existing_reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php if ($review['profile_image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($review['profile_image']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($review['client_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                                        <div class="review-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="review-date">
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    <?php if ($review['updated_at'] && $review['updated_at'] !== $review['created_at']): ?>
                                        <br><small style="color: #94a3b8;">(edited)</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p>
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // Rating description update
        const ratingDescriptions = {
            '5': '⭐ Excellent - Outstanding service!',
            '4': '⭐ Very Good - Great experience',
            '3': '⭐ Good - Satisfactory service',
            '2': '⭐ Fair - Needs improvement',
            '1': '⭐ Poor - Very disappointed'
        };

        document.querySelectorAll('input[name="rating"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const description = document.getElementById('ratingDescription');
                description.textContent = ratingDescriptions[this.value];
                description.style.color = this.value >= 4 ? '#10b981' : (this.value >= 3 ? '#f59e0b' : '#ef4444');
            });
        });

        // Character counter
        const textarea = document.getElementById('reviewComment');
        if (textarea) {
            const charCount = document.getElementById('charCount');
            const minLength = <?php echo $system_settings['min_review_length']; ?>;
            const maxLength = <?php echo $system_settings['max_review_length']; ?>;

            textarea.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = length;
                
                if (length < minLength) {
                    charCount.style.color = '#ef4444';
                } else if (length > maxLength - 50) {
                    charCount.style.color = '#f59e0b';
                } else if (length >= maxLength) {
                    charCount.style.color = '#ef4444';
                } else {
                    charCount.style.color = '#10b981';
                }
            });

            // Form validation
            document.getElementById('reviewForm').addEventListener('submit', function(e) {
                const rating = document.querySelector('input[name="rating"]:checked');
                const comment = textarea.value.trim();
                const isMandatory = <?php echo $is_review_mandatory ? 'true' : 'false'; ?>;
                const canEdit = <?php echo $system_settings['allow_review_editing'] ? 'true' : 'false'; ?>;
                const canDelete = <?php echo $system_settings['allow_review_deletion'] ? 'true' : 'false'; ?>;

                if (!rating) {
                    e.preventDefault();
                    alert('Please select a rating');
                    return false;
                }

                if (comment.length < minLength) {
                    e.preventDefault();
                    alert(`Please write at least ${minLength} characters in your review`);
                    return false;
                }

                if (comment.length > maxLength) {
                    e.preventDefault();
                    alert(`Review comment cannot exceed ${maxLength} characters`);
                    return false;
                }

                // Confirm submission
                let confirmMessage = 'Are you sure you want to submit this review?';
                if (!canEdit) {
                    confirmMessage += '\n\nNote: Reviews cannot be edited after submission.';
                }
                if (!canDelete) {
                    confirmMessage += '\nNote: Reviews cannot be deleted after submission.';
                }

                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
            });

            // Auto-focus on textarea
            textarea.focus();
        }

    </script>
<?php client_header_render_scripts(); ?>
</body>
</html>