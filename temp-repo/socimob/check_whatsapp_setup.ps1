# Script para verificar configuração WhatsApp
# Uso: .\check_whatsapp_setup.ps1

Write-Host "`n"
Write-Host "╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║    📱 VERIFICAÇÃO: Configuração WhatsApp                         ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host "`n"

$allGood = $true

# 1. Verificar Servidor PHP
Write-Host "1️⃣ Servidor PHP..." -ForegroundColor Yellow -NoNewline
try {
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:8000" -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
    Write-Host " ✅ RODANDO" -ForegroundColor Green
    Write-Host "   URL: http://127.0.0.1:8000" -ForegroundColor Gray
} catch {
    Write-Host " ❌ NÃO RODANDO" -ForegroundColor Red
    Write-Host "   Inicie com: php -S 127.0.0.1:8000 -t public" -ForegroundColor Yellow
    $allGood = $false
}

# 2. Verificar Endpoint Webhook
Write-Host "`n2️⃣ Endpoint /webhook/whatsapp..." -ForegroundColor Yellow -NoNewline
try {
    $testPayload = @{
        From = "whatsapp:+5500000000000"
        Body = "teste"
        MessageSid = "TEST123"
    }
    $formData = ($testPayload.GetEnumerator() | ForEach-Object { "$($_.Key)=$($_.Value)" }) -join "&"
    
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:8000/webhook/whatsapp" `
        -Method POST `
        -Body $formData `
        -ContentType "application/x-www-form-urlencoded" `
        -UseBasicParsing `
        -TimeoutSec 3 `
        -ErrorAction Stop
    
    Write-Host " ✅ FUNCIONANDO" -ForegroundColor Green
    Write-Host "   Status: $($response.StatusCode)" -ForegroundColor Gray
} catch {
    Write-Host " ❌ ERRO" -ForegroundColor Red
    Write-Host "   $_" -ForegroundColor Yellow
    $allGood = $false
}

# 3. Verificar Ngrok
Write-Host "`n3️⃣ Ngrok..." -ForegroundColor Yellow -NoNewline
try {
    # Tentar acessar API do ngrok
    $ngrokApi = Invoke-RestMethod -Uri "http://127.0.0.1:4040/api/tunnels" -ErrorAction Stop
    $tunnel = $ngrokApi.tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1
    
    if ($tunnel) {
        Write-Host " ✅ RODANDO" -ForegroundColor Green
        Write-Host "   URL: $($tunnel.public_url)" -ForegroundColor Gray
        Write-Host "   Dashboard: http://127.0.0.1:4040" -ForegroundColor Gray
        
        $ngrokUrl = $tunnel.public_url
        
        # Testar ngrok
        Write-Host "`n   🔍 Testando acesso via ngrok..." -ForegroundColor Cyan -NoNewline
        try {
            $ngrokTest = Invoke-WebRequest -Uri "$ngrokUrl/webhook/whatsapp" `
                -Method POST `
                -Body "test=1" `
                -UseBasicParsing `
                -TimeoutSec 5 `
                -ErrorAction Stop
            Write-Host " ✅ OK" -ForegroundColor Green
        } catch {
            Write-Host " ❌ FALHOU" -ForegroundColor Red
            Write-Host "      $_" -ForegroundColor Yellow
            $allGood = $false
        }
    } else {
        Write-Host " ⚠️ SEM TÚNEL HTTPS" -ForegroundColor Yellow
        $allGood = $false
    }
} catch {
    Write-Host " ❌ NÃO RODANDO" -ForegroundColor Red
    Write-Host "   Inicie com: ngrok http 8000" -ForegroundColor Yellow
    $allGood = $false
    $ngrokUrl = $null
}

# 4. Verificar Configurações no .env
Write-Host "`n4️⃣ Configurações Twilio no .env..." -ForegroundColor Yellow
$envFile = Get-Content ".env" -ErrorAction SilentlyContinue

if ($envFile) {
    $twilioSid = $envFile | Select-String "TWILIO_ACCOUNT_SID=(.+)" | ForEach-Object { $_.Matches.Groups[1].Value }
    $twilioToken = $envFile | Select-String "TWILIO_AUTH_TOKEN=(.+)" | ForEach-Object { $_.Matches.Groups[1].Value }
    $twilioFrom = $envFile | Select-String "TWILIO_WHATSAPP_FROM=(.+)" | ForEach-Object { $_.Matches.Groups[1].Value }
    
    if ($twilioSid -and $twilioSid -ne "sua_twilio_account_sid") {
        Write-Host "   ✅ TWILIO_ACCOUNT_SID: configurado" -ForegroundColor Green
    } else {
        Write-Host "   ❌ TWILIO_ACCOUNT_SID: não configurado" -ForegroundColor Red
        $allGood = $false
    }
    
    if ($twilioToken -and $twilioToken -ne "seu_twilio_auth_token") {
        Write-Host "   ✅ TWILIO_AUTH_TOKEN: configurado" -ForegroundColor Green
    } else {
        Write-Host "   ❌ TWILIO_AUTH_TOKEN: não configurado" -ForegroundColor Red
        $allGood = $false
    }
    
    if ($twilioFrom -and $twilioFrom -ne "whatsapp:+5531999999999") {
        Write-Host "   ✅ TWILIO_WHATSAPP_FROM: $twilioFrom" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️ TWILIO_WHATSAPP_FROM: não configurado (OK se usar sandbox)" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ❌ Arquivo .env não encontrado!" -ForegroundColor Red
    $allGood = $false
}

# 5. Verificar Banco de Dados
Write-Host "`n5️⃣ Banco de Dados..." -ForegroundColor Yellow -NoNewline
try {
    $dbConfig = $envFile | Select-String "DB_DATABASE=(.+)" | ForEach-Object { $_.Matches.Groups[1].Value }
    
    if ($dbConfig) {
        Write-Host " ✅ Configurado: $dbConfig" -ForegroundColor Green
        # TODO: Testar conexão real com MySQL se necessário
    } else {
        Write-Host " ❌ Não configurado" -ForegroundColor Red
        $allGood = $false
    }
} catch {
    Write-Host " ❌ Erro ao verificar" -ForegroundColor Red
    $allGood = $false
}

# 6. Verificar Logs
Write-Host "`n6️⃣ Sistema de Logs..." -ForegroundColor Yellow -NoNewline
$logFile = "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log"
if (Test-Path $logFile) {
    $logSize = (Get-Item $logFile).Length
    Write-Host " ✅ Funcionando" -ForegroundColor Green
    Write-Host "   Arquivo: $logFile" -ForegroundColor Gray
    Write-Host "   Tamanho: $([math]::Round($logSize/1KB, 2)) KB" -ForegroundColor Gray
} else {
    Write-Host " ⚠️ Nenhum log hoje (normal se acabou de iniciar)" -ForegroundColor Yellow
}

# Resumo Final
Write-Host "`n"
Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Cyan

if ($allGood) {
    Write-Host "🎉 TUDO PRONTO PARA RECEBER MENSAGENS!" -ForegroundColor Green
    Write-Host "`n📋 PRÓXIMOS PASSOS:" -ForegroundColor White
    Write-Host "   1. Acesse: https://console.twilio.com/" -ForegroundColor Cyan
    Write-Host "   2. Configure webhook para: $ngrokUrl/webhook/whatsapp" -ForegroundColor Cyan
    Write-Host "   3. Conecte seu WhatsApp ao sandbox Twilio" -ForegroundColor Cyan
    Write-Host "   4. Envie uma mensagem de teste!" -ForegroundColor Cyan
    Write-Host "`n📊 MONITORAR:" -ForegroundColor White
    Write-Host "   Get-Content '$logFile' -Wait" -ForegroundColor Gray
} else {
    Write-Host "⚠️ ALGUNS ITENS PRECISAM DE ATENÇÃO" -ForegroundColor Yellow
    Write-Host "`nVerifique os itens marcados com ❌ acima." -ForegroundColor White
}

Write-Host "═══════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "`n"

# Menu rápido
Write-Host "💡 AÇÕES RÁPIDAS:" -ForegroundColor Cyan
Write-Host "   [1] Abrir Twilio Console" -ForegroundColor White
Write-Host "   [2] Abrir Ngrok Dashboard" -ForegroundColor White
Write-Host "   [3] Ver logs em tempo real" -ForegroundColor White
Write-Host "   [4] Testar webhook local" -ForegroundColor White
Write-Host "   [5] Ver instruções completas" -ForegroundColor White
Write-Host "   [Q] Sair" -ForegroundColor Gray
Write-Host "`n"

$action = Read-Host "Escolha uma opção"

switch ($action) {
    "1" {
        Start-Process "https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn"
        Write-Host "✅ Twilio Console aberto no navegador" -ForegroundColor Green
    }
    "2" {
        Start-Process "http://127.0.0.1:4040"
        Write-Host "✅ Ngrok Dashboard aberto no navegador" -ForegroundColor Green
    }
    "3" {
        if (Test-Path $logFile) {
            Write-Host "`n📋 Monitorando logs... (Ctrl+C para sair)" -ForegroundColor Cyan
            Get-Content $logFile -Wait -Tail 20
        } else {
            Write-Host "❌ Arquivo de log não encontrado" -ForegroundColor Red
        }
    }
    "4" {
        Write-Host "`n🧪 Executando teste local..." -ForegroundColor Cyan
        & "$PSScriptRoot\test_webhook_ngrok.ps1"
    }
    "5" {
        if (Test-Path "CONFIGURAR_WHATSAPP_REAL.md") {
            Write-Host "`n📖 Abrindo instruções..." -ForegroundColor Cyan
            code "CONFIGURAR_WHATSAPP_REAL.md"
        } else {
            Write-Host "❌ Arquivo de instruções não encontrado" -ForegroundColor Red
        }
    }
    default {
        Write-Host "👋 Até logo!" -ForegroundColor Gray
    }
}
