<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Property;
use Carbon\Carbon;

/**
 * Serviço de Importação de Imóveis de Múltiplas Fontes
 */
class PropertyImportService
{
    private $propertySyncService;

    public function __construct(PropertySyncService $propertySyncService)
    {
        $this->propertySyncService = $propertySyncService;
    }

    /**
     * Importar de Vivareal
     *
     * @param int $tenantId
     * @param array $credentials
     * @return array
     */
    public function importFromVivareal(int $tenantId, array $credentials): array
    {
        Log::info('Starting Vivareal import', ['tenant_id' => $tenantId]);

        try {
            $apiToken = $credentials['api_token'] ?? env('VIVAREAL_API_TOKEN');
            $accountId = $credentials['account_id'] ?? env('VIVAREAL_ACCOUNT_ID');

            if (!$apiToken || !$accountId) {
                throw new \Exception('Vivareal credentials not configured');
            }

            $baseUrl = 'https://api.vivareal.com/v2';
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiToken}",
                'Accept' => 'application/json'
            ])->get("{$baseUrl}/listings", [
                'account_id' => $accountId,
                'limit' => 100
            ]);

            if (!$response->successful()) {
                throw new \Exception('Vivareal API error: ' . $response->body());
            }

            $listings = $response->json()['listings'] ?? [];

            $stats = [
                'found' => count($listings),
                'imported' => 0,
                'updated' => 0,
                'errors' => 0
            ];

            foreach ($listings as $listing) {
                try {
                    $mapped = $this->mapVivarealData($listing, $tenantId);
                    $existing = Property::where('external_id', $mapped['external_id'])
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($existing) {
                        $existing->update($mapped);
                        $stats['updated']++;
                    } else {
                        Property::create($mapped);
                        $stats['imported']++;
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Error importing Vivareal listing', [
                        'listing_id' => $listing['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Vivareal import completed', $stats);

            return [
                'success' => true,
                'source' => 'vivareal',
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Vivareal import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'source' => 'vivareal',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Importar de Imobiliaria.com (OLX)
     *
     * @param int $tenantId
     * @param array $credentials
     * @return array
     */
    public function importFromImobiliariacom(int $tenantId, array $credentials): array
    {
        Log::info('Starting Imobiliaria.com import', ['tenant_id' => $tenantId]);

        try {
            $apiToken = $credentials['api_token'] ?? env('IMOBILIARIACOM_API_TOKEN');

            if (!$apiToken) {
                throw new \Exception('Imobiliaria.com credentials not configured');
            }

            $baseUrl = 'https://api.imobiliaria.com.br/v1';
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiToken}",
                'Accept' => 'application/json'
            ])->get("{$baseUrl}/properties", [
                'status' => 'active',
                'limit' => 100
            ]);

            if (!$response->successful()) {
                throw new \Exception('Imobiliaria.com API error: ' . $response->body());
            }

            $properties = $response->json()['data'] ?? [];

            $stats = [
                'found' => count($properties),
                'imported' => 0,
                'updated' => 0,
                'errors' => 0
            ];

            foreach ($properties as $property) {
                try {
                    $mapped = $this->mapImobiliariacomData($property, $tenantId);
                    $existing = Property::where('external_id', $mapped['external_id'])
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($existing) {
                        $existing->update($mapped);
                        $stats['updated']++;
                    } else {
                        Property::create($mapped);
                        $stats['imported']++;
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Error importing Imobiliaria.com property', [
                        'property_id' => $property['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Imobiliaria.com import completed', $stats);

            return [
                'success' => true,
                'source' => 'imobiliariacom',
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Imobiliaria.com import failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'source' => 'imobiliariacom',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Importar via SFTP
     *
     * @param int $tenantId
     * @param array $sftpConfig
     * @return array
     */
    public function importFromSFTP(int $tenantId, array $sftpConfig): array
    {
        Log::info('Starting SFTP import', ['tenant_id' => $tenantId]);

        try {
            $host = $sftpConfig['host'] ?? env('SFTP_HOST');
            $username = $sftpConfig['username'] ?? env('SFTP_USERNAME');
            $password = $sftpConfig['password'] ?? env('SFTP_PASSWORD');
            $port = $sftpConfig['port'] ?? 22;
            $remotePath = $sftpConfig['path'] ?? '/properties';

            if (!$host || !$username || !$password) {
                throw new \Exception('SFTP credentials not configured');
            }

            // Conectar via SFTP
            $connection = ssh2_connect($host, $port);
            if (!$connection) {
                throw new \Exception('Could not connect to SFTP server');
            }

            if (!ssh2_auth_password($connection, $username, $password)) {
                throw new \Exception('SFTP authentication failed');
            }

            $sftp = ssh2_sftp($connection);

            // Listar arquivos XML/JSON
            $files = [];
            $handle = opendir("ssh2.sftp://{$sftp}{$remotePath}");

            while (false !== ($file = readdir($handle))) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['xml', 'json'])) {
                    $files[] = $file;
                }
            }

            closedir($handle);

            $stats = [
                'files_found' => count($files),
                'imported' => 0,
                'updated' => 0,
                'errors' => 0
            ];

            foreach ($files as $file) {
                try {
                    $content = file_get_contents("ssh2.sftp://{$sftp}{$remotePath}/{$file}");

                    if (str_ends_with($file, '.xml')) {
                        $data = simplexml_load_string($content);
                        $properties = $this->parseXMLProperties($data);
                    } else {
                        $data = json_decode($content, true);
                        $properties = $data['properties'] ?? $data;
                    }

                    foreach ($properties as $property) {
                        $mapped = $this->mapCustomData($property, $tenantId);
                        $existing = Property::where('codigo', $mapped['codigo'])
                            ->where('tenant_id', $tenantId)
                            ->first();

                        if ($existing) {
                            $existing->update($mapped);
                            $stats['updated']++;
                        } else {
                            Property::create($mapped);
                            $stats['imported']++;
                        }
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Error processing SFTP file', [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('SFTP import completed', $stats);

            return [
                'success' => true,
                'source' => 'sftp',
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('SFTP import failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'source' => 'sftp',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Importar via API customizada
     *
     * @param int $tenantId
     * @param array $apiConfig
     * @return array
     */
    public function importFromCustomAPI(int $tenantId, array $apiConfig): array
    {
        Log::info('Starting custom API import', ['tenant_id' => $tenantId]);

        try {
            $url = $apiConfig['url'] ?? env('CUSTOM_API_URL');
            $token = $apiConfig['token'] ?? env('CUSTOM_API_TOKEN');

            if (!$url) {
                throw new \Exception('Custom API URL not configured');
            }

            $headers = ['Accept' => 'application/json'];
            if ($token) {
                $headers['Authorization'] = "Bearer {$token}";
            }

            $response = Http::withHeaders($headers)->get($url);

            if (!$response->successful()) {
                throw new \Exception('Custom API error: ' . $response->body());
            }

            $data = $response->json();
            $properties = $data['properties'] ?? $data['data'] ?? $data;

            $stats = [
                'found' => count($properties),
                'imported' => 0,
                'updated' => 0,
                'errors' => 0
            ];

            foreach ($properties as $property) {
                try {
                    $mapped = $this->mapCustomData($property, $tenantId);
                    $existing = Property::where('codigo', $mapped['codigo'])
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($existing) {
                        $existing->update($mapped);
                        $stats['updated']++;
                    } else {
                        Property::create($mapped);
                        $stats['imported']++;
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Error importing from custom API', [
                        'property_code' => $property['codigo'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Custom API import completed', $stats);

            return [
                'success' => true,
                'source' => 'custom_api',
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Custom API import failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'source' => 'custom_api',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Agendar importação automática
     *
     * @param int $tenantId
     * @param string $source
     * @param string $schedule (cron expression)
     * @param array $config
     * @return array
     */
    public function scheduleImport(int $tenantId, string $source, string $schedule, array $config): array
    {
        try {
            // Salvar configuração de importação agendada
            $scheduled = DB::table('scheduled_imports')->insertGetId([
                'tenant_id' => $tenantId,
                'source' => $source,
                'schedule' => $schedule,
                'config' => json_encode($config),
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            Log::info('Import scheduled', [
                'tenant_id' => $tenantId,
                'source' => $source,
                'schedule' => $schedule,
                'id' => $scheduled
            ]);

            return [
                'success' => true,
                'scheduled_import_id' => $scheduled
            ];

        } catch (\Exception $e) {
            Log::error('Error scheduling import', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mapear dados do Vivareal
     */
    private function mapVivarealData(array $data, int $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'external_id' => 'vivareal_' . ($data['id'] ?? uniqid()),
            'codigo' => 'VR' . ($data['id'] ?? uniqid()),
            'titulo' => $data['title'] ?? '',
            'descricao' => $data['description'] ?? '',
            'tipo_imovel' => $this->mapPropertyType($data['type'] ?? ''),
            'finalidade_imovel' => $this->mapPurpose($data['transaction'] ?? 'sale'),
            'valor_venda' => $data['price'] ?? 0,
            'area_total' => $data['area'] ?? 0,
            'dormitorios' => $data['bedrooms'] ?? 0,
            'banheiros' => $data['bathrooms'] ?? 0,
            'garagem' => $data['parking'] ?? 0,
            'logradouro' => $data['address']['street'] ?? '',
            'bairro' => $data['address']['neighborhood'] ?? '',
            'cidade' => $data['address']['city'] ?? '',
            'estado' => $data['address']['state'] ?? '',
            'imagens' => json_encode($data['images'] ?? []),
            'active' => true,
            'exibir_imovel' => true,
            'last_sync' => Carbon::now(),
        ];
    }

    /**
     * Mapear dados do Imobiliaria.com
     */
    private function mapImobiliariacomData(array $data, int $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'external_id' => 'imob_' . ($data['id'] ?? uniqid()),
            'codigo' => 'IM' . ($data['id'] ?? uniqid()),
            'titulo' => $data['titulo'] ?? '',
            'descricao' => $data['descricao'] ?? '',
            'tipo_imovel' => $this->mapPropertyType($data['tipo'] ?? ''),
            'finalidade_imovel' => $this->mapPurpose($data['finalidade'] ?? 'venda'),
            'valor_venda' => $data['valor'] ?? 0,
            'area_total' => $data['area'] ?? 0,
            'dormitorios' => $data['quartos'] ?? 0,
            'banheiros' => $data['banheiros'] ?? 0,
            'garagem' => $data['vagas'] ?? 0,
            'logradouro' => $data['endereco']['rua'] ?? '',
            'bairro' => $data['endereco']['bairro'] ?? '',
            'cidade' => $data['endereco']['cidade'] ?? '',
            'estado' => $data['endereco']['estado'] ?? '',
            'imagens' => json_encode($data['fotos'] ?? []),
            'active' => true,
            'exibir_imovel' => true,
            'last_sync' => Carbon::now(),
        ];
    }

    /**
     * Mapear dados customizados
     */
    private function mapCustomData(array $data, int $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'external_id' => $data['external_id'] ?? $data['id'] ?? uniqid(),
            'codigo' => $data['codigo'] ?? $data['code'] ?? uniqid(),
            'titulo' => $data['titulo'] ?? $data['title'] ?? '',
            'descricao' => $data['descricao'] ?? $data['description'] ?? '',
            'tipo_imovel' => $this->mapPropertyType($data['tipo'] ?? $data['type'] ?? ''),
            'finalidade_imovel' => $this->mapPurpose($data['finalidade'] ?? $data['purpose'] ?? 'venda'),
            'valor_venda' => $data['valor'] ?? $data['price'] ?? 0,
            'area_total' => $data['area'] ?? 0,
            'dormitorios' => $data['quartos'] ?? $data['bedrooms'] ?? 0,
            'banheiros' => $data['banheiros'] ?? $data['bathrooms'] ?? 0,
            'garagem' => $data['vagas'] ?? $data['parking'] ?? 0,
            'logradouro' => $data['logradouro'] ?? $data['street'] ?? '',
            'bairro' => $data['bairro'] ?? $data['neighborhood'] ?? '',
            'cidade' => $data['cidade'] ?? $data['city'] ?? '',
            'estado' => $data['estado'] ?? $data['state'] ?? '',
            'imagens' => json_encode($data['imagens'] ?? $data['images'] ?? []),
            'active' => true,
            'exibir_imovel' => true,
            'last_sync' => Carbon::now(),
        ];
    }

    /**
     * Parse XML properties
     */
    private function parseXMLProperties($xml): array
    {
        $properties = [];

        foreach ($xml->property ?? [] as $prop) {
            $properties[] = [
                'codigo' => (string)($prop->code ?? ''),
                'titulo' => (string)($prop->title ?? ''),
                'descricao' => (string)($prop->description ?? ''),
                'tipo' => (string)($prop->type ?? ''),
                'finalidade' => (string)($prop->purpose ?? ''),
                'valor' => (float)($prop->price ?? 0),
                'area' => (float)($prop->area ?? 0),
                'quartos' => (int)($prop->bedrooms ?? 0),
                'banheiros' => (int)($prop->bathrooms ?? 0),
                'vagas' => (int)($prop->parking ?? 0),
            ];
        }

        return $properties;
    }

    /**
     * Mapear tipo de imóvel
     */
    private function mapPropertyType(string $type): string
    {
        $map = [
            'apartment' => 'Apartamento',
            'house' => 'Casa',
            'condo' => 'Casa',
            'land' => 'Terreno',
            'commercial' => 'Comercial',
        ];

        return $map[strtolower($type)] ?? 'Apartamento';
    }

    /**
     * Mapear finalidade
     */
    private function mapPurpose(string $purpose): string
    {
        $map = [
            'sale' => 'Venda',
            'rent' => 'Aluguel',
            'rental' => 'Aluguel',
        ];

        return $map[strtolower($purpose)] ?? 'Venda';
    }
}
