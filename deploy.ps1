#!/usr/bin/env pwsh
# Deploy simples: build -> copia p/ public -> commit/push -> pull+copy no servidor -> healthcheck
# Uso: .\deploy.ps1 [-CommitMessage "msg"] [-Force] [-SkipCommit]

param(
  [string]$CommitMessage = "build: update frontend build",
  [switch]$Force,
  [switch]$SkipCommit
)

$ErrorActionPreference = "Stop"

function Step($msg) { Write-Host "`n> $msg" -ForegroundColor Cyan }
function Ok($msg)   { Write-Host "✓ $msg" -ForegroundColor Green }
function Warn($msg) { Write-Host "! $msg" -ForegroundColor Yellow }
function Fail($msg) { throw $msg }

# SSH
$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "MundoMelhor@10"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

try {
  Write-Host "`nSOCIMOB v2 deploy`n" -ForegroundColor Cyan

  Step "Build (pnpm)"
  pnpm run build
  if ($LASTEXITCODE -ne 0) { Fail "Build falhou" }
  if (-not (Test-Path "dist/public/index.html")) { Fail "Build nao gerou dist/public/index.html" }
  Ok "Build ok"

  Step "Copiar dist/public -> public"
  Copy-Item -Path "dist/public/*" -Destination "public/" -Recurse -Force
  Ok "Copiado"

  if (-not $SkipCommit) {
    Step "Git add/commit/push"
    git add .

    $hasChanges = (git status --porcelain)
    if (-not $hasChanges) {
      if (-not $Force) { Warn "Sem mudancas; use -Force para forcar commit/push (na pratica nao faz sentido)" }
      else { Warn "Sem mudancas para commitar" }
    } else {
      git commit -m $CommitMessage
      if ($LASTEXITCODE -ne 0) { Fail "Commit falhou" }
      git push origin master
      if ($LASTEXITCODE -ne 0) { Fail "Push falhou" }
      Ok "Push ok"
    }
  } else {
    Warn "SkipCommit ativo (pulando commit/push)"
  }

  Step "Deploy SSH (pull + copiar build)"
  $remote = @"
set -e
cd $DEPLOY_PATH
echo '[pull]'
git pull origin master
echo '[deploy]'
rm -f index.html.bak
[ -f index.html ] && cp index.html index.html.bak || true
rm -f index.html
rm -f assets/index-*.js assets/index-*.css || true
cp -rf dist/public/* ./
touch .htaccess 2>/dev/null || true
echo '[ok]'
ls -lh index.html
date
"@

  $done = $false

  if (Get-Command plink -ErrorAction SilentlyContinue) {
    # plink: passa o script como argumento e evita prompts
    plink -P $SSH_PORT -pw $SSH_PASS -batch "$SSH_USER@$SSH_HOST" "bash -lc '$($remote -replace "'","'\''")'"
    if ($LASTEXITCODE -ne 0) { Fail "Deploy via plink falhou (codigo $LASTEXITCODE)" }
    $done = $true
  }
  elseif (Get-Command ssh -ErrorAction SilentlyContinue) {
    if (Get-Command sshpass -ErrorAction SilentlyContinue) {
      $remote | sshpass -p $SSH_PASS ssh -p $SSH_PORT -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" "bash -s"
      if ($LASTEXITCODE -ne 0) { Fail "Deploy via ssh falhou" }
      $done = $true
    } else {
      Warn "sshpass nao encontrado; vai pedir senha no prompt"
      $remote | ssh -p $SSH_PORT -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" "bash -s"
      if ($LASTEXITCODE -ne 0) { Fail "Deploy via ssh falhou" }
      $done = $true
    }
  }

  if (-not $done) { Fail "Nem plink nem ssh encontrados no PATH" }
  Ok "Deploy ok"

  Step "Healthcheck"
  try {
    $r = Invoke-WebRequest -Uri "https://lojadaesquina.store/api/health" -TimeoutSec 10 -UseBasicParsing
    $h = $r.Content | ConvertFrom-Json
    Ok "API: $($h.status) | $($h.app) | $($h.version)"
  } catch {
    Warn "Healthcheck falhou"
  }

  Write-Host "`n✓ Concluido: https://lojadaesquina.store`n" -ForegroundColor Green
  exit 0

} catch {
  Write-Host "`nX Erro: $_`n" -ForegroundColor Red
  exit 1
}

