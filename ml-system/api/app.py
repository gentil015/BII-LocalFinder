"""
app.py  —  Provider Recommendation Prediction API
---------------------------------------------------
Serves a trained Logistic Regression model via FastAPI.

Start:
    uvicorn app:app --host 0.0.0.0 --port 8000 --reload

Endpoints:
    POST /predict       — single provider prediction
    POST /predict/batch — bulk predictions for a list of providers
    GET  /health        — health check
    GET  /model/info    — model metadata
"""

import os
import sys
import joblib
import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field, field_validator
from typing import List, Optional

# ── Paths ─────────────────────────────────────────────────────────────────────
BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "..", "model", "model.pkl")

# ── Load model bundle ─────────────────────────────────────────────────────────
if not os.path.exists(MODEL_PATH):
    sys.exit(
        f"[ERROR] model.pkl not found at: {MODEL_PATH}\n"
        "Run train_model.py first."
    )

bundle   = joblib.load(MODEL_PATH)
MODEL    = bundle["model"]
SCALER   = bundle["scaler"]
FEATURES = bundle["features"]   # ordered list of feature names

print(f"[INFO] Model loaded — features: {FEATURES}")

# ── FastAPI app ───────────────────────────────────────────────────────────────
app = FastAPI(
    title="Provider Recommendation API",
    description="Predicts the probability that a service provider will be hired, "
                "based on behavioral and profile features.",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],   # restrict to your PHP server in production
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


# ── Request / Response schemas ────────────────────────────────────────────────
class ProviderFeatures(BaseModel):
    """Input features for one provider — must match training columns exactly."""
    views:             float = Field(..., ge=0,  description="Number of profile views")
    clicks:            float = Field(..., ge=0,  description="Number of profile clicks")
    messages:          float = Field(..., ge=0,  description="Number of messages received")
    rating:            float = Field(..., ge=0, le=5, description="Average star rating (0-5)")
    price:             float = Field(..., ge=0,  description="Average service price (RWF)")
    avg_response_time: float = Field(..., ge=0,  description="Average response time in hours")
    user_avg_price:    float = Field(..., ge=0,  description="User's average booking price")
    user_avg_response_time: float = Field(..., ge=0, description="User's average provider response time")
    user_total_bookings: int = Field(..., ge=0,   description="User's total number of bookings")

    @field_validator("rating")
    @classmethod
    def rating_range(cls, v):
        if not (0.0 <= v <= 5.0):
            raise ValueError("rating must be between 0 and 5")
        return v


class PredictionResult(BaseModel):
    prediction:  int   # 1 = likely hired, 0 = unlikely
    probability: float  # hire probability (0.0 – 1.0)
    confidence:  str    # human-readable confidence label


class BatchItem(BaseModel):
    provider_id: int
    features:    ProviderFeatures


class BatchResult(BaseModel):
    provider_id: int
    prediction:  int
    probability: float
    confidence:  str


# ── Helpers ───────────────────────────────────────────────────────────────────
def features_to_array(f: ProviderFeatures) -> np.ndarray:
    """Convert a ProviderFeatures object to a numpy array in the correct column order."""
    return np.array([[
        f.views,
        f.clicks,
        f.messages,
        f.rating,
        f.price,
        f.avg_response_time,
        f.user_avg_price,
        f.user_avg_response_time,
        f.user_total_bookings,
    ]])


def confidence_label(prob: float) -> str:
    if prob >= 0.80:
        return "very high"
    elif prob >= 0.60:
        return "high"
    elif prob >= 0.40:
        return "medium"
    elif prob >= 0.20:
        return "low"
    else:
        return "very low"


def predict_one(f: ProviderFeatures) -> PredictionResult:
    X       = features_to_array(f)
    X_sc    = SCALER.transform(X)
    pred    = int(MODEL.predict(X_sc)[0])
    prob    = float(MODEL.predict_proba(X_sc)[0][1])
    return PredictionResult(
        prediction  = pred,
        probability = round(prob, 4),
        confidence  = confidence_label(prob),
    )


# ── Routes ────────────────────────────────────────────────────────────────────
@app.get("/health")
def health_check():
    """Liveness probe — returns 200 when the API is ready."""
    return {"status": "ok", "model_loaded": True}


@app.get("/model/info")
def model_info():
    """Returns metadata about the currently loaded model."""
    return {
        "model_type": type(MODEL).__name__,
        "feature_columns": FEATURES,
        "model_file": MODEL_PATH,
    }


@app.post("/predict", response_model=PredictionResult)
def predict(features: ProviderFeatures):
    """
    Predict whether a single provider is likely to be hired.

    Returns:
    - prediction  : 1 (likely hired) or 0 (unlikely)
    - probability : float between 0 and 1
    - confidence  : human-readable label
    """
    try:
        return predict_one(features)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/predict/batch", response_model=List[BatchResult])
def predict_batch(items: List[BatchItem]):
    """
    Predict for a list of providers at once.
    Returns results sorted by probability DESC (best candidates first).
    Optimized for performance with vectorized predictions.
    """
    if not items:
        return []

    try:
        # Extract features for all providers
        features_list = []
        provider_ids = []

        for item in items:
            provider_ids.append(item.provider_id)
            # Convert ProviderFeatures to numpy array
            features_array = features_to_array(item.features)
            features_list.append(features_array[0])  # Remove extra dimension

        # Convert to numpy array for vectorized processing
        X = np.array(features_list)

        # Scale all features at once
        X_sc = SCALER.transform(X)

        # Predict all at once (vectorized)
        predictions = MODEL.predict(X_sc)
        probabilities = MODEL.predict_proba(X_sc)[:, 1]  # Probability of class 1 (hired)

        # Build results
        results = []
        for i, provider_id in enumerate(provider_ids):
            prob = float(probabilities[i])
            pred = int(predictions[i])

            results.append(BatchResult(
                provider_id=provider_id,
                prediction=pred,
                probability=round(prob, 4),
                confidence=confidence_label(prob),
            ))

        # Sort best providers first (highest probability)
        results.sort(key=lambda r: r.probability, reverse=True)
        return results

    except Exception as e:
        # On any error, return empty results to allow fallback
        print(f"[ERROR] Batch prediction failed: {str(e)}")
        return []