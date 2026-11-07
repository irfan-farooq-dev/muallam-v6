@extends('layouts.app')

@section('title', $post->title)
@section('meta_description', \Illuminate\Support\Str::limit($post->summary ?: strip_tags($post->body), 155))

@section('canonical', route('posts.show', $post))
@section('og_type', 'article')
@if ($post->featuredImageUrl())
    @section('og_image', $post->featuredImageUrl())
@endif
@unless ($isPublic)
    @section('robots', 'noindex, nofollow')
@endunless

@if ($isPublic)
    @push('meta')
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
        @if ($post->category)
            <meta property="article:section" content="{{ $post->category->name }}">
        @endif
        {{-- Built in PostController: Blade would treat "@context" in this file as one of its own commands. --}}
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
@endif

@section('content')
    @if ($isInvitedReader)
        <div class="preview-banner">
            <div class="container py-2">
                <i class="bi bi-shield-lock me-1"></i>
                <strong>Shared with you privately</strong> by {{ $post->user->name }}. Please don't pass on the link or the password.
            </div>
        </div>
    @elseif (! $isPublic)
        <div class="preview-banner">
            <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                <span>
                    <i class="bi bi-eye-slash me-1"></i>
                    <strong>Not public.</strong>
                    @if ($post->isScheduled())
                        Scheduled to go live on {{ $post->published_at->format('j M Y, g:i a') }}.
                    @elseif ($post->status === \App\Enums\PostStatus::Restricted)
                        This post is restricted: only people you invite (with the password) can read it.
                    @else
                        This post is {{ strtolower($post->status->label()) }}, so only you{{ auth()->user()->isAdmin() && ! auth()->user()->is($post->user) ? ' (as an admin) and its author' : '' }} can see it.
                    @endif
                </span>
                @can('update', $post)
                    <a href="{{ route('posts.edit', $post) }}{{ $post->status === \App\Enums\PostStatus::Restricted ? '#sharing' : '' }}" class="btn btn-sm btn-outline-accent">
                        {{ $post->status === \App\Enums\PostStatus::Restricted ? 'Manage sharing' : 'Edit post' }}
                    </a>
                @endcan
            </div>
        </div>
    @endif

    <section class="section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <x-breadcrumbs :items="$post->category
                            ? [[$post->category->name, route('categories.show', $post->category)], [$post->title]]
                            : [[$post->title]]" />
                        @if ($isPublic)
                            @can('update', $post)
                                <a href="{{ route('posts.edit', $post) }}" class="btn btn-sm btn-outline-accent"><i class="bi bi-pencil me-1"></i>Edit post</a>
                            @endcan
                        @endif
                    </div>

                    @php($post->published_at ??= $post->updated_at)
                    @include('posts.partials.article', ['post' => $post])

                    <div class="post-footer d-flex flex-wrap align-items-center gap-3 mt-5 pt-3 small text-secondary">
                        <span title="Views"><i class="bi bi-eye me-1"></i>{{ number_format($post->views_count) }} {{ \Illuminate\Support\Str::plural('view', $post->views_count) }}</span>
                        @include('posts.partials.like-button', ['post' => $post, 'isPublic' => $isPublic, 'likedByMe' => $likedByMe])
                        <a href="#comments" class="text-secondary" title="Comments"><i class="bi bi-chat me-1"></i>{{ number_format($post->comments_count) }}</a>
                    </div>

                    <div class="author-box d-flex gap-3 align-items-start mt-4 p-4">
                        <x-user-avatar :user="$post->user" :size="56" />
                        <div>
                            <div class="small text-secondary">Written by</div>
                            <div class="fw-semibold mb-1">{{ $post->user->name }}</div>
                            @if ($post->user->is_bio_public && $post->user->bio)
                                <p class="small text-secondary mb-2">{{ \Illuminate\Support\Str::limit($post->user->bio, 200) }}</p>
                            @endif
                            <a href="{{ route('users.posts', $post->user->slug) }}" class="small">All posts by {{ $post->user->name }}</a>
                            @if ($post->user->is_bio_public)
                                <span class="text-secondary small mx-1">&middot;</span>
                                <a href="{{ route('users.bio', $post->user->slug) }}" class="small">About</a>
                            @endif
                        </div>
                    </div>

                    @if ($isPublic)
                        @include('posts.partials.comments', ['post' => $post, 'comments' => $comments])
                    @endif

                    @if ($moreFromAuthor->isNotEmpty())
                        <div class="mt-4">
                            <h2 class="h6 text-uppercase text-secondary mb-2">More from {{ $post->user->name }}</h2>
                            <ul class="list-unstyled mb-0">
                                @foreach ($moreFromAuthor as $other)
                                    <li class="mb-1"><a href="{{ route('posts.show', $other) }}">{{ $other->title }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @if ($related->isNotEmpty())
                <h2 class="h5 mt-5 mb-3">More in {{ $post->category->name }}</h2>
                <div class="row g-3">
                    @foreach ($related as $relatedPost)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.post-card', ['post' => $relatedPost])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
