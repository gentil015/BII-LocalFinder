<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

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
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for clients
$query = "SELECT * FROM users WHERE user_type = 'client'";
$params = [];

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $query .= " AND is_verified = ?";
    $params[] = $status_filter === 'verified' ? 1 : 0;
}

if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete client
    if (isset($_POST['delete_client'])) {
        $id = intval($_POST['user_id']);
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Delete related records first
            $db->prepare("DELETE FROM bookings WHERE client_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM reviews WHERE client_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM reports WHERE reporter_id = ? OR reported_user_id = ?")->execute([$id, $id]);
            $db->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$id, $id]);
            
            // Delete user
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            
            $db->commit();
            $success = "Client deleted successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to delete client: " . $e->getMessage();
        }
    }
    
    // Update client
    if (isset($_POST['update_client'])) {
        $id = intval($_POST['user_id']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $location = sanitize($_POST['location'] ?? '');
        
        try {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $id]);
            $success = "Client updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update client: " . $e->getMessage();
        }
    }
    
    // Suspend/Activate client
    if (isset($_POST['toggle_suspend'])) {
        $id = intval($_POST['user_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        try {
            $db->prepare("UPDATE users SET is_verified = ? WHERE id = ?")->execute([$new_status, $id]);
            $action = $new_status ? 'activated' : 'suspended';
            $success = "Client {$action} successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update client status";
        }
    }
    
    // Reset password
    if (isset($_POST['reset_password'])) {
        $id = intval($_POST['user_id']);
        $new_password = password_hash('123456', PASSWORD_DEFAULT); // Default reset password
        
        try {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_password, $id]);
            $success = "Password reset successfully. New password: 123456";
        } catch (Exception $e) {
            $errors[] = "Failed to reset password";
        }
    }
    
    // Update profile photo
    if (isset($_POST['update_photo'])) {
        $id = intval($_POST['user_id']);
        
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'client_' . $id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $file_path)) {
                $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$filename, $id]);
                $success = "Profile photo updated successfully";
            } else {
                $errors[] = "Failed to upload profile photo";
            }
        } else {
            $errors[] = "Please select a valid image file";
        }
    }
}

// Function to get client statistics
function getClientStats($db, $client_id) {
    $stats = [];
    
    // Bookings count
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats['total_bookings'] = $stmt->fetchColumn();
    
    // Reviews count
    $stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats['total_reviews'] = $stmt->fetchColumn();
    
    // Reports count
    $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id = ?");
    $stmt->execute([$client_id]);
    $stats['reports_filed'] = $stmt->fetchColumn();
    
    return $stats;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management - BII LocalFinder</title>
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
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: var(--dark);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
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
        
        .badge.suspended {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Stats Badges */
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 0.75rem;
            margin: 0.1rem;
            color: var(--secondary);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
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
            background: var(--info);
            color: white;
        }
        
        .btn-edit:hover {
            background: #0aa2c0;
            color: white;
        }
        
        .btn-suspend {
            background: var(--warning);
            color: black;
        }
        
        .btn-suspend:hover {
            background: #e0a800;
            color: black;
        }
        
        .btn-reset {
            background: var(--secondary);
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
            color: white;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #bb2d3b;
            color: white;
        }
        
        /* Client Details */
        .client-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.8rem;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 500;
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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
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
                        <h1><i class="fas fa-users me-2"></i> Client Management</h1>
                        <p>Manage all registered clients and their activities</p>
                    </div>
                    <div class="quick-actions">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-download me-2"></i> Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:exportClients('pdf')">
                                    <i class="fas fa-file-pdf me-2"></i> Export as PDF
                                </a></li>
                                <li><a class="dropdown-item" href="javascript:exportClients('excel')">
                                    <i class="fas fa-file-excel me-2"></i> Export as Excel
                                </a></li>
                                <li><a class="dropdown-item" href="javascript:exportClients('csv')">
                                    <i class="fas fa-file-csv me-2"></i> Export as CSV
                                </a></li>
                                <li><a class="dropdown-item" href="javascript:exportClients('docx')">
                                    <i class="fas fa-file-word me-2"></i> Export as Word
                                </a></li>
                            </ul>
                        </div>
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
                <h3 class="mb-3">Search & Filter Clients</h3>
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="form-control" placeholder="Search by name, email, or phone...">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Apply Filters
                            </button>
                        </div>
                        <div class="filter-group">
                            <a href="users.php" class="btn btn-secondary w-100">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Clients Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Registered Clients (<?php echo count($clients); ?>)</h3>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client Info</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Activity Stats</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): 
                                $client_stats = getClientStats($db, $client['id']);
                            ?>
                                <tr>
                                    <td>#<?php echo $client['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($client['full_name']); ?></strong>
                                        <?php if ($client['profile_image']): ?>
                                            <br><small class="text-muted">Has profile photo</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($client['email']); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars($client['phone']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $client['is_verified'] ? 'verified' : 'pending'; ?>">
                                            <?php echo $client['is_verified'] ? 'Active' : 'Suspended'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="stats-badge">
                                                <i class="fas fa-calendar"></i> <?php echo $client_stats['total_bookings']; ?> bookings
                                            </span>
                                            <span class="stats-badge">
                                                <i class="fas fa-star"></i> <?php echo $client_stats['total_reviews']; ?> reviews
                                            </span>
                                            <span class="stats-badge">
                                                <i class="fas fa-flag"></i> <?php echo $client_stats['reports_filed']; ?> reports
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($client['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-sm btn-view" 
                                                    onclick="viewClientDetails(<?php echo $client['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn-sm btn-edit" 
                                                    onclick="editClient(<?php echo $client['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $client['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $client['is_verified']; ?>">
                                                <button type="submit" name="toggle_suspend" class="btn-sm btn-suspend">
                                                    <i class="fas <?php echo $client['is_verified'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $client['id']; ?>">
                                                <button type="submit" name="reset_password" class="btn-sm btn-reset"
                                                        onclick="return confirm('Reset password for this client? Default: 123456')">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this client? This will remove all their data.')">
                                                <input type="hidden" name="user_id" value="<?php echo $client['id']; ?>">
                                                <button type="submit" name="delete_client" class="btn-sm btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($clients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No clients found</h3>
                        <p>No clients found matching your criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Client Details Modal -->
    <div id="clientDetailsModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 800px; position:relative;">
            <button class="close" onclick="closeModal('clientDetailsModal')" style="position:absolute;right:12px;top:8px;font-size:1.2rem;">&times;</button>
            <div id="clientDetailsContent">
                <!-- content filled by JS -->
            </div>
        </div>
    </div>

    <!-- Edit Client Modal -->
    <div id="editClientModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Client Information</h3>
            <form id="editClientForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Profile Photo</label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                    <small class="text-muted">Current photo: <span id="current_photo"></span></small>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_client" class="btn btn-primary">Update Client</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editClientModal')">Cancel</button>
                </div>
            </form>
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
        function viewClientDetails(clientId) {
            const modal = document.getElementById('clientDetailsModal');
            const content = document.getElementById('clientDetailsContent');
            content.innerHTML = '<p>Loading...</p>';
            modal.style.display = 'block';

            fetch(`get_client.php?id=${clientId}`)
                .then(res => {
                    if (!res.ok) throw new Error('Network error');
                    return res.json();
                })
                .then(data => {
                    if (data.error) {
                        content.innerHTML = `<p class="text-danger">${data.error}</p>`;
                        return;
                    }
                    const u = data.user;
                    const s = data.stats;
                    const imgHtml = u.profile_image ? `<img src="../uploads/profiles/${u.profile_image}" alt="Photo" style="max-width:120px;border-radius:8px;margin-right:12px;">` : '';
                    content.innerHTML = `
                        <div class="d-flex" style="gap:1rem;align-items:flex-start;">
                            ${imgHtml}
                            <div style="min-width:0;">
                                <h4 style="margin:0;">${escapeHtml(u.full_name)}</h4>
                                <div class="text-muted" style="margin-bottom:8px;">${escapeHtml(u.email)} · ${escapeHtml(u.phone || '')}</div>
                                <div class="client-details">
                                    <div class="detail-row">
                                        <div class="detail-item">
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value">${u.is_verified ? 'Active' : 'Suspended'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Location</div>
                                            <div class="detail-value">${escapeHtml(u.location || '—')}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Registered</div>
                                            <div class="detail-value">${new Date(u.created_at).toLocaleString()}</div>
                                        </div>
                                    </div>

                                    <div style="margin-top:12px;">
                                        <span class="stats-badge"><i class="fas fa-calendar"></i> ${s.total_bookings} bookings</span>
                                        <span class="stats-badge"><i class="fas fa-star"></i> ${s.total_reviews} reviews</span>
                                        <span class="stats-badge"><i class="fas fa-flag"></i> ${s.reports_filed} reports</span>
                                    </div>

                                    <div style="margin-top:20px;">
                                        <h5>Activity Log</h5>
                                        <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;">
                                            ${data.activity_logs && data.activity_logs.length > 0 
                                                ? data.activity_logs.map(log => `
                                                    <div style="padding:8px;border-bottom:1px solid #eee;font-size:0.9rem;">
                                                        <div style="color:#666;"><strong>${escapeHtml(log.activity_type)}</strong></div>
                                                        <div style="color:#999;font-size:0.85rem;">${escapeHtml(log.description)}</div>
                                                        <div style="color:#bbb;font-size:0.8rem;">${new Date(log.created_at).toLocaleString()}</div>
                                                        ${log.ip_address ? `<div style="color:#bbb;font-size:0.8rem;">IP: ${escapeHtml(log.ip_address)}</div>` : ''}
                                                    </div>
                                                `).join('')
                                                : '<p style="color:#999;">No activity logs found</p>'
                                            }
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = '<p class="text-danger">Failed to load client details.</p>';
                });
        }

        function editClient(clientId) {
            const modal = document.getElementById('editClientModal');
            const form = document.getElementById('editClientForm');
            const currentPhoto = document.getElementById('current_photo');
            // clear previous
            document.getElementById('edit_user_id').value = '';
            document.getElementById('edit_full_name').value = '';
            document.getElementById('edit_email').value = '';
            document.getElementById('edit_phone').value = '';
            currentPhoto.textContent = '—';

            fetch(`get_client.php?id=${clientId}`)
                .then(res => {
                    if (!res.ok) throw new Error('Network error');
                    return res.json();
                })
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    const u = data.user;
                    document.getElementById('edit_user_id').value = u.id;
                    document.getElementById('edit_full_name').value = u.full_name;
                    document.getElementById('edit_email').value = u.email;
                    document.getElementById('edit_phone').value = u.phone;
                    currentPhoto.textContent = u.profile_image ? u.profile_image : 'No photo';
                    modal.style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to load client for editing.');
                });
        }

        // small helper to escape text when inserting into HTML
        function escapeHtml(str) {
            if (!str && str !== 0) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function exportClients(format) {
            // Get current filter parameters
            const search = document.querySelector('input[name="search"]').value;
            const status = document.querySelector('select[name="status"]').value;
            const dateFrom = document.querySelector('input[name="date_from"]').value;
            const dateTo = document.querySelector('input[name="date_to"]').value;

            // Build query string
            const params = new URLSearchParams({
                format: format,
                search: search,
                status: status,
                date_from: dateFrom,
                date_to: dateTo
            });

            // Redirect to export API
            window.location.href = `api/export_clients.php?${params.toString()}`;
        }

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