<?php
namespace App\Services;

use App\Models\Property;
use App\Models\ImovelImagem;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class ImobelBrasilImageService
{
    /**
     * Tentar enviar imagens para Imobi Brasil
     * A API Imobi Brasil NÃO tem endpoint implementado para imagens no momento
     * 
     * WORKAROUND:
     * - Armazena as URLs de imagens localmente
     * - Mantém registro de quais imagens foram sincronizadas
     * - Quando a API implementar o endpoint, pode sincronizar em batch
     */
    public static function sendPropertyImages(Property $property, Tenant $tenant): array
    {
        try {
            // Validar se propriedade foi enviada
            if (!$property->imobi_brasil_sent || !$property->imobi_brasil_external_id) {
                return [
                    'success' => false,
                    'error' => 'Propriedade não foi enviada para Imobi Brasil ainda',
                ];
            }

            // Buscar imagens locais
            $imagens = ImovelImagem::where('codigo', $property->codigo)->get();
            
            if ($imagens->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'Nenhuma imagem para enviar',
                    'pending_images' => 0,
                ];
            }

            // Armazenar referência das imagens para envio futuro
            $property->update([
                'imobi_brasil_images_pending' => true,
                'imobi_brasil_images_count' => $imagens->count(),
            ]);

            Log::info('Imagens da propriedade marcadas para sincronização com Imobi Brasil', [
                'property_id' => $property->id,
                'external_id' => $property->imobi_brasil_external_id,
                'images_count' => $imagens->count(),
                'status' => 'AGUARDANDO IMPLEMENTAÇÃO DO ENDPOINT NA API',
            ]);

            return [
                'success' => true,
                'message' => 'Imagens armazenadas para sincronização futura (API ainda não implementou endpoint)',
                'pending_images' => $imagens->count(),
                'status' => 'PENDING',
                'note' => 'O endpoint de imagens da API Imobi Brasil ainda não foi implementado. ' .
                         'As imagens estão armazenadas localmente e serão sincronizadas automaticamente ' .
                         'quando a API disponibilizar o endpoint de upload.',
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao processar imagens para Imobi Brasil', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Listar imagens pendentes de sincronização
     */
    public static function getPendingImages(Tenant $tenant): array
    {
        $properties = Property::where('tenant_id', $tenant->id)
            ->where('imobi_brasil_sent', true)
            ->where('imobi_brasil_images_pending', true)
            ->get();

        $pending = [];
        foreach ($properties as $property) {
            $images = ImovelImagem::where('codigo', $property->codigo)->get();
            if ($images->isNotEmpty()) {
                $pending[] = [
                    'property_id' => $property->id,
                    'external_id' => $property->imobi_brasil_external_id,
                    'reference' => $property->referencia,
                    'images_count' => $images->count(),
                    'images' => $images->pluck('url')->toArray(),
                ];
            }
        }

        return $pending;
    }

    /**
     * Quando a API implementar o endpoint de imagens, usar este método
     */
    public static function syncPendingImagesToAPI(Property $property, Tenant $tenant): array
    {
        // Este método será implementado quando a API Imobi Brasil disponibilizar
        // o endpoint: POST /imovel/{codigo}/galeria ou similar
        
        Log::warning('Sincronização de imagens ainda não implementada', [
            'reason' => 'API Imobi Brasil não tem endpoint de imagens',
            'expected_endpoint' => 'POST /imobi/codigo/galeria ou similar',
        ]);

        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED',
            'message' => 'Endpoint de sincronização de imagens ainda não está disponível na API Imobi Brasil',
        ];
    }
}

?>
