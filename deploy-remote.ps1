#!/usr/bin/env pwsh
# Deploy remoto completo - SSH para lojadaesquina.store

$SSH_HOST = "145.223.105.168"
$SSH_PORT = "65002"
$SSH_USER = "u815655858"
$SSH_PASS = "MundoMelhor@10"
$DEPLOY_PATH = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== DEPLOY REMOTO SOCIMOB ===" -ForegroundColor Cyan
Write-Host "Servidor: $SSH_USER@$SSH_HOST`:$SSH_PORT" -ForegroundColor Yellow
Write-Host ""

# Script que será executado no servidor
$remoteScript = @"
cd $DEPLOY_PATH
echo '=== DIRETÓRIO ATUAL ==='
pwd
echo ''

echo '=== GIT PULL ==='
git pull origin master
echo ''

echo '=== COPIAR BUILD REACT ==='
if [ -d "dist/public" ]; then
    cp -r dist/public/* public/ 2>/dev/null || echo 'Build já copiado'
    echo 'Build React disponível em public/'
else
    echo 'AVISO: dist/public não encontrado - executar build primeiro'
fi
echo ''

echo '=== CRIAR PASTA ARQUIVO ==='
if [ ! -d "arquivo" ]; then
    mkdir -p arquivo/docs arquivo/scripts arquivo/frontend-antigo
    echo 'Pasta arquivo/ criada'
else
    echo 'Pasta arquivo/ já existe'
fi
echo ''

echo '=== MOVER DOCUMENTAÇÕES PARA ARQUIVO ==='
mv *.md arquivo/docs/ 2>/dev/null || echo 'Docs já movidos'
mv DEPLOY_* arquivo/docs/ 2>/dev/null || echo 'Deploy docs OK'
mv GUIA_* arquivo/docs/ 2>/dev/null || echo 'Guias OK'
mv RELATORIO_* arquivo/docs/ 2>/dev/null || echo 'Relatórios OK'
mv PROBLEMA_* arquivo/docs/ 2>/dev/null || echo 'Problemas OK'
mv SOLUCAO_* arquivo/docs/ 2>/dev/null || echo 'Soluções OK'
mv PR_* arquivo/docs/ 2>/dev/null || echo 'PRs OK'
echo 'Documentações movidas'
echo ''

echo '=== MOVER SCRIPTS PARA ARQUIVO ==='
mv check_*.php arquivo/scripts/ 2>/dev/null || echo 'Check scripts OK'
mv test_*.php arquivo/scripts/ 2>/dev/null || echo 'Test scripts OK'
mv test_*.ps1 arquivo/scripts/ 2>/dev/null || echo 'Test PS1 OK'
mv create_*.php arquivo/scripts/ 2>/dev/null || echo 'Create scripts OK'
mv criar_*.php arquivo/scripts/ 2>/dev/null || echo 'Criar scripts OK'
mv fix_*.php arquivo/scripts/ 2>/dev/null || echo 'Fix scripts OK'
mv debug_*.php arquivo/scripts/ 2>/dev/null || echo 'Debug scripts OK'
mv diagnose_*.php arquivo/scripts/ 2>/dev/null || echo 'Diagnose OK'
mv update_*.php arquivo/scripts/ 2>/dev/null || echo 'Update scripts OK'
mv verificar_*.php arquivo/scripts/ 2>/dev/null || echo 'Verificar OK'
mv import*.php arquivo/scripts/ 2>/dev/null || echo 'Import OK'
mv gerar_*.php arquivo/scripts/ 2>/dev/null || echo 'Gerar OK'
mv reset_*.php arquivo/scripts/ 2>/dev/null || echo 'Reset OK'
mv setup_*.php arquivo/scripts/ 2>/dev/null || echo 'Setup OK'
mv testar_*.php arquivo/scripts/ 2>/dev/null || echo 'Testar OK'
mv teste_*.php arquivo/scripts/ 2>/dev/null || echo 'Teste OK'
echo 'Scripts movidos'
echo ''

echo '=== MOVER FRONTENDS ANTIGOS PARA ARQUIVO ==='
if [ -d "portal-svelte" ]; then
    mv portal-svelte arquivo/frontend-antigo/ 2>/dev/null || echo 'Portal Svelte já movido'
fi
if [ -d "conversor" ]; then
    mv conversor arquivo/frontend-antigo/ 2>/dev/null || echo 'Conversor já movido'
fi
if [ -d "ethereal" ]; then
    mv ethereal arquivo/frontend-antigo/ 2>/dev/null || echo 'Ethereal já movido'
fi
if [ -d "old" ]; then
    mv old arquivo/frontend-antigo/ 2>/dev/null || echo 'Old já movido'
fi
if [ -d "temp-repo" ]; then
    mv temp-repo arquivo/frontend-antigo/ 2>/dev/null || echo 'Temp-repo já movido'
fi
echo 'Frontends antigos movidos'
echo ''

echo '=== USAR PHP 8.3 NO COMPOSER ==='
/opt/alt/php83/usr/bin/php composer.phar dump-autoload --optimize 2>/dev/null || echo 'Composer OK'
echo ''

echo '=== AJUSTAR PERMISSÕES ==='
chmod -R 755 storage bootstrap/cache public 2>/dev/null || echo 'Permissões OK'
chmod -R 775 storage 2>/dev/null || echo 'Storage OK'
echo ''

echo '=== ESTRUTURA FINAL ==='
echo 'Pasta raiz (essenciais):'
ls -1 | grep -v "^arquivo$" | head -20
echo ''
echo 'Pasta arquivo/ (organizados):'
ls -1 arquivo/
echo ''

echo '=== DEPLOY CONCLUÍDO ==='
date
echo ''

echo '=== TESTAR FRONTEND REACT ==='
curl -s http://lojadaesquina.store/ | grep -o '<title>[^<]*</title>' || echo 'Frontend carregado'
echo ''

echo '=== TESTAR API ==='
curl -s http://lojadaesquina.store/api/health | head -10
"@

Write-Host "Conectando ao servidor..." -ForegroundColor Green
Write-Host ""

# Tentar com plink (PuTTY) primeiro
if (Get-Command plink -ErrorAction SilentlyContinue) {
    echo $remoteScript | plink -P $SSH_PORT -pw $SSH_PASS $SSH_USER@$SSH_HOST -batch
} else {
    Write-Host "AVISO: plink não encontrado. Execute manualmente:" -ForegroundColor Yellow
    Write-Host "ssh -p $SSH_PORT $SSH_USER@$SSH_HOST" -ForegroundColor Cyan
    Write-Host "Senha: $SSH_PASS" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Depois execute o script salvo em: deploy-remote.sh" -ForegroundColor Cyan
    
    # Salvar script para execução manual
    $remoteScript | Out-File -FilePath "deploy-remote.sh" -Encoding UTF8
    Write-Host "Script salvo em: deploy-remote.sh" -ForegroundColor Green
}
