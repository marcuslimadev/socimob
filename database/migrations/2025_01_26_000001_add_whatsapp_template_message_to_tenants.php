<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('whatsapp_template_message')->nullable()->after('api_key_openai')
                ->comment('Texto do template aprovado no WhatsApp/Meta com placeholders {{1}}, {{2}}, etc.');
        });
        
        // Atualizar o tenant 1 com o template padrão
        DB::table('tenants')->where('id', 1)->update([
            'whatsapp_template_message' => 'Olá {{1}}, tudo bem? Vi que você tem interesse em {{2}}. Sou a Teresa, assistente virtual da Exclusiva Imóveis. Como posso ajudar você a encontrar o imóvel ideal?'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('whatsapp_template_message');
        });
    }
};
