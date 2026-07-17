# NLU System Deployment Guide

## Overview

This guide covers deploying the Multilingual NLU Service to production.

## Pre-Deployment Checklist

- [ ] Model trained and saved to `nlu_model/`
- [ ] All requirements installed: `pip install -r requirements.txt`
- [ ] Tests passing: `python test_nlu_service.py`
- [ ] PHP client integrated: `includes/NLUClient.php`
- [ ] Database tables created: `config/create_nlu_tables.sql`
- [ ] Environment configured: `.env` file set up

## Deployment Options

### Option 1: Local Development (Windows)

#### Terminal 1: Start Python Virtual Environment

```powershell
cd c:\xampp\htdocs\Bii_localFinder\ml-system
. .venv\Scripts\Activate.ps1
python api/nlu_service.py
```

The server will start on `http://localhost:8001`

#### Terminal 2: Test the Service

```bash
curl -X POST http://localhost:8001/nlu ^
  -H "Content-Type: application/json" ^
  -d "{\"text\": \"I need a plumber\"}"
```

### Option 2: Docker Deployment

#### 1. Create Dockerfile

```dockerfile
FROM python:3.10-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements
COPY ml-system/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY ml-system/ .

# Expose port
EXPOSE 8001

# Run the application
CMD ["python", "api/nlu_service.py"]
```

#### 2. Build and Run

```bash
# Build image
docker build -t bii-nlu-service .

# Run container
docker run -d -p 8001:8001 --name nlu-service bii-nlu-service
```

### Option 3: Production with Gunicorn

#### Install Gunicorn

```bash
pip install gunicorn
```

#### Start with Gunicorn

```bash
gunicorn api.nlu_service:app \
  --workers 4 \
  --worker-class uvicorn.workers.UvicornWorker \
  --bind 0.0.0.0:8001 \
  --access-logfile logs/nlu_access.log \
  --error-logfile logs/nlu_error.log
```

### Option 4: Systemd Service (Linux)

#### Create Service File

Create `/etc/systemd/system/nlu-service.service`:

```ini
[Unit]
Description=Bii LocalFinder NLU Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bii-localfinder/ml-system
ExecStart=/usr/bin/python3 api/nlu_service.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### Enable and Start

```bash
sudo systemctl daemon-reload
sudo systemctl enable nlu-service
sudo systemctl start nlu-service
sudo systemctl status nlu-service
```

### Option 5: Apache/Nginx Proxy

#### Nginx Configuration

```nginx
upstream nlu_backend {
    server localhost:8001;
}

server {
    listen 80;
    server_name your-domain.com;

    location /nlu/ {
        proxy_pass http://nlu_backend/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Timeouts for long-running requests
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    
    <Location /nlu/>
        ProxyPreserveHost On
        ProxyPass http://localhost:8001/
        ProxyPassReverse http://localhost:8001/
    </Location>
</VirtualHost>
```

## Environment Configuration

Create `.env` in `ml-system/`:

```env
# NLU Service Configuration
NLU_PORT=8001
NLU_HOST=0.0.0.0
NLU_MODEL_PATH=./nlu_model
NLU_LOG_LEVEL=INFO

# GPU Configuration
CUDA_VISIBLE_DEVICES=0

# API Configuration
MAX_BATCH_SIZE=100
REQUEST_TIMEOUT=30
```

## Performance Tuning

### GPU Optimization

For NVIDIA GPUs with CUDA:

```bash
# Check CUDA availability
python -c "import torch; print(torch.cuda.is_available())"

# Use specific GPU
export CUDA_VISIBLE_DEVICES=0
python api/nlu_service.py
```

### Memory Optimization

Reduce memory usage with quantization:

```python
# In nlu_service.py
import torch
quantized_model = torch.quantization.quantize_dynamic(
    model,
    {torch.nn.Linear},
    dtype=torch.qint8
)
```

### Request Optimization

Adjust batch sizes for your hardware:

```python
# In nlu_service.py
BATCH_SIZE = 16  # Increase for GPU, decrease for CPU
MAX_LENGTH = 128  # Reduce for faster inference
```

## Monitoring

### Check Service Health

```bash
curl http://localhost:8001/health
```

### View Logs

**Systemd logs**:
```bash
sudo journalctl -u nlu-service -f
```

**Gunicorn logs**:
```bash
tail -f logs/nlu_error.log
tail -f logs/nlu_access.log
```

### Set Up Monitoring

Use Prometheus for metrics:

```python
# Add to nlu_service.py
from prometheus_client import Counter, Histogram
import prometheus_client

request_count = Counter('nlu_requests_total', 'Total NLU requests')
request_duration = Histogram('nlu_request_duration_seconds', 'Request duration')
```

## Security

### API Key Authentication

Add to `nlu_service.py`:

```python
from fastapi import Depends, HTTPException, Header

async def verify_api_key(x_token: str = Header(...)):
    if x_token != os.getenv('NLU_API_KEY'):
        raise HTTPException(status_code=400, detail="Invalid API key")
    return x_token

@app.post("/nlu", dependencies=[Depends(verify_api_key)])
async def classify_service(request: NLURequest):
    ...
```

### Rate Limiting

```python
from slowapi import Limiter
from slowapi.util import get_remote_address

limiter = Limiter(key_func=get_remote_address)
app.state.limiter = limiter

@app.post("/nlu")
@limiter.limit("100/minute")
async def classify_service(request: NLURequest):
    ...
```

### HTTPS/SSL

Use certbot for Let's Encrypt:

```bash
sudo certbot certonly --standalone -d your-domain.com
```

Update Nginx config to use SSL certificates.

## Scaling

### Load Balancing

With multiple NLU instances:

```nginx
upstream nlu_backend {
    server localhost:8001;
    server localhost:8002;
    server localhost:8003;
    server localhost:8004;
}
```

### Redis Caching

Cache predictions to reduce model inference:

```python
import redis

cache = redis.Redis(host='localhost', port=6379, db=0)

@app.post("/nlu")
async def classify_service(request: NLURequest):
    cache_key = f"nlu:{request.text}"
    cached = cache.get(cache_key)
    
    if cached:
        return json.loads(cached)
    
    # Get prediction from model
    prediction = classifier.predict(request.text)
    
    # Cache result for 24 hours
    cache.setex(cache_key, 86400, json.dumps(prediction))
    
    return prediction
```

## Backup and Recovery

### Regular Backups

```bash
# Backup trained model
tar -czf nlu_model_backup_$(date +%Y%m%d).tar.gz nlu_model/

# Backup database (NLU classifications)
mysqldump -u user -p bii_localfinder nlu_classifications > nlu_classifications_backup.sql
```

### Model Versioning

Keep multiple model versions:

```
nlu_model/
├── v1/ (current)
├── v2/ (in training)
└── v0_backup/ (previous)
```

### Disaster Recovery

```bash
# Restore from backup
tar -xzf nlu_model_backup_20240101.tar.gz -C .
mysql -u user -p bii_localfinder < nlu_classifications_backup.sql
```

## Testing in Production

### Full Integration Test

```bash
python test_nlu_service.py http://your-domain.com/nlu
```

### Load Testing with Apache Bench

```bash
ab -n 1000 -c 10 -p test.json -T application/json http://localhost:8001/nlu
```

### Load Testing with wrk

```bash
wrk -t4 -c100 -d30s http://localhost:8001/health
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Out of memory | Reduce batch size, use smaller model |
| Slow inference | Use GPU, increase workers, add caching |
| API not responding | Check logs, verify port is open, restart service |
| Model loading errors | Verify model files exist, check file permissions |
| Database errors | Check DB connection, verify schema is created |

## Rollback Procedure

If issues occur after deployment:

```bash
# Stop current service
sudo systemctl stop nlu-service

# Restore previous model version
cp -r nlu_model_backup/ nlu_model/

# Restart service
sudo systemctl start nlu-service
```

## Next Steps

1. Deploy model to staging first
2. Run full test suite
3. Set up monitoring and alerting
4. Configure backups and recovery
5. Deploy to production
6. Monitor performance metrics
7. Collect user feedback
8. Retrain model with new data periodically

For questions or issues, check NLU_README.md or test_nlu_service.py.
