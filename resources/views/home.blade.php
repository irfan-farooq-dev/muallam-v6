@extends('layouts.app')

@section('title', 'Home')

@section('content')
    @if ($posts->onFirstPage())
        <section class="section home-hero light-background">
            <div class="container text-center">
                <h2 class="display-5 fw-semibold mb-3">Learn something new today</h2>
                <p class="lead text-secondary mb-4 mx-auto home-hero-lead">
                    Muallam is a place to read and write clear, practical articles on the topics you care about.
                </p>
                <form method="GET" action="{{ route('search') }}" class="home-search mx-auto mb-3" role="search">
                    <div class="input-group input-group-lg">
                        <input type="search" name="q" class="form-control" placeholder="Search articles" aria-label="Search articles">
                        <button class="btn btn-accent" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
                @guest
                    <a href="{{ route('register') }}" class="btn btn-outline-accent">Start writing</a>
                @endguest
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            @if ($categories !== [])
                <div class="category-chips d-flex flex-wrap gap-2 mb-4">
                    @foreach ($categories as $chip)
                        <a href="{{ route('categories.show', $chip['slug']) }}" class="badge rounded-pill category-chip">{{ $chip['name'] }}</a>
                    @endforeach
                </div>
            @endif

            <div class="d-flex align-items-baseline justify-content-between mb-4">
                <h3 class="mb-0">Latest posts</h3>
            </div>

            @if ($posts->isEmpty())
                <div class="empty-state text-center text-secondary py-5">
                    <i class="bi bi-journal-text display-6 d-block mb-3"></i>
                    No posts yet. Published posts will appear here.
                </div>
            @else
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
