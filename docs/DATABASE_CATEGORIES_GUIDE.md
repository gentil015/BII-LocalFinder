# NLU with Database Categories - Implementation Guide

## Overview

The NLU system now dynamically loads **AI-enabled categories from the MySQL database** that admins create in the admin panel. No more hardcoded categories!

## Key Components

### 1. **ml-system/db_config.py**
Database handler that connects to MySQL and:
- Fetches AI-enabled categories
- Generates training data from category keywords
- Saves classification results
- Provides database integration utilities

### 2. **ml-system/train_with_db_categories.py**
Training script that:
- Connects to the database
- Loads all AI-enabled categories
- Generates training data from category keywords
- Trains the model
- Saves the trained model

### 3. **ml-system/api/nlu_service_db.py**
FastAPI server that:
- Loads model on startup
- Connects to database for category sync
- Caches categories in memory
- Saves classifications to database
- Provides `/nlu/sync-categories` endpoint for manual sync

## Database Schema Requirements

### Categories Table (existing)
```sql
CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(100),
  description TEXT,
  is_premium BOOLEAN DEFAULT 0,
  monthly_fee DECIMAL(10,2) DEFAULT 0,
  is_ai_enabled BOOLEAN DEFAULT 0,
  ai_keywords TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### NLU Classifications Table (for logging)
```sql
CREATE TABLE nlu_classifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  query TEXT NOT NULL,
  service_category VARCHAR(100),
  confidence DECIMAL(3,2),
  language VARCHAR(10),
  user_id INT,
  was_helpful BOOLEAN,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (service_category),
  INDEX (created_at),
  INDEX (user_id)
);
```

## Setup Steps

### 1. Create Database Tables
```bash
mysql -u root bii_localfinder < config/create_nlu_tables.sql
```

### 2. Create AI-Enabled Categories in Admin Panel
Go to: `http://yoursite/admin/categories.php`

Create categories with:
- **Name**: e.g., "Electrician", "Plumber", "Carpenter"
- **Icon**: e.g., "fa-bolt", "fa-wrench", "fa-hammer"
- **AI Keywords**: Comma-separated keywords for recognition
  - Example for Electrician: "electrical,wiring,lights,socket,outlet,power,circuit"
  - Example for Plumber: "plumbing,pipe,leak,drain,tap,water,toilet"
- **Enable AI**: Check this box to enable AI recognition

### 3. Train the Model with Database Categories
```bash
cd ml-system
python train_with_db_categories.py
```

This will:
- ✓ Connect to MySQL
- ✓ Load AI-enabled categories
- ✓ Generate training data from keywords
- ✓ Train the model
- ✓ Save trained model to `nlu_model/`

### 4. Start the API Server
```bash
cd ml-system/api
python -m uvicorn nlu_service_db:app --host 0.0.0.0 --port 8001 --reload
```

Or directly:
```bash
python nlu_service_db.py
```

### 5. Verify Setup
- Visit: `http://localhost:8001/docs` (Swagger UI)
- Click **GET /categories** to see loaded categories
- Click **POST /nlu/test** to test classifications

## API Endpoints (Database-Driven)

### GET /health
Check service health and database connection
```bash
curl http://localhost:8001/health
```
Response:
```json
{
  "status": "healthy",
  "model_loaded": true,
  "database_connected": true,
  "categories_count": 6
}
```

### GET /categories
Get all AI-enabled categories from database
```bash
curl http://localhost:8001/categories
```
Response:
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Electrician",
      "icon": "fa-bolt",
      "description": "Electrical work and installation",
      "keywords": "electrical,wiring,lights,socket,outlet,power,circuit"
    }
  ],
  "total": 6
}
```

### POST /nlu
Classify a single text
```bash
curl -X POST http://localhost:8001/nlu \
  -H "Content-Type: application/json" \
  -d '{"text": "I need a plumber to fix my pipes"}'
```
Response:
```json
{
  "text": "I need a plumber to fix my pipes",
  "label": "Plumber",
  "score": 0.95,
  "language": "en"
}
```

### POST /nlu/batch
Classify multiple texts
```bash
curl -X POST http://localhost:8001/nlu/batch \
  -H "Content-Type: application/json" \
  -d '{"texts": ["Need a plumber", "Looking for electrician"]}'
```

### POST /nlu/sync-categories
Manually sync categories from database (after adding new categories)
```bash
curl -X POST http://localhost:8001/nlu/sync-categories
```

## PHP Integration

### Using the Updated NLUClient

```php
<?php
require_once 'includes/NLUClient.php';

// Initialize client
$nlu = new NLUClient('http://localhost:8001');

// Classify single text
$result = $nlu->classify('I need a plumber to fix my sink');
echo $result['label'];      // "Plumber"
echo $result['score'];      // 0.95

// Get categories from database (now dynamic!)
$categories = $nlu->getCategories();
foreach ($categories['categories'] as $cat) {
    echo $cat['name'];      // Electrician, Plumber, etc.
    echo $cat['keywords'];  // ai_keywords from database
}

// Health check
if ($nlu->healthCheck()) {
    echo "NLU service is ready!";
    echo "Database connected: " . $health['database_connected'];
}
```

### Using NLUIntegration

```php
<?php
require_once 'config/database.php';
require_once 'includes/NLUIntegration.php';

$db = Database::getInstance()->getConnection();
$nluIntegration = new NLUIntegration($db);

// Process search with database categories
$result = $nluIntegration->processSearchQuery('I need an electrician');
// Returns providers for "Electrician" category from DB

// Get available categories from database
$categories = $nluIntegration->getServiceCategories();
```

## Workflow: Adding New Categories

### 1. Admin Creates Category
- Go to `/admin/categories.php`
- Click "Add New Category"
- Fill: Name, Icon, Description, AI Keywords
- Check "Enable AI"
- Save

### 2. Generate Training Data
Option A: Manual retraining (recommended monthly)
```bash
python ml-system/train_with_db_categories.py
# New category will be included in training data automatically
```

Option B: Automatic sync (limited)
```bash
curl -X POST http://localhost:8001/nlu/sync-categories
# Updates category cache but doesn't retrain model
```

### 3. Model Uses New Category
After retraining, the model predicts using the new category.

## Database Connections

### Configuration
All database details come from: `config/database.php`
```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bii_localfinder');
```

### Python uses same config
In `ml-system/db_config.py`:
```python
db = get_db_instance(
    host='127.0.0.1',
    user='root',
    password='',
    database='bii_localfinder'
)
```

## Monitoring & Logging

### View Classifications
```sql
SELECT * FROM nlu_classifications 
ORDER BY created_at DESC 
LIMIT 100;
```

### Category Popularity
```sql
SELECT service_category, COUNT(*) as count, AVG(confidence) as avg_confidence
FROM nlu_classifications
GROUP BY service_category
ORDER BY count DESC;
```

### Model Performance
```sql
SELECT 
    DATE(created_at) as date,
    service_category,
    AVG(confidence) as avg_confidence,
    COUNT(*) as total_classifications
FROM nlu_classifications
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), service_category;
```

## Troubleshooting

### Issue: "No AI-enabled categories found"
**Solution**: Go to admin panel and check at least one category's "Enable AI" checkbox

### Issue: Model not loading
**Solution**: Run training script first
```bash
python ml-system/train_with_db_categories.py
```

### Issue: Database connection failed
**Solution**: Verify database credentials in `config/database.php`

### Issue: Categories not syncing
**Solution**: Call sync endpoint manually
```bash
curl -X POST http://localhost:8001/nlu/sync-categories
```

## Performance Tips

1. **Enable AI only for major categories**: Reduce training data size
2. **Good keywords**: Use specific, relevant keywords
3. **Rebalance quarterly**: Retrain when category distribution changes
4. **Monitor confidence scores**: Low scores indicate need for more training data
5. **Cache categories**: API caches categories in memory (reload with sync endpoint)

## Files Created/Modified

### New Files
- `ml-system/db_config.py` - Database handling
- `ml-system/train_with_db_categories.py` - Training with DB categories
- `ml-system/api/nlu_service_db.py` - API with DB integration

### Existing Files Using Database Categories
- `includes/NLUClient.php` - Already compatible
- `includes/NLUIntegration.php` - Already compatible

### Database Tables
- `categories` - Admin creates these
- `nlu_classifications` - Auto-populated by API

## Next Steps

1. ✓ Go to `/admin/categories.php`
2. ✓ Create/mark categories as AI-enabled
3. ✓ Run `python ml-system/train_with_db_categories.py`
4. ✓ Start API: `python ml-system/api/nlu_service_db.py`
5. ✓ Test at `http://localhost:8001/docs`
6. ✓ Integrate in your PHP code using `NLUClient`
