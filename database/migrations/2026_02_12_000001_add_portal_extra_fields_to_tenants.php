<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPortalExtraFieldsToTenants extends Migration
{
    public function up()
    {
        // Add mascot_url and extra portal fields to tenants
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'mascot_url')) {
                    $table->string('mascot_url')->nullable()->after('favicon_url');
                }
                if (!Schema::hasColumn('tenants', 'creci')) {
                    $table->string('creci', 50)->nullable()->after('cnpj');
                }
                if (!Schema::hasColumn('tenants', 'about_text')) {
                    $table->text('about_text')->nullable()->after('description');
                }
                if (!Schema::hasColumn('tenants', 'services')) {
                    $table->json('services')->nullable()->after('about_text');
                }
                if (!Schema::hasColumn('tenants', 'social_links')) {
                    $table->json('social_links')->nullable()->after('services');
                }
                if (!Schema::hasColumn('tenants', 'neighborhoods')) {
                    $table->json('neighborhoods')->nullable()->after('social_links');
                }
                if (!Schema::hasColumn('tenants', 'office_hours')) {
                    $table->string('office_hours')->nullable()->after('neighborhoods');
                }
            });
        }

        // Create property_evaluations table for evaluation requests
        if (!Schema::hasTable('property_evaluations')) {
            Schema::create('property_evaluations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('nome');
                $table->string('telefone');
                $table->string('email')->nullable();
                $table->string('tipo_imovel')->nullable();
                $table->string('endereco')->nullable();
                $table->string('bairro')->nullable();
                $table->string('cidade')->nullable();
                $table->text('observacoes')->nullable();
                $table->enum('status', ['pendente', 'em_andamento', 'concluida', 'cancelada'])->default('pendente');
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('status');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('property_evaluations');

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                $columns = ['mascot_url', 'creci', 'about_text', 'services', 'social_links', 'neighborhoods', 'office_hours'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('tenants', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
