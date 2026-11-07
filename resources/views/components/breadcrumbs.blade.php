{{--
    "You are here" trail for public pages. Home is always first; the last item is the current page (not a link).
    Usage: <x-breadcrumbs :items="[['Categories', route('categories.index')], [$category->name]]" />
--}}
@props(['items' => []])

@php
    // Each item is [label, url]; the url may be left out (current page).
    $trail = array_map(fn (array $item): array => [$item[0], $item[1] ?? null], [['Home', route('home')], ...$items]);
@endphp

<nav aria-label="breadcrumb" {{ $attributes->class(['site-breadcrumbs']) }}>
    <ol class="breadcrumb small mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach ($trail as [$label, $url])
            <li @class(['breadcrumb-item', 'active' => $loop->last])
                itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"
                @if ($loop->last) aria-current="page" @endif>
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" itemprop="item"><span itemprop="name">{{ $label }}</span></a>
                @else
                    <span itemprop="name">{{ \Illuminate\Support\Str::limit($label, 60) }}</span>
                @endif
                <meta itemprop="position" content="{{ $loop->iteration }}">
            </li>
        @endforeach
    </ol>
</nav>
