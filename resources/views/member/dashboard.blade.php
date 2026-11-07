@extends('layouts.member')

@section('title', 'Dashboard')

@section('page_actions')
    @if (Route::has('posts.create'))
        <a href="{{ route('posts.create') }}" class="btn btn-accent"><i class="bi bi-plus-lg me-1"></i>New post</a>
    @endif
@endsection

@section('member_content')
    <p class="text-secondary mb-4">Hi {{ auth()->user()->name }}, here's how your writing is doing.</p>

    @php
        $postCards = [
            ['label' => 'Published', 'value' => $stats['published'], 'icon' => 'bi-globe2'],
            ['label' => 'Scheduled', 'value' => $stats['scheduled'], 'icon' => 'bi-clock'],
            ['label' => 'Drafts', 'value' => $stats['drafts'], 'icon' => 'bi-pencil'],
            ['label' => 'Private & restricted', 'value' => $stats['private'], 'icon' => 'bi-lock'],
        ];
        $engagementCards = [
            ['label' => 'Views', 'value' => $stats['views'], 'icon' => 'bi-eye'],
            ['label' => 'Likes', 'value' => $stats['likes'], 'icon' => 'bi-hand-thumbs-up'],
            ['label' => 'Comments', 'value' => $stats['comments'], 'icon' => 'bi-chat'],
        ];
    @endphp

    <h3 class="h6 text-uppercase text-secondary mb-3">Posts <span class="fw-normal">({{ $stats['total'] }} in total)</span></h3>
    <div class="row g-3 mb-4">
        @foreach ($postCards as $card)
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <i class="bi {{ $card['icon'] }} text-secondary"></i>
                        <div class="stat-value mt-2" data-stat="{{ str($card['label'])->slug() }}">{{ number_format($card['value']) }}</div>
                        <div class="stat-label">{{ $card['label'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="h6 text-uppercase text-secondary mb-3">Engagement</h3>
    <div class="row g-3 mb-5">
        @foreach ($engagementCards as $card)
            <div class="col-4">
                <div class="card stat-card">
                    <div class="card-body">
                        <i class="bi {{ $card['icon'] }} text-secondary"></i>
                        <div class="stat-value mt-2" data-stat="{{ str($card['label'])->slug() }}">{{ number_format($card['value']) }}</div>
                        <div class="stat-label">{{ $card['label'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="h6 text-uppercase text-secondary mb-3">Recently updated</h3>
    @if ($recentPosts->isEmpty())
        <div class="empty-state text-center text-secondary py-5">
            <i class="bi bi-journal-plus display-6 d-block mb-3"></i>
            You haven't written anything yet.
            @if (Route::has('posts.create'))
                <div class="mt-3"><a href="{{ route('posts.create') }}" class="btn btn-accent">Write your first post</a></div>
            @else
                <div class="small mt-2">Writing posts arrives in an upcoming update.</div>
            @endif
        </div>
    @else
        <div class="list-group recent-posts">
            @foreach ($recentPosts as $post)
                <div class="list-group-item d-flex flex-wrap align-items-center gap-2 py-3">
                    <div class="me-auto text-truncate" style="min-width: 0; max-width: 100%;">
                        @if (Route::has('posts.edit'))
                            <a href="{{ route('posts.edit', $post) }}" class="fw-semibold text-truncate d-block">{{ $post->title }}</a>
                        @else
                            <span class="fw-semibold text-truncate d-block">{{ $post->title }}</span>
                        @endif
                        <small class="text-secondary">
                            {{ $post->category?->name ?? 'Uncategorised' }} &middot; updated {{ $post->updated_at->diffForHumans() }}
                        </small>
                    </div>
                    <x-post-status-badge :post="$post" />
                </div>
            @endforeach
        </div>
    @endif
@endsection
