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

$errors = [];
$success = '';

// Handle POST: save profile, bio, experience, profile image and create/update service_providers row
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profession = sanitize($_POST['profession'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    $experience_years = isset($_POST['experience_years']) ? intval($_POST['experience_years']) : 0;

    // Handle profile image
    if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (!in_array($file_ext, $allowed)) {
            $errors[] = 'Invalid profile image type';
        } else {
            $upload_dir = __DIR__ . '/../../uploads/profiles';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $newname = 'profile_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . DIRECTORY_SEPARATOR . $newname)) {
                // update users.profile_image
                $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$newname, $user_id]);
            } else {
                $errors[] = 'Failed to save profile image';
            }
        }
    }

    if (empty($errors)) {
        try {
            // Create or update provider profile
            $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $provider = $stmt->fetch();

            if ($provider) {
                $stmt = $db->prepare("UPDATE service_providers SET profession = ?, bio = ?, location = ?, experience_years = ? WHERE user_id = ?");
                $stmt->execute([$profession, $bio, $location, $experience_years, $user_id]);
                $provider_id = $provider['id'];
            } else {
                $stmt = $db->prepare("INSERT INTO service_providers (user_id, profession, bio, location, experience_years, is_active) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$user_id, $profession, $bio, $location, $experience_years]);
                $provider_id = $db->lastInsertId();
            }

            // store provider id in session for convenience
            $_SESSION['provider_id'] = $provider_id;

            header('Location: step2-id.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to save profile. Please try again.';
        }
    }
}

// Simple form
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Setup — Profile</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <style>
        .setup-steps {margin-bottom:1rem}
        .step-pill {display:inline-block;padding:.25rem .6rem;border-radius:.5rem;margin-right:.4rem}
        .step-active {background:#0d6efd;color:#fff}
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="setup-steps">
        <span class="step-pill step-active">1. Profile</span>
        <span class="step-pill">2. ID</span>
        <span class="step-pill">3. Services</span>
        <span class="step-pill">4. Schedule</span>
    </div>

    <div class="progress mb-3" style="height:12px">
      <div class="progress-bar bg-primary" role="progressbar" style="width:25%"></div>
    </div>

    <h2>Step 1 — Profile</h2>
    <div id="alerts">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
        <?php endif; ?>
    </div>

    <?php
    // Check if user already has a profile image
    $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userRow = $stmt->fetch();
    $hasProfileImage = !empty($userRow['profile_image']);
    ?>

    <form id="profileForm" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Profession</label>
            <input id="profession" type="text" name="profession" class="form-control" value="<?php echo htmlspecialchars($profession ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input id="location" type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($location ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Short bio</label>
            <textarea id="bio" name="bio" class="form-control"><?php echo htmlspecialchars($bio ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Years of experience</label>
            <input id="experience" type="number" name="experience_years" min="0" class="form-control" value="<?php echo htmlspecialchars($experience_years ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Profile image <?php echo $hasProfileImage ? '(already uploaded)' : '(required)'; ?></label>
            <input id="profile_image" type="file" name="profile_image" accept="image/*" class="form-control">
        </div>

        <div class="d-flex gap-2">
            <a href="/" class="btn btn-light">Cancel</a>
            <button id="saveContinue" class="btn btn-primary">Save and continue</button>
        </div>
    </form>

    <script>
    document.getElementById('profileForm').addEventListener('submit', function(e){
        const errors = [];
        const prof = document.getElementById('profession').value.trim();
        const loc = document.getElementById('location').value.trim();
        const bio = document.getElementById('bio').value.trim();
        const exp = parseInt(document.getElementById('experience').value || '0', 10);
        const hasExisting = <?php echo $hasProfileImage ? 'true' : 'false'; ?>;
        const fileInput = document.getElementById('profile_image');

        if (!prof) errors.push('Profession is required');
        if (!loc) errors.push('Location is required');
        if (!bio || bio.length < 10) errors.push('Short bio (min 10 chars) is required');
        if (!exp || exp <= 0) errors.push('Please enter years of experience');
        if (!hasExisting && fileInput.files.length === 0) errors.push('Profile image is required');

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
