#!/bin/bash

# Script de Deploy - Integração Chaves na Mão
# Execute no servidor de produção

cd ~/domains/lojadaesquina.store/public_html

echo "📦 Fazendo pull das alterações..."
git pull origin master

echo "🗄️ Executando migrations..."
/opt/alt/php83/usr/bin/php artisan migrate --force

echo "🧹 Limpando cache..."
/opt/alt/php83/usr/bin/php artisan cache:clear 2>/dev/null || true

echo "🔄 Limpando OPcache..."
curl -s "https://lojadaesquina.store/opcache_clear.php" > /dev/null

echo "✅ Verificando status da integração..."
/opt/alt/php83/usr/bin/php artisan chaves:sync status

echo ""
echo "🎉 Deploy concluído!"
echo ""
echo "Para testar a integração:"
echo "  php artisan chaves:sync test"
echo ""
echo "Ou via HTTP:"
echo "  curl -X POST https://lojadaesquina.store/api/admin/chaves-na-mao/test \\"
echo "       -H 'Authorization: Bearer SEU_TOKEN'"
