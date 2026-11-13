{{--
    One post in a list. Expects $post with its user loaded. Optional $imageUrl overrides the featured image (used by the preview).
    Above the line (image, title, summary) opens the post; below it (author, date, counts) opens the author.
--}}
@php
    $postUrl = $post->exists && Route::has('posts.show') ? route('posts.show', $post) : null;
    $author = $post->user;
    $authorUrl = $author->exists
        ? ($author->is_bio_public ? route('users.bio', $author->slug) : route('users.posts', $author->slug))
        : null;
    $cardImage = $imageUrl ?? $post->featuredImageUrl();
@endphp

<article class="post-card card h-100">
    <{{ $postUrl ? 'a' : 'div' }} @if ($postUrl) href="{{ $postUrl }}" @endif class="post-card-main d-flex flex-column flex-grow-1">
        @if ($cardImage)
            <img src="{{ $cardImage }}" alt="" class="card-img-top post-card-image" loading="lazy">
        @endif
        <div class="card-body pb-3">
            <h3 class="h5 post-card-title mb-2">{{ $post->title }}</h3>

            @if ($post->cardSummary(160))
                <p class="text-secondary mb-0">{{ $post->cardSummary(160) }}</p>
            @endif
        </div>
    </{{ $postUrl ? 'a' : 'div' }}>

    <{{ $authorUrl ? 'a' : 'div' }} @if ($authorUrl) href="{{ $authorUrl }}" title="About {{ $author->name }}" @endif
        class="post-card-author d-flex flex-wrap align-items-center gap-2 small text-secondary">
        <x-user-avatar :user="$author" :size="24" />
        <span class="post-card-author-name">{{ $author->name }}</span>
        <span>&middot;</span>
        <x-local-time :at="$post->published_at" format="date" />
        <span class="ms-auto d-flex gap-3">
            <span title="Likes"><i class="bi bi-hand-thumbs-up me-1"></i>{{ number_format($post->likes_count) }}</span>
            <span title="Comments"><i class="bi bi-chat me-1"></i>{{ number_format($post->comments_count) }}</span>
        </span>
    </{{ $authorUrl ? 'a' : 'div' }}>
</article>
