"""
data/export_personalization_data.py
-----------------------------------
Exports training dataset for the Personalization Model from MySQL.

This creates data for predicting user preferences for providers.
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

RAW_OUTPUT_PATH = os.getenv("PERSONALIZATION_DATA_PATH", "data/personalization_data.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_OUTPUT_PATH))

SQL = """
SELECT
    ev.user_id,
    ev.entity_id AS provider_id,
    ev.event_type AS interaction_type,
    CASE
        WHEN ev.event_type = 'provider_view' THEN 0.3
        WHEN ev.event_type = 'provider_click' THEN 0.8
        WHEN ev.event_type = 'search' THEN 0.2
        ELSE 0.5
    END AS interaction_score,
    ev.created_at AS interaction_time,

    -- Provider features
    sp.average_rating AS rating,
    COALESCE(sp.hourly_rate, 0.0) AS price,
    0.0 AS avg_response_time,
    COALESCE(sp.experience_years, 0) AS experience_years,
    COALESCE(sp.is_verified, 0) AS is_verified,
    COALESCE(sp.is_featured, 0) AS is_featured,
    0.0 AS completion_rate,

    -- User historical behavior
    COALESCE(up.user_avg_price, 0.0) AS user_avg_price_paid,
    COALESCE(up.user_avg_response_time, 24.0) AS user_preferred_response_time,
    COALESCE(up.user_total_bookings, 0) AS user_total_bookings,
    0.0 AS user_category_preference_score,

    -- Interaction history between this user and provider
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.user_id = ev.user_id AND pv.provider_id = ev.entity_id), 0) AS user_provider_interaction_count,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE (m.sender_id = ev.user_id AND m.receiver_id = sp.user_id) OR (m.sender_id = sp.user_id AND m.receiver_id = ev.user_id)), 0) AS user_provider_message_count,
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.user_id = ev.user_id AND pv.provider_id = ev.entity_id), 0) AS user_provider_view_count,
    COALESCE((SELECT DATEDIFF(NOW(), MIN(ev2.created_at)) FROM event_logs ev2 WHERE ev2.user_id = ev.user_id AND ev2.entity_type = 'provider' AND ev2.entity_id = ev.entity_id), 0) AS days_since_last_interaction,

    -- Target: whether user prefers this provider (based on booking or provider engagement)
    CASE
        WHEN EXISTS(SELECT 1 FROM bookings b WHERE b.client_id = ev.user_id AND b.provider_id = ev.entity_id AND b.status = 'completed') THEN 1
        WHEN ev.event_type = 'provider_click' THEN 1
        WHEN (SELECT COUNT(*) FROM event_logs ev3 WHERE ev3.user_id = ev.user_id AND ev3.entity_type = 'provider' AND ev3.entity_id = ev.entity_id AND ev3.event_type IN ('provider_view', 'provider_click')) >= 3 THEN 1
        ELSE 0
    END AS user_preference

FROM event_logs ev
JOIN service_providers sp ON ev.entity_type = 'provider' AND ev.entity_id = sp.id
LEFT JOIN user_profiles up ON ev.user_id = up.user_id
WHERE ev.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  AND ev.entity_type = 'provider'
ORDER BY ev.created_at DESC
"""

def balance_dataset(df):
    """Balance the dataset to have roughly equal positive and negative examples."""
    positive = df[df['user_preference'] == 1]
    negative = df[df['user_preference'] == 0]

    print(f"[INFO] Original distribution: {len(positive)} positive, {len(negative)} negative")

    # If we have more negative examples, sample them down
    if len(negative) > len(positive) * 1.5:
        negative_sampled = negative.sample(n=len(positive) * 1.2, random_state=42)
        df_balanced = pd.concat([positive, negative_sampled])
    # If we have more positive examples, sample them down
    elif len(positive) > len(negative) * 1.5:
        positive_sampled = positive.sample(n=len(negative) * 1.2, random_state=42)
        df_balanced = pd.concat([positive_sampled, negative])
    else:
        df_balanced = df

    print(f"[INFO] Balanced distribution: {len(df_balanced[df_balanced['user_preference'] == 1])} positive, {len(df_balanced[df_balanced['user_preference'] == 0])} negative")

    return df_balanced.sample(frac=1, random_state=42).reset_index(drop=True)  # Shuffle

def main():
    print("[INFO] Exporting personalization training data...")

    try:
        conn = get_connection()
        df = pd.read_sql(SQL, conn)
        conn.close()

        print(f"[INFO] Retrieved {len(df)} user interaction records")

        # Basic data validation
        if len(df) == 0:
            sys.exit("[ERROR] No data retrieved from database")

        # Fill nulls with sensible defaults
        df = df.fillna({
            'rating': 0.0, 'price': 0.0, 'avg_response_time': 60.0,
            'experience_years': 0, 'is_verified': 0, 'is_featured': 0,
            'completion_rate': 0.0, 'avg_response_time': 0.0,
            'user_avg_price_paid': 0.0, 'user_preferred_response_time': 24.0,
            'user_total_bookings': 0, 'user_category_preference_score': 0.0,
            'user_provider_interaction_count': 0, 'user_provider_message_count': 0,
            'user_provider_view_count': 0, 'days_since_last_interaction': 0
        })

        # Balance the dataset
        df = balance_dataset(df)

        # Save to CSV
        os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
        df.to_csv(OUTPUT_PATH, index=False)

        print(f"[SUCCESS] Data exported to {OUTPUT_PATH}")
        print(f"[INFO] Shape: {df.shape}")
        print(f"[INFO] Positive preference ratio: {df['user_preference'].mean():.3f}")

    except Exception as e:
        sys.exit(f"[ERROR] Failed to export data: {e}")

if __name__ == "__main__":
    main()