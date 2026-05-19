<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vistorias')
            ->whereNull('link_publico_midias_token')
            ->orWhere('link_publico_midias_token', '')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('vistorias')
                        ->where('id', $row->id)
                        ->update(['link_publico_midias_token' => Str::random(80)]);
                }
            });

        DB::table('vistorias')
            ->whereNull('link_contestacao_token')
            ->orWhere('link_contestacao_token', '')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('vistorias')
                        ->where('id', $row->id)
                        ->update(['link_contestacao_token' => Str::random(80)]);
                }
            });
    }

    public function down(): void
    {
        // Tokens públicos não são removidos no rollback para não quebrar laudos já emitidos.
    }
};
