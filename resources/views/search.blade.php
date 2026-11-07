@extends('layouts.app')

@section('title', $query !== '' ? "Search: {$query}" : 'Search')

@push('styles')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <section class="section">
        <div class="container">
            <x-breadcrumbs class="mb-2" :items="[['Search']]" />
            <h2 class="h3 mb-3">Search</h2>

            <form method="GET" action="{{ route('search') }}" class="mb-4" role="search" style="max-width: 640px;">
                <div class="input-group">
                    <input type="search" name="q" value="{{ $query }}" class="form-control" placeholder="Search articles" aria-label="Search articles" autofocus>
                    <button class="btn btn-accent" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                </div>
            </form>

            @if ($posts === null)
                <p class="text-secondary">Type at least {{ \App\Http\Controllers\SearchController::MIN_LENGTH }} letters to search titles, summaries and post text.</p>
            @elseif ($posts->isEmpty())
                <div class="empty-state text-center text-secondary py-5">
                    <i class="bi bi-search display-6 d-block mb-3"></i>
                    No posts match "{{ $query }}". Try fewer or different words.
                </div>
            @else
                <p class="text-secondary">{{ trans_choice('{1} 1 post found|[2,*] :count posts found', $posts->total()) }} for "{{ $query }}".</p>
                <div class="row g-3">
                    @foreach ($posts as $post)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.post-card', ['post' => $post])
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $posts->links() }}</div>
            @endif
        </div>
    </section>
@endsection
