<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 10px;
            line-height: 1.32;
            margin: 20px;
        }
        .header-table, .meta-table, .info-table, .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td, .meta-table td, .info-table td, .info-table th, .totals-table td {
            border: 1px solid #d1d5db;
            vertical-align: top;
            padding: 6px 8px;
        }
        .header-left {
            width: 60%;
            background: #f8fafc;
        }
        .header-right {
            width: 40%;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 2px;
        }
        .subtitle {
            font-size: 12px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 8px;
        }
        .authority {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 2px;
        }
        .muted {
            color: #6b7280;
        }
        .box-title {
            display: block;
            font-size: 9px;
            font-weight: bold;
            color: #374151;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .box-value {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            word-break: break-word;
        }
        .section {
            margin-top: 10px;
        }
        .section-heading {
            background: #111827;
            color: #ffffff;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #111827;
        }
        .label {
            display: block;
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .value {
            display: block;
            font-size: 10px;
            color: #111827;
            font-weight: bold;
        }
        .small {
            font-size: 8px;
        }
        .half {
            width: 50%;
        }
        .third {
            width: 33.333%;
        }
        .two-thirds {
            width: 66.666%;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
        .footer-note {
            margin-top: 8px;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="title">DANFSe v1.0</div>
                <div class="subtitle">Documento Auxiliar da NFS-e</div>
                <div class="authority">{{ $header['autoridade_titulo'] }}</div>
                <div class="authority">{{ $header['autoridade_subtitulo'] }}</div>
                <div class="muted small" style="margin-top:8px;">{{ $header['mensagem_autenticidade'] }}</div>
            </td>
            <td class="header-right">
                <span class="box-title">{{ $header['chave_titulo'] }}</span>
                <div class="box-value">{{ $header['chave_valor'] }}</div>
                <table class="meta-table" style="margin-top:8px;">
                    <tr>
                        <td>
                            <span class="label">Número da NFS-e</span>
                            <span class="value">{{ $header['numero_nfse'] }}</span>
                        </td>
                        <td>
                            <span class="label">Competência</span>
                            <span class="value">{{ $header['competencia'] }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label">Emissão da NFS-e</span>
                            <span class="value">{{ $header['emitida_em'] }}</span>
                        </td>
                        <td>
                            <span class="label">Status</span>
                            <span class="value">{{ $header['status'] }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label">Número da DPS/RPS</span>
                            <span class="value">{{ $header['numero_dps'] }}</span>
                        </td>
                        <td>
                            <span class="label">Série</span>
                            <span class="value">{{ $header['serie_dps'] }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label">Emissão da DPS</span>
                            <span class="value">{{ $header['emitida_dps_em'] }}</span>
                        </td>
                        <td>
                            <span class="label">Código de verificação</span>
                            <span class="value">{{ $header['codigo_verificacao'] }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-heading">Emitente da NFS-e</div>
        <table class="info-table">
            <tr>
                <td class="third"><span class="label">CNPJ / CPF / NIF</span><span class="value">{{ $emitente['documento'] }}</span></td>
                <td class="third"><span class="label">Inscrição Municipal</span><span class="value">{{ $emitente['inscricao_municipal'] }}</span></td>
                <td class="third"><span class="label">Telefone</span><span class="value">{{ $emitente['telefone'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Nome / Nome Empresarial</span><span class="value">{{ $emitente['nome'] }}</span></td>
                <td><span class="label">E-mail</span><span class="value">{{ $emitente['email'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value">{{ $emitente['endereco'] }}</span></td>
                <td><span class="label">CEP</span><span class="value">{{ $emitente['cep'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Município</span><span class="value">{{ $emitente['municipio'] }}</span></td>
                <td><span class="label">Simples Nacional</span><span class="value">{{ $emitente['simples_nacional'] }}</span></td>
                <td><span class="label">Regime de Apuração</span><span class="value">{{ $emitente['regime_apuracao'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Tomador do Serviço</div>
        <table class="info-table">
            <tr>
                <td class="third"><span class="label">CNPJ / CPF / NIF</span><span class="value">{{ $tomador['documento'] }}</span></td>
                <td class="third"><span class="label">Inscrição Municipal</span><span class="value">{{ $tomador['inscricao_municipal'] }}</span></td>
                <td class="third"><span class="label">Telefone</span><span class="value">{{ $tomador['telefone'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Nome / Nome Empresarial</span><span class="value">{{ $tomador['nome'] }}</span></td>
                <td><span class="label">E-mail</span><span class="value">{{ $tomador['email'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value">{{ $tomador['endereco'] }}</span></td>
                <td><span class="label">CEP</span><span class="value">{{ $tomador['cep'] }}</span></td>
            </tr>
            <tr>
                <td colspan="3"><span class="label">Município</span><span class="value">{{ $tomador['municipio'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Intermediário do Serviço</div>
        <table class="info-table">
            <tr>
                <td><span class="value">Não identificado na NFS-e</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Serviço Prestado</div>
        <table class="info-table">
            <tr>
                <td class="half"><span class="label">Código de Tributação Nacional</span><span class="value">{{ $servico['codigo_tributacao_nacional'] }}</span></td>
                <td class="half"><span class="label">Código de Tributação Municipal</span><span class="value">{{ $servico['codigo_tributacao_municipal'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Local da Prestação</span><span class="value">{{ $servico['local_prestacao'] }}</span></td>
                <td><span class="label">País da Prestação</span><span class="value">{{ $servico['pais_prestacao'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">CNAE</span><span class="value">{{ $servico['cnae'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Descrição do Serviço</span><span class="value">{{ $servico['descricao'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Tributação Municipal</div>
        <table class="info-table">
            <tr>
                <td class="third"><span class="label">Tributação do ISSQN</span><span class="value">{{ $tributacao_municipal['tributacao_issqn'] }}</span></td>
                <td class="third"><span class="label">País Resultado</span><span class="value">{{ $tributacao_municipal['pais_resultado'] }}</span></td>
                <td class="third"><span class="label">Município de Incidência</span><span class="value">{{ $tributacao_municipal['municipio_incidencia'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Regime Especial</span><span class="value">{{ $tributacao_municipal['regime_especial'] }}</span></td>
                <td><span class="label">Tipo de Imunidade</span><span class="value">{{ $tributacao_municipal['tipo_imunidade'] }}</span></td>
                <td><span class="label">Suspensão da Exigibilidade</span><span class="value">{{ $tributacao_municipal['suspensao_exigibilidade'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Número Processo Suspensão</span><span class="value">{{ $tributacao_municipal['numero_processo'] }}</span></td>
                <td colspan="2"><span class="label">Benefício Municipal</span><span class="value">{{ $tributacao_municipal['beneficio_municipal'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Valor do Serviço</span><span class="value">{{ $tributacao_municipal['valor_servico'] }}</span></td>
                <td><span class="label">Desconto Incondicionado</span><span class="value">{{ $tributacao_municipal['desconto_incondicionado'] }}</span></td>
                <td><span class="label">Total Deduções / Reduções</span><span class="value">{{ $tributacao_municipal['total_deducoes'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Cálculo do BM</span><span class="value">{{ $tributacao_municipal['calculo_bm'] }}</span></td>
                <td><span class="label">BC ISSQN</span><span class="value">{{ $tributacao_municipal['bc_issqn'] }}</span></td>
                <td><span class="label">Alíquota Aplicada</span><span class="value">{{ $tributacao_municipal['aliquota_aplicada'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Retenção do ISSQN</span><span class="value">{{ $tributacao_municipal['retencao_issqn'] }}</span></td>
                <td colspan="2"><span class="label">ISSQN Apurado</span><span class="value">{{ $tributacao_municipal['issqn_apurado'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Tributação Federal</div>
        <table class="info-table">
            <tr>
                <td class="third"><span class="label">IRRF</span><span class="value">{{ $tributacao_federal['irrf'] }}</span></td>
                <td class="third"><span class="label">Contribuição Previdenciária</span><span class="value">{{ $tributacao_federal['previdencia'] }}</span></td>
                <td class="third"><span class="label">Contribuições Sociais</span><span class="value">{{ $tributacao_federal['contribuicoes_sociais'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Descrição Contribuições</span><span class="value">{{ $tributacao_federal['descricao_contribuicoes'] }}</span></td>
                <td><span class="label">PIS</span><span class="value">{{ $tributacao_federal['pis'] }}</span></td>
                <td><span class="label">COFINS</span><span class="value">{{ $tributacao_federal['cofins'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Valor Total da NFS-e</div>
        <table class="totals-table">
            <tr>
                <td class="third"><span class="label">Valor do Serviço</span><span class="value">{{ $totais['valor_servico'] }}</span></td>
                <td class="third"><span class="label">Desconto Condicionado</span><span class="value">{{ $totais['desconto_condicionado'] }}</span></td>
                <td class="third"><span class="label">Desconto Incondicionado</span><span class="value">{{ $totais['desconto_incondicionado'] }}</span></td>
            </tr>
            <tr>
                <td><span class="label">ISSQN Retido</span><span class="value">{{ $totais['issqn_retido'] }}</span></td>
                <td><span class="label">Retenções Federais</span><span class="value">{{ $totais['retencoes_federais'] }}</span></td>
                <td><span class="label">PIS/COFINS Próprio</span><span class="value">{{ $totais['pis_cofins'] }}</span></td>
            </tr>
            <tr>
                <td colspan="3" class="center"><span class="label">Valor Líquido da NFS-e</span><span class="value" style="font-size:14px;">{{ $totais['valor_liquido'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Totais Aproximados dos Tributos</div>
        <table class="info-table">
            <tr>
                <td class="third"><span class="label">Federais</span><span class="value">{{ $tributos_aproximados['federais'] }}</span></td>
                <td class="third"><span class="label">Estaduais</span><span class="value">{{ $tributos_aproximados['estaduais'] }}</span></td>
                <td class="third"><span class="label">Municipais</span><span class="value">{{ $tributos_aproximados['municipais'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-heading">Informações Complementares</div>
        <table class="info-table">
            <tr>
                <td class="two-thirds"><span class="label">Observações</span><span class="value">{{ $informacoes_complementares['texto'] }}</span></td>
                <td class="third"><span class="label">NBS</span><span class="value">{{ $informacoes_complementares['nbs'] }}</span></td>
            </tr>
        </table>
    </div>

    <div class="footer-note">
        Espelho interno DANFSe gerado pelo SOCIMOB com base nos dados persistidos da NFSe e do financeiro.
    </div>
</body>
</html>