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
            $table->string('slogan', 500)->nullable()->after('description');
            $table->string('favicon_url', 500)->nullable()->after('logo_url');
            $table->string('primary_color', 7)->default('#1e293b')->after('favicon_url');
            $table->string('secondary_color', 7)->default('#3b82f6')->after('primary_color');
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
            $table->dropColumn(['slogan', 'favicon_url', 'primary_color', 'secondary_color']);
        });
    }
}
