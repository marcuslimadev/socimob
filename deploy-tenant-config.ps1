#!/usr/bin/env pwsh
# Deploy e configuração completa do tenant Exclusiva

$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "MundoMelhor@10"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== DEPLOY E CONFIGURAÇÃO TENANT EXCLUSIVA ===" -ForegroundColor Cyan
Write-Host ""

$remoteScript = @"
cd $DEPLOY_PATH

echo '=== GIT PULL ==='
git pull origin master

echo ''
echo '=== EXECUTAR MIGRATIONS ==='
php artisan migrate --force

echo ''
echo '=== CONFIGURAR TENANT EXCLUSIVA ==='
php setup_exclusiva_configs.php

echo ''
echo '=== VERIFICAR CONFIGURAÇÃO ==='
mysql -u u815655858_saas -p'MundoMelhor@10' u815655858_saas -e "
SELECT 
    tc.tenant_id,
    t.nome,
    tc.whatsapp_number,
    tc.ai_assistant_name,
    tc.openai_model,
    tc.smtp_from_email
FROM tenant_configs tc
INNER JOIN tenants t ON t.id = tc.tenant_id
WHERE tc.tenant_id = 1;
"

echo ''
echo '✅ CONFIGURAÇÃO CONCLUÍDA'
"@

$scriptPath = "$env:TEMP\deploy_tenant_config.sh"
$remoteScript | Out-File -FilePath $scriptPath -Encoding UTF8 -NoNewline

Write-Host "📤 Executando no servidor..." -ForegroundColor Yellow

& plink -ssh "$SSH_USER@$SSH_HOST" -P $SSH_PORT -pw $SSH_PASS -batch -m $scriptPath

Remove-Item $scriptPath -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "✅ Deploy concluído!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 O tenant Exclusiva foi configurado com:" -ForegroundColor Yellow
Write-Host "  - WhatsApp: +553173341150"
Write-Host "  - IA: Teresa (gpt-4o-mini)"
Write-Host "  - Email: alert@socimob.com"
Write-Host "  - Notificações: contato@exclusivalarimoveis.com.br"
Write-Host ""
