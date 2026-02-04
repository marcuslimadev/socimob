<?php

/**
 * Script para atualizar coordenadas de imóveis existentes
 * Execute via: php update_property_coordinates.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

echo "====================================\n";
echo "Atualizando coordenadas dos imóveis\n";
echo "====================================\n\n";

// Buscar imóveis do tenant 1 sem coordenadas
$tenantId = 1;
$properties = DB::table('imo_properties')
    ->where('tenant_id', $tenantId)
    ->where(function($query) {
        $query->whereNull('latitude')
              ->orWhereNull('longitude');
    })
    ->get();

echo "Encontrados " . count($properties) . " imóveis sem coordenadas.\n\n";

$updated = 0;
$failed = 0;
$lastGeocodeCall = 0;
$geocodeCache = [];

function searchNominatim($query, &$lastGeocodeCall) {
    // Rate limiting - wait at least 1.1 seconds between requests
    if ($lastGeocodeCall > 0) {
        $elapsed = microtime(true) - $lastGeocodeCall;
        if ($elapsed < 1.1) {
            usleep((int)((1.1 - $elapsed) * 1000000));
        }
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $query,
        'format' => 'json',
        'limit' => 1,
        'addressdetails' => 1,
        'countrycodes' => 'br'
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'User-Agent: PropertySync/1.0 (contato@exclusivalarimoveis.com.br)'
        ]
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $lastGeocodeCall = microtime(true);

    if ($httpCode !== 200 || $resp === false) {
        return [null, null];
    }

    $data = json_decode($resp, true);
    if (is_array($data) && count($data) > 0) {
        $lat = isset($data[0]['lat']) ? (float) $data[0]['lat'] : null;
        $lng = isset($data[0]['lon']) ? (float) $data[0]['lon'] : null;
        return [$lat, $lng];
    }

    return [null, null];
}

function validCoordinates($lat, $lng) {
    if ($lat === null || $lng === null) {
        return false;
    }
    // Valid coordinates for Brazil
    return $lat >= -33.75 && $lat <= 5.27 && $lng >= -73.99 && $lng <= -28.84;
}

function getStateCoordinates($estado) {
    $coords = [
        'AC' => [-9.0238, -70.8120],
        'AL' => [-9.5713, -36.7820],
        'AP' => [1.4061, -51.6022],
        'AM' => [-3.4168, -65.8561],
        'BA' => [-12.5797, -41.7007],
        'CE' => [-5.4984, -39.3206],
        'DF' => [-15.7998, -47.8645],
        'ES' => [-19.1834, -40.3089],
        'GO' => [-15.8270, -49.8362],
        'MA' => [-4.9609, -45.2744],
        'MT' => [-12.6819, -56.9211],
        'MS' => [-20.7722, -54.7852],
        'MG' => [-19.9167, -43.9345],
        'PA' => [-3.7970, -52.4751],
        'PB' => [-7.2399, -36.7819],
        'PR' => [-24.8940, -51.5555],
        'PE' => [-8.8137, -36.9541],
        'PI' => [-6.6000, -42.2800],
        'RJ' => [-22.9068, -43.1729],
        'RN' => [-5.4026, -36.9541],
        'RS' => [-30.0346, -51.2177],
        'RO' => [-10.9472, -62.8278],
        'RR' => [1.3227, -60.6522],
        'SC' => [-27.2423, -50.2189],
        'SP' => [-23.5505, -46.6333],
        'SE' => [-10.5741, -37.3857],
        'TO' => [-10.1753, -48.2982],
    ];

    $estado = strtoupper($estado);
    return $coords[$estado] ?? [null, null];
}

function resolveCoordinates($property, &$lastGeocodeCall, &$geocodeCache) {
    $logradouro = trim($property->logradouro ?? '');
    $bairro = trim($property->bairro ?? '');
    $cidade = trim($property->cidade ?? '');
    $estado = strtoupper(trim($property->estado ?? ''));

    if (empty($cidade)) {
        $cidade = 'Belo Horizonte';
        $estado = $estado ?: 'MG';
    }

    $cacheKey = md5(json_encode([$logradouro, $bairro, $cidade, $estado]));
    if (isset($geocodeCache[$cacheKey])) {
        return $geocodeCache[$cacheKey];
    }

    if (empty($bairro) && empty($logradouro) && empty($cidade)) {
        return $geocodeCache[$cacheKey] = [null, null];
    }

    $queries = [];
    if ($logradouro && $bairro) {
        $queries[] = "{$logradouro}, {$bairro}, {$cidade}, {$estado}, Brasil";
    }
    if ($bairro) {
        $queries[] = "{$bairro}, {$cidade}, {$estado}, Brasil";
    }
    $queries[] = "{$cidade}, {$estado}, Brasil";

    foreach ($queries as $query) {
        $coords = searchNominatim($query, $lastGeocodeCall);
        if (validCoordinates($coords[0], $coords[1])) {
            return $geocodeCache[$cacheKey] = $coords;
        }
    }

    // Fallback to state center
    if ($estado) {
        $coords = getStateCoordinates($estado);
        if (validCoordinates($coords[0], $coords[1])) {
            return $geocodeCache[$cacheKey] = $coords;
        }
    }

    return $geocodeCache[$cacheKey] = [null, null];
}

foreach ($properties as $index => $property) {
    $codigo = $property->codigo ?? $property->id;
    echo "[" . ($index + 1) . "/" . count($properties) . "] Processando imóvel {$codigo}... ";

    try {
        [$lat, $lng] = resolveCoordinates($property, $lastGeocodeCall, $geocodeCache);

        if (validCoordinates($lat, $lng)) {
            DB::table('imo_properties')
                ->where('id', $property->id)
                ->update([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'updated_at' => Carbon::now()
                ]);

            echo "OK! ({$lat}, {$lng})\n";
            $updated++;
        } else {
            echo "FALHOU (não encontrou coordenadas)\n";
            $failed++;
        }
    } catch (\Exception $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n====================================\n";
echo "Resultado:\n";
echo "  Atualizados: {$updated}\n";
echo "  Falhas: {$failed}\n";
echo "====================================\n";
