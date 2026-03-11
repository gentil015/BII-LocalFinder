# Provider Requirements Checklist - Implementation Guide

## Overview

The Provider Requirements system validates and displays completion of 5 core provider setup requirements:

1. **National ID / Passport** - Uploaded and approved verification document
2. **Profile Photo** - Real face profile picture
3. **Bio & Experience** - Professional biography and years of experience
4. **Service with Price** - At least one service offering with pricing
5. **Working Hours** - Defined availability schedule

---

## Core Classes & Methods

### ProviderRequirements Class

Located in: `/includes/provider_requirements.php`

#### Initialization

```php
require_once '../includes/provider_requirements.php';

$db = Database::getInstance()->getConnection();
$requirements = new ProviderRequirements($db, $provider_id);
```

#### Main Methods

```php
// Get all requirements status (boolean array)
$requirements->getAllRequirements();
// Returns: ['national_id' => true, 'profile_photo' => true, ...]

// Get requirements with detailed info
$requirements->getRequirementsWithDetails();
// Returns array with name, description, completion status, icon, help text

// Check if all requirements are met
$requirements->isComplete();
// Returns: true/false

// Get completion percentage (0-100)
$requirements->getCompletionPercentage();
// Returns: 80

// Get count of completed requirements
$requirements->getCompletedCount();
// Returns: ['completed' => 4, 'total' => 5]

// Get next incomplete requirement
$requirements->getNextStep();
// Returns: array with first incomplete requirement details or null
```

#### Rendering Methods

```php
// Full checklist with progress bar (for profile pages)
echo $requirements->renderChecklist($show_help_text = true);

// Compact mini checklist (for directory listings)
echo $requirements->renderMiniChecklist($show_percentage = true);

// Quick status badge
echo $requirements->renderCompletionBadge();
// Output: <span class="profile-completion-badge complete">...</span>

// Tooltip showing incomplete requirements
echo $requirements->renderIncompleteTooltip();

// JSON export (for AJAX/JavaScript)
$json = $requirements->toJSON();
```

---

## Helper Functions

Located in: `/includes/provider_directory_helpers.php`

```php
require_once '../includes/provider_directory_helpers.php';
```

### Display Functions

```php
// Render provider card with requirements checklist
renderProviderCardWithRequirements($provider, $db);

// Render provider row in table/list
renderProviderRow($provider, $db);

// Render full directory list with all providers
renderProviderDirectoryList($providers, $db, $show_mini = true);

// Get provider requirements as HTML data attributes
getProviderRequirementsDataAttrs($provider, $db);
// Returns: data-completion="80" data-complete="true" data-count="4/5"
```

### Utility Functions

```php
// Check if provider meets minimum requirements for bookings
isProviderReadyForBookings($provider, $db, $minimum_percentage = 80);
// Returns: true/false

// Get array of incomplete requirements
getRemainingRequirements($db, $provider_id);
// Returns: ['national_id' => {...}, 'profile_photo' => {...}]

// Get system-wide completion statistics
getProviderCompletionStats($db);
// Returns: stats about how many providers completed each requirement

// Quick access function
getProviderRequirements($db, $provider_id);
// Returns: ProviderRequirements instance
```

---

## Usage Examples

### Provider Profile Page

```php
<?php
require_once '../includes/provider_requirements.php';

$db = Database::getInstance()->getConnection();
$requirements = new ProviderRequirements($db, $provider_id);
?>

<div class="profile-section">
    <!-- Show full checklist -->
    <?php echo $requirements->renderChecklist(true); ?>
    
    <!-- Show completion badge -->
    <p><?php echo $requirements->renderCompletionBadge(); ?></p>
</div>
```

### Provider Settings Page

```php
<?php
require_once '../includes/provider_requirements.php';

$db = Database::getInstance()->getConnection();
$requirements = new ProviderRequirements($db, $provider_id);
$completion_pct = $requirements->getCompletionPercentage();
?>

<div class="settings-header">
    <p>Profile Complete: <?php echo $completion_pct; ?>%</p>
</div>

<!-- Show full checklist before settings -->
<?php echo $requirements->renderChecklist(true); ?>
```

### Provider Directory (Admin)

```php
<?php
require_once '../includes/provider_directory_helpers.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM service_providers WHERE is_active = 1");
$providers = $stmt->fetchAll();
?>

<!-- Show all providers with requirements -->
<?php echo renderProviderDirectoryList($providers, $db, true); ?>
```

### Provider Search Results (Client)

```php
<?php
require_once '../includes/provider_directory_helpers.php';

$db = Database::getInstance()->getConnection();
?>

<div class="search-results">
    <?php foreach ($providers as $provider): ?>
        <div class="result-item">
            <h4><?php echo htmlspecialchars($provider['full_name']); ?></h4>
            
            <!-- Show mini checklist -->
            <?php $req = new ProviderRequirements($db, $provider['id']); ?>
            <?php echo $req->renderMiniChecklist(false); ?>
            
            <!-- Show badge -->
            <?php echo $req->renderCompletionBadge(); ?>
        </div>
    <?php endforeach; ?>
</div>
```

### Provider Card in Listing

```php
<?php
require_once '../includes/provider_directory_helpers.php';

$db = Database::getInstance()->getConnection();
$req = new ProviderRequirements($db, $provider['id']);
?>

<div class="provider-card" <?php echo getProviderRequirementsDataAttrs($provider, $db); ?>>
    <h3><?php echo htmlspecialchars($provider['full_name']); ?></h3>
    
    <!-- Show mini checklist -->
    <?php echo $req->renderMiniChecklist(true); ?>
    
    <!-- Show status badge -->
    <div class="card-footer">
        <?php echo $req->renderCompletionBadge(); ?>
    </div>
</div>
```

### Check If Ready for Bookings

```php
<?php
require_once '../includes/provider_directory_helpers.php';

$db = Database::getInstance()->getConnection();

// Check if provider has at least 80% requirements complete
if (isProviderReadyForBookings($provider, $db, 80)) {
    echo "This provider can receive bookings";
} else {
    $remaining = getRemainingRequirements($db, $provider['id']);
    echo "Provider still needs to complete: ";
    foreach ($remaining as $req) {
        echo $req['name'] . ", ";
    }
}
?>
```

### AJAX Integration

```javascript
// Get provider requirements as JSON
fetch('/api/provider-requirements.php?provider_id=12')
    .then(response => response.json())
    .then(data => {
        console.log(data.completion_percentage); // 80
        console.log(data.is_complete); // false
        console.log(data.next_step); // {name: 'National ID / Passport', ...}
    });
```

Create `/api/provider-requirements.php`:

```php
<?php
require_once '../includes/provider_requirements.php';

$db = Database::getInstance()->getConnection();
$provider_id = intval($_GET['provider_id'] ?? 0);

if (!$provider_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider ID required']);
    exit;
}

$requirements = new ProviderRequirements($db, $provider_id);
header('Content-Type: application/json');
echo json_encode($requirements->toJSON());
```

---

## CSS Classes

All styles are in `/assets/css/provider-requirements.css`

### Main Classes

- `.provider-requirements-checklist` - Full checklist container
- `.checklist-mini` - Compact mini checklist
- `.profile-completion-badge` - Status badge
- `.provider-directory-list` - Directory list container
- `.directory-item` - Individual directory item
- `.provider-card-with-requirements` - Card with requirements

### Status Classes

- `.complete` - Applied when requirement is complete
- `.incomplete` - Applied when requirement is incomplete
- `.partial` - Applied when some but not all requirements met

---

## Database Queries

The system checks these database tables/fields:

- `verification_documents` - For national ID/passport verification
- `users.profile_image` - For profile photo
- `service_providers.bio` - For bio text
- `service_providers.experience_years` - For experience
- `provider_services` - For services with prices
- `service_providers.working_days` - For availability
- `service_providers.working_hours_start/end` - For working hours

---

## Integration Points

Currently integrated in:

1. ✅ `/provider/profile.php` - Full checklist display
2. ✅ `/provider/settings.php` - Full checklist display
3. ✅ `/client/provider-profile.php` - Mini badge display
4. ⏳ `/admin/providers.php` - Ready for integration
5. ⏳ `/client/providers.php` - Ready for integration

---

## Customization

### Change Completion Percentage Requirement

In your code:
```php
// Require 90% instead of 80%
if (!isProviderReadyForBookings($provider, $db, 90)) {
    echo "Provider needs more completions";
}
```

### Modify Requirement Definitions

Edit `/includes/provider_requirements.php` in the `getRequirementsWithDetails()` method.

### Customize Styling

Edit `/assets/css/provider-requirements.css`

### Add Custom Validation

Extend the `ProviderRequirements` class:

```php
class CustomProviderRequirements extends ProviderRequirements {
    public function hasPhoneVerified() {
        // Custom logic
    }
}
```

---

## Performance Notes

- Uses prepared statements for security
- Minimal database queries (cached per instance)
- All rendering uses string concatenation (no template engine overhead)
- Suitable for loops and bulk operations

---

## Troubleshooting

**Requirements show as incomplete when they should be complete:**
- Clear any caching in your application
- Verify the database contains the expected data
- Check field names match database schema

**Mini checklist not displaying correctly:**
- Ensure `provider-requirements.css` is linked in HTML
- Check browser console for JavaScript errors
- Verify Font Awesome 6.4.0+ is loaded

**Performance issues:**
- Create database indexes on `verification_documents.provider_id`, `provider_services.provider_id`
- Use `getProviderRequirementsDataAttrs()` to cache data in HTML
- Consider redis caching for frequently accessed providers

---

## Future Enhancements

Possible additions:
- Email notifications when requirements are completed
- Admin approval workflow for document verification
- Gamification badges/levels
- Time-based requirement reminders
- Custom requirement rules per platform
- Multi-language support
