<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$emailParam = isset($_GET['email']) ? trim($_GET['email']) : '';

$message = '';
$success = false;
$showOtpForm = false;

try {
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // OTP submission flow
        $email = sanitize($_POST['email'] ?? '');
        $otp = sanitize($_POST['otp'] ?? '');

        if (empty($email) || empty($otp)) {
            $message = 'Please provide both email and the verification code.';
            $showOtpForm = true;
        } else {
            $stmt = $db->prepare("SELECT id, is_verified, verification_token, token_expiry, full_name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $message = 'Invalid email or verification code.';
                $showOtpForm = true;
            } elseif (!empty($user['is_verified']) && (int)$user['is_verified'] === 1) {
                $message = 'Your account is already verified. You can <a href="login.php">login</a>.';
            } else {
                // Validate OTP and expiry
                if ((string)$user['verification_token'] !== (string)$otp) {
                    $message = 'Invalid verification code.';
                    $showOtpForm = true;
                } elseif (!empty($user['token_expiry']) && strtotime($user['token_expiry']) !== false && strtotime($user['token_expiry']) < time()) {
                    $message = 'This verification code has expired. Please request a new code.';
                    $showOtpForm = true;
                } else {
                    // Mark user verified
                    $update = $db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = ?");
                    $update->execute([$user['id']]);

                    $success = true;
                    $message = 'Thank you, ' . htmlspecialchars($user['full_name'] ?? '') . ". Your account has been verified. You can now <a href=\"login.php\">login</a>.";
                }
            }
        }

    } elseif (!empty($token)) {
        // Legacy token/link flow - keep existing behavior
        $stmt = $db->prepare("SELECT id, is_verified, verification_token, token_expiry, full_name, email FROM users WHERE verification_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $message = 'This verification link is invalid or has already been used.';
        } else {
            if (!empty($user['is_verified']) && (int)$user['is_verified'] === 1) {
                $message = 'Your account is already verified. You can <a href="login.php">login</a>.';
            } else {
                if (array_key_exists('token_expiry', $user) && !empty($user['token_expiry'])) {
                    $expiry = strtotime($user['token_expiry']);
                    if ($expiry !== false && $expiry < time()) {
                        $message = 'This verification link has expired. If you did not receive a new link, please request a new verification email from your account page.';
                    }
                }

                if (empty($message)) {
                    $update = $db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = ?");
                    $update->execute([$user['id']]);

                    $success = true;
                    $message = 'Thank you, ' . htmlspecialchars($user['full_name'] ?? '') . ". Your account has been verified. You can now <a href=\"login.php\">login</a>.";
                }
            }
        }

    } elseif (!empty($emailParam)) {
        // Show OTP form pre-filled with email
        $showOtpForm = true;
        $prefillEmail = $emailParam;
    }

} catch (Exception $e) {
    // Log full exception details for debugging: message + stack + token/email + client IP
    $err = "Verification error: " . $e->getMessage() . " | Token: " . $token . " | Email: " . ($emailParam ?? '') . " | IP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN') . "\n" . $e->getTraceAsString();
    error_log($err);

    $debug = false;
    if (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true') {
        $debug = true;
    }
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $debug = true;
    }

    if ($debug) {
        $message = 'Internal error: ' . htmlspecialchars($e->getMessage());
    } else {
        $message = 'An internal error occurred while verifying your account. Please try again later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Verification - BII LocalFinder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="nav-brand">
                <i class="fas fa-map-marked-alt"></i>
                <span>BII LocalFinder</span>
            </a>
        </div>
    </nav>

    <div class="container" style="max-width: 700px; margin: 40px auto;">
        <div class="card">
            <div class="card-body" style="padding: 30px;">
                <h2>Account Verification</h2>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-top: 20px;">
                        <?php echo $message; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger" style="margin-top: 20px;">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <p style="margin-top: 20px;">
                    <a href="index.php" class="btn-secondary">Back to Home</a>
                    <?php if ($success): ?>
                        <a href="login.php" class="btn-primary" style="margin-left: 10px;">Login</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p style="text-align:center">&copy; <?php echo date('Y'); ?> BII LocalFinder</p>
        </div>
    </footer>
</body>
</html>