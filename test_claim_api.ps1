Write-Host "=== TESTE CLAIM API ===" -ForegroundColor Green

# Fazer login
$loginBody = @{
    email = "contato@exclusivalarimoveis.com.br"
    password = "MundoMelhor@10"
} | ConvertTo-Json

Write-Host "`nFazendo login..." -ForegroundColor Yellow
try {
    $loginResponse = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/api/login" -Method POST -Body $loginBody -ContentType "application/json"
    $token = $loginResponse.token
    Write-Host "✅ Login OK - Token: $($token.Substring(0,20))..." -ForegroundColor Green
} catch {
    Write-Host "❌ Erro no login:" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Buscar leads
Write-Host "`nBuscando leads..." -ForegroundColor Yellow
$headers = @{
    "Authorization" = "Bearer $token"
}

try {
    $leadsResponse = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/api/leads" -Method GET -Headers $headers
    Write-Host "✅ Total de leads: $($leadsResponse.data.Count)" -ForegroundColor Green
    
    # Pegar primeiro lead sem corretor
    $leadDisponivel = $leadsResponse.data | Where-Object { $null -eq $_.corretor_id } | Select-Object -First 1
    
    if ($leadDisponivel) {
        Write-Host "`n📋 Lead disponível encontrado:"
        Write-Host "   ID: $($leadDisponivel.id)"
        Write-Host "   Nome: $($leadDisponivel.nome)"
        Write-Host "   Status: $($leadDisponivel.status)"
        
        # Tentar pegar atendimento
        Write-Host "`n🖐️ Tentando pegar atendimento..." -ForegroundColor Yellow
        try {
            $claimResponse = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/api/leads/$($leadDisponivel.id)/claim" -Method POST -Headers $headers
            Write-Host "✅ SUCESSO!" -ForegroundColor Green
            Write-Host ($claimResponse | ConvertTo-Json -Depth 5)
        } catch {
            Write-Host "ERRO ao pegar atendimento:" -ForegroundColor Red
            Write-Host "Status Code: $($_.Exception.Response.StatusCode.value__)"
            Write-Host "Mensagem: $($_.Exception.Message)"
            if ($_.ErrorDetails) {
                Write-Host "Detalhes: $($_.ErrorDetails.Message)"
            }
        }
    } else {
        Write-Host "`n⚠️ Nenhum lead disponível para pegar" -ForegroundColor Yellow
    }
} catch {
    Write-Host "Erro ao buscar leads:" -ForegroundColor Red
    Write-Host $_.Exception.Message
}
