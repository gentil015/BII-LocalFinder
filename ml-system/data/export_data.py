"""
export_data.py
------------------
Exports a fresh training dataset from MySQL into a CSV file.

Usage:
    python export_data.py

The output is written to `data/ml_interactions.csv` by default.
"""

import os
import sys
from dotenv import load_dotenv
import pandas as pd
from utils.db_connection import get_connection

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_DIR = os.path.dirname(BASE_DIR)

ENV_PATH = os.path.join(ROOT_DIR, ".env")
load_dotenv(ENV_PATH)

RAW_OUTPUT_PATH = os.getenv("DATA_PATH", "data/ml_interactions.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(ROOT_DIR, RAW_OUTPUT_PATH))

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
        up.user_total_bookings,
        (SELECT COUNT(*) FROM bookings b3 WHERE b3.client_id = b.client_id),
        0
    ) AS user_total_bookings,
    COALESCE(
        up.user_avg_response_time,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, b3.created_at, b3.responded_at))
         FROM bookings b3
         WHERE b3.client_id = b.client_id AND b3.responded_at IS NOT NULL),
        24.0
    ) AS user_avg_response_time,
    CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END AS hired
FROM bookings b
JOIN service_providers sp ON sp.id = b.provider_id
LEFT JOIN user_profiles up ON up.user_id = b.client_id
WHERE b.status IN ('completed', 'cancelled', 'confirmed', 'pending')

UNION ALL

SELECT
    cl.id AS event_id,
    cl.user_id AS user_id,
    cl.target_id AS provider_id,
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = cl.target_id), 0) AS views,
    COALESCE((SELECT COUNT(*) FROM click_logs cl2 WHERE cl2.target_type = 'provider' AND cl2.target_id = cl.target_id), 0) AS clicks,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = sp.user_id), 0) AS messages,
    COALESCE(sp.average_rating, 0.0) AS rating,
    COALESCE((SELECT AVG(ps.price) FROM provider_services ps WHERE ps.provider_id = cl.target_id AND ps.is_available = 1), 0.0) AS price,
    COALESCE((SELECT AVG(TIMESTAMPDIFF(HOUR, b2.created_at, b2.responded_at))
             FROM bookings b2
             WHERE b2.provider_id = cl.target_id AND b2.responded_at IS NOT NULL), 24.0) AS avg_response_time,
    COALESCE(
        up.user_avg_price,
        (SELECT AVG(amount) FROM bookings b3 WHERE b3.client_id = cl.user_id AND b3.amount IS NOT NULL),
        0.0
    ) AS user_avg_price,
    COALESCE(
        up.user_total_bookings,
        (SELECT COUNT(*) FROM bookings b3 WHERE b3.client_id = cl.user_id),
        0
    ) AS user_total_bookings,
    COALESCE(
        up.user_avg_response_time,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, b3.created_at, b3.responded_at))
         FROM bookings b3
         WHERE b3.client_id = cl.user_id AND b3.responded_at IS NOT NULL),
        24.0
    ) AS user_avg_response_time,
    0 AS hired
FROM click_logs cl
JOIN service_providers sp ON sp.id = cl.target_id
LEFT JOIN user_profiles up ON up.user_id = cl.user_id
WHERE cl.target_type = 'provider'
  AND cl.user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM bookings b2
      WHERE b2.client_id = cl.user_id
        AND b2.provider_id = cl.target_id
        AND b2.created_at > cl.created_at
  )
ORDER BY event_id DESC;
"""

REQUIRED_COLUMNS = [
    "event_id",
    "user_id",
    "provider_id",
    "views",
    "clicks",
    "messages",
    "rating",
    "price",
    "avg_response_time",
    "user_avg_price",
    "user_avg_response_time",
    "user_total_bookings",
    "hired",
]


def ensure_output_dir(path: str):
    directory = os.path.dirname(path)
    if directory and not os.path.exists(directory):
        os.makedirs(directory, exist_ok=True)


def fetch_training_data() -> pd.DataFrame:
    conn = get_connection()
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(SQL)
        rows = cursor.fetchall()
        cursor.close()
    finally:
        conn.close()

    if not rows:
        sys.exit("[ERROR] No booking rows were returned from the database. Check your booking data.")

    df = pd.DataFrame(rows)
    missing = [col for col in REQUIRED_COLUMNS if col not in df.columns]
    if missing:
        sys.exit(f"[ERROR] Missing required columns from query result: {missing}")

    return df[REQUIRED_COLUMNS]


def save_csv(df: pd.DataFrame, path: str):
    ensure_output_dir(path)
    df.to_csv(path, index=False)
    print(f"[INFO] Training dataset written to: {path}")


def main():
    print("[START] Exporting training dataset from MySQL")
    df = fetch_training_data()
    save_csv(df, OUTPUT_PATH)
    print(f"[DONE] Export complete — {len(df)} rows exported.")


if __name__ == "__main__":
    main()
