{{-- Compact post row (small picture, title, author and date) linking to the post. Expects $post with user loaded. --}}
@php($miniImage = $post->featuredImageUrl())
<a href="{{ route('posts.show', $post) }}" class="post-mini d-flex gap-3 align-items-start">
    @if ($miniImage)
        <img src="{{ $miniImage }}" alt="" class="post-mini-image" loading="lazy">
    @else
        <span class="post-mini-image post-mini-placeholder" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
    @endif
    <span class="d-block" style="min-width: 0;">
        <span class="post-mini-title d-block">{{ $post->title }}</span>
        <span class="d-block small text-secondary mt-1">
            {{ $post->user->name }} &middot;
            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('j M Y') }}</time>
        </span>
    </span>
</a>
