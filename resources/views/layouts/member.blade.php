{{--
    Member area: the public layout plus a sidebar.
    Pages set @section('title') and @section('member_content'); optional @section('page_actions') for buttons beside the heading.
    Sidebar items appear automatically once their route exists.
--}}
@extends('layouts.app')

@php
    $memberLinks = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
        ['route' => 'posts.mine', 'active' => 'posts.*', 'icon' => 'bi-journal-text', 'label' => 'My posts'],
        ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'bi-person', 'label' => 'Profile'],
    ];
    $memberLinks = array_filter($memberLinks, fn (array $link): bool => Route::has($link['route']));
@endphp

@section('content')
    <section class="member-area">
        <div class="container">
            <div class="row g-4">
                <aside class="col-lg-3">
                    <nav class="member-nav" aria-label="Member area">
                        <div class="member-nav-user d-none d-lg-flex align-items-center gap-2 mb-3">
                            <span class="header-avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                            <div class="text-truncate">
                                <div class="fw-semibold text-truncate">{{ auth()->user()->name }}</div>
                                <div class="small text-secondary text-truncate">{{ auth()->user()->email }}</div>
                            </div>
                        </div>
                        <ul class="nav nav-pills member-nav-links">
                            @foreach ($memberLinks as $link)
                                <li class="nav-item">
                                    <a href="{{ route($link['route']) }}"
                                       @class(['nav-link', 'active' => request()->routeIs($link['active'])])
                                       @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                                        <i class="bi {{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </aside>

                <div class="col-lg-9">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                        <h2 class="h3 mb-0">@yield('title')</h2>
                        @yield('page_actions')
                    </div>

                    @yield('member_content')
                </div>
            </div>
        </div>
    </section>
@endsection
