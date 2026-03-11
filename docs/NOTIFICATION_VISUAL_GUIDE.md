# Notification System - Visual Guide & Architecture

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLIENT SIDE ACTIONS                          │
├─────────────────────────────────────────────────────────────────┤
│  Booking Request │ Add Favorite │ Submit Review │ File Complaint  │
│  Submit Offer    │ Update Service│ View Profile │ Leave Review    │
└────────┬─────────┬──────────────┬───────────────┬────────────────┘
         │         │              │               │
         └────────────────────────┬───────────────┘
                                  ▼
                    ┌──────────────────────────┐
                    │  Database Trigger or     │
                    │  Programmatic Call       │
                    │  (in action handler)     │
                    └───────────┬──────────────┘
                                │
                    ┌───────────▼──────────────┐
                    │ Notification Function   │
                    │ (notifyXXX)              │
                    └───────────┬──────────────┘
                                │
              ┌─────────────────┼─────────────────┐
              │                 │                 │
              ▼                 ▼                 ▼
    ┌──────────────┐  ┌─────────────────┐  ┌──────────────┐
    │ Check User   │  │ Check if Type   │  │ Get Related  │
    │ Preferences  │  │ is Enabled      │  │ Entity Data  │
    │              │  │                 │  │              │
    └──────┬───────┘  └────────┬────────┘  └──────┬───────┘
           │                   │                   │
           └───────────────────┼───────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │ Generate JSON Data  │
                    │ (additional context)│
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ INSERT into DB      │
                    │ notifications table │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │ Create Preferences  │
                    │ if not exist        │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────────┐
                    │ Return notification_id │
                    └──────────┬───────────────┘
                               │
                    ┌──────────▼──────────────┐
                    │ PROVIDER SEES IN       │
                    │ DASHBOARD              │
                    └────────────────────────┘
```

## Database Schema Relationship

```
USER TABLE
┌─────────────────┐
│ id              │
│ full_name       │
│ email           │
│ ...             │
└────────┬────────┘
         │
         │ (1 to Many)
         │
         ▼
┌───────────────────────────┐     ┌──────────────────────────────┐
│  NOTIFICATIONS            │     │ NOTIFICATION_PREFERENCES     │
├───────────────────────────┤     ├──────────────────────────────┤
│ id                        │     │ id                           │
│ user_id (FK)              │     │ user_id (FK)                 │
│ notification_type         │     │ booking_notifications        │
│ title                     │     │ offer_notifications          │
│ message                   │     │ favorite_notifications       │
│ related_id                │     │ service_notifications        │
│ related_type              │     │ review_notifications         │
│ icon                      │     │ complaint_notifications      │
│ icon_color                │     │ email_notifications          │
│ priority                  │     │ notification_digest_freq...  │
│ data (JSON)               │     │ created_at                   │
│ is_read                   │     │ updated_at                   │
│ action_url                │     └──────────────────────────────┘
│ action_label              │
│ created_at                │
│ updated_at                │
└───────────────────────────┘

Indices:
├─ user_id
├─ notification_type
├─ is_read
├─ created_at
├─ priority
└─ (user_id, is_read)
└─ (user_id, created_at)
└─ (related_id, related_type)
```

## Notification Type Hierarchy

```
NOTIFICATIONS
│
├─ BOOKING NOTIFICATIONS
│  ├─ New booking request
│  ├─ Booking confirmed
│  ├─ Booking completed
│  └─ Booking cancelled
│
├─ OFFER NOTIFICATIONS
│  ├─ New offer received
│  ├─ Offer accepted
│  └─ Offer rejected
│
├─ FAVORITE NOTIFICATIONS
│  ├─ Added to favorites
│  └─ Removed from favorites
│
├─ SERVICE NOTIFICATIONS
│  ├─ Service added
│  ├─ Service updated
│  ├─ Service deleted
│  └─ Service availability changed
│
├─ REVIEW NOTIFICATIONS
│  ├─ New review posted
│  ├─ Review rating changed
│  └─ Review response received
│
├─ PROFILE NOTIFICATIONS
│  └─ Profile viewed
│
├─ COMPLAINT NOTIFICATIONS
│  ├─ Complaint filed
│  ├─ Complaint resolved
│  └─ Complaint escalated
│
└─ SYSTEM NOTIFICATIONS
   ├─ Account warnings
   ├─ Account suspended
   ├─ System maintenance
   └─ Policy updates
```

## Priority & Color Coding System

```
URGENT (Red - #dc3545)
│
├─ Complaints
├─ Account suspensions
└─ Critical system alerts
│
├──────────────────────────────
│
HIGH (Blue - #007bff)
│
├─ New bookings
├─ New offers
└─ Payment issues
│
├──────────────────────────────
│
MEDIUM (Yellow - #ffc107)
│
├─ Service updates
├─ New reviews
├─ Offer changes
└─ Service additions
│
├──────────────────────────────
│
LOW (Gray - #6c757d)
│
├─ Profile views
├─ Service deletions (if no bookings)
└─ System information
```

## Notification Lifecycle Flow

```
┌─────────────────────────────────┐
│ 1. CREATE NOTIFICATION          │
│ - Insert into DB                │
│ - Set is_read = 0               │
│ - Set created_at = NOW()        │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ 2. NOTIFICATION APPEARS         │
│ - Show in notification list     │
│ - Display with icon/color       │
│ - Mark as NEW                   │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ 3. USER INTERACTION             │
│ User can:                       │
│ ├─ Click action link            │
│ ├─ Mark as read                 │
│ ├─ Delete notification          │
│ └─ Filter by type               │
└────────────┬────────────────────┘
             │
      ┌──────┴──────┬──────────┐
      │             │          │
      ▼             ▼          ▼
   MARK READ     DELETE    TAKE ACTION
      │             │          │
      ▼             ▼          ▼
┌────────┐   ┌────────┐   ┌──────────┐
│ UPDATE │   │ DELETE │   │ REDIRECT │
│ is_read│   │FROM DB │   │TO CONTENT│
│read_at │   │        │   │          │
└────────┘   └────────┘   └──────────┘
      │             │          │
      └─────────────┴──────────┘
             │
             ▼
┌─────────────────────────────────┐
│ 4. NOTIFICATION ARCHIVED        │
│ (removed from list / history)   │
└─────────────────────────────────┘
```

## Dashboard UI Layout

```
┌─────────────────────────────────────────────────────────────┐
│              PAGE HEADER                                     │
│  🔔 Notification Center    [Stat: 5 unread]  [Mark All Read]│
├─────────────────────────────────────────────────────────────┤
│  STATS ROW                                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐    │
│  │ 45 Total │  │ 5 Unread │  │ 3 Bookings  │ 2 Offers │    │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘    │
├─────────────────────────────────────────────────────────────┤
│  FILTER TABS                                                 │
│  [All] [🗓️ Bookings] [🎁 Offers] [❤️ Favorites]             │
│  [🔄 Updates] [⭐ Reviews] [👁️ Views] [⚠️ Complaints]       │
├─────────────────────────────────────────────────────────────┤
│  NOTIFICATION CARDS (List View)                             │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ 📅 [NEW]  New Booking Request           HIGH        │   │
│  │    From: John Doe                                    │   │
│  │    Service: Window cleaning                          │   │
│  │    Time: 2 minutes ago                               │   │
│  │    [View Booking] [Mark Read] [Delete]               │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ ❤️  [NEW]  Added to Favorites            MEDIUM      │   │
│  │    From: Jane Smith                                  │   │
│  │    Time: 1 hour ago                                  │   │
│  │    [View Profile] [Mark Read] [Delete]               │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ ⭐  [READ]  New Review - 4.5 Stars           MEDIUM   │   │
│  │    From: Mike Johnson                                │   │
│  │    Great service, very professional                 │   │
│  │    Time: 3 hours ago                                 │   │
│  │    [View Review] [Delete]                            │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## API Flow Diagram

```
PROVIDER BROWSER
       │
       │ AJAX Request
       │ GET /api/notifications.php?action=get_notifications
       │
       ▼
┌──────────────────────┐
│ Notification API     │
├──────────────────────┤
│ ✓ Get Notifications  │
│ ✓ Get Unread Count   │
│ ✓ Mark as Read       │
│ ✓ Mark All Read      │
│ ✓ Delete             │
│ ✓ Delete All         │
│ ✓ Get Stats          │
└──────┬───────────────┘
       │
       │ Query Database
       │
       ▼
┌──────────────────────┐
│ Database Query       │
│ SELECT ... FROM      │
│ notifications WHERE  │
│ user_id = ?          │
└──────┬───────────────┘
       │
       │ Return Results
       │
       ▼
┌──────────────────────┐
│ Format JSON Response │
│ {                    │
│   success: true,     │
│   notifications: [], │
│   count: N           │
│ }                    │
└──────┬───────────────┘
       │
       │ JSON Response
       │
       ▼
PROVIDER BROWSER
Update UI with new data
```

## Feature Integration Points

```
SYSTEM FEATURES              NOTIFICATION TRIGGER
─────────────────────────────────────────────────
Booking System         ──→   notifyNewBooking()
Favorite Toggle        ──→   notifyFavoriteAction()
Review System          ──→   notifyReviewReceived()
Offer/Negotiation      ──→   notifyOfferReceived()
Service Operations     ──→   notifyServiceUpdate()
Profile Interactions   ──→   notifyProfileView()
Complaint System       ──→   notifyComplaintReceived()
System Events          ──→   createNotification()
```

## Mobile Responsive Design Breakpoints

```
DESKTOP (>992px)
┌───────────────────────┐
│ Sidebar | Main Content│
│ 250px   | Dynamic     │
└───────────────────────┘

TABLET (768px - 992px)
┌──────────────────┐
│ Sidebar (hidden) │
│ Main Content     │
│ Toggle button    │
└──────────────────┘

MOBILE (<768px)
┌──────────────┐
│ Main Content │
│ Toggle menu  │
│ Single column│
└──────────────┘
```

## Caching & Performance Strategy

```
DATABASE QUERIES
     ↓
   CACHE (Optional)
     ├─ Cache Key: notif_{user_id}_{type}_{offset}
     ├─ TTL: 300 seconds (5 min)
     └─ Invalidate on new notification
     ↓
  IF HIT → Return cached
  IF MISS → Query DB + Store in cache
     ↓
 INDEX LOOKUPS
     ├─ user_id index
     ├─ is_read index
     ├─ created_at index
     └─ (user_id, is_read) composite
     ↓
 PAGINATION
     ├─ Default: 50 per page
     ├─ SQL LIMIT + OFFSET
     └─ Prevents large result sets
```

## Security & Access Control

```
REQUEST RECEIVED
     │
     ▼
CHECK SESSION
     ├─ Is provider logged in?
     ├─ Session is valid?
     └─ Session not expired?
     │
     ├─ ✗ → 403 Forbidden
     └─ ✓ → Continue
     │
     ▼
REQUIRE PROVIDER
     ├─ Is user type = provider?
     └─ Is user not client?
     │
     ├─ ✗ → 403 Forbidden
     └─ ✓ → Continue
     │
     ▼
VALIDATE INPUT
     ├─ Sanitize GET/POST params
     ├─ Type check IDs (intval)
     └─ Validate enum values
     │
     ├─ ✗ → 400 Bad Request
     └─ ✓ → Continue
     │
     ▼
PARAMETERIZED QUERY
     ├─ Use prepared statements
     ├─ Bind user_id parameter
     └─ Prevent SQL injection
     │
     ▼
RETURN RESULT
     └─ Only user's own data
```

## State Transitions for Notification

```
         CREATE
          │
          ▼
    ┌──────────┐
    │ UNREAD   │ ← Initial state
    │(is_read=0)
    └──┬───────┘
       │
       ├─────────────────┬──────────────────┐
       │                 │                  │
       ▼                 ▼                  ▼
    READ            DELETE           ACTION
    │                │                 │
    ▼                ▼                 ▼
  ┌──────────┐   ┌──────────┐    ┌──────────┐
  │ READ     │   │ DELETED  │    │ REDIRECT │
  │(is_read=1)  │(removed) │    │TO RELATED│
  │read_at=NOW  └──────────┘    └──────────┘
  └──────────┘

State Diagram: UNREAD → (READ or DELETE) or REDIRECT
```

---

This visual guide shows how the notification system fits together and how data flows through it.
