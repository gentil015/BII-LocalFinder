-- Migration: Create comprehensive notifications system
-- This migration creates a unified notifications table for all notification types
-- Including bookings, offers, favorites, service updates, and other provider actions

-- Create the main notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Provider ID receiving the notification',
  `notification_type` enum('booking','offer','favorite','service_update','service_added','profile_view','review','complaint','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'Booking ID, Service ID, User ID, etc.',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'booking, service, user, offer, etc.',
  `icon` varchar(50) DEFAULT NULL,
  `icon_color` varchar(20) DEFAULT NULL,
  `data` json DEFAULT NULL COMMENT 'Additional JSON data for notification details',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `action_url` varchar(500) DEFAULT NULL,
  `action_label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id_idx` (`user_id`),
  KEY `notification_type_idx` (`notification_type`),
  KEY `is_read_idx` (`is_read`),
  KEY `created_at_idx` (`created_at`),
  KEY `priority_idx` (`priority`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create notification preferences table
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `booking_notifications` tinyint(1) DEFAULT 1,
  `offer_notifications` tinyint(1) DEFAULT 1,
  `favorite_notifications` tinyint(1) DEFAULT 1,
  `service_notifications` tinyint(1) DEFAULT 1,
  `review_notifications` tinyint(1) DEFAULT 1,
  `complaint_notifications` tinyint(1) DEFAULT 1,
  `system_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `push_notifications` tinyint(1) DEFAULT 0,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `notification_digest_frequency` enum('instant','daily','weekly','never') DEFAULT 'instant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_unique` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create notification read status tracking
CREATE TABLE IF NOT EXISTS `notification_read_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_user_unique` (`notification_id`, `user_id`),
  FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indices for better performance
CREATE INDEX IF NOT EXISTS `idx_notifications_user_read` ON `notifications`(`user_id`, `is_read`);
CREATE INDEX IF NOT EXISTS `idx_notifications_user_created` ON `notifications`(`user_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_notifications_related` ON `notifications`(`related_id`, `related_type`);
