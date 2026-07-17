$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
$logFile = Join-Path $scriptRoot 'logs\ml_service_watchdog.log'
$pythonExe = Join-Path $scriptRoot '.venv\Scripts\python.exe'

function LogMessage {
    param([string]$message)
    $line = "$(Get-Date -Format 's') [ML Watchdog] $message"
    $line | Out-File -FilePath $logFile -Append -Encoding utf8
    Write-Host $line
}

if (-not (Test-Path $pythonExe)) {
    LogMessage "ERROR: Python executable not found at $pythonExe"
    throw "Python executable missing"
}

$process = $null
function Start-MLService {
    if ($process -and -not $process.HasExited) {
        return
    }

    $args = @('-m', 'uvicorn', 'ml-system.api.multi_model_app:app', '--host', '0.0.0.0', '--port', '8000')
    LogMessage "Starting ML service with $pythonExe"
    $process = Start-Process -FilePath $pythonExe -ArgumentList $args -WorkingDirectory $scriptRoot -NoNewWindow -PassThru
    Start-Sleep -Seconds 2
}

while ($true) {
    $healthy = $false
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/health' -UseBasicParsing -TimeoutSec 3
        $json = $response.Content | ConvertFrom-Json
        if ($json.status -in @('healthy', 'degraded')) {
            $healthy = $true
        }
    } catch {
        LogMessage "Health check failed: $($_.Exception.Message)"
    }

    if (-not $healthy) {
        LogMessage "ML API is not healthy or not responding."
        Start-MLService
    } else {
        if (-not $process -or $process.HasExited) {
            LogMessage "ML API responded healthy but process handle is missing or exited. Starting service."
            Start-MLService
        } else {
            LogMessage "ML API healthy."
        }
    }

    Start-Sleep -Seconds 20
}
