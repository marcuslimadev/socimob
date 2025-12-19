<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    protected $themeService;

    public function __construct(ThemeService $themeService)
    {
        $this->themeService = $themeService;
    }

    /**
     * Obter tema atual do tenant
     * GET /api/theme
     */
    public function current(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $theme = $this->themeService->getTheme($tenant);

        return response()->json($theme);
    }

    /**
     * Obter CSS customizado do tema
     * GET /api/theme/css
     */
    public function css(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response('', 400, [
                'Content-Type' => 'text/css',
            ]);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response('', 404, [
                'Content-Type' => 'text/css',
            ]);
        }

        $css = $this->themeService->generateCSS($tenant);

        return response($css, 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Atualizar tema do tenant
     * PUT /api/theme
     */
    public function update(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validated = $request->validate([
            'theme' => 'required|in:classico,bauhaus',
            'colors' => 'nullable|array',
            'colors.primary' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'colors.secondary' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'colors.accent' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'colors.success' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'colors.warning' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'colors.danger' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'colors.info' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        try {
            $theme = $this->themeService->updateTheme(
                $tenant,
                $validated['theme'],
                $validated['colors'] ?? []
            );

            return response()->json([
                'message' => 'Theme updated successfully',
                'theme' => $theme,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update theme',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resetar tema para padrão
     * POST /api/theme/reset
     */
    public function reset(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        try {
            $theme = $this->themeService->resetTheme($tenant);

            return response()->json([
                'message' => 'Theme reset successfully',
                'theme' => $theme,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reset theme',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obter temas disponíveis
     * GET /api/themes
     */
    public function available(Request $request)
    {
        $themes = $this->themeService->getAvailableThemes();

        return response()->json([
            'themes' => $themes,
        ]);
    }

    /**
     * Obter preview do tema
     * GET /api/themes/{themeName}/preview
     */
    public function preview(Request $request, string $themeName)
    {
        try {
            $themes = $this->themeService->getAvailableThemes();
            $theme = collect($themes)->firstWhere('id', $themeName);

            if (!$theme) {
                return response()->json(['error' => 'Theme not found'], 404);
            }

            return response()->json([
                'theme' => $theme,
                'preview' => $this->generatePreview($theme),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load theme preview',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Gerar preview do tema
     */
    private function generatePreview(array $theme): array
    {
        $colors = $theme['colors'];

        return [
            'name' => $theme['name'],
            'colors' => $colors,
            'elements' => [
                'button_primary' => [
                    'background' => $colors['primary'],
                    'color' => $colors['secondary'],
                    'text' => 'Botão Primário',
                ],
                'button_accent' => [
                    'background' => $colors['accent'],
                    'color' => $colors['secondary'],
                    'text' => 'Botão Destaque',
                ],
                'card_background' => [
                    'background' => $colors['secondary'],
                    'border' => $colors['primary'],
                    'text' => 'Cartão',
                ],
                'header_background' => [
                    'background' => $colors['primary'],
                    'color' => $colors['secondary'],
                    'text' => 'Cabeçalho',
                ],
            ],
        ];
    }
}
