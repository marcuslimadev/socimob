<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VisitasTablesManager
{
    public static function ensureVisitasTableExists(): void
    {
        if (Schema::hasTable('visitas')) {
            return;
        }

        Schema::create('visitas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('property_titulo', 255)->nullable();
            $table->string('nome', 255);
            $table->string('email', 255)->nullable();
            $table->string('telefone', 50)->nullable();
            $table->timestamp('data_hora');
            $table->string('status', 30)->default('pendente');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'data_hora']);
        });

        Log::info('VisitasTablesManager: tabela visitas criada automaticamente.');
    }
}
