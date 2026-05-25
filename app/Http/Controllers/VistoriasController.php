<?php
namespace App\Http\Controllers;

use App\Models\ContratoLocacao;
use App\Models\Pessoa;
use App\Models\Property;
use App\Models\User;
use App\Models\Vistoria;
use App\Models\VistoriaSolicitacao;
use App\Services\VistoriaPdfService;
use App\Services\VistoriaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VistoriasController extends Controller
{
    public function meta(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $pessoas = Pessoa::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $query) {
                $query->whereNull('ativo')->orWhere('ativo', true);
            })
            ->orderBy('nome')
            ->limit(400)
            ->get(['id', 'nome', 'email', 'telefone', 'celular', 'papeis']);

        $imoveis = Property::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('titulo')
            ->limit(400)
            ->get([
                'id', 'codigo', 'titulo', 'tipo_imovel', 'finalidade_imovel',
                'logradouro', 'bairro', 'cidade', 'estado', 'area_total', 'dormitorios', 'banheiros', 'garagem',
            ]);

        $contratos = ContratoLocacao::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total,dormitorios,banheiros,garagem',
                'locador:id,nome,email,telefone,celular',
                'locatario:id,nome,email,telefone,celular',
            ])
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json([
            'pessoas' => $pessoas,
            'imoveis' => $imoveis,
            'contratos' => $contratos->map(fn (ContratoLocacao $contrato) => $this->serializeContrato($contrato))->values(),
        ]);
    }

    public function index(Request $request)
    {
        $query = $this->applyFilters(
            Vistoria::query()->with($this->detailRelations()),
            $request
        );

        $perPage = (int) $request->query('per_page', 15);

        $vistorias = $query->orderByRaw('data_vistoria is null, data_vistoria desc')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $vistorias->setCollection(
            $vistorias->getCollection()->map(fn (Vistoria $vistoria) => $this->serializeVistoria($vistoria))
        );

        return response()->json($vistorias);
    }

    public function show(Request $request, $id)
    {
        $query = $this->applyTenantScope(Vistoria::query()->with($this->detailRelations()), $request);
        $vistoria = $query->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        return response()->json($this->serializeVistoria($vistoria));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(
            Vistoria::query()->with($this->detailRelations()),
            $request
        )->orderByRaw('data_vistoria is null, data_vistoria desc')
         ->orderByDesc('created_at');

        $filename = 'vistorias_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'codigo',
                'status',
                'tipo',
                'contrato',
                'imovel',
                'cliente_nome',
                'responsavel',
                'participantes',
                'metragem',
                'mobiliado',
                'data_vistoria',
                'observacoes',
                'created_at',
            ]);

            $query->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $vistoria) {
                    $payload = $this->serializeVistoria($vistoria);

                    fputcsv($handle, [
                        $payload['id'],
                        $payload['codigo'],
                        $payload['status'],
                        $payload['tipo'],
                        $payload['contrato']['numero_contrato'] ?? '',
                        $payload['imovel']['label'] ?? '',
                        $payload['cliente_nome'],
                        $payload['responsavel']['nome'] ?? '',
                        implode(', ', $payload['participantes_nomes'] ?? []),
                        $payload['metragem'],
                        $payload['mobiliado'] ? 'sim' : 'nao',
                        $payload['data_vistoria'] ?? '',
                        $payload['observacoes'] ?? '',
                        $payload['created_at'] ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, false);
        $tenantId = $this->resolveTenantId($request);

        $payload = $this->preparePayload($validated, $tenantId);
        $payload['tenant_id'] = $tenantId;

        if (empty($payload['codigo'])) {
            $payload['codigo'] = 'VST-' . now()->format('Ymd-His');
        }

        $payload = array_merge($payload, $this->laudoPayload($validated, $request));
        $payload['link_publico_midias_token'] = $payload['link_publico_midias_token'] ?? app(VistoriaService::class)->token();
        $payload['link_contestacao_token'] = $payload['link_contestacao_token'] ?? app(VistoriaService::class)->token();
        $payload['criado_por'] = $request->user()?->id;
        $payload['atualizado_por'] = $request->user()?->id;

        $vistoria = Vistoria::create($payload)->load($this->detailRelations());
        app(VistoriaService::class)->registrarHistorico($vistoria, 'vistoria_criada', 'Vistoria criada.', $request);

        return response()->json([
            'success' => true,
            'message' => 'Vistoria criada com sucesso',
            'vistoria' => $this->serializeVistoria($vistoria),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query()->with($this->detailRelations()), $request)->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        $validated = $this->validatePayload($request, true);
        $payload = $this->preparePayload($validated, $this->resolveTenantId($request), $vistoria);

        $payload = array_merge($payload, $this->laudoPayload($validated, $request));
        $payload['atualizado_por'] = $request->user()?->id;

        $vistoria->update($payload);
        $vistoria->load($this->detailRelations());

        return response()->json([
            'success' => true,
            'message' => 'Vistoria atualizada com sucesso',
            'vistoria' => $this->serializeVistoria($vistoria),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array((string) ($user->role ?? ''), ['admin', 'super_admin'], true)) {
            return response()->json(['error' => 'Forbidden', 'message' => 'Somente administradores podem excluir vistorias.'], 403);
        }

        $vistoria = $this->applyTenantScope(Vistoria::query(), $request)->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        $vistoria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vistoria deletada com sucesso',
        ]);
    }

    public function iniciar(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query(), $request)->find($id);
        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        return response()->json([
            'success' => true,
            'vistoria' => $this->serializeVistoria(app(VistoriaService::class)->iniciar($vistoria, $request)),
        ]);
    }

    public function finalizar(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query(), $request)->find($id);
        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        return response()->json([
            'success' => true,
            'vistoria' => $this->serializeVistoria(app(VistoriaService::class)->finalizar($vistoria, $request)),
        ]);
    }

    public function cancelar(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query(), $request)->find($id);
        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        return response()->json([
            'success' => true,
            'vistoria' => $this->serializeVistoria(app(VistoriaService::class)->cancelar($vistoria, $request)),
        ]);
    }

    public function gerarPdf(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query()->with($this->detailRelations()), $request)->find($id);
        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        $vistoria = app(VistoriaPdfService::class)->gerar($vistoria, $request);

        return response()->json([
            'success' => true,
            'message' => 'PDF gerado com sucesso',
            'vistoria' => $this->serializeVistoria($vistoria),
        ]);
    }

    public function downloadPdf(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query(), $request)->find($id);
        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        if ($this->deveRegenerarPdf($vistoria)) {
            $vistoria = app(VistoriaPdfService::class)->gerar($vistoria->load($this->detailRelations()), $request);
        }

        if (!$vistoria->pdf_path || !Storage::disk('public')->exists($vistoria->pdf_path)) {
            return response()->json(['error' => 'PDF not found'], 404);
        }

        return Storage::disk('public')->download($vistoria->pdf_path, ($vistoria->codigo ?: 'vistoria') . '.pdf');
    }

    private function deveRegenerarPdf(Vistoria $vistoria): bool
    {
        if (!$vistoria->pdf_path || !Storage::disk('public')->exists($vistoria->pdf_path)) {
            return false;
        }

        if (blank($vistoria->link_publico_midias_token) || blank($vistoria->link_contestacao_token)) {
            return true;
        }

        try {
            $conteudo = Storage::disk('public')->get($vistoria->pdf_path);
            return str_contains($conteudo, '/vistorias/publico//midias')
                || str_contains($conteudo, '/vistorias/publico//contestacao')
                || str_contains($conteudo, '/vistorias/publico//pdf')
                || str_contains($conteudo, 'https://exclusivalarimoveis.com/vistorias/publico/')
                || str_contains($conteudo, 'http://exclusivalarimoveis.com/vistorias/publico/');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cadastra participante avulso no fluxo da vistoria.
     * Pode criar usuário "vistoriador" já com senha padrão e troca obrigatória no primeiro acesso.
     * POST /api/vistorias/participantes
     */
    public function storeParticipante(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $validator = app('validator')->make($request->all(), [
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:50',
            'celular' => 'nullable|string|max:50',
            'tipo_pessoa' => 'nullable|string|in:fisica,juridica',
            'create_vistoriador_user' => 'nullable|boolean',
            'user_email' => 'nullable|email|max:255',
            'user_name' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $pessoa = Pessoa::query()->create([
            'tenant_id' => $tenantId,
            'nome' => $data['nome'],
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'celular' => $data['celular'] ?? null,
            'tipo' => $data['tipo_pessoa'] ?? 'fisica',
            'ativo' => true,
            'papeis' => ['vistoriador'],
        ]);

        $userPayload = null;
        $createUser = filter_var($data['create_vistoriador_user'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($createUser) {
            $email = $data['user_email'] ?? $data['email'] ?? null;
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Para criar login do vistoriador, informe um email.',
                ], 422);
            }

            $exists = User::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'message' => 'Já existe usuário com este email no tenant.'], 422);
            }

            $defaultPassword = env('DEFAULT_VISTORIADOR_PASSWORD', 'exclusiva123');
            $user = User::query()->create([
                'tenant_id' => $tenantId,
                'pessoa_id' => $pessoa->id,
                'name' => $data['user_name'] ?? $pessoa->nome,
                'email' => $email,
                'password' => Hash::make($defaultPassword),
                'role' => 'corretor',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $userPayload = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'default_password' => $defaultPassword,
                'must_change_password' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'pessoa' => [
                'id' => $pessoa->id,
                'nome' => $pessoa->nome,
                'email' => $pessoa->email,
                'telefone' => $pessoa->telefone,
                'celular' => $pessoa->celular,
            ],
            'user' => $userPayload,
        ], 201);
    }

    /**
     * Converte uma solicitação de vistoria em registro operacional (vistoria).
     * POST /api/vistorias/solicitacoes/{solicitacaoId}/converter
     */
    public function converterFromSolicitacao(Request $request, int $solicitacaoId)
    {
        $tenantId = $this->resolveTenantId($request);

        $solicitacao = VistoriaSolicitacao::where('tenant_id', $tenantId)->find($solicitacaoId);
        if (!$solicitacao) {
            return response()->json(['success' => false, 'error' => 'Solicitação não encontrada'], 404);
        }

        if ($solicitacao->status === 'cancelada') {
            return response()->json(['success' => false, 'message' => 'Solicitação cancelada não pode gerar vistoria.'], 422);
        }

        if ($solicitacao->vistoria_id) {
            $vistoria = $this->applyTenantScope(Vistoria::query()->with($this->detailRelations()), $request)
                ->find($solicitacao->vistoria_id);

            return response()->json([
                'success' => true,
                'already_converted' => true,
                'vistoria' => $vistoria ? $this->serializeVistoria($vistoria) : null,
                'vistoria_id' => $solicitacao->vistoria_id,
                'solicitacao_id' => $solicitacao->id,
            ]);
        }

        try {
            $response = DB::transaction(function () use ($solicitacao, $tenantId) {
                $imovelId = null;
                if ($solicitacao->imovel_id) {
                    $exists = Property::query()
                        ->where('tenant_id', $tenantId)
                        ->whereKey((int) $solicitacao->imovel_id)
                        ->exists();
                    $imovelId = $exists ? (int) $solicitacao->imovel_id : null;
                }

                $participantesIds = $this->participantesIdsFromSolicitacao($solicitacao, $tenantId);
                $tipo = $this->normalizeTipoFromSolicitacao((string) $solicitacao->tipo);

                $labelSol = trim((string) ($solicitacao->codigo ?? ''));
                $refPrefix = '[Origem: solicitação ' . ($labelSol !== '' ? $labelSol : '#' . $solicitacao->id) . ']';
                $obsOriginal = trim((string) ($solicitacao->observacoes ?? ''));

                $imovelLivre = null;
                if (!$imovelId) {
                    $livreRef = $obsOriginal !== '' ? $obsOriginal : 'Referência pendente — preencher no detalhe da vistoria.';
                    $livreTitulo = $labelSol !== '' ? 'Solicitação ' . $labelSol : 'Solicitação #' . $solicitacao->id;
                    $imovelLivre = ['titulo' => $livreTitulo, 'referencia' => $livreRef];
                }

                $observacoes = $obsOriginal !== ''
                    ? $refPrefix . "\n\n" . $obsOriginal
                    : $refPrefix;

                $vistoriaStatus = $solicitacao->status === 'solicitada' ? 'designada' : $solicitacao->status;
                if (!in_array($vistoriaStatus, ['solicitada', 'designada', 'andamento', 'concluida', 'cancelada'], true)) {
                    $vistoriaStatus = 'designada';
                }

                $validated = [
                    'codigo' => null,
                    'status' => $vistoriaStatus,
                    'cliente_nome' => $solicitacao->cliente_nome,
                    'contrato_id' => null,
                    'imovel_id' => $imovelId,
                    'imovel_livre' => $imovelLivre,
                    'responsavel_pessoa_id' => null,
                    'tipo' => $tipo,
                    'vistoriadores' => null,
                    'participantes_ids' => $participantesIds,
                    'metragem' => null,
                    'mobiliado' => false,
                    'data_vistoria' => null,
                    'observacoes' => $observacoes,
                    'comodos' => null,
                    'assinatura_inquilino_status' => 'pendente',
                    'assinatura_proprietario_status' => 'pendente',
                ];

                $payload = $this->preparePayload($validated, $tenantId);

                $stringPessoas = $this->pessoaNomesStringFromSolicitacao($solicitacao);
                if ($stringPessoas !== [] && empty($payload['participantes_ids'])) {
                    $existing = $payload['pessoas'] ?? [];
                    if (!is_array($existing) || $existing === []) {
                        $payload['pessoas'] = $stringPessoas;
                    }
                }

                $payload['tenant_id'] = $tenantId;

                $codigo = 'VST-' . now()->format('Ymd-His');
                if ($labelSol !== '') {
                    $slug = preg_replace('/[^a-zA-Z0-9\-]+/', '-', $labelSol);
                    $slug = trim((string) $slug, '-');
                    if ($slug !== '') {
                        $codigo .= '-' . mb_substr($slug, 0, 32);
                    }
                }
                $payload['codigo'] = $codigo;

                $vistoria = Vistoria::create($payload)->load($this->detailRelations());

                $historico = $solicitacao->historico ?? [];
                $historico[] = [
                    'evento' => 'convertida_em_vistoria',
                    'descricao' => "Vistoria operacional #{$vistoria->id} ({$vistoria->codigo})",
                    'data' => now()->toDateTimeString(),
                ];
                $solicitacao->vistoria_id = $vistoria->id;
                $solicitacao->status = $vistoriaStatus;
                $solicitacao->historico = $historico;
                $solicitacao->save();

                return response()->json([
                    'success' => true,
                    'already_converted' => false,
                    'message' => 'Solicitação convertida em vistoria.',
                    'vistoria' => $this->serializeVistoria($vistoria),
                    'vistoria_id' => $vistoria->id,
                    'solicitacao_id' => $solicitacao->id,
                    'solicitacao' => $solicitacao->fresh(),
                ], 201);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Não foi possível converter esta solicitação.',
            ], 422);
        }

        return $response;
    }

    /** @return list<int> */
    private function participantesIdsFromSolicitacao(VistoriaSolicitacao $solicitacao, int $tenantId): array
    {
        $ids = [];
        foreach ($solicitacao->pessoas ?? [] as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $ids[] = (int) $item;
                continue;
            }
            if (is_array($item) && isset($item['id']) && (is_int($item['id']) || ctype_digit((string) $item['id']))) {
                $ids[] = (int) $item['id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return Pessoa::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function pessoaNomesStringFromSolicitacao(VistoriaSolicitacao $solicitacao): array
    {
        $out = [];
        foreach ($solicitacao->pessoas ?? [] as $item) {
            if (is_string($item)) {
                $t = trim($item);
                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }

    private function normalizeTipoFromSolicitacao(string $raw): string
    {
        $t = mb_strtolower(trim($raw));
        if ($t === 'entrada' || str_contains($t, 'entrada')) {
            return 'entrada';
        }
        if ($t === 'saida' || $t === 'saída' || str_contains($t, 'saida') || str_contains($t, 'saída')) {
            return 'saida';
        }
        if ($t === 'periodica' || $t === 'periódica' || str_contains($t, 'periodica')) {
            return 'periodica';
        }

        return 'periodica';
    }

    private function validatePayload(Request $request, bool $partial): array
    {
        $rules = [
            'codigo' => 'nullable|string|max:50',
            'status' => [$partial ? 'sometimes' : 'required', 'string', 'in:solicitada,designada,andamento,concluida,cancelada,rascunho,agendada,em_andamento,aguardando_assinatura,finalizada,contestada,revisada'],
            'cliente_nome' => 'nullable|string|max:255',
            'contrato_id' => 'nullable|integer|exists:contratos_locacao,id',
            'imovel_id' => 'nullable|integer|exists:imo_properties,id',
            'imovel_livre' => 'nullable|array',
            'imovel_livre.titulo' => 'nullable|string|max:255',
            'imovel_livre.logradouro' => 'nullable|string|max:500',
            'imovel_livre.bairro' => 'nullable|string|max:120',
            'imovel_livre.cidade' => 'nullable|string|max:120',
            'imovel_livre.estado' => 'nullable|string|max:2',
            'imovel_livre.tipo_imovel' => 'nullable|string|max:120',
            'imovel_livre.referencia' => 'nullable|string|max:1000',
            'responsavel_pessoa_id' => 'nullable|integer|exists:pessoas,id',
            'tipo' => [$partial ? 'sometimes' : 'required', 'string', 'in:entrada,saida,periodica,conferencia,manutencao,avulsa'],
            'vistoriadores' => 'nullable|array',
            'vistoriadores.*' => 'nullable|string|max:120',
            'participantes_ids' => 'nullable|array',
            'participantes_ids.*' => 'integer|exists:pessoas,id',
            'metragem' => 'nullable|numeric|min:0',
            'mobiliado' => 'nullable|boolean',
            'data_vistoria' => 'nullable|date',
            'observacoes' => 'nullable|string',
            'comodos' => 'nullable|array',
            'assinatura_inquilino_status' => 'nullable|string|max:30',
            'assinatura_proprietario_status' => 'nullable|string|max:30',
            'data_agendada' => 'nullable|date',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date',
            'vistoriador_id' => 'nullable|integer|exists:pessoas,id',
            'observacoes_gerais' => 'nullable|string',
            'introducao_texto' => 'nullable|string',
            'criterios_avaliacao_json' => 'nullable|array',
            'criterios_pintura_json' => 'nullable|array',
            'criterios_limpeza_json' => 'nullable|array',
            'prazo_contestacao_dias' => 'nullable|integer|min:1|max:90',
            'data_limite_contestacao' => 'nullable|date',
        ];

        $validator = app('validator')->make($request->all(), $rules);

        if (!$partial) {
            $validator->after(function (\Illuminate\Contracts\Validation\Validator $v): void {
                $data = $v->getData();
                $hasContrato = !empty($data['contrato_id']);
                $hasImovelCadastro = !empty($data['imovel_id']);
                $hasManual = $this->imovelLivreTemConteudo($data['imovel_livre'] ?? null);

                if (!$hasContrato && !$hasImovelCadastro && !$hasManual) {
                    $v->errors()->add(
                        'imovel_id',
                        'Informe um contrato, um imóvel do cadastro ou os dados do local na vistoria (modo livre).'
                    );
                }
            });
        }

        return $validator->validate();
    }

    private function preparePayload(array $validated, int $tenantId, ?Vistoria $current = null): array
    {
        $contratoId = $validated['contrato_id'] ?? $current?->contrato_id;
        $contrato = null;

        if ($contratoId) {
            $contrato = ContratoLocacao::query()
                ->where('tenant_id', $tenantId)
                ->with(['imovel', 'locador', 'locatario'])
                ->findOrFail($contratoId);

            $validated['contrato_id'] = $contrato->id;
            $validated['imovel_id'] = $contrato->imovel_id ?: ($validated['imovel_id'] ?? null);
            $validated['cliente_nome'] = $validated['cliente_nome']
                ?? $contrato->locatario?->nome
                ?? $contrato->locador?->nome
                ?? $current?->cliente_nome;

            if (!array_key_exists('participantes_ids', $validated) || empty($validated['participantes_ids'])) {
                $validated['participantes_ids'] = array_values(array_filter([
                    $contrato->locador_pessoa_id,
                    $contrato->locatario_pessoa_id,
                ]));
            }

            if (empty($validated['metragem']) && $contrato->imovel?->area_total) {
                $validated['metragem'] = $contrato->imovel->area_total;
            }
        } elseif (array_key_exists('contrato_id', $validated) && empty($validated['contrato_id'])) {
            $validated['contrato_id'] = null;
        }

        if (array_key_exists('responsavel_pessoa_id', $validated) && empty($validated['responsavel_pessoa_id'])) {
            $validated['responsavel_pessoa_id'] = null;
        }

        if (array_key_exists('imovel_id', $validated) && empty($validated['imovel_id'])) {
            $validated['imovel_id'] = null;
        }

        $participantesIds = collect($validated['participantes_ids'] ?? $current?->participantes_ids ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $participantes = $participantesIds->isEmpty()
            ? collect()
            : Pessoa::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $participantesIds->all())
                ->orderBy('nome')
                ->get(['id', 'nome']);

        $validated['participantes_ids'] = $participantesIds->all();
        $validated['pessoas'] = $participantes->pluck('nome')->values()->all();

        if (empty($validated['cliente_nome']) && $participantes->isNotEmpty()) {
            $validated['cliente_nome'] = $participantes->pluck('nome')->implode(', ');
        }

        if (!$contrato && !empty($validated['imovel_id']) && empty($validated['metragem'])) {
            $imovel = Property::query()
                ->where('tenant_id', $tenantId)
                ->find($validated['imovel_id']);

            if ($imovel?->area_total) {
                $validated['metragem'] = $imovel->area_total;
            }
        }

        $finalContratoId = array_key_exists('contrato_id', $validated)
            ? ($validated['contrato_id'] ?: null)
            : $current?->contrato_id;
        $finalImovelId = array_key_exists('imovel_id', $validated)
            ? ($validated['imovel_id'] ?: null)
            : $current?->imovel_id;

        if ($finalContratoId || $finalImovelId) {
            $validated['imovel_livre'] = null;
        } else {
            $livreIncoming = array_key_exists('imovel_livre', $validated)
                ? $validated['imovel_livre']
                : ($current?->imovel_livre ?? null);
            $validated['imovel_livre'] = $this->normalizeImovelLivre(
                is_array($livreIncoming) ? $livreIncoming : null
            );
        }

        return $validated;
    }

    private function laudoPayload(array $validated, Request $request): array
    {
        $fields = [
            'data_agendada', 'data_inicio', 'data_fim', 'vistoriador_id', 'observacoes_gerais',
            'introducao_texto', 'criterios_avaliacao_json', 'criterios_pintura_json',
            'criterios_limpeza_json', 'prazo_contestacao_dias', 'data_limite_contestacao',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (!empty($payload['data_agendada']) && empty($validated['data_vistoria'])) {
            $payload['data_vistoria'] = $payload['data_agendada'];
        }

        return $payload;
    }

    /** @param  array<string, mixed>|null  $raw */
    private function normalizeImovelLivre(?array $raw): ?array
    {
        $keys = ['titulo', 'logradouro', 'bairro', 'cidade', 'estado', 'tipo_imovel', 'referencia'];
        $out = [];
        foreach ($keys as $k) {
            if (!isset($raw[$k])) {
                continue;
            }
            $v = trim((string) $raw[$k]);
            if ($k === 'estado') {
                $v = mb_strtoupper($v);
            }
            if ($v !== '') {
                $out[$k] = $v;
            }
        }

        return $out === [] ? null : $out;
    }

    /** @param  array<string, mixed>|null  $raw */
    private function imovelLivreTemConteudo($raw): bool
    {
        if (!is_array($raw)) {
            return false;
        }
        foreach (['titulo', 'logradouro', 'bairro', 'cidade', 'estado', 'tipo_imovel', 'referencia'] as $key) {
            if (!empty(trim((string) ($raw[$key] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>|null  $livre */
    private function serializeImovelLivre(?array $livre): ?array
    {
        $livre = $this->normalizeImovelLivre($livre);
        if ($livre === null) {
            return null;
        }

        $endereco = collect([
            $livre['logradouro'] ?? null,
            $livre['bairro'] ?? null,
            $livre['cidade'] ?? null,
            $livre['estado'] ?? null,
        ])->filter()->implode(', ');

        $titulo = $livre['titulo'] ?? null;
        $label = $titulo
            ?: ($endereco !== '' ? $endereco : 'Local informado só na vistoria');

        return [
            'id' => null,
            'codigo' => null,
            'titulo' => $titulo,
            'label' => $label,
            'tipo_imovel' => $livre['tipo_imovel'] ?? null,
            'finalidade_imovel' => null,
            'endereco' => $endereco,
            'metragem' => null,
            'dormitorios' => null,
            'banheiros' => null,
            'garagem' => null,
            'referencia_manual' => $livre['referencia'] ?? null,
        ];
    }

    private function applyTenantScope($query, Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
        return $query;
    }

    private function applyFilters($query, Request $request)
    {
        $query = $this->applyTenantScope($query, $request);

        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cliente')) {
            $query->where('cliente_nome', 'like', '%' . $request->cliente . '%');
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('imovel_id')) {
            $query->where('imovel_id', $request->imovel_id);
        }

        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->contrato_id);
        }

        if ($request->filled('responsavel_pessoa_id')) {
            $query->where('responsavel_pessoa_id', $request->responsavel_pessoa_id);
        }

        if ($request->filled('participante_id')) {
            $query->whereJsonContains('participantes_ids', (int) $request->participante_id);
        }

        if ($request->filled('mobiliado')) {
            $query->where('mobiliado', filter_var($request->mobiliado, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('metragem_min')) {
            $query->where('metragem', '>=', $request->metragem_min);
        }

        if ($request->filled('metragem_max')) {
            $query->where('metragem', '<=', $request->metragem_max);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('data_vistoria', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data_vistoria', '<=', $request->data_fim);
        }

        if ($request->filled('vistoriador')) {
            $query->whereJsonContains('vistoriadores', $request->vistoriador);
        }

        if ($request->filled('pessoa')) {
            $query->where(function (Builder $builder) use ($request) {
                $builder->whereJsonContains('pessoas', $request->pessoa);
                if (is_numeric($request->pessoa)) {
                    $builder->orWhereJsonContains('participantes_ids', (int) $request->pessoa);
                }
            });
        }

        if ($request->filled('somente_com_contrato')) {
            $somenteComContrato = filter_var($request->somente_com_contrato, FILTER_VALIDATE_BOOLEAN);
            $somenteComContrato ? $query->whereNotNull('contrato_id') : $query->whereNull('contrato_id');
        }

        $somenteMinhas = filter_var(
            $request->query('somente_minhas', $request->query('minhas', false)),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($somenteMinhas) {
            $user = $request->user();
            if ($user && !empty($user->pessoa_id)) {
                $query->where('responsavel_pessoa_id', (int) $user->pessoa_id);
            }
        }

        return $query;
    }

    protected function detailRelations(): array
    {
        return [
            'fotos',
            'comentarios',
            'partes',
            'ambientes.itens',
            'ambientes.midias',
            'ambientes.inconformidades.midias',
            'inconformidades.ambiente',
            'inconformidades.midias',
            'midias',
            'chaves.midias',
            'medidores.midias',
            'contestacoes.itens',
            'contestacoes.midias',
            'historicos',
            'responsavel:id,nome,email,telefone,celular',
            'imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total,dormitorios,banheiros,garagem',
            'contrato:id,numero_contrato,status,inicio,fim,imovel_id,locador_pessoa_id,locatario_pessoa_id',
            'contrato.locador:id,nome,email,telefone,celular',
            'contrato.locatario:id,nome,email,telefone,celular',
            'contrato.imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total,dormitorios,banheiros,garagem',
        ];
    }

    private function serializeVistoria(Vistoria $vistoria): array
    {
        $participantesIds = collect($vistoria->participantes_ids ?? [])->map(fn ($id) => (int) $id)->values();
        $participantes = $participantesIds->isEmpty()
            ? collect()
            : Pessoa::query()
                ->where('tenant_id', $vistoria->tenant_id)
                ->whereIn('id', $participantesIds->all())
                ->orderBy('nome')
                ->get(['id', 'nome', 'email', 'telefone', 'celular', 'papeis']);

        $imovelRelation = $vistoria->contrato?->imovel ?: $vistoria->imovel;
        $imovelView = $imovelRelation
            ? $this->serializeImovel($imovelRelation)
            : $this->serializeImovelLivre($vistoria->imovel_livre);
        $contrato = $vistoria->contrato;

        return [
            'id' => $vistoria->id,
            'codigo' => $vistoria->codigo,
            'status' => $vistoria->status,
            'cliente_nome' => $vistoria->cliente_nome,
            'imovel_id' => $vistoria->imovel_id,
            'contrato_id' => $vistoria->contrato_id,
            'responsavel_pessoa_id' => $vistoria->responsavel_pessoa_id,
            'tipo' => $vistoria->tipo,
            'vistoriadores' => $vistoria->vistoriadores ?? [],
            'pessoas' => $vistoria->pessoas ?? [],
            'participantes_ids' => $participantesIds->all(),
            'participantes' => $participantes->values()->all(),
            'participantes_nomes' => $participantes->pluck('nome')->values()->all(),
            'metragem' => $vistoria->metragem,
            'mobiliado' => (bool) $vistoria->mobiliado,
            'data_vistoria' => optional($vistoria->data_vistoria)->toIso8601String(),
            'observacoes' => $vistoria->observacoes,
            'comodos' => $vistoria->comodos ?? [],
            'assinatura_inquilino_status' => $vistoria->assinatura_inquilino_status,
            'assinatura_proprietario_status' => $vistoria->assinatura_proprietario_status,
            'data_agendada' => optional($vistoria->data_agendada)->toIso8601String(),
            'data_inicio' => optional($vistoria->data_inicio)->toIso8601String(),
            'data_fim' => optional($vistoria->data_fim)->toIso8601String(),
            'vistoriador_id' => $vistoria->vistoriador_id,
            'observacoes_gerais' => $vistoria->observacoes_gerais,
            'introducao_texto' => $vistoria->introducao_texto,
            'criterios_avaliacao_json' => $vistoria->criterios_avaliacao_json,
            'criterios_pintura_json' => $vistoria->criterios_pintura_json,
            'criterios_limpeza_json' => $vistoria->criterios_limpeza_json,
            'prazo_contestacao_dias' => $vistoria->prazo_contestacao_dias,
            'data_limite_contestacao' => optional($vistoria->data_limite_contestacao)->toIso8601String(),
            'links_publicos' => [
                'midias' => $vistoria->link_publico_midias_token ? url('/api/vistorias/publico/' . $vistoria->link_publico_midias_token . '/midias') : null,
                'contestacao' => $vistoria->link_contestacao_token ? url('/api/vistorias/publico/' . $vistoria->link_contestacao_token . '/contestacao') : null,
                'pdf' => $vistoria->link_publico_midias_token ? url('/api/vistorias/publico/' . $vistoria->link_publico_midias_token . '/pdf') : null,
            ],
            'pdf_path' => $vistoria->pdf_path,
            'hash_pdf' => $vistoria->hash_pdf,
            'imovel_livre' => $this->normalizeImovelLivre($vistoria->imovel_livre),
            'created_at' => optional($vistoria->created_at)->toIso8601String(),
            'updated_at' => optional($vistoria->updated_at)->toIso8601String(),
            'responsavel' => $vistoria->responsavel ? [
                'id' => $vistoria->responsavel->id,
                'nome' => $vistoria->responsavel->nome,
                'email' => $vistoria->responsavel->email,
                'telefone' => $vistoria->responsavel->telefone,
                'celular' => $vistoria->responsavel->celular,
            ] : null,
            'imovel' => $imovelView,
            'contrato' => $contrato ? $this->serializeContrato($contrato) : null,
            'fotos' => $vistoria->relationLoaded('fotos')
                ? $vistoria->fotos->map(fn ($foto) => $foto->toArray())->values()->all()
                : [],
            'comentarios' => $vistoria->relationLoaded('comentarios')
                ? $vistoria->comentarios->map(fn ($c) => $c->toArray())->values()->all()
                : [],
            'partes' => $vistoria->relationLoaded('partes') ? $vistoria->partes->values()->all() : [],
            'ambientes' => $vistoria->relationLoaded('ambientes') ? $vistoria->ambientes->values()->all() : [],
            'inconformidades' => $vistoria->relationLoaded('inconformidades') ? $vistoria->inconformidades->values()->all() : [],
            'midias' => $vistoria->relationLoaded('midias') ? $vistoria->midias->values()->all() : [],
            'chaves' => $vistoria->relationLoaded('chaves') ? $vistoria->chaves->values()->all() : [],
            'medidores' => $vistoria->relationLoaded('medidores') ? $vistoria->medidores->values()->all() : [],
            'contestacoes' => $vistoria->relationLoaded('contestacoes') ? $vistoria->contestacoes->values()->all() : [],
            'historicos' => $vistoria->relationLoaded('historicos') ? $vistoria->historicos->values()->all() : [],
        ];
    }

    private function serializeContrato(ContratoLocacao $contrato): array
    {
        return [
            'id' => $contrato->id,
            'numero_contrato' => $contrato->numero_contrato,
            'status' => $contrato->status,
            'inicio' => optional($contrato->inicio)->toDateString(),
            'fim' => optional($contrato->fim)->toDateString(),
            'locador' => $contrato->locador ? [
                'id' => $contrato->locador->id,
                'nome' => $contrato->locador->nome,
                'email' => $contrato->locador->email,
                'telefone' => $contrato->locador->telefone,
                'celular' => $contrato->locador->celular,
            ] : null,
            'locatario' => $contrato->locatario ? [
                'id' => $contrato->locatario->id,
                'nome' => $contrato->locatario->nome,
                'email' => $contrato->locatario->email,
                'telefone' => $contrato->locatario->telefone,
                'celular' => $contrato->locatario->celular,
            ] : null,
            'imovel' => $contrato->imovel ? $this->serializeImovel($contrato->imovel) : null,
        ];
    }

    private function serializeImovel(Property $imovel): array
    {
        $address = collect([$imovel->logradouro, $imovel->bairro, $imovel->cidade, $imovel->estado])
            ->filter()
            ->implode(', ');

        return [
            'id' => $imovel->id,
            'codigo' => $imovel->codigo,
            'titulo' => $imovel->titulo,
            'label' => $imovel->titulo ?: ($imovel->codigo ? 'Imóvel ' . $imovel->codigo : 'Imóvel #' . $imovel->id),
            'tipo_imovel' => $imovel->tipo_imovel,
            'finalidade_imovel' => $imovel->finalidade_imovel,
            'endereco' => $address,
            'metragem' => $imovel->area_total,
            'dormitorios' => $imovel->dormitorios,
            'banheiros' => $imovel->banheiros,
            'garagem' => $imovel->garagem,
        ];
    }

    private function resolveTenantId(Request $request): int
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);

        if (!$tenantId) {
            abort(403, 'Tenant não identificado');
        }

        return (int) $tenantId;
    }
}
