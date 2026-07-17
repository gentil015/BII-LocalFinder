-- Migration for provider_services support of service-level availability and booking mode
ALTER TABLE `provider_services`
  ADD COLUMN `availability_days` TEXT DEFAULT NULL COMMENT 'Comma-separated weekdays available for this service' AFTER `optional_extras`,
  ADD COLUMN `time_slots` TEXT DEFAULT NULL COMMENT 'JSON encoded time slots for the service' AFTER `availability_days`,
  ADD COLUMN `booking_mode` enum('request_approval','instant') NOT NULL DEFAULT 'request_approval' AFTER `time_slots`,
  ADD COLUMN `service_status` enum('draft','published','paused') NOT NULL DEFAULT 'draft' AFTER `booking_mode`;
