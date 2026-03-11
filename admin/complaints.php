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
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update complaint status
    if (isset($_POST['update_complaint_status'])) {
        $complaint_id = intval($_POST['complaint_id']);
        $status = sanitize($_POST['status']);
        $admin_notes = sanitize($_POST['admin_notes'] ?? '');
        
        try {
            $stmt = $db->prepare("
                UPDATE complaints 
                SET status = ?, admin_notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_notes, $complaint_id]);
            
            // Log the status change
            $stmt = $db->prepare("
                INSERT INTO complaint_logs (complaint_id, action, details, admin_id) 
                VALUES (?, 'status_change', ?, ?)
            ");
            $stmt->execute([$complaint_id, "Status changed to: {$status}", $_SESSION['user_id']]);
            
            $success = "Complaint status updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update complaint status: " . $e->getMessage();
        }
    }
    
    // Assign complaint to admin
    if (isset($_POST['assign_complaint'])) {
        $complaint_id = intval($_POST['complaint_id']);
        $assigned_admin_id = intval($_POST['assigned_admin_id']);
        
        try {
            $stmt = $db->prepare("
                UPDATE complaints 
                SET assigned_admin_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$assigned_admin_id, $complaint_id]);
            
            // Log the assignment
            $stmt = $db->prepare("
                INSERT INTO complaint_logs (complaint_id, action, details, admin_id) 
                VALUES (?, 'assignment', 'Assigned to admin ID: {$assigned_admin_id}', ?)
            ");
            $stmt->execute([$complaint_id, $_SESSION['user_id']]);
            
            $success = "Complaint assigned successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to assign complaint: " . $e->getMessage();
        }
    }
    
    // Add internal note
    if (isset($_POST['add_internal_note'])) {
        $complaint_id = intval($_POST['complaint_id']);
        $internal_note = sanitize($_POST['internal_note']);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO complaint_notes (complaint_id, note, admin_id, is_internal) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$complaint_id, $internal_note, $_SESSION['user_id']]);
            
            $success = "Internal note added successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to add internal note: " . $e->getMessage();
        }
    }
    
    // Send response to user
    if (isset($_POST['send_response'])) {
        $complaint_id = intval($_POST['complaint_id']);
        $response_message = sanitize($_POST['response_message']);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO complaint_responses (complaint_id, admin_id, message) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$complaint_id, $_SESSION['user_id'], $response_message]);
            
            // Update complaint status if needed
            $stmt = $db->prepare("UPDATE complaints SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$complaint_id]);
            
            $success = "Response sent successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to send response: " . $e->getMessage();
        }
    }
    
    // Bulk actions
    if (isset($_POST['bulk_action'])) {
        $action = sanitize($_POST['bulk_action']);
        $complaint_ids = $_POST['complaint_ids'] ?? [];
        
        if (empty($complaint_ids)) {
            $errors[] = "Please select complaints to perform bulk action";
        } else {
            try {
                $placeholders = str_repeat('?,', count($complaint_ids) - 1) . '?';
                
                if ($action === 'delete') {
                    $stmt = $db->prepare("DELETE FROM complaints WHERE id IN ($placeholders)");
                    $stmt->execute($complaint_ids);
                    $success = count($complaint_ids) . " complaints deleted successfully";
                } elseif ($action === 'close') {
                    $stmt = $db->prepare("UPDATE complaints SET status = 'resolved', updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($complaint_ids);
                    $success = count($complaint_ids) . " complaints marked as resolved";
                } elseif ($action === 'reopen') {
                    $stmt = $db->prepare("UPDATE complaints SET status = 'open', updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($complaint_ids);
                    $success = count($complaint_ids) . " complaints reopened";
                }
            } catch (Exception $e) {
                $errors[] = "Failed to perform bulk action: " . $e->getMessage();
            }
        }
    }
}

// Build query for complaints
$query = "
    SELECT 
        c.*,
        u.full_name as user_name,
        u.email as user_email,
        u.phone as user_phone,
        sp.profession,
        pv.full_name as provider_name,
        pv.email as provider_email,
        a.full_name as assigned_admin_name,
        COUNT(DISTINCT ca.id) as attachment_count,
        COUNT(DISTINCT cr.id) as response_count
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN service_providers sp ON c.provider_id = sp.id
    LEFT JOIN users pv ON sp.user_id = pv.id
    LEFT JOIN users a ON c.assigned_admin_id = a.id
    LEFT JOIN complaint_attachments ca ON c.id = ca.complaint_id
    LEFT JOIN complaint_responses cr ON c.id = cr.complaint_id
    WHERE 1=1
";

$count_query = "
    SELECT COUNT(DISTINCT c.id)
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN service_providers sp ON c.provider_id = sp.id
    LEFT JOIN users pv ON sp.user_id = pv.id
    WHERE 1=1
";

$params = [];
$count_params = [];

if (!empty($search)) {
    $query .= " AND (c.description LIKE ? OR u.full_name LIKE ? OR pv.full_name LIKE ? OR sp.profession LIKE ?)";
    $count_query .= " AND (c.description LIKE ? OR u.full_name LIKE ? OR pv.full_name LIKE ? OR sp.profession LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
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
$stmt = $db->prepare($query);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_complaints = $count_stmt->fetchColumn();
$total_pages = ceil($total_complaints / $limit);

// Get complaint statistics
function getComplaintStats($db) {
    $stats = [];
    
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN priority_level = 'high' THEN 1 ELSE 0 END) as `high_priority`,
            SUM(CASE WHEN priority_level = 'medium' THEN 1 ELSE 0 END) as `medium_priority`,
            SUM(CASE WHEN priority_level = 'low' THEN 1 ELSE 0 END) as `low_priority`,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as `last_7_days`
        FROM complaints
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching complaint stats: " . $e->getMessage());
        $stats = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'closed' => 0,
            'high_priority' => 0,
            'medium_priority' => 0,
            'low_priority' => 0,
            'last_7_days' => 0
        ];
    }
    
    return $stats;
}

$complaint_stats = getComplaintStats($db);

// Get available admins for assignment
$admins = $db->query("
    SELECT id, full_name, email 
    FROM users 
    WHERE user_type = 'admin' AND is_active = 1
    ORDER BY full_name
")->fetchAll();

// Complaint types
$complaint_types = [
    'service_quality' => 'Service Quality Issues',
    'professional_behavior' => 'Unprofessional Behavior',
    'pricing_dispute' => 'Pricing Dispute',
    'scheduling' => 'Scheduling/Timing Issues',
    'communication' => 'Communication Problems',
    'safety_concerns' => 'Safety Concerns',
    'fraud' => 'Fraud or Scam',
    'property_damage' => 'Property Damage',
    'other' => 'Other Issues'
];

// Function to get complaint details
function getComplaintDetails($db, $complaint_id) {
    $stmt = $db->prepare("
        SELECT 
            c.*,
            u.full_name as user_name,
            u.email as user_email,
            u.phone as user_phone,
            sp.profession,
            pv.full_name as provider_name,
            pv.email as provider_email,
            a.full_name as assigned_admin_name
        FROM complaints c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN service_providers sp ON c.provider_id = sp.id
        LEFT JOIN users pv ON sp.user_id = pv.id
        LEFT JOIN users a ON c.assigned_admin_id = a.id
        WHERE c.id = ?
    ");
    $stmt->execute([$complaint_id]);
    return $stmt->fetch();
}

// Function to get complaint attachments
function getComplaintAttachments($db, $complaint_id) {
    $stmt = $db->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ?");
    $stmt->execute([$complaint_id]);
    return $stmt->fetchAll();
}

// Function to get complaint responses
function getComplaintResponses($db, $complaint_id) {
    $stmt = $db->prepare("
        SELECT cr.*, u.full_name as admin_name 
        FROM complaint_responses cr 
        LEFT JOIN users u ON cr.admin_id = u.id 
        WHERE cr.complaint_id = ? 
        ORDER BY cr.created_at DESC
    ");
    $stmt->execute([$complaint_id]);
    return $stmt->fetchAll();
}

// Function to get complaint notes
function getComplaintNotes($db, $complaint_id) {
    $stmt = $db->prepare("
        SELECT cn.*, u.full_name as admin_name 
        FROM complaint_notes cn 
        LEFT JOIN users u ON cn.admin_id = u.id 
        WHERE cn.complaint_id = ? 
        ORDER BY cn.created_at DESC
    ");
    $stmt->execute([$complaint_id]);
    return $stmt->fetchAll();
}

// Function to get complaint logs
function getComplaintLogs($db, $complaint_id) {
    $stmt = $db->prepare("
        SELECT cl.*, u.full_name as admin_name 
        FROM complaint_logs cl 
        LEFT JOIN users u ON cl.admin_id = u.id 
        WHERE cl.complaint_id = ? 
        ORDER BY cl.created_at DESC
    ");
    $stmt->execute([$complaint_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints Management - BII LocalFinder</title>
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
        
        .stat-card.closed {
            border-left-color: var(--secondary);
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
        
        .badge-closed {
            background: #e2e3e5;
            color: #383d41;
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
        
        .btn-assign {
            background: var(--warning);
            color: black;
        }
        
        .btn-assign:hover {
            background: #e0a800;
            color: black;
        }
        
        .btn-respond {
            background: var(--success);
            color: white;
        }
        
        .btn-respond:hover {
            background: #157347;
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
        
        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: transparent;
        }
        
        .tab-content {
            padding: 1rem 0;
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
                        <h1><i class="fas fa-flag me-2"></i> Complaints Management</h1>
                        <p>Manage and resolve customer complaints efficiently</p>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-success" onclick="exportComplaints()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                        <button class="btn btn-primary" onclick="showComplaintStats()">
                            <i class="fas fa-chart-bar me-2"></i> Analytics
                        </button>
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

            <!-- Complaint Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $complaint_stats['total']; ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
                <div class="stat-card open">
                    <div class="stat-number"><?php echo $complaint_stats['open']; ?></div>
                    <div class="stat-label">Open Cases</div>
                </div>
                <div class="stat-card in-progress">
                    <div class="stat-number"><?php echo $complaint_stats['in_progress']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card resolved">
                    <div class="stat-number"><?php echo $complaint_stats['resolved']; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
                <div class="stat-card high-priority">
                    <div class="stat-number"><?php echo $complaint_stats['high_priority']; ?></div>
                    <div class="stat-label">High Priority</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $complaint_stats['last_7_days']; ?></div>
                    <div class="stat-label">Last 7 Days</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="filters-card">
                <h3 class="mb-3">Search & Filter Complaints</h3>
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="form-control" placeholder="Search by description, user, or provider...">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="">All Priorities</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
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
                            <a href="complaints.php" class="btn btn-secondary w-100">
                                <i class="fas fa-refresh me-2"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Complaints Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Complaints (<?php echo $total_complaints; ?>)</h3>
                    <div class="bulk-actions">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <label for="selectAll">Select All</label>
                        
                        <select id="bulkAction" class="form-select" style="width: auto;">
                            <option value="">Bulk Actions</option>
                            <option value="close">Mark as Resolved</option>
                            <option value="reopen">Reopen</option>
                            <option value="delete">Delete</option>
                        </select>
                        
                        <button type="button" class="btn btn-primary btn-sm" onclick="applyBulkAction()">
                            Apply
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()">
                                </th>
                                <th>ID</th>
                                <th>Complaint Details</th>
                                <th>User</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="complaint_ids[]" value="<?php echo $complaint['id']; ?>" class="complaint-checkbox">
                                    </td>
                                    <td>#<?php echo $complaint['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($complaint_types[$complaint['complaint_type']] ?? $complaint['complaint_type']); ?></strong>
                                        <div class="text-muted small mt-1" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars(substr($complaint['description'], 0, 100)); ?>...
                                        </div>
                                        <?php if ($complaint['attachment_count'] > 0): ?>
                                            <small class="text-info">
                                                <i class="fas fa-paperclip"></i> <?php echo $complaint['attachment_count']; ?> attachment(s)
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($complaint['response_count'] > 0): ?>
                                            <small class="text-success">
                                                <i class="fas fa-reply"></i> <?php echo $complaint['response_count']; ?> response(s)
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($complaint['anonymous_report']): ?>
                                            <span class="badge badge-anonymous">Anonymous</span>
                                        <?php else: ?>
                                            <div><?php echo htmlspecialchars($complaint['user_name'] ?? 'N/A'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($complaint['user_email'] ?? ''); ?></div>
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
                                    <td>
                                        <?php if ($complaint['assigned_admin_name']): ?>
                                            <?php echo htmlspecialchars($complaint['assigned_admin_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-sm btn-view" 
                                                    onclick="viewComplaintDetails(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn-sm btn-edit" 
                                                    onclick="editComplaintStatus(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn-sm btn-assign" 
                                                    onclick="assignComplaint(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            <button type="button" class="btn-sm btn-respond" 
                                                    onclick="respondToComplaint(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this complaint?')">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                <button type="submit" name="delete_complaint" class="btn-sm btn-delete">
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
                
                <?php if (empty($complaints)): ?>
                    <div class="empty-state">
                        <i class="fas fa-flag"></i>
                        <h3>No complaints found</h3>
                        <p>No complaints found matching your criteria</p>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        Previous
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
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Complaint Details Modal -->
    <div id="complaintDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Complaint Details</h3>
                <button type="button" class="close" onclick="closeModal('complaintDetailsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="complaintDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div id="editStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Complaint Status</h3>
                <button type="button" class="close" onclick="closeModal('editStatusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editStatusForm" method="POST">
                    <input type="hidden" name="complaint_id" id="edit_complaint_id">
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Notes (Internal)</label>
                        <textarea name="admin_notes" id="edit_admin_notes" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_complaint_status" class="btn btn-primary">Update Status</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editStatusModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Complaint Modal -->
    <div id="assignComplaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Complaint</h3>
                <button type="button" class="close" onclick="closeModal('assignComplaintModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignComplaintForm" method="POST">
                    <input type="hidden" name="complaint_id" id="assign_complaint_id">
                    
                    <div class="form-group">
                        <label>Assign To Admin</label>
                        <select name="assigned_admin_id" id="assign_admin_id" class="form-control" required>
                            <option value="">Select Admin</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?php echo $admin['id']; ?>">
                                    <?php echo htmlspecialchars($admin['full_name']); ?> (<?php echo htmlspecialchars($admin['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="assign_complaint" class="btn btn-primary">Assign</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('assignComplaintModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Respond to Complaint Modal -->
    <div id="respondComplaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Respond to Complaint</h3>
                <button type="button" class="close" onclick="closeModal('respondComplaintModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="respondComplaintForm" method="POST">
                    <input type="hidden" name="complaint_id" id="respond_complaint_id">
                    
                    <div class="form-group">
                        <label>Response Message</label>
                        <textarea name="response_message" id="response_message" class="form-control" rows="6" required
                                  placeholder="Enter your response to the user..."></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="send_response" class="btn btn-primary">Send Response</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('respondComplaintModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Action Form -->
    <form id="bulkActionForm" method="POST" style="display: none;">
        <input type="hidden" name="bulk_action" id="bulk_action_value">
        <div id="bulk_complaint_ids"></div>
    </form>

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
        function viewComplaintDetails(complaintId) {
            fetch(`ajax/get_complaint_details.php?id=${complaintId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('complaintDetailsContent').innerHTML = html;
                    document.getElementById('complaintDetailsModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error loading complaint details:', error);
                    alert('Error loading complaint details');
                });
        }

        function editComplaintStatus(complaintId) {
            document.getElementById('edit_complaint_id').value = complaintId;
            document.getElementById('editStatusModal').style.display = 'block';
        }

        function assignComplaint(complaintId) {
            document.getElementById('assign_complaint_id').value = complaintId;
            document.getElementById('assignComplaintModal').style.display = 'block';
        }

        function respondToComplaint(complaintId) {
            document.getElementById('respond_complaint_id').value = complaintId;
            document.getElementById('respondComplaintModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Bulk actions
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.complaint-checkbox');
            const selectAll = document.getElementById('selectAll').checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll;
            });
        }

        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            const checkboxes = document.querySelectorAll('.complaint-checkbox:checked');
            
            if (!action) {
                alert('Please select a bulk action');
                return;
            }
            
            if (checkboxes.length === 0) {
                alert('Please select at least one complaint');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${checkboxes.length} complaint(s)?`)) {
                const form = document.getElementById('bulkActionForm');
                const container = document.getElementById('bulk_complaint_ids');
                
                // Clear previous inputs
                container.innerHTML = '';
                
                // Add complaint IDs
                checkboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'complaint_ids[]';
                    input.value = checkbox.value;
                    container.appendChild(input);
                });
                
                // Set action and submit
                document.getElementById('bulk_action_value').value = action;
                form.submit();
            }
        }

        function exportComplaints() {
            // Add export functionality here
            const params = new URLSearchParams(window.location.search);
            window.open(`export_complaints.php?${params.toString()}`, '_blank');
        }

        function showComplaintStats() {
            // Implementation for showing advanced analytics
            alert('Advanced analytics feature would be implemented here');
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
    </script>
</body>
</html>