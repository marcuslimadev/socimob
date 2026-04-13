<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('integration_type', 50);
            $table->string('integration_name', 100);
            $table->string('channel', 50)->nullable();
            $table->string('direction', 10)->nullable();
            $table->string('operation', 100)->nullable();
            $table->string('endpoint', 255)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'integration_name', 'created_at'], 'integration_logs_tenant_name_created_idx');
            $table->index(['correlation_id'], 'integration_logs_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
