#!/usr/bin/env pwsh
# Deploy helper (novo fluxo): somente git push.
# O deploy no servidor é executado automaticamente pelo GitHub Actions.
# Uso: .\deploy.ps1 [mensagem] [-SkipCheck]

param(
    [string]$CommitMessage = "chore: update",
    [switch]$SkipCheck
)

$ErrorActionPreference = "Stop"

function Write-Step { param($msg) Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Write-Success { param($msg) Write-Host "V $msg" -ForegroundColor Green }
function Write-Warn { param($msg) Write-Host "! $msg" -ForegroundColor Yellow }

try {
    Write-Step "VALIDACAO LOCAL"
    if (-not $SkipCheck) {
        pnpm run check
        if ($LASTEXITCODE -ne 0) { throw "Falha na validacao TypeScript" }
        Write-Success "TypeScript OK"
    } else {
        Write-Warn "Validacao ignorada (-SkipCheck)"
    }

    Write-Step "COMMIT"
    git add -A

    $hasChanges = git diff --cached --name-only
    if ([string]::IsNullOrWhiteSpace($hasChanges)) {
        Write-Warn "Nenhuma mudanca para commit"
    } else {
        git commit -m $CommitMessage
        Write-Success "Commit criado"
    }

    Write-Step "PUSH"
    $branch = git branch --show-current
    if ([string]::IsNullOrWhiteSpace($branch)) { $branch = "master" }
    git push origin $branch
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push" }

    Write-Success "Push concluido em origin/$branch"
    Write-Host "Deploy no servidor sera executado automaticamente pelo workflow .github/workflows/hostinger-deploy.yml" -ForegroundColor Cyan
}
catch {
    Write-Host "`nErro: $_" -ForegroundColor Red
    exit 1
}
