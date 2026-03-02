<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProprietarioInseridoToImoProperties extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            // Quem inseriu o imóvel (uso interno, não exibir ao cliente/portal)
            $table->unsignedBigInteger('inserido_por_user_id')->nullable()->after('user_id');
            $table->string('inserido_por_nome', 150)->nullable()->after('inserido_por_user_id');

            // Dados do proprietário (uso interno, não exibir ao cliente/portal)
            $table->string('proprietario_nome', 150)->nullable()->after('inserido_por_nome');
            $table->string('proprietario_telefone', 30)->nullable()->after('proprietario_nome');
            $table->string('proprietario_email', 150)->nullable()->after('proprietario_telefone');
            $table->text('proprietario_observacoes')->nullable()->after('proprietario_email');
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropColumn([
                'inserido_por_user_id',
                'inserido_por_nome',
                'proprietario_nome',
                'proprietario_telefone',
                'proprietario_email',
                'proprietario_observacoes',
            ]);
        });
    }
}
