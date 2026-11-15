<?php

namespace Tests\Feature;

use App\Jobs\LogPostView;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Setting;
use App\Models\User;
use App\Services\CountryLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostViewLogTest extends TestCase
{
    use RefreshDatabase;

    private function fakeCountryService(): void
    {
        Http::fake(['api.country.is/*' => Http::response(['ip' => '8.8.8.8', 'country' => 'US'])]);
    }

    private function viewFrom(Post $post, string $ip, string $userAgent = 'Mozilla/5.0 (Macintosh)'): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', $userAgent)
            ->get(route('posts.show', $post))
            ->assertOk();
    }

    public function test_a_counted_view_is_logged_with_the_visitors_country_but_not_their_ip(): void
    {
        $this->fakeCountryService();
        $post = Post::factory()->published()->create();
        $reader = User::factory()->create();

        $this->actingAs($reader);
        $this->viewFrom($post, '8.8.8.8');

        $view = PostView::sole();
        $this->assertTrue($view->post->is($post));
        $this->assertTrue($view->user->is($reader));
        $this->assertSame('US', $view->country_code);
        $this->assertSame(1, $post->fresh()->views_count);
        $this->assertStringNotContainsString('8.8.8.8', json_encode(PostView::first()->getAttributes()));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.country.is/8.8.8.8');
    }

    public function test_each_address_is_looked_up_once_and_local_addresses_never(): void
    {
        $this->fakeCountryService();
        $post = Post::factory()->published()->create();

        // Run the job directly: in tests one app serves every request, so after-response jobs would repeat.
        foreach (['8.8.8.8', '8.8.8.8', '192.168.1.20', null] as $ip) {
            (new LogPostView($post->id, null, $ip, now()))->handle(app(CountryLookup::class));
        }

        $this->assertSame(['US', 'US', null, null], PostView::orderBy('id')->pluck('country_code')->all());
        Http::assertSentCount(1);
    }

    public function test_a_failed_lookup_logs_the_view_with_an_unknown_country(): void
    {
        Http::fake(['api.country.is/*' => Http::response('Server error', 500)]);

        $this->viewFrom(Post::factory()->published()->create(), '1.1.1.1');

        $this->assertNull(PostView::sole()->country_code);
    }

    public function test_nothing_is_logged_when_turned_off_or_for_bots_but_views_are_still_counted(): void
    {
        $this->fakeCountryService();
        $post = Post::factory()->published()->create();

        $this->viewFrom($post, '8.8.8.8', 'Googlebot/2.1 (+http://www.google.com/bot.html)');
        $this->flushSession();

        Setting::put(Setting::LOG_VIEW_COUNTRIES, false);
        $this->viewFrom($post, '8.8.8.8');

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(2, $post->fresh()->views_count);
        Http::assertNothingSent();
    }

    public function test_admin_sees_views_by_country_and_can_turn_logging_off_and_on(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->published()->create(['title' => 'Popular post']);
        PostView::factory()->for($post)->count(3)->create(['country_code' => 'PK', 'viewed_at' => now()->subDay()]);
        PostView::factory()->for($post)->create(['country_code' => 'US', 'viewed_at' => now()->subDays(2)]);
        PostView::factory()->for($post)->create(['country_code' => 'GB', 'viewed_at' => now()->subDays(45)]);

        $this->actingAs($admin)->get(route('admin.views.index'))
            ->assertOk()
            ->assertSee('data-logging-state>On<', false)
            ->assertSeeInOrder(['Top countries', 'Pakistan', '3', 'United States', '1'])
            ->assertDontSee('United Kingdom')
            ->assertSeeInOrder(['Most viewed posts', 'Popular post', '4']);

        $this->actingAs($admin)->get(route('admin.views.index', ['post' => $post->slug]))
            ->assertSee('Showing views of <strong>Popular post</strong> only.', false);

        $this->actingAs($admin)->from(route('admin.views.index'))->put(route('admin.views.settings'), ['log_view_countries' => '0'])
            ->assertRedirect(route('admin.views.index'))
            ->assertSessionHas('success', 'View logging is off: post views are still counted, but no longer logged.');
        $this->assertFalse(Setting::enabled(Setting::LOG_VIEW_COUNTRIES));

        $this->actingAs($admin)->put(route('admin.views.settings'), ['log_view_countries' => '1']);
        $this->assertTrue(Setting::enabled(Setting::LOG_VIEW_COUNTRIES));
    }

    public function test_only_admins_can_open_the_views_page_or_change_the_setting(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.views.index'))->assertForbidden();
        $this->actingAs($member)->put(route('admin.views.settings'), ['log_view_countries' => '0'])->assertForbidden();
        $this->assertTrue(Setting::enabled(Setting::LOG_VIEW_COUNTRIES));
    }

    public function test_privacy_policy_mentions_the_country_lookup_only_while_logging_is_on(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('your IP address is sent to a lookup service (country.is)')
            ->assertSee("readers' IP addresses are sent to the lookup service country.is", false)
            ->assertSee('The view log (post, country, time): deleted automatically after 12 months.');

        Setting::put(Setting::LOG_VIEW_COUNTRIES, false);

        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('We do not record who read it or where from.')
            ->assertDontSee('country.is')
            ->assertDontSee('The view log (post, country, time)');
    }

    public function test_privacy_policy_keeps_the_retention_line_while_earlier_logged_views_remain(): void
    {
        Setting::put(Setting::LOG_VIEW_COUNTRIES, false);
        PostView::factory()->create();

        $this->get(route('privacy'))
            ->assertDontSee('country.is')
            ->assertSee("Reads saved earlier with the reader's country are kept", false)
            ->assertSee('The view log (post, country, time): deleted automatically after 12 months.');
    }

    public function test_logged_views_older_than_a_year_are_pruned(): void
    {
        $old = PostView::factory()->create(['viewed_at' => now()->subDays(400)]);
        $recent = PostView::factory()->create(['viewed_at' => now()->subDays(10)]);

        $this->artisan('views:prune')->expectsOutput('Deleted 1 old logged view(s).')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
