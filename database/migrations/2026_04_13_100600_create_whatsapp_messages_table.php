<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id');
            $table->unsignedBigInteger('whatsapp_conversation_id')->nullable();
            $table->unsignedBigInteger('whatsapp_contact_id')->nullable();
            $table->string('wamid', 128)->nullable()->unique();
            $table->string('direction', 10);
            $table->string('message_type', 30);
            $table->string('internal_status', 30)->default('queued');
            $table->string('meta_message_status', 50)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('sender_phone', 25)->nullable();
            $table->string('recipient_phone', 25)->nullable();
            $table->text('body')->nullable();
            $table->string('template_name', 255)->nullable();
            $table->string('template_language', 20)->nullable();
            $table->json('template_payload')->nullable();
            $table->json('payload')->nullable();
            $table->string('context_message_wamid', 128)->nullable();
            $table->string('reply_to_wamid', 128)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'whatsapp_messages_tenant_idempotency_unique');
            $table->index(['tenant_id', 'whatsapp_conversation_id'], 'whatsapp_messages_conversation_idx');
            $table->index(['tenant_id', 'direction', 'created_at'], 'whatsapp_messages_tenant_direction_idx');
            $table->index(['correlation_id'], 'whatsapp_messages_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
