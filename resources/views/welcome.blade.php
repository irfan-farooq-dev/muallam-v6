<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="{{ asset('frontend/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <script defer src="{{ asset('vendor/alpinejs/alpine.min.js') }}"></script>
</head>
<body class="bg-light">
    <main class="container py-5 text-center">
        <h1 class="mb-3">{{ config('app.name') }}</h1>
        <p class="text-muted">Setup placeholder. ZenBlog layouts arrive with the auth module.</p>
        <div x-data="{ ok: false }" x-init="ok = true">
            <span class="badge" :class="ok ? 'text-bg-success' : 'text-bg-secondary'"
                  x-text="ok ? 'Bootstrap + Alpine loaded' : 'Alpine not loaded'">Alpine not loaded</span>
        </div>
    </main>
</body>
</html>
