<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id');
            $table->unsignedBigInteger('whatsapp_message_id')->nullable();
            $table->string('meta_media_id', 64)->nullable();
            $table->string('direction', 10)->nullable();
            $table->string('media_type', 30)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('sha256', 128)->nullable();
            $table->string('filename', 255)->nullable();
            $table->string('caption', 1024)->nullable();
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_path', 1024)->nullable();
            $table->string('download_url', 2048)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->unique(['meta_media_id'], 'whatsapp_media_meta_media_unique');
            $table->index(['tenant_id', 'whatsapp_message_id'], 'whatsapp_media_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_media');
    }
};
