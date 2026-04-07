<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;


use App\Services\VisitSchedulingService;
use App\Services\VisitasTablesManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VisitasController extends Controller
{
    /**
     * GET /api/admin/visitas
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        VisitasTablesManager::ensureVisitasTableExists();

        $visitas = DB::table('visitas')
            ->leftJoin('users as assigned_user', 'visitas.assigned_user_id', '=', 'assigned_user.id')
            ->leftJoin('users as created_by_user', 'visitas.created_by_user_id', '=', 'created_by_user.id')
            ->where('visitas.tenant_id', $user->tenant_id)
            ->when(($user->role ?? null) === 'corretor', function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->where('visitas.assigned_user_id', $user->id)
                        ->orWhere('visitas.created_by_user_id', $user->id);
                });
            })
            ->when($request->filled('assigned_user_id'), function ($query) use ($request) {
                $assignedUserId = (int) $request->input('assigned_user_id');
                if ($assignedUserId > 0) {
                    $query->where('visitas.assigned_user_id', $assignedUserId);
                }
            })
            ->select('visitas.*', 'assigned_user.name as assigned_user_name', 'created_by_user.name as created_by_user_name')
            ->orderBy('data_hora', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitas,
            'total' => $visitas->count(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        VisitasTablesManager::ensureVisitasTableExists();

        $validator = Validator::make($request->all(), [
            'property_id' => 'nullable|integer',
            'lead_id' => 'nullable|integer',
            'property_titulo' => 'nullable|string|max:255',
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'required|string|max:50',
            'data_hora' => 'required|string|max:50',
            'observacoes' => 'nullable|string|max:1000',
            'assigned_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados inválidos.',
                'messages' => $validator->errors(),
            ], 422);
        }

        $assignedUserId = $this->resolveAssignedUserId($user, $request->input('assigned_user_id'));

        $visit = app(VisitSchedulingService::class)->schedule([
            'tenant_id' => $user->tenant_id,
            'property_id' => $request->input('property_id'),
            'lead_id' => $request->input('lead_id'),
            'property_titulo' => $request->input('property_titulo'),
            'nome' => $request->input('nome'),
            'email' => $request->input('email'),
            'telefone' => $request->input('telefone'),
            'data_hora' => $request->input('data_hora'),
            'observacoes' => $request->input('observacoes'),
            'assigned_user_id' => $assignedUserId,
            'created_by_user_id' => $user->id,
            'origem' => 'agenda_admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visita criada com sucesso.',
            'data' => $visit,
        ], 201);
    }

    /**
     * PATCH /api/admin/visitas/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        VisitasTablesManager::ensureVisitasTableExists();

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pendente,confirmada,cancelada,concluida',
            'observacoes' => 'nullable|string|max:1000',
            'data_hora' => 'nullable|string|max:50',
            'assigned_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados inválidos.',
                'messages' => $validator->errors(),
            ], 422);
        }

        $visita = DB::table('visitas')
            ->where('tenant_id', $user->tenant_id)
            ->when(($user->role ?? null) === 'corretor', function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->where('assigned_user_id', $user->id)
                        ->orWhere('created_by_user_id', $user->id);
                });
            })
            ->where('id', $id)
            ->first();

        if (!$visita) {
            return response()->json(['error' => 'Visita not found'], 404);
        }

        $payload = [];
        if ($request->has('status')) {
            $payload['status'] = $request->input('status');
        }
        if ($request->has('observacoes')) {
            $payload['observacoes'] = $request->input('observacoes');
        }
        if ($request->has('data_hora')) {
            $payload['data_hora'] = app(VisitSchedulingService::class)->normalizeDateTime($request->input('data_hora'));
        }
        if ($request->has('assigned_user_id')) {
            $payload['assigned_user_id'] = $this->resolveAssignedUserId($user, $request->input('assigned_user_id'));
        }
        if (empty($payload)) {
            return response()->json(['error' => 'No changes provided'], 400);
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        DB::table('visitas')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->update($payload);

        return response()->json(['success' => true]);
    }

    private function resolveAssignedUserId($user, $requestedAssignedUserId): ?int
    {
        if (($user->role ?? null) === 'corretor') {
            return (int) $user->id;
        }

        if (!$requestedAssignedUserId) {
            return (int) $user->id;
        }

        $assignedUser = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('role', ['corretor', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->find((int) $requestedAssignedUserId);

        if (!$assignedUser) {
            abort(422, 'Usuário responsável inválido para esta agenda.');
        }

        return (int) $assignedUser->id;
    }
}
