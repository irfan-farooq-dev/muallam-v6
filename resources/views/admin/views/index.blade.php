@extends('admin.layouts.app')

@section('title', 'Views')

@section('content')
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1">Log the country of views</h5>
                <p class="text-muted small mb-0">
                    Each counted post view is saved with the visitor's country (the IP address is only used for the lookup, never stored).
                    Turn it off if the site gets busy; the view counts on posts keep working either way.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.views.settings') }}" class="d-flex align-items-center gap-2">
                @csrf
                @method('PUT')
                <input type="hidden" name="log_view_countries" value="{{ $isLogging ? 0 : 1 }}">
                <span @class(['badge', 'bg-label-success' => $isLogging, 'bg-label-secondary' => ! $isLogging]) data-logging-state>{{ $isLogging ? 'On' : 'Off' }}</span>
                <button type="submit" @class(['btn btn-sm', 'btn-outline-secondary' => $isLogging, 'btn-primary' => ! $isLogging])>
                    {{ $isLogging ? 'Turn off' : 'Turn on' }}
                </button>
            </form>
        </div>
    </div>

    @if ($post)
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>Showing views of <strong>{{ $post->title }}</strong> only.</span>
            <a href="{{ route('admin.views.index') }}" class="btn btn-sm btn-outline-primary">All posts</a>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Logged views per day</h5>
                    <small class="text-muted">Last {{ \App\Http\Controllers\Admin\PostViewController::DAYS }} days &middot; {{ number_format($total) }} in total</small>
                </div>
                <div class="card-body">
                    <div id="viewsChart"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Top countries</h5></div>
                <ul class="list-group list-group-flush">
                    @forelse ($countries as $country)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $country['name'] }} @if ($country['code'])<small class="text-muted">{{ $country['code'] }}</small>@endif</span>
                            <span class="fw-semibold">{{ number_format($country['total']) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No views logged in this period yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @unless ($post)
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Most viewed posts</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th class="text-end">Logged views</th>
                            <th class="text-end">By country</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topPosts as $row)
                            <tr>
                                <td><a href="{{ route('posts.show', $row->post) }}" target="_blank">{{ $row->post->title }}</a></td>
                                <td class="text-end">{{ number_format($row->getAttribute('total')) }}</td>
                                <td class="text-end"><a href="{{ route('admin.views.index', ['post' => $row->post->slug]) }}" class="btn btn-sm btn-outline-primary">Countries</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No views logged in this period yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless
@endsection

@push('scripts')
    <script src="{{ asset('admin-theme/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script>
        (() => {
            const chart = @js($chart);
            new ApexCharts(document.querySelector('#viewsChart'), {
                chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif' },
                series: [{ name: 'Views', data: chart.views }],
                xaxis: { categories: chart.labels, tickAmount: 10, labels: { rotate: 0, style: { colors: '#8592a3' } } },
                yaxis: { min: 0, max: Math.max(...chart.views) < 4 ? 4 : undefined, forceNiceScale: true, labels: { formatter: (value) => Math.round(value), style: { colors: '#8592a3' } } },
                colors: ['#0d6efd'],
                dataLabels: { enabled: false },
                grid: { borderColor: '#eceef1' },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            }).render();
        })();
    </script>
@endpush
