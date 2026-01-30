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

    # 2. VERIFICAR MUDANCAS
    Write-Step "VERIFICAR MUDANCAS NO BUILD"
    $gitStatus = git status --porcelain dist/public
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
    git add dist/public

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

    # 4. DEPLOY NO SERVIDOR SSH (apenas pull e copy)
    Write-Step "DEPLOY NO SERVIDOR SSH"
    Write-Host "Servidor: $SSH_USER@$SSH_HOST`:$SSH_PORT" -ForegroundColor Yellow
    Write-Host "Caminho: $DEPLOY_PATH" -ForegroundColor Yellow
    Write-Host ""

    $deployCommands = @"
cd $DEPLOY_PATH && \
echo '=== GIT PULL ===' && \
git pull origin master && \
echo '' && \
echo '=== LIMPAR PUBLIC COMPLETO ===' && \
rm -rf public/* && \
echo '' && \
echo '=== COPIAR BUILD ===' && \
cp -rf dist/public/* public/ && \
echo '' && \
echo '=== LIMPAR CACHE (touch .htaccess) ===' && \
touch public/.htaccess && \
echo '' && \
echo '=== VERIFICAR BUILD ===' && \
ls -lh public/index.html && \
ls -lh public/assets/ | head -5 && \
echo '' && \
echo '=== DEPLOY CONCLUIDO ===' && \
date
"@

    # Verificar se plink esta disponivel
    if (Get-Command plink -ErrorAction SilentlyContinue) {
        Write-Host "Conectando via plink..." -ForegroundColor Gray
        # -batch: non-interactive mode (no prompts)
        # Pipe commands to plink stdin
        $deployCommands | plink -P $SSH_PORT -pw $SSH_PASS -batch $SSH_USER@$SSH_HOST

        if ($LASTEXITCODE -eq 0) {
            Write-Success "Deploy SSH concluido com sucesso"
        } else {
            Write-Warning "Deploy SSH completou com avisos (codigo: $LASTEXITCODE)"
        }
    } elseif (Get-Command ssh -ErrorAction SilentlyContinue) {
        Write-Host "Conectando via ssh..." -ForegroundColor Gray
        # Use sshpass if available, otherwise manual password entry
        if (Get-Command sshpass -ErrorAction SilentlyContinue) {
            $deployCommands | sshpass -p $SSH_PASS ssh -p $SSH_PORT -o StrictHostKeyChecking=no $SSH_USER@$SSH_HOST "bash -s"
        } else {
            Write-Warning "Voce precisara digitar a senha manualmente: $SSH_PASS"
            $deployCommands | ssh -p $SSH_PORT -o StrictHostKeyChecking=no $SSH_USER@$SSH_HOST "bash -s"
        }

        if ($LASTEXITCODE -eq 0) {
            Write-Success "Deploy SSH concluido com sucesso"
        } else {
            Write-Warning "Deploy SSH completou com avisos"
        }
    } else {
        Write-Error "Nem plink nem ssh encontrados!"
        Write-Warning "Execute manualmente no servidor:"
        Write-Host ""
        Write-Host "ssh -p $SSH_PORT $SSH_USER@$SSH_HOST" -ForegroundColor Yellow
        Write-Host "cd $DEPLOY_PATH" -ForegroundColor Yellow
        Write-Host "git pull origin master" -ForegroundColor Yellow
        Write-Host "cp -rf dist/public/* public/" -ForegroundColor Yellow
        Write-Host ""
        exit 1
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
