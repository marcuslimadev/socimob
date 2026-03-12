<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->string('tipo', 100)->nullable();
            $table->string('nome', 255);
            $table->string('arquivo_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('imo_properties')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('property_documents', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
        });

        Schema::dropIfExists('property_documents');
    }
};