"""
FastAPI server for Multilingual NLU Service Category Classification
Endpoint for real-time inference
"""

import json
import torch
from pathlib import Path
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import logging
import sys

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from models.nlu_service_classifier import MultilingualNLUClassifier

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize Flask app
app = FastAPI(
    title="Multilingual NLU Service Classifier",
    description="Classify service requests in English and Kinyarwanda",
    version="1.0.0"
)

# Add CORS middleware for PHP integration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global classifier instance
classifier = None
MODEL_PATH = Path(__file__).parent.parent / 'nlu_model'


class NLURequest(BaseModel):
    """Request model for NLU endpoint"""
    text: str
    language: str = None  # Optional: 'en', 'rw', or auto-detect


class NLUResponse(BaseModel):
    """Response model for NLU endpoint"""
    text: str
    label: str
    score: float
    language: str = None


class BatchNLURequest(BaseModel):
    """Batch request model"""
    texts: list[str]
    language: str = None


class BatchNLUResponse(BaseModel):
    """Batch response model"""
    predictions: list[NLUResponse]
    total: int
    processed: int


@app.on_event("startup")
async def load_model():
    """Load the NLU model on startup"""
    global classifier
    
    logger.info("Loading NLU model on startup...")
    
    try:
        classifier = MultilingualNLUClassifier(
            model_name='xlm-roberta-base',
            output_dir=str(MODEL_PATH)
        )
        
        # Try to load pre-trained model
        if MODEL_PATH.exists():
            classifier.load_model()
            logger.info("Pre-trained model loaded successfully")
        else:
            logger.warning(f"Model not found at {MODEL_PATH}")
            logger.info("Please train the model first using: python models/nlu_service_classifier.py")
    
    except Exception as e:
        logger.error(f"Error loading model: {str(e)}")
        raise


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "model_loaded": classifier is not None and classifier.model is not None,
        "device": str(classifier.device) if classifier else "unknown"
    }


@app.post("/nlu", response_model=NLUResponse)
async def classify_service(request: NLURequest):
    """
    Classify a service request into service categories
    
    Args:
        request: NLU request with text and optional language
        
    Returns:
        NLUResponse with predicted label and confidence score
        
    Example:
        POST /nlu
        {
            "text": "I need a plumber to fix my pipes",
            "language": "en"
        }
        
        Response:
        {
            "text": "I need a plumber to fix my pipes",
            "label": "plumber",
            "score": 0.95,
            "language": "en"
        }
    """
    
    if classifier is None or classifier.model is None:
        raise HTTPException(
            status_code=503,
            detail="Model not loaded. Please train the model first."
        )
    
    if not request.text or not request.text.strip():
        raise HTTPException(
            status_code=400,
            detail="Text cannot be empty"
        )
    
    try:
        # Make prediction
        prediction = classifier.predict(request.text.strip())
        
        # Detect language (simplified - check for non-ASCII characters)
        detected_language = request.language or "en"
        if any(ord(c) > 127 for c in request.text):
            detected_language = "rw"  # Kinyarwanda text detected
        
        return NLUResponse(
            text=prediction['text'],
            label=prediction['label'],
            score=prediction['score'],
            language=detected_language
        )
    
    except Exception as e:
        logger.error(f"Error during prediction: {str(e)}")
        raise HTTPException(
            status_code=500,
            detail=f"Prediction error: {str(e)}"
        )


@app.post("/nlu/batch", response_model=BatchNLUResponse)
async def classify_batch(request: BatchNLURequest):
    """
    Classify multiple service requests in batch
    
    Args:
        request: Batch request with list of texts
        
    Returns:
        BatchNLUResponse with predictions for all texts
        
    Example:
        POST /nlu/batch
        {
            "texts": [
                "I need a plumber",
                "Ndashaka electrician",
                "Clean my house"
            ]
        }
    """
    
    if classifier is None or classifier.model is None:
        raise HTTPException(
            status_code=503,
            detail="Model not loaded. Please train the model first."
        )
    
    if not request.texts or len(request.texts) == 0:
        raise HTTPException(
            status_code=400,
            detail="Texts list cannot be empty"
        )
    
    if len(request.texts) > 100:
        raise HTTPException(
            status_code=400,
            detail="Maximum 100 texts per batch"
        )
    
    try:
        predictions = []
        
        for text in request.texts:
            if not text or not text.strip():
                continue
            
            prediction = classifier.predict(text.strip())
            
            # Detect language
            detected_language = request.language or "en"
            if any(ord(c) > 127 for c in text):
                detected_language = "rw"
            
            predictions.append(
                NLUResponse(
                    text=prediction['text'],
                    label=prediction['label'],
                    score=prediction['score'],
                    language=detected_language
                )
            )
        
        return BatchNLUResponse(
            predictions=predictions,
            total=len(request.texts),
            processed=len(predictions)
        )
    
    except Exception as e:
        logger.error(f"Error during batch prediction: {str(e)}")
        raise HTTPException(
            status_code=500,
            detail=f"Batch prediction error: {str(e)}"
        )


@app.get("/categories")
async def get_categories():
    """Get all available service categories"""
    
    if classifier is None:
        raise HTTPException(
            status_code=503,
            detail="Model not loaded"
        )
    
    return {
        "categories": list(classifier.label2id.keys()),
        "total": len(classifier.label2id),
        "model": "xlm-roberta-base"
    }


@app.get("/model/info")
async def model_info():
    """Get information about the loaded model"""
    
    if classifier is None:
        raise HTTPException(
            status_code=503,
            detail="Model not loaded"
        )
    
    return {
        "model_name": classifier.model_name,
        "device": str(classifier.device),
        "num_labels": len(classifier.id2label),
        "label_mappings": {
            "id2label": classifier.id2label,
            "label2id": classifier.label2id
        }
    }


@app.post("/nlu/test")
async def test_prediction():
    """Test endpoint with predefined examples"""
    
    if classifier is None or classifier.model is None:
        raise HTTPException(
            status_code=503,
            detail="Model not loaded"
        )
    
    test_cases = [
        {"text": "I need a plumber to fix my pipes", "language": "en"},
        {"text": "Ndashaka electrician kubishyura amashanyalaze", "language": "rw"},
        {"text": "Clean my house", "language": "en"},
        {"text": "Ndashaka umuntu yomurika inzu", "language": "rw"},
        {"text": "I need a painter", "language": "en"},
    ]
    
    results = []
    for test_case in test_cases:
        prediction = classifier.predict(test_case['text'])
        results.append({
            "text": prediction['text'],
            "label": prediction['label'],
            "score": prediction['score'],
            "language": test_case['language']
        })
    
    return {
        "test_results": results,
        "total": len(results)
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)
