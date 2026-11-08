{{-- Full notifications list. Expects $items (presented by NotificationController) and $iconKey ('icon' or 'admin_icon'). --}}
@php($iconBase = $iconKey === 'admin_icon' ? 'bx' : 'bi')

<div class="notification-page-list">
    @forelse ($items as $item)
        <a href="{{ $item['open_url'] }}" @class(['notification-item', 'is-unread' => ! $item['read']])>
            <span class="notification-icon"><i class="{{ $iconBase }} {{ $item[$iconKey] }}"></i></span>
            <span class="notification-text">
                <span class="notification-message">{{ $item['message'] }}</span>
                @if ($item['excerpt'])
                    <span class="notification-excerpt">{{ $item['excerpt'] }}</span>
                @endif
                <span class="notification-time">{{ $item['time'] }}</span>
            </span>
            @unless ($item['read'])
                <span class="notification-dot" aria-label="Unread"></span>
            @endunless
        </a>
    @empty
        <div class="text-center text-secondary py-5">
            <i class="{{ $iconBase }} {{ $iconBase }}-bell d-block mb-2" style="font-size: 2rem;"></i>
            No notifications yet. You'll hear here when someone likes or comments on your posts.
        </div>
    @endforelse
</div>
