# Service Negotiation System - Implementation Checklist

## Step 1: Database Setup ✓

- [ ] Run the migration file: `config/migrate_negotiation_system.sql`
  ```bash
  mysql -u root -p bii_localfinder < config/migrate_negotiation_system.sql
  ```

- [ ] Verify tables created:
  - [ ] `service_offers` table exists
  - [ ] `service_counteroffers` table exists
  - [ ] `negotiation_history` table exists
  - [ ] `finalized_service_prices` table exists
  - [ ] `provider_services` modified with negotiation columns

## Step 2: Backend Files ✓

- [ ] Place files in correct locations:
  - [ ] `includes/service_negotiation.php` - Main negotiation class
  - [ ] `api/service_offers.php` - API endpoints
  - [ ] `provider/services.php` - Updated with negotiation support
  - [ ] Update `config/database.php` if needed (for connection pooling)

- [ ] Verify file permissions (readable/executable)

## Step 3: Frontend Setup ✓

- [ ] Add CSS file:
  - [ ] `assets/css/service_negotiation.css`

- [ ] Add JavaScript file:
  - [ ] `assets/js/service_negotiation.js`

- [ ] Test CSS loads: Check browser dev tools, no 404 errors

## Step 4: Provider Portal Integration

### In `provider/services.php`:

- [ ] Update form to include negotiation fields:
  ```html
  <!-- Add to form -->
  <input type="checkbox" name="negotiable" id="negotiableCheckbox">
  <input type="number" name="min_price" id="minPrice">
  <input type="number" name="max_price" id="maxPrice">
  ```

- [ ] Include CSS and JS in page:
  ```html
  <link rel="stylesheet" href="../assets/css/service_negotiation.css">
  <script src="../assets/js/service_negotiation.js"></script>
  ```

- [ ] Test adding a service with negotiation enabled

### In `provider/bookings.php` or dashboard:

- [ ] Display pending offers for provider review
  ```php
  require_once '../includes/service_negotiation.php';
  $negotiation = new ServiceNegotiation($db);
  ```

- [ ] Add offer management buttons (Accept, Reject, Counter-Offer)

- [ ] Test responding to offers

## Step 5: Client Portal Integration

### In `client/provider-profile.php`:

- [ ] Add offer creation form for services
  ```html
  <div id="negotiationAlertContainer"></div>
  <div id="negotiationStatusContainer"></div>
  ```

- [ ] Include CSS and JS:
  ```html
  <link rel="stylesheet" href="../assets/css/service_negotiation.css">
  <script src="../assets/js/service_negotiation.js"></script>
  ```

- [ ] Initialize negotiation UI:
  ```html
  <div data-booking-id="<?php echo $booking['id']; ?>"></div>
  ```

### In `client/my-bookings.php`:

- [ ] Display negotiation status for active bookings
- [ ] Show offer history timeline
- [ ] Display finalized prices

## Step 6: Testing Checklist

### Test as Provider:

- [ ] [ ] Create service without negotiation - works normally
- [ ] [ ] Create service WITH negotiation:
  - [ ] Set min price lower than base
  - [ ] Set max price higher than base
  - [ ] Verify min < max validation
- [ ] [ ] Receive client offer
- [ ] [ ] Accept offer - price locks
- [ ] [ ] Receive another offer, reject it
- [ ] [ ] Send counter-offer
- [ ] [ ] Counter-offer expires after 30 minutes (test with modified timestamp)
- [ ] [ ] View negotiation history

### Test as Client:

- [ ] [ ] See fixed-price service (no offer option)
- [ ] [ ] See negotiable service with price range
- [ ] [ ] Create offer within price range
- [ ] [ ] See status "Waiting for response"
- [ ] [ ] Receive counter-offer from provider
- [ ] [ ] Accept counter-offer - price locks
- [ ] [ ] Reject counter-offer - can create new offer (round 2)
- [ ] [ ] Complete 3 rounds and verify can't create more offers
- [ ] [ ] View full negotiation history timeline

### Database Verification:

- [ ] [ ] Check `service_offers` table has data after creating offer
- [ ] [ ] Check `service_counteroffers` has data after counter-offer
- [ ] [ ] Check `negotiation_history` logs all actions
- [ ] [ ] Check `finalized_service_prices` locked price after agreement
- [ ] [ ] Verify auto-expiry sets status to 'expired' after 30 minutes

### API Testing:

- [ ] [ ] Test each endpoint with Postman/Thunder Client:
  - [ ] `create_offer` - success/error cases
  - [ ] `accept_offer` - success/error cases
  - [ ] `counteroffer` - success/error cases
  - [ ] `accept_counteroffer` - success/error cases
  - [ ] `reject_counteroffer` - success/error cases
  - [ ] `get_status` - returns correct status
  - [ ] `get_history` - returns full timeline
  - [ ] `get_finalized_price` - returns locked price

## Step 7: Security Hardening

- [ ] [ ] Verify only logged-in users can create offers
- [ ] [ ] Verify clients can't see/modify other clients' offers
- [ ] [ ] Verify providers can't see other providers' offers
- [ ] [ ] Verify price validation prevents invalid amounts
- [ ] [ ] Test round limit enforcement
- [ ] [ ] Test expired offer can't be accepted
- [ ] [ ] Test all endpoints with invalid user IDs

## Step 8: Performance Testing

- [ ] [ ] Test with 100+ offers in database - no slow queries
- [ ] [ ] Monitor auto-expire query performance
- [ ] [ ] Check database indexes are being used
- [ ] [ ] Monitor API response times (should be <200ms)
- [ ] [ ] Test concurrent offer submissions

## Step 9: User Experience

- [ ] [ ] Alert messages display clearly
- [ ] [ ] Countdown timers update in real-time
- [ ] [ ] Forms validate before submission
- [ ] [ ] Mobile responsive design works
- [ ] [ ] Modal dialogs open/close smoothly
- [ ] [ ] History timeline displays correctly

## Step 10: Documentation

- [ ] [ ] Update main README with negotiation feature
- [ ] [ ] Document API endpoints for developers
- [ ] [ ] Add provider FAQ about price negotiation
- [ ] [ ] Add client FAQ about making offers
- [ ] [ ] Create tutorial screenshots/GIFs

## Step 11: Deployment

- [ ] [ ] Deploy migration to production database
- [ ] [ ] Deploy PHP files to production
- [ ] [ ] Deploy CSS/JS to production
- [ ] [ ] Test in staging environment thoroughly
- [ ] [ ] Test in production with small batch
- [ ] [ ] Monitor error logs for issues
- [ ] [ ] Set up monitoring/alerts for failed API calls

## Step 12: Post-Launch

- [ ] [ ] Monitor negotiation success rates
- [ ] [ ] Track average rounds per negotiation
- [ ] [ ] Collect user feedback
- [ ] [ ] Monitor for expired offer issues
- [ ] [ ] Watch for price validation exploits
- [ ] [ ] Plan future enhancements

## Troubleshooting Commands

### Check for errors:
```sql
SELECT * FROM service_offers WHERE status = 'pending' AND expires_at < NOW();
SELECT * FROM negotiation_history WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
SELECT COUNT(*) FROM finalized_service_prices WHERE status = 'active';
```

### Reset test data:
```sql
DELETE FROM finalized_service_prices;
DELETE FROM negotiation_history;
DELETE FROM service_counteroffers;
DELETE FROM service_offers;
```

### Check service negotiation settings:
```sql
SELECT id, name, negotiable, min_price, max_price, price FROM provider_services WHERE negotiable = 1;
```

## Quick Start Script

If you need to quickly verify everything is working:

```bash
# 1. Run database migration
mysql -u root -p bii_localfinder < config/migrate_negotiation_system.sql

# 2. Test API endpoint
curl -X POST http://localhost/Bii_localFinder/api/service_offers.php \
  -d "action=get_status&booking_id=1"

# 3. Check logs for errors
tail -f /var/log/php_errors.log
```

## Support Contact

For implementation issues:
1. Check NEGOTIATION_SYSTEM_GUIDE.md
2. Review NEGOTIATION_INTEGRATION_EXAMPLES.php
3. Check database error logs
4. Enable PHP error logging
5. Review API response JSON

---

**Status**: Ready for Implementation ✓
**Last Updated**: December 26, 2025
**Version**: 1.0
