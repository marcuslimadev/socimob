<?php
chdir('/home/u815655858/domains/lojadaesquina.store/public_html');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tenant;
use App\Models\Pessoa;

$tenant = Tenant::first();
if (!$tenant) { echo 'SEM TENANT'; exit; }
echo 'Tenant: ' . $tenant->id . PHP_EOL;

$dados = [
  ['nome' => 'Carlos Alberto Ferreira', 'tipo' => 'fisica', 'papel' => 'proprietario'],
  ['nome' => 'Fernanda Ribeiro da Costa', 'tipo' => 'fisica', 'papel' => 'proprietario'],
  ['nome' => 'Lucas Mendes Oliveira', 'tipo' => 'fisica', 'papel' => 'inquilino'],
  ['nome' => 'Juliana Souza Lima', 'tipo' => 'fisica', 'papel' => 'inquilino'],
];

foreach ($dados as $d) {
  $e = Pessoa::where('tenant_id', $tenant->id)->where('nome', $d['nome'])->first();
  if ($e) {
    $e->adicionarPapel($d['papel']); $e->save();
    echo 'Atualizado: ' . $d['nome'] . PHP_EOL;
  } else {
    $p = Pessoa::create(['tenant_id' => $tenant->id, 'nome' => $d['nome'], 'tipo' => $d['tipo'], 'papeis' => [$d['papel']]]);
    echo 'Criado ID ' . $p->id . ': ' . $p->nome . ' [' . $d['papel'] . ']' . PHP_EOL;
  }
}
echo 'Concluido.' . PHP_EOL;
