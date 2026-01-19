<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Serviço de integração com NFE.io
 * Documentação: https://nfe.io/docs/desenvolvedores/rest-api/nota-fiscal-servico-RNFTS
 */
class NFeIOService
{
    private $apiKey;
    private $companyId;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('NFE_IO_API_KEY');
        $this->companyId = env('NFE_IO_COMPANY_ID');
        $this->baseUrl = env('NFE_IO_BASE_URL', 'https://api.nfe.io');
        
        if (empty($this->apiKey) || empty($this->companyId)) {
            Log::warning('[NFE.io] API não configurada - configure NFE_IO_API_KEY e NFE_IO_COMPANY_ID no .env');
        }
    }

    /**
     * Emitir Nota Fiscal de Serviço Eletrônica (NFSe)
     * 
     * @param array $data Dados da nota fiscal
     * @return array Dados da NFSe emitida
     */
    public function emitirNFSe(array $data)
    {
        try {
            Log::info('[NFE.io] Emitindo NFSe', [
                'valor' => $data['valorServicos'],
                'tomador' => $data['tomador']['nome']
            ]);

            if (empty($this->apiKey) || empty($this->companyId)) {
                throw new \Exception('NFE.io não configurado - configure NFE_IO_API_KEY e NFE_IO_COMPANY_ID no .env');
            }

            // Preparar payload segundo a API do NFE.io
            $payload = [
                'cityServiceCode' => env('NFE_IO_SERVICE_CODE', '01.01'), // Código do serviço na cidade
                'description' => $data['descricao'],
                'servicesAmount' => $data['valorServicos'],
                'borrower' => [
                    'federalTaxNumber' => $this->limparCpfCnpj($data['tomador']['cpfCnpj']),
                    'name' => $data['tomador']['nome'],
                    'email' => $data['tomador']['email']
                ]
            ];

            // Adicionar endereço do tomador se fornecido
            if (isset($data['tomador']['endereco'])) {
                $payload['borrower']['address'] = [
                    'country' => 'BRA',
                    'postalCode' => $data['tomador']['endereco']['cep'] ?? '',
                    'street' => $data['tomador']['endereco']['logradouro'] ?? '',
                    'number' => $data['tomador']['endereco']['numero'] ?? 'S/N',
                    'additionalInformation' => $data['tomador']['endereco']['complemento'] ?? '',
                    'district' => $data['tomador']['endereco']['bairro'] ?? '',
                    'city' => [
                        'code' => $data['tomador']['endereco']['codigoMunicipio'] ?? '',
                        'name' => $data['tomador']['endereco']['cidade'] ?? ''
                    ],
                    'state' => $data['tomador']['endereco']['uf'] ?? ''
                ];
            }

            // Adicionar observações
            if (!empty($data['observacoes'])) {
                $payload['additionalInformation'] = $data['observacoes'];
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post("{$this->baseUrl}/v1/companies/{$this->companyId}/serviceinvoices", $payload);

            if (!$response->successful()) {
                Log::error('[NFE.io] Erro ao emitir NFSe', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                $errorMessage = $response->json('message') ?? 'Erro ao emitir NFSe';
                throw new \Exception($errorMessage);
            }

            $result = $response->json();

            $nfseData = [
                'id' => $result['id'],
                'numero' => $result['number'] ?? null,
                'codigoVerificacao' => $result['checkCode'] ?? null,
                'status' => $result['status'],
                'pdfUrl' => $result['pdfUrl'] ?? null,
                'xmlUrl' => $result['xmlUrl'] ?? null,
                'rpsNumber' => $result['rpsNumber'] ?? null,
                'rpsSerialNumber' => $result['rpsSerialNumber'] ?? null
            ];

            Log::info('[NFE.io] NFSe emitida com sucesso', [
                'id' => $nfseData['id'],
                'numero' => $nfseData['numero'],
                'status' => $nfseData['status']
            ]);

            return $nfseData;

        } catch (\Exception $e) {
            Log::error('[NFE.io] Exceção ao emitir NFSe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Consultar status de uma NFSe
     * 
     * @param string $nfseId ID da NFSe no NFE.io
     * @return array Dados da NFSe
     */
    public function consultarNFSe($nfseId)
    {
        try {
            Log::info('[NFE.io] Consultando NFSe', ['nfse_id' => $nfseId]);

            if (empty($this->apiKey) || empty($this->companyId)) {
                throw new \Exception('NFE.io não configurado');
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Accept' => 'application/json'
            ])->get("{$this->baseUrl}/v1/companies/{$this->companyId}/serviceinvoices/{$nfseId}");

            if (!$response->successful()) {
                Log::error('[NFE.io] Erro ao consultar NFSe', [
                    'nfse_id' => $nfseId,
                    'status' => $response->status()
                ]);
                
                throw new \Exception('Erro ao consultar NFSe');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('[NFE.io] Exceção ao consultar NFSe', [
                'nfse_id' => $nfseId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Cancelar uma NFSe
     * 
     * @param string $nfseId ID da NFSe
     * @return bool Sucesso
     */
    public function cancelarNFSe($nfseId)
    {
        try {
            Log::info('[NFE.io] Cancelando NFSe', ['nfse_id' => $nfseId]);

            if (empty($this->apiKey) || empty($this->companyId)) {
                throw new \Exception('NFE.io não configurado');
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->delete("{$this->baseUrl}/v1/companies/{$this->companyId}/serviceinvoices/{$nfseId}");

            if (!$response->successful()) {
                Log::error('[NFE.io] Erro ao cancelar NFSe', [
                    'nfse_id' => $nfseId,
                    'status' => $response->status()
                ]);
                
                return false;
            }

            Log::info('[NFE.io] NFSe cancelada', ['nfse_id' => $nfseId]);
            return true;

        } catch (\Exception $e) {
            Log::error('[NFE.io] Exceção ao cancelar NFSe', [
                'nfse_id' => $nfseId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Baixar PDF da NFSe
     * 
     * @param string $pdfUrl URL do PDF
     * @return string Conteúdo do PDF em base64
     */
    public function baixarPDF($pdfUrl)
    {
        try {
            $response = Http::get($pdfUrl);

            if (!$response->successful()) {
                throw new \Exception('Erro ao baixar PDF');
            }

            return base64_encode($response->body());

        } catch (\Exception $e) {
            Log::error('[NFE.io] Erro ao baixar PDF', [
                'url' => $pdfUrl,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Limpar CPF/CNPJ removendo caracteres especiais
     * 
     * @param string $cpfCnpj
     * @return string
     */
    private function limparCpfCnpj($cpfCnpj)
    {
        return preg_replace('/[^0-9]/', '', $cpfCnpj);
    }
}
