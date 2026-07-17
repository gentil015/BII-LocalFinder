# 💰 Price Negotiation System - Client Workflow

## Overview

Clients can now send price offers for services marked as **negotiable** by providers. This allows flexible pricing based on the specific work requested.

---

## How It Works for Providers

### Step 1: Create a Negotiable Service
When adding/editing a service in **Provider Services**:

1. Check the **"Negotiable"** checkbox
2. Set **Minimum Price** - Lowest price you'll accept (e.g., RWF 4,000)
3. Set **Maximum Price** - Highest price you'll accept (e.g., RWF 6,000)
4. Save the service

**Example:**
- Service: "House Cleaning"
- Base Price: RWF 5,000 (reference only)
- Min Price: RWF 4,000 (client can't offer less)
- Max Price: RWF 6,000 (client can't offer more)

---

## How It Works for Clients

### View Negotiable Services
When browsing a provider's profile on **provider-profile.php**:

1. **Services marked as "Negotiable"** show:
   - Purple badge: "🤝 Negotiable"
   - Price range: "RWF 4,000 - RWF 6,000"
   - Label: "Negotiable range"

2. **Services NOT negotiable** show:
   - Fixed price: "RWF 5,000"
   - Standard "Select This Service" button

### Send an Offer
For negotiable services:

1. Click **"🤝 Send Offer"** button
2. A modal appears with:
   - Service name
   - Price range (min-max)
   - Input field for your offer price
   - Optional message field
3. Enter a price **within the min-max range**
4. Add optional message (e.g., "I need this by next week")
5. Click **"Send Offer"**

### What Happens After

**Timeline:**
- ✅ Offer sent (expires in 30 minutes)
- Provider sees pending offer
- Provider can:
  - **Accept** → Price locked at your offer amount
  - **Reject** → You can send a new offer
  - **Counter-Offer** → Provider suggests a different price
- If counter-offer received, you can:
  - **Accept** → Price locked at counter amount
  - **Reject** → Send another offer (max 3 rounds total)
- **Auto-expires** → If no response in 30 minutes, offer expires

---

## Display Examples

### On Provider Profile Page

#### Negotiable Service Card
```
┌─────────────────────────────────┐
│ House Cleaning                  │
│ 🤝 Negotiable                   │
├─────────────────────────────────┤
│ Professional home cleaning      │
│ with eco-friendly products      │
├─────────────────────────────────┤
│ RWF 4,000 - RWF 6,000           │
│ 💡 Negotiable range             │
│ ⏱ 120 mins                      │
├─────────────────────────────────┤
│ 🤝 Send Offer                   │
└─────────────────────────────────┘
```

#### Fixed Price Service Card
```
┌─────────────────────────────────┐
│ Website Design                  │
├─────────────────────────────────┤
│ Modern responsive website       │
│ design with SEO optimization    │
├─────────────────────────────────┤
│ RWF 150,000                     │
│ ⏱ 480 mins                      │
├─────────────────────────────────┤
│ ✓ Select This Service           │
└─────────────────────────────────┘
```

#### Offer Modal
```
┌─────────────────────────────────────────┐
│ 🤝 Send Price Offer             [✕]    │
├─────────────────────────────────────────┤
│ Service: House Cleaning                 │
│                                         │
│ ℹ Price Range: RWF 4,000 - RWF 6,000   │
│                                         │
│ Your Offer Price *                      │
│ [RWF] [  5000  ]                        │
│ 💡 Enter between RWF 4,000-6,000        │
│                                         │
│ Additional Message (Optional)           │
│ [Need by next week please...]           │
│                                         │
│ ℹ How it works:                         │
│   • Provider reviews in 24 hours        │
│   • Can accept, reject, or counter      │
│   • Offers expire in 30 minutes         │
│   • Max 3 offers before finalizing      │
├─────────────────────────────────────────┤
│ [Cancel] [📤 Send Offer]                │
└─────────────────────────────────────────┘
```

---

## Key Features

✅ **Flexible Pricing** - Negotiate within provider's min/max range
✅ **Time-Limited** - Offers expire in 30 minutes
✅ **Limited Rounds** - Max 3 negotiation rounds
✅ **Clear Information** - See min/max range upfront
✅ **Optional Messages** - Explain your needs/budget
✅ **No Hidden Fees** - Final price is what's agreed upon
✅ **Auto-Expiry** - No dead offers hanging around

---

## Price Validation

### What You Can Offer
```
Provider sets: Min RWF 4,000, Max RWF 6,000

✅ Valid Offers:
- RWF 4,000 (minimum)
- RWF 5,000 (middle)
- RWF 5,500 (within range)
- RWF 6,000 (maximum)

❌ Invalid Offers:
- RWF 3,900 (below minimum)
- RWF 6,100 (above maximum)
- Negative amounts
- Zero
```

---

## Negotiation Status

### Status Icons & Meanings

| Status | Icon | Color | Meaning |
|--------|------|-------|---------|
| Pending | ⏳ | Yellow | Waiting for provider response |
| Accepted | ✅ | Green | Price locked - service booked |
| Rejected | ❌ | Red | Provider declined, send new offer |
| Counter | 🔄 | Blue | Provider suggested different price |
| Expired | ⏰ | Gray | No response in 30 mins |

---

## FAQ for Clients

### Q: What if my offer is rejected?
**A:** You can send another offer (up to 3 total). Each new offer resets the 30-minute timer.

### Q: Can I negotiate after the price is locked?
**A:** No. Once accepted, the price is final and locked.

### Q: What if the provider counters?
**A:** You'll see their proposed price and can either accept or send a new counter-offer.

### Q: How long do offers last?
**A:** 30 minutes. If no response, automatically expires.

### Q: Can I send an offer outside the price range?
**A:** No. The system only accepts prices within provider's min/max range.

### Q: Is there a commission on negotiated prices?
**A:** Platform commission applies to final agreed price same as any service.

### Q: Can I talk to the provider about the price?
**A:** Not directly, but you can use the optional message field in your offer.

---

## For Best Results

✅ **Be Realistic** - Stay within min/max range
✅ **Explain Needs** - Use message field to provide context
✅ **Respond Quickly** - When provider counters, respond promptly
✅ **Be Professional** - Good offers get better responses
✅ **Check Details** - Verify service includes what you need

---

## Technical Details

### Service Card Attributes
- `data-negotiable="1|0"` - Indicates if service can be negotiated
- Min price stored in `data.min_price`
- Max price stored in `data.max_price`
- Base/reference price shown only if fixed

### Price Display Logic
```
If negotiable AND has min/max prices:
  Display: "RWF 4,000 - RWF 6,000"
  Label: "Negotiable range"
  Button: "Send Offer"

Else:
  Display: Fixed price
  Button: "Select This Service"
```

### Validation
- Price must be >= min_price
- Price must be <= max_price
- Client must be logged in
- Client cannot be a provider
- Service must be marked negotiable

---

## Integration Points

### Files Modified/Created
- ✅ `provider-profile.php` - Display negotiable services with price ranges
- ✅ `provider/services.php` - Provider can set min/max prices
- ✅ Service negotiation system (backend)
- ✅ API endpoint for sending offers

### Database
- `provider_services.negotiable` - 0/1 flag
- `provider_services.min_price` - Minimum acceptable price
- `provider_services.max_price` - Maximum acceptable price
- `service_offers` - Table storing all offers

---

## Ready to Use

The negotiation system is fully integrated into the client-facing provider-profile.php page. Clients can now:

1. **Browse negotiable services** with clear price ranges
2. **Send offers** directly from the service card
3. **Receive counter-offers** from providers
4. **Lock in final prices** through negotiation

---

**Status:** ✅ Live and operational
**Version:** 1.0.0
**Last Updated:** December 26, 2025
