<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class SiteSeoController extends Controller
{
    public function home(Request $request)
    {
        $html = @file_get_contents(public_path('index.html'));
        if (!$html) {
            return response('Not found', 404);
        }

        $host = strtolower($request->getHost());
        if (!in_array($host, [
            'exclusivalarimoveis.com',
            'www.exclusivalarimoveis.com',
            'exclusivalarimoveis.com.br',
            'www.exclusivalarimoveis.com.br',
            'exclusivarlarimoveis.com',
            'www.exclusivarlarimoveis.com',
        ], true)) {
            return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $title = 'Exclusiva Lar Imóveis | Imóveis em Belo Horizonte';
        $description = 'Encontre imóveis para comprar, vender ou alugar em Belo Horizonte com atendimento personalizado da Exclusiva Lar Imóveis.';
        $canonical = 'https://exclusivalarimoveis.com/';
        $image = $canonical . 'images/og-exclusiva.png';
        $escape = static fn (string $value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $html = preg_replace('/<title>.*?<\/title>/s', '<title>' . $escape($title) . '</title>', $html, 1) ?? $html;
        $html = preg_replace('/<meta\s+(?:name|property)=["\'](?:description|og:[^"\']+|twitter:[^"\']+)["\'][^>]*>\s*/i', '', $html) ?? $html;
        $html = preg_replace('/<link\s+rel=["\']canonical["\'][^>]*>\s*/i', '', $html) ?? $html;
        $html = preg_replace('/<script\s+id=["\']site-structured-data["\'][^>]*>.*?<\/script>\s*/si', '', $html) ?? $html;

        $structuredData = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            '@id' => $canonical . '#organization',
            'name' => 'Exclusiva Lar Imóveis',
            'url' => $canonical,
            'logo' => $canonical . 'assets/logo-exclusiva.png',
            'image' => $image,
            'description' => $description,
            'areaServed' => ['@type' => 'City', 'name' => 'Belo Horizonte'],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Belo Horizonte', 'addressRegion' => 'MG', 'addressCountry' => 'BR'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tags = '<meta name="description" content="' . $escape($description) . '">' . "\n"
            . '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n"
            . '<link rel="canonical" href="' . $canonical . '">' . "\n"
            . '<meta property="og:locale" content="pt_BR"><meta property="og:type" content="website">' . "\n"
            . '<meta property="og:site_name" content="Exclusiva Lar Imóveis"><meta property="og:title" content="' . $escape($title) . '">' . "\n"
            . '<meta property="og:description" content="' . $escape($description) . '"><meta property="og:url" content="' . $canonical . '">' . "\n"
            . '<meta property="og:image" content="' . $image . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n"
            . '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . $escape($title) . '">' . "\n"
            . '<meta name="twitter:description" content="' . $escape($description) . '"><meta name="twitter:image" content="' . $image . '">' . "\n"
            . '<script id="site-structured-data" type="application/ld+json">' . $structuredData . '</script>';

        return response(str_replace('</head>', $tags . "\n</head>", $html))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function sitemap(Request $request)
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if (!$tenant) {
            return response('Tenant not found', 404);
        }

        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        $properties = Property::withoutTenant()
            ->where('tenant_id', $tenant->id)
            ->publiclyVisible()
            ->orderByDesc('updated_at')
            ->get(['id', 'updated_at']);

        $urls = [['loc' => $baseUrl . '/', 'lastmod' => null, 'priority' => '1.0']];
        foreach ($properties as $property) {
            $urls[] = [
                'loc' => $baseUrl . '/portal/imovel/' . $property->id,
                'lastmod' => optional($property->updated_at)->toAtomString(),
                'priority' => '0.8',
            ];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();
        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
