<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 24mm 16mm 20mm 16mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #20232a; line-height: 1.65; }
    .watermark { position: fixed; top: 42%; left: 50%; width: 34%; transform: translate(-50%, -50%); opacity: .045; z-index: -1; }
    .watermark img { width: 100%; height: auto; }
    .shell { border: 1px solid rgba(31, 41, 55, 0.08); border-radius: 12px; padding: 16mm 14mm 14mm; min-height: calc(100vh - 38mm); background: rgba(255,255,255,.94); }
    .header { border-bottom: 2px solid var(--tenant-primary-color); padding-bottom: 10px; margin-bottom: 16px; }
    .header-brand { width: 100%; display: table; margin-bottom: 10px; }
    .header-brand .left, .header-brand .right { display: table-cell; vertical-align: middle; }
    .header-brand .right { text-align: right; }
    .header-logo { max-width: 140px; max-height: 58px; }
    .header h1 { font-size: 13px; text-align: center; letter-spacing: 1px; text-transform: uppercase; }
    .header p { text-align: center; font-size: 9px; color: #667085; margin-top: 4px; }
    .section-label { font-size: 10px; font-weight: bold; text-transform: uppercase; background: #eef2f7; border-left: 4px solid var(--tenant-primary-color); padding: 4px 8px; margin: 12px 0 6px; }
    .party-box { border: 1px solid #d0d5dd; border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; background: #fcfcfd; }
    .party-title { font-size: 10px; font-weight: bold; margin-bottom: 4px; color: #344054; }
    .party-line { font-size: 9.5px; text-align: justify; }
    .clause-title { font-size: 10px; font-weight: bold; text-transform: uppercase; margin-top: 12px; margin-bottom: 4px; }
    .clause-body { font-size: 9.5px; text-align: justify; margin-bottom: 5px; }
    .clause-paragraph { font-size: 9.3px; text-align: justify; margin-bottom: 4px; padding-left: 16px; }
    .payment-item { border: 1px solid #e4e7ec; border-radius: 8px; padding: 7px 9px; margin-bottom: 6px; background: #fff; }
    .payment-item strong { color: #111827; }
    .signature-grid { width: 100%; border-collapse: separate; border-spacing: 18px 30px; margin-top: 28px; }
    .signature-grid td { width: 50%; vertical-align: bottom; text-align: center; }
    .signature-line { border-top: 1px solid #344054; padding-top: 6px; font-size: 9px; font-weight: bold; }
    .signature-sub { font-size: 8px; color: #667085; margin-top: 2px; }
    .footer { margin-top: 16px; border-top: 1px solid #e4e7ec; padding-top: 8px; text-align: center; font-size: 8px; color: #98a2b3; }
</style>
</head>
<body style="--tenant-primary-color: {{ $tenant?->primary_color ?? '#1f2937' }};">

@php
    $ecMap = [
        'solteiro' => 'solteiro(a)',
        'casado' => 'casado(a)',
        'uniao_estavel' => 'em união estável',
        'divorciado' => 'divorciado(a)',
        'viuvo' => 'viúvo(a)',
        'separado' => 'separado(a)',
    ];
    $vendedores = $contrato->todosVendedores();
    $compradores = $contrato->todosCompradores();
    $parcelas = collect($contrato->parcelas_pagamento ?? []);
    $cidadeImovel = $imovel?->cidade ?? 'Belo Horizonte';
    $estadoImovel = $imovel?->estado ?? 'MG';
    $enderecoImovel = trim(implode(', ', array_filter([
        $imovel?->logradouro ?? null,
        !empty($imovel?->numero) ? 'nº ' . $imovel?->numero : null,
        $imovel?->complemento ?? null,
        $imovel?->bairro ?? null,
        (!empty($imovel?->cidade) || !empty($imovel?->estado)) ? trim(($imovel?->cidade ?? '') . '/' . ($imovel?->estado ?? '')) : null,
    ])));
    $objetoDescricao = trim((string) ($contrato->objeto_descricao ?? ''));
    if ($objetoDescricao === '') {
        $objetoDescricao = 'Imóvel situado em ' . ($enderecoImovel ?: 'endereço a complementar') . '.';
        if (!empty($imovel?->area_privativa)) {
            $objetoDescricao .= ' Área privativa de ' . number_format((float) $imovel?->area_privativa, 2, ',', '.') . 'm².';
        } elseif (!empty($imovel?->area_total)) {
            $objetoDescricao .= ' Área total de ' . number_format((float) $imovel?->area_total, 2, ',', '.') . 'm².';
        }
        if (!empty($imovel?->garagem)) {
            $objetoDescricao .= ' Com ' . (int) $imovel?->garagem . ' vaga(s) de garagem.';
        }
    }
@endphp

@if(!empty($tenantWatermarkSrc))
<div class="watermark">
    <img src="{{ $tenantWatermarkSrc }}" alt="Marca d'água" />
</div>
@endif

<div class="shell">
    <div class="header">
        @if(!empty($tenantLogoSrc) && ($tenantTemplate?->incluir_logo ?? true))
        <div class="header-brand">
            <div class="left"></div>
            <div class="right">
                <img src="{{ $tenantLogoSrc }}" alt="Logo" class="header-logo" />
            </div>
        </div>
        @endif
        <h1>{{ $tenantTemplate?->titulo ?? 'Contrato Particular de Promessa de Compra e Venda' }}</h1>
        <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} | Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
    </div>

    @if(!empty($tenantTemplate?->intro_texto))
    <div class="clause-body">{!! nl2br(e($tenantTemplate->intro_texto)) !!}</div>
    @else
    <div class="clause-body">
        DA QUALIFICAÇÃO ABAIXO: Serão PROMITENTES VENDEDORES e PROMISSÁRIOS COMPRADORES, todos conforme qualificados neste instrumento,
        obrigando-se ao fiel cumprimento das cláusulas e condições seguintes.
    </div>
    @endif

    <div class="section-label">Promitentes Vendedores</div>
    @foreach($vendedores as $vendedorItem)
    <div class="party-box">
        <div class="party-title">{{ $vendedorItem->nome }}</div>
        <div class="party-line">
            @if($vendedorItem->data_nascimento) Nasc.: {{ $vendedorItem->data_nascimento->format('d/m/Y') }}. @endif
            @if($vendedorItem->nacionalidade) {{ $vendedorItem->nacionalidade }}. @endif
            @if($vendedorItem->estado_civil) Estado civil: {{ $ecMap[$vendedorItem->estado_civil] ?? $vendedorItem->estado_civil }}. @endif
            @if($vendedorItem->profissao) Profissão: {{ $vendedorItem->profissao }}. @endif
            @if($vendedorItem->cpf) CPF: {{ $vendedorItem->cpf }}. @endif
            @if($vendedorItem->cnpj && !$vendedorItem->cpf) CNPJ: {{ $vendedorItem->cnpj }}. @endif
            @if($vendedorItem->rg) RG: {{ $vendedorItem->rg }}@if($vendedorItem->orgao_expedidor)/{{ $vendedorItem->orgao_expedidor }}@endif. @endif
            @if($vendedorItem->email) E-mail: {{ $vendedorItem->email }}. @endif
            @if($vendedorItem->endereco)
                Endereço: {{ $vendedorItem->endereco }}
                @if($vendedorItem->numero), {{ $vendedorItem->numero }}@endif
                @if($vendedorItem->complemento), {{ $vendedorItem->complemento }}@endif
                @if($vendedorItem->bairro), {{ $vendedorItem->bairro }}@endif
                @if($vendedorItem->cidade), {{ $vendedorItem->cidade }}@endif
                @if($vendedorItem->estado)/{{ $vendedorItem->estado }}@endif
                @if($vendedorItem->cep), CEP {{ $vendedorItem->cep }}@endif.
            @endif
        </div>
    </div>
    @endforeach

    <div class="section-label">Promissários Compradores</div>
    @foreach($compradores as $compradorItem)
    <div class="party-box">
        <div class="party-title">{{ $compradorItem->nome }}</div>
        <div class="party-line">
            @if($compradorItem->data_nascimento) Nasc.: {{ $compradorItem->data_nascimento->format('d/m/Y') }}. @endif
            @if($compradorItem->nacionalidade) {{ $compradorItem->nacionalidade }}. @endif
            @if($compradorItem->estado_civil) Estado civil: {{ $ecMap[$compradorItem->estado_civil] ?? $compradorItem->estado_civil }}. @endif
            @if($compradorItem->profissao) Profissão: {{ $compradorItem->profissao }}. @endif
            @if($compradorItem->cpf) CPF: {{ $compradorItem->cpf }}. @endif
            @if($compradorItem->cnpj && !$compradorItem->cpf) CNPJ: {{ $compradorItem->cnpj }}. @endif
            @if($compradorItem->rg) RG: {{ $compradorItem->rg }}@if($compradorItem->orgao_expedidor)/{{ $compradorItem->orgao_expedidor }}@endif. @endif
            @if($compradorItem->email) E-mail: {{ $compradorItem->email }}. @endif
            @if($compradorItem->endereco)
                Endereço: {{ $compradorItem->endereco }}
                @if($compradorItem->numero), {{ $compradorItem->numero }}@endif
                @if($compradorItem->complemento), {{ $compradorItem->complemento }}@endif
                @if($compradorItem->bairro), {{ $compradorItem->bairro }}@endif
                @if($compradorItem->cidade), {{ $compradorItem->cidade }}@endif
                @if($compradorItem->estado)/{{ $compradorItem->estado }}@endif
                @if($compradorItem->cep), CEP {{ $compradorItem->cep }}@endif.
            @endif
        </div>
    </div>
    @endforeach

    @if(!empty($tenantTemplate?->clausulas_padrao) && count($tenantTemplate->clausulas_padrao) > 0)
        @foreach($tenantTemplate->clausulas_padrao as $i => $clausula)
        <div class="clause-title">Cláusula {{ $i + 1 }}</div>
        <div class="clause-body">{!! nl2br(e($clausula)) !!}</div>
        @endforeach
    @else
    <div class="clause-title">Cláusula Primeira: Do Objeto e da Promessa de Compra e Venda</div>
    <div class="clause-body">
        Constitui objeto deste contrato o imóvel descrito a seguir: {{ $objetoDescricao }}
        @if($contrato->matricula_numero) O imóvel está matriculado sob o nº {{ $contrato->matricula_numero }}@if($contrato->cartorio_nome), no {{ $contrato->cartorio_nome }}@endif. @endif
        @if($contrato->inscricao_cadastral) Índice cadastral nº {{ $contrato->inscricao_cadastral }}. @endif
    </div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Os PROMITENTES VENDEDORES prometem vender o imóvel livre e desembaraçado de ônus reais, fiscais, judiciais e extrajudiciais, respondendo pela evicção de direito, na forma da lei.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Os PROMISSÁRIOS COMPRADORES declaram ter visitado e vistoriado o imóvel, conhecendo suas características, benfeitorias, dimensões e estado de conservação, aceitando-o no estado em que se encontra.</div>

    <div class="clause-title">Cláusula Segunda: Do Preço e Forma de Pagamento</div>
    <div class="clause-body">
        O preço certo e ajustado para esta negociação é de <strong>R$ {{ number_format($contrato->valor_total ?? 0, 2, ',', '.') }}</strong>,
        a ser pago conforme condições abaixo.
    </div>
    @if($parcelas->isNotEmpty())
        @foreach($parcelas as $indice => $parcela)
        <div class="payment-item">
            <strong>{{ $parcela['descricao'] ?? chr(97 + $indice) . ')' }}</strong>
            @if(!empty($parcela['valor']))
                R$ {{ number_format((float) $parcela['valor'], 2, ',', '.') }}
            @endif
            @if(!empty($parcela['texto']))
                <div class="clause-body" style="margin-top:4px;">{!! nl2br(e($parcela['texto'])) !!}</div>
            @endif
        </div>
        @endforeach
    @else
        @if(!empty($contrato->valor_sinal))
        <div class="payment-item">
            <strong>a)</strong> R$ {{ number_format((float) $contrato->valor_sinal, 2, ',', '.') }} a título de sinal e princípio de pagamento.
        </div>
        @endif
        @if(!empty($contrato->valor_parcela_final))
        <div class="payment-item">
            <strong>b)</strong> R$ {{ number_format((float) $contrato->valor_parcela_final, 2, ',', '.') }} no ato da outorga da escritura pública de compra e venda.
        </div>
        @endif
    @endif
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Em caso de atraso tolerado pelos PROMITENTES VENDEDORES, incidirão multa de {{ number_format($contrato->multa_moratoria_percentual ?? 2, 2, ',', '.') }}% e juros de {{ number_format($contrato->juros_percentual_mes ?? 1, 2, ',', '.') }}% ao mês, calculados pro rata die.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Ultrapassado o prazo de tolerância, poderá ocorrer rescisão de pleno direito, com aplicação das penalidades deste contrato.</div>

    <div class="clause-title">Cláusula Terceira: Da Posse</div>
    <div class="clause-body">A posse direta do imóvel será transmitida aos PROMISSÁRIOS COMPRADORES mediante o pagamento integral do preço e a assinatura da escritura definitiva, com entrega das chaves na data ajustada entre as partes.</div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Na entrega das chaves, os PROMITENTES VENDEDORES apresentarão comprovantes de quitação de condomínio, IPTU, energia e demais encargos incidentes até a imissão da posse.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Débitos cujo fato gerador seja anterior à posse permanecerão sob responsabilidade dos PROMITENTES VENDEDORES.</div>

    <div class="clause-title">Cláusula Quarta: Das Despesas</div>
    <div class="clause-body">Correrão por conta dos PROMISSÁRIOS COMPRADORES as despesas com ITBI, escritura, registro, despachante e demais custos inerentes à transferência da propriedade, salvo ajuste diverso expresso neste instrumento.</div>

    <div class="clause-title">Cláusula Quinta: Da Transferência</div>
    <div class="clause-body">Os PROMITENTES VENDEDORES obrigam-se a transferir o domínio e o direito que possuem sobre o imóvel, livre de ônus e tributos vencidos, no ato da outorga da escritura pública de compra e venda.</div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Os PROMITENTES VENDEDORES deverão entregar a documentação necessária para a transferência no prazo de {{ $contrato->prazo_documentacao_dias ?? 10 }} dias contados da assinatura deste contrato.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> A escritura deverá ser providenciada em até {{ $contrato->prazo_escritura_dias ?? 30 }} dias, ressalvadas hipóteses de força maior e fatos alheios à vontade das partes.</div>

    <div class="clause-title">Cláusula Sexta: Do Registro</div>
    <div class="clause-body">Os PROMISSÁRIOS COMPRADORES comprometem-se a protocolizar a escritura pública para registro no cartório competente no prazo máximo de {{ $contrato->prazo_registro_dias ?? 5 }} dias úteis contados da assinatura da escritura.</div>

    <div class="clause-title">Cláusula Sétima: Da Multa Penal Contratual</div>
    <div class="clause-body">O descumprimento de qualquer obrigação deste contrato poderá ensejar rescisão de pleno direito, independentemente de notificação, e aplicação de multa penal equivalente a {{ number_format($contrato->multa_percentual ?? 10, 2, ',', '.') }}% sobre o valor total da negociação, sem prejuízo das perdas e danos comprovados.</div>

    <div class="clause-title">Cláusula Oitava: Da Intermediação</div>
    <div class="clause-body">
        A intermediação desta negociação foi realizada por
        <strong>{{ $contrato->intermediadora_nome ?: ($tenant?->name ?? 'imobiliária intermediadora') }}</strong>
        @if($contrato->intermediadora_documento), documento {{ $contrato->intermediadora_documento }}@endif
        @if($contrato->intermediadora_fantasia), nome fantasia {{ $contrato->intermediadora_fantasia }}@endif.
        @if(!empty($contrato->corretagem_valor))
            O valor da corretagem é de <strong>R$ {{ number_format((float) $contrato->corretagem_valor, 2, ',', '.') }}</strong>
            @if($contrato->corretagem_responsavel), de responsabilidade de {{ $contrato->corretagem_responsavel }}@endif.
        @endif
    </div>

    <div class="clause-title">Cláusula Nona: Da Irretratabilidade e Irrevogabilidade</div>
    <div class="clause-body">O presente contrato é celebrado em caráter irretratável, irrevogável e inarrependível, obrigando as partes, seus herdeiros e sucessores, ao fiel cumprimento de todas as cláusulas aqui pactuadas.</div>

    <div class="clause-title">Cláusula Décima: Do Foro</div>
    <div class="clause-body">Fica eleito o foro da comarca de {{ $cidadeImovel }}/{{ $estadoImovel }}, com renúncia expressa a qualquer outro, por mais privilegiado que seja, para dirimir quaisquer questões oriundas deste contrato.</div>
    @endif

    @if($contrato->clausulas && count($contrato->clausulas) > 0)
    <div class="clause-title">Cláusulas Específicas</div>
    @foreach($contrato->clausulas as $indice => $clausula)
    <div class="clause-body"><strong>{{ $indice + 1 }}.</strong> {{ $clausula }}</div>
    @endforeach
    @endif

    @if($contrato->observacoes)
    <div class="clause-title">Observações</div>
    <div class="clause-body">{!! nl2br(e($contrato->observacoes)) !!}</div>
    @endif

    <div class="clause-body" style="margin-top:18px; text-align:right;">
        {{ $cidadeImovel }}/{{ $estadoImovel }},
        @if($contrato->data_contrato)
            {{ $contrato->data_contrato->format('d/m/Y') }}
        @else
            ____/____/________
        @endif
    </div>

    <table class="signature-grid">
        <tr>
            @foreach($vendedores as $vendedorItem)
            <td>
                <div class="signature-line">{{ $vendedorItem->nome }}</div>
                <div class="signature-sub">PROMITENTE VENDEDOR(A)</div>
            </td>
            @endforeach
        </tr>
    </table>

    <table class="signature-grid">
        <tr>
            @foreach($compradores as $compradorItem)
            <td>
                <div class="signature-line">{{ $compradorItem->nome }}</div>
                <div class="signature-sub">PROMISSÁRIO(A) COMPRADOR(A)</div>
            </td>
            @endforeach
        </tr>
    </table>

    <table class="signature-grid">
        <tr>
            <td>
                <div class="signature-line">{{ $contrato->testemunha_um_nome ?: 'Testemunha 1' }}</div>
                <div class="signature-sub">{{ $contrato->testemunha_um_documento ?: 'Documento' }} @if($contrato->testemunha_um_email)| {{ $contrato->testemunha_um_email }}@endif</div>
            </td>
            <td>
                <div class="signature-line">{{ $contrato->testemunha_dois_nome ?: 'Testemunha 2' }}</div>
                <div class="signature-sub">{{ $contrato->testemunha_dois_documento ?: 'Documento' }} @if($contrato->testemunha_dois_email)| {{ $contrato->testemunha_dois_email }}@endif</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        @if(!empty($tenantTemplate?->rodape_texto))
            {{ $tenantTemplate->rodape_texto }}
        @else
            Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }} | Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }}
        @endif
    </div>
</div>

</body>
</html>
