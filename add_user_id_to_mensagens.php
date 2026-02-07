<?php

require __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    echo "=== Verificando coluna user_id na tabela mensagens ===\n";
    
    if (!Schema::hasColumn('mensagens', 'user_id')) {
        echo "Coluna user_id não existe. Criando...\n";
        
        Schema::table('mensagens', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('conversa_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('user_id');
        });
        
        echo "✅ Coluna user_id criada com sucesso!\n";
    } else {
        echo "✅ Coluna user_id já existe.\n";
    }
    
    // Verificar se há índice
    $indexes = DB::select("SHOW INDEXES FROM mensagens WHERE Column_name = 'user_id'");
    if (empty($indexes)) {
        echo "Adicionando índice em user_id...\n";
        DB::statement('ALTER TABLE mensagens ADD INDEX idx_user_id (user_id)');
        echo "✅ Índice adicionado!\n";
    }
    
    echo "\n=== Concluído ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
