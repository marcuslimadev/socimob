<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('imoveis_imagens')) {
            Schema::create('imoveis_imagens', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->index(); // código do imóvel (FK para properties.codigo)
                $table->text('url');
                $table->boolean('destaque')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('imoveis_imagens');
    }
};
