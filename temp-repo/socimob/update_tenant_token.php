<?php

require_once 'bootstrap/app.php';

$db = app('db');
$token = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';

echo "🔄 Atualizando token do tenant...\n";

$result = $db->table('tenants')->where('id', 1)->update([
    'api_key_apm_imoveis' => $token,
    'updated_at' => date('Y-m-d H:i:s')
]);

echo "✅ Token atualizado: $result registro(s)\n\n";

$tenant = $db->table('tenants')->where('id', 1)->first();
echo "📋 Dados do tenant:\n";
echo "ID: {$tenant->id}\n";
echo "Nome: {$tenant->name}\n";
echo "Token: " . substr($tenant->api_key_apm_imoveis, 0, 40) . "...\n";
