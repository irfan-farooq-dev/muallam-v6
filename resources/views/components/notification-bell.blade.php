{{--
    The bell with unread count and a dropdown of the newest notifications. variant="site" (Bootstrap Icons) or "admin" (Sneat/Boxicons).
    The count comes with the page; the list loads when the dropdown opens, and both refresh every minute and when the tab comes back into view.
--}}
@props(['variant' => 'site'])

@php
    $isAdmin = $variant === 'admin';
    $unread = auth()->user()->unreadNotifications()->count();
    $allUrl = $isAdmin && Route::has('admin.notifications.index') ? route('admin.notifications.index') : route('notifications.index');
@endphp

<div {{ $attributes->class(['dropdown notification-bell', 'notification-bell-admin' => $isAdmin]) }}
     x-data="notificationBell(@js(route('notifications.feed')), {{ $unread }})"
     x-init="$el.addEventListener('show.bs.dropdown', () => refresh())">
    <button type="button" @class(['btn btn-link notification-bell-toggle position-relative', 'nav-link' => $isAdmin])
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
            :aria-label="unread ? `Notifications, ${unread} unread` : 'Notifications'" aria-label="Notifications">
        <i @class(['bx bx-bell bx-sm' => $isAdmin, 'bi bi-bell' => ! $isAdmin])></i>
        <span class="notification-count" x-show="unread > 0" x-text="unread > 99 ? '99+' : unread" @if (! $unread) x-cloak @endif>{{ $unread ?: '' }}</span>
    </button>

    <div class="dropdown-menu dropdown-menu-end notification-menu p-0 shadow">
        <div class="notification-menu-header d-flex align-items-center justify-content-between gap-2">
            <span class="fw-semibold">Notifications</span>
            <form method="POST" action="{{ route('notifications.read-all') }}" x-show="unread > 0" @if (! $unread) x-cloak @endif>
                @csrf
                <button type="submit" class="btn btn-link btn-sm p-0">Mark all as read</button>
            </form>
        </div>

        <div class="notification-menu-list">
            <div class="text-center text-secondary small py-4" x-show="!loaded">Loading…</div>
            <div class="text-center text-secondary small py-4" x-show="loaded && items.length === 0" x-cloak>No notifications yet.</div>
            <template x-for="item in items" :key="item.id">
                <a :href="item.open_url" class="notification-item" :class="{ 'is-unread': !item.read }">
                    <span class="notification-icon"><i class="{{ $isAdmin ? 'bx' : 'bi' }}" :class="{{ $isAdmin ? 'item.admin_icon' : 'item.icon' }}"></i></span>
                    <span class="notification-text">
                        <span class="notification-message" x-text="item.message"></span>
                        <span class="notification-excerpt" x-show="item.excerpt" x-text="item.excerpt"></span>
                        <span class="notification-time" x-text="item.time"></span>
                    </span>
                    <span class="notification-dot" x-show="!item.read" aria-label="Unread"></span>
                </a>
            </template>
        </div>

        <a href="{{ $allUrl }}" class="notification-menu-footer">View all notifications</a>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('notificationBell', (feedUrl, initialUnread) => ({
                    unread: initialUnread,
                    items: [],
                    loaded: false,
                    timer: null,

                    init() {
                        this.timer = setInterval(() => this.refresh(), 60000);
                        document.addEventListener('visibilitychange', () => {
                            if (!document.hidden) {
                                this.refresh();
                            }
                        });
                    },

                    async refresh() {
                        if (document.hidden) {
                            return;
                        }

                        try {
                            const response = await fetch(feedUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

                            // Signed out or session expired: stop asking.
                            if (response.status === 401 || response.status === 419) {
                                clearInterval(this.timer);
                                return;
                            }
                            if (!response.ok) {
                                return;
                            }

                            const feed = await response.json();
                            this.unread = feed.unread;
                            this.items = feed.items;
                            this.loaded = true;
                        } catch (error) {
                            // Offline for a moment; the next check will try again.
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
