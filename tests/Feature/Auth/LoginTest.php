<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Failed sign-ins normally take at least 200 ms (timing-attack protection); skip that wait in tests.
        config(['auth.timebox_duration' => 0]);
    }

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Irfan Farooq',
            'email' => 'irfan@example.com',
            'password' => 'secret-pass-1',
        ], $attributes));
    }

    public function test_guest_can_open_the_sign_in_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('name="remember"', false)
            ->assertSee(route('register'));
    }

    public function test_signed_in_user_is_sent_away_from_the_sign_in_page(): void
    {
        $this->actingAs($this->createUser())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_user_can_sign_in_with_correct_credentials(): void
    {
        $user = $this->createUser();

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Welcome back, Irfan Farooq!');

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_letter_case_and_spaces_do_not_matter(): void
    {
        $user = $this->createUser();

        $this->post(route('login.store'), ['email' => '  IRFAN@Example.com ', 'password' => 'secret-pass-1']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_sets_a_long_lived_cookie(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login.store'), [
            'email' => 'irfan@example.com',
            'password' => 'secret-pass-1',
            'remember' => '1',
        ]);

        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertNotNull($user->refresh()->remember_token);
    }

    public function test_without_remember_me_no_remember_cookie_is_set(): void
    {
        $this->createUser();

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1'])
            ->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function wrongCredentials(): array
    {
        return [
            'wrong password' => ['irfan@example.com', 'wrong-pass'],
            'unknown email' => ['nobody@example.com', 'secret-pass-1'],
        ];
    }

    #[DataProvider('wrongCredentials')]
    public function test_wrong_credentials_get_the_same_generic_message(string $email, string $password): void
    {
        $this->createUser();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $email, 'password' => $password])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
        $this->assertSame($email, session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function incompleteInput(): array
    {
        return [
            'missing email' => [['password' => 'secret-pass-1'], 'email'],
            'invalid email' => [['email' => 'not-an-email', 'password' => 'secret-pass-1'], 'email'],
            'missing password' => [['email' => 'irfan@example.com'], 'password'],
        ];
    }

    #[DataProvider('incompleteInput')]
    public function test_incomplete_sign_in_is_rejected(array $input, string $field): void
    {
        $this->createUser();

        $this->post(route('login.store'), $input)->assertSessionHasErrors($field);

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        $this->createUser(['is_active' => false]);

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors(['email' => 'Your account has been deactivated. Please contact support.']);

        $this->assertGuest();
    }

    public function test_deactivated_message_is_not_shown_for_a_wrong_password(): void
    {
        $this->createUser(['is_active' => false]);

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_sign_in_returns_the_user_to_the_page_they_wanted(): void
    {
        Route::get('/_test/members-only', fn () => 'members area')->middleware(['web', 'auth']);
        $this->createUser();

        $this->get('/_test/members-only')->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect('/_test/members-only');
    }

    public function test_session_id_changes_after_sign_in(): void
    {
        $this->createUser();
        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_email_is_locked_after_too_many_failed_attempts(): void
    {
        $this->createUser();

        foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
            $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'wrong-pass']);
        }

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringStartsWith('Too many sign-in attempts.', session('errors')->first('email'));
    }

    public function test_lockout_ends_after_the_wait(): void
    {
        $user = $this->createUser();

        foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
            $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'wrong-pass']);
        }

        $this->travel(LoginRequest::DECAY_SECONDS + 1)->seconds();

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_sign_in_resets_the_failed_attempt_count(): void
    {
        $this->createUser();

        foreach (range(1, LoginRequest::MAX_ATTEMPTS - 1) as $attempt) {
            $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'wrong-pass']);
        }
        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'secret-pass-1']);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_one_ip_is_limited_across_many_emails(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->post(route('login.store'), ['email' => "user{$attempt}@example.com", 'password' => 'wrong-pass']);
        }

        $this->post(route('login.store'), ['email' => 'another@example.com', 'password' => 'wrong-pass'])
            ->assertTooManyRequests();
    }
}
