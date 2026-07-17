# Booking Details Page - Complete Documentation

## Overview
The `client/booking-details.php` page is a comprehensive booking information and payment processing interface that supports two distinct booking modes:
- **Request Approval**: Client requests service, provider responds, then payment is required
- **Instant Booking**: Client pays immediately to secure the service

## File Location
```
c:\xampp\htdocs\Bii_localFinder\client\booking-details.php
```

## Features

### 1. Booking Data Loading & Validation
- ✅ Loads booking by ID from URL parameter (`?id=booking_id`)
- ✅ Validates booking ownership (ensures only the client who created it can view)
- ✅ Fetches all related data: service info, provider info, payment details
- ✅ Retrieves booking mode from the associated service

### 2. Booking Mode Support

#### A. Request Approval Mode (`booking_mode = 'request_approval'`)

**Flow:**
```
1. Client creates booking → status = 'pending', payment_status = 'pending'
   Message: "Awaiting Your Confirmation - Review and proceed with payment"
   UI: [Pay to Confirm Booking] button appears

2. Client pays → payment_status = 'completed', booking status = 'confirmed'
   Message: "Service Scheduled - Your service is confirmed"
   UI: [Pay to Confirm Booking] button hidden, success message shown
```

#### B. Instant Booking Mode (`booking_mode = 'instant'`)

**Flow:**
```
1. Client creates & booking created → status = 'pending', payment_status = 'pending'
   Message: "Ready to Pay - Confirm and pay to secure this service"
   UI: [Confirm & Pay to Secure] button immediately visible

2. Client pays → payment_status = 'completed', booking status = 'confirmed'
   Message: "Service Scheduled - Your service is confirmed"
   UI: [Pay Now] button hidden, success message shown
```

### 3. Payment Logic

**When to Show "Pay Now" Button:**
```php
// Both Modes:
- booking.status = 'pending' AND payment_status = 'pending'

// Notes:
// - Instant bookings start as 'pending' if negotiable, or 'confirmed' if fixed-price
// - Request approval bookings always start as 'pending'
// - Payment button shows only when both conditions are met
```

**Payment Processing:**
```
1. User clicks [Pay Now] or [Confirm & Pay]
2. AJAX POST request sent to: /payments/process_payment.php
3. Returns: { success: true/false, message: "...", ... }
4. On success:
   - Hide pay button
   - Show success message
   - Reload page to reflect updated status
5. On failure:
   - Show error message
   - Re-enable button for retry
```

### 4. Security Features

- ✅ **Ownership Validation**: Checks `booking.client_id === session['user_id']`
- ✅ **Double Payment Prevention**: Validates payment_status before processing
- ✅ **State Validation**: Prevents payment action without proper booking state
- ✅ **Error Handling**: Graceful error messages and fallbacks

### 5. UI Components

#### Status Badge (Header)
- Dynamic color & icon based on booking state
- Shows current booking state in summary

#### Status Message Box
- Contextual messaging for each state
- Clear instructions for user's next action

#### Service Description
- Full service details in highlighted box
- Service name, category information

#### Provider Card
- Provider avatar/initials
- Name, profession, rating, review count
- Professional presentation

#### Schedule Section
- Preferred date
- Preferred time
- Service location
- Booking created timestamp

#### Amount Section
- Large, prominent display of price
- Payment status indicator
- "₦" currency symbol

#### Action Buttons
- Conditional rendering based on booking state
- [View Details] in listing
- [Pay Now] / [Confirm & Pay] when eligible
- [Leave Review] after service completion
- [Back to Bookings] always available

### 6. Database Queries

**Main Booking Query:**
```sql
SELECT 
    b.*,  -- All booking fields
    u.*, -- Provider user info
    sp.*, -- Service provider profile
    ps.*, -- Service details including booking_mode
    cat.* -- Category info
FROM bookings b
LEFT JOIN users u ON b.provider_id = u.id
LEFT JOIN service_providers sp ON b.provider_id = sp.user_id
LEFT JOIN provider_services ps ON b.service_id = ps.id
LEFT JOIN categories cat ON ps.category_id = cat.id
WHERE b.id = ? AND b.client_id = ?
```

**Payment Query:**
```php
$payment = $payment_manager->getPaymentForBooking($booking_id);
// Returns: payment record with id, status, amount, transaction_id, etc.
```

### 7. Important Note on Statuses

**Database Booking Statuses:** `pending`, `confirmed`, `completed`, `cancelled`

**Key Facts:**
- Instant bookings (non-negotiable) are created with `status = 'confirmed'` directly
- Request approval or negotiable instant bookings are created with `status = 'pending'`
- When payment succeeds, status automatically changes to `confirmed`
- The 'accepted' status shown in documentation refers to service offers, NOT bookings

### 8. Status Flow Diagrams

#### Request Approval Mode
```
┌─────────────────────────────────────────────────────────┐
│ pending, payment_status: pending                        │
│ "Awaiting Your Confirmation - Review and proceed"      │
│ ✅ [Pay to Confirm Booking] button                       │
└─────────────────┬───────────────────────────────────────┘
                  │ (User pays)
                  ↓
┌─────────────────────────────────────────────────────────┐
│ confirmed, payment_status: completed                    │
│ "Service Scheduled - Your service is confirmed"        │
│ ❌ Button hidden, success message                        │
└─────────────────┬───────────────────────────────────────┘
                  │
          (service completed)
                  ↓
┌─────────────────────────────────────────────────────────┐
│ completed                                               │
│ "Service Completed"                                     │
│ ✅ [Leave Review] button                                 │
└─────────────────────────────────────────────────────────┘
```

#### Instant Booking Mode
```
┌─────────────────────────────────────────────────────────┐
│ pending, payment_status: pending                        │
│ "Ready to Pay - Confirm and pay to secure this service"│
│ ✅ [Confirm & Pay to Secure]                             │
└─────────────────┬───────────────────────────────────────┘
                  │ (User pays)
                  ↓
┌─────────────────────────────────────────────────────────┐
│ confirmed, payment_status: completed                    │
│ "Service Scheduled - Your service is confirmed"        │
│ ❌ Button hidden, success message                        │
└─────────────────┬───────────────────────────────────────┘
                  │
          (service completed)
                  ↓
┌─────────────────────────────────────────────────────────┐
│ completed                                               │
│ "Service Completed"                                     │
│ ✅ [Leave Review] button                                 │
└─────────────────────────────────────────────────────────┘
```

## Code Structure

### Key Functions

#### `shouldShowPayButton($booking_mode, $booking_status, $payment_status)`
```php
// Returns: boolean
// Determines if [Pay Now] button should be visible

// Request mode: status must be 'accepted' AND payment_status 'pending'
// Instant mode: status must be 'pending' AND payment_status 'pending'
```

#### `getPaymentButtonLabel($booking_mode)`
```php
// Returns: string
// Instant: "Confirm & Pay to Secure"
// Request: "Pay Now"
```

#### `getStatusMessage($booking_mode, $booking_status, $payment_status)`
```php
// Returns: array with:
// - title: Message title
// - message: Detailed message
// - icon: FontAwesome icon class
// - badge_class: CSS class for badge color
```

### JavaScript Functions

#### `processPayment(paymentId, buttonElement)`
```javascript
// AJAX payment processing
// Parameters:
//   - paymentId: Payment record ID
//   - buttonElement: Button that triggered payment
// 
// Process:
// 1. Confirm with user
// 2. Disable button, show loading
// 3. Send AJAX POST to /payments/process_payment.php
// 4. On success: show message, reload page
// 5. On error: show error, re-enable button
```

#### `showSuccessMessage(message)`
```javascript
// Shows green success alert at top of page
// Auto-dismisses after 5 seconds (or manually on page reload)
```

#### `showErrorMessage(message)`
```javascript
// Shows red error alert at top of page
// Auto-dismisses after 5 seconds
```

## Integration Points

### 1. Navigation Link
**File:** `client/my-bookings.php`
```html
<a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn-sm btn-view">
    <i class="fas fa-eye me-1"></i> View Details
</a>
```

### 2. Payment Processing
**File:** `payments/process_payment.php`
- Receives AJAX POST with `{ payment_id: int }`
- Returns JSON: `{ success: bool, message: string, ... }`

### 3. Payment Manager
**File:** `payments/PaymentManager.php`
```php
$payment_manager = new PaymentManager();
$payment = $payment_manager->getPaymentForBooking($booking_id);
// Returns payment record or null
```

## Testing Guide

### Test Case 1: Request Approval Flow
1. Create a booking with a service (booking_mode = 'request_approval')
2. Visit `booking-details.php?id=BOOKING_ID`
3. ✅ Verify: Message shows "Waiting for provider approval", no pay button
4. Use admin to accept the booking
5. ✅ Verify: Message shows "Provider Accepted", [Pay Now] button visible
6. Click [Pay Now]
7. ✅ Verify: Payment processes, button hidden, success message shown
8. ✅ Verify: Booking status changes to 'confirmed'

### Test Case 2: Instant Booking Flow
1. Create a booking with instant service (booking_mode = 'instant')
2. Visit `booking-details.php?id=BOOKING_ID`
3. ✅ Verify: Message shows "Ready to Pay", [Confirm & Pay] button visible
4. Click [Confirm & Pay]
5. ✅ Verify: Payment processes, button hidden, success message shown
6. ✅ Verify: Booking status is immediately 'confirmed'

### Test Case 3: Security
1. Create booking as User A
2. Try accessing as User B: `booking-details.php?id=BOOKING_ID`
3. ✅ Verify: Shows error "Booking not found or access denied"

### Test Case 4: Double Payment Prevention
1. Process payment successfully
2. Try clicking [Pay Now] again
3. ✅ Verify: Button is hidden/disabled, can't process again

## Styling & Responsive Design

### Mobile-Friendly
- ✅ Responsive grid layout (1 column on mobile, 2 on desktop)
- ✅ Touch-friendly buttons (minimum 44px height)
- ✅ Readable font sizes across all devices
- ✅ Optimized spacing for small screens

### Color Scheme
- **Primary**: #2563eb (Blue)
- **Success**: #10b981 (Green)
- **Warning**: #f59e0b (Orange)
- **Danger**: #ef4444 (Red)
- **Info**: #3b82f6 (Light Blue)

### Accessibility
- ✅ Semantic HTML structure
- ✅ ARIA labels where needed
- ✅ Sufficient color contrast
- ✅ Keyboard navigation support

## Error Handling

### Handled Exceptions
1. **Invalid Booking ID**: Shows empty state with error message
2. **Booking Not Found**: Shows access denied message
3. **Wrong User**: Shows access denied message
4. **Payment Processing Error**: Shows error alert with retry option
5. **Network Error**: Shows error message with retry option
6. **Missing Payment Record**: Shows warning inside action area

## Future Enhancements

1. **Email Notifications**: Send confirmation emails on payment
2. **Refund Processing**: Allow refunds for cancelled bookings
3. **Payment History**: Show transaction details and history
4. **Partial Payments**: Support payment plans
5. **Receipt Download**: Generate and download payment receipts
6. **Customer Support**: Show support contact info based on booking status

## Code Examples

### Access a Booking Detail Page
```url
http://localhost/Bii_localFinder/client/booking-details.php?id=5
```

### Process Payment via AJAX
```javascript
const response = await fetch('payments/process_payment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_id: 1 })
});
const result = await response.json();
if (result.success) {
    console.log('Payment successful!');
    location.reload();
}
```

### Check Payment Status
```php
$payment_manager = new PaymentManager();
$payment = $payment_manager->getPaymentForBooking($booking_id);

if ($payment) {
    echo $payment['status']; // 'pending', 'success', 'failed', 'refunded'
}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Booking not found" error | Verify booking ID in URL, ensure you're the booking owner |
| Payment button not showing | Check booking and payment status in database |
| AJAX failing silently | Check browser console for errors, verify endpoint path |
| Double payment allowed | Clear browser cache and reload |
| Wrong status showing | Check database for inconsistent records |

## Files Modified/Created

### Created
- ✅ `client/booking-details.php` - New booking details page

### Modified
- ✅ `client/my-bookings.php` - Added "View Details" link to bookings
- ✅ `payments/PaymentManager.php` - Already has necessary methods
- ✅ `payments/process_payment.php` - Already handles payment processing

## Database Requirements

These tables and fields must exist:
- ✅ `bookings` table with all fields
- ✅ `payments` table (created by migration_payments.php)
- ✅ `provider_services` table with `booking_mode` field
- ✅ `service_providers` table
- ✅ `users` table with profile info
- ✅ `categories` table
