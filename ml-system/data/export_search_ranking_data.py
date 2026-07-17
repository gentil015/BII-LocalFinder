"""
data/export_search_ranking_data.py
----------------------------------
Exports training dataset for the Search Ranking Model from MySQL.

This creates data for predicting provider relevance in search results.
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

RAW_OUTPUT_PATH = os.getenv("SEARCH_RANKING_DATA_PATH", "data/search_ranking_data.csv")
OUTPUT_PATH = RAW_OUTPUT_PATH if os.path.isabs(RAW_OUTPUT_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_OUTPUT_PATH))

SQL = """
SELECT
    sq.id AS search_id,
    sq.user_id,
    sq.search_query,
    sq.search_type,
    sq.filters,
    sq.results_count,
    sq.searched_at AS search_time,
    cl.target_id AS provider_id,
    1 AS was_clicked,
    1 AS click_position,
    0 AS time_spent_seconds,

    -- Provider features
    sp.average_rating AS rating,
    COALESCE((SELECT AVG(ps.price) FROM provider_services ps WHERE ps.provider_id = cl.target_id AND ps.is_available = 1), 0.0) AS price,
    COALESCE(sp.hourly_rate, 0.0) AS hourly_rate,
    COALESCE(sp.is_verified, 0) AS is_verified,
    COALESCE(sp.is_featured, 0) AS is_featured,
    COALESCE(sp.experience_years, 0) AS experience_years,
    0.0 AS completion_rate,

    -- Provider activity (last 30 days)
    COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = cl.target_id AND pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS views,
    COALESCE((SELECT COUNT(*) FROM click_logs cl2 WHERE cl2.target_type = 'provider' AND cl2.target_id = cl.target_id AND cl2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS clicks,
    COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = sp.user_id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS messages,

    -- Search context features
    LENGTH(sq.search_query) AS search_query_length,
    CASE
        WHEN sq.search_type = 'providers' AND JSON_UNQUOTE(JSON_EXTRACT(sq.filters, '$.location')) IS NOT NULL
             AND JSON_UNQUOTE(JSON_EXTRACT(sq.filters, '$.location')) != ''
             AND sp.location LIKE CONCAT('%', JSON_UNQUOTE(JSON_EXTRACT(sq.filters, '$.location')), '%')
        THEN 1 ELSE 0
    END AS location_match,
    0 AS category_match,
    0 AS price_match,
    CASE WHEN sp.availability = 'available' THEN 1 ELSE 0 END AS availability_match,

    -- User context features
    COALESCE((SELECT COUNT(*) FROM search_logs sq2 WHERE sq2.user_id = sq.user_id AND sq2.searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS user_search_frequency,
    0.0 AS user_category_preference,
    '0-50000' AS user_price_range_preference

FROM search_logs sq
JOIN click_logs cl ON sq.session_id = cl.session_id AND cl.target_type = 'provider' AND cl.created_at >= sq.searched_at
LEFT JOIN service_providers sp ON cl.target_id = sp.id
WHERE sq.searched_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
ORDER BY sq.searched_at DESC
"""

def calculate_relevance_score(row):
    """Calculate relevance score based on user interaction."""
    score = 0.0

    # Base score from position (higher positions get higher base scores)
    position_score = max(0, 100 - (row['click_position'] - 1) * 10)
    score += position_score * 0.3

    # Click bonus
    if row['was_clicked']:
        score += 30

    # Time spent bonus (more time = more relevant)
    time_bonus = min(row['time_spent_seconds'] / 60.0 * 10, 20)  # Max 20 points for time
    score += time_bonus

    # Provider quality bonus
    quality_score = (row['rating'] / 5.0) * 20  # Max 20 points for 5-star rating
    score += quality_score

    # Verification bonus
    if row['is_verified']:
        score += 10

    # Featured bonus
    if row['is_featured']:
        score += 15

    # Category/location match bonus
    match_bonus = 0
    if row['category_match']:
        match_bonus += 10
    if row['location_match']:
        match_bonus += 10
    if row['price_match']:
        match_bonus += 5
    score += match_bonus

    return min(100.0, max(0.0, score))

def main():
    print("[INFO] Exporting search ranking training data...")

    try:
        conn = get_connection()
        df = pd.read_sql(SQL, conn)
        conn.close()

        print(f"[INFO] Retrieved {len(df)} search result records")

        # Basic data validation
        if len(df) == 0:
            sys.exit("[ERROR] No data retrieved from database")

        # Calculate relevance scores
        df['relevance_score'] = df.apply(calculate_relevance_score, axis=1)

        # Fill any remaining nulls with defaults
        df = df.fillna({
            'views': 0, 'clicks': 0, 'messages': 0, 'rating': 0.0,
            'is_verified': 0, 'is_featured': 0, 'experience_years': 0,
            'completion_rate': 0.0, 'search_query_length': 0,
            'category_match': 0, 'location_match': 0, 'price_match': 0, 'availability_match': 0,
            'user_search_frequency': 0, 'user_category_preference': 0.0
        })

        # Save to CSV
        os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
        df.to_csv(OUTPUT_PATH, index=False)

        print(f"[SUCCESS] Data exported to {OUTPUT_PATH}")
        print(f"[INFO] Shape: {df.shape}")
        print(f"[INFO] Average relevance score: {df['relevance_score'].mean():.2f}")
        print(f"[INFO] Click-through rate: {df['was_clicked'].mean():.3f}")

    except Exception as e:
        sys.exit(f"[ERROR] Failed to export data: {e}")

if __name__ == "__main__":
    main()