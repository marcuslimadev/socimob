#!/bin/bash
cd ~/domains/lojadaesquina.store/public_html
echo "=== DIRETORIO ATUAL ==="
pwd
echo ""
echo "=== GIT STATUS ==="
git status
echo ""
echo "=== GIT PULL ==="
git pull origin master
echo ""
echo "=== LOCALIZAR COMPOSER ==="
which composer || command -v composer || echo "Composer nao encontrado no PATH"
echo ""
echo "=== RECARREGAR AUTOLOAD ==="
php composer.phar dump-autoload --optimize 2>/dev/null || composer dump-autoload --optimize 2>/dev/null || echo "Composer nao disponivel"
echo ""
echo "=== AJUSTAR PERMISSOES ==="
chmod -R 755 storage bootstrap/cache public 2>/dev/null || echo "Permissoes ajustadas conforme possivel"
echo ""
echo "=== DEPLOY CONCLUIDO ==="
date
