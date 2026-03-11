<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

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
];

// Get client information
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

// Check if favorites table exists, if not create it
try {
    $db->query("SELECT 1 FROM favorites LIMIT 1");
} catch (Exception $e) {
    // Create favorites table if it doesn't exist
    $createTable = "
        CREATE TABLE favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            provider_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_favorite (client_id, provider_id)
        )
    ";
    $db->exec($createTable);
}

// Handle add to favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_favorites'])) {
    $provider_id = intval($_POST['provider_id']);
    
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO favorites (client_id, provider_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $provider_id]);
        $success = "Provider added to favorites successfully";
    } catch (Exception $e) {
        $error = "Failed to add provider to favorites";
    }
}

// Handle remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_favorites'])) {
    $provider_id = intval($_POST['provider_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM favorites WHERE client_id = ? AND provider_id = ?");
        $stmt->execute([$_SESSION['user_id'], $provider_id]);
        $success = "Provider removed from favorites successfully";
    } catch (Exception $e) {
        $error = "Failed to remove provider from favorites";
    }
}

// Handle clear all favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_favorites'])) {
    try {
        $stmt = $db->prepare("DELETE FROM favorites WHERE client_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $success = "All favorites cleared successfully";
    } catch (Exception $e) {
        $error = "Failed to clear favorites";
    }
}

// Get favorite providers with detailed information
$stmt = $db->prepare("
    SELECT 
        f.*,
        sp.id as provider_id,
        sp.profession,
        sp.location,
        sp.availability,
        sp.hourly_rate,
        sp.average_rating,
        sp.total_reviews,
        sp.experience_years,
        sp.is_verified,
        u.full_name as provider_name,
        u.email as provider_email,
        u.phone as provider_phone,
        u.profile_image as provider_image,
        u.is_verified as user_verified
    FROM favorites f
    JOIN service_providers sp ON f.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE f.client_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$favorite_providers = $stmt->fetchAll();

// Get total favorites count
$total_favorites = count($favorite_providers);

// Get recently added favorites (last 3)
$recent_favorites = array_slice($favorite_providers, 0, 3);

// Get recommended providers (not in favorites)
$stmt = $db->prepare("
    SELECT 
        sp.id,
        sp.profession,
        sp.location,
        sp.availability,
        sp.hourly_rate,
        sp.average_rating,
        sp.total_reviews,
        sp.experience_years,
        sp.is_verified,
        u.full_name as provider_name,
        u.profile_image as provider_image,
        u.is_verified as user_verified
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.id NOT IN (
        SELECT provider_id FROM favorites WHERE client_id = ?
    )
    AND sp.average_rating >= 4.0
    AND sp.is_active = 1
    ORDER BY sp.average_rating DESC, sp.total_reviews DESC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id']]);
$recommended_providers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - <?php echo $system_settings['platform_name']; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* EXACT SAME STYLES AS DASHBOARD - NO CHANGES */
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
        
        /* Provider Cards */
        .provider-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .provider-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.1);
            transform: translateY(-2px);
        }
        
        .provider-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .provider-info {
            display: flex;
            gap: 1rem;
            flex: 1;
        }
        
        .provider-avatar {
            width: 70px;
            height: 70px;
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
        
        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .provider-details {
            flex: 1;
        }
        
        .provider-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .provider-profession {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .provider-location {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .provider-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .rating {
            color: #ffc107;
        }
        
        /* Badges */
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-premium {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .badge-new {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-sm:hover {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a58ca;
            color: white;
            text-decoration: none;
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            text-decoration: none;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #bb2d3b;
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #157347;
            color: white;
        }
        
        /* Favorite Heart */
        .favorite-heart {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }
        
        .favorite-heart:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .favorite-heart.active {
            color: var(--danger);
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
        
        /* Grid Layout for Providers */
        .providers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .providers-grid {
                grid-template-columns: 1fr;
            }
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
            
            .provider-header {
                flex-direction: column;
            }
            
            .provider-info {
                flex-direction: column;
                text-align: center;
            }
            
            .provider-avatar {
                margin: 0 auto;
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
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--success);
        }
        
        /* Availability Badge */
        .availability-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .available {
            background: #d4edda;
            color: #155724;
        }
        
        .busy {
            background: #f8d7da;
            color: #721c24;
        }
        
        .away {
            background: #fff3cd;
            color: #856404;
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-section">
                <div class="welcome-text">
                    <h1>My Favorites</h1>
                    <p>Your saved service providers for quick access</p>
                </div>
                <div class="quick-actions">
                    <a href="find-providers.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i> Find More Providers
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
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
                <div class="stat-icon" style="background: #fce7f3; color: #be185d;">
                    <i class="fas fa-heart"></i>
                </div>
                <h3><?php echo $total_favorites; ?></h3>
                <p>Total Favorites</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo count($recent_favorites); ?></h3>
                <p>Recently Added</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo count(array_filter($favorite_providers, fn($p) => $p['average_rating'] >= 4.5)); ?></h3>
                <p>Top Rated</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo count(array_filter($favorite_providers, fn($p) => $p['is_verified'])); ?></h3>
                <p>Verified Providers</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- My Favorites -->
                <div class="card">
                    <div class="card-header">
                        <h3>My Favorite Providers (<?php echo $total_favorites; ?>)</h3>
                        <?php if ($total_favorites > 0): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to clear all favorites?')">
                                <button type="submit" name="clear_all_favorites" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-trash me-1"></i> Clear All
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($favorite_providers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <h3>No favorites yet</h3>
                            <p>Start by adding some service providers to your favorites</p>
                            <a href="../client/providers.php" class="btn btn-primary mt-3">
                                <i class="fas fa-search me-2"></i> Find Providers
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="providers-grid">
                            <?php foreach ($favorite_providers as $provider): ?>
                                <div class="provider-card">
                                    <!-- Favorite Heart -->
                                    <form method="POST" class="favorite-form">
                                        <input type="hidden" name="provider_id" value="<?php echo $provider['provider_id']; ?>">
                                        <button type="submit" name="remove_from_favorites" class="favorite-heart active" title="Remove from favorites">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </form>

                                    <div class="provider-info">
                                        <div class="provider-avatar">
                                            <?php 
                                            $provider_image = $provider['provider_image'] ?? '';
                                            $provider_initial = strtoupper(substr($provider['provider_name'] ?? '', 0, 1)) ?: '?';
                                            ?>
                                            <?php if (!empty($provider_image)): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($provider_image); ?>" alt="<?php echo htmlspecialchars($provider['provider_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($provider_initial); ?>';">
                                            <?php else: ?>
                                                <?php echo $provider_initial; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="provider-details">
                                            <div class="provider-name">
                                                <?php echo htmlspecialchars($provider['provider_name']); ?>
                                                <?php if ($provider['is_verified'] || $provider['user_verified']): ?>
                                                    <span class="badge badge-verified ms-1">
                                                        <i class="fas fa-check-circle me-1"></i> Verified
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="provider-profession">
                                                <?php echo htmlspecialchars($provider['profession']); ?>
                                            </div>
                                            <div class="provider-location">
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($provider['location']); ?>
                                            </div>
                                            
                                            <div class="provider-meta">
                                                <div class="meta-item">
                                                    <span class="rating">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <?php if ($i <= floor($provider['average_rating'])): ?>
                                                                <i class="fas fa-star"></i>
                                                            <?php elseif ($i == ceil($provider['average_rating']) && fmod($provider['average_rating'], 1) != 0): ?>
                                                                <i class="fas fa-star-half-alt"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star"></i>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span>(<?php echo $provider['total_reviews']; ?>)</span>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="fas fa-briefcase me-1"></i> <?php echo $provider['experience_years'] ?? '0'; ?> years
                                                </div>
                                                <div class="meta-item">
                                                    <span class="availability-badge <?php echo strtolower($provider['availability'] ?? 'available'); ?>">
                                                        <?php echo ucfirst($provider['availability'] ?? 'Available'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($provider['hourly_rate']): ?>
                                                <div class="price-display mb-2">
                                                    <i class="fas fa-money-bill-wave me-1"></i> RWF <?php echo number_format($provider['hourly_rate']); ?>/hour
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <a href="../client/provider-profile.php?id=<?php echo $provider['provider_id']; ?>" class="btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> View Profile
                                        </a>
                                        <a href="book-provider.php?provider_id=<?php echo $provider['provider_id']; ?>" class="btn-sm btn-success">
                                            <i class="fas fa-calendar-plus me-1"></i> Book Now
                                        </a>
                                        <a href="../contact.php?provider_id=<?php echo $provider['provider_id']; ?>" class="btn-sm btn-outline-primary">
                                            <i class="fas fa-envelope me-1"></i> Contact
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Quick Actions -->
                <div class="card">
                    <h3 class="mb-3">Quick Actions</h3>
                    <div class="d-grid gap-2">
                        <a href="providers.php" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Find New Providers
                        </a>
                        <a href="my-bookings.php" class="btn btn-outline-primary">
                            <i class="fas fa-calendar me-2"></i> My Bookings
                        </a>
                        <a href="my-reviews.php" class="btn btn-outline-primary">
                            <i class="fas fa-star me-2"></i> My Reviews
                        </a>
                    </div>
                </div>

                <!-- Recently Added -->
                <?php if (!empty($recent_favorites)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Recently Added</h3>
                        </div>
                        <?php foreach ($recent_favorites as $provider): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="provider-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <?php 
                                    $provider_image = $provider['provider_image'] ?? '';
                                    $provider_initial = strtoupper(substr($provider['provider_name'] ?? '', 0, 1)) ?: '?';
                                    ?>
                                    <?php if (!empty($provider_image)): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($provider_image); ?>" alt="<?php echo htmlspecialchars($provider['provider_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($provider_initial); ?>';">
                                    <?php else: ?>
                                        <?php echo $provider_initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-2 flex-grow-1">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($provider['provider_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($provider['profession']); ?></div>
                                </div>
                                <a href="../client/provider-profile.php?id=<?php echo $provider['provider_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Recommended Providers -->
                <?php if (!empty($recommended_providers)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>You Might Also Like</h3>
                        </div>
                        <?php foreach ($recommended_providers as $provider): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="provider-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <?php 
                                    $provider_image = $provider['provider_image'] ?? '';
                                    $provider_initial = strtoupper(substr($provider['provider_name'] ?? '', 0, 1)) ?: '?';
                                    ?>
                                    <?php if (!empty($provider_image)): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($provider_image); ?>" alt="<?php echo htmlspecialchars($provider['provider_name']); ?>" onerror="this.style.display='none'; this.parentNode.textContent='<?php echo addslashes($provider_initial); ?>';">
                                    <?php else: ?>
                                        <?php echo $provider_initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-2 flex-grow-1">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($provider['provider_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($provider['profession']); ?></div>
                                    <div class="small text-warning">
                                        <i class="fas fa-star"></i> <?php echo number_format($provider['average_rating'], 1); ?>
                                    </div>
                                </div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                    <button type="submit" name="add_to_favorites" class="btn btn-sm btn-outline-danger" title="Add to favorites">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-2">
                            <a href="find-providers.php" class="btn btn-sm btn-outline-primary">
                                View All Recommendations
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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

        // Favorite heart animation
        document.querySelectorAll('.favorite-heart').forEach(heart => {
            heart.addEventListener('click', function(e) {
                if (this.classList.contains('active')) {
                    this.innerHTML = '<i class="far fa-heart"></i>';
                    this.classList.remove('active');
                } else {
                    this.innerHTML = '<i class="fas fa-heart"></i>';
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>