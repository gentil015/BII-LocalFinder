-- Migration: Add Offer-Counteroffer Negotiation System
-- This migration adds support for price negotiation between clients and providers

-- Alter provider_services table to add min/max price range
ALTER TABLE `provider_services` 
ADD COLUMN `min_price` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Minimum negotiable price',
ADD COLUMN `max_price` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Maximum negotiable price',
ADD COLUMN `negotiable` TINYINT(1) DEFAULT 0 COMMENT 'Is this service price negotiable?',
ADD COLUMN `base_price` DECIMAL(10,2) NULL COMMENT 'Base price for negotiation reference';

-- Create service offers table
CREATE TABLE IF NOT EXISTS `service_offers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL UNIQUE,
  `service_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `offered_price` DECIMAL(10,2) NOT NULL COMMENT 'Initial price offered by client',
  `status` ENUM('pending', 'accepted', 'rejected', 'expired', 'withdrawn') DEFAULT 'pending',
  `round_number` INT DEFAULT 1 COMMENT 'Negotiation round (1-3)',
  `expires_at` DATETIME NOT NULL COMMENT 'Offer expires at this time',
  `responded_at` DATETIME NULL,
  `response_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `provider_services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_expires_at` (`expires_at`),
  INDEX `idx_provider_id` (`provider_id`),
  INDEX `idx_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create counter-offers table
CREATE TABLE IF NOT EXISTS `service_counteroffers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `offer_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `proposed_price` DECIMAL(10,2) NOT NULL COMMENT 'Counter-offered price by provider',
  `status` ENUM('pending', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
  `round_number` INT DEFAULT 1 COMMENT 'Negotiation round (1-3)',
  `expires_at` DATETIME NOT NULL COMMENT 'Counter-offer expires',
  `responded_at` DATETIME NULL,
  `response_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`offer_id`) REFERENCES `service_offers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `provider_services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_expires_at` (`expires_at`),
  INDEX `idx_offer_id` (`offer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create negotiation history table
CREATE TABLE IF NOT EXISTS `negotiation_history` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `offer_id` INT NULL,
  `counteroffer_id` INT NULL,
  `action_type` ENUM('offer_created', 'offer_accepted', 'offer_rejected', 'offer_expired', 'counteroffer_created', 'counteroffer_accepted', 'counteroffer_rejected', 'counteroffer_expired', 'final_agreement') DEFAULT 'offer_created',
  `price_offered` DECIMAL(10,2),
  `actor_id` INT NOT NULL COMMENT 'User who took the action',
  `actor_type` ENUM('client', 'provider') NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`offer_id`) REFERENCES `service_offers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`counteroffer_id`) REFERENCES `service_counteroffers`(`id`) ON DELETE SET NULL,
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create table to track final agreed prices
CREATE TABLE IF NOT EXISTS `finalized_service_prices` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL UNIQUE,
  `service_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `finalized_price` DECIMAL(10,2) NOT NULL COMMENT 'Final agreed price',
  `negotiation_rounds` INT DEFAULT 1 COMMENT 'Number of rounds it took',
  `client_final_offer_id` INT NULL,
  `provider_final_counteroffer_id` INT NULL,
  `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `provider_services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
