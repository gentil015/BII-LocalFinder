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

if (!$provider_id) {
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $p = $stmt->fetch();
    if ($p) $provider_id = $p['id'];
}

if (!$provider_id) {
    header('Location: step1-profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = $_POST['working_days'] ?? [];
    $start = sanitize($_POST['working_hours_start'] ?? '');
    $end = sanitize($_POST['working_hours_end'] ?? '');

    if (empty($days) || empty($start) || empty($end)) {
        $errors[] = 'Please select working days and set start/end times';
    } else {
        $days_csv = implode(',', $days);
        try {
            $stmt = $db->prepare("UPDATE service_providers SET working_days = ?, working_hours_start = ?, working_hours_end = ? WHERE id = ?");
            $stmt->execute([$days_csv, $start, $end, $provider_id]);
            header('Location: complete.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to save schedule';
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Setup — Schedule</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <style>.container{max-width:800px}</style>
</head>
<body class="p-4">
<div class="container">
    <div class="setup-steps mb-2">
        <span class="step-pill">1. Profile</span>
        <span class="step-pill">2. ID</span>
        <span class="step-pill">3. Services</span>
        <span class="step-pill step-active">4. Schedule</span>
    </div>
    <div class="progress mb-3" style="height:12px"><div class="progress-bar bg-primary" role="progressbar" style="width:100%"></div></div>

    <h2>Step 4 — Availability & Hours</h2>
    <div id="alerts">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
    <?php endif; ?>
    </div>

    <form id="scheduleForm" method="post">
        <div class="mb-3">
            <label class="form-label">Working days</label>
            <div class="d-flex gap-2 flex-wrap">
                <?php $daysList = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                foreach ($daysList as $i => $d): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="working_days[]" value="<?php echo $i+1; ?>" id="day<?php echo $i; ?>">
                        <label class="form-check-label" for="day<?php echo $i; ?>"><?php echo $d; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Start time</label>
                <input id="startTime" type="time" name="working_hours_start" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">End time</label>
                <input id="endTime" type="time" name="working_hours_end" class="form-control">
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="step3-services.php" class="btn btn-light">Back</a>
            <button class="btn btn-primary">Save schedule and finish</button>
        </div>
    </form>

    <script>
    document.getElementById('scheduleForm').addEventListener('submit', function(e){
        var days = document.querySelectorAll('input[name="working_days[]"]:checked');
        var start = document.getElementById('startTime').value;
        var end = document.getElementById('endTime').value;
        var errors = [];
        if(days.length === 0) errors.push('Please select at least one working day');
        if(!start || !end) errors.push('Please set start and end times');
        if(start && end && start >= end) errors.push('Start time must be before end time');
        if(errors.length){ e.preventDefault(); document.getElementById('alerts').innerHTML = '<div class="alert alert-danger">'+errors.join('<br>')+'</div>'; window.scrollTo(0,0); return false; }
        return true;
    });
    </script>

</div>
</body>
</html>
