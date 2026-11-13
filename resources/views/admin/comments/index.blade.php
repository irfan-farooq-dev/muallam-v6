@extends('admin.layouts.app')

@section('title', 'Comments')

@section('content')
    <div class="card" x-data="{ selected: [], pageIds: @js($comments->getCollection()->reject->trashed()->pluck('id')->values()) }">
        <div class="card-body border-bottom">
            @if ($filteredPost)
                <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                    <span>Comments on <a href="{{ route('posts.show', $filteredPost) }}" target="_blank" class="fw-semibold">{{ $filteredPost->title }}</a></span>
                    <a href="{{ route('admin.comments.index', array_filter(\Illuminate\Support\Arr::except($filters, 'post'))) }}" class="btn btn-sm btn-outline-secondary">All posts</a>
                </div>
            @endif
            <form method="GET" action="{{ route('admin.comments.index') }}" class="row g-2 align-items-end" role="search">
                @if ($filteredPost)
                    <input type="hidden" name="post" value="{{ $filteredPost->id }}">
                @endif
                <div class="col-md-4">
                    <label for="search" class="form-label small">Comment text</label>
                    <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Words in the comment">
                </div>
                <div class="col-md-3">
                    <label for="author" class="form-label small">Written by</label>
                    <input type="search" id="author" name="author" value="{{ $filters['author'] }}" class="form-control" placeholder="Name or email">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Visible and hidden</option>
                        @foreach (\App\Http\Controllers\Admin\CommentController::STATUS_FILTERS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.comments.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bx bx-x"></i></a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Bulk actions: the row checkboxes belong to this form through their form="" attribute. --}}
        {{-- x-show sits on a wrapper: Bootstrap's d-flex (!important) would override Alpine's display: none. --}}
        <div x-show="selected.length" x-cloak>
            <form id="bulk-form" method="POST" action="{{ route('admin.comments.bulk') }}"
                  class="card-body border-bottom d-flex flex-wrap align-items-center gap-2 py-2"
                  onsubmit="return event.submitter?.value !== 'delete' || confirm('Delete the selected comments? This cannot be undone.')">
                @csrf
                <span class="small me-2"><strong x-text="selected.length"></strong> selected</span>
                <button type="submit" name="action" value="hide" class="btn btn-sm btn-outline-warning"><i class="bx bx-hide me-1"></i>Hide</button>
                <button type="submit" name="action" value="show" class="btn btn-sm btn-outline-success"><i class="bx bx-show me-1"></i>Show</button>
                <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash me-1"></i>Delete</button>
                <button type="button" class="btn btn-sm btn-link text-secondary" @click="selected = []">Clear selection</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 1%;">
                            <input type="checkbox" class="form-check-input" aria-label="Select all comments on this page"
                                   :checked="pageIds.length && selected.length === pageIds.length"
                                   @change="selected = $event.target.checked ? [...pageIds] : []" @disabled($comments->isEmpty())>
                        </th>
                        <th>Comment</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comments as $comment)
                        <tr @class(['table-light' => $comment->trashed()])>
                            <td>
                                @unless ($comment->trashed())
                                    <input type="checkbox" class="form-check-input" name="comments[]" value="{{ $comment->id }}" form="bulk-form"
                                           x-model.number="selected" aria-label="Select comment {{ $comment->id }}">
                                @endunless
                            </td>
                            <td style="min-width: 320px;">
                                <div class="admin-comment-body">{{ \Illuminate\Support\Str::limit($comment->body, 300) }}</div>
                                <div class="small text-muted mt-1">
                                    <span class="fw-semibold text-body">{{ $comment->user->name }}</span>
                                    @if ($comment->parent)
                                        replying to {{ $comment->parent->user?->name ?? 'a comment' }}
                                    @endif
                                    on <a href="{{ route('posts.show', $comment->post).'#comment-'.$comment->id }}" target="_blank">{{ \Illuminate\Support\Str::limit($comment->post->title, 60) }}</a>
                                    &middot; <a href="{{ route('admin.comments.index', ['author' => $comment->user->email]) }}" class="text-muted" title="All comments by {{ $comment->user->name }}">all by them</a>
                                </div>
                            </td>
                            <td>
                                @if ($comment->trashed())
                                    <span class="badge bg-label-secondary">Deleted</span>
                                @elseif ($comment->is_approved)
                                    <span class="badge bg-label-success">Visible</span>
                                @else
                                    <span class="badge bg-label-warning">Hidden</span>
                                @endif
                            </td>
                            <td class="text-nowrap small"><x-local-time :at="$comment->created_at" relative /></td>
                            <td class="text-end text-nowrap">
                                @unless ($comment->trashed())
                                    <form method="POST" action="{{ route('admin.comments.visibility', $comment) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="visible" value="{{ $comment->is_approved ? 0 : 1 }}">
                                        @if ($comment->is_approved)
                                            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bx bx-hide me-1"></i>Hide</button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="bx bx-show me-1"></i>Show</button>
                                        @endif
                                    </form>
                                    <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" class="d-inline"
                                          onsubmit="return confirm(@js('Delete this comment by '.$comment->user->name.'? This cannot be undone.'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete comment {{ $comment->id }}"><i class="bx bx-trash"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No comments match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($comments->hasPages())
            <div class="card-body border-top">{{ $comments->links() }}</div>
        @endif
    </div>
    <p class="small text-muted mt-2 mb-0">
        {{ trans_choice('{1} 1 comment|[0,*] :count comments', $comments->total()) }}.
        Hiding keeps a comment (and its replies) away from readers; the post's author and admins still see it. Deleting removes it for everyone.
    </p>
@endsection
