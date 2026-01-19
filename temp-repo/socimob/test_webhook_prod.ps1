# Script de Teste Rápido - Webhook WhatsApp Produção
# Testa GET e POST no endpoint de produção

$ErrorActionPreference = "Stop"

Write-Host "`n=================================================" -ForegroundColor Cyan
Write-Host "  TESTE WEBHOOK WHATSAPP - PRODUÇÃO" -ForegroundColor Yellow
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""

$prodUrl = "https://exclusivalarimoveis.com/webhook/whatsapp"

# ==========================================
# TESTE 1: GET (Validação)
# ==========================================
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host "📋 TESTE 1: GET /webhook/whatsapp (Validação)" -ForegroundColor White
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host ""

try {
    $response = Invoke-WebRequest -Uri $prodUrl -Method GET -UseBasicParsing
    Write-Host "✓ Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "✓ Conteúdo: $($response.Content)" -ForegroundColor Green
    Write-Host "✓ GET funcionando!" -ForegroundColor Green
} catch {
    Write-Host "✗ ERRO GET:" -ForegroundColor Red
    Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    Write-Host "  Mensagem: $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""

# ==========================================
# TESTE 2: POST (Mensagem Simulada)
# ==========================================
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host "📋 TESTE 2: POST /webhook/whatsapp (Mensagem)" -ForegroundColor White
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Blue
Write-Host ""

# Payload simulando Twilio
$payload = @{
    MessageSid = "TEST$(Get-Date -Format 'HHmmss')"
    AccountSid = "TEST_ACCOUNT"
    From = "whatsapp:+5521999999999"
    To = "whatsapp:+5521988888888"
    Body = "Teste webhook - $(Get-Date -Format 'HH:mm:ss')"
    ProfileName = "Teste Produção"
    FromCity = "Rio de Janeiro"
    FromState = "RJ"
    FromCountry = "BR"
}

try {
    $response = Invoke-WebRequest -Uri $prodUrl -Method POST -Body $payload -UseBasicParsing
    Write-Host "✓ Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "✓ POST funcionando!" -ForegroundColor Green
} catch {
    Write-Host "✗ ERRO POST:" -ForegroundColor Red
    Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    Write-Host "  Mensagem: $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""

# ==========================================
# RESUMO
# ==========================================
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "  RESUMO" -ForegroundColor Yellow
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "URL testada: $prodUrl" -ForegroundColor White
Write-Host ""
Write-Host "Se ambos os testes passaram:" -ForegroundColor Green
Write-Host "  ✓ Webhook está funcionando corretamente" -ForegroundColor Green
Write-Host "  ✓ Configure no Twilio Console" -ForegroundColor Green
Write-Host ""
Write-Host "Se houver erros:" -ForegroundColor Yellow
Write-Host "  1. Verificar se fez deploy dos arquivos" -ForegroundColor Yellow
Write-Host "  2. Limpar cache: php artisan route:clear" -ForegroundColor Yellow
Write-Host "  3. Verificar logs: storage/logs/lumen-*.log" -ForegroundColor Yellow
Write-Host ""
Write-Host "Documentação: SOLUCAO_ERRO_405_WEBHOOK.md" -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
