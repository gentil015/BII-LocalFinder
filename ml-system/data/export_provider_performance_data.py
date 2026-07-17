"""
data/export_provider_performance_data.py
----------------------------------------
Exports training dataset for the Provider Performance Model from MySQL.

This creates data for predicting provider performance metrics.
"""

import os
import sys
from dotenv import load_dotenv
import pandas as pd
import numpy as np
from utils.db_connection import get_connection

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(BASE_DIR)  # Add parent directory to Python path

ROOT_DIR = os.path.dirname(BASE_DIR)

ENV_PATH = os.path.join(BASE_DIR, ".env")
load_dotenv(ENV_PATH)

RAW_OUTPUT_PATH = os.getenv("PROVIDER_PERFORMANCE_DATA_PATH", "data/provider_performance_data.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_OUTPUT_PATH))

SQL = """
SELECT
    sp.id AS provider_id,
    sp.user_id,
    sp.created_at,

    -- Provider profile features
    COALESCE(sp.experience_years, 0) AS experience_years,
    COALESCE(sp.is_verified, 0) AS is_verified,
    COALESCE(sp.is_featured, 0) AS is_featured,
    COALESCE(sp.hourly_rate, 0.0) AS hourly_rate,
    LENGTH(TRIM(sp.working_days)) - LENGTH(REPLACE(TRIM(sp.working_days), ',', '')) + 1 AS working_days_count,
    COALESCE(sp.max_daily_bookings, 5) AS max_daily_bookings,
    CASE WHEN sp.portfolio_enabled = 1 THEN 1 ELSE 0 END AS portfolio_enabled,

    -- Performance history (latest available metrics)
    COALESCE(pp.total_reviews, 0) AS total_reviews,
    COALESCE(pp.avg_rating, 0.0) AS average_rating,
    COALESCE(pp.total_bookings, 0) AS total_jobs_completed,
    COALESCE(pp.avg_response_time_hours * 60, 60.0) AS avg_response_time_minutes,
    COALESCE(pp.on_time_completion_rate, 0.0) AS completion_rate,
    COALESCE(pp.cancellation_rate, 0.0) AS cancellation_rate,

    -- Activity metrics (last 30 days)
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = sp.id AND pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS profile_views_last_30d,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = sp.user_id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS messages_received_last_30d,
    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.provider_id = sp.id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS bookings_last_30d,
    COALESCE((SELECT DATEDIFF(NOW(), MAX(created_at)) FROM bookings b WHERE b.provider_id = sp.id AND b.status = 'completed'), 30) AS days_since_last_booking,
    COALESCE((SELECT DATEDIFF(NOW(), MIN(viewed_at)) FROM provider_views pv WHERE pv.provider_id = sp.id), 0) AS days_active,

    -- Social proof (simplified - you may need to add actual social media follower counts)
    CASE WHEN sp.facebook IS NOT NULL AND sp.facebook != '' THEN GREATEST(0, LENGTH(sp.facebook) - 20) ELSE 0 END AS facebook_followers,
    CASE WHEN sp.instagram IS NOT NULL AND sp.instagram != '' THEN GREATEST(0, LENGTH(sp.instagram) - 20) ELSE 0 END AS instagram_followers,
    CASE WHEN sp.website IS NOT NULL AND sp.website != '' THEN 1 ELSE 0 END AS website_has_content,

    -- Target variables (predicted performance metrics)
    COALESCE(pp.on_time_completion_rate, 0.0) AS predicted_completion_rate,
    COALESCE(pp.avg_rating, 0.0) AS predicted_rating,
    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.provider_id = sp.id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS predicted_monthly_bookings,
    COALESCE(pp.avg_response_time_hours * 60, 60.0) AS predicted_response_time,

    -- Overall performance score (calculated from multiple factors)
    (
        (COALESCE(pp.avg_rating, 0.0) / 5.0) * 30 +  -- Rating component (30%)
        (1 - COALESCE(pp.cancellation_rate, 0.0)) * 25 +  -- Reliability component (25%)
        (COALESCE(pp.on_time_completion_rate, 0.0)) * 20 +        -- Completion component (20%)
        (CASE WHEN COALESCE(sp.is_verified, 0) = 1 THEN 15 ELSE 0 END) +  -- Verification bonus (15%)
        (CASE WHEN COALESCE(sp.is_featured, 0) = 1 THEN 10 ELSE 0 END)    -- Featured bonus (10%)
    ) AS overall_performance_score

FROM service_providers sp
LEFT JOIN (
    SELECT p1.*
    FROM provider_performance p1
    JOIN (
        SELECT provider_id, MAX(period_end) AS latest_period_end
        FROM provider_performance
        GROUP BY provider_id
    ) p2 ON p1.provider_id = p2.provider_id AND p1.period_end = p2.latest_period_end
) pp ON pp.provider_id = sp.id
WHERE sp.is_active = 1
  AND sp.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)  -- Only providers active in last year
  AND COALESCE(pp.total_reviews, 0) > 0  -- Must have some review history
ORDER BY sp.created_at DESC
"""

def main():
    print("[INFO] Exporting provider performance training data...")

    try:
        conn = get_connection()
        df = pd.read_sql(SQL, conn)
        conn.close()

        print(f"[INFO] Retrieved {len(df)} provider records")

        # Basic data validation
        if len(df) == 0:
            sys.exit("[ERROR] No data retrieved from database")

        # Fill any remaining nulls with defaults
        df = df.fillna({
            'experience_years': 0, 'is_verified': 0, 'is_featured': 0,
            'hourly_rate': 0.0, 'working_days_count': 5, 'max_daily_bookings': 5,
            'portfolio_enabled': 0, 'total_reviews': 0, 'average_rating': 0.0,
            'total_jobs_completed': 0, 'avg_response_time_minutes': 60.0,
            'completion_rate': 0.0, 'cancellation_rate': 0.0,
            'profile_views_last_30d': 0, 'messages_received_last_30d': 0,
            'bookings_last_30d': 0, 'days_since_last_booking': 30,
            'days_active': 0, 'facebook_followers': 0,
            'instagram_followers': 0, 'website_has_content': 0
        })

        # Ensure working_days_count is reasonable
        df['working_days_count'] = df['working_days_count'].clip(1, 7)

        # Cap extreme values
        df['hourly_rate'] = df['hourly_rate'].clip(0, 100000)
        df['experience_years'] = df['experience_years'].clip(0, 50)
        df['max_daily_bookings'] = df['max_daily_bookings'].clip(1, 20)

        # Save to CSV
        os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
        df.to_csv(OUTPUT_PATH, index=False)

        print(f"[SUCCESS] Data exported to {OUTPUT_PATH}")
        print(f"[INFO] Shape: {df.shape}")
        print(f"[INFO] Average performance score: {df['overall_performance_score'].mean():.2f}")
        print(f"[INFO] Average completion rate: {df['completion_rate'].mean():.3f}")

    except Exception as e:
        sys.exit(f"[ERROR] Failed to export data: {e}")

if __name__ == "__main__":
    main()