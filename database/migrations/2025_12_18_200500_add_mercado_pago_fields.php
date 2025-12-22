<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'gateway')) {
                $table->string('gateway', 50)->default('mercado_pago')->after('status');
            }

            if (!Schema::hasColumn('subscriptions', 'mercado_pago_preapproval_id')) {
                $table->string('mercado_pago_preapproval_id')->nullable()->unique()->after('pagar_me_subscription_id');
            }

            if (!Schema::hasColumn('subscriptions', 'mercado_pago_customer_id')) {
                $table->string('mercado_pago_customer_id')->nullable()->after('mercado_pago_preapproval_id');
            }

            if (!Schema::hasColumn('subscriptions', 'mercado_pago_card_token')) {
                $table->string('mercado_pago_card_token')->nullable()->after('mercado_pago_customer_id');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'mercado_pago_customer_id')) {
                $table->string('mercado_pago_customer_id')->nullable()->unique()->after('pagar_me_subscription_id');
            }

            if (!Schema::hasColumn('tenants', 'mercado_pago_preapproval_id')) {
                $table->string('mercado_pago_preapproval_id')->nullable()->unique()->after('mercado_pago_customer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'gateway')) {
                $table->dropColumn('gateway');
            }

            if (Schema::hasColumn('subscriptions', 'mercado_pago_preapproval_id')) {
                $table->dropColumn('mercado_pago_preapproval_id');
            }

            if (Schema::hasColumn('subscriptions', 'mercado_pago_customer_id')) {
                $table->dropColumn('mercado_pago_customer_id');
            }

            if (Schema::hasColumn('subscriptions', 'mercado_pago_card_token')) {
                $table->dropColumn('mercado_pago_card_token');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'mercado_pago_customer_id')) {
                $table->dropColumn('mercado_pago_customer_id');
            }

            if (Schema::hasColumn('tenants', 'mercado_pago_preapproval_id')) {
                $table->dropColumn('mercado_pago_preapproval_id');
            }
        });
    }
};
