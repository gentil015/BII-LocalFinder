# Price Negotiation System - Visual Overview

## Complete Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PROVIDER SIDE                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Add/Edit Service in provider/services.php:                                │
│  ┌──────────────────────────────────────────┐                             │
│  │ Service Name: House Cleaning             │                             │
│  │ Category: Cleaning                       │                             │
│  │ Base Price: RWF 5,000                    │                             │
│  │ Duration: 120 minutes                    │                             │
│  │ ☑ Negotiable (checkbox)                  │                             │
│  │ Min Price: RWF 4,000 ◄──────────────────┐│                             │
│  │ Max Price: RWF 6,000 ◄──────────────────┘│  Provider sets              │
│  └──────────────────────────────────────────┘  acceptable range           │
│           ↓ SAVE SERVICE ↓                                                │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                      CLIENT SIDE (provider-profile.php)                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Service Display:                                                          │
│  ┌──────────────────────────────────────────┐                             │
│  │ 🏠 House Cleaning          🤝 Negotiable │  ◄── Badge shows it's       │
│  │ Cleaning | Professional   │               │      negotiable             │
│  │ ├─────────────────────────┤               │                             │
│  │ │ RWF 4,000 - RWF 6,000  │  ◄──────────┐ │  Price range                │
│  │ │ 💡 Negotiable range     │             │ │  clearly shown             │
│  │ │ ⏱ 120 mins              │             │ │                             │
│  │ ├─────────────────────────┤             │ │                             │
│  │ │ 🤝 Send Offer           │  ◄──────────┼──  Button for offering       │
│  │ │                         │             │ │  (negotiable only)         │
│  │ └──────────────────────────┘             │ │                             │
│  │                          Fixed Service: │ │                             │
│  │ ┌──────────────────────────┐             │ │                             │
│  │ │ 🖥️  Web Design         │             │ │                             │
│  │ │ Web Services | Design   │             │ │                             │
│  │ │ ├─────────────────────────┤          │ │                             │
│  │ │ │ RWF 150,000            │  ◄────────┘ │  Fixed price               │
│  │ │ │ ⏱ 480 mins              │             │  (no negotiation)           │
│  │ │ ├─────────────────────────┤             │                             │
│  │ │ │ ✓ Select This Service   │             │  Standard button            │
│  │ │ └──────────────────────────┘             │                             │
│  └──────────────────────────────────────────┘                             │
│           ↓ CLICK: Send Offer ↓                                            │
│                                                                             │
│  Offer Modal Appears:                                                     │
│  ┌──────────────────────────────────────────┐                             │
│  │ 🤝 Send Price Offer                [X]  │                             │
│  ├──────────────────────────────────────────┤                             │
│  │ Service: House Cleaning                  │                             │
│  │ Price Range: RWF 4,000 - RWF 6,000      │  ◄─ Validation range        │
│  ├──────────────────────────────────────────┤                             │
│  │ Your Offer Price *                       │                             │
│  │ [RWF] [  5000  ]◄──────────────────────┐│ │  Client enters offer       │
│  │ ✓ Valid (within range)                │ │  │  (validated in real time) │
│  ├──────────────────────────────────────────┤                             │
│  │ Additional Message:                      │                             │
│  │ [Need by next Friday, bulk discount?] │  │  Optional context           │
│  │                                          │                             │
│  │ ℹ️ How it works:                        │                             │
│  │ • Provider reviews in 24 hours          │                             │
│  │ • Can accept, reject, or counter       │                             │
│  │ • Offers expire in 30 minutes          │                             │
│  │ • Max 3 offers before finalizing       │                             │
│  └──────────────────────────────────────────┘                             │
│         ↓ CLICK: Send Offer ↓                                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                    ↓
                        ✅ OFFER SENT (Expires 30 min)
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                      PROVIDER RECEIVES OFFER                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Provider sees pending offer in dashboard:                                │
│  ┌──────────────────────────────────────────┐                             │
│  │ ⏳ Pending Offer                          │                             │
│  │ Service: House Cleaning                  │                             │
│  │ Client Offer: RWF 5,000                  │                             │
│  │ Your Range: RWF 4,000-6,000              │                             │
│  │ Message: "Need by Friday, bulk offer?"   │                             │
│  │ Expires: 27 mins                         │                             │
│  ├──────────────────────────────────────────┤                             │
│  │ ✅ Accept  ❌ Reject  🔄 Counter-Offer   │                             │
│  └──────────────────────────────────────────┘                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
             ↙                      ↓                      ↘
    ┌──────────────┐        ┌──────────────┐        ┌──────────────┐
    │ ACCEPT       │        │ REJECT       │        │ COUNTER      │
    │ Price Locked │        │ Send New     │        │ Send New     │
    │ at RWF 5,000 │        │ Offer / New  │        │ Price / New  │
    │ ✅ COMPLETE  │        │ Message      │        │ Message      │
    │              │        │              │        │              │
    │ SERVICE      │        │ CLIENT GETS  │        │ ROUND 2:     │
    │ BOOKED       │        │ ALERT:       │        │ WAITING...   │
    │              │        │ OFFER        │        │              │
    │ PAYMENT      │        │ REJECTED     │        │ ⏳ 30 mins    │
    │ PROCESSING   │        │              │        │              │
    └──────────────┘        └──────────────┘        └──────────────┘
                                                            ↓
                                    ┌───────────────────────┴─────────────────┐
                                    │                                         │
                            ┌──────────────┐                         ┌──────────────┐
                            │ ACCEPT       │                         │ REJECT       │
                            │ COUNTER      │                         │ NEW OFFER    │
                            │ Price Locked │                         │ REPEAT...    │
                            │ at RWF 5,200 │                         │              │
                            │ ✅ COMPLETE  │                         │ Max 3 rounds │
                            │              │                         │              │
                            │ SERVICE      │                         │ Round 3:     │
                            │ BOOKED       │                         │ Last chance  │
                            └──────────────┘                         └──────────────┘
```

---

## Status Flow Timeline

```
CLIENT SIDE                    SYSTEM                      PROVIDER SIDE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Time: 0:00
Client clicks          ───────→ Offer Created    ───────→ Offer Pending
"Send Offer"              (RWF 5,000)            Shows in Dashboard
Price: RWF 5,000      ←───────  ⏳ Expires: 30m   ←───────

                      ROUND 1 (0:00 - 0:30)

Time: 0:05                                              Provider Reviews
Client sees          ←───────  Offer Received  ←───────
"Offer Sent"              Status Update              Status: PENDING

Time: 0:20                                              Provider Decides
                      ←───────  Counter-Offer ←───────
                         (RWF 5,500)          Suggests: RWF 5,500
                                              Message: "Perfect"

                      ROUND 2 (0:20 - 0:50)

Time: 0:22
Client sees          ←───────  Counter Alert  ←───────
"Counter-Offer"           Provider wants           Status: COUNTER
Gets Alert                RWF 5,500

Time: 0:25
Client accepts       ───────→ Price Locked   ───────→ ✅ ACCEPTED
Counter            (RWF 5,500 FINAL)             Service Booked
                                              Status: COMPLETED

                      ✅ NEGOTIATION COMPLETE ✅

Final Price: RWF 5,500 (Client offered 5,000, Provider countered 5,500, Client agreed)
Rounds Used: 2 out of 3
Time Used: 25 minutes out of 30 per offer
Status: PRICE LOCKED - READY FOR PAYMENT
```

---

## Price Range Examples

### Example 1: House Cleaning
```
Provider's Setup:
┌─────────────────────┐
│ Min: RWF 4,000      │ ← Lowest acceptable
│ Base: RWF 5,000     │ ← Reference price
│ Max: RWF 6,000      │ ← Highest acceptable
└─────────────────────┘

Valid Client Offers:
✅ RWF 4,000    (minimum)
✅ RWF 4,500    (within range)
✅ RWF 5,000    (base)
✅ RWF 5,500    (within range)
✅ RWF 6,000    (maximum)

Invalid Client Offers:
❌ RWF 3,500    (below minimum)
❌ RWF 6,500    (above maximum)
❌ RWF 10,000   (way too high)
```

### Example 2: Website Design
```
Provider's Setup:
┌──────────────────────┐
│ Min: RWF 100,000     │
│ Base: RWF 150,000    │ ← No negotiation
│ Max: RWF 150,000     │   (Fixed price)
└──────────────────────┘

Display: RWF 150,000 (Fixed)
Button: "Select This Service" (No offer option)
```

---

## Database Relationships

```
provider_services
├── id: 1
├── provider_id: 5
├── name: "House Cleaning"
├── price: 5000              ← Reference price
├── negotiable: 1            ← ✅ Can negotiate
├── min_price: 4000          ← Min client can offer
├── max_price: 6000          ← Max client can offer
└── base_price: 5000         ← Reference copy

           ↓ WHEN CLIENT MAKES OFFER ↓

service_offers
├── id: 1
├── service_id: 1
├── booking_id: NULL         ← For future booking
├── client_id: 10
├── provider_id: 5
├── offered_price: 5000      ← What client offered
├── status: "pending"        ← Current status
├── expires_at: "2024-12-26 23:35"
├── round: 1                 ← Round number
└── created_at: "2024-12-26 23:05"

           ↓ IF PROVIDER COUNTERS ↓

service_counteroffers
├── id: 1
├── offer_id: 1
├── service_id: 1
├── provider_id: 5
├── proposed_price: 5500     ← Provider's counter
├── status: "pending"        ← Waiting for client
├── expires_at: "2024-12-26 23:50"
├── round: 2                 ← Round number
└── notes: "Perfect price!"

           ↓ IF CLIENT ACCEPTS ↓

finalized_service_prices
├── id: 1
├── booking_id: 50
├── service_id: 1
├── provider_id: 5
├── client_id: 10
├── negotiated_price: 5500   ← LOCKED FINAL PRICE
├── rounds_used: 2           ← How many rounds
├── status: "active"         ← Price locked
└── created_at: "2024-12-26 23:25"
```

---

## User Experience Summary

### Provider Perspective
```
✅ Set min/max prices when creating service
✅ Service shows "Negotiable" badge
✅ Receive notifications of client offers
✅ View offer details (price, message, timeline)
✅ Accept, reject, or counter-offer
✅ See negotiation history
✅ Price automatically locked when accepted
```

### Client Perspective
```
✅ See which services are negotiable
✅ View min/max price range upfront
✅ Send offer with optional message
✅ See offer status and expiry time
✅ Receive counter-offers
✅ Accept or send new offer
✅ Max 3 rounds of negotiation
✅ Price locked when both parties agree
```

---

## Key Constraints

```
┌─────────────────────────────────────────────────────────┐
│ MAXIMUM ROUNDS: 3                                       │
├─────────────────────────────────────────────────────────┤
│ Round 1: Client Offer      (0-30 min)                  │
│ Round 2: Provider Counter  (0-30 min)                  │
│ Round 3: Client Final Offer (0-30 min)                 │
│                                                         │
│ After Round 3: MUST FINALIZE or EXPIRE                 │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ OFFER EXPIRY: 30 MINUTES                                │
├─────────────────────────────────────────────────────────┤
│ Starts when: Offer/Counter created                      │
│ If no response: Auto-expires                            │
│ Timer shows: Real-time countdown                        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ PRICE VALIDATION:                                       │
├─────────────────────────────────────────────────────────┤
│ Offer Price >= Min Price (required)                     │
│ Offer Price <= Max Price (required)                     │
│ Offer Price > 0 (required)                              │
│ Whole numbers or cents accepted                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ PARTICIPATION:                                          │
├─────────────────────────────────────────────────────────┤
│ Client: Must be logged in, non-provider                 │
│ Provider: Must own the service                          │
│ Service: Must be marked negotiable                      │
└─────────────────────────────────────────────────────────┘
```

---

## System Architecture

```
CLIENT (provider-profile.php)
│
├── Display Negotiable Services
│   ├── Service Card
│   │   ├── Show 🤝 Negotiable Badge
│   │   ├── Display Price Range (min-max)
│   │   └── Show "Send Offer" Button
│   │
│   └── Offer Modal
│       ├── Price Input (Validated)
│       ├── Message Field (Optional)
│       ├── Range Info Display
│       └── Submit Button
│
└── API Call (service_offers.php)
    │
    ├── POST /api/service_offers.php?action=create_offer
    │   ├── Validate user is logged in
    │   ├── Validate price in range
    │   ├── Create offer in DB
    │   └── Return success/error
    │
    └── RESPONSE
        ├── Success Alert
        ├── Update Status
        └── Refresh UI

PROVIDER (Dashboard/Bookings)
│
├── See Pending Offers
│   ├── Service name
│   ├── Client's offered price
│   ├── Your min/max range
│   ├── Timer (remaining time)
│   └── Action buttons
│
└── API Calls
    ├── Accept: POST service_offers.php?action=accept_offer
    ├── Reject: POST service_offers.php?action=reject_offer
    └── Counter: POST service_offers.php?action=counteroffer

BACKEND
│
├── service_negotiation.php (Class)
│   ├── createOffer()
│   ├── acceptOffer()
│   ├── rejectOffer()
│   ├── createCounterOffer()
│   ├── acceptCounterOffer()
│   └── autoExpireOffers()
│
├── api/service_offers.php (API)
│   ├── User authentication
│   ├── Input validation
│   ├── Call ServiceNegotiation methods
│   └── Return JSON response
│
└── Database
    ├── provider_services (negotiable, min/max price)
    ├── service_offers (client offers)
    ├── service_counteroffers (provider counters)
    ├── negotiation_history (audit trail)
    └── finalized_service_prices (locked prices)
```

---

This visual overview explains exactly how the negotiation system works from both client and provider perspectives!
