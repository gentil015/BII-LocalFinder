<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$provider_id = $_SESSION['provider_id'] ?? null;

// Provide a simple completion screen and next steps
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Setup — Complete</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <style>.container{max-width:800px}</style>
    <style>.step-pill{display:inline-block;padding:.25rem .6rem;border-radius:.5rem;margin-right:.4rem}.step-active{background:#0d6efd;color:#fff}</style>
</head>
<body class="p-4">
<div class="container">
    <div class="setup-steps mb-2">
        <span class="step-pill">1. Profile</span>
        <span class="step-pill">2. ID</span>
        <span class="step-pill">3. Services</span>
        <span class="step-pill step-active">4. Schedule</span>
    </div>
    <div class="progress mb-3" style="height:12px"><div class="progress-bar bg-success" role="progressbar" style="width:100%"></div></div>

    <h2>Setup Complete</h2>
    <div class="alert alert-success">Your provider profile setup is complete and your documents have been submitted for review. Your account is pending admin approval. We will notify you by email once approved.</div>
    <p><a href="/login.php" class="btn btn-primary">Go to login</a> <a href="/" class="btn btn-secondary">Return to home</a></p>
</div>
</body>
</html>
