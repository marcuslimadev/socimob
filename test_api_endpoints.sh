#!/bin/bash

echo "=== Testando endpoint GET /api/imoveis ==="
echo ""

# Testar endpoint sem autenticação (deve dar erro)
echo "1. Testando sem autenticação (esperado: erro 401):"
curl -s "https://exclusivalarimoveis.com/api/imoveis?per_page=5" | jq '.error' || echo "Sem erro de autenticação"
echo ""

# Precisamos de um token válido para testar
# Por enquanto, vamos testar se a rota existe
echo "2. Testando se a rota responde (404 vs 401/403):"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://exclusivalarimoveis.com/api/imoveis?per_page=5")
echo "HTTP Status: $STATUS"

if [ "$STATUS" = "404" ]; then
    echo "ERRO: Rota não encontrada (404)"
elif [ "$STATUS" = "401" ] || [ "$STATUS" = "403" ]; then
    echo "OK: Rota existe, precisa autenticação"
else
    echo "Status inesperado: $STATUS"
fi

echo ""
echo "=== Testando endpoint /api/notifications/unread-count ==="
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://exclusivalarimoveis.com/api/notifications/unread-count")
echo "HTTP Status: $STATUS"

if [ "$STATUS" = "404" ]; then
    echo "ERRO: Rota não encontrada (404)"
elif [ "$STATUS" = "401" ] || [ "$STATUS" = "403" ]; then
    echo "OK: Rota existe, precisa autenticação"
else
    echo "Status inesperado: $STATUS"
fi
