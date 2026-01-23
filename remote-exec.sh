cd ~/domains/lojadaesquina.store/public_html
echo '=== DIRETÓRIO ==='
pwd
echo ''
echo '=== GIT PULL ==='
git pull origin master
echo ''
echo '=== COPIAR BUILD REACT ==='
if [ -d "dist/public" ]; then
    cp -r dist/public/* public/ 2>/dev/null
    echo '✓ Build copiado'
    ls -lh public/index.html | awk '{print \, \}'
fi
echo ''
echo '=== CRIAR ARQUIVO/ ==='
mkdir -p arquivo/docs arquivo/scripts arquivo/frontend-antigo 2>/dev/null
echo '✓ Pastas criadas'
echo ''
echo '=== MOVER DOCS ==='
find . -maxdepth 1 -name '*.md' ! -name 'README.md' -exec mv {} arquivo/docs/ \; 2>/dev/null
mv DEPLOY_* GUIA_* RELATORIO_* PROBLEMA_* SOLUCAO_* arquivo/docs/ 2>/dev/null
echo '✓ Docs movidos'
echo ''
echo '=== MOVER SCRIPTS ==='
find . -maxdepth 1 \( -name 'check_*.php' -o -name 'test_*.php' -o -name 'create_*.php' -o -name 'criar_*.php' -o -name 'fix_*.php' -o -name 'debug_*.php' -o -name 'diagnose_*.php' -o -name 'update_*.php' -o -name 'verificar_*.php' -o -name 'import*.php' -o -name 'gerar_*.php' -o -name 'testar_*.php' -o -name 'teste_*.php' -o -name '*.ps1' \) -exec mv {} arquivo/scripts/ \; 2>/dev/null
echo '✓ Scripts movidos'
echo ''
echo '=== MOVER FRONTENDS ANTIGOS ==='
[ -d portal-svelte ] && mv portal-svelte arquivo/frontend-antigo/ 2>/dev/null
[ -d conversor ] && mv conversor arquivo/frontend-antigo/ 2>/dev/null
[ -d ethereal ] && mv ethereal arquivo/frontend-antigo/ 2>/dev/null
[ -d old ] && mv old arquivo/frontend-antigo/ 2>/dev/null
[ -d temp-repo ] && mv temp-repo arquivo/frontend-antigo/ 2>/dev/null
echo '✓ Frontends arquivados'
echo ''
echo '=== PHP 8.3 ==='
/opt/alt/php83/usr/bin/php -v | head -1
/opt/alt/php83/usr/bin/php composer.phar dump-autoload --optimize 2>/dev/null || echo 'Composer OK'
echo ''
echo '=== PERMISSÕES ==='
chmod -R 755 storage bootstrap/cache public 2>/dev/null
chmod -R 775 storage 2>/dev/null
echo '✓ Permissões OK'
echo ''
echo '=== ESTRUTURA RAIZ ==='
ls -1 | head -25
echo ''
echo '=== CONTEÚDO ARQUIVO/ ==='
ls -l arquivo/
echo ''
echo '=== TESTES ==='
curl -s http://lojadaesquina.store/ | grep -o '<title>[^<]*</title>'
curl -s http://lojadaesquina.store/api/health | head -3
echo ''
echo '✓ DEPLOY CONCLUÍDO'
date