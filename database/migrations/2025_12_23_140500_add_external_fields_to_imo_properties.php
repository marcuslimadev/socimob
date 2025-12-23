<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'external_id')) {
                $table->string('external_id', 255)->nullable()->after('numero');
            }
            if (!Schema::hasColumn('imo_properties', 'preco')) {
                $table->decimal('preco', 15, 2)->nullable()->after('valor_condominio');
            }
            if (!Schema::hasColumn('imo_properties', 'endereco')) {
                $table->text('endereco')->nullable()->after('preco');
            }
            if (!Schema::hasColumn('imo_properties', 'quartos')) {
                $table->integer('quartos')->nullable()->after('area_terreno');
            }
            if (!Schema::hasColumn('imo_properties', 'vagas')) {
                $table->integer('vagas')->nullable()->after('quartos');
            }
            if (!Schema::hasColumn('imo_properties', 'fotos')) {
                $table->longText('fotos')->nullable()->after('imagens');
            }
            if (!Schema::hasColumn('imo_properties', 'url_ficha')) {
                $table->string('url_ficha', 500)->nullable()->after('fotos');
            }
        });

        $existingIndex = DB::select(
            "SHOW INDEX FROM imo_properties WHERE Key_name = ?",
            ['idx_tenant_external']
        );

        if (empty($existingIndex)) {
            Schema::table('imo_properties', function (Blueprint $table) {
                $table->index(['tenant_id', 'external_id'], 'idx_tenant_external');
            });
        }
    }

    public function down(): void
    {
        $existingIndex = DB::select(
            "SHOW INDEX FROM imo_properties WHERE Key_name = ?",
            ['idx_tenant_external']
        );

        if (!empty($existingIndex)) {
            Schema::table('imo_properties', function (Blueprint $table) {
                $table->dropIndex('idx_tenant_external');
            });
        }

        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'url_ficha')) {
                $table->dropColumn('url_ficha');
            }
            if (Schema::hasColumn('imo_properties', 'fotos')) {
                $table->dropColumn('fotos');
            }
            if (Schema::hasColumn('imo_properties', 'vagas')) {
                $table->dropColumn('vagas');
            }
            if (Schema::hasColumn('imo_properties', 'quartos')) {
                $table->dropColumn('quartos');
            }
            if (Schema::hasColumn('imo_properties', 'endereco')) {
                $table->dropColumn('endereco');
            }
            if (Schema::hasColumn('imo_properties', 'preco')) {
                $table->dropColumn('preco');
            }
            if (Schema::hasColumn('imo_properties', 'external_id')) {
                $table->dropColumn('external_id');
            }
            if (Schema::hasIndex('imo_properties', 'idx_tenant_external')) {
                $table->dropIndex('idx_tenant_external');
            }
        });

    }
};
