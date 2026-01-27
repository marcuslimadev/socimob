<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scheduled_imports')) {
            Schema::create('scheduled_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('source')->index(); // vivareal, imobiliariacom, sftp, custom_api
                $table->string('schedule'); // cron expression
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->json('last_result')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
                $table->index('next_run_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_imports');
    }
};
