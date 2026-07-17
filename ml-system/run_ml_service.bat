@echo off
REM Starts the ML FastAPI service and restarts it automatically if it stops.
cd /d %~dp0
if not exist ".venv\Scripts\python.exe" (
  echo Virtual environment not found: .venv\Scripts\python.exe
  pause
  exit /b 1
)
:RESTART
echo [%date% %time%] Starting ML service...
.venv\Scripts\python.exe -m uvicorn ml-system.api.multi_model_app:app --host 0.0.0.0 --port 8000
echo [%date% %time%] ML service stopped unexpectedly. Restarting in 5 seconds... >> logs\ml_service_watchdog.log
timeout /t 5 /nobreak > nul
goto RESTART
