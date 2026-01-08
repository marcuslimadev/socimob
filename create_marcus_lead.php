<?php
require __DIR__ . '/bootstrap/app.php';

try {
    $db = app('db')->connection();
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " CRIANDO LEAD PARA MARCUS\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    // Criar lead
    $now = \Illuminate\Support\Carbon::now();
    $leadId = $db->table('leads')->insertGetId([
        'tenant_id' => 1,
        'nome' => 'Marcus',
        'telefone' => '+5592992287144',
        'whatsapp' => '+5592992287144',
        'status' => 'novo',
        'observacoes' => 'Lead criado para teste de atendimento IA',
        'quartos' => 2,
        'created_at' => $now,
        'updated_at' => $now
    ]);
    
    echo "✅ Lead criado com ID: {$leadId}\n";
    echo "Nome: Marcus\n";
    echo "Telefone: +5592992287144\n";
    echo "Status: novo\n\n";
    
    // Iniciar atendimento IA
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " INICIANDO ATENDIMENTO IA\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $lead = $db->table('leads')->where('id', $leadId)->first();
    $leadModel = new \App\Models\Lead((array)$lead);
    $leadModel->id = $lead->id;
    $leadModel->exists = true;
    
    $automationService = new \App\Services\LeadAutomationService();
    $resultado = $automationService->iniciarAtendimento($leadModel, true);
    
    if ($resultado['success']) {
        echo "✅ SUCESSO!\n\n";
        echo "Conversa ID: {$resultado['conversa_id']}\n";
        echo "Mensagem enviada:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo wordwrap($resultado['mensagem'], 60, "\n") . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Verificar mensagem no banco
        $mensagem = $db->table('mensagens')
            ->where('conversa_id', $resultado['conversa_id'])
            ->orderBy('id', 'desc')
            ->first();
        
        if ($mensagem) {
            echo "📝 Mensagem registrada no banco:\n";
            echo "   ID: {$mensagem->id}\n";
            echo "   Direction: {$mensagem->direction}\n";
            echo "   Status: {$mensagem->status}\n";
            echo "   Sent at: {$mensagem->sent_at}\n\n";
        }
        
        // Verificar lead atualizado
        $leadAtualizado = $db->table('leads')->where('id', $leadId)->first();
        echo "📊 Lead atualizado:\n";
        echo "   Status: {$leadAtualizado->status}\n";
        echo "   Última interação: {$leadAtualizado->ultima_interacao}\n\n";
        
    } else {
        echo "❌ ERRO: {$resultado['error']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
