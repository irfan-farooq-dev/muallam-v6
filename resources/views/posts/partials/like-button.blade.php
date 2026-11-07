{{--
    Like button under a post.
    - Signed-in readers: toggles instantly (Alpine + JSON), and still works as a plain form without JavaScript.
    - Guests: goes through sign-in and comes back to the post.
    - The author, or a post that is not public: shows the count only.
--}}
@php
    $count = (int) $post->likes_count;
@endphp

@auth
    @can('like', $post)
        <form method="POST" action="{{ route('posts.like', $post) }}" class="d-inline"
              x-data="likeButton(@js(['liked' => $likedByMe, 'count' => $count, 'url' => route('posts.like', $post)]))"
              @submit.prevent="toggle()">
            @csrf
            <button type="submit" class="btn btn-sm like-button {{ $likedByMe ? 'is-liked' : '' }}" :class="{ 'is-liked': liked }"
                    aria-pressed="{{ $likedByMe ? 'true' : 'false' }}" :aria-pressed="liked.toString()" :disabled="busy">
                <i class="bi {{ $likedByMe ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' }}"
                   :class="{ 'bi-hand-thumbs-up-fill': liked, 'bi-hand-thumbs-up': !liked }"></i>
                <span x-text="liked ? 'Liked' : 'Like'">{{ $likedByMe ? 'Liked' : 'Like' }}</span>
                <span class="like-count" x-text="count.toLocaleString()">{{ number_format($count) }}</span>
            </button>
        </form>
    @else
        <span title="{{ auth()->user()->is($post->user) ? 'Likes on your post' : 'Likes' }}">
            <i class="bi bi-hand-thumbs-up me-1"></i><span data-likes-count>{{ number_format($count) }}</span>
        </span>
    @endcan
@else
    @if ($isPublic)
        <a href="{{ route('posts.like.sign-in', $post) }}" class="btn btn-sm like-button" title="Sign in to like this post">
            <i class="bi bi-hand-thumbs-up"></i> Like <span class="like-count">{{ number_format($count) }}</span>
        </a>
    @else
        <span><i class="bi bi-hand-thumbs-up me-1"></i>{{ number_format($count) }}</span>
    @endif
@endauth

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('likeButton', ({ liked, count, url }) => ({
                    liked,
                    count,
                    busy: false,

                    async toggle() {
                        if (this.busy) return;
                        this.busy = true;

                        // Show the change straight away; undo it if the server says no.
                        const before = { liked: this.liked, count: this.count };
                        this.liked = !this.liked;
                        this.count += this.liked ? 1 : -1;

                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                            });
                            if (!response.ok) throw new Error(response.status);
                            const result = await response.json();
                            this.liked = result.liked;
                            this.count = result.likes_count;
                        } catch {
                            this.liked = before.liked;
                            this.count = before.count;
                        } finally {
                            this.busy = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
