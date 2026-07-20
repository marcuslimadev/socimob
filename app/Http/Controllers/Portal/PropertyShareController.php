<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Serve a página pública de um imóvel (/portal/imovel/{id}) com metatags Open Graph
 * dinâmicas (título, descrição, foto do imóvel), para que links compartilhados no
 * WhatsApp/Facebook mostrem uma prévia rica em vez do card genérico do SPA. O HTML
 * base continua sendo o build do React, então a experiência do visitante não muda -
 * só as metatags lidas pelos crawlers de preview (que não executam JavaScript).
 */
class PropertyShareController extends Controller
{
    public function show(Request $request, $id)
    {
        $indexPath = public_path('index.html');
        $html = file_exists($indexPath) ? file_get_contents($indexPath) : null;

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (!$html || !$tenant) {
            return $this->fallback($html);
        }

        $property = Property::withoutTenant()
            ->where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->with('fotos')
            ->publiclyVisible()
            ->first();

        if (!$property) {
            return $this->fallback($html);
        }

        $siteName = $tenant->name ?: 'Portal Imobiliário';
        $title = trim(($property->titulo ?: 'Imóvel') . ' | ' . $siteName);
        $description = $this->buildDescription($property);
        $image = $property->imagem_destaque ?: optional($property->fotos->first())->url;
        $url = rtrim($request->getSchemeAndHttpHost(), '/') . "/portal/imovel/{$property->id}";

        return response($this->injectMetaTags($html, $title, $description, $image, $url, $siteName), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function fallback(?string $html)
    {
        if ($html) {
            return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response('Not found', 404);
    }

    private function buildDescription(Property $property): string
    {
        $tipo = $property->tipo_imovel ? Str::ucfirst($property->tipo_imovel) : 'Imóvel';
        $finalidade = $property->finalidade_imovel ? Str::ucfirst($property->finalidade_imovel) : null;
        $local = trim(($property->cidade ?: '') . ($property->estado ? ' / ' . $property->estado : ''));

        $intro = $tipo . ($finalidade ? " para {$finalidade}" : '');
        if ($local !== '') {
            $intro .= ", {$local}";
        }

        $parts = [$intro];

        if ($property->bairro) {
            $parts[] = "bairro {$property->bairro}";
        }

        if ($property->dormitorios) {
            $suiteText = $property->suites ? ", sendo {$property->suites} suíte(s)" : '';
            $parts[] = "{$property->dormitorios} dormitórios{$suiteText}";
        }

        if ($property->banheiros) {
            $parts[] = "{$property->banheiros} banheiros";
        }

        if ($property->garagem) {
            $parts[] = "{$property->garagem} vagas de garagem";
        }

        if ($property->area_total) {
            $parts[] = 'área total ' . number_format((float) $property->area_total, 2, ',', '.') . ' m²';
        }

        $descricao = implode(', ', $parts);

        return $descricao !== '' ? $descricao : ($property->descricao_resumida ?: 'Confira este imóvel.');
    }

    private function injectMetaTags(string $html, string $title, string $description, ?string $image, string $url, string $siteName): string
    {
        $escape = fn (string $value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Remove as metatags estáticas do index.html (og:*, twitter:*, description) para
        // não deixar duplicatas no <head> - crawlers como o do WhatsApp usam a primeira
        // ocorrência de cada tag, então a genérica do SOCIMOB "vencia" a do imóvel.
        $html = preg_replace('/<meta\s+(?:name|property)=["\'](?:description|og:[^"\']+|twitter:[^"\']+)["\'][^>]*>\s*/i', '', $html) ?? $html;

        $tags = [
            '<meta property="og:type" content="website" />',
            '<meta property="og:site_name" content="' . $escape($siteName) . '" />',
            '<meta property="og:title" content="' . $escape($title) . '" />',
            '<meta property="og:description" content="' . $escape($description) . '" />',
            '<meta property="og:url" content="' . $escape($url) . '" />',
            '<meta name="twitter:title" content="' . $escape($title) . '" />',
            '<meta name="twitter:description" content="' . $escape($description) . '" />',
            '<meta name="description" content="' . $escape($description) . '" />',
        ];

        if ($image) {
            $tags[] = '<meta property="og:image" content="' . $escape($image) . '" />';
            $tags[] = '<meta name="twitter:card" content="summary_large_image" />';
            $tags[] = '<meta name="twitter:image" content="' . $escape($image) . '" />';
        }

        $html = preg_replace('/<title>.*?<\/title>/s', '<title>' . $escape($title) . '</title>', $html, 1) ?? $html;

        return str_replace('</head>', implode("\n    ", $tags) . "\n  </head>", $html);
    }
}
