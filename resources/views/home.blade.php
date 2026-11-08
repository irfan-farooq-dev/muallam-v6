@extends('layouts.app')

@section('title', 'Home')

@if ($featured->isNotEmpty())
    @push('styles')
        <link rel="stylesheet" href="{{ asset('frontend/vendor/swiper/swiper-bundle.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('frontend/vendor/swiper/swiper-bundle.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const slider = document.querySelector('.featured-slider');
                if (!slider || typeof Swiper === 'undefined') {
                    return;
                }

                // No automatic sliding for visitors who asked their system for less motion.
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                new Swiper(slider, {
                    loop: slider.dataset.loop === '1',
                    speed: 600,
                    autoplay: reduceMotion ? false : { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false },
                    pagination: { el: slider.querySelector('.swiper-pagination'), clickable: true },
                    navigation: { nextEl: slider.querySelector('.swiper-button-next'), prevEl: slider.querySelector('.swiper-button-prev') },
                    keyboard: { enabled: true, onlyInViewport: true },
                    a11y: { enabled: true },
                });
            });
        </script>
    @endpush
@endif

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
                    <a href="{{ route('login') }}" class="btn btn-outline-accent">Start writing</a>
                @endguest
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-9">
                    @if ($featured->isNotEmpty())
                        @include('partials.featured-slider', ['featured' => $featured])
                    @endif

                    <div class="home-section-heading d-flex align-items-baseline justify-content-between mb-4">
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
                                <div class="col-md-6 col-xl-4" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 100 }}">
                                    @include('partials.post-card', ['post' => $post])
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">{{ $posts->links() }}</div>
                    @endif

                    @foreach ($categorySections as $section)
                        @include('partials.category-section', $section)
                    @endforeach
                </div>

                <div class="col-lg-3">
                    @include('partials.category-sidebar')
                </div>
            </div>
        </div>
    </section>
@endsection
