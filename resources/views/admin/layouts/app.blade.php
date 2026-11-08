{{--
    Admin panel layout (Sneat template, recoloured to the site's blue and Poppins).
    Pages set @section('title') and @section('content'); optional @section('page_actions') and @push('scripts').
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style layout-menu-fixed" dir="ltr"
      data-theme="theme-default" data-assets-path="{{ asset('admin') }}/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ trim($__env->yieldContent('title')) ?: 'Admin' }} | {{ config('app.name') }} admin</title>

    <link rel="icon" href="{{ asset('frontend/img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('shared/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/vendor/css/core-muallam.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/vendor/css/theme-muallam.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
    @stack('styles')

    <script src="{{ asset('admin/vendor/js/helpers.js') }}"></script>
    <script defer src="{{ asset('vendor/alpinejs/alpine.min.js') }}"></script>
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('admin.layouts.partials.sidebar')

            <div class="layout-page">
                @include('admin.layouts.partials.navbar')

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <x-flash />

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                            <h4 class="fw-bold mb-0">@yield('title')</h4>
                            @yield('page_actions')
                        </div>

                        @yield('content')
                    </div>

                    @include('admin.layouts.partials.footer')
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        {{-- Dims the page while the menu is open on small screens. --}}
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="{{ asset('admin/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('admin/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('admin/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('admin/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('admin/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('admin/js/main.js') }}"></script>
    @stack('scripts')
</body>
</html>
