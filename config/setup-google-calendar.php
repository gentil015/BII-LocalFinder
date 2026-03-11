<?php
/**
 * Google Calendar Integration Setup
 * 
 * This script initializes the database tables needed for Google Calendar integration
 * Run this once during installation or via admin panel
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

class GoogleCalendarSetup {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Initialize all required tables
     */
    public function initialize() {
        try {
            echo "Initializing Google Calendar Integration...\n";
            
            $this->createTokenTable();
            echo "✓ Token table created\n";
            
            $this->updateProviderSettingsTable();
            echo "✓ Provider settings table updated\n";
            
            echo "\n✓ Google Calendar integration initialized successfully!\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Error during initialization: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Create google_calendar_tokens table
     */
    private function createTokenTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS google_calendar_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                provider_id INT NOT NULL UNIQUE,
                access_token LONGTEXT NOT NULL,
                refresh_token LONGTEXT DEFAULT NULL,
                expires_in INT DEFAULT NULL,
                expires_at INT DEFAULT NULL,
                token_type VARCHAR(50) DEFAULT 'Bearer',
                scope LONGTEXT DEFAULT NULL,
                authenticated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                INDEX idx_provider (provider_id),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        
        $this->db->exec($sql);
    }
    
    /**
     * Ensure provider_settings table has necessary columns
     */
    private function updateProviderSettingsTable() {
        // Check if provider_settings table exists
        $result = $this->db->query("SHOW TABLES LIKE 'provider_settings'");
        
        if ($result->rowCount() == 0) {
            // Create the table if it doesn't exist
            $sql = "
                CREATE TABLE provider_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    provider_id INT NOT NULL,
                    setting_key VARCHAR(100) NOT NULL,
                    setting_value LONGTEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_setting (provider_id, setting_key),
                    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ";
            
            $this->db->exec($sql);
        }
    }
    
    /**
     * Get setup instructions
     */
    public static function getSetupInstructions() {
        return "
=== Google Calendar Integration Setup Guide ===

STEP 1: Configure Google OAuth Credentials
=========================================

1. Go to Google Cloud Console:
   https://console.cloud.google.com/

2. Create a new project or select existing one

3. Enable Google Calendar API:
   - Search for 'Google Calendar API'
   - Click 'Enable'

4. Create OAuth 2.0 Credentials:
   - Go to 'Credentials' page
   - Click 'Create Credentials' → 'OAuth 2.0 Client ID'
   - Choose 'Web application'
   - Add Authorized redirect URIs:
     * http://localhost/provider/google-calendar-callback.php
     * https://yourdomain.com/provider/google-calendar-callback.php

5. Copy Client ID and Client Secret


STEP 2: Set Environment Variables
================================

Option A: Using .env file (recommended)
Create a .env file in the project root:

GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/provider/google-calendar-callback.php


Option B: Server Environment Variables
Set via .htaccess, web server config, or hosting panel


Option C: Manual Configuration
Edit config/google-oauth.config.php and add credentials directly
(NOT recommended for production)


STEP 3: Database Setup
======================

Run this setup script or execute SQL migrations:

php config/setup-google-calendar.php

Or run manually in MySQL:

CREATE TABLE IF NOT EXISTS google_calendar_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL UNIQUE,
    access_token LONGTEXT NOT NULL,
    refresh_token LONGTEXT DEFAULT NULL,
    expires_in INT DEFAULT NULL,
    expires_at INT DEFAULT NULL,
    token_type VARCHAR(50) DEFAULT 'Bearer',
    scope LONGTEXT DEFAULT NULL,
    authenticated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE
);


STEP 4: Verify Installation
==========================

1. Log in as a provider
2. Go to Schedule → Integrations tab
3. Click 'Connect Google Calendar'
4. Follow OAuth authorization flow
5. Authorize the app when prompted


TROUBLESHOOTING
==============

Issue: 'Google Client ID not configured'
- Check that environment variables are set correctly
- Verify .env file exists in project root
- Restart web server after adding environment variables

Issue: 'Redirect URI mismatch'
- Ensure callback URL in Google Console matches exactly
- Check protocol (http vs https)
- Include full path: /provider/google-calendar-callback.php

Issue: 'Token refresh failed'
- Revoke and reconnect Google Calendar
- Check that refresh_token is being stored
- Verify database permissions

Issue: Events not syncing
- Check that provider has valid authentication
- Verify calendar ID is set correctly
- Check application logs for API errors


FEATURES
========

✓ OAuth 2.0 authorization flow
✓ Automatic token refresh
✓ Secure token storage in database
✓ Time off management
✓ Calendar sync
✓ Multiple calendar support
✓ Admin revocation controls


SECURITY NOTES
==============

1. Never commit credentials to version control
2. Always use HTTPS in production
3. Store client secret securely
4. Revoke access immediately if credentials compromised
5. Regularly audit connected calendars
6. Implement rate limiting on auth endpoints
7. Monitor token usage and access logs


API ENDPOINTS
=============

Authorization:
GET /provider/google-calendar-callback.php

Token Management:
- Tokens stored in: google_calendar_tokens table
- Calendar ID stored in: provider_settings table

Classes:
- GoogleCalendarAuth: OAuth 2.0 flow and token management
- GoogleCalendarAPI: Calendar API operations


NEXT STEPS
==========

1. Install Google PHP Client (optional, for advanced features)
2. Configure calendar syncing (if needed)
3. Set up booking → calendar event mapping
4. Test with sample bookings
5. Enable email notifications (optional)
";
    }
}

// If run directly from CLI
if (php_sapi_name() === 'cli') {
    $setup = new GoogleCalendarSetup();
    $setup->initialize();
    echo "\n" . GoogleCalendarSetup::getSetupInstructions() . "\n";
} else {
    // If accessed via browser
    $setup = new GoogleCalendarSetup();
    $success = $setup->initialize();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Google Calendar Setup</title>
        <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    </head>
    <body class="p-4">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Google Calendar Integration Setup</h2>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h4>✓ Setup Successful!</h4>
                            <p>Google Calendar integration has been initialized.</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h4>✗ Setup Failed</h4>
                            <p>Please check the error logs for more information.</p>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="mt-4">Setup Instructions</h3>
                    <pre><?php echo htmlspecialchars(GoogleCalendarSetup::getSetupInstructions()); ?></pre>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
