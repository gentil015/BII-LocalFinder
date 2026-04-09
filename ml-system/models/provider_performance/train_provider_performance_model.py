"""
models/provider_performance/train_provider_performance_model.py
---------------------------------------------------------------
Trains a Provider Performance Model to predict provider success metrics
and performance scores based on historical data and current features.

This model focuses on predicting provider performance indicators.
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

RAW_CSV_PATH = os.getenv("PROVIDER_PERFORMANCE_DATA_PATH", "data/provider_performance_data.csv")
RAW_MODEL_PATH = os.getenv("PROVIDER_PERFORMANCE_MODEL_PATH", "models/provider_performance/provider_performance_model.pkl")
RAW_EVAL_PATH = os.getenv("PROVIDER_PERFORMANCE_EVAL_PATH", "models/provider_performance/provider_performance_evaluation.json")
CSV_PATH = RAW_CSV_PATH if os.path.isabs(RAW_CSV_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_CSV_PATH))
MODEL_PATH = RAW_MODEL_PATH if os.path.isabs(RAW_MODEL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_MODEL_PATH))
EVAL_PATH = RAW_EVAL_PATH if os.path.isabs(RAW_EVAL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_EVAL_PATH))

# ── Feature columns ───────────────────────────────────────────────────────
FEATURE_COLS = [
    # Provider profile features
    "experience_years", "is_verified", "is_featured", "hourly_rate",
    "working_days_count", "max_daily_bookings", "portfolio_enabled",

    # Performance history
    "total_reviews", "average_rating", "total_jobs_completed",
    "avg_response_time_minutes", "completion_rate", "cancellation_rate",

    # Activity metrics
    "profile_views_last_30d", "messages_received_last_30d", "bookings_last_30d",
    "days_since_last_booking", "days_active",

    # Social proof
    "facebook_followers", "instagram_followers", "website_has_content",
]

TARGET_COLS = [
    "predicted_completion_rate",
    "predicted_rating",
    "predicted_monthly_bookings",
    "predicted_response_time",
    "overall_performance_score"
]


def load_data(path: str) -> pd.DataFrame:
    """Load CSV and perform basic validation."""
    if not os.path.exists(path):
        sys.exit(f"[ERROR] CSV not found: {path}\nRun data/export_provider_performance_data.py first.")

    df = pd.read_csv(path)
    print(f"[INFO] Loaded {len(df)} rows from {path}")

    missing = [c for c in FEATURE_COLS + TARGET_COLS if c not in df.columns]
    if missing:
        sys.exit(f"[ERROR] Missing columns in CSV: {missing}")

    return df


def preprocess(df: pd.DataFrame):
    """Clean and prepare features + targets."""
    # Drop rows with any null in required columns
    df = df[FEATURE_COLS + TARGET_COLS].dropna()
    print(f"[INFO] After dropping nulls: {len(df)} rows")

    # Cap extreme outliers (99th percentile) to reduce skew
    for col in FEATURE_COLS:
        if col in ["is_verified", "is_featured", "portfolio_enabled", "website_has_content"]:
            continue  # Skip binary features
        cap = df[col].quantile(0.99)
        df[col] = df[col].clip(upper=cap)

    X = df[FEATURE_COLS].values
    y = df[TARGET_COLS].values  # Multi-output regression

    return X, y


def train(X_train, y_train) -> tuple:
    """Scale features and train the RandomForest regressor."""
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)

    # Use multi-output random forest for predicting multiple performance metrics
    model = RandomForestRegressor(
        n_estimators=200,
        random_state=42,
        max_depth=12,
    )

    model.fit(X_scaled, y_train)

    return model, scaler


def evaluate(model, scaler, X_test, y_test):
    """Evaluate model performance for multi-output regression."""
    X_test_scaled = scaler.transform(X_test)
    y_pred = model.predict(X_test_scaled)

    results = {}
    for i, target in enumerate(TARGET_COLS):
        mse = mean_squared_error(y_test[:, i], y_pred[:, i])
        mae = mean_absolute_error(y_test[:, i], y_pred[:, i])
        r2 = r2_score(y_test[:, i], y_pred[:, i])

        results[target] = {
            "mse": mse,
            "mae": mae,
            "r2_score": r2,
        }

    # Overall feature importance (averaged across targets)
    results["feature_importance"] = dict(zip(FEATURE_COLS, model.feature_importances_))

    return results


def save_model_bundle(model, scaler, features, targets, path: str):
    """Save model, scaler, and metadata."""
    os.makedirs(os.path.dirname(path), exist_ok=True)

    bundle = {
        "model": model,
        "scaler": scaler,
        "features": features,
        "targets": targets,
        "model_type": "provider_performance",
        "trained_at": datetime.now().isoformat(),
        "version": "1.0.0",
    }

    joblib.dump(bundle, path)
    print(f"[INFO] Model bundle saved to {path}")


def main():
    print("[INFO] Starting Provider Performance Model Training")

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

    print("[INFO] Model Performance by Target:")
    for target, metrics in eval_results.items():
        if target != "feature_importance":
            print(f"  {target}:")
            print(".4f")
            print(".4f")
            print(".4f")

    # Save model
    save_model_bundle(model, scaler, FEATURE_COLS, TARGET_COLS, MODEL_PATH)

    # Save evaluation results
    with open(EVAL_PATH, 'w') as f:
        json.dump(eval_results, f, indent=2)
    print(f"[INFO] Evaluation results saved to {EVAL_PATH}")

    print("[SUCCESS] Provider Performance Model training completed!")


if __name__ == "__main__":
    main()