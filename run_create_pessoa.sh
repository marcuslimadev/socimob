#!/bin/bash

echo "=== CRIAR PESSOA DO ROBERTO JR ==="
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
/opt/alt/php83/usr/bin/php create_pessoa_roberto.php
echo "=== CONCLUÍDO ==="
