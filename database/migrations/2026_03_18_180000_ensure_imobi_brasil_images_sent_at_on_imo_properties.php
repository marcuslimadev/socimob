<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('imo_properties') || Schema::hasColumn('imo_properties', 'imobi_brasil_images_sent_at')) {
            return;
        }

        Schema::table('imo_properties', function (Blueprint $table) {
            $column = $table->timestamp('imobi_brasil_images_sent_at')
                ->nullable()
                ->comment('Ultima sincronizacao de imagens com Imobi Brasil');

            if (Schema::hasColumn('imo_properties', 'imobi_brasil_sent_at')) {
                $column->after('imobi_brasil_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('imo_properties') || !Schema::hasColumn('imo_properties', 'imobi_brasil_images_sent_at')) {
            return;
        }

        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropColumn('imobi_brasil_images_sent_at');
        });
    }
};