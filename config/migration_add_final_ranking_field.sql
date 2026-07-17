-- Migration: Add final ranking score to service_providers
-- Run this after adding the final ranking engine.

ALTER TABLE `service_providers`
  ADD COLUMN `final_score` INT(11) NOT NULL DEFAULT 0 AFTER `system_ranking_score`;

UPDATE `service_providers`
SET `final_score` = 0
WHERE `final_score` IS NULL;
