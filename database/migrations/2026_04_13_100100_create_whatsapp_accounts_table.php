<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('tenant_integration_id');
            $table->string('meta_business_account_id', 64)->nullable();
            $table->string('waba_id', 64);
            $table->string('app_id', 64)->nullable();
            $table->text('app_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->string('system_user_id', 64)->nullable();
            $table->string('status', 30)->default('inactive');
            $table->string('display_name_status', 50)->nullable();
            $table->string('quality_rating', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_token_validated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'waba_id'], 'whatsapp_accounts_tenant_waba_unique');
            $table->index(['tenant_id', 'status'], 'whatsapp_accounts_tenant_status_idx');
            $table->index(['tenant_integration_id'], 'whatsapp_accounts_integration_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
