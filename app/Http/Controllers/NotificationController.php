<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        $tab = $validated['tab'] ?? 'all';
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $action($user, $tab),
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    public function markAsRead(DatabaseNotification $notification, MarkAsReadAction $action): RedirectResponse
    {
        $this->authorize('update', $notification);

        return redirect()->to($action($notification));
    }

    public function show(DatabaseNotification $notification, MarkAsReadAction $action): View
    {
        $this->authorize('view', $notification);
        $action($notification);

        return view('notifications.show', [
            'notification' => $notification->fresh(),
        ]);
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $this->authorize('viewAny', DatabaseNotification::class);
        $action($request->user());

        return redirect()->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
