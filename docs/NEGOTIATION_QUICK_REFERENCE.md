# Service Negotiation System - Quick Reference Card

## 🚀 Quick Start (5 Minutes)

### 1. Database Setup
```bash
mysql -u root -p bii_localfinder < config/migrate_negotiation_system.sql
```

### 2. Verify Tables Created
```sql
SHOW TABLES LIKE '%offer%';  -- Should see 5 new tables
DESCRIBE provider_services;  -- Should see 4 new columns
```

### 3. Test API
```bash
curl -X POST http://localhost/Bii_localFinder/api/service_offers.php \
  -d "action=get_status&booking_id=1"
```

---

## 📊 Key Constants

```php
OFFER_EXPIRY_MINUTES = 30     // Each offer expires in 30 minutes
MAX_ROUNDS = 3                 // Maximum 3 negotiation rounds
```

---

## 🔌 API Quick Reference

| Action | Endpoint | Parameters |
|--------|----------|------------|
| Create Offer | `create_offer` | `booking_id`, `service_id`, `offered_price` |
| Accept Offer | `accept_offer` | `offer_id` |
| Counter-Offer | `counteroffer` | `offer_id`, `service_id`, `proposed_price`, `notes` |
| Accept Counter | `accept_counteroffer` | `counteroffer_id` |
| Get Status | `get_status` | `booking_id` |
| Get History | `get_history` | `booking_id` |

---

## 💾 Database Tables

### service_offers
```
id, booking_id, service_id, client_id, provider_id, 
offered_price, status, round_number, expires_at, 
responded_at, response_notes
```

### service_counteroffers
```
id, offer_id, service_id, provider_id, client_id, 
proposed_price, status, round_number, expires_at, 
responded_at, response_notes
```

### finalized_service_prices (Most Important)
```
id, booking_id, service_id, client_id, provider_id,
finalized_price, negotiation_rounds, status
```

### negotiation_history (Audit Trail)
```
id, booking_id, offer_id, counteroffer_id, 
action_type, price_offered, actor_id, actor_type, 
notes, created_at
```

---

## 🎯 Status Values

**Offers & Counter-Offers:**
- `pending` - Awaiting response
- `accepted` - Agreement reached
- `rejected` - Declined
- `expired` - 30 minutes passed without response
- `withdrawn` - Withdrawn by creator (offers only)

---

## 📋 HTML Integration

### Minimal Setup
```html
<!-- Add to page head -->
<link rel="stylesheet" href="../assets/css/service_negotiation.css">
<script src="../assets/js/service_negotiation.js"></script>

<!-- Add to page body -->
<div id="negotiationAlertContainer"></div>
<div id="negotiationStatusContainer"></div>

<!-- Set booking ID -->
<div data-booking-id="<?php echo $booking['id']; ?>"></div>
```

### JavaScript Initialization
```javascript
// Auto-initializes on page load
// Or manually:
const ui = new ServiceNegotiationUI(bookingId);
```

---

## 🔒 Security Checks

```php
// All API endpoints verify:
if (!isLoggedIn()) return error;              // User must be logged in
if (!isProvider()) return error;              // For provider endpoints
if (!isClient()) return error;                // For client endpoints

// Price validation
if ($price < $min || $price > $max) return error;

// Ownership verification
if ($offer['provider_id'] != $user_id) return error;

// Round limits
if ($round_count >= 3) return error;
```

---

## 📱 Common Tasks

### Create Offer (JavaScript)
```javascript
const formData = new FormData();
formData.append('action', 'create_offer');
formData.append('booking_id', 123);
formData.append('service_id', 45);
formData.append('offered_price', 4500);

fetch('../api/service_offers.php', {
    method: 'POST',
    body: formData
}).then(r => r.json()).then(data => {
    console.log(data.success ? 'Offer created!' : 'Failed');
});
```

### Get Negotiation Status (PHP)
```php
require_once '../includes/service_negotiation.php';
$negotiation = new ServiceNegotiation($db);
$status = $negotiation->getNegotiationStatus($booking_id);

echo $status['status'];           // pending, counter_pending, finalized
echo $status['offered_price'];    // Current offer amount
```

### Get Finalized Price (PHP)
```php
$final = $negotiation->getFinalizedPrice($booking_id);
if ($final) {
    echo "Final Price: " . $final['finalized_price'];
    echo "Rounds: " . $final['negotiation_rounds'];
}
```

---

## 🧹 Maintenance

### Auto-Expire Old Offers
```php
// This runs automatically on each API request
ServiceNegotiation::autoExpireOffers($db);

// Or run manually:
$connection->exec("
    UPDATE service_offers 
    SET status = 'expired' 
    WHERE expires_at < NOW() AND status = 'pending'
");
```

### Check Active Negotiations
```sql
-- Pending offers
SELECT * FROM service_offers WHERE status = 'pending';

-- Pending counter-offers
SELECT * FROM service_counteroffers WHERE status = 'pending';

-- Expired but not marked
SELECT * FROM service_offers 
WHERE expires_at < NOW() AND status = 'pending';

-- View history for booking
SELECT * FROM negotiation_history 
WHERE booking_id = ? ORDER BY created_at DESC;
```

---

## 🐛 Debugging

### Enable logging
```php
error_log("Debug info: " . json_encode($data));
// Check PHP error log for output
```

### Test endpoint directly
```bash
curl -X POST http://localhost/api/service_offers.php \
  -d "action=get_status&booking_id=1" \
  -v  # Shows request/response headers
```

### Check database state
```sql
-- For booking 123
SELECT * FROM service_offers WHERE booking_id = 123;
SELECT * FROM service_counteroffers WHERE offer_id IN (
    SELECT id FROM service_offers WHERE booking_id = 123
);
SELECT * FROM finalized_service_prices WHERE booking_id = 123;
```

---

## 📊 Useful Queries

### Provider's Pending Offers
```sql
SELECT so.*, ps.name, u.full_name
FROM service_offers so
JOIN provider_services ps ON so.service_id = ps.id
JOIN users u ON so.client_id = u.id
WHERE so.provider_id = ? AND so.status = 'pending'
ORDER BY so.created_at DESC;
```

### Client's Negotiation History
```sql
SELECT nh.*, u.full_name as actor_name
FROM negotiation_history nh
LEFT JOIN users u ON nh.actor_id = u.id
WHERE nh.booking_id = ?
ORDER BY nh.created_at DESC;
```

### Completed Negotiations
```sql
SELECT fsp.*, ps.name as service_name, 
       u.full_name as provider_name
FROM finalized_service_prices fsp
JOIN provider_services ps ON fsp.service_id = ps.id
JOIN users u ON fsp.provider_id = u.id
WHERE fsp.status = 'active'
ORDER BY fsp.created_at DESC;
```

---

## ⚠️ Troubleshooting Quick Guide

| Problem | Solution |
|---------|----------|
| Offers not expiring | Run `ServiceNegotiation::autoExpireOffers($db)` |
| Can't create offer | Check booking exists, is yours, and no active offer |
| Price validation fails | Check min/max range in provider_services |
| Round limit reached | Only 3 rounds allowed, try accepting current offer |
| API returns 401 | Not logged in, check session/authentication |
| API returns 400 | Invalid parameters, check required fields |
| Offer disappears | Check if it's expired (30 min timeout) |

---

## 📞 Helper Functions

```php
require_once '../includes/negotiation_helpers.php';

// Formatting
formatCurrency(5000);                         // "RWF 5,000"
getTimeRemaining($expires_at);               // "25m 30s remaining"
getNegotiationStatusBadge('pending');        // HTML badge

// Validation
validateNegotiationPrice(4500, 4000, 6000); // true/false

// Stats
getProviderNegotiationStats($db, $provider_id);
getClientNegotiationStats($db, $client_id);

// Export
exportNegotiationHistoryCSV($db, $booking_id, 'path/file.csv');
```

---

## 🎨 CSS Classes

```css
.negotiation-section      /* Main container */
.offer-card               /* Individual offer */
.offer-status             /* Status badge */
.offer-price              /* Price display */
.counteroffer-container   /* Counter-offer section */
.negotiation-timeline     /* History timeline */
.timeline-item            /* Each timeline entry */
.negotiation-alert        /* Alert message */
.offer-form               /* Form styling */
```

---

## ⏱️ Workflow Diagram

```
CLIENT                     PROVIDER                  DATABASE
   │                          │                         │
   ├──────────────────────────>│ Create Offer ────────>│
   │ (Round 1)                │                   (30 min)
   │                          │                         │
   │                    ACCEPT/REJECT/COUNTER
   │                          │                         │
   │<──────── Counter-Offer ──│                    Update
   │ (Round 2)                │<──────────────────────│
   │          (30 min)        │                         │
   │                          │                         │
   ├──────────────────────────>│ Accept/Reject/Counter │
   │                          │                         │
   │<──────── Response ───────│                         │
   │                          │                         │
   │                     FINALIZE                       │
   │                          │                         │
   │<───── Agreed Price ──────│<────────────────────>│
   │   (LOCKED)               │          Lock Price   │
   │                          │                         │
```

---

## 📦 Files Checklist

- [x] Database migration: `config/migrate_negotiation_system.sql`
- [x] Backend class: `includes/service_negotiation.php`
- [x] API endpoints: `api/service_offers.php`
- [x] Helper functions: `includes/negotiation_helpers.php`
- [x] CSS styling: `assets/css/service_negotiation.css`
- [x] JavaScript UI: `assets/js/service_negotiation.js`
- [x] Updated services: `provider/services.php`

---

## 🚦 Status Indicators

| Status | Color | Meaning |
|--------|-------|---------|
| pending | Yellow ⚠️ | Awaiting response |
| accepted | Green ✓ | Agreement reached |
| rejected | Red ✗ | Declined |
| expired | Gray ⏱️ | Timeout reached |

---

## 💡 Tips

1. **Always set a booking ID** on pages using negotiation UI
2. **Test with different user roles** (client vs provider)
3. **Monitor error logs** during testing
4. **Use prepared statements** when querying negotiation tables
5. **Set up automated backups** for negotiation history
6. **Cache negotiation status** for frequently-viewed bookings
7. **Email notify** users of offer expirations

---

**Version**: 1.0 | **Status**: Production Ready | **Updated**: Dec 26, 2025
