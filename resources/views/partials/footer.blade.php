<footer id="footer" class="footer">
    <div class="container footer-top">
        <div class="row gy-4">
            <div class="col-lg-6 col-md-8 footer-about">
                <a href="{{ route('home') }}" class="logo d-flex align-items-center">
                    <span class="sitename">{{ config('app.name') }}</span>
                </a>
                <p class="mt-3 mb-0">Articles and tutorials written by people who teach what they know.</p>
            </div>

            <div class="col-lg-3 col-md-4 footer-links ms-lg-auto">
                <h4>Explore</h4>
                <ul>
                    <li><a href="{{ route('home') }}">Home</a></li>
                    @guest
                        <li><a href="{{ route('login') }}">Start writing</a></li>
                    @endguest
                </ul>
            </div>
        </div>
    </div>

    <div class="container copyright text-center mt-4">
        <p class="mb-0">&copy; {{ now()->year }} <strong class="px-1 sitename">{{ config('app.name') }}</strong> All rights reserved.</p>
    </div>
</footer>
