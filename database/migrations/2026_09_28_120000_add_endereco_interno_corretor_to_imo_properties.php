<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnderecoInternoCorretorToImoProperties extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            // Endereço real do imóvel para uso interno dos corretores (uso interno, não exibir ao portal/anúncio)
            $table->text('endereco_interno_corretor')->nullable()->after('proprietario_observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropColumn('endereco_interno_corretor');
        });
    }
}
