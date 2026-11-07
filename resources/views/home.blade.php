@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <section class="section home-hero light-background">
        <div class="container text-center">
            <h2 class="display-5 fw-semibold mb-3">Learn something new today</h2>
            <p class="lead text-secondary mb-4 mx-auto home-hero-lead">
                Muallam is a place to read and write clear, practical articles on programming, databases, careers and more.
            </p>
            @guest
                <a href="{{ Route::has('register') ? route('register') : '#' }}" class="btn btn-accent btn-lg px-4">Start writing</a>
            @endguest
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title-row d-flex align-items-baseline justify-content-between mb-4">
                <h3 class="mb-0">Latest posts</h3>
            </div>
            <div class="empty-state text-center text-secondary py-5">
                <i class="bi bi-journal-text display-6 d-block mb-3"></i>
                No posts yet. Published posts will appear here.
            </div>
        </div>
    </section>
@endsection
