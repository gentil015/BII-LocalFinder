-- ============================================================
-- BII LocalFinder Subscription System Migration
-- Complete plan features implementation
-- ============================================================

-- Drop existing tables if they exist (for fresh install)
DROP TABLE IF EXISTS provider_subscriptions;
DROP TABLE IF EXISTS plans;

-- Create plans table with all features
CREATE TABLE plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    monthly_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    yearly_price DECIMAL(10, 2) DEFAULT NULL,
    billing_cycle ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    
    -- Service & Portfolio Limits
    service_limit INT NOT NULL DEFAULT 3,
    photo_limit INT NOT NULL DEFAULT 3,
    
    -- Analytics Levels
    analytics_level ENUM('none', 'basic', 'better', 'advanced') NOT NULL DEFAULT 'basic',
    
    -- AI Features
    ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ai_title_suggestion TINYINT(1) NOT NULL DEFAULT 0,
    ai_description_generator TINYINT(1) NOT NULL DEFAULT 0,
    ai_pricing_recommendation TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Ranking & Visibility
    ranking_boost_days INT NOT NULL DEFAULT 14,
    priority_ranking TINYINT(1) NOT NULL DEFAULT 0,
    higher_search_ranking TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Verification & Badges
    verified_badge_request TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Payout Features
    faster_payout TINYINT(1) NOT NULL DEFAULT 0,
    instant_payout_priority TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Insights & Reports
    basic_lead_insight TINYINT(1) NOT NULL DEFAULT 0,
    customer_repeat_insight TINYINT(1) NOT NULL DEFAULT 0,
    export_reports TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Boost Features
    boost_any_time TINYINT(1) NOT NULL DEFAULT 0,
    boost_fair_limit INT NOT NULL DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create provider_subscriptions table
CREATE TABLE provider_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'grace_period') NOT NULL DEFAULT 'active',
    auto_renew TINYINT(1) NOT NULL DEFAULT 0,
    grace_until DATE DEFAULT NULL,
    last_boost_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
    INDEX idx_provider_id (provider_id),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Insert Complete Plan Definitions
-- ============================================================

-- FREE PLAN (Entry Growth Funnel)
-- Price: 0 RWF
INSERT INTO plans (
    name, price, monthly_price, yearly_price, billing_cycle, service_limit, photo_limit,
    analytics_level, ai_enabled, ai_title_suggestion, ai_description_generator,
    ai_pricing_recommendation, ranking_boost_days, priority_ranking, higher_search_ranking,
    verified_badge_request, faster_payout, instant_payout_priority,
    basic_lead_insight, customer_repeat_insight, export_reports,
    boost_any_time, boost_fair_limit
) VALUES (
    'Free', 0, 0, NULL, 'monthly', 3, 3,
    'basic', 0, 0, 0, 0,
    14, 0, 0,
    0, 0, 0,
    0, 0, 0,
    0, 0
);

-- STANDARD PLAN
-- Price: 4,890 RWF/month
INSERT INTO plans (
    name, price, monthly_price, yearly_price, billing_cycle, service_limit, photo_limit,
    analytics_level, ai_enabled, ai_title_suggestion, ai_description_generator,
    ai_pricing_recommendation, ranking_boost_days, priority_ranking, higher_search_ranking,
    verified_badge_request, faster_payout, instant_payout_priority,
    basic_lead_insight, customer_repeat_insight, export_reports,
    boost_any_time, boost_fair_limit
) VALUES (
    'Standard', 4890, 4890, NULL, 'monthly', 15, 7,
    'better', 0, 1, 0, 0,
    30, 1, 0,
    1, 1, 0,
    1, 0, 0,
    1, 7
);

-- PRO PLAN
-- Price: 14,960 RWF/month
INSERT INTO plans (
    name, price, monthly_price, yearly_price, billing_cycle, service_limit, photo_limit,
    analytics_level, ai_enabled, ai_title_suggestion, ai_description_generator,
    ai_pricing_recommendation, ranking_boost_days, priority_ranking, higher_search_ranking,
    verified_badge_request, faster_payout, instant_payout_priority,
    basic_lead_insight, customer_repeat_insight, export_reports,
    boost_any_time, boost_fair_limit
) VALUES (
    'Pro', 14960, 14960, NULL, 'monthly', 0, 0,
    'advanced', 1, 1, 1, 1,
    999, 1, 1,
    1, 1, 1,
    1, 1, 1,
    1, 30
);

-- ============================================================
-- Add subscription columns to service_providers table
-- ============================================================
-- Note: Run these separately if the column already exists
ALTER TABLE service_providers 
ADD COLUMN subscription_plan_id INT DEFAULT 1,
ADD COLUMN subscription_status ENUM('none', 'active', 'expired', 'cancelled') DEFAULT 'none',
ADD COLUMN last_boost DATETIME DEFAULT NULL,
ADD COLUMN is_featured_provider TINYINT(1) DEFAULT 0;


Add index for faster queries
ALTER TABLE service_providers ADD INDEX idx_subscription (subscription_plan_id);