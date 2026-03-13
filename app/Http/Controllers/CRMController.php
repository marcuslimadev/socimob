<?php
namespace App\Http\Controllers;

use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CRMController extends Controller
{
    private function normalizeStatus(?string $status): string
    {
        return match (mb_strtolower(trim((string) $status))) {
            '', 'novo', 'contato', 'interesse' => 'novo',
            'em_atendimento' => 'em_atendimento',
            'qualificado' => 'qualificado',
            'proposta', 'negociacao' => 'proposta',
            'fechado', 'convertido' => 'fechado',
            'perdido', 'descartado' => 'perdido',
            default => 'novo',
        };
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function normalizeOrigin(?string $origin, Lead $lead): string
    {
        $rawOrigin = trim((string) $origin);
        $normalizedOrigin = mb_strtolower($rawOrigin);
        $normalizedOrigin = str_replace(['_', '-'], ' ', $normalizedOrigin);
        $normalizedOrigin = preg_replace('/\s+/', ' ', $normalizedOrigin ?? '') ?? '';
        $normalizedOrigin = trim($normalizedOrigin);

        $isChavesLead = $lead->isFromIntegration() || !empty($lead->chaves_na_mao_status) || !empty($lead->chaves_na_mao_sent_at);

        if ($isChavesLead) {
            return 'Chaves na Mão';
        }

        if ($normalizedOrigin !== '') {
            if (in_array($normalizedOrigin, ['chaves na mao', 'chaves na mão'], true)) {
                return 'Chaves na Mão';
            }

            if (in_array($normalizedOrigin, ['whatsapp'], true)) {
                return 'WhatsApp';
            }

            if (in_array($normalizedOrigin, ['sms'], true)) {
                return 'SMS';
            }

            if (
                in_array($normalizedOrigin, ['site', 'form', 'formulario', 'formulário', 'portal', 'manual', 'crm', 'lead crm', 'outro'], true)
                || str_contains($normalizedOrigin, 'form')
                || str_contains($normalizedOrigin, 'site')
                || str_contains($normalizedOrigin, 'portal')
                || str_contains($normalizedOrigin, 'crm')
            ) {
                return 'Site';
            }

            return $rawOrigin;
        }

        return 'Site';
    }

    private function normalizeObservationText(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $separatorPosition = mb_stripos($text, '--- Atualização de Lead ---');
        if ($separatorPosition !== false && $separatorPosition > 0) {
            $text = mb_substr($text, 0, $separatorPosition);
        }

        return trim($text);
    }

    private function normalizeLooseText(?string $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractLeadPropertyContext(Lead $lead): array
    {
        $sources = array_values(array_filter([
            $this->normalizeObservationText($lead->observacoes_cliente),
            $this->normalizeObservationText($lead->observacoes),
        ]));

        $context = [
            'portal_link' => null,
            'title' => null,
            'code' => null,
            'location' => null,
            'city' => null,
            'neighborhood' => null,
            'value' => null,
        ];

        foreach ($sources as $source) {
            $compactSource = preg_replace('/\s+/', ' ', $source) ?? $source;

            if ($context['portal_link'] === null && preg_match('/https?:\/\/[^\s]+\/portal\/imovel\/(\d+)/i', $compactSource, $matches)) {
                $context['portal_link'] = $matches[0];
            }

            if ($context['title'] === null && preg_match('/interesse no im[oó]vel\s+["“]?([^"”.]+?)?["”]?(?:\.|,| Localiza[cç][aã]o:| Valor:| Valor anunciado:| Origem:|$)/iu', $compactSource, $matches)) {
                $context['title'] = trim((string) ($matches[1] ?? '')) ?: null;
            }

            if ($context['code'] === null && preg_match('/refer[êe]ncia(?: do im[oó]vel)?\s*:\s*([^\.]+)/iu', $compactSource, $matches)) {
                $context['code'] = trim((string) ($matches[1] ?? '')) ?: null;
            }

            if ($context['location'] === null && preg_match('/localiza[cç][aã]o\s*:\s*([^\.]+?)(?:\.\s|\.$|\svalor\s*:|\svalor anunciado\s*:|\sorigem\s*:|$)/iu', $compactSource, $matches)) {
                $context['location'] = trim((string) ($matches[1] ?? '')) ?: null;
                if ($context['location']) {
                    $parts = array_map('trim', explode(',', $context['location']));
                    $context['neighborhood'] = $parts[0] ?? null;
                    if (isset($parts[1])) {
                        $cityAndState = preg_split('/\//', $parts[1]);
                        $context['city'] = trim((string) ($cityAndState[0] ?? '')) ?: null;
                    }
                }
            }

            if ($context['value'] === null && preg_match('/valor(?: anunciado)?\s*:\s*R\$\s*([0-9\.\,]+)/iu', $compactSource, $matches)) {
                $rawValue = str_replace(['.', ','], ['', '.'], (string) $matches[1]);
                $context['value'] = is_numeric($rawValue) ? (float) $rawValue : null;
            }
        }

        return $context;
    }

    private function resolveLeadProperty(Lead $lead): ?array
    {
        static $cache = [];

        $context = $this->extractLeadPropertyContext($lead);
        $cacheKey = md5(json_encode([$lead->tenant_id, $context], JSON_UNESCAPED_UNICODE));

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if ($context['portal_link'] && preg_match('/\/portal\/imovel\/(\d+)/', $context['portal_link'], $matches)) {
            $property = Property::query()
                ->where('id', (int) $matches[1])
                ->where(function ($query) use ($lead) {
                    $query->where('tenant_id', $lead->tenant_id)
                        ->orWhereNull('tenant_id');
                })
                ->first();

            if ($property) {
                return $cache[$cacheKey] = [
                    'id' => $property->id,
                    'title' => $property->titulo,
                    'code' => $property->codigo ?: $property->referencia_imovel ?: $property->codigo_imovel,
                    'location' => trim(implode(', ', array_filter([$property->bairro, $property->cidade]))),
                    'value' => $property->valor_venda ?? $property->preco,
                    'link' => "/portal/imovel/{$property->id}",
                ];
            }
        }

        if (empty($context['title']) && empty($context['code'])) {
            return $cache[$cacheKey] = null;
        }

        $query = Property::query()
            ->where(function ($builder) use ($lead) {
                $builder->where('tenant_id', $lead->tenant_id)
                    ->orWhereNull('tenant_id');
            })
            ->where(function ($builder) use ($context) {
                if (!empty($context['code'])) {
                    $builder->orWhere('codigo', 'like', '%' . $context['code'] . '%')
                        ->orWhere('referencia_imovel', 'like', '%' . $context['code'] . '%')
                        ->orWhere('codigo_imovel', 'like', '%' . $context['code'] . '%');
                }

                if (!empty($context['title'])) {
                    $builder->orWhere('titulo', 'like', '%' . $context['title'] . '%');
                }
            })
            ->limit(25)
            ->get(['id', 'titulo', 'codigo', 'referencia_imovel', 'codigo_imovel', 'bairro', 'cidade', 'valor_venda', 'preco']);

        if ($query->isEmpty()) {
            return $cache[$cacheKey] = null;
        }

        $normalizedTitle = $this->normalizeLooseText($context['title']);
        $normalizedNeighborhood = $this->normalizeLooseText($context['neighborhood']);
        $normalizedCity = $this->normalizeLooseText($context['city']);
        $expectedValue = $context['value'];

        $bestProperty = $query
            ->map(function (Property $property) use ($normalizedTitle, $normalizedNeighborhood, $normalizedCity, $expectedValue, $context) {
                $score = 0;
                $propertyTitle = $this->normalizeLooseText($property->titulo);
                $propertyNeighborhood = $this->normalizeLooseText($property->bairro);
                $propertyCity = $this->normalizeLooseText($property->cidade);
                $propertyCode = trim((string) ($property->codigo ?: $property->referencia_imovel ?: $property->codigo_imovel));
                $propertyValue = $property->valor_venda ?? $property->preco;

                if (!empty($context['code']) && $propertyCode !== '' && str_contains($propertyCode, (string) $context['code'])) {
                    $score += 120;
                }

                if ($normalizedTitle !== '') {
                    if ($propertyTitle === $normalizedTitle) {
                        $score += 100;
                    } elseif (str_contains($propertyTitle, $normalizedTitle) || str_contains($normalizedTitle, $propertyTitle)) {
                        $score += 70;
                    }
                }

                if ($normalizedNeighborhood !== '' && $propertyNeighborhood !== '' && str_contains($propertyNeighborhood, $normalizedNeighborhood)) {
                    $score += 20;
                }

                if ($normalizedCity !== '' && $propertyCity !== '' && str_contains($propertyCity, $normalizedCity)) {
                    $score += 20;
                }

                if ($expectedValue !== null && $propertyValue !== null) {
                    $delta = abs((float) $propertyValue - (float) $expectedValue);
                    if ($delta < 1) {
                        $score += 80;
                    } elseif ($expectedValue > 0 && ($delta / $expectedValue) <= 0.03) {
                        $score += 40;
                    }
                }

                return [
                    'score' => $score,
                    'property' => $property,
                ];
            })
            ->sortByDesc('score')
            ->first();

        if (!$bestProperty || ($bestProperty['score'] ?? 0) < 60) {
            return $cache[$cacheKey] = null;
        }

        /** @var Property $property */
        $property = $bestProperty['property'];

        return $cache[$cacheKey] = [
            'id' => $property->id,
            'title' => $property->titulo,
            'code' => $property->codigo ?: $property->referencia_imovel ?: $property->codigo_imovel,
            'location' => trim(implode(', ', array_filter([$property->bairro, $property->cidade]))),
            'value' => $property->valor_venda ?? $property->preco,
            'link' => "/portal/imovel/{$property->id}",
        ];
    }

    private function mapLead(Lead $lead, ?object $lastMsg, int $unread, ?int $conversaId): array
    {
        $property = $this->resolveLeadProperty($lead);

        return [
            'id' => $lead->id,
            'pessoa_id' => $lead->pessoa_id,
            'nome' => $this->firstFilled([
                $lead->nome,
                $lead->whatsapp_name ?? null,
                $lead->pessoa?->nome ?? null,
            ]) ?? 'Lead sem nome',
            'telefone' => $this->firstFilled([
                $lead->telefone,
                $lead->whatsapp ?? null,
                $lead->pessoa?->celular ?? null,
                $lead->pessoa?->telefone ?? null,
            ]) ?? '',
            'email' => $this->firstFilled([
                $lead->email,
                $lead->pessoa?->email ?? null,
            ]),
            'status' => $this->normalizeStatus($lead->status),
            'classificacao' => $lead->classificacao,
            'observacoes' => $lead->observacoes ?: ($lead->pessoa?->observacoes ?? null),
            'observacoes_cliente' => $lead->observacoes_cliente,
            'valor' => $lead->budget_max ?? $lead->budget_min,
            'corretor_id' => $lead->corretor_id,
            'corretor_nome' => $lead->corretor?->name,
            'pessoa' => $lead->pessoa,
            'conversa_id' => $conversaId,
            'ultima_mensagem' => $lastMsg?->content,
            'ultima_mensagem_at' => $lastMsg?->created_at,
            'unread' => (int) $unread,
            'origem' => $this->normalizeOrigin($this->firstFilled([
                $lead->origem ?? null,
                $lead->fonte ?? null,
                $lead->pessoa?->origem ?? null,
            ]), $lead),
            'sms_enviado' => (bool) $lead->sms_enviado,
            'property_id' => $property['id'] ?? null,
            'property_title' => $property['title'] ?? null,
            'property_code' => $property['code'] ?? null,
            'property_location' => $property['location'] ?? null,
            'property_value' => $property['value'] ?? null,
            'property_link' => $property['link'] ?? null,
            'updated_at' => $lead->updated_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }

    /**
     * Listar clientes do CRM agrupados por status
     * GET /api/crm/clientes
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $user = $request->user();

            $query = Lead::with(['pessoa:id,nome,tipo,cpf,email,telefone,celular,observacoes,origem', 'corretor:id,name'])
                ->where('tenant_id', $tenantId);

            // Permissoes: corretor ve so os seus + livres
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                $query->where(function ($q) use ($user) {
                    $q->where('corretor_id', $user->id)
                      ->orWhereNull('corretor_id');
                });
            }

            // Filtros
            if ($request->search) {
                $s = $request->search;
                $query->where(function ($q) use ($s) {
                    $q->where('nome', 'like', "%{$s}%")
                      ->orWhere('telefone', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%");
                });
            }
            if ($request->corretor_id) {
                $query->where('corretor_id', $request->corretor_id);
            }
            if ($request->classificacao) {
                $query->where('classificacao', $request->classificacao);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }

            $sortBy = $request->get('sort_by', 'updated_at');
            $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSort = ['updated_at', 'nome', 'status'];
            if (!in_array($sortBy, $allowedSort, true)) {
                $sortBy = 'updated_at';
            }

            if ($request->boolean('flat')) {
                $perPage = (int) $request->get('per_page', 50);
                $perPage = max(10, min(200, $perPage));

                $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);
                $leads = collect($paginator->items());

                $leadIds = $leads->pluck('id');
                $conversas = Conversa::whereIn('lead_id', $leadIds)
                    ->orderBy('ultima_atividade', 'desc')
                    ->get()
                    ->groupBy('lead_id');

                $conversaIds = $conversas->flatten()->pluck('id');

                $lastMessages = collect();
                if ($conversaIds->isNotEmpty()) {
                    $lastMessages = DB::table('mensagens')
                        ->whereIn('conversa_id', $conversaIds)
                        ->whereIn('id', function ($q) use ($conversaIds) {
                            $q->selectRaw('MAX(id)')
                              ->from('mensagens')
                              ->whereIn('conversa_id', $conversaIds)
                              ->groupBy('conversa_id');
                        })
                        ->get()
                        ->keyBy('conversa_id');
                }

                $unreadCounts = collect();
                if ($conversaIds->isNotEmpty()) {
                    $unreadCounts = DB::table('mensagens')
                        ->whereIn('conversa_id', $conversaIds)
                        ->where('direction', 'incoming')
                        ->where(function ($q) {
                            $q->whereNull('read_at')
                              ->orWhere('read_at', '');
                        })
                        ->selectRaw('conversa_id, count(*) as count')
                        ->groupBy('conversa_id')
                        ->pluck('count', 'conversa_id');
                }

                $result = $leads->map(function ($lead) use ($conversas, $lastMessages, $unreadCounts) {
                    $conversa = $conversas->get($lead->id)?->first();
                    $conversaId = $conversa?->id;
                    $lastMsg = $conversaId ? $lastMessages->get($conversaId) : null;
                    $unread = $conversaId ? ($unreadCounts[$conversaId] ?? 0) : 0;
                    return $this->mapLead($lead, $lastMsg, (int) $unread, $conversaId);
                });

                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ]);
            }

            $leads = $query->orderBy('updated_at', 'desc')->get();

            // Batch load conversas + last message + unread count
            $leadIds = $leads->pluck('id');

            $conversas = Conversa::whereIn('lead_id', $leadIds)
                ->orderBy('ultima_atividade', 'desc')
                ->get()
                ->groupBy('lead_id');

            $conversaIds = $conversas->flatten()->pluck('id');

            // Last message per conversa
            $lastMessages = collect();
            if ($conversaIds->isNotEmpty()) {
                $lastMessages = DB::table('mensagens')
                    ->whereIn('conversa_id', $conversaIds)
                    ->whereIn('id', function ($q) use ($conversaIds) {
                        $q->selectRaw('MAX(id)')
                          ->from('mensagens')
                          ->whereIn('conversa_id', $conversaIds)
                          ->groupBy('conversa_id');
                    })
                    ->get()
                    ->keyBy('conversa_id');
            }

            // Unread counts
            $unreadCounts = collect();
            if ($conversaIds->isNotEmpty()) {
                $unreadCounts = DB::table('mensagens')
                    ->whereIn('conversa_id', $conversaIds)
                    ->where('direction', 'incoming')
                    ->where(function ($q) {
                        $q->whereNull('read_at')
                          ->orWhere('read_at', '');
                    })
                    ->selectRaw('conversa_id, count(*) as count')
                    ->groupBy('conversa_id')
                    ->pluck('count', 'conversa_id');
            }

            // Map results
            $result = $leads->map(function ($lead) use ($conversas, $lastMessages, $unreadCounts) {
                $conversa = $conversas->get($lead->id)?->first();
                $conversaId = $conversa?->id;
                $lastMsg = $conversaId ? $lastMessages->get($conversaId) : null;
                $unread = $conversaId ? ($unreadCounts[$conversaId] ?? 0) : 0;

                return $this->mapLead($lead, $lastMsg, (int) $unread, $conversaId);
            });

            // Agrupar por status
            $statuses = ['novo', 'em_atendimento', 'qualificado', 'proposta', 'fechado', 'perdido'];
            $grouped = [];
            foreach ($statuses as $status) {
                $grouped[$status] = $result->where('status', $status)->values();
            }

            return response()->json([
                'success' => true,
                'data' => $grouped,
                'total' => $result->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('[CRM] Erro ao listar clientes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar clientes',
            ], 500);
        }
    }

    /**
     * Atualizar status do lead (drag-and-drop ou botao)
     * PATCH /api/crm/clientes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id') ?? $request->user()?->tenant_id;

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:novo,em_atendimento,qualificado,proposta,fechado,perdido',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Status inválido',
                    'messages' => $validator->errors(),
                ], 422);
            }

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }
            $lead = Lead::where('tenant_id', $tenantId)->findOrFail($id);
            $lead->status = $request->status;
            $lead->updated_at = now();
            $lead->save();

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cliente não encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('[CRM] Erro ao atualizar status', [
                'lead_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status',
            ], 500);
        }
    }
}
