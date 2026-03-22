"""
train_model.py
--------------
Trains a Logistic Regression model to predict whether a provider will
be hired ("hired" = 1) based on behavioral and profile features.

Usage:
    python train_model.py

Output:
    ../model/model.pkl   — trained model + scaler bundle
"""

import os
import sys
import joblib
import pandas as pd
import numpy as np
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    roc_auc_score,
)

# ── Paths ─────────────────────────────────────────────────────────────────────
BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
CSV_PATH   = os.path.join(BASE_DIR, "..", "data", "ml_interactions.csv")
MODEL_PATH = os.path.join(BASE_DIR, "model.pkl")

# ── Feature columns (must match FastAPI input & PHP feature builder) ──────────
FEATURE_COLS = [
    "views",
    "clicks",
    "messages",
    "rating",
    "price",
    "avg_response_time",
    "user_avg_price",
    "user_avg_response_time",
    "user_total_bookings",
]
TARGET_COL = "hired"


def load_data(path: str) -> pd.DataFrame:
    """Load CSV and perform basic validation."""
    if not os.path.exists(path):
        sys.exit(f"[ERROR] CSV not found: {path}\nRun export_data.php first.")

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
        cap = df[col].quantile(0.99)
        df[col] = df[col].clip(upper=cap)

    X = df[FEATURE_COLS].values
    y = df[TARGET_COL].astype(int).values

    return X, y


def train(X_train, y_train) -> tuple:
    """Scale features and train the model."""
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)

    model = LogisticRegression(
        max_iter=1000,
        class_weight="balanced",   # handle imbalanced hired/not-hired
        random_state=42,
        solver="lbfgs",
    )
    model.fit(X_scaled, y_train)

    return model, scaler


def evaluate(model, scaler, X_test, y_test):
    """Print evaluation metrics."""
    X_scaled = scaler.transform(X_test)
    y_pred   = model.predict(X_scaled)
    y_prob   = model.predict_proba(X_scaled)[:, 1]

    acc = accuracy_score(y_test, y_pred)
    auc = roc_auc_score(y_test, y_prob) if len(set(y_test)) > 1 else float("nan")

    print(f"\n{'='*50}")
    print(f"  Accuracy : {acc:.4f}")
    print(f"  ROC-AUC  : {auc:.4f}")
    print(f"\n  Classification Report:")
    print(classification_report(y_test, y_pred, target_names=["Not Hired", "Hired"]))
    print(f"  Confusion Matrix:")
    print(confusion_matrix(y_test, y_pred))
    print(f"{'='*50}\n")


def save_model(model, scaler, path: str):
    """Persist model + scaler as a single bundle."""
    bundle = {"model": model, "scaler": scaler, "features": FEATURE_COLS}
    joblib.dump(bundle, path)
    print(f"[INFO] Model saved to: {path}")


def main():
    print("\n[START] Provider Recommendation Model Training\n")

    # 1. Load
    df = load_data(CSV_PATH)

    # Class distribution
    hired_pct = df[TARGET_COL].mean() * 100
    print(f"[INFO] Class balance — Hired: {hired_pct:.1f}%  |  Not hired: {100-hired_pct:.1f}%")

    # 2. Preprocess
    X, y = preprocess(df)

    # Need at least 20 samples to split meaningfully
    if len(X) < 20:
        print("[WARN] Very few samples — training on full dataset without test split.")
        model, scaler = train(X, y)
        save_model(model, scaler, MODEL_PATH)
        print("[DONE] Training complete (no evaluation — insufficient data).\n")
        return

    # 3. Split
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    print(f"[INFO] Train: {len(X_train)} rows | Test: {len(X_test)} rows")

    # 4. Train
    model, scaler = train(X_train, y_train)

    # 5. Evaluate
    evaluate(model, scaler, X_test, y_test)

    # 6. Save
    save_model(model, scaler, MODEL_PATH)

    print("[DONE] Training complete.\n")


if __name__ == "__main__":
    main()