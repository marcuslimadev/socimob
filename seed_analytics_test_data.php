<?php
require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Configurar tenant_id (ajuste conforme necessário)
$tenantId = 1; // Altere para o ID do tenant que você está testando

echo "Populando dados de teste de analytics para tenant_id: $tenantId\n\n";

// Gerar session_ids únicos
$sessions = [];
for ($i = 0; $i < 10; $i++) {
    $sessions[] = \Illuminate\Support\Str::uuid()->toString();
}

// Dados de teste
$paths = [
    '/dashboard',
    '/imoveis',
    '/leads',
    '/chat',
    '/configuracoes',
    '/portal',
    '/relatorios'
];

$referrers = [
    'https://google.com',
    'https://facebook.com',
    'https://instagram.com',
    null,
    null,
    null
];

$devices = ['desktop', 'mobile', 'tablet'];
$oses = ['Windows', 'Android', 'iOS', 'macOS'];
$browsers = ['Chrome', 'Safari', 'Firefox', 'Edge'];

// Criar sessões
echo "Criando sessões...\n";
foreach ($sessions as $idx => $sessionId) {
    DB::table('analytics_sessions')->insert([
        'tenant_id' => $tenantId,
        'session_id' => $sessionId,
        'ip_hash' => hash('sha256', '127.0.0.1' . $idx),
        'user_agent' => 'Mozilla/5.0 (Test Agent)',
        'device_type' => $devices[array_rand($devices)],
        'os' => $oses[array_rand($oses)],
        'browser' => $browsers[array_rand($browsers)],
        'country' => 'BR',
        'region' => 'SP',
        'city' => 'São Paulo',
        'referrer' => $referrers[array_rand($referrers)],
        'landing_path' => $paths[array_rand($paths)],
        'consent_at' => Carbon::now()->subDays(rand(0, 30)),
        'first_seen_at' => Carbon::now()->subDays(rand(0, 30)),
        'last_seen_at' => Carbon::now()->subDays(rand(0, 5)),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);
}
echo "✓ " . count($sessions) . " sessões criadas\n\n";

// Criar eventos (pageviews)
echo "Criando eventos (pageviews)...\n";
$eventCount = 0;
for ($day = 30; $day >= 0; $day--) {
    $eventsPerDay = rand(5, 20);
    for ($i = 0; $i < $eventsPerDay; $i++) {
        $sessionId = $sessions[array_rand($sessions)];
        $path = $paths[array_rand($paths)];
        
        DB::table('analytics_events')->insert([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'user_id' => null,
            'event_name' => 'pageview',
            'path' => $path,
            'referrer' => $referrers[array_rand($referrers)],
            'properties' => null,
            'occurred_at' => Carbon::now()->subDays($day)->addHours(rand(0, 23))->addMinutes(rand(0, 59)),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $eventCount++;
    }
}
echo "✓ $eventCount eventos criados\n\n";

// Verificar dados
$totalSessions = DB::table('analytics_sessions')->where('tenant_id', $tenantId)->count();
$totalEvents = DB::table('analytics_events')->where('tenant_id', $tenantId)->count();

echo "Resumo:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total de Sessões: $totalSessions\n";
echo "Total de Eventos: $totalEvents\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ Dados de teste populados com sucesso!\n";
echo "Agora você pode acessar a página de Analytics e visualizar os dados.\n";
