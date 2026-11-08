@extends('layouts.member')

@section('title', 'Notifications')

@section('page_actions')
    @if ($unreadCount)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline-accent btn-sm"><i class="bi bi-check2-all me-1"></i>Mark all as read</button>
        </form>
    @endif
@endsection

@section('member_content')
    <p class="text-secondary small mb-3">
        {{ $unreadCount ? trans_choice('{1} 1 unread notification.|[2,*] :count unread notifications.', $unreadCount) : 'You are all caught up.' }}
    </p>

    <div class="card">
        @include('partials.notification-list', ['items' => $items, 'iconKey' => 'icon'])
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
