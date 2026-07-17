<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$provider_id = $_SESSION['provider_id'] ?? null;

// Ensure provider record exists
if (!$provider_id) {
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $p = $stmt->fetch();
    if ($p) $provider_id = $p['id'];
}

if (!$provider_id) {
    // Redirect back to profile step to create provider
    header('Location: step1-profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['national_id']['name']) && $_FILES['national_id']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['national_id']['tmp_name'];
        $file_name = $_FILES['national_id']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];
        if (!in_array($file_ext, $allowed)) {
            $errors[] = 'Invalid file type';
        } else {
            $upload_dir = __DIR__ . '/../../uploads/verification_docs';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $newname = 'nid_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . DIRECTORY_SEPARATOR . $newname)) {
                // insert verification_documents
                $stmt = $db->prepare("INSERT INTO verification_documents (provider_id, document_type, file_path, status, uploaded_at) VALUES (?, 'national_id', ?, 'pending', NOW())");
                $stmt->execute([$provider_id, $newname]);
                $_SESSION['provider_id'] = $provider_id;
                header('Location: step3-services.php');
                exit;
            } else {
                $errors[] = 'Failed to save file';
            }
        }
    } else {
        $errors[] = 'Please choose a file to upload';
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Setup — Upload ID</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <style>.container{max-width:700px}</style>
</head>
<body class="p-4">
<div class="container">
    <div class="setup-steps mb-2">
        <span class="step-pill">1. Profile</span>
        <span class="step-pill step-active">2. ID</span>
        <span class="step-pill">3. Services</span>
        <span class="step-pill">4. Schedule</span>
    </div>

    <div class="progress mb-3" style="height:12px"><div class="progress-bar bg-primary" role="progressbar" style="width:50%"></div></div>

    <h2>Step 2 — Upload National ID / Passport</h2>
    <div id="alerts">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
    <?php endif; ?>
    </div>

    <form id="idForm" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">National ID / Passport</label>
            <input id="national_id" type="file" name="national_id" accept="image/*,.pdf" class="form-control">
        </div>
        <div class="d-flex gap-2">
            <a href="step1-profile.php" class="btn btn-light">Back</a>
            <button class="btn btn-primary">Upload and continue</button>
        </div>
    </form>

    <script>
    document.getElementById('idForm').addEventListener('submit', function(e){
        const input = document.getElementById('national_id');
        const allowed = ['jpg','jpeg','png','pdf'];
        const errors = [];
        if (!input.files || input.files.length === 0) {
            errors.push('Please choose a file to upload');
        } else {
            const name = input.files[0].name;
            const ext = name.split('.').pop().toLowerCase();
            if (allowed.indexOf(ext) === -1) errors.push('Invalid file type');
        }
        if (errors.length) {
            e.preventDefault();
            document.getElementById('alerts').innerHTML = '<div class="alert alert-danger">' + errors.join('<br>') + '</div>';
            window.scrollTo(0,0);
            return false;
        }
        return true;
    });
    </script>

</div>
</body>
</html>
