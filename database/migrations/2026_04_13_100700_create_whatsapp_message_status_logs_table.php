<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_message_id')->nullable();
            $table->string('wamid', 128)->nullable();
            $table->string('status', 50);
            $table->timestamp('status_at')->nullable();
            $table->string('recipient_id', 64)->nullable();
            $table->boolean('billable')->nullable();
            $table->string('pricing_category', 50)->nullable();
            $table->json('errors')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'wamid'], 'whatsapp_message_status_logs_tenant_wamid_idx');
            $table->index(['tenant_id', 'status_at'], 'whatsapp_message_status_logs_tenant_status_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_status_logs');
    }
};
