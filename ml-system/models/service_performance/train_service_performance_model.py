"""
models/service_performance/train_service_performance_model.py
-----------------------------------------------------------
Trains a Service Performance Model to predict service-level bookings,
ratings, revenue, and conversion for each provider service.
"""

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

BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
load_dotenv(os.path.join(BASE_DIR, ".env"))

RAW_CSV_PATH = os.getenv("SERVICE_PERFORMANCE_DATA_PATH", "data/service_performance_data.csv")
RAW_MODEL_PATH = os.getenv("SERVICE_PERFORMANCE_MODEL_PATH", "models/service_performance/service_performance_model.pkl")
RAW_EVAL_PATH = os.getenv("SERVICE_PERFORMANCE_EVAL_PATH", "models/service_performance/service_performance_evaluation.json")
CSV_PATH = RAW_CSV_PATH if os.path.isabs(RAW_CSV_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_CSV_PATH))
MODEL_PATH = RAW_MODEL_PATH if os.path.isabs(RAW_MODEL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_MODEL_PATH))
EVAL_PATH = RAW_EVAL_PATH if os.path.isabs(RAW_EVAL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_EVAL_PATH))

FEATURE_COLS = [
    "category_id", "price", "is_available", "is_featured", "payment_type_code",
    "negotiable", "min_price", "max_price", "base_price", "description_length",
    "provider_average_rating", "provider_total_reviews", "provider_total_jobs",
    "provider_hourly_rate", "provider_is_verified", "provider_is_featured",
    "provider_experience_years", "provider_search_boost", "provider_perf_avg_rating",
    "provider_perf_total_bookings", "provider_perf_completed_bookings",
    "provider_perf_cancellation_rate", "service_bookings_90d", "service_rating_90d",
    "service_review_count_90d", "service_completion_rate_90d",
    "provider_views_last_30d", "provider_clicks_last_30d", "provider_bookings_last_30d"
]

TARGET_COLS = [
    "predicted_monthly_bookings",
    "predicted_rating",
    "predicted_revenue",
    "predicted_conversion_rate"
]


def load_data(path: str) -> pd.DataFrame:
    if not os.path.exists(path):
        sys.exit(f"[ERROR] CSV not found: {path}\nRun data/export_service_performance_data.py first.")

    df = pd.read_csv(path)
    print(f"[INFO] Loaded {len(df)} rows from {path}")

    missing = [c for c in FEATURE_COLS + TARGET_COLS if c not in df.columns]
    if missing:
        sys.exit(f"[ERROR] Missing columns in CSV: {missing}")

    return df


def preprocess(df: pd.DataFrame):
    df = df[FEATURE_COLS + TARGET_COLS].dropna()
    print(f"[INFO] After dropping nulls: {len(df)} rows")

    for col in FEATURE_COLS:
        if col in ["is_available", "is_featured", "negotiable", "provider_is_verified", "provider_is_featured"]:
            continue
        cap = df[col].quantile(0.99)
        df[col] = df[col].clip(upper=cap)

    X = df[FEATURE_COLS].values
    y = df[TARGET_COLS].values
    return X, y


def train(X_train, y_train):
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)
    model = RandomForestRegressor(
        n_estimators=200,
        random_state=42,
        max_depth=14,
    )
    model.fit(X_scaled, y_train)
    return model, scaler


def evaluate(model, scaler, X_test, y_test):
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

    results["feature_importance"] = dict(zip(FEATURE_COLS, model.feature_importances_))
    return results


def save_model_bundle(model, scaler, features, targets, path: str):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    bundle = {
        "model": model,
        "scaler": scaler,
        "features": features,
        "targets": targets,
        "model_type": "service_performance",
        "trained_at": datetime.now().isoformat(),
        "version": "1.0.0",
    }
    joblib.dump(bundle, path)
    print(f"[INFO] Model bundle saved to {path}")


def main():
    print("[INFO] Starting Service Performance Model Training")
    df = load_data(CSV_PATH)
    X, y = preprocess(df)
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    print(f"[INFO] Training on {len(X_train)} samples, testing on {len(X_test)} samples")
    model, scaler = train(X_train, y_train)
    eval_results = evaluate(model, scaler, X_test, y_test)

    print("[INFO] Model Performance by Target:")
    for target, metrics in eval_results.items():
        if target != "feature_importance":
            print(f"  {target}: mse={metrics['mse']:.4f}, mae={metrics['mae']:.4f}, r2={metrics['r2_score']:.4f}")

    save_model_bundle(model, scaler, FEATURE_COLS, TARGET_COLS, MODEL_PATH)
    with open(EVAL_PATH, "w") as f:
        json.dump(eval_results, f, indent=2)
    print(f"[INFO] Evaluation results saved to {EVAL_PATH}")
    print("[SUCCESS] Service Performance Model training completed!")


if __name__ == "__main__":
    main()
