"""
api/multi_model_app.py  —  Multi-Model Provider Prediction API
---------------------------------------------------------------
Serves multiple specialized ML models via FastAPI.

Supports:
- Recommendation Model: Predicts hiring probability
- Search Ranking Model: Predicts search relevance scores
- Personalization Model: Predicts user preferences
- Provider Performance Model: Predicts provider performance metrics
- User Engagement Model: Predicts user engagement likelihood

Start:
    uvicorn api.multi_model_app:app --host 0.0.0.0 --port 8000 --reload

Endpoints:
    POST /predict/recommendation        — Single provider recommendation prediction
    POST /predict/recommendation/batch  — Bulk recommendation predictions
    POST /predict/search_ranking        — Single provider search ranking score
    POST /predict/search_ranking/batch  — Bulk search ranking predictions
    POST /predict/personalization       — Single user-provider preference prediction
    POST /predict/provider_performance  — Single provider performance prediction
    POST /predict/user_engagement       — Single user engagement prediction
    POST /reload-models                 — Reload all latest model bundles
    GET  /health                        — Health check
    GET  /models/info                   — Model metadata for all models
"""
import datetime
import json
import logging
import os
import sys
import joblib
import numpy as np
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field, field_validator
from typing import List, Optional, Dict, Any

# ── Paths ─────────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ROOT_DIR = os.path.dirname(BASE_DIR)
load_dotenv(os.path.join(BASE_DIR, ".env"))

# Model paths
MODEL_PATHS = {
    "recommendation": os.getenv("RECOMMENDATION_MODEL_PATH", os.path.normpath(os.path.join(BASE_DIR, "models", "recommendation", "recommendation_model.pkl"))),
    "search_ranking": os.getenv("SEARCH_RANKING_MODEL_PATH", os.path.normpath(os.path.join(BASE_DIR, "models", "search_ranking", "search_ranking_model.pkl"))),
    "personalization": os.getenv("PERSONALIZATION_MODEL_PATH", os.path.normpath(os.path.join(BASE_DIR, "models", "personalization", "personalization_model.pkl"))),
    "provider_performance": os.getenv("PROVIDER_PERFORMANCE_MODEL_PATH", os.path.normpath(os.path.join(BASE_DIR, "models", "provider_performance", "provider_performance_model.pkl"))),
    "service_performance": os.getenv("SERVICE_PERFORMANCE_MODEL_PATH", os.path.normpath(os.path.join(BASE_DIR, "models", "service_performance", "service_performance_model.pkl"))),
    "user_engagement": os.getenv("USER_ENGAGEMENT_MODEL_PATH", os.path.normpath(os.path.join(BASE_DIR, "models", "user_engagement", "user_engagement_model.pkl"))),
}

RAW_PREDICTION_LOG_PATH = os.getenv("PREDICTION_LOG_PATH", "logs/predictions.jsonl")
PREDICTION_LOG_PATH = RAW_PREDICTION_LOG_PATH if os.path.isabs(RAW_PREDICTION_LOG_PATH) else os.path.normpath(os.path.join(BASE_DIR, RAW_PREDICTION_LOG_PATH))

def load_model_bundle(model_name: str, path: str):
    """Load a model bundle for a specific model type."""
    if not os.path.exists(path):
        print(f"[WARNING] Model file not found: {path} for {model_name}")
        return None

    try:
        bundle = joblib.load(path)
        print(f"[INFO] Loaded {model_name} model — features: {bundle.get('features', 'unknown')}")
        return bundle
    except Exception as e:
        print(f"[ERROR] Failed to load {model_name} model: {e}")
        return None

# Load all model bundles
MODEL_BUNDLES = {}
for model_name, path in MODEL_PATHS.items():
    MODEL_BUNDLES[model_name] = load_model_bundle(model_name, path)

os.makedirs(os.path.dirname(PREDICTION_LOG_PATH), exist_ok=True)
prediction_logger = logging.getLogger("prediction_logger")
if not prediction_logger.handlers:
    prediction_logger.setLevel(logging.INFO)
    handler = logging.FileHandler(PREDICTION_LOG_PATH)
    handler.setFormatter(logging.Formatter("%(message)s"))
    prediction_logger.addHandler(handler)

print(f"[INFO] Prediction log path: {PREDICTION_LOG_PATH}")

# ── FastAPI app ───────────────────────────────────────────────────────────────
app = FastAPI(
    title="Multi-Model Provider Prediction API",
    description="Serves multiple specialized ML models for provider recommendations, search ranking, personalization, performance prediction, and user engagement.",
    version="2.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],   # restrict to your PHP server in production
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

# ── Request / Response schemas ────────────────────────────────────────────────

class RecommendationFeatures(BaseModel):
    """Features for recommendation model."""
    views: float = Field(..., ge=0, description="Number of profile views")
    clicks: float = Field(..., ge=0, description="Number of profile clicks")
    messages: float = Field(..., ge=0, description="Number of messages received")
    rating: float = Field(..., ge=0, le=5, description="Average star rating (0-5)")
    price: float = Field(..., ge=0, description="Average service price (RWF)")
    avg_response_time: float = Field(..., ge=0, description="Average response time in hours")
    user_avg_price: float = Field(..., ge=0, description="User's average booking price")
    user_avg_response_time: float = Field(..., ge=0, description="User's average provider response time")
    user_total_bookings: int = Field(..., ge=0, description="User's total number of bookings")

class SearchRankingFeatures(BaseModel):
    """Features for search ranking model."""
    views: float = Field(..., ge=0)
    clicks: float = Field(..., ge=0)
    messages: float = Field(..., ge=0)
    rating: float = Field(..., ge=0, le=5)
    price: float = Field(..., ge=0)
    avg_response_time: float = Field(..., ge=0)
    is_verified: int = Field(..., ge=0, le=1)
    is_featured: int = Field(..., ge=0, le=1)
    experience_years: float = Field(..., ge=0)
    completion_rate: float = Field(..., ge=0, le=1)
    search_query_length: int = Field(..., ge=0)
    category_match: int = Field(..., ge=0, le=1)
    location_match: int = Field(..., ge=0, le=1)
    price_match: int = Field(..., ge=0, le=1)
    availability_match: int = Field(..., ge=0, le=1)
    user_search_frequency: float = Field(..., ge=0)
    user_category_preference: float = Field(..., ge=0)
    user_price_range_preference: str = Field(..., description="Price range string")

class PersonalizationFeatures(BaseModel):
    """Features for personalization model."""
    rating: float = Field(..., ge=0, le=5)
    price: float = Field(..., ge=0)
    avg_response_time: float = Field(..., ge=0)
    experience_years: float = Field(..., ge=0)
    is_verified: int = Field(..., ge=0, le=1)
    is_featured: int = Field(..., ge=0, le=1)
    completion_rate: float = Field(..., ge=0, le=1)
    user_avg_rating_given: float = Field(..., ge=0, le=5)
    user_avg_price_paid: float = Field(..., ge=0)
    user_preferred_response_time: float = Field(..., ge=0)
    user_total_bookings: int = Field(..., ge=0)
    user_category_preference_score: float = Field(..., ge=0)
    user_provider_interaction_count: int = Field(..., ge=0)
    user_provider_message_count: int = Field(..., ge=0)
    user_provider_view_count: int = Field(..., ge=0)
    days_since_last_interaction: int = Field(..., ge=0)

class ProviderPerformanceFeatures(BaseModel):
    """Features for provider performance model."""
    experience_years: float = Field(..., ge=0)
    is_verified: int = Field(..., ge=0, le=1)
    is_featured: int = Field(..., ge=0, le=1)
    hourly_rate: float = Field(..., ge=0)
    working_days_count: int = Field(..., ge=1, le=7)
    max_daily_bookings: int = Field(..., ge=1)
    portfolio_enabled: int = Field(..., ge=0, le=1)
    total_reviews: int = Field(..., ge=0)
    average_rating: float = Field(..., ge=0, le=5)
    total_jobs_completed: int = Field(..., ge=0)
    avg_response_time_minutes: float = Field(..., ge=0)
    completion_rate: float = Field(..., ge=0, le=1)
    cancellation_rate: float = Field(..., ge=0, le=1)
    profile_views_last_30d: int = Field(..., ge=0)
    messages_received_last_30d: int = Field(..., ge=0)
    bookings_last_30d: int = Field(..., ge=0)
    days_since_last_booking: int = Field(..., ge=0)
    days_active: int = Field(..., ge=0)
    facebook_followers: int = Field(..., ge=0)
    instagram_followers: int = Field(..., ge=0)
    website_has_content: int = Field(..., ge=0, le=1)

class ServicePerformanceFeatures(BaseModel):
    """Features for service performance model."""
    category_id: int = Field(..., ge=0)
    price: float = Field(..., ge=0)
    is_available: int = Field(..., ge=0, le=1)
    is_featured: int = Field(..., ge=0, le=1)
    payment_type_code: int = Field(..., ge=0)
    negotiable: int = Field(..., ge=0, le=1)
    min_price: float = Field(..., ge=0)
    max_price: float = Field(..., ge=0)
    base_price: float = Field(..., ge=0)
    description_length: int = Field(..., ge=0)
    provider_average_rating: float = Field(..., ge=0, le=5)
    provider_total_reviews: int = Field(..., ge=0)
    provider_total_jobs: int = Field(..., ge=0)
    provider_hourly_rate: float = Field(..., ge=0)
    provider_is_verified: int = Field(..., ge=0, le=1)
    provider_is_featured: int = Field(..., ge=0, le=1)
    provider_experience_years: int = Field(..., ge=0)
    provider_search_boost: int = Field(..., ge=0)
    provider_perf_avg_rating: float = Field(..., ge=0, le=5)
    provider_perf_total_bookings: int = Field(..., ge=0)
    provider_perf_completed_bookings: int = Field(..., ge=0)
    provider_perf_cancellation_rate: float = Field(..., ge=0, le=1)
    service_bookings_90d: int = Field(..., ge=0)
    service_rating_90d: float = Field(..., ge=0, le=5)
    service_review_count_90d: int = Field(..., ge=0)
    service_completion_rate_90d: float = Field(..., ge=0, le=1)
    provider_views_last_30d: int = Field(..., ge=0)
    provider_clicks_last_30d: int = Field(..., ge=0)
    provider_bookings_last_30d: int = Field(..., ge=0)

class UserEngagementFeatures(BaseModel):
    """Features for user engagement model."""
    account_age_days: int = Field(..., ge=0)
    is_verified: int = Field(..., ge=0, le=1)
    profile_completion_score: float = Field(..., ge=0, le=1)
    total_bookings: int = Field(..., ge=0)
    total_reviews_written: int = Field(..., ge=0)
    total_messages_sent: int = Field(..., ge=0)
    provider_rating: float = Field(..., ge=0, le=5)
    provider_price: float = Field(..., ge=0)
    provider_response_time: float = Field(..., ge=0)
    provider_experience_years: float = Field(..., ge=0)
    provider_is_verified: int = Field(..., ge=0, le=1)
    provider_is_featured: int = Field(..., ge=0, le=1)
    previous_views_of_provider: int = Field(..., ge=0)
    previous_messages_with_provider: int = Field(..., ge=0)
    days_since_first_interaction: int = Field(..., ge=0)
    interaction_frequency: int = Field(..., ge=0)
    time_of_day: int = Field(..., ge=0, le=23)
    day_of_week: int = Field(..., ge=1, le=7)
    is_weekend: int = Field(..., ge=0, le=1)
    session_duration_minutes: float = Field(..., ge=0)
    pages_viewed_in_session: int = Field(..., ge=1)
    search_queries_in_session: int = Field(..., ge=0)
    avg_session_duration: float = Field(..., ge=0)
    pages_per_session: float = Field(..., ge=0)
    return_visitor: int = Field(..., ge=0, le=1)
    days_since_last_visit: int = Field(..., ge=0)
    total_sessions_last_30d: int = Field(..., ge=0)

# User Segmentation Features
class UserSegmentationFeatures(BaseModel):
    """Features for user segmentation model."""
    total_bookings: int = Field(..., ge=0)
    completed_bookings: int = Field(..., ge=0)
    cancelled_bookings: int = Field(..., ge=0)
    avg_booking_value: float = Field(..., ge=0)
    total_spent: float = Field(..., ge=0)
    booking_frequency: float = Field(..., ge=0)
    completion_rate: float = Field(..., ge=0, le=1)
    service_diversity: int = Field(..., ge=0)
    price_sensitivity: float = Field(..., ge=0, le=1)
    preferred_professions_count: int = Field(..., ge=0)
    profile_completeness: float = Field(..., ge=0, le=1)
    response_rate: float = Field(..., ge=0, le=1)
    avg_rating_given: float = Field(..., ge=0, le=5)
    favorites_count: int = Field(..., ge=0)
    reviews_written: int = Field(..., ge=0)
    engagement_score: float = Field(..., ge=0, le=1)
    location_diversity: int = Field(..., ge=0)
    peak_booking_hour: int = Field(..., ge=0, le=23)
    weekend_bookings_ratio: float = Field(..., ge=0, le=1)
    seasonal_pattern: float = Field(..., ge=0, le=1)
    account_age_days: int = Field(..., ge=0)
    last_activity_days: int = Field(..., ge=0)
    login_frequency: float = Field(..., ge=0)
    search_queries_count: int = Field(..., ge=0)
    provider_views_count: int = Field(..., ge=0)

# ── Helper functions ──────────────────────────────────────────────────────────

def features_to_array(features: Dict[str, Any], feature_order: List[str]) -> np.ndarray:
    """Convert features dict to numpy array in correct order."""
    return np.array([features[feature] for feature in feature_order]).reshape(1, -1)

def log_prediction(model_name: str, input_data: Dict, prediction: Any):
    """Log prediction for monitoring."""
    log_entry = {
        "timestamp": datetime.datetime.now().isoformat(),
        "model": model_name,
        "input": input_data,
        "prediction": prediction
    }
    prediction_logger.info(json.dumps(log_entry))

def predict_single(model_name: str, features: Dict[str, Any]) -> Dict[str, Any]:
    """Make prediction with a single model."""
    bundle = MODEL_BUNDLES.get(model_name)
    if not bundle:
        raise HTTPException(status_code=503, detail=f"Model {model_name} not available")

    try:
        model = bundle["model"]
        scaler = bundle["scaler"]
        feature_order = bundle["features"]

        # Convert features to array
        X = features_to_array(features, feature_order)

        # Scale features
        X_scaled = scaler.transform(X)

        # Make prediction
        prediction = model.predict(X_scaled)

        # For classification models, also return probabilities
        if hasattr(model, "predict_proba"):
            probabilities = model.predict_proba(X_scaled)[0]
            result = {
                "prediction": float(prediction[0]),
                "probabilities": probabilities.tolist()
            }
        else:
            # Regression model
            pred = prediction[0]
            if isinstance(pred, (list, tuple, np.ndarray)):
                result = {"prediction": np.asarray(pred).tolist()}
            else:
                result = {"prediction": float(pred)}

        # Log prediction
        log_prediction(model_name, features, result)

        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Prediction failed: {str(e)}")

def predict_batch(model_name: str, features_list: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """Make batch predictions with a single model."""
    bundle = MODEL_BUNDLES.get(model_name)
    if not bundle:
        raise HTTPException(status_code=503, detail=f"Model {model_name} not available")

    try:
        model = bundle["model"]
        scaler = bundle["scaler"]
        feature_order = bundle["features"]

        # Convert all features to arrays
        X_list = [features_to_array(features, feature_order) for features in features_list]
        X = np.vstack(X_list)

        # Scale features
        X_scaled = scaler.transform(X)

        # Make predictions
        predictions = model.predict(X_scaled)

        results = []
        for i, pred in enumerate(predictions):
            if hasattr(model, "predict_proba"):
                probabilities = model.predict_proba(X_scaled[i:i+1])[0]
                result = {
                    "prediction": float(pred),
                    "probabilities": probabilities.tolist()
                }
            else:
                if isinstance(pred, (list, tuple, np.ndarray)):
                    result = {"prediction": np.asarray(pred).tolist()}
                else:
                    result = {"prediction": float(pred)}

            results.append(result)

            # Log each prediction
            log_prediction(model_name, features_list[i], result)

        return results

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Batch prediction failed: {str(e)}")

# ── API Endpoints ────────────────────────────────────────────────────────────

@app.get("/health")
async def health_check():
    """Health check endpoint."""
    available_models = [name for name, bundle in MODEL_BUNDLES.items() if bundle is not None]
    return {
        "status": "healthy" if available_models else "degraded",
        "available_models": available_models,
        "total_models": len(MODEL_BUNDLES)
    }

@app.get("/models/info")
async def get_models_info():
    """Get metadata for all models."""
    info = {}
    for model_name, bundle in MODEL_BUNDLES.items():
        if bundle:
            info[model_name] = {
                "type": bundle.get("model_type", "unknown"),
                "features": bundle.get("features", []),
                "trained_at": bundle.get("trained_at", "unknown"),
                "version": bundle.get("version", "unknown")
            }
        else:
            info[model_name] = {"status": "not_loaded"}
    return info

@app.post("/reload-models")
async def reload_models():
    """Reload all model bundles from disk."""
    reloaded = []
    failed = []

    for model_name, path in MODEL_PATHS.items():
        try:
            bundle = load_model_bundle(model_name, path)
            if bundle:
                MODEL_BUNDLES[model_name] = bundle
                reloaded.append(model_name)
            else:
                failed.append(model_name)
        except Exception as e:
            failed.append(f"{model_name} ({str(e)})")

    return {
        "reloaded": reloaded,
        "failed": failed,
        "total": len(MODEL_PATHS)
    }

# Recommendation Model Endpoints
@app.post("/predict/recommendation")
async def predict_recommendation(features: RecommendationFeatures):
    """Predict hiring probability for a single provider-user pair."""
    return predict_single("recommendation", features.model_dump())

@app.post("/predict/recommendation/batch")
async def predict_recommendation_batch(features_list: List[RecommendationFeatures]):
    """Predict hiring probabilities for multiple provider-user pairs."""
    return predict_batch("recommendation", [f.model_dump() for f in features_list])

# Search Ranking Model Endpoints
@app.post("/predict/search_ranking")
async def predict_search_ranking(features: SearchRankingFeatures):
    """Predict search relevance score for a single provider."""
    return predict_single("search_ranking", features.model_dump())

@app.post("/predict/search_ranking/batch")
async def predict_search_ranking_batch(features_list: List[SearchRankingFeatures]):
    """Predict search relevance scores for multiple providers."""
    return predict_batch("search_ranking", [f.model_dump() for f in features_list])

# Personalization Model Endpoints
@app.post("/predict/personalization")
async def predict_personalization(features: PersonalizationFeatures):
    """Predict user preference for a single provider."""
    return predict_single("personalization", features.model_dump())

# Provider Performance Model Endpoints
@app.post("/predict/provider_performance")
async def predict_provider_performance(features: ProviderPerformanceFeatures):
    """Predict performance metrics for a single provider."""
    result = predict_single("provider_performance", features.model_dump())

    bundle = MODEL_BUNDLES.get("provider_performance")
    if bundle and "targets" in bundle:
        targets = bundle["targets"]
        if isinstance(result["prediction"], list):
            result["predictions"] = dict(zip(targets, result["prediction"]))
        else:
            result["overall_performance_score"] = result["prediction"]

    return result

@app.post("/predict/service_performance")
async def predict_service_performance(features: ServicePerformanceFeatures):
    """Predict service-level performance metrics for a single service."""
    result = predict_single("service_performance", features.model_dump())

    bundle = MODEL_BUNDLES.get("service_performance")
    if bundle and "targets" in bundle and isinstance(result.get("prediction"), list):
        result["predictions"] = dict(zip(bundle["targets"], result["prediction"]))

    return result

# User Engagement Model Endpoints
@app.post("/predict/user_engagement")
async def predict_user_engagement(features: UserEngagementFeatures):
    """Predict user engagement likelihood for a single user-provider interaction."""
    return predict_single("user_engagement", features.model_dump())

# User Segmentation Model Endpoints
@app.post("/predict/user_segmentation")
async def predict_user_segmentation(features: UserSegmentationFeatures):
    """Predict user segment for a single user."""
    result = predict_single("user_segmentation", features.model_dump())

    # For clustering models, return segment info
    bundle = MODEL_BUNDLES.get("user_segmentation")
    if bundle and "segment_names" in bundle:
        segment_names = bundle["segment_names"]
        cluster_id = int(result["prediction"])
        result["segment_name"] = segment_names.get(cluster_id, f"Segment {cluster_id}")
        result["segment_id"] = cluster_id

    return result

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)