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
        Schema::create("crm_conversation_proposals", function (Blueprint $table) {
            $table->id();
            $table->foreignId("tenant_id")->constrained()->onDelete("cascade");
            $table->foreignId("conversation_id")->constrained("crm_conversations")->onDelete("cascade");
            $table->foreignId("property_id")->constrained()->onDelete("cascade");
            $table->decimal("amount", 10, 2);
            $table->enum("proposal_type", ["purchase", "rent", "other"]);
            $table->string("status");
            $table->text("notes")->nullable();
            $table->foreignId("created_by")->constrained("users")->onDelete("cascade");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("crm_conversation_proposals");
    }
};
