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

    /**
     * Emitir NFSe com retry automático
     *
     * @param array $data
     * @param int $maxRetries
     * @return array|null
     */
    public function emitirComRetry(array $data, int $maxRetries = 3): ?array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                Log::info('[NFE.io] Tentando emitir NFSe', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'tomador' => $data['tomador']['nome'] ?? 'N/A'
                ]);

                $result = $this->emitirNFSe($data);

                if ($result) {
                    Log::info('[NFE.io] NFSe emitida com sucesso', [
                        'attempt' => $attempt,
                        'nfse_id' => $result['id']
                    ]);

                    return $result;
                }

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('[NFE.io] Tentativa falhou', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxRetries) {
                    Log::error('[NFE.io] Falha definitiva ao emitir NFSe', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return null;
                }

                // Aguardar antes de retry (backoff exponencial)
                sleep(pow(2, $attempt));
            }

            $attempt++;
        }

        return null;
    }

    /**
     * Processar webhook do NFE.io
     *
     * @param array $webhookData
     * @return array
     */
    public function processarWebhook(array $webhookData): array
    {
        try {
            Log::info('[NFE.io] Webhook recebido', [
                'data_keys' => array_keys($webhookData)
            ]);

            $nfseId = $webhookData['id'] ?? $webhookData['serviceInvoiceId'] ?? null;
            $status = $webhookData['status'] ?? null;

            if (!$nfseId) {
                throw new \Exception('Webhook sem ID da NFSe');
            }

            // Consultar dados atualizados da NFSe
            $nfseData = $this->consultarNFSe($nfseId);

            // Buscar CommissionInvoice relacionada
            $invoice = \App\Models\CommissionInvoice::where('nfse_id', $nfseId)
                ->orWhere('nfse_numero', $nfseData['number'] ?? null)
                ->first();

            if ($invoice) {
                // Atualizar dados da invoice
                $invoice->update([
                    'nfse_id' => $nfseData['id'],
                    'nfse_numero' => $nfseData['number'] ?? $invoice->nfse_numero,
                    'nfse_codigo_verificacao' => $nfseData['checkCode'] ?? $invoice->nfse_codigo_verificacao,
                    'nfse_pdf_url' => $nfseData['pdfUrl'] ?? $invoice->nfse_pdf_url,
                    'nfse_xml_url' => $nfseData['xmlUrl'] ?? $invoice->nfse_xml_url,
                    'nfse_status' => $status ?? $invoice->nfse_status,
                ]);

                Log::info('[NFE.io] Invoice atualizada via webhook', [
                    'invoice_id' => $invoice->id,
                    'nfse_id' => $nfseId,
                    'status' => $status
                ]);

                // Se NFSe foi emitida, atualizar status da invoice
                if ($status === 'Issued' && $invoice->status !== 'issued') {
                    $invoice->update(['status' => 'issued']);
                }
            }

            return [
                'success' => true,
                'nfse_id' => $nfseId,
                'status' => $status,
                'invoice_updated' => $invoice ? true : false
            ];

        } catch (\Exception $e) {
            Log::error('[NFE.io] Erro ao processar webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar webhook',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Armazenar comprovante (PDF) da NFSe
     *
     * @param string $nfseId
     * @param string $pdfUrl
     * @param string $storagePath
     * @return array
     */
    public function armazenarComprovante(string $nfseId, string $pdfUrl, string $storagePath = 'nfse'): array
    {
        try {
            Log::info('[NFE.io] Armazenando comprovante', [
                'nfse_id' => $nfseId,
                'pdf_url' => $pdfUrl
            ]);

            // Baixar PDF
            $pdfContent = $this->baixarPDF($pdfUrl);

            // Decodificar base64
            $pdfBinary = base64_decode($pdfContent);

            // Definir nome do arquivo
            $filename = "nfse_{$nfseId}_" . date('Y-m-d_H-i-s') . ".pdf";
            $fullPath = storage_path("app/{$storagePath}/{$filename}");

            // Criar diretório se não existir
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Salvar arquivo
            file_put_contents($fullPath, $pdfBinary);

            $relativePath = "{$storagePath}/{$filename}";

            Log::info('[NFE.io] Comprovante armazenado', [
                'nfse_id' => $nfseId,
                'path' => $relativePath,
                'size_bytes' => strlen($pdfBinary)
            ]);

            return [
                'success' => true,
                'path' => $relativePath,
                'full_path' => $fullPath,
                'filename' => $filename,
                'size' => strlen($pdfBinary)
            ];

        } catch (\Exception $e) {
            Log::error('[NFE.io] Erro ao armazenar comprovante', [
                'nfse_id' => $nfseId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao armazenar comprovante',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formatar dados fiscais para emissão de NFSe
     *
     * @param array $invoiceData
     * @return array
     */
    public function formatarDadosFiscais(array $invoiceData): array
    {
        // Validar campos obrigatórios
        $required = ['valor', 'descricao', 'tomador_nome', 'tomador_cpf_cnpj', 'tomador_email'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($invoiceData[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \Exception('Dados fiscais incompletos: ' . implode(', ', $missing));
        }

        // Formatar no padrão esperado pelo NFE.io
        return [
            'valorServicos' => (float) $invoiceData['valor'],
            'descricao' => $invoiceData['descricao'],
            'tomador' => [
                'cpfCnpj' => $this->limparCpfCnpj($invoiceData['tomador_cpf_cnpj']),
                'nome' => $invoiceData['tomador_nome'],
                'email' => $invoiceData['tomador_email'],
                'endereco' => [
                    'logradouro' => $invoiceData['tomador_logradouro'] ?? '',
                    'numero' => $invoiceData['tomador_numero'] ?? 'S/N',
                    'complemento' => $invoiceData['tomador_complemento'] ?? '',
                    'bairro' => $invoiceData['tomador_bairro'] ?? '',
                    'cidade' => $invoiceData['tomador_cidade'] ?? '',
                    'uf' => $invoiceData['tomador_uf'] ?? '',
                    'cep' => $this->limparCpfCnpj($invoiceData['tomador_cep'] ?? ''),
                    'codigoMunicipio' => $invoiceData['tomador_codigo_municipio'] ?? '',
                ]
            ],
            'observacoes' => $invoiceData['observacoes'] ?? ''
        ];
    }

    /**
     * Validar dados fiscais antes de emitir
     *
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validarDadosFiscais(array $data): array
    {
        $errors = [];

        // Validar valor
        if (empty($data['valorServicos']) || $data['valorServicos'] <= 0) {
            $errors[] = 'Valor dos serviços deve ser maior que zero';
        }

        // Validar descrição
        if (empty($data['descricao']) || strlen($data['descricao']) < 10) {
            $errors[] = 'Descrição deve ter no mínimo 10 caracteres';
        }

        // Validar tomador
        if (empty($data['tomador']['cpfCnpj'])) {
            $errors[] = 'CPF/CNPJ do tomador é obrigatório';
        } else {
            $cpfCnpj = $this->limparCpfCnpj($data['tomador']['cpfCnpj']);
            if (strlen($cpfCnpj) != 11 && strlen($cpfCnpj) != 14) {
                $errors[] = 'CPF/CNPJ inválido (deve ter 11 ou 14 dígitos)';
            }
        }

        if (empty($data['tomador']['nome'])) {
            $errors[] = 'Nome do tomador é obrigatório';
        }

        if (empty($data['tomador']['email'])) {
            $errors[] = 'Email do tomador é obrigatório';
        } elseif (!filter_var($data['tomador']['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email do tomador é inválido';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Listar NFSes emitidas
     *
     * @param array $filters
     * @return array
     */
    public function listarNFSes(array $filters = []): array
    {
        try {
            Log::info('[NFE.io] Listando NFSes', ['filters' => array_keys($filters)]);

            if (empty($this->apiKey) || empty($this->companyId)) {
                throw new \Exception('NFE.io não configurado');
            }

            $queryParams = [];

            if (!empty($filters['page'])) {
                $queryParams['page'] = $filters['page'];
            }

            if (!empty($filters['pageSize'])) {
                $queryParams['pageSize'] = $filters['pageSize'];
            }

            if (!empty($filters['status'])) {
                $queryParams['status'] = $filters['status'];
            }

            $url = "{$this->baseUrl}/v1/companies/{$this->companyId}/serviceinvoices";
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Accept' => 'application/json'
            ])->get($url);

            if (!$response->successful()) {
                throw new \Exception('Erro ao listar NFSes');
            }

            $data = $response->json();

            return [
                'success' => true,
                'data' => $data['serviceInvoices'] ?? [],
                'pagination' => [
                    'page' => $data['page'] ?? 1,
                    'pageSize' => $data['pageSize'] ?? 20,
                    'totalPages' => $data['totalPages'] ?? 1,
                    'totalItems' => $data['totalItems'] ?? 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error('[NFE.io] Erro ao listar NFSes', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao listar NFSes',
                'error' => $e->getMessage()
            ];
        }
    }
}
