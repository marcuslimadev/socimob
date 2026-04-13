<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('provider', 50);
            $table->string('channel', 50);
            $table->string('integration_version', 30)->nullable();
            $table->string('status', 30)->default('inactive');
            $table->boolean('is_active')->default(false);
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_verify_token_hash', 255)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'channel'], 'tenant_integrations_unique_provider_channel');
            $table->index(['tenant_id', 'status'], 'tenant_integrations_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_integrations');
    }
};
