#!/bin/bash

echo "=== EXECUTANDO MIGRAÇÃO E SINCRONIZAÇÃO ==="
echo ""

echo "1. Executando migração..."
php artisan migrate --force

echo ""
echo "2. Sincronizando configurações da Exclusiva..."
php sync_exclusiva_configs.php

echo ""
echo "=== CONCLUÍDO ==="
