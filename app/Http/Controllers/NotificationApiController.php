<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => NotificationResource::collection(
                $user->notifications()->latest()->limit(20)->get()
            ),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(
        Request $request,
        DatabaseNotification $notification,
        MarkAsReadAction $action,
    ): JsonResponse {
        $this->authorize('update', $notification);
        $action($notification);

        return response()->json([
            'data' => new NotificationResource($notification->fresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): JsonResponse
    {
        $this->authorize('viewAny', DatabaseNotification::class);
        $action($request->user());

        return response()->json(['unread_count' => 0]);
    }
}
