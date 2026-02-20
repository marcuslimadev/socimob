<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'visibilidade_endereco')) {
                $table->string('visibilidade_endereco', 30)
                    ->default('bairro_cidade')
                    ->after('status_chaves');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'visibilidade_endereco')) {
                $table->dropColumn('visibilidade_endereco');
            }
        });
    }
};

