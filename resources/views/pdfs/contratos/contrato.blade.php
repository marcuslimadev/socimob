<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #222; line-height: 1.6; }

    /* ─── CABEÇALHO ─── */
    .header { text-align: center; border-bottom: 2px solid #222; padding-bottom: 12px; margin-bottom: 18px; }
    .header h1 { font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold; }
    .header p  { font-size: 9px; margin-top: 4px; color: #555; }

    .intro-texto { margin-bottom: 14px; font-size: 10px; line-height: 1.7; text-align: justify; }

    /* ─── QUADRO RESUMO (tabela) ─── */
    .quadro { border: 1.5px solid #444; margin-bottom: 18px; }
    .quadro-title { background: #333; color: #fff; text-align: center; font-size: 11px; font-weight: bold;
                    text-transform: uppercase; letter-spacing: 1px; padding: 5px 8px; }
    .quadro-table { width: 100%; border-collapse: collapse; }
    .quadro-table td { padding: 5px 10px; border-bottom: 1px solid #ddd; vertical-align: top; font-size: 10px; }
    .quadro-table td.qlabel { font-weight: bold; width: 32%; background: #f5f5f5; color: #444; }
    .quadro-table tr:last-child td { border-bottom: none; }

    /* ─── CLÁUSULAS ─── */
    .clausula-titulo { font-size: 10px; font-weight: bold; text-transform: uppercase;
                       margin-top: 14px; margin-bottom: 4px; }
    .clausula-body { font-size: 9.5px; text-align: justify; margin-bottom: 6px; line-height: 1.7; }
    .clausula-paragrafo { font-size: 9.5px; text-align: justify; margin-bottom: 5px;
                          padding-left: 16px; line-height: 1.7; }

    /* ─── ABERTURA ─── */
    .abertura { font-size: 9.5px; text-align: justify; margin-bottom: 12px; line-height: 1.7;
                border-top: 1px solid #ccc; padding-top: 12px; }

    /* ─── TABELA FIADORES ─── */
    .fiad-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9px; }
    .fiad-table th { background: #333; color: #fff; padding: 4px 8px; text-align: left; }
    .fiad-table td { padding: 4px 8px; border-bottom: 1px solid #ddd; }
    .fiad-table tr:nth-child(even) td { background: #f9f9f9; }

    /* ─── ASSINATURAS ─── */
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 56px; }
    .sig-table td { text-align: center; padding: 0 10px; vertical-align: bottom; width: 33%; }
    .sig-line { border-top: 1px solid #333; padding-top: 5px; font-size: 9px; font-weight: bold;
                margin-top: 50px; }
    .sig-sub  { font-size: 8px; color: #555; margin-top: 2px; }

    /* ─── RODAPÉ ─── */
    .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #999;
              border-top: 1px solid #ddd; padding-top: 8px; }

    .mt-12 { margin-top: 12px; }
    .section-label { font-size: 10px; font-weight: bold; text-transform: uppercase;
                     background: #eee; padding: 3px 8px; border-left: 3px solid #333;
                     margin-bottom: 6px; margin-top: 14px; }
</style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     CABEÇALHO
════════════════════════════════════════════════════════════ --}}
<div class="header">
    <h1>{{ $tenantTemplate?->titulo ?? 'Contrato de Locação de Imóvel Urbano' }}</h1>
    <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} &nbsp;|&nbsp; Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</div>

{{-- TEXTO DE ABERTURA do tenant --}}
@if(!empty($tenantTemplate?->intro_texto))
<div class="intro-texto">{!! nl2br(e($tenantTemplate->intro_texto)) !!}</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     QUADRO RESUMO
════════════════════════════════════════════════════════════ --}}
@php
    $ecMap = [
        'solteiro'      => 'solteiro(a)',
        'casado'        => 'casado(a)',
        'uniao_estavel' => 'em união estável',
        'divorciado'    => 'divorciado(a)',
        'viuvo'         => 'viúvo(a)',
        'separado'      => 'separado(a)',
    ];
    $garantiaMap = [
        'none'                 => 'Sem garantia',
        'caucao'               => 'Caução',
        'fiador'               => 'Fiador',
        'seguro_fianca'        => 'Seguro Fiança',
        'titulo_capitalizacao' => 'Título de Capitalização',
        'fianca_bancaria'      => 'Fiança Bancária',
    ];
    $todosLocadores = $contrato->todosLocadores();
@endphp

<div class="quadro">
    <div class="quadro-title">Quadro Resumo</div>
    <table class="quadro-table">

        {{-- 1. LOCADOR(ES) --}}
        <tr>
            <td class="qlabel">1.&nbsp;Locador(es)</td>
            <td>
                @foreach($todosLocadores as $loc)
                    <strong>{{ $loc->nome }}</strong>
                    @if($loc->cpf), CPF: {{ $loc->cpf }}@endif
                    @if($loc->rg), RG: {{ $loc->rg }}@if($loc->orgao_expedidor)/{{ $loc->orgao_expedidor }}@endif
                    @endif
                    @if($loc->nacionalidade), {{ $loc->nacionalidade }}@endif
                    @if($loc->profissao), {{ $loc->profissao }}@endif
                    @if(!empty($loc->estado_civil))
                        , {{ $ecMap[$loc->estado_civil] ?? $loc->estado_civil }}
                        @if(in_array($loc->estado_civil, ['casado','uniao_estavel']) && $loc->conjuge_nome)
                            &nbsp;com <strong>{{ $loc->conjuge_nome }}</strong>
                            @if($loc->conjuge_cpf), CPF: {{ $loc->conjuge_cpf }}@endif
                            @if($loc->conjuge_rg), RG: {{ $loc->conjuge_rg }}@if($loc->conjuge_orgao_expedidor)/{{ $loc->conjuge_orgao_expedidor }}@endif
                            @endif
                            @if($loc->conjuge_profissao), {{ $loc->conjuge_profissao }}@endif
                            @if($loc->conjuge_nacionalidade), {{ $loc->conjuge_nacionalidade }}@endif
                        @endif
                    @endif
                    @if($loc->endereco)
                        , residente: {{ $loc->endereco }}
                        @if($loc->numero) nº {{ $loc->numero }}@endif
                        @if($loc->complemento), {{ $loc->complemento }}@endif
                        @if($loc->bairro), {{ $loc->bairro }}@endif
                        @if($loc->cidade), {{ $loc->cidade }}@endif
                        @if($loc->estado)/{{ $loc->estado }}@endif
                        @if($loc->cep), CEP: {{ $loc->cep }}@endif
                    @endif
                    @if(!$loop->last)<br><br>@endif
                @endforeach
            </td>
        </tr>

        {{-- 2. LOCATÁRIO --}}
        <tr>
            <td class="qlabel">2.&nbsp;Locatário</td>
            <td>
                <strong>{{ $locatario->nome ?? '—' }}</strong>
                @if(!empty($locatario->nacionalidade)), {{ $locatario->nacionalidade }}@endif
                @if(!empty($locatario->estado_civil)), {{ $ecMap[$locatario->estado_civil] ?? $locatario->estado_civil }}@endif
                @if(!empty($locatario->profissao)), {{ $locatario->profissao }}@endif
                @if(!empty($locatario->cpf)), CPF: {{ $locatario->cpf }}@endif
                @if(!empty($locatario->rg)), RG: {{ $locatario->rg }}@if(!empty($locatario->orgao_expedidor))/{{ $locatario->orgao_expedidor }}@endif
                @endif
                @if(!empty($locatario->endereco))
                    , residente: {{ $locatario->endereco }}
                    @if(!empty($locatario->numero)) nº {{ $locatario->numero }}@endif
                    @if(!empty($locatario->complemento)), {{ $locatario->complemento }}@endif
                    @if(!empty($locatario->bairro)), {{ $locatario->bairro }}@endif
                    @if(!empty($locatario->cidade)), {{ $locatario->cidade }}@endif
                    @if(!empty($locatario->estado))/{{ $locatario->estado }}@endif
                    @if(!empty($locatario->cep)), CEP: {{ $locatario->cep }}@endif
                @endif
                @if(!empty($locatario->email)) — E-mail: {{ $locatario->email }}@endif
                @if(!empty($locatario->celular)) — Cel.: {{ $locatario->celular }}@endif
            </td>
        </tr>

        {{-- 3. IMÓVEL --}}
        <tr>
            <td class="qlabel">3.&nbsp;Imóvel</td>
            <td>
                @php $endImovel = $imovel->logradouro ?? $imovel->endereco ?? ''; @endphp
                {{ $endImovel }}
                @if(!empty($imovel->numero)) Nº {{ $imovel->numero }}@endif
                @if(!empty($imovel->complemento)), {{ $imovel->complemento }}@endif
                @if(!empty($imovel->bairro)), {{ $imovel->bairro }}@endif
                @if(!empty($imovel->cidade)), {{ $imovel->cidade }}@endif
                @if(!empty($imovel->estado))/{{ $imovel->estado }}@endif
                @if(!empty($imovel->cep)), CEP: {{ $imovel->cep }}@endif
            </td>
        </tr>

        {{-- 4. ALUGUEL --}}
        <tr>
            <td class="qlabel">4.&nbsp;Aluguel Mensal</td>
            <td><strong>R$ {{ number_format($contrato->valor_aluguel ?? 0, 2, ',', '.') }}</strong></td>
        </tr>

        {{-- 5. PRAZO --}}
        <tr>
            <td class="qlabel">5.&nbsp;Prazo de Locação</td>
            <td>
                De <strong>{{ $contrato->inicio?->format('d/m/Y') ?? '—' }}</strong>
                a <strong>{{ $contrato->fim?->format('d/m/Y') ?? '—' }}</strong>
                @if($contrato->inicio && $contrato->fim)
                    ({{ $contrato->inicio->diffInMonths($contrato->fim) }} meses)
                @endif
            </td>
        </tr>

        {{-- 6. PAGAMENTO --}}
        <tr>
            <td class="qlabel">6.&nbsp;Data de Pagamento</td>
            <td>Dia <strong>{{ $contrato->dia_vencimento ?? '—' }}</strong> de cada mês</td>
        </tr>

        {{-- 7. DESTINAÇÃO --}}
        <tr>
            <td class="qlabel">7.&nbsp;Destinação do Imóvel</td>
            <td><strong>{{ strtoupper($contrato->destinacao_imovel ?? 'Residencial') }}</strong></td>
        </tr>

        {{-- 8. GARANTIA --}}
        <tr>
            <td class="qlabel">8.&nbsp;Garantia Locatícia</td>
            <td>
                <strong>{{ $garantiaMap[$contrato->tipo_garantia ?? ''] ?? ucfirst(str_replace('_', ' ', $contrato->tipo_garantia ?? 'Não informada')) }}</strong>
                @if(!empty($contrato->garantidora_nome))
                    — {{ $contrato->garantidora_nome }}
                    @if(!empty($contrato->garantidora_cnpj)), CNPJ: {{ $contrato->garantidora_cnpj }}@endif
                @endif
                @if(!empty($contrato->valor_garantia))
                    — Valor: R$ {{ number_format($contrato->valor_garantia, 2, ',', '.') }}
                @endif
            </td>
        </tr>

        {{-- 9. DATA ASSINATURA --}}
        <tr>
            <td class="qlabel">9.&nbsp;Data de Assinatura</td>
            <td>
                @if(!empty($contrato->data_assinatura))
                    {{ $contrato->data_assinatura->format('d/m/Y') }}
                @elseif(!empty($contrato->inicio))
                    {{ $contrato->inicio->format('d/m/Y') }}
                @else
                    —
                @endif
            </td>
        </tr>

    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     ABERTURA DO CONTRATO
════════════════════════════════════════════════════════════ --}}
<p class="abertura">
    As partes identificadas no item 1 e no item 2 do Quadro Resumo resolvem firmar o presente
    <strong>"Contrato de Locação de Imóvel Urbano"</strong>, que se regerá pelas seguintes
    cláusulas e condições, que mutuamente outorgam e aceitam:
</p>

{{-- ══════════════════════════════════════════════════════════
     CLÁUSULAS
════════════════════════════════════════════════════════════ --}}

@if(!empty($tenantTemplate?->clausulas_padrao) && count($tenantTemplate->clausulas_padrao) > 0)

    {{-- Cláusulas personalizadas do tenant --}}
    @foreach($tenantTemplate->clausulas_padrao as $i => $clausula)
    @php
        $clausulaTexto = trim((string) $clausula);
        $clausulaPersonalizadaTemTitulo = \\Illuminate\\Support\\Str::startsWith(
            \\Illuminate\\Support\\Str::upper($clausulaTexto),
            'CLÁUSULA '
        );
    @endphp
    @unless($clausulaPersonalizadaTemTitulo)
    <div class="clausula-titulo">Cláusula {{ $i + 1 }}</div>
    @endunless
    <div class="clausula-body">{!! nl2br(e($clausulaTexto)) !!}</div>
    @endforeach

@else

    {{-- ── CLÁUSULAS PADRÃO COMPLETAS ── --}}

    <div class="clausula-titulo">Cláusula Primeira — Objeto do Contrato</div>
    <div class="clausula-body">O objeto do presente Contrato é a locação do imóvel descrito no item 3 do Quadro Resumo.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> O LOCATÁRIO declara ter visitado o imóvel, tendo-o achado conforme, estando ciente de suas características, tamanho, benfeitorias e pertences, aceitando-o no estado de conservação em que se encontra.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> O LOCATÁRIO se compromete a receber os profissionais responsáveis por obras e serviços em execução no imóvel, conforme acordado entre as partes.</div>

    <div class="clausula-titulo">Cláusula Segunda — Prazo e Data de Locação</div>
    <div class="clausula-body">A locação é ajustada pelo prazo mencionado no item 5 do Quadro Resumo e terminará após a data final do Contrato, independentemente de qualquer notificação, aviso, interpelação judicial ou extrajudicial.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> Poderá o LOCADOR, após o término do Presente Contrato, estipular preço, prazo e demais condições para a renovação da locação do imóvel.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> Se o LOCATÁRIO continuar na posse do imóvel por mais de 30 (trinta) dias após o término do Contrato sem oposição do LOCADOR, o Presente instrumento terá sua vigência prorrogada por prazo indeterminado.</div>

    <div class="clausula-titulo">Cláusula Terceira — Preço e Reajuste</div>
    <div class="clausula-body">O valor mensal da locação está descrito no item 4 do Quadro Resumo. O aluguel será reajustado anualmente pelo índice <strong>{{ strtoupper($contrato->indice_reajuste ?? 'IGP-M') }}</strong>, publicado pela Fundação Getúlio Vargas, ou, na falta deste, pelo maior índice permitido pelo Governo Federal, a cada {{ $contrato->periodicidade_reajuste ?? 12 }} (doze) meses.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> Quando o índice de reajuste for negativo, o valor do aluguel mensal não sofrerá alterações.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> Vencido o prazo de locação e não fixando as partes novo período, o valor do aluguel será reajustado pelo maior índice permitido em lei.</div>

    <div class="clausula-titulo">Cláusula Quarta — Pagamento</div>
    <div class="clausula-body">O aluguel será pago impreterivelmente na data informada no item 6 do Quadro Resumo, de cada mês subsequente ao mês vencido, através de boleto bancário ou conforme convencionado pela ADMINISTRADORA.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> O aluguel só será recebido com os encargos locatícios ajustados neste Contrato. O descumprimento caracterizará infração contratual.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> Em caso de não recebimento do boleto, o LOCATÁRIO deverá comunicar ao LOCADOR com antecedência mínima de 5 (cinco) dias úteis antes do vencimento.</div>
    <div class="clausula-paragrafo"><strong>§ 3º</strong> Eventuais débitos não pagos poderão ser comunicados a entidades de proteção ao crédito (SERASA, SPC, etc.) independentemente de comunicação prévia.</div>

    <div class="clausula-titulo">Cláusula Quinta — Multa por Inadimplência</div>
    <div class="clausula-body">O aluguel e os encargos não pagos na data do vencimento serão acrescidos de: multa moratória de <strong>10% (dez por cento)</strong>; juros de <strong>1% (um por cento)</strong> ao mês; correção monetária; honorários advocatícios de <strong>10%</strong> em composição amigável ou <strong>20%</strong> em procedimento judicial; e custas processuais.</div>
    <div class="clausula-paragrafo"><strong>Parágrafo Único</strong> — Se o atraso for superior a 30 (trinta) dias, o débito será monetariamente atualizado pelo IGP-M/FGV.</div>

    <div class="clausula-titulo">Cláusula Sexta — Encargos e Obrigações</div>
    <div class="clausula-body">Além do aluguel, pagará o LOCATÁRIO: IPTU, despesas de manutenção, água, energia elétrica, gás, condomínio e outras taxas incidentes sobre o imóvel durante o período da locação.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> O LOCATÁRIO se obriga a comprovar, no ato do pagamento dos aluguéis, a quitação plena de todos os encargos da locação.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> Correrão por conta do LOCATÁRIO todas as despesas bancárias, emolumentos de cartório e demais despesas de formalização do Contrato.</div>
    <div class="clausula-paragrafo"><strong>§ 3º</strong> O LOCATÁRIO é responsável pela manutenção, conservação e reparo de pequenos danos ou defeitos no imóvel, nos termos do art. 23, V, da Lei 8.245/91.</div>

    <div class="clausula-titulo">Cláusula Sétima — Seguro Contra Incêndio</div>
    <div class="clausula-body">O LOCATÁRIO pagará o seguro contra incêndio em Companhia de livre escolha do LOCADOR. A apólice terá vigência de 12 (doze) meses, com renovação anual obrigatória.</div>
    <div class="clausula-paragrafo"><strong>Parágrafo Único</strong> — No caso de incêndio ou sinistro que inutilize o uso normal do imóvel, o Presente Contrato fica rescindido de pleno direito. Caso o LOCATÁRIO não faça o seguro e haja incêndio, responderá por perdas e danos perante o LOCADOR.</div>

    <div class="clausula-titulo">Cláusula Oitava — Cessão, Empréstimo e Sublocação</div>
    <div class="clausula-body">O LOCATÁRIO não poderá ceder, sublocar, emprestar ou transferir o uso do imóvel a terceiros, no todo ou em parte, sem prévia autorização por escrito do LOCADOR.</div>

    <div class="clausula-titulo">Cláusula Nona — Benfeitorias e Alterações</div>
    <div class="clausula-body">É vedado ao LOCATÁRIO, sob pena de rescisão, fazer quaisquer benfeitorias ou alterações no imóvel sem prévio consentimento por escrito do LOCADOR.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> As benfeitorias realizadas, mesmo que permitidas, não darão ao LOCATÁRIO direito a retenção, indenização ou abatimento de qualquer obrigação contratual.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> Perfurações em paredes devem respeitar a integridade estrutural, a rede elétrica e hidráulica. Não são permitidas perfurações em pisos ou paredes azulejadas sem consentimento do LOCADOR.</div>

    <div class="clausula-titulo">Cláusula Décima — Devolução do Imóvel</div>
    <div class="clausula-body">O LOCATÁRIO deverá notificar o LOCADOR por escrito com antecedência mínima de 30 (trinta) dias da intenção de devolução do imóvel.</div>
    <div class="clausula-paragrafo"><strong>§ 1º</strong> O LOCATÁRIO compromete-se a restituir o imóvel conforme Laudo de Vistoria Inicial: totalmente limpo, pintado com a mesma cor por profissional, e com todas as instalações em perfeito funcionamento.</div>
    <div class="clausula-paragrafo"><strong>§ 2º</strong> Se o LOCATÁRIO devolver o imóvel antes do vencimento do prazo, pagará ao LOCADOR multa compensatória de <strong>15% (quinze por cento)</strong> sobre o valor total da locação proporcional ao período restante.</div>
    <div class="clausula-paragrafo"><strong>§ 3º</strong> O LOCADOR poderá recusar o recebimento das chaves se o LOCATÁRIO não comprovar a quitação de todos os encargos (IPTU, condomínio, água, energia elétrica, gás, etc.).</div>

    <div class="clausula-titulo">Cláusula Décima Primeira — Destinação do Imóvel</div>
    <div class="clausula-body">O imóvel locado destina-se exclusivamente ao uso <strong>{{ strtoupper($contrato->destinacao_imovel ?? 'RESIDENCIAL') }}</strong>, não sendo permitida a mudança de uso sob qualquer pretexto. A violação desta cláusula constitui infração contratual passível de rescisão.</div>

    <div class="clausula-titulo">Cláusula Décima Segunda — Penalidade</div>
    <div class="clausula-body">O descumprimento de qualquer cláusula contratual sujeitará o infrator a multa de <strong>3 (três) meses de aluguel atualizado</strong>, sem prejuízo das demais penalidades previstas neste instrumento.</div>

    <div class="clausula-titulo">Cláusula Décima Terceira — Citações e Comunicações</div>
    <div class="clausula-body">O LOCATÁRIO aceita expressamente ser citado, notificado ou intimado mediante carta registrada, e-mail ou qualquer outro meio judicial ou extrajudicial, nos endereços indicados no item 2 do Quadro Resumo.</div>

    @if(!empty($contrato->garantidora_nome) || $contrato->tipo_garantia === 'seguro_fianca')
    <div class="clausula-titulo">Cláusula Décima Quarta — Garantia Locatícia — Seguro Fiança</div>
    <div class="clausula-body">O presente Contrato é garantido por Seguro Fiança prestado por <strong>{{ $contrato->garantidora_nome ?? 'empresa garantidora a ser indicada' }}</strong>@if(!empty($contrato->garantidora_cnpj)), inscrita no CNPJ sob o nº <strong>{{ $contrato->garantidora_cnpj }}</strong>@endif@if(!empty($contrato->garantidora_endereco)), com sede à {{ $contrato->garantidora_endereco }}@endif, que se compromete a efetuar o pagamento de eventuais débitos relativos aos aluguéis e encargos inadimplidos pelo LOCATÁRIO, conforme Termos e Condições Gerais dos Serviços, que integram o presente Contrato como Anexo.</div>
    @endif

    <div class="clausula-titulo">Cláusula {{ (!empty($contrato->garantidora_nome) || $contrato->tipo_garantia === 'seguro_fianca') ? 'Décima Quinta' : 'Décima Quarta' }} — Foro</div>
    <div class="clausula-body">Elegem as partes o foro de <strong>{{ $imovel->cidade ?? 'Belo Horizonte' }}</strong>/{{ $imovel->estado ?? 'MG' }} para dirimir qualquer pendência relativa a este Contrato, com expressa renúncia a qualquer outro, por mais privilegiado que seja.</div>

@endif

{{-- Cláusulas específicas do contrato --}}
@if($contrato->clausulas && count($contrato->clausulas) > 0)
<div class="clausula-titulo mt-12">Cláusulas Específicas deste Contrato</div>
@foreach($contrato->clausulas as $i => $clausula)
<div class="clausula-body"><strong>{{ $i + 1 }}.</strong> {{ $clausula }}</div>
@endforeach
@endif

{{-- Observações --}}
@if($contrato->observacoes)
<div class="clausula-titulo mt-12">Observações</div>
<div class="clausula-body">{{ $contrato->observacoes }}</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     FIADORES
════════════════════════════════════════════════════════════ --}}
@if($fiadores && $fiadores->isNotEmpty())
<div class="section-label">Fiadores / Garantidores</div>
<table class="fiad-table">
    <thead>
        <tr><th>Nome</th><th>CPF/CNPJ</th><th>RG</th><th>Tipo</th></tr>
    </thead>
    <tbody>
    @foreach($fiadores as $f)
    <tr>
        <td>{{ $f->pessoa->nome ?? '—' }}</td>
        <td>{{ $f->pessoa->cpf ?? $f->pessoa->cnpj ?? '—' }}</td>
        <td>{{ $f->pessoa->rg ?? '—' }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $f->tipo_vinculo ?? 'fiador')) }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
@endif

{{-- ══════════════════════════════════════════════════════════
     LOCAL E DATA
════════════════════════════════════════════════════════════ --}}
<p style="margin-top:20px; text-align:right; font-size:9.5px;">
    {{ $imovel->cidade ?? 'Belo Horizonte' }}/{{ $imovel->estado ?? 'MG' }},&nbsp;
    @if(!empty($contrato->data_assinatura))
        {{ $contrato->data_assinatura->format('d/m/Y') }}
    @elseif(!empty($contrato->inicio))
        {{ $contrato->inicio->format('d/m/Y') }}
    @else
        ____/____/________
    @endif
</p>

{{-- ══════════════════════════════════════════════════════════
     ASSINATURAS
════════════════════════════════════════════════════════════ --}}

{{-- Locadores --}}
@php $locadoresAssinatura = $contrato->todosLocadores(); @endphp
<table class="sig-table">
    <tr>
        @foreach($locadoresAssinatura as $loc)
        <td>
            <div class="sig-line">{{ $loc->nome }}</div>
            <div class="sig-sub">LOCADOR(A)</div>
            @if(in_array($loc->estado_civil ?? '', ['casado','uniao_estavel']) && !empty($loc->conjuge_nome))
                <br>
                <div class="sig-line">{{ $loc->conjuge_nome }}</div>
                <div class="sig-sub">CÔNJUGE / COMPANHEIRO(A)</div>
            @endif
        </td>
        @endforeach
    </tr>
</table>

{{-- Locatário e Fiadores --}}
<table class="sig-table">
    <tr>
        <td>
            <div class="sig-line">{{ $locatario->nome ?? 'Locatário' }}</div>
            <div class="sig-sub">LOCATÁRIO</div>
        </td>
        @foreach($fiadores ?? [] as $f)
        <td>
            <div class="sig-line">{{ $f->pessoa->nome ?? 'Fiador' }}</div>
            <div class="sig-sub">{{ strtoupper(str_replace('_',' ', $f->tipo_vinculo ?? 'FIADOR')) }}</div>
        </td>
        @endforeach
    </tr>
</table>

{{-- Testemunhas --}}
<table class="sig-table">
    <tr>
        <td>
            <div class="sig-line">Testemunha 1</div>
            <div class="sig-sub">CPF: ___.___.___-__</div>
        </td>
        <td>
            <div class="sig-line">Testemunha 2</div>
            <div class="sig-sub">CPF: ___.___.___-__</div>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════
     RODAPÉ
════════════════════════════════════════════════════════════ --}}
<div class="footer">
    @if(!empty($tenantTemplate?->rodape_texto))
        {{ $tenantTemplate->rodape_texto }}
    @else
        Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }} | Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }}
    @endif
</div>

</body>
</html>
