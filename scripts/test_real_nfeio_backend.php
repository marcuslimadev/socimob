<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\FinanceiroController;
use App\Models\User;
use Illuminate\Http\Request;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$tenantId = (int)($argv[1] ?? 1);

$corretor = User::where('tenant_id', $tenantId)
    ->orderBy('id')
    ->first();

if (!$corretor) {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum usuário encontrado para o tenant informado',
        'tenant_id' => $tenantId,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$controller = $app->make(FinanceiroController::class);

$baseTomador = [
    'nome' => 'Tomador Teste NFEIO',
    'documento' => '52998224725',
    'email' => 'financeiro+teste@socimob.com',
    'telefone' => '31999990000',
    'endereco' => [
        'logradouro' => 'Rua São Miguel',
        'numero' => '1523',
        'bairro' => 'Itapoã',
        'cidade' => 'Belo Horizonte',
        'uf' => 'MG',
        'cep' => '31710-350',
        'codigoMunicipio' => '3106200',
    ],
];

$payloads = [
    'corretagem' => [
        'corretor_id' => $corretor->id,
        'tipo_nota' => 'corretagem',
        'valor' => 350.00,
        'aliquota_iss' => 5,
        'descricao' => 'Intermediação de corretagem imobiliária - teste real backend NFE.io',
        'competencia' => date('Y-m-d'),
        'tomador' => $baseTomador,
        'financeiro' => [
            'vencimento' => date('Y-m-d', strtotime('+5 days')),
            'forma_pagamento' => 'pix',
            'descricao' => 'Teste backend NFE.io - corretagem',
        ],
    ],
    'aluguel' => [
        'corretor_id' => $corretor->id,
        'tipo_nota' => 'aluguel',
        'valor' => 220.00,
        'aliquota_iss' => 5,
        'competencia' => date('Y-m-d'),
        'tomador' => $baseTomador,
        'financeiro' => [
            'vencimento' => date('Y-m-d', strtotime('+7 days')),
            'forma_pagamento' => 'boleto',
            'descricao' => 'Teste backend NFE.io - aluguel',
        ],
    ],
];

$results = [];
$hasError = false;

foreach ($payloads as $tipo => $payload) {
    $request = Request::create('/api/admin/financeiro/notas-servico', 'POST', $payload);
    $request->setUserResolver(static fn () => $corretor);

    $response = $controller->emitirNfseComissao($request);
    $statusCode = $response->getStatusCode();
    $bodyRaw = $response->getContent();
    $body = json_decode($bodyRaw, true);

    if ($statusCode >= 400) {
        $hasError = true;
    }

    $results[$tipo] = [
        'http_status' => $statusCode,
        'response' => $body ?? $bodyRaw,
    ];
}

echo json_encode([
    'executed_at' => date('c'),
    'php_binary' => PHP_BINARY,
    'tenant_id' => $tenantId,
    'corretor_id' => $corretor->id,
    'corretor_email' => $corretor->email,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

exit($hasError ? 1 : 0);
