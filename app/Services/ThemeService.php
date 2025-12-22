<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantConfig;

class ThemeService
{
    /**
     * Temas disponíveis
     */
    const THEMES = [
        'classico' => 'Clássico',
        'bauhaus' => 'Moderno',
    ];

    /**
     * Cores padrão por tema
     */
    const DEFAULT_COLORS = [
        'classico' => [
            'primary' => '#1a1a1a',
            'secondary' => '#ffffff',
            'accent' => '#ff6b6b',
            'success' => '#51cf66',
            'warning' => '#ffd43b',
            'danger' => '#ff6b6b',
            'info' => '#74c0fc',
        ],
        'bauhaus' => [
            'primary' => '#000000',
            'secondary' => '#f5f5f5',
            'accent' => '#ff0000',
            'success' => '#00ff00',
            'warning' => '#ffff00',
            'danger' => '#ff0000',
            'info' => '#0000ff',
        ],
    ];

    /**
     * Obter tema do tenant
     */
    public function getTheme(Tenant $tenant): array
    {
        $theme = $tenant->theme ?? 'classico';
        $config = $tenant->config;

        return [
            'name' => $theme,
            'label' => self::THEMES[$theme] ?? $theme,
            'colors' => [
                'primary' => $config->primary_color ?? self::DEFAULT_COLORS[$theme]['primary'],
                'secondary' => $config->secondary_color ?? self::DEFAULT_COLORS[$theme]['secondary'],
                'accent' => $config->accent_color ?? self::DEFAULT_COLORS[$theme]['accent'],
                'success' => $config->success_color ?? self::DEFAULT_COLORS[$theme]['success'],
                'warning' => $config->warning_color ?? self::DEFAULT_COLORS[$theme]['warning'],
                'danger' => $config->danger_color ?? self::DEFAULT_COLORS[$theme]['danger'],
                'info' => $config->info_color ?? self::DEFAULT_COLORS[$theme]['info'],
            ],
            'logo_url' => $tenant->logo_url,
            'favicon_url' => $config->favicon_url ?? null,
        ];
    }

    /**
     * Atualizar tema do tenant
     */
    public function updateTheme(Tenant $tenant, string $themeName, array $colors = []): array
    {
        if (!isset(self::THEMES[$themeName])) {
            throw new \InvalidArgumentException("Tema '{$themeName}' não existe");
        }

        // Atualizar tema no tenant
        $tenant->update(['theme' => $themeName]);

        // Atualizar cores na config
        $config = $tenant->config;
        if (!$config) {
            $config = TenantConfig::create(['tenant_id' => $tenant->id]);
        }

        // Mesclar cores fornecidas com padrões
        $defaultColors = self::DEFAULT_COLORS[$themeName];
        $finalColors = array_merge($defaultColors, $colors);

        // Validar cores
        foreach ($finalColors as $key => $color) {
            if (!$this->isValidColor($color)) {
                throw new \InvalidArgumentException("Cor inválida para '{$key}': {$color}");
            }
        }

        // Atualizar config
        $config->update([
            'primary_color' => $finalColors['primary'],
            'secondary_color' => $finalColors['secondary'],
            'accent_color' => $finalColors['accent'],
            'success_color' => $finalColors['success'],
            'warning_color' => $finalColors['warning'],
            'danger_color' => $finalColors['danger'],
            'info_color' => $finalColors['info'],
        ]);

        return $this->getTheme($tenant);
    }

    /**
     * Resetar tema para padrão
     */
    public function resetTheme(Tenant $tenant): array
    {
        $theme = $tenant->theme ?? 'classico';
        $defaultColors = self::DEFAULT_COLORS[$theme];

        $config = $tenant->config;
        if (!$config) {
            $config = TenantConfig::create(['tenant_id' => $tenant->id]);
        }

        $config->update([
            'primary_color' => $defaultColors['primary'],
            'secondary_color' => $defaultColors['secondary'],
            'accent_color' => $defaultColors['accent'],
            'success_color' => $defaultColors['success'],
            'warning_color' => $defaultColors['warning'],
            'danger_color' => $defaultColors['danger'],
            'info_color' => $defaultColors['info'],
        ]);

        return $this->getTheme($tenant);
    }

    /**
     * Gerar CSS customizado
     */
    public function generateCSS(Tenant $tenant): string
    {
        $theme = $this->getTheme($tenant);
        $colors = $theme['colors'];

        $css = ":root {\n";
        $css .= "  --color-primary: {$colors['primary']};\n";
        $css .= "  --color-secondary: {$colors['secondary']};\n";
        $css .= "  --color-accent: {$colors['accent']};\n";
        $css .= "  --color-success: {$colors['success']};\n";
        $css .= "  --color-warning: {$colors['warning']};\n";
        $css .= "  --color-danger: {$colors['danger']};\n";
        $css .= "  --color-info: {$colors['info']};\n";
        $css .= "}\n\n";

        // Adicionar estilos base por tema
        if ($theme['name'] === 'bauhaus') {
            $css .= $this->getModernCSS($colors);
        } else {
            $css .= $this->getClassicoCSS($colors);
        }

        return $css;
    }

    /**
     * Obter CSS do tema Clássico
     */
    private function getClassicoCSS(array $colors): string
    {
        return <<<CSS
/* Tema Clássico */
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: {$colors['primary']};
  background-color: {$colors['secondary']};
}

.btn-primary {
  background-color: {$colors['primary']};
  border-color: {$colors['primary']};
  color: {$colors['secondary']};
}

.btn-primary:hover {
  background-color: {$this->lighten($colors['primary'], 10)};
  border-color: {$this->lighten($colors['primary'], 10)};
}

.btn-accent {
  background-color: {$colors['accent']};
  border-color: {$colors['accent']};
  color: {$colors['secondary']};
}

.btn-accent:hover {
  background-color: {$this->lighten($colors['accent'], 10)};
  border-color: {$this->lighten($colors['accent'], 10)};
}

.card {
  border: 1px solid {$this->lighten($colors['primary'], 80)};
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header {
  background-color: {$colors['primary']};
  color: {$colors['secondary']};
}

.sidebar {
  background-color: {$this->lighten($colors['primary'], 5)};
  color: {$colors['secondary']};
}

.link {
  color: {$colors['accent']};
  text-decoration: none;
}

.link:hover {
  text-decoration: underline;
}
CSS;
    }

    /**
     * Obter CSS do tema moderno
     */
    private function getModernCSS(array $colors): string
    {
        return <<<CSS
/* Tema moderno */
body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  color: {$colors['primary']};
  background-color: {$colors['secondary']};
  letter-spacing: 0.5px;
}

.btn-primary {
  background-color: {$colors['primary']};
  border: 2px solid {$colors['primary']};
  color: {$colors['secondary']};
  padding: 12px 24px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.btn-primary:hover {
  background-color: {$colors['secondary']};
  color: {$colors['primary']};
}

.btn-accent {
  background-color: {$colors['accent']};
  border: 2px solid {$colors['accent']};
  color: {$colors['secondary']};
  padding: 12px 24px;
  font-weight: 600;
  text-transform: uppercase;
}

.btn-accent:hover {
  background-color: {$colors['secondary']};
  color: {$colors['accent']};
}

.card {
  border: none;
  border-left: 4px solid {$colors['accent']};
  box-shadow: none;
  padding: 24px;
}

.header {
  background-color: {$colors['primary']};
  color: {$colors['secondary']};
  padding: 40px 0;
  border-bottom: 4px solid {$colors['accent']};
}

.sidebar {
  background-color: {$colors['secondary']};
  border-right: 2px solid {$colors['primary']};
  padding: 24px;
}

.sidebar a {
  display: block;
  padding: 12px 0;
  border-bottom: 1px solid {$this->lighten($colors['primary'], 80)};
  color: {$colors['primary']};
  text-decoration: none;
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 1px;
  font-weight: 600;
}

.sidebar a:hover {
  color: {$colors['accent']};
}

.link {
  color: {$colors['accent']};
  text-decoration: none;
  border-bottom: 1px solid {$colors['accent']};
}

.link:hover {
  opacity: 0.8;
}

h1, h2, h3, h4, h5, h6 {
  font-weight: 700;
  letter-spacing: 0.5px;
}

.grid {
  display: grid;
  gap: 24px;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}
CSS;
    }

    /**
     * Validar cor hex
     */
    private function isValidColor(string $color): bool
    {
        return preg_match('/^#[0-9A-F]{6}$/i', $color) === 1;
    }

    /**
     * Clarear cor (aumentar luminosidade)
     */
    private function lighten(string $color, int $percent): string
    {
        $hex = str_replace('#', '', $color);
        $rgb = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        foreach ($rgb as &$value) {
            $value = min(255, $value + (255 - $value) * ($percent / 100));
        }

        return '#' . implode('', array_map(function ($value) {
            return str_pad(dechex((int)$value), 2, '0', STR_PAD_LEFT);
        }, $rgb));
    }

    /**
     * Escurecer cor (diminuir luminosidade)
     */
    private function darken(string $color, int $percent): string
    {
        $hex = str_replace('#', '', $color);
        $rgb = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        foreach ($rgb as &$value) {
            $value = max(0, $value - ($value * ($percent / 100)));
        }

        return '#' . implode('', array_map(function ($value) {
            return str_pad(dechex((int)$value), 2, '0', STR_PAD_LEFT);
        }, $rgb));
    }

    /**
     * Obter lista de temas disponíveis
     */
    public function getAvailableThemes(): array
    {
        return array_map(function ($key, $label) {
            return [
                'id' => $key,
                'name' => $label,
                'colors' => self::DEFAULT_COLORS[$key],
            ];
        }, array_keys(self::THEMES), array_values(self::THEMES));
    }
}
