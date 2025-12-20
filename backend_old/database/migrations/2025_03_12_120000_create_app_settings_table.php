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
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('chave', 150)->unique();
                $table->text('valor')->nullable();
                $table->string('tipo', 50)->default('string');
                $table->string('descricao', 255)->nullable();
                $table->string('categoria', 100)->nullable();
                $table->boolean('editavel')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
