-- Migration: Add missing columns to service_providers table
-- This adds fields needed for provider ranking and featured status

ALTER TABLE `service_providers` ADD COLUMN `is_featured` TINYINT(1) DEFAULT 0 AFTER `is_banned`;
ALTER TABLE `service_providers` ADD COLUMN `featured_until` DATETIME NULL AFTER `is_featured`;
ALTER TABLE `service_providers` ADD COLUMN `search_boost` INT DEFAULT 0 AFTER `featured_until`;

-- Create index for faster queries
CREATE INDEX `idx_is_featured` ON `service_providers`(`is_featured`);
CREATE INDEX `idx_search_boost` ON `service_providers`(`search_boost`);
CREATE INDEX `idx_is_active_banned` ON `service_providers`(`is_active`, `is_banned`);

-- Update existing providers (set all to not featured, boost 0)
UPDATE `service_providers` SET `is_featured` = 0, `search_boost` = 0 WHERE `is_featured` IS NULL OR `search_boost` IS NULL;
