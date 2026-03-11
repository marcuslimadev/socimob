#!/usr/bin/env pwsh
# Sincroniza os imóveis da Exclusiva via API
# Uso: .\sync_imoveis.ps1

$ErrorActionPreference = "Stop"

function Write-Step    { param($msg) Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Write-Success { param($msg) Write-Host "V $msg" -ForegroundColor Green }
function Write-Fail    { param($msg) Write-Host "X $msg" -ForegroundColor Red }

Write-Host ""
Write-Host "===============================================================" -ForegroundColor Cyan
Write-Host "       SINCRONIZACAO DE IMOVEIS - EXCLUSIVA LAR IMOVEIS        " -ForegroundColor Cyan
Write-Host "===============================================================" -ForegroundColor Cyan

try {
    Write-Step "EXECUTANDO SINCRONIZACAO"
    $output = php artisan properties:sync --tenant-id=1 2>&1
    Write-Host $output

    if ($LASTEXITCODE -ne 0) {
        throw "Comando retornou erro (exit code $LASTEXITCODE)"
    }

    Write-Host ""
    Write-Host "===============================================================" -ForegroundColor Green
    Write-Success "Sincronizacao concluida com sucesso!"
    Write-Host "===============================================================" -ForegroundColor Green

} catch {
    Write-Host ""
    Write-Host "===============================================================" -ForegroundColor Red
    Write-Fail "Erro na sincronizacao: $_"
    Write-Host "===============================================================" -ForegroundColor Red
    exit 1
}
