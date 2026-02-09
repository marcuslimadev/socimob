# Script para executar comandos PHP no servidor via SSH

$ErrorActionPreference = "Stop"

# Configurações do servidor
$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PATH = "~/domains/lojadaesquina.store/public_html"

Write-Host ""
Write-Host "===============================================================" -ForegroundColor Cyan
Write-Host "          EXECUTAR SCRIPT PHP NO SERVIDOR" -ForegroundColor Cyan
Write-Host "===============================================================" -ForegroundColor Cyan
Write-Host ""

# Script a executar
$scriptName = "create_pessoa_roberto.php"

Write-Host "=== EXECUTANDO $scriptName ===" -ForegroundColor Yellow
Write-Host ""

# Comandos SSH
$sshCommands = @"
cd $SSH_PATH
git pull origin master
/opt/alt/php83/usr/bin/php $scriptName
"@

# Executar via plink
$plinkPath = "plink"
$sshTarget = "${SSH_USER}@${SSH_HOST}:${SSH_PORT}"

Write-Host "Servidor: $sshTarget" -ForegroundColor Gray
Write-Host "Caminho: $SSH_PATH" -ForegroundColor Gray
Write-Host ""

Write-Host "Conectando via plink..." -ForegroundColor Yellow

# Executar comandos
$sshCommands | & $plinkPath -ssh $sshTarget -P $SSH_PORT -batch

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "V Script executado com sucesso" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "X Erro na execução do script" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "===============================================================" -ForegroundColor Cyan
Write-Host "              EXECUÇÃO CONCLUÍDA" -ForegroundColor Cyan
Write-Host "===============================================================" -ForegroundColor Cyan
Write-Host ""
