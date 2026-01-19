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
        if (Schema::hasTable('tenants')) {
            return;
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Informações básicas
            $table->string('name', 255)->notNullable();
            $table->string('domain', 255)->unique()->notNullable();
            $table->string('slug', 255)->unique()->notNullable();
            
            // Tema e aparência
            $table->enum('theme', ['classico', 'bauhaus'])->default('classico');
            $table->string('primary_color', 7)->default('#000000')->nullable();
            $table->string('secondary_color', 7)->default('#FFFFFF')->nullable();
            $table->string('logo_url', 500)->nullable();
            
            // Status de assinatura
            $table->enum('subscription_status', ['active', 'inactive', 'suspended', 'expired'])->default('inactive');
            $table->string('subscription_plan', 50)->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamp('subscription_started_at')->nullable();
            
            // Integração Pagar.me
            $table->string('pagar_me_customer_id', 255)->unique()->nullable();
            $table->string('pagar_me_subscription_id', 255)->unique()->nullable();
            
            // Chaves de API
            $table->string('api_key_pagar_me', 255)->nullable();
            $table->string('api_key_apm_imoveis', 255)->nullable();
            $table->string('api_key_neca', 255)->nullable();
            
            // Chave de API interna
            $table->string('api_token', 255)->unique()->nullable();
            
            // Contato e informações
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('description')->nullable();
            
            // Configurações
            $table->boolean('is_active')->default(true);
            $table->integer('max_users')->default(10);
            $table->integer('max_properties')->default(1000);
            $table->integer('max_leads')->default(5000);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('domain');
            $table->index('subscription_status');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
