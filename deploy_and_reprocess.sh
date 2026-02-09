#!/bin/bash

echo "=== DEPLOY E REPROCESSAMENTO ==="
cd ~/domains/lojadaesquina.store/public_html

echo "1. Git pull..."
git pull origin master

echo ""
echo "2. Commits recentes:"
git log --oneline -5

echo ""
echo "3. Reprocessando leads..."
/opt/alt/php83/usr/bin/php reprocess_chaves_leads.php

echo ""
echo "=== CONCLUÍDO ==="
