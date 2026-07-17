#!/usr/bin/env bash
# =============================================================================
# retrain.sh — Automated ML pipeline: export → train → replace model
# =============================================================================
#
# 1. Exports fresh interaction data from the DB to CSV
# 2. Re-trains the RandomForest model
# 3. Replaces model_latest.pkl (FastAPI picks it up on next request if using --reload,
#    or you can restart the service here)
#
# Suggested cron (run nightly at 2 AM):
#   0 2 * * * /path/to/ml-system/retrain.sh >> /var/log/ml_retrain.log 2>&1
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
PYTHON_BIN="${PYTHON_BIN:-python3}"
LOG_PREFIX="[$(date '+%Y-%m-%d %H:%M:%S')]"

echo "$LOG_PREFIX ─── ML Retrain Pipeline Started ───"

# ── Step 1: Export data ───────────────────────────────────────────────────────
echo "$LOG_PREFIX Step 1: Exporting interaction data..."
"$PYTHON_BIN" "$SCRIPT_DIR/data/export_data.py"
echo "$LOG_PREFIX Data export complete."

# ── Step 2: Train model ───────────────────────────────────────────────────────
echo "$LOG_PREFIX Step 2: Training model..."
cd "$SCRIPT_DIR"
"$PYTHON_BIN" model/train_model.py
echo "$LOG_PREFIX Model training complete."

# ── Step 3: Reload FastAPI model ─────────────────────────────────────────────
if command -v curl >/dev/null 2>&1; then
  echo "$LOG_PREFIX Step 3: Reloading FastAPI model..."
  if curl --silent --fail -X POST "http://localhost:8000/reload-model"; then
    echo "$LOG_PREFIX FastAPI model reload succeeded."
  else
    echo "$LOG_PREFIX Warning: FastAPI reload failed. Ensure the API is running and reachable at http://localhost:8000 or restart it manually."
  fi
else
  echo "$LOG_PREFIX Skipping FastAPI reload: curl command not found. Restart the API manually to load the new model."
fi

echo "$LOG_PREFIX ─── Pipeline Complete ───"