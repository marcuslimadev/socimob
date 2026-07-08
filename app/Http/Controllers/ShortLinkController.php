<?php
namespace App\Http\Controllers;

use App\Models\LeadHandoffLink;
use App\Services\SmsShortLinkService;
use Illuminate\Http\Request;

class ShortLinkController extends Controller
{
    private SmsShortLinkService $smsShortLinkService;

    public function __construct(SmsShortLinkService $smsShortLinkService)
    {
        $this->smsShortLinkService = $smsShortLinkService;
    }

    /**
     * Redireciona para o WhatsApp usando o código curto
     */
    public function redirectWhatsApp(Request $request, string $code)
    {
        $tenant = app('tenant');
        if (!$tenant) {
            return response('Tenant não encontrado', 404);
        }

        $shortLink = $this->smsShortLinkService->resolveCode($tenant->id, $code);
        if (!$shortLink) {
            return response('Link inválido ou expirado', 404);
        }

        $this->smsShortLinkService->markUsed($shortLink, null, null);

        $leadName = null;
        if ($shortLink->lead_id) {
            $lead = \App\Models\Lead::find($shortLink->lead_id);
            $leadName = $lead?->nome ?: null;
        }

        $message = $leadName
            ? "Olá, sou {$leadName}. Código de atendimento: {$shortLink->code}"
            : "Olá! Código de atendimento: {$shortLink->code}";

        $redirectUrl = 'https://wa.me/' . $shortLink->whatsapp_number
            . '?text=' . urlencode($message);

        return response('', 302)->header('Location', $redirectUrl);
    }

    /**
     * Redireciona um link de handoff (notificação de lead do Chaves na Mão) para o
     * WhatsApp do cliente, com a saudação do corretor já preenchida.
     */
    public function redirectHandoff(Request $request, string $code)
    {
        $tenant = app('tenant');
        if (!$tenant) {
            return response('Tenant não encontrado', 404);
        }

        $link = LeadHandoffLink::where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->first();

        if (!$link) {
            return response('Link inválido ou expirado', 404);
        }

        if (!$link->used_at) {
            $link->update(['used_at' => now()]);
        }

        $redirectUrl = 'https://wa.me/' . $link->client_phone
            . '?text=' . urlencode($link->message);

        return response('', 302)->header('Location', $redirectUrl);
    }
}
