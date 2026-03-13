<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCaptadorToImoProperties extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'captador_user_id')) {
                $table->unsignedBigInteger('captador_user_id')->nullable()->after('inserido_por_nome');
            }

            if (!Schema::hasColumn('imo_properties', 'captador_nome')) {
                $table->string('captador_nome', 150)->nullable()->after('captador_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('imo_properties', 'captador_user_id')) {
                $drop[] = 'captador_user_id';
            }

            if (Schema::hasColumn('imo_properties', 'captador_nome')) {
                $drop[] = 'captador_nome';
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
}
