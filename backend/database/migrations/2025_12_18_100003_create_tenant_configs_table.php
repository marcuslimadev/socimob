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
        Schema::create('tenant_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Relacionamento com tenant
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->unique('tenant_id');
            
            // Chaves de API (criptografadas em produção)
            $table->text('api_key_pagar_me')->nullable();
            $table->text('api_key_apm_imoveis')->nullable();
            $table->text('api_key_neca')->nullable();
            
            // Configurações de tema
            $table->string('primary_color', 7)->default('#000000')->nullable();
            $table->string('secondary_color', 7)->default('#FFFFFF')->nullable();
            $table->string('accent_color', 7)->default('#FF6B6B')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('favicon_url', 500)->nullable();
            
            // Configurações de email
            $table->string('smtp_host', 255)->nullable();
            $table->integer('smtp_port')->default(587)->nullable();
            $table->string('smtp_username', 255)->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_from_email', 255)->nullable();
            $table->string('smtp_from_name', 255)->nullable();
            
            // Configurações de notificação
            $table->boolean('notify_new_leads')->default(true);
            $table->boolean('notify_new_properties')->default(true);
            $table->boolean('notify_new_messages')->default(true);
            $table->string('notification_email', 255)->nullable();
            
            // Configurações de imóveis
            $table->integer('max_images_per_property')->default(20);
            $table->integer('max_properties')->default(1000);
            $table->boolean('require_approval_for_properties')->default(false);
            
            // Configurações de leads
            $table->integer('max_leads')->default(5000);
            $table->boolean('auto_assign_leads')->default(false);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_configs');
    }
};
