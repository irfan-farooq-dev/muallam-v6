<footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column small text-muted">
        <div>&copy; {{ now()->year }} {{ config('app.name') }} admin</div>
        <div>Signed in as {{ auth()->user()->name }}</div>
    </div>
</footer>
