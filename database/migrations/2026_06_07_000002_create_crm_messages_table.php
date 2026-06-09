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
        Schema::create("crm_messages", function (Blueprint $table) {
            $table->id();
            $table->foreignId("tenant_id")->constrained()->onDelete("cascade");
            $table->foreignId("conversation_id")->constrained("crm_conversations")->onDelete("cascade");
            $table->foreignId("user_id")->nullable()->constrained("users")->onDelete("set null");
            $table->enum("direction", ["inbound", "outbound"]);
            $table->enum("message_type", ["text", "image", "file", "audio", "system"]);
            $table->text("body");
            $table->string("external_message_id")->nullable();
            $table->timestamp("external_sent_at")->nullable();
            $table->json("metadata_json")->nullable();
            $table->timestamps();

            $table->index("tenant_id");
            $table->index("conversation_id");
            $table->index("external_message_id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("crm_messages");
    }
};
