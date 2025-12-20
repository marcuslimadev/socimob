<?php
/**
 * Script para atualizar descrições existentes para formato HTML
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

set_time_limit(0);

/**
 * Formata descrição de texto plano para HTML
 */
function format_description_html($text) {
    if (empty($text)) {
        return null;
    }
    
    // Remove espaços extras
    $text = trim($text);
    
    // Converte quebras de linha em <br> temporariamente
    $text = str_replace(["\r\n", "\r", "\n"], "|||BR|||", $text);
    
    // Processa emojis e marcadores especiais
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
        
        // Detecta títulos com emojis (🏡, ✨, 🌟, 💰, 🚗, 📄, 🎯, 🔑, ⭐, 🎉)
        if (preg_match('/^[🏡✨🌟💰🚗📄🎯🔑⭐🎉]/', $line)) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h3>' . htmlspecialchars($line) . '</h3>';
        }
        // Detecta itens de lista (começa com -, *, •, ou emoji seguido de **)
        elseif (preg_match('/^[\-\*•]/', $line) || preg_match('/^\*\*/', $line)) {
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            // Remove o marcador inicial
            $item = preg_replace('/^[\-\*•]\s*/', '', $line);
            $item = preg_replace('/^\*\*([^:]+):\*\*/', '<strong>$1:</strong>', $item);
            $html .= '<li>' . htmlspecialchars($item, ENT_NOQUOTES) . '</li>';
        }
        // Linha normal
        else {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            // Detecta negrito **texto**
            $line = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line);
            $html .= '<p>' . htmlspecialchars($line, ENT_NOQUOTES) . '</p>';
        }
    }
    
    if ($inList) {
        $html .= '</ul>';
    }
    
    return $html;
}

echo "🔄 Iniciando conversão de descrições para HTML...\n\n";

// Buscar todos os imóveis com descrição
$properties = DB::table('imo_properties')
    ->whereNotNull('descricao')
    ->where('descricao', '!=', '')
    ->select('id', 'codigo_imovel', 'descricao')
    ->get();

echo "📊 Total de imóveis com descrição: " . count($properties) . "\n\n";

$updated = 0;
$skipped = 0;

foreach ($properties as $prop) {
    // Verifica se já está em HTML (contém tags)
    if (strpos($prop->descricao, '<') !== false) {
        $skipped++;
        echo "⏭  Imóvel {$prop->codigo_imovel}: Já está em HTML\n";
        continue;
    }
    
    $htmlDesc = format_description_html($prop->descricao);
    
    if ($htmlDesc) {
        DB::table('imo_properties')
            ->where('id', $prop->id)
            ->update(['descricao' => $htmlDesc]);
        
        $updated++;
        echo "✅ Imóvel {$prop->codigo_imovel}: Convertido para HTML\n";
    }
}

echo "\n🎉 CONVERSÃO CONCLUÍDA!\n";
echo "Total atualizado: {$updated}\n";
echo "Total já em HTML: {$skipped}\n";
