# Share Functionality Architecture

## System Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    PROVIDER CARDS (providers.php)               │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │             Provider Card Component                      │   │
│  │  ┌─────────────────────────────────────────────────────┐ │   │
│  │  │ Provider Name & Details                            │ │   │
│  │  │ [View] [Book] [Report] [Emergency] [Share] ◄──────┤ │   │
│  │  │                                           │          │ │   │
│  │  └─────────────────────────────────────────────────────┘ │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                    │
│                              │ onclick="openShareModal(...)"      │
│                              ▼                                    │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │         Modern Share Modal                              │   │
│  │  ┌─────────────────────────────────────────────────────┐ │   │
│  │  │ [X] Share Provider                                  │ │   │
│  │  │                                                     │ │   │
│  │  │ Provider Info Box (Name, Profession)               │ │   │
│  │  │                                                     │ │   │
│  │  │ Share via:                                          │ │   │
│  │  │ [WhatsApp] [Facebook] [Twitter]                     │ │   │
│  │  │ [Email] [Copy Link] [QR Code]                       │ │   │
│  │  │                                                     │ │   │
│  │  │ Share Link: [link..............] [Copy]             │ │   │
│  │  │                                                     │ │   │
│  │  │ QR Code Container (hidden by default)               │ │   │
│  │  │ Email Form Container (hidden by default)            │ │   │
│  │  │                                                     │ │   │
│  │  │ Share Stats: Shares: 42 | Views: 156               │ │   │
│  │  └─────────────────────────────────────────────────────┘ │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Share Platform Flow

```
                   ┌─────── openShareModal() ──────────┐
                   │                                    │
          ┌────────┴────────┐                          │
          │                 │                          │
       shareVia()       fetchShareStats()         Load Modal
          │                 │                          │
    ┌─────┴─────────────────┴─────────────────────────┘
    │
    ├─────────────────┬──────────────────┬──────────────┬──────────┬─────────┬──────────┐
    │                 │                  │              │          │         │          │
    ▼                 ▼                  ▼              ▼          ▼         ▼          ▼
 [WhatsApp]      [Facebook]         [Twitter]      [Email]    [Copy]    [QRCode]  [Track]
    │                 │                  │              │          │         │          │
    │                 │                  │         showEmailForm   │    showQRCode  trackShare
    │                 │                  │              │          │         │          │
    ▼                 ▼                  ▼              ▼          ▼         ▼          ▼
  wa.me          facebook.com/       twitter.com/  submitEmail  Copy   generateQR  Database
  direct         sharer             intent/tweet  Link Form    Link    (CDN)      (provider_
  message                                                                         shares)
    │                 │                  │              │          │         │          │
    └─────────────────┴──────────────────┴──────────────┴──────────┴─────────┴──────────┘
                                    │
                         ┌──────────┴──────────┐
                         │                     │
                    Open in                 Database
                   External                  Insert
                    Browser                  Record
```

## Email Sharing Detailed Flow

```
┌─────────────────────────────────────────────────────────────┐
│              Email Share Form Submission                     │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  1. Form Input                                               │
│     ├─ Recipient Email                                       │
│     ├─ Personal Message (optional)                           │
│     └─ [Send Email] button                                   │
│                                                               │
│  2. submitEmailShare(event)                                  │
│     ├─ Validate email format                                 │
│     ├─ Prepare JSON payload                                  │
│     └─ Send via AJAX                                         │
│                                                               │
│  3. Backend: /client/providers.php?send_share_email=1        │
│     ├─ Validate all inputs                                   │
│     ├─ Build HTML email template                             │
│     │  ├─ Sender info                                        │
│     │  ├─ Provider details                                   │
│     │  ├─ Personal message                                   │
│     │  └─ Direct link button                                 │
│     ├─ Call Mailer::sendCustomEmail()                        │
│     ├─ Log share to provider_shares table                    │
│     └─ Return JSON response                                  │
│                                                               │
│  4. Frontend Response                                        │
│     ├─ Success: Show confirmation message                    │
│     ├─ Clear form                                            │
│     ├─ Hide email form                                       │
│     └─ Update statistics                                     │
│                                                               │
│  5. Recipient Email                                          │
│     ├─ From: biilocalfinder@gmail.com                        │
│     ├─ Subject: "Check out {provider} on BII LocalFinder"    │
│     ├─ Body: Beautiful HTML template                         │
│     │  ├─ Greeting                                           │
│     │  ├─ Sender name                                        │
│     │  ├─ Provider info card                                 │
│     │  ├─ Personal message                                   │
│     │  └─ "View Profile" button                              │
│     └─ Tracked as "email" share                              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## QR Code Generation Flow

```
┌──────────────────────────────────────────────────────────┐
│            QR Code Generation Process                    │
├──────────────────────────────────────────────────────────┤
│                                                            │
│  1. User clicks "QR Code" button                          │
│     │                                                     │
│     ▼                                                     │
│  2. showQRCode(shareLink) function                        │
│     ├─ Toggle visibility of QR container                 │
│     └─ Check if QRCode library loaded                    │
│            │                                             │
│            ├─ If NOT loaded:                             │
│            │  ├─ Create script tag                       │
│            │  ├─ Src: cdnjs.cloudflare.com/.../qrcode    │
│            │  ├─ Wait for load                           │
│            │  └─ Then generate                           │
│            │                                             │
│            └─ If loaded:                                 │
│               └─ Generate immediately                    │
│     │                                                     │
│     ▼                                                     │
│  3. generateQRCode(canvas, url)                          │
│     ├─ Create QRCode instance                            │
│     ├─ Text: Full provider profile URL                   │
│     ├─ Size: 200x200 pixels                              │
│     ├─ Error Correction: Level H (30%)                   │
│     └─ Render to canvas                                  │
│     │                                                     │
│     ▼                                                     │
│  4. Display Result                                        │
│     ├─ Canvas visible in modal                           │
│     ├─ "Scan with your phone" text                       │
│     ├─ Mobile camera scans QR                            │
│     └─ Opens provider profile                            │
│                                                            │
│  5. Track Share                                           │
│     └─ Record in database as "qrcode" platform           │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

## Database Schema

```
provider_shares Table:
┌────────────────┬──────────┬──────────────────────┐
│ Column         │ Type     │ Purpose              │
├────────────────┼──────────┼──────────────────────┤
│ id             │ INT PK   │ Unique identifier    │
│ provider_id    │ INT FK   │ Which provider       │
│ user_id        │ INT FK   │ Who shared           │
│ platform       │ VARCHAR  │ How (whatsapp,       │
│                │          │ facebook, etc)       │
│ shared_at      │ TIMESTAMP│ When (auto)          │
└────────────────┴──────────┴──────────────────────┘
Index: (provider_id, shared_at)

provider_views Table:
┌────────────────┬──────────┬──────────────────────┐
│ Column         │ Type     │ Purpose              │
├────────────────┼──────────┼──────────────────────┤
│ id             │ INT PK   │ Unique identifier    │
│ provider_id    │ INT FK   │ Which provider       │
│ user_id        │ INT FK   │ Who viewed           │
│ viewed_at      │ TIMESTAMP│ When (auto)          │
└────────────────┴──────────┴──────────────────────┘
Index: (provider_id, viewed_at)
```

## Component Interaction

```
┌──────────────────────────────────────────────────────────┐
│                Frontend (providers.php)                  │
├──────────────────────────────────────────────────────────┤
│                                                            │
│  JavaScript Layer:                                        │
│  ├─ openShareModal()              ◄─ User click          │
│  ├─ shareVia()                    ◄─ Platform selection  │
│  ├─ copyShareLink()               ◄─ Copy button         │
│  ├─ showEmailShareForm()          ◄─ Email button        │
│  ├─ submitEmailShare()            ◄─ Form submit         │
│  ├─ showQRCode()                  ◄─ QR button           │
│  ├─ trackShare()                  ◄─ Background track    │
│  └─ fetchShareStats()             ◄─ Load stats          │
│       │                                                   │
│       │ AJAX Fetch/Post                                  │
│       ▼                                                   │
├──────────────────────────────────────────────────────────┤
│                Backend (PHP)                             │
├──────────────────────────────────────────────────────────┤
│                                                            │
│  AJAX Handlers:                                           │
│  ├─ ?track_share=1                                       │
│  │  └─ INSERT into provider_shares                       │
│  │                                                        │
│  ├─ ?get_share_stats=1                                   │
│  │  ├─ COUNT from provider_shares (30 days)              │
│  │  └─ COUNT from provider_views (30 days)               │
│  │                                                        │
│  ├─ ?send_share_email=1                                  │
│  │  ├─ Validate input                                    │
│  │  ├─ Call Mailer::sendCustomEmail()                    │
│  │  ├─ INSERT into provider_shares                       │
│  │  └─ Return JSON result                                │
│                                                            │
├──────────────────────────────────────────────────────────┤
│                Database (MySQL)                          │
├──────────────────────────────────────────────────────────┤
│                                                            │
│  Tables:                                                  │
│  ├─ provider_shares     ◄─ Write share events             │
│  ├─ provider_views      ◄─ Write view events              │
│  └─ system_settings     ◄─ Read email settings            │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

## Analytics Flow

```
Each Share Action:
│
├─ Platform selection
│  └─ trackShare(platform)
│     └─ POST to track_share endpoint
│        └─ INSERT: provider_shares
│           (provider_id, user_id, platform, shared_at)
│
├─ After user shares via social media
│  └─ External link click
│     └─ User visits profile
│
├─ Statistics retrieval
│  └─ fetchShareStats(providerId)
│     └─ GET get_share_stats endpoint
│        ├─ SELECT COUNT from provider_shares (last 30 days)
│        ├─ SELECT COUNT from provider_views (last 30 days)
│        └─ Return JSON with counts
│
└─ Display in modal
   └─ Update #shareCount and #viewCount
```

## Security Layers

```
Input Validation
    ├─ Email: filter_var(FILTER_VALIDATE_EMAIL)
    ├─ Provider ID: intval()
    ├─ Platform: sanitize()
    └─ Message: sanitize()
    
Output Protection
    ├─ htmlspecialchars() for HTML
    ├─ Parameterized queries
    └─ JSON encoding safe
    
Access Control
    ├─ isLoggedIn() check
    ├─ User ID verification
    └─ Provider exists validation
    
Rate Limiting Ready
    ├─ Database records all shares
    └─ Can implement limits per user/provider
```

---

**Architecture Version**: 1.0  
**Last Updated**: December 17, 2025
