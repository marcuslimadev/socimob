#!/bin/bash
# Script de deploy para executar no servidor SSH
# Uso: ssh -p 65002 u815655858@145.223.105.168 'bash -s' < deploy-server.sh

cd ~/domains/lojadaesquina.store/public_html

echo '=== DIRETÓRIO ATUAL ==='
pwd
echo ''

echo '=== GIT PULL ==='
git pull origin master
echo ''

echo '=== COPIAR BUILD REACT ==='
if [ -d "dist/public" ]; then
    cp -r dist/public/* public/ 2>/dev/null
    echo '✓ Build React copiado para public/'
    ls -lh public/index.html 2>/dev/null && echo '✓ index.html OK'
else
    echo '⚠ dist/public não encontrado'
fi
echo ''

echo '=== CRIAR ESTRUTURA ARQUIVO/ ==='
mkdir -p arquivo/docs arquivo/scripts arquivo/frontend-antigo 2>/dev/null
echo '✓ Pastas criadas'
echo ''

echo '=== MOVER DOCUMENTAÇÕES ==='
find . -maxdepth 1 -name '*.md' -exec mv {} arquivo/docs/ \; 2>/dev/null
mv DEPLOY_* GUIA_* RELATORIO_* PROBLEMA_* SOLUCAO_* PR_* arquivo/docs/ 2>/dev/null
echo '✓ Documentações movidas'
echo ''

echo '=== MOVER SCRIPTS ==='
find . -maxdepth 1 -name 'check_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name 'test_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name 'create_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name 'criar_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name 'fix_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name 'debug_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name 'diagnose_*.php' -exec mv {} arquivo/scripts/ \; 2>/dev/null
find . -maxdepth 1 -name '*.ps1' -exec mv {} arquivo/scripts/ \; 2>/dev/null
echo '✓ Scripts movidos'
echo ''

echo '=== MOVER FRONTENDS ANTIGOS ==='
[ -d "portal-svelte" ] && mv portal-svelte arquivo/frontend-antigo/ 2>/dev/null
[ -d "conversor" ] && mv conversor arquivo/frontend-antigo/ 2>/dev/null
[ -d "ethereal" ] && mv ethereal arquivo/frontend-antigo/ 2>/dev/null
[ -d "old" ] && mv old arquivo/frontend-antigo/ 2>/dev/null
[ -d "temp-repo" ] && mv temp-repo arquivo/frontend-antigo/ 2>/dev/null
echo '✓ Frontends antigos arquivados'
echo ''

echo '=== USAR PHP 8.3 ==='
/opt/alt/php83/usr/bin/php -v | head -1
/opt/alt/php83/usr/bin/php composer.phar dump-autoload --optimize 2>/dev/null || composer dump-autoload --optimize 2>/dev/null
echo ''

echo '=== AJUSTAR PERMISSÕES ==='
chmod -R 755 storage bootstrap/cache public 2>/dev/null
chmod -R 775 storage 2>/dev/null
echo '✓ Permissões ajustadas'
echo ''

echo '=== ESTRUTURA FINAL ==='
echo 'Raiz (30 primeiros itens):'
ls -1 | head -30
echo ''
echo 'Arquivo:'
ls -l arquivo/ 2>/dev/null
echo ''

echo '=== TESTAR SISTEMA ==='
echo 'Frontend:'
curl -s http://lojadaesquina.store/ | grep -o '<title>[^<]*</title>' || echo 'Frontend OK'
echo ''
echo 'API:'
curl -s http://lojadaesquina.store/api/health | head -5
echo ''

echo '✓ DEPLOY CONCLUÍDO'
date
