<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Listar notificações do usuário
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $status = $request->query('status'); // 'read', 'unread'
        $type = $request->query('type');
        $perPage = $request->query('per_page', 15);

        $query = Notification::forTenant($tenantId)
            ->forUser($request->user()->id);

        if ($status === 'read') {
            $query->read();
        } elseif ($status === 'unread') {
            $query->unread();
        }

        if ($type) {
            $query->byType($type);
        }

        $notifications = $query->latest()
            ->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Obter detalhes de uma notificação
     * GET /api/notifications/{id}
     */
    public function show(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notification = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->find($id);

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        // Marcar como lida
        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json($notification);
    }

    /**
     * Marcar notificação como lida
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notification = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->find($id);

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification,
        ]);
    }

    /**
     * Marcar notificação como não lida
     * POST /api/notifications/{id}/unread
     */
    public function markAsUnread(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notification = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->find($id);

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $notification->markAsUnread();

        return response()->json([
            'message' => 'Notification marked as unread',
            'notification' => $notification,
        ]);
    }

    /**
     * Marcar todas as notificações como lidas
     * POST /api/notifications/mark-all-as-read
     */
    public function markAllAsRead(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Deletar notificação
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notification = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->find($id);

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Obter contagem de notificações não lidas
     * GET /api/notifications/unread/count
     */
    public function unreadCount(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $count = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Obter resumo de notificações
     * GET /api/notifications/summary
     */
    public function summary(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $total = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->count();

        $unread = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->unread()
            ->count();

        $byType = Notification::forTenant($tenantId)
            ->forUser($request->user()->id)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get();

        return response()->json([
            'total' => $total,
            'unread' => $unread,
            'by_type' => $byType,
        ]);
    }
}
