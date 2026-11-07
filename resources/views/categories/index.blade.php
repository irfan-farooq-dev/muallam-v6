@extends('layouts.app')

@section('title', 'Categories')
@section('meta_description', 'Browse Muallam articles by topic.')

@section('content')
    <section class="section">
        <div class="container">
            <h2 class="h3 mb-4">Categories</h2>

            @if ($categories->isEmpty())
                <div class="empty-state text-center text-secondary py-5">No categories yet.</div>
            @else
                <div class="row g-3">
                    @foreach ($categories as $category)
                        <div class="col-sm-6 col-lg-4">
                            <a href="{{ route('categories.show', $category) }}" class="card category-card h-100 text-decoration-none">
                                <div class="card-body">
                                    <h3 class="h5 mb-1">{{ $category->name }}</h3>
                                    @if ($category->description)
                                        <p class="text-secondary small mb-2">{{ $category->description }}</p>
                                    @endif
                                    <span class="small text-secondary" data-posts-count="{{ $category->slug }}">
                                        {{ trans_choice('{0} No posts yet|{1} :count post|[2,*] :count posts', $category->posts_count) }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
