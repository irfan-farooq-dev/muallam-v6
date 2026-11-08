<?php

namespace App\Http\Controllers;

use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The bell and the notifications pages (member area and admin panel).
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * How many the bell's dropdown shows.
     */
    private const FEED_SIZE = 8;

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $notifications = $user->notifications()->paginate(self::PER_PAGE);

        return view($request->routeIs('admin.*') ? 'admin.notifications.index' : 'member.notifications', [
            'notifications' => $notifications,
            'items' => $notifications->getCollection()->map(fn (DatabaseNotification $notification): array => $this->present($notification)),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * What the bell polls: the unread count and the newest few.
     */
    public function feed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->take(self::FEED_SIZE)->get()
                ->map(fn (DatabaseNotification $notification): array => $this->present($notification))
                ->values(),
        ]);
    }

    /**
     * Marks one as read and goes where it points.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = $user->notifications()->whereKey($notification)->firstOrFail();
        $found->markAsRead();

        $url = $found->data['url'] ?? null;
        // Only this site's own paths ("/posts/..."), never another website.
        $isOwnPath = is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//');

        return $isOwnPath ? redirect($url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * @return array{id: string, kind: string, icon: string, admin_icon: string, message: string, excerpt: ?string, time: string, read: bool, open_url: string}
     */
    private function present(DatabaseNotification $notification): array
    {
        $kind = NotificationKind::tryFrom((string) ($notification->data['kind'] ?? ''));

        return [
            'id' => $notification->getKey(),
            'kind' => $kind?->value ?? 'other',
            'icon' => $kind?->icon() ?? 'bi-bell',
            'admin_icon' => $kind?->adminIcon() ?? 'bx-bell',
            'message' => (string) ($notification->data['message'] ?? ''),
            'excerpt' => $notification->data['excerpt'] ?? null,
            'time' => $notification->created_at->diffForHumans(),
            'read' => $notification->read_at !== null,
            'open_url' => route('notifications.open', $notification->getKey()),
        ];
    }
}
