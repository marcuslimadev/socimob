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
        Schema::table('tenant_configs', function (Blueprint $table) {
            // Adicionar cores se não existirem
            if (!Schema::hasColumn('tenant_configs', 'primary_color')) {
                $table->string('primary_color')->default('#1a1a1a')->after('tenant_id');
            }

            if (!Schema::hasColumn('tenant_configs', 'secondary_color')) {
                $table->string('secondary_color')->default('#ffffff')->after('primary_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'accent_color')) {
                $table->string('accent_color')->default('#ff6b6b')->after('secondary_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'success_color')) {
                $table->string('success_color')->default('#51cf66')->after('accent_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'warning_color')) {
                $table->string('warning_color')->default('#ffd43b')->after('success_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'danger_color')) {
                $table->string('danger_color')->default('#ff6b6b')->after('warning_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'info_color')) {
                $table->string('info_color')->default('#74c0fc')->after('danger_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'logo_url')) {
                $table->string('logo_url')->nullable()->after('info_color');
            }

            if (!Schema::hasColumn('tenant_configs', 'favicon_url')) {
                $table->string('favicon_url')->nullable()->after('logo_url');
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
        Schema::table('tenant_configs', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color',
                'secondary_color',
                'accent_color',
                'success_color',
                'warning_color',
                'danger_color',
                'info_color',
                'logo_url',
                'favicon_url',
            ]);
        });
    }
};
