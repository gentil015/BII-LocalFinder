# Multi-Model ML Recommendation System

This system provides real-time provider ranking and personalized recommendations for the Bii LocalFinder platform using multiple specialized machine learning models.

## Overview

The ML system consists of **6 specialized models**:
- **Recommendation Model**: Predicts hiring probability for personalized recommendations
- **Search Ranking Model**: Ranks providers in search results based on relevance
- **Personalization Model**: Learns user preferences for customized suggestions
- **Provider Performance Model**: Predicts provider success metrics and performance scores
- **User Engagement Model**: Predicts user interaction patterns and engagement levels
- **User Segmentation Model**: Clusters users into behavioral segments for targeted marketing

## Architecture

```
ml-system/
├── models/                          # Individual model directories
│   ├── recommendation/             # Hiring probability prediction
│   ├── search_ranking/             # Search result ranking
│   ├── personalization/            # User preference learning
│   ├── provider_performance/       # Provider metrics prediction
│   ├── user_engagement/            # User behavior prediction
│   └── user_segmentation/          # User behavioral clustering
├── data/                           # Training data export scripts
├── api/                            # FastAPI service and PHP clients
├── utils/                          # Shared utilities
└── train_all_models.py            # Master training script
```

## Quick Start

1. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

3. **Train all models:**
   ```bash
   python train_all_models.py
   ```

4. **Start the API service:**
   ```bash
   uvicorn api.multi_model_app:app --host 0.0.0.0 --port 8000 --reload
   ```

## Model Details

### 1. Recommendation Model
- **Purpose**: Predicts the probability that a user will hire a provider
- **Features**: Provider activity (views, clicks, messages), ratings, pricing, response times, user behavior patterns
- **Use Case**: Personalized provider recommendations on homepage and search results
- **Algorithm**: RandomForest Classifier

### 2. Search Ranking Model
- **Purpose**: Ranks providers in search results by relevance to the query
- **Features**: Provider quality metrics, search context, user preferences, location/category matching
- **Use Case**: Improving search result quality and user satisfaction
- **Algorithm**: RandomForest Regressor

### 3. Personalization Model
- **Purpose**: Learns individual user preferences for providers
- **Features**: User booking history, provider characteristics, interaction patterns
- **Use Case**: Highly personalized provider suggestions
- **Algorithm**: RandomForest Classifier

### 4. Provider Performance Model
- **Purpose**: Predicts provider success metrics and overall performance
- **Features**: Provider profile data, historical performance, activity metrics
- **Use Case**: Provider analytics and performance insights
- **Algorithm**: RandomForest Regressor (multi-output)

### 5. User Engagement Model
- **Purpose**: Predicts user engagement and interaction likelihood
- **Features**: User profile, session context, provider characteristics, interaction history
- **Use Case**: Optimizing user experience and conversion rates
- **Algorithm**: RandomForest Classifier

## API Endpoints

The FastAPI service provides the following endpoints:

### Recommendation Model
```http
POST /predict/recommendation
POST /predict/recommendation/batch
```

### Search Ranking Model
```http
POST /predict/search_ranking
POST /predict/search_ranking/batch
```

### Personalization Model
```http
POST /predict/personalization
```

### Provider Performance Model
```http
POST /predict/provider_performance
```

### User Engagement Model
```http
POST /predict/user_engagement
```

### Management
```http
GET  /health          # Service health check
GET  /models/info     # Model metadata
POST /reload-models   # Reload model bundles
```

## PHP Integration

Use the `MultiModelRecommender` class for PHP integration:

```php
require_once '../includes/MultiModelRecommender.php';
$recommender = new MultiModelRecommender($db);

// Recommendation ranking
$providers = $recommender->rankByRecommendation($providers, $userId);

// Search ranking
$providers = $recommender->rankBySearchRelevance($providers, $searchQuery, $userId, $filters);

// Personalization
$preferences = $recommender->getPersonalizedPreferences($userId, $providers);

// Provider performance
$performance = $recommender->predictProviderPerformance($providerData);

// User engagement
$engagement = $recommender->predictUserEngagement($userId, $providerData, $context);
```

## Training Data

Each model requires specific training data exported from your database:

- **Recommendation Data**: Booking history with provider-user interactions
- **Search Ranking Data**: Search queries with click-through data
- **Personalization Data**: User-provider interaction patterns
- **Provider Performance Data**: Provider metrics and historical performance
- **User Engagement Data**: User session and interaction data

Run data export scripts individually or use the master training script.

## Performance & Reliability

### Features
- **Batch Processing**: Efficient handling of multiple providers in single requests
- **Graceful Degradation**: System works even when individual models are unavailable
- **Automatic Fallback**: Rating-based scoring when ML API is down
- **Health Monitoring**: Built-in health checks and model status tracking
- **Error Handling**: Comprehensive error handling with detailed logging

### Optimization
- Vectorized predictions using NumPy
- Reduced network overhead through batching
- Smart caching of provider features
- Asynchronous processing capabilities

## Deployment

### Production Setup
1. Train models on a regular schedule (weekly/monthly)
2. Deploy models to production API servers
3. Monitor model performance and drift
4. Retrain models when accuracy degrades

### Monitoring
- Check `/health` endpoint for service status
- Monitor prediction logs for anomalies
- Track model performance metrics
- Set up alerts for API failures

## Troubleshooting

### Common Issues
- **Model not found**: Ensure models are trained and saved correctly
- **Database connection failed**: Check database credentials in `.env`
- **Memory errors**: Reduce batch sizes or increase server memory
- **Slow predictions**: Check model file sizes and server resources

### Logs
- Prediction logs: `logs/predictions.jsonl`
- Training reports: `training_report.json`
- Model evaluations: `models/*/evaluation.json`

## Development

### Adding New Models
1. Create model directory under `models/`
2. Implement training script with proper feature engineering
3. Add data export script in `data/`
4. Update `multi_model_app.py` with new endpoints
5. Update `MultiModelRecommender.php` with new methods
6. Add to `train_all_models.py`

### Model Validation
- Always validate features match between training and prediction
- Test models with edge cases and missing data
- Monitor feature distributions for drift
- Regular retraining with fresh datacorn api.multi_model_app:app --host 0.0.0.0 --port 8000 --reload
   ```

## Model Details

### 1. Recommendation Model
- **Purpose**: Predicts the probability that a user will hire a provider
- **Features**: Provider activity (views, clicks, messages), ratings, pricing, response times, user behavior patterns
- **Use Case**: Personalized provider recommendations on homepage and search results
- **Algorithm**: RandomForest Classifier

### 2. Search Ranking Model
- **Purpose**: Ranks providers in search results by relevance to the query
- **Features**: Provider quality metrics, search context, user preferences, location/category matching
- **Use Case**: Improving search result quality and user satisfaction
- **Algorithm**: RandomForest Regressor

### 3. Personalization Model
- **Purpose**: Learns individual user preferences for providers
- **Features**: User booking history, provider characteristics, interaction patterns
- **Use Case**: Highly personalized provider suggestions
- **Algorithm**: RandomForest Classifier

### 4. Provider Performance Model
- **Purpose**: Predicts provider success metrics and overall performance
- **Features**: Provider profile data, historical performance, activity metrics
- **Use Case**: Provider analytics and performance insights
- **Algorithm**: RandomForest Regressor (multi-output)

### 5. User Engagement Model
- **Purpose**: Predicts user engagement and interaction likelihood
- **Features**: User profile, session context, provider characteristics, interaction history
- **Use Case**: Optimizing user experience and conversion rates
- **Algorithm**: RandomForest Classifier

## API Endpoints

The FastAPI service provides the following endpoints:

### Recommendation Model
```http
POST /predict/recommendation
POST /predict/recommendation/batch
```

### Search Ranking Model
```http
POST /predict/search_ranking
POST /predict/search_ranking/batch
```

### Personalization Model
```http
POST /predict/personalization
```

### Provider Performance Model
```http
POST /predict/provider_performance
```

### User Engagement Model
```http
POST /predict/user_engagement
```

### Management
```http
GET  /health          # Service health check
GET  /models/info     # Model metadata
POST /reload-models   # Reload model bundles
```

## PHP Integration

Use the `MultiModelRecommender` class for PHP integration:

```php
require_once '../includes/MultiModelRecommender.php';
$recommender = new MultiModelRecommender($db);

// Recommendation ranking
$providers = $recommender->rankByRecommendation($providers, $userId);

// Search ranking
$providers = $recommender->rankBySearchRelevance($providers, $searchQuery, $userId, $filters);

// Personalization
$preferences = $recommender->getPersonalizedPreferences($userId, $providers);

// Provider performance
$performance = $recommender->predictProviderPerformance($providerData);

// User engagement
$engagement = $recommender->predictUserEngagement($userId, $providerData, $context);
```

## Training Data

Each model requires specific training data exported from your database:

- **Recommendation Data**: Booking history with provider-user interactions
- **Search Ranking Data**: Search queries with click-through data
- **Personalization Data**: User-provider interaction patterns
- **Provider Performance Data**: Provider metrics and historical performance
- **User Engagement Data**: User session and interaction data

Run data export scripts individually or use the master training script.

## Performance & Reliability

### Features
- **Batch Processing**: Efficient handling of multiple providers in single requests
- **Graceful Degradation**: System works even when individual models are unavailable
- **Automatic Fallback**: Rating-based scoring when ML API is down
- **Health Monitoring**: Built-in health checks and model status tracking
- **Error Handling**: Comprehensive error handling with detailed logging

### Optimization
- Vectorized predictions using NumPy
- Reduced network overhead through batching
- Smart caching of provider features
- Asynchronous processing capabilities

## Deployment

### Production Setup
1. Train models on a regular schedule (weekly/monthly)
2. Deploy models to production API servers
3. Monitor model performance and drift
4. Retrain models when accuracy degrades

### Monitoring
- Check `/health` endpoint for service status
- Monitor prediction logs for anomalies
- Track model performance metrics
- Set up alerts for API failures

## Troubleshooting

### Common Issues
- **Model not found**: Ensure models are trained and saved correctly
- **Database connection failed**: Check database credentials in `.env`
- **Memory errors**: Reduce batch sizes or increase server memory
- **Slow predictions**: Check model file sizes and server resources

### Logs
- Prediction logs: `logs/predictions.jsonl`
- Training reports: `training_report.json`
- Model evaluations: `models/*/evaluation.json`

## Development

### Adding New Models
1. Create model directory under `models/`
2. Implement training script with proper feature engineering
3. Add data export script in `data/`
4. Update `multi_model_app.py` with new endpoints
5. Update `MultiModelRecommender.php` with new methods
6. Add to `train_all_models.py`

### Model Validation
- Always validate features match between training and prediction
- Test models with edge cases and missing data
- Monitor feature distributions for drift
- Regular retraining with fresh data

4. Start the prediction service:
   ```bash
   uvicorn api.app:app --host 0.0.0.0 --port 8000 --reload
   ```

5. After retraining, the `ml-system/retrain.sh` pipeline will automatically request the FastAPI `/reload-model` endpoint to load the new model if the service is reachable.

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
      "avg_response_time": 2.5,
      "user_avg_price": 0,
      "user_avg_response_time": 24,
      "user_total_bookings": 0
    }
  }
]
```

### POST `/predict/action`
Predicts the hire probability for a single provider and returns a recommended action.

**Request:**
```json
{
  "provider_id": 1,
  "features": {
    "views": 150,
    "clicks": 25,
    "messages": 10,
    "rating": 4.5,
    "price": 50000,
    "avg_response_time": 2.5,
    "user_avg_price": 0,
    "user_avg_response_time": 24,
    "user_total_bookings": 0
  }
}
```

**Response:**
```json
{
  "provider_id": 1,
  "prediction": 1,
  "probability": 0.785,
  "confidence": "high",
  "action": "recommend",
  "action_reason": "Strong candidate for recommendation."
}
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