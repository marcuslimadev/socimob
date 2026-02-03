#!/bin/bash
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
echo "=== VERIFICANDO ARQUIVO ATUALIZADO ==="
grep -A 2 "last_sync" app/Services/PropertySyncService.php
