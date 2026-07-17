<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/language.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Load platform settings
$platform_name = getPlatformName();
$contact_email = getContactEmail();

// Search and filter parameters for user complaints
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle new complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $provider_id = intval($_POST['provider_id']);
    $complaint_type = sanitize($_POST['complaint_type']);
    $complaint_description = sanitize($_POST['complaint_description']);
    $priority_level = sanitize($_POST['priority_level']);
    $anonymous_report = isset($_POST['anonymous_report']) ? 1 : 0;
    
    // Validate required fields
    if (empty($complaint_type) || empty($complaint_description)) {
        $errors[] = __('complaints.fill_required', [], 'dashboard');
    }
    
    // Validate provider exists
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE id = ?");
    $stmt->execute([$provider_id]);
    if (!$stmt->fetch()) {
        $errors[] = __('complaints.invalid_provider', [], 'dashboard');
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert complaint
            $stmt = $db->prepare("
                INSERT INTO complaints 
                (user_id, provider_id, complaint_type, description, priority_level, anonymous_report, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'open')
            ");
            
            $stmt->execute([
                $anonymous_report ? null : $_SESSION['user_id'],
                $provider_id,
                $complaint_type,
                $complaint_description,
                $priority_level,
                $anonymous_report
            ]);
            
            $complaint_id = $db->lastInsertId();

            // Handle file attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../uploads/complaints/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = time() . '_' . sanitize_filename($_FILES['attachments']['name'][$key]);
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $stmt = $db->prepare("
                                INSERT INTO complaint_attachments (complaint_id, file_name, file_path) 
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$complaint_id, $file_name, $file_path]);
                        }
                    }
                }
            }
            
            $db->commit();
            
            // Notify admin
            $admin_stmt = $db->prepare("SELECT email, full_name FROM users WHERE user_type = 'admin'");
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll();
            
            foreach ($admins as $admin) {
                Mailer::sendComplaintNotification(
                    $admin['email'],
                    $admin['full_name'],
                    $complaint_id,
                    $complaint_type,
                    $priority_level
                );
            }
            
            $ref = 'COMP' . str_pad($complaint_id, 6, '0', STR_PAD_LEFT);
            $success = __('complaints.submitted_success', ['ref' => $ref], 'dashboard');
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = __('complaints.submit_failed', [], 'dashboard') . $e->getMessage();
            error_log("Complaint submission error: " . $e->getMessage());
        }
    }
}

// Handle complaint status update (for users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint_status'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $user_feedback = sanitize($_POST['user_feedback']);
    
    try {
        // Verify user owns this complaint
        $stmt = $db->prepare("
            SELECT id FROM complaints 
            WHERE id = ? AND (user_id = ? OR anonymous_report = 1)
        ");
        $stmt->execute([$complaint_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            $update_stmt = $db->prepare("
                UPDATE complaints 
                SET user_feedback = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$user_feedback, $complaint_id]);
            $success = __('complaints.feedback_updated', [], 'dashboard');
        } else {
            $errors[] = __('complaints.not_found', [], 'dashboard');
        }
    } catch (Exception $e) {
        $errors[] = __('complaints.feedback_update_failed', [], 'dashboard');
        error_log("Complaint update error: " . $e->getMessage());
    }
}

// Build query for user complaints with filters
$query = "
    SELECT 
        c.*,
        sp.profession,
        u.full_name as provider_name,
        u.profile_image as provider_image,
        COUNT(DISTINCT ca.id) as attachment_count,
        COUNT(DISTINCT cr.id) as response_count
    FROM complaints c
    LEFT JOIN service_providers sp ON c.provider_id = sp.id
    LEFT JOIN users u ON sp.user_id = u.id
    LEFT JOIN complaint_attachments ca ON c.id = ca.complaint_id
    LEFT JOIN complaint_responses cr ON c.id = cr.complaint_id
    WHERE (c.user_id = ? OR c.anonymous_report = 1)
";

$count_query = "
    SELECT COUNT(DISTINCT c.id)
    FROM complaints c
    WHERE (c.user_id = ? OR c.anonymous_report = 1)
";

$params = [$user_id = $_SESSION['user_id']];
$count_params = [$user_id];

if (!empty($search)) {
    $query .= " AND (c.description LIKE ? OR u.full_name LIKE ? OR sp.profession LIKE ?)";
    $count_query .= " AND (c.description LIKE ? OR u.full_name LIKE ? OR sp.profession LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
    array_push($count_params, $search_term, $search_term, $search_term);
}

if (!empty($status_filter)) {
    $query .= " AND c.status = ?";
    $count_query .= " AND c.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

if (!empty($priority_filter)) {
    $query .= " AND c.priority_level = ?";
    $count_query .= " AND c.priority_level = ?";
    $params[] = $priority_filter;
    $count_params[] = $priority_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(c.created_at) >= ?";
    $count_query .= " AND DATE(c.created_at) >= ?";
    $params[] = $date_from;
    $count_params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(c.created_at) <= ?";
    $count_query .= " AND DATE(c.created_at) <= ?";
    $params[] = $date_to;
    $count_params[] = $date_to;
}

$query .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset";

// Execute queries
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $user_complaints = $stmt->fetchAll();

    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_complaints = $count_stmt->fetchColumn();
    $total_pages = ceil($total_complaints / $limit);
} catch (Exception $e) {
    error_log("Error fetching user complaints: " . $e->getMessage());
    $user_complaints = [];
    $total_complaints = 0;
    $total_pages = 1;
}

// Get available providers for complaint form
$providers = [];
try {
    $stmt = $db->prepare("
        SELECT sp.id, u.full_name, sp.profession, u.profile_image
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.is_active = 1 AND sp.is_banned = 0
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $providers = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching providers: " . $e->getMessage());
}

// Complaint statistics
$complaint_stats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'high_priority' => 0,
    'last_7_days' => 0
];

try {
    $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN `priority_level` = 'high' THEN 1 ELSE 0 END) as `high_priority`,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as `last_7_days`
        FROM complaints 
        WHERE user_id = ? OR anonymous_report = 1
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $complaint_stats = $stats_stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching complaint stats: " . $e->getMessage());
}

// Complaint types for dropdown
$complaint_types = [
    'service_quality' => __('complaints.type.service_quality', [], 'dashboard'),
    'professional_behavior' => __('complaints.type.professional_behavior', [], 'dashboard'),
    'pricing_dispute' => __('complaints.type.pricing_dispute', [], 'dashboard'),
    'scheduling' => __('complaints.type.scheduling', [], 'dashboard'),
    'communication' => __('complaints.type.communication', [], 'dashboard'),
    'safety_concerns' => __('complaints.type.safety_concerns', [], 'dashboard'),
    'fraud' => __('complaints.type.fraud', [], 'dashboard'),
    'property_damage' => __('complaints.type.property_damage', [], 'dashboard'),
    'other' => __('complaints.type.other', [], 'dashboard')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('complaints.title', [], 'dashboard'); ?> - <?php echo htmlspecialchars($platform_name); ?></title>
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
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
        }
        
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
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.open {
            border-left-color: var(--warning);
        }
        
        .stat-card.in-progress {
            border-left-color: var(--info);
        }
        
        .stat-card.resolved {
            border-left-color: var(--success);
        }
        
        .stat-card.high-priority {
            border-left-color: var(--danger);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--secondary);
            font-weight: 600;
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
        
        .badge-open {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-resolved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-priority-low {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-anonymous {
            background: #e2e3e5;
            color: #383d41;
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
        
        .btn-respond {
            background: var(--success);
            color: white;
        }
        
        .btn-respond:hover {
            background: #157347;
            color: white;
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
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
            background: none;
            border: none;
        }
        
        .close:hover {
            color: var(--dark);
        }
        
        /* Complaint Details */
        .complaint-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .detail-section {
            margin-bottom: 1.5rem;
        }
        
        .detail-section h5 {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }
        
        /* File Upload */
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary);
            background: #f8f9fa;
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary);
            background: #e7f3ff;
        }
        
        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .file-preview-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .remove-file {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
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
                    <h1><i class="fas fa-flag me-2"></i> <?php echo __('complaints.title', [], 'dashboard'); ?></h1>
                    <p><?php echo __('complaints.subtitle', [], 'dashboard'); ?></p>
                </div>
                <div class="quick-actions">
                    <button class="btn btn-success" onclick="exportComplaints()">
                        <i class="fas fa-download me-2"></i> <?php echo __('complaints.export_button', [], 'dashboard'); ?>
                    </button>
                    <button class="btn btn-primary" onclick="openNewComplaintModal()">
                        <i class="fas fa-plus me-2"></i> <?php echo __('complaints.new_complaint', [], 'dashboard'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo __('general.close', [], 'dashboard'); ?>"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo __('general.close', [], 'dashboard'); ?>"></button>
            </div>
        <?php endif; ?>

        <!-- Complaint Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $complaint_stats['total']; ?></div>
                <div class="stat-label"><?php echo __('complaints.stats_total', [], 'dashboard'); ?></div>
            </div>
            <div class="stat-card open">
                <div class="stat-number"><?php echo $complaint_stats['open']; ?></div>
                <div class="stat-label"><?php echo __('complaints.stats_open', [], 'dashboard'); ?></div>
            </div>
            <div class="stat-card in-progress">
                <div class="stat-number"><?php echo $complaint_stats['in_progress']; ?></div>
                <div class="stat-label"><?php echo __('complaints.stats_in_progress', [], 'dashboard'); ?></div>
            </div>
            <div class="stat-card resolved">
                <div class="stat-number"><?php echo $complaint_stats['resolved']; ?></div>
                <div class="stat-label"><?php echo __('complaints.stats_resolved', [], 'dashboard'); ?></div>
            </div>
            <div class="stat-card high-priority">
                <div class="stat-number"><?php echo $complaint_stats['high_priority']; ?></div>
                <div class="stat-label"><?php echo __('complaints.stats_high_priority', [], 'dashboard'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $complaint_stats['last_7_days']; ?></div>
                <div class="stat-label"><?php echo __('complaints.stats_last_7_days', [], 'dashboard'); ?></div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="filters-card">
            <h3 class="mb-3"><?php echo __('complaints.search_filter_title', [], 'dashboard'); ?></h3>
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label"><?php echo __('complaints.search_label', [], 'dashboard'); ?></label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               class="form-control" placeholder="<?php echo __('complaints.search_placeholder', [], 'dashboard'); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="form-label"><?php echo __('complaints.status_label', [], 'dashboard'); ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?php echo __('complaints.all_status', [], 'dashboard'); ?></option>
                            <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>><?php echo __('complaints.status_open', [], 'dashboard'); ?></option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>><?php echo __('complaints.status_in_progress', [], 'dashboard'); ?></option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>><?php echo __('complaints.status_resolved', [], 'dashboard'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="form-label"><?php echo __('complaints.priority_label', [], 'dashboard'); ?></label>
                        <select name="priority" class="form-select">
                            <option value=""><?php echo __('complaints.all_priorities', [], 'dashboard'); ?></option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>><?php echo __('complaints.priority_high', [], 'dashboard'); ?></option>
                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>><?php echo __('complaints.priority_medium', [], 'dashboard'); ?></option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>><?php echo __('complaints.priority_low', [], 'dashboard'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="form-label"><?php echo __('complaints.date_from_label', [], 'dashboard'); ?></label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label class="form-label"><?php echo __('complaints.date_to_label', [], 'dashboard'); ?></label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> <?php echo __('complaints.apply_filters', [], 'dashboard'); ?>
                        </button>
                    </div>
                    <div class="filter-group">
                        <a href="complaints.php" class="btn btn-secondary w-100">
                            <i class="fas fa-refresh me-2"></i> <?php echo __('complaints.reset_filters', [], 'dashboard'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Complaints Table -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo __('complaints.your_complaints', [], 'dashboard'); ?> (<?php echo $total_complaints; ?>)</h3>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo __('complaints.table_id', [], 'dashboard'); ?></th>
                            <th><?php echo __('complaints.table_details', [], 'dashboard'); ?></th>
                            <th><?php echo __('complaints.table_provider', [], 'dashboard'); ?></th>
                            <th><?php echo __('complaints.table_status', [], 'dashboard'); ?></th>
                            <th><?php echo __('complaints.table_priority', [], 'dashboard'); ?></th>
                            <th><?php echo __('complaints.table_created', [], 'dashboard'); ?></th>
                            <th><?php echo __('complaints.table_actions', [], 'dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_complaints as $complaint): ?>
                            <tr>
                                <td>#<?php echo $complaint['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($complaint_types[$complaint['complaint_type']] ?? $complaint['complaint_type']); ?></strong>
                                    <div class="text-muted small mt-1" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars(substr($complaint['description'], 0, 100)); ?>...
                                    </div>
                                    <?php if ($complaint['attachment_count'] > 0): ?>
                                        <small class="text-info">
                                            <i class="fas fa-paperclip"></i> <?php echo $complaint['attachment_count']; ?> <?php echo __('complaints.attachments', [], 'dashboard'); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($complaint['response_count'] > 0): ?>
                                        <small class="text-success">
                                            <i class="fas fa-reply"></i> <?php echo $complaint['response_count']; ?> <?php echo __('complaints.responses', [], 'dashboard'); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($complaint['anonymous_report']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user-secret"></i> <?php echo __('complaints.anonymous_report', [], 'dashboard'); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($complaint['provider_name'] ?? 'N/A'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($complaint['profession'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $complaint['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-priority-<?php echo $complaint['priority_level']; ?>">
                                        <?php echo ucfirst($complaint['priority_level']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-sm btn-view" 
                                                onclick="viewComplaintDetails(<?php echo $complaint['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-sm btn-edit" 
                                                onclick="openFeedbackModal(<?php echo $complaint['id']; ?>)">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                        <?php if ($complaint['status'] === 'open'): ?>
                                            <button type="button" class="btn-sm btn-respond" 
                                                    onclick="escalateComplaint(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($user_complaints)): ?>
                <div class="empty-state">
                    <i class="fas fa-flag"></i>
                    <h3><?php echo __('complaints.no_complaints_found', [], 'dashboard'); ?></h3>
                    <p><?php echo __('complaints.no_complaints_message', [], 'dashboard'); ?></p>
                    <button class="btn btn-primary mt-3" onclick="openNewComplaintModal()">
                        <i class="fas fa-plus me-2"></i> <?php echo __('complaints.submit_first_complaint', [], 'dashboard'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <?php echo __('complaints.previous', [], 'dashboard'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <?php echo __('complaints.next', [], 'dashboard'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Complaint Details Modal -->
    <div id="complaintDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo __('complaints.details_title', [], 'dashboard'); ?></h3>
                <button type="button" class="close" onclick="closeModal('complaintDetailsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="complaintDetailsContent">
                    <!-- Content will be loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- New Complaint Modal -->
    <div id="newComplaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo __('complaints.new_complaint_title', [], 'dashboard'); ?></h3>
                <button type="button" class="close" onclick="closeModal('newComplaintModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="complaintForm">
                    <div class="form-group">
                        <label><?php echo __('complaints.select_provider_label', [], 'dashboard'); ?> <span class="text-danger">*</span></label>
                        <select name="provider_id" class="form-control" required>
                            <option value=""><?php echo __('complaints.select_provider_placeholder', [], 'dashboard'); ?></option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?php echo $provider['id']; ?>">
                                    <?php echo htmlspecialchars($provider['full_name']); ?> - 
                                    <?php echo htmlspecialchars($provider['profession']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php echo __('complaints.complaint_type_label', [], 'dashboard'); ?> <span class="text-danger">*</span></label>
                        <select name="complaint_type" class="form-control" required>
                            <option value=""><?php echo __('complaints.select_complaint_type', [], 'dashboard'); ?></option>
                            <?php foreach ($complaint_types as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php echo __('complaints.priority_level_label', [], 'dashboard'); ?></label>
                        <select name="priority_level" class="form-control">
                            <option value="low"><?php echo __('complaints.priority_low', [], 'dashboard'); ?></option>
                            <option value="medium" selected><?php echo __('complaints.priority_medium', [], 'dashboard'); ?></option>
                            <option value="high"><?php echo __('complaints.priority_high', [], 'dashboard'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php echo __('complaints.description_label', [], 'dashboard'); ?> <span class="text-danger">*</span></label>
                        <textarea name="complaint_description" class="form-control" required rows="6" 
                                  placeholder="<?php echo __('complaints.description_placeholder', [], 'dashboard'); ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label><?php echo __('complaints.attachments_label', [], 'dashboard'); ?> (<?php echo __('general.optional', [], 'dashboard'); ?>)</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                            <p class="mb-2"><?php echo __('complaints.file_upload_text1', [], 'dashboard'); ?></p>
                            <p class="text-muted small"><?php echo __('complaints.file_upload_text2', [], 'dashboard'); ?></p>
                            <input type="file" name="attachments[]" multiple style="display: none;" id="fileInput">
                        </div>
                        <div class="file-preview" id="filePreview"></div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="anonymous_report" id="anonymousReport">
                            <label class="form-check-label" for="anonymousReport">
                                <?php echo __('complaints.anonymous_submission', [], 'dashboard'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong><?php echo __('complaints.important_note', [], 'dashboard'); ?></strong> <?php echo __('complaints.important_message', [], 'dashboard'); ?>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" name="submit_complaint" class="btn btn-primary"><?php echo __('complaints.submit_complaint_button', [], 'dashboard'); ?></button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('newComplaintModal')"><?php echo __('general.cancel', [], 'dashboard'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo __('complaints.add_feedback', [], 'dashboard'); ?></h3>
                <button type="button" class="close" onclick="closeModal('feedbackModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="feedbackForm">
                    <input type="hidden" name="complaint_id" id="feedbackComplaintId">
                    
                    <div class="form-group">
                        <label><?php echo __('complaints.additional_feedback_label', [], 'dashboard'); ?></label>
                        <textarea name="user_feedback" class="form-control" rows="6" 
                                  placeholder="<?php echo __('complaints.additional_feedback_placeholder', [], 'dashboard'); ?>"></textarea>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" name="update_complaint_status" class="btn btn-primary"><?php echo __('complaints.save_feedback', [], 'dashboard'); ?></button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('feedbackModal')"><?php echo __('general.cancel', [], 'dashboard'); ?></button>
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
        
        // Modal functions
        function openNewComplaintModal() {
            document.getElementById('newComplaintModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openFeedbackModal(complaintId) {
            document.getElementById('feedbackComplaintId').value = complaintId;
            document.getElementById('feedbackModal').style.display = 'block';
        }

        function viewComplaintDetails(complaintId) {
            // Simulate loading complaint details
            const content = `
                <div class="complaint-details">
                    <div class="detail-section">
                        <h5>Complaint Information</h5>
                        <div class="detail-row">
                            <div class="detail-item">
                                <span class="detail-label">Complaint ID</span>
                                <span class="detail-value">#${complaintId}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status</span>
                                <span class="badge badge-open">Open</span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-item">
                                <span class="detail-label">Provider</span>
                                <span class="detail-value">John Doe</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Profession</span>
                                <span class="detail-value">Plumber</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h5>Complaint Details</h5>
                        <p>Detailed complaint description would appear here...</p>
                    </div>
                    
                    <div class="detail-section">
                        <h5>Timeline</h5>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-date">Today, 10:30 AM</div>
                                <div class="timeline-content">
                                    <strong>Complaint Submitted</strong>
                                    <p>You submitted this complaint</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('complaintDetailsContent').innerHTML = content;
            document.getElementById('complaintDetailsModal').style.display = 'block';
        }

        function escalateComplaint(complaintId) {
            if (confirm(<?php echo json_encode(__('complaints.escalate_confirm', [], 'dashboard')); ?>)) {
                alert(<?php echo json_encode(__('complaints.escalated', [], 'dashboard')); ?>);
                // In real implementation, this would be an AJAX call
            }
        }

        function exportComplaints() {
            const params = new URLSearchParams(window.location.search);
            alert(<?php echo json_encode(__('complaints.export_notice', [], 'dashboard')); ?>);
            // window.open('export_user_complaints.php?' + params.toString(), '_blank');
        }

        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');

        if (fileUploadArea && fileInput) {
            fileUploadArea.addEventListener('click', () => fileInput.click());
            
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });
            
            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('dragover');
            });
            
            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
                fileInput.files = e.dataTransfer.files;
                updateFilePreview();
            });
            
            fileInput.addEventListener('change', updateFilePreview);
        }

        function updateFilePreview() {
            filePreview.innerHTML = '';
            const files = fileInput.files;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-preview-item';
                
                const fileName = document.createElement('span');
                fileName.textContent = file.name;
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-file';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.onclick = () => removeFile(i);
                
                fileItem.appendChild(fileName);
                fileItem.appendChild(removeBtn);
                filePreview.appendChild(fileItem);
            }
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            const files = fileInput.files;
            
            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }
            
            fileInput.files = dt.files;
            updateFilePreview();
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

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation
        document.getElementById('complaintForm')?.addEventListener('submit', function(e) {
            const description = this.querySelector('textarea[name="complaint_description"]');
            if (description.value.trim().length < 10) {
                e.preventDefault();
                alert(<?php echo json_encode(__('complaints.description_validation', [], 'dashboard')); ?>);
                description.focus();
            }
        });
    </script>
</body>
</html>