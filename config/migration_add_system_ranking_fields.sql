-- Migration: Add system ranking fields to service_providers
-- Run this after updating the application logic.

ALTER TABLE `service_providers`
  ADD COLUMN IF NOT EXISTS `avg_response_time_minutes` INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `completion_rate` DECIMAL(5,4) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `last_active` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `is_online` TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `system_ranking_score` INT(11) NOT NULL DEFAULT 0;

UPDATE `service_providers`
SET `is_online` = 0
WHERE `is_online` IS NULL;
