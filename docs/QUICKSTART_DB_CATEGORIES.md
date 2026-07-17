# Quick Start: NLU with Database Categories

## 5-Minute Setup

### Step 1: Create AI-Enabled Categories (2 min)
1. Go to `/admin/categories.php`
2. Click **"Add New Category"**
3. Fill in:
   - **Name**: e.g., "Electrician"
   - **Icon**: e.g., "fa-bolt"
   - **Description**: "Electrical work and installation"
   - **AI Keywords**: "electrical,wiring,lights,socket,outlet"
4. ✓ Check **"Enable AI"**
5. Click Save

Repeat for other service types (Plumber, Carpenter, Cleaner, etc.)

### Step 2: Train Model (2 min)
```bash
cd c:\xampp\htdocs\Bii_localFinder\ml-system
python train_with_db_categories.py
```

**What it does:**
- ✓ Reads AI-enabled categories from database
- ✓ Generates training data from keywords
- ✓ Trains multilingual model
- ✓ Saves model to `nlu_model/`

**Expected output:**
```
Loading NLU model and database on startup...
✓ Model loaded successfully
✓ Database connected
✓ Cached 6 AI-enabled categories from database
Generated 120 training examples
Starting model training...
✓ Model training completed successfully!
```

### Step 3: Start API Server (instant)
**Option A: Development**
```bash
cd ml-system\api
python -m uvicorn nlu_service_db:app --host 0.0.0.0 --port 8001 --reload
```

**Option B: Background (PowerShell)**
```bash
Start-Process python -ArgumentList "ml-system\api\nlu_service_db.py" -NoNewWindow
```

### Step 4: Test It Works
Visit: **`http://localhost:8001/docs`**

⚡ **Interactive testing interface appears!**

Choose **POST /nlu** and enter:
```json
{
  "text": "I need a plumber to fix my pipes"
}
```

Expected response:
```json
{
  "text": "I need a plumber to fix my pipes",
  "label": "Plumber",
  "score": 0.95,
  "language": "en"
}
```

## Usage in PHP Code

### Basic Classification
```php
<?php
require_once 'includes/NLUClient.php';

$nlu = new NLUClient('http://localhost:8001');

$result = $nlu->classify('I need an electrician');
echo $result['label'];   // "Electrician"
echo $result['score'];   // 0.92
```

### Verify Database Connection
```php
$health = $nlu->healthCheck();
if ($health['database_connected']) {
    echo "✓ Using database categories!";
}
```

### Get Categories from Database
```php
$response = $nlu->getCategories();
foreach ($response['categories'] as $category) {
    echo $category['name'];      // "Electrician", "Plumber", etc.
    echo $category['keywords'];  // From database ai_keywords
}
```

## Adding New Categories Later

1. **Create in admin panel**
   - `/admin/categories.php`
   - Check "Enable AI"

2. **Retrain model**
   ```bash
   python ml-system/train_with_db_categories.py
   ```

3. **API automatically uses it**
   - No server restart needed
   - Model predicts new category

## Verify Setup

### Check Categories Loaded
```bash
curl http://localhost:8001/categories
```

### Check Model Status
```bash
curl http://localhost:8001/health
```

### Test Classification
```bash
curl -X POST http://localhost:8001/nlu -H "Content-Type: application/json" -d "{\"text\": \"I need a carpenter\"}"
```

## Key Differences from Original

| Feature | Original | Database-Driven |
|---------|----------|-----------------|
| **Categories** | Hardcoded (6 types) | Admin-created, dynamic |
| **Adding categories** | Must edit JSON + retrain | Admin panel + retrain |
| **Training data** | Static JSON file | Auto-generated from keywords |
| **Logging** | Optional | Automatic classification logging |
| **Category sync** | Manual upload | Automatic database load |

## Files You Need

**Core files:**
- ✓ `ml-system/db_config.py` - Database handler
- ✓ `ml-system/train_with_db_categories.py` - Training script
- ✓ `ml-system/api/nlu_service_db.py` - API server
- ✓ `includes/NLUClient.php` - PHP client (unchanged)

**Database:**
- ✓ `categories` table (already exists)
- ✓ `nlu_classifications` table (created by SQL script)

## Stopping the Service

**PowerShell:**
```bash
Get-Process python | Stop-Process -Force
```

**To keep running after closing terminal:**
```bash
$env:FLASK_ENV="production"
Start-Process python -ArgumentList "ml-system\api\nlu_service_db.py" -WindowStyle Hidden
```

## Common Commands

```bash
# Train with latest categories
python ml-system/train_with_db_categories.py

# Start API
python ml-system/api/nlu_service_db.py

# Test API
curl http://localhost:8001/health

# Sync categories (after adding new ones in admin)
curl -X POST http://localhost:8001/nlu/sync-categories

# View logs
# On Windows, use:
Get-Content "logs\nlu.log" -Tail 50
```

## Next Steps

1. ✓ Create categories in admin panel
2. ✓ Run training script
3. ✓ Start API server
4. ✓ Test classifications
5. ✓ Integrate into your PHP code
6. ✓ Monitor classifications in database

## Full Documentation

For detailed information and advanced configuration, see:
- `DATABASE_CATEGORIES_GUIDE.md` - Complete guide
- `NLU_README.md` - Original NLU documentation
- `DEPLOYMENT_NLU.md` - Production deployment

## Troubleshooting

**Q: "No AI-enabled categories found"**
A: Go to `/admin/categories.php`, add categories and check "Enable AI"

**Q: "Model not loaded"**
A: Run `python ml-system/train_with_db_categories.py` first

**Q: "Database connection failed"**
A: Check `config/database.php` for correct credentials

**Q: Categories not showing in API**
A: Call `POST /nlu/sync-categories` endpoint

**Q: Low confidence scores**
A: Add more keywords to categories in admin panel and retrain
