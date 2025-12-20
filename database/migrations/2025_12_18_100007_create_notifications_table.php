<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('intention_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            
            // Tipo de notificação
            $table->enum('type', [
                'property_match',
                'property_new',
                'price_change',
                'status_change',
                'message',
                'system',
            ])->default('system');
            
            // Conteúdo
            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            
            // Canais de notificação
            $table->enum('channel', ['email', 'whatsapp', 'sms', 'push', 'in_app'])->default('in_app');
            
            // Status
            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            
            // Tentativas de envio
            $table->integer('send_attempts')->default(0);
            $table->string('send_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('intention_id');
            $table->index('property_id');
            $table->index('type');
            $table->index('channel');
            $table->index('is_read');
            $table->index('is_sent');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
