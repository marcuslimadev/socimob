<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    /**
     * Lista as configurações disponíveis
     */
    public function index()
    {
        $settings = Schema::hasTable('app_settings')
            ? AppSetting::pluckSettings()
            : [];

        $defaults = [
            'ai_name' => env('AI_ASSISTANT_NAME', 'Teresa'),
        ];

        $response = array_merge($defaults, $settings);

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }

    /**
     * Atualiza configurações em lote
     */
    public function update(Request $request)
    {
        if (!Schema::hasTable('app_settings')) {
            return response()->json([
                'success' => false,
                'error' => 'Tabela de configurações indisponível. Execute as migrações.',
            ], 500);
        }

        $payload = $request->input('settings');

        if (!is_array($payload) || empty($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'Nenhuma configuração enviada',
            ], 422);
        }

        foreach ($payload as $key => $value) {
            AppSetting::setValue($key, $value);
        }

        return response()->json([
            'success' => true,
            'data' => AppSetting::pluckSettings(),
        ]);
    }
}
