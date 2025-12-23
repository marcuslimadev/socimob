# Script para obter URL atual do ngrok
# Uso: .\get_ngrok_url.ps1

Write-Host "`n╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║              🌐 VERIFICAR URL DO NGROK                           ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

try {
    $tunnels = Invoke-RestMethod -Uri "http://127.0.0.1:4040/api/tunnels" -ErrorAction Stop
    
    if ($tunnels.tunnels.Count -eq 0) {
        Write-Host "❌ Nenhum túnel ngrok ativo!" -ForegroundColor Red
        Write-Host "`nPara iniciar ngrok:" -ForegroundColor Yellow
        Write-Host "   ngrok http 8000`n" -ForegroundColor White
        exit 1
    }
    
    $httpsTunnel = $tunnels.tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1
    
    if ($httpsTunnel) {
        $url = $httpsTunnel.public_url
        
        Write-Host "✅ Ngrok está ativo!`n" -ForegroundColor Green
        Write-Host "📍 URL PÚBLICA:" -ForegroundColor White
        Write-Host "   $url`n" -ForegroundColor Cyan
        
        Write-Host "🔗 ENDPOINT WEBHOOK:" -ForegroundColor White
        Write-Host "   $url/webhook/whatsapp`n" -ForegroundColor Yellow
        
        Write-Host "📊 DASHBOARD:" -ForegroundColor White
        Write-Host "   http://127.0.0.1:4040`n" -ForegroundColor Gray
        
        Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
        Write-Host "`n🔧 CONFIGURAR NO TWILIO:`n" -ForegroundColor Yellow
        Write-Host "1. Acesse: https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn" -ForegroundColor White
        Write-Host "2. Vá em 'Sandbox Settings'" -ForegroundColor White
        Write-Host "3. Cole esta URL:" -ForegroundColor White
        Write-Host "   $url/webhook/whatsapp" -ForegroundColor Cyan
        Write-Host "4. Método: POST" -ForegroundColor White
        Write-Host "5. Salve`n" -ForegroundColor White
        
        # Copiar para clipboard
        $url + "/webhook/whatsapp" | Set-Clipboard
        Write-Host "✅ URL copiada para área de transferência!`n" -ForegroundColor Green
        
        # Testar conexão
        Write-Host "🧪 Testando conexão...`n" -ForegroundColor Yellow
        
        try {
            $test = Invoke-WebRequest -Uri "$url" -UseBasicParsing -TimeoutSec 5
            Write-Host "   ✅ Servidor acessível via ngrok! Status: $($test.StatusCode)" -ForegroundColor Green
        } catch {
            Write-Host "   ❌ Erro ao acessar via ngrok: $_" -ForegroundColor Red
            Write-Host "   Verifique se o servidor PHP está rodando em 0.0.0.0:8000" -ForegroundColor Yellow
        }
        
        Write-Host "`n═══════════════════════════════════════════════════════════════════`n" -ForegroundColor Cyan
        
        # Menu
        Write-Host "💡 Ações rápidas:" -ForegroundColor White
        Write-Host "   [1] Abrir Twilio Console" -ForegroundColor Cyan
        Write-Host "   [2] Abrir Ngrok Dashboard" -ForegroundColor Cyan
        Write-Host "   [3] Testar webhook" -ForegroundColor Cyan
        Write-Host "   [4] Ver logs" -ForegroundColor Cyan
        Write-Host "   [Q] Sair`n" -ForegroundColor Gray
        
        $choice = Read-Host "Escolha"
        
        switch ($choice) {
            "1" {
                Start-Process "https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn"
                Write-Host "`n✅ Twilio Console aberto!`n" -ForegroundColor Green
            }
            "2" {
                Start-Process "http://127.0.0.1:4040"
                Write-Host "`n✅ Ngrok Dashboard aberto!`n" -ForegroundColor Green
            }
            "3" {
                Write-Host "`n🧪 Testando webhook...`n" -ForegroundColor Cyan
                try {
                    $testPayload = @{
                        From = "whatsapp:+5521999999999"
                        To = "whatsapp:+5521888888888"
                        Body = "Teste automatizado via script"
                        MessageSid = "SM_TEST_" + (Get-Random)
                        ProfileName = "Teste"
                    }
                    
                    $formData = ($testPayload.GetEnumerator() | ForEach-Object { "$($_.Key)=$([System.Uri]::EscapeDataString($_.Value))" }) -join "&"
                    
                    $result = Invoke-WebRequest -Uri "$url/webhook/whatsapp" `
                        -Method POST `
                        -Body $formData `
                        -ContentType "application/x-www-form-urlencoded" `
                        -UseBasicParsing
                    
                    Write-Host "✅ Webhook respondeu! Status: $($result.StatusCode)" -ForegroundColor Green
                    Write-Host "Resposta: $($result.Content)`n" -ForegroundColor Gray
                } catch {
                    Write-Host "❌ Erro: $_`n" -ForegroundColor Red
                }
            }
            "4" {
                $logFile = "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log"
                if (Test-Path $logFile) {
                    Write-Host "`n📋 Últimas 30 linhas do log:`n" -ForegroundColor Cyan
                    Get-Content $logFile -Tail 30
                } else {
                    Write-Host "`n❌ Log não encontrado`n" -ForegroundColor Red
                }
            }
            default {
                Write-Host "`n👋 Até logo!`n" -ForegroundColor Gray
            }
        }
        
    } else {
        Write-Host "❌ Túnel HTTPS não encontrado!" -ForegroundColor Red
    }
    
} catch {
    Write-Host "❌ Erro ao conectar ao ngrok!" -ForegroundColor Red
    Write-Host "`nPossíveis causas:" -ForegroundColor Yellow
    Write-Host "   1. Ngrok não está rodando" -ForegroundColor White
    Write-Host "   2. Ngrok não está na porta padrão (4040)`n" -ForegroundColor White
    Write-Host "Para iniciar ngrok:" -ForegroundColor Yellow
    Write-Host "   ngrok http 8000`n" -ForegroundColor White
    Write-Host "Erro: $_`n" -ForegroundColor Gray
}
