"""
data/export_user_engagement_data.py
-----------------------------------
Exports training dataset for the User Engagement Model from MySQL.

This creates data for predicting user engagement patterns.
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

RAW_OUTPUT_PATH = os.getenv("USER_ENGAGEMENT_DATA_PATH", "data/user_engagement_data.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_OUTPUT_PATH))

SQL = """
SELECT
    ev.user_id,
    ev.session_id,
    ev.entity_id AS provider_id,
    CONCAT('/provider/', ev.entity_id) AS page_url,
    ev.event_type,
    ev.entity_type AS interaction_type,
    ev.created_at AS event_time,

    -- User profile features
    DATEDIFF(NOW(), u.created_at) AS account_age_days,
    CASE WHEN u.is_verified = 1 THEN 1 ELSE 0 END AS is_verified,
    COALESCE(up.user_total_bookings, 0) AS total_bookings,
    COALESCE((SELECT COUNT(*) FROM reviews r WHERE r.client_id = ev.user_id), 0) AS total_reviews_written,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE m.sender_id = ev.user_id), 0) AS total_messages_sent,

    -- Provider context features
    COALESCE(sp.average_rating, 0.0) AS provider_rating,
    COALESCE(sp.hourly_rate, 0.0) AS provider_price,
    COALESCE(sp.experience_years, 0) AS provider_experience_years,
    COALESCE(sp.is_verified, 0) AS provider_is_verified,
    COALESCE(sp.is_featured, 0) AS provider_is_featured,

    -- Interaction history
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.user_id = ev.user_id AND pv.provider_id = ev.entity_id AND pv.viewed_at < ev.created_at), 0) AS previous_views_of_provider,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE ((m.sender_id = ev.user_id AND m.receiver_id = sp.user_id) OR (m.sender_id = sp.user_id AND m.receiver_id = ev.user_id)) AND m.created_at < ev.created_at), 0) AS previous_messages_with_provider,
    COALESCE((SELECT DATEDIFF(NOW(), MIN(ev2.created_at)) FROM event_logs ev2 WHERE ev2.user_id = ev.user_id AND ev2.entity_type = 'provider' AND ev2.entity_id = ev.entity_id), 0) AS days_since_first_interaction,
    COALESCE((SELECT COUNT(*) FROM event_logs ev3 WHERE ev3.user_id = ev.user_id AND ev3.entity_type = 'provider' AND ev3.entity_id = ev.entity_id AND ev3.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS interaction_frequency,

    -- Session context
    HOUR(ev.created_at) AS time_of_day,
    DAYOFWEEK(ev.created_at) AS day_of_week,
    CASE WHEN DAYOFWEEK(ev.created_at) IN (1, 7) THEN 1 ELSE 0 END AS is_weekend,
    COALESCE(TIMESTAMPDIFF(MINUTE, us.login_time, IFNULL(us.logout_time, NOW())), 0) AS session_duration_minutes,
    COALESCE((SELECT COUNT(*) FROM event_logs evs WHERE evs.session_id = ev.session_id), 1) AS pages_viewed_in_session,
    COALESCE((SELECT COUNT(*) FROM search_logs sl WHERE sl.session_id = ev.session_id), 0) AS search_queries_in_session,

    -- Platform engagement history
    COALESCE(ue_stats.avg_session_duration, 0.0) AS avg_session_duration,
    COALESCE(ue_stats.pages_per_session, 1.0) AS pages_per_session,
    CASE WHEN ue_stats.total_sessions > 1 THEN 1 ELSE 0 END AS return_visitor,
    COALESCE(ue_stats.days_since_last_visit, 0) AS days_since_last_visit,
    COALESCE(ue_stats.sessions_last_30d, 0) AS total_sessions_last_30d,

    -- Target: whether user will engage further (view or book within next 24 hours)
    CASE
        WHEN EXISTS(
            SELECT 1 FROM event_logs ev4
            WHERE ev4.user_id = ev.user_id
              AND ev4.entity_type = 'provider'
              AND ev4.entity_id = ev.entity_id
              AND ev4.created_at > ev.created_at
              AND ev4.created_at <= DATE_ADD(ev.created_at, INTERVAL 24 HOUR)
              AND ev4.event_type IN ('provider_view', 'provider_click')
        ) THEN 1
        WHEN EXISTS(
            SELECT 1 FROM bookings b
            WHERE b.client_id = ev.user_id
              AND b.provider_id = ev.entity_id
              AND b.created_at > ev.created_at
              AND b.created_at <= DATE_ADD(ev.created_at, INTERVAL 24 HOUR)
        ) THEN 1
        ELSE 0
    END AS will_engage

FROM event_logs ev
JOIN users u ON ev.user_id = u.id
LEFT JOIN service_providers sp ON ev.entity_type = 'provider' AND ev.entity_id = sp.id
LEFT JOIN user_profiles up ON ev.user_id = up.user_id
LEFT JOIN user_sessions us ON ev.session_id = us.session_id
LEFT JOIN (
    SELECT
        user_id,
        AVG(TIMESTAMPDIFF(MINUTE, login_time, IFNULL(logout_time, NOW()))) AS avg_session_duration,
        1.0 AS pages_per_session,
        COUNT(DISTINCT session_id) AS total_sessions,
        DATEDIFF(NOW(), MAX(login_time)) AS days_since_last_visit,
        SUM(CASE WHEN login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS sessions_last_30d
    FROM user_sessions
    GROUP BY user_id
) ue_stats ON ue_stats.user_id = ev.user_id
WHERE ev.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
  AND ev.entity_type = 'provider'
ORDER BY ev.created_at DESC
"""

def balance_dataset(df):
    """Balance the dataset to have roughly equal positive and negative examples."""
    positive = df[df['will_engage'] == 1]
    negative = df[df['will_engage'] == 0]

    print(f"[INFO] Original distribution: {len(positive)} positive, {len(negative)} negative")

    # Sample negative examples to balance (but keep some imbalance as engagement is rarer)
    if len(negative) > len(positive) * 2:
        negative_sampled = negative.sample(n=len(positive) * 2, random_state=42)
        df_balanced = pd.concat([positive, negative_sampled])
    else:
        df_balanced = df

    print(f"[INFO] Balanced distribution: {len(df_balanced[df_balanced['will_engage'] == 1])} positive, {len(df_balanced[df_balanced['will_engage'] == 0])} negative")

    return df_balanced.sample(frac=1, random_state=42).reset_index(drop=True)  # Shuffle

def main():
    print("[INFO] Exporting user engagement training data...")

    try:
        conn = get_connection()
        df = pd.read_sql(SQL, conn)
        conn.close()

        print(f"[INFO] Retrieved {len(df)} user engagement records")

        # Basic data validation
        if len(df) == 0:
            sys.exit("[ERROR] No data retrieved from database")

        # Fill nulls with sensible defaults
        df = df.fillna({
            'account_age_days': 0, 'is_verified': 0,
            'total_bookings': 0, 'total_reviews_written': 0, 'total_messages_sent': 0,
            'provider_rating': 0.0, 'provider_price': 0.0, 'provider_response_time': 60.0,
            'provider_experience_years': 0, 'provider_is_verified': 0, 'provider_is_featured': 0,
            'previous_views_of_provider': 0, 'previous_messages_with_provider': 0,
            'days_since_first_interaction': 0, 'interaction_frequency': 0,
            'time_of_day': 12, 'day_of_week': 1, 'is_weekend': 0,
            'session_duration_minutes': 0, 'pages_viewed_in_session': 1,
            'search_queries_in_session': 0, 'avg_session_duration': 0.0,
            'pages_per_session': 1.0, 'return_visitor': 0,
            'days_since_last_visit': 0, 'total_sessions_last_30d': 0
        })

        # Balance the dataset
        df = balance_dataset(df)

        # Save to CSV
        os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
        df.to_csv(OUTPUT_PATH, index=False)

        print(f"[SUCCESS] Data exported to {OUTPUT_PATH}")
        print(f"[INFO] Shape: {df.shape}")
        print(f"[INFO] Engagement rate: {df['will_engage'].mean():.3f}")

    except Exception as e:
        sys.exit(f"[ERROR] Failed to export data: {e}")

if __name__ == "__main__":
    main()