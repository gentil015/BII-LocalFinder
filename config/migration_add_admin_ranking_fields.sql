-- Migration: Add admin ranking fields to service_providers
-- Run this migration after updating the application code.

ALTER TABLE `service_providers`
  ADD COLUMN `admin_promotion_boost` INT(11) NOT NULL DEFAULT 0 AFTER `search_boost`,
  ADD COLUMN `admin_priority_level` TINYINT(1) NOT NULL DEFAULT 0 AFTER `admin_promotion_boost`,
  ADD COLUMN `admin_score_override` INT(11) DEFAULT NULL AFTER `admin_priority_level`,
  ADD COLUMN `admin_ranking_score` INT(11) NOT NULL DEFAULT 0 AFTER `admin_score_override`;

UPDATE `service_providers`
SET `admin_promotion_boost` = 0,
    `admin_priority_level` = 0,
    `admin_score_override` = NULL,
    `admin_ranking_score` = 0
WHERE `admin_promotion_boost` IS NULL
   OR `admin_priority_level` IS NULL
   OR `admin_ranking_score` IS NULL;
