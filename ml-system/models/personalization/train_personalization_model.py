"""
models/personalization/train_personalization_model.py
---------------------------------------------------
Trains a Personalization Model to predict user preferences and
recommend providers based on individual user behavior patterns.

This model focuses on learning what each user likes.
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
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.metrics import accuracy_score, classification_report, roc_auc_score

# ── Paths ─────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ROOT_DIR = os.path.dirname(BASE_DIR)
load_dotenv(os.path.join(BASE_DIR, ".env"))

RAW_CSV_PATH = os.getenv("PERSONALIZATION_DATA_PATH", "data/personalization_data.csv")
RAW_MODEL_PATH = os.getenv("PERSONALIZATION_MODEL_PATH", "models/personalization/personalization_model.pkl")
RAW_EVAL_PATH = os.getenv("PERSONALIZATION_EVAL_PATH", "models/personalization/personalization_evaluation.json")
CSV_PATH = RAW_CSV_PATH if os.path.isabs(RAW_CSV_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_CSV_PATH))
MODEL_PATH = RAW_MODEL_PATH if os.path.isabs(RAW_MODEL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_MODEL_PATH))
EVAL_PATH = RAW_EVAL_PATH if os.path.isabs(RAW_EVAL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_EVAL_PATH))

# ── Feature columns ───────────────────────────────────────────────────────
FEATURE_COLS = [
    # Provider features
    "rating", "price", "avg_response_time", "experience_years",
    "is_verified", "is_featured", "completion_rate",

    # User historical behavior
    "user_avg_rating_given", "user_avg_price_paid", "user_preferred_response_time",
    "user_total_bookings", "user_category_preference_score",

    # Interaction features
    "user_provider_interaction_count", "user_provider_message_count",
    "user_provider_view_count", "days_since_last_interaction",
]

TARGET_COL = "user_preference"  # 1 if user prefers this provider, 0 otherwise


def load_data(path: str) -> pd.DataFrame:
    """Load CSV and perform basic validation."""
    if not os.path.exists(path):
        sys.exit(f"[ERROR] CSV not found: {path}\nRun data/export_personalization_data.py first.")

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
        if col in ["is_verified", "is_featured"]:
            continue  # Skip binary features
        cap = df[col].quantile(0.99)
        df[col] = df[col].clip(upper=cap)

    X = df[FEATURE_COLS].values
    y = df[TARGET_COL].astype(int).values

    return X, y


def train(X_train, y_train) -> tuple:
    """Scale features and train the RandomForest model."""
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)

    model = RandomForestClassifier(
        n_estimators=150,
        class_weight="balanced",
        random_state=42,
        max_depth=8,  # Prevent overfitting for personalization
    )

    model.fit(X_scaled, y_train)

    return model, scaler


def evaluate(model, scaler, X_test, y_test):
    """Evaluate model performance."""
    X_test_scaled = scaler.transform(X_test)
    y_pred = model.predict(X_test_scaled)
    y_pred_proba = model.predict_proba(X_test_scaled)[:, 1]

    return {
        "accuracy": accuracy_score(y_test, y_pred),
        "roc_auc": roc_auc_score(y_test, y_pred_proba),
        "classification_report": classification_report(y_test, y_pred, output_dict=True),
        "feature_importance": dict(zip(FEATURE_COLS, model.feature_importances_)),
    }


def save_model_bundle(model, scaler, features, path: str):
    """Save model, scaler, and metadata."""
    os.makedirs(os.path.dirname(path), exist_ok=True)

    bundle = {
        "model": model,
        "scaler": scaler,
        "features": features,
        "model_type": "personalization",
        "trained_at": datetime.now().isoformat(),
        "version": "1.0.0",
    }

    joblib.dump(bundle, path)
    print(f"[INFO] Model bundle saved to {path}")


def main():
    print("[INFO] Starting Personalization Model Training")

    # Load and preprocess data
    df = load_data(CSV_PATH)
    X, y = preprocess(df)

    # Split data (stratified to maintain class balance)
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    print(f"[INFO] Training on {len(X_train)} samples, testing on {len(X_test)} samples")
    print(f"[INFO] Positive class ratio: {np.mean(y_train):.3f}")

    # Train model
    model, scaler = train(X_train, y_train)

    # Evaluate
    eval_results = evaluate(model, scaler, X_test, y_test)
    print(f"[INFO] Model Accuracy: {eval_results['accuracy']:.4f}")
    print(f"[INFO] ROC AUC: {eval_results['roc_auc']:.4f}")

    # Save model
    save_model_bundle(model, scaler, FEATURE_COLS, MODEL_PATH)

    # Save evaluation results
    with open(EVAL_PATH, 'w') as f:
        json.dump(eval_results, f, indent=2)
    print(f"[INFO] Evaluation results saved to {EVAL_PATH}")

    print("[SUCCESS] Personalization Model training completed!")


if __name__ == "__main__":
    main()