# Notification System - Quick Reference

## Functions Quick Guide

### Creating Notifications

```php
// Include the notifications file
require_once __DIR__ . '/../includes/notifications.php';

// Booking notification
notifyNewBooking($provider_id, $booking_id, [
    'client_name' => 'John Doe',
    'service_description' => 'Service description'
]);

// Favorite notification  
notifyFavoriteAction($provider_id, $client_id, 'added', 'Jane Smith');

// Service notifications
notifyServiceUpdate($provider_id, $service_id, 'updated', 'Service Name');
notifyServiceUpdate($provider_id, $service_id, 'added', 'Service Name');

// Review notification
notifyReviewReceived($provider_id, $review_id, 'Client Name', 4.5);

// Offer notification
notifyOfferReceived($provider_id, $offer_id, [
    'client_name' => 'Client Name',
    'amount' => 50000
]);

// Profile view notification
notifyProfileView($provider_id, $viewer_id, 'Viewer Name');

// Complaint notification
notifyComplaintReceived($provider_id, $complaint_id, 'Client Name');
```

### Getting Notifications

```php
// Get all notifications for provider
$notifications = getNotifications($provider_id);

// Get with filters
$bookings = getNotifications($provider_id, [
    'type' => 'booking',
    'limit' => 20
]);

// Get only unread
$unread = getNotifications($provider_id, [
    'is_read' => false,
    'limit' => 50
]);

// Get unread count
$count = getUnreadNotificationCount($provider_id);

// Get grouped by type
$grouped = getNotificationsGrouped($provider_id);
```

### Managing Notifications

```php
// Mark single notification as read
markNotificationAsRead($notification_id, $provider_id);

// Mark all as read
markAllNotificationsAsRead($provider_id);

// Delete notification
deleteNotification($notification_id, $provider_id);

// Delete all notifications
deleteAllNotifications($provider_id);
```

### Preferences

```php
// Create default preferences
createDefaultNotificationPreferences($provider_id);

// Get preferences
$prefs = getNotificationPreferences($provider_id);

// Update preferences
updateNotificationPreferences($provider_id, [
    'booking_notifications' => 1,
    'offer_notifications' => 1,
    'email_notifications' => 1,
    'notification_digest_frequency' => 'instant'
]);

// Check if type enabled
if (isNotificationTypeEnabled($provider_id, 'booking')) {
    // Send notification
}
```

## Notification Types

| Type | When Used |
|------|-----------|
| `booking` | New booking request from client |
| `offer` | Client sends price negotiation offer |
| `favorite` | Client adds provider to favorites |
| `service_update` | Existing service is updated |
| `service_added` | New service is added |
| `review` | Client submits review |
| `profile_view` | Client views provider profile |
| `complaint` | Complaint filed against provider |
| `system` | System-level messages |

## Priority Levels

| Level | Usage |
|-------|-------|
| `low` | Non-urgent items (profile views) |
| `medium` | Regular items (offers, reviews) |
| `high` | Important items (bookings) |
| `urgent` | Critical items (complaints) |

## Database Tables

### notifications
- `id` - Primary key
- `user_id` - Provider receiving notification
- `notification_type` - Type of notification
- `title` - Notification title
- `message` - Notification message
- `related_id` - ID of related entity (booking, review, etc)
- `related_type` - Type of related entity
- `icon` - FontAwesome icon class
- `icon_color` - Color hex code
- `data` - JSON data field for extra info
- `is_read` - Read status (0/1)
- `read_at` - When marked as read
- `priority` - Priority level
- `action_url` - URL to take action
- `action_label` - Label for action button
- `created_at` - Timestamp
- `updated_at` - Last updated

### notification_preferences
- `id` - Primary key
- `user_id` - Provider ID
- `booking_notifications` - Enable/disable
- `offer_notifications` - Enable/disable
- `favorite_notifications` - Enable/disable
- `service_notifications` - Enable/disable
- `review_notifications` - Enable/disable
- `complaint_notifications` - Enable/disable
- `system_notifications` - Enable/disable
- `email_notifications` - Enable/disable email
- `push_notifications` - Enable/disable push
- `sms_notifications` - Enable/disable SMS
- `notification_digest_frequency` - Frequency preference

## API Endpoints

### Get Notifications
```
GET /provider/api/notifications.php?action=get_notifications&type=booking&limit=20
```
Returns: List of notifications

### Get Unread Count
```
GET /provider/api/notifications.php?action=get_unread_count
```
Returns: `{unread_count: N}`

### Mark As Read
```
POST /provider/api/notifications.php
Body: action=mark_as_read&notification_id=123
```
Returns: Success message

### Mark All Read
```
POST /provider/api/notifications.php
Body: action=mark_all_read&type=booking
```
Returns: Success message

### Delete Notification
```
POST /provider/api/notifications.php
Body: action=delete&notification_id=123
```
Returns: Success message

### Get Statistics
```
GET /provider/api/notifications.php?action=get_stats
```
Returns: Count and type breakdown

## Dashboard Access

Provider Notification Center:
```
/provider/dashboard-notifications.php
```

Features:
- Filter by type
- Sort by priority
- Mark as read/unread
- Delete notifications
- View statistics
- Real-time updates ready

## Integration Checklist

- [ ] Database migration executed
- [ ] `includes/notifications.php` exists and loads
- [ ] `provider/dashboard-notifications.php` accessible
- [ ] API endpoints working
- [ ] Booking notifications created
- [ ] Favorite notifications created
- [ ] Provider sidebar updated with link
- [ ] Test all notification types
- [ ] Test preferences working
- [ ] Email integration (optional)

## Testing Commands

```bash
# Create test notification
php -r "
require 'config/database.php';
require 'includes/notifications.php';
createNotification(1, 'booking', 'Test Booking', 'This is a test', [
    'related_id' => 1,
    'related_type' => 'booking',
    'priority' => 'high'
]);
echo 'Test notification created';
"

# Check notification table
mysql -u root -p bii_localfinder -e "SELECT COUNT(*) FROM notifications;"

# Get latest notifications
mysql -u root -p bii_localfinder -e "
SELECT id, user_id, notification_type, is_read, created_at 
FROM notifications 
ORDER BY created_at DESC 
LIMIT 10;
"
```

## Common Issues & Solutions

### Notifications not showing
1. Check database tables exist
2. Verify `isNotificationTypeEnabled()` returns true
3. Look in PHP error log

### API returning 403
1. Check provider is logged in
2. Verify session is active
3. Check `requireProvider()` function

### Bookings not creating notifications
1. Confirm booking saves successfully
2. Check `notifyNewBooking()` is called
3. Verify provider ID is correct

### Preferences not saving
1. Check `notification_preferences` table exists
2. Verify foreign key constraint
3. Check user_id is valid

## Best Practices

1. **Always include notification type** - Helps with filtering
2. **Set appropriate priority** - Urgent items get high priority
3. **Include action URL** - Let users navigate directly
4. **Test preferences** - Ensure users can disable types
5. **Monitor notification count** - Clean up old ones periodically
6. **Use JSON data field** - Store additional context
7. **Check enabled status** - Respect user preferences before sending
8. **Log failures** - Help with debugging

## Performance Tips

1. Use indexes on `user_id`, `is_read`, `created_at`
2. Limit queries to 50 notifications per page
3. Archive notifications older than 90 days
4. Use caching for frequently accessed data
5. Monitor database query performance

## Future Enhancements

- [ ] Email notifications
- [ ] Push notifications (browsers)
- [ ] SMS alerts
- [ ] Notification digest
- [ ] WebSocket real-time
- [ ] Notification templates
- [ ] Scheduled notifications
- [ ] Notification history export
- [ ] Custom notification sounds
- [ ] Notification batching

---

For detailed setup instructions, see: `docs/NOTIFICATION_SYSTEM_SETUP.md`
