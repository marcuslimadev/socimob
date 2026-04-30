#!/usr/bin/env pwsh
# Script de deploy completo: Build + Commit + Push + Deploy SSH
# Uso: .\deploy.ps1 [mensagem-do-commit]

param(
    [string]$CommitMessage = "build: update frontend build",
    [switch]$Force,
    [switch]$RemoteFailureSelfTest,
    [string]$HealthUrl
)

$ErrorActionPreference = "Stop"

function Get-NextDeployVersion {
    param(
        [string]$VersionFilePath,
        [string]$FallbackBaseVersion
    )

    $baseVersion = $FallbackBaseVersion
    if ([string]::IsNullOrWhiteSpace($baseVersion)) {
        $baseVersion = "11.48.0"
    }

    $majorMinor = "11.48"
    if ($baseVersion -match '^(\d+)\.(\d+)\.(\d+)$') {
        $majorMinor = "$($matches[1]).$($matches[2])"
    }

    $nextPatch = 1

    if (Test-Path $VersionFilePath) {
        try {
            $existing = Get-Content $VersionFilePath -Raw | ConvertFrom-Json
            $existingVersion = [string]$existing.version
            if ($existingVersion -match '^\d+\.\d+\.(\d{1,2})$') {
                $nextPatch = [int]$matches[1] + 1
            }
        } catch {
            $nextPatch = 1
        }
    }

    if ($nextPatch -gt 99) {
        $nextPatch = 99
    }

    return "$majorMinor.$($nextPatch.ToString('00'))"
}

# Cores
function Write-Step { param($msg) Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Write-Success { param($msg) Write-Host "V $msg" -ForegroundColor Green }
function Write-Error { param($msg) Write-Host "X $msg" -ForegroundColor Red }
function Write-Warning { param($msg) Write-Host "! $msg" -ForegroundColor Yellow }
function Get-DotEnvValue {
    param(
        [string]$Key
    )

    if (-not (Test-Path ".env")) {
        return $null
    }

    $match = Select-String -Path ".env" -Pattern "^$([regex]::Escape($Key))=(.*)$" | Select-Object -First 1
    if (-not $match) {
        return $null
    }

    return $match.Matches[0].Groups[1].Value.Trim().Trim('"')
}

# Configuracoes SSH
$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "DimidricaGata09'@"
$COMPOSER_PATH = "/usr/local/bin/composer"
$APP_URL = Get-DotEnvValue -Key "APP_URL"
if ([string]::IsNullOrWhiteSpace($APP_URL)) {
    $APP_URL = "https://exclusivalarimoveis.com"
}

$configuredDeployPath = Get-DotEnvValue -Key "DEPLOY_PATH"
if ([string]::IsNullOrWhiteSpace($configuredDeployPath)) {
    # Domínio principal está apontado como alias para este document root na hospedagem
    $DEPLOY_PATH = "/home/$SSH_USER/domains/lojadaesquina.store/public_html"
} else {
    $DEPLOY_PATH = $configuredDeployPath
}

if ([string]::IsNullOrWhiteSpace($HealthUrl)) {
    $HEALTH_URL = "$($APP_URL.TrimEnd('/'))/api/health"
} else {
    $HEALTH_URL = $HealthUrl.Trim()
}

try {
    Write-Host "`n" -NoNewline
    Write-Host "===============================================================" -ForegroundColor Cyan
    Write-Host "          DEPLOY AUTOMATICO - SOCIMOB v2                       " -ForegroundColor Cyan
    Write-Host "===============================================================" -ForegroundColor Cyan
    Write-Host ""

    if (-not $RemoteFailureSelfTest) {
        # 0. VERSIONAMENTO DE DEPLOY
        Write-Step "ATUALIZAR METADADOS DE VERSAO"
        $versionFilePath = "storage/app/deploy-version.json"
        $nextVersion = Get-NextDeployVersion -VersionFilePath $versionFilePath -FallbackBaseVersion (Get-DotEnvValue -Key "APP_VERSION")
        $deployedAt = Get-Date
        $deployedAtDisplay = $deployedAt.ToString("dd/MM/yyyy HH:mm")
        $deployedAtIso = $deployedAt.ToString("yyyy-MM-ddTHH:mm:ssK")
        $deploySummaryEscaped = $CommitMessage.Replace('"', '\"')
        $deployedByEscaped = $env:USERNAME.Replace('"', '\"')

        $versionPayload = @{
            app = "SOCIMOB"
            version = $nextVersion
            deployed_at = $deployedAtDisplay
            deployed_at_iso = $deployedAtIso
            deploy_summary = $CommitMessage
            deployed_by = $env:USERNAME
        } | ConvertTo-Json -Depth 4

        Set-Content -Path $versionFilePath -Value $versionPayload -Encoding UTF8
        Write-Success "Versao de deploy atualizada para $nextVersion ($deployedAtDisplay)"

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
            Copy-Item -Path public/.htaccess -Destination .htaccess -Force
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
    } else {
        Write-Step "AUTOTESTE DE FALHA REMOTA"
        Write-Warning "Modo de autoteste ativado: sem build, sem commit e sem push."
    }

    # 4. DEPLOY NO SERVIDOR SSH
    Write-Step "DEPLOY NO SERVIDOR SSH"
    Write-Host "Servidor: $SSH_USER@$SSH_HOST`:$SSH_PORT" -ForegroundColor Yellow
    Write-Host "Caminho: $DEPLOY_PATH" -ForegroundColor Yellow
    Write-Host ""

    if ($RemoteFailureSelfTest) {
        $deployCommands = @"
bash -lc '
set -euo pipefail
printf "%s\n" "=== TESTE CONTROLADO DE FALHA REMOTA ==="
false
'
"@
    } else {
        $deployCommands = @"
bash -lc '
set -euo pipefail
cd "$DEPLOY_PATH"

printf "%s\n" "=== PREPARAR WORKTREE ==="
rm -f .htaccess index.html favicon.ico manifest.webmanifest sw.js workbox-*.js
rm -rf assets __manus__

printf "\n"
printf "%s\n" "=== SINCRONIZAR REPOSITORIO ==="
git fetch origin master
git reset --hard origin/master

printf "\n"
printf "%s\n" "=== COPIAR BUILD ==="
cp -rf dist/public/* ./
cp public/.htaccess ./.htaccess

printf "\n"
printf "%s\n" "=== ATUALIZAR METADADOS DE VERSAO ==="
mkdir -p storage/app
cat > storage/app/deploy-version.json <<EOF
{
  "app": "SOCIMOB",
  "version": "$nextVersion",
  "deployed_at": "$deployedAtDisplay",
  "deployed_at_iso": "$deployedAtIso",
  "deploy_summary": "$deploySummaryEscaped",
  "deployed_by": "$deployedByEscaped"
}
EOF

printf "\n"
printf "%s\n" "=== COMPOSER INSTALL ==="
/opt/alt/php83/usr/bin/php "$COMPOSER_PATH" install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

printf "\n"
printf "%s\n" "=== MIGRATIONS ==="
/opt/alt/php83/usr/bin/php artisan migrate --force

printf "\n"
printf "%s\n" "=== VALIDAR SCHEMA IMOBI BRASIL ==="
/opt/alt/php83/usr/bin/php artisan migrate:status | grep "2026_03_18_180000_ensure_imobi_brasil_images_sent_at_on_imo_properties" | grep "Ran"

printf "\n"
printf "%s\n" "=== VERIFICAR BUILD ==="
grep "index-" index.html
test -f .htaccess

printf "%s\n" "=== DEPLOY CONCLUIDO ==="
date
'
"@
    }

    plink -P $SSH_PORT -pw $SSH_PASS -batch $SSH_USER@$SSH_HOST $deployCommands
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Deploy SSH concluido com sucesso"
    } else {
        throw "Deploy SSH falhou (codigo: $LASTEXITCODE)"
    }

    # 5. VERIFICAR SITE
    if ($RemoteFailureSelfTest) {
        throw "Autoteste concluido: a falha remota foi propagada corretamente."
    }

    Write-Step "VERIFICAR SITE ONLINE"
    try {
        $response = Invoke-WebRequest -Uri $HEALTH_URL -TimeoutSec 10 -UseBasicParsing
        if ($response.StatusCode -ne 200) {
            throw "Health check retornou HTTP $($response.StatusCode)"
        }

        $contentType = [string]$response.Headers['Content-Type']
        if ($contentType -notmatch 'application/json') {
            throw "Health check retornou Content-Type invalido: $contentType"
        }

        $health = $response.Content | ConvertFrom-Json
        if (-not $health.status) {
            throw "Health check retornou JSON sem o campo status"
        }

        if ($health.status -ne 'online') {
            throw "Health check retornou status inesperado: $($health.status)"
        }

        Write-Success "API Health: $($health.status)"
        Write-Host "  App: $($health.app)" -ForegroundColor Gray
        Write-Host "  Version: $($health.version)" -ForegroundColor Gray
    } catch {
        throw "Falha na verificacao final de health: $_"
    }

    # SUCESSO
    Write-Host ""
    Write-Host "===============================================================" -ForegroundColor Green
    Write-Host "              DEPLOY CONCLUIDO COM SUCESSO!                    " -ForegroundColor Green
    Write-Host "===============================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Frontend: $APP_URL" -ForegroundColor Cyan
    Write-Host "API:      $HEALTH_URL" -ForegroundColor Cyan
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
