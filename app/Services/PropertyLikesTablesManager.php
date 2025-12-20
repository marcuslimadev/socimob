<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PropertyLikesTablesManager
{
    public static function ensurePropertyLikesTableExists(): void
    {
        if (Schema::hasTable('property_likes')) {
            return;
        }

        Schema::create('property_likes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'user_id']);
            $table->index(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Log::info('PropertyLikesTablesManager: tabela property_likes criada automaticamente.');
    }
}
