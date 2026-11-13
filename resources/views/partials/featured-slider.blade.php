{{-- Home page slider: newest posts with a featured image. Expects $featured (at least two posts). Started by the script in home.blade.php. --}}
<div class="featured-slider swiper mb-5" data-loop="{{ $featured->count() >= 3 ? 1 : 0 }}"
     role="region" aria-roledescription="carousel" aria-label="Featured posts">
    <div class="swiper-wrapper">
        @foreach ($featured as $slidePost)
            <div class="swiper-slide" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $loop->count }}">
                <a href="{{ route('posts.show', $slidePost) }}" class="featured-slide">
                    <img src="{{ $slidePost->featuredImageUrl() }}" alt="" class="featured-slide-image" @unless ($loop->first) loading="lazy" @endunless>
                    <div class="featured-slide-content">
                        @if ($slidePost->category)
                            <span class="featured-slide-category">{{ $slidePost->category->name }}</span>
                        @endif
                        <h3 class="featured-slide-title">{{ $slidePost->title }}</h3>
                        @if ($slidePost->cardSummary(150))
                            <p class="featured-slide-summary d-none d-md-block">{{ $slidePost->cardSummary(150) }}</p>
                        @endif
                        <div class="featured-slide-meta">
                            {{ $slidePost->user->name }} &middot;
                            <x-local-time :at="$slidePost->published_at" format="date" />
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
    <div class="swiper-pagination"></div>
    <button type="button" class="swiper-button-prev" aria-label="Previous slide"></button>
    <button type="button" class="swiper-button-next" aria-label="Next slide"></button>
</div>
