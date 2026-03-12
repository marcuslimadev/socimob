<?php

namespace App\Services;

use App\Models\ImovelImagem;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PropertyTrashService
{
    public function moveToTrash(Property $property, string $source, string $reason, ?int $userId = null, array $metadata = []): Property
    {
        if ($property->trashed()) {
            return $property;
        }

        $property->forceFill([
            'trash_source' => $source,
            'trash_reason' => $reason,
            'trashed_by_user_id' => $userId,
            'trash_metadata' => empty($metadata) ? null : $metadata,
        ])->save();

        $property->delete();

        return Property::withTrashed()->findOrFail($property->id);
    }

    public function restoreFromTrash(Property $property): Property
    {
        if (!$property->trashed()) {
            return $property;
        }

        $property->restore();
        $property->forceFill([
            'trash_source' => null,
            'trash_reason' => null,
            'trashed_by_user_id' => null,
            'trash_metadata' => null,
        ])->save();

        return $property->fresh();
    }

    public function forceDelete(Property $property): void
    {
        DB::transaction(function () use ($property) {
            $tenant = $property->tenant_id ? Tenant::find($property->tenant_id) : null;

            if ($tenant && $property->imobi_brasil_external_id) {
                $result = ImobiBrasilService::deleteProperty((int) $property->imobi_brasil_external_id, $tenant);
                if (!($result['success'] ?? false)) {
                    throw new \RuntimeException($result['error'] ?? 'Falha ao excluir imóvel no Imobi Brasil.');
                }
            }

            $mediaUrls = $this->collectMediaUrls($property);

            if (Schema::hasTable('property_portal_tenants')) {
                DB::table('property_portal_tenants')->where('property_id', $property->id)->delete();
            }

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->where('property_id', $property->id)->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('controle_chaves_movimentacoes')) {
                DB::table('controle_chaves_movimentacoes')->where('property_id', $property->id)->delete();
            }

            ImovelImagem::where('codigo', $property->codigo)->delete();

            foreach ($mediaUrls as $url) {
                $this->deleteLocalMediaByUrl($url);
            }

            $this->deletePropertyUploadDirectories($property);

            $property->forceDelete();
        });
    }

    private function collectMediaUrls(Property $property): array
    {
        $urls = [];

        if (!empty($property->imagem_destaque)) {
            $urls[] = $property->imagem_destaque;
        }

        if (is_array($property->imagens)) {
            foreach ($property->imagens as $url) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        ImovelImagem::where('codigo', $property->codigo)
            ->pluck('url')
            ->each(function ($url) use (&$urls) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            });

        return array_values(array_unique($urls));
    }

    private function deleteLocalMediaByUrl(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (!str_contains($normalized, 'uploads/properties/')) {
            return;
        }

        $candidates = [];

        if (str_starts_with($normalized, 'storage/')) {
            $candidates[] = storage_path('app/public/' . substr($normalized, strlen('storage/')));
        }

        $candidates[] = public_path($normalized);

        if (str_starts_with($normalized, 'uploads/')) {
            $candidates[] = storage_path('app/public/' . $normalized);
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate && File::exists($candidate)) {
                File::delete($candidate);
            }
        }
    }

    private function deletePropertyUploadDirectories(Property $property): void
    {
        $propertyCode = $property->codigo_imovel ?: $property->codigo;
        if (!$property->tenant_id || !$propertyCode) {
            return;
        }

        $relative = 'uploads/properties/tenant_' . $property->tenant_id . '/' . $propertyCode;
        $directories = [
            public_path($relative),
            storage_path('app/public/' . $relative),
        ];

        foreach ($directories as $directory) {
            if (File::isDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }
}