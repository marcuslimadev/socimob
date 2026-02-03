#!/usr/bin/env pwsh
# Deploy forçado - limpar conflitos e atualizar

$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "MundoMelhor@10"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== DEPLOY FORÇADO - LIMPAR E ATUALIZAR ===" -ForegroundColor Cyan

$remoteScript = @"
cd $DEPLOY_PATH

echo '=== LIMPAR ARQUIVOS CONFLITANTES ==='
rm -f public/assets/index-DSMH3E2_.css
rm -f public/assets/index-JbyPSATz.js

echo ''
echo '=== GIT PULL ==='
git pull origin master

echo ''
echo '=== EXECUTAR MIGRATION ==='
php artisan migrate --force

echo ''
echo '✅ DEPLOY CONCLUÍDO'
"@

$scriptPath = "$env:TEMP\deploy_force.sh"
$remoteScript | Out-File -FilePath $scriptPath -Encoding UTF8 -NoNewline

& plink -ssh "$SSH_USER@$SSH_HOST" -P $SSH_PORT -pw $SSH_PASS -batch -m $scriptPath

Remove-Item $scriptPath -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "✅ Deploy concluído!" -ForegroundColor Green
