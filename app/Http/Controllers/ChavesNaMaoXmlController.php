<?php

namespace App\Http\Controllers;

use App\Services\ChavesNaMaoXmlService;
use Illuminate\Http\Request;

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
}
