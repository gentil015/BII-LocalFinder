<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/ai_helpers.php';
require_once '../includes/provider_requirements.php';
require_once '../includes/profession_titles.php';
require_once '../controllers/pages/provider/ProviderProfileController.php';

requireProvider();

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();
$controller = new ProviderProfileController();

// Get section from URL (default to 'basic')
$section = isset($_GET['section']) ? sanitize($_GET['section']) : 'basic';
$valid_sections = ['basic', 'services', 'portfolio', 'social', 'requirements'];
if (!in_array($section, $valid_sections)) {
    $section = 'basic';
}
$aiHelper = new AIHelper($db);
$success = '';
$errors = [];

$viewData = $controller->index($db, $_SESSION['user_id'], $section);
$provider = $viewData['provider'] ?? null;
$social_platforms = $viewData['social_platforms'] ?? [];
$provider_categories = $viewData['provider_categories'] ?? [];
$provider_category_ids = $viewData['provider_category_ids'] ?? [];
$all_categories = $viewData['all_categories'] ?? [];
$districts = $viewData['districts'] ?? [];
$portfolio_images = $viewData['portfolio_images'] ?? [];
$portfolio_video = $viewData['portfolio_video'] ?? null;
$has_portfolio_video = $viewData['has_portfolio_video'] ?? false;
$portfolio_count = $viewData['portfolio_count'] ?? 0;
$max_portfolio_images = $viewData['max_portfolio_images'] ?? 6;
$portfolio_enabled = $viewData['portfolio_enabled'] ?? true;
$enable_ai_features = $viewData['enable_ai_features'] ?? false;
$ai_description_improvement_enabled = $viewData['ai_description_improvement_enabled'] ?? false;

// Check if AI features are enabled for this provider
$enable_ai_features = false;
if (!empty($provider['id'])) {
    $enable_ai_features = isProviderAIEnabled($provider['id']);
}

// Check if specific AI sub-features are enabled
$ai_description_improvement_enabled = getProviderSetting($provider['id'], 'ai_features_ai_description_improvement') == '1';

// Get provider social links
$social_platforms = [
    'website' => $provider['website'] ?? '',
    'facebook' => $provider['facebook'] ?? '',
    'twitter' => $provider['twitter'] ?? '',
    'instagram' => $provider['instagram'] ?? '',
    'linkedin' => $provider['linkedin'] ?? '',
    'youtube' => $provider['youtube'] ?? '',
    'whatsapp' => $provider['whatsapp'] ?? '',
    'tiktok' => $provider['tiktok'] ?? '',
    'other_social' => $provider['other_social'] ?? '',
    'other_social_label' => $provider['other_social_label'] ?? ''
];

// Get provider categories/services
$stmt = $db->prepare("
    SELECT c.* 
    FROM categories c
    JOIN provider_categories pc ON c.id = pc.category_id
    WHERE pc.provider_id = ?
");
$stmt->execute([$provider['id']]);
$provider_categories = $stmt->fetchAll();
$provider_category_ids = array_column($provider_categories, 'id');

// Get all categories
$stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$all_categories = $stmt->fetchAll();

// Get all districts for dropdown
$districts = getAllDistricts();

// Get portfolio images
$stmt = $db->prepare("
    SELECT * FROM portfolio_images 
    WHERE provider_id = ? AND is_active = 1 
    ORDER BY display_order, uploaded_at DESC
");
$stmt->execute([$provider['id']]);
$portfolio_images = $stmt->fetchAll();
$portfolio_count = count($portfolio_images);

// Get portfolio videos (single video per provider)
$stmt = $db->prepare("
    SELECT * FROM portfolio_videos 
    WHERE provider_id = ? AND is_active = 1 
    ORDER BY uploaded_at DESC
    LIMIT 1
");
$stmt->execute([$provider['id']]);
$portfolio_video = $stmt->fetch();
$has_portfolio_video = !empty($portfolio_video);

// Get portfolio settings
$max_portfolio_images = 6; // Default
$portfolio_enabled = true; // Default

try {
    $stmt = $db->prepare("SELECT value FROM platform_settings WHERE setting_key = ?");
    $stmt->execute(['max_portfolio_images']);
    $result = $stmt->fetch();
    $max_portfolio_images = $result ? intval($result['value']) : 6;
    
    $stmt->execute(['portfolio_enabled']);
    $result = $stmt->fetch();
    $portfolio_enabled = $result ? ($result['value'] === '1') : true;
} catch (Exception $e) {
    // Use defaults
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $result = $controller->handleSubmit($db, $_SESSION['user_id'], $_POST, $_FILES, $_SERVER);
    $success = $result['success'] ?? '';
    $errors = $result['errors'] ?? [];
    $viewData = $result['viewData'] ?? [];

    $provider = $viewData['provider'] ?? null;
    $social_platforms = $viewData['social_platforms'] ?? [];
    $provider_categories = $viewData['provider_categories'] ?? [];
    $provider_category_ids = $viewData['provider_category_ids'] ?? [];
    $all_categories = $viewData['all_categories'] ?? [];
    $districts = $viewData['districts'] ?? [];
    $portfolio_images = $viewData['portfolio_images'] ?? [];
    $portfolio_video = $viewData['portfolio_video'] ?? null;
    $has_portfolio_video = $viewData['has_portfolio_video'] ?? false;
    $portfolio_count = $viewData['portfolio_count'] ?? 0;
    $max_portfolio_images = $viewData['max_portfolio_images'] ?? 6;
    $portfolio_enabled = $viewData['portfolio_enabled'] ?? true;
    $enable_ai_features = $viewData['enable_ai_features'] ?? false;
    $ai_description_improvement_enabled = $viewData['ai_description_improvement_enabled'] ?? false;
}

// Handle AJAX section updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_section'])) {
    header('Content-Type: application/json');
    $response = $controller->handleAjaxSection($db, $_SESSION['user_id'], $_POST, $_FILES, $_SERVER);
    echo json_encode($response);
    exit;
}

// Initialize requirements checker
$requirements = new ProviderRequirements($db, $provider['id']);
$requirements_details = $requirements->getRequirementsWithDetails();
$completion_pct = $requirements->getCompletionPercentage();
$is_profile_complete = $requirements->isComplete();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __("title", [], "profile"); ?> - <?php echo getPlatformName(); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/provider-requirements.css">
    <!-- Dark Mode CSS -->
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --accent:        #0d6efd;
            --accent-dark:   #0a58ca;
            --accent-light:  #eff4ff;
            --success:       #16a34a;
            --success-light: #f0fdf4;
            --danger:        #dc2626;
            --danger-light:  #fef2f2;
            --warning:       #d97706;
            --warning-light: #fffbeb;
            --info:          #0891b2;
            --info-light:    #ecfeff;
            --purple:        #7c3aed;
            --purple-light:  #f5f3ff;
            --surface:       #ffffff;
            --surface-2:     #f7f8fc;
            --border:        #e8eaf0;
            --border-subtle: #f0f2f7;
            --text-primary:  #0f1117;
            --text-secondary:#6b7280;
            --text-muted:    #9ca3af;
            --sidebar-width: 260px;
            --radius-sm:     8px;
            --radius-md:     12px;
            --radius-lg:     16px;
            --radius-xl:     24px;
            --shadow-xs:     0 1px 3px rgba(0,0,0,0.06);
            --shadow-sm:     0 2px 8px rgba(0,0,0,0.07);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.09);
            --transition:    all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --accent:        #3b82f6;
            --accent-dark:   #2563eb;
            --accent-light:  #1e3a8a;
            --success:       #10b981;
            --success-light: #064e3b;
            --danger:        #ef4444;
            --danger-light:  #7f1d1d;
            --warning:       #f59e0b;
            --warning-light: #78350f;
            --info:          #06b6d4;
            --info-light:    #164e63;
            --purple:        #8b5cf6;
            --purple-light:  #2d1b69;
            --surface:       #0f172a;
            --surface-2:     #1e293b;
            --border:        #334155;
            --border-subtle: #475569;
            --text-primary:  #f8fafc;
            --text-secondary:#cbd5e1;
            --text-muted:    #94a3b8;
            --shadow-xs:     0 1px 3px rgba(0,0,0,0.3);
            --shadow-sm:     0 2px 8px rgba(0,0,0,0.4);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.5);
        }

        body {
            background: var(--surface-2);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* ── APP SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh; left: 0; top: 0;
            transition: var(--transition);
            z-index: 1000;
        }
        .sidebar-header { padding: 1.5rem 1.25rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
        .sidebar-header h2 { margin: 0; font-weight: 800; font-size: 1.1rem; color: var(--accent); }
        .sidebar-header p  { margin: 0.3rem 0 0; color: var(--text-muted); font-size: 0.78rem; }
        .sidebar-menu { list-style: none; padding: 0.75rem; margin: 0; }
        .sidebar-menu li { margin: 2px 0; }
        .sidebar-menu a {
            color: var(--text-secondary); text-decoration: none;
            padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.65rem;
            transition: var(--transition); border-radius: var(--radius-sm);
            font-size: 0.875rem; font-weight: 500;
        }
        .sidebar-menu a:hover { background: var(--accent-light); color: var(--accent); }
        .sidebar-menu a.active { background: var(--accent); color: white; font-weight: 600; }
        .sidebar-menu i { width: 18px; font-size: 0.9rem; flex-shrink: 0; }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.75rem 2rem;
            min-height: 100vh;
        }

        /* ── ALERTS ── */
        .alert {
            border-radius: var(--radius-md); border: 1px solid transparent;
            padding: 0.875rem 1.125rem; margin-bottom: 1.25rem; font-size: 0.875rem;
            display: flex; align-items: flex-start; gap: 0.75rem;
        }
        .alert > * { margin: 0; }
        .alert i { flex-shrink: 0; margin-top: 0.1rem; }
        .alert .alert-body { flex: 1; }
        .alert-success  { background: var(--success-light); color: var(--success); border-color: #bbf7d0; }
        .alert-danger   { background: var(--danger-light);  color: var(--danger);  border-color: #fecaca; }
        .alert-warning  { background: var(--warning-light); color: var(--warning); border-color: #fde68a; }
        .alert-info     { background: var(--info-light);    color: var(--info);    border-color: #a5f3fc; }
        .alert-secondary{ background: var(--surface-2);     color: var(--text-secondary); border-color: var(--border); }

        .maintenance-warning { background: var(--warning-light); border-color: #fde68a; color: var(--warning); }

        /* AI Banner */
        .ai-features {
            background: linear-gradient(135deg, var(--purple), #5b21b6);
            color: white; border: none; border-radius: var(--radius-md);
            padding: 0.875rem 1.25rem; margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: 0.875rem;
        }
        .ai-features strong { font-weight: 700; }
        .ai-features p { margin: 0.15rem 0 0; font-size: 0.8rem; opacity: 0.88; }

        .ai-badge {
            background: rgba(255,255,255,0.2); color: white;
            padding: 0.18rem 0.6rem; border-radius: 100px;
            font-size: 0.65rem; font-weight: 800; letter-spacing: 0.3px;
        }

        .section-alert { margin-bottom: 1rem; }

        /* ── PROFILE HEADER ── */
        .profile-header {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .profile-header-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #1e40af);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 1.5rem;
            overflow: hidden; flex-shrink: 0;
            border: 3px solid var(--surface);
            box-shadow: 0 0 0 2px var(--accent);
        }

        .profile-header-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .profile-header-text h1 {
            color: var(--text-primary); margin: 0 0 0.2rem; font-weight: 800;
            font-size: 1.3rem; letter-spacing: -0.3px;
        }
        .profile-header-text p { color: var(--text-muted); margin: 0; font-size: 0.82rem; }
        .profile-header-meta { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.4rem; flex-wrap: wrap; }
        .profile-meta-chip {
            display: inline-flex; align-items: center; gap: 0.3rem;
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: 100px; padding: 0.18rem 0.65rem;
            font-size: 0.72rem; font-weight: 600; color: var(--text-secondary);
        }
        .profile-meta-chip i { font-size: 0.65rem; color: var(--accent); }

        /* ── VIEW TABS (pill switcher) ── */
        .view-tabs {
            display: flex; gap: 0.25rem;
            margin-bottom: 1.5rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.3rem; width: fit-content;
            box-shadow: var(--shadow-xs);
            flex-wrap: wrap;
        }
        .view-tab {
            padding: 0.55rem 1.1rem;
            text-decoration: none; color: var(--text-secondary);
            font-weight: 600; font-size: 0.8rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: flex; align-items: center; gap: 0.4rem;
        }
        .view-tab:hover { color: var(--accent); text-decoration: none; }
        .view-tab.active { background: var(--accent); color: white; }
        .view-tab i { font-size: 0.82rem; }

        /* ── SECTION CONTENT ── */
        .profile-section-content { display: none; }
        .profile-section-content.active { display: block; animation: secIn 0.2s ease; }
        @keyframes secIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        /* ── CARDS ── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.625rem 1.75rem;
            margin-bottom: 1.375rem;
            border: 1px solid var(--border) !important;
            box-shadow: var(--shadow-xs);
            transition: box-shadow 0.18s ease;
        }
        .card:hover { box-shadow: var(--shadow-sm); }

        .card-title {
            color: var(--text-primary); margin-bottom: 1.125rem;
            font-weight: 800; display: flex; align-items: center; gap: 0.6rem;
            font-size: 0.975rem; letter-spacing: -0.2px;
            padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--border-subtle);
        }
        /* Use h2 in card same as card-title */
        .card h2 {
            color: var(--text-primary); margin-bottom: 1.125rem;
            font-weight: 800; display: flex; align-items: center; gap: 0.6rem;
            font-size: 0.975rem; letter-spacing: -0.2px;
            padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--border-subtle);
        }
        .card h2 i, .card-title i { color: var(--accent); font-size: 0.9rem; }
        .card > p:nth-of-type(1) { color: var(--text-muted); font-size: 0.82rem; margin-bottom: 1.125rem; }

        /* Card save footer */
        .card-save-footer {
            margin-top: 1.25rem; padding-top: 1.125rem;
            border-top: 1px solid var(--border-subtle);
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .card-save-note { color: var(--text-muted); font-size: 0.72rem; }

        /* ── PROFILE IMAGE HERO ── */
        .profile-image-section {
            display: flex; align-items: center; gap: 1.75rem;
            flex-wrap: wrap; margin-bottom: 1.5rem;
        }
        .current-image {
            width: 100px; height: 100px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #1e40af);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 2.25rem;
            overflow: hidden; flex-shrink: 0;
            border: 3px solid var(--surface);
            box-shadow: 0 0 0 3px var(--accent), var(--shadow-md);
        }
        .current-image img { width: 100%; height: 100%; object-fit: cover; }

        .image-upload-area { display: flex; flex-direction: column; gap: 0.5rem; }
        .image-upload-btn { position: relative; }
        .image-upload-btn input[type="file"] { position: absolute; opacity:0; width:0; height:0; }
        .image-upload-btn label {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            background: var(--accent); border-radius: var(--radius-sm);
            color: white; cursor: pointer; transition: var(--transition);
            font-weight: 700; font-size: 0.82rem; font-family: inherit;
        }
        .image-upload-btn label:hover { background: var(--accent-dark); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .image-preview { color: var(--text-muted); font-size: 0.72rem; line-height: 1.65; }

        /* ── FORMS ── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.125rem; margin-bottom: 1.125rem;
        }
        .form-label { font-weight: 600; margin-bottom: 0.35rem; color: var(--text-primary); font-size: 0.8rem; display: block; }
        .required { color: var(--danger); font-weight: 700; }
        .form-control, .form-select {
            padding: 0.575rem 0.875rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-family: inherit; font-size: 0.875rem;
            color: var(--text-primary); background: var(--surface-2);
            transition: var(--transition); width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); background: var(--surface);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.08); outline: none;
        }
        .form-control:disabled { opacity: 0.6; cursor: not-allowed; }
        .form-text { color: var(--text-muted); font-size: 0.72rem; margin-top: 0.25rem; }
        textarea.form-control { resize: vertical; min-height: 110px; }
        .form-control-sm { padding: 0.4rem 0.7rem; font-size: 0.78rem; }

        /* ── CATEGORIES GRID ── */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 0.75rem; margin-bottom: 1.125rem;
        }
        .category-checkbox { position: relative; }
        .category-checkbox input[type="checkbox"] { position: absolute; opacity: 0; }
        .category-label {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border); border-radius: var(--radius-md);
            cursor: pointer; transition: var(--transition);
            background: var(--surface-2); font-weight: 500; font-size: 0.82rem;
            color: var(--text-primary); height: 100%;
        }
        .category-label:hover { border-color: var(--accent); background: var(--accent-light); transform: translateY(-2px); }
        .category-checkbox input[type="checkbox"]:checked + .category-label {
            border-color: var(--accent); background: var(--accent-light); color: var(--accent); font-weight: 700;
        }
        .category-label i { font-size: 1.1rem; width: 22px; text-align: center; color: var(--accent); flex-shrink: 0; }
        .category-label div { font-weight: 600; line-height: 1.3; }

        .premium-badge {
            background: var(--warning-light); color: var(--warning);
            padding: 0.12rem 0.45rem; border-radius: 100px;
            font-size: 0.58rem; font-weight: 800; letter-spacing: 0.4px;
            border: 1px solid #fde68a; display: inline-block; margin-top: 2px;
        }

        /* ── SAVE BUTTON ── */
        .btn-save {
            background: var(--success); color: white; border: none;
            padding: 0.6rem 1.5rem; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.875rem; font-family: inherit;
            display: inline-flex; align-items: center; gap: 0.45rem;
            transition: var(--transition); cursor: pointer;
        }
        .btn-save:hover { background: #15803d; transform: translateY(-1px); box-shadow: var(--shadow-sm); }

        /* Bootstrap btn shims */
        .btn { font-family: inherit; font-weight: 600; transition: var(--transition); border-radius: var(--radius-sm); font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn-success { background: var(--success); color: white; border-color: var(--success); }
        .btn-success:hover { background: #15803d; color: white; transform: translateY(-1px); box-shadow: var(--shadow-xs); }
        .btn-sm { font-size: 0.75rem; padding: 0.35rem 0.75rem; }
        .btn-outline-primary { color: var(--accent); border: 1px solid var(--accent); background: transparent; }
        .btn-outline-primary:hover { background: var(--accent-light); color: var(--accent); }
        .btn-outline-danger { color: var(--danger); border: 1px solid #fecaca; background: transparent; }
        .btn-outline-danger:hover { background: var(--danger-light); }
        .btn-link { color: var(--accent); background: none; border: none; cursor: pointer; text-decoration: underline; padding: 0; }

        /* ── PORTFOLIO ── */
        .portfolio-item { transition: var(--transition); }
        .portfolio-item:hover { transform: translateY(-4px); }
        .portfolio-item .card { border: 1px solid var(--border) !important; padding: 0; overflow: hidden; }
        .portfolio-item:hover .card { border-color: var(--accent) !important; box-shadow: var(--shadow-sm); }
        .portfolio-image { height: 180px; object-fit: cover; width: 100%; display: block; }

        .nav-tabs { border-bottom: 1px solid var(--border) !important; margin-bottom: 1rem; }
        .nav-tabs .nav-link { color: var(--text-secondary); border: none; border-bottom: 2px solid transparent; font-weight: 600; font-size: 0.82rem; transition: var(--transition); padding: 0.65rem 1rem; }
        .nav-tabs .nav-link:hover { color: var(--accent); border-bottom-color: var(--accent); }
        .nav-tabs .nav-link.active { color: var(--accent); border-bottom-color: var(--accent); background: transparent; }

        .portfolio-upload-slot {
            border: 2px dashed var(--border); border-radius: var(--radius-md);
            padding: 1.25rem; margin-bottom: 1rem;
            background: var(--surface-2); transition: var(--transition); position: relative;
        }
        .portfolio-upload-slot:hover { border-color: var(--accent); background: var(--accent-light); }
        .portfolio-upload-slot .remove-btn {
            position: absolute; top: -10px; right: -10px;
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--danger); color: white; border: none;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; cursor: pointer; z-index: 10;
            transition: var(--transition); box-shadow: var(--shadow-sm);
        }
        .portfolio-upload-slot .remove-btn:hover { background: #b91c1c; transform: scale(1.1); }

        .portfolio-image-preview { max-height: 140px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 0.875rem; display: none; width: 100%; }
        .portfolio-video-preview { max-height: 140px; max-width: 100%; border-radius: var(--radius-sm); margin-bottom: 0.875rem; display: none; background: #000; }

        .portfolio-upload-btn, .video-upload-btn { position: relative; overflow: hidden; display: block; width: 100%; }
        .portfolio-upload-btn label, .video-upload-btn label {
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 1.25rem 1rem;
            background: var(--surface-2); border: 2px dashed var(--border);
            border-radius: var(--radius-sm); color: var(--text-secondary);
            cursor: pointer; transition: var(--transition); font-weight: 600; font-size: 0.82rem;
        }
        .portfolio-upload-btn label:hover, .video-upload-btn label:hover {
            background: var(--accent); color: white; border-color: var(--accent); border-style: solid;
        }
        .portfolio-upload-btn input[type="file"], .video-upload-btn input[type="file"] {
            position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; top: 0; left: 0;
        }

        /* Portfolio video box */
        .portfolio-video-box {
            background: var(--success-light); border: 1px solid #bbf7d0;
            border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem;
        }

        /* ── SOCIAL MEDIA ── */
        .social-input-row {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.875rem 0; border-bottom: 1px solid var(--border-subtle);
        }
        .social-input-row:last-child { border-bottom: none; }
        .social-icon-badge {
            width: 36px; height: 36px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .social-input-row .form-control { flex: 1; }

        .social-preview {
            display: flex; flex-wrap: wrap; gap: 0.5rem;
            margin-top: 1rem; padding: 1rem;
            background: var(--surface-2); border-radius: var(--radius-md);
            border: 1px solid var(--border);
        }
        .social-link-preview {
            display: inline-flex; align-items: center; gap: 0.45rem;
            padding: 0.35rem 0.875rem;
            background: var(--surface); border-radius: 100px;
            border: 1px solid var(--border);
            text-decoration: none; color: var(--text-primary);
            transition: var(--transition); font-weight: 700; font-size: 0.75rem;
        }
        .social-link-preview:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); text-decoration: none; }
        .social-link-preview i { font-size: 0.85rem; width: 14px; text-align: center; }

        .social-link-preview.facebook  { border-color: #1877f2; color: #1877f2; }
        .social-link-preview.facebook:hover  { background: #f0f7ff; }
        .social-link-preview.twitter   { border-color: #1da1f2; color: #1da1f2; }
        .social-link-preview.twitter:hover   { background: #f0f7ff; }
        .social-link-preview.instagram { border-color: #e4405f; color: #e4405f; }
        .social-link-preview.instagram:hover { background: #fff0f5; }
        .social-link-preview.linkedin  { border-color: #0a66c2; color: #0a66c2; }
        .social-link-preview.linkedin:hover  { background: #f0f7ff; }
        .social-link-preview.youtube   { border-color: #ff0000; color: #ff0000; }
        .social-link-preview.youtube:hover   { background: #fff0f0; }
        .social-link-preview.whatsapp  { border-color: #25d366; color: #25d366; }
        .social-link-preview.whatsapp:hover  { background: #f0fff4; }
        .social-link-preview.website   { border-color: var(--accent); color: var(--accent); }
        .social-link-preview.website:hover   { background: var(--accent-light); }
        .social-link-preview.tiktok    { border-color: #111; color: #111; }
        .social-link-preview.tiktok:hover    { background: #f5f5f5; }

        .social-validation {
            font-size: 0.68rem; padding: 0.18rem 0.5rem;
            border-radius: 100px; margin-top: 0.25rem; font-weight: 700; display: inline-block;
        }
        .social-validation.valid   { background: var(--success-light); color: var(--success); border: 1px solid #bbf7d0; }
        .social-validation.invalid { background: var(--danger-light);  color: var(--danger);  border: 1px solid #fecaca; }

        /* ── MOBILE ── */
        .mobile-menu-toggle {
            display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1100;
            background: var(--accent); color: white; border: none;
            border-radius: var(--radius-sm); width: 42px; height: 42px;
            align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-md);
        }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; backdrop-filter: blur(2px); }
        .overlay.active { display: block; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.12); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-menu-toggle { display: flex !important; }
            .form-grid { grid-template-columns: 1fr; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); }
            .profile-image-section { flex-direction: column; text-align: center; }
            .view-tabs { width: 100%; overflow-x: auto; }
            .profile-header { flex-direction: column; text-align: center; }
        }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
    </style>
    <!-- Shared User Behavior Tracking -->
    <?php include __DIR__ . '/../includes/user_behavior_tracking.php'; ?>
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
                <strong>Maintenance Mode Active</strong>
                <p class="mb-0 mt-2">The platform is currently under maintenance. Some features may be limited.</p>
            </div>
        <?php endif; ?>

        <!-- AI Features Notice -->
        <?php if ($enable_ai_features): ?>
            <div class="ai-features">
                <i class="fas fa-robot fa-lg"></i>
                <div>
                    <strong>AI Assistant Active</strong>
                    <p>Our AI will help polish your bio and profile text automatically.</p>
                </div>
                <span class="ai-badge ms-auto">AI</span>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="profile-header">
            <div class="profile-header-avatar" id="headerAvatar">
                <?php if (!empty($provider['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($provider['profile_image']); ?>" alt="Profile" onerror="this.style.display='none';">
                <?php else: ?>
                    <?php echo strtoupper(substr($provider['full_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-header-text">
                <h1><?php echo htmlspecialchars($provider['full_name']); ?></h1>
                <div class="profile-header-meta">
                    <?php if (!empty($provider['profession'])): ?>
                        <span class="profile-meta-chip"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($provider['profession']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($provider['location'])): ?>
                        <span class="profile-meta-chip"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($provider['location']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($provider['experience_years'])): ?>
                        <span class="profile-meta-chip"><i class="fas fa-star"></i> <?php echo $provider['experience_years']; ?> yrs exp</span>
                    <?php endif; ?>
                </div>
                <p><?php echo __("subtitle", [], "profile"); ?></p>
            </div>
        </div>

        <!-- Section Navigation Tabs -->
        <div class="view-tabs">
            <a href="?section=basic" class="view-tab <?php echo $section === 'basic' ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i> <?php echo __("tabs.basic", [], "profile"); ?>
            </a>
            <a href="?section=services" class="view-tab <?php echo $section === 'services' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> <?php echo __("tabs.services", [], "profile"); ?>
            </a>
            <a href="?section=portfolio" class="view-tab <?php echo $section === 'portfolio' ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> <?php echo __("tabs.portfolio", [], "profile"); ?>
            </a>
            <a href="?section=social" class="view-tab <?php echo $section === 'social' ? 'active' : ''; ?>">
                <i class="fas fa-share-alt"></i> <?php echo __("tabs.social", [], "profile"); ?>
            </a>
            <a href="?section=requirements" class="view-tab <?php echo $section === 'requirements' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i> <?php echo __("tabs.requirements", [], "profile"); ?>
            </a>
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

        <!-- Profile Completion Checklist -->
        <?php if ($section === 'requirements'): ?>
        <div class="profile-section-content active">
            <div class="row mb-4">
                <div class="col-lg-8">
                    <?php echo $requirements->renderChecklist(true); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <!-- Profile Image (Part of Basic Section) -->
            <?php if ($section === 'basic'): ?>
            <div class="profile-section-content active">
                <!-- Profile Image -->
                <div class="card">
                    <h2><i class="fas fa-image"></i> <?php echo __("profile_picture.title", [], "profile"); ?></h2>
                    <div class="profile-image-section">
                        <div class="current-image" id="imagePreview">
                            <?php if (!empty($provider['profile_image'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($provider['profile_image']); ?>" alt="Profile" onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo strtoupper(substr($provider['full_name'], 0, 1)); ?>';">
                            <?php else: ?>
                                <?php echo strtoupper(substr($provider['full_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="image-upload-btn">
                            <input type="file" name="profile_image" id="profileImage" accept="image/*" onchange="previewImage(this)">
                            <label for="profileImage">
                                <i class="fas fa-camera"></i> <?php echo __('profile_picture.change_photo', [], 'profile'); ?>
                            </label>
                        </div>
                        <p class="image-preview" id="fileName">
                            <?php echo __("profile_picture.file_size_info", [], "profile"); ?>: <?php echo getMaxFileSize(); ?>MB<br>
                            <?php echo __("profile_picture.allowed_formats", [], "profile"); ?>: JPG, PNG, GIF
                        </p>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="card">
                    <h2><i class="fas fa-info-circle"></i> <?php echo __("basic_info.title", [], "profile"); ?></h2>
                    <div class="form-grid">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __("basic_info.full_name", [], "profile"); ?> <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($provider['full_name']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __("basic_info.email", [], "profile"); ?> <span class="required">*</span></label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($provider['email']); ?>" disabled>
                            <div class="form-text"><?php echo __("alerts.email_cannot_change", [], "profile"); ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __("basic_info.phone", [], "profile"); ?> <span class="required">*</span></label>
                            <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($provider['phone']); ?>" placeholder="<?php echo __("basic_info.phone_placeholder", [], "profile"); ?>">
                            <div class="form-text"><?php echo __("basic_info.phone_help", [], "profile"); ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __("basic_info.profession", [], "profile"); ?> <span class="required">*</span></label>
                            <select name="profession" id="professionSelect" class="form-select form-control" required>
                                <option value="">-- Select Your Category --</option>
                                <?php foreach (getProfessionCategories() as $prof): ?>
                                    <option value="<?php echo htmlspecialchars($prof); ?>" <?php echo $provider['profession'] === $prof ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prof); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Choose your primary service category</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __("basic_info.professional_title", [], "profile") ?? "Professional Title"; ?> <span class="required">*</span></label>
                            <select name="professional_title" id="titleSelect" class="form-select form-control" required>
                                <option value="">-- Select First --</option>
                                <?php if (!empty($provider['profession'])): ?>
                                    <?php foreach (getProfessionalTitles($provider['profession']) as $title): ?>
                                        <option value="<?php echo htmlspecialchars($title); ?>" <?php echo $provider['profession_title'] === $title ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">Select your specific professional title</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __("basic_info.experience_years", [], "profile"); ?></label>
                            <input type="number" name="experience_years" class="form-control" min="0" max="50" value="<?php echo $provider['experience_years'] ?? 0; ?>">
                            <div class="form-text"><?php echo __("basic_info.experience_help", [], "profile"); ?></div>
                        </div>

                        <!-- Hourly rate removed: prices are defined per-service -->
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __("basic_info.bio", [], "profile"); ?></label>
                        <textarea name="bio" class="form-control" rows="5" placeholder="<?php echo __("basic_info.bio_placeholder", [], "profile"); ?>"><?php echo htmlspecialchars($provider['bio'] ?? ''); ?></textarea>
                        <div class="form-text"><?php echo __("basic_info.bio_help", [], "profile"); ?></div>
                    </div>
                    
                    <!-- Section Save Button -->
                    <div class="mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-success" onclick="saveSection('basic_info', this)">
                            <i class="fas fa-save me-2"></i> <?php echo __("actions.save", [], "profile"); ?>
                        </button>
                        <small class="text-muted d-block mt-2">Saving in real-time</small>
                    </div>
                </div>

                <!-- Location Information -->
            <div class="card">
                <h2><i class="fas fa-map-marker-alt"></i> <?php echo __("location_info.title", [], "profile"); ?></h2>
                <div class="form-grid">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __("location_info.location", [], "profile"); ?> <span class="required">*</span></label>
                        <input type="text" name="location" class="form-control" required value="<?php echo htmlspecialchars($provider['location'] ?? ''); ?>" placeholder="<?php echo __("location_info.location_placeholder", [], "profile"); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __("location_info.district", [], "profile"); ?></label>
                        <select name="district" class="form-select">
                            <option value=""><?php echo __("location_info.district_placeholder", [], "profile"); ?></option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo htmlspecialchars($district['name']); ?>" 
                                    <?php echo ($provider['district'] ?? '') == $district['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php echo __("location_info.district_help", [], "profile"); ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __("location_info.sector", [], "profile"); ?></label>
                        <input type="text" name="sector" class="form-control" value="<?php echo htmlspecialchars($provider['sector'] ?? ''); ?>" placeholder="<?php echo __("location_info.sector_placeholder", [], "profile"); ?>">
                        <div class="form-text"><?php echo __("location_info.sector_help", [], "profile"); ?></div>
                    </div>
                </div>
                
                <!-- Section Save Button -->
                <div class="mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-success" onclick="saveSection('location_info', this)">
                        <i class="fas fa-save me-2"></i> <?php echo __("actions.save", [], "profile"); ?>
                    </button>
                    <small class="text-muted d-block mt-2">Saving in real-time</small>
                </div>
            </div>

                <!-- Services Section -->
            <?php endif; ?>

            <!-- Services Section -->
            <?php if ($section === 'services'): ?>
            <div class="profile-section-content active">
            <!-- Services/Categories -->
            <div class="card">
                <h2><i class="fas fa-briefcase"></i> <?php echo __("services_section.title", [], "profile"); ?> <span class="required">*</span></h2>
                <p class="text-muted mb-3"><?php echo __("services_section.description", [], "profile"); ?></p>
                
                <div class="categories-grid">
                    <?php foreach ($all_categories as $category): ?>
                        <?php
                            $catIsPremium = !empty($category['is_premium']) ? 1 : 0;
                            $provIsPremium = !empty($provider['is_premium']) ? 1 : 0;
                            $checked = in_array($category['id'], $provider_category_ids) ? 'checked' : '';
                            $disabled = ($catIsPremium && !$provIsPremium) ? 'disabled' : '';
                            $labelMuted = ($catIsPremium && !$provIsPremium) ? 'text-muted' : '';
                        ?>
                        <div class="category-checkbox">
                            <input type="checkbox"
                                   name="categories[]"
                                   value="<?php echo $category['id']; ?>"
                                   id="cat_<?php echo $category['id']; ?>"
                                   <?php echo $checked; ?>
                                   <?php echo $disabled; ?>>
                            <label for="cat_<?php echo $category['id']; ?>" class="category-label <?php echo $labelMuted; ?>">
                                <i class="fas <?php echo $category['icon'] ?? 'fa-cog'; ?>"></i>
                                <div>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                    <?php if ($catIsPremium): ?>
                                        <span class="premium-badge">PREMIUM</span>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php if ($catIsPremium): ?>
                                <div class="form-text small mt-1">
                                    <?php if (!$provIsPremium): ?>
                                        <i class="fas fa-crown text-warning"></i> <?php echo __('services_section.premium_feature', [], 'profile'); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($all_categories)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo __('services_section.no_categories', [], 'profile'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Section Save Button -->
                <div class="card-save-footer">
                    <button type="button" class="btn-save" onclick="saveSection('services', this)">
                        <i class="fas fa-save"></i> <?php echo __('services_section.save_button', [], 'profile'); ?>
                    </button>
                    <span class="card-save-note"><?php echo __('services_section.save_note', [], 'profile'); ?></span>
                </div>
            </div>
            </div>
            <?php endif; ?>

            <!-- Portfolio Section -->
            <?php if ($section === 'portfolio'): ?>
            <div class="profile-section-content active">
            <!-- Portfolio/Work Samples -->
            <div class="card">
                <h2><i class="fas fa-images"></i> <?php echo __("portfolio_section.title", [], "profile"); ?></h2>
                <p class="text-muted mb-3"><?php echo __("portfolio_section.description", [], "profile"); ?></p>
                
                <?php if (!$portfolio_enabled): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo __("portfolio_section.description", [], "profile"); ?>
                    </div>
                <?php else: ?>
                    <!-- Current Portfolio Video -->
                    <?php if ($has_portfolio_video): ?>
                        <div class="portfolio-video-box">
                            <h5 class="mb-3"><i class="fas fa-video text-success me-2"></i> <?php echo __("portfolio_section.videos.title", [], "profile"); ?></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <video width="100%" controls style="border-radius:8px;background:#000;max-height:200px;display:block;">
                                        <source src="../uploads/portfolio/<?php echo htmlspecialchars($portfolio_video['video_path']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label"><strong><?php echo __('portfolio_section.videos.video_title', [], 'profile'); ?>:</strong></label>
                                        <p><?php echo htmlspecialchars($portfolio_video['title'] ?? __('portfolio_section.videos.title', [], 'profile')); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label"><strong><?php echo __('portfolio_section.videos.video_description', [], 'profile'); ?>:</strong></label>
                                        <p><?php echo htmlspecialchars($portfolio_video['description'] ?? __('portfolio_section.images.no_images', [], 'profile')); ?></p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('<?php echo __('portfolio_section.videos.delete_confirm', [], 'profile'); ?>?')) { document.getElementById('deleteVideoCheckbox').checked = true; document.querySelector('form').submit(); }">
                                        <i class="fas fa-trash me-1"></i> <?php echo __('portfolio_section.videos.replace_remove', [], 'profile'); ?>
                                    </button>
                                    <input type="hidden" id="deleteVideoCheckbox" name="delete_portfolio_video" value="0">
                                </div>
                            </div>
                            <hr>
                            <p class="text-muted small mb-0"><?php echo __('portfolio_section.videos.uploaded_on', [], 'profile'); ?> <?php echo date('M d, Y', strtotime($portfolio_video['uploaded_at'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Existing Portfolio Images -->
                    <div id="existingPortfolio" class="mb-4">
                        <h4 class="h6 mb-3"><?php echo __("portfolio_section.images.title", [], "profile"); ?> (<?php echo $portfolio_count; ?>/<?php echo $max_portfolio_images; ?>)</h4>
                        
                        <?php if (empty($portfolio_images)): ?>
                            <div class="alert alert-secondary">
                                <i class="fas fa-image me-2"></i>
                                <?php echo __('portfolio_section.images.no_images', [], 'profile'); ?>
                            </div>
                        <?php else: ?>
                            <div class="row" id="portfolioImagesContainer">
                                <?php foreach ($portfolio_images as $index => $image): ?>
                                    <div class="col-md-4 mb-3 portfolio-item" data-id="<?php echo $image['id']; ?>">
                                        <div class="card h-100">
                                            <img src="../uploads/portfolio/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                 class="card-img-top portfolio-image" 
                                                 alt="<?php echo htmlspecialchars($image['title'] ?: 'Portfolio Image'); ?>"
                                                 class="portfolio-image"
                                                 onerror="this.src='https://via.placeholder.com/300x200?text=Image+Not+Found'">
                                            <div class="card-body" style="padding:1rem;">
                                                <input type="hidden" name="existing_portfolio_ids[]" value="<?php echo $image['id']; ?>">
                                                
                                                <div class="mb-2">
                                                    <label class="form-label small"><?php echo __('portfolio_section.images.image_title', [], 'profile'); ?></label>
                                                    <input type="text" 
                                                           name="existing_portfolio_titles[]" 
                                                           class="form-control form-control-sm" 
                                                           value="<?php echo htmlspecialchars($image['title'] ?? ''); ?>" 
                                                           placeholder="<?php echo __('portfolio_section.images.placeholder_title', [], 'profile'); ?>">
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <label class="form-label small"><?php echo __('portfolio_section.images.image_description', [], 'profile'); ?></label>
                                                    <textarea name="existing_portfolio_descriptions[]" 
                                                              class="form-control form-control-sm" 
                                                              rows="2" 
                                                              placeholder="<?php echo __('portfolio_section.images.placeholder_description', [], 'profile'); ?>"><?php echo htmlspecialchars($image['description'] ?? ''); ?></textarea>
                                                </div>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100 delete-portfolio-btn" onclick="removePortfolioImage(this, <?php echo $image['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i> <?php echo __('portfolio_section.images.delete_confirm', [], 'profile'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- New Portfolio Upload Section -->
                    <div id="newPortfolioSection" class="mt-4">
                        <div class="nav nav-tabs mb-3" role="tablist">
                            <button class="nav-link active" id="imageTab" data-bs-toggle="tab" data-bs-target="#imageUploadTab" type="button" role="tab">
                                <i class="fas fa-image me-2"></i> <?php echo __('portfolio_section.images_tab', [], 'profile'); ?>
                            </button>
                            <button class="nav-link" id="videoTab" data-bs-toggle="tab" data-bs-target="#videoUploadTab" type="button" role="tab">
                                <i class="fas fa-video me-2"></i> <?php echo __('portfolio_section.videos.tab_name', [], 'profile'); ?>
                            </button>
                        </div>
                        
                        <div class="tab-content">
                            <!-- Image Upload Tab -->
                            <div class="tab-pane fade show active" id="imageUploadTab" role="tabpanel">
                                <h4 class="h6 mb-3"><?php echo __('portfolio_section.add_new_images', [], 'profile'); ?></h4>
                                <div id="portfolioImageUploadContainer">
                                    <!-- New image upload slots will be added here -->
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPortfolioUploadField('image')" 
                                            <?php echo ($portfolio_count >= $max_portfolio_images) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus me-1"></i> <?php echo __('portfolio_section.images.add_another', [], 'profile'); ?>
                                    </button>
                                    <small class="text-muted">
                                        <span id="currentImageUploadCount">0</span>/<?php echo $max_portfolio_images - $portfolio_count; ?> <?php echo __('portfolio_section.images.remaining', [], 'profile'); ?>
                                    </small>
                                </div>
                                
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo str_replace(':max', $max_portfolio_images, str_replace(':size', getMaxFileSize(), __('portfolio_section.images.max_info', [], 'profile'))); ?>
                                </div>
                            </div>
                            
                            <!-- Video Upload Tab -->
                            <div class="tab-pane fade" id="videoUploadTab" role="tabpanel">
                                <h4 class="h6 mb-3"><?php echo __('portfolio_section.videos.upload_label', [], 'profile'); ?></h4>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <?php echo __('portfolio_section.videos.info', [], 'profile'); ?>
                                </div>
                                <div id="portfolioVideoUploadContainer">
                                    <!-- Video upload slot will be added here -->
                                </div>
                                
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo str_replace(':size', getMaxFileSize() * 2, __('portfolio_section.videos.max_info', [], 'profile')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="deleted_portfolio[]" id="deletedPortfolioIds" value="">
                    
                    <!-- Section Save Button -->
                    <div class="mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-success" onclick="savePortfolioSection(this)">
                            <i class="fas fa-save me-2"></i> <?php echo __('portfolio_section.save_button', [], 'profile'); ?>
                        </button>
                        <small class="text-muted d-block mt-2"><?php echo __('portfolio_section.save_note', [], 'profile'); ?></small>
                    </div>
                <?php endif; ?>
            </div>
            </div>
            <?php endif; ?>

            <!-- Social Media Section -->
            <?php if ($section === 'social'): ?>
            <div class="profile-section-content active">
            <!-- Social Media Links -->
            <div class="card">
                <h2><i class="fas fa-share-alt"></i> <?php echo __("social_media.title", [], "profile"); ?></h2>
                <p class="text-muted mb-3"><?php echo __("social_media.description", [], "profile"); ?></p>
                
                <div class="form-grid">
                    <!-- Website -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-globe text-primary me-2"></i><?php echo __("social_media.website", [], "profile"); ?>
                        </label>
                        <input type="url" name="website" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['website']); ?>" 
                               placeholder="<?php echo __("social_media.website_placeholder", [], "profile"); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __("social_media.website_placeholder", [], "profile"); ?></div>
                    </div>

                    <!-- Facebook -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-facebook text-primary me-2"></i><?php echo __("social_media.facebook", [], "profile"); ?>
                        </label>
                        <input type="url" name="facebook" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['facebook']); ?>" 
                               placeholder="<?php echo __("social_media.facebook_placeholder", [], "profile"); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.facebook', [], 'profile'); ?></div>
                    </div>

                    <!-- Instagram -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-instagram text-danger me-2"></i><?php echo __("social_media.instagram", [], "profile"); ?>
                        </label>
                        <input type="url" name="instagram" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['instagram']); ?>" 
                               placeholder="<?php echo __("social_media.instagram_placeholder", [], "profile"); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.instagram', [], 'profile'); ?></div>
                    </div>

                    <!-- Twitter -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-twitter text-info me-2"></i><?php echo __("social_media.twitter", [], "profile"); ?>
                        </label>
                        <input type="url" name="twitter" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['twitter']); ?>" 
                               placeholder="<?php echo __("social_media.twitter_placeholder", [], "profile"); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.twitter', [], 'profile'); ?></div>
                    </div>

                    <!-- LinkedIn -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-linkedin text-primary me-2"></i><?php echo __("social_media.linkedin", [], "profile"); ?>
                        </label>
                        <input type="url" name="linkedin" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['linkedin']); ?>" 
                               placeholder="<?php echo __("social_media.linkedin_placeholder", [], "profile"); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.linkedin', [], 'profile'); ?></div>
                    </div>

                    <!-- YouTube -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-youtube text-danger me-2"></i><?php echo __("social_media.youtube", [], "profile"); ?>
                        </label>
                        <input type="url" name="youtube" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['youtube']); ?>" 
                               placeholder="<?php echo __("social_media.youtube_placeholder", [], "profile"); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.youtube', [], 'profile'); ?></div>
                    </div>

                    <!-- WhatsApp -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-whatsapp text-success me-2"></i><?php echo __('social_media.whatsapp', [], 'profile'); ?>
                        </label>
                        <input type="text" name="whatsapp" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['whatsapp']); ?>" 
                               placeholder="<?php echo __('social_media.whatsapp_placeholder', [], 'profile'); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.whatsapp_help', [], 'profile'); ?></div>
                    </div>

                    <!-- TikTok -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fab fa-tiktok me-2"></i><?php echo __('social_media.tiktok', [], 'profile'); ?>
                        </label>
                        <input type="url" name="tiktok" class="form-control" 
                               value="<?php echo htmlspecialchars($social_platforms['tiktok']); ?>" 
                               placeholder="<?php echo __('social_media.tiktok_placeholder', [], 'profile'); ?>"
                               onblur="validateSocialURL(this)">
                        <div class="form-text"><?php echo __('social_media.tiktok', [], 'profile'); ?></div>
                    </div>
                </div>

                <!-- Other Social Media -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('social_media.other_social', [], 'profile'); ?></label>
                            <input type="url" name="other_social" class="form-control" 
                                   value="<?php echo htmlspecialchars($social_platforms['other_social']); ?>" 
                                   placeholder="<?php echo __('social_media.other_social_placeholder', [], 'profile'); ?>"
                                   onblur="validateSocialURL(this)">
                            <div class="form-text"><?php echo __('social_media.other_social_help', [], 'profile'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('social_media.other_social_label', [], 'profile'); ?></label>
                            <input type="text" name="other_social_label" class="form-control" 
                                   value="<?php echo htmlspecialchars($social_platforms['other_social_label']); ?>" 
                                   placeholder="<?php echo __('social_media.platform_name_placeholder', [], 'profile'); ?>">
                            <div class="form-text"><?php echo __('social_media.platform_name_help', [], 'profile'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Social Links Preview -->
                <div class="mt-4">
                    <h5 class="mb-2"><?php echo __('social_media.preview_heading', [], 'profile'); ?></h5>
                    <div id="socialPreview" class="social-preview">
                        <!-- Social links will appear here as you type -->
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo __('social_media.info_message', [], 'profile'); ?>
                </div>
                
                <!-- Section Save Button -->
                <div class="mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-success" onclick="saveSection('social_media', this)">
                        <i class="fas fa-save me-2"></i> <?php echo __('social_media.save_button', [], 'profile'); ?>
                    </button>
                    <small class="text-muted d-block mt-2"><?php echo __('social_media.save_note', [], 'profile'); ?></small>
                </div>
            </div>
            </div>
            <?php endif; ?>

            <!-- Save Button -->
            <div class="text-center mt-4">
                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fas fa-save me-2"></i> Save All Changes
                </button>
                <a href="dashboard.php" class="btn btn-secondary ms-2">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('providerSidebar');
        const overlay = document.getElementById('overlay');
        
        if (mobileToggle && sidebar && overlay) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
            });
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
        }
        
        // Preview image before upload
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const fileName = document.getElementById('fileName');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                // Check file size
                const maxSize = <?php echo getMaxFileSize() * 1024 * 1024; ?>;
                if (file.size > maxSize) {
                    alert('File size exceeds maximum allowed size of <?php echo getMaxFileSize(); ?>MB');
                    input.value = '';
                    return;
                }
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                }
                
                reader.readAsDataURL(file);
                fileName.innerHTML = '<?php echo __('validation.selected', [], 'profile'); ?>: ' + file.name + '<br><?php echo __('validation.maximum_file_size', [], 'profile'); ?>: <?php echo getMaxFileSize(); ?>MB<br><?php echo __('validation.allowed_formats', [], 'profile'); ?>: JPG, PNG, GIF';
            }
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
        document.querySelector('form').addEventListener('submit', function(e) {
            const categories = document.querySelectorAll('input[name="categories[]"]:checked');
            if (categories.length === 0) {
                e.preventDefault();
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-warning alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i> Please select at least one service category
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.main-content').insertBefore(alertDiv, document.querySelector('form'));
                
                // Scroll to categories section
                document.querySelector('.categories-grid').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                
                return false;
            }
            
            // Validate phone number
            const phoneInput = document.querySelector('input[name="phone"]');
            const phoneRegex = /^\+?[\d\s\-\(\)]{10,}$/;
            if (!phoneRegex.test(phoneInput.value.trim())) {
                e.preventDefault();
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-warning alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i> Please enter a valid phone number
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.main-content').insertBefore(alertDiv, document.querySelector('form'));
                phoneInput.focus();
                return false;
            }
            
            // Validate social media URLs
            let hasInvalidSocialURL = false;
            document.querySelectorAll('input[name="website"], input[name="facebook"], input[name="twitter"], input[name="instagram"], input[name="linkedin"], input[name="youtube"], input[name="whatsapp"], input[name="tiktok"], input[name="other_social"]').forEach(input => {
                if (!validateSocialURL(input)) {
                    hasInvalidSocialURL = true;
                }
            });
            
            if (hasInvalidSocialURL) {
                e.preventDefault();
                alert('<?php echo __('validation.correct_invalid_urls', [], 'profile'); ?>');
                return false;
            }
        });

        // Portfolio functionality
        let portfolioImageUploadCount = 0;
        let portfolioVideoUploadCount = 0;
        const maxPortfolioItems = <?php echo $max_portfolio_images; ?>;
        const currentPortfolioCount = <?php echo $portfolio_count; ?>;
        const deletedPortfolioIds = [];
        
        // Supported video formats and sizes
        const videoFormats = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'ogv'];
        const maxVideoSize = <?php echo getMaxFileSize() * 2 * 1024 * 1024; ?>; // 2x the image size
        
        // Initialize portfolio upload count displays
        if (document.getElementById('currentImageUploadCount')) {
            document.getElementById('currentImageUploadCount').textContent = portfolioImageUploadCount;
        }
        if (document.getElementById('currentVideoUploadCount')) {
            document.getElementById('currentVideoUploadCount').textContent = portfolioVideoUploadCount;
        }
        
        function addPortfolioUploadField(type = 'image') {
            const totalUploads = portfolioImageUploadCount + portfolioVideoUploadCount;
            if (totalUploads + currentPortfolioCount >= maxPortfolioItems) {
                alert('<?php $msg = __('validation.max_portfolio_items', [], 'profile'); echo str_replace(':max', '" + maxPortfolioItems + "', $msg); ?>');
                return;
            }
            
            const container = type === 'image' ? 
                document.getElementById('portfolioImageUploadContainer') : 
                document.getElementById('portfolioVideoUploadContainer');
            const index = type === 'image' ? portfolioImageUploadCount : portfolioVideoUploadCount;
            
            const uploadSlot = document.createElement('div');
            uploadSlot.className = 'portfolio-upload-slot';
            
            if (type === 'image') {
                uploadSlot.innerHTML = `
                    <button type="button" class="remove-btn" onclick="removePortfolioUploadSlot(this)">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="image-upload-btn">
                                <label>
                                    <i class="fas fa-cloud-upload-alt me-2"></i> Choose Image
                                    <input type="file" name="portfolio_images[]" accept="image/jpeg,image/png,image/gif,image/webp" 
                                           onchange="previewPortfolioImage(this, ${index})" required>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <img id="portfolioImagePreview${index}" class="portfolio-image-preview" alt="Preview">
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-md-6 mb-2">
                            <input type="text" name="portfolio_titles[]" class="form-control form-control-sm" 
                                   placeholder="Image title (optional)" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <textarea name="portfolio_descriptions[]" class="form-control form-control-sm" 
                                      placeholder="Brief description (optional)" maxlength="500" rows="2"></textarea>
                        </div>
                    </div>
                `;
            } else {
                uploadSlot.innerHTML = `
                    <button type="button" class="remove-btn" onclick="removePortfolioUploadSlot(this)">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="video-upload-btn">
                                <label>
                                    <i class="fas fa-cloud-upload-alt me-2"></i> Choose Video
                                    <input type="file" name="portfolio_videos[]" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska" 
                                           onchange="previewPortfolioVideo(this, ${index})" required>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <video id="portfolioVideoPreview${index}" class="portfolio-video-preview" controls></video>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-md-6 mb-2">
                            <input type="text" name="portfolio_video_titles[]" class="form-control form-control-sm" 
                                   placeholder="Video title (optional)" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <textarea name="portfolio_video_descriptions[]" class="form-control form-control-sm" 
                                      placeholder="Brief description (optional)" maxlength="500" rows="2"></textarea>
                        </div>
                    </div>
                `;
            }
            
            container.appendChild(uploadSlot);
            
            if (type === 'image') {
                portfolioImageUploadCount++;
                document.getElementById('currentImageUploadCount').textContent = portfolioImageUploadCount;
            } else {
                portfolioVideoUploadCount++;
                document.getElementById('currentVideoUploadCount').textContent = portfolioVideoUploadCount;
            }
            
            // Disable add buttons if max reached
            const newTotal = portfolioImageUploadCount + portfolioVideoUploadCount;
            if (newTotal + currentPortfolioCount >= maxPortfolioItems) {
                document.querySelectorAll('#newPortfolioSection button[onclick*="addPortfolioUploadField"]').forEach(btn => {
                    btn.disabled = true;
                });
            }
        }
        
        function removePortfolioUploadSlot(button) {
            const slot = button.closest('.portfolio-upload-slot');
            const isImage = slot.querySelector('input[name="portfolio_images[]"]') !== null;
            
            slot.remove();
            
            if (isImage) {
                portfolioImageUploadCount--;
                document.getElementById('currentImageUploadCount').textContent = portfolioImageUploadCount;
            } else {
                portfolioVideoUploadCount--;
                document.getElementById('currentVideoUploadCount').textContent = portfolioVideoUploadCount;
            }
            
            // Re-enable add buttons if under limit
            const totalUploads = portfolioImageUploadCount + portfolioVideoUploadCount;
            if (totalUploads + currentPortfolioCount < maxPortfolioItems) {
                document.querySelectorAll('#newPortfolioSection button[onclick*="addPortfolioUploadField"]').forEach(btn => {
                    btn.disabled = false;
                });
            }
        }
        
        function previewPortfolioImage(input, index) {
            const preview = document.getElementById(`portfolioImagePreview${index}`);
            const file = input.files[0];
            
            if (file) {
                // Check file size
                const maxSize = <?php echo getMaxFileSize() * 1024 * 1024; ?>;
                if (file.size > maxSize) {
                    alert('<?php $msg = __('validation.file_size_exceeds', [], 'profile'); echo str_replace(':size', getMaxFileSize(), $msg); ?>');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function previewPortfolioVideo(input, index) {
            const preview = document.getElementById(`portfolioVideoPreview${index}`);
            const file = input.files[0];
            
            if (file) {
                // Check file extension
                const fileName = file.name.toLowerCase();
                const fileExt = fileName.split('.').pop();
                
                if (!videoFormats.includes(fileExt)) {
                    alert('<?php $msg = __('validation.invalid_video_format', [], 'profile'); echo str_replace(':formats', '" + videoFormats.join(", ") + "', $msg); ?>');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Check file size
                if (file.size > maxVideoSize) {
                    alert('<?php $msg = __('validation.file_size_exceeds', [], 'profile'); echo str_replace(':size', '" + Math.round(maxVideoSize / 1024 / 1024) + "', $msg); ?>');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function removePortfolioImage(button, imageId) {
            if (confirm('<?php echo __('validation.confirm_delete_portfolio', [], 'profile'); ?>')) {
                const portfolioItem = button.closest('.portfolio-item');
                portfolioItem.style.display = 'none';
                
                // Add to deleted IDs array
                deletedPortfolioIds.push(imageId);
                document.getElementById('deletedPortfolioIds').value = deletedPortfolioIds.join(',');
                
                // Update remaining count
                const totalUploads = portfolioImageUploadCount + portfolioVideoUploadCount;
                const remainingSlots = maxPortfolioItems - (currentPortfolioCount - deletedPortfolioIds.length);
                if (totalUploads < remainingSlots) {
                    document.querySelectorAll('#newPortfolioSection button[onclick*="addPortfolioUploadField"]').forEach(btn => {
                        btn.disabled = false;
                    });
                }
            }
        }
        
        // Social media URL validation
        function validateSocialURL(input) {
            const value = input.value.trim();
            const platform = input.name;
            
            if (!value) {
                // Remove any existing validation message
                const validationDiv = input.parentNode.querySelector('.social-validation');
                if (validationDiv) {
                    validationDiv.remove();
                }
                return true; // Empty is okay
            }
            
            let isValid = false;
            let pattern = '';
            
            switch(platform) {
                case 'website':
                case 'facebook':
                case 'twitter':
                case 'instagram':
                case 'linkedin':
                case 'youtube':
                case 'tiktok':
                case 'other_social':
                    pattern = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/;
                    isValid = pattern.test(value);
                    break;
                case 'whatsapp':
                    // Accept both phone numbers and wa.me links
                    pattern = /^(https?:\/\/)?(wa\.me\/|whatsapp\.com\/)?(\+\d{10,15}|\d{10,15})$/;
                    isValid = pattern.test(value);
                    break;
                default:
                    isValid = true;
            }
            
            // Remove existing validation
            const existingValidation = input.parentNode.querySelector('.social-validation');
            if (existingValidation) {
                existingValidation.remove();
            }
            
            // Add new validation message
            const newValidation = document.createElement('div');
            newValidation.className = `social-validation ${isValid ? 'valid' : 'invalid'}`;
            newValidation.innerHTML = isValid ? 
                '<i class="fas fa-check-circle me-1"></i> Valid URL' : 
                '<i class="fas fa-exclamation-circle me-1"></i> Please enter a valid URL';
            
            input.parentNode.appendChild(newValidation);
            
            updateSocialPreview();
            return isValid;
        }
        
        // Preview social links
        function updateSocialPreview() {
            const previewContainer = document.getElementById('socialPreview');
            if (!previewContainer) return;
            
            let previewHTML = '';
            const platforms = {
                'website': { icon: 'fas fa-globe', label: 'Website', class: 'website' },
                'facebook': { icon: 'fab fa-facebook', label: 'Facebook', class: 'facebook' },
                'instagram': { icon: 'fab fa-instagram', label: 'Instagram', class: 'instagram' },
                'twitter': { icon: 'fab fa-twitter', label: 'Twitter', class: 'twitter' },
                'linkedin': { icon: 'fab fa-linkedin', label: 'LinkedIn', class: 'linkedin' },
                'youtube': { icon: 'fab fa-youtube', label: 'YouTube', class: 'youtube' },
                'whatsapp': { icon: 'fab fa-whatsapp', label: 'WhatsApp', class: 'whatsapp' },
                'tiktok': { icon: 'fab fa-tiktok', label: 'TikTok', class: 'tiktok' }
            };
            
            for (const [platform, info] of Object.entries(platforms)) {
                const input = document.querySelector(`input[name="${platform}"]`);
                if (input && input.value.trim()) {
                    const url = input.value.trim();
                    previewHTML += `
                        <a href="${url}" target="_blank" class="social-link-preview ${info.class}">
                            <i class="${info.icon}"></i>
                            <span>${info.label}</span>
                        </a>
                    `;
                }
            }
            
            // Check for custom social media
            const otherSocial = document.querySelector('input[name="other_social"]');
            const otherLabel = document.querySelector('input[name="other_social_label"]');
            if (otherSocial && otherSocial.value.trim() && otherLabel && otherLabel.value.trim()) {
                previewHTML += `
                    <a href="${otherSocial.value.trim()}" target="_blank" class="social-link-preview">
                        <i class="fas fa-link"></i>
                        <span>${otherLabel.value.trim()}</span>
                    </a>
                `;
            }
            
            if (previewHTML) {
                previewContainer.innerHTML = previewHTML;
                previewContainer.style.display = 'flex';
            } else {
                previewContainer.innerHTML = '<div class="text-muted">No social links added yet</div>';
            }
        }
        
        // Add event listeners for social media inputs
        document.querySelectorAll('input[name="website"], input[name="facebook"], input[name="instagram"], input[name="twitter"], input[name="linkedin"], input[name="youtube"], input[name="whatsapp"], input[name="tiktok"], input[name="other_social"], input[name="other_social_label"]').forEach(input => {
            input.addEventListener('input', updateSocialPreview);
        });
        
        // ===== REAL-TIME SECTION SAVING =====
        async function saveSection(section, button) {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            formData.append('ajax_section', section);
            
            // Disable button and show loading state
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
            button.disabled = true;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                const card = button.closest('.card');
                
                // Remove old alert if exists
                const oldAlert = card.querySelector('.section-alert');
                if (oldAlert) oldAlert.remove();
                
                // Create alert
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${data.success ? 'alert-success' : 'alert-danger'} alert-dismissible fade show section-alert`;
                alertDiv.innerHTML = `
                    <i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                    <strong>${data.success ? 'Success!' : 'Error!'}</strong>
                    <p class="mb-0 mt-1">${data.message}</p>
                    ${data.errors.length > 0 ? '<ul class="mb-0 mt-2"><li>' + data.errors.join('</li><li>') + '</li></ul>' : ''}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Insert alert at top of card
                card.insertBefore(alertDiv, card.firstChild);
                
                // Auto-remove alert after 5 seconds
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }, 5000);
                
            } catch (error) {
                console.error('Save error:', error);
                const card = button.closest('.card');
                const oldAlert = card.querySelector('.section-alert');
                if (oldAlert) oldAlert.remove();
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show section-alert';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Error!</strong>
                    <p class="mb-0 mt-1">Network error. Please check your connection and try again.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                card.insertBefore(alertDiv, card.firstChild);
                
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }, 5000);
            } finally {
                // Restore button state
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }
        
        // Portfolio section save handler
        async function savePortfolioSection(button) {
            const card = button.closest('.card');
            const form = document.querySelector('form');
            const formData = new FormData(form);
            formData.append('ajax_section', 'portfolio');
            
            // Show loading state
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
            button.disabled = true;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                // Remove old alert if exists
                const oldAlert = card.querySelector('.section-alert');
                if (oldAlert) oldAlert.remove();
                
                // Create alert based on response
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${data.success ? 'alert-success' : 'alert-danger'} alert-dismissible fade show section-alert`;
                alertDiv.innerHTML = `
                    <i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                    <strong>${data.success ? 'Success!' : 'Error!'}</strong>
                    <p class="mb-0 mt-1">${data.message}</p>
                    ${data.errors.length > 0 ? '<ul class="mb-0 mt-2"><li>' + data.errors.join('</li><li>') + '</li></ul>' : ''}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                card.insertBefore(alertDiv, card.firstChild);
                
                // Auto-remove alert after 5 seconds
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }, 5000);
                
            } catch (error) {
                console.error('Portfolio save error:', error);
                
                // Remove old alert if exists
                const oldAlert = card.querySelector('.section-alert');
                if (oldAlert) oldAlert.remove();
                
                // Show error alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show section-alert';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Error!</strong>
                    <p class="mb-0 mt-1">Network error. Please check your connection and try again.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                card.insertBefore(alertDiv, card.firstChild);
                
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }, 5000);
            } finally {
                // Restore button state
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }
        
        
        // Initialize professional titles mapping from PHP
        const professionTitlesData = <?php echo json_encode(array_reduce(getProfessionCategories(), function($carry, $prof) {
            $carry[$prof] = getProfessionalTitles($prof);
            return $carry;
        }, [])); ?>;

        // Handle profession dropdown change
        const professionSelect = document.getElementById('professionSelect');
        const titleSelect = document.getElementById('titleSelect');

        if (professionSelect && titleSelect) {
            professionSelect.addEventListener('change', function() {
                const selectedProfession = this.value;
                titleSelect.innerHTML = '<option value="">-- Select First --</option>';
                
                if (selectedProfession && professionTitlesData[selectedProfession]) {
                    professionTitlesData[selectedProfession].forEach(title => {
                        const option = document.createElement('option');
                        option.value = title;
                        option.textContent = title;
                        titleSelect.appendChild(option);
                    });
                    titleSelect.disabled = false;
                } else {
                    titleSelect.disabled = true;
                }
                
                // Reset title selection
                titleSelect.value = '';
            });
        }
        
        // Initialize preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSocialPreview();
            <?php if ($portfolio_count < $max_portfolio_images && $portfolio_enabled): ?>
            addPortfolioUploadField('image');
            <?php endif; ?>
            // Check if video upload slot exists and add if needed
            const videoContainer = document.getElementById('portfolioVideoUploadContainer');
            if (videoContainer && videoContainer.children.length === 0) {
                addPortfolioUploadField('video');
            }
        });
    </script>
</body>
</html>