@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $tiles = [
            ['label' => 'Users', 'value' => $users['total'], 'note' => "+{$users['new_this_week']} this week", 'icon' => 'bx-user', 'tone' => 'primary', 'key' => 'users'],
            ['label' => 'Published posts', 'value' => $posts['published'], 'note' => "{$posts['total']} posts in total", 'icon' => 'bx-file', 'tone' => 'success', 'key' => 'published'],
            ['label' => 'Comments', 'value' => $engagement['comments'], 'note' => "{$engagement['hidden_comments']} hidden", 'icon' => 'bx-comment-detail', 'tone' => 'info', 'key' => 'comments'],
            ['label' => 'Views', 'value' => $engagement['views'], 'note' => number_format($engagement['likes']).' likes', 'icon' => 'bx-show', 'tone' => 'warning', 'key' => 'views'],
        ];
    @endphp

    <div class="row g-4 mb-4">
        @foreach ($tiles as $tile)
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-tile h-100">
                    <div class="card-body d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small mb-1">{{ $tile['label'] }}</span>
                            <div class="stat-value" data-stat="{{ $tile['key'] }}">{{ number_format($tile['value']) }}</div>
                            <small class="text-muted">{{ $tile['note'] }}</small>
                        </div>
                        <span class="stat-icon bg-label-{{ $tile['tone'] }}"><i class="bx {{ $tile['icon'] }}"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Last {{ \App\Http\Controllers\Admin\DashboardController::CHART_DAYS }} days</h5>
                    <small class="text-muted">New sign-ups and published posts per day</small>
                </div>
                <div class="card-body">
                    <div id="activityChart" style="min-height: 280px;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">At a glance</h5></div>
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-2">Posts</h6>
                    <ul class="list-unstyled mb-4" data-section="posts">
                        <li class="d-flex justify-content-between py-1"><span>Published</span><strong data-stat="posts-published">{{ $posts['published'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Scheduled</span><strong data-stat="posts-scheduled">{{ $posts['scheduled'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Drafts</span><strong data-stat="posts-drafts">{{ $posts['drafts'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Private</span><strong data-stat="posts-private">{{ $posts['private'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Restricted</span><strong data-stat="posts-restricted">{{ $posts['restricted'] }}</strong></li>
                    </ul>
                    <h6 class="text-muted text-uppercase small mb-2">Users</h6>
                    <ul class="list-unstyled mb-4">
                        <li class="d-flex justify-content-between py-1"><span>Admins</span><strong data-stat="users-admins">{{ $users['admins'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Email not verified</span><strong data-stat="users-unverified">{{ $users['unverified'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Deactivated</span><strong data-stat="users-inactive">{{ $users['inactive'] }}</strong></li>
                    </ul>
                    <h6 class="text-muted text-uppercase small mb-2">Content</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between py-1"><span>Active categories</span><strong data-stat="categories">{{ $activeCategories }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Likes</span><strong>{{ number_format($engagement['likes']) }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Newest members</h5></div>
                <ul class="list-group list-group-flush admin-list">
                    @forelse ($recentUsers as $user)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <x-user-avatar :user="$user" :size="32" />
                            <div class="flex-grow-1 text-truncate">
                                <div class="fw-semibold text-truncate">{{ $user->name }}</div>
                                <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                            </div>
                            @if ($user->isAdmin())
                                <span class="badge bg-label-primary">Admin</span>
                            @elseif (! $user->hasVerifiedEmail())
                                <span class="badge bg-label-warning">Unverified</span>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No members yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Recently updated posts</h5></div>
                <ul class="list-group list-group-flush admin-list">
                    @forelse ($recentPosts as $post)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <a href="{{ route('posts.show', $post) }}" target="_blank" class="fw-semibold text-truncate">{{ $post->title }}</a>
                                <x-post-status-badge :post="$post" />
                            </div>
                            <small class="text-muted">{{ $post->user->name }} &middot; {{ $post->updated_at->diffForHumans() }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No posts yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Latest comments</h5></div>
                <ul class="list-group list-group-flush admin-list">
                    @forelse ($recentComments as $comment)
                        <li class="list-group-item">
                            <div class="small text-truncate">{{ \Illuminate\Support\Str::limit($comment->body, 90) }}</div>
                            <small class="text-muted">
                                {{ $comment->user->name }} on
                                <a href="{{ route('posts.show', $comment->post).'#comment-'.$comment->id }}" target="_blank">{{ \Illuminate\Support\Str::limit($comment->post->title, 40) }}</a>
                                @unless ($comment->is_approved)
                                    <span class="badge bg-label-warning ms-1">Hidden</span>
                                @endunless
                            </small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No comments yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script>
        (() => {
            const chart = @js($chart);
            new ApexCharts(document.querySelector('#activityChart'), {
                chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif' },
                series: [
                    { name: 'New sign-ups', data: chart.signups },
                    { name: 'Published posts', data: chart.posts },
                ],
                xaxis: { categories: chart.labels, tickAmount: 10, labels: { rotate: 0, style: { colors: '#8592a3' } } },
                yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value), style: { colors: '#8592a3' } } },
                colors: ['#0d6efd', '#20c997'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#eceef1' },
                legend: { position: 'top', horizontalAlign: 'left' },
            }).render();
        })();
    </script>
@endpush
