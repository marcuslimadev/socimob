<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->withFacades();
$app->withEloquent();

use Illuminate\Support\Facades\DB;

echo "🔧 Alterando tipo das colunas Twilio para TEXT...\n\n";

try {
    DB::statement("ALTER TABLE tenant_configs MODIFY twilio_account_sid TEXT NULL");
    echo "✓ twilio_account_sid alterado para TEXT\n";
    
    DB::statement("ALTER TABLE tenant_configs MODIFY twilio_auth_token TEXT NULL");
    echo "✓ twilio_auth_token alterado para TEXT\n";
    
    echo "\n✅ Colunas alteradas com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
