# Advanced Share Functionality Guide

## Overview

The BII LocalFinder provider card share functionality has been completely redesigned with modern, advanced features to enhance user engagement and provider discoverability.

## Features Implemented

### 1. **Multi-Platform Sharing**
   - **WhatsApp**: Direct share with rich message formatting
   - **Facebook**: Share to Facebook timeline or groups
   - **Twitter/X**: Tweet provider profiles with automatic hashtags
   - **Email**: Send provider recommendations with personal messages
   - **Direct Link**: Copy profile link to clipboard
   - **QR Code**: Generate scannable QR code for easy sharing

### 2. **Modern Share Modal Interface**
   - Clean, intuitive design with gradient backgrounds
   - Animated entry/exit transitions
   - Provider information preview in modal
   - Real-time share statistics
   - Responsive design for all devices

### 3. **Share Statistics & Analytics**
   - Track shares by platform
   - Monitor profile views (30-day rolling window)
   - Display share count to users
   - Database persistence for analytics

### 4. **Email Sharing with Personalization**
   - Custom message support
   - Professional HTML email templates
   - Sender information included
   - Direct link in email
   - Tracking of email shares

### 5. **QR Code Generation**
   - Dynamic QR code generation
   - Links directly to provider profile
   - Scannable from any mobile device
   - Uses popular QR code library (CDN-based)

### 6. **User Experience Enhancements**
   - Copy-to-clipboard confirmation
   - Share success messages
   - Loading states
   - Smooth animations
   - Mobile-optimized buttons

## Files Modified

### 1. `/client/providers.php`
   - Added Share Button to provider cards
   - New Share Modal (HTML structure)
   - Share Modal Styles (CSS)
   - JavaScript functions for share handling
   - Backend AJAX handlers:
     - `track_share` - Records share event
     - `get_share_stats` - Fetches statistics
     - `send_share_email` - Handles email sharing

### 2. `/includes/mailer.php`
   - New `sendCustomEmail()` static method
   - Used for sharing notifications
   - Supports HTML email templates

## Database Tables Created

### `provider_shares`
Tracks all share events:
```sql
CREATE TABLE provider_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    user_id INT,
    platform VARCHAR(50),
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    KEY(provider_id, shared_at)
)
```

### `provider_views`
Tracks profile views from shared links:
```sql
CREATE TABLE provider_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    user_id INT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    KEY(provider_id, viewed_at)
)
```

## JavaScript Functions

### Core Functions

```javascript
// Open share modal
openShareModal(providerId, providerName, profession)

// Close share modal
closeShareModal()

// Share via specific platform
shareVia(platform) // 'whatsapp', 'facebook', 'twitter', 'email', 'copy', 'qrcode'

// Copy link to clipboard
copyShareLink()

// Show email form
showEmailShareForm()

// Submit email share
submitEmailShare(event)

// Generate QR code
showQRCode(url)
generateQRCode(canvas, url)

// Track share event
trackShare(platform)

// Fetch share statistics
fetchShareStats(providerId)

// Show success message
showShareSuccess(message)
```

## API Endpoints (AJAX)

### 1. Track Share
**Request:**
```
GET /client/providers.php?track_share=1
POST data: {
    provider_id: int,
    platform: string
}
```

**Response:**
```json
{
    "success": true,
    "message": "Share tracked"
}
```

### 2. Get Share Statistics
**Request:**
```
GET /client/providers.php?get_share_stats=1&provider_id={id}
```

**Response:**
```json
{
    "success": true,
    "share_count": 15,
    "view_count": 42
}
```

### 3. Send Share Email
**Request:**
```
GET /client/providers.php?send_share_email=1
POST data: {
    recipient_email: string,
    provider_id: int,
    personal_message: string,
    share_link: string,
    provider_name: string,
    profession: string
}
```

**Response:**
```json
{
    "success": true,
    "message": "Email sent successfully"
}
```

## CSS Classes

### Share Modal
- `.share-modal-container` - Modal background with animation
- `.share-modal-content` - Modal content area
- `.share-modal-header` - Header with gradient
- `.share-modal-body` - Body content

### Share Elements
- `.share-provider-info` - Provider information display
- `.share-provider-avatar` - Avatar area
- `.share-options` - Share buttons container
- `.share-btn` - Individual share button
- `.share-link-section` - Link input area
- `.share-qrcode-container` - QR code display area
- `.share-email-form` - Email form container
- `.share-stats` - Statistics display

### Animations
- `fadeInShare` - Modal fade-in animation
- `slideUpShare` - Modal slide-up animation
- `slideInAlert` - Alert message animation

## Usage Example

```php
// Open share modal from provider card
<button onclick="openShareModal(<?php echo $provider['id']; ?>, 
        '<?php echo htmlspecialchars($provider['full_name']); ?>', 
        '<?php echo htmlspecialchars($provider['profession']); ?>')">
    <i class="fas fa-share-alt"></i> Share
</button>
```

## Features Breakdown

### Social Media Integration
- **WhatsApp**: Uses official WhatsApp API (wa.me)
- **Facebook**: Uses Facebook Share Dialog
- **Twitter**: Uses Twitter Web Intent
- All include provider name, profession, and direct link

### Email Sharing
- Validates recipient email
- Creates professional HTML email
- Includes sender information
- Automatically adds timestamp
- Supports optional personal message

### QR Code
- Dynamically loads QR code library from CDN
- Generates code on first use
- Direct link to provider profile
- High error correction level

### Analytics
- Tracks platform usage
- Records user and timestamp
- 30-day rolling window for statistics
- Helps identify popular providers

## Security Considerations

1. **Input Validation**
   - Email validation using FILTER_VALIDATE_EMAIL
   - Provider ID sanitization
   - XSS prevention in output

2. **User Privacy**
   - Optional user ID logging
   - Anonymous shares supported
   - User can choose sharing method

3. **Rate Limiting**
   - Database records each share
   - Can be used for abuse detection
   - Admin panel can monitor suspicious patterns

## Performance Optimization

1. **Lazy Loading**
   - QR code library loads on demand
   - Statistics fetched via AJAX
   - Modal content loaded asynchronously

2. **Caching**
   - Share statistics cached in database
   - 30-day aggregation reduces queries
   - Statistics update in real-time

3. **Database Optimization**
   - Indexes on provider_id and shared_at
   - Automatic table creation on first use
   - Efficient query patterns

## Mobile Responsiveness

- Touch-friendly buttons (minimum 44px height)
- Responsive grid layout (col-6 col-md-4)
- Full-width inputs on mobile
- Optimized modal sizing
- Automatic text truncation

## Browser Compatibility

- Chrome/Edge (Latest)
- Firefox (Latest)
- Safari (Latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Dependencies

1. **External Libraries**
   - QRCode.js (CDN-loaded from cdnjs.cloudflare.com)
   - Font Awesome icons
   - Bootstrap framework

2. **Internal**
   - Mailer class (mailer.php)
   - Database (database.php)
   - Functions (functions.php)

## Future Enhancements

1. **Referral Tracking**
   - Track actual conversions from shares
   - Reward users for successful referrals
   - Provider booking attribution

2. **Advanced Analytics**
   - Dashboard visualization
   - Share trend analysis
   - Platform performance metrics

3. **Social Proof**
   - Display share count on provider card
   - Show recent shares
   - Social proof badges

4. **Customization**
   - Custom share messages per provider
   - Branded QR codes
   - Custom short URLs

## Troubleshooting

### QR Code not showing
- Check browser console for errors
- Verify CDN accessibility
- Clear browser cache

### Email not sending
- Verify SMTP settings in mailer.php
- Check system_settings for email_notifications
- Review error logs

### Share tracking not working
- Verify database tables exist
- Check user authentication
- Review AJAX response in console

## Support

For issues or questions about the share functionality, please refer to:
- `/docs/SHARE_FUNCTIONALITY_GUIDE.md` (this file)
- `/includes/mailer.php` (email handling)
- `/client/providers.php` (implementation details)

---

**Last Updated**: December 17, 2025
**Version**: 1.0
**Status**: Production Ready
