<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vistoria_comentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('vistoria_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('pessoa_id')->nullable();
            $table->string('autor_nome')->nullable();
            $table->text('comentario');
            $table->timestamps();

            $table->index(['tenant_id', 'vistoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vistoria_comentarios');
    }
};

