<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_short_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('code', 12);
            $table->string('whatsapp_number', 30);
            $table->text('message_preview')->nullable();
            $table->string('sms_message_sid', 64)->nullable();
            $table->timestamp('used_at')->nullable();
            $table->string('used_message_sid', 64)->nullable();
            $table->unsignedBigInteger('used_mensagem_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_short_links');
    }
};
