<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('telefone');
            $table->enum('status', ['ativa', 'encerrada', 'aguardando_corretor'])->default('ativa');
            $table->string('canal')->nullable(); // whatsapp, sms, telegram, etc
            $table->text('mensagens')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->dateTime('iniciada_em')->nullable();
            $table->dateTime('finalizada_em')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversas');
    }
};
