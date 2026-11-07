<header id="header" class="header d-flex align-items-center sticky-top">
    <div class="container position-relative d-flex align-items-center justify-content-between">

        <a href="{{ route('home') }}" class="logo d-flex align-items-center me-auto me-xl-0">
            <h1 class="sitename">{{ config('app.name') }}</h1>
        </a>

        <nav id="navmenu" class="navmenu">
            <ul>
                <li><a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>Home</a></li>

                @if ($navCategories !== [])
                    <li class="dropdown">
                        <a href="{{ route('categories.index') }}" @class(['active' => request()->routeIs('categories.*')])>
                            <span>Categories</span> <i class="bi bi-chevron-down toggle-dropdown"></i>
                        </a>
                        <ul>
                            @foreach ($navCategories as $navCategory)
                                <li><a href="{{ route('categories.show', $navCategory['slug']) }}">{{ $navCategory['name'] }}</a></li>
                            @endforeach
                            <li><a href="{{ route('categories.index') }}" class="fw-semibold">All categories</a></li>
                        </ul>
                    </li>
                @endif

                @guest
                    {{-- On small screens the account links live inside the menu. --}}
                    <li class="d-sm-none"><a href="{{ Route::has('login') ? route('login') : '#' }}">Sign in</a></li>
                    <li class="d-sm-none"><a href="{{ Route::has('register') ? route('register') : '#' }}">Sign up</a></li>
                @endguest
            </ul>
            <i class="mobile-nav-toggle d-xl-none bi bi-list" role="button" aria-label="Toggle navigation"></i>
        </nav>

        <div class="header-account d-flex align-items-center gap-2 ms-3">
            @guest
                <a href="{{ Route::has('login') ? route('login') : '#' }}" class="btn btn-outline-accent btn-sm d-none d-sm-inline-block">Sign in</a>
                <a href="{{ Route::has('register') ? route('register') : '#' }}" class="btn btn-accent btn-sm d-none d-sm-inline-block">Sign up</a>
            @endguest

            @auth
                <div class="dropdown">
                    <button class="btn btn-link header-account-toggle d-flex align-items-center gap-2 p-0" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <x-user-avatar :user="auth()->user()" :size="36" />
                        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header">{{ auth()->user()->email }}</li>
                        @if (Route::has('dashboard'))
                            <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        @endif
                        @if (Route::has('profile.edit'))
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                        @endif
                        @if (Route::has('logout'))
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                                </form>
                            </li>
                        @endif
                    </ul>
                </div>
            @endauth
        </div>

    </div>
</header>
