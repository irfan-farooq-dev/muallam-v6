{{-- Standalone page: no header, session or database access, so it still renders when those are the cause of the error. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Back soon | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('frontend/vendor/bootstrap/css/bootstrap.min.css') }}">
</head>
<body class="bg-light">
    <main class="container text-center py-5 mt-5">
        <div class="display-1 fw-bold text-secondary mb-3">503</div>
        <h1 class="h3 mb-3">Back soon</h1>
        <p class="lead text-secondary mb-4">Muallam is down for a short maintenance. Please check back shortly.</p>
        <a href="{{ url('/') }}" class="btn btn-dark px-4">Back to home</a>
    </main>
</body>
</html>
