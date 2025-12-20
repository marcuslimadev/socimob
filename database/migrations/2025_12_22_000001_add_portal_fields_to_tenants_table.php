<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPortalFieldsToTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'slogan')) {
                $table->string('slogan', 500)->nullable()->after('description');
            }
            if (!Schema::hasColumn('tenants', 'favicon_url')) {
                $table->string('favicon_url', 500)->nullable()->after('logo_url');
            }
            if (!Schema::hasColumn('tenants', 'primary_color')) {
                $table->string('primary_color', 7)->default('#1e293b')->after('favicon_url');
            }
            if (!Schema::hasColumn('tenants', 'secondary_color')) {
                $table->string('secondary_color', 7)->default('#3b82f6')->after('primary_color');
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
            $columns = [];
            if (Schema::hasColumn('tenants', 'slogan')) {
                $columns[] = 'slogan';
            }
            if (Schema::hasColumn('tenants', 'favicon_url')) {
                $columns[] = 'favicon_url';
            }
            if (Schema::hasColumn('tenants', 'primary_color')) {
                $columns[] = 'primary_color';
            }
            if (Schema::hasColumn('tenants', 'secondary_color')) {
                $columns[] = 'secondary_color';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
}
