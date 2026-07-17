<?php
session_start();
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];
$user_type = $_SESSION['user_type'];

// Redirect to appropriate folder based on user type
$base_path = $user_type === 'provider' ? 'provider/' : ($user_type === 'client' ? 'client/' : 'admin/');

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required";
    } elseif (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed, $_SESSION['user_id']])) {
            $success = "Password changed successfully!";
        } else {
            $errors[] = "Failed to change password";
        }
    }
}

// Handle email notifications settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    // For future implementation with notifications table
    $success = "Notification preferences updated!";
}

// Handle account deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_account'])) {
    $stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
    if ($stmt->execute([$_SESSION['user_id']])) {
        session_destroy();
        redirect('index.php');
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $password_confirm = $_POST['password_confirm'];
    
    if (password_verify($password_confirm, $user['password'])) {
        try {
            $db->beginTransaction();
            
            // Delete related data based on user type
            if ($user_type === 'provider') {
                $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $provider = $stmt->fetch();
                
                if ($provider) {
                    // Delete provider's reviews, bookings, etc.
                    $db->prepare("DELETE FROM reviews WHERE provider_id = ?")->execute([$provider['id']]);
                    $db->prepare("DELETE FROM bookings WHERE provider_id = ?")->execute([$provider['id']]);
                    $db->prepare("DELETE FROM provider_services WHERE provider_id = ?")->execute([$provider['id']]);
                    $db->prepare("DELETE FROM service_providers WHERE id = ?")->execute([$provider['id']]);
                }
            } elseif ($user_type === 'client') {
                // Delete client's reviews, bookings, etc.
                $db->prepare("DELETE FROM reviews WHERE client_id = ?")->execute([$_SESSION['user_id']]);
                $db->prepare("DELETE FROM bookings WHERE client_id = ?")->execute([$_SESSION['user_id']]);
            }
            
            // Delete user account
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$_SESSION['user_id']]);
            
            $db->commit();
            
            session_destroy();
            redirect('index.php?deleted=1');
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to delete account. Please try again.";
            error_log($e->getMessage());
        }
    } else {
        $errors[] = "Incorrect password. Account not deleted.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BII LocalFinder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; margin: 0; }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header { padding: 30px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li { margin: 5px 15px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            gap: 15px;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(102, 126, 234, 0.2); color: white; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 30px; }
        
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .settings-grid {
            display: grid;
            gap: 25px;
        }
        
        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .settings-card h2 {
            margin-bottom: 10px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-card p {
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 30px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary);
        }
        
        input:checked + .slider:before {
            transform: translateX(30px);
        }
        
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .danger-zone {
            border: 2px solid #fecaca;
            border-radius: 10px;
            padding: 20px;
            background: #fef2f2;
        }
        
        .danger-zone h3 {
            color: #991b1b;
            margin-bottom: 10px;
        }
        
        .btn-danger-outline {
            padding: 12px 24px;
            background: white;
            color: var(--danger);
            border: 2px solid var(--danger);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-danger-outline:hover {
            background: var(--danger);
            color: white;
        }
        
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>BII LocalFinder</h2>
                <p><?php echo ucfirst($user_type); ?> Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo $base_path; ?>dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <?php if ($user_type === 'provider'): ?>
                    <li><a href="provider/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="provider/bookings.php"><i class="fas fa-calendar-alt"></i> Bookings</a></li>
                    <li><a href="provider/reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <?php else: ?>
                    <li><a href="client/my-bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a></li>
                    <li><a href="providers.php"><i class="fas fa-search"></i> Find Providers</a></li>
                    <li><a href="client/my-reviews.php"><i class="fas fa-star"></i> My Reviews</a></li>
                    <li><a href="client/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <?php endif; ?>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-cog"></i> Settings</h1>
                <p>Manage your account settings and preferences</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Account Information -->
                <div class="settings-card">
                    <h2><i class="fas fa-user-circle"></i> Account Information</h2>
                    <p>Your basic account details</p>
                    
                    <div style="background: #f9fafb; padding: 20px; border-radius: 10px;">
                        <div style="margin-bottom: 15px;">
                            <strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Account Type:</strong> <span style="color: var(--primary); font-weight: 600;"><?php echo ucfirst($user_type); ?></span>
                        </div>
                        <div>
                            <strong>Member Since:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <a href="<?php echo $user_type === 'provider' ? 'provider/profile.php' : 'client/profile.php'; ?>" class="btn-secondary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="settings-card">
                    <h2><i class="fas fa-lock"></i> Change Password</h2>
                    <p>Update your password to keep your account secure</p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password <span style="color: var(--danger);">*</span></label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password <span style="color: var(--danger);">*</span></label>
                            <input type="password" name="new_password" required minlength="6">
                            <small style="color: #64748b;">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password <span style="color: var(--danger);">*</span></label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>

                <!-- Notification Preferences -->
                <div class="settings-card">
                    <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
                    <p>Choose what notifications you want to receive</p>
                    
                    <form method="POST">
                        <div class="notification-item">
                            <div>
                                <strong>Email Notifications</strong>
                                <div style="color: #64748b; font-size: 0.9rem;">Receive updates via email</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div>
                                <strong>Booking Updates</strong>
                                <div style="color: #64748b; font-size: 0.9rem;">Get notified about booking changes</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="booking_notifications" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div>
                                <strong>Review Notifications</strong>
                                <div style="color: #64748b; font-size: 0.9rem;">Get notified when you receive reviews</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="review_notifications" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div>
                                <strong>Marketing Emails</strong>
                                <div style="color: #64748b; font-size: 0.9rem;">Receive promotional offers and tips</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="marketing_notifications">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" name="update_notifications" class="btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Privacy & Security -->
                <div class="settings-card">
                    <h2><i class="fas fa-shield-alt"></i> Privacy & Security</h2>
                    <p>Manage your privacy and security settings</p>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px;">Two-Factor Authentication</h4>
                        <p style="color: #64748b; margin-bottom: 15px;">
                            Add an extra layer of security to your account
                        </p>
                        <button class="btn-secondary" disabled>
                            <i class="fas fa-mobile-alt"></i> Enable 2FA (Coming Soon)
                        </button>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 10px;">Active Sessions</h4>
                        <p style="color: #64748b; margin-bottom: 15px;">
                            You're currently logged in from 1 device
                        </p>
                        <button class="btn-secondary" disabled>
                            <i class="fas fa-desktop"></i> Manage Sessions (Coming Soon)
                        </button>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="settings-card">
                    <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                    <p>Irreversible actions for your account</p>
                    
                    <div class="danger-zone" style="margin-bottom: 20px;">
                        <h3><i class="fas fa-pause-circle"></i> Deactivate Account</h3>
                        <p style="color: #64748b; margin: 10px 0;">
                            Temporarily disable your account. You can reactivate it anytime.
                        </p>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account?')">
                            <button type="submit" name="deactivate_account" class="btn-danger-outline">
                                Deactivate Account
                            </button>
                        </form>
                    </div>
                    
                    <div class="danger-zone">
                        <h3><i class="fas fa-trash-alt"></i> Delete Account</h3>
                        <p style="color: #64748b; margin: 10px 0;">
                            Permanently delete your account and all associated data. This action cannot be undone.
                        </p>
                        <form method="POST" onsubmit="return confirm('⚠️ WARNING: This will permanently delete your account and all data. Type your password to confirm.')">
                            <div class="form-group">
                                <label>Enter your password to confirm</label>
                                <input type="password" name="password_confirm" required>
                            </div>
                            <button type="submit" name="delete_account" class="btn-danger-outline">
                                <i class="fas fa-trash"></i> Delete Account Permanently
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    </script>
</body>
</html>