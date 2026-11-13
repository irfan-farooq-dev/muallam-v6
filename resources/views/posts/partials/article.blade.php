{{-- The full post as readers see it. Expects $post (user and category loaded); optional $imageUrl overrides the featured image. --}}
@php
    $articleImage = $imageUrl ?? $post->featuredImageUrl();
    $author = $post->user;
@endphp

<article class="post-article">
    <header class="mb-4">
        <h1 class="post-article-title mb-3">{{ $post->title }}</h1>
        @if ($post->summary)
            <p class="lead text-secondary">{{ $post->summary }}</p>
        @endif
        <div class="d-flex align-items-center gap-2 text-secondary small">
            <x-user-avatar :user="$author" :size="32" />
            @if ($author->is_bio_public)
                <a href="{{ route('users.bio', $author->slug) }}" class="text-secondary fw-semibold">{{ $author->name }}</a>
            @else
                <span class="fw-semibold">{{ $author->name }}</span>
            @endif
            <span>&middot;</span>
            <x-local-time :at="$post->published_at" format="long-date" />
        </div>
    </header>

    @if ($articleImage)
        <img src="{{ $articleImage }}" alt="" class="post-article-image mb-4">
    @endif

    <div class="post-content">
        {!! $post->body !!}
    </div>
</article>
