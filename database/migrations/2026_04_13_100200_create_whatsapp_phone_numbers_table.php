<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->string('phone_number_id', 64);
            $table->string('display_phone_number', 50)->nullable();
            $table->string('e164_phone_number', 25)->nullable();
            $table->string('verified_name', 255)->nullable();
            $table->string('status', 30)->default('inactive');
            $table->string('code_verification_status', 50)->nullable();
            $table->string('quality_rating', 30)->nullable();
            $table->string('messaging_limit_tier', 30)->nullable();
            $table->string('current_provider', 50)->default('meta_cloud');
            $table->string('migrated_from_provider', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_number_id'], 'whatsapp_phone_numbers_tenant_phone_id_unique');
            $table->index(['tenant_id', 'is_default'], 'whatsapp_phone_numbers_tenant_default_idx');
            $table->index(['whatsapp_account_id', 'is_active'], 'whatsapp_phone_numbers_account_active_idx');
            $table->index(['e164_phone_number'], 'whatsapp_phone_numbers_e164_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_phone_numbers');
    }
};
