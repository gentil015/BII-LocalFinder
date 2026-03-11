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
    $names = $_POST['service_name'] ?? [];
    $prices = $_POST['service_price'] ?? [];

    $inserted = 0;
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO provider_services (provider_id, name, price, is_available, created_at) VALUES (?, ?, ?, 1, NOW())");
        foreach ($names as $i => $n) {
            $name = trim($n);
            $price = isset($prices[$i]) ? floatval($prices[$i]) : 0;
            if ($name !== '' && $price >= 0) {
                $stmt->execute([$provider_id, $name, $price]);
                $inserted++;
            }
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = 'Failed to save services';
    }

    if (empty($errors)) {
        header('Location: step4-schedule.php');
        exit;
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Setup — Services</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <style>.container{max-width:900px}</style>
    <style>.step-pill{display:inline-block;padding:.25rem .6rem;border-radius:.5rem;margin-right:.4rem}.step-active{background:#0d6efd;color:#fff}</style>
</head>
<body class="p-4">
<div class="container">
    <div class="setup-steps mb-2">
        <span class="step-pill">1. Profile</span>
        <span class="step-pill">2. ID</span>
        <span class="step-pill step-active">3. Services</span>
        <span class="step-pill">4. Schedule</span>
    </div>
    <div class="progress mb-3" style="height:12px"><div class="progress-bar bg-primary" role="progressbar" style="width:75%"></div></div>

    <h2>Step 3 — Add Services</h2>
    <div id="alerts">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
    <?php endif; ?>
    </div>

    <form id="servicesForm" method="post">
        <div id="servicesList">
            <div class="row mb-2 service-row">
                <div class="col-md-6"><input name="service_name[]" class="form-control" placeholder="Service name"></div>
                <div class="col-md-4"><input name="service_price[]" class="form-control" placeholder="Price" type="number" step="0.01" min="0"></div>
                <div class="col-md-2"><button type="button" class="btn btn-danger btn-remove">Remove</button></div>
            </div>
        </div>
        <div class="mb-3">
            <button type="button" id="addService" class="btn btn-secondary">Add another service</button>
        </div>
        <div class="d-flex gap-2">
            <a href="step2-id.php" class="btn btn-light">Back</a>
            <button class="btn btn-primary">Save services and continue</button>
        </div>
    </form>
</div>
<script>
document.getElementById('addService').addEventListener('click', function(){
    var container = document.getElementById('servicesList');
    var row = document.createElement('div');
    row.className = 'row mb-2 service-row';
    row.innerHTML = '<div class="col-md-6"><input name="service_name[]" class="form-control" placeholder="Service name"></div>'+
                    '<div class="col-md-4"><input name="service_price[]" class="form-control" placeholder="Price" type="number" step="0.01" min="0"></div>'+
                    '<div class="col-md-2"><button type="button" class="btn btn-danger btn-remove">Remove</button></div>';
    container.appendChild(row);
});
document.addEventListener('click', function(e){
    if (e.target && e.target.classList.contains('btn-remove')) {
        e.target.closest('.service-row').remove();
    }
});

document.getElementById('servicesForm').addEventListener('submit', function(e){
    var names = Array.from(document.querySelectorAll('input[name="service_name[]"]')).map(i=>i.value.trim());
    var prices = Array.from(document.querySelectorAll('input[name="service_price[]"]')).map(i=>i.value.trim());
    var valid=false; var errors=[];
    for(var i=0;i<names.length;i++){ if(names[i]!=='' && (prices[i]!=='' && parseFloat(prices[i])>=0)){ valid=true; break; } }
    if(!valid){ errors.push('Please add at least one service with a valid price'); }
    if(errors.length){ e.preventDefault(); document.getElementById('alerts').innerHTML = '<div class="alert alert-danger">'+errors.join('<br>')+'</div>'; window.scrollTo(0,0); return false; }
    return true;
});
</script>
</body>
</html>
