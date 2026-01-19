# Script de Teste - Deploy Webhook
# Testa o endpoint de deploy automático

param(
    [string]$Url = "http://127.0.0.1:8000/api/deploy",
    [string]$Secret = "change-me-in-production",
    [string]$Project = "default"
)

$ErrorActionPreference = "Stop"

Write-Host "`n=================================================" -ForegroundColor Cyan
Write-Host "  TESTE DEPLOY WEBHOOK" -ForegroundColor Yellow
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""

# ==========================================
# TESTE 1: Info do Sistema
# ==========================================
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host "📋 TESTE 1: GET /api/deploy/info" -ForegroundColor White
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host ""

$headers = @{
    "X-Deploy-Secret" = $Secret
}

try {
    $infoUrl = $Url -replace '/deploy$', '/deploy/info'
    $response = Invoke-RestMethod -Uri $infoUrl -Method GET -Headers $headers
    
    Write-Host "✓ PHP Version: $($response.php_version)" -ForegroundColor Green
    Write-Host "✓ PHP Path: $($response.php_path)" -ForegroundColor Green
    Write-Host "✓ Composer Path: $($response.composer_path)" -ForegroundColor Green
    Write-Host "✓ Base Path: $($response.base_path)" -ForegroundColor Green
    Write-Host "✓ OS: $($response.server.os)" -ForegroundColor Green
    Write-Host "✓ Git Version: $($response.git.version)" -ForegroundColor Green
} catch {
    Write-Host "✗ ERRO:" -ForegroundColor Red
    Write-Host "  $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""

# ==========================================
# TESTE 2: Deploy (Dry Run - sem realmente executar)
# ==========================================
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host "📋 TESTE 2: POST /api/deploy" -ForegroundColor White
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host ""
Write-Host "⚠️  ATENÇÃO: Este teste vai executar comandos reais!" -ForegroundColor Yellow
Write-Host "   Projeto: $Project" -ForegroundColor White
Write-Host "   URL: $Url" -ForegroundColor White
Write-Host ""

$confirm = Read-Host "Deseja continuar? (S/N)"
if ($confirm -ne 'S' -and $confirm -ne 's') {
    Write-Host "`n❌ Teste cancelado pelo usuário." -ForegroundColor Yellow
    exit 0
}

Write-Host ""

$body = @{
    project = $Project
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri $Url -Method POST -Headers $headers -Body $body -ContentType "application/json"
    
    Write-Host "✓ Deploy executado!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Resultado:" -ForegroundColor Cyan
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    Write-Host "  Success: $($response.success)" -ForegroundColor $(if($response.success) { "Green" } else { "Red" })
    Write-Host "  Message: $($response.message)"
    Write-Host "  Project: $($response.project)"
    Write-Host "  Duration: $($response.duration)"
    
    if ($response.errors -and $response.errors.Count -gt 0) {
        Write-Host ""
        Write-Host "  Erros:" -ForegroundColor Red
        foreach ($error in $response.errors) {
            Write-Host "    - $error" -ForegroundColor Yellow
        }
    }
    
    Write-Host ""
    Write-Host "Output detalhado:" -ForegroundColor Cyan
    
    # Git Pull
    if ($response.output.git_pull) {
        Write-Host ""
        Write-Host "  🔄 Git Pull (Exit: $($response.output.git_pull.exit_code))"
        foreach ($line in $response.output.git_pull.output) {
            Write-Host "     $line" -ForegroundColor Gray
        }
    }
    
    # Composer Install
    if ($response.output.composer_install) {
        Write-Host ""
        Write-Host "  📦 Composer Install (Exit: $($response.output.composer_install.exit_code))"
        $lines = $response.output.composer_install.output | Select-Object -First 5
        foreach ($line in $lines) {
            Write-Host "     $line" -ForegroundColor Gray
        }
        if ($response.output.composer_install.output.Count -gt 5) {
            Write-Host "     ... ($($response.output.composer_install.output.Count - 5) linhas omitidas)" -ForegroundColor DarkGray
        }
    }
    
    Write-Host ""
    Write-Host "  Timestamp: $($response.timestamp)" -ForegroundColor Gray
    
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    Write-Host "✗ ERRO (Status: $statusCode):" -ForegroundColor Red
    Write-Host "  $($_.Exception.Message)" -ForegroundColor Yellow
    
    if ($statusCode -eq 401) {
        Write-Host ""
        Write-Host "💡 Dica: Verifique se o DEPLOY_SECRET está correto no .env" -ForegroundColor Cyan
    }
}

Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "  RESUMO" -ForegroundColor Yellow
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Documentação completa: docs\DEPLOY_WEBHOOK.md" -ForegroundColor White
Write-Host ""
Write-Host "Comandos úteis:" -ForegroundColor Cyan
Write-Host "  # Testar em produção:" -ForegroundColor Gray
Write-Host "  .\test_deploy.ps1 -Url 'https://lojadaesquina.store/api/deploy' -Secret 'seu-token' -Project 'lojadaesquina'" -ForegroundColor Gray
Write-Host ""
Write-Host "  # Ver logs:" -ForegroundColor Gray
Write-Host "  tail -f storage/logs/lumen-`$(date +%Y-%m-%d).log" -ForegroundColor Gray
Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
