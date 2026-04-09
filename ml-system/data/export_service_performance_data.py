"""
data/export_service_performance_data.py
-------------------------------------
Exports training dataset for the Service Performance Model from MySQL.

This creates service-level training examples to predict performance for each
provider service based on service metadata, provider context, and recent
booking/review activity.
"""

import os
import sys
from dotenv import load_dotenv
import pandas as pd
from utils.db_connection import get_connection

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(BASE_DIR)

ENV_PATH = os.path.join(BASE_DIR, ".env")
load_dotenv(ENV_PATH)

RAW_OUTPUT_PATH = os.getenv("SERVICE_PERFORMANCE_DATA_PATH", "data/service_performance_data.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_OUTPUT_PATH))

SQL = """
SELECT
    ps.id AS service_id,
    ps.provider_id,
    COALESCE(ps.category_id, 0) AS category_id,
    COALESCE(ps.price, 0.0) AS price,
    COALESCE(ps.is_available, 0) AS is_available,
    COALESCE(ps.is_featured, 0) AS is_featured,
    CASE ps.payment_type
        WHEN 'fixed_price' THEN 0
        WHEN 'hourly_rate' THEN 1
        WHEN 'per_job_estimate' THEN 2
        WHEN 'per_day' THEN 3
        WHEN 'per_service' THEN 4
        WHEN 'base_price' THEN 5
        ELSE 0
    END AS payment_type_code,
    COALESCE(ps.negotiable, 0) AS negotiable,
    COALESCE(ps.min_price, 0.0) AS min_price,
    COALESCE(ps.max_price, 0.0) AS max_price,
    COALESCE(ps.base_price, 0.0) AS base_price,
    LENGTH(COALESCE(ps.description, '')) AS description_length,

    -- Provider context
    COALESCE(sp.average_rating, 0.0) AS provider_average_rating,
    COALESCE(sp.total_reviews, 0) AS provider_total_reviews,
    COALESCE(sp.total_jobs, 0) AS provider_total_jobs,
    COALESCE(sp.hourly_rate, 0.0) AS provider_hourly_rate,
    COALESCE(sp.is_verified, 0) AS provider_is_verified,
    COALESCE(sp.is_featured, 0) AS provider_is_featured,
    COALESCE(sp.experience_years, 0) AS provider_experience_years,
    COALESCE(sp.search_boost, 0) AS provider_search_boost,

    COALESCE(pp.avg_rating, 0.0) AS provider_perf_avg_rating,
    COALESCE(pp.total_bookings, 0) AS provider_perf_total_bookings,
    COALESCE(pp.completed_bookings, 0) AS provider_perf_completed_bookings,
    COALESCE(pp.cancellation_rate, 0.0) AS provider_perf_cancellation_rate,

    -- Service history features (90-day lookback)
    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)), 0) AS service_bookings_90d,
    COALESCE((SELECT AVG(r.rating) FROM reviews r JOIN bookings b2 ON b2.id = r.booking_id WHERE b2.service_id = ps.id AND b2.provider_id = ps.provider_id AND b2.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)), COALESCE(sp.average_rating, 0.0)) AS service_rating_90d,
    COALESCE((SELECT COUNT(*) FROM reviews r JOIN bookings b2 ON b2.id = r.booking_id WHERE b2.service_id = ps.id AND b2.provider_id = ps.provider_id AND b2.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)), 0) AS service_review_count_90d,
    CASE WHEN COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)), 0) > 0
        THEN COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND b.status = 'completed'), 0)
             / COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)), 0)
        ELSE 0
    END AS service_completion_rate_90d,

    -- Provider activity features
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = ps.provider_id AND pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS provider_views_last_30d,
    COALESCE((SELECT COUNT(*) FROM click_logs cl WHERE cl.target_type = 'provider' AND cl.target_id = ps.provider_id AND cl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS provider_clicks_last_30d,
    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS provider_bookings_last_30d,

    -- Targets: current service performance over the last 30 days
    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS predicted_monthly_bookings,
    COALESCE((SELECT AVG(r.rating) FROM reviews r JOIN bookings b2 ON b2.id = r.booking_id WHERE b2.service_id = ps.id AND b2.provider_id = ps.provider_id AND b2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), COALESCE(sp.average_rating, 0.0)) AS predicted_rating,
    COALESCE((SELECT SUM(b.amount) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0.0) AS predicted_revenue,
    CASE WHEN COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) > 0
        THEN COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND b.status = 'completed'), 0)
             / COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.service_id = ps.id AND b.provider_id = ps.provider_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0)
        ELSE 0
    END AS predicted_conversion_rate

FROM provider_services ps
LEFT JOIN service_providers sp ON ps.provider_id = sp.id
LEFT JOIN (
    SELECT p1.*
    FROM provider_performance p1
    JOIN (
        SELECT provider_id, MAX(period_end) AS latest_period_end
        FROM provider_performance
        GROUP BY provider_id
    ) p2 ON p1.provider_id = p2.provider_id AND p1.period_end = p2.latest_period_end
) pp ON pp.provider_id = ps.provider_id
WHERE ps.is_available = 1
ORDER BY ps.provider_id, ps.id
"""


def main():
    print("[INFO] Exporting service performance training data...")

    try:
        conn = get_connection()
        df = pd.read_sql(SQL, conn)
        conn.close()

        print(f"[INFO] Retrieved {len(df)} service records")

        if len(df) == 0:
            sys.exit("[ERROR] No data retrieved from database")

        df = df.fillna({
            'price': 0.0,
            'is_available': 0,
            'is_featured': 0,
            'payment_type_code': 0,
            'negotiable': 0,
            'min_price': 0.0,
            'max_price': 0.0,
            'base_price': 0.0,
            'description_length': 0,
            'provider_average_rating': 0.0,
            'provider_total_reviews': 0,
            'provider_total_jobs': 0,
            'provider_hourly_rate': 0.0,
            'provider_is_verified': 0,
            'provider_is_featured': 0,
            'provider_experience_years': 0,
            'provider_search_boost': 0,
            'provider_perf_avg_rating': 0.0,
            'provider_perf_total_bookings': 0,
            'provider_perf_completed_bookings': 0,
            'provider_perf_cancellation_rate': 0.0,
            'service_bookings_90d': 0,
            'service_rating_90d': 0.0,
            'service_review_count_90d': 0,
            'service_completion_rate_90d': 0.0,
            'provider_views_last_30d': 0,
            'provider_clicks_last_30d': 0,
            'provider_bookings_last_30d': 0,
            'predicted_monthly_bookings': 0,
            'predicted_rating': 0.0,
            'predicted_revenue': 0.0,
            'predicted_conversion_rate': 0.0,
        })

        os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
        df.to_csv(OUTPUT_PATH, index=False)

        print(f"[SUCCESS] Data exported to {OUTPUT_PATH}")
        print(f"[INFO] Shape: {df.shape}")
        print(f"[INFO] Average predicted monthly bookings: {df['predicted_monthly_bookings'].mean():.2f}")

    except Exception as e:
        sys.exit(f"[ERROR] Failed to export data: {e}")


if __name__ == "__main__":
    main()
