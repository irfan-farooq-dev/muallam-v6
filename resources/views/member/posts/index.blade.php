@extends('layouts.member')

@section('title', 'My posts')

@section('page_actions')
    <a href="{{ route('posts.create') }}" class="btn btn-accent"><i class="bi bi-plus-lg me-1"></i>New post</a>
@endsection

@section('member_content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <ul class="nav nav-pills post-filters">
            @foreach (\App\Http\Controllers\Posts\MyPostsController::FILTERS as $key => $label)
                <li class="nav-item">
                    <a href="{{ route('posts.mine', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search ?: null])) }}"
                       @class(['nav-link', 'active' => $filter === $key])>
                        {{ $label }} <span class="badge rounded-pill text-bg-light ms-1" data-count="{{ $key }}">{{ $counts[$key] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="GET" action="{{ route('posts.mine') }}" class="d-flex gap-2" role="search">
            @if ($filter !== 'all')
                <input type="hidden" name="status" value="{{ $filter }}">
            @endif
            <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search titles" aria-label="Search your posts">
            <button type="submit" class="btn btn-sm btn-outline-accent">Search</button>
        </form>
    </div>

    @if ($posts->isEmpty())
        <div class="empty-state text-center text-secondary py-5">
            <i class="bi bi-journal-plus display-6 d-block mb-3"></i>
            @if ($search !== '' || $filter !== 'all')
                No posts match. <a href="{{ route('posts.mine') }}">Show all posts</a>
            @else
                You haven't written anything yet.
                <div class="mt-3"><a href="{{ route('posts.create') }}" class="btn btn-accent">Write your first post</a></div>
            @endif
        </div>
    @else
        <div class="list-group">
            @foreach ($posts as $post)
                <div class="list-group-item d-flex flex-wrap align-items-center gap-3 py-3">
                    <div class="me-auto" style="min-width: 0; flex: 1 1 280px;">
                        <a href="{{ route('posts.edit', $post) }}" class="fw-semibold d-block text-truncate">{{ $post->title }}</a>
                        <small class="text-secondary">
                            {{ $post->category?->name ?? 'Uncategorised' }}
                            &middot; updated {{ $post->updated_at->diffForHumans() }}
                            @if ($post->isScheduled())
                                &middot; goes live {{ $post->published_at->format('j M Y, g:i a') }}
                            @endif
                        </small>
                    </div>
                    <x-post-status-badge :post="$post" />
                    <div class="d-flex gap-1">
                        <a href="{{ route('posts.edit', $post) }}" class="btn btn-sm btn-outline-accent" aria-label="Edit {{ $post->title }}"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('posts.destroy', $post) }}"
                              onsubmit="return confirm(@js('Delete "'.$post->title.'"? This cannot be undone.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $post->title }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
@endsection
