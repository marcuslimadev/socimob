<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class LeadDocumentService
{
    public function storeUploadedDocument(
        Lead $lead,
        UploadedFile $file,
        ?string $tipo = null,
        ?string $nome = null,
        string $status = 'pendente'
    ): LeadDocument {
        $path = $file->store("leads/{$lead->id}/documents");

        return LeadDocument::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'nome' => $nome ?: $file->getClientOriginalName(),
            'tipo' => $tipo,
            'mime_type' => $file->getClientMimeType(),
            'arquivo_url' => $path,
            'status' => $status,
        ]);
    }

    public function deleteDocument(LeadDocument $document): void
    {
        $path = ltrim((string) $document->arquivo_url, '/');

        foreach (['local', 'public'] as $disk) {
            $storage = Storage::disk($disk);
            if ($path && $storage->exists($path)) {
                $storage->delete($path);
                break;
            }
        }
    }

    public function createZipForLead(Lead $lead, $documents): ?string
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = "lead-{$lead->id}-documentos.zip";
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP.');
        }

        $added = 0;
        foreach ($documents as $document) {
            $content = $this->getDocumentContent($document);
            if (!$content) {
                continue;
            }

            $fileName = $this->buildDocumentFileName($document, ++$added);
            $zip->addFromString($fileName, $content);
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            return null;
        }

        return $zipPath;
    }

    private function buildDocumentFileName(LeadDocument $document, int $index): string
    {
        $baseName = $document->nome ?: 'documento';
        $extension = pathinfo($baseName, PATHINFO_EXTENSION) ?: 'pdf';
        $sanitized = Str::slug(pathinfo($baseName, PATHINFO_FILENAME));

        if (!$sanitized) {
            $sanitized = 'documento';
        }

        return sprintf('%02d-%s.%s', $index, $sanitized, $extension);
    }

    private function getDocumentContent(LeadDocument $document): ?string
    {
        $path = ltrim((string) $document->arquivo_url, '/');

        foreach (['local', 'public'] as $disk) {
            $storage = Storage::disk($disk);
            if ($path && $storage->exists($path)) {
                return $storage->get($path);
            }
        }

        if (Str::startsWith($document->arquivo_url, ['http://', 'https://'])) {
            $response = Http::timeout(10)->get($document->arquivo_url);
            if ($response->successful()) {
                return $response->body();
            }
        }

        return null;
    }
}
