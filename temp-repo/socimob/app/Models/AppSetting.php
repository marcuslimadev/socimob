<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    public static function getValue(string $key, $default = null, ?int $tenantId = null)
    {
        if (!Schema::hasTable('app_settings')) {
            return $default;
        }

        $tenantId = $tenantId ?? (app()->bound('tenant') ? app('tenant')->id : null);

        $query = self::where('key', $key);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $setting = $query->first();

        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, $value, ?int $tenantId = null): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        $tenantId = $tenantId ?? (app()->bound('tenant') ? app('tenant')->id : null);

        $query = self::where('key', $key);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $setting = $query->first();
        if ($setting) {
            $setting->update(['value' => $value]);
            return;
        }

        self::create([
            'tenant_id' => $tenantId,
            'key' => $key,
            'value' => $value,
        ]);
    }

    public static function pluckSettings(?int $tenantId = null): array
    {
        if (!Schema::hasTable('app_settings')) {
            return [];
        }

        $tenantId = $tenantId ?? (app()->bound('tenant') ? app('tenant')->id : null);

        $query = self::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        })->toArray();
    }
}
