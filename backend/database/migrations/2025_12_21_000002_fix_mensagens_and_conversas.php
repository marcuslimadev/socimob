<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mensagens')) {
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
        }

        if (Schema::hasTable('conversas') && !Schema::hasColumn('conversas', 'ultima_atividade')) {
            Schema::table('conversas', function (Blueprint $table) {
                $table->dateTime('ultima_atividade')->nullable()->after('finalizada_em');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversas') && Schema::hasColumn('conversas', 'ultima_atividade')) {
            Schema::table('conversas', function (Blueprint $table) {
                $table->dropColumn('ultima_atividade');
            });
        }
        if (Schema::hasTable('mensagens')) {
            Schema::dropIfExists('mensagens');
        }
    }
};
