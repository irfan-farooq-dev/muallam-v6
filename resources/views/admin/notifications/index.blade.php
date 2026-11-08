@extends('admin.layouts.app')

@section('title', 'Notifications')

@section('page_actions')
    @if ($unreadCount)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bx bx-check-double me-1"></i>Mark all as read</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-header small text-muted">
            {{ $unreadCount ? trans_choice('{1} 1 unread notification.|[2,*] :count unread notifications.', $unreadCount) : 'You are all caught up.' }}
        </div>
        @include('partials.notification-list', ['items' => $items, 'iconKey' => 'admin_icon'])
    </div>

    @if ($notifications->hasPages())
        <div class="mt-3">{{ $notifications->links() }}</div>
    @endif
@endsection
