<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #222; line-height: 1.65; }

    .header { text-align: center; border-bottom: 2px solid #222; padding-bottom: 14px; margin-bottom: 20px; }
    .header h1 { font-size: 14px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold; }
    .header p  { font-size: 9.5px; margin-top: 4px; color: #555; }

    .intro-texto { margin-bottom: 16px; font-size: 10.5px; line-height: 1.7; text-align: justify; }

    /* ─── QUADRO RESUMO ─── */
    .quadro { border: 1.5px solid #333; border-radius: 4px; margin-bottom: 20px; }
    .quadro-title { background: #333; color: #fff; text-align: center; font-size: 11px; font-weight: bold;
                    text-transform: uppercase; letter-spacing: 1px; padding: 5px 8px; }
    .quadro table { width: 100%; border-collapse: collapse; }
    .quadro table td { padding: 5px 10px; border-bottom: 1px solid #ddd; vertical-align: top; font-size: 10px; }
    .quadro table td.label { font-weight: bold; width: 35%; background: #f7f7f7; color: #444; }
    .quadro table tr:last-child td { border-bottom: none; }

    /* ─── SEÇÕES ─── */
    .section { margin-bottom: 14px; }
    .section-title { font-size: 10.5px; font-weight: bold; text-transform: uppercase;
                     background: #eeeeee; padding: 4px 8px; border-left: 3px solid #333;
                     margin-bottom: 8px; letter-spacing: 0.5px; }

    .row { display: flex; gap: 12px; margin-bottom: 4px; }
    .field { flex: 1; }
    .field label { font-size: 8.5px; text-transform: uppercase; color: #666; display: block; }
    .field span  { font-size: 10.5px; font-weight: bold; }

    /* ─── CLÁUSULAS ─── */
    .clausulas-container { padding: 4px 0; }
    .clausula-titulo { font-size: 10.5px; font-weight: bold; text-transform: uppercase;
                       margin-top: 12px; margin-bottom: 4px; }
    .clausula-body { font-size: 10px; text-align: justify; margin-bottom: 6px; line-height: 1.7; }
    .clausula-paragrafo { font-size: 10px; text-align: justify; margin-bottom: 5px;
                          margin-left: 14px; line-height: 1.7; }

    /* ─── TABELA FIADORES ─── */
    .table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9.5px; }
    .table th { background: #333; color: #fff; padding: 5px 8px; text-align: left; }
    .table td { padding: 4px 8px; border-bottom: 1px solid #ddd; }
    .table tr:nth-child(even) td { background: #f9f9f9; }

    /* ─── ASSINATURAS ─── */
    .signatures { margin-top: 56px; }
    .sig-row { display: flex; gap: 24px; justify-content: space-around; margin-bottom: 36px; }
    .sig-block { flex: 1; text-align: center; max-width: 200px; }
    .sig-line { border-top: 1px solid #333; padding-top: 5px; font-size: 9.5px; margin-top: 52px; font-weight: bold; }
    .sig-sub  { font-size: 8.5px; color: #555; margin-top: 2px; }

    /* ─── RODAPÉ ─── */
    .footer { margin-top: 22px; text-align: center; font-size: 8.5px; color: #999;
              border-top: 1px solid #ddd; padding-top: 8px; }

    .page-break { page-break-before: always; }
    .text-center { text-align: center; }
    .bold { font-weight: bold; }
    .mt-8 { margin-top: 8px; }
    .highlight { background: #f0f0f0; padding: 6px 10px; border-radius: 3px; margin: 6px 0; font-size: 10px; }
</style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     CABEÇALHO
════════════════════════════════════════════════════════════ --}}
<div class="header">
    <h1>{{ $tenantTemplate?->titulo ?? 'Contrato de Locação de Imóvel Urbano' }}</h1>
    <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} &nbsp;|&nbsp;
       Gerado em {{ $geradoEm->format('d/m/Y \à\s H:i') }}</p>
</div>

{{-- TEXTO DE ABERTURA personalizado pelo tenant --}}
@if(!empty($tenantTemplate?->intro_texto))
<div class="intro-texto">
    {!! nl2br(e($tenantTemplate->intro_texto)) !!}
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     QUADRO RESUMO
════════════════════════════════════════════════════════════ --}}
<div class="quadro">
    <div class="quadro-title">Quadro Resumo</div>
    <table>
        {{-- LOCADOR(ES) --}}
        @php
            $todosLocadores = $contrato->todosLocadores();
        @endphp
        <tr>
            <td class="label">1. Locador(es)</td>
            <td>
                @foreach($todosLocadores as $loc)
                    <strong>{{ $loc->nome }}</strong>
                    @if($loc->cpf), CPF: {{ $loc->cpf }}@endif
                    @if($loc->rg), RG: {{ $loc->rg }}@if($loc->orgao_expedidor)/{{ $loc->orgao_expedidor }}@endif@endif
                    @if($loc->nacionalidade), {{ $loc->nacionalidade }}@endif
                    @if($loc->profissao), {{ $loc->profissao }}@endif
                    @if($loc->estado_civil)
                        @php
                            $ecLabels = ['solteiro'=>'solteiro(a)','casado'=>'casado(a)','uniao_estavel'=>'em união estável',
                                        'divorciado'=>'divorciado(a)','viuvo'=>'viúvo(a)','separado'=>'separado(a)'];
                        @endphp
                        , {{ $ecLabels[$loc->estado_civil] ?? $loc->estado_civil }}
                        @if(in_array($loc->estado_civil, ['casado','uniao_estavel']) && $loc->conjuge_nome)
                            com <strong>{{ $loc->conjuge_nome }}</strong>
                            @if($loc->conjuge_cpf), CPF: {{ $loc->conjuge_cpf }}@endif
                            @if($loc->conjuge_rg), RG: {{ $loc->conjuge_rg }}@if($loc->conjuge_orgao_expedidor)/{{ $loc->conjuge_orgao_expedidor }}@endif@endif
                            @if($loc->conjuge_nacionalidade), {{ $loc->conjuge_nacionalidade }}@endif
                            @if($loc->conjuge_profissao), {{ $loc->conjuge_profissao }}@endif
                        @endif
                    @endif
                    @if($loc->endereco)
                        , residente {{ $loc->endereco }}@if($loc->numero), nº {{ $loc->numero }}@endif
                        @if($loc->complemento), {{ $loc->complemento }}@endif
                        @if($loc->bairro), {{ $loc->bairro }}@endif
                        @if($loc->cidade), {{ $loc->cidade }}@endif
                        @if($loc->estado)/{{ $loc->estado }}@endif
                        @if($loc->cep), CEP: {{ $loc->cep }}@endif
                    @endif
                    @if(!$loop->last)<br>@endif
                @endforeach
            </td>
        </tr>

        {{-- LOCATÁRIO --}}
        <tr>
            <td class="label">2. Locatário</td>
            <td>
                <strong>{{ $locatario->nome ?? '—' }}</strong>
                @if($locatario->nacionalidade), {{ $locatario->nacionalidade }}@endif
                @if($locatario->estado_civil)
                    @php
                        $ecLabels = ['solteiro'=>'solteiro(a)','casado'=>'casado(a)','uniao_estavel'=>'em união estável',
                                    'divorciado'=>'divorciado(a)','viuvo'=>'viúvo(a)','separado'=>'separado(a)'];
                    @endphp
                    , {{ $ecLabels[$locatario->estado_civil] ?? $locatario->estado_civil }}
                @endif
                @if($locatario->profissao), {{ $locatario->profissao }}@endif
                @if($locatario->cpf), CPF: {{ $locatario->cpf }}@endif
                @if($locatario->rg)
                    , RG: {{ $locatario->rg }}
                    @if($locatario->orgao_expedidor)/{{ $locatario->orgao_expedidor }}@endif
                @endif
                @if($locatario->endereco)
                    , residente à {{ $locatario->endereco }}
                    @if($locatario->numero), nº {{ $locatario->numero }}@endif
                    @if($locatario->complemento), {{ $locatario->complemento }}@endif
                    @if($locatario->bairro), {{ $locatario->bairro }}@endif
                    @if($locatario->cidade), {{ $locatario->cidade }}@endif
                    @if($locatario->estado)/{{ $locatario->estado }}@endif
                    @if($locatario->cep), CEP: {{ $locatario->cep }}@endif
                @endif
                @if($locatario->email) — E-mail: {{ $locatario->email }}@endif
                @if($locatario->celular) — Cel: {{ $locatario->celular }}@endif
            </td>
        </tr>

        {{-- IMÓVEL --}}
        <tr>
            <td class="label">3. Imóvel</td>
            <td>
                {{ $imovel->logradouro ?? $imovel->endereco ?? '—' }}
                @if($imovel->numero), Nº {{ $imovel->numero }}@endif
                @if($imovel->complemento) {{ $imovel->complemento }}@endif
                @if($imovel->bairro), {{ $imovel->bairro }}@endif
                @if($imovel->cidade), {{ $imovel->cidade }}@endif
                @if($imovel->estado)/{{ $imovel->estado }}@endif
                @if($imovel->cep), CEP: {{ $imovel->cep }}@endif
            </td>
        </tr>

        {{-- ALUGUEL --}}
        <tr>
            <td class="label">4. Valor do Aluguel Mensal</td>
            <td>
                <strong>R$ {{ number_format($contrato->valor_aluguel, 2, ',', '.') }}</strong>
                @if($contrato->valor_aluguel)
                @php
                    $formatter = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);
                    // Fallback simples para extenso
                    $extenso = '';
                @endphp
                @endif
            </td>
        </tr>

        {{-- PRAZO --}}
        <tr>
            <td class="label">5. Prazo de Locação</td>
            <td>
                12 (doze) meses — de <strong>{{ $contrato->inicio?->format('d/m/Y') ?? '—' }}</strong>
                a <strong>{{ $contrato->fim?->format('d/m/Y') ?? '—' }}</strong>
            </td>
        </tr>

        {{-- VENCIMENTO --}}
        <tr>
            <td class="label">6. Data de Pagamento</td>
            <td>Dia <strong>{{ $contrato->dia_vencimento ?? '—' }}</strong> de cada mês</td>
        </tr>

        {{-- DESTINAÇÃO --}}
        <tr>
            <td class="label">7. Destinação do Imóvel</td>
            <td><strong>{{ strtoupper($contrato->destinacao_imovel ?? 'Residencial') }}</strong></td>
        </tr>

        {{-- GARANTIA --}}
        <tr>
            <td class="label">8. Garantia Locatícia</td>
            <td>
                @php
                    $garantiaLabels = [
                        'none' => 'Sem garantia',
                        'fianca_bancaria' => 'Fiança Bancária',
                        'caution' => 'Caução',
                        'fiador_pessoa' => 'Fiador',
                        'seguro_fianca' => 'Seguro Fiança',
                        'titulo_capitalizacao' => 'Título de Capitalização',
                    ];
                    $garantiaLabel = $garantiaLabels[$contrato->tipo_garantia ?? ''] ?? ucfirst(str_replace('_', ' ', $contrato->tipo_garantia ?? '—'));
                @endphp
                <strong>{{ $garantiaLabel }}</strong>
                @if($contrato->garantidora_nome)
                    — {{ $contrato->garantidora_nome }}
                    @if($contrato->garantidora_cnpj), CNPJ: {{ $contrato->garantidora_cnpj }}@endif
                @endif
                @if($contrato->valor_garantia)
                    — Valor: R$ {{ number_format($contrato->valor_garantia, 2, ',', '.') }}
                @endif
            </td>
        </tr>

        {{-- DATA ASSINATURA --}}
        <tr>
            <td class="label">9. Data de Assinatura</td>
            <td>
                @if($contrato->data_assinatura)
                    {{ $contrato->data_assinatura->formatLocalized('%A, %d de %B de %Y') ?? $contrato->data_assinatura->format('d/m/Y') }}
                @elseif($contrato->inicio)
                    {{ $contrato->inicio->format('d/m/Y') }}
                @else
                    —
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     CORPO DO CONTRATO — CLÁUSULAS
════════════════════════════════════════════════════════════ --}}
<div class="clausulas-container">

<p style="text-align:justify;margin-bottom:12px;font-size:10px;">
As partes identificadas no item 1 e no item 2 do Quadro Resumo, resolvem firmar o Presente
<strong>"Contrato de Locação de Imóvel Urbano"</strong> (o "Contrato"), que se regerá pelas
seguintes cláusulas e condições, que mutuamente outorgam e aceitam:
</p>

{{-- CLÁUSULAS PADRÃO DO TENANT (se houver) --}}
@if(!empty($tenantTemplate?->clausulas_padrao) && count($tenantTemplate->clausulas_padrao) > 0)
    @foreach($tenantTemplate->clausulas_padrao as $i => $clausula)
    <div class="clausula-titulo">Cláusula {{ $i + 1 }}</div>
    <div class="clausula-body">{{ $clausula }}</div>
    @endforeach
@else
{{-- CLÁUSULAS PADRÃO EMBUTIDAS (modelo completo) --}}

<div class="clausula-titulo">Cláusula Primeira — Objeto do Contrato</div>
<div class="clausula-body">
O Objeto do Presente Contrato é a locação do Imóvel descrito no item 3 do Quadro Resumo.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — O LOCATÁRIO declara ter visitado o Imóvel, tendo-o achado conforme, estando ciente de suas
respectivas características, tamanho, benfeitorias e pertences, aceitando-o no estado de conservação em que se encontra.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — O LOCATÁRIO se compromete a receber os profissionais responsáveis por obras e serviços na área
externa do Imóvel, caso existam trabalhos em execução, não se opondo às melhorias acordadas entre as partes.
</div>

<div class="clausula-titulo">Cláusula Segunda — Prazo e Data de Locação</div>
<div class="clausula-body">
A locação é ajustada pelo prazo mencionado no item 5 do Quadro Resumo e terminará após a data final do Contrato
independentemente de qualquer notificação, aviso, interpelação judicial ou extrajudicial.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — Poderá o LOCADOR após o término do Presente Contrato estipular preço, prazo e demais condições
para a renovação da locação do Imóvel.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — Se o LOCATÁRIO continuar na posse do Imóvel por mais de 30 (trinta) dias após o término do
Contrato sem oposição do LOCADOR, o Presente instrumento terá sua vigência prorrogada por prazo indeterminado,
mantendo todas as cláusulas e condições do Presente Instrumento.
</div>

<div class="clausula-titulo">Cláusula Terceira — Preço e Reajuste</div>
<div class="clausula-body">
O valor mensal da locação na data de celebração deste Contrato está descrito conforme item 4 do Quadro Resumo.
O aluguel será reajustado anualmente, aplicando-se o
<strong>{{ strtoupper($contrato->indice_reajuste ?? 'IGP-M') }}</strong>
(Índice Geral de Preços do Mercado) publicado pela Fundação Getúlio Vargas, ou, na falta deste, o maior índice
permitido pelo Governo Federal, a cada {{ $contrato->periodicidade_reajuste ?? 12 }} (doze) meses.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — Sendo certo que quando o índice de reajuste for negativo, o valor do aluguel mensal não sofrerá alterações.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — Vencido o prazo de locação e não fixando as partes um novo período, o valor do aluguel, enquanto
não for restituído o Imóvel, será reajustado pelo maior índice permitido em lei.
</div>

<div class="clausula-titulo">Cláusula Quarta — Pagamento</div>
<div class="clausula-body">
O aluguel será pago impreterivelmente na data informada no item 6 do Quadro Resumo, de cada mês subsequente ao mês
vencido, através de boleto bancário ou conforme convencionado pela ADMINISTRADORA.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — O aluguel só será recebido com os encargos locatícios ajustados neste Contrato. O seu
descumprimento caracterizará infração contratual.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — Em caso de não recebimento do boleto, o LOCATÁRIO deverá comunicar o fato ao LOCADOR com
antecedência mínima de 5 (cinco) dias úteis antes do vencimento.
</div>
<div class="clausula-paragrafo">
<strong>§ 3º</strong> — Eventuais débitos não pagos poderão ser comunicados a entidades de proteção ao crédito
(SERASA, SPC, etc.) independentemente de comunicação prévia.
</div>

<div class="clausula-titulo">Cláusula Quinta — Multa por Inadimplência</div>
<div class="clausula-body">
O aluguel e os encargos não pagos na data do vencimento serão acrescidos de:
<br>• Multa moratória de <strong>10% (dez por cento)</strong> sobre o valor total do débito;
<br>• Juros moratórios de <strong>1% (um por cento)</strong> ao mês;
<br>• Correção monetária;
<br>• Honorários advocatícios de <strong>10%</strong> em composição amigável ou <strong>20%</strong> em procedimento judicial;
<br>• Custas processuais, quando cabíveis.
</div>
<div class="clausula-paragrafo">
<strong>Parágrafo Único</strong> — Se o atraso for superior a 30 (trinta) dias, o débito será monetariamente atualizado
pelo IGP-M/FGV.
</div>

<div class="clausula-titulo">Cláusula Sexta — Encargos e Obrigações</div>
<div class="clausula-body">
Além do aluguel mensal, pagará o LOCATÁRIO, diretamente às repartições arrecadadoras, nas épocas próprias e nos
termos da Lei vigente: IPTU, despesas de manutenção e conservação, conta de consumo de água, energia elétrica,
gás, despesas ordinárias de condomínio e outras taxas incidentes sobre o Imóvel durante o período da Locação.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — O LOCATÁRIO se obriga, no ato do pagamento dos aluguéis, a comprovar a quitação plena de
todos os encargos da locação.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — Correrão por conta do LOCATÁRIO todas as despesas relativas à emissão de boleto bancário,
taxas bancárias, emolumentos de cartório e demais despesas inerentes à formalização deste Contrato.
</div>
<div class="clausula-paragrafo">
<strong>§ 3º</strong> — O LOCATÁRIO é responsável pela manutenção, conservação e o reparo de pequenos danos ou
defeitos no Imóvel, conforme o artigo 23º, inciso V da Lei 8.245/91.
</div>

<div class="clausula-titulo">Cláusula Sétima — Seguro Contra Incêndio</div>
<div class="clausula-body">
O LOCATÁRIO pagará o seguro contra incêndio em Companhia de livre escolha do LOCADOR. A apólice terá como
beneficiário o LOCADOR, com vigência de 12 (doze) meses, sendo obrigatória a renovação anual.
</div>
<div class="clausula-paragrafo">
<strong>Parágrafo Único</strong> — No caso de incêndio ou sinistro que inutilize o uso normal do Imóvel, fica o
Presente Contrato rescindido de pleno direito. Caso o LOCATÁRIO não faça o seguro e haja incêndio, responderá
por perdas e danos perante o LOCADOR.
</div>

<div class="clausula-titulo">Cláusula Oitava — Cessão, Empréstimo e Sublocação</div>
<div class="clausula-body">
O LOCATÁRIO não poderá ceder, trespassar, sublocar, emprestar ou por qualquer outro modo transferir o uso do
Imóvel a terceiros, no todo ou em parte, sem a prévia autorização por escrito do LOCADOR.
</div>

<div class="clausula-titulo">Cláusula Nona — Benfeitorias e Alterações</div>
<div class="clausula-body">
É vedado ao LOCATÁRIO, sob pena de rescisão da locação, fazer quaisquer benfeitorias ou alterações no Imóvel
locado sem prévio consentimento por escrito do LOCADOR.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — As benfeitorias ou alterações realizadas, mesmo que permitidas, não darão ao LOCATÁRIO
direito de retenção, indenização, reembolso ou abatimento de qualquer obrigação contratual.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — Perfurações em paredes devem respeitar rigorosamente a integridade estrutural, a rede elétrica
e hidráulica. Não são permitidas perfurações em pisos ou paredes azulejadas sem consentimento do LOCADOR.
</div>

<div class="clausula-titulo">Cláusula Décima — Devolução do Imóvel</div>
<div class="clausula-body">
O LOCATÁRIO deverá notificar o LOCADOR de sua intenção de devolver o Imóvel por escrito, com antecedência mínima
de 30 (trinta) dias.
</div>
<div class="clausula-paragrafo">
<strong>§ 1º</strong> — O LOCATÁRIO se compromete a restituir o Imóvel no estado em que o recebeu, conforme Laudo
de Vistoria Inicial, totalmente limpo, pintado por profissional com a mesma cor e qualidade, com todas as instalações
em perfeito estado de funcionamento.
</div>
<div class="clausula-paragrafo">
<strong>§ 2º</strong> — Se o LOCATÁRIO devolver o Imóvel antes do vencimento do prazo ajustado na Cláusula Segunda,
pagará ao LOCADOR multa compensatória de <strong>15% (quinze por cento)</strong> sobre o valor total da locação
proporcional ao período restante do Contrato.
</div>
<div class="clausula-paragrafo">
<strong>§ 3º</strong> — O LOCADOR poderá recusar o recebimento das chaves se o LOCATÁRIO não comprovar a quitação
de todos os encargos da locação (IPTU, condomínio, água, energia, gás, etc.).
</div>

<div class="clausula-titulo">Cláusula Décima Primeira — Destinação do Imóvel</div>
<div class="clausula-body">
O Imóvel locado é destinado exclusivamente para uso <strong>{{ strtoupper($contrato->destinacao_imovel ?? 'RESIDENCIAL') }}</strong>,
não sendo permitida a mudança do seu uso e destinação sob qualquer pretexto. A violação desta cláusula constitui
infração contratual passível de rescisão do Presente Instrumento.
</div>

<div class="clausula-titulo">Cláusula Décima Segunda — Penalidade</div>
<div class="clausula-body">
A falta de cumprimento de qualquer cláusula contratual acarretará a rescisão do Contrato, sujeitando o infrator a
uma multa no valor de <strong>3 (três) meses de aluguel atualizado</strong>, sem prejuízo das demais penalidades
constantes neste Instrumento.
</div>

<div class="clausula-titulo">Cláusula Décima Terceira — Citações, Intimações e Comunicações</div>
<div class="clausula-body">
O LOCATÁRIO aceita expressamente, nas pendências judiciais oriundas desta locação, ser citado, notificado ou
intimado mediante carta registrada, e-mail, correspondência eletrônica ou qualquer outro meio judicial ou extrajudicial,
nos endereços indicados no item 2 do Quadro Resumo.
</div>

@if($contrato->tipo_garantia === 'seguro_fianca' || !empty($contrato->garantidora_nome))
<div class="clausula-titulo">Cláusula Décima Quarta — Garantia Locatícia — Seguro Fiança</div>
<div class="clausula-body">
O presente Contrato é garantido através de Seguro Fiança prestado por
<strong>{{ $contrato->garantidora_nome ?? 'empresa garantidora' }}</strong>
@if($contrato->garantidora_cnpj), inscrita no CNPJ sob o nº {{ $contrato->garantidora_cnpj }}@endif
@if($contrato->garantidora_endereco), com sede à {{ $contrato->garantidora_endereco }}@endif,
a qual se compromete a efetuar o pagamento de eventuais débitos relativos aos aluguéis e demais encargos da presente
locação que venham a ser inadimplidos pelo LOCATÁRIO, conforme condições e limitações constantes nos Termos e
Condições Gerais dos Serviços, que integram o presente Contrato como Anexo, valendo a assinatura deste como aceite
por parte do LOCATÁRIO de tais condições.
</div>
@endif

<div class="clausula-titulo">Cláusula {{ !empty($contrato->garantidora_nome) ? 'Décima Quinta' : 'Décima Quarta' }} — Foro</div>
<div class="clausula-body">
Elegem as partes o foro de <strong>{{ $imovel->cidade ?? 'Belo Horizonte' }}</strong> para dirimir qualquer pendência
relativa a este Contrato. As partes aceitam integralmente que as assinaturas do presente instrumento serão realizadas na
forma eletrônica, nos termos do artigo 10 e seus parágrafos da Medida Provisória 2.200-2/2001, sendo o presente
instrumento considerado, por todos que o assinam, como prova documental e título executivo para todos os fins de direito.
</div>

@endif {{-- fim cláusulas padrão embutidas --}}

{{-- CLÁUSULAS ESPECÍFICAS DO CONTRATO --}}
@if($contrato->clausulas && count($contrato->clausulas) > 0)
<div class="clausula-titulo mt-8">Cláusulas Específicas deste Contrato</div>
@foreach($contrato->clausulas as $i => $clausula)
<div class="clausula-body"><strong>{{ $i + 1 }}.</strong> {{ $clausula }}</div>
@endforeach
@endif

{{-- OBSERVAÇÕES --}}
@if($contrato->observacoes)
<div class="clausula-titulo mt-8">Observações</div>
<div class="clausula-body">{{ $contrato->observacoes }}</div>
@endif

</div> {{-- fim clausulas-container --}}

{{-- ══════════════════════════════════════════════════════════
     LOCAL E DATA
════════════════════════════════════════════════════════════ --}}
<div style="margin-top:24px; text-align:right; font-size:10px;">
    {{ $imovel->cidade ?? 'Belo Horizonte' }}/{{ $imovel->estado ?? 'MG' }},
    @if($contrato->data_assinatura)
        {{ $contrato->data_assinatura->format('d/m/Y') }}
    @elseif($contrato->inicio)
        {{ $contrato->inicio->format('d/m/Y') }}
    @else
        ____/____/________
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════
     FIADORES
════════════════════════════════════════════════════════════ --}}
@if($fiadores && $fiadores->isNotEmpty())
<div class="section mt-8">
    <div class="section-title">Fiadores / Garantidores</div>
    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>CPF/CNPJ</th>
                <th>RG</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
        @foreach($fiadores as $f)
        <tr>
            <td>{{ $f->pessoa->nome ?? '—' }}</td>
            <td>{{ $f->pessoa->cpf ?? $f->pessoa->cnpj ?? '—' }}</td>
            <td>{{ $f->pessoa->rg ?? '—' }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $f->tipo_vinculo ?? 'Fiador')) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     ASSINATURAS
════════════════════════════════════════════════════════════ --}}
<div class="signatures">

    {{-- LOCADOR(ES) --}}
    @php $todosLocadoresAssinatura = $contrato->todosLocadores(); @endphp
    <div class="sig-row">
        @foreach($todosLocadoresAssinatura as $loc)
        <div class="sig-block">
            <div class="sig-line">{{ $loc->nome }}</div>
            <div class="sig-sub">LOCADOR(A)</div>
            @if(in_array($loc->estado_civil, ['casado','uniao_estavel']) && $loc->conjuge_nome)
                <div style="margin-top:36px;">
                    <div class="sig-line">{{ $loc->conjuge_nome }}</div>
                    <div class="sig-sub">CÔNJUGE / COMPANHEIRO(A)</div>
                </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- LOCATÁRIO --}}
    <div class="sig-row">
        <div class="sig-block">
            <div class="sig-line">{{ $locatario->nome ?? 'Locatário' }}</div>
            <div class="sig-sub">LOCATÁRIO</div>
        </div>

        {{-- FIADORES --}}
        @foreach($fiadores ?? [] as $f)
        <div class="sig-block">
            <div class="sig-line">{{ $f->pessoa->nome ?? 'Fiador' }}</div>
            <div class="sig-sub">{{ strtoupper($f->tipo_vinculo ?? 'FIADOR') }}</div>
        </div>
        @endforeach
    </div>

    {{-- TESTEMUNHAS --}}
    <div class="sig-row">
        <div class="sig-block">
            <div class="sig-line">Testemunha 1</div>
            <div class="sig-sub">CPF: ___.___.___-__</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Testemunha 2</div>
            <div class="sig-sub">CPF: ___.___.___-__</div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════
     RODAPÉ
════════════════════════════════════════════════════════════ --}}
<div class="footer">
    @if(!empty($tenantTemplate?->rodape_texto))
        {{ $tenantTemplate->rodape_texto }}
    @else
        Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}
        &nbsp;|&nbsp; Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }}
    @endif
</div>

</body>
</html>
