<?php

/**
 * Executar todos os seeders do sistema
 * Script principal para popular o banco com dados iniciais
 */

$seedersPath = __DIR__;

echo "🌱 Executando todos os seeders...\n";
echo "═══════════════════════════════════════\n\n";

try {
    // Lista de seeders para executar
    $seeders = [
        'ExclusivaSeeder.php'
    ];

    foreach ($seeders as $seeder) {
        $seederFile = $seedersPath . '/' . $seeder;
        
        if (file_exists($seederFile)) {
            echo "📦 Executando: {$seeder}\n";
            echo "────────────────────────────────────\n";
            
            // Capturar output do seeder
            ob_start();
            include $seederFile;
            $output = ob_get_clean();
            
            echo $output;
            echo "\n";
        } else {
            echo "⚠️  Seeder não encontrado: {$seeder}\n";
        }
    }

    echo "═══════════════════════════════════════\n";
    echo "✅ Todos os seeders executados com sucesso!\n\n";
    
    echo "🎯 PRÓXIMOS PASSOS:\n";
    echo "1. Iniciar servidor: php -S 127.0.0.1:8000 -t public\n";
    echo "2. Acessar: http://127.0.0.1:8000/app/\n";
    echo "3. Fazer login com as credenciais criadas\n\n";

} catch (Exception $e) {
    echo "❌ Erro ao executar seeders: " . $e->getMessage() . "\n";
    exit(1);
}