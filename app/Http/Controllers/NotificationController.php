<?php

namespace App\Http\Controllers;

use App\Models\InternalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = InternalNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15)
            ->through(fn (InternalNotification $notification) => $this->notificationRow($notification));

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, InternalNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update([
            'read_at' => now(),
        ]);

        return back()->with('success', 'Notificação marcada como lida.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        InternalNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationRow(InternalNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'type' => $notification->type,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
            'notifiable_type' => $notification->notifiable_type,
            'notifiable_id' => $notification->notifiable_id,
        ];
    }
}
