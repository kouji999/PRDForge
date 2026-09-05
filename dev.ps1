# PRDForge dev runner — Windows PowerShell
# Usage: ./dev.ps1 start | stop | restart | status
param(
    [Parameter(Position = 0)]
    [ValidateSet('start', 'stop', 'restart', 'status')]
    [string]$Action = 'start'
)

$ErrorActionPreference = 'SilentlyContinue'
$Root = $PSScriptRoot
$Port = 8187

function Get-ProcList {
    return Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -match [regex]::Escape($Root) }
}

function Stop-All {
    Get-ProcList | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
    Start-Sleep -Seconds 1
}

function Start-Server {
    Start-Process -FilePath 'php' `
        -ArgumentList 'artisan', 'serve', "--port=$Port", '--no-reload' `
        -WorkingDirectory $Root -WindowStyle Hidden
}

function Start-Worker {
    Start-Process -FilePath 'php' `
        -ArgumentList 'artisan', 'queue:work', '--timeout=3700', '--tries=1', '--sleep=1', '--max-jobs=200' `
        -WorkingDirectory $Root -WindowStyle Hidden
}

function Test-Healthy {
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/up" -UseBasicParsing -TimeoutSec 5
        return $r.StatusCode -eq 200
    } catch {
        return $false
    }
}

switch ($Action) {
    'start' {
        if (Test-Healthy) {
            Write-Host "[PRDForge] Server sudah jalan di :$Port" -ForegroundColor Yellow
        } else {
            Stop-All
            Start-Server
            Start-Sleep -Seconds 3
            if (Test-Healthy) {
                Write-Host "[PRDForge] Server OK → http://127.0.0.1:$Port" -ForegroundColor Green
            } else {
                Write-Host '[PRDForge] Server GAGAL start. Cek storage/logs/laravel.log' -ForegroundColor Red
                exit 1
            }
        }

        Start-Worker
        Write-Host '[PRDForge] Queue worker started' -ForegroundColor Green
        Write-Host "[PRDForge] App: http://127.0.0.1:$Port (login dengan akun kamu)"
    }
    'stop' {
        Stop-All
        Write-Host '[PRDForge] Semua proses di-stop' -ForegroundColor Yellow
    }
    'restart' {
        Stop-All
        & $MyInvocation.MyCommand.Path 'start'
    }
    'status' {
        $healthy = Test-Healthy
        $procs = @(Get-ProcList).Count
        Write-Host "Server :$Port : $(if ($healthy) { 'UP' } else { 'DOWN' })"
        Write-Host "PHP proses   : $procs"
    }
}
