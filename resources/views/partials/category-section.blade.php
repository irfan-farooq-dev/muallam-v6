{{-- One category block on the home page: the newest post large, the next ones as compact rows. Expects $category and $posts. --}}
<section class="home-category-section mt-5" data-aos="fade-up" aria-labelledby="category-section-{{ $category->slug }}">
    <div class="home-section-heading d-flex align-items-baseline justify-content-between mb-3">
        <h3 id="category-section-{{ $category->slug }}" class="h4 mb-0">{{ $category->name }}</h3>
        <a href="{{ route('categories.show', $category) }}" class="small">See all <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3">
        <div class="col-md-7">
            @include('partials.post-card', ['post' => $posts->first()])
        </div>
        <div class="col-md-5">
            <ul class="list-unstyled mb-0 post-mini-list">
                @foreach ($posts->skip(1) as $miniPost)
                    <li>@include('partials.post-mini', ['post' => $miniPost])</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
