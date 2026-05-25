<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vistoria_medidores')) {
            Schema::create('vistoria_medidores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vistoria_id');
                $table->string('tipo', 30);
                $table->string('leitura', 100);
                $table->string('unidade', 30)->nullable();
                $table->text('observacoes')->nullable();
                $table->timestamps();

                $table->index('vistoria_id');
                $table->index(['vistoria_id', 'tipo']);
            });
        }

        Schema::table('vistoria_midias', function (Blueprint $table) {
            if (!Schema::hasColumn('vistoria_midias', 'chave_id')) {
                $table->unsignedBigInteger('chave_id')->nullable()->after('inconformidade_id');
                $table->index('chave_id');
            }
            if (!Schema::hasColumn('vistoria_midias', 'medidor_id')) {
                $table->unsignedBigInteger('medidor_id')->nullable()->after('chave_id');
                $table->index('medidor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vistoria_midias', function (Blueprint $table) {
            if (Schema::hasColumn('vistoria_midias', 'medidor_id')) {
                $table->dropColumn('medidor_id');
            }
            if (Schema::hasColumn('vistoria_midias', 'chave_id')) {
                $table->dropColumn('chave_id');
            }
        });

        Schema::dropIfExists('vistoria_medidores');
    }
};
