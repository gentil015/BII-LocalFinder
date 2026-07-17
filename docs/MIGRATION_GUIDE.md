# Database Migration Instructions

## Missing Columns Added

The following columns are now required for the provider carousel feature:
- `is_featured` - Boolean flag for featured providers
- `featured_until` - DateTime for featured period expiration
- `search_boost` - Integer for search ranking boost

## How to Apply Migration

### Option 1: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your `bii_localfinder` database
3. Click the "SQL" tab
4. Open file: `config/migration_add_provider_fields.sql`
5. Copy and paste the SQL content
6. Click "Go" to execute

### Option 2: Using MySQL Command Line
```bash
cd C:\xampp\htdocs\Bii_localFinder
mysql -u root bii_localfinder < config/migration_add_provider_fields.sql
```

### Option 3: Manual SQL Execution
Run these commands in phpMyAdmin or MySQL:

```sql
-- Add missing columns
ALTER TABLE `service_providers` ADD COLUMN `is_featured` TINYINT(1) DEFAULT 0;
ALTER TABLE `service_providers` ADD COLUMN `featured_until` DATETIME NULL;
ALTER TABLE `service_providers` ADD COLUMN `search_boost` INT DEFAULT 0;

-- Create indexes for performance
CREATE INDEX `idx_is_featured` ON `service_providers`(`is_featured`);
CREATE INDEX `idx_search_boost` ON `service_providers`(`search_boost`);
```

## What These Columns Do

| Column | Type | Purpose |
|--------|------|---------|
| `is_featured` | TINYINT(1) | Marks provider as featured (highest priority in carousel) |
| `featured_until` | DATETIME | When featured status expires |
| `search_boost` | INT | Search ranking boost value (0 = no boost) |

## Admin Panel Usage

Once columns are added, admins can:
1. Mark providers as featured
2. Set featured periods
3. Adjust search boost values
4. These settings affect carousel rotation order

## Verification

After running migration, verify columns exist:
```sql
DESCRIBE service_providers;
-- Look for: is_featured, featured_until, search_boost
```

## Error Resolution

If you see "Undefined array key" warnings:
- Run the migration SQL above
- Clear browser cache
- Refresh the page

All issues should be resolved! ✅
