<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('imo_properties')) {
            Schema::table('imo_properties', function (Blueprint $table) {
                if (!Schema::hasColumn('imo_properties', 'imobi_brasil_images_sent_at')) {
                    $table->timestamp('imobi_brasil_images_sent_at')
                        ->nullable()
                        ->after('imobi_brasil_sent_at')
                        ->comment('Última sincronização de imagens com Imobi Brasil');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('imo_properties')) {
            Schema::table('imo_properties', function (Blueprint $table) {
                if (Schema::hasColumn('imo_properties', 'imobi_brasil_images_sent_at')) {
                    $table->dropColumn('imobi_brasil_images_sent_at');
                }
            });
        }
    }
};
