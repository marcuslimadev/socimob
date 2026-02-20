<?php

declare(strict_types=1);

use App\Models\CommissionInvoice;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$invoiceIdsArg = $argv[1] ?? '1,2';
$invoiceIds = array_values(array_filter(array_map('intval', explode(',', $invoiceIdsArg))));

if (empty($invoiceIds)) {
    echo json_encode([
        'success' => false,
        'message' => 'Informe IDs válidos de invoices. Ex: 1,2',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$baseUrl = env('NFE_IO_BASE_URL', 'https://api.nfe.io');
$apiKey = env('NFE_IO_API_KEY');
$companyId = env('NFE_IO_COMPANY_ID');

if (!$apiKey || !$companyId) {
    echo json_encode([
        'success' => false,
        'message' => 'Credenciais NFE_IO_* não configuradas no .env',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$results = [];
$hasError = false;

$invoices = CommissionInvoice::query()
    ->whereIn('id', $invoiceIds)
    ->get();

foreach ($invoiceIds as $invoiceId) {
    $invoice = $invoices->firstWhere('id', $invoiceId);

    if (!$invoice) {
        $hasError = true;
        $results[] = [
            'invoice_id' => $invoiceId,
            'success' => false,
            'message' => 'Invoice não encontrada no banco local',
        ];
        continue;
    }

    if (empty($invoice->integracao_id)) {
        $hasError = true;
        $results[] = [
            'invoice_id' => $invoiceId,
            'success' => false,
            'message' => 'Invoice sem integracao_id para cancelamento na NFE.io',
        ];
        continue;
    }

    $url = rtrim($baseUrl, '/') . '/v1/companies/' . $companyId . '/serviceinvoices/' . $invoice->integracao_id;

    $response = Http::withHeaders([
        'X-NFE-APIKEY' => $apiKey,
        'Authorization' => $apiKey,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->delete($url);

    $responseBody = $response->json();

    if (!$response->successful()) {
        $hasError = true;
        $results[] = [
            'invoice_id' => $invoiceId,
            'integracao_id' => $invoice->integracao_id,
            'success' => false,
            'http_status' => $response->status(),
            'response' => $responseBody ?: $response->body(),
        ];
        continue;
    }

    $invoice->status = 'cancelled';
    $invoice->financeiro_status = 'cancelado';
    $invoice->retorno_integracao = array_merge((array)($invoice->retorno_integracao ?? []), [
        'cancelled_at' => date('c'),
        'cancel_response' => $responseBody,
    ]);
    $invoice->save();

    FinancialTransaction::query()
        ->where('commission_invoice_id', $invoice->id)
        ->update(['status' => 'cancelado']);

    $results[] = [
        'invoice_id' => $invoiceId,
        'integracao_id' => $invoice->integracao_id,
        'success' => true,
        'http_status' => $response->status(),
        'response' => $responseBody ?: $response->body(),
    ];
}

echo json_encode([
    'executed_at' => date('c'),
    'php_binary' => PHP_BINARY,
    'invoice_ids' => $invoiceIds,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

exit($hasError ? 1 : 0);
