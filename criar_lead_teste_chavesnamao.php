<?php
/**
 * Script para criar Lead de Teste simulando Chaves na Mão
 * 
 * Uso: php criar_lead_teste_chavesnamao.php
 * 
 * Este lead vai disparar o atendimento automático IA se estiver ativado
 */

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Tenant;

echo "\n🧪 CRIAR LEAD DE TESTE (SIMULANDO CHAVES NA MÃO)\n";
echo "=================================================\n\n";

// Buscar tenant EXCLUSIVA
$tenant = Tenant::where('slug', 'exclusiva')->first();

if (!$tenant) {
    echo "❌ Tenant 'exclusiva' não encontrado!\n";
    echo "   Criando tenant...\n";
    
    $tenant = Tenant::create([
        'nome' => 'Exclusiva Lar Imóveis',
        'slug' => 'exclusiva',
        'dominio' => 'exclusiva.test',
        'is_active' => true,
    ]);
    
    echo "✅ Tenant criado: {$tenant->nome}\n\n";
}

echo "📋 Tenant encontrado:\n";
echo "   ID: {$tenant->id}\n";
echo "   Nome: {$tenant->nome}\n";
echo "   Slug: {$tenant->slug}\n\n";

// Dados do lead de teste
$dadosLead = [
    'tenant_id' => $tenant->id,
    'nome' => 'João Silva Teste',
    'email' => 'joao.teste@email.com',
    'telefone' => '+5531987654321', // Formato com + (WhatsApp)
    'status' => 'novo',
    'origem' => 'chavesnamao',
    // IMPORTANTE: Observações com "Chaves na Mão" para o Observer identificar
    'observacoes' => "Lead recebido via integração Chaves na Mão\nTeste de atendimento automático IA\nInteresse: Apartamento 2 quartos",
    'tipo_imovel' => 'Apartamento',
    'tipo_negocio' => 'compra',
    'valor_minimo' => 250000,
    'valor_maximo' => 400000,
    'quartos' => 2,
    'banheiros' => 1,
    'vagas_garagem' => 1,
    'bairro' => 'Centro',
    'cidade' => 'Belo Horizonte',
    'estado' => 'MG',
    'created_at' => now(),
    'updated_at' => now(),
];

echo "📝 Dados do Lead:\n";
foreach ($dadosLead as $key => $value) {
    if ($key === 'observacoes') {
        echo "   {$key}: [" . strlen($value) . " caracteres]\n";
    } else {
        echo "   {$key}: {$value}\n";
    }
}
echo "\n";

echo "⏳ Criando lead...\n";

try {
    $lead = Lead::create($dadosLead);
    
    echo "\n✅ LEAD CRIADO COM SUCESSO!\n";
    echo "=================================================\n";
    echo "   ID: {$lead->id}\n";
    echo "   Nome: {$lead->nome}\n";
    echo "   Email: {$lead->email}\n";
    echo "   Telefone: {$lead->telefone}\n";
    echo "   Status: {$lead->status}\n";
    echo "   Tenant: {$tenant->nome}\n";
    echo "=================================================\n\n";
    
    echo "🤖 ATENDIMENTO AUTOMÁTICO IA:\n";
    echo "-------------------------------------------\n";
    
    // Verificar se atendimento automático está ativo
    $atendimentoAtivo = \App\Models\AppSetting::getValue('atendimento_automatico_ativo', false, $tenant->id);
    
    if ($atendimentoAtivo) {
        echo "✅ Status: ATIVO\n";
        echo "📤 O LeadObserver deve ter disparado automaticamente!\n\n";
        echo "Verifique:\n";
        echo "  1. Logs: backend/storage/logs/lumen-" . date('Y-m-d') . ".log\n";
        echo "  2. Tabela 'conversas': SELECT * FROM conversas WHERE lead_id = {$lead->id};\n";
        echo "  3. Tabela 'mensagens': SELECT * FROM mensagens WHERE conversa_id IN (SELECT id FROM conversas WHERE lead_id = {$lead->id});\n";
    } else {
        echo "⚠️  Status: DESATIVADO\n";
        echo "📋 Para ativar:\n";
        echo "  1. Acesse: http://127.0.0.1:8000/app/configuracoes.html\n";
        echo "  2. Vá na aba 'Integrações'\n";
        echo "  3. Ative o toggle 'Atendimento Automático IA'\n\n";
        echo "📌 Ou dispare manualmente:\n";
        echo "  1. Acesse: http://127.0.0.1:8000/app/leads.html\n";
        echo "  2. Clique no botão 🤖 ao lado do lead\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO AO CRIAR LEAD:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\n   Stack trace:\n";
    echo "   " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n📌 CREDENCIAIS DE ACESSO:\n";
echo "-------------------------------------------\n";
echo "URL: http://127.0.0.1:8000/app/login.html\n\n";
echo "Super Admin:\n";
echo "  Email: admin@exclusiva.com\n";
echo "  Senha: password\n\n";
echo "Admin Imobiliária:\n";
echo "  Email: contato@exclusivalarimoveis.com.br\n";
echo "  Senha: [verificar no banco]\n\n";

echo "🎯 PRÓXIMOS PASSOS:\n";
echo "-------------------------------------------\n";
echo "1. Configure as credenciais no .env:\n";
echo "   EXCLUSIVA_TWILIO_ACCOUNT_SID=...\n";
echo "   EXCLUSIVA_TWILIO_AUTH_TOKEN=...\n";
echo "   EXCLUSIVA_TWILIO_WHATSAPP_FROM=whatsapp:+14155238886\n";
echo "   EXCLUSIVA_OPENAI_API_KEY=sk-proj-...\n\n";
echo "2. Ative o atendimento automático em Configurações\n\n";
echo "3. Crie outro lead de teste para ver funcionar:\n";
echo "   php criar_lead_teste_chavesnamao.php\n\n";

echo "✅ Script concluído!\n\n";
