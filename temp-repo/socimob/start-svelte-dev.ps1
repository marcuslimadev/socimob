# Script para iniciar desenvolvimento do Portal Svelte
# Inicia backend + frontend em paralelo

param(
    [switch]$BackendOnly,
    [switch]$FrontendOnly
)

$ErrorActionPreference = "Stop"

Write-Host "`n=================================================" -ForegroundColor Cyan
Write-Host "  PORTAL SVELTE - Ambiente de Desenvolvimento" -ForegroundColor Yellow
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""

# Verificar se Node.js está instalado
try {
    $nodeVersion = node --version
    Write-Host "✓ Node.js: $nodeVersion" -ForegroundColor Green
} catch {
    Write-Host "✗ Node.js não encontrado!" -ForegroundColor Red
    Write-Host "  Instale em: https://nodejs.org/" -ForegroundColor Yellow
    exit 1
}

# Verificar se npm install foi rodado
if (-not (Test-Path "portal-svelte\node_modules")) {
    Write-Host "⚠ Dependências não instaladas. Instalando..." -ForegroundColor Yellow
    Set-Location portal-svelte
    npm install
    Set-Location ..
}

Write-Host ""
Write-Host "🚀 Iniciando servidores..." -ForegroundColor Cyan
Write-Host ""

# Função para iniciar backend
function Start-Backend {
    Write-Host "📦 Backend (Lumen): http://127.0.0.1:8000" -ForegroundColor Green
    Set-Location backend
    php -S 127.0.0.1:8000 -t public
}

# Função para iniciar frontend
function Start-Frontend {
    Write-Host "⚡ Frontend (Svelte): http://localhost:5173" -ForegroundColor Green
    Set-Location portal-svelte
    npm run dev
}

# Iniciar conforme parâmetros
if ($BackendOnly) {
    Start-Backend
} elseif ($FrontendOnly) {
    Start-Frontend
} else {
    # Iniciar ambos em paralelo
    Write-Host "Iniciando Backend e Frontend em paralelo..." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "URLs:" -ForegroundColor Cyan
    Write-Host "  Backend API: http://127.0.0.1:8000/api" -ForegroundColor White
    Write-Host "  Portal Svelte: http://localhost:5173" -ForegroundColor White
    Write-Host "  Portal jQuery: http://127.0.0.1:8000/portal/" -ForegroundColor White
    Write-Host ""
    Write-Host "Pressione Ctrl+C para parar ambos servidores" -ForegroundColor Yellow
    Write-Host ""
    
    # Iniciar backend em background
    $backendJob = Start-Job -ScriptBlock {
        Set-Location $using:PWD
        Set-Location backend
        php -S 127.0.0.1:8000 -t public
    }
    
    # Aguardar 2 segundos para backend iniciar
    Start-Sleep -Seconds 2
    
    # Iniciar frontend (em foreground para ver HMR logs)
    try {
        Set-Location portal-svelte
        npm run dev
    } finally {
        # Parar backend ao fechar
        Write-Host "`n🛑 Parando servidores..." -ForegroundColor Yellow
        Stop-Job $backendJob
        Remove-Job $backendJob
    }
}
