<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Últimas notificaciones del usuario autenticado, para el desplegable
     * de la campana (se pide por AJAX desde el layout).
     */
    public function index(Request $request)
    {
        $userId = (string) $request->user()->_id;

        $notifications = Notification::forUser($userId)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return response()->json([
            'unread_count' => Notification::forUser($userId)->unread()->count(),
            'notifications' => $notifications->map(fn ($notification) => [
                'id' => (string) $notification->_id,
                'title' => $notification->title,
                'body' => $notification->body,
                'url' => $notification->url,
                'read' => filled($notification->read_at),
                'created_at' => $notification->created_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Marca una notificación puntual como leída (se llama al hacer clic en ella).
     */
    public function markRead(Request $request, string $id)
    {
        $notification = Notification::forUser((string) $request->user()->_id)->findOrFail($id);
        $notification->markAsRead();

        return response()->noContent();
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function markAllRead(Request $request)
    {
        Notification::forUser((string) $request->user()->_id)->unread()->get()->each->markAsRead();

        return response()->noContent();
    }
}
