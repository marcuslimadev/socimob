<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22mm 15mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2933; font-size: 11px; line-height: 1.45; }
        .header { border-bottom: 2px solid {{ $tenant->primary_color ?? '#1f4e79' }}; padding-bottom: 10px; margin-bottom: 18px; }
        .brand { font-size: 19px; font-weight: 700; color: {{ $tenant->primary_color ?? '#1f4e79' }}; }
        .muted { color: #667085; }
        h1 { text-align: center; font-size: 22px; margin: 22px 0; letter-spacing: .06em; }
        h2 { font-size: 14px; color: {{ $tenant->primary_color ?? '#1f4e79' }}; margin: 18px 0 8px; border-bottom: 1px solid #d9e2ec; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        th, td { border: 1px solid #d9e2ec; padding: 6px; vertical-align: top; }
        th { background: #f3f6f9; text-align: left; }
        .grid { display: table; width: 100%; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .badge { display: inline-block; border: 1px solid #d9e2ec; border-radius: 4px; padding: 2px 6px; background: #f8fafc; }
        .photo { width: 31%; height: 120px; object-fit: cover; margin: 0 1% 8px 0; border: 1px solid #d9e2ec; }
        .page-break { page-break-before: always; }
        .sign { height: 58px; border-bottom: 1px solid #667085; margin-bottom: 4px; text-align: center; }
        .qr { width: 110px; height: 110px; }
        .footer { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 9px; color: #667085; border-top: 1px solid #d9e2ec; padding-top: 4px; }
    </style>
</head>
<body>
<div class="footer">
    {{ $tenant->name ?? 'Imobiliária' }} | Gerado em {{ $geradoEm->format('d/m/Y H:i') }} | Vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }}
</div>

<div class="header">
    <div class="brand">{{ $tenant->name ?? 'Imobiliária' }}</div>
    <div class="muted">{{ $tenant->contact_phone ?? '' }} {{ $tenant->contact_email ? ' | '.$tenant->contact_email : '' }}</div>
</div>

<h1>TERMO DE VISTORIA</h1>

<h2>Dados da Vistoria</h2>
<table>
    <tr>
        <th>Número</th><td>{{ $vistoria->codigo ?? '#'.$vistoria->id }}</td>
        <th>Tipo</th><td>{{ ucfirst(str_replace('_', ' ', $vistoria->tipo ?? 'entrada')) }}</td>
    </tr>
    <tr>
        <th>Status</th><td>{{ ucfirst(str_replace('_', ' ', $vistoria->status ?? '')) }}</td>
        <th>Data/Hora</th><td>{{ optional($vistoria->data_vistoria ?: $vistoria->data_agendada)->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <th>Vistoriador</th><td colspan="3">{{ $vistoria->responsavel->nome ?? implode(', ', $vistoria->vistoriadores ?? []) }}</td>
    </tr>
</table>

<h2>Dados do Imóvel</h2>
@php($imovel = $vistoria->contrato?->imovel ?: $vistoria->imovel)
<table>
    <tr><th>Identificação</th><td>{{ $imovel->titulo ?? $vistoria->imovel_livre['titulo'] ?? $imovel->codigo ?? 'Imóvel informado na vistoria' }}</td></tr>
    <tr><th>Endereço</th><td>{{ collect([$imovel->logradouro ?? $vistoria->imovel_livre['logradouro'] ?? null, $imovel->bairro ?? $vistoria->imovel_livre['bairro'] ?? null, $imovel->cidade ?? $vistoria->imovel_livre['cidade'] ?? null, $imovel->estado ?? $vistoria->imovel_livre['estado'] ?? null])->filter()->implode(', ') }}</td></tr>
    <tr><th>Metragem</th><td>{{ $vistoria->metragem ?: $imovel->area_total ?? '-' }} m²</td></tr>
    <tr><th>Mobiliado</th><td>{{ $vistoria->mobiliado ? 'Sim' : 'Não' }}</td></tr>
</table>

<h2>Partes</h2>
<table>
    <tr><th>Nome</th><th>Função</th><th>Documento</th><th>Contato</th></tr>
    @forelse($vistoria->partes as $parte)
        <tr>
            <td>{{ $parte->nome }}</td>
            <td>{{ ucfirst($parte->funcao) }}</td>
            <td>{{ $parte->documento }}</td>
            <td>{{ collect([$parte->email, $parte->telefone])->filter()->implode(' | ') }}</td>
        </tr>
    @empty
        @foreach(($vistoria->pessoas ?? []) as $nome)
            <tr><td>{{ $nome }}</td><td>Parte</td><td></td><td></td></tr>
        @endforeach
    @endforelse
</table>

<h2>Introdução e Critérios</h2>
<p>{{ $vistoria->introducao_texto ?: 'A presente vistoria descreve o estado aparente do imóvel na data registrada, com avaliação visual de ambientes, itens, chaves, mídias e inconformidades.' }}</p>
<p><strong>Critérios:</strong> Novo, Bom, Regular, Mau e Não aplicável. Pintura e limpeza seguem os mesmos parâmetros quando informados.</p>
<p><strong>Contestação:</strong> o prazo é de {{ $vistoria->prazo_contestacao_dias ?: 5 }} dia(s), até {{ optional($vistoria->data_limite_contestacao)->format('d/m/Y H:i') ?: 'data não definida' }}.</p>

<div class="page-break"></div>
<h2>Chaves e Observações</h2>
<table>
    <tr><th>Tipo</th><th>Quantidade</th><th>Estado</th><th>Observações</th></tr>
    @forelse($vistoria->chaves as $chave)
        <tr><td>{{ $chave->tipo }}</td><td>{{ $chave->quantidade }}</td><td>{{ $chave->estado }}</td><td>{{ $chave->observacoes }}</td></tr>
    @empty
        <tr><td colspan="4">Nenhuma chave registrada.</td></tr>
    @endforelse
</table>
<p>{{ $vistoria->observacoes_gerais ?: $vistoria->observacoes }}</p>

@foreach($vistoria->ambientes as $ambiente)
    <div class="page-break"></div>
    <h2>{{ $ambiente->nome }}</h2>
    <p>
        <span class="badge">Estado: {{ $ambiente->estado_geral ?: '-' }}</span>
        <span class="badge">Pintura: {{ $ambiente->pintura_estado ?: '-' }}</span>
        <span class="badge">Limpeza: {{ $ambiente->limpeza_estado ?: '-' }}</span>
    </p>
    <p>{{ $ambiente->observacoes }}</p>

    <h3>Itens</h3>
    <table>
        <tr><th>Item</th><th>Estado</th><th>Observação</th><th>Inconformidade</th></tr>
        @forelse($ambiente->itens as $item)
            <tr><td>{{ $item->nome }}</td><td>{{ $item->estado }}</td><td>{{ $item->observacoes }}</td><td>{{ $item->possui_inconformidade ? 'Sim' : 'Não' }}</td></tr>
        @empty
            <tr><td colspan="4">Nenhum item registrado.</td></tr>
        @endforelse
    </table>

    <h3>Inconformidades</h3>
    @forelse($ambiente->inconformidades as $inc)
        <p><strong>{{ ucfirst($inc->severidade) }}:</strong> {{ $inc->descricao }}</p>
    @empty
        <p>Nenhuma inconformidade registrada neste ambiente.</p>
    @endforelse

    <h3>Fotos e vídeos</h3>
    @forelse($ambiente->midias as $midia)
        @if(str_starts_with((string) $midia->mime_type, 'image/'))
            <img class="photo" src="{{ public_path('storage/'.$midia->path_original) }}" alt="">
        @else
            <p><strong>{{ strtoupper($midia->tipo) }}:</strong> {{ $midia->legenda ?: basename($midia->path_original) }}</p>
        @endif
    @empty
        <p>Nenhuma mídia vinculada ao ambiente.</p>
    @endforelse
@endforeach

<div class="page-break"></div>
<h2>Termos de Responsabilidade</h2>
<p>As partes declaram ciência de que este termo reflete o estado aparente do imóvel na data da vistoria, podendo contestar divergências no prazo informado. Alterações posteriores, mau uso, ausência de comunicação ou danos não registrados deverão ser avaliados conforme contrato e legislação aplicável.</p>

<h2>Assinaturas</h2>
<table>
    <tr><th>Parte</th><th>Função</th><th>Assinatura</th></tr>
    @forelse($vistoria->partes as $parte)
        <tr>
            <td>{{ $parte->nome }}<br><span class="muted">{{ $parte->documento }}</span></td>
            <td>{{ ucfirst($parte->funcao) }}</td>
            <td>
                <div class="sign">
                    @if($parte->assinatura_path)
                        <img src="{{ public_path('storage/'.$parte->assinatura_path) }}" style="max-height:54px; max-width:220px;">
                    @endif
                </div>
                {{ $parte->assinou ? 'Assinado em '.optional($parte->data_assinatura)->format('d/m/Y H:i') : 'Pendente' }}
            </td>
        </tr>
    @empty
        <tr><td colspan="3">Nenhuma parte de assinatura cadastrada.</td></tr>
    @endforelse
</table>

<div class="page-break"></div>
<h2>Acesso às Mídias e Contestação</h2>
<div class="grid">
    <div class="col">
        <h3>Mídias da vistoria</h3>
        <img class="qr" src="{{ $midiasQrUrl }}" alt="QR mídias">
        <p>{{ $midiasUrl }}</p>
    </div>
    <div class="col">
        <h3>Contestação</h3>
        <img class="qr" src="{{ $contestacaoQrUrl }}" alt="QR contestação">
        <p>{{ $contestacaoUrl }}</p>
    </div>
</div>
<p class="muted">Número da vistoria: {{ $vistoria->codigo ?? '#'.$vistoria->id }} | Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</body>
</html>
