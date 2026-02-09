#!/usr/bin/env pwsh
# Executar teste de mapeamento em produção

$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PATH = "~/domains/lojadaesquina.store/public_html"

# Comandos a executar
$commands = @(
    "cd $SSH_PATH",
    "git pull",
    "/opt/alt/php83/usr/bin/php test_chaves_mapping.php"
) -join " && "

Write-Host "`n=== TESTE MAPEAMENTO CHAVES NA MÃO ===" -ForegroundColor Cyan
Write-Host "Servidor: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}" -ForegroundColor Gray
Write-Host ""

# Executar via plink
$plinkCmd = "plink -ssh -P $SSH_PORT ${SSH_USER}@${SSH_HOST} `"$commands`""
Write-Host "Executando: $plinkCmd" -ForegroundColor Gray
Write-Host ""

Invoke-Expression $plinkCmd
