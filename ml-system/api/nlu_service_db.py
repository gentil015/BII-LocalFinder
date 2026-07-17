"""
Updated FastAPI server for NLU - Uses Database Categories
Dynamically loads categories from MySQL database
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
from db_config import get_db_instance

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize FastAPI app
app = FastAPI(
    title="Multilingual NLU Service Classifier (Database-Driven)",
    description="Classify service requests using admin-defined categories from database",
    version="2.0.0"
)

# Add CORS middleware for PHP integration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global instances
classifier = None
db_instance = None
MODEL_PATH = Path(__file__).parent.parent / 'nlu_model'
CATEGORIES_CACHE = None


class NLURequest(BaseModel):
    """Request model for NLU endpoint"""
    text: str
    language: str = None


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


class CategoryInfo(BaseModel):
    """Category information"""
    id: int
    name: str
    icon: str = None
    description: str = None
    keywords: str = None


@app.on_event("startup")
async def load_model():
    """Load the NLU model and database on startup"""
    global classifier, db_instance, CATEGORIES_CACHE
    
    logger.info("Loading NLU model and database on startup...")
    
    try:
        # Load model
        if MODEL_PATH.exists():
            classifier = MultilingualNLUClassifier(output_dir=str(MODEL_PATH))
            classifier.load_model()
            logger.info("✓ Model loaded successfully")
        else:
            logger.warning("⚠ Model directory not found. Training may be required.")
            logger.warning("  Run: python train_with_db_categories.py")
        
        # Connect to database
        try:
            db_instance = get_db_instance()
            logger.info("✓ Database connected")
            
            # Cache categories
            CATEGORIES_CACHE = db_instance.get_categories(ai_enabled_only=True)
            logger.info(f"✓ Cached {len(CATEGORIES_CACHE)} AI-enabled categories from database")
        except Exception as e:
            logger.warning(f"⚠ Database connection failed: {e}")
            logger.warning("  API will work but won't save classifications or sync categories")
            
    except Exception as e:
        logger.error(f"Error during startup: {e}")


@app.on_event("shutdown")
async def shutdown():
    """Close database connection on shutdown"""
    global db_instance
    if db_instance:
        try:
            db_instance.close()
            logger.info("Database connection closed")
        except Exception as e:
            logger.error(f"Error closing database: {e}")


def detect_language(text: str) -> str:
    """
    Detect language from text
    Kinyarwanda typically uses non-ASCII characters
    """
    try:
        text.encode('ascii')
        return 'en'
    except UnicodeEncodeError:
        return 'rw'


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "model_loaded": classifier is not None,
        "database_connected": db_instance is not None,
        "categories_count": len(CATEGORIES_CACHE) if CATEGORIES_CACHE else 0
    }


@app.get("/categories")
async def get_categories():
    """Get all AI-enabled categories from database"""
    if CATEGORIES_CACHE:
        return {
            "categories": [
                {
                    "id": cat['id'],
                    "name": cat['name'],
                    "icon": cat.get('icon'),
                    "description": cat.get('description'),
                    "keywords": cat.get('ai_keywords')
                }
                for cat in CATEGORIES_CACHE
            ],
            "total": len(CATEGORIES_CACHE)
        }
    return {
        "categories": [],
        "total": 0,
        "warning": "No categories cached. Check if database is connected."
    }


@app.get("/model/info")
async def get_model_info():
    """Get model information"""
    if classifier:
        return {
            "model": classifier.model_name,
            "device": classifier.device,
            "labels": classifier.id2label,
            "categories_count": len(classifier.id2label)
        }
    return {
        "error": "Model not loaded",
        "message": "Run training script first"
    }


@app.post("/nlu", response_model=NLUResponse)
async def classify_text(request: NLURequest):
    """
    Classify a single text
    
    Args:
        request: NLURequest with text and optional language
        
    Returns:
        NLUResponse with classification result
    """
    if not classifier:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    if not request.text or not request.text.strip():
        raise HTTPException(status_code=400, detail="Text cannot be empty")
    
    try:
        # Detect language if not provided
        language = request.language or detect_language(request.text)
        
        # Get prediction
        prediction = classifier.predict(request.text)
        
        # Save to database if connected
        if db_instance:
            try:
                db_instance.save_classification(
                    query=request.text,
                    service_category=prediction['label'],
                    confidence=prediction['score'],
                    language=language
                )
            except Exception as e:
                logger.warning(f"Failed to save classification: {e}")
        
        return NLUResponse(
            text=request.text,
            label=prediction['label'],
            score=prediction['score'],
            language=language
        )
    except Exception as e:
        logger.error(f"Classification error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/nlu/batch", response_model=BatchNLUResponse)
async def classify_batch(request: BatchNLURequest):
    """
    Classify multiple texts in batch
    
    Args:
        request: BatchNLURequest with list of texts
        
    Returns:
        BatchNLUResponse with all predictions
    """
    if not classifier:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    if not request.texts or len(request.texts) == 0:
        raise HTTPException(status_code=400, detail="Texts list cannot be empty")
    
    if len(request.texts) > 100:
        raise HTTPException(status_code=400, detail="Maximum 100 texts per batch")
    
    try:
        predictions = []
        
        for text in request.texts:
            if not text or not text.strip():
                continue
            
            language = request.language or detect_language(text)
            prediction = classifier.predict(text)
            
            # Save to database if connected
            if db_instance:
                try:
                    db_instance.save_classification(
                        query=text,
                        service_category=prediction['label'],
                        confidence=prediction['score'],
                        language=language
                    )
                except Exception as e:
                    logger.warning(f"Failed to save classification: {e}")
            
            predictions.append(NLUResponse(
                text=text,
                label=prediction['label'],
                score=prediction['score'],
                language=language
            ))
        
        return BatchNLUResponse(
            predictions=predictions,
            total=len(request.texts),
            processed=len(predictions)
        )
    except Exception as e:
        logger.error(f"Batch classification error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/nlu/test")
async def test_nlu():
    """Test endpoint with predefined examples"""
    if not classifier:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    test_examples = [
        {"text": "I need a plumber to fix my pipes", "language": "en"},
        {"text": "Can you find me an electrician?", "language": "en"},
        {"text": "Ndashaka umuntu yishyura inzira", "language": "rw"},
        {"text": "Looking for a carpenter", "language": "en"},
        {"text": "I need professional cleaning services", "language": "en"},
    ]
    
    try:
        results = []
        for example in test_examples:
            prediction = classifier.predict(example['text'])
            results.append({
                "text": example['text'],
                "language": example['language'],
                "predicted_label": prediction['label'],
                "confidence": prediction['score']
            })
        
        return {
            "test_results": results,
            "total_tests": len(results),
            "model_status": "ready"
        }
    except Exception as e:
        logger.error(f"Test error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/nlu/sync-categories")
async def sync_categories():
    """Sync categories from database to model"""
    global CATEGORIES_CACHE
    
    if not db_instance:
        raise HTTPException(status_code=503, detail="Database not connected")
    
    try:
        CATEGORIES_CACHE = db_instance.get_categories(ai_enabled_only=True)
        return {
            "status": "success",
            "message": f"Synced {len(CATEGORIES_CACHE)} categories",
            "categories": [cat['name'] for cat in CATEGORIES_CACHE]
        }
    except Exception as e:
        logger.error(f"Sync error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)
