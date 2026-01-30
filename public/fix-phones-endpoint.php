<?php
// Endpoint temporário para corrigir números

require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;

header('Content-Type: application/json');

try {
    // Contar total antes
    $totalAntes = DB::table('leads')->count();
    
    // Atualizar telefones
    $updatedTelefone = DB::update("
        UPDATE leads 
        SET telefone = CONCAT('55', telefone)
        WHERE telefone IS NOT NULL 
          AND telefone != ''
          AND telefone NOT LIKE '55%'
          AND CHAR_LENGTH(telefone) BETWEEN 10 AND 11
    ");
    
    // Atualizar whatsapp
    $updatedWhatsapp = DB::update("
        UPDATE leads 
        SET whatsapp = CONCAT('55', whatsapp)
        WHERE whatsapp IS NOT NULL 
          AND whatsapp != ''
          AND whatsapp NOT LIKE '55%'
          AND CHAR_LENGTH(whatsapp) BETWEEN 10 AND 11
    ");
    
    // Verificar resultados
    $comDDI = DB::table('leads')
        ->where(function($q) {
            $q->where('telefone', 'like', '55%')
              ->orWhere('whatsapp', 'like', '55%');
        })
        ->count();
    
    echo json_encode([
        'success' => true,
        'total_leads' => $totalAntes,
        'telefones_atualizados' => $updatedTelefone,
        'whatsapp_atualizados' => $updatedWhatsapp,
        'leads_com_ddi' => $comDDI
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
