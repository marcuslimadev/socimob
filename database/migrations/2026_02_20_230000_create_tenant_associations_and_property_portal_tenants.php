<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tenant_associations')) {
            Schema::create('tenant_associations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('associated_tenant_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'associated_tenant_id'], 'tenant_associations_unique');
                $table->index(['tenant_id']);
                $table->index(['associated_tenant_id']);

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('associated_tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('tenant_associations') && Schema::hasTable('tenants')) {
            $tenantExclusiva = \Illuminate\Support\Facades\DB::table('tenants')
                ->select('id')
                ->where(function ($query) {
                    $query->where('slug', 'like', '%exclusiva%')
                        ->orWhere('domain', 'like', '%exclusiva%')
                        ->orWhere('name', 'like', '%exclusiva%');
                })
                ->first();

            $tenantMaison = \Illuminate\Support\Facades\DB::table('tenants')
                ->select('id')
                ->where(function ($query) {
                    $query->where('slug', 'like', '%maison%')
                        ->orWhere('domain', 'like', '%maison%')
                        ->orWhere('name', 'like', '%maison%')
                        ->orWhere('slug', 'like', '%lagoa%')
                        ->orWhere('domain', 'like', '%lagoa%')
                        ->orWhere('name', 'like', '%lagoa%');
                })
                ->first();

            if ($tenantExclusiva && $tenantMaison && (int) $tenantExclusiva->id !== (int) $tenantMaison->id) {
                $now = now();
                \Illuminate\Support\Facades\DB::table('tenant_associations')->updateOrInsert(
                    [
                        'tenant_id' => (int) $tenantExclusiva->id,
                        'associated_tenant_id' => (int) $tenantMaison->id,
                    ],
                    [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                \Illuminate\Support\Facades\DB::table('tenant_associations')->updateOrInsert(
                    [
                        'tenant_id' => (int) $tenantMaison->id,
                        'associated_tenant_id' => (int) $tenantExclusiva->id,
                    ],
                    [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        if (!Schema::hasTable('property_portal_tenants')) {
            Schema::create('property_portal_tenants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('property_id');
                $table->unsignedBigInteger('tenant_id');
                $table->timestamps();

                $table->unique(['property_id', 'tenant_id'], 'property_portal_tenants_unique');
                $table->index(['tenant_id']);

                $table->foreign('property_id')->references('id')->on('imo_properties')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('imo_properties') && Schema::hasTable('property_portal_tenants')) {
            $now = now();
            $properties = \Illuminate\Support\Facades\DB::table('imo_properties')
                ->select('id', 'tenant_id')
                ->whereNotNull('tenant_id')
                ->get();

            foreach ($properties as $property) {
                \Illuminate\Support\Facades\DB::table('property_portal_tenants')->updateOrInsert(
                    [
                        'property_id' => (int) $property->id,
                        'tenant_id' => (int) $property->tenant_id,
                    ],
                    [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('property_portal_tenants')) {
            Schema::dropIfExists('property_portal_tenants');
        }

        if (Schema::hasTable('tenant_associations')) {
            Schema::dropIfExists('tenant_associations');
        }
    }
};
