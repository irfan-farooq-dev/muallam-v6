@extends('admin.layouts.app')

@section('title', 'Posts')

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.posts.index') }}" class="row g-2 align-items-end" role="search">
                <div class="col-md-3">
                    <label for="search" class="form-label small">Title</label>
                    <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Search titles">
                </div>
                <div class="col-md-3">
                    <label for="author" class="form-label small">Author</label>
                    <input type="search" id="author" name="author" value="{{ $filters['author'] }}" class="form-control" placeholder="Name or email">
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Any</option>
                        @foreach (\App\Http\Controllers\Admin\PostController::STATUS_FILTERS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="category" class="form-label small">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">Any</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.posts.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bx bx-x"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Status</th>
                        <th class="text-end text-nowrap" title="Views / likes / comments">Stats</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr>
                            <td style="min-width: 260px;">
                                <a href="{{ route('posts.show', $post) }}" target="_blank" class="fw-semibold text-body">{{ \Illuminate\Support\Str::limit($post->title, 80) }}</a>
                                @if ($post->pending_admin_edits_count)
                                    <span class="badge bg-label-info ms-1" title="Edited by an admin; the author has not reviewed it yet">Admin edit</span>
                                @endif
                                <div class="small text-muted">
                                    by {{ $post->user->name }} &middot; {{ $post->category?->name ?? 'Uncategorised' }}
                                </div>
                            </td>
                            <td><x-post-status-badge :post="$post" /></td>
                            <td class="text-end text-nowrap small">
                                <span title="Views"><i class="bx bx-show"></i> {{ number_format($post->views_count) }}</span>
                                <span class="ms-2" title="Likes"><i class="bx bx-like"></i> {{ number_format($post->likes_count) }}</span>
                                <a href="{{ route('admin.comments.index', ['post' => $post->id]) }}" class="ms-2 text-body" title="Manage comments on this post"><i class="bx bx-comment"></i> {{ number_format($post->comments_count) }}</a>
                            </td>
                            <td class="text-nowrap small"><span title="{{ $post->updated_at->format('j M Y, g:i a') }}">{{ $post->updated_at->diffForHumans() }}</span></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('posts.edit', $post) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-edit-alt me-1"></i>Edit</a>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-label="More actions for {{ $post->title }}"><i class="bx bx-dots-vertical-rounded"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('posts.show', $post) }}" target="_blank"><i class="bx bx-show me-2"></i>View on site</a></li>
                                        @if ($post->status !== \App\Enums\PostStatus::Draft)
                                            <li>
                                                <form method="POST" action="{{ route('admin.posts.unpublish', $post) }}"
                                                      onsubmit="return confirm(@js('Unpublish "'.$post->title.'"? It becomes a draft that only its author can see.'))">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="bx bx-hide me-2"></i>Unpublish (to draft)</button>
                                                </form>
                                            </li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}"
                                                  onsubmit="return confirm(@js('Delete "'.$post->title.'" by '.$post->user->name.'? Its comments, likes and images are deleted too. This cannot be undone.'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="bx bx-trash me-2"></i>Delete</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No posts match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($posts->hasPages())
            <div class="card-body border-top">{{ $posts->links() }}</div>
        @endif
    </div>
    <p class="small text-muted mt-2 mb-0">{{ trans_choice('{1} 1 post|[0,*] :count posts', $posts->total()) }}</p>
@endsection
