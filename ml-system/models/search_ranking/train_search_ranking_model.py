"""
models/search_ranking/train_search_ranking_model.py
---------------------------------------------------
Trains a Search Ranking Model to predict provider relevance scores
for search queries based on provider features and search context.

This model focuses on ranking providers in search results.
"""

import argparse
import json
import os
import sys
import joblib
import pandas as pd
import numpy as np
from datetime import datetime
from dotenv import load_dotenv
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score

# ── Paths ─────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ROOT_DIR = os.path.dirname(BASE_DIR)
load_dotenv(os.path.join(BASE_DIR, ".env"))

RAW_CSV_PATH = os.getenv("SEARCH_RANKING_DATA_PATH", "data/search_ranking_data.csv")
RAW_MODEL_PATH = os.getenv("SEARCH_RANKING_MODEL_PATH", "models/search_ranking/search_ranking_model.pkl")
RAW_EVAL_PATH = os.getenv("SEARCH_RANKING_EVAL_PATH", "models/search_ranking/search_ranking_evaluation.json")
CSV_PATH = RAW_CSV_PATH if os.path.isabs(RAW_CSV_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_CSV_PATH))
MODEL_PATH = RAW_MODEL_PATH if os.path.isabs(RAW_MODEL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_MODEL_PATH))
EVAL_PATH = RAW_EVAL_PATH if os.path.isabs(RAW_EVAL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_EVAL_PATH))

# ── Feature columns ───────────────────────────────────────────────────────
FEATURE_COLS = [
    # Provider features
    "views", "clicks", "messages", "rating", "price", "avg_response_time",
    "is_verified", "is_featured", "experience_years", "completion_rate",

    # Search context features
    "search_query_length", "category_match", "location_match",
    "price_match", "availability_match",

    # User context features
    "user_search_frequency", "user_category_preference", "user_price_range_preference",
]

TARGET_COL = "relevance_score"  # 0-100 score indicating search relevance


def load_data(path: str) -> pd.DataFrame:
    """Load CSV and perform basic validation."""
    if not os.path.exists(path):
        sys.exit(f"[ERROR] CSV not found: {path}\nRun data/export_search_ranking_data.py first.")

    df = pd.read_csv(path)
    print(f"[INFO] Loaded {len(df)} rows from {path}")

    missing = [c for c in FEATURE_COLS + [TARGET_COL] if c not in df.columns]
    if missing:
        sys.exit(f"[ERROR] Missing columns in CSV: {missing}")

    return df


def preprocess(df: pd.DataFrame):
    """Clean and prepare features + target."""
    # Drop rows with any null in required columns
    df = df[FEATURE_COLS + [TARGET_COL]].dropna()
    print(f"[INFO] After dropping nulls: {len(df)} rows")

    # Cap extreme outliers (99th percentile) to reduce skew
    for col in FEATURE_COLS:
        if col in ["is_verified", "is_featured", "category_match", "location_match",
                   "price_match", "availability_match"]:
            continue  # Skip binary/categorical features
        cap = df[col].quantile(0.99)
        df[col] = df[col].clip(upper=cap)

    X = df[FEATURE_COLS].values
    y = df[TARGET_COL].values  # Regression target

    return X, y


def train(X_train, y_train) -> tuple:
    """Scale features and train the RandomForest regressor."""
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)

    model = RandomForestRegressor(
        n_estimators=200,
        random_state=42,
        max_depth=10,  # Prevent overfitting
    )

    model.fit(X_scaled, y_train)

    return model, scaler


def evaluate(model, scaler, X_test, y_test):
    """Evaluate model performance."""
    X_test_scaled = scaler.transform(X_test)
    y_pred = model.predict(X_test_scaled)

    return {
        "mse": mean_squared_error(y_test, y_pred),
        "mae": mean_absolute_error(y_test, y_pred),
        "r2_score": r2_score(y_test, y_pred),
        "feature_importance": dict(zip(FEATURE_COLS, model.feature_importances_)),
    }


def save_model_bundle(model, scaler, features, path: str):
    """Save model, scaler, and metadata."""
    os.makedirs(os.path.dirname(path), exist_ok=True)

    bundle = {
        "model": model,
        "scaler": scaler,
        "features": features,
        "model_type": "search_ranking",
        "trained_at": datetime.now().isoformat(),
        "version": "1.0.0",
    }

    joblib.dump(bundle, path)
    print(f"[INFO] Model bundle saved to {path}")


def main():
    print("[INFO] Starting Search Ranking Model Training")

    # Load and preprocess data
    df = load_data(CSV_PATH)
    X, y = preprocess(df)

    # Split data
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42
    )

    print(f"[INFO] Training on {len(X_train)} samples, testing on {len(X_test)} samples")

    # Train model
    model, scaler = train(X_train, y_train)

    # Evaluate
    eval_results = evaluate(model, scaler, X_test, y_test)
    print(f"[INFO] Model MSE: {eval_results['mse']:.4f}")
    print(f"[INFO] Model MAE: {eval_results['mae']:.4f}")
    print(f"[INFO] R² Score: {eval_results['r2_score']:.4f}")

    # Save model
    save_model_bundle(model, scaler, FEATURE_COLS, MODEL_PATH)

    # Save evaluation results
    with open(EVAL_PATH, 'w') as f:
        json.dump(eval_results, f, indent=2)
    print(f"[INFO] Evaluation results saved to {EVAL_PATH}")

    print("[SUCCESS] Search Ranking Model training completed!")


if __name__ == "__main__":
    main()