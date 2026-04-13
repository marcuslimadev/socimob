<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->string('external_template_id', 64)->nullable();
            $table->string('name', 255);
            $table->string('language', 20);
            $table->string('category', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('quality_score', 30)->nullable();
            $table->string('parameter_format', 30)->nullable();
            $table->json('components')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'whatsapp_account_id', 'name', 'language'], 'whatsapp_templates_unique_name_lang');
            $table->index(['tenant_id', 'status'], 'whatsapp_templates_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
