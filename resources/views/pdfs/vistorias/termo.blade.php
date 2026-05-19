<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 14mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #202124; font-size: 10.8px; line-height: 1.42; }
        .footer { position: fixed; left: 0; right: 0; bottom: -11mm; border-top: 1px solid #cfd6dd; padding-top: 4px; color: #59636e; font-size: 8.8px; }
        .footer .page:after { content: "Página " counter(page) " de " counter(pages); }
        .top { width: 100%; border-bottom: 2px solid {{ $tenant?->primary_color ?? '#1f4e79' }}; padding-bottom: 8px; margin-bottom: 12px; }
        .logo { width: 86px; max-height: 48px; object-fit: contain; }
        .brand { font-size: 15px; font-weight: 700; color: {{ $tenant?->primary_color ?? '#1f4e79' }}; }
        .muted { color: #65717d; }
        h1 { text-align: center; font-size: 21px; margin: 16px 0 18px; letter-spacing: .08em; }
        h2 { font-size: 13px; margin: 14px 0 7px; color: {{ $tenant?->primary_color ?? '#1f4e79' }}; border-bottom: 1px solid #d9e0e7; padding-bottom: 3px; }
        h3 { font-size: 11.5px; margin: 10px 0 5px; color: #202124; }
        table { width: 100%; border-collapse: collapse; margin: 5px 0 9px; }
        th, td { border: 1px solid #d9e0e7; padding: 5px 6px; vertical-align: top; }
        th { background: #eef3f7; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #394650; text-align: left; }
        .no-border td, .no-border th { border: 0; padding: 0; }
        .two-col { display: table; width: 100%; table-layout: fixed; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 9px; }
        .pill { display: inline-block; border: 1px solid #cfd6dd; background: #f8fafc; border-radius: 3px; padding: 2px 5px; margin: 0 4px 4px 0; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .section-title { font-weight: 700; color: #111827; }
        .photo-grid { margin-top: 4px; }
        .photo-box { display: inline-block; width: 31.5%; margin: 0 1% 8px 0; vertical-align: top; page-break-inside: avoid; }
        .media-frame { position: relative; width: 100%; height: 132px; overflow: hidden; background: #f1f5f9; border: 1px solid #cfd6dd; }
        .photo { width: 100%; height: 132px; object-fit: cover; }
        .caption { font-size: 8.6px; color: #111827; margin-top: 2px; text-align: center; font-weight: 700; font-style: italic; }
        .media-date { position: absolute; left: 0; top: 0; background: #fff; border: 1px solid #111827; padding: 3px 6px; font-size: 8.2px; font-weight: 700; color: #111827; z-index: 2; }
        .video-tile { position: relative; width: 100%; height: 132px; overflow: hidden; background: #d1d5db; border: 1px solid #111827; }
        .video-thumb { width: 100%; height: 132px; object-fit: cover; }
        .video-placeholder { width: 100%; height: 132px; background: #d1d5db; }
        .video-qr { position: absolute; left: 4px; bottom: 4px; width: 48px; height: 48px; background: #fff; border: 1px solid #111827; padding: 1px; z-index: 2; }
        .play-circle { position: absolute; left: 50%; top: 50%; width: 52px; height: 52px; margin-left: -26px; margin-top: -26px; border: 5px solid rgba(255,255,255,.92); border-radius: 50%; background: rgba(255,255,255,.26); z-index: 3; }
        .play-triangle { position: absolute; left: 19px; top: 13px; width: 0; height: 0; border-left: 19px solid rgba(255,255,255,.94); border-top: 13px solid transparent; border-bottom: 13px solid transparent; }
        .sign { height: 54px; border-bottom: 1px solid #59636e; text-align: center; margin-bottom: 3px; }
        .signature-grid { width: 100%; border-collapse: separate; border-spacing: 10px 18px; margin-top: 8px; }
        .signature-grid td { width: 50%; border: 0; vertical-align: bottom; padding: 0 8px 8px; }
        .signature-box { min-height: 92px; text-align: center; page-break-inside: avoid; }
        .signature-image { height: 44px; text-align: center; }
        .signature-image img { max-height: 42px; max-width: 230px; margin: 0 auto; }
        .signature-line { border-top: 1px solid #202124; padding-top: 5px; font-weight: 700; font-size: 9.5px; }
        .signature-sub { color: #59636e; font-size: 8.8px; margin-top: 2px; }
        .qr { width: 112px; height: 112px; border: 1px solid #d9e0e7; padding: 3px; }
        .tiny { font-size: 8.8px; }
        ol { margin: 5px 0 6px 17px; padding: 0; }
        p { margin: 4px 0 7px; }
    </style>
</head>
<body>
@php
    $imovel = $vistoria->contrato?->imovel ?: $vistoria->imovel;
    $tenantName = $tenant?->name ?? $tenant?->razao_social ?? 'Imobiliária';
    $tenantPhone = $tenant?->contact_phone ?? '';
    $tenantEmail = $tenant?->contact_email ?? '';
    $numero = $vistoria->codigo ?: '#'.$vistoria->id;
    $tipo = ucfirst(str_replace(['_', '-'], ' ', $vistoria->tipo_vistoria ?: $vistoria->tipo ?: 'entrada'));
    $dataVistoria = $vistoria->data_inicio ?: $vistoria->data_vistoria ?: $vistoria->data_agendada;
    $endereco = collect([
        $imovel->logradouro ?? $vistoria->imovel_livre['logradouro'] ?? null,
        $imovel->bairro ?? $vistoria->imovel_livre['bairro'] ?? null,
        $imovel->cidade ?? $vistoria->imovel_livre['cidade'] ?? null,
        $imovel->estado ?? $vistoria->imovel_livre['estado'] ?? null,
    ])->filter()->implode(' - ');
    $criterios = $vistoria->criterios_avaliacao_json ?: [
        ['titulo' => 'NOVO', 'texto' => 'Nunca foi utilizado ou imóvel novo.'],
        ['titulo' => 'BOM', 'texto' => 'Apresenta pouco desgaste.'],
        ['titulo' => 'REGULAR', 'texto' => 'Apresenta sinais de desgastes aparentes.'],
        ['titulo' => 'MAU', 'texto' => 'Apresenta grandes sinais de deterioração.'],
    ];
    $criteriosPintura = $vistoria->criterios_pintura_json ?: [
        ['titulo' => 'Pintura NOVA', 'texto' => 'Pintura recente, sem manchas, falhas ou marcas.'],
        ['titulo' => 'Pintura em BOM estado de conservação', 'texto' => 'Pintura com pequenas manchas, falhas de acabamento, coberturas, recortes e uso.'],
        ['titulo' => 'Pintura em estado REGULAR', 'texto' => 'Pintura com manchas, furos, falhas, descascamentos ou sinais evidentes de uso.'],
    ];
    $criteriosLimpeza = $vistoria->criterios_limpeza_json ?: [
        ['titulo' => 'Imóvel limpo', 'texto' => 'Imóvel faxinado recentemente, sem poeiras ou vestígios relevantes de sujeira.'],
        ['titulo' => 'Imóvel com poeira superficial', 'texto' => 'Imóvel fechado por algum tempo, com poeira em vidros e superfícies.'],
        ['titulo' => 'Imóvel sujo', 'texto' => 'Poeira ou sujeira em banheiros, rejuntes, lixos, restos de obras ou áreas internas.'],
    ];
    $normalizaCompartimento = fn ($nome) => mb_strtolower(trim((string) ($nome ?: 'Sem compartimento')));
    $fotosPorCompartimento = $vistoria->fotos->groupBy(fn ($foto) => $normalizaCompartimento($foto->comodo));
    $ambientesRenderizados = collect();
    $mediaQr = fn (string $url) => 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($url);
@endphp

<div class="footer">
    {{ $tenantName }} | Gerado em {{ url('/vistorias') }} no dia {{ $geradoEm->format('d/m/Y') }} às {{ $geradoEm->format('H:i:s') }}
    <span style="float:right;"><span class="page"></span> | Vistoria: {{ $numero }}</span>
</div>

<table class="top no-border">
    <tr>
        <td style="width: 100px;">
            @if($tenantLogo)
                <img class="logo" src="{{ $tenantLogo }}" alt="">
            @endif
        </td>
        <td>
            <div class="brand">{{ $tenantName }}</div>
            <div class="muted">{{ collect([$tenantPhone, $tenantEmail])->filter()->implode(' | ') }}</div>
            @if($tenant?->creci || $tenant?->cnpj)
                <div class="muted tiny">{{ collect([$tenant?->creci ? 'CRECI '.$tenant->creci : null, $tenant?->cnpj ? 'CNPJ '.$tenant->cnpj : null])->filter()->implode(' | ') }}</div>
            @endif
        </td>
    </tr>
</table>

<h1>TERMO DE VISTORIA</h1>

<h2>Dados da Vistoria</h2>
<table>
    <tr>
        <th>Data</th><th>Vistoriador</th><th>Tipo</th>
    </tr>
    <tr>
        <td>{{ optional($dataVistoria)->format('d/m/Y H:i:s') ?: '-' }}</td>
        <td>{{ $vistoria->responsavel->nome ?? implode(', ', $vistoria->vistoriadores ?? []) ?: '-' }}</td>
        <td>{{ $tipo }}</td>
    </tr>
</table>

<h2>Imóvel</h2>
<table>
    <tr><th>Identificação</th><td>{{ $imovel->titulo ?? $vistoria->imovel_livre['titulo'] ?? $imovel->codigo ?? 'Imóvel informado na vistoria' }}</td></tr>
    <tr><th>Endereço</th><td>{{ $endereco ?: '-' }}</td></tr>
    <tr>
        <th>Metragem</th><td>{{ $vistoria->metragem ?: $imovel->area_total ?? '-' }} m²</td>
    </tr>
    <tr><th>Mobiliado</th><td>{{ $vistoria->mobiliado ? 'SIM' : 'NÃO' }}</td></tr>
</table>

<h2>Pessoas (partes)</h2>
<table>
    <tr><th>Nome</th><th>Função</th><th>Documento</th><th>Contato</th></tr>
    @forelse($vistoria->partes as $parte)
        <tr>
            <td>{{ $parte->nome }}</td>
            <td>{{ strtoupper(str_replace('_', ' ', $parte->funcao)) }}</td>
            <td>{{ $parte->documento ?: '-' }}</td>
            <td>{{ collect([$parte->email, $parte->telefone])->filter()->implode(' | ') ?: '-' }}</td>
        </tr>
    @empty
        @foreach(($vistoria->pessoas ?? []) as $nome)
            <tr><td>{{ $nome }}</td><td>PARTE</td><td>-</td><td>-</td></tr>
        @endforeach
        @if(empty($vistoria->pessoas))
            <tr><td colspan="4">Nenhuma parte cadastrada.</td></tr>
        @endif
    @endforelse
</table>

<h2>Introdução</h2>
<p>{{ $vistoria->introducao_texto ?: 'As informações constantes neste relatório trazem uma descrição fiel do atual estado aparente do imóvel vistoriado. Além das informações escritas, as fotos, vídeos e anexos servem como prova da vistoria realizada e da condição do imóvel.' }}</p>

<h2>Parâmetros de Avaliação / Condição do Imóvel / Estado</h2>
@foreach($criterios as $criterio)
    <p><strong>{{ $criterio['titulo'] ?? '' }}:</strong> {{ $criterio['texto'] ?? '' }}</p>
@endforeach

<div class="page-break"></div>
<h2>Chaves</h2>
@forelse($vistoria->chaves as $idx => $chave)
    <div class="avoid-break">
        <p><strong>{{ $idx + 1 }}. {{ ucfirst(str_replace('_', ' ', $chave->tipo)) }}:</strong></p>
        <p>{{ ucfirst($chave->estado ?: 'estado não informado') }} &nbsp; <strong>Quantidade:</strong> {{ $chave->quantidade ?: 0 }}</p>
        @if($chave->observacoes)<p><strong>Observação:</strong> {{ $chave->observacoes }}</p>@endif
    </div>
@empty
    <p>Nenhuma chave registrada.</p>
@endforelse

<h2>Inconformidades Gerais</h2>
@forelse($vistoria->inconformidades->whereNull('ambiente_id') as $idx => $inc)
    <p><strong>{{ $idx + 1 }}.</strong> {{ $inc->descricao }} <span class="muted">({{ ucfirst($inc->severidade ?: 'media') }})</span></p>
@empty
    <p>Nenhuma inconformidade geral registrada.</p>
@endforelse

<h2>Critérios de Avaliação de Pintura</h2>
@foreach($criteriosPintura as $criterio)
    <p><strong>{{ $criterio['titulo'] ?? '' }}:</strong> {{ $criterio['texto'] ?? '' }}</p>
@endforeach

<h2>Critérios de Avaliação da Limpeza</h2>
@foreach($criteriosLimpeza as $criterio)
    <p><strong>{{ $criterio['titulo'] ?? '' }}:</strong> {{ $criterio['texto'] ?? '' }}</p>
@endforeach

<h2>Contestação</h2>
<p>Na eventualidade de encontrar incompatibilidades no relatório referente à condição do imóvel no momento da vistoria, é possível abrir CONTESTAÇÃO, desde que seguidas as diretrizes abaixo:</p>
<ol>
    <li>O prazo para apresentar a contestação é de {{ $vistoria->prazo_contestacao_dias ?: 5 }} dia(s) a partir da data da vistoria.</li>
    <li>Aponte especificamente a(s) incompatibilidade(s) identificada(s) por escrito.</li>
    <li>Anexe foto(s) ou vídeo(s) que comprovem a(s) incompatibilidade(s) identificada(s).</li>
    <li>Não serão aceitas contestações fora do prazo.</li>
</ol>
<p><strong>Data limite:</strong> {{ optional($vistoria->data_limite_contestacao)->format('d/m/Y H:i') ?: 'não definida' }}</p>

<h2>Observações</h2>
<p>{{ $vistoria->observacoes_gerais ?: $vistoria->observacoes ?: 'Sem observações gerais.' }}</p>

<div class="page-break"></div>
<h2>Ambientes</h2>
@forelse($vistoria->ambientes as $ambienteIndex => $ambiente)
    @php
        $ambienteKey = $normalizaCompartimento($ambiente->nome);
        $ambientesRenderizados->push($ambienteKey);
        $fotosLegadas = $fotosPorCompartimento->get($ambienteKey, collect());
    @endphp
    <div class="avoid-break">
        <h3>{{ $ambienteIndex + 1 }}. {{ $ambiente->nome }}</h3>
        <p>
            <span class="pill">Estado: {{ $ambiente->estado_geral ?: '-' }}</span>
            <span class="pill">Pintura: {{ $ambiente->pintura_estado ?: '-' }}</span>
            <span class="pill">Limpeza: {{ $ambiente->limpeza_estado ?: '-' }}</span>
        </p>
        @if($ambiente->observacoes)<p>{{ $ambiente->observacoes }}</p>@endif
        <p class="section-title">Inconformidade:</p>
        @forelse($ambiente->inconformidades as $idx => $inc)
            <p>{{ $idx + 1 }}. {{ $inc->descricao }} <span class="muted">({{ ucfirst($inc->severidade ?: 'media') }})</span></p>
        @empty
            <p>Nenhuma inconformidade registrada neste ambiente.</p>
        @endforelse
    </div>

    @if($ambiente->itens->count())
        <table>
            <tr><th>Item</th><th>Estado</th><th>Observação</th><th>Inconf.</th></tr>
            @foreach($ambiente->itens as $item)
                <tr>
                    <td>{{ $item->nome }}</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $item->estado ?: '-')) }}</td>
                    <td>{{ $item->observacoes ?: $item->descricao ?: '-' }}</td>
                    <td>{{ $item->possui_inconformidade ? 'Sim' : 'Não' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($ambiente->midias->count() || $fotosLegadas->count())
        <div class="photo-grid">
            @foreach($ambiente->midias as $midiaIndex => $midia)
                @php
                    $isVideo = str_starts_with((string) $midia->mime_type, 'video/') || $midia->tipo === 'video';
                    $thumb = $midia->path_thumb ? $pdfImageSrc($midia->path_thumb, 'image/jpeg', null) : null;
                    $srcPath = $midia->path_thumb ?: $midia->path_original;
                    $src = $isVideo ? $thumb : $pdfImageSrc($srcPath, $midia->mime_type, $midia->url);
                    $midiaPublicUrl = $midiasUrl . '#midia-' . $midia->id;
                    $midiaQrUrl = $mediaQr($midiaPublicUrl);
                @endphp
                @if($isVideo)
                    <div class="photo-box">
                        <div class="video-tile">
                            <div class="media-date">{{ optional($midia->created_at)->format('d/m/y H:i:s') }}</div>
                            @if($src)
                                <img class="video-thumb" src="{{ $src }}" alt="">
                            @else
                                <div class="video-placeholder"></div>
                            @endif
                            <div class="play-circle"><div class="play-triangle"></div></div>
                            <img class="video-qr" src="{{ $midiaQrUrl }}" alt="QR vídeo">
                        </div>
                        <div class="caption">{{ $ambienteIndex + 1 }}. {{ $ambiente->nome }}</div>
                    </div>
                @elseif($src)
                    <div class="photo-box">
                        <div class="media-frame">
                            <div class="media-date">{{ optional($midia->created_at)->format('d/m/y H:i:s') }}</div>
                            <img class="photo" src="{{ $src }}" alt="">
                        </div>
                        <div class="caption">{{ $ambienteIndex + 1 }}. {{ $ambiente->nome }}</div>
                    </div>
                @endif
            @endforeach
            @foreach($fotosLegadas as $foto)
                @php
                    $isVideoFoto = str_starts_with((string) $foto->mime_type, 'video/');
                    $fotoSrc = $isVideoFoto ? null : $pdfImageSrc($foto->arquivo_path, $foto->mime_type, $foto->url_signed ?: $foto->url);
                    $fotoQrUrl = $mediaQr($midiasUrl . '#foto-' . $foto->id);
                @endphp
                @if($isVideoFoto)
                    <div class="photo-box">
                        <div class="video-tile">
                            <div class="media-date">{{ optional($foto->created_at)->format('d/m/y H:i:s') }}</div>
                            <div class="video-placeholder"></div>
                            <div class="play-circle"><div class="play-triangle"></div></div>
                            <img class="video-qr" src="{{ $fotoQrUrl }}" alt="QR vídeo">
                        </div>
                        <div class="caption">{{ $ambienteIndex + 1 }}. {{ $ambiente->nome }}</div>
                    </div>
                @elseif($fotoSrc)
                    <div class="photo-box">
                        <div class="media-frame">
                            <div class="media-date">{{ optional($foto->created_at)->format('d/m/y H:i:s') }}</div>
                            <img class="photo" src="{{ $fotoSrc }}" alt="">
                        </div>
                        <div class="caption">{{ $ambienteIndex + 1 }}. {{ $ambiente->nome }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <p class="muted">Nenhuma foto ou vídeo vinculado a este ambiente.</p>
    @endif
@empty
    @if($fotosPorCompartimento->isEmpty())
        <p>Nenhum ambiente cadastrado.</p>
    @endif
@endforelse

@foreach($fotosPorCompartimento as $compartimentoKey => $fotosCompartimento)
    @continue($ambientesRenderizados->contains($compartimentoKey))
    <div class="avoid-break">
        <h3>{{ $loop->iteration }}. {{ $fotosCompartimento->first()->comodo ?: 'Sem compartimento' }}</h3>
        <p class="muted">Fotos agrupadas pelo compartimento informado na execução da vistoria.</p>
        <div class="photo-grid">
            @foreach($fotosCompartimento as $foto)
                @php
                    $isVideoFoto = str_starts_with((string) $foto->mime_type, 'video/');
                    $fotoSrc = $isVideoFoto ? null : $pdfImageSrc($foto->arquivo_path, $foto->mime_type, $foto->url_signed ?: $foto->url);
                    $fotoQrUrl = $mediaQr($midiasUrl . '#foto-' . $foto->id);
                @endphp
                @if($isVideoFoto)
                    <div class="photo-box">
                        <div class="video-tile">
                            <div class="media-date">{{ optional($foto->created_at)->format('d/m/y H:i:s') }}</div>
                            <div class="video-placeholder"></div>
                            <div class="play-circle"><div class="play-triangle"></div></div>
                            <img class="video-qr" src="{{ $fotoQrUrl }}" alt="QR vídeo">
                        </div>
                        <div class="caption">{{ $foto->comodo ?: 'Sem compartimento' }}</div>
                    </div>
                @elseif($fotoSrc)
                    <div class="photo-box">
                        <div class="media-frame">
                            <div class="media-date">{{ optional($foto->created_at)->format('d/m/y H:i:s') }}</div>
                            <img class="photo" src="{{ $fotoSrc }}" alt="">
                        </div>
                        <div class="caption">{{ $foto->comodo ?: 'Sem compartimento' }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endforeach

<div class="page-break"></div>
<h2>Termos de Responsabilidade</h2>
<p>Cabe ao CLIENTE e seus FIADORES a inteira responsabilidade por quaisquer danos causados no imóvel no decorrer da locação, os quais serão apurados na vistoria final, devendo às suas expensas serem corrigidos, ficando ainda acertado que só será extinta a relação locatícia após concluídos os reparos necessários.</p>
<p>Declaramos para os devidos fins que estamos cientes que, se neste imóvel existirem aparelhos eletrodomésticos ou equipamentos não novos, caso venham a apresentar problema ou não estejam funcionando perfeitamente, o LOCADOR fica isento de repor aparelho para substituição, incluindo despesa com descarte, salvo disposição contratual em contrário.</p>
<p>Para o bom funcionamento e integridade da vida útil dos aparelhos de ar condicionado e aquecedor de água, quando entregues funcionando no início da locação, deverão ser revisados e limpos periodicamente conforme orientação técnica.</p>
<p>Nós, abaixo assinados, DECLARAMOS, para quaisquer fins de direito, estarmos plenamente de acordo com o presente termo e suas condições. Fluído o prazo de contestação sem manifestação expressa por parte do locatário, subentende-se aceita a vistoria na sua integralidade.</p>
<p>As mídias vinculadas a este termo integram o laudo e podem ser acessadas por QR Code ou link público protegido por token.</p>

<h2>Assinaturas</h2>
@if($vistoria->partes->count())
    <table class="signature-grid">
        @foreach($vistoria->partes->chunk(2) as $linha)
            <tr>
                @foreach($linha as $parte)
                    @php($assinaturaSrc = $pdfImageSrc($parte->assinatura_path, 'image/png'))
                    <td>
                        <div class="signature-box">
                            <div class="signature-image">
                                @if($assinaturaSrc)
                                    <img src="{{ $assinaturaSrc }}" alt="">
                                @endif
                            </div>
                            <div class="signature-line">{{ $parte->nome }}</div>
                            <div class="signature-sub">{{ $parte->documento ?: 'Documento não informado' }}</div>
                            <div class="signature-sub">{{ ucfirst(str_replace('_', ' ', $parte->funcao ?: 'parte')) }}</div>
                            @if($parte->assinou)
                                <div class="signature-sub">Assinado em {{ optional($parte->data_assinatura)->format('d/m/Y H:i') }}</div>
                            @endif
                        </div>
                    </td>
                @endforeach
                @if($linha->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@elseif(!empty($vistoria->pessoas))
    <table class="signature-grid">
        @foreach(collect($vistoria->pessoas)->chunk(2) as $linha)
            <tr>
                @foreach($linha as $nome)
                    <td>
                        <div class="signature-box">
                            <div class="signature-image"></div>
                            <div class="signature-line">{{ $nome }}</div>
                            <div class="signature-sub">Parte</div>
                        </div>
                    </td>
                @endforeach
                @if($linha->count() === 1)<td></td>@endif
            </tr>
        @endforeach
    </table>
@else
    <p>Nenhuma parte de assinatura cadastrada.</p>
@endif

<div class="page-break"></div>
<h2>Página Final - Acesso às Mídias e Contestação</h2>
<div class="two-col">
    <div class="col">
        <h3>QRCODE PARA ACESSO ÀS MÍDIAS</h3>
        <img class="qr" src="{{ $midiasQrUrl }}" alt="QR mídias">
    </div>
    <div class="col">
        <h3>QRCODE PARA REALIZAR CONTESTAÇÃO</h3>
        <img class="qr" src="{{ $contestacaoQrUrl }}" alt="QR contestação">
    </div>
</div>
<table>
    <tr><th>Número da vistoria</th><td>{{ $numero }}</td></tr>
    <tr><th>Data de geração</th><td>{{ $geradoEm->format('d/m/Y H:i:s') }}</td></tr>
    <tr><th>Hash do PDF anterior</th><td>{{ $vistoria->hash_pdf ?: 'Primeira geração' }}</td></tr>
</table>
</body>
</html>
