-- Migration: Add user_profiles table for ML personalization
-- This table stores aggregated user behavior data for personalized recommendations

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `user_id` int(11) NOT NULL,
  `user_avg_price` decimal(10,2) DEFAULT 0.00 COMMENT 'Average price of services the user has booked',
  `user_avg_response_time` decimal(5,2) DEFAULT 24.00 COMMENT 'Average response time of providers the user has booked (hours)',
  `user_total_bookings` int(11) DEFAULT 0 COMMENT 'Total number of bookings made by this user',
  `preferred_categories` text COMMENT 'Comma-separated list of preferred service categories',
  `preferred_price_range` varchar(50) DEFAULT '0-50000' COMMENT 'Preferred price range (min-max)',
  `preferred_rating_min` decimal(2,1) DEFAULT 0.0 COMMENT 'Minimum rating preference',
  `location_preference` varchar(255) DEFAULT NULL COMMENT 'Preferred service location',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_user_avg_price` (`user_avg_price`),
  KEY `idx_user_total_bookings` (`user_total_bookings`),
  CONSTRAINT `fk_user_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Aggregated user preferences for ML personalization';

-- Insert initial data for existing users based on their booking history
INSERT IGNORE INTO `user_profiles` (`user_id`, `user_avg_price`, `user_avg_response_time`, `user_total_bookings`, `preferred_categories`, `preferred_price_range`, `preferred_rating_min`)
SELECT
  u.id as user_id,
  COALESCE(AVG(ps.price), 0) as user_avg_price,
  COALESCE(AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)), 24) as user_avg_response_time,
  COUNT(b.id) as user_total_bookings,
  GROUP_CONCAT(DISTINCT c.name SEPARATOR ',') as preferred_categories,
  CASE
    WHEN AVG(ps.price) < 10000 THEN '0-10000'
    WHEN AVG(ps.price) < 25000 THEN '10000-25000'
    WHEN AVG(ps.price) < 50000 THEN '25000-50000'
    ELSE '50000+'
  END as preferred_price_range,
  0.0 as preferred_rating_min
FROM users u
LEFT JOIN bookings b ON u.id = b.client_id AND b.status IN ('confirmed', 'completed')
LEFT JOIN service_providers sp ON b.provider_id = sp.id
LEFT JOIN provider_services ps ON sp.id = ps.provider_id
LEFT JOIN categories c ON ps.category_id = c.id
WHERE u.user_type = 'client'
GROUP BY u.id;

-- Create a stored procedure to update user profiles periodically
DELIMITER //

CREATE PROCEDURE update_user_profiles()
BEGIN
  -- Update existing profiles
  UPDATE user_profiles up
  SET
    user_avg_price = (
      SELECT COALESCE(AVG(ps.price), 0)
      FROM bookings b
      JOIN service_providers sp ON b.provider_id = sp.id
      JOIN provider_services ps ON sp.id = ps.provider_id
      WHERE b.client_id = up.user_id AND b.status IN ('confirmed', 'completed')
    ),
    user_avg_response_time = (
      SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)), 24)
      FROM bookings b
      WHERE b.client_id = up.user_id AND b.responded_at IS NOT NULL AND b.status IN ('confirmed', 'completed')
    ),
    user_total_bookings = (
      SELECT COUNT(*)
      FROM bookings b
      WHERE b.client_id = up.user_id AND b.status IN ('confirmed', 'completed')
    ),
    preferred_categories = (
      SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ',')
      FROM bookings b
      JOIN service_providers sp ON b.provider_id = sp.id
      JOIN provider_services ps ON sp.id = ps.provider_id
      JOIN categories c ON ps.category_id = c.id
      WHERE b.client_id = up.user_id AND b.status IN ('confirmed', 'completed')
    ),
    preferred_price_range = (
      SELECT
        CASE
          WHEN AVG(ps.price) < 10000 THEN '0-10000'
          WHEN AVG(ps.price) < 25000 THEN '10000-25000'
          WHEN AVG(ps.price) < 50000 THEN '25000-50000'
          ELSE '50000+'
        END
      FROM bookings b
      JOIN service_providers sp ON b.provider_id = sp.id
      JOIN provider_services ps ON sp.id = ps.provider_id
      WHERE b.client_id = up.user_id AND b.status IN ('confirmed', 'completed')
    ),
    last_updated = NOW()
  WHERE EXISTS (
    SELECT 1 FROM bookings b WHERE b.client_id = up.user_id
  );

  -- Insert new profiles for users who don't have one yet
  INSERT IGNORE INTO user_profiles (user_id, user_avg_price, user_avg_response_time, user_total_bookings, preferred_categories, preferred_price_range, preferred_rating_min)
  SELECT
    u.id as user_id,
    COALESCE(AVG(ps.price), 0) as user_avg_price,
    COALESCE(AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)), 24) as user_avg_response_time,
    COUNT(b.id) as user_total_bookings,
    GROUP_CONCAT(DISTINCT c.name SEPARATOR ',') as preferred_categories,
    CASE
      WHEN AVG(ps.price) < 10000 THEN '0-10000'
      WHEN AVG(ps.price) < 25000 THEN '10000-25000'
      WHEN AVG(ps.price) < 50000 THEN '25000-50000'
      ELSE '50000+'
    END as preferred_price_range,
    0.0 as preferred_rating_min
  FROM users u
  LEFT JOIN bookings b ON u.id = b.client_id AND b.status IN ('confirmed', 'completed')
  LEFT JOIN service_providers sp ON b.provider_id = sp.id
  LEFT JOIN provider_services ps ON sp.id = ps.provider_id
  LEFT JOIN categories c ON ps.category_id = c.id
  WHERE u.user_type = 'client'
    AND u.id NOT IN (SELECT user_id FROM user_profiles)
  GROUP BY u.id;
END //

DELIMITER ;