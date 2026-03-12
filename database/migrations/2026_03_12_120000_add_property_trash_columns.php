<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
                $table->index('deleted_at', 'imo_properties_deleted_at_index');
            }

            if (!Schema::hasColumn('imo_properties', 'trash_source')) {
                $table->string('trash_source', 50)->nullable()->after('deleted_at');
                $table->index('trash_source', 'imo_properties_trash_source_index');
            }

            if (!Schema::hasColumn('imo_properties', 'trash_reason')) {
                $table->string('trash_reason', 120)->nullable()->after('trash_source');
            }

            if (!Schema::hasColumn('imo_properties', 'trashed_by_user_id')) {
                $table->unsignedBigInteger('trashed_by_user_id')->nullable()->after('trash_reason');
                $table->index('trashed_by_user_id', 'imo_properties_trashed_by_user_id_index');
            }

            if (!Schema::hasColumn('imo_properties', 'trash_metadata')) {
                $table->json('trash_metadata')->nullable()->after('trashed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'trash_metadata')) {
                $table->dropColumn('trash_metadata');
            }

            if (Schema::hasColumn('imo_properties', 'trashed_by_user_id')) {
                $table->dropIndex('imo_properties_trashed_by_user_id_index');
                $table->dropColumn('trashed_by_user_id');
            }

            if (Schema::hasColumn('imo_properties', 'trash_reason')) {
                $table->dropColumn('trash_reason');
            }

            if (Schema::hasColumn('imo_properties', 'trash_source')) {
                $table->dropIndex('imo_properties_trash_source_index');
                $table->dropColumn('trash_source');
            }

            if (Schema::hasColumn('imo_properties', 'deleted_at')) {
                $table->dropIndex('imo_properties_deleted_at_index');
                $table->dropSoftDeletes();
            }
        });
    }
};