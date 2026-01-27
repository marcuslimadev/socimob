<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('security_audit_logs')) {
            Schema::create('security_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event')->index();
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
                $table->json('data')->nullable();
                $table->string('ip_address', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index('created_at');
                $table->index(['event', 'created_at']);
                $table->index(['severity', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
