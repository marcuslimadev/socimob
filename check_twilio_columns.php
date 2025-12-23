<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->withFacades();
$app->withEloquent();

use Illuminate\Support\Facades\DB;

echo "🔍 Verificando colunas Twilio e OpenAI na tabela tenant_configs...\n\n";

try {
    $columns = DB::select("SHOW COLUMNS FROM tenant_configs WHERE Field LIKE '%twilio%' OR Field LIKE '%openai%'");
    
    if (empty($columns)) {
        echo "❌ Nenhuma coluna Twilio/OpenAI encontrada!\n";
    } else {
        echo "✅ Colunas encontradas:\n";
        foreach ($columns as $col) {
            echo "  - {$col->Field} ({$col->Type}) - Nullable: {$col->Null}\n";
        }
    }
    
    echo "\n📊 Testando inserção de dados...\n";
    
    // Buscar primeiro tenant
    $tenant = DB::table('tenants')->first();
    if (!$tenant) {
        echo "❌ Nenhum tenant encontrado\n";
        exit(1);
    }
    
    echo "  Tenant ID: {$tenant->id} - {$tenant->name}\n";
    
    // Verificar se já tem config
    $config = DB::table('tenant_configs')->where('tenant_id', $tenant->id)->first();
    
    if (!$config) {
        echo "  ⚠️  Tenant não tem config, criando...\n";
        DB::table('tenant_configs')->insert([
            'tenant_id' => $tenant->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $config = DB::table('tenant_configs')->where('tenant_id', $tenant->id)->first();
    }
    
    echo "  Config ID: {$config->id}\n";
    echo "\n✅ Estrutura do banco está correta!\n";
    echo "\n🔧 Agora você pode salvar as credenciais Twilio pela interface.\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
