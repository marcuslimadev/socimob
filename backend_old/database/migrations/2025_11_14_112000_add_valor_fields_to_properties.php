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
        Schema::table('imo_properties', function (Blueprint $table) {
            // Adicionar campos que faltam na produção
            if (!Schema::hasColumn('imo_properties', 'valor_iptu')) {
                $table->decimal('valor_iptu', 10, 2)->default(0)->after('valor_aluguel');
            }
            
            if (!Schema::hasColumn('imo_properties', 'valor_condominio')) {
                $table->decimal('valor_condominio', 10, 2)->default(0)->after('valor_iptu');
            }
            
            if (!Schema::hasColumn('imo_properties', 'logradouro')) {
                $table->string('logradouro')->nullable()->after('bairro');
            }
            
            if (!Schema::hasColumn('imo_properties', 'numero')) {
                $table->string('numero')->nullable()->after('logradouro');
            }
            
            if (!Schema::hasColumn('imo_properties', 'complemento')) {
                $table->string('complemento')->nullable()->after('numero');
            }
            
            if (!Schema::hasColumn('imo_properties', 'cep')) {
                $table->string('cep')->nullable()->after('complemento');
            }
            
            if (!Schema::hasColumn('imo_properties', 'area_terreno')) {
                $table->decimal('area_terreno', 10, 2)->nullable()->after('area_total');
            }
            
            if (!Schema::hasColumn('imo_properties', 'caracteristicas')) {
                $table->json('caracteristicas')->nullable()->after('api_data');
            }
            
            if (!Schema::hasColumn('imo_properties', 'imagens')) {
                $table->json('imagens')->nullable()->after('caracteristicas');
            }
            
            if (!Schema::hasColumn('imo_properties', 'exclusividade')) {
                $table->boolean('exclusividade')->default(false)->after('em_condominio');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropColumn([
                'valor_iptu', 'valor_condominio', 'logradouro', 'numero', 
                'complemento', 'cep', 'area_terreno', 'caracteristicas', 
                'imagens', 'exclusividade'
            ]);
        });
    }
};