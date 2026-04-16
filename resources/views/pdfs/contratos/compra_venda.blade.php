<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 24mm 16mm 20mm 16mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #20232a; line-height: 1.65; }
    .sheet { position: relative; }
    .watermark { position: absolute; top: 50%; left: 50%; width: 52%; transform: translate(-50%, -50%); opacity: .15; z-index: 0; }
    .watermark img { width: 100%; height: auto; }
    .shell { position: relative; z-index: 1; border: 1px solid rgba(31, 41, 55, 0.08); border-radius: 12px; padding: 16mm 14mm 14mm; min-height: calc(100vh - 38mm); background: rgba(255,255,255,.85); }
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
    $prazoDocumentacao = $contrato->prazo_documentacao_dias ?? 5;
    $prazoEscritura = $contrato->prazo_escritura_dias ?? 30;
    $prazoRegistro = $contrato->prazo_registro_dias ?? 5;
    $parcelasTexto = $parcelas->map(function ($parcela) {
        return mb_strtolower(trim((string) (($parcela['descricao'] ?? '') . ' ' . ($parcela['texto'] ?? ''))));
    })->implode(' ');
    $temFinanciamento = str_contains($parcelasTexto, 'financi') || str_contains($parcelasTexto, 'fgts');
    $temSaldoFinal = !empty($contrato->valor_parcela_final) || $temFinanciamento;
    $foroCidade = $cidadeImovel ?: 'Belo Horizonte';
    $foroEstado = $estadoImovel ?: 'MG';
@endphp

<div class="sheet">
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
    <div class="clause-title">Cláusula Primeira: Do Objeto</div>
    <div class="clause-body">
        Constitui objeto deste contrato o imóvel descrito a seguir: {{ $objetoDescricao }}
        @if($contrato->matricula_numero) O imóvel está matriculado sob o nº {{ $contrato->matricula_numero }}@if($contrato->cartorio_nome), no {{ $contrato->cartorio_nome }}@endif. @endif
        @if($contrato->inscricao_cadastral) Índice cadastral nº {{ $contrato->inscricao_cadastral }}. @endif
    </div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Os PROMITENTES VENDEDORES prometem vender o imóvel livre e desembaraçado de ônus reais, fiscais, judiciais e extrajudiciais, respondendo pela evicção de direito, na forma da lei.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Os PROMITENTES VENDEDORES declaram, sob responsabilidade civil e penal, inexistirem débitos pretéritos ou gravames não informados neste instrumento.</div>
    <div class="clause-paragraph"><strong>Parágrafo Terceiro:</strong> Os PROMISSÁRIOS COMPRADORES declaram ter visitado e vistoriado o imóvel, conhecendo suas características, benfeitorias, dimensões, localização e estado de conservação aparente.</div>

    <div class="clause-title">Cláusula Segunda: Da Forma de Pagamento</div>
    <div class="clause-body">
        O preço certo e ajustado para esta negociação é de <strong>R$ {{ number_format($contrato->valor_total ?? 0, 2, ',', '.') }}</strong>,
        a ser pago conforme as condições abaixo, valendo o sinal, quando previsto, como arras confirmatórias.
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
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Os pagamentos somente serão considerados quitados após a compensação bancária e a efetiva disponibilidade dos valores em favor dos PROMITENTES VENDEDORES.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Em caso de atraso, incidirão multa moratória de {{ number_format($contrato->multa_moratoria_percentual ?? 2, 2, ',', '.') }}% e juros de {{ number_format($contrato->juros_percentual_mes ?? 1, 2, ',', '.') }}% ao mês, calculados pro rata die, sem prejuízo das demais consequências contratuais e legais.</div>

    <div class="clause-title">Cláusula Terceira: Da Posse e da Escritura</div>
    <div class="clause-body">A posse direta do imóvel e a entrega das chaves ficarão vinculadas à quitação integral do preço, à liberação definitiva dos valores e à formalização do instrumento hábil para transferência da propriedade.</div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Na entrega das chaves, os PROMITENTES VENDEDORES deverão apresentar comprovantes de quitação de condomínio, IPTU e demais encargos incidentes até a data da posse.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Havendo financiamento bancário, as partes obrigam-se a assinar o contrato bancário, escritura ou termo equivalente tão logo sejam convocadas pelo agente financeiro, despachante ou cartório competente.</div>
    <div class="clause-paragraph"><strong>Parágrafo Terceiro:</strong> @if($contrato->data_entrega_chaves)A entrega das chaves está prevista para {{ $contrato->data_entrega_chaves->format('d/m/Y') }}, ressalvada a necessidade de quitação integral e cumprimento das etapas registrais.@else A entrega das chaves observará a data ajustada entre as partes, sempre condicionada à quitação integral do negócio e à regular formalização do título.@endif</div>

    <div class="clause-title">Cláusula Quarta: Da Condição Suspensiva de Financiamento e Avaliação</div>
    <div class="clause-body">
        @if($temSaldoFinal)
            Caso a operação dependa, total ou parcialmente, de financiamento imobiliário, FGTS ou avaliação bancária, a conclusão do negócio ficará condicionada à aprovação do crédito do(s) PROMISSÁRIO(S) COMPRADOR(ES), à aprovação jurídica do imóvel e à avaliação suficiente para viabilizar a compra nas condições pactuadas.
        @else
            Não havendo financiamento bancário, eventual formalização por instituição financeira ou exigência registral superveniente dependerá de ajuste entre as partes, preservado o preço e as condições essenciais já pactuadas.
        @endif
    </div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Se o financiamento não for aprovado por motivo não imputável ao(s) PROMISSÁRIO(S) COMPRADOR(ES), ou se a avaliação inviabilizar a operação nos termos ajustados, as partes poderão renegociar a forma de pagamento ou resolver o contrato sem multa.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Na hipótese de resolução sem culpa do(s) PROMISSÁRIO(S) COMPRADOR(ES), os valores pagos deverão ser restituídos em até 3 (três) dias úteis, salvo retenções legalmente admissíveis e expressamente previstas.</div>

    <div class="clause-title">Cláusula Quinta: Do Registro do Imóvel e da Documentação</div>
    <div class="clause-body">Os documentos necessários à formalização do negócio deverão ser apresentados e assinados pelas partes nos prazos abaixo, observadas as exigências do banco, do cartório e da legislação aplicável.</div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Os PROMITENTES VENDEDORES deverão disponibilizar a documentação do imóvel e das pessoas envolvidas no prazo de até {{ $prazoDocumentacao }} dia(s) úteis, contado da solicitação formal ou da assinatura deste instrumento.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> A escritura, contrato bancário ou instrumento equivalente deverá ser providenciado em até {{ $prazoEscritura }} dia(s), admitida prorrogação quando houver pendência operacional, bancária, cadastral ou registral não causada por culpa da parte interessada.</div>
    <div class="clause-paragraph"><strong>Parágrafo Terceiro:</strong> Após a assinatura do título apto à transferência, o protocolo para registro no cartório competente deverá ocorrer em até {{ $prazoRegistro }} dia(s) úteis, cabendo ao(s) PROMISSÁRIO(S) COMPRADOR(ES) acompanhar o procedimento.</div>

    <div class="clause-title">Cláusula Sexta: Dos Encargos e Despesas</div>
    <div class="clause-body">Correrão por conta dos PROMISSÁRIOS COMPRADORES as despesas de ITBI, escritura, emolumentos, registro, despachante, tarifas e custos bancários da transferência, salvo ajuste escrito em contrário.</div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Permanecerão sob responsabilidade dos PROMITENTES VENDEDORES os débitos e encargos cujo fato gerador seja anterior à imissão de posse do(s) PROMISSÁRIO(S) COMPRADOR(ES).</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Após a posse, os encargos ordinários, consumos e tributos correntes passarão a ser suportados pelo(s) PROMISSÁRIO(S) COMPRADOR(ES), observado eventual rateio proporcional.</div>

    <div class="clause-title">Cláusula Sétima: Das Penalidades</div>
    <div class="clause-body">O inadimplemento de obrigação essencial poderá ensejar a resolução do contrato e a aplicação de multa contratual equivalente a {{ number_format($contrato->multa_percentual ?? 10, 2, ',', '.') }}% sobre o valor total do negócio, sem prejuízo de perdas e danos comprovados, quando cabíveis.</div>
    <div class="clause-paragraph"><strong>Parágrafo Primeiro:</strong> Antes da resolução por inadimplemento sanável, a parte inadimplente deverá ser notificada para purgar a mora em prazo razoável, salvo hipótese de urgência, impossibilidade de cura ou descumprimento definitivo.</div>
    <div class="clause-paragraph"><strong>Parágrafo Segundo:</strong> Se a inexecução decorrer da parte que deu as arras, poderá a outra tê-las por retidas; se decorrer da parte que as recebeu, poderá quem as pagou exigir sua devolução mais o equivalente, na forma da lei.</div>

    <div class="clause-title">Cláusula Oitava: Da Boa-fé e Cooperação</div>
    <div class="clause-body">As partes comprometem-se a agir com boa-fé, transparência e cooperação, fornecendo as informações, certidões, assinaturas e documentos necessários para a regular conclusão do negócio.</div>

    <div class="clause-title">Cláusula Nona: Da Intermediação</div>
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

    <div class="clause-title">Cláusula Décima: Da Irretratabilidade e Irrevogabilidade</div>
    <div class="clause-body">O presente contrato é celebrado em caráter irretratável, irrevogável e inarrependível, obrigando as partes, seus herdeiros e sucessores, ao fiel cumprimento de todas as cláusulas aqui pactuadas.</div>

    <div class="clause-title">Cláusula Décima Primeira: Do Foro</div>
    <div class="clause-body">Fica eleito o foro da comarca de {{ $foroCidade }}/{{ $foroEstado }}, com renúncia expressa a qualquer outro, por mais privilegiado que seja, para dirimir quaisquer questões oriundas deste contrato.</div>
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

</div>

</body>
</html>
