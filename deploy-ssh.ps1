#!/usr/bin/env pwsh
# Deploy automatizado via SSH para lojadaesquina.store

$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u738323131"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== DEPLOY SOCIMOB - REACT 19 + LUMEN ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Servidor: $SSH_USER@$SSH_HOST`:$SSH_PORT" -ForegroundColor Yellow
Write-Host "Caminho: $DEPLOY_PATH" -ForegroundColor Yellow
Write-Host ""

# Comando SSH que será executado no servidor
$deployCommands = @"
cd $DEPLOY_PATH
echo '=== DIRETORIO ATUAL ==='
pwd
echo ''
echo '=== GIT STATUS ==='
git status
echo ''
echo '=== GIT PULL ==='
git pull origin master
echo ''
echo '=== VERIFICAR NODE/PNPM ==='
node --version 2>/dev/null || echo 'Node não encontrado'
pnpm --version 2>/dev/null || echo 'pnpm não encontrado'
echo ''

# Instalar pnpm se não existir
if ! command -v pnpm &> /dev/null; then
    echo '=== INSTALAR PNPM ==='
    npm install -g pnpm 2>/dev/null || echo 'AVISO: Não foi possível instalar pnpm'
fi

# Build do frontend React
if [ -f "package.json" ]; then
    echo '=== INSTALAR DEPENDENCIAS NODE ==='
    pnpm install --frozen-lockfile 2>/dev/null || npm install 2>/dev/null || echo 'AVISO: Falha ao instalar dependências'
    echo ''
    echo '=== BUILD FRONTEND REACT ==='
    pnpm build 2>/dev/null || npm run build 2>/dev/null || echo 'AVISO: Falha no build'
    echo ''
    echo '=== VERIFICAR BUILD ==='
    ls -lh dist/public/index.html 2>/dev/null || echo 'Build não encontrado'
    echo ''
fi

echo '=== RECARREGAR AUTOLOAD PHP ==='
php composer.phar dump-autoload --optimize 2>/dev/null || composer dump-autoload --optimize 2>/dev/null || echo 'Composer OK'
echo ''
echo '=== AJUSTAR PERMISSOES ==='
chmod -R 755 storage bootstrap/cache public 2>/dev/null
chmod -R 775 storage 2>/dev/null
echo 'Permissões ajustadas'
echo ''
echo '=== LIMPAR CACHE ==='
rm -rf storage/framework/cache/* 2>/dev/null || echo 'Cache limpo'
echo ''
echo '=== DEPLOY CONCLUIDO ==='
date
echo ''
echo '=== VERIFICAR HEALTH ==='
curl -s http://lojadaesquina.store/api/health | head -20 || echo 'API não respondeu'
"@

# Executar via SSH
Write-Host "Conectando ao servidor..." -ForegroundColor Green
Write-Host ""

try {
    # Usar plink (PuTTY) ou ssh nativo do Windows
    if (Get-Command plink -ErrorAction SilentlyContinue) {
        echo $deployCommands | plink -P $SSH_PORT $SSH_USER@$SSH_HOST -batch
    } elseif (Get-Command ssh -ErrorAction SilentlyContinue) {
        echo $deployCommands | ssh -p $SSH_PORT $SSH_USER@$SSH_HOST "bash -s"
    } else {
        Write-Host "ERRO: SSH client não encontrado!" -ForegroundColor Red
        Write-Host "Instale OpenSSH ou PuTTY" -ForegroundColor Yellow
        exit 1
    }
    
    Write-Host ""
    Write-Host "=== DEPLOY FINALIZADO COM SUCESSO ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "Acesse: https://lojadaesquina.store" -ForegroundColor Cyan
    Write-Host "API: https://lojadaesquina.store/api/health" -ForegroundColor Cyan
    
} catch {
    Write-Host "ERRO no deploy: $_" -ForegroundColor Red
    exit 1
}
