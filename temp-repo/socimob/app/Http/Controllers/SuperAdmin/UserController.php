<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar todos os usuários
     * GET /api/super-admin/users
     */
    public function index(Request $request)
    {
        try {
            // Debug: verificar autenticação
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'User not authenticated'], 401);
            }
            
            if (!$user->isSuperAdmin()) {
                return response()->json(['success' => false, 'error' => 'User is not super admin'], 403);
            }

            $perPage = $request->query('per_page', 15);
            $search = $request->query('search');
            $role = $request->query('role');
            $status = $request->query('status');

            $query = User::with('tenant');

            // Filtrar por busca
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filtrar por perfil
            if ($role) {
                $query->where('role', $role);
            }

            // Filtrar por status
            if ($status !== null) {
                $query->where('is_active', $status == '1');
            }

            $allUsers = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'users' => $allUsers,
                'count' => count($allUsers),
                'data' => $allUsers
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Criar novo usuário
     * POST /api/super-admin/users
     */
    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:super_admin,admin,user,client',
            'tenant_id' => 'nullable|exists:tenants,id',
            'is_active' => 'boolean'
        ]);

        try {
            $data = $validated;
            $data['password'] = Hash::make($validated['password']);
            
            // Se for super_admin, não pode ter tenant
            if ($data['role'] === 'super_admin') {
                $data['tenant_id'] = null;
            }

            $user = User::create($data);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create user',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar usuário específico
     * GET /api/super-admin/users/{id}
     */
    public function show(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::with('tenant')->find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json(['user' => $user]);
    }

    /**
     * Atualizar usuário
     * PUT /api/super-admin/users/{id}
     */
    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validated = $this->validate($request, [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|in:super_admin,admin,user,client',
            'tenant_id' => 'nullable|exists:tenants,id',
            'is_active' => 'boolean'
        ]);

        try {
            $data = $validated;
            
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Se for super_admin, não pode ter tenant
            if (isset($data['role']) && $data['role'] === 'super_admin') {
                $data['tenant_id'] = null;
            }

            $user->update($data);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update user',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deletar usuário
     * DELETE /api/super-admin/users/{id}
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Não permitir deletar o próprio usuário
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }

        try {
            $user->delete();
            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete user',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
