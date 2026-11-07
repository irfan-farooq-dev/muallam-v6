{{-- Comments under a public post. Expects $post and $comments (from PostComments::thread). --}}
<section id="comments" class="comments mt-5" x-data="{ replyTo: @js((int) old('parent_id') ?: null) }">
    <h2 class="h5 mb-3">
        Comments
        <span class="text-secondary fw-normal">({{ number_format($post->comments_count) }})</span>
    </h2>

    @if ($errors->comment->any())
        <div class="alert alert-danger small">{{ $errors->comment->first() }}</div>
    @endif

    @if (! $post->allow_comments)
        <p class="text-secondary small"><i class="bi bi-chat-square-dots me-1"></i>Comments are turned off for this post.</p>
    @else
        @auth
            @can('create', [\App\Models\Comment::class, $post])
                <form method="POST" action="{{ route('comments.store', $post) }}" class="comment-form mb-4">
                    @csrf
                    <div class="d-flex gap-2">
                        <x-user-avatar :user="auth()->user()" :size="36" />
                        <div class="flex-grow-1">
                            <textarea name="body" rows="3" class="form-control mb-2" maxlength="{{ \App\Http\Requests\Posts\StoreCommentRequest::MAX_LENGTH }}"
                                      placeholder="Share your thoughts…" aria-label="Your comment" required>{{ old('parent_id') ? '' : old('body') }}</textarea>
                            <button type="submit" class="btn btn-accent btn-sm">Post comment</button>
                        </div>
                    </div>
                </form>
            @else
                @unless (auth()->user()->hasVerifiedEmail())
                    <p class="small text-secondary">Please <a href="{{ route('verification.notice') }}">verify your email</a> to join the conversation.</p>
                @endunless
            @endcan
        @else
            <p class="small text-secondary"><a href="{{ route('comments.sign-in', $post) }}">Sign in</a> to join the conversation.</p>
        @endauth
    @endif

    @if ($comments->isEmpty())
        @if ($post->allow_comments)
            <p class="text-secondary small mb-0">No comments yet. Be the first to share your thoughts.</p>
        @endif
    @else
        <div class="comment-list">
            @foreach ($comments as $comment)
                @include('posts.partials.comment', ['comment' => $comment, 'post' => $post, 'canReply' => true])
            @endforeach
        </div>
    @endif
</section>
