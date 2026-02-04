<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('session_id', 64)->index();
            $table->string('ip_hash', 128)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('os', 64)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('country', 8)->nullable();
            $table->string('region', 64)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->string('landing_path', 512)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_role', 32)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('session_id', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_name', 64)->index();
            $table->string('path', 512)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sessions');
    }
};
