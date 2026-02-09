#!/bin/bash
echo "=== SINCRONIZAR LEADS COM PESSOAS ==="
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
/opt/alt/php83/usr/bin/php artisan leads:sync-pessoas
echo "=== CONCLUÍDO ==="
