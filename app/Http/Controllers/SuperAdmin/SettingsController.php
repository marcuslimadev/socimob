<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Obter configurações globais
     * GET /api/super-admin/settings
     */
    public function index(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $settings = AppSetting::whereNull('tenant_id')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            });

        return response()->json($settings);
    }

    /**
     * Obter uma configuração específica
     * GET /api/super-admin/settings/{key}
     */
    public function show(Request $request, $key)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $setting = AppSetting::where('key', $key)
            ->whereNull('tenant_id')
            ->first();

        if (!$setting) {
            return response()->json(['error' => 'Setting not found'], 404);
        }

        return response()->json([
            'key' => $setting->key,
            'value' => $setting->value,
        ]);
    }

    /**
     * Atualizar configuração
     * PUT /api/super-admin/settings/{key}
     */
    public function update(Request $request, $key)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'value' => 'required',
        ]);

        $setting = AppSetting::where('key', $key)
            ->whereNull('tenant_id')
            ->first();

        if (!$setting) {
            $setting = AppSetting::create([
                'key' => $key,
                'value' => $validated['value'],
                'tenant_id' => null,
            ]);
        } else {
            $setting->update(['value' => $validated['value']]);
        }

        return response()->json([
            'message' => 'Setting updated successfully',
            'key' => $setting->key,
            'value' => $setting->value,
        ]);
    }

    /**
     * Obter configurações de planos
     * GET /api/super-admin/settings/plans
     */
    public function getPlans(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $plans = AppSetting::where('key', 'like', 'plan_%')
            ->whereNull('tenant_id')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => json_decode($setting->value, true)];
            });

        return response()->json($plans);
    }

    /**
     * Atualizar plano
     * PUT /api/super-admin/settings/plans/{planId}
     */
    public function updatePlan(Request $request, $planId)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'max_users' => 'required|integer|min:1',
            'max_properties' => 'required|integer|min:1',
            'max_leads' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $key = 'plan_' . $planId;

        $setting = AppSetting::where('key', $key)
            ->whereNull('tenant_id')
            ->first();

        if (!$setting) {
            $setting = AppSetting::create([
                'key' => $key,
                'value' => json_encode($validated),
                'tenant_id' => null,
            ]);
        } else {
            $setting->update(['value' => json_encode($validated)]);
        }

        return response()->json([
            'message' => 'Plan updated successfully',
            'plan' => json_decode($setting->value, true),
        ]);
    }

    /**
     * Obter configurações de integração
     * GET /api/super-admin/settings/integrations
     */
    public function getIntegrations(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $integrations = [
            'pagar_me' => $this->getSetting('pagar_me_api_key'),
            'apm_imoveis' => $this->getSetting('apm_imoveis_api_key'),
            'neca' => $this->getSetting('neca_api_key'),
        ];

        return response()->json($integrations);
    }

    /**
     * Atualizar integração
     * PUT /api/super-admin/settings/integrations/{service}
     */
    public function updateIntegration(Request $request, $service)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'api_key' => 'required|string',
            'api_secret' => 'nullable|string',
        ]);

        $key = $service . '_api_key';

        $setting = AppSetting::where('key', $key)
            ->whereNull('tenant_id')
            ->first();

        if (!$setting) {
            $setting = AppSetting::create([
                'key' => $key,
                'value' => $validated['api_key'],
                'tenant_id' => null,
            ]);
        } else {
            $setting->update(['value' => $validated['api_key']]);
        }

        // Se houver api_secret, salvar também
        if (!empty($validated['api_secret'])) {
            $secretKey = $service . '_api_secret';
            $secretSetting = AppSetting::where('key', $secretKey)
                ->whereNull('tenant_id')
                ->first();

            if (!$secretSetting) {
                AppSetting::create([
                    'key' => $secretKey,
                    'value' => $validated['api_secret'],
                    'tenant_id' => null,
                ]);
            } else {
                $secretSetting->update(['value' => $validated['api_secret']]);
            }
        }

        return response()->json([
            'message' => 'Integration updated successfully',
            'service' => $service,
        ]);
    }

    /**
     * Obter uma configuração
     */
    private function getSetting($key)
    {
        $setting = AppSetting::where('key', $key)
            ->whereNull('tenant_id')
            ->first();

        return $setting ? $setting->value : null;
    }
}
