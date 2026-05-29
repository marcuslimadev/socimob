<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportantLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImportantLinksController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = ImportantLink::query()
            ->with('createdBy:id,name')
            ->orderBy('sort_order')
            ->orderBy('title');

        if (!in_array($user->role, ['admin', 'super_admin'], true)) {
            $query->where('is_active', true);
        } elseif ($request->filled('active')) {
            $query->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit(500)->get(),
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item = ImportantLink::create(array_merge($validator->validated(), [
            'created_by_user_id' => $request->user()?->id,
        ]));

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function update(Request $request, int $id)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $item = ImportantLink::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Link não encontrado'], 404);
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update($validator->validated());

        return response()->json(['success' => true, 'data' => $item->fresh('createdBy:id,name')]);
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $item = ImportantLink::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Link não encontrado'], 404);
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Link excluído com sucesso']);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'title' => 'required|string|max:160',
            'url' => 'required|url|max:2000',
            'category' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function ensureAdmin(Request $request)
    {
        $role = $request->user()?->role;
        if (!in_array($role, ['admin', 'super_admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas administradores podem cadastrar links importantes.',
            ], 403);
        }

        return null;
    }
}
