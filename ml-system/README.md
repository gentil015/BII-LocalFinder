# ML Recommendation System

This system provides real-time provider ranking and personalized recommendations for the Bii LocalFinder platform using machine learning.

## Overview

The ML system consists of:
- **Real-time provider ranking** using behavioral features (views, clicks, messages, rating, price, response time)
- **Batch prediction API** for efficient processing of multiple providers
- **Fallback ranking** using rating and response time when ML API is unavailable
- **FastAPI prediction service** with vectorized processing
- **PHP client integration** with automatic fallback logic

## Recent Improvements

### 🚀 Real-time Provider Ranking
- **Batch API endpoint** (`/predict/batch`) accepts multiple providers in one request
- **Vectorized predictions** for optimal performance with large provider lists
- **Automatic fallback** to rating + response time scoring when API is unavailable
- **Efficient feature building** with database query optimization

### 📈 Performance Optimizations
- Single API call for all providers instead of individual requests
- Vectorized model predictions using NumPy
- Reduced network overhead and latency
- Smart caching of provider features

### 🛡️ Reliability Features
- **Graceful degradation** - system works even when ML API is down
- **Fallback scoring** based on rating (70%) and response time (30%)
- **Error handling** with detailed logging
- **Health checks** for API monitoring

## Setup

1. Install Python dependencies:
   ```bash
   pip install -r requirements.txt
   ```

2. Configure environment variables:
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

3. Train the model:
   ```bash
   python model/train_model.py
   ```

4. Start the prediction service:
   ```bash
   uvicorn api.app:app --host 0.0.0.0 --port 8000 --reload
   ```

5. Test the system:
   ```bash
   php test_ml_system.php
   ```

## API Endpoints

### POST `/predict/batch`
Predicts hire probability for multiple providers at once.

**Request:**
```json
[
  {
    "provider_id": 1,
    "features": {
      "views": 150,
      "clicks": 25,
      "messages": 10,
      "rating": 4.5,
      "price": 50000,
      "avg_response_time": 2.5
    }
  }
]
```

**Response:**
```json
[
  {
    "provider_id": 1,
    "prediction": 1,
    "probability": 0.785,
    "confidence": "high"
  }
]
```

### GET `/health`
Health check endpoint.

### GET `/model/info`
Model metadata and feature information.

## PHP Integration

### Basic Usage
```php
require_once 'includes/MLRecommender.php';

$recommender = new MLRecommender($db);
$rankedProviders = $recommender->rankProviders($providers);
// $providers now sorted by ML score (highest first)
```

### Fallback Behavior
When the ML API is unavailable, the system automatically falls back to:
- **70% weight**: Provider rating (0-5 stars)
- **30% weight**: Response time score (faster = better)

### Configuration
Set the ML API URL in system settings:
```sql
INSERT INTO system_settings (setting_key, setting_value) VALUES
('ml_api_base_url', 'http://localhost:8000'),
('enable_ml_recommendations', '1');
```

## Testing

Run the test script to verify the system:
```bash
php ml-system/test_ml_system.php
```

This will test:
- API connectivity
- Provider ranking functionality
- Fallback behavior
- Error handling

Endpoints:
- GET /health
- POST /recommend/{user_id}

## PHP Integration

Use MLRecommender.php in your includes/ directory.

Example:
```php
$recommender = new MLRecommender();
$recommendations = $recommender->getRecommendations($user_id);
```

## File Structure

- data/: Data export and storage
- model/: Model training and storage
- api/: Prediction service
- utils/: Helper utilities
- requirements.txt: Python dependencies
- retrain.sh: Automation script
- .env.example: Environment template