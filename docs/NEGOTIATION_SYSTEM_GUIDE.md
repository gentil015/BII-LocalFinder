# Service Offer & Counter-Offer Negotiation System

## Overview

This system enables a structured price negotiation between clients and service providers with the following features:

### Key Features

1. **Offer-Counteroffer Negotiation**: Clients create offers, providers send counter-offers
2. **Limited Rounds**: Maximum 3 negotiation rounds to prevent endless discussions
3. **Time-Limited Offers**: Each offer/counter-offer expires in 30 minutes automatically
4. **Auto-Cancel**: Expired offers are automatically marked as expired
5. **Price Locking**: Once agreed, the price is finalized and locked
6. **Negotiable Services**: Providers can set min/max price ranges for negotiable services
7. **Complete Audit Trail**: Full negotiation history is tracked

## Database Schema

### Tables Added

#### 1. `provider_services` (Modified)
New columns added:
- `negotiable` (TINYINT): Whether this service supports price negotiation
- `min_price` (DECIMAL): Minimum negotiable price
- `max_price` (DECIMAL): Maximum negotiable price
- `base_price` (DECIMAL): Reference price for negotiations

#### 2. `service_offers`
Main offers table created by clients
```sql
- id: Unique offer ID
- booking_id: References booking
- service_id: Service being offered for
- client_id: Client making the offer
- provider_id: Provider receiving the offer
- offered_price: Price proposed by client
- status: pending, accepted, rejected, expired, withdrawn
- round_number: Which negotiation round (1-3)
- expires_at: When offer expires (NOW + 30 minutes)
- responded_at: When provider responded
- response_notes: Provider's feedback
- created_at, updated_at: Timestamps
```

#### 3. `service_counteroffers`
Provider counter-offers
```sql
- id: Unique counter-offer ID
- offer_id: References parent offer
- service_id: Service being offered
- provider_id: Provider making counter-offer
- client_id: Client receiving counter-offer
- proposed_price: Price proposed by provider
- status: pending, accepted, rejected, expired
- round_number: Negotiation round
- expires_at: When counter-offer expires
- responded_at: When client responded
- response_notes: Client's feedback
- created_at, updated_at: Timestamps
```

#### 4. `negotiation_history`
Complete audit trail
```sql
- id: Entry ID
- booking_id: Associated booking
- offer_id: Associated offer (if applicable)
- counteroffer_id: Associated counter-offer (if applicable)
- action_type: offer_created, offer_accepted, offer_rejected, etc.
- price_offered: Price at this step
- actor_id: User performing action
- actor_type: client or provider
- notes: Additional notes
- created_at: When action happened
```

#### 5. `finalized_service_prices`
Final agreed prices
```sql
- id: Entry ID
- booking_id: Associated booking (UNIQUE)
- service_id: Service being provided
- client_id: Client
- provider_id: Provider
- finalized_price: Final agreed price
- negotiation_rounds: How many rounds it took
- client_final_offer_id: Client's final offer ID
- provider_final_counteroffer_id: Provider's final counter-offer ID
- status: active, completed, cancelled
- created_at, updated_at: Timestamps
```

## Migration

Run the migration to create all tables:

```bash
mysql -u root -p bii_localfinder < config/migrate_negotiation_system.sql
```

Or manually execute the SQL in your database management tool.

## API Endpoints

### Base URL
`/api/service_offers.php`

### Endpoints

#### 1. Create Offer (Client)
```php
POST /api/service_offers.php
Action: create_offer
Parameters:
  - booking_id: int (required)
  - service_id: int (required)
  - offered_price: float (required)
Response:
  {
    "success": true,
    "message": "Offer created successfully",
    "offer_id": 123
  }
```

#### 2. Accept Offer (Provider)
```php
POST /api/service_offers.php
Action: accept_offer
Parameters:
  - offer_id: int (required)
Response:
  {
    "success": true,
    "message": "Offer accepted successfully"
  }
```

#### 3. Reject Offer (Provider)
```php
POST /api/service_offers.php
Action: reject_offer
Parameters:
  - offer_id: int (required)
  - notes: string (optional)
Response:
  {
    "success": true,
    "message": "Offer rejected"
  }
```

#### 4. Send Counter-Offer (Provider)
```php
POST /api/service_offers.php
Action: counteroffer
Parameters:
  - offer_id: int (required)
  - service_id: int (required)
  - proposed_price: float (required)
  - notes: string (optional)
Response:
  {
    "success": true,
    "message": "Counter-offer sent",
    "counteroffer_id": 456
  }
```

#### 5. Accept Counter-Offer (Client)
```php
POST /api/service_offers.php
Action: accept_counteroffer
Parameters:
  - counteroffer_id: int (required)
Response:
  {
    "success": true,
    "message": "Counter-offer accepted successfully"
  }
```

#### 6. Reject Counter-Offer (Client)
```php
POST /api/service_offers.php
Action: reject_counteroffer
Parameters:
  - counteroffer_id: int (required)
  - notes: string (optional)
Response:
  {
    "success": true,
    "message": "Counter-offer rejected"
  }
```

#### 7. Get Negotiation Status
```php
POST /api/service_offers.php
Action: get_status
Parameters:
  - booking_id: int (required)
Response:
  {
    "success": true,
    "status": {
      "status": "offer_pending|counter_pending|finalized|no_offer",
      "message": "Description",
      "time_remaining_minutes": 25,
      "offered_price": 5000,
      "finalized_price": 5000,
      "rounds": 2
    }
  }
```

#### 8. Get Negotiation History
```php
POST /api/service_offers.php
Action: get_history
Parameters:
  - booking_id: int (required)
Response:
  {
    "success": true,
    "history": [
      {
        "id": 1,
        "action_type": "offer_created",
        "actor_type": "client",
        "price_offered": 5000,
        "notes": "Initial offer",
        "created_at": "2025-12-26 10:00:00"
      }
    ]
  }
```

#### 9. Get Finalized Price
```php
POST /api/service_offers.php
Action: get_finalized_price
Parameters:
  - booking_id: int (required)
Response:
  {
    "success": true,
    "finalized_price": {
      "id": 789,
      "finalized_price": 5000,
      "negotiation_rounds": 2,
      "status": "active"
    }
  }
```

## Usage Examples

### Client Side - Create Offer

```javascript
const negotiationUI = new ServiceNegotiationUI(bookingId);

// Create offer
const formData = new FormData();
formData.append('action', 'create_offer');
formData.append('booking_id', bookingId);
formData.append('service_id', serviceId);
formData.append('offered_price', 4500);

const response = await fetch('../api/service_offers.php', {
    method: 'POST',
    body: formData
});
const result = await response.json();
```

### Provider Side - Create Counter-Offer

```javascript
const formData = new FormData();
formData.append('action', 'counteroffer');
formData.append('offer_id', offerId);
formData.append('service_id', serviceId);
formData.append('proposed_price', 5500);
formData.append('notes', 'Higher price due to rush delivery');

const response = await fetch('../api/service_offers.php', {
    method: 'POST',
    body: formData
});
const result = await response.json();
```

### Setup in HTML

```html
<!-- Include CSS -->
<link rel="stylesheet" href="../assets/css/service_negotiation.css">

<!-- Include JavaScript -->
<script src="../assets/js/service_negotiation.js"></script>

<!-- Alert Container -->
<div id="negotiationAlertContainer"></div>

<!-- Status Container -->
<div id="negotiationStatusContainer"></div>

<!-- Initialize with booking ID -->
<div data-booking-id="123"></div>
```

## Service Configuration (Provider Portal)

When adding/editing a service, providers can:

1. **Set Negotiable**: Toggle "Enable Price Negotiation"
2. **Set Price Range**: 
   - Minimum Price: Lowest price client can offer
   - Maximum Price: Highest price provider accepts
   - Base Price: Reference/starting price

Example:
```
Service: House Cleaning
Base Price: RWF 5,000
Minimum Price: RWF 4,000 (client can't offer less)
Maximum Price: RWF 6,500 (client can't offer more)
```

## Workflow

### Standard Negotiation Flow

```
1. Client creates booking and offers price (Round 1)
   └─ Expires in 30 minutes
   
2. Provider receives offer
   ├─ Accept: Price is locked, booking confirmed
   ├─ Reject: Sends counter-offer (Round 1) with 30 min expiry
   └─ No response: Auto-expires after 30 minutes
   
3. If counter-offer sent, client receives it
   ├─ Accept: Price is locked, booking confirmed
   ├─ Reject: Creates new offer (Round 2) with 30 min expiry
   └─ No response: Auto-expires after 30 minutes
   
4. Process repeats for Round 2 (if not finalized)
   
5. Max 3 rounds enforced
   ├─ After Round 3: Can't create more offers/counter-offers
   └─ Either finalize current or abandon negotiation
```

### Timeline Example

```
10:00 AM - Client offers RWF 4,500
          └─ Expires 10:30 AM

10:15 AM - Provider rejects and counter-offers RWF 5,500
          └─ Expires 10:45 AM

10:25 AM - Client accepts counter-offer at RWF 5,500
          └─ Price locked in finalized_service_prices
          └─ Booking amount updated to RWF 5,500
          └─ Negotiation complete (2 rounds)
```

## Auto-Expiry Mechanism

Expired offers are automatically marked as 'expired' through:

1. **Per-Request Check**: 
   - `ServiceNegotiation::autoExpireOffers($db)` runs on each API request
   
2. **Scheduled Task (Optional)**:
   ```php
   // Add to cron job or background task
   require_once 'config/database.php';
   require_once 'includes/service_negotiation.php';
   
   $db = Database::getInstance()->getConnection();
   ServiceNegotiation::autoExpireOffers($db);
   ```

## Security Considerations

1. **User Verification**: All endpoints verify user ownership
   - Clients can only create offers for their own bookings
   - Providers can only respond to offers for their services
   - Clients can only accept counter-offers for their offers

2. **Price Validation**: 
   - Offered price must be within min/max range (if negotiable)
   - Negative prices rejected
   - Zero prices rejected

3. **Round Limits**: 
   - Maximum 3 rounds enforced
   - After 3 rounds, no more offers/counter-offers allowed

4. **State Management**:
   - Can't accept/reject already processed offers
   - Can't modify accepted/rejected offers

## Troubleshooting

### Offers Not Expiring
```php
// Manually trigger expiry
ServiceNegotiation::autoExpireOffers($db);

// Or check:
SELECT * FROM service_offers WHERE expires_at < NOW() AND status = 'pending';
```

### Lost Negotiation History
```php
// View all negotiations for a booking
SELECT * FROM negotiation_history WHERE booking_id = ? ORDER BY created_at DESC;
```

### Verify Finalized Prices
```php
SELECT * FROM finalized_service_prices WHERE booking_id = ?;
```

## Frontend Implementation Notes

### CSS Classes Available
- `.negotiation-section`: Main container
- `.offer-card`: Individual offer display
- `.counteroffer-container`: Counter-offer display
- `.negotiation-timeline`: History timeline
- `.offer-form`: Form styling
- `.negotiation-alert`: Alert messages

### JavaScript Methods
```javascript
// Initialize
const ui = new ServiceNegotiationUI(bookingId);

// Methods available
ui.handleCreateOffer();           // Create new offer
ui.handleAcceptOffer(offerId);    // Accept offer
ui.handleAcceptCounterOffer(id);  // Accept counter-offer
ui.updateNegotiationStatus();     // Refresh status
ui.showAlert(message, type);      // Show notification
```

## Performance Optimization

1. **Index Optimization**: Database indexes on:
   - `service_offers.booking_id`
   - `service_offers.status`
   - `service_offers.expires_at`
   - `service_counteroffers.offer_id`
   - `negotiation_history.booking_id`

2. **Query Optimization**: Use prepared statements (already implemented)

3. **Caching**: Consider caching negotiation status for frequently accessed bookings

## Future Enhancements

1. **AI-Powered Suggestions**: Recommend counter-offer prices
2. **Payment Integration**: Process payment after price agreement
3. **Contract Generation**: Auto-generate service contracts
4. **Email Notifications**: Send emails for offer expiry warnings
5. **Mobile App Integration**: Native mobile negotiation UI
6. **Bulk Service Updates**: Manage negotiation settings for multiple services
7. **Analytics Dashboard**: Track negotiation success rates

## Support

For issues or questions:
1. Check `error_log()` entries in PHP error log
2. Review negotiation_history for action timeline
3. Verify database migration was successful
4. Check user roles and permissions
