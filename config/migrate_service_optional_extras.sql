-- Migration for provider_services support of optional extras and expanded payment types
ALTER TABLE `provider_services`
  MODIFY COLUMN `payment_type` enum('fixed_price','hourly_rate','per_job_estimate','per_day','per_service','base_price') NOT NULL DEFAULT 'fixed_price',
  ADD COLUMN `optional_extras` TEXT DEFAULT NULL AFTER `base_price`;
