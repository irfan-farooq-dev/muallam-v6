{{-- One post in a list. Expects $post with its user loaded. Optional $imageUrl overrides the featured image (used by the preview). --}}
@php
    $postUrl = $post->exists && Route::has('posts.show') ? route('posts.show', $post) : null;
    $author = $post->user;
    $cardImage = $imageUrl ?? $post->featuredImageUrl();
@endphp

<article class="post-card card h-100">
    @if ($cardImage)
        <img src="{{ $cardImage }}" alt="" class="card-img-top post-card-image" loading="lazy">
    @endif
    <div class="card-body d-flex flex-column">
        <h3 class="h5 post-card-title mb-2">
            @if ($postUrl)
                <a href="{{ $postUrl }}">{{ $post->title }}</a>
            @else
                {{ $post->title }}
            @endif
        </h3>

        @if ($post->summary)
            <p class="text-secondary mb-3">{{ \Illuminate\Support\Str::limit($post->summary, 160) }}</p>
        @endif

        <div class="post-card-meta mt-auto d-flex flex-wrap align-items-center gap-2 small text-secondary">
            <x-user-avatar :user="$author" :size="24" />
            @if ($author->exists)
                <a href="{{ route('users.posts', $author->slug) }}" class="text-secondary">{{ $author->name }}</a>
            @else
                <span>{{ $author->name }}</span>
            @endif
            <span>&middot;</span>
            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('j M Y') }}</time>
            <span class="ms-auto d-flex gap-3">
                <span title="Likes"><i class="bi bi-hand-thumbs-up me-1"></i>{{ number_format($post->likes_count) }}</span>
                <span title="Comments"><i class="bi bi-chat me-1"></i>{{ number_format($post->comments_count) }}</span>
            </span>
        </div>
    </div>
</article>
