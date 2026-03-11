# Google Calendar Integration - Installation Checklist

## Pre-Installation Requirements

- [ ] Apache/Nginx web server with PHP 7.4+
- [ ] MySQL/MariaDB database
- [ ] cURL extension enabled in PHP
- [ ] OpenSSL extension enabled in PHP
- [ ] Write permissions on database
- [ ] HTTPS enabled (for production)

## Step 1: Get Google OAuth Credentials (5 minutes)

### Create Google Cloud Project
- [ ] Go to https://console.cloud.google.com/
- [ ] Create a new project (or select existing)
- [ ] Name: "BII LocalFinder" (or your app name)
- [ ] Project created successfully

### Enable Google Calendar API
- [ ] In Cloud Console, search for "Google Calendar API"
- [ ] Click on "Google Calendar API"
- [ ] Click "Enable"
- [ ] Wait for enabling to complete

### Create OAuth 2.0 Credentials
- [ ] Go to "Credentials" in Cloud Console
- [ ] Click "Create Credentials" → "OAuth 2.0 Client ID"
- [ ] Application type: "Web application"
- [ ] Add Authorized Redirect URIs:
  - [ ] `http://localhost/provider/google-calendar-callback.php` (development)
  - [ ] `https://yourdomain.com/provider/google-calendar-callback.php` (production)
- [ ] Click "Create"
- [ ] Copy "Client ID" (save securely)
- [ ] Copy "Client Secret" (save securely)
- [ ] Download credentials JSON (backup)

## Step 2: Configure Environment Variables (3 minutes)

### Create .env File
- [ ] In project root, create file `.env`
- [ ] Copy content from `.env.example`
- [ ] Add Google credentials:
  ```env
  GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
  GOOGLE_CLIENT_SECRET=your_client_secret
  GOOGLE_REDIRECT_URI=http://localhost/provider/google-calendar-callback.php
  ```
- [ ] Save file
- [ ] Verify file is NOT in version control (.gitignore)

### Verify Environment Variables
- [ ] Create test file: `test_env.php`
  ```php
  <?php
  echo "CLIENT_ID: " . getenv('GOOGLE_CLIENT_ID') . "\n";
  echo "CLIENT_SECRET: " . getenv('GOOGLE_CLIENT_SECRET') . "\n";
  ?>
  ```
- [ ] Access via browser: `http://localhost/test_env.php`
- [ ] Verify values are displayed
- [ ] Delete test file

## Step 3: Initialize Database (2 minutes)

### Create Token Table
- [ ] Access: `http://localhost/config/setup-google-calendar.php`
- [ ] See confirmation: "Google Calendar integration initialized successfully"
- [ ] Check database:
  - [ ] Table `google_calendar_tokens` exists
  - [ ] Has columns: access_token, refresh_token, expires_at, etc.
  - [ ] Foreign key to service_providers

### Alternative: CLI Setup
- [ ] Open terminal in project root
- [ ] Run: `php config/setup-google-calendar.php`
- [ ] See confirmation message
- [ ] Check database for tables

### Verify Database Changes
- [ ] In MySQL/MariaDB:
  ```sql
  SHOW TABLES LIKE 'google_calendar%';
  DESCRIBE google_calendar_tokens;
  ```
- [ ] All tables created successfully
- [ ] All columns present

## Step 4: Verify File Structure (1 minute)

### Check Core Files Exist
- [ ] `includes/GoogleCalendarAuth.php` (380 lines)
- [ ] `includes/GoogleCalendarAPI.php` (200 lines)
- [ ] `includes/GoogleCalendarHelpers.php` (250 lines)
- [ ] `provider/google-calendar-callback.php` (100 lines)
- [ ] `provider/schedule.php` (modified - check integrations tab)

### Check Configuration Files
- [ ] `config/google-oauth.config.php`
- [ ] `config/setup-google-calendar.php`
- [ ] `config/google-calendar-migration.php`
- [ ] `.env.example`
- [ ] `.env` (created with credentials)

### Check Documentation
- [ ] `docs/GOOGLE_CALENDAR_INTEGRATION.md`
- [ ] `docs/GOOGLE_CALENDAR_QUICK_REFERENCE.md`
- [ ] `GOOGLE_CALENDAR_IMPLEMENTATION.md`
- [ ] `SETUP_GOOGLE_CALENDAR.md`

## Step 5: Test OAuth Flow (5 minutes)

### Login as Provider
- [ ] Access provider login page
- [ ] Log in with valid provider credentials
- [ ] Verify session is created

### Navigate to Schedule
- [ ] Go to "Schedule" (or "Provider Dashboard")
- [ ] Navigate to "Integrations" tab
- [ ] Verify page loads without errors

### Start OAuth Flow
- [ ] Click "Connect Google Calendar" button
- [ ] Verify redirected to Google login

### Complete Authorization
- [ ] Log in with Google account (if needed)
- [ ] Grant permissions (see "Select a Google Account" screen)
- [ ] Click "Continue" to authorize
- [ ] Verify redirected back to schedule page

### Verify Success
- [ ] See success message: "Authentication Successful"
- [ ] See "Disconnect Google Calendar" button
- [ ] Check database:
  - [ ] Token stored in `google_calendar_tokens` table
  - [ ] access_token has value
  - [ ] refresh_token has value (optional)

## Step 6: Test Core Functionality (5 minutes)

### Test Status Display
- [ ] Go back to Integrations tab
- [ ] Verify "Connected" status shown
- [ ] See authentication date displayed

### Test Token Refresh (optional)
- [ ] In database, set `expires_at` to past timestamp:
  ```sql
  UPDATE google_calendar_tokens SET expires_at = UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY)) WHERE provider_id = 1;
  ```
- [ ] Refresh provider page
- [ ] Verify still authenticated (token auto-refreshed)

### Test Revocation
- [ ] Click "Disconnect Google Calendar"
- [ ] Confirm disconnection
- [ ] Verify success message
- [ ] Check database:
  - [ ] Token record deleted
  - [ ] Can see "Connect" button again

## Step 7: Optional - Test Booking Sync (5 minutes)

### Create Test Booking
- [ ] Go to client portal
- [ ] Create a test booking with this provider
- [ ] Verify booking created in database

### Check Calendar Sync (if integrated)
- [ ] In Google Calendar, refresh
- [ ] Look for booking event
- [ ] Verify event details correct
- [ ] Check `bookings.google_event_id` populated in database

### Update Booking (if integrated)
- [ ] Modify booking details
- [ ] Check if Google Calendar event updated
- [ ] Verify changes synced

## Step 8: Production Deployment (10 minutes)

### Prepare Production
- [ ] Update `GOOGLE_REDIRECT_URI` to production domain:
  ```env
  GOOGLE_REDIRECT_URI=https://yourdomain.com/provider/google-calendar-callback.php
  ```
- [ ] Update Google Cloud Console redirect URIs
- [ ] Enable HTTPS on server
- [ ] Test OAuth flow on production domain

### Security Hardening
- [ ] Move `.env` file outside web root (if possible)
- [ ] Use environment variables via hosting platform
- [ ] Enable SECURE_COOKIES in .env
- [ ] Enable HTTPS_REQUIRED in .env
- [ ] Rotate credentials if exposed
- [ ] Enable logging for debug

### Database Backup
- [ ] Create backup of database
- [ ] Export `google_calendar_tokens` table
- [ ] Store in secure location
- [ ] Document restore procedure

### Monitoring Setup
- [ ] Set up error logging
- [ ] Monitor API quota usage
- [ ] Set up alerts for failures
- [ ] Review logs regularly

## Troubleshooting Checklist

### Issue: "Google Client ID not configured"
- [ ] Check `.env` file exists
- [ ] Verify GOOGLE_CLIENT_ID is set
- [ ] Restart web server
- [ ] Check environment variables loaded:
  ```php
  echo getenv('GOOGLE_CLIENT_ID');
  ```

### Issue: "Redirect URI mismatch"
- [ ] Check exact URI in `.env`
- [ ] Check exact URI in Google Cloud Console
- [ ] Ensure no trailing slashes
- [ ] Verify protocol (http vs https)
- [ ] Include full path: `/provider/google-calendar-callback.php`

### Issue: "Invalid state token"
- [ ] Clear browser cookies
- [ ] Check PHP session is working
- [ ] Verify session.save_path is writable
- [ ] Check PHP version >= 7.4

### Issue: "SQLSTATE[42S02]: Table or view not found"
- [ ] Run setup script again:
  ```bash
  php config/setup-google-calendar.php
  ```
- [ ] Or access via browser:
  ```
  http://localhost/config/setup-google-calendar.php
  ```
- [ ] Check database user has CREATE TABLE privilege

### Issue: "cURL error: SSL certificate problem"
- [ ] Disable SSL verification for development only:
  ```php
  // In GoogleCalendarAuth::makeTokenRequest()
  CURLOPT_SSL_VERIFYPEER => false, // Not for production!
  ```
- [ ] Or: Install CA certificates on server
- [ ] Or: Use PHP with curl bundle enabled

## Verification Tests

### Unit Tests
- [ ] GoogleCalendarAuth class instantiation
  ```php
  $auth = new GoogleCalendarAuth(1);
  ```
- [ ] Authorization URL generation
  ```php
  $url = $auth->getAuthorizationUrl();
  ```
- [ ] Helper functions available
  ```php
  require_once 'includes/GoogleCalendarHelpers.php';
  isGoogleCalendarConnected(1);
  ```

### Integration Tests
- [ ] User can connect Google Calendar
- [ ] User can disconnect
- [ ] Status shows authentication date
- [ ] Token is stored in database
- [ ] Token is automatically refreshed

### Security Tests
- [ ] CSRF state token is validated
- [ ] Tokens not exposed in logs
- [ ] Sensitive data not in URLs
- [ ] HTTPS enforced in production
- [ ] Session is secure (HttpOnly, Secure flags)

## Sign-Off Checklist

- [ ] All files created and in place
- [ ] Environment variables configured
- [ ] Database setup complete
- [ ] OAuth flow tested successfully
- [ ] Provider can connect/disconnect
- [ ] No errors in error logs
- [ ] Security checks passed
- [ ] Documentation reviewed
- [ ] Backup created
- [ ] Production ready

## Support Resources

| Resource | Location |
|----------|----------|
| Quick Start | `docs/GOOGLE_CALENDAR_QUICK_REFERENCE.md` |
| Full Docs | `docs/GOOGLE_CALENDAR_INTEGRATION.md` |
| Setup Guide | `SETUP_GOOGLE_CALENDAR.md` |
| Implementation | `GOOGLE_CALENDAR_IMPLEMENTATION.md` |
| Example .env | `.env.example` |
| Setup Script | `config/setup-google-calendar.php` |

## Final Verification

### Test Case 1: New Provider Connection
- [ ] Log in as provider
- [ ] Navigate to Schedule → Integrations
- [ ] Click "Connect Google Calendar"
- [ ] Complete OAuth flow
- [ ] See success message
- [ ] Token stored in database

### Test Case 2: Existing Provider Disconnection
- [ ] Log in as connected provider
- [ ] Go to Schedule → Integrations
- [ ] Click "Disconnect"
- [ ] Confirm disconnection
- [ ] Token deleted from database

### Test Case 3: Token Expiration & Refresh
- [ ] Set token as expired in database
- [ ] Try to get access token via code
- [ ] Verify automatic refresh occurs
- [ ] New token stored correctly

### Test Case 4: Error Handling
- [ ] Try invalid redirect URI
- [ ] Try missing client ID
- [ ] Try expired authorization code
- [ ] Verify friendly error messages shown

## Performance Baseline

- OAuth authorization flow: 1-2 seconds
- Token refresh: 500ms
- Database queries: < 10ms
- API rate limiting: None (within Google limits)

## Sign-Off

- **Installer Name**: ___________________
- **Installation Date**: ___________________
- **Domain**: ___________________
- **Notes**: ___________________

---

**Installation Complete!** ✅

Your Google Calendar integration is now ready to use.

For support, refer to:
- Quick Reference: `docs/GOOGLE_CALENDAR_QUICK_REFERENCE.md`
- Full Documentation: `docs/GOOGLE_CALENDAR_INTEGRATION.md`
- Troubleshooting: `docs/GOOGLE_CALENDAR_INTEGRATION.md#troubleshooting`
