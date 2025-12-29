<?php

/**
 * Script para corrigir leads duplicados e criar leads faltantes
 * - Remove duplicações de leads (mesmo telefone)
 * - Cria leads para conversas sem lead_id
 * - Cria clientes (users) para leads sem user_id
 * 
 * Uso via cURL:
 * curl -X POST http://127.0.0.1:8000/fix_leads_duplicados.php \
 *   -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=" \
 *   -H "Content-Type: application/json"
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Carregar .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Autenticação simples
$secretHeader = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$expectedSecret = $_ENV['DEPLOY_SECRET'] ?? 'ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=';

// Permitir execução via CLI sem autenticação
if (php_sapi_name() !== 'cli' && $secretHeader !== $expectedSecret) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Header X-Admin-Secret inválido'
    ]);
    exit;
}

// Capturar output para retornar JSON se for HTTP
$isHttp = php_sapi_name() !== 'cli';
if ($isHttp) {
    ob_start();
    header('Content-Type: application/json');
}

// Configurar banco de dados
$capsule = new DB;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'      => $_ENV['DB_PORT'] ?? '3306',
    'database'  => $_ENV['DB_DATABASE'] ?? 'exclusiva',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔧 CORREÇÃO DE LEADS DUPLICADOS E FALTANTES                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// =====================================================
// 1. IDENTIFICAR E REMOVER LEADS DUPLICADOS
// =====================================================
echo "📋 Etapa 1: Identificar leads duplicados\n";
echo "─────────────────────────────────────────────────────────────\n";

$duplicados = DB::table('leads')
    ->select('telefone', DB::raw('COUNT(*) as total'))
    ->whereNotNull('telefone')
    ->where('telefone', '!=', '')
    ->groupBy('telefone')
    ->having('total', '>', 1)
    ->get();

echo "Encontrados " . count($duplicados) . " telefones duplicados\n\n";

$totalRemovidos = 0;
$totalMerged = 0;

foreach ($duplicados as $dup) {
    $telefone = $dup->telefone;
    
    echo "📞 Telefone: $telefone (Total: {$dup->total} leads)\n";
    
    // Buscar todos os leads com este telefone
    $leads = DB::table('leads')
        ->where('telefone', $telefone)
        ->orderBy('created_at', 'ASC')
        ->get();
    
    // Manter o primeiro (mais antigo)
    $leadPrincipal = $leads->first();
    $leadsParaRemover = $leads->slice(1);
    
    echo "   ✓ Mantendo Lead ID: {$leadPrincipal->id} (criado em: {$leadPrincipal->created_at})\n";
    
    foreach ($leadsParaRemover as $leadDup) {
        echo "   ⊗ Removendo Lead ID: {$leadDup->id} (criado em: {$leadDup->created_at})\n";
        
        // Atualizar conversas que apontam para o lead duplicado
        $conversasAtualizadas = DB::table('conversas')
            ->where('lead_id', $leadDup->id)
            ->update(['lead_id' => $leadPrincipal->id]);
        
        if ($conversasAtualizadas > 0) {
            echo "      → {$conversasAtualizadas} conversa(s) reatribuída(s)\n";
        }
        
        // Atualizar matches de imóveis
        $matchesAtualizados = DB::table('lead_property_matches')
            ->where('lead_id', $leadDup->id)
            ->update(['lead_id' => $leadPrincipal->id]);
        
        if ($matchesAtualizados > 0) {
            echo "      → {$matchesAtualizados} match(es) de imóveis reatribuído(s)\n";
        }
        
        // Mesclar dados se o principal estiver vazio
        $updates = [];
        if (empty($leadPrincipal->nome) && !empty($leadDup->nome)) {
            $updates['nome'] = $leadDup->nome;
        }
        if (empty($leadPrincipal->email) && !empty($leadDup->email)) {
            $updates['email'] = $leadDup->email;
        }
        if (empty($leadPrincipal->whatsapp_name) && !empty($leadDup->whatsapp_name)) {
            $updates['whatsapp_name'] = $leadDup->whatsapp_name;
        }
        if (empty($leadPrincipal->localizacao) && !empty($leadDup->localizacao)) {
            $updates['localizacao'] = $leadDup->localizacao;
        }
        if (!$leadPrincipal->user_id && $leadDup->user_id) {
            $updates['user_id'] = $leadDup->user_id;
        }
        
        if (!empty($updates)) {
            DB::table('leads')->where('id', $leadPrincipal->id)->update($updates);
            echo "      → Dados mesclados: " . implode(', ', array_keys($updates)) . "\n";
            $totalMerged++;
        }
        
        // Deletar o lead duplicado
        DB::table('leads')->where('id', $leadDup->id)->delete();
        $totalRemovidos++;
    }
    
    echo "\n";
}

echo "✅ Etapa 1 concluída: $totalRemovidos leads duplicados removidos, $totalMerged leads mesclados\n\n";

// =====================================================
// 2. CRIAR LEADS PARA CONVERSAS SEM LEAD_ID
// =====================================================
echo "📋 Etapa 2: Criar leads faltantes para conversas\n";
echo "─────────────────────────────────────────────────────────────\n";

$conversasSemLead = DB::table('conversas')
    ->whereNull('lead_id')
    ->orWhere('lead_id', 0)
    ->get();

echo "Encontradas " . count($conversasSemLead) . " conversas sem lead\n\n";

$leadsCriados = 0;

foreach ($conversasSemLead as $conversa) {
    $telefone = $conversa->telefone;
    
    echo "💬 Conversa ID: {$conversa->id} - Telefone: $telefone\n";
    
    // Verificar se já existe lead com este telefone
    $leadExistente = DB::table('leads')
        ->where('telefone', $telefone)
        ->where(function($query) use ($conversa) {
            if ($conversa->tenant_id) {
                $query->where('tenant_id', $conversa->tenant_id);
            }
        })
        ->first();
    
    if ($leadExistente) {
        echo "   ✓ Lead existente encontrado (ID: {$leadExistente->id})\n";
        DB::table('conversas')
            ->where('id', $conversa->id)
            ->update(['lead_id' => $leadExistente->id]);
        echo "   → Conversa vinculada ao lead existente\n\n";
        continue;
    }
    
    // Criar novo lead
    $leadId = DB::table('leads')->insertGetId([
        'nome' => $conversa->whatsapp_name ?: 'Contato WhatsApp',
        'whatsapp_name' => $conversa->whatsapp_name,
        'telefone' => $telefone,
        'status' => 'novo',
        'origem' => 'whatsapp',
        'tenant_id' => $conversa->tenant_id,
        'primeira_interacao' => $conversa->iniciada_em ?: date('Y-m-d H:i:s'),
        'ultima_interacao' => $conversa->updated_at ?: date('Y-m-d H:i:s'),
        'created_at' => $conversa->created_at ?: date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "   🆕 Novo lead criado (ID: $leadId)\n";
    
    // Vincular conversa ao lead
    DB::table('conversas')
        ->where('id', $conversa->id)
        ->update(['lead_id' => $leadId]);
    
    echo "   → Conversa vinculada ao novo lead\n";
    
    $leadsCriados++;
    echo "\n";
}

echo "✅ Etapa 2 concluída: $leadsCriados leads criados para conversas\n\n";

// =====================================================
// 3. CRIAR CLIENTES PARA LEADS SEM USER_ID
// =====================================================
echo "📋 Etapa 3: Criar clientes para leads sem user_id\n";
echo "─────────────────────────────────────────────────────────────\n";

$leadsSemCliente = DB::table('leads')
    ->whereNull('user_id')
    ->orWhere('user_id', 0)
    ->get();

echo "Encontrados " . count($leadsSemCliente) . " leads sem cliente\n\n";

$clientesCriados = 0;

foreach ($leadsSemCliente as $lead) {
    echo "👤 Lead ID: {$lead->id} - {$lead->nome} ({$lead->telefone})\n";
    
    // Verificar se já existe cliente com este email
    $clienteExistente = null;
    if (!empty($lead->email)) {
        $clienteExistente = DB::table('users')
            ->where('email', $lead->email)
            ->first();
    }
    
    if ($clienteExistente) {
        echo "   ✓ Cliente existente encontrado (ID: {$clienteExistente->id})\n";
        DB::table('leads')
            ->where('id', $lead->id)
            ->update(['user_id' => $clienteExistente->id]);
        echo "   → Lead vinculado ao cliente existente\n\n";
        continue;
    }
    
    // Gerar email placeholder se necessário
    $email = $lead->email;
    if (empty($email)) {
        $tenantPart = $lead->tenant_id ?: 0;
        $email = "lead-{$tenantPart}-{$lead->id}@no-email.local";
        
        // Garantir que o email seja único
        $suffix = 1;
        while (DB::table('users')->where('email', $email)->exists()) {
            $email = "lead-{$tenantPart}-{$lead->id}-{$suffix}@no-email.local";
            $suffix++;
        }
    }
    
    // Criar cliente
    $userId = DB::table('users')->insertGetId([
        'name' => $lead->nome ?: ($lead->whatsapp_name ?: 'Cliente'),
        'email' => $email,
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'role' => 'client',
        'is_active' => 1,
        'tenant_id' => $lead->tenant_id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "   🆕 Novo cliente criado (ID: $userId - Email: $email)\n";
    
    // Vincular lead ao cliente
    DB::table('leads')
        ->where('id', $lead->id)
        ->update(['user_id' => $userId]);
    
    echo "   → Lead vinculado ao novo cliente\n";
    
    $clientesCriados++;
    echo "\n";
}

echo "✅ Etapa 3 concluída: $clientesCriados clientes criados\n\n";

// =====================================================
// RESUMO FINAL
// =====================================================
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📊 RESUMO FINAL                                             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$totalLeads = DB::table('leads')->count();
$totalConversas = DB::table('conversas')->count();
$conversasComLead = DB::table('conversas')->whereNotNull('lead_id')->where('lead_id', '>', 0)->count();
$leadsComCliente = DB::table('leads')->whereNotNull('user_id')->where('user_id', '>', 0)->count();

echo "📈 Estatísticas:\n";
echo "   • Total de leads: $totalLeads\n";
echo "   • Total de conversas: $totalConversas\n";
echo "   • Conversas com lead: $conversasComLead\n";
echo "   • Leads com cliente: $leadsComCliente\n\n";

echo "🔧 Ações realizadas:\n";
echo "   • Leads duplicados removidos: $totalRemovidos\n";
echo "   • Leads mesclados: $totalMerged\n";
echo "   • Leads criados: $leadsCriados\n";
echo "   • Clientes criados: $clientesCriados\n\n";

echo "✅ Script concluído com sucesso!\n";

// Se for HTTP, retornar JSON
if ($isHttp) {
    $output = ob_get_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Script executado com sucesso',
        'estatisticas' => [
            'total_leads' => $totalLeads,
            'total_conversas' => $totalConversas,
            'conversas_com_lead' => $conversasComLead,
            'leads_com_cliente' => $leadsComCliente
        ],
        'acoes' => [
            'leads_duplicados_removidos' => $totalRemovidos,
            'leads_mesclados' => $totalMerged,
            'leads_criados' => $leadsCriados,
            'clientes_criados' => $clientesCriados
        ],
        'log' => $output
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
