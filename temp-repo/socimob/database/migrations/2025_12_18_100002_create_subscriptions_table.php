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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Relacionamento com tenant
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->unique('tenant_id');
            
            // Plano
            $table->string('plan_id', 50)->notNullable();
            $table->string('plan_name', 255)->notNullable();
            $table->decimal('plan_amount', 10, 2)->notNullable();
            $table->string('plan_interval', 20)->default('month'); // month, year
            
            // Status
            $table->enum('status', ['active', 'past_due', 'canceled', 'paused'])->default('active');
            $table->string('status_reason', 255)->nullable();
            
            // Períodos
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            
            // Integração Pagar.me
            $table->string('pagar_me_subscription_id', 255)->unique()->nullable();
            $table->string('pagar_me_customer_id', 255)->nullable();
            $table->string('pagar_me_card_id', 255)->nullable();
            
            // Informações de pagamento
            $table->string('payment_method', 50)->nullable(); // credit_card, boleto, pix
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand', 20)->nullable();
            
            // Tentativas de cobrança
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('status');
            $table->index('current_period_end');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
