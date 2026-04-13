<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('whatsapp_account_id')->nullable();
            $table->unsignedBigInteger('whatsapp_phone_number_id')->nullable();
            $table->string('object_type', 50)->nullable();
            $table->string('entry_id', 64)->nullable();
            $table->string('change_field', 64)->nullable();
            $table->string('event_type', 50)->default('unknown');
            $table->string('delivery_key', 191);
            $table->string('payload_hash', 64);
            $table->boolean('signature_valid')->default(false);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('correlation_id', 64)->nullable();
            $table->json('headers')->nullable();
            $table->json('event_payload')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['delivery_key'], 'whatsapp_webhook_events_delivery_key_unique');
            $table->index(['tenant_id', 'processed_at'], 'whatsapp_webhook_events_tenant_processed_idx');
            $table->index(['payload_hash'], 'whatsapp_webhook_events_payload_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
