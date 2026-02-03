#!/usr/bin/env pwsh
# Deploy e configuração do WhatsApp SMS

$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "MundoMelhor@10"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== DEPLOY E CONFIGURAÇÃO SMS WHATSAPP ===" -ForegroundColor Cyan
Write-Host "Servidor: $SSH_USER@$SSH_HOST`:$SSH_PORT" -ForegroundColor Yellow
Write-Host ""

# Script remoto
$remoteScript = @"
cd $DEPLOY_PATH

echo '=== GIT PULL ==='
git pull origin master

echo ''
echo '=== EXECUTAR MIGRATION ==='
php artisan migrate --force

echo ''
echo '=== CONFIGURAÇÃO CONCLUÍDA ==='
echo 'Para configurar o número de WhatsApp do tenant, execute:'
echo 'php configure_whatsapp_number.php'
"@

# Salvar script temporário
$scriptPath = "$env:TEMP\deploy_sms_whatsapp.sh"
$remoteScript | Out-File -FilePath $scriptPath -Encoding UTF8 -NoNewline

Write-Host "📤 Executando comandos no servidor..." -ForegroundColor Yellow

# Executar via plink
$plinkArgs = @(
    "-ssh"
    "$SSH_USER@$SSH_HOST"
    "-P", $SSH_PORT
    "-pw", $SSH_PASS
    "-batch"
    "-m", $scriptPath
)

& plink $plinkArgs

# Limpar arquivo temporário
Remove-Item $scriptPath -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "✅ Deploy concluído!" -ForegroundColor Green
Write-Host ""
Write-Host "📝 Próximos passos:" -ForegroundColor Yellow
Write-Host "1. Conectar ao servidor e executar: php configure_whatsapp_number.php"
Write-Host "2. Configurar número de WhatsApp do tenant (ex: +5531973341150)"
Write-Host "3. Testar webhook do Chaves na Mão"
Write-Host ""
