<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitas')) {
            return;
        }

        Schema::table('visitas', function (Blueprint $table) {
            if (!Schema::hasColumn('visitas', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('property_id');
            }

            if (!Schema::hasColumn('visitas', 'assigned_user_id')) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('observacoes');
            }

            if (!Schema::hasColumn('visitas', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('assigned_user_id');
            }

            if (!Schema::hasColumn('visitas', 'origem')) {
                $table->string('origem', 50)->nullable()->after('created_by_user_id');
            }
        });

        Schema::table('visitas', function (Blueprint $table) {
            $table->index(['tenant_id', 'assigned_user_id', 'data_hora'], 'visitas_tenant_assigned_date_idx');
            $table->index(['tenant_id', 'lead_id'], 'visitas_tenant_lead_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitas')) {
            return;
        }

        Schema::table('visitas', function (Blueprint $table) {
            try {
                $table->dropIndex('visitas_tenant_assigned_date_idx');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('visitas_tenant_lead_idx');
            } catch (\Throwable $e) {
            }

            $dropColumns = [];
            foreach (['lead_id', 'assigned_user_id', 'created_by_user_id', 'origem'] as $column) {
                if (Schema::hasColumn('visitas', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};