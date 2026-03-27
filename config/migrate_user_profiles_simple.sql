-- Migration: Create user_profiles table for user metrics tracking
-- This table stores aggregated user behavior data for personalization

CREATE TABLE IF NOT EXISTS `user_profiles` (
    `user_id` INT PRIMARY KEY,
    `user_avg_price` DECIMAL(10,2) DEFAULT 0,
    `user_avg_response_time` FLOAT DEFAULT 24,
    `user_total_bookings` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create an index on user_total_bookings for faster queries
CREATE INDEX IF NOT EXISTS idx_user_total_bookings ON user_profiles(user_total_bookings);

-- Insert initial data for existing users based on their booking history
INSERT IGNORE INTO `user_profiles` (`user_id`, `user_avg_price`, `user_avg_response_time`, `user_total_bookings`)
SELECT
  u.id as user_id,
  COALESCE(AVG(b.amount), 0) as user_avg_price,
  COALESCE(AVG(CASE WHEN b.responded_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at) ELSE 24 END), 24) as user_avg_response_time,
  COUNT(b.id) as user_total_bookings
FROM users u
LEFT JOIN bookings b ON u.id = b.client_id AND b.status IN ('confirmed', 'completed')
WHERE u.user_type = 'client'
GROUP BY u.id;

-- Create a stored procedure to update user profiles after booking completion
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS update_user_profile_on_booking(IN p_user_id INT, IN p_booking_amount DECIMAL)
BEGIN
  DECLARE user_exists INT;
  
  -- Check if user profile exists
  SELECT COUNT(*) INTO user_exists FROM user_profiles WHERE user_id = p_user_id;
  
  IF user_exists > 0 THEN
    -- Update existing profile
    UPDATE user_profiles
    SET 
      user_total_bookings = (SELECT COUNT(*) FROM bookings WHERE client_id = p_user_id AND status IN ('confirmed', 'completed')),
      user_avg_price = (SELECT COALESCE(AVG(amount), 0) FROM bookings WHERE client_id = p_user_id AND status IN ('confirmed', 'completed')),
      user_avg_response_time = (SELECT COALESCE(AVG(CASE WHEN responded_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, responded_at) ELSE 24 END), 24) FROM bookings WHERE client_id = p_user_id AND status IN ('confirmed', 'completed')),
      updated_at = CURRENT_TIMESTAMP
    WHERE user_id = p_user_id;
  ELSE
    -- Insert new profile
    INSERT INTO user_profiles (user_id, user_avg_price, user_avg_response_time, user_total_bookings)
    VALUES (p_user_id, p_booking_amount, 24, 1);
  END IF;
END //

DELIMITER ;
