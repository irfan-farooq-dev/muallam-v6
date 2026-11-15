<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Setting;
use App\Services\CountryLookup;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin > Views: where post views come from (country, per day, which posts) and the switch that turns the
 * country log on or off. Only views counted while the switch was on are in the log.
 */
class PostViewController extends Controller
{
    public const DAYS = 30;

    private const TOP = 10;

    public function index(Request $request): View
    {
        $post = $request->filled('post') ? Post::where('slug', (string) $request->query('post'))->first() : null;
        $start = now()->subDays(self::DAYS - 1)->startOfDay();
        $views = fn (): Builder => PostView::query()
            ->where('viewed_at', '>=', $start)
            ->when($post, fn (Builder $query) => $query->whereBelongsTo($post));

        $countries = $views()
            ->selectRaw('country_code, count(*) as total')
            ->groupBy('country_code')
            ->orderByDesc('total')
            ->limit(self::TOP)
            ->get()
            ->map(fn (PostView $row): array => [
                'code' => $row->country_code,
                'name' => CountryLookup::countryName($row->country_code),
                'total' => (int) $row->getAttribute('total'),
            ]);

        $topPosts = $post ? collect() : $views()
            ->selectRaw('post_id, count(*) as total')
            ->groupBy('post_id')
            ->orderByDesc('total')
            ->limit(self::TOP)
            ->with('post:id,title,slug')
            ->get();

        return view('admin.views.index', [
            'isLogging' => Setting::enabled(Setting::LOG_VIEW_COUNTRIES),
            'post' => $post,
            'total' => $views()->count(),
            'countries' => $countries,
            'topPosts' => $topPosts,
            'chart' => $this->chart($views()->pluck('viewed_at'), $start),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $isLogging = $request->boolean('log_view_countries');
        Setting::put(Setting::LOG_VIEW_COUNTRIES, $isLogging);

        return back()->with('success', $isLogging
            ? 'View logging is on: new post views are logged with their country.'
            : 'View logging is off: post views are still counted, but no longer logged.');
    }

    /**
     * Logged views per day (UTC days) for the last DAYS days.
     *
     * @param  iterable<CarbonInterface>  $dates
     * @return array{labels: list<string>, views: list<int>}
     */
    private function chart(iterable $dates, CarbonInterface $start): array
    {
        $perDay = collect($dates)->countBy(fn (CarbonInterface $date): string => $date->format('Y-m-d'));
        $days = collect(range(0, self::DAYS - 1))->map(fn (int $offset): CarbonInterface => $start->copy()->addDays($offset));

        return [
            'labels' => $days->map(fn (CarbonInterface $day): string => $day->format('j M'))->values()->all(),
            'views' => $days->map(fn (CarbonInterface $day): int => $perDay->get($day->format('Y-m-d'), 0))->values()->all(),
        ];
    }
}
