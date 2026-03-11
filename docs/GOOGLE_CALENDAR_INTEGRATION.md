# Google Calendar Integration Documentation

## Overview

The Google Calendar integration allows providers to automatically sync their bookings with Google Calendar. This system handles OAuth 2.0 authentication, token management, and provides APIs for calendar operations.

## Architecture

### Core Components

1. **GoogleCalendarAuth** (`includes/GoogleCalendarAuth.php`)
   - Handles OAuth 2.0 authentication flow
   - Manages access and refresh tokens
   - Stores credentials securely in database

2. **GoogleCalendarAPI** (`includes/GoogleCalendarAPI.php`)
   - Provides methods to interact with Google Calendar API
   - Create, update, delete events
   - Check availability and manage time off

3. **Callback Handler** (`provider/google-calendar-callback.php`)
   - Processes OAuth callback from Google
   - Exchanges authorization code for tokens
   - Handles errors and redirects

4. **Database Tables**
   - `google_calendar_tokens`: Stores access/refresh tokens
   - `provider_settings`: Stores calendar ID and other settings

## Setup Instructions

### 1. Google Cloud Console Setup

```bash
# Step 1: Create Google Cloud Project
https://console.cloud.google.com/

# Step 2: Enable Google Calendar API
- Search for "Google Calendar API"
- Click "Enable"

# Step 3: Create OAuth 2.0 Credentials
- Go to "Credentials"
- Create OAuth 2.0 Client ID (Web application)
- Add Authorized Redirect URIs:
  - http://localhost/provider/google-calendar-callback.php
  - https://yourdomain.com/provider/google-calendar-callback.php

# Step 4: Copy credentials
- Client ID: your_client_id.apps.googleusercontent.com
- Client Secret: your_client_secret
```

### 2. Environment Configuration

Create `.env` file in project root:

```env
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/provider/google-calendar-callback.php
```

Or set environment variables via:
- `.htaccess` file
- Web server configuration
- Hosting control panel

### 3. Database Setup

Run the setup script:

```bash
php config/setup-google-calendar.php
```

This creates the required `google_calendar_tokens` table.

## Usage

### For Providers

1. **Connect Google Calendar**
   - Log in to provider account
   - Go to Schedule → Integrations
   - Click "Connect Google Calendar"
   - Authorize the application
   - Account is now synced

2. **Disconnect Calendar**
   - Go to Schedule → Integrations
   - Click "Disconnect Google Calendar"
   - Authorization is revoked

### For Developers

#### Initialize GoogleCalendarAuth

```php
require_once 'includes/GoogleCalendarAuth.php';
require_once 'config/database.php';

// Create instance for a provider
$auth = new GoogleCalendarAuth($provider_id);

// Initialize token table (one-time)
$auth->initializeTokenTable();
```

#### Check Authentication Status

```php
$status = $auth->getAuthStatus();

if ($status['authenticated']) {
    echo "Authenticated";
    echo "Expires in: " . $status['expires_in'] . " seconds";
} else {
    echo "Not authenticated";
}
```

#### Get Authorization URL

```php
$auth_url = $auth->getAuthorizationUrl();
// Redirect user to this URL for authorization
header('Location: ' . $auth_url);
```

#### Handle OAuth Callback

```php
// In google-calendar-callback.php
try {
    $token_response = $auth->handleCallback($_GET['code'], $_GET['state']);
    // User is now authenticated
} catch (Exception $e) {
    // Handle error
    echo "Authentication failed: " . $e->getMessage();
}
```

#### Get Valid Access Token

```php
try {
    $access_token = $auth->getAccessToken();
    // Token is automatically refreshed if expired
} catch (Exception $e) {
    echo "Error getting token: " . $e->getMessage();
}
```

#### Use Calendar API

```php
require_once 'includes/GoogleCalendarAPI.php';

$access_token = $auth->getAccessToken();
$api = new GoogleCalendarAPI($access_token);

// Get primary calendar ID
$calendar_id = $api->getPrimaryCalendarId();

// List events
$events = $api->listEvents($calendar_id, [
    'timeMin' => '2025-01-01T00:00:00Z',
    'timeMax' => '2025-01-31T23:59:59Z',
    'singleEvents' => 'true'
]);

// Create event
$event = [
    'summary' => 'Service Appointment',
    'description' => 'Client: John Doe',
    'start' => ['dateTime' => '2025-01-15T10:00:00'],
    'end' => ['dateTime' => '2025-01-15T11:00:00']
];
$api->createEvent($calendar_id, $event);

// Add time off
$api->addTimeOff($calendar_id, '2025-02-01', '2025-02-07', 'Vacation');

// Check availability
$available = $api->isTimeAvailable(
    $calendar_id,
    '2025-01-15T10:00:00Z',
    '2025-01-15T11:00:00Z'
);
```

#### Revoke Access

```php
$success = $auth->revokeAccess();
// Tokens deleted from database and Google access revoked
```

## Database Schema

### google_calendar_tokens Table

```sql
CREATE TABLE google_calendar_tokens (
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
);
```

### provider_settings Table

Stores calendar_id and other settings:

```sql
INSERT INTO provider_settings (provider_id, setting_key, setting_value)
VALUES (12, 'google_calendar_id', 'provider@gmail.com');
```

## Token Management

### Automatic Refresh

Tokens are automatically refreshed when expired:

```php
$access_token = $auth->getAccessToken();
// If expired, automatically refreshes and returns new token
```

### Manual Refresh

```php
$auth->refreshAccessToken();
// Returns new token response
```

### Token Storage

Tokens are stored encrypted in the database with:
- `access_token`: Current access token
- `refresh_token`: Refresh token (long-lived)
- `expires_at`: Unix timestamp of expiration
- `authenticated_at`: When authorization was granted

## Error Handling

### Common Errors

**Invalid State Token**
```
Error: Invalid state token. Possible CSRF attack.
Solution: Ensure session is preserved during callback
```

**Redirect URI Mismatch**
```
Error: The redirect_uri parameter does not match...
Solution: Ensure callback URL matches exactly in Google Console
```

**Token Expired**
```
Error: No refresh token available
Solution: User must re-authorize the application
```

**Calendar Not Found**
```
Error: API Error 404: calendarNotFound
Solution: Check that calendar ID is valid and accessible
```

## Security Considerations

1. **Credentials**
   - Never commit credentials to version control
   - Use environment variables for configuration
   - Store client secret securely

2. **Tokens**
   - Tokens stored in database (consider encryption at rest)
   - Refresh tokens have long expiration (months/years)
   - Access tokens expire quickly (1 hour)

3. **Communication**
   - Always use HTTPS in production
   - Verify SSL certificates
   - Use secure redirect URIs

4. **Authorization**
   - Validate state token to prevent CSRF
   - Verify user is logged in before authorizing
   - Log authorization attempts

5. **Revocation**
   - Provide easy revocation for users
   - Immediately revoke if credentials compromised
   - Audit revocation attempts

## Troubleshooting

### Setup Issues

**Table doesn't exist**
```php
// Run setup script
php config/setup-google-calendar.php
```

**Environment variables not read**
```php
// Check with phpinfo()
phpinfo();

// Or manually add to config
define('GOOGLE_CLIENT_ID', 'your_id');
```

### Authentication Issues

**Redirect loop**
- Check that callback URL matches exactly
- Clear browser cookies/cache
- Verify session is working

**Invalid client ID**
- Double-check credentials in Google Console
- Regenerate if needed
- Verify environment variables loaded

### API Issues

**Events not syncing**
- Check access token validity
- Verify calendar ID is correct
- Check API quota limits
- Review error logs

**Availability check failing**
- Ensure calendar is accessible
- Check time format (must be RFC 3339)
- Verify no API errors in logs

## Performance Optimization

### Caching

```php
// Cache token status to reduce DB queries
$cache_key = 'google_auth_status_' . $provider_id;
if ($status = apcu_fetch($cache_key)) {
    return $status;
}

$status = $auth->getAuthStatus();
apcu_store($cache_key, $status, 300); // 5 min cache
```

### Rate Limiting

Google Calendar API has quota limits:
- Requests per day: 1,000,000
- Requests per 100 seconds: 1,000

Implement rate limiting:
```php
// Check quota before making request
if ($requests_today >= 1000000) {
    throw new Exception('Daily quota exceeded');
}
```

### Batch Operations

```php
// Instead of creating events one by one
$events = [/* array of events */];

// Create a batch request
// (requires advanced API usage)
```

## Integration Examples

### Sync Booking to Calendar

```php
function syncBookingToCalendar($booking_id, $provider_id) {
    global $db;
    
    // Get booking details
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    // Get provider auth
    $auth = new GoogleCalendarAuth($provider_id);
    $access_token = $auth->getAccessToken();
    $api = new GoogleCalendarAPI($access_token);
    
    // Get calendar ID
    $calendar_id = $auth->getCalendarId();
    
    // Create event
    $event = [
        'summary' => 'Booking: ' . $booking['service_description'],
        'start' => [
            'dateTime' => $booking['preferred_date'] . 'T' . $booking['preferred_time'],
            'timeZone' => 'UTC'
        ],
        'end' => [
            'dateTime' => date('c', strtotime($booking['preferred_date'] . ' ' . $booking['preferred_time'] . ' +1 hour')),
            'timeZone' => 'UTC'
        ]
    ];
    
    $result = $api->createEvent($calendar_id, $event);
    
    // Store event ID
    $stmt = $db->prepare("UPDATE bookings SET google_event_id = ? WHERE id = ?");
    $stmt->execute([$result['id'], $booking_id]);
}
```

### Delete Booking from Calendar

```php
function deleteBookingFromCalendar($booking_id, $provider_id) {
    global $db;
    
    // Get booking
    $stmt = $db->prepare("SELECT google_event_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking['google_event_id']) {
        return true; // No event to delete
    }
    
    // Get auth
    $auth = new GoogleCalendarAuth($provider_id);
    $access_token = $auth->getAccessToken();
    $api = new GoogleCalendarAPI($access_token);
    $calendar_id = $auth->getCalendarId();
    
    // Delete event
    $api->deleteEvent($calendar_id, $booking['google_event_id']);
    
    // Clear event ID
    $stmt = $db->prepare("UPDATE bookings SET google_event_id = NULL WHERE id = ?");
    $stmt->execute([$booking_id]);
}
```

## Testing

### Unit Tests

```php
class GoogleCalendarAuthTest extends PHPUnit_Framework_TestCase {
    
    public function testGetAuthorizationUrl() {
        $auth = new GoogleCalendarAuth(1);
        $url = $auth->getAuthorizationUrl();
        
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=', $url);
        $this->assertStringContainsString('state=', $url);
    }
    
    public function testTokenStorage() {
        $auth = new GoogleCalendarAuth(1);
        $auth->initializeTokenTable();
        
        $tokens = [
            'access_token' => 'test_token',
            'refresh_token' => 'refresh_token',
            'expires_in' => 3600
        ];
        
        // Store tokens
        // Verify stored correctly
        $stored = $auth->getStoredTokens();
        $this->assertEquals($tokens['access_token'], $stored['access_token']);
    }
}
```

## Future Enhancements

1. **Batch Syncing**
   - Sync multiple bookings at once
   - Background job processing

2. **Two-Way Sync**
   - Sync calendar events back to bookings
   - Conflict detection and resolution

3. **Multiple Calendar Support**
   - Sync to multiple calendars
   - Per-service calendar selection

4. **Advanced Features**
   - Automatic reminder emails
   - Video conferencing links
   - Calendar sharing

5. **Mobile Integration**
   - Mobile calendar sync
   - Push notifications

## Support & Resources

- [Google Calendar API Documentation](https://developers.google.com/calendar/api)
- [OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Google Cloud Console](https://console.cloud.google.com/)
- [API Explorer](https://developers.google.com/calendar/api/quickstart/php)

## License

This integration is part of BII LocalFinder platform.
