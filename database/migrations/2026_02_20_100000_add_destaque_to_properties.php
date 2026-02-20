<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDestaqueToProperties extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('imo_properties', 'destaque')) {
            Schema::table('imo_properties', function (Blueprint $table) {
                $table->boolean('destaque')->default(false)->after('exibir_imovel');
            });
        }
    }

    public function down()
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropColumn('destaque');
        });
    }
}
