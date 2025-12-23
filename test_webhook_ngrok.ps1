# Script PowerShell para testar webhook WhatsApp via ngrok
# Uso: .\test_webhook_ngrok.ps1

Write-Host "`n"
Write-Host "╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║         🟢 TESTE WEBHOOK WHATSAPP VIA NGROK                      ║" -ForegroundColor Green
Write-Host "╚═══════════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host "`n"

# URL do ngrok
$ngrokUrl = "https://99a3345711a3.ngrok-free.app"
$webhookUrl = "$ngrokUrl/webhook/whatsapp"

Write-Host "🌐 URL do webhook: $webhookUrl" -ForegroundColor Cyan
Write-Host "`n"

# Função para enviar webhook
function Send-Webhook {
    param(
        [string]$Url,
        [hashtable]$Payload,
        [string]$ContentType = "application/x-www-form-urlencoded"
    )
    
    try {
        if ($ContentType -eq "application/json") {
            $body = $Payload | ConvertTo-Json -Depth 10
        } else {
            # URL encode para form data
            $formData = @()
            foreach ($key in $Payload.Keys) {
                $value = [System.Uri]::EscapeDataString($Payload[$key])
                $formData += "$key=$value"
            }
            $body = $formData -join "&"
        }
        
        $headers = @{
            "Content-Type" = $ContentType
            "User-Agent" = "TwilioProxy/1.1"
        }
        
        $response = Invoke-WebRequest -Uri $Url -Method POST -Headers $headers -Body $body -UseBasicParsing
        
        return @{
            Success = $true
            StatusCode = $response.StatusCode
            Content = $response.Content
        }
    }
    catch {
        return @{
            Success = $false
            StatusCode = $_.Exception.Response.StatusCode.value__
            Error = $_.Exception.Message
        }
    }
}

# Teste 1: Mensagem Twilio
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "📱 TESTE 1: Mensagem simulada do Twilio" -ForegroundColor Yellow
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Yellow

$twilioPayload = @{
    MessageSid = "SM$(Get-Random)"
    AccountSid = "AC$(Get-Random)"
    From = "whatsapp:+5521987654321"
    To = "whatsapp:+5521999887766"
    Body = "Olá! Estou interessado em um imóvel."
    ProfileName = "João da Silva"
    FromCity = "Rio de Janeiro"
    FromState = "RJ"
    FromCountry = "BR"
    NumMedia = "0"
}

Write-Host "Payload:" -ForegroundColor White
$twilioPayload | ConvertTo-Json | Write-Host -ForegroundColor Gray
Write-Host "`n"

Write-Host "Enviando requisição..." -ForegroundColor White
$result1 = Send-Webhook -Url $webhookUrl -Payload $twilioPayload

if ($result1.Success) {
    Write-Host "✅ Resposta HTTP $($result1.StatusCode): $($result1.Content)" -ForegroundColor Green
} else {
    Write-Host "❌ Erro: $($result1.Error)" -ForegroundColor Red
}
Write-Host "`n"

# Teste 2: Mensagem Evolution API
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "📱 TESTE 2: Mensagem simulada da Evolution API" -ForegroundColor Yellow
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Yellow

$evolutionPayload = @{
    event = "messages.upsert"
    instance = "exclusiva_instance"
    data = @{
        key = @{
            remoteJid = "5521987654321@s.whatsapp.net"
            fromMe = $false
            id = "3EB0$(Get-Random)"
        }
        pushName = "Maria Santos"
        message = @{
            conversation = "Gostaria de agendar uma visita ao apartamento."
        }
        messageTimestamp = [int](Get-Date -UFormat %s)
    }
}

Write-Host "Payload:" -ForegroundColor White
$evolutionPayload | ConvertTo-Json -Depth 10 | Write-Host -ForegroundColor Gray
Write-Host "`n"

Write-Host "Enviando requisição..." -ForegroundColor White
$result2 = Send-Webhook -Url $webhookUrl -Payload $evolutionPayload -ContentType "application/json"

if ($result2.Success) {
    Write-Host "✅ Resposta HTTP $($result2.StatusCode): $($result2.Content)" -ForegroundColor Green
} else {
    Write-Host "❌ Erro: $($result2.Error)" -ForegroundColor Red
}
Write-Host "`n"

# Teste 3: Mensagem com mídia
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Yellow
Write-Host "📱 TESTE 3: Mensagem com mídia (Twilio)" -ForegroundColor Yellow
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Yellow

$twilioMediaPayload = @{
    MessageSid = "SM$(Get-Random)"
    AccountSid = "AC$(Get-Random)"
    From = "whatsapp:+5521987654321"
    To = "whatsapp:+5521999887766"
    Body = "Segue foto do imóvel"
    ProfileName = "Carlos Oliveira"
    NumMedia = "1"
    MediaUrl0 = "https://api.twilio.com/2010-04-01/Accounts/ACxxxx/Messages/MMxxxx/Media/MExxxx"
    MediaContentType0 = "image/jpeg"
}

Write-Host "Payload:" -ForegroundColor White
$twilioMediaPayload | ConvertTo-Json | Write-Host -ForegroundColor Gray
Write-Host "`n"

Write-Host "Enviando requisição..." -ForegroundColor White
$result3 = Send-Webhook -Url $webhookUrl -Payload $twilioMediaPayload

if ($result3.Success) {
    Write-Host "✅ Resposta HTTP $($result3.StatusCode): $($result3.Content)" -ForegroundColor Green
} else {
    Write-Host "❌ Erro: $($result3.Error)" -ForegroundColor Red
}
Write-Host "`n"

# Resumo
Write-Host "╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║                    📊 RESUMO DOS TESTES                           ║" -ForegroundColor Green
Write-Host "╚═══════════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host "`n"

$status1 = if ($result1.Success) { "✅ Sucesso" } else { "❌ Falhou" }
$status2 = if ($result2.Success) { "✅ Sucesso" } else { "❌ Falhou" }
$status3 = if ($result3.Success) { "✅ Sucesso" } else { "❌ Falhou" }

Write-Host "Teste 1 (Twilio básico):  $status1 - HTTP $($result1.StatusCode)" -ForegroundColor $(if ($result1.Success) { "Green" } else { "Red" })
Write-Host "Teste 2 (Evolution API):  $status2 - HTTP $($result2.StatusCode)" -ForegroundColor $(if ($result2.Success) { "Green" } else { "Red" })
Write-Host "Teste 3 (Twilio mídia):   $status3 - HTTP $($result3.StatusCode)" -ForegroundColor $(if ($result3.Success) { "Green" } else { "Red" })
Write-Host "`n"

# Dicas
Write-Host "╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                      💡 PRÓXIMOS PASSOS                           ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host "`n"
Write-Host "1. Verifique os logs da aplicação em:" -ForegroundColor White
Write-Host "   storage/logs/lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -ForegroundColor Gray
Write-Host "`n"
Write-Host "2. Configure o webhook no Twilio Console:" -ForegroundColor White
Write-Host "   URL: $webhookUrl" -ForegroundColor Gray
Write-Host "   Method: POST" -ForegroundColor Gray
Write-Host "`n"
Write-Host "3. Para Evolution API, configure:" -ForegroundColor White
Write-Host "   Webhook URL: $webhookUrl" -ForegroundColor Gray
Write-Host "   Events: messages.upsert" -ForegroundColor Gray
Write-Host "`n"
Write-Host "4. Teste via navegador:" -ForegroundColor White
Write-Host "   http://127.0.0.1:8000/test-webhook-whatsapp.html" -ForegroundColor Gray
Write-Host "`n"
