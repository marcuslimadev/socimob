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
        Schema::create("crm_conversation_tasks", function (Blueprint $table) {
            $table->id();
            $table->foreignId("tenant_id")->constrained()->onDelete("cascade");
            $table->foreignId("conversation_id")->constrained("crm_conversations")->onDelete("cascade");
            $table->foreignId("assigned_user_id")->constrained("users")->onDelete("cascade");
            $table->foreignId("created_by")->constrained("users")->onDelete("cascade");
            $table->string("title");
            $table->text("description")->nullable();
            $table->timestamp("due_at")->nullable();
            $table->string("priority");
            $table->string("status");
            $table->timestamp("completed_at")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("crm_conversation_tasks");
    }
};
