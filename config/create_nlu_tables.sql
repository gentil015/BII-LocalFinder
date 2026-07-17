-- NLU Classification Logging Tables
-- Add these tables to track NLU predictions and improve the model

-- Table for search query classifications
CREATE TABLE IF NOT EXISTS nlu_classifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    query TEXT NOT NULL,
    service_category VARCHAR(100) NOT NULL,
    confidence FLOAT NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    user_id BIGINT,
    ip_address VARCHAR(45),
    session_id VARCHAR(255),
    was_helpful BOOLEAN,
    was_helpful_timestamp TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_category (service_category),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- Table for booking classification records
CREATE TABLE IF NOT EXISTS nlu_booking_classifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    description TEXT NOT NULL,
    service_category VARCHAR(100) NOT NULL,
    confidence FLOAT NOT NULL,
    location VARCHAR(255),
    user_id BIGINT,
    booking_id BIGINT,
    was_correct BOOLEAN,
    was_correct_timestamp TIMESTAMP NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_category (service_category),
    INDEX idx_booking_id (booking_id),
    INDEX idx_created_at (created_at)
);

-- Table for NLU model performance metrics
CREATE TABLE IF NOT EXISTS nlu_performance (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    metric_name VARCHAR(100),
    metric_value FLOAT,
    service_category VARCHAR(100),
    period_start DATE,
    period_end DATE,
    total_samples INT,
    accuracy FLOAT,
    precision FLOAT,
    recall FLOAT,
    f1_score FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_name (metric_name),
    INDEX idx_period (period_start, period_end)
);

-- Table to track user feedback on NLU predictions
CREATE TABLE IF NOT EXISTS nlu_user_feedback (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    classification_id BIGINT,
    user_id BIGINT,
    original_text TEXT,
    predicted_service VARCHAR(100),
    corrected_service VARCHAR(100),
    feedback_type ENUM('correct', 'incorrect', 'ambiguous') DEFAULT 'incorrect',
    feedback_text TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_classification_id (classification_id)
);

-- Add these columns to existing tables if needed
-- ALTER TABLE bookings ADD COLUMN nlu_detected_service VARCHAR(100);
-- ALTER TABLE bookings ADD COLUMN nlu_confidence FLOAT;
-- ALTER TABLE search_queries ADD COLUMN nlu_classification_id BIGINT;
