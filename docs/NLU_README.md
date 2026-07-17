# Multilingual NLU Service Classification System

## Overview

This is a complete multilingual NLU (Natural Language Understanding) system for classifying service requests in both English and Kinyarwanda. It uses XLM-RoBERTa, a state-of-the-art multilingual transformer model from Hugging Face.

## Features

✅ **Multilingual Support**: English and Kinyarwanda  
✅ **Deep Learning**: XLM-RoBERTa base model (110M parameters)  
✅ **Fast Inference**: CPU and GPU optimized  
✅ **RESTful API**: FastAPI endpoint for easy integration  
✅ **Batch Processing**: Classify multiple texts at once  
✅ **Confidence Scores**: Get prediction confidence for each classification  
✅ **PHP Integration**: Native PHP client included  

## Architecture

### Components

```
ml-system/
├── models/
│   └── nlu_service_classifier.py          # Training script and model class
├── api/
│   └── nlu_service.py                     # FastAPI inference server
├── data/
│   └── nlu_service_categories.json        # Training dataset
└── nlu_model/                             # Trained model artifacts (after training)
    ├── config.json
    ├── pytorch_model.bin
    ├── tokenizer.json
    ├── label_mappings.json
    └── ...
```

### Model Details

- **Base Model**: `xlm-roberta-base` (multilingual, 110M parameters)
- **Architecture**: Transformer-based sequence classification
- **Task**: Multi-class text classification
- **Languages**: English, Kinyarwanda
- **Categories**: plumber, electrician, cleaner, carpenter, painter, handyman

## Installation

### 1. Update Python Dependencies

```bash
cd ml-system
pip install -r requirements.txt
```

### 2. Key Packages Installed

```
torch>=2.0.0                    # Deep learning framework
transformers>=4.30.0            # Hugging Face transformers
fastapi                         # Web framework
uvicorn[standard]              # ASGI server
scikit-learn                   # ML utilities
```

## Training the Model

### Quick Start

```bash
cd ml-system
python models/nlu_service_classifier.py
```

This will:
1. Load the dataset from `data/nlu_service_categories.json`
2. Tokenize texts using XLM-RoBERTa tokenizer
3. Train for 3 epochs (configurable)
4. Save the trained model to `nlu_model/`
5. Test predictions with sample queries

### Training Configuration

Edit `models/nlu_service_classifier.py` to adjust:

```python
EPOCHS = 3                    # Number of training epochs
BATCH_SIZE = 16              # Training batch size
LEARNING_RATE = 2e-5         # Adam learning rate
```

### Expected Output

```
Loading dataset from ./data/nlu_service_categories.json
Loaded 50 examples
Categories: ['carpenter', 'cleaner', 'electrician', 'handyman', 'painter', 'plumber']

Preparing datasets
Starting training for 3 epochs

=== Epoch 1/3 ===
Training loss: 0.8234
Validation loss: 0.3421, Accuracy: 0.9091

=== Epoch 2/3 ===
Training loss: 0.1232
Validation loss: 0.0875, Accuracy: 0.9545

=== Epoch 3/3 ===
Training loss: 0.0543
Validation loss: 0.0654, Accuracy: 0.9545

Saving model to ./nlu_model
Model saved successfully

=== Testing Predictions ===
Text: I need a plumber to fix my pipes
Label: plumber, Score: 0.9821

Text: Ndashaka electrician
Label: electrician, Score: 0.8934

...
Training completed successfully!
```

## Running the FastAPI Server

### Start the Service

```bash
cd ml-system
python api/nlu_service.py
```

Or with uvicorn directly:

```bash
uvicorn api.nlu_service:app --host 0.0.0.0 --port 8001 --reload
```

### Access the API

- **API Server**: http://localhost:8001
- **Interactive Docs**: http://localhost:8001/docs
- **Alternative Docs**: http://localhost:8001/redoc

## API Endpoints

### 1. Single Classification

**Endpoint**: `POST /nlu`

**Request**:
```json
{
  "text": "I need a plumber to fix my pipes",
  "language": "en"
}
```

**Response**:
```json
{
  "text": "I need a plumber to fix my pipes",
  "label": "plumber",
  "score": 0.9821,
  "language": "en"
}
```

### 2. Batch Classification

**Endpoint**: `POST /nlu/batch`

**Request**:
```json
{
  "texts": [
    "I need a plumber",
    "Ndashaka electrician",
    "Clean my house"
  ],
  "language": "en"
}
```

**Response**:
```json
{
  "predictions": [
    {
      "text": "I need a plumber",
      "label": "plumber",
      "score": 0.9821,
      "language": "en"
    },
    {
      "text": "Ndashaka electrician",
      "label": "electrician",
      "score": 0.8934,
      "language": "rw"
    },
    {
      "text": "Clean my house",
      "label": "cleaner",
      "score": 0.9156,
      "language": "en"
    }
  ],
  "total": 3,
  "processed": 3
}
```

### 3. Get Categories

**Endpoint**: `GET /categories`

**Response**:
```json
{
  "categories": [
    "plumber",
    "electrician",
    "cleaner",
    "carpenter",
    "painter",
    "handyman"
  ],
  "total": 6,
  "model": "xlm-roberta-base"
}
```

### 4. Model Information

**Endpoint**: `GET /model/info`

**Response**:
```json
{
  "model_name": "xlm-roberta-base",
  "device": "cuda:0",
  "num_labels": 6,
  "label_mappings": {
    "id2label": {
      "0": "carpenter",
      "1": "cleaner",
      "2": "electrician",
      "3": "handyman",
      "4": "painter",
      "5": "plumber"
    },
    "label2id": {
      "carpenter": 0,
      "cleaner": 1,
      "electrician": 2,
      "handyman": 3,
      "painter": 4,
      "plumber": 5
    }
  }
}
```

### 5. Health Check

**Endpoint**: `GET /health`

**Response**:
```json
{
  "status": "healthy",
  "model_loaded": true,
  "device": "cuda:0"
}
```

### 6. Test Predictions

**Endpoint**: `POST /nlu/test`

**Response**:
```json
{
  "test_results": [
    {
      "text": "I need a plumber to fix my pipes",
      "label": "plumber",
      "score": 0.9821,
      "language": "en"
    },
    ...
  ],
  "total": 5
}
```

## PHP Integration

### 1. Use the NLUClient Class

```php
<?php
require_once 'includes/NLUClient.php';

// Initialize client
$nlu = new NLUClient('http://localhost:8001');

// Single classification
$result = $nlu->classify('I need a plumber to fix my pipes');
if ($result) {
    echo "Label: " . $result['label'];
    echo "Confidence: " . $result['score'];
}

// Batch classification
$texts = [
    'I need a plumber',
    'Ndashaka electrician',
    'Clean my house'
];
$batch_result = $nlu->classifyBatch($texts);

// Get available categories
$categories = $nlu->getCategories();

// Check service health
if ($nlu->healthCheck()) {
    echo "NLU service is online";
}
```

### 2. Quick Helper Functions

```php
<?php
require_once 'includes/NLUClient.php';

// Single classification
$result = classify_service('I need someone to fix my plumbing');

// Batch classification
$results = classify_services_batch([
    'I need a plumber',
    'Ndashaka carpenter'
]);
```

### 3. Practical Example: Service Search

```php
<?php
require_once 'includes/NLUClient.php';

// Get user search query
$user_query = $_POST['search_query'] ?? '';

if (!empty($user_query)) {
    $nlu = new NLUClient('http://localhost:8001');
    $classification = $nlu->classify($user_query);
    
    if ($classification && $classification['score'] > 0.7) {
        // High confidence prediction
        $service_category = $classification['label'];
        
        // Query database for providers in this category
        $providers = $db->query(
            "SELECT * FROM services WHERE category = ? 
             ORDER BY rating DESC LIMIT 10",
            [$service_category]
        );
    } else {
        // Show all categories or ask user to be more specific
        $providers = $db->query(
            "SELECT * FROM services ORDER BY rating DESC LIMIT 10"
        );
    }
}
```

## Dataset Format

The training dataset is in JSON format with the following structure:

```json
[
  {
    "text": "I need a plumber to fix my pipes",
    "label": "plumber",
    "language": "en"
  },
  {
    "text": "Ndashaka umuntu yishyura inzira z'amazi",
    "label": "plumber",
    "language": "rw"
  }
]
```

### Fields

- **text**: User query or description
- **label**: Service category (must match one of the training categories)
- **language**: Language code ('en' for English, 'rw' for Kinyarwanda)

### Adding More Training Data

1. Create JSON entries with the same format
2. Add to `data/nlu_service_categories.json`
3. Retrain the model: `python models/nlu_service_classifier.py`

## Performance Metrics

### Model Accuracy

After training on the dataset, expected accuracy:
- **Training Accuracy**: ~95%+
- **Validation Accuracy**: ~95%+
- **Test Accuracy**: ~92-95%

### Inference Speed

- **CPU Mode**: ~50-150ms per request
- **GPU Mode**: ~10-30ms per request
- **Batch (10 texts)**: ~200-500ms CPU, ~50-100ms GPU

### Memory Requirements

- **Model Size**: ~350MB (XLM-RoBERTa base)
- **RAM**: 2GB minimum (4GB recommended)
- **CUDA Memory**: 2GB (if using GPU)

## Troubleshooting

### Model Not Found Error

**Error**: `Model not found at ./nlu_model`

**Solution**: Train the model first
```bash
python models/nlu_service_classifier.py
```

### Connection Refused

**Error**: `Failed to connect to NLU service`

**Solution**: Ensure FastAPI server is running
```bash
python api/nlu_service.py
```

### CUDA Out of Memory

**Error**: `CUDA out of memory` during training

**Solution**: Reduce batch size in `nlu_service_classifier.py`
```python
BATCH_SIZE = 8  # Reduce from 16
```

### Low Accuracy on predictions

**Solution**: 
1. Increase training data
2. Fine-tune hyperparameters
3. Train for more epochs
4. Increase maximum sequence length

## Performance Tuning

### Faster Inference

Use quantization for faster inference:

```python
from transformers import AutoModelForSequenceClassification
import torch

# Load and quantize
model = AutoModelForSequenceClassification.from_pretrained('./nlu_model')
quantized_model = torch.quantization.quantize_dynamic(
    model,
    {torch.nn.Linear},
    dtype=torch.qint8
)
```

### Better Accuracy

1. **More Training Data**: Add more examples per category
2. **Higher Learning Rate**: Try `3e-5` or `5e-5`
3. **More Epochs**: Train for 5-10 epochs
4. **Larger Model**: Use `xlm-roberta-large` (350M parameters)

## Production Deployment

### Using Gunicorn with FastAPI

```bash
pip install gunicorn
gunicorn api.nlu_service:app --workers 4 --worker-class uvicorn.workers.UvicornWorker --bind 0.0.0.0:8001
```

### Docker Deployment

```dockerfile
FROM python:3.10-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install -r requirements.txt

COPY ml-system/ .

EXPOSE 8001

CMD ["python", "api/nlu_service.py"]
```

### Environment Variables

Create `.env` file:
```
NLU_MODEL_PATH=./nlu_model
NLU_PORT=8001
NLU_HOST=0.0.0.0
CUDA_VISIBLE_DEVICES=0
```

## Example Use Cases

### 1. Service Search Box

```php
// Auto-complete search based on NLU classification
$query = $_GET['q'];
$nlu = new NLUClient();
$classification = $nlu->classify($query);

if ($classification['score'] > 0.8) {
    // Show matching providers for detected service
    return get_providers_by_category($classification['label']);
}
```

### 2. Chatbot Integration

```php
// User message in chatbot
$user_message = "I need someone to fix my electrical wiring";
$nlu = new NLUClient();
$intent = $nlu->classify($user_message);

// Route to appropriate handler
switch ($intent['label']) {
    case 'electrician':
        return show_electricians($user_location);
    // ... other cases
}
```

### 3. Smart Booking

```php
// Automatically suggest service category
$booking_description = $_POST['description'];
$nlu = new NLUClient();
$suggested_category = $nlu->classify($booking_description);

// Prepopulate form
$form['service_category'] = $suggested_category['label'];
$form['confidence'] = $suggested_category['score'];
```

## Support for Additional Languages

To add support for more languages (e.g., Swahili, French):

1. Add training examples to `data/nlu_service_categories.json`
2. Set language code in "language" field
3. Retrain model: `python models/nlu_service_classifier.py`

XLM-RoBERTa supports 100+ languages, so any language data will work!

## References

- **Hugging Face Transformers**: https://huggingface.co/transformers/
- **XLM-RoBERTa Model**: https://huggingface.co/xlm-roberta-base
- **PyTorch**: https://pytorch.org/
- **FastAPI**: https://fastapi.tiangolo.com/

## License

This NLU system is part of the Bii LocalFinder project.
