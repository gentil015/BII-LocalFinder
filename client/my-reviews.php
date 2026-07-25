<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/client_header.php';

// Check if user is logged in and is a client
if (!isLoggedIn()) {
    redirect('login.php');
}

if (isProvider()) {
    redirect('provider/reviews.php');
}

if (isAdmin()) {
    redirect('admin/dashboard.php');
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
    
    // Review and rating settings
    'require_rating_after_completion' => getSetting($db, 'require_rating_after_completion', '0'),
    'allow_review_editing' => getSetting($db, 'allow_review_editing', '1'),
    'allow_review_deletion' => getSetting($db, 'allow_review_deletion', '1'),
    'min_review_length' => intval(getSetting($db, 'min_review_length', '10')),
    'max_review_length' => intval(getSetting($db, 'max_review_length', '500')),
    
    // Booking settings
    'auto_cancel_unconfirmed' => getSetting($db, 'auto_cancel_unconfirmed', '1'),
    'max_pending_time' => intval(getSetting($db, 'max_pending_time', '15')),
];

// Handle review deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    if (!$system_settings['allow_review_deletion']) {
        $errors[] = "Review deletion is currently disabled by system administrator";
    } else {
        $review_id = intval($_POST['review_id']);
        
        // Verify review belongs to client
        $stmt = $db->prepare("SELECT provider_id FROM reviews WHERE id = ? AND client_id = ?");
        $stmt->execute([$review_id, $_SESSION['user_id']]);
        $review = $stmt->fetch();
        
        if ($review) {
            $provider_id = $review['provider_id'];
            
            // Delete review
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            if ($stmt->execute([$review_id])) {
                // Update provider's average rating and total reviews
                $stmt = $db->prepare("
                    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                    FROM reviews
                    WHERE provider_id = ?
                ");
                $stmt->execute([$provider_id]);
                $stats = $stmt->fetch();
                
                $avg = $stats['avg_rating'] ?? 0;
                $total = $stats['total_reviews'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE service_providers 
                    SET average_rating = ?, total_reviews = ?
                    WHERE id = ?
                ");
                $stmt->execute([round($avg, 2), $total, $provider_id]);
                
                $success = "Review deleted successfully";
                
                // Log activity
                logActivity($db, $_SESSION['user_id'], 'review_deleted', "Deleted review for provider #{$provider_id}");
            } else {
                $errors[] = "Failed to delete review";
            }
        } else {
            $errors[] = "Invalid review";
        }
    }
}

// Handle review editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_review'])) {
    if (!$system_settings['allow_review_editing']) {
        $errors[] = "Review editing is currently disabled by system administrator";
    } else {
        $review_id = intval($_POST['review_id']);
        $new_rating = intval($_POST['rating']);
        $new_comment = sanitize($_POST['comment']);
        
        // Validate review length
        if (strlen($new_comment) < $system_settings['min_review_length']) {
            $errors[] = "Review comment must be at least {$system_settings['min_review_length']} characters";
        } elseif (strlen($new_comment) > $system_settings['max_review_length']) {
            $errors[] = "Review comment cannot exceed {$system_settings['max_review_length']} characters";
        } else {
            // Verify review belongs to client
            $stmt = $db->prepare("SELECT provider_id FROM reviews WHERE id = ? AND client_id = ?");
            $stmt->execute([$review_id, $_SESSION['user_id']]);
            $review = $stmt->fetch();
            
            if ($review) {
                $provider_id = $review['provider_id'];
                
                // Update review
                // Only include updated_at if the column exists to avoid SQL errors on older schemas
                try {
                    $colStmt = $db->prepare("SHOW COLUMNS FROM reviews LIKE 'updated_at'");
                    $colStmt->execute();
                    $hasUpdatedAt = (bool) $colStmt->fetch();
                } catch (Throwable $e) {
                    $hasUpdatedAt = false;
                }

                if ($hasUpdatedAt) {
                    $stmt = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?");
                    $execParams = [$new_rating, $new_comment, $review_id];
                } else {
                    $stmt = $db->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ?");
                    $execParams = [$new_rating, $new_comment, $review_id];
                }

                if ($stmt->execute($execParams)) {
                     // Update provider's average rating
                     $stmt = $db->prepare("
                         SELECT AVG(rating) as avg_rating
                         FROM reviews
                         WHERE provider_id = ?
                     ");
                     $stmt->execute([$provider_id]);
                     $avg_rating = $stmt->fetch()['avg_rating'] ?? 0;
                     
                     $stmt = $db->prepare("UPDATE service_providers SET average_rating = ? WHERE id = ?");
                     $stmt->execute([round($avg_rating, 2), $provider_id]);
                     
                     $success = "Review updated successfully";
                     
                     // Log activity
                     logActivity($db, $_SESSION['user_id'], 'review_updated', "Updated review for provider #{$provider_id}");
                 } else {
                     $errors[] = "Failed to update review";
                 }
            } else {
                $errors[] = "Invalid review";
            }
        }
    }
}

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'recent';

// Build query
$sql = "
    SELECT r.*, 
           sp.profession, sp.location, sp.average_rating as provider_rating,
           u.full_name as provider_name, u.profile_image as provider_image
    FROM reviews r
    JOIN service_providers sp ON r.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE r.client_id = ?
";

$params = [$_SESSION['user_id']];

if ($rating_filter > 0) {
    $sql .= " AND r.rating = ?";
    $params[] = $rating_filter;
}

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
    WHERE client_id = ?
";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch();

// Get pending reviews (bookings that need reviews)
try {
    // detect an appropriate timestamp column to sort by (avoid missing completed_at)
    $cols = $db->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('completed_at', $cols, true)) {
        $orderCol = 'completed_at';
    } elseif (in_array('updated_at', $cols, true)) {
        $orderCol = 'updated_at';
    } elseif (in_array('preferred_date', $cols, true)) {
        $orderCol = 'preferred_date';
    } else {
        $orderCol = 'created_at';
    }

   $pending_reviews_sql = "
       SELECT b.id as booking_id, b.service_description, b.preferred_date,
              sp.profession, u.full_name as provider_name, u.profile_image as provider_image
       FROM bookings b
       JOIN service_providers sp ON b.provider_id = sp.id
       JOIN users u ON sp.user_id = u.id
       WHERE b.client_id = ? 
         AND b.status = 'completed'
         AND NOT EXISTS (
             SELECT 1 FROM reviews r 
             WHERE r.booking_id = b.id AND r.client_id = ?
         )
       ORDER BY b." . $orderCol . " DESC
       LIMIT 5
   ";
   $pending_reviews_stmt = $db->prepare($pending_reviews_sql);
   $pending_reviews_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
   $pending_reviews = $pending_reviews_stmt->fetchAll();
} catch (Throwable $e) {
    error_log('my-reviews: failed to load pending reviews: ' . $e->getMessage());
    $pending_reviews = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - <?php echo $system_settings['platform_name']; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* EXACT SAME STYLES AS BEFORE - NO CHANGES */
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 1rem 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
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
        
        /* Stats Grid */
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
            transition: transform 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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
        
        /* Review Items */
        .review-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .review-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
        }
        
        .review-header {
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
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Rating Stars */
        .rating {
            color: #ffc107;
            margin: 0.5rem 0;
        }
        
        /* Action Buttons */
        .review-actions {
            display: flex;
            gap: 0.5rem;
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
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: #0a58ca;
            color: white;
            text-decoration: none;
        }
        
        .btn-edit {
            background: var(--warning);
            color: black;
        }
        
        .btn-edit:hover {
            background: #e0a800;
            color: black;
            text-decoration: none;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #bb2d3b;
            color: white;
        }
        
        /* Filter Section */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .welcome-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .review-header {
                flex-direction: column;
            }
            
            .filters-grid {
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

        /* Pending Reviews */
        .pending-review-item {
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fffbf0;
        }

        .system-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
<?php client_header_render_styles(); ?>
</head>
<body>
    <?php client_header_render_markup(basename($_SERVER['PHP_SELF'])); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-section">
                <div class="welcome-text">
                    <h1>My Reviews</h1>
                    <p>View and manage your reviews for <?php echo $system_settings['platform_name']; ?></p>
                </div>
                <div class="quick-actions">
                    <a href="../client/providers.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i> Find Providers
                    </a>
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

        <?php if (!$system_settings['allow_review_deletion']): ?>
            <div class="system-notice">
                <i class="fas fa-info-circle me-2"></i>
                Review deletion is currently disabled by system administrator.
            </div>
        <?php endif; ?>

        <?php if (!$system_settings['allow_review_editing']): ?>
            <div class="system-notice">
                <i class="fas fa-info-circle me-2"></i>
                Review editing is currently disabled by system administrator.
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Reviews</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3><?php echo $stats['total'] > 0 ? number_format($stats['avg_rating'], 1) : '0.0'; ?></h3>
                <p>Average Rating Given</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <h3><?php echo $stats['five_star'] + $stats['four_star']; ?></h3>
                <p>Positive Reviews</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3>
                    <?php 
                    if (!empty($reviews)) {
                        $latest = strtotime($reviews[0]['created_at']);
                        $days_ago = floor((time() - $latest) / (60 * 60 * 24));
                        echo $days_ago === 0 ? 'Today' : $days_ago . 'd ago';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </h3>
                <p>Last Review</p>
            </div>
        </div>

        <!-- Pending Reviews -->
        <?php if (!empty($pending_reviews) && $system_settings['require_rating_after_completion']): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock me-2"></i> Pending Reviews</h3>
                    <span class="badge bg-warning">Required</span>
                </div>
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Reviews are required for completed bookings. Please share your experience.
                </p>
                <?php foreach ($pending_reviews as $pending): ?>
                    <div class="pending-review-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($pending['provider_name']); ?></h5>
                                <p class="text-primary small mb-1"><?php echo htmlspecialchars($pending['profession']); ?></p>
                                <p class="text-muted small mb-0">Service: <?php echo htmlspecialchars($pending['service_description']); ?></p>
                                <p class="text-muted small mb-0">Completed: <?php echo date('M d, Y', strtotime($pending['preferred_date'])); ?></p>
                            </div>
                            <a href="write-review.php?booking_id=<?php echo $pending['booking_id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-star me-1"></i> Write Review
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="my-reviews.php">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label fw-semibold">Filter by Rating</label>
                        <select name="rating" class="form-select">
                            <option value="0">All Ratings</option>
                            <option value="5" <?php echo $rating_filter === 5 ? 'selected' : ''; ?>>5 Stars (<?php echo $stats['five_star']; ?>)</option>
                            <option value="4" <?php echo $rating_filter === 4 ? 'selected' : ''; ?>>4 Stars (<?php echo $stats['four_star']; ?>)</option>
                            <option value="3" <?php echo $rating_filter === 3 ? 'selected' : ''; ?>>3 Stars (<?php echo $stats['three_star']; ?>)</option>
                            <option value="2" <?php echo $rating_filter === 2 ? 'selected' : ''; ?>>2 Stars (<?php echo $stats['two_star']; ?>)</option>
                            <option value="1" <?php echo $rating_filter === 1 ? 'selected' : ''; ?>>1 Star (<?php echo $stats['one_star']; ?>)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label fw-semibold">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="highest" <?php echo $sort === 'highest' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="lowest" <?php echo $sort === 'lowest' ? 'selected' : ''; ?>>Lowest Rating</option>
                        </select>
                    </div>

                    <div class="form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column - Reviews List -->
            <div>
                <?php if (empty($reviews)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No Reviews Yet</h3>
                            <p>
                                <?php if ($system_settings['require_rating_after_completion']): ?>
                                    Complete a booking and share your experience to help other clients!
                                <?php else: ?>
                                    You haven't written any reviews yet. Share your experience to help other clients!
                                <?php endif; ?>
                            </p>
                            <a href="../client/providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>My Reviews</h3>
                            <span class="text-muted">Showing <?php echo count($reviews); ?> reviews</span>
                        </div>

                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="provider-info">
                                        <?php 
    $provider_image = $review['provider_image'] ?? '';
    $provider_initial = strtoupper(substr($review['provider_name'] ?? '', 0, 1)) ?: '?';
    ?>
    <div class="provider-avatar">
        <?php if (!empty($provider_image)): ?>
            <img src="../uploads/profiles/<?php echo htmlspecialchars($provider_image); ?>" alt="<?php echo htmlspecialchars($review['provider_name'] ?? 'Provider'); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($provider_initial); ?>';">
        <?php else: ?>
            <?php echo $provider_initial; ?>
        <?php endif; ?>
    </div>
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($review['provider_name']); ?></h5>
                                            <p class="text-primary fw-semibold small mb-1">
                                                <i class="fas fa-briefcase me-1"></i> <?php echo htmlspecialchars($review['profession']); ?>
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($review['location']); ?>
                                            </p>
                                            <p class="text-warning small mb-0">
                                                Current Rating: <?php echo number_format($review['provider_rating'], 1); ?> ⭐
                                            </p>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="rating">
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++): 
                                                echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                            endfor; 
                                            ?>
                                        </div>
                                        <span class="text-muted small">
                                            <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                            <?php
    // safe check for updated_at to avoid undefined array key notice
    $review_updated_at = $review['updated_at'] ?? null;
    if ($review_updated_at && $review_updated_at !== ($review['created_at'] ?? null)): ?>
        <br><small style="color: #94a3b8;">(edited)</small>
    <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded mb-2">
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                    <?php if ($system_settings['min_review_length'] > 0): ?>
                                        <small class="text-muted">
                                            Length: <?php echo strlen($review['comment']); ?> characters
                                            (min: <?php echo $system_settings['min_review_length']; ?>, 
                                            max: <?php echo $system_settings['max_review_length']; ?>)
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="review-actions">
                                    <a href="../provider-profile.php?id=<?php echo $review['provider_id']; ?>" class="btn-sm btn-view">
                                        <i class="fas fa-eye me-1"></i> View Provider
                                    </a>
                                    
                                    <?php if ($system_settings['allow_review_editing']): ?>
                                        <button type="button" class="btn-sm btn-edit" onclick="editReview(<?php echo $review['id']; ?>, <?php echo $review['rating']; ?>, '<?php echo addslashes($review['comment']); ?>')">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($system_settings['allow_review_deletion']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.')">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" name="delete_review" class="btn-sm btn-delete">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Quick Stats -->
            <div>
                <!-- Review Guidelines -->
                <div class="card">
                    <h3>Review Guidelines</h3>
                    <div class="small text-muted">
                        <p><strong>Minimum Length:</strong> <?php echo $system_settings['min_review_length']; ?> characters</p>
                        <p><strong>Maximum Length:</strong> <?php echo $system_settings['max_review_length']; ?> characters</p>
                        <p><strong>Rating Required:</strong> <?php echo $system_settings['require_rating_after_completion'] ? 'Yes' : 'No'; ?></p>
                        <p><strong>Editing Allowed:</strong> <?php echo $system_settings['allow_review_editing'] ? 'Yes' : 'No'; ?></p>
                        <p><strong>Deletion Allowed:</strong> <?php echo $system_settings['allow_review_deletion'] ? 'Yes' : 'No'; ?></p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <h3>Quick Actions</h3>
                    <div class="d-grid gap-2">
                        <a href="../providers.php" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Find New Providers
                        </a>
                        <a href="my-bookings.php" class="btn btn-outline-primary">
                            <i class="fas fa-calendar me-2"></i> View Bookings
                        </a>
                        <?php if (!empty($pending_reviews)): ?>
                            <a href="#pending-reviews" class="btn btn-warning">
                                <i class="fas fa-star me-2"></i> Pending Reviews (<?php echo count($pending_reviews); ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Support Information -->
                <div class="card">
                    <h3>Need Help?</h3>
                    <p class="text-muted small mb-2">
                        If you have questions about reviews or need assistance, contact our support team.
                    </p>
                    <div class="small">
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i> <?php echo $system_settings['contact_email']; ?></p>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i> <?php echo $system_settings['contact_phone']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Review Modal -->
    <?php if ($system_settings['allow_review_editing']): ?>
    <div class="modal fade" id="editReviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editReviewForm">
                    <div class="modal-body">
                        <input type="hidden" name="review_id" id="editReviewId">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating-input mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star rating-star" data-rating="<?php echo $i; ?>" style="cursor: pointer; font-size: 1.5rem; color: #dee2e6;"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="editRating" value="5">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Review Comment</label>
                            <textarea name="comment" id="editComment" class="form-control" rows="4" 
                                      minlength="<?php echo $system_settings['min_review_length']; ?>"
                                      maxlength="<?php echo $system_settings['max_review_length']; ?>"
                                      placeholder="Share your experience..."></textarea>
                            <div class="form-text">
                                Minimum <?php echo $system_settings['min_review_length']; ?> characters, 
                                maximum <?php echo $system_settings['max_review_length']; ?> characters
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_review" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Edit review functionality
        function editReview(reviewId, currentRating, currentComment) {
            document.getElementById('editReviewId').value = reviewId;
            document.getElementById('editRating').value = currentRating;
            document.getElementById('editComment').value = currentComment;
            
            // Update star display
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach((star, index) => {
                if (index < currentRating) {
                    star.style.color = '#ffc107';
                } else {
                    star.style.color = '#dee2e6';
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('editReviewModal'));
            modal.show();
        }

        // Star rating interaction
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                document.getElementById('editRating').value = rating;
                
                // Update all stars
                document.querySelectorAll('.rating-star').forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#dee2e6';
                    }
                });
            });
        });

        // Character count for review textarea
        const commentTextarea = document.getElementById('editComment');
        if (commentTextarea) {
            commentTextarea.addEventListener('input', function() {
                const length = this.value.length;
                const min = <?php echo $system_settings['min_review_length']; ?>;
                const max = <?php echo $system_settings['max_review_length']; ?>;
                
                if (length < min) {
                    this.setCustomValidity(`Please enter at least ${min} characters`);
                } else if (length > max) {
                    this.setCustomValidity(`Please enter no more than ${max} characters`);
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
<?php client_header_render_scripts(); ?>
</body>
</html>