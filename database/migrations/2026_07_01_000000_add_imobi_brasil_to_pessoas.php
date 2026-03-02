<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImobiBrasilToPessoas extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            if (!Schema::hasColumn('pessoas', 'imobi_brasil_external_id')) {
                $table->string('imobi_brasil_external_id')->nullable()->after('score');
            }
            if (!Schema::hasColumn('pessoas', 'imobi_brasil_sent')) {
                $table->boolean('imobi_brasil_sent')->default(false)->after('imobi_brasil_external_id');
            }
            if (!Schema::hasColumn('pessoas', 'imobi_brasil_sent_at')) {
                $table->timestamp('imobi_brasil_sent_at')->nullable()->after('imobi_brasil_sent');
            }
            if (!Schema::hasColumn('pessoas', 'imobi_brasil_error')) {
                $table->text('imobi_brasil_error')->nullable()->after('imobi_brasil_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $cols = ['imobi_brasil_external_id', 'imobi_brasil_sent', 'imobi_brasil_sent_at', 'imobi_brasil_error'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('pessoas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
