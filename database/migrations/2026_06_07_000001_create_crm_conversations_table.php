<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("crm_conversations", function (Blueprint $table) {
            $table->id();
            $table->foreignId("tenant_id")->constrained()->onDelete("cascade");
            $table->foreignId("lead_id")->nullable()->constrained()->onDelete("set null");
            $table->foreignId("property_id")->nullable()->constrained()->onDelete("set null");
            $table->foreignId("assigned_user_id")->nullable()->constrained("users")->onDelete("set null");
            $table->foreignId("channel_id")->nullable()->constrained("communication_channels")->onDelete("set null");
            $table->string("source");
            $table->string("external_identifier_hash")->nullable()->unique();
            $table->string("contact_name");
            $table->string("contact_phone");
            $table->string("status");
            $table->string("stage");
            $table->integer("interest_level")->nullable();
            $table->timestamp("last_message_at")->nullable();
            $table->timestamp("last_summary_at")->nullable();
            $table->foreignId("created_by")->constrained("users")->onDelete("cascade");
            $table->timestamps();
            $table->softDeletes();

            $table->index("tenant_id");
            $table->index("lead_id");
            $table->index("property_id");
            $table->index("assigned_user_id");
            $table->index("status");
            $table->index("external_identifier_hash");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("crm_conversations");
    }
};
