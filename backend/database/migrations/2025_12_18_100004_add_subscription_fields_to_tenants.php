<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Adicionar campos de assinatura se não existirem
            if (!Schema::hasColumn('tenants', 'subscription_status')) {
                $table->enum('subscription_status', ['active', 'inactive', 'suspended', 'expired'])
                    ->default('inactive')
                    ->after('theme');
            }

            if (!Schema::hasColumn('tenants', 'subscription_plan')) {
                $table->string('subscription_plan')->nullable()->after('subscription_status');
            }

            if (!Schema::hasColumn('tenants', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable()->after('subscription_plan');
            }

            if (!Schema::hasColumn('tenants', 'subscription_started_at')) {
                $table->timestamp('subscription_started_at')->nullable()->after('subscription_expires_at');
            }

            if (!Schema::hasColumn('tenants', 'pagar_me_customer_id')) {
                $table->string('pagar_me_customer_id')->nullable()->unique()->after('subscription_started_at');
            }

            if (!Schema::hasColumn('tenants', 'pagar_me_subscription_id')) {
                $table->string('pagar_me_subscription_id')->nullable()->unique()->after('pagar_me_customer_id');
            }

            if (!Schema::hasColumn('tenants', 'api_key_pagar_me')) {
                $table->text('api_key_pagar_me')->nullable()->after('pagar_me_subscription_id');
            }

            if (!Schema::hasColumn('tenants', 'api_key_apm_imoveis')) {
                $table->text('api_key_apm_imoveis')->nullable()->after('api_key_pagar_me');
            }

            if (!Schema::hasColumn('tenants', 'api_key_neca')) {
                $table->text('api_key_neca')->nullable()->after('api_key_apm_imoveis');
            }

            if (!Schema::hasColumn('tenants', 'api_token')) {
                $table->string('api_token')->nullable()->unique()->after('api_key_neca');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status',
                'subscription_plan',
                'subscription_expires_at',
                'subscription_started_at',
                'pagar_me_customer_id',
                'pagar_me_subscription_id',
                'api_key_pagar_me',
                'api_key_apm_imoveis',
                'api_key_neca',
                'api_token',
            ]);
        });
    }
};
