<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';

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
    SELECT sp.*, u.full_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'recent';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for reviews
$sql = "
    SELECT r.*, 
           u.full_name as client_name, 
           u.profile_image as client_image,
           u.email as client_email,
           b.service_description,
           b.preferred_date
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    JOIN bookings b ON r.booking_id = b.id
    WHERE r.provider_id = ?
";

$params = [$provider['id']];

if ($rating_filter > 0) {
    $sql .= " AND r.rating = ?";
    $params[] = $rating_filter;
}

// Get total count
$count_sql = "
    SELECT COUNT(*) as total
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    JOIN bookings b ON r.booking_id = b.id
    WHERE r.provider_id = ?
";

if ($rating_filter > 0) {
    $count_sql .= " AND r.rating = ?";
}

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_result = $count_stmt->fetch();

$total_reviews = $total_result && isset($total_result['total']) ? (int)$total_result['total'] : 0;
$total_pages = ceil($total_reviews / $per_page);

// Sorting
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY r.created_at ASC";
        break;
    case 'highest':
        $sql .= " ORDER BY r.rating DESC, r.created_at DESC";
        break;
    case 'lowest':
        $sql .= " ORDER BY r.rating ASC, r.created_at DESC";
        break;
    default: // recent
        $sql .= " ORDER BY r.created_at DESC";
}

// Add pagination
$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM reviews
    WHERE provider_id = ?
";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute([$provider['id']]);
$stats = $stats_stmt->fetch();

// Handle review response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_response'])) {
    $review_id = intval($_POST['review_id']);
    $response = sanitize($_POST['response']);
    
    // Verify review belongs to provider
    $stmt = $db->prepare("SELECT * FROM reviews WHERE id = ? AND provider_id = ?");
    $stmt->execute([$review_id, $provider['id']]);
    
    if ($stmt->fetch()) {
        $stmt = $db->prepare("UPDATE reviews SET provider_response = ?, response_date = NOW() WHERE id = ?");
        if ($stmt->execute([$response, $review_id])) {
            $success = __('reviews.response_submitted', [], 'dashboard');
            
            // Send notification if enabled
            if (isEmailNotificationsEnabled()) {
                require_once '../includes/mailer.php';
                
                // Get review details for notification
                $stmt = $db->prepare("
                    SELECT u.email, u.full_name, r.rating, r.comment
                    FROM reviews r
                    JOIN users u ON r.client_id = u.id
                    WHERE r.id = ?
                ");
                $stmt->execute([$review_id]);
                $review_details = $stmt->fetch();
                
                if ($review_details) {
                    $subject = __('email.review_response_subject', ['platform' => getPlatformName()], 'email');
                    $message = "
                        <p>" . __('email.hello', ['name' => $review_details['full_name']], 'email') . "</p>
                        <p>" . __('email.review_response_intro', [], 'email') . "</p>
                        <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <strong>" . __('email.your_review_label', [], 'email') . ":</strong><br>
                            " . __('email.rating_label', [], 'email') . ": {$review_details['rating']} " . __('reviews.stars', [], 'dashboard') . "<br>
                            " . __('email.comment_label', [], 'email') . ": {$review_details['comment']}
                        </div>
                        <div style='background: #e0e7ff; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <strong>" . __('email.provider_response_label', [], 'email') . ":</strong><br>
                            {$response}
                        </div>
                        <p>" . __('email.thank_you_feedback', [], 'email') . "</p>
                    ";
                    
                    Mailer::sendAnnouncement(
                        $review_details['email'],
                        $review_details['full_name'],
                        $subject,
                        $message
                    );
                }
            }
        } else {
            $errors[] = __('reviews.response_failed', [], 'dashboard');
        }
    } else {
        $errors[] = __('reviews.invalid_review', [], 'dashboard');
    }
}

// Check if rating is required after completion
$rating_required = isRatingRequiredAfterCompletion();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('reviews.title', [], 'dashboard'); ?> - <?php echo getPlatformName(); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Dark Mode CSS -->
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
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

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --primary: #3b82f6;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --light: #1e293b;
            --dark: #f1f5f9;
        }

        [data-theme="dark"] body {
            background-color: #0f172a;
            color: #f1f5f9;
        }

        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary, #0f1117);
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
        
        /* Stats Overview */
        .stats-overview {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 3rem;
            align-items: center;
        }
        
        .rating-summary {
            text-align: center;
            padding: 1rem;
        }
        
        .rating-number {
            font-size: 3.5rem;
            font-weight: bold;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .rating-count {
            color: var(--secondary);
            font-size: 1rem;
        }
        
        .rating-breakdown {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .rating-label {
            width: 70px;
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .rating-bar {
            flex: 1;
            height: 10px;
            background: #f1f5f9;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffc107, #ffb300);
            transition: width 0.3s;
        }
        
        .rating-count-num {
            width: 50px;
            text-align: right;
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Filters Card */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        /* Reviews Container */
        .reviews-container {
            display: grid;
            gap: 1.5rem;
        }
        
        .review-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: none;
        }
        
        .review-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .client-info {
            display: flex;
            gap: 1rem;
            flex: 1;
        }
        
        .client-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .client-details h5 {
            margin-bottom: 0.25rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .client-details p {
            color: var(--secondary);
            font-size: 0.875rem;
            margin: 0.1rem 0;
        }
        
        .review-rating {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .stars {
            color: #ffc107;
            font-size: 1.1rem;
        }
        
        .review-date {
            color: #94a3b8;
            font-size: 0.8rem;
        }
        
        .review-content {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
        }
        
        .review-content p {
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
        }
        
        .response-section {
            padding: 1rem 1.25rem;
            background: #e0e7ff;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;
            margin-top: 1rem;
        }
        
        .response-label {
            font-weight: 600;
            color: #4f46e5;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .response-form {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #dee2e6;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--secondary);
            margin-bottom: 1.5rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            text-decoration: none;
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .review-header {
                flex-direction: column;
            }
            
            .review-rating {
                align-items: flex-start;
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
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .form-select, .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .service-info {
            background: #f1f5f9;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .service-info strong {
            color: var(--dark);
        }
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
                <strong><?php echo __('reviews.maintenance_mode', [], 'dashboard'); ?></strong>
                <p class="mb-0 mt-2"><?php echo __('reviews.maintenance_message', [], 'dashboard'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Success/Error Messages -->
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

        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-star"></i> <?php echo __('reviews.title', [], 'dashboard'); ?></h1>
            <p><?php echo __('reviews.subtitle', [], 'dashboard'); ?></p>
            <?php if ($rating_required): ?>
                <div class="alert alert-info mt-2 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?php echo __('reviews.rating_required_label', [], 'dashboard'); ?></strong> <?php echo __('reviews.rating_required_message', [], 'dashboard'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rating Overview -->
        <div class="stats-overview">
            <div class="stats-grid">
                <div class="rating-summary">
                    <div class="rating-number">
                        <?php echo $stats['total'] > 0 ? number_format($stats['avg_rating'], 1) : '0.0'; ?>
                    </div>
                    <div class="rating-stars">
                        <?php 
                        $avg = $stats['avg_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++): 
                            if ($i <= floor($avg)) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i == ceil($avg) && $avg - floor($avg) >= 0.5) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        endfor; 
                        ?>
                    </div>
                    <div class="rating-count">
                        <?php echo $stats['total']; ?> <?php echo $stats['total'] == 1 ? __('reviews.total_review_singular', [], 'dashboard') : __('reviews.total_review_plural', [], 'dashboard'); ?>
                    </div>
                </div>

                <div class="rating-breakdown">
                    <?php
                    $total = $stats['total'] > 0 ? $stats['total'] : 1;
                    for ($i = 5; $i >= 1; $i--):
                        $count = $stats[($i == 5 ? 'five' : ($i == 4 ? 'four' : ($i == 3 ? 'three' : ($i == 2 ? 'two' : 'one')))) . '_star'] ?? 0;
                        $percentage = ($count / $total) * 100;
                    ?>
                    <div class="rating-bar-item">
                        <div class="rating-label">
                            <?php echo $i; ?> <?php echo __('reviews.stars', [], 'dashboard'); ?>
                        </div>
                        <div class="rating-bar">
                            <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                        <div class="rating-count-num">
                            <?php echo $count; ?> (<?php echo number_format($percentage, 0); ?>%)
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="reviews.php">
                <div class="filters-grid">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('reviews.filter_by_rating', [], 'dashboard'); ?></label>
                        <select name="rating" class="form-select">
                            <option value="0"><?php echo __('reviews.all_ratings', [], 'dashboard'); ?></option>
                            <option value="5" <?php echo $rating_filter === 5 ? 'selected' : ''; ?>>5 <?php echo __('reviews.stars', [], 'dashboard'); ?> (<?php echo $stats['five_star'] ?? 0; ?>)</option>
                            <option value="4" <?php echo $rating_filter === 4 ? 'selected' : ''; ?>>4 <?php echo __('reviews.stars', [], 'dashboard'); ?> (<?php echo $stats['four_star'] ?? 0; ?>)</option>
                            <option value="3" <?php echo $rating_filter === 3 ? 'selected' : ''; ?>>3 <?php echo __('reviews.stars', [], 'dashboard'); ?> (<?php echo $stats['three_star'] ?? 0; ?>)</option>
                            <option value="2" <?php echo $rating_filter === 2 ? 'selected' : ''; ?>>2 <?php echo __('reviews.stars', [], 'dashboard'); ?> (<?php echo $stats['two_star'] ?? 0; ?>)</option>
                            <option value="1" <?php echo $rating_filter === 1 ? 'selected' : ''; ?>>1 <?php echo __('reviews.star', [], 'dashboard'); ?> (<?php echo $stats['one_star'] ?? 0; ?>)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('reviews.sort_by', [], 'dashboard'); ?></label>
                        <select name="sort" class="form-select">
                            <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>><?php echo __('reviews.sort_recent', [], 'dashboard'); ?></option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>><?php echo __('reviews.sort_oldest', [], 'dashboard'); ?></option>
                            <option value="highest" <?php echo $sort === 'highest' ? 'selected' : ''; ?>><?php echo __('reviews.sort_highest', [], 'dashboard'); ?></option>
                            <option value="lowest" <?php echo $sort === 'lowest' ? 'selected' : ''; ?>><?php echo __('reviews.sort_lowest', [], 'dashboard'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> <?php echo __('reviews.apply_filters', [], 'dashboard'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reviews List -->
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <i class="fas fa-star"></i>
                <h3><?php echo __('reviews.no_reviews_title', [], 'dashboard'); ?></h3>
                <p>
                    <?php if (!empty($rating_filter) || !empty($sort)): ?>
                        <?php echo __('reviews.no_reviews_filter', [], 'dashboard'); ?>
                    <?php else: ?>
                        <?php echo __('reviews.no_reviews_message', [], 'dashboard'); ?>
                    <?php endif; ?>
                </p>
                <a href="bookings.php" class="btn btn-primary">
                    <i class="fas fa-calendar-check me-2"></i> <?php echo __('reviews.view_bookings', [], 'dashboard'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="reviews-container">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="client-info">
                                <div class="client-avatar">
                                    <?php if (!empty($review['client_image'])): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($review['client_image']); ?>" alt="<?php echo htmlspecialchars($review['client_name']); ?>" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo strtoupper(substr($review['client_name'], 0, 1)); ?>';">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($review['client_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="client-details">
                                    <h5><?php echo htmlspecialchars($review['client_name']); ?></h5>
                                    <p><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($review['client_email']); ?></p>
                                    <p><i class="fas fa-calendar me-1"></i> <?php echo __('reviews.reviewed_on', [], 'dashboard'); ?> <?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="review-rating">
                                <div class="stars">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++): 
                                        echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    endfor; 
                                    ?>
                                </div>
                                <div class="review-date">
                                    <?php echo date('h:i A', strtotime($review['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Service Information -->
                        <div class="service-info">
                            <strong><?php echo __('reviews.service_label', [], 'dashboard'); ?>:</strong> <?php echo htmlspecialchars($review['service_description']); ?>
                            <?php if (!empty($review['preferred_date'])): ?>
                                <br><strong><?php echo __('reviews.date_label', [], 'dashboard'); ?>:</strong> <?php echo date('M d, Y', strtotime($review['preferred_date'])); ?>
                            <?php endif; ?>
                        </div>

                        <div class="review-content">
                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>

                        <!-- Provider Response -->
                        <?php if (!empty($review['provider_response'])): ?>
                            <div class="response-section">
                                <div class="response-label">
                                    <i class="fas fa-reply"></i> <?php echo __('reviews.your_response', [], 'dashboard'); ?>
                                    <span class="text-muted" style="font-size: 0.8rem; margin-left: auto;">
                                        <?php echo date('M d, Y', strtotime($review['response_date'])); ?>
                                    </span>
                                </div>
                                <p style="color: var(--dark); margin: 0;">
                                    <?php echo nl2br(htmlspecialchars($review['provider_response'])); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Response Form -->
                            <div class="response-form">
                                <form method="POST">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo __('reviews.respond_label', [], 'dashboard'); ?></label>
                                        <textarea name="response" class="form-control" rows="3" placeholder="<?php echo __('reviews.response_placeholder', [], 'dashboard'); ?>" maxlength="500"></textarea>
                                        <div class="form-text">
                                            <?php echo __('reviews.response_notice', [], 'dashboard'); ?>
                                        </div>
                                    </div>
                                    <button type="submit" name="submit_response" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane me-1"></i> <?php echo __('reviews.submit_response', [], 'dashboard'); ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['rating' => $rating_filter, 'sort' => $sort])); ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i> <?php echo __('reviews.previous', [], 'dashboard'); ?>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['rating' => $rating_filter, 'sort' => $sort])); ?>" 
                           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['rating' => $rating_filter, 'sort' => $sort])); ?>" class="page-btn">
                            <?php echo __('reviews.next', [], 'dashboard'); ?> <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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

        // Character counter for response textarea
        document.querySelectorAll('textarea[name="response"]').forEach(textarea => {
            const charCount = document.createElement('div');
            charCount.className = 'form-text text-end';
            charCount.textContent = `${textarea.value.length}/500 <?php echo __('reviews.characters', [], 'dashboard'); ?>`;
            textarea.parentNode.appendChild(charCount);
            
            textarea.addEventListener('input', function() {
                charCount.textContent = `${this.value.length}/500 <?php echo __('reviews.characters', [], 'dashboard'); ?>`;
                if (this.value.length > 500) {
                    charCount.style.color = 'var(--danger)';
                } else {
                    charCount.style.color = 'var(--secondary)';
                }
            });
        });
    </script>
</body>
</html>