# Script para iniciar ambiente de teste WhatsApp com Ngrok
# Uso: .\start_webhook_test.ps1

param(
    [string]$NgrokUrl = "https://99a3345711a3.ngrok-free.app"
)

Write-Host "`n"
Write-Host "╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║    🟢 EXCLUSIVA - Ambiente de Teste WhatsApp via Ngrok           ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host "`n"

# 1. Parar processos PHP existentes
Write-Host "1️⃣ Parando processos PHP existentes..." -ForegroundColor Yellow
$phpProcesses = Get-Process -Name php -ErrorAction SilentlyContinue
if ($phpProcesses) {
    $phpProcesses | Stop-Process -Force
    Write-Host "   ✅ Processos PHP parados" -ForegroundColor Green
} else {
    Write-Host "   ℹ️ Nenhum processo PHP rodando" -ForegroundColor Gray
}

Start-Sleep -Seconds 1

# 2. Iniciar servidor PHP
Write-Host "`n2️⃣ Iniciando servidor PHP..." -ForegroundColor Yellow
$serverJob = Start-Job -ScriptBlock {
    Set-Location "c:\Projetos\saas"
    php -S 127.0.0.1:8000 -t public
}

Start-Sleep -Seconds 2

# Verificar se servidor iniciou
try {
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:8000" -UseBasicParsing -TimeoutSec 5
    Write-Host "   ✅ Servidor PHP rodando em http://127.0.0.1:8000" -ForegroundColor Green
} catch {
    Write-Host "   ❌ Erro ao iniciar servidor: $_" -ForegroundColor Red
    Stop-Job -Job $serverJob
    Remove-Job -Job $serverJob
    exit 1
}

# 3. Configuração do Ngrok
Write-Host "`n3️⃣ Configuração Ngrok" -ForegroundColor Yellow
Write-Host "   URL configurada: $NgrokUrl" -ForegroundColor Cyan
Write-Host "   Webhook endpoint: $NgrokUrl/webhook/whatsapp" -ForegroundColor Cyan

# 4. Teste local primeiro
Write-Host "`n4️⃣ Testando endpoint local..." -ForegroundColor Yellow
try {
    $testPayload = @{
        From = "whatsapp:+5521987654321"
        To = "whatsapp:+5521999887766"
        Body = "Teste automatizado"
        MessageSid = "SM_TEST_" + (Get-Random)
        ProfileName = "Teste Script"
    }
    
    $formData = ($testPayload.GetEnumerator() | ForEach-Object { "$($_.Key)=$($_.Value)" }) -join "&"
    
    $localTest = Invoke-WebRequest -Uri "http://127.0.0.1:8000/webhook/whatsapp" `
        -Method POST `
        -Body $formData `
        -ContentType "application/x-www-form-urlencoded" `
        -UseBasicParsing
    
    if ($localTest.StatusCode -eq 200) {
        Write-Host "   ✅ Endpoint local funcionando! Status: $($localTest.StatusCode)" -ForegroundColor Green
    }
} catch {
    Write-Host "   ⚠️ Teste local falhou: $_" -ForegroundColor Yellow
    Write-Host "   Continuando mesmo assim..." -ForegroundColor Gray
}

# 5. Menu de opções
Write-Host "`n"
Write-Host "╔═══════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║                  🎯 AMBIENTE PRONTO!                              ║" -ForegroundColor Green
Write-Host "╚═══════════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host "`n"

Write-Host "📋 Opções disponíveis:" -ForegroundColor White
Write-Host "   1. Abrir interface de teste no navegador" -ForegroundColor Cyan
Write-Host "   2. Executar testes via PowerShell" -ForegroundColor Cyan
Write-Host "   3. Ver logs da aplicação" -ForegroundColor Cyan
Write-Host "   4. Testar webhook com mensagem personalizada" -ForegroundColor Cyan
Write-Host "   5. Abrir ngrok dashboard (http://127.0.0.1:4040)" -ForegroundColor Cyan
Write-Host "   6. Parar servidor e sair" -ForegroundColor Red
Write-Host "`n"

do {
    $choice = Read-Host "Escolha uma opção (1-6)"
    
    switch ($choice) {
        "1" {
            Write-Host "`n🌐 Abrindo navegador..." -ForegroundColor Cyan
            Start-Process "http://127.0.0.1:8000/test-webhook-whatsapp.html"
            Write-Host "✅ Navegador aberto!" -ForegroundColor Green
        }
        
        "2" {
            Write-Host "`n🚀 Executando testes..." -ForegroundColor Cyan
            & "$PSScriptRoot\test_webhook_ngrok.ps1"
        }
        
        "3" {
            Write-Host "`n📋 Últimas 30 linhas do log:" -ForegroundColor Cyan
            $logFile = "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log"
            if (Test-Path $logFile) {
                Get-Content $logFile -Tail 30
            } else {
                Write-Host "❌ Arquivo de log não encontrado: $logFile" -ForegroundColor Red
            }
        }
        
        "4" {
            Write-Host "`n📱 Enviar mensagem personalizada" -ForegroundColor Cyan
            $from = Read-Host "Número de origem (ex: +5521987654321)"
            $message = Read-Host "Mensagem"
            $name = Read-Host "Nome do contato"
            
            $customPayload = @{
                From = "whatsapp:$from"
                To = "whatsapp:+5521999887766"
                Body = $message
                MessageSid = "SM_CUSTOM_" + (Get-Random)
                ProfileName = $name
            }
            
            $formData = ($customPayload.GetEnumerator() | ForEach-Object { "$($_.Key)=$($_.Value)" }) -join "&"
            
            try {
                $result = Invoke-WebRequest -Uri "$NgrokUrl/webhook/whatsapp" `
                    -Method POST `
                    -Body $formData `
                    -ContentType "application/x-www-form-urlencoded" `
                    -UseBasicParsing
                
                Write-Host "✅ Mensagem enviada! Status: $($result.StatusCode)" -ForegroundColor Green
            } catch {
                Write-Host "❌ Erro: $_" -ForegroundColor Red
            }
        }
        
        "5" {
            Write-Host "`n🌐 Abrindo Ngrok Dashboard..." -ForegroundColor Cyan
            Start-Process "http://127.0.0.1:4040"
            Write-Host "✅ Dashboard aberto!" -ForegroundColor Green
        }
        
        "6" {
            Write-Host "`n🛑 Parando servidor..." -ForegroundColor Red
            Stop-Job -Job $serverJob
            Remove-Job -Job $serverJob
            Write-Host "✅ Servidor parado. Até logo!" -ForegroundColor Green
            exit 0
        }
        
        default {
            Write-Host "❌ Opção inválida. Escolha 1-6." -ForegroundColor Red
        }
    }
    
    Write-Host "`n"
    
} while ($choice -ne "6")
