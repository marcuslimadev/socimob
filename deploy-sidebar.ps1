# Deploy Sidebar via Git Push/Pull - SOCIMOB

param(
    [string]$HostName = "145.223.105.168",
    [int]$Port = 65002,
    [string]$User = "u815655858",
    [string]$RemotePath = "domains/lojadaesquina.store/public_html",
    [string]$CommitMessage = "Deploy: Sistema de Sidebar implementado"
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  DEPLOY SIDEBAR VIA GIT - SOCIMOB" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Verificar git
if (-not (Get-Command git.exe -ErrorAction SilentlyContinue)) {
    Write-Host "[ERRO] git.exe nao encontrado!" -ForegroundColor Red
    exit 1
}

# Verificar plink
if (-not (Get-Command plink.exe -ErrorAction SilentlyContinue)) {
    Write-Host "[ERRO] plink.exe nao encontrado!" -ForegroundColor Red
    exit 1
}

# Arquivos da sidebar
$files = @(
    "public/app/sidebar.css",
    "public/app/sidebar.js",
    "public/app/SIDEBAR_README.md",
    "public/app/SIDEBAR_DOCS.md",
    "public/app/_template-sidebar.html",
    "public/app/demo-sidebar.html",
    "public/app/dashboard.html",
    "public/app/leads.html",
    "public/app/conversas.html",
    "public/app/configuracoes.html"
)

Write-Host "Arquivos da sidebar:" -ForegroundColor White
foreach ($file in $files) {
    if (Test-Path $file) {
        Write-Host "  [OK] $file" -ForegroundColor Green
    }
}
Write-Host ""

# Senha
$plainPassword = "MundoMelhor@10"

# ETAPA 1: GIT ADD E COMMIT
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  ETAPA 1: GIT ADD E COMMIT" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

try {
    Write-Host "Adicionando arquivos ao git..." -ForegroundColor White
    foreach ($file in $files) {
        if (Test-Path $file) {
            git add $file 2>&1 | Out-Null
        }
    }
    Write-Host "  [OK] Arquivos adicionados" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "Fazendo commit..." -ForegroundColor White
    git commit -m $CommitMessage 2>&1 | Out-Null
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  [OK] Commit realizado" -ForegroundColor Green
    }
    else {
        Write-Host "  [INFO] Nenhuma mudanca para commit" -ForegroundColor Yellow
    }
}
catch {
    Write-Host "  [INFO] $($_.Exception.Message)" -ForegroundColor Yellow
}

# ETAPA 2: GIT PUSH
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  ETAPA 2: GIT PUSH" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

try {
    Write-Host "Enviando para repositorio remoto..." -ForegroundColor White
    $pushOutput = git push 2>&1
    
    if ($LASTEXITCODE -eq 0 -or $pushOutput -match "Everything up-to-date") {
        Write-Host "  [OK] Repositorio atualizado" -ForegroundColor Green
    }
    else {
        Write-Host $pushOutput -ForegroundColor Gray
        Write-Host "  [ERRO] Falha no push" -ForegroundColor Red
        exit 1
    }
}
catch {
    Write-Host "  [ERRO] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# ETAPA 3: GIT PULL NO SERVIDOR
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  ETAPA 3: GIT PULL NO SERVIDOR" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

$cmd1 = "cd $RemotePath"
$cmd2 = "git pull origin main"
$cmd3 = "export PHP_BIN=/opt/alt/php83/usr/bin/php"
$cmd4 = '$PHP_BIN artisan config:clear'
$cmd5 = '$PHP_BIN artisan cache:clear'
$cmd6 = '$PHP_BIN artisan view:clear'
$cmd7 = "echo '[OK] Deploy concluido'"

$sshCommand = "$cmd1 ; $cmd2 ; $cmd3 ; $cmd4 ; $cmd5 ; $cmd6 ; $cmd7"

try {
    Write-Host "Conectando ao servidor via SSH..." -ForegroundColor White
    Write-Host "  Host: $HostName" -ForegroundColor Gray
    Write-Host "  User: $User" -ForegroundColor Gray
    Write-Host ""
    
    $plinkArgs = @(
        "-P", $Port,
        "-pw", $plainPassword,
        "-batch",
        "${User}@${HostName}",
        $sshCommand
    )
    
    Write-Host "Executando git pull e limpeza de cache..." -ForegroundColor White
    $output = & plink.exe $plinkArgs 2>&1
    Write-Host $output -ForegroundColor Gray
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "  [OK] Pull e limpeza de cache executados" -ForegroundColor Green
    }
    else {
        Write-Host ""
        Write-Host "  [ERRO] Falha ao executar comandos (codigo $LASTEXITCODE)" -ForegroundColor Red
        exit 1
    }
}
catch {
    Write-Host "  [ERRO] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# ETAPA 4: LIMPAR OPCACHE
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  ETAPA 4: LIMPAR OPCACHE" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

try {
    Write-Host "Limpando OPcache via HTTP..." -ForegroundColor White
    $opcacheUrl = "https://lojadaesquina.store/opcache_clear.php"
    $response = Invoke-WebRequest -Uri $opcacheUrl -UseBasicParsing -TimeoutSec 10
    
    if ($response.StatusCode -eq 200) {
        Write-Host "  [OK] OPcache limpo" -ForegroundColor Green
    }
}
catch {
    Write-Host "  [AVISO] Nao foi possivel limpar OPcache" -ForegroundColor Yellow
}

# RESUMO
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  DEPLOY CONCLUIDO" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Acesse: https://lojadaesquina.store/app/demo-sidebar.html" -ForegroundColor Cyan
Write-Host ""
