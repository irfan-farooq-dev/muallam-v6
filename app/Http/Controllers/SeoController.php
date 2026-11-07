<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml and robots.txt for search engines.
 */
class SeoController extends Controller
{
    public const SITEMAP_CACHE_KEY = 'seo.sitemap';

    private const SITEMAP_CACHE_SECONDS = 3600;

    /**
     * Every public page worth indexing. Rebuilt at most once an hour.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember(self::SITEMAP_CACHE_KEY, self::SITEMAP_CACHE_SECONDS, fn (): string => $this->buildSitemap());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Only the live site (APP_ENV=production) invites search engines in; every other copy asks them to stay out.
     */
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Disallow: /dashboard',
                'Disallow: /my/',
                'Disallow: /profile',
                'Disallow: /search',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /forgot-password',
                'Disallow: /reset-password/',
                'Disallow: /email/',
                'Disallow: /posts/create',
                'Disallow: /posts/preview',
                'Disallow: /posts/*/edit',
                'Disallow: /posts/*/access',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function buildSitemap(): string
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => Post::publiclyVisible()->max('published_at')],
            ['loc' => route('categories.index'), 'lastmod' => null],
        ]);

        Category::query()->active()->get(['id', 'slug', 'updated_at'])->each(function (Category $category) use ($urls): void {
            $urls->push([
                'loc' => route('categories.show', $category),
                'lastmod' => Post::publiclyVisible()->whereBelongsTo($category)->max('published_at') ?? $category->updated_at,
            ]);
        });

        Post::publiclyVisible()->orderByDesc('published_at')->get(['id', 'slug', 'updated_at'])->each(function (Post $post) use ($urls): void {
            $urls->push(['loc' => route('posts.show', $post), 'lastmod' => $post->updated_at]);
        });

        User::query()
            ->where('is_active', true)
            ->whereHas('posts', fn ($query) => $query->publiclyVisible())
            ->get(['id', 'slug', 'is_bio_public', 'updated_at'])
            ->each(function (User $author) use ($urls): void {
                $urls->push(['loc' => route('users.posts', $author->slug), 'lastmod' => null]);

                if ($author->is_bio_public) {
                    $urls->push(['loc' => route('users.bio', $author->slug), 'lastmod' => $author->updated_at]);
                }
            });

        $entries = $urls->map(function (array $url): string {
            $lastmod = $url['lastmod'] ? "\n    <lastmod>".Carbon::parse($url['lastmod'])->toAtomString().'</lastmod>' : '';

            return '  <url>'."\n    <loc>".htmlspecialchars($url['loc'], ENT_XML1).'</loc>'.$lastmod."\n  </url>";
        })->implode("\n");

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$entries."\n"
            .'</urlset>'."\n";
    }
}
