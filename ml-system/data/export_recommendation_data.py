"""
data/export_recommendation_data.py
----------------------------------
Exports training dataset for the Recommendation Model from MySQL.

This creates data for predicting whether a provider will be hired.
"""

import os
import sys
from dotenv import load_dotenv
import pandas as pd
from utils.db_connection import get_connection

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(BASE_DIR)  # Add parent directory to Python path

ROOT_DIR = os.path.dirname(BASE_DIR)

ENV_PATH = os.path.join(BASE_DIR, ".env")
load_dotenv(ENV_PATH)

RAW_OUTPUT_PATH = os.getenv("RECOMMENDATION_DATA_PATH", "data/recommendation_data.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_OUTPUT_PATH))

SQL = """
SELECT
    b.id AS event_id,
    b.client_id AS user_id,
    b.provider_id,
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = b.provider_id), 0) AS views,
    COALESCE((SELECT COUNT(*) FROM click_logs cl WHERE cl.target_type = 'provider' AND cl.target_id = b.provider_id), 0) AS clicks,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = sp.user_id), 0) AS messages,
    COALESCE(sp.average_rating, 0.0) AS rating,
    COALESCE((SELECT AVG(ps.price) FROM provider_services ps WHERE ps.provider_id = b.provider_id AND ps.is_available = 1), 0.0) AS price,
    COALESCE((SELECT AVG(TIMESTAMPDIFF(HOUR, b2.created_at, b2.responded_at))
             FROM bookings b2
             WHERE b2.provider_id = b.provider_id AND b2.responded_at IS NOT NULL), 24.0) AS avg_response_time,
    COALESCE(
        up.user_avg_price,
        (SELECT AVG(amount) FROM bookings b3 WHERE b3.client_id = b.client_id AND b3.amount IS NOT NULL),
        0.0
    ) AS user_avg_price,
    COALESCE(
        up.user_avg_response_time,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, b4.created_at, b4.responded_at))
         FROM bookings b4
         WHERE b4.client_id = b.client_id AND b4.responded_at IS NOT NULL),
        24.0
    ) AS user_avg_response_time,
    COALESCE(
        up.user_total_bookings,
        (SELECT COUNT(*) FROM bookings b5 WHERE b5.client_id = b.client_id),
        0
    ) AS user_total_bookings,
    CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END AS hired
FROM bookings b
JOIN service_providers sp ON b.provider_id = sp.id
LEFT JOIN user_profiles up ON b.client_id = up.user_id
WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  AND b.status IN ('completed', 'cancelled', 'pending')
ORDER BY b.created_at DESC
"""

def main():
    print("[INFO] Exporting recommendation training data...")

    try:
        conn = get_connection()
        df = pd.read_sql(SQL, conn)
        conn.close()

        print(f"[INFO] Retrieved {len(df)} booking records")

        # Basic data validation
        if len(df) == 0:
            sys.exit("[ERROR] No data retrieved from database")

        # Check for required columns
        required_cols = ['provider_id', 'user_id', 'hired']
        missing_cols = [col for col in required_cols if col not in df.columns]
        if missing_cols:
            sys.exit(f"[ERROR] Missing required columns: {missing_cols}")

        # Save to CSV
        os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
        df.to_csv(OUTPUT_PATH, index=False)

        print(f"[SUCCESS] Data exported to {OUTPUT_PATH}")
        print(f"[INFO] Shape: {df.shape}")
        print(f"[INFO] Positive class ratio (hired): {df['hired'].mean():.3f}")

    except Exception as e:
        sys.exit(f"[ERROR] Failed to export data: {e}")

if __name__ == "__main__":
    main()