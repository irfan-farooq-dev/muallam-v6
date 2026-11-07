@extends('layouts.app')

@section('title', 'Preview: '.$post->title)

@push('styles')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="preview-banner">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
            <span>
                <i class="bi bi-eye me-1"></i><strong>Preview.</strong> This is how readers will see your post.
                Nothing has been saved{{ $isScheduled ? ', and it is scheduled for '.$post->published_at->format('j M Y, g:i a') : '' }}.
            </span>
            <button type="button" class="btn btn-sm btn-outline-accent" onclick="window.close()">Close preview</button>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <h2 class="h6 text-uppercase text-secondary mb-3">On the home page and category lists</h2>
            <div class="row mb-5">
                <div class="col-md-6 col-lg-4">
                    @include('partials.post-card', ['post' => $post, 'imageUrl' => $imageUrl])
                </div>
            </div>

            <h2 class="h6 text-uppercase text-secondary mb-3">The post page</h2>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    @include('posts.partials.article', ['post' => $post, 'imageUrl' => $imageUrl])
                </div>
            </div>
        </div>
    </section>
@endsection
