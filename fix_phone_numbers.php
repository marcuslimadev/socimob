<?php

require_once __DIR__.'/vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "=== CORRIGINDO NÚMEROS DE TELEFONE ===\n\n";

// Função para formatar telefone
function fixPhoneNumber($phone) {
    if (empty($phone)) {
        return $phone;
    }
    
    // Remover caracteres não numéricos
    $digits = preg_replace('/[^0-9]/', '', $phone);
    
    // Se já começa com 55, não mexer
    if (str_starts_with($digits, '55')) {
        return $digits;
    }
    
    // Se é número brasileiro (10-11 dígitos sem DDI), adicionar 55
    $length = strlen($digits);
    if ($length >= 10 && $length <= 11) {
        return '55' . $digits;
    }
    
    // Outros casos, manter como está
    return $digits;
}

// Atualizar campo telefone
$leadsComTelefone = DB::table('leads')
    ->whereNotNull('telefone')
    ->where('telefone', '!=', '')
    ->get();

echo "Leads com telefone: " . $leadsComTelefone->count() . "\n";

$updatedTelefone = 0;
foreach ($leadsComTelefone as $lead) {
    $original = $lead->telefone;
    $fixed = fixPhoneNumber($original);
    
    if ($original !== $fixed) {
        DB::table('leads')
            ->where('id', $lead->id)
            ->update(['telefone' => $fixed]);
        
        echo "Lead {$lead->id}: {$original} → {$fixed}\n";
        $updatedTelefone++;
    }
}

echo "\nTelefones atualizados: {$updatedTelefone}\n\n";

// Atualizar campo whatsapp
$leadsComWhatsapp = DB::table('leads')
    ->whereNotNull('whatsapp')
    ->where('whatsapp', '!=', '')
    ->get();

echo "Leads com WhatsApp: " . $leadsComWhatsapp->count() . "\n";

$updatedWhatsapp = 0;
foreach ($leadsComWhatsapp as $lead) {
    $original = $lead->whatsapp;
    $fixed = fixPhoneNumber($original);
    
    if ($original !== $fixed) {
        DB::table('leads')
            ->where('id', $lead->id)
            ->update(['whatsapp' => $fixed]);
        
        echo "Lead {$lead->id}: {$original} → {$fixed}\n";
        $updatedWhatsapp++;
    }
}

echo "\nWhatsApp atualizados: {$updatedWhatsapp}\n";

echo "\n=== CORREÇÃO CONCLUÍDA ===\n";
echo "Total de números corrigidos: " . ($updatedTelefone + $updatedWhatsapp) . "\n";
