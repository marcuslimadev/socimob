<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ImobiBrasilService;
use Illuminate\Http\Request;

/**
 * Proxy controller para a API ImobiBrasil.
 * Todos os endpoints operam com os códigos nativos do ImobiBrasil
 * (não IDs locais), exceto onde indicado.
 */
class ImobiBrasilController extends Controller
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveTenantId(Request $request): ?int
    {
        return $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);
    }

    private function tenant(Request $request): ?Tenant
    {
        $id = $this->resolveTenantId($request);
        return $id ? Tenant::find($id) : null;
    }

    private function noTenant(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'No tenant context'], 400);
    }

    private function notConfigured(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'Integração Imobi Brasil não configurada'], 422);
    }

    // -------------------------------------------------------------------------
    // CONTA
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/account/status
     */
    public function accountStatus(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getAccountStatus($tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // IMÓVEIS
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/imoveis
     * Query: page, per_page, status, referencia, finalidade, codigoCorretor, codigoProprietario
     */
    public function listarImoveis(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listProperties($tenant, $request->only([
            'page', 'per_page', 'status', 'referencia', 'finalidade', 'codigoCorretor', 'codigoProprietario',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /imobi-brasil/imoveis/{codigoImovel}
     */
    public function dadosImovel(Request $request, int $codigoImovel)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getPropertyData($codigoImovel, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/imoveis/{codigoImovel}
     */
    public function excluirImovel(Request $request, int $codigoImovel)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deleteProperty($codigoImovel, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /imobi-brasil/imoveis/tipos
     */
    public function listarTiposImovel(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listPropertyTypes($tenant, $request->only([
            'page', 'per_page', 'descricaoTipoImovel',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // IMAGENS DE IMÓVEIS
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/imoveis/{codigoImovel}/imagens
     */
    public function listarImagensImovel(Request $request, int $codigoImovel)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listPropertyImages($codigoImovel, $tenant, $request->only([
            'page', 'per_page', 'status',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/imoveis/{codigoImovel}/imagens/{codigoImagem}
     */
    public function excluirImagemImovel(Request $request, int $codigoImovel, int $codigoImagem)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deletePropertyImage($codigoImovel, $codigoImagem, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // CARACTERÍSTICAS
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/caracteristicas
     */
    public function listarCaracteristicas(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listCaracteristicas($tenant, $request->only([
            'page', 'per_page', 'nomeGrupo', 'nomeCaracteristica',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /imobi-brasil/caracteristicas
     * Body: { nomeCaracteristica, nomeGrupo }
     */
    public function inserirCaracteristica(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $request->validate([
            'nomeCaracteristica' => 'required|string',
            'nomeGrupo'          => 'required|string',
        ]);

        $result = ImobiBrasilService::insertCaracteristica(
            $request->input('nomeCaracteristica'),
            $request->input('nomeGrupo'),
            $tenant
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * DELETE /imobi-brasil/caracteristicas/{codigoCaracteristica}
     */
    public function excluirCaracteristica(Request $request, int $codigoCaracteristica)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deleteCaracteristica($codigoCaracteristica, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /imobi-brasil/imoveis/{codigoImovel}/caracteristicas/{codigoCaracteristica}
     */
    public function adicionarCaracteristicaImovel(Request $request, int $codigoImovel, int $codigoCaracteristica)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::addCaracteristicaToProperty($codigoImovel, $codigoCaracteristica, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/imoveis/{codigoImovel}/caracteristicas/{codigoCaracteristica}
     */
    public function removerCaracteristicaImovel(Request $request, int $codigoImovel, int $codigoCaracteristica)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::removeCaracteristicaFromProperty($codigoImovel, $codigoCaracteristica, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // PESSOAS
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/pessoas
     */
    public function listarPessoas(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listPessoas($tenant, $request->only([
            'page', 'per_page', 'status', 'tipoPessoa', 'nomeResponsavel', 'tipoCadastro',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /imobi-brasil/pessoas
     */
    public function inserirPessoa(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $request->validate([
            'tipoPessoa'      => 'required|in:F,J',
            'nomeResponsavel' => 'required|string',
        ]);

        $result = ImobiBrasilService::insertPessoa($request->all(), $tenant);

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /imobi-brasil/pessoas/{codigoPessoa}
     */
    public function dadosPessoa(Request $request, int $codigoPessoa)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getPessoa($codigoPessoa, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * PUT /imobi-brasil/pessoas/{codigoPessoa}
     */
    public function alterarPessoa(Request $request, int $codigoPessoa)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $request->validate([
            'tipoPessoa'      => 'required|in:F,J',
            'nomeResponsavel' => 'required|string',
        ]);

        $result = ImobiBrasilService::updatePessoa($codigoPessoa, $request->all(), $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/pessoas/{codigoPessoa}
     */
    public function excluirPessoa(Request $request, int $codigoPessoa)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deletePessoa($codigoPessoa, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/pessoas/{codigoPessoa}/imagem
     */
    public function excluirImagemPessoa(Request $request, int $codigoPessoa)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deletePessoaImage($codigoPessoa, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // MENSAGENS
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/mensagens
     */
    public function listarMensagens(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listMensagens($tenant, $request->only([
            'page', 'per_page', 'lido', 'dataInicio', 'dataFim', 'tipo',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /imobi-brasil/mensagens
     */
    public function inserirMensagem(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $request->validate([
            'tipo'     => 'required|in:PP,L,I,W,C,VL,EE',
            'assunto'  => 'required|string|max:255',
            'mensagem' => 'required|string|max:1500',
            'nome'     => 'required|string|min:3|max:130',
        ]);

        $result = ImobiBrasilService::insertMensagem($request->all(), $tenant);

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /imobi-brasil/mensagens/{codigoMensagem}
     */
    public function dadosMensagem(Request $request, int $codigoMensagem)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getMensagem($codigoMensagem, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/mensagens/{codigoMensagem}
     */
    public function excluirMensagem(Request $request, int $codigoMensagem)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deleteMensagem($codigoMensagem, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /imobi-brasil/mensagens/{codigoMensagem}/lido
     */
    public function marcarMensagemLida(Request $request, int $codigoMensagem)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::markMensagemAsRead($codigoMensagem, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // NEGÓCIOS
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/negocios
     */
    public function listarNegocios(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listNegocios($tenant, $request->only([
            'page', 'per_page', 'status', 'codigoCorretor', 'codigoCliente',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /imobi-brasil/negocios
     */
    public function inserirNegocio(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $request->validate([
            'codigoEtapa' => 'required|integer',
            'titulo'      => 'required|string',
            'status'      => 'required|string',
        ]);

        $result = ImobiBrasilService::insertNegocio($request->all(), $tenant);

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /imobi-brasil/negocios/etapas
     */
    public function listarEtapasNegocios(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listEtapasNegocios($tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /imobi-brasil/negocios/{codigoNegocio}
     */
    public function dadosNegocio(Request $request, int $codigoNegocio)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getNegocio($codigoNegocio, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * PUT /imobi-brasil/negocios/{codigoNegocio}
     */
    public function alterarNegocio(Request $request, int $codigoNegocio)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $request->validate([
            'codigoEtapa' => 'required|integer',
            'titulo'      => 'required|string',
            'status'      => 'required|string',
        ]);

        $result = ImobiBrasilService::updateNegocio($codigoNegocio, $request->all(), $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /imobi-brasil/negocios/{codigoNegocio}
     */
    public function excluirNegocio(Request $request, int $codigoNegocio)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::deleteNegocio($codigoNegocio, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // CORRETORES
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/corretores
     */
    public function listarCorretores(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listCorretores($tenant, $request->only([
            'page', 'per_page', 'status',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /imobi-brasil/corretores/{codigoCorretor}
     */
    public function dadosCorretor(Request $request, int $codigoCorretor)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getCorretor($codigoCorretor, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /imobi-brasil/corretores/{codigoCorretor}/imoveis
     */
    public function imoveisCorretor(Request $request, int $codigoCorretor)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listImoveisCorretor($codigoCorretor, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // CLIENTES
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/clientes
     */
    public function listarClientes(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listClientes($tenant, $request->only([
            'page', 'per_page', 'status',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /imobi-brasil/clientes/{codigoCliente}
     */
    public function dadosCliente(Request $request, int $codigoCliente)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getCliente($codigoCliente, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // CIDADES
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/cidades
     */
    public function listarCidades(Request $request)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::listCidades($tenant, $request->only([
            'page', 'per_page',
        ]));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // USUÁRIO ADICIONAL
    // -------------------------------------------------------------------------

    /**
     * GET /imobi-brasil/usuarios-adicionais/{codigoUsuario}
     */
    public function dadosUsuarioAdicional(Request $request, int $codigoUsuario)
    {
        $tenant = $this->tenant($request);
        if (!$tenant) return $this->noTenant();

        $result = ImobiBrasilService::getUsuarioAdicional($codigoUsuario, $tenant);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
