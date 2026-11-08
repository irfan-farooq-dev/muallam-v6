{{-- Admin menu. Items appear automatically once their admin pages exist. --}}
@php
    $adminLinks = [
        ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'bx-home-circle', 'label' => 'Dashboard'],
        ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'bx-user', 'label' => 'Users'],
        ['route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'bx-category', 'label' => 'Categories'],
        ['route' => 'admin.posts.index', 'active' => 'admin.posts.*', 'icon' => 'bx-file', 'label' => 'Posts'],
        ['route' => 'admin.comments.index', 'active' => 'admin.comments.*', 'icon' => 'bx-comment-detail', 'label' => 'Comments'],
        ['route' => 'admin.notifications.index', 'active' => 'admin.notifications.*', 'icon' => 'bx-bell', 'label' => 'Notifications'],
    ];
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand">
        <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
            <span class="app-brand-text menu-text fw-bold ms-1">{{ config('app.name') }}</span>
            <span class="badge bg-label-primary ms-2">Admin</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none" aria-label="Close menu">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        @foreach ($adminLinks as $link)
            @if (Route::has($link['route']))
                <li @class(['menu-item', 'active' => request()->routeIs($link['active'])])>
                    <a href="{{ route($link['route']) }}" class="menu-link">
                        <i class="menu-icon tf-icons bx {{ $link['icon'] }}"></i>
                        <div>{{ $link['label'] }}</div>
                    </a>
                </li>
            @endif
        @endforeach

        <li class="menu-header small text-uppercase"><span class="menu-header-text">Site</span></li>
        <li class="menu-item">
            <a href="{{ route('home') }}" class="menu-link" target="_blank">
                <i class="menu-icon tf-icons bx bx-globe"></i>
                <div>View site</div>
            </a>
        </li>
        <li class="menu-item">
            <a href="{{ route('dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user-circle"></i>
                <div>My dashboard</div>
            </a>
        </li>
    </ul>
</aside>
