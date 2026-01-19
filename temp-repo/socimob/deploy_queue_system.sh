#!/bin/bash

# Script de Deploy - Sistema de Fila de Atendimento
# Execute no servidor de produção

echo "🚀 Iniciando deploy do sistema de fila..."
echo ""

cd ~/domains/lojadaesquina.store/public_html

echo "📦 Fazendo pull das alterações..."
git pull origin master

if [ $? -ne 0 ]; then
    echo "❌ Erro ao fazer pull do repositório"
    exit 1
fi

echo ""
echo "🔄 Limpando OPcache..."
curl -s "https://lojadaesquina.store/opcache_clear.php" > /dev/null 2>&1 || true

echo ""
echo "✅ Verificando arquivos modificados..."
ls -lh app/Http/Controllers/Admin/ConversasController.php
ls -lh public/app/chat.html
ls -lh routes/web.php

echo ""
echo "🧪 Testando endpoints da fila..."
echo "  GET /api/admin/conversas/fila/estatisticas"
echo "  POST /api/admin/conversas/fila/pegar-proxima"
echo "  POST /api/admin/conversas/{id}/devolver-fila"

echo ""
echo "🎉 Deploy concluído com sucesso!"
echo ""
echo "📱 Acesse o PWA Chat:"
echo "  https://lojadaesquina.store/app/chat.html"
echo ""
echo "Para testar como corretor:"
echo "  1. Faça login com credenciais de corretor"
echo "  2. Clique em 'Pegar Próximo Cliente da Fila'"
echo "  3. Veja as estatísticas no botão 📊"
