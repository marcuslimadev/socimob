#!/bin/bash
# Executar no servidor para corrigir imagens antigas

cd ~/domains/lojadaesquina.store/public_html

echo "=== ATUALIZANDO CÓDIGO ==="
git pull origin master

echo ""
echo "=== EXECUTANDO CORREÇÃO ==="
php fix_twilio_images.php

echo ""
echo "✅ Pronto! Recarregue o chat."
