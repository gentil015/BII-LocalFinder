# Google Calendar Integration - Quick Reference

## Files Created/Modified

### New Files
1. **`includes/GoogleCalendarAuth.php`** - OAuth 2.0 authentication handler
2. **`includes/GoogleCalendarAPI.php`** - Google Calendar API wrapper
3. **`provider/google-calendar-callback.php`** - OAuth callback handler
4. **`config/google-oauth.config.php`** - Configuration template
5. **`config/setup-google-calendar.php`** - Database setup script
6. **`docs/GOOGLE_CALENDAR_INTEGRATION.md`** - Full documentation

### Modified Files
1. **`provider/schedule.php`** - Added Google Calendar authentication UI and handlers

## Quick Start

### 1. Get Google OAuth Credentials
```
https://console.cloud.google.com/
1. Create project
2. Enable Google Calendar API
3. Create OAuth 2.0 Client ID (Web app)
4. Add redirect URI: https://yourdomain.com/provider/google-calendar-callback.php
5. Copy Client ID and Secret
```

### 2. Set Environment Variables
Create `.env` file or set server environment:
```env
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
```

### 3. Initialize Database
```bash
php config/setup-google-calendar.php
```

### 4. Test Integration
- Log in as provider
- Go to Schedule → Integrations
- Click "Connect Google Calendar"
- Authorize and test

## Core Classes

### GoogleCalendarAuth
```php
// Initialize
$auth = new GoogleCalendarAuth($provider_id);

// Get auth URL (step 1)
$url = $auth->getAuthorizationUrl();

// Handle callback (step 2)
$tokens = $auth->handleCallback($code, $state);

// Get access token (auto-refreshes if needed)
$token = $auth->getAccessToken();

// Check status
$status = $auth->getAuthStatus();
// Returns: {authenticated: bool, expires_in: int, is_expired: bool, ...}

// Revoke access
$auth->revokeAccess();

// Manage calendar ID
$auth->setCalendarId($calendar_id);
$calendar_id = $auth->getCalendarId();
```

### GoogleCalendarAPI
```php
// Initialize with access token
$api = new GoogleCalendarAPI($access_token);

// Calendar operations
$events = $api->listEvents($calendar_id, [
    'timeMin' => '2025-01-01T00:00:00Z',
    'timeMax' => '2025-01-31T23:59:59Z'
]);

$api->createEvent($calendar_id, [
    'summary' => 'Appointment',
    'start' => ['dateTime' => '2025-01-15T10:00:00'],
    'end' => ['dateTime' => '2025-01-15T11:00:00']
]);

$api->updateEvent($calendar_id, $event_id, $updated_data);
$api->deleteEvent($calendar_id, $event_id);

// Time off
$api->addTimeOff($calendar_id, '2025-02-01', '2025-02-07', 'Vacation');

// Check availability
$available = $api->isTimeAvailable(
    $calendar_id,
    '2025-01-15T10:00:00Z',
    '2025-01-15T11:00:00Z'
);
```

## Database Schema

### google_calendar_tokens
Stores OAuth tokens for providers:
```sql
- id (PK)
- provider_id (FK, UNIQUE)
- access_token (LONGTEXT)
- refresh_token (LONGTEXT)
- expires_in (INT)
- expires_at (INT) - Unix timestamp
- token_type (VARCHAR)
- scope (LONGTEXT)
- authenticated_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### provider_settings
Stores calendar ID and other settings:
```sql
- setting_key: 'google_calendar_id'
- setting_value: 'provider@gmail.com' or calendar ID
```

## Common Operations

### Check if Provider is Authenticated
```php
$auth = new GoogleCalendarAuth($provider_id);
if ($auth->isAuthenticated()) {
    echo "Connected to Google Calendar";
}
```

### Create Event from Booking
```php
$auth = new GoogleCalendarAuth($provider_id);
$token = $auth->getAccessToken();
$api = new GoogleCalendarAPI($token);

$calendar_id = $auth->getCalendarId();

$api->createEvent($calendar_id, [
    'summary' => 'Booking: ' . $booking['service_description'],
    'start' => ['dateTime' => $booking['preferred_date'] . 'T' . $booking['preferred_time']],
    'end' => ['dateTime' => date('c', strtotime($booking['preferred_date'] . ' ' . $booking['preferred_time'] . ' +1 hour'))]
]);
```

### Sync Provider's Time Off
```php
$time_off = [
    'start_date' => '2025-02-01',
    'end_date' => '2025-02-07',
    'reason' => 'Vacation'
];

$api->addTimeOff(
    $calendar_id,
    $time_off['start_date'],
    $time_off['end_date'],
    $time_off['reason']
);
```

### Handle Token Expiration
Automatic in `getAccessToken()`:
```php
$token = $auth->getAccessToken();
// If expired, automatically refreshes and returns new token
// No additional code needed
```

## UI Integration (schedule.php)

### Connect Button
```php
<?php if (!$google_authenticated): ?>
    <form method="POST">
        <input type="hidden" name="return_url" value="schedule.php?tab=integrations">
        <button type="submit" name="start_google_auth" class="btn btn-primary">
            <i class="fab fa-google me-2"></i> Connect Google Calendar
        </button>
    </form>
<?php endif; ?>
```

### Disconnect Button
```php
<?php if ($google_authenticated): ?>
    <form method="POST" style="display: inline;">
        <button type="submit" name="disconnect_google" class="btn btn-outline-danger">
            <i class="fas fa-unlink me-2"></i> Disconnect
        </button>
    </form>
<?php endif; ?>
```

### Status Display
```php
<?php if ($google_authenticated): ?>
    <div class="alert alert-success">
        Authenticated: <?php echo date('M d, Y', strtotime($google_auth_status['authenticated_at'])); ?>
    </div>
<?php endif; ?>
```

## Error Handling

### Common Errors

```php
try {
    $auth = new GoogleCalendarAuth($provider_id);
    $auth->initializeTokenTable();
    $url = $auth->getAuthorizationUrl();
} catch (Exception $e) {
    // Handle: "Google Client ID not configured"
    // Solution: Set GOOGLE_CLIENT_ID environment variable
    error_log($e->getMessage());
}

try {
    $tokens = $auth->handleCallback($code, $state);
} catch (Exception $e) {
    // Handle: "Invalid state token" or authorization errors
    // Solution: Verify session and state parameter
}

try {
    $token = $auth->getAccessToken();
} catch (Exception $e) {
    // Handle: "No Google Calendar tokens found"
    // Solution: User must authenticate first
}
```

## Testing Checklist

- [ ] Google OAuth credentials obtained
- [ ] Environment variables set
- [ ] Database setup script ran
- [ ] Provider can click "Connect Google Calendar"
- [ ] OAuth flow completes successfully
- [ ] Provider is redirected back to schedule
- [ ] "Disconnect" button appears when authenticated
- [ ] Can revoke authorization
- [ ] Events can be created/updated/deleted
- [ ] Time off syncs correctly
- [ ] Token refresh works automatically

## Production Deployment

1. **Security**
   - Use environment variables for credentials
   - Enable HTTPS
   - Set secure redirect URIs

2. **Monitoring**
   - Log authentication attempts
   - Monitor API quota usage
   - Alert on token refresh failures

3. **Backup**
   - Backup token database regularly
   - Keep credentials secure
   - Document revocation procedures

4. **Updates**
   - Keep Google API client library updated
   - Monitor API changes
   - Test before deploying

## Support Resources

- [Full Documentation](GOOGLE_CALENDAR_INTEGRATION.md)
- [Google Calendar API Docs](https://developers.google.com/calendar/api)
- [OAuth 2.0 Flow](https://developers.google.com/identity/protocols/oauth2)
- Setup Script: `config/setup-google-calendar.php`

## Troubleshooting

**Issue: "Client ID not configured"**
```
✓ Check GOOGLE_CLIENT_ID environment variable
✓ Verify .env file exists in project root
✓ Restart web server after adding env var
```

**Issue: "Redirect URI mismatch"**
```
✓ Ensure callback URL matches exactly in Google Console
✓ Include full path: /provider/google-calendar-callback.php
✓ Use HTTPS in production
```

**Issue: "Invalid state token"**
```
✓ Verify session is enabled
✓ Check session.save_path is writable
✓ Clear browser cookies and try again
```

**Issue: "No tokens found"**
```
✓ Run config/setup-google-calendar.php
✓ Verify database permissions
✓ Check google_calendar_tokens table exists
```
