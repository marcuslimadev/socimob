#!/usr/bin/env pwsh

Write-Host "`n=== ADICIONAR WEBHOOK_TENANT_ID NO .ENV DE PRODUCAO ===" -ForegroundColor Cyan

$server = "u815655858@145.223.105.168"
$port = "65002"
$path = "~/domains/lojadaesquina.store/public_html"
$password = "MundoMelhor@10"

Write-Host "`nServidor: $server" -ForegroundColor Yellow
Write-Host "Caminho: $path" -ForegroundColor Yellow

# Comando para adicionar WEBHOOK_TENANT_ID no .env se não existir
$commands = @"
cd $path
if ! grep -q 'WEBHOOK_TENANT_ID' .env; then
    echo '' >> .env
    echo '# Webhook configuration' >> .env
    echo 'WEBHOOK_TENANT_ID=1' >> .env
    echo 'WEBHOOK_TENANT_ID adicionado ao .env'
else
    echo 'WEBHOOK_TENANT_ID ja existe no .env'
fi
cat .env | grep WEBHOOK_TENANT_ID
"@

Write-Host "`nExecutando comandos via SSH..." -ForegroundColor Yellow

$commands | plink -batch -pw $password -P $port $server

Write-Host "`n=== CONCLUIDO ===" -ForegroundColor Green
