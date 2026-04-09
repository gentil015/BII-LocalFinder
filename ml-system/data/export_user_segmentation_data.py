"""
data/export_user_segmentation_data.py
-------------------------------------
Exports training data for User Segmentation Model.

This script extracts user behavior data for clustering users into segments
based on their booking patterns, preferences, and engagement metrics.
"""

import argparse
import json
import os
import sys
import pandas as pd
from datetime import datetime
from dotenv import load_dotenv
import mysql.connector

# ── Paths ─────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(BASE_DIR)  # Add parent directory to Python path

ROOT_DIR = os.path.dirname(BASE_DIR)

# Load environment variables
ENV_PATH = os.path.join(BASE_DIR, ".env")
load_dotenv(ENV_PATH)

RAW_CSV_PATH = os.getenv("USER_SEGMENTATION_DATA_PATH", "data/user_segmentation_data.csv")
CSV_PATH = RAW_CSV_PATH if os.path.isabs(RAW_CSV_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_CSV_PATH))

# Feature columns for the segmentation model
FEATURE_COLUMNS = [
    "total_bookings", "completed_bookings", "cancelled_bookings", "avg_booking_value", "total_spent",
    "service_diversity", "reviews_written", "avg_rating_given", "favorites_count",
    "account_age_days", "last_activity_days", "completion_rate", "spending_rate", "booking_frequency",
    "engagement_score", "profile_completeness", "response_rate", "price_sensitivity",
    "location_diversity", "peak_booking_hour", "weekend_bookings_ratio", "seasonal_pattern",
    "login_frequency", "search_queries_count", "provider_views_count",
]

def get_db_connection():
    """Establish database connection."""
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        user=os.getenv("DB_USER", ""),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "bii_localfinder")
    )

def export_user_segmentation_data(limit=None):
    """Export user segmentation training data."""

    db = get_db_connection()

    # Simplified query focusing on available data
    query = """
    SELECT
        u.id as user_id,
        u.full_name,
        u.created_at as account_created,

        -- Booking metrics
        COALESCE(b.total_bookings, 0) as total_bookings,
        COALESCE(b.completed_bookings, 0) as completed_bookings,
        COALESCE(b.cancelled_bookings, 0) as cancelled_bookings,
        COALESCE(b.avg_booking_value, 0) as avg_booking_value,
        COALESCE(b.total_spent, 0) as total_spent,

        -- Service diversity (simplified)
        COALESCE(b.service_diversity, 1) as service_diversity,

        -- Engagement metrics
        COALESCE(r.reviews_written, 0) as reviews_written,
        COALESCE(r.avg_rating_given, 0) as avg_rating_given,
        COALESCE(f.favorites_count, 0) as favorites_count,

        -- Account metrics
        DATEDIFF(CURDATE(), u.created_at) as account_age_days,
        CASE WHEN u.last_login IS NOT NULL THEN DATEDIFF(CURDATE(), u.last_login) ELSE 999 END as last_activity_days,

        -- Derived metrics
        CASE WHEN COALESCE(b.total_bookings, 0) > 0 THEN COALESCE(b.completed_bookings, 0) / b.total_bookings ELSE 0 END as completion_rate,
        COALESCE(b.total_spent, 0) / GREATEST(DATEDIFF(CURDATE(), u.created_at), 1) as spending_rate,
        CASE WHEN COALESCE(b.total_bookings, 0) > 0 THEN b.total_bookings / GREATEST(DATEDIFF(CURDATE(), u.created_at), 1) * 30 ELSE 0 END as booking_frequency

    FROM users u

    -- Booking metrics subquery
    LEFT JOIN (
        SELECT
            client_id,
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            AVG(COALESCE(amount, 0)) as avg_booking_value,
            SUM(COALESCE(amount, 0)) as total_spent,
            COUNT(DISTINCT provider_id) as service_diversity
        FROM bookings
        GROUP BY client_id
    ) b ON u.id = b.client_id

    -- Reviews subquery
    LEFT JOIN (
        SELECT
            client_id,
            COUNT(*) as reviews_written,
            AVG(rating) as avg_rating_given
        FROM reviews
        GROUP BY client_id
    ) r ON u.id = r.client_id

    -- Favorites subquery
    LEFT JOIN (
        SELECT
            client_id,
            COUNT(*) as favorites_count
        FROM favorites
        GROUP BY client_id
    ) f ON u.id = f.client_id

    WHERE u.user_type = 'client'
    AND u.is_active = 1
    """

    if limit:
        query += f" LIMIT {limit}"

    try:
        print("[INFO] Executing query to export user segmentation data...")
        df = pd.read_sql(query, db)

        # Add derived features
        df['engagement_score'] = (
            (df['reviews_written'] > 0).astype(int) * 0.3 +
            (df['favorites_count'] > 0).astype(int) * 0.2 +
            (df['total_bookings'] > 0).astype(int) * 0.5
        )

        # Fill missing values
        df = df.fillna({
            'total_bookings': 0,
            'completed_bookings': 0,
            'cancelled_bookings': 0,
            'avg_booking_value': 0,
            'total_spent': 0,
            'service_diversity': 1,
            'reviews_written': 0,
            'avg_rating_given': 0,
            'favorites_count': 0,
            'account_age_days': 1,
            'last_activity_days': 999,
            'completion_rate': 0,
            'spending_rate': 0,
            'booking_frequency': 0,
            'engagement_score': 0
        })

        # Add some default values for features that might be missing
        df['profile_completeness'] = 0.5  # Default medium completeness
        df['response_rate'] = 0.5  # Default medium response rate
        df['price_sensitivity'] = 0.5  # Default medium sensitivity
        df['location_diversity'] = 1  # Default low diversity
        df['peak_booking_hour'] = 12  # Default noon
        df['weekend_bookings_ratio'] = 0.3  # Default 30%
        df['seasonal_pattern'] = 0.5  # Default neutral
        df['login_frequency'] = 0.1  # Default low frequency
        df['search_queries_count'] = 5  # Default few searches
        df['provider_views_count'] = 10  # Default few views

        # Select final features for segmentation
        df = df[['user_id'] + FEATURE_COLUMNS]

        # Save to CSV
        df.to_csv(CSV_PATH, index=False)
        print(f"[SUCCESS] Exported {len(df)} user records to {CSV_PATH}")

        return df

    except Exception as e:
        print(f"[ERROR] Failed to export data: {e}")
        raise
    finally:
        db.close()

def main():
    parser = argparse.ArgumentParser(description="Export user segmentation training data")
    parser.add_argument("--limit", type=int, help="Limit number of users to export")
    parser.add_argument("--output", type=str, help="Output CSV file path")

    args = parser.parse_args()

    if args.output:
        global CSV_PATH
        CSV_PATH = args.output

    try:
        df = export_user_segmentation_data(limit=args.limit)

        # Print summary statistics
        print("\n📊 Data Summary:")
        print(f"Total users: {len(df)}")
        print(f"Users with bookings: {(df['total_bookings'] > 0).sum()}")
        print(f"Average bookings per user: {df['total_bookings'].mean():.2f}")

    except Exception as e:
        print(f"[ERROR] Export failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()