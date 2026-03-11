<?php
/**
 * Google Calendar OAuth Callback Handler
 * 
 * Handles the OAuth callback from Google and completes the authentication
 */

session_start();
require_once '../config/.env.loader.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/GoogleCalendarAuth.php';

// Verify user is logged in as provider
if (!isLoggedIn() || !isProvider()) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

$provider_id = null;
$error = null;
$success = false;

try {
    // Get provider ID
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $provider = $stmt->fetch();
    
    if (!$provider) {
        throw new Exception('Provider profile not found');
    }
    
    $provider_id = $provider['id'];
    
    // Verify CSRF state
    if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
        throw new Exception('Invalid state token. Possible CSRF attack.');
    }
    
    // Check for authorization code
    if (isset($_GET['error'])) {
        throw new Exception('Authorization denied: ' . $_GET['error']);
    }
    
    if (!isset($_GET['code'])) {
        throw new Exception('No authorization code received');
    }
    
    // Initialize auth handler and handle callback
    $auth = new GoogleCalendarAuth($provider_id);
    $auth->initializeTokenTable();
    
    $token_response = $auth->handleCallback($_GET['code'], $_GET['state']);
    
    // Try to fetch primary calendar ID
    if (isset($token_response['access_token'])) {
        $calendar_id = $this->getPrimaryCalendarId($token_response['access_token']);
        if ($calendar_id) {
            $auth->setCalendarId($calendar_id);
        }
    }
    
    $success = true;
    
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log('Google Calendar OAuth Error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Calendar Authentication</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 1rem;
        }
        
        .auth-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .auth-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f0f0f0;
            font-size: 2.5rem;
        }
        
        .auth-icon.success {
            background: #d4edda;
            color: #28a745;
        }
        
        .auth-icon.error {
            background: #f8d7da;
            color: #dc3545;
        }
        
        h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            color: white;
        }
        
        .btn-secondary {
            background: #e9ecef;
            color: #495057;
        }
        
        .btn-secondary:hover {
            background: #dee2e6;
            color: #495057;
        }
        
        .alert {
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: none;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <?php if ($success): ?>
            <div class="auth-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Authentication Successful!</h1>
            <p>Your Google Calendar has been successfully connected to your provider account. You can now sync your bookings with Google Calendar.</p>
            
            <div class="button-group">
                <a href="schedule.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt me-2"></i> Go to Schedule
                </a>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-cog me-2"></i> Settings
                </a>
            </div>
        <?php elseif ($error): ?>
            <div class="auth-icon error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Authentication Failed</h1>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
            <p>Please try again or contact support if the problem persists.</p>
            
            <div class="button-group">
                <a href="schedule.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Schedule
                </a>
                <a href="javascript:window.close();" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Close
                </a>
            </div>
        <?php else: ?>
            <div class="loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p class="mt-3">Processing your authentication...</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <?php if ($success): ?>
        <script>
            // Auto-redirect after 3 seconds
            setTimeout(() => {
                window.location.href = 'schedule.php?tab=integrations';
            }, 3000);
        </script>
    <?php endif; ?>
</body>
</html>
