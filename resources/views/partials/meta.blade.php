{{--
    Search and link-preview tags for every page. Pages can set:
      @section('title'), @section('meta_description'), @section('robots') (e.g. "noindex, nofollow"),
      @section('canonical'), @section('og_type') ("article" for posts), @section('og_image') (absolute URL),
    and @push('meta') for anything extra (article dates, structured data).
--}}
@php
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' | '.config('app.name') : config('app.name');
    $description = trim($__env->yieldContent('meta_description')) ?: 'Muallam: articles and tutorials written by people who teach what they know.';
    $page = (int) request()->query('page', 1);
    $canonical = trim($__env->yieldContent('canonical')) ?: url()->current().($page > 1 ? '?page='.$page : '');
    $image = trim($__env->yieldContent('og_image')) ?: asset('img/og-default.png');
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="@yield('robots', 'index, follow')">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="{{ $pageTitle !== '' ? $pageTitle : config('app.name') }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle !== '' ? $pageTitle : config('app.name') }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">

@stack('meta')
