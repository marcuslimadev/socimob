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
        Schema::create("crm_conversation_events", function (Blueprint $table) {
            $table->id();
            $table->foreignId("tenant_id")->constrained()->onDelete("cascade");
            $table->foreignId("conversation_id")->constrained("crm_conversations")->onDelete("cascade");
            $table->foreignId("user_id")->nullable()->constrained("users")->onDelete("set null");
            $table->string("event_type");
            $table->string("title");
            $table->text("description")->nullable();
            $table->json("payload_json")->nullable();
            $table->string("source");
            $table->timestamps();

            $table->index("tenant_id");
            $table->index("conversation_id");
            $table->index("event_type");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("crm_conversation_events");
    }
};
