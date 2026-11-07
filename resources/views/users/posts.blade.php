@extends('layouts.app')

@section('title', "Posts by {$author->name}")
@section('meta_description', "Articles written by {$author->name} on Muallam.")

@section('content')
    <section class="section">
        <div class="container">
            <x-breadcrumbs class="mb-3" :items="[[$author->name]]" />
            <div class="d-flex align-items-center gap-3 mb-4">
                <x-user-avatar :user="$author" :size="72" />
                <div>
                    <h2 class="h3 mb-1">{{ $author->name }}</h2>
                    <div class="text-secondary small">
                        {{ trans_choice('{0} No posts yet|{1} 1 post|[2,*] :count posts', $posts->total()) }}
                        &middot; member since {{ $author->created_at->format('F Y') }}
                        @if ($author->is_bio_public)
                            &middot; <a href="{{ route('users.bio', $author->slug) }}">About {{ $author->name }}</a>
                        @endif
                    </div>
                </div>
            </div>

            @if ($posts->isEmpty())
                <div class="empty-state text-center text-secondary py-5">{{ $author->name }} hasn't published anything yet.</div>
            @else
                <div class="row g-3">
                    @foreach ($posts as $post)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.post-card', ['post' => $post->setRelation('user', $author)])
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $posts->links() }}</div>
            @endif
        </div>
    </section>
@endsection
