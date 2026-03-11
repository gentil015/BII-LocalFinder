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
$filter_status = $_GET['status'] ?? 'pending';
$search_query = $_GET['search'] ?? '';
$verification_type = $_GET['type'] ?? 'all';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        if (isset($_POST['approve_verification'])) {
            $document_id = intval($_POST['document_id']);
            $provider_id = intval($_POST['provider_id']);
            
            // Update document status
            $stmt = $db->prepare("UPDATE verification_documents SET status = 'approved', reviewer_id = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $document_id]);
            
            // Update provider verification level
            updateProviderVerificationLevel($db, $provider_id);
            
            // Send notification to provider
            sendVerificationNotification($db, $provider_id, 'approved', 'Document approved');
            
            $success = "Verification document approved successfully";
            
        } elseif (isset($_POST['reject_verification'])) {
            $document_id = intval($_POST['document_id']);
            $provider_id = intval($_POST['provider_id']);
            $rejection_reason = sanitize($_POST['rejection_reason']);
            
            // Update document status
            $stmt = $db->prepare("UPDATE verification_documents SET status = 'rejected', notes = ?, reviewer_id = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$rejection_reason, $_SESSION['user_id'], $document_id]);
            
            // Send notification to provider
            sendVerificationNotification($db, $provider_id, 'rejected', $rejection_reason);
            
            $success = "Verification document rejected";
            
        } elseif (isset($_POST['bulk_action'])) {
            $action = sanitize($_POST['bulk_action']);
            $selected_docs = $_POST['selected_docs'] ?? [];
            
            if (empty($selected_docs)) {
                $errors[] = "Please select at least one document";
            } else {
                $placeholders = implode(',', array_fill(0, count($selected_docs), '?'));
                
                switch ($action) {
                    case 'approve':
                        $stmt = $db->prepare("UPDATE verification_documents SET status = 'approved', reviewer_id = ?, reviewed_at = NOW() WHERE id IN ($placeholders)");
                        $params = array_merge([$_SESSION['user_id']], $selected_docs);
                        $stmt->execute($params);
                        
                        // Update verification levels for affected providers
                        foreach ($selected_docs as $doc_id) {
                            $stmt = $db->prepare("SELECT provider_id FROM verification_documents WHERE id = ?");
                            $stmt->execute([$doc_id]);
                            $provider_id = $stmt->fetchColumn();
                            if ($provider_id) {
                                updateProviderVerificationLevel($db, $provider_id);
                            }
                        }
                        
                        $success = count($selected_docs) . " document(s) approved";
                        break;
                        
                    case 'reject':
                        $stmt = $db->prepare("UPDATE verification_documents SET status = 'rejected', reviewer_id = ?, reviewed_at = NOW() WHERE id IN ($placeholders)");
                        $params = array_merge([$_SESSION['user_id']], $selected_docs);
                        $stmt->execute($params);
                        $success = count($selected_docs) . " document(s) rejected";
                        break;
                        
                    case 'delete':
                        $stmt = $db->prepare("DELETE FROM verification_documents WHERE id IN ($placeholders)");
                        $stmt->execute($selected_docs);
                        $success = count($selected_docs) . " document(s) deleted";
                        break;
                }
            }
        } elseif (isset($_POST['verify_provider'])) {
            $provider_id = intval($_POST['provider_id']);
            $verification_level = sanitize($_POST['verification_level']);
            $notes = sanitize($_POST['verification_notes']);
            
            // Update provider verification
            $stmt = $db->prepare("UPDATE service_providers SET verification_level = ? WHERE id = ?");
            $stmt->execute([$verification_level, $provider_id]);
            
            // Update all pending documents as reviewed
            $stmt = $db->prepare("UPDATE verification_documents SET reviewer_id = ?, reviewed_at = NOW() WHERE provider_id = ? AND status = 'pending'");
            $stmt->execute([$_SESSION['user_id'], $provider_id]);
            
            // Send notification
            sendVerificationNotification($db, $provider_id, 'level_updated', "Verification level set to $verification_level");
            
            $success = "Provider verification level updated";
            
        } elseif (isset($_POST['update_settings'])) {
            $settings = [
                'auto_approve_verifications' => isset($_POST['auto_approve_verifications']) ? 1 : 0,
                'verification_required' => isset($_POST['verification_required']) ? 1 : 0,
                'document_expiry_days' => intval($_POST['document_expiry_days']),
                'min_verification_score' => intval($_POST['min_verification_score']),
                'enable_email_alerts' => isset($_POST['enable_email_alerts']) ? 1 : 0
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = "Verification settings updated";
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "Failed to process request: " . $e->getMessage();
    }
}

// Helper functions
function updateProviderVerificationLevel($db, $provider_id) {
    // Calculate verification score
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_docs,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_docs
        FROM verification_documents 
        WHERE provider_id = ?
    ");
    $stmt->execute([$provider_id]);
    $result = $stmt->fetch();
    
    if ($result['total_docs'] > 0) {
        $approval_rate = ($result['approved_docs'] / $result['total_docs']) * 100;
        
        // Determine verification level
        $verification_level = 'none';
        if ($approval_rate >= 80) {
            $verification_level = 'verified';
        } elseif ($approval_rate >= 50) {
            $verification_level = 'partial';
        }
        
        $stmt = $db->prepare("UPDATE service_providers SET verification_level = ? WHERE id = ?");
        $stmt->execute([$verification_level, $provider_id]);
    }
}

function sendVerificationNotification($db, $provider_id, $type, $message) {
    // Get provider email
    $stmt = $db->prepare("
        SELECT u.email, u.full_name 
        FROM users u 
        JOIN service_providers sp ON u.id = sp.user_id 
        WHERE sp.id = ?
    ");
    $stmt->execute([$provider_id]);
    $provider = $stmt->fetch();
    
    if ($provider) {
        // Log notification (you can implement email/SMS sending here)
        try {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, status) 
                VALUES ((SELECT user_id FROM service_providers WHERE id = ?), ?, ?, ?, 'pending')
            ");
            $title = "Verification Update";
            $full_message = "Your verification request has been $type. $message";
            $stmt->execute([$provider_id, 'verification', $title, $full_message]);
        } catch (Exception $e) {
            // Notifications table may not exist or other error - log but don't fail
            error_log("Notification error: " . $e->getMessage());
        }
    }
}

// Get verification settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

// Build query for verifications
$query = "
    SELECT 
        vd.*,
        vd.notes AS rejection_reason,
        u.full_name as provider_name,
        u.email as provider_email,
        u.phone as provider_phone,
        sp.profession,
        sp.verification_level,
        u2.full_name as reviewer_name
    FROM verification_documents vd
    JOIN service_providers sp ON vd.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN users u2 ON vd.reviewer_id = u2.id
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter_status !== 'all') {
    $query .= " AND vd.status = ?";
    $params[] = $filter_status;
}

if ($verification_type !== 'all' && $verification_type !== '') {
    $query .= " AND vd.document_type = ?";
    $params[] = $verification_type;
}

if (!empty($search_query)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR sp.profession LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY vd.uploaded_at DESC";

// Get verifications
$stmt = $db->prepare($query);
$stmt->execute($params);
$verifications = $stmt->fetchAll();

// Get stats
$stats = [
    'total' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents", []),
    'pending' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents WHERE status = ?", ['pending']),
    'approved' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents WHERE status = ?", ['approved']),
    'rejected' => fetchCount($db, "SELECT COUNT(*) FROM verification_documents WHERE status = ?", ['rejected']),
    'providers_pending' => fetchCount($db, "
        SELECT COUNT(DISTINCT provider_id) 
        FROM verification_documents 
        WHERE status = 'pending'
    ", []),
    'providers_verified' => fetchCount($db, "
        SELECT COUNT(*) 
        FROM service_providers 
        WHERE verification_level = 'verified'
    ", [])
];

// Get document types
$document_types = $db->query("
    SELECT DISTINCT document_type 
    FROM verification_documents 
    ORDER BY document_type
")->fetchAll(PDO::FETCH_COLUMN);

// Get recent activity
$recent_activity = $db->query("
    SELECT 
        vd.*,
        vd.notes AS rejection_reason,
        u.full_name as provider_name,
        u2.full_name as reviewer_name,
        sp.profession
    FROM verification_documents vd
    JOIN service_providers sp ON vd.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN users u2 ON vd.reviewer_id = u2.id
    ORDER BY COALESCE(vd.reviewed_at, vd.uploaded_at) DESC
    LIMIT 10
")->fetchAll();

// Helper function for fetchCount
function fetchCount($db, $query, $params) {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Management - BII LocalFinder Admin</title>
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
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        /* Page Header */
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
        }
        
        .page-header p {
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
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-card.pending .stat-icon { background: var(--warning); }
        .stat-card.approved .stat-icon { background: var(--success); }
        .stat-card.rejected .stat-icon { background: var(--danger); }
        .stat-card.total .stat-icon { background: var(--primary); }
        
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
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
        
        /* Status Badges */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-partial {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-none {
            background: #e5e7eb;
            color: #374151;
        }
        
        /* Document Type Badge */
        .document-type {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
            text-transform: uppercase;
        }
        
        /* Action Buttons */
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Modal Styles */
        .modal-document-preview {
            max-width: 800px;
        }
        
        .document-image {
            max-height: 500px;
            width: 100%;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        /* Recent Activity */
        .activity-timeline {
            margin-top: 1rem;
        }
        
        .activity-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .activity-icon.approved { background: var(--success); }
        .activity-icon.rejected { background: var(--danger); }
        .activity-icon.uploaded { background: var(--primary); }
        
        /* Verification Progress */
        .verification-progress {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--success);
            border-radius: 4px;
            transition: width 0.3s ease;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                font-size: 0.875rem;
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
        
        /* Settings Panel */
        .settings-panel {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        /* Checkbox Styles */
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-shield-alt me-2"></i> Verification Management</h1>
                <p>Review and manage service provider verification requests</p>
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

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Documents</p>
                </div>
                
                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Review</p>
                </div>
                
                <div class="stat-card approved">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
                
                <div class="stat-card rejected">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #8b5cf6;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3><?php echo $stats['providers_verified']; ?></h3>
                    <p>Verified Providers</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f59e0b;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3><?php echo $stats['providers_pending']; ?></h3>
                    <p>Providers Pending</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Document Type</label>
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $verification_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach ($document_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $verification_type === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search provider name, email, or profession..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="verifications.php" class="btn btn-outline-danger">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <form method="POST" id="bulkForm" class="bulk-actions">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label" for="selectAll">
                        Select All
                    </label>
                </div>
                
                <select name="bulk_action" class="form-select" style="width: auto;">
                    <option value="">Bulk Actions</option>
                    <option value="approve">Approve Selected</option>
                    <option value="reject">Reject Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                
                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction()">
                    <i class="fas fa-play me-2"></i> Apply
                </button>
                
                <div class="ms-auto">
                    <span class="text-muted" id="selectedCount">0 items selected</span>
                </div>
            </form>

            <!-- Verifications Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAllHeader">
                            </th>
                            <th>Provider</th>
                            <th>Document Type</th>
                            <th>Status</th>
                            <th>Verification Level</th>
                            <th>Uploaded</th>
                            <th>Reviewed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($verifications)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-3 d-block"></i>
                                    No verification documents found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($verifications as $verification): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="doc-checkbox" name="selected_docs[]" value="<?php echo $verification['id']; ?>">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($verification['provider_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($verification['provider_email']); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($verification['profession']); ?></small>
                                    </td>
                                    <td>
                                        <span class="document-type">
                                            <?php echo strtoupper(substr($verification['document_type'], 0, 3)); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo ucfirst(str_replace('_', ' ', $verification['document_type'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $verification['status']; ?>">
                                            <?php echo ucfirst($verification['status']); ?>
                                        </span>
                                        <?php if ($verification['rejection_reason']): ?>
                                            <br>
                                            <small class="text-danger">
                                                Reason: <?php echo htmlspecialchars($verification['rejection_reason']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $verification['verification_level'] ?? 'none'; ?>">
                                            <?php echo ucfirst($verification['verification_level'] ?? 'none'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($verification['uploaded_at'])); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('h:i A', strtotime($verification['uploaded_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($verification['reviewed_at']): ?>
                                            <?php echo date('M d, Y', strtotime($verification['reviewed_at'])); ?>
                                            <br>
                                            <small class="text-muted">
                                                By: <?php echo htmlspecialchars($verification['reviewer_name'] ?? 'System'); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Not reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="viewDocument(<?php echo $verification['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($verification['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#approveModal"
                                                        onclick="prepareApprove(<?php echo $verification['id']; ?>, <?php echo $verification['provider_id']; ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rejectModal"
                                                        onclick="prepareReject(<?php echo $verification['id']; ?>, <?php echo $verification['provider_id']; ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-outline-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#verifyProviderModal"
                                                    onclick="prepareProviderVerification(<?php echo $verification['provider_id']; ?>)">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (if needed) -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                    Showing <?php echo count($verifications); ?> of <?php echo $stats['total']; ?> documents
                </div>
                <!-- Pagination links would go here -->
            </div>

            <!-- Recent Activity -->
            <div class="settings-panel mt-4">
                <h4 class="mb-3"><i class="fas fa-history me-2"></i> Recent Activity</h4>
                <div class="activity-timeline">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['status']; ?>">
                                <?php if ($activity['status'] === 'approved'): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($activity['status'] === 'rejected'): ?>
                                    <i class="fas fa-times"></i>
                                <?php else: ?>
                                    <i class="fas fa-upload"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?php echo htmlspecialchars($activity['provider_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['document_type'])); ?> 
                                    was <?php echo $activity['status']; ?>
                                    <?php if ($activity['reviewer_name']): ?>
                                        by <?php echo htmlspecialchars($activity['reviewer_name']); ?>
                                    <?php endif; ?>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <?php echo date('M d, Y h:i A', strtotime($activity['reviewed_at'] ?? $activity['uploaded_at'])); ?>
                                </small>
                            </div>
                            <span class="status-badge badge-<?php echo $activity['status']; ?>">
                                <?php echo ucfirst($activity['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Settings Panel -->
            <div class="settings-panel mt-4">
                <h4 class="mb-3"><i class="fas fa-cog me-2"></i> Verification Settings</h4>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="verification_required" id="verification_required" value="1" 
                                       <?php echo getSetting($db, 'verification_required', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="verification_required">
                                    Require verification for providers
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_approve_verifications" id="auto_approve_verifications" value="1"
                                       <?php echo getSetting($db, 'auto_approve_verifications') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_approve_verifications">
                                    Auto-approve verifications after X days
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_email_alerts" id="enable_email_alerts" value="1"
                                       <?php echo getSetting($db, 'enable_email_alerts', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_email_alerts">
                                    Enable email alerts for new verifications
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Document Expiry (days)</label>
                                <input type="number" class="form-control" name="document_expiry_days" 
                                       value="<?php echo getSetting($db, 'document_expiry_days', '365'); ?>" min="30" max="730">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Minimum Verification Score (%)</label>
                                <input type="number" class="form-control" name="min_verification_score" 
                                       value="<?php echo getSetting($db, 'min_verification_score', '70'); ?>" min="0" max="100">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- View Document Modal -->
    <div class="modal fade" id="viewDocumentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-document-preview">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div id="documentPreview"></div>
                        <div class="mt-3">
                            <a href="#" id="downloadDocument" class="btn btn-outline-primary">
                                <i class="fas fa-download me-2"></i> Download
                            </a>
                        </div>
                    </div>
                    <div id="documentInfo"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="document_id" id="approveDocumentId">
                    <input type="hidden" name="provider_id" id="approveProviderId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title text-success"><i class="fas fa-check-circle me-2"></i> Approve Verification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to approve this verification document?</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will increase the provider's verification score and may upgrade their verification level.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_verification" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="document_id" id="rejectDocumentId">
                    <input type="hidden" name="provider_id" id="rejectProviderId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-times-circle me-2"></i> Reject Verification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" name="rejection_reason" rows="3" placeholder="Provide a reason for rejection..." required></textarea>
                            <small class="text-muted">This will be shown to the provider.</small>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            The provider will be notified and may need to re-upload the document.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_verification" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Verify Provider Modal -->
    <div class="modal fade" id="verifyProviderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="provider_id" id="verifyProviderId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-check me-2"></i> Verify Provider</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Verification Level</label>
                            <select class="form-select" name="verification_level" required>
                                <option value="none">None</option>
                                <option value="partial">Partial</option>
                                <option value="verified">Verified</option>
                                <option value="gold">Gold</option>
                                <option value="premium">Premium</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Notes</label>
                            <textarea class="form-control" name="verification_notes" rows="3" placeholder="Add any notes about this verification..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will set the provider's overall verification level and may affect their visibility in search results.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="verify_provider" class="btn btn-primary">Save Verification</button>
                    </div>
                </form>
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
        
        // Bulk selection
        const selectAllHeader = document.getElementById('selectAllHeader');
        const selectAll = document.getElementById('selectAll');
        const docCheckboxes = document.querySelectorAll('.doc-checkbox');
        const selectedCount = document.getElementById('selectedCount');
        
        function updateSelectedCount() {
            const checkedCount = document.querySelectorAll('.doc-checkbox:checked').length;
            selectedCount.textContent = `${checkedCount} item${checkedCount !== 1 ? 's' : ''} selected`;
        }
        
        selectAllHeader.addEventListener('change', function() {
            docCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            selectAll.checked = this.checked;
            updateSelectedCount();
        });
        
        selectAll.addEventListener('change', function() {
            docCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            selectAllHeader.checked = this.checked;
            updateSelectedCount();
        });
        
        docCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Initialize count
        updateSelectedCount();
        
        // Bulk action confirmation
        function confirmBulkAction() {
            const form = document.getElementById('bulkForm');
            const action = form.querySelector('select[name="bulk_action"]').value;
            const selectedCount = document.querySelectorAll('.doc-checkbox:checked').length;
            
            if (!action) {
                alert('Please select a bulk action');
                return false;
            }
            
            if (selectedCount === 0) {
                alert('Please select at least one document');
                return false;
            }
            
            let message = `Are you sure you want to ${action} ${selectedCount} document(s)?`;
            
            if (action === 'delete') {
                message = `WARNING: This will permanently delete ${selectedCount} document(s). This action cannot be undone.\n\nAre you sure?`;
            }
            
            return confirm(message);
        }
        
        // Document preview
        function viewDocument(documentId) {
            // Fetch document details via AJAX
            fetch(`ajax/get_document.php?id=${documentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const preview = document.getElementById('documentPreview');
                        const info = document.getElementById('documentInfo');
                        const downloadLink = document.getElementById('downloadDocument');
                        
                        // Set image or file preview
                        if (data.document_path.match(/\.(jpg|jpeg|png|gif)$/i)) {
                            preview.innerHTML = `
                                <img src="../uploads/verifications/${data.document_path}" 
                                     class="document-image" 
                                     alt="Document Preview"
                                     onerror="this.src='../assets/images/placeholder.png'">
                            `;
                        } else {
                            preview.innerHTML = `
                                <div class="alert alert-info">
                                    <i class="fas fa-file-alt fa-3x mb-3"></i>
                                    <h5>${data.document_type}</h5>
                                    <p>This is a ${data.document_path.split('.').pop().toUpperCase()} file</p>
                                </div>
                            `;
                        }
                        
                        // Set download link
                        downloadLink.href = `../uploads/verifications/${data.document_path}`;
                        downloadLink.download = data.document_path;
                        
                        // Set document info
                        info.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Provider:</strong> ${data.provider_name}<br>
                                    <strong>Email:</strong> ${data.provider_email}<br>
                                    <strong>Profession:</strong> ${data.profession}
                                </div>
                                <div class="col-md-6">
                                    <strong>Document Type:</strong> ${data.document_type}<br>
                                    <strong>Status:</strong> <span class="badge bg-${data.status === 'approved' ? 'success' : data.status === 'rejected' ? 'danger' : 'warning'}">${data.status}</span><br>
                                    <strong>Uploaded:</strong> ${data.uploaded_at}
                                </div>
                            </div>
                        `;
                        
                        // Show the modal
                        const modal = new bootstrap.Modal(document.getElementById('viewDocumentModal'));
                        modal.show();
                    } else {
                        alert('Failed to load document: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load document. Please check the console for details.');
                });
        }
        
        // Prepare approve action
        function prepareApprove(documentId, providerId) {
            document.getElementById('approveDocumentId').value = documentId;
            document.getElementById('approveProviderId').value = providerId;
        }
        
        // Prepare reject action
        function prepareReject(documentId, providerId) {
            document.getElementById('rejectDocumentId').value = documentId;
            document.getElementById('rejectProviderId').value = providerId;
        }
        
        // Prepare provider verification
        function prepareProviderVerification(providerId) {
            document.getElementById('verifyProviderId').value = providerId;
            
            // Fetch provider current verification level
            fetch(`ajax/get_provider_verification.php?id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.querySelector('#verifyProviderModal select[name="verification_level"]');
                        select.value = data.verification_level;
                        
                        const textarea = document.querySelector('#verifyProviderModal textarea[name="verification_notes"]');
                        textarea.value = data.verification_notes || '';
                    }
                });
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Export functionality
        function exportVerifications(format) {
            const filters = new URLSearchParams(window.location.search);
            filters.set('export', format);
            window.location.href = `export_verifications.php?${filters.toString()}`;
        }
        
        // Quick stats update
        function refreshStats() {
            fetch('ajax/get_verification_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update stats cards
                    document.querySelectorAll('.stat-card h3').forEach(card => {
                        // Update logic would go here
                    });
                });
        }
        
        // Auto-refresh pending count every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetch('ajax/get_pending_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const pendingCard = document.querySelector('.stat-card.pending h3');
                        if (pendingCard && data.pending !== undefined) {
                            pendingCard.textContent = data.pending;
                        }
                    });
            }
        }, 30000);
    </script>
</body>
</html>