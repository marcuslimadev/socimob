<?php
/**
 * Worker de sincronização de imóveis - Duas fases
 * Fase 1: Percorre TODAS as páginas e salva dados básicos
 * Fase 2: Busca detalhes apenas dos imóveis que precisam atualização
 * 
 * VERSÃO CORRIGIDA - Schema PostgreSQL
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

set_time_limit(0);
ini_set('memory_limit', '512M');

define('API_TOKEN', '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O');
define('API_BASE', 'https://www.exclusivalarimoveis.com.br/api/v1/app/imovel');
define('FORCE_FULL_UPDATE', false); // Forçar atualização completa (ignora cache de 4 horas)

/**
 * Formata descrição de texto plano para HTML
 */
function format_description_html($text) {
    if (empty($text)) {
        return null;
    }

    $normalized = normalize_description_text($text);

    $aiFormatted = ai_format_description($normalized);
    if (!empty($aiFormatted)) {
        return $aiFormatted;
    }

    return build_fallback_description_html($normalized);
}

function normalize_description_text($text)
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\x{00A0}/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/(\p{L})(\d)/u', '$1 $2', $text);
    $text = preg_replace('/(\d)(\p{L})/u', '$1 $2', $text);
    $text = preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $text);
    $text = preg_replace('/([^\s])R\$/u', '$1 R$', $text);
    $text = preg_replace('/m\x{00B2}/u', 'm² ', $text);
    return trim($text);
}

function ai_format_description($descricao)
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        return null;
    }

    $systemPrompt = 'Você é especialista em marketing imobiliário. Gere descrições atraentes, estruturadas em HTML (<p>, <ul>, <li>, <strong>), corrigindo erros e garantindo texto conciso e topificado.';
    $userPrompt = "Reescreva e organize a seguinte descrição de imóvel, mantendo todas as informações relevantes. Use marcadores quando fizer sentido e destaque os dados principais:

{$descricao}";

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'max_tokens' => 800,
        'temperature' => 0.6,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        echo "⚠️  Falha ao usar OpenAI para descrição ({$httpCode}): {$error}
";
        return null;
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        return null;
    }

    $content = trim($result['choices'][0]['message']['content']);
    $content = preg_replace('/```html\s*(.*?)\s*```/s', '$1', $content);
    $content = preg_replace('/```\s*(.*?)\s*```/s', '$1', $content);

    return $content;
}

function build_fallback_description_html($text)
{
    $text = preg_replace('/<\/?p>/', '', $text);
    $text = trim($text);
    $text = str_replace(["

", "
", "
"], "|||BR|||", $text);

    $html = '';
    $lines = explode("|||BR|||", $text);
    $inList = false;

    foreach ($lines as $line) {
        $line = trim($line);

        if (empty($line)) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<br>';
            continue;
        }

        if (preg_match('/^[🏡🔑🌟📍💎✨🏆🚪🎯📞📐📌🤩💡🛏🛁🚗🌳]/u', $line)) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h3>' . htmlspecialchars($line) . '</h3>';
        } elseif (preg_match('/^[\-\*✅]/', $line) || preg_match('/^\*\*/', $line)) {
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $item = preg_replace('/^[\-\*✅]\s*/', '', $line);
            $item = preg_replace('/^\*\*([^:]+):\*\*/', '<strong>$1:</strong>', $item);
            $item = str_replace(['<strong>', '</strong>'], ['|||STRONG|||', '|||/STRONG|||'], $item);
            $item = htmlspecialchars($item, ENT_NOQUOTES);
            $item = str_replace(['|||STRONG|||', '|||/STRONG|||'], ['<strong>', '</strong>'], $item);
            $html .= '<li>' . $item . '</li>';
        } else {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $line = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line);
            $line = str_replace(['<strong>', '</strong>'], ['|||STRONG|||', '|||/STRONG|||'], $line);
            $line = htmlspecialchars($line, ENT_NOQUOTES);
            $line = str_replace(['|||STRONG|||', '|||/STRONG|||'], ['<strong>', '</strong>'], $line);
            $html .= '<p>' . $line . '</p>';
        }
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html;
}

function parse_api_datetime($value)
{
    if (empty($value)) {
        return null;
    }

    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    $normalized = str_replace('/', '-', trim((string) $value));
    $timestamp = strtotime($normalized);

    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}

$lockFile = sys_get_temp_dir() . '/sync_2phase.lock';
$lock = fopen($lockFile, 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "⚠ Já existe um processo de sincronização rodando.\n";
    exit;
}

$now = date('Y-m-d H:i:s');
echo "🚀 Iniciando sincronização em duas fases em {$now}\n";
echo "📌 Versão: 3.0 - Backend Lumen + PostgreSQL\n";

// ============== HELPERS DE API ==============

function call_api_get($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'token: ' . API_TOKEN,
            'User-Agent: Sync-Worker-Backend/3.0'
        ]
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "⚠ API retornou HTTP {$httpCode}\n";
        return null;
    }
    
    $data = json_decode($resp, true);
    return is_array($data) ? $data : null;
}

// ============== GEOCODIFICAÇÃO NOMINATIM ==============

function geocode_address($endereco, $codigo = null)
{
    static $geocode_cache = [];
    
    $logradouro = trim($endereco['logradouro'] ?? '');
    $numero = trim($endereco['numero'] ?? '');
    $bairro = trim($endereco['bairro'] ?? '');
    $cidade = trim($endereco['cidade'] ?? '');
    $estado = strtoupper(trim($endereco['estado'] ?? ''));
    $cep = preg_replace('/\\D/', '', $endereco['cep'] ?? '');
    
    if (empty($cidade)) {
        $cidade = 'Belo Horizonte';
        $estado = $estado ?: 'MG';
    }
    
    $cacheKey = md5(json_encode([$logradouro, $numero, $bairro, $cidade, $estado, $cep]));
    if (isset($geocode_cache[$cacheKey])) {
        return $geocode_cache[$cacheKey];
    }
    
    if (empty($bairro) && empty($logradouro) && empty($cidade)) {
        $geocode_cache[$cacheKey] = ['lat' => null, 'lng' => null];
        return $geocode_cache[$cacheKey];
    }
    
    echo "   [INFO] Geocodificando imovel " . ($codigo ?? 'N/A') . "...
";
    
    if (!empty($cep)) {
        $coords = geocode_via_cep($cep);
        if ($coords['lat'] && $coords['lng']) {
            echo "   [OK] Coordenadas via ViaCEP: {$coords['lat']}, {$coords['lng']}
";
            $geocode_cache[$cacheKey] = $coords;
            return $coords;
        }
    }
    
    $queries = [];
    if ($logradouro && $numero) {
        $queries[] = "{$logradouro}, {$numero}, {$bairro}, {$cidade}, {$estado}, Brasil";
    }
    if ($logradouro) {
        $queries[] = "{$logradouro}, {$bairro}, {$cidade}, {$estado}, Brasil";
    }
    if ($bairro) {
        $queries[] = "{$bairro}, {$cidade}, {$estado}, Brasil";
    }
    $queries[] = "{$cidade}, {$estado}, Brasil";
    
    foreach ($queries as $query) {
        $coords = nominatim_search($query);
        if ($coords['lat'] && $coords['lng']) {
            echo "   [OK] Geocodificado: {$query} -> {$coords['lat']}, {$coords['lng']}
";
            $geocode_cache[$cacheKey] = $coords;
            return $coords;
        }
    }
    
    if ($estado) {
        $coords = get_state_coordinates($estado);
        if ($coords['lat'] && $coords['lng']) {
            echo "   [WARN] Coordenadas aproximadas do estado {$estado}: {$coords['lat']}, {$coords['lng']}
";
            $geocode_cache[$cacheKey] = $coords;
            return $coords;
        }
    }
    
    echo "   [WARN] Nao foi possivel geocodificar: {$bairro}, {$cidade}, {$estado}
";
    $geocode_cache[$cacheKey] = ['lat' => null, 'lng' => null];
    return $geocode_cache[$cacheKey];
}

function geocode_via_cep($cep)
{
    $cep = preg_replace('/\\D/', '', $cep);
    if (strlen($cep) !== 8) {
        return ['lat' => null, 'lng' => null];
    }
    
    $url = "https://viacep.com.br/ws/{$cep}/json/";
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET',
            'header' => "User-Agent: Exclusiva-Lar-Sync/3.0

"
        ]
    ]);
    
    $resp = @file_get_contents($url, false, $context);
    if ($resp === false) {
        return ['lat' => null, 'lng' => null];
    }
    
    $data = json_decode($resp, true);
    if (empty($data) || !empty($data['erro'])) {
        return ['lat' => null, 'lng' => null];
    }
    
    $parts = array_filter([
        $data['logradouro'] ?? null,
        $data['bairro'] ?? null,
        ($data['localidade'] ?? '') . ' - ' . ($data['uf'] ?? ''),
        'Brasil'
    ]);
    
    if (empty($parts)) {
        return ['lat' => null, 'lng' => null];
    }
    
    $query = implode(', ', $parts);
    return nominatim_search($query);
}

function nominatim_search($query)
{
    static $lastCall = 0;
    
    $now = microtime(true);
    if ($lastCall > 0) {
        $elapsed = $now - $lastCall;
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
            'User-Agent: Exclusiva-Lar-Sync/3.0 (contato@exclusivalarimoveis.com.br)'
        ]
    ]);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $lastCall = microtime(true);
    
    if ($httpCode !== 200) {
        return ['lat' => null, 'lng' => null];
    }
    
    $data = json_decode($resp, true);
    
    if (is_array($data) && count($data) > 0) {
        return [
            'lat' => isset($data[0]['lat']) ? (float)$data[0]['lat'] : null,
            'lng' => isset($data[0]['lon']) ? (float)$data[0]['lon'] : null
        ];
    }
    
    return ['lat' => null, 'lng' => null];
}

function get_state_coordinates($estado)
{
    $coords = [
        'AC' => ['lat' => -9.0238, 'lng' => -70.8120],
        'AL' => ['lat' => -9.5713, 'lng' => -36.7820],
        'AP' => ['lat' => 1.4061, 'lng' => -51.6022],
        'AM' => ['lat' => -3.4168, 'lng' => -65.8561],
        'BA' => ['lat' => -12.5797, 'lng' => -41.7007],
        'CE' => ['lat' => -5.4984, 'lng' => -39.3206],
        'DF' => ['lat' => -15.7998, 'lng' => -47.8645],
        'ES' => ['lat' => -19.1834, 'lng' => -40.3089],
        'GO' => ['lat' => -15.8270, 'lng' => -49.8362],
        'MA' => ['lat' => -4.9609, 'lng' => -45.2744],
        'MT' => ['lat' => -12.6819, 'lng' => -56.9211],
        'MS' => ['lat' => -20.7722, 'lng' => -54.7852],
        'MG' => ['lat' => -19.9167, 'lng' => -43.9345],
        'PA' => ['lat' => -3.7970, 'lng' => -52.4751],
        'PB' => ['lat' => -7.2399, 'lng' => -36.7819],
        'PR' => ['lat' => -24.8940, 'lng' => -51.5555],
        'PE' => ['lat' => -8.8137, 'lng' => -36.9541],
        'PI' => ['lat' => -6.6000, 'lng' => -42.2800],
        'RJ' => ['lat' => -22.9068, 'lng' => -43.1729],
        'RN' => ['lat' => -5.4026, 'lng' => -36.9541],
        'RS' => ['lat' => -30.0346, 'lng' => -51.2177],
        'RO' => ['lat' => -10.9472, 'lng' => -62.8278],
        'RR' => ['lat' => 1.3227, 'lng' => -60.6522],
        'SC' => ['lat' => -27.2423, 'lng' => -50.2189],
        'SP' => ['lat' => -23.5505, 'lng' => -46.6333],
        'SE' => ['lat' => -10.5741, 'lng' => -37.3857],
        'TO' => ['lat' => -10.1753, 'lng' => -48.2982],
    ];
    
    $estado = strtoupper($estado);
    return $coords[$estado] ?? ['lat' => null, 'lng' => null];
}


// ============== FASE 1: SALVAR LISTA COMPLETA ==============

function upsert_basico($row)
{
    // APENAS VENDAS - Sistema exclusivo para vendas
    $finalidade_raw = $row['finalidadeImovel'] ?? 'Venda';
    
    // Ignorar imóveis que não sejam exclusivos de venda
    if (stripos($finalidade_raw, 'aluguel') !== false || stripos($finalidade_raw, 'locação') !== false) {
        return null; // Pula este imóvel
    }
    
    $finalidade = 'Venda';

    $apiCreatedAt = parse_api_datetime($row['dataInsercaoImovel'] ?? null);
    $apiUpdatedAt = parse_api_datetime($row['ultimaAtualizacaoImovel'] ?? null);

    $baseWhere = ['codigo_imovel' => $row['codigoImovel']];

    // Buscar registro atual para evitar sobrescrever dados bons desnecessariamente
    $current = DB::table('imo_properties')
        ->where($baseWhere)
        ->first();

    $update = [
        'codigo_imovel' => $row['codigoImovel'],
        'referencia_imovel' => $row['referenciaImovel'] ?? ($current->referencia_imovel ?? null),
        'finalidade_imovel' => $finalidade,
        'tipo_imovel' => $row['descricaoTipoImovel'] ?? ($current->tipo_imovel ?? 'Residencial'),
        'api_created_at' => $apiCreatedAt ?: ($current->api_created_at ?? null),
        'api_updated_at' => $apiUpdatedAt ?: ($current->api_updated_at ?? null),
    ];

    // Só atualiza o campo active se a API enviar status explicitamente
    if (array_key_exists('statusImovel', $row)) {
        $update['active'] = ($row['statusImovel'] ?? false) ? 1 : 0;
    } elseif ($current) {
        $update['active'] = $current->active;
    } else {
        $update['active'] = 1; // novo imóvel: assume ativo
    }

    if ($current) {
        DB::table('imo_properties')
            ->where($baseWhere)
            ->update($update);
    } else {
        DB::table('imo_properties')->insert($update);
    }
}

echo "\n📋 FASE 1: Salvando lista completa de imóveis...\n";
echo "════════════════════════════════════════════════════════════════\n";

$page = 1;
$totalSaved = 0;
$maxPages = 999;

do {
    $url = API_BASE . "/lista?status=ativo&page={$page}&per_page=100";
    echo "📄 Página {$page}: {$url}\n";
    
    $lista = call_api_get($url);
    
    if (!$lista || !($lista['status'] ?? false)) {
        echo "⚠ Falha ao obter página {$page}. Parando.\n";
        break;
    }
    
    $rs = $lista['resultSet'] ?? [];
    $data = $rs['data'] ?? [];
    $totalPages = (int)($rs['total_pages'] ?? 1);
    
    echo "   ✓ Encontrados " . count($data) . " imóveis (total de páginas: {$totalPages})\n";
    
    foreach ($data as $row) {
        upsert_basico($row);
        $totalSaved++;
    }
    
    $page++;
    
    if ($page > $maxPages) {
        echo "⚠ Atingido limite de {$maxPages} páginas. Parando.\n";
        break;
    }
    
} while ($page <= $totalPages);

echo "\n✅ FASE 1 CONCLUÍDA: {$totalSaved} imóveis salvos/atualizados\n";
echo "════════════════════════════════════════════════════════════════\n";

// ============== FASE 2: BUSCAR DETALHES ==============

echo "\n📝 FASE 2: Buscando detalhes dos imóveis...\n";
echo "════════════════════════════════════════════════════════════════\n";

$fourHoursAgo = date('Y-m-d H:i:s', strtotime('-4 hours'));

if (defined('FORCE_FULL_UPDATE') && FORCE_FULL_UPDATE) {
    echo "⚡ MODO FORÇADO: Atualizando TODOS os imóveis (ignorando cache)\n\n";
    $ids = DB::table('imo_properties')
        ->where('active', true)
        ->orderByRaw('COALESCE(updated_at, "1970-01-01 00:00:00") ASC')
        ->pluck('codigo_imovel')
        ->toArray();
} else {
    $ids = DB::table('imo_properties')
        ->where(function($query) use ($fourHoursAgo) {
            $query->whereNull('descricao')
                  ->orWhereNull('cidade')
                  ->orWhereNull('api_updated_at')
                  ->orWhereNull('updated_at')
                  ->orWhereColumn('updated_at', '<', 'api_updated_at');
        })
        ->orderByRaw('COALESCE(updated_at, "1970-01-01 00:00:00") ASC')
        ->pluck('codigo_imovel')
        ->toArray();
}

echo "   ℹ️  Total de imóveis para atualizar: " . count($ids) . "\n\n";

$updated = 0;
$errors = 0;

foreach ($ids as $codigo) {
    try {
        $url = API_BASE . "/dados/{$codigo}";
        $det = call_api_get($url);
        
        if (!$det || !($det['status'] ?? false)) {
            echo "⚠ Falha ao obter detalhes do imóvel {$codigo}\n";
            $errors++;
            continue;
        }
        
        $imovel = $det['resultSet'];
        
        // Contar imagens antes de salvar (para logging)
        $numImagens = 0;
        if (isset($imovel['imagens']) && is_array($imovel['imagens'])) {
            foreach ($imovel['imagens'] as $img) {
                if (isset($img['url']) && !empty($img['url'])) {
                    $numImagens++;
                }
            }
        }
        
        upsert_detalhes($imovel);
        
        echo "✓ Imóvel {$codigo} atualizado ({$numImagens} imagens)\n";
        $updated++;
        
        usleep(100000); // 0.1s entre requisições
        
    } catch (Exception $e) {
        echo "❌ Erro ao processar imóvel {$codigo}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n✅ FASE 2 CONCLUÍDA: {$updated} imóveis atualizados, {$errors} erros\n";
echo "════════════════════════════════════════════════════════════════\n";

// ============== FUNÇÃO DE ATUALIZAÇÃO DETALHADA ==============

function upsert_detalhes($d)
{
    // Áreas
    $area_privativa = null;
    if (isset($d['area']['privativa']['valor'])) {
        $area_privativa = (float)str_replace(',', '.', $d['area']['privativa']['valor']);
    }
    
    $area_total = null;
    if (isset($d['area']['total']['valor'])) {
        $area_total = (float)str_replace(',', '.', $d['area']['total']['valor']);
    }
    
    $area_terreno = null;
    if (isset($d['area']['terreno']['valor'])) {
        $area_terreno = (float)str_replace(',', '.', $d['area']['terreno']['valor']);
    }
    
    $codigo = $d['codigoImovel'];
    $apiUpdatedAt = parse_api_datetime($d['ultimaAtualizacaoImovel'] ?? null);
    
    // APENAS VENDAS - Sistema exclusivo para vendas
    $finalidade_raw = $d['finalidadeImovel'] ?? 'Venda';
    
    // Ignorar imóveis que não sejam exclusivos de venda
    if (stripos($finalidade_raw, 'aluguel') !== false || stripos($finalidade_raw, 'locação') !== false) {
        return; // Pula este imóvel
    }
    
    $finalidade = 'Venda';
    
    // Apenas valor de venda
    $valor_venda = $d['valorEsperado'] ?? null;
    $valor_aluguel = null;
    
    // Coordenadas - tentar da API primeiro, depois geocodificar
    $latitude = $d['endereco']['latitude'] ?? null;
    $longitude = $d['endereco']['longitude'] ?? null;
    
    // Se não tem coordenadas, tentar geocodificar pelo endereço
    if (empty($latitude) || empty($longitude)) {
        $coords = geocode_address($d['endereco'] ?? [], $codigo);
        $latitude = $coords['lat'];
        $longitude = $coords['lng'];
    }
    
    // Coletar imagens em array JSON
    $imagens = [];
    $imagem_destaque = null;
    if (!empty($d['imagens']) && is_array($d['imagens'])) {
        foreach ($d['imagens'] as $img) {
            // Garantir que a URL existe antes de adicionar
            if (isset($img['url']) && !empty($img['url'])) {
                $imagens[] = [
                    'url' => $img['url'],
                    'destaque' => (bool)($img['destaque'] ?? false)
                ];
                if (($img['destaque'] ?? false) && !$imagem_destaque) {
                    $imagem_destaque = $img['url'];
                }
            }
        }
    }
    
    // Se não tem imagem destaque, pega a primeira
    if (!$imagem_destaque && !empty($imagens)) {
        $imagem_destaque = $imagens[0]['url'];
    }
    
    // Coletar características em array JSON
    $caracteristicas = [];
    if (!empty($d['caracteristicas'])) {
        foreach ($d['caracteristicas'] as $c) {
            $caracteristicas[] = $c['nomeCaracteristica'];
        }
    }
    
    $current = DB::table('imo_properties')
        ->where('codigo_imovel', $codigo)
        ->first();

    $update = [];

    // Campos básicos sempre atualizados a partir da API
    $update['finalidade_imovel'] = $finalidade;
    $update['tipo_imovel'] = $d['descricaoTipoImovel'] ?? ($current->tipo_imovel ?? 'Residencial');

    // Numéricos: só atualiza se a API trouxer algo não nulo
    foreach ([
        'dormitorios' => 'dormitorios',
        'suites' => 'suites',
        'banheiros' => 'banheiros',
        'garagem' => 'garagem',
    ] as $dbField => $apiField) {
        if (array_key_exists($apiField, $d) && $d[$apiField] !== null) {
            $update[$dbField] = (int)$d[$apiField];
        }
    }

    // Valores financeiros
    if ($valor_venda !== null) {
        $update['valor_venda'] = $valor_venda;
    }
    if ($valor_aluguel !== null) {
        $update['valor_aluguel'] = $valor_aluguel;
    }
    if (array_key_exists('valorIPTU', $d) && $d['valorIPTU'] !== null) {
        $update['iptu'] = $d['valorIPTU'];
    }
    if (array_key_exists('valorCondominio', $d) && $d['valorCondominio'] !== null) {
        $update['condominio'] = $d['valorCondominio'];
    }

    // Endereço: só atualiza se vier valor não vazio
    $endereco = $d['endereco'] ?? [];
    foreach ([
        'cidade' => 'cidade',
        'estado' => 'estado',
        'bairro' => 'bairro',
        'endereco' => 'logradouro',
        'numero' => 'numero',
        'cep' => 'cep',
    ] as $dbField => $apiField) {
        if (isset($endereco[$apiField]) && $endereco[$apiField] !== '') {
            $update[$dbField] = $endereco[$apiField];
        }
    }

    // Coordenadas: só sobrescreve se conseguimos algo válido
    if ($latitude !== null && $longitude !== null) {
        $update['latitude'] = $latitude;
        $update['longitude'] = $longitude;
    }

    // Áreas
    if ($area_privativa !== null) {
        $update['area_privativa'] = $area_privativa;
    }
    if ($area_total !== null) {
        $update['area_total'] = $area_total;
    }
    if ($area_terreno !== null) {
        $update['area_terreno'] = $area_terreno;
    }

    // Descrição: só atualiza se vier texto útil
    if (!empty($d['descricaoImovel'])) {
        $update['descricao'] = format_description_html($d['descricaoImovel']);
    }

    // Imagens: só atualiza se tiver pelo menos uma imagem válida
    if (!empty($imagens)) {
        $update['imagem_destaque'] = $imagem_destaque;
        $update['imagens'] = json_encode($imagens);
    }

    // Características: idem
    if (!empty($caracteristicas)) {
        $update['caracteristicas'] = json_encode($caracteristicas);
    }

    // Flags booleanas: só se a API enviar a chave
    foreach ([
        'em_condominio' => 'emCondominio',
        'aceita_financiamento' => 'aceitaFinanciamento',
        'exibir_imovel' => 'exibirImovel',
    ] as $dbField => $apiField) {
        if (array_key_exists($apiField, $d)) {
            $update[$dbField] = $d[$apiField] ? 1 : 0;
        }
    }

    // Metadados sempre atualizados
    $update['api_updated_at'] = $apiUpdatedAt ?: ($current->api_updated_at ?? null);
    $update['api_data'] = json_encode($d);
    $update['updated_at'] = date('Y-m-d H:i:s');

    DB::table('imo_properties')
        ->where('codigo_imovel', $codigo)
        ->update($update);
}

echo "\n🎉 SINCRONIZAÇÃO COMPLETA!\n";
echo "Total salvo na fase 1: {$totalSaved}\n";
echo "Total atualizado na fase 2: {$updated}\n";
echo "Erros: {$errors}\n";

// Verificar quantos imóveis têm imagens
$comImagens = DB::table('imo_properties')
    ->whereNotNull('imagens')
    ->where('imagens', '!=', '[]')
    ->where('imagens', '!=', '')
    ->count();

echo "Imóveis com imagens: {$comImagens}\n\n";

flock($lock, LOCK_UN);
fclose($lock);
