<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/client_header.php';
require_once '../controllers/pages/client/ClientProfileController.php';

// Check if user is logged in and is a client
if (!isLoggedIn()) {
    redirect('login.php');
}

if (isProvider()) {
    redirect('provider/profile.php');
}

$db = Database::getInstance()->getConnection();
$controller = new ClientProfileController();
$success = '';
$errors = [];

// Load system settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

$system_settings = [
    'platform_name' => getSetting($db, 'platform_name', 'BII LocalFinder'),
    'contact_email' => getSetting($db, 'contact_email', 'support@biilocalfinder.com'),
    'contact_phone' => getSetting($db, 'contact_phone', '+250 788 123 456'),
    'allowed_file_types' => getSetting($db, 'allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx'),
    'max_file_size' => intval(getSetting($db, 'max_file_size', '10')),
    'email_verification' => getSetting($db, 'email_verification', '1'),
    'phone_verification' => getSetting($db, 'phone_verification', '0'),
    'enable_2fa' => getSetting($db, 'enable_2fa', '0'),
];

$viewData = $controller->index($db, $_SESSION['user_id'], $system_settings);
$client = $viewData['client'] ?? null;
$total_bookings = $viewData['total_bookings'] ?? 0;
$total_reviews = $viewData['total_reviews'] ?? 0;
$recent_activities = $viewData['recent_activities'] ?? [];
$needs_email_verification = $viewData['needs_email_verification'] ?? false;
$needs_phone_verification = $viewData['needs_phone_verification'] ?? false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $result = $controller->handleSubmit($db, $_SESSION['user_id'], $_POST, $_FILES, $system_settings);
    $success = $result['success'];
    $errors = $result['errors'];

    if (!empty($success)) {
        $viewData = $controller->index($db, $_SESSION['user_id'], $system_settings);
        $client = $viewData['client'] ?? null;
        $total_bookings = $viewData['total_bookings'] ?? 0;
        $total_reviews = $viewData['total_reviews'] ?? 0;
        $recent_activities = $viewData['recent_activities'] ?? [];
        $needs_email_verification = $viewData['needs_email_verification'] ?? false;
        $needs_phone_verification = $viewData['needs_phone_verification'] ?? false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo $system_settings['platform_name']; ?></title>
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
        
        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border-radius: 12px;
            padding: 2.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .profile-header-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .profile-header h1 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            font-size: 2rem;
        }
        
        .profile-header p {
            margin: 0 0 1rem 0;
            opacity: 0.9;
            font-size: 1rem;
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
        
        .card h2 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card h2 i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        /* Profile Section */
        .profile-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        /* Profile Image Section */
        .profile-image-section {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .current-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            font-size: 3.5rem;
            font-weight: bold;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
        
        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-upload-btn label {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .image-upload-btn label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-box {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa, #f1f5f9);
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1);
        }
        
        .stat-box .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-box .label {
            color: var(--secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.85rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-text {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.4rem;
        }
        
        .required {
            color: var(--danger);
            font-weight: 600;
        }
        
        /* Button Styles */
        .btn-save {
            padding: 0.85rem 2.5rem;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc3545;
        }
        
        .alert i {
            font-size: 1.2rem;
            margin-top: 0.2rem;
        }
        
        /* Verification Status */
        .verification-status {
            background: linear-gradient(135deg, #f0f7ff, #e3f2fd);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .verification-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .verification-item:last-child {
            margin-bottom: 0;
        }
        
        .verification-badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Activity Styles */
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            align-items: flex-start;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 1.1rem;
        }
        
        .activity-content p {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-weight: 500;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        /* Text Center */
        .text-center {
            text-align: center;
        }
        
        .mt-4 {
            margin-top: 2rem;
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
                display: flex !important;
            }
            
            .profile-header-content {
                flex-direction: column;
            }
            
            .profile-section {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            cursor: pointer;
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
    </style>
<?php client_header_render_styles(); ?>
</head>
<body>
    <?php client_header_render_markup(basename($_SERVER['PHP_SELF'])); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div>
                    <h1>My Profile</h1>
                    <p>Manage your personal information</p>
                </div>
                <div>
                    <a href="providers.php" class="btn-save">
                        <i class="fas fa-search"></i> Find Providers
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

        <!-- Profile Header -->
        <div class="card profile-section">
            <div class="profile-image-section">
                <div class="current-image" id="imagePreview">
                    <?php if ($client['profile_image']): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($client['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo strtoupper(substr($client['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" id="imageForm">
                    <div class="image-upload-btn">
                        <input type="file" name="profile_image" id="profileImage" accept="image/*" onchange="document.getElementById('imageForm').submit()">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="full_name" value="<?php echo htmlspecialchars($client['full_name']); ?>">
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>">
                        <label for="profileImage">
                            <i class="fas fa-camera"></i> Change Photo
                        </label>
                    </div>
                </form>
                <div class="form-text">
                    Max size: <?php echo $system_settings['max_file_size']; ?>MB<br>
                    Allowed: <?php echo str_replace(',', ', ', $system_settings['allowed_file_types']); ?>
                </div>
            </div>

            <div class="profile-stats">
                <h2 style="border: none; padding: 0; margin-bottom: 1.5rem;">
                    <i class="fas fa-chart-bar"></i> Account Statistics
                </h2>
                
                <!-- Verification Status -->
                <?php if ($needs_email_verification || $needs_phone_verification): ?>
                <div class="verification-status mb-3">
                    <h6 class="mb-2">Verification Status</h6>
                    <div class="verification-item">
                        <i class="fas fa-envelope"></i>
                        <span>Email: </span>
                        <?php if ($client['email_verified']): ?>
                            <span class="verification-badge badge-verified">Verified</span>
                        <?php else: ?>
                            <span class="verification-badge badge-pending">Pending</span>
                            <?php if ($system_settings['email_verification']): ?>
                                <a href="verify-email.php" class="btn btn-sm btn-outline-warning ms-2">Verify</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="verification-item">
                        <i class="fas fa-phone"></i>
                        <span>Phone: </span>
                        <?php if ($client['phone_verified']): ?>
                            <span class="verification-badge badge-verified">Verified</span>
                        <?php else: ?>
                            <span class="verification-badge badge-pending">Pending</span>
                            <?php if ($system_settings['phone_verification']): ?>
                                <a href="verify-phone.php" class="btn btn-sm btn-outline-warning ms-2">Verify</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="number"><?php echo $total_bookings; ?></div>
                        <div class="label">Total Bookings</div>
                    </div>
                    <div class="stat-box">
                        <div class="number"><?php echo $total_reviews; ?></div>
                        <div class="label">Reviews Written</div>
                    </div>
                    <div class="stat-box">
                        <div class="number">
                            <?php 
                            $member_since = new DateTime($client['created_at']);
                            $now = new DateTime();
                            $diff = $member_since->diff($now);
                            echo $diff->days;
                            ?>
                        </div>
                        <div class="label">Days as Member</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personal Information Form -->
        <form method="POST" enctype="multipart/form-data">
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Personal Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" required value="<?php echo htmlspecialchars($client['full_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" value="<?php echo htmlspecialchars($client['email']); ?>" disabled>
                        <div class="form-text">
                            Email cannot be changed
                            <?php if ($system_settings['email_verification'] && !$client['email_verified']): ?>
                                • <a href="verify-email.php" class="text-warning">Verify your email</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" required value="<?php echo htmlspecialchars($client['phone']); ?>">
                        <div class="form-text">
                            <?php if ($system_settings['phone_verification'] && !$client['phone_verified']): ?>
                                <a href="verify-phone.php" class="text-warning">Verify your phone number</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Profile Image</label>
                        <input type="file" name="profile_image" accept="image/*">
                        <div class="form-text">
                            Max file size: <?php echo $system_settings['max_file_size']; ?>MB. 
                            Allowed types: <?php echo str_replace(',', ', ', $system_settings['allowed_file_types']); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?php echo date('F d, Y', strtotime($client['created_at'])); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Last Updated</label>
                        <input type="text" value="<?php echo $client['updated_at'] ? date('F d, Y H:i', strtotime($client['updated_at'])) : 'Never'; ?>" disabled>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>

        <!-- Recent Activity -->
        <?php if (!empty($recent_activities)): ?>
        <div class="card">
            <h2><i class="fas fa-history"></i> Recent Activity</h2>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-<?php 
                            switch($activity['activity_type']) {
                                case 'profile_update': echo 'user-edit'; break;
                                case 'booking_created': echo 'calendar-plus'; break;
                                case 'review_created': echo 'star'; break;
                                default: echo 'circle';
                            }
                        ?>"></i>
                    </div>
                    <div class="activity-content">
                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                        <div class="activity-time">
                            <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Security Card -->
        <div class="card security-card">
            <h2><i class="fas fa-shield-alt"></i> Account Security</h2>
            <p>
                Keep your account secure by using a strong password and 
                <?php if ($system_settings['enable_2fa']): ?>
                    enabling two-factor authentication.
                <?php else: ?>
                    following security best practices.
                <?php endif; ?>
                Contact support: <?php echo $system_settings['contact_email']; ?> | <?php echo $system_settings['contact_phone']; ?>
            </p>
            <a href="settings.php" class="btn-secondary">
                <i class="fas fa-cog"></i> Go to Security Settings
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview image before upload
        document.getElementById('profileImage')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // File size validation
        document.querySelector('input[name="profile_image"]')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = <?php echo $system_settings['max_file_size'] * 1024 * 1024; ?>;
            
            if (file && file.size > maxSize) {
                alert('File size must be less than <?php echo $system_settings['max_file_size']; ?>MB');
                this.value = '';
            }
        });
    </script>
<?php client_header_render_scripts(); ?>
</body>
</html>