<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id')->nullable();
            $table->string('wa_id', 64)->nullable();
            $table->string('phone_e164', 25);
            $table->string('profile_name', 255)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_e164'], 'whatsapp_contacts_tenant_phone_unique');
            $table->index(['tenant_id', 'wa_id'], 'whatsapp_contacts_tenant_waid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
