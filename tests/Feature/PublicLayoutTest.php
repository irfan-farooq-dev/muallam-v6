<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_inside_the_public_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home | Muallam</title>', false)
            ->assertSee('id="header"', false)
            ->assertSee('id="footer"', false)
            ->assertSee('Latest posts');
    }

    public function test_guest_sees_sign_in_and_sign_up_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Sign up')
            ->assertDontSee('header-avatar', false);
    }

    public function test_signed_in_user_sees_their_account_menu_instead_of_sign_up(): void
    {
        $user = User::factory()->create(['name' => 'Irfan Farooq', 'email' => 'irfan@example.com']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Irfan Farooq')
            ->assertSee('irfan@example.com')
            ->assertDontSee('Sign up');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function flashMessages(): array
    {
        return [
            'success' => ['success', 'alert-success'],
            'status' => ['status', 'alert-success'],
            'error' => ['error', 'alert-danger'],
            'warning' => ['warning', 'alert-warning'],
            'info' => ['info', 'alert-info'],
        ];
    }

    #[DataProvider('flashMessages')]
    public function test_flash_message_is_shown_with_matching_style(string $key, string $cssClass): void
    {
        $this->withSession([$key => 'Profile saved'])
            ->get('/')
            ->assertSee($cssClass, false)
            ->assertSee('Profile saved');
    }

    public function test_flash_message_html_is_escaped(): void
    {
        $this->withSession(['success' => '<script>alert(1)</script>'])
            ->get('/')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_unknown_url_shows_styled_404_page(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('id="header"', false);
    }

    public function test_403_page_shows_the_reason_given(): void
    {
        Route::get('/_test/forbidden', fn () => abort(403, 'Only the author can edit this post.'));

        $this->get('/_test/forbidden')
            ->assertForbidden()
            ->assertSee('Access denied')
            ->assertSee('Only the author can edit this post.');
    }

    public function test_419_page_explains_the_expired_session(): void
    {
        Route::get('/_test/expired', fn () => abort(419));

        $this->get('/_test/expired')
            ->assertStatus(419)
            ->assertSee('Page expired');
    }

    public function test_429_page_shows_the_wait_time(): void
    {
        Route::get('/_test/throttled', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => 42]));

        $this->get('/_test/throttled')
            ->assertTooManyRequests()
            ->assertSee('Too many attempts')
            ->assertSee('seconds: 42', false);
    }

    public function test_500_page_renders_without_the_site_header(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/broken', fn () => throw new \RuntimeException('boom'));

        $this->get('/_test/broken')
            ->assertServerError()
            ->assertSee('Something went wrong')
            ->assertDontSee('boom')
            ->assertDontSee('id="header"', false);
    }
}
