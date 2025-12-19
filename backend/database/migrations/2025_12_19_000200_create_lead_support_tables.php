<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('conversa_id');
            $table->string('message_sid')->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->string('message_type')->default('text');
            $table->longText('content')->nullable();
            $table->string('media_url')->nullable();
            $table->longText('transcription')->nullable();
            $table->string('status')->default('sent');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('conversa_id');
            $table->index('direction');
            $table->foreign('conversa_id')->references('id')->on('conversas')->onDelete('cascade');
        });

        Schema::create('lead_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('conversa_id')->nullable();
            $table->unsignedBigInteger('mensagem_id')->nullable();
            $table->string('nome');
            $table->string('tipo')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('arquivo_url');
            $table->string('status')->default('pendente');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('conversa_id')->references('id')->on('conversas')->onDelete('set null');
            $table->foreign('mensagem_id')->references('id')->on('mensagens')->onDelete('set null');
        });

        Schema::create('lead_property_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('conversa_id')->nullable();
            $table->decimal('match_score', 5, 2)->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('property_id');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('property_id')->references('id')->on('imo_properties')->onDelete('cascade');
            $table->foreign('conversa_id')->references('id')->on('conversas')->onDelete('set null');
        });

        Schema::create('atividades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('tipo')->nullable();
            $table->text('descricao')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades');
        Schema::dropIfExists('lead_property_matches');
        Schema::dropIfExists('lead_documents');
        Schema::dropIfExists('mensagens');
    }
};
