<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyPortalTenant extends Model
{
    protected $table = 'property_portal_tenants';

    protected $fillable = [
        'property_id',
        'tenant_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Property
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Relationship: Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope: Find by property and tenant
     */
    public function scopeByPropertyAndTenant($query, $propertyId, $tenantId)
    {
        return $query->where('property_id', $propertyId)
                     ->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Find by property
     */
    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope: Find by tenant
     */
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
