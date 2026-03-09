<?php
/**
 * Testa as APIs do ImobiBrasil e mostra os campos reais retornados.
 * Uso: php test_imobi_api.php
 */
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Services\ImobiBrasilService;

// Pegar o primeiro tenant com integração ImobiBrasil configurada
$tenant = Tenant::whereNotNull('domain')->first();
if (!$tenant) {
    echo "Nenhum tenant encontrado.\n";
    exit(1);
}

echo "=== Tenant: {$tenant->name} ===\n";
echo "Base URL: " . ImobiBrasilService::getBaseUrl($tenant) . "\n\n";

// ─── Helper ──────────────────────────────────────────────────────────────────

function showFields(array $data, string $prefix = ''): void {
    foreach ($data as $key => $value) {
        $fullKey = $prefix ? "$prefix.$key" : $key;
        if (is_array($value)) {
            if (count($value) === 0) {
                echo "  $fullKey: []\n";
            } elseif (isset($value[0]) && is_array($value[0])) {
                echo "  $fullKey: [array[" . count($value) . "]]\n";
                showFields($value[0], "$fullKey[0]");
            } else {
                echo "  $fullKey: {" . implode(', ', array_keys($value)) . "}\n";
            }
        } else {
            $display = $value === null ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value);
            if (strlen($display) > 80) $display = substr($display, 0, 77) . '...';
            echo "  $fullKey: $display\n";
        }
    }
}

// ─── Lista de Pessoas ────────────────────────────────────────────────────────
echo "=== PESSOA/LISTA (primeiros 3 itens) ===\n";
$r = ImobiBrasilService::listPessoas($tenant, ['per_page' => 3]);
if (!$r['success']) {
    echo "ERRO: " . ($r['error'] ?? 'unknown') . "\n";
} else {
    $rs = $r['result_set'] ?? [];
    $data = $rs['data'] ?? (is_array($rs) ? $rs : []);
    if (empty($data)) {
        echo "Nenhum resultado\n";
        echo "result_set estrutura: " . json_encode(array_keys($rs ?: [])) . "\n";
    } else {
        echo "Total itens: " . ($rs['totalItems'] ?? $rs['total_items'] ?? $rs['total'] ?? '?') . "\n";
        echo "Campos do primeiro item:\n";
        showFields($data[0]);
        echo "\nPrimeiros 2 itens resumo:\n";
        foreach (array_slice($data, 0, 2) as $i => $item) {
            echo "  [$i] " . json_encode($item) . "\n";
        }
    }
}

// ─── Detalhe de Pessoa ───────────────────────────────────────────────────────
echo "\n=== PESSOA/DADOS (primeira da lista) ===\n";
$r2 = ImobiBrasilService::listPessoas($tenant, ['per_page' => 1]);
$lista2 = $r2['result_set']['data'] ?? [];
if (!empty($lista2)) {
    $cod = $lista2[0]['codigoPessoa'] ?? $lista2[0]['codigo'] ?? null;
    if ($cod) {
        $det = ImobiBrasilService::getPessoa((int)$cod, $tenant);
        if ($det['success']) {
            echo "Campos do result_set:\n";
            showFields($det['result_set'] ?? []);
        } else {
            echo "ERRO detalhe: " . ($det['error'] ?? 'unknown') . "\n";
        }
    }
}

// ─── Lista Corretores ────────────────────────────────────────────────────────
echo "\n=== CORRETOR/LISTA (primeiros 2 itens) ===\n";
$r3 = ImobiBrasilService::listCorretores($tenant, ['per_page' => 3]);
if (!$r3['success']) {
    echo "ERRO: " . ($r3['error'] ?? 'unknown') . "\n";
} else {
    $rs3 = $r3['result_set'] ?? [];
    $data3 = $rs3['data'] ?? (is_array($rs3) ? $rs3 : []);
    if (empty($data3)) {
        echo "Nenhum resultado. result_set: " . json_encode($rs3) . "\n";
    } else {
        echo "Campos do primeiro item:\n";
        showFields($data3[0]);
    }
}

// ─── Detalhe Corretor ────────────────────────────────────────────────────────
echo "\n=== CORRETOR/DADOS (primeiro da lista) ===\n";
$r3b = ImobiBrasilService::listCorretores($tenant, ['per_page' => 1]);
$rs3b = $r3b['result_set'] ?? [];
$lista3 = $rs3b['data'] ?? (is_array($rs3b) ? $rs3b : []);
if (!empty($lista3)) {
    $cod3 = $lista3[0]['codigoCorretor'] ?? $lista3[0]['codigo'] ?? null;
    if ($cod3) {
        $det3 = ImobiBrasilService::getCorretor((int)$cod3, $tenant);
        if ($det3['success']) {
            echo "Campos do result_set:\n";
            showFields($det3['result_set'] ?? []);
        } else {
            echo "ERRO detalhe: " . ($det3['error'] ?? 'unknown') . "\n";
            echo "result_set raw: " . json_encode($det3['result_set'] ?? '') . "\n";
        }
    }
}

// ─── Lista Clientes ───────────────────────────────────────────────────────────
echo "\n=== CLIENTE/LISTA (primeiros 2 itens) ===\n";
$r4 = ImobiBrasilService::listClientes($tenant, ['per_page' => 3]);
if (!$r4['success']) {
    echo "ERRO: " . ($r4['error'] ?? 'unknown') . "\n";
} else {
    $rs4 = $r4['result_set'] ?? [];
    $data4 = $rs4['data'] ?? (is_array($rs4) ? $rs4 : []);
    if (empty($data4)) {
        echo "Nenhum resultado. result_set keys: " . json_encode(array_keys($rs4 ?: [])) . "\n";
    } else {
        echo "Campos do primeiro item:\n";
        showFields($data4[0]);
    }
}

// ─── Detalhe Cliente ──────────────────────────────────────────────────────────
echo "\n=== CLIENTE/DADOS (primeiro da lista) ===\n";
$r4b = ImobiBrasilService::listClientes($tenant, ['per_page' => 1]);
$rs4b = $r4b['result_set'] ?? [];
$lista4 = $rs4b['data'] ?? (is_array($rs4b) ? $rs4b : []);
if (!empty($lista4)) {
    $cod4 = $lista4[0]['codigoCliente'] ?? $lista4[0]['codigo'] ?? null;
    if ($cod4) {
        $det4 = ImobiBrasilService::getCliente((int)$cod4, $tenant);
        if ($det4['success']) {
            echo "Campos do result_set:\n";
            showFields($det4['result_set'] ?? []);
        } else {
            echo "ERRO detalhe: " . ($det4['error'] ?? 'unknown') . "\n";
            echo "result_set raw: " . json_encode($det4['result_set'] ?? '') . "\n";
        }
    }
}

// ─── Lista Negócios ───────────────────────────────────────────────────────────
echo "\n=== NEGOCIO/LISTA (primeiros 2 itens) ===\n";
$r5 = ImobiBrasilService::listNegocios($tenant, ['per_page' => 3]);
if (!$r5['success']) {
    echo "ERRO: " . ($r5['error'] ?? 'unknown') . "\n";
} else {
    $rs5 = $r5['result_set'] ?? [];
    $data5 = $rs5['data'] ?? (is_array($rs5) ? $rs5 : []);
    if (empty($data5)) {
        echo "Nenhum resultado. result_set: " . json_encode($rs5) . "\n";
    } else {
        echo "Campos do primeiro item:\n";
        showFields($data5[0]);
    }
}

// ─── Detalhe Negócio ──────────────────────────────────────────────────────────
echo "\n=== NEGOCIO/DADOS (primeiro da lista) ===\n";
$r5b = ImobiBrasilService::listNegocios($tenant, ['per_page' => 1]);
$rs5b = $r5b['result_set'] ?? [];
$lista5 = $rs5b['data'] ?? (is_array($rs5b) ? $rs5b : []);
if (!empty($lista5)) {
    $cod5 = $lista5[0]['codigoNegocio'] ?? $lista5[0]['codigo'] ?? null;
    if ($cod5) {
        $det5 = ImobiBrasilService::getNegocio((int)$cod5, $tenant);
        if ($det5['success']) {
            echo "Campos do result_set:\n";
            showFields($det5['result_set'] ?? []);
        } else {
            echo "ERRO detalhe: " . ($det5['error'] ?? 'unknown') . "\n";
        }
    }
}

// ─── Lista Mensagens ──────────────────────────────────────────────────────────
echo "\n=== MENSAGEM/LISTA (primeiros 2 itens) ===\n";
$r6 = ImobiBrasilService::listMensagens($tenant, ['per_page' => 3]);
if (!$r6['success']) {
    echo "ERRO: " . ($r6['error'] ?? 'unknown') . "\n";
} else {
    $rs6 = $r6['result_set'] ?? [];
    $data6 = $rs6['data'] ?? (is_array($rs6) ? $rs6: []);
    if (empty($data6)) {
        echo "Nenhum resultado. result_set: " . json_encode($rs6) . "\n";
    } else {
        echo "Campos do primeiro item:\n";
        showFields($data6[0]);
    }
}

// ─── Detalhe Mensagem ─────────────────────────────────────────────────────────
echo "\n=== MENSAGEM/DADOS (primeira da lista) ===\n";
$r6b = ImobiBrasilService::listMensagens($tenant, ['per_page' => 1]);
$rs6b = $r6b['result_set'] ?? [];
$lista6 = $rs6b['data'] ?? (is_array($rs6b) ? $rs6b : []);
if (!empty($lista6)) {
    $cod6 = $lista6[0]['codigoMensagem'] ?? $lista6[0]['codigo'] ?? null;
    if ($cod6) {
        $det6 = ImobiBrasilService::getMensagem((int)$cod6, $tenant);
        if ($det6['success']) {
            echo "Campos do result_set:\n";
            showFields($det6['result_set'] ?? []);
        } else {
            echo "ERRO detalhe: " . ($det6['error'] ?? 'unknown') . "\n";
        }
    }
}

echo "\n=== FIM DOS TESTES ===\n";
