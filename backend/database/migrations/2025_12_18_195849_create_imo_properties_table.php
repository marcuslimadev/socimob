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
        Schema::create('imo_properties', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('titulo')->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('exibir_imovel')->default(true);
            $table->timestamps();
        });
        
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->integer('tempo_execucao')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('imo_properties');
    }
};
