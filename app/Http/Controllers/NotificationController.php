<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * GET /api/me/notifications
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $notifications = AppNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $unreadCount = AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unreadCount' => $unreadCount,
            'items' => $notifications->map(fn ($n) => [
                'id' => (string) $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'readAt' => $n->read_at?->toIso8601String(),
                'createdAt' => $n->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * PATCH /api/me/notifications/{id}/read
     */
    public function read(int $id): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $n = AppNotification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! $n->read_at) {
            $n->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/me/notifications/read-all
     */
    public function readAll(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 401);

        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
