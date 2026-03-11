#!/usr/bin/env pwsh
# Script de deploy completo: Build + Commit + Push + Deploy SSH
# Uso: .\deploy.ps1 [mensagem-do-commit]

param(
    [string]$CommitMessage = "build: update frontend build",
    [switch]$Force
)

$ErrorActionPreference = "Stop"

# Cores
function Write-Step { param($msg) Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Write-Success { param($msg) Write-Host "V $msg" -ForegroundColor Green }
function Write-Error { param($msg) Write-Host "X $msg" -ForegroundColor Red }
function Write-Warning { param($msg) Write-Host "! $msg" -ForegroundColor Yellow }

# Configuracoes SSH
$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "MundoMelhor@10"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

try {
    Write-Host "`n" -NoNewline
    Write-Host "===============================================================" -ForegroundColor Cyan
    Write-Host "          DEPLOY AUTOMATICO - SOCIMOB v2                       " -ForegroundColor Cyan
    Write-Host "===============================================================" -ForegroundColor Cyan
    Write-Host ""

    # 1. BUILD DO FRONTEND (já gera em dist/public/)
    Write-Step "BUILD DO FRONTEND REACT"
    pnpm run build
    if ($LASTEXITCODE -ne 0) { throw "Build falhou" }
    Write-Success "Build do frontend concluido em dist/public/"
    
    # Verificar se build foi gerado
    if (-not (Test-Path "dist/public/index.html")) {
        throw "Build nao gerou dist/public/index.html - verifique vite.config.ts"
    }

    # 2. COPIAR BUILD PARA public/
    Write-Step "COPIAR BUILD PARA public/"
    try {
        Copy-Item -Path dist/public/* -Destination public/ -Recurse -Force
        Write-Success "Build copiado para public/"
    } catch {
        throw "Erro ao copiar build: $_"
    }

    # 3. VERIFICAR MUDANCAS
    Write-Step "VERIFICAR MUDANCAS NO BUILD"
    $gitStatus = git status --porcelain dist/public public/index.html public/assets
    if ([string]::IsNullOrWhiteSpace($gitStatus)) {
        Write-Warning "Nenhuma mudanca no build detectada"
        if (-not $Force) {
            $continue = Read-Host "Continuar mesmo assim? (s/N)"
            if ($continue -ne 's' -and $continue -ne 'S') {
                Write-Host "Deploy cancelado pelo usuario" -ForegroundColor Yellow
                exit 0
            }
        } else {
            Write-Host "Modo -Force ativado, continuando deploy..." -ForegroundColor Yellow
        }
    } else {
        Write-Success "Mudancas detectadas no build:"
        git status dist/public --short
    }

    # 3. COMMIT E PUSH
    Write-Step "COMMIT E PUSH"
    git add .

    $fullCommitMessage = @"
$CommitMessage

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
"@

    git commit -m $fullCommitMessage
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Commit criado com sucesso"
    } else {
        Write-Warning "Nenhuma mudanca para commitar ou commit falhou"
    }

    git push origin master
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Push para origin/master concluido"
    } else {
        throw "Push falhou"
    }

    # 4. DEPLOY NO SERVIDOR SSH
    Write-Step "DEPLOY NO SERVIDOR SSH"
    Write-Host "Servidor: $SSH_USER@$SSH_HOST`:$SSH_PORT" -ForegroundColor Yellow

    $DEPLOY_FULL_PATH = "/home/$SSH_USER/domains/lojadaesquina.store/public_html"

    # Descobre quais assets ja existem no servidor (evita copiar tudo)
    Write-Host "Verificando assets no servidor..." -ForegroundColor Gray
    $serverAssets = (echo "exit" | plink -P $SSH_PORT -pw $SSH_PASS -batch $SSH_USER@$SSH_HOST `
        "ls ${DEPLOY_FULL_PATH}/assets/ 2>/dev/null") | Where-Object { $_ -match '\.' }

    $localAssets = Get-ChildItem "public\assets" -File
    $newAssets = $localAssets | Where-Object { $serverAssets -notcontains $_.Name }

    Write-Host "Assets novos: $($newAssets.Count) de $($localAssets.Count) total" -ForegroundColor Gray

    # Copia index.html + apenas assets novos em uma unica chamada pscp
    $filesToCopy = @("public\index.html") + ($newAssets | ForEach-Object { $_.FullName })
    Write-Host "Copiando $($filesToCopy.Count) arquivo(s)..." -ForegroundColor Gray
    & pscp -P $SSH_PORT -pw $SSH_PASS -batch @filesToCopy "${SSH_USER}@${SSH_HOST}:${DEPLOY_FULL_PATH}/"
    if ($LASTEXITCODE -ne 0) { throw "Falha ao copiar arquivos para o servidor" }
    Write-Success "Frontend atualizado no servidor"

    # Comandos Laravel via plink
    $deployCommands = @"
cd $DEPLOY_FULL_PATH && \
echo '=== INDEX ATIVO ===' && \
grep 'index-' index.html && \
echo '' && \
echo '=== COMPOSER INSTALL ===' && \
/opt/alt/php83/usr/bin/php `$(which composer) install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3 && \
echo '' && \
echo '=== MIGRATIONS ===' && \
/opt/alt/php83/usr/bin/php artisan migrate --force 2>&1 || true && \
echo '=== DEPLOY CONCLUIDO ===' && \
date
"@

    echo "exit" | plink -P $SSH_PORT -pw $SSH_PASS -batch $SSH_USER@$SSH_HOST $deployCommands
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Deploy SSH concluido com sucesso"
    } else {
        Write-Warning "Deploy SSH completou com avisos (codigo: $LASTEXITCODE)"
    }

    # 5. VERIFICAR SITE
    Write-Step "VERIFICAR SITE ONLINE"
    try {
        $response = Invoke-WebRequest -Uri "https://lojadaesquina.store/api/health" -TimeoutSec 10 -UseBasicParsing
        $health = $response.Content | ConvertFrom-Json
        Write-Success "API Health: $($health.status)"
        Write-Host "  App: $($health.app)" -ForegroundColor Gray
        Write-Host "  Version: $($health.version)" -ForegroundColor Gray
    } catch {
        Write-Warning "Nao foi possivel verificar API health"
    }

    # SUCESSO
    Write-Host ""
    Write-Host "===============================================================" -ForegroundColor Green
    Write-Host "              DEPLOY CONCLUIDO COM SUCESSO!                    " -ForegroundColor Green
    Write-Host "===============================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Frontend: https://lojadaesquina.store" -ForegroundColor Cyan
    Write-Host "API:      https://lojadaesquina.store/api/health" -ForegroundColor Cyan
    Write-Host ""

} catch {
    Write-Host ""
    Write-Host "===============================================================" -ForegroundColor Red
    Write-Host "                   ERRO NO DEPLOY!                             " -ForegroundColor Red
    Write-Host "===============================================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "Erro: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Stack trace:" -ForegroundColor Gray
    Write-Host $_.ScriptStackTrace -ForegroundColor DarkGray
    exit 1
}
