Write-Host "=== TESTE COM CREDENCIAIS CORRETAS ===" -ForegroundColor Green
Write-Host ""
Write-Host "Email: teste@chavesnamao.com.br" -ForegroundColor Cyan
Write-Host "Token: d825c542e26df27c9fe696c391ee590f" -ForegroundColor Cyan
Write-Host ""

$headers = @{
    "Authorization" = "Basic dGVzdGVAY2hhdmVzbmFtYW8uY29tLmJyOmQ4MjVjNTQyZTI2ZGYyN2M5ZmU2OTZjMzkxZWU1OTBm"
    "Content-Type" = "application/json"
}

$body = @{
    id = "TESTE_CREDENCIAIS_OK"
    name = "Maria Silva dos Santos"
    phone = "11987654321"
    email = "maria@email.com"
    segment = "REAL_ESTATE"
    ad = @{
        rooms = 3
        suites = 2
        garages = 2
        price = 850000
        neighborhood = "Jardim Paulista"
        city = "São Paulo"
    }
} | ConvertTo-Json -Depth 10

Write-Host "Enviando webhook..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/webhook/chaves-na-mao" -Method POST -Headers $headers -Body $body
    Write-Host ""
    Write-Host "✅ SUCESSO!" -ForegroundColor Green
    Write-Host ""
    $response | ConvertTo-Json
} catch {
    Write-Host ""
    Write-Host "❌ ERRO!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)"
    }
}
