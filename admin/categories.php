<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$db = Database::getInstance()->getConnection();

// Ensure the class file is loaded (avoid duplicate require issues)
if (!class_exists('AIBookingHandler')) {
    require_once __DIR__ . '/../includes/ai_booking.php';
}

$aiHandler = null;
try {
    // Instantiate the AI booking handler as an object (do not use $this in global scope)
    $aiHandler = new AIBookingHandler($db);
} catch (Throwable $e) {
    // Log the detailed error and continue without AI handler to avoid fatal crash
    error_log('AIBookingHandler init failed: ' . $e->getMessage());
    // Optionally add a user-friendly message to $errors for admin display
    $errors[] = 'AI booking subsystem failed to initialize. Check error log.';
}

$success = '';
$errors = [];

// Get AI professions from the booking handler's keyword mapping
$aiProfessions = [
    'electrician', 'plumber', 'carpenter', 'cleaner', 'painter', 
    'mechanic', 'gardener', 'mason', 'welder', 'tailor', 'roofer'
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add category
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name']);
        $icon = sanitize($_POST['icon']);
        $description = sanitize($_POST['description']);
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
        $is_ai_enabled = isset($_POST['is_ai_enabled']) ? 1 : 0;
        $ai_keywords = isset($_POST['ai_keywords']) ? sanitize($_POST['ai_keywords']) : '';
        
        try {
            // Generate AI keywords if not provided and AI is enabled
            if ($is_ai_enabled && empty($ai_keywords)) {
                $ai_keywords = generateAIKeywords($name);
            }
            
            $stmt = $db->prepare("INSERT INTO categories (name, icon, description, is_premium, monthly_fee, is_ai_enabled, ai_keywords) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $icon, $description, $is_premium, $monthly_fee, $is_ai_enabled, $ai_keywords]);
            $category_id = $db->lastInsertId();
            
            // If AI enabled, add to AI professions mapping
            if ($is_ai_enabled) {
                updateAIMapping($category_id, $name, $ai_keywords);
            }
            
            $success = "Category added successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to add category: " . $e->getMessage();
        }
    }
    
    // Update category
    if (isset($_POST['update_category'])) {
        $id = intval($_POST['category_id']);
        $name = sanitize($_POST['name']);
        $icon = sanitize($_POST['icon']);
        $description = sanitize($_POST['description']);
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
        $is_ai_enabled = isset($_POST['is_ai_enabled']) ? 1 : 0;
        $ai_keywords = isset($_POST['ai_keywords']) ? sanitize($_POST['ai_keywords']) : '';
        
        try {
            $stmt = $db->prepare("UPDATE categories SET name = ?, icon = ?, description = ?, is_premium = ?, monthly_fee = ?, is_ai_enabled = ?, ai_keywords = ? WHERE id = ?");
            $stmt->execute([$name, $icon, $description, $is_premium, $monthly_fee, $is_ai_enabled, $ai_keywords, $id]);
            
            // Update AI mapping
            if ($is_ai_enabled) {
                updateAIMapping($id, $name, $ai_keywords);
            }
            
            $success = "Category updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update category: " . $e->getMessage();
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category'])) {
        $id = intval($_POST['category_id']);
        
        try {
            // Check if category has providers
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM provider_services WHERE category_id = ?");
            $checkStmt->execute([$id]);
            $providerCount = $checkStmt->fetchColumn();
            
            if ($providerCount > 0) {
                $errors[] = "Cannot delete category. There are {$providerCount} providers associated with this category.";
            } else {
                // Remove from AI mapping
                removeFromAIMapping($id);
                
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Category deleted successfully";
            }
        } catch (Exception $e) {
            $errors[] = "Failed to delete category: " . $e->getMessage();
        }
    }
    
    // Bulk actions
    if (isset($_POST['bulk_action'])) {
        $action = sanitize($_POST['bulk_action']);
        $category_ids = $_POST['category_ids'] ?? [];
        
        if (empty($category_ids)) {
            $errors[] = "No categories selected for bulk action";
        } else {
            try {
                $placeholders = str_repeat('?,', count($category_ids) - 1) . '?';
                
                switch ($action) {
                    case 'delete':
                        // Check for providers before deletion
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM provider_services WHERE category_id IN ($placeholders)");
                        $checkStmt->execute($category_ids);
                        $providerCount = $checkStmt->fetchColumn();
                        
                        if ($providerCount > 0) {
                            $errors[] = "Cannot delete categories. There are providers associated with some categories.";
                        } else {
                            // Remove from AI mapping
                            foreach ($category_ids as $cat_id) {
                                removeFromAIMapping($cat_id);
                            }
                            
                            $stmt = $db->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
                            $stmt->execute($category_ids);
                            $success = count($category_ids) . " categories deleted successfully";
                        }
                        break;
                        
                    case 'make_premium':
                        $stmt = $db->prepare("UPDATE categories SET is_premium = 1 WHERE id IN ($placeholders)");
                        $stmt->execute($category_ids);
                        $success = count($category_ids) . " categories marked as premium";
                        break;
                        
                    case 'make_free':
                        $stmt = $db->prepare("UPDATE categories SET is_premium = 0 WHERE id IN ($placeholders)");
                        $stmt->execute($category_ids);
                        $success = count($category_ids) . " categories marked as free";
                        break;
                        
                    case 'enable_ai':
                        $stmt = $db->prepare("UPDATE categories SET is_ai_enabled = 1 WHERE id IN ($placeholders)");
                        $stmt->execute($category_ids);
                        
                        // Generate AI keywords for newly enabled categories
                        foreach ($category_ids as $cat_id) {
                            $catStmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
                            $catStmt->execute([$cat_id]);
                            $category = $catStmt->fetch();
                            if ($category) {
                                $keywords = generateAIKeywords($category['name']);
                                $updateStmt = $db->prepare("UPDATE categories SET ai_keywords = ? WHERE id = ?");
                                $updateStmt->execute([$keywords, $cat_id]);
                                updateAIMapping($cat_id, $category['name'], $keywords);
                            }
                        }
                        $success = count($category_ids) . " categories enabled for AI";
                        break;
                        
                    case 'disable_ai':
                        $stmt = $db->prepare("UPDATE categories SET is_ai_enabled = 0 WHERE id IN ($placeholders)");
                        $stmt->execute($category_ids);
                        
                        // Remove from AI mapping
                        foreach ($category_ids as $cat_id) {
                            removeFromAIMapping($cat_id);
                        }
                        $success = count($category_ids) . " categories disabled for AI";
                        break;
                }
            } catch (Exception $e) {
                $errors[] = "Failed to perform bulk action: " . $e->getMessage();
            }
        }
    }
}

// Get categories with provider counts and AI status
$categories = $db->query("
    SELECT c.*, COUNT(ps.provider_id) as provider_count 
    FROM categories c 
    LEFT JOIN provider_services ps ON c.id = ps.category_id 
    GROUP BY c.id 
    ORDER BY c.is_ai_enabled DESC, c.name
")->fetchAll();

// Get AI categories stats
$ai_categories = $db->query("SELECT COUNT(*) FROM categories WHERE is_ai_enabled = 1")->fetchColumn();
$ai_booking_count = $db->query("SELECT COUNT(*) FROM bookings WHERE ai_generated = 1")->fetchColumn();
$ai_success_rate = $db->query("
    SELECT 
        CASE 
            WHEN COUNT(*) > 0 THEN 
                ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1)
            ELSE 0 
        END as success_rate
    FROM bookings 
    WHERE ai_generated = 1
")->fetchColumn();

// Get total stats
$total_categories = count($categories);
$total_providers = $db->query("SELECT COUNT(*) FROM service_providers WHERE is_active = 1")->fetchColumn();
$total_bookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$avg_rating = $db->query("SELECT AVG(rating) FROM reviews")->fetchColumn();

// Helper functions for AI integration
function generateAIKeywords($categoryName) {
    $keywords = strtolower($categoryName);
    
    // Add related keywords based on category
    $keywordMap = [
        'electrician' => 'electrical,electricity,socket,outlet,wiring,light,power,circuit',
        'plumber' => 'plumbing,pipe,leak,tap,sink,toilet,drain,water',
        'carpenter' => 'carpentry,wood,door,window,furniture,repair,install',
        'cleaner' => 'cleaning,house,office,deep clean,dust,vacuum',
        'painter' => 'painting,paint,wall,interior,exterior,color',
        'mechanic' => 'car,vehicle,repair,engine,brake,tire',
        'gardener' => 'gardening,lawn,tree,plant,landscaping',
        'mason' => 'masonry,brick,wall,construction,cement',
        'welder' => 'welding,metal,gate,fence,fabrication',
        'tailor' => 'sewing,clothes,alteration,stitch,repair',
        'roofer' => 'roof,leak,repair,tiles,gutter'
    ];
    
    $catLower = strtolower($categoryName);
    foreach ($keywordMap as $key => $value) {
        if (strpos($catLower, $key) !== false) {
            $keywords .= ',' . $value;
            break;
        }
    }
    
    return $keywords;
}

function updateAIMapping($categoryId, $categoryName, $keywords) {
    global $db;
    
    // This would update the AI system's internal mapping
    // For now, we'll just store it in the categories table
    return true;
}

function removeFromAIMapping($categoryId) {
    global $db;
    
    // Remove category from AI mapping
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - BII LocalFinder</title>
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
            --ai-color: #10b981;
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
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .card h3 {
            margin: 0 0 1.5rem 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.3rem;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 0.75rem;
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
        
        /* Category Icons */
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .category-icon.ai-enabled {
            background: linear-gradient(135deg, var(--ai-color), #0ca678);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
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
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--secondary);
            font-size: 0.8rem;
        }
        
        .form-group a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .form-group a:hover {
            text-decoration: underline;
        }
        
        /* Category Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .category-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .category-card.ai-enabled {
            border-left-color: var(--ai-color);
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .category-card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        
        .category-card-icon.ai-enabled {
            background: linear-gradient(135deg, var(--ai-color), #0ca678);
        }
        
        .category-card-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .category-card-desc {
            color: var(--secondary);
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 1rem;
        }
        
        .category-card-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: var(--secondary);
        }
        
        .category-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
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
        
        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #0b5ed7;
            color: white;
        }
        
        .btn-ai {
            background: var(--ai-color);
            color: white;
        }
        
        .btn-ai:hover {
            background: #0ca678;
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .btn-edit {
            background: var(--info);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        /* Stats Cards */
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
        }
        
        .stat-card.ai-stat {
            border-left-color: var(--ai-color);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.3rem;
        }
        
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
        }
        
        /* Premium Badge */
        .premium-badge {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #856404;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        
        /* AI Badge */
        .ai-badge {
            background: linear-gradient(135deg, var(--ai-color), #0ca678);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            position: absolute;
            top: 1rem;
            left: 1rem;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }
        
        .bulk-actions.active {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
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
        
        .modal-title {
            margin-bottom: 1.5rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Icon Preview */
        .icon-preview {
            margin-top: 0.5rem;
            padding: 1rem;
            border: 2px dashed #e9ecef;
            border-radius: 6px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .icon-preview i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        /* Keywords Tags */
        .keywords-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .keyword-tag {
            background: #e9ecef;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: var(--dark);
        }
        
        /* AI Keywords Input */
        .keywords-input-container {
            position: relative;
        }
        
        .keywords-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .keyword-suggestion {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .keyword-suggestion:hover {
            background: #f8f9fa;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            font-weight: 500;
        }
        
        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1><i class="fas fa-tag me-2"></i> Category Management</h1>
                        <p>Manage service categories and AI integration</p>
                    </div>
                    <div class="quick-actions">
                        <button class="btn btn-success" onclick="exportCategories()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                        <button class="btn btn-ai" onclick="showAITestModal()">
                            <i class="fas fa-robot me-2"></i> Test AI
                        </button>
                        <button class="btn btn-primary" onclick="showAddCategoryModal()">
                            <i class="fas fa-plus me-2"></i> Add Category
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

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3><?php echo $total_categories; ?></h3>
                    <p>Total Categories</p>
                </div>

                <div class="stat-card ai-stat">
                    <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3><?php echo $ai_categories; ?></h3>
                    <p>AI-Enabled Categories</p>
                </div>

                <div class="stat-card ai-stat">
                    <div class="stat-icon" style="background: #cffafe; color: #0e7490;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3><?php echo $ai_booking_count; ?></h3>
                    <p>AI Bookings</p>
                </div>

                <div class="stat-card ai-stat">
                    <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3><?php echo $ai_success_rate; ?>%</h3>
                    <p>AI Success Rate</p>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <span id="selectedCount">0 categories selected</span>
                <select class="form-select" style="width: auto;" id="bulkActionSelect">
                    <option value="">Choose action...</option>
                    <option value="make_premium">Mark as Premium</option>
                    <option value="make_free">Mark as Free</option>
                    <option value="enable_ai">Enable AI</option>
                    <option value="disable_ai">Disable AI</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="performBulkAction()">Apply</button>
                <button class="btn btn-secondary btn-sm" onclick="clearSelection()">Clear</button>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('all')">
                    <i class="fas fa-th-large me-2"></i> All Categories
                </div>
                <div class="tab" onclick="switchTab('ai')">
                    <i class="fas fa-robot me-2"></i> AI-Enabled
                </div>
                <div class="tab" onclick="switchTab('premium')">
                    <i class="fas fa-crown me-2"></i> Premium
                </div>
            </div>

            <!-- Categories Grid View -->
            <div class="card">
                <h3><i class="fas fa-th-large me-2"></i> Categories Overview</h3>
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <h3>No Categories Found</h3>
                        <p>Get started by adding your first service category</p>
                        <button class="btn btn-primary mt-3" onclick="showAddCategoryModal()">
                            <i class="fas fa-plus me-2"></i> Add First Category
                        </button>
                    </div>
                <?php else: ?>
                    <div class="categories-grid" id="categoriesGrid">
                        <?php foreach ($categories as $cat): ?>
                            <div class="category-card <?php echo $cat['is_ai_enabled'] ? 'ai-enabled' : ''; ?>" 
                                 data-type="<?php echo $cat['is_premium'] ? 'premium' : 'free'; ?>"
                                 data-ai="<?php echo $cat['is_ai_enabled'] ? 'enabled' : 'disabled'; ?>">
                                <?php if ($cat['is_premium']): ?>
                                    <span class="premium-badge">PREMIUM</span>
                                <?php endif; ?>
                                <?php if ($cat['is_ai_enabled']): ?>
                                    <span class="ai-badge">AI</span>
                                <?php endif; ?>
                                <div class="category-card-icon <?php echo $cat['is_ai_enabled'] ? 'ai-enabled' : ''; ?>">
                                    <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                </div>
                                <div class="category-card-name">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </div>
                                <div class="category-card-desc">
                                    <?php echo htmlspecialchars($cat['description']); ?>
                                </div>
                                <?php if ($cat['ai_keywords']): ?>
                                <div class="keywords-tags">
                                    <?php 
                                    $keywords = explode(',', $cat['ai_keywords']);
                                    foreach (array_slice($keywords, 0, 3) as $keyword): 
                                    ?>
                                        <span class="keyword-tag"><?php echo trim($keyword); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($keywords) > 3): ?>
                                        <span class="keyword-tag">+<?php echo count($keywords) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="category-card-stats">
                                    <div class="stat">
                                        <div class="stat-value"><?php echo $cat['provider_count']; ?></div>
                                        <div class="stat-label">Providers</div>
                                    </div>
                                    <?php if ($cat['is_premium']): ?>
                                    <div class="stat">
                                        <div class="stat-value">RWF <?php echo number_format($cat['monthly_fee']); ?></div>
                                        <div class="stat-label">Monthly</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="category-actions">
                                    <button class="btn btn-edit btn-sm" onclick="editCategory(<?php echo $cat['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($cat['is_ai_enabled']): ?>
                                    <button class="btn btn-ai btn-sm" onclick="testAICategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                        <i class="fas fa-robot"></i> Test
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-delete btn-sm" onclick="showDeleteModal(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Categories Table View -->
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><i class="fas fa-table me-2"></i> All Categories</h3>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">Select All</label>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()">
                                </th>
                                <th>ID</th>
                                <th>Icon</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>AI Keywords</th>
                                <th>Providers</th>
                                <th>Type</th>
                                <th>AI</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fas fa-tags fa-2x mb-2 d-block"></i>
                                        No categories found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr data-type="<?php echo $cat['is_premium'] ? 'premium' : 'free'; ?>"
                                        data-ai="<?php echo $cat['is_ai_enabled'] ? 'enabled' : 'disabled'; ?>">
                                        <td>
                                            <input type="checkbox" class="category-checkbox" value="<?php echo $cat['id']; ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td>#<?php echo $cat['id']; ?></td>
                                        <td>
                                            <div class="category-icon <?php echo $cat['is_ai_enabled'] ? 'ai-enabled' : ''; ?>">
                                                <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                        <td>
                                            <?php if ($cat['ai_keywords']): ?>
                                                <div class="keywords-tags">
                                                    <?php 
                                                    $keywords = explode(',', $cat['ai_keywords']);
                                                    foreach (array_slice($keywords, 0, 2) as $keyword): 
                                                    ?>
                                                        <span class="keyword-tag"><?php echo trim($keyword); ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($keywords) > 2): ?>
                                                        <span class="keyword-tag">+<?php echo count($keywords) - 2; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $cat['provider_count']; ?> providers</span>
                                        </td>
                                        <td>
                                            <?php if ($cat['is_premium']): ?>
                                                <span class="badge bg-warning">Premium</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Free</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cat['is_ai_enabled']): ?>
                                                <span class="badge" style="background: var(--ai-color);">AI Enabled</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">AI Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-edit btn-sm" onclick="editCategory(<?php echo $cat['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($cat['is_ai_enabled']): ?>
                                                <button class="btn btn-ai btn-sm" onclick="testAICategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                                    <i class="fas fa-robot"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-delete btn-sm" onclick="showDeleteModal(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
            <h3 class="modal-title">Add New Category</h3>
            <form method="POST" id="addCategoryForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="categoryName">Category Name</label>
                            <input type="text" id="categoryName" name="name" class="form-control" required 
                                   placeholder="e.g., Electrician, Plumber, Carpenter"
                                   oninput="suggestKeywords()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="categoryIcon">Icon Class</label>
                            <input type="text" id="categoryIcon" name="icon" class="form-control" required 
                                   placeholder="e.g., fa-bolt, fa-wrench, fa-hammer">
                            <small>
                                Visit <a href="https://fontawesome.com/icons" target="_blank">Font Awesome</a> for icon names
                            </small>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="categoryDescription">Description</label>
                    <textarea id="categoryDescription" name="description" class="form-control" rows="3" 
                              placeholder="Brief description of the service category"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="monthlyFee">Monthly Fee (RWF)</label>
                            <input type="number" id="monthlyFee" name="monthly_fee" class="form-control" value="0" min="0" step="1000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_premium" id="is_premium" value="1">
                                <label class="form-check-label" for="is_premium">Premium Category (Paid)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_ai_enabled" id="is_ai_enabled" value="1" checked>
                        <label class="form-check-label" for="is_ai_enabled">
                            <i class="fas fa-robot me-1"></i> Enable AI Recognition
                        </label>
                        <small>Allow AI to recognize this category from natural language</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="ai_keywords">AI Keywords (comma-separated)</label>
                    <div class="keywords-input-container">
                        <input type="text" id="ai_keywords" name="ai_keywords" class="form-control" 
                               placeholder="e.g., electrical,electricity,socket,outlet,wiring">
                        <small>Keywords that AI should look for to identify this category</small>
                        <div class="keywords-suggestions" id="keywordsSuggestions"></div>
                    </div>
                </div>
                <div class="icon-preview">
                    <i class="fas fa-question" id="iconPreview"></i>
                    <div class="mt-2"><small>Icon Preview</small></div>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editCategoryModal')">&times;</span>
            <h3 class="modal-title">Edit Category</h3>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_categoryName">Category Name</label>
                            <input type="text" id="edit_categoryName" name="name" class="form-control" required
                                   oninput="suggestKeywords('edit')">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_categoryIcon">Icon Class</label>
                            <input type="text" id="edit_categoryIcon" name="icon" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_categoryDescription">Description</label>
                    <textarea id="edit_categoryDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_monthlyFee">Monthly Fee (RWF)</label>
                            <input type="number" id="edit_monthlyFee" name="monthly_fee" class="form-control" min="0" step="1000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_premium" id="edit_is_premium" value="1">
                                <label class="form-check-label" for="edit_is_premium">Premium Category (Paid)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_ai_enabled" id="edit_is_ai_enabled" value="1">
                        <label class="form-check-label" for="edit_is_ai_enabled">
                            <i class="fas fa-robot me-1"></i> Enable AI Recognition
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_ai_keywords">AI Keywords (comma-separated)</label>
                    <div class="keywords-input-container">
                        <input type="text" id="edit_ai_keywords" name="ai_keywords" class="form-control"
                               placeholder="e.g., electrical,electricity,socket,outlet,wiring">
                        <small>Keywords that AI should look for to identify this category</small>
                        <div class="keywords-suggestions" id="editKeywordsSuggestions"></div>
                    </div>
                </div>
                <div class="icon-preview">
                    <i class="fas fa-question" id="editIconPreview"></i>
                    <div class="mt-2"><small>Icon Preview</small></div>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="update_category" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div id="deleteCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteCategoryModal')">&times;</span>
            <h3 class="modal-title">Delete Category</h3>
            <form method="POST" id="deleteCategoryForm">
                <input type="hidden" name="category_id" id="delete_category_id">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
                <p>Are you sure you want to delete the category "<strong id="deleteCategoryName"></strong>"?</p>
                <p class="text-muted">This will remove the category from the system and AI recognition.</p>
                <div class="form-buttons">
                    <button type="submit" name="delete_category" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Delete Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteCategoryModal')">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div id="bulkDeleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('bulkDeleteModal')">&times;</span>
            <h3 class="modal-title">Delete Multiple Categories</h3>
            <form method="POST" id="bulkDeleteForm">
                <input type="hidden" name="bulk_action" value="delete">
                <div id="bulkDeleteCategories"></div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
                <p>Are you sure you want to delete <strong id="bulkDeleteCount">0</strong> categories?</p>
                <p class="text-muted">This will permanently remove the selected categories from the system.</p>
                <div class="form-buttons">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Delete Categories
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bulkDeleteModal')">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- AI Test Modal -->
    <div id="aiTestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('aiTestModal')">&times;</span>
            <h3 class="modal-title">Test AI Recognition</h3>
            <div class="form-group">
                <label for="aiTestInput">Enter a natural language request:</label>
                <textarea id="aiTestInput" class="form-control" rows="3" 
                          placeholder="e.g., 'I need an electrician to fix my socket tomorrow at 2pm in Kigali'"></textarea>
            </div>
            <div class="form-group">
                <label for="aiTestCategory">Select category to test:</label>
                <select id="aiTestCategory" class="form-control">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <?php if ($cat['is_ai_enabled']): ?>
                            <option value="<?php echo $cat['id']; ?>" data-keywords="<?php echo htmlspecialchars($cat['ai_keywords'] ?? ''); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-ai w-100" onclick="testAIRecognition()">
                <i class="fas fa-robot me-2"></i> Test Recognition
            </button>
            <div id="aiTestResult" class="mt-3" style="display: none;">
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle me-2"></i> AI Recognition Result</h5>
                    <div id="aiResultDetails"></div>
                </div>
            </div>
            <div class="form-buttons mt-3">
                <button type="button" class="btn btn-secondary" onclick="closeModal('aiTestModal')">
                    <i class="fas fa-times me-2"></i> Close
                </button>
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
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Modal functions
        function showAddCategoryModal() {
            document.getElementById('addCategoryForm').reset();
            document.getElementById('iconPreview').className = 'fas fa-question';
            document.getElementById('keywordsSuggestions').innerHTML = '';
            document.getElementById('addCategoryModal').style.display = 'block';
        }
        
        function editCategory(categoryId) {
            // Fetch category data via AJAX
            fetch(`get_category.php?id=${categoryId}`)
                .then(response => response.json())
                .then(category => {
                    if (category) {
                        document.getElementById('edit_category_id').value = category.id;
                        document.getElementById('edit_categoryName').value = category.name;
                        document.getElementById('edit_categoryIcon').value = category.icon;
                        document.getElementById('edit_categoryDescription').value = category.description;
                        document.getElementById('edit_monthlyFee').value = category.monthly_fee;
                        document.getElementById('edit_is_premium').checked = category.is_premium == 1;
                        document.getElementById('edit_is_ai_enabled').checked = category.is_ai_enabled == 1;
                        document.getElementById('edit_ai_keywords').value = category.ai_keywords || '';
                        document.getElementById('editIconPreview').className = 'fas ' + category.icon;
                        
                        document.getElementById('editCategoryModal').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching category:', error);
                    // Fallback to PHP data
                    const categories = <?php echo json_encode($categories); ?>;
                    const category = categories.find(cat => cat.id == categoryId);
                    
                    if (category) {
                        document.getElementById('edit_category_id').value = category.id;
                        document.getElementById('edit_categoryName').value = category.name;
                        document.getElementById('edit_categoryIcon').value = category.icon;
                        document.getElementById('edit_categoryDescription').value = category.description;
                        document.getElementById('edit_monthlyFee').value = category.monthly_fee;
                        document.getElementById('edit_is_premium').checked = category.is_premium == 1;
                        document.getElementById('edit_is_ai_enabled').checked = category.is_ai_enabled == 1;
                        document.getElementById('edit_ai_keywords').value = category.ai_keywords || '';
                        document.getElementById('editIconPreview').className = 'fas ' + category.icon;
                        
                        document.getElementById('editCategoryModal').style.display = 'block';
                    }
                });
        }
        
        function showDeleteModal(categoryId, categoryName) {
            document.getElementById('delete_category_id').value = categoryId;
            document.getElementById('deleteCategoryName').textContent = categoryName;
            document.getElementById('deleteCategoryModal').style.display = 'block';
        }
        
        function showAITestModal() {
            document.getElementById('aiTestInput').value = '';
            document.getElementById('aiTestCategory').value = '';
            document.getElementById('aiTestResult').style.display = 'none';
            document.getElementById('aiTestModal').style.display = 'block';
        }
        
        function testAICategory(categoryId, categoryName) {
            document.getElementById('aiTestInput').value = `I need a ${categoryName.toLowerCase()} to help me`;
            document.getElementById('aiTestCategory').value = categoryId;
            document.getElementById('aiTestResult').style.display = 'none';
            document.getElementById('aiTestModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function exportCategories() {
            alert('Export feature would be implemented here');
        }

        // Icon preview functionality
        document.getElementById('categoryIcon').addEventListener('input', function() {
            updateIconPreview(this.value, 'iconPreview');
        });
        
        document.getElementById('edit_categoryIcon').addEventListener('input', function() {
            updateIconPreview(this.value, 'editIconPreview');
        });
        
        function updateIconPreview(iconClass, previewId) {
            const preview = document.getElementById(previewId);
            if (iconClass.trim()) {
                preview.className = 'fas ' + iconClass.trim();
            } else {
                preview.className = 'fas fa-question';
            }
        }

        // Premium category toggle
        document.getElementById('is_premium').addEventListener('change', function() {
            const monthlyFee = document.getElementById('monthlyFee');
            if (this.checked) {
                monthlyFee.value = monthlyFee.value || '5000';
            } else {
                monthlyFee.value = '0';
            }
        });
        
        document.getElementById('edit_is_premium').addEventListener('change', function() {
            const monthlyFee = document.getElementById('edit_monthlyFee');
            if (this.checked) {
                monthlyFee.value = monthlyFee.value || '5000';
            } else {
                monthlyFee.value = '0';
            }
        });

        // AI keywords suggestions
        function suggestKeywords(type = 'add') {
            const inputId = type === 'add' ? 'categoryName' : 'edit_categoryName';
            const suggestionsId = type === 'add' ? 'keywordsSuggestions' : 'editKeywordsSuggestions';
            const keywordsInputId = type === 'add' ? 'ai_keywords' : 'edit_ai_keywords';
            
            const categoryName = document.getElementById(inputId).value.toLowerCase();
            const suggestionsDiv = document.getElementById(suggestionsId);
            const keywordsInput = document.getElementById(keywordsInputId);
            
            if (!categoryName.trim()) {
                suggestionsDiv.innerHTML = '';
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            // Keyword mapping based on AI booking handler
            const keywordMap = {
                'electrician': 'electrical,electricity,socket,outlet,wiring,light,power,circuit',
                'plumber': 'plumbing,pipe,leak,tap,sink,toilet,drain,water',
                'carpenter': 'carpentry,wood,door,window,furniture,repair,install',
                'cleaner': 'cleaning,house,office,deep clean,dust,vacuum',
                'painter': 'painting,paint,wall,interior,exterior,color',
                'mechanic': 'car,vehicle,repair,engine,brake,tire',
                'gardener': 'gardening,lawn,tree,plant,landscaping',
                'mason': 'masonry,brick,wall,construction,cement',
                'welder': 'welding,metal,gate,fence,fabrication',
                'tailor': 'sewing,clothes,alteration,stitch,repair',
                'roofer': 'roof,leak,repair,tiles,gutter'
            };
            
            let suggestions = [];
            let foundMatch = false;
            
            for (const [key, keywords] of Object.entries(keywordMap)) {
                if (categoryName.includes(key)) {
                    suggestions = keywords.split(',');
                    foundMatch = true;
                    break;
                }
            }
            
            if (!foundMatch) {
                // Default suggestions based on common terms
                suggestions = [
                    categoryName,
                    categoryName + ' services',
                    categoryName + ' work',
                    categoryName + ' repair'
                ];
            }
            
            suggestionsDiv.innerHTML = suggestions.map(keyword => 
                `<div class="keyword-suggestion" onclick="selectKeyword('${keyword}', '${type}')">${keyword}</div>`
            ).join('');
            
            suggestionsDiv.style.display = suggestions.length > 0 ? 'block' : 'none';
            
            // Auto-fill keywords if empty
            if (!keywordsInput.value.trim() && foundMatch) {
                keywordsInput.value = suggestions.join(',');
            }
        }
        
        function selectKeyword(keyword, type) {
            const inputId = type === 'add' ? 'ai_keywords' : 'edit_ai_keywords';
            const suggestionsId = type === 'add' ? 'keywordsSuggestions' : 'editKeywordsSuggestions';
            
            const keywordsInput = document.getElementById(inputId);
            const currentKeywords = keywordsInput.value.split(',').map(k => k.trim()).filter(k => k);
            
            if (!currentKeywords.includes(keyword)) {
                currentKeywords.push(keyword);
                keywordsInput.value = currentKeywords.join(', ');
            }
            
            document.getElementById(suggestionsId).style.display = 'none';
        }

        // AI recognition test
        function testAIRecognition() {
            const testInput = document.getElementById('aiTestInput').value;
            const categorySelect = document.getElementById('aiTestCategory');
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            const keywords = selectedOption ? selectedOption.getAttribute('data-keywords') : '';
            
            if (!testInput.trim() || !categorySelect.value) {
                alert('Please enter a test request and select a category');
                return;
            }
            
            const aiKeywords = keywords.split(',').map(k => k.trim().toLowerCase());
            const testInputLower = testInput.toLowerCase();
            
            let matches = [];
            let confidence = 0;
            
            // Check for keyword matches
            aiKeywords.forEach(keyword => {
                if (keyword && testInputLower.includes(keyword)) {
                    matches.push(keyword);
                }
            });
            
            // Calculate confidence
            confidence = matches.length > 0 ? Math.min(0.7 + (matches.length * 0.1), 0.95) : 0.3;
            
            // Display results
            const resultDiv = document.getElementById('aiResultDetails');
            resultDiv.innerHTML = `
                <p><strong>Category:</strong> ${selectedOption.text}</p>
                <p><strong>Confidence:</strong> ${(confidence * 100).toFixed(1)}%</p>
                <p><strong>Matched Keywords:</strong> ${matches.length > 0 ? matches.join(', ') : 'None'}</p>
                <p><strong>Test Input:</strong> "${testInput}"</p>
            `;
            
            document.getElementById('aiTestResult').style.display = 'block';
        }

        // Tab switching
        function switchTab(tab) {
            // Update active tab
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter categories grid
            const cards = document.querySelectorAll('.category-card');
            cards.forEach(card => {
                if (tab === 'all') {
                    card.style.display = 'block';
                } else if (tab === 'ai' && card.dataset.ai === 'enabled') {
                    card.style.display = 'block';
                } else if (tab === 'premium' && card.dataset.type === 'premium') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Filter table rows
            const rows = document.querySelectorAll('tbody tr[data-type]');
            rows.forEach(row => {
                if (tab === 'all') {
                    row.style.display = '';
                } else if (tab === 'ai' && row.dataset.ai === 'enabled') {
                    row.style.display = '';
                } else if (tab === 'premium' && row.dataset.type === 'premium') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Bulk actions functionality
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.category-checkbox');
            const selectAll = document.getElementById('selectAllHeader').checked;
            
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('tr').style.display !== 'none') {
                    checkbox.checked = selectAll;
                }
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.category-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('active');
                selectedCount.textContent = checkboxes.length + ' categories selected';
            } else {
                bulkActions.classList.remove('active');
            }
        }
        
        function performBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const checkboxes = document.querySelectorAll('.category-checkbox:checked');
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            if (checkboxes.length === 0) {
                alert('Please select at least one category');
                return;
            }
            
            const categoryIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (action === 'delete') {
                showBulkDeleteModal(categoryIds);
            } else {
                // Submit form for other bulk actions
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'bulk_action';
                actionInput.value = action;
                form.appendChild(actionInput);
                
                categoryIds.forEach(id => {
                    const input = document.createElement('input');
                    input.name = 'category_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showBulkDeleteModal(categoryIds) {
            document.getElementById('bulkDeleteCount').textContent = categoryIds.length;
            document.getElementById('bulkDeleteCategories').innerHTML = '';
            
            categoryIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'category_ids[]';
                input.value = id;
                document.getElementById('bulkDeleteCategories').appendChild(input);
            });
            
            document.getElementById('bulkDeleteModal').style.display = 'block';
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.category-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAllHeader').checked = false;
            updateBulkActions();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Close keyword suggestions
            document.querySelectorAll('.keywords-suggestions').forEach(suggestions => {
                suggestions.style.display = 'none';
            });
        }

        // Close keyword suggestions when clicking elsewhere
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.keywords-input-container')) {
                document.querySelectorAll('.keywords-suggestions').forEach(suggestions => {
                    suggestions.style.display = 'none';
                });
            }
        });

        // Close modals with close button
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = 'none';
            };
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {



                    const iconInput = this.querySelector('input[name="icon"]');
                    if (iconInput && !iconInput.value.startsWith('fa-')) {
                        e.preventDefault();
                        alert('Icon class must start with "fa-" (e.g., fa-bolt, fa-wrench)');
                        iconInput.focus();
                    }
                });
            });
        });
    </script>
</body>
</html>