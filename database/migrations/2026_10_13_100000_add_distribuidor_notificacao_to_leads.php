<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controle do alerta "novo lead -> distribuidora (Alexsandra)": só é marcado como
     * notificado quando o envio à distribuidora realmente deu certo, para que falhas
     * sejam reenviadas pelo comando leads:alert-distributor.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('distribuidor_notificado_em')->nullable()->after('atendimento_notificado_para');
            $table->unsignedTinyInteger('distribuidor_notificacao_tentativas')->default(0)->after('distribuidor_notificado_em');
            $table->timestamp('distribuidor_recontato_em')->nullable()->after('distribuidor_notificacao_tentativas');
            $table->timestamp('corretor_recontato_em')->nullable()->after('distribuidor_recontato_em');
            $table->index(['distribuidor_notificado_em', 'created_at'], 'leads_distribuidor_pendente_idx');
        });

        // Leads já existentes não devem disparar alerta retroativo.
        DB::table('leads')
            ->whereNull('distribuidor_notificado_em')
            ->update(['distribuidor_notificado_em' => DB::raw('COALESCE(atendimento_notificado_em, created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_distribuidor_pendente_idx');
            $table->dropColumn(['distribuidor_notificado_em', 'distribuidor_notificacao_tentativas', 'distribuidor_recontato_em', 'corretor_recontato_em']);
        });
    }
};
