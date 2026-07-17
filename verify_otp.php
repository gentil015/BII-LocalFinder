<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $otp = sanitize($_POST['otp']);

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, otp_code, otp_expires_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['otp_code'] === $otp) {
            if (strtotime($user['otp_expires_at']) > time()) {
                // Mark verified
                $update = $db->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
                $update->execute([$user['id']]);
                $message = "<p style='color:green;'>Account verified successfully! You can now <a href='login.php'>login</a>.</p>";
            } else {
                $message = "<p style='color:red;'>OTP expired. Please request a new one.</p>";
            }
        } else {
            $message = "<p style='color:red;'>Invalid OTP code.</p>";
        }
    } else {
        $message = "<p style='color:red;'>Email not found.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify OTP - BII LocalFinder</title>
<link rel="stylesheet" href="assets/css/register.css">
</head>
<body>
<div class="form-container">
    <h2>Verify Your Account</h2>
    <p>Enter the 6-digit OTP sent to your email.</p>
    <?php echo $message; ?>
    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="Enter your email">
        </div>
        <div class="form-group">
            <label>OTP Code</label>
            <input type="text" name="otp" required maxlength="6" placeholder="Enter 6-digit code">
        </div>
        <button type="submit" class="btn-primary" style="width:100%;">Verify</button>
    </form>
</div>
</body>
</html>
