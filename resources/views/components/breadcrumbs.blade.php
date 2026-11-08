{{--
    "You are here" trail for public pages. Home is always first; the last item is the current page (not a link).
    Usage: <x-breadcrumbs :items="[['Categories', route('categories.index')], [$category->name]]" />
    Pass :ends-with-current-page="false" when the page itself is not in the trail (every item then stays a link).
--}}
@props(['items' => [], 'endsWithCurrentPage' => true])

@php
    // Each item is [label, url]; the url may be left out (current page).
    $trail = array_map(fn (array $item): array => [$item[0], $item[1] ?? null], [['Home', route('home')], ...$items]);
@endphp

<nav aria-label="breadcrumb" {{ $attributes->class(['site-breadcrumbs']) }}>
    <ol class="breadcrumb small mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach ($trail as [$label, $url])
            @php($isCurrentPage = $endsWithCurrentPage && $loop->last)
            <li @class(['breadcrumb-item', 'active' => $isCurrentPage])
                itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"
                @if ($isCurrentPage) aria-current="page" @endif>
                @if ($url && ! $isCurrentPage)
                    <a href="{{ $url }}" itemprop="item"><span itemprop="name">{{ $label }}</span></a>
                @else
                    <span itemprop="name">{{ \Illuminate\Support\Str::limit($label, 60) }}</span>
                @endif
                <meta itemprop="position" content="{{ $loop->iteration }}">
            </li>
        @endforeach
    </ol>
</nav>
