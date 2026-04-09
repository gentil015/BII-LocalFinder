"""
models/user_segmentation/train_user_segmentation_model.py
---------------------------------------------------------
Trains a User Segmentation Model to cluster users into behavioral segments.

This model uses K-means clustering to group users based on their booking patterns,
service preferences, engagement metrics, and platform interaction behavior.
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
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import silhouette_score, calinski_harabasz_score, davies_bouldin_score
from sklearn.decomposition import PCA
import matplotlib.pyplot as plt
import seaborn as sns

# ── Paths ─────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.append(BASE_DIR)  # Add parent directory to Python path

ROOT_DIR = os.path.dirname(BASE_DIR)
load_dotenv(os.path.join(BASE_DIR, ".env"))

RAW_CSV_PATH = os.getenv("USER_SEGMENTATION_DATA_PATH", "data/user_segmentation_data.csv")
RAW_MODEL_PATH = os.getenv("USER_SEGMENTATION_MODEL_PATH", "models/user_segmentation/model.pkl")
RAW_EVAL_PATH = os.getenv("USER_SEGMENTATION_EVAL_PATH", "models/user_segmentation/evaluation.json")
CSV_PATH = RAW_CSV_PATH if os.path.isabs(RAW_CSV_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_CSV_PATH))
MODEL_PATH = RAW_MODEL_PATH if os.path.isabs(RAW_MODEL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_MODEL_PATH))
EVAL_PATH = RAW_EVAL_PATH if os.path.isabs(RAW_EVAL_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_EVAL_PATH))

# ── Feature columns ───────────────────────────────────────────────────────
FEATURE_COLS = [
    # Booking behavior
    "total_bookings", "completed_bookings", "cancelled_bookings",
    "avg_booking_value", "total_spent", "booking_frequency", "completion_rate",

    # Service preferences
    "service_diversity", "price_sensitivity", "preferred_professions_count",

    # Engagement metrics
    "profile_completeness", "response_rate", "avg_rating_given",
    "favorites_count", "reviews_written", "engagement_score",

    # Geographic behavior
    "location_diversity",

    # Temporal patterns
    "peak_booking_hour", "weekend_bookings_ratio", "seasonal_pattern",

    # Platform interaction
    "account_age_days", "last_activity_days", "login_frequency",
    "search_queries_count", "provider_views_count",
]

# ── User segment definitions ──────────────────────────────────────────────
SEGMENT_NAMES = {
    0: "New Users",           # Low activity, new accounts
    1: "Casual Users",        # Occasional bookings, moderate engagement
    2: "Regular Customers",   # Frequent bookings, high engagement
    3: "Premium Users",       # High spending, diverse services
    4: "Power Users",         # Very active, high engagement across all metrics
}

def load_data(path: str) -> pd.DataFrame:
    """Load CSV and perform basic validation."""
    if not os.path.exists(path):
        sys.exit(f"[ERROR] CSV not found: {path}\nRun data/export_user_segmentation_data.py first.")

    df = pd.read_csv(path)
    print(f"[INFO] Loaded {len(df)} rows from {path}")

    missing = [c for c in FEATURE_COLS if c not in df.columns]
    if missing:
        sys.exit(f"[ERROR] Missing columns in CSV: {missing}")

    return df

def preprocess(df: pd.DataFrame):
    """Clean and prepare features for clustering."""
    # Drop rows with too many missing values
    df = df.dropna(thresh=len(FEATURE_COLS) * 0.8)  # Keep rows with at least 80% of features
    print(f"[INFO] After dropping incomplete rows: {len(df)} rows")

    # Fill remaining missing values with median
    for col in FEATURE_COLS:
        if df[col].isnull().any():
            median_val = df[col].median()
            df[col] = df[col].fillna(median_val)
            print(f"[INFO] Filled missing {col} with median: {median_val}")

    # Remove outliers using IQR method for key metrics
    outlier_cols = ["total_spent", "total_bookings", "search_queries_count"]
    for col in outlier_cols:
        Q1 = df[col].quantile(0.25)
        Q3 = df[col].quantile(0.75)
        IQR = Q3 - Q1
        lower_bound = Q1 - 1.5 * IQR
        upper_bound = Q3 + 1.5 * IQR
        df = df[(df[col] >= lower_bound) & (df[col] <= upper_bound)]

    print(f"[INFO] After outlier removal: {len(df)} rows")

    X = df[FEATURE_COLS].values
    user_ids = df['user_id'].values if 'user_id' in df.columns else None

    return X, user_ids, df

def find_optimal_clusters(X, max_clusters=8):
    """Find optimal number of clusters using elbow method and silhouette score."""
    print("[INFO] Finding optimal number of clusters...")

    inertias = []
    silhouette_scores = []

    for k in range(2, max_clusters + 1):
        kmeans = KMeans(n_clusters=k, random_state=42, n_init=10)
        kmeans.fit(X)
        inertias.append(kmeans.inertia_)
        silhouette_scores.append(silhouette_score(X, kmeans.labels_))

    # Find elbow point (simple heuristic)
    diffs = np.diff(inertias)
    diffs2 = np.diff(diffs)
    elbow_idx = np.argmin(diffs2) + 2  # +2 because we start from k=2 and use second derivative

    # Also consider silhouette score
    best_silhouette_idx = np.argmax(silhouette_scores) + 2  # +2 for same reason

    # Choose the best k based on both metrics
    optimal_k = min(elbow_idx, best_silhouette_idx)
    if abs(elbow_idx - best_silhouette_idx) > 1:
        # If they disagree significantly, prefer silhouette score
        optimal_k = best_silhouette_idx

    print(f"[INFO] Elbow method suggests {elbow_idx} clusters")
    print(f"[INFO] Silhouette score suggests {best_silhouette_idx} clusters")
    print(f"[INFO] Selected {optimal_k} clusters")

    return optimal_k, inertias, silhouette_scores

def train(X_train, n_clusters=None) -> tuple:
    """Train K-means clustering model."""
    if n_clusters is None:
        n_clusters, _, _ = find_optimal_clusters(X_train)

    # Scale features
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)

    # Train K-means
    kmeans = KMeans(
        n_clusters=n_clusters,
        random_state=42,
        n_init=10,
        max_iter=300
    )

    cluster_labels = kmeans.fit_predict(X_scaled)

    return kmeans, scaler, cluster_labels, n_clusters

def evaluate_model(model, scaler, X, cluster_labels, n_clusters):
    """Evaluate clustering model performance."""
    X_scaled = scaler.transform(X)

    # Calculate metrics
    silhouette = silhouette_score(X_scaled, cluster_labels)
    calinski = calinski_harabasz_score(X_scaled, cluster_labels)
    davies = davies_bouldin_score(X_scaled, cluster_labels)

    # Analyze cluster characteristics
    cluster_sizes = np.bincount(cluster_labels)
    cluster_centers = model.cluster_centers_

    # Feature importance (variance explained by clusters)
    feature_variance = np.var(X_scaled, axis=0)
    cluster_variance = np.var(cluster_centers, axis=0)

    evaluation = {
        "n_clusters": n_clusters,
        "silhouette_score": silhouette,
        "calinski_harabasz_score": calinski,
        "davies_bouldin_score": davies,
        "cluster_sizes": cluster_sizes.tolist(),
        "cluster_centers": cluster_centers.tolist(),
        "feature_importance": {
            FEATURE_COLS[i]: {
                "variance": float(feature_variance[i]),
                "cluster_separation": float(cluster_variance[i])
            }
            for i in range(len(FEATURE_COLS))
        },
        "segment_names": SEGMENT_NAMES
    }

    return evaluation

def analyze_segments(df, cluster_labels, n_clusters):
    """Analyze characteristics of each user segment."""
    df_with_clusters = df.copy()
    df_with_clusters['cluster'] = cluster_labels

    segment_analysis = {}

    for cluster_id in range(n_clusters):
        cluster_data = df_with_clusters[df_with_clusters['cluster'] == cluster_id]

        segment_analysis[cluster_id] = {
            "name": SEGMENT_NAMES.get(cluster_id, f"Segment {cluster_id}"),
            "size": len(cluster_data),
            "percentage": len(cluster_data) / len(df) * 100,
            "avg_total_bookings": float(cluster_data['total_bookings'].mean()),
            "avg_total_spent": float(cluster_data['total_spent'].mean()),
            "avg_engagement_score": float(cluster_data['engagement_score'].mean()),
            "avg_completion_rate": float(cluster_data['completion_rate'].mean()),
            "avg_account_age_days": float(cluster_data['account_age_days'].mean()),
            "avg_service_diversity": float(cluster_data['service_diversity'].mean()),
        }

    return segment_analysis

def save_model_bundle(model, scaler, features, n_clusters, path: str):
    """Save model, scaler, and metadata."""
    bundle = {
        "model": model,
        "scaler": scaler,
        "features": features,
        "n_clusters": n_clusters,
        "segment_names": SEGMENT_NAMES,
        "trained_at": datetime.now().isoformat(),
        "model_type": "user_segmentation"
    }

    os.makedirs(os.path.dirname(path), exist_ok=True)
    joblib.dump(bundle, path)
    print(f"[SUCCESS] Model bundle saved to {path}")

def plot_clusters(X_scaled, cluster_labels, n_clusters, save_path=None):
    """Create visualization of clusters using PCA."""
    try:
        pca = PCA(n_components=2)
        X_pca = pca.fit_transform(X_scaled)

        plt.figure(figsize=(10, 8))
        scatter = plt.scatter(X_pca[:, 0], X_pca[:, 1], c=cluster_labels, cmap='viridis', alpha=0.6)
        plt.title(f'User Segments (K-means, {n_clusters} clusters)')
        plt.xlabel(f'PC1 ({pca.explained_variance_ratio_[0]:.2%} variance)')
        plt.ylabel(f'PC2 ({pca.explained_variance_ratio_[1]:.2%} variance)')
        plt.colorbar(scatter)

        if save_path:
            plt.savefig(save_path, dpi=150, bbox_inches='tight')
            print(f"[INFO] Cluster plot saved to {save_path}")

        plt.close()
    except Exception as e:
        print(f"[WARNING] Could not create cluster plot: {e}")

def main():
    parser = argparse.ArgumentParser(description="Train user segmentation model")
    parser.add_argument("--n-clusters", type=int, help="Number of clusters (auto-detect if not specified)")
    parser.add_argument("--model-path", type=str, help="Output model path")
    parser.add_argument("--eval-path", type=str, help="Output evaluation path")

    args = parser.parse_args()

    try:
        # Load and preprocess data
        df = load_data(CSV_PATH)
        X, user_ids, df_processed = preprocess(df)

        if len(X) < 10:
            sys.exit("[ERROR] Not enough data for clustering (minimum 10 samples required)")

        # Train model
        print("[INFO] Training user segmentation model...")
        model, scaler, cluster_labels, n_clusters = train(X, n_clusters=args.n_clusters)

        # Evaluate model
        evaluation = evaluate_model(model, scaler, X, cluster_labels, n_clusters)

        # Analyze segments
        segment_analysis = analyze_segments(df_processed, cluster_labels, n_clusters)
        evaluation["segment_analysis"] = segment_analysis

        # Save model
        model_path = args.model_path or MODEL_PATH
        save_model_bundle(model, scaler, FEATURE_COLS, n_clusters, model_path)

        # Save evaluation
        eval_path = args.eval_path or EVAL_PATH
        os.makedirs(os.path.dirname(eval_path), exist_ok=True)
        with open(eval_path, 'w') as f:
            json.dump(evaluation, f, indent=2, default=str)

        # Create visualization
        X_scaled = scaler.transform(X)
        plot_path = os.path.join(os.path.dirname(model_path), "clusters_plot.png")
        plot_clusters(X_scaled, cluster_labels, n_clusters, plot_path)

        # Print results
        print("\n🎯 User Segmentation Model Trained Successfully!")
        print(f"📊 Number of clusters: {n_clusters}")
        print(f"📈 Silhouette Score: {silhouette_avg:.3f}")
        print(f"📉 Calinski-Harabasz Score: {ch_score:.3f}")
        print(f"📊 Davies-Bouldin Score: {db_score:.3f}")

        print("\n👥 Segment Analysis:")
        for cluster_id, analysis in segment_analysis.items():
            print(f"  {analysis['name']} (Cluster {cluster_id}):")
            print(f"    Size: {analysis['size']} users ({analysis['percentage']:.1f}%)")
            print(f"    Avg bookings: {analysis['avg_bookings']:.1f}")
            print(f"    Avg spending: {analysis['avg_spending']:.0f} RWF")
            print(f"    Completion rate: {analysis['completion_rate']:.2f}")
    except Exception as e:
        print(f"[ERROR] Training failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()