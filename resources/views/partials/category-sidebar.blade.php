{{--
    Right-hand column on the home and post pages: the category list, with the current one highlighted.
    Optional $activeCategory (slug). Room is left below for ads later.
--}}
@php
    $activeCategory ??= null;
@endphp

<aside class="site-sidebar" aria-label="Categories">
    @if ($sidebarCategories !== [])
        <div class="sidebar-box">
            <h2 class="sidebar-title">Categories</h2>
            <ul class="list-unstyled mb-0 sidebar-categories">
                @foreach ($sidebarCategories as $sidebarCategory)
                    @php($isActive = $activeCategory === $sidebarCategory['slug'])
                    <li>
                        <a href="{{ route('categories.show', $sidebarCategory['slug']) }}"
                           @class(['active' => $isActive]) @if ($isActive) aria-current="true" @endif>
                            {{ $sidebarCategory['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('categories.index') }}" class="d-inline-block small mt-3">All categories</a>
        </div>
    @endif
</aside>
