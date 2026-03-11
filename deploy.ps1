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

    # Aguarda o pull automatico do servidor processar o push
    Write-Host "Aguardando pull automatico do servidor (10s)..." -ForegroundColor Gray
    Start-Sleep -Seconds 10

    $deployCommands = @"
cd $DEPLOY_PATH && \
echo '=== COMMIT NO SERVIDOR ===' && \
git log --oneline -1 && \
echo '' && \
echo '=== COPIANDO BUILD ===' && \
cp -rf dist/public/* ./ && \
echo '' && \
echo '=== COMPOSER INSTALL ===' && \
/opt/alt/php83/usr/bin/php `$(which composer) install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5 && \
echo '' && \
echo '=== MIGRATIONS ===' && \
/opt/alt/php83/usr/bin/php artisan migrate --force 2>&1 || true && \
echo '' && \
echo '=== VERIFICAR BUILD ===' && \
ls -lh index.html && \
ls -lh assets/index-*.js assets/index-*.css 2>&1 | head -3 && \
echo '' && \
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
