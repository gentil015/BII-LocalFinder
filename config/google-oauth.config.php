<?php
/**
 * Google OAuth Configuration
 * 
 * IMPORTANT: This file contains sensitive credentials.
 * Never commit this file to version control.
 * Add this file to .gitignore
 */

// Google OAuth 2.0 Credentials
// Get these from Google Cloud Console: https://console.cloud.google.com/

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?? '');
}

if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?? '');
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?? '');
}

/**
 * How to setup Google OAuth:
 * 
 * 1. Create a project in Google Cloud Console:
 *    - Go to https://console.cloud.google.com/
 *    - Create a new project
 * 
 * 2. Enable Google Calendar API:
 *    - Search for "Google Calendar API"
 *    - Enable it for your project
 * 
 * 3. Create OAuth 2.0 credentials:
 *    - Go to Credentials page
 *    - Create OAuth 2.0 Client ID
 *    - Choose "Web application"
 *    - Add authorized redirect URIs:
 *      - http://localhost/provider/google-calendar-callback.php (development)
 *      - https://yourdomain.com/provider/google-calendar-callback.php (production)
 * 
 * 4. Copy the Client ID and Client Secret
 *    - Paste them below or set as environment variables
 * 
 * 5. Optionally create a service account for server-to-server operations
 *    - Download JSON key file
 *    - Store securely on your server
 */

// Example of how to set credentials from environment variables:
// In .env file or server environment:
// GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
// GOOGLE_CLIENT_SECRET=your_client_secret
// GOOGLE_REDIRECT_URI=https://yourdomain.com/provider/google-calendar-callback.php

// If using environment variables, they will be automatically loaded above

// For manual configuration, uncomment and fill in:
/*
define('GOOGLE_CLIENT_ID', 'your_client_id_here.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your_client_secret_here');
*/
