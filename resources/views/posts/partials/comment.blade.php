{{-- One comment (or reply). Expects $comment, $post, and $canReply (only true for top-level comments). --}}
<article id="comment-{{ $comment->id }}" @class(['comment', 'comment-hidden' => ! $comment->is_approved])>
    @if ($comment->trashed())
        <p class="text-secondary fst-italic small mb-0">This comment was removed.</p>
    @else
        <div class="d-flex gap-2">
            <x-user-avatar :user="$comment->user" :size="36" />
            <div class="flex-grow-1" style="min-width: 0;">
                <div class="d-flex flex-wrap align-items-baseline gap-2 small">
                    <a href="{{ route('users.posts', $comment->user->slug) }}" class="fw-semibold text-body">{{ $comment->user->name }}</a>
                    @if ($comment->user_id === $post->user_id)
                        <span class="badge text-bg-light">Author</span>
                    @endif
                    <time class="text-secondary" datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ $comment->created_at->format('j M Y, g:i a') }}">{{ $comment->created_at->diffForHumans() }}</time>
                    @unless ($comment->is_approved)
                        <span class="badge text-bg-warning">Hidden</span>
                    @endunless
                </div>

                <div class="comment-body mt-1">{!! nl2br(e($comment->body)) !!}</div>

                <div class="comment-actions d-flex flex-wrap gap-3 mt-1 small">
                    @if ($canReply)
                        @can('reply', $comment)
                            <button type="button" class="btn btn-link btn-sm p-0" @click="replyTo = replyTo === {{ $comment->id }} ? null : {{ $comment->id }}">Reply</button>
                        @endcan
                    @endif
                    @can('moderate', $comment)
                        <form method="POST" action="{{ route('comments.visibility', $comment) }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="visible" value="{{ $comment->is_approved ? 0 : 1 }}">
                            <button type="submit" class="btn btn-link btn-sm p-0 text-secondary">{{ $comment->is_approved ? 'Hide' : 'Show' }}</button>
                        </form>
                    @endcan
                    @can('delete', $comment)
                        <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="d-inline"
                              onsubmit="return confirm('Delete this comment? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Delete</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    @if ($canReply)
        @can('reply', $comment)
            <form method="POST" action="{{ route('comments.store', $post) }}" class="comment-reply-form mt-2" x-show="replyTo === {{ $comment->id }}" x-cloak>
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <textarea name="body" rows="2" class="form-control form-control-sm mb-2" maxlength="{{ \App\Http\Requests\Posts\StoreCommentRequest::MAX_LENGTH }}"
                          placeholder="Reply to {{ $comment->user->name }}…" aria-label="Your reply" required></textarea>
                <button type="submit" class="btn btn-sm btn-accent">Post reply</button>
                <button type="button" class="btn btn-sm btn-link text-secondary" @click="replyTo = null">Cancel</button>
            </form>
        @endcan

        @if ($comment->replies->isNotEmpty())
            <div class="comment-replies mt-3">
                @foreach ($comment->replies as $reply)
                    @include('posts.partials.comment', ['comment' => $reply, 'post' => $post, 'canReply' => false])
                @endforeach
            </div>
        @endif
    @endif
</article>
