<?php
/**
 * QUICK REFERENCE GUIDE - Booking Details Page
 * 
 * This file shows common usage patterns and quick examples
 */

/**
 * ============================================================================
 * ACCESSING BOOKING DETAILS
 * ============================================================================
 */

// URL Format:
// http://localhost/Bii_localFinder/client/booking-details.php?id=BOOKING_ID

// Example URLs:
// - Booking #1: /client/booking-details.php?id=1
// - Booking #42: /client/booking-details.php?id=42


/**
 * ============================================================================
 * BOOKING STATUS FLOW - REQUEST APPROVAL MODE
 * ============================================================================
 */

// Step 1: Client creates booking with payment amount
// - booking.status = 'pending'
// - booking.payment_status = 'pending'
// - UI Shows: "Awaiting Your Confirmation - Review and proceed with payment"
// - Pay button: ✅ VISIBLE - [Pay to Confirm Booking]

// Step 2: Client pays
// - booking.payment_status = 'completed' (via AJAX)
// - booking.status = 'confirmed' (auto-updated)
// - UI Shows: "Service Scheduled - Your service is confirmed"
// - Pay button: ❌ HIDDEN


/**
 * ============================================================================
 * BOOKING STATUS FLOW - INSTANT BOOKING MODE
 * ============================================================================
 */

// TWO SCENARIOS FOR INSTANT BOOKINGS:

// SCENARIO A: Fixed-price (non-negotiable)
// Step 1: Client creates booking
// - booking.status = 'confirmed' (immediately set to confirmed)
// - booking.payment_status = 'pending'
// - UI Shows: "Service Scheduled" (no pay button - already confirmed)

// SCENARIO B: Negotiable pricing
// Step 1: Client creates booking with price negotiation
// - booking.status = 'pending'
// - booking.payment_status = 'pending'
// - UI Shows: "Ready to Pay - Confirm and pay to secure this service"
// - Pay button: ✅ VISIBLE - [Confirm & Pay to Secure]

// Step 2: Client pays
// - booking.payment_status = 'completed' (via AJAX)
// - booking.status = 'confirmed' (auto-updated)
// - UI Shows: "Service Scheduled - Your service is confirmed"
// - Pay button: ❌ HIDDEN


/**
 * ============================================================================
 * PAYMENT BUTTON VISIBILITY RULES
 * ============================================================================
 */

// Both REQUEST APPROVAL and INSTANT modes use the same logic:
// Show pay button IF:
//   booking.status === 'pending' AND
//   booking.payment_status === 'pending' AND
//   payment_manager.isPaymentsEnabled() === true

// IMPORTANT:
// - Instant bookings (non-negotiable fixed-price) are created with status='confirmed'
//   → Pay button will NOT show for those (already confirmed)
// - Instant bookings with negotiable prices are created with status='pending'
//   → Pay button WILL show (waiting for client confirmation payment)
// - Request approval bookings are always created with status='pending'
//   → Pay button WILL show (waiting for client to proceed with payment)


/**
 * ============================================================================
 * HOW PAYMENT PROCESSING WORKS
 * ============================================================================
 */

// 1. User clicks "Pay Now" or "Confirm & Pay"
// 2. JavaScript confirms action: 
//    → "Are you sure you want to proceed with the payment?"

// 3. Button disabled, shows loading state:
//    → "⟳ Processing Payment..."

// 4. AJAX POST sent to payment processor:
//    fetch('/payments/process_payment.php', {
//        method: 'POST',
//        body: JSON.stringify({ payment_id: 123 })
//    })

// 5A. IF PAYMENT SUCCEEDS:
//     - Success alert shown: "✓ Payment successful!"
//     - Page reloads after 2 seconds
//     - User sees updated status

// 5B. IF PAYMENT FAILS:
//     - Error alert shown: "✗ Payment failed: [reason]"
//     - Button re-enabled for retry
//     - User can investigate or try again


/**
 * ============================================================================
 * INFORMATION DISPLAYED
 * ============================================================================
 */

// SERVICE INFORMATION
// - Service name
// - Service description
// - Category
// - Service full description (in box)

// PROVIDER INFORMATION
// - Provider name
// - Profession/specialization
// - Avatar/profile image
// - Rating (if available)
// - Number of reviews

// BOOKING INFORMATION
// - Reference ID (BK-2026-00005)
// - Preferred date
// - Preferred time
// - Location
// - Amount / Price
// - Booking created date

// PAYMENT INFORMATION
// - Total amount in large font
// - Payment status badge
// - Status color-coded


/**
 * ============================================================================
 * SECURITY FEATURES
 * ============================================================================
 */

// 1. OWNERSHIP VALIDATION
//    User can only view their own bookings
//    Error: "Booking not found or access denied"

// 2. DOUBLE PAYMENT PREVENTION
//    Once payment_status = 'completed', button is hidden
//    Cannot re-process same payment

// 3. STATE VALIDATION
//    Payment button only shows in correct states
//    Prevents payments in invalid booking states

// 4. AJAX CSRF PROTECTION
//    Session validation in process_payment.php
//    User must be logged in


/**
 * ============================================================================
 * ERROR MESSAGES & SOLUTIONS
 * ============================================================================
 */

// ERROR: "Booking not found or access denied"
// CAUSE: Invalid ID or accessing another user's booking
// SOLUTION: Check booking ID in URL, ensure you created this booking

// ERROR: "No payment record found"
// CAUSE: Payment button should show but no payment record exists (creation failed during booking)
// SOLUTION: System now automatically tries to create missing payment record
//           If creation fails, shows "Unable to process payment" message

// ERROR: "Payment already processed"
// CAUSE: Payment was already successfully completed
// SOLUTION: This is correct - page should hide the pay button now

// ERROR: "Payment failed: (reason)"
// CAUSE: FakeGateway returned failure or actual payment issue
// SOLUTION: Try the payment again; contact support if continues

// ERROR: "An error occurred while processing payment"
// CAUSE: Network issue, server error, or session expired
// SOLUTION: Check console for errors, refresh page, try again

// STATUS SHOWING: "Pending / Status unknown"
// CAUSE: Booking has an unexpected status combination
// SOLUTION: This shouldn't happen - check database for inconsistent records


/**
 * ============================================================================
 * DATABASE QUERIES USED
 * ============================================================================
 */

// Main query gets:
// - Booking details (all fields)
// - Provider user information
// - Service provider profile (rating, reviews, verification)
// - Service details including booking_mode
// - Category information

// Payment query gets:
// - Payment record for this booking
// - Status, amount, transaction_id, metadata

// JOIN structure:
// bookings 
//   ← JOIN users (provider)
//   ← JOIN service_providers (provider profile)
//   ← JOIN provider_services (service & booking_mode)
//   ← JOIN categories (category info)


/**
 * ============================================================================
 * CSS CLASSES & STYLING
 * ============================================================================
 */

// Status Badges
// - .badge-warning (pending/request sent)
// - .badge-info (accepted/waiting for payment)
// - .badge-success (confirmed/completed)
// - .badge-danger (cancelled)

// Sections
// .section - Main content block
// .section-title - Section header
// .info-grid - 2-column layout for info items
// .provider-card - Provider profile card
// .service-description - Highlighted service description
// .amount-section - Prominent price display


/**
 * ============================================================================
 * RESPONSIVE BREAKPOINTS
 * ============================================================================
 */

// Mobile (< 640px)
// - Single column layout
// - Smaller fonts
// - Full-width buttons

// Tablet (640px - 1024px)
// - 2 column grid
// - Medium fonts
// - Flexible buttons

// Desktop (> 1024px)
// - 2 column grid
// - Larger fonts
// - Optimized spacing


/**
 * ============================================================================
 * FILES INVOLVED
 * ============================================================================
 */

// MAIN PAGE
// client/booking-details.php
// - 600+ lines of PHP/HTML/CSS/JS
// - Handles all booking display and payment logic

// INTEGRATION POINTS
// client/my-bookings.php
// - Added "View Details" link to bookings
// - Links to booking-details.php?id=ID

// BACKEND PROCESSING
// payments/process_payment.php
// - Receives AJAX requests
// - Returns JSON response

// BUSINESS LOGIC
// payments/PaymentManager.php
// - getPaymentForBooking(booking_id)
// - isPaymentsEnabled()
// - getPaymentStats()

// DATABASE
// database.php (connection)
// bii_localfinder.sql (schema)


/**
 * ============================================================================
 * TESTING CHECKLIST
 * ============================================================================
 */

// REQUEST APPROVAL MODE
// [ ] Booking shows with pending status
// [ ] No pay button visible
// [ ] Provider accepts booking
// [ ] Pay button appears
// [ ] Click pay button works
// [ ] Payment processes successfully
// [ ] Status updates to confirmed
// [ ] Can see review button after completion

// INSTANT BOOKING MODE
// [ ] Booking shows with ready to pay message
// [ ] Pay button visible immediately
// [ ] Button text says "Confirm & Pay to Secure"
// [ ] Click pay button works
// [ ] Payment processes successfully
// [ ] Status immediately becomes confirmed
// [ ] No approval step needed

// SECURITY
// [ ] Other users cannot access this booking
// [ ] Payment button hidden after successful payment
// [ ] Cannot double-pay same booking
// [ ] Session validation works

// ERROR HANDLING
// [ ] Invalid booking ID shows error
// [ ] Network error handled gracefully
// [ ] Wrong page ID shows 404-style error

?>