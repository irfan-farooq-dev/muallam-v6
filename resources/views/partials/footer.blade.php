<footer id="footer" class="footer">
    <div class="container footer-top">
        <div class="row gy-4">
            <div class="col-lg-5 col-md-12 footer-about">
                <a href="{{ route('home') }}" class="logo d-flex align-items-center">
                    <span class="sitename">{{ config('app.name') }}</span>
                </a>
                <p class="mt-3 mb-2">Articles and tutorials written by people who teach what they know.</p>
                <p class="mb-0 small">
                    <i class="bi bi-envelope me-1"></i><a href="mailto:{{ config('muallam.contact_email') }}">{{ config('muallam.contact_email') }}</a>
                </p>
            </div>

            <div class="col-lg-2 col-6 footer-links ms-lg-auto">
                <h4>Explore</h4>
                <ul>
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('categories.index') }}">Categories</a></li>
                    <li><a href="{{ route('search') }}">Search</a></li>
                    @guest
                        <li><a href="{{ route('login') }}">Start writing</a></li>
                    @endguest
                </ul>
            </div>

            <div class="col-lg-2 col-6 footer-links">
                <h4>Help</h4>
                <ul>
                    <li><a href="{{ route('contact') }}">Contact us</a></li>
                    <li><a href="{{ route('privacy') }}">Privacy policy</a></li>
                    <li><a href="{{ route('terms') }}">Terms &amp; conditions</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container copyright text-center mt-4">
        <p class="mb-0">&copy; {{ now()->year }} <strong class="px-1 sitename">{{ config('app.name') }}</strong> All rights reserved.</p>
    </div>
</footer>
