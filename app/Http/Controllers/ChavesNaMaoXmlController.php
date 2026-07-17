<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\ChavesNaMaoXmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ChavesNaMaoXmlController extends Controller
{
    public function feed(Request $request, ChavesNaMaoXmlService $service)
    {
        $tenant = app('tenant');
        abort_unless($tenant, 404, 'Tenant não identificado.');

        $result = $service->generate($tenant, $request->getSchemeAndHttpHost());

        return response($result['xml'], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300')
            ->header('X-Chaves-Exported', (string) $result['exported'])
            ->header('X-Chaves-Rejected', (string) count($result['rejected']));
    }

    public function image(Request $request, int $property, string $version, int $position, ChavesNaMaoXmlService $service)
    {
        $tenant = app('tenant');
        abort_unless($tenant, 404, 'Tenant não identificado.');
        abort_unless(extension_loaded('gd'), 503, 'Conversão de imagens indisponível.');

        $item = Property::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('id', $property)
            ->where('active', true)
            ->where('exibir_imovel', true)
            ->whereNull('deleted_at')
            ->with('fotos')
            ->firstOrFail();

        $sources = $service->sourceImageUrls($item);
        abort_unless(isset($sources[$position]), 404, 'Imagem não encontrada.');

        $sourceUrl = $sources[$position];
        $expectedVersion = optional($item->updated_at)->format('YmdHis') ?: 'current';
        abort_unless(hash_equals($expectedVersion, $version), 404, 'Versão da imagem expirada.');

        $cacheDirectory = storage_path("app/chaves-na-mao-images/{$tenant->id}");
        $cachePath = $cacheDirectory . DIRECTORY_SEPARATOR . "{$item->id}-{$version}-{$position}.jpg";

        if (!File::exists($cachePath)) {
            $contents = $this->readImageContents($sourceUrl, $request);
            abort_if($contents === '' || strlen($contents) > 30 * 1024 * 1024, 422, 'Imagem inválida ou muito grande.');

            $sourceImage = @imagecreatefromstring($contents);
            abort_unless($sourceImage !== false, 422, 'Formato de imagem não conversível.');

            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);
            $jpeg = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($jpeg, 255, 255, 255);
            imagefill($jpeg, 0, 0, $white);
            imagecopy($jpeg, $sourceImage, 0, 0, 0, 0, $width, $height);

            File::ensureDirectoryExists($cacheDirectory);
            $saved = imagejpeg($jpeg, $cachePath, 90);
            imagedestroy($sourceImage);
            imagedestroy($jpeg);
            abort_unless($saved, 500, 'Não foi possível converter a imagem.');
        }

        return response()->file($cachePath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function readImageContents(string $sourceUrl, Request $request): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH) ?: '';
        $sourceHost = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
        if ($sourceHost === '' || $sourceHost === strtolower($request->getHost())) {
            $localPath = public_path(ltrim($path, '/'));
            if (File::isFile($localPath)) {
                return (string) File::get($localPath);
            }
        }

        $response = Http::timeout(30)->retry(2, 250)->get($sourceUrl);
        abort_unless($response->successful(), 502, 'Não foi possível obter a imagem original.');

        return $response->body();
    }
}
