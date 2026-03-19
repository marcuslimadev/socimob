<?php

namespace App\Services;

use App\Models\CommissionInvoice;
use App\Models\DocumentoFiscal;
use App\Models\Property;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\CarbonInterface;

class NfseDanfseService
{
    public function gerarParaDocumentoFiscal(DocumentoFiscal $documento): DomPdf
    {
        $documento->loadMissing('tomador');

        return $this->criarPdf($this->montarDadosDocumentoFiscal($documento));
    }

    public function gerarParaCommissionInvoice(CommissionInvoice $invoice): DomPdf
    {
        $invoice->loadMissing(['corretor', 'property']);

        return $this->criarPdf($this->montarDadosCommissionInvoice($invoice));
    }

    private function criarPdf(array $dados): DomPdf
    {
        $pdf = Pdf::loadView('pdfs.nfse.danfse', $dados);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    private function montarDadosDocumentoFiscal(DocumentoFiscal $documento): array
    {
        $tenant = Tenant::find($documento->tenant_id);
        $property = $documento->property_id ? Property::find($documento->property_id) : null;
        $payload = $documento->payload ?? [];
        $retorno = $documento->retorno ?? [];
        $nfePayload = data_get($payload, 'nfe_payload', []);
        $tomador = $documento->tomador;
        $tomadorPayload = $payload['tomador'] ?? [];

        $servicoValor = (float) $documento->valor_servico;
        $valorIss = (float) $documento->valor_impostos;
        $aliquotaIss = (float) data_get($payload, 'aliquota_iss', 0);
        $numero = $this->textoOuTraco($documento->numero);
        $emitidaEm = $this->resolverDataHora(
            $documento->emitida_em,
            data_get($retorno, 'issuedOn'),
            data_get($retorno, 'createdAt'),
            $documento->created_at
        );

        $tomadorEndereco = $this->montarEnderecoTomador(
            [
                'street' => $tomador?->endereco,
                'number' => $tomador?->numero,
                'district' => $tomador?->bairro,
                'city' => $tomador?->cidade,
                'state' => $tomador?->estado,
                'postalCode' => $tomador?->cep,
                'additionalInformation' => $tomador?->complemento,
            ],
            data_get($tomadorPayload, 'endereco', []),
            data_get($nfePayload, 'borrower.address', []),
        );

        $emitenteEndereco = $this->montarEnderecoEmitente($tenant, $retorno, $nfePayload);
        $localPrestacao = $this->resolverLocalPrestacao($property, $emitenteEndereco, $retorno, $nfePayload);
        $informacoesComplementares = $this->montarInformacoesComplementares([
            data_get($payload, 'financeiro.descricao'),
            data_get($nfePayload, 'additionalInformation'),
            $property ? 'Imóvel: ' . $this->identificarImovel($property) : null,
            data_get($payload, 'financeiro.forma_pagamento') ? 'Pagamento: ' . data_get($payload, 'financeiro.forma_pagamento') : null,
            data_get($payload, 'financeiro.vencimento') ? 'Vencimento: ' . data_get($payload, 'financeiro.vencimento') : null,
            data_get($payload, 'integracao_id') ? 'Integração NFe.io: ' . data_get($payload, 'integracao_id') : null,
        ]);

        return [
            'header' => [
                'autoridade_titulo' => $this->resolverAutoridadeMunicipal($emitenteEndereco['city']),
                'autoridade_subtitulo' => 'Secretaria Municipal de Fazenda',
                'chave_titulo' => $this->resolverTituloChaveAcesso($retorno, $payload),
                'chave_valor' => $this->resolverChaveAcesso($retorno, $payload),
                'mensagem_autenticidade' => 'A autenticidade desta NFS-e pode ser verificada pelo código abaixo ou pela consulta dos dados no portal fiscal.',
                'numero_nfse' => $numero,
                'competencia' => $this->resolverDataCurta(
                    data_get($retorno, 'competenceDate'),
                    data_get($retorno, 'competence'),
                    $documento->emitida_em,
                    $documento->created_at,
                ),
                'emitida_em' => $emitidaEm,
                'numero_dps' => $this->textoOuTraco(
                    data_get($retorno, 'rpsNumber'),
                    data_get($payload, 'rps'),
                    $documento->id
                ),
                'serie_dps' => $this->textoOuTraco(
                    data_get($retorno, 'rpsSeries'),
                    data_get($retorno, 'series'),
                    data_get($payload, 'serie')
                ),
                'emitida_dps_em' => $emitidaEm,
                'codigo_verificacao' => $this->textoOuTraco(
                    $documento->codigo_verificacao,
                    data_get($retorno, 'checkCode')
                ),
                'status' => strtoupper((string) ($documento->status ?: 'N/A')),
            ],
            'emitente' => [
                'documento' => $this->formatarDocumento($tenant?->cnpj),
                'inscricao_municipal' => $this->textoOuTraco(
                    data_get($retorno, 'seller.municipalTaxNumber'),
                    data_get($retorno, 'provider.municipalTaxNumber'),
                    data_get($tenant?->metadata, 'nfeio_inscricao_municipal')
                ),
                'telefone' => $this->formatarTelefone($tenant?->contact_phone),
                'nome' => $tenant?->razao_social ?: $tenant?->getCompanyName() ?: 'Emitente não identificado',
                'email' => $this->textoOuTraco($tenant?->contact_email),
                'endereco' => $emitenteEndereco['street_line'],
                'municipio' => $emitenteEndereco['city_line'],
                'cep' => $emitenteEndereco['postal_code'],
                'simples_nacional' => $this->resolverSimplesNacional($retorno, $tenant),
                'regime_apuracao' => $this->resolverRegimeApuracao($retorno),
            ],
            'tomador' => [
                'documento' => $this->formatarDocumento(
                    $tomador?->cnpj ?: $tomador?->cpf ?: ($tomadorPayload['documento'] ?? data_get($nfePayload, 'borrower.federalTaxNumber'))
                ),
                'inscricao_municipal' => $this->textoOuTraco(
                    $tomador?->inscricao_municipal,
                    data_get($retorno, 'borrower.municipalTaxNumber')
                ),
                'telefone' => $this->formatarTelefone(
                    $tomador?->telefone ?: $tomador?->celular ?: ($tomadorPayload['telefone'] ?? null)
                ),
                'nome' => $tomador?->razao_social ?: $tomador?->nome ?: ($tomadorPayload['nome'] ?? data_get($nfePayload, 'borrower.name')),
                'email' => $this->textoOuTraco(
                    $tomador?->email ?: ($tomadorPayload['email'] ?? data_get($nfePayload, 'borrower.email'))
                ),
                'endereco' => $tomadorEndereco['street_line'],
                'municipio' => $tomadorEndereco['city_line'],
                'cep' => $tomadorEndereco['postal_code'],
            ],
            'servico' => [
                'codigo_tributacao_nacional' => $this->textoOuTraco(
                    data_get($retorno, 'nationalTaxCode'),
                    data_get($retorno, 'serviceCode.national'),
                    data_get($tenant?->metadata, 'nfse_national_service_code'),
                    $documento->contexto_emissao === 'proprietario' ? '10.05.01 - Agenciamento, corretagem ou intermediação de bens imóveis' : null,
                ),
                'codigo_tributacao_municipal' => $this->textoOuTraco(
                    data_get($retorno, 'cityServiceCode'),
                    data_get($nfePayload, 'cityServiceCode'),
                    $documento->city_service_code,
                    $documento->contexto_emissao === 'proprietario' ? '004 - Agenciamento de bens imóveis' : null,
                ),
                'local_prestacao' => $localPrestacao,
                'pais_prestacao' => $this->textoOuTraco(data_get($retorno, 'serviceCountry'), 'Brasil'),
                'cnae' => $this->textoOuTraco(
                    data_get($retorno, 'cnae'),
                    data_get($retorno, 'provider.cnae'),
                    data_get($tenant?->metadata, 'nfse_cnae')
                ),
                'descricao' => $this->textoOuTraco(
                    data_get($retorno, 'description'),
                    data_get($nfePayload, 'description'),
                    data_get($payload, 'descricao_servico')
                ),
            ],
            'tributacao_municipal' => [
                'tributacao_issqn' => $this->textoOuTraco(data_get($retorno, 'issTaxationType'), 'Operação Tributável'),
                'pais_resultado' => $this->textoOuTraco(data_get($retorno, 'serviceResultCountry')),
                'municipio_incidencia' => $localPrestacao,
                'regime_especial' => $this->textoOuTraco(data_get($retorno, 'specialTaxRegime'), data_get($retorno, 'provider.specialTaxRegime'), 'Nenhum'),
                'tipo_imunidade' => $this->textoOuTraco(data_get($retorno, 'immunityType')),
                'suspensao_exigibilidade' => $this->textoOuTraco(data_get($retorno, 'issSuspended') ? 'Sim' : 'Não'),
                'numero_processo' => $this->textoOuTraco(data_get($retorno, 'issSuspensionProcessNumber')),
                'beneficio_municipal' => $this->textoOuTraco(data_get($retorno, 'municipalBenefit')),
                'valor_servico' => $this->formatarMoeda($servicoValor),
                'desconto_incondicionado' => $this->formatarMoedaOuTraco(data_get($retorno, 'unconditionalDiscountAmount')),
                'total_deducoes' => $this->formatarMoedaOuTraco(data_get($retorno, 'deductionsAmount')),
                'calculo_bm' => $this->formatarMoedaOuTraco(data_get($retorno, 'bmAmount')),
                'bc_issqn' => $this->formatarMoedaOuTraco(
                    data_get($retorno, 'taxableAmount')
                    ?? data_get($retorno, 'baseTaxAmount')
                    ?? $servicoValor
                ),
                'aliquota_aplicada' => $this->formatarPercentual($aliquotaIss),
                'retencao_issqn' => $this->textoOuTraco(data_get($retorno, 'issWithheld') ? 'Retido' : 'Não Retido'),
                'issqn_apurado' => $this->formatarMoedaOuTraco($valorIss),
            ],
            'tributacao_federal' => [
                'irrf' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.irrfAmount')),
                'previdencia' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.socialSecurityAmount')),
                'contribuicoes_sociais' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.socialContributionsAmount')),
                'descricao_contribuicoes' => $this->textoOuTraco(data_get($retorno, 'federalTaxes.socialContributionsDescription')),
                'pis' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.pisAmount')),
                'cofins' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.cofinsAmount')),
            ],
            'totais' => [
                'valor_servico' => $this->formatarMoeda($servicoValor),
                'desconto_condicionado' => $this->formatarMoedaOuTraco(data_get($retorno, 'conditionalDiscountAmount')),
                'desconto_incondicionado' => $this->formatarMoedaOuTraco(data_get($retorno, 'unconditionalDiscountAmount')),
                'issqn_retido' => $this->formatarMoedaOuTraco(data_get($retorno, 'withheldIssAmount')),
                'retencoes_federais' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.totalAmount')),
                'pis_cofins' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.pisCofinsOwnAssessmentAmount')),
                'valor_liquido' => $this->formatarMoeda(
                    max(0, $servicoValor - $this->floatValue(data_get($retorno, 'withheldIssAmount')) - $this->floatValue(data_get($retorno, 'federalTaxes.totalAmount')))
                ),
            ],
            'tributos_aproximados' => [
                'federais' => $this->formatarMoedaOuTraco(data_get($retorno, 'approximateTaxes.federal')),
                'estaduais' => $this->formatarMoedaOuTraco(data_get($retorno, 'approximateTaxes.state')),
                'municipais' => $this->formatarMoedaOuTraco($valorIss),
            ],
            'informacoes_complementares' => [
                'texto' => $informacoesComplementares,
                'nbs' => $this->textoOuTraco(
                    data_get($retorno, 'nbs'),
                    data_get($tenant?->metadata, 'nfse_nbs'),
                    $documento->contexto_emissao === 'proprietario' ? '110012100' : null,
                ),
            ],
        ];
    }

    private function montarDadosCommissionInvoice(CommissionInvoice $invoice): array
    {
        $tenant = Tenant::find($invoice->tenant_id);
        $property = $invoice->property;
        $retorno = $invoice->retorno_integracao ?? [];
        $tomador = $invoice->tomador_dados ?? [];
        $servicoValor = (float) $invoice->valor_total;
        $valorIss = (float) $invoice->valor_iss;
        $emitenteEndereco = $this->montarEnderecoEmitente($tenant, $retorno, []);
        $tomadorEndereco = $this->montarEnderecoTomador($tomador['endereco'] ?? [], $tomador['endereco'] ?? [], data_get($retorno, 'borrower.address', []));
        $localPrestacao = $this->resolverLocalPrestacao($property, $emitenteEndereco, $retorno, []);

        return [
            'header' => [
                'autoridade_titulo' => $this->resolverAutoridadeMunicipal($emitenteEndereco['city']),
                'autoridade_subtitulo' => 'Secretaria Municipal de Fazenda',
                'chave_titulo' => $this->resolverTituloChaveAcesso($retorno, ['integracao_id' => $invoice->integracao_id]),
                'chave_valor' => $this->resolverChaveAcesso($retorno, ['integracao_id' => $invoice->integracao_id]),
                'mensagem_autenticidade' => 'A autenticidade desta NFS-e pode ser verificada pelo código abaixo ou pela consulta dos dados no portal fiscal.',
                'numero_nfse' => $this->textoOuTraco($invoice->nfse_numero),
                'competencia' => $this->resolverDataCurta($invoice->competencia, data_get($retorno, 'competenceDate')),
                'emitida_em' => $this->resolverDataHora(data_get($retorno, 'issuedOn'), data_get($retorno, 'createdAt'), $invoice->created_at),
                'numero_dps' => $this->textoOuTraco($invoice->nfse_rps, $invoice->id),
                'serie_dps' => $this->textoOuTraco(data_get($retorno, 'rpsSeries'), data_get($retorno, 'series')),
                'emitida_dps_em' => $this->resolverDataHora(data_get($retorno, 'issuedOn'), data_get($retorno, 'createdAt'), $invoice->created_at),
                'codigo_verificacao' => $this->textoOuTraco($invoice->nfse_codigo_verificacao, data_get($retorno, 'checkCode')),
                'status' => strtoupper((string) ($invoice->status ?: 'N/A')),
            ],
            'emitente' => [
                'documento' => $this->formatarDocumento($tenant?->cnpj),
                'inscricao_municipal' => $this->textoOuTraco(data_get($retorno, 'seller.municipalTaxNumber'), data_get($tenant?->metadata, 'nfeio_inscricao_municipal')),
                'telefone' => $this->formatarTelefone($tenant?->contact_phone),
                'nome' => $tenant?->razao_social ?: $tenant?->getCompanyName() ?: 'Emitente não identificado',
                'email' => $this->textoOuTraco($tenant?->contact_email),
                'endereco' => $emitenteEndereco['street_line'],
                'municipio' => $emitenteEndereco['city_line'],
                'cep' => $emitenteEndereco['postal_code'],
                'simples_nacional' => $this->resolverSimplesNacional($retorno, $tenant),
                'regime_apuracao' => $this->resolverRegimeApuracao($retorno),
            ],
            'tomador' => [
                'documento' => $this->formatarDocumento($tomador['documento'] ?? data_get($retorno, 'borrower.federalTaxNumber')),
                'inscricao_municipal' => $this->textoOuTraco(data_get($retorno, 'borrower.municipalTaxNumber')),
                'telefone' => $this->formatarTelefone($tomador['telefone'] ?? null),
                'nome' => $this->textoOuTraco($tomador['nome'] ?? data_get($retorno, 'borrower.name')),
                'email' => $this->textoOuTraco($tomador['email'] ?? data_get($retorno, 'borrower.email')),
                'endereco' => $tomadorEndereco['street_line'],
                'municipio' => $tomadorEndereco['city_line'],
                'cep' => $tomadorEndereco['postal_code'],
            ],
            'servico' => [
                'codigo_tributacao_nacional' => $this->textoOuTraco(data_get($retorno, 'nationalTaxCode'), '10.05.01 - Agenciamento, corretagem ou intermediação de bens imóveis'),
                'codigo_tributacao_municipal' => $this->textoOuTraco(data_get($retorno, 'cityServiceCode'), '004 - Agenciamento de bens imóveis'),
                'local_prestacao' => $localPrestacao,
                'pais_prestacao' => 'Brasil',
                'cnae' => $this->textoOuTraco(data_get($retorno, 'cnae'), data_get($retorno, 'provider.cnae'), data_get($tenant?->metadata, 'nfse_cnae')),
                'descricao' => $this->textoOuTraco($invoice->descricao_servico, data_get($retorno, 'description')),
            ],
            'tributacao_municipal' => [
                'tributacao_issqn' => $this->textoOuTraco(data_get($retorno, 'issTaxationType'), 'Operação Tributável'),
                'pais_resultado' => $this->textoOuTraco(data_get($retorno, 'serviceResultCountry')),
                'municipio_incidencia' => $localPrestacao,
                'regime_especial' => $this->textoOuTraco(data_get($retorno, 'specialTaxRegime'), data_get($retorno, 'provider.specialTaxRegime'), 'Nenhum'),
                'tipo_imunidade' => $this->textoOuTraco(data_get($retorno, 'immunityType')),
                'suspensao_exigibilidade' => $this->textoOuTraco(data_get($retorno, 'issSuspended') ? 'Sim' : 'Não'),
                'numero_processo' => $this->textoOuTraco(data_get($retorno, 'issSuspensionProcessNumber')),
                'beneficio_municipal' => $this->textoOuTraco(data_get($retorno, 'municipalBenefit')),
                'valor_servico' => $this->formatarMoeda($servicoValor),
                'desconto_incondicionado' => $this->formatarMoedaOuTraco(data_get($retorno, 'unconditionalDiscountAmount')),
                'total_deducoes' => $this->formatarMoedaOuTraco(data_get($retorno, 'deductionsAmount')),
                'calculo_bm' => $this->formatarMoedaOuTraco(data_get($retorno, 'bmAmount')),
                'bc_issqn' => $this->formatarMoedaOuTraco(
                    data_get($retorno, 'taxableAmount')
                    ?? data_get($retorno, 'baseTaxAmount')
                    ?? $servicoValor
                ),
                'aliquota_aplicada' => $this->formatarPercentual((float) $invoice->aliquota_iss),
                'retencao_issqn' => $this->textoOuTraco(data_get($retorno, 'issWithheld') ? 'Retido' : 'Não Retido'),
                'issqn_apurado' => $this->formatarMoedaOuTraco($valorIss),
            ],
            'tributacao_federal' => [
                'irrf' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.irrfAmount')),
                'previdencia' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.socialSecurityAmount')),
                'contribuicoes_sociais' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.socialContributionsAmount')),
                'descricao_contribuicoes' => $this->textoOuTraco(data_get($retorno, 'federalTaxes.socialContributionsDescription')),
                'pis' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.pisAmount')),
                'cofins' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.cofinsAmount')),
            ],
            'totais' => [
                'valor_servico' => $this->formatarMoeda($servicoValor),
                'desconto_condicionado' => $this->formatarMoedaOuTraco(data_get($retorno, 'conditionalDiscountAmount')),
                'desconto_incondicionado' => $this->formatarMoedaOuTraco(data_get($retorno, 'unconditionalDiscountAmount')),
                'issqn_retido' => $this->formatarMoedaOuTraco(data_get($retorno, 'withheldIssAmount')),
                'retencoes_federais' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.totalAmount')),
                'pis_cofins' => $this->formatarMoedaOuTraco(data_get($retorno, 'federalTaxes.pisCofinsOwnAssessmentAmount')),
                'valor_liquido' => $this->formatarMoeda(
                    max(0, $servicoValor - $this->floatValue(data_get($retorno, 'withheldIssAmount')) - $this->floatValue(data_get($retorno, 'federalTaxes.totalAmount')))
                ),
            ],
            'tributos_aproximados' => [
                'federais' => $this->formatarMoedaOuTraco(data_get($retorno, 'approximateTaxes.federal')),
                'estaduais' => $this->formatarMoedaOuTraco(data_get($retorno, 'approximateTaxes.state')),
                'municipais' => $this->formatarMoedaOuTraco($valorIss),
            ],
            'informacoes_complementares' => [
                'texto' => $this->montarInformacoesComplementares([
                    $invoice->descricao_servico,
                    $property ? 'Imóvel: ' . $this->identificarImovel($property) : null,
                    $invoice->financeiro_metadata['forma_pagamento'] ?? null ? 'Pagamento: ' . $invoice->financeiro_metadata['forma_pagamento'] : null,
                    $invoice->financeiro_vencimento ? 'Vencimento: ' . $invoice->financeiro_vencimento->format('d/m/Y') : null,
                    $invoice->integracao_id ? 'Integração NFe.io: ' . $invoice->integracao_id : null,
                ]),
                'nbs' => $this->textoOuTraco(data_get($retorno, 'nbs'), data_get($tenant?->metadata, 'nfse_nbs'), '110012100'),
            ],
        ];
    }

    private function resolverAutoridadeMunicipal(?string $cidade): string
    {
        $cidadeNormalizada = trim((string) $cidade);

        return $cidadeNormalizada !== ''
            ? 'Prefeitura Municipal de ' . $cidadeNormalizada
            : 'Prefeitura Municipal';
    }

    private function resolverTituloChaveAcesso(array $retorno, array $payload): string
    {
        return $this->resolverChaveAcesso($retorno, $payload) !== '-'
            ? 'Chave de acesso da NFS-e'
            : 'Identificador da NFSe';
    }

    private function resolverChaveAcesso(array $retorno, array $payload): string
    {
        return $this->textoOuTraco(
            data_get($retorno, 'accessKey'),
            data_get($retorno, 'access_key'),
            data_get($retorno, 'key'),
            data_get($retorno, 'verificationCode'),
            data_get($payload, 'integracao_id')
        );
    }

    private function montarEnderecoEmitente(?Tenant $tenant, array $retorno, array $nfePayload): array
    {
        $street = $this->textoOuTraco(
            data_get($retorno, 'seller.address.street'),
            data_get($retorno, 'provider.address.street'),
            data_get($nfePayload, 'seller.address.street'),
            $tenant?->endereco
        );
        $number = $this->textoOuTraco(
            data_get($retorno, 'seller.address.number'),
            data_get($retorno, 'provider.address.number'),
            data_get($nfePayload, 'seller.address.number')
        );
        $district = $this->textoOuTraco(
            data_get($retorno, 'seller.address.district'),
            data_get($retorno, 'provider.address.district'),
            data_get($nfePayload, 'seller.address.district')
        );
        $city = $this->textoOuTraco(
            data_get($retorno, 'seller.address.city.name'),
            data_get($retorno, 'provider.address.city.name'),
            data_get($nfePayload, 'seller.address.city.name'),
            data_get($tenant?->metadata, 'cidade')
        );
        $state = $this->textoOuTraco(
            data_get($retorno, 'seller.address.state'),
            data_get($retorno, 'provider.address.state'),
            data_get($nfePayload, 'seller.address.state'),
            data_get($tenant?->metadata, 'uf')
        );
        $postalCode = $this->formatarCep(
            data_get($retorno, 'seller.address.postalCode')
            ?? data_get($retorno, 'provider.address.postalCode')
            ?? data_get($nfePayload, 'seller.address.postalCode')
            ?? data_get($tenant?->metadata, 'cep')
        );

        return [
            'street_line' => trim(implode(', ', array_filter([$street !== '-' ? $street : null, $number !== '-' ? $number : null, $district !== '-' ? $district : null]))) ?: '-',
            'city_line' => trim(implode(' - ', array_filter([$city !== '-' ? $city : null, $state !== '-' ? $state : null]))) ?: '-',
            'postal_code' => $postalCode,
            'city' => $city !== '-' ? $city : null,
        ];
    }

    private function montarEnderecoTomador(array ...$fontes): array
    {
        $campos = [
            'street' => ['logradouro', 'street'],
            'number' => ['numero', 'number'],
            'district' => ['bairro', 'district'],
            'city' => ['cidade', 'city', 'city.name'],
            'state' => ['uf', 'state'],
            'postalCode' => ['cep', 'postalCode'],
            'additionalInformation' => ['complemento', 'additionalInformation'],
        ];

        $dados = [];
        foreach ($campos as $campoDestino => $aliases) {
            $valor = null;
            foreach ($fontes as $fonte) {
                foreach ($aliases as $alias) {
                    $candidato = data_get($fonte, $alias);
                    if ($candidato !== null && $candidato !== '') {
                        $valor = $candidato;
                        break 2;
                    }
                }
            }
            $dados[$campoDestino] = $valor;
        }

        $streetLine = trim(implode(', ', array_filter([
            $dados['street'] ?? null,
            $dados['number'] ?? null,
            $dados['district'] ?? null,
            $dados['additionalInformation'] ?? null,
        ]))) ?: '-';
        $cityLine = trim(implode(' - ', array_filter([
            $dados['city'] ?? null,
            $dados['state'] ?? null,
        ]))) ?: '-';

        return [
            'street_line' => $streetLine,
            'city_line' => $cityLine,
            'postal_code' => $this->formatarCep($dados['postalCode'] ?? null),
        ];
    }

    private function resolverLocalPrestacao(?Property $property, array $emitenteEndereco, array $retorno, array $nfePayload): string
    {
        return $this->textoOuTraco(
            data_get($retorno, 'serviceCity'),
            data_get($retorno, 'serviceCity.name'),
            data_get($nfePayload, 'serviceCity.name'),
            $emitenteEndereco['city_line'] ?? null,
            $property ? trim(implode(' - ', array_filter([$property->cidade, $property->estado]))) : null,
        );
    }

    private function resolverSimplesNacional(array $retorno, ?Tenant $tenant): string
    {
        $valor = data_get($retorno, 'simpleNational')
            ?? data_get($retorno, 'taxRegime.simpleNational')
            ?? data_get($tenant?->metadata, 'simple_national');

        if (is_bool($valor)) {
            return $valor
                ? 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)'
                : 'Não optante';
        }

        return $this->textoOuTraco($valor, 'Não informado');
    }

    private function resolverRegimeApuracao(array $retorno): string
    {
        return $this->textoOuTraco(
            data_get($retorno, 'taxRegimeDescription'),
            data_get($retorno, 'taxRegime'),
            'Regime de apuração não informado'
        );
    }

    private function identificarImovel(Property $property): string
    {
        return $property->codigo
            ?: ($property->codigo_imovel ?: ($property->referencia_imovel ?: ('#' . $property->id)));
    }

    private function formatarDocumento(?string $documento): string
    {
        $digits = preg_replace('/\D+/', '', (string) $documento);

        if ($digits === '') {
            return '-';
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?: $digits;
        }

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?: $digits;
        }

        return $digits;
    }

    private function formatarTelefone(?string $telefone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $telefone);

        if ($digits === '') {
            return '-';
        }

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        return $digits;
    }

    private function formatarCep(?string $cep): string
    {
        $digits = preg_replace('/\D+/', '', (string) $cep);

        if ($digits === '') {
            return '-';
        }

        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?: $digits;
    }

    private function formatarMoeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function formatarMoedaOuTraco(mixed $valor): string
    {
        if ($valor === null || $valor === '' || (is_numeric($valor) && (float) $valor <= 0)) {
            return '-';
        }

        return $this->formatarMoeda((float) $valor);
    }

    private function formatarPercentual(float $valor): string
    {
        return $valor > 0
            ? number_format($valor, 2, ',', '.') . '%'
            : '-';
    }

    private function resolverDataCurta(mixed ...$valores): string
    {
        $data = $this->resolverCarbon(...$valores);

        return $data?->format('d/m/Y') ?: '-';
    }

    private function resolverDataHora(mixed ...$valores): string
    {
        $data = $this->resolverCarbon(...$valores);

        return $data?->format('d/m/Y H:i:s') ?: '-';
    }

    private function resolverCarbon(mixed ...$valores): ?CarbonInterface
    {
        foreach ($valores as $valor) {
            if ($valor instanceof CarbonInterface) {
                return $valor;
            }

            if (is_string($valor) && trim($valor) !== '') {
                try {
                    return now()->parse($valor);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function montarInformacoesComplementares(array $partes): string
    {
        $partesFiltradas = array_values(array_filter(array_map(function ($parte) {
            $texto = trim((string) $parte);
            return $texto !== '' ? $texto : null;
        }, $partes)));

        return $partesFiltradas !== [] ? implode(' | ', $partesFiltradas) : '-';
    }

    private function textoOuTraco(mixed ...$valores): string
    {
        foreach ($valores as $valor) {
            if (is_bool($valor)) {
                return $valor ? 'Sim' : 'Não';
            }

            if (is_scalar($valor)) {
                $texto = trim((string) $valor);
                if ($texto !== '') {
                    return $texto;
                }
            }
        }

        return '-';
    }

    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}