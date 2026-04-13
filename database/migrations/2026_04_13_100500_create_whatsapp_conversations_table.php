<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id');
            $table->unsignedBigInteger('whatsapp_contact_id');
            $table->string('external_conversation_id', 64)->nullable();
            $table->string('status', 30)->default('open');
            $table->string('category', 30)->nullable();
            $table->string('conversation_type', 30)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'whatsapp_contact_id', 'status'], 'whatsapp_conversations_contact_status_idx');
            $table->index(['tenant_id', 'last_message_at'], 'whatsapp_conversations_tenant_last_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
