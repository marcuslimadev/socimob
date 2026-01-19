Write-Host "=== TESTE ENDPOINT CLAIM ===" -ForegroundColor Green
Write-Host ""

# Primeiro fazer login para pegar o token
$loginBody = @{
    email = "contato@exclusivalarimoveis.com.br"
    password = "password"
} | ConvertTo-Json

Write-Host "Fazendo login..." -ForegroundColor Yellow
try {
    $loginResponse = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/api/auth/login" -Method POST -Headers @{"Content-Type"="application/json"} -Body $loginBody
    $token = $loginResponse.token
    Write-Host "✅ Login OK! Token: $($token.Substring(0,20))..." -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "❌ Erro no login!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit
}

# Pegar o ID do lead 11 (último criado)
$leadId = 11

Write-Host "Tentando pegar atendimento do Lead #$leadId..." -ForegroundColor Yellow
Write-Host ""

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

try {
    $response = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/api/leads/$leadId/claim" -Method POST -Headers $headers
    Write-Host "✅ SUCESSO!" -ForegroundColor Green
    Write-Host ""
    $response | ConvertTo-Json -Depth 5
} catch {
    Write-Host "❌ ERRO!" -ForegroundColor Red
    Write-Host "Mensagem: $($_.Exception.Message)"
    Write-Host ""
    if ($_.Exception.Response) {
        Write-Host "Status Code: $($_.Exception.Response.StatusCode.value__)"
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response Body: $responseBody"
    }
}
