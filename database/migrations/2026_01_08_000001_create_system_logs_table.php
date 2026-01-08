<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemLogsTable extends Migration
{
    public function up()
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('level', 20)->default('info')->index(); // debug, info, warning, error, critical
            $table->string('category', 100)->index(); // lead, whatsapp, ia, twilio, webhook, etc
            $table->string('action', 100)->index(); // created, updated, sent, received, etc
            $table->text('message');
            $table->json('context')->nullable(); // Dados adicionais em JSON
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('stack_trace')->nullable(); // Para erros
            $table->timestamp('created_at')->useCurrent();
            
            // Índices compostos para queries comuns
            $table->index(['tenant_id', 'created_at']);
            $table->index(['level', 'created_at']);
            $table->index(['category', 'action', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_logs');
    }
}
