<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Irfan Farooq',
            'email' => 'irfan@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ], $overrides);
    }

    public function test_guest_can_open_the_signup_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_signed_in_user_is_sent_away_from_the_signup_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('home'));
    }

    public function test_new_user_is_created_signed_in_and_welcomed(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post(route('register.store'), $this->validInput());

        $response->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Welcome to Muallam, Irfan Farooq! Your account is ready.');

        $user = User::where('email', 'irfan@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('irfan-farooq', $user->slug);
        $this->assertSame(UserRole::Author, $user->refresh()->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($user));
    }

    public function test_extra_form_fields_cannot_make_the_user_an_admin(): void
    {
        $this->post(route('register.store'), $this->validInput([
            'role' => UserRole::Admin->value,
            'slug' => 'chosen-slug',
            'is_active' => 0,
            'email_verified_at' => now()->toDateTimeString(),
        ]));

        $user = User::where('email', 'irfan@example.com')->sole()->refresh();
        $this->assertSame(UserRole::Author, $user->role);
        $this->assertSame('irfan-farooq', $user->slug);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_is_stored_in_lower_case_and_name_spaces_are_tidied(): void
    {
        $this->post(route('register.store'), $this->validInput([
            'name' => '  Irfan    Farooq ',
            'email' => '  Irfan@Example.COM ',
        ]));

        $this->assertDatabaseHas('users', ['name' => 'Irfan Farooq', 'email' => 'irfan@example.com']);
    }

    public function test_second_user_with_the_same_name_gets_a_numbered_slug(): void
    {
        User::factory()->create(['name' => 'Irfan Farooq', 'slug' => 'irfan-farooq']);

        $this->post(route('register.store'), $this->validInput());

        $this->assertSame('irfan-farooq-2', User::where('email', 'irfan@example.com')->value('slug'));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'one-letter name' => [['name' => 'A'], 'name'],
            'name over 100 characters' => [['name' => str_repeat('a', 101)], 'name'],
            'name with digits' => [['name' => 'Irfan 123'], 'name'],
            'name with html' => [['name' => '<b>Irfan</b>'], 'name'],
            'missing email' => [['email' => ''], 'email'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'missing password' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'password under 8 characters' => [['password' => 'short1', 'password_confirmation' => 'short1'], 'password'],
            'password over 72 characters' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
            'confirmation does not match' => [['password_confirmation' => 'different-pass'], 'password'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_signup_is_rejected(array $overrides, string $field): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), $this->validInput($overrides))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors($field);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_already_registered_in_any_letter_case_is_rejected(): void
    {
        User::factory()->create(['email' => 'irfan@example.com']);

        $this->post(route('register.store'), $this->validInput(['email' => 'IRFAN@example.com']))
            ->assertSessionHasErrors(['email' => 'An account with this email already exists.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_failed_signup_keeps_name_and_email_but_not_passwords(): void
    {
        $this->post(route('register.store'), $this->validInput(['password_confirmation' => 'different-pass']));

        $this->assertSame('Irfan Farooq', session()->getOldInput('name'));
        $this->assertSame('irfan@example.com', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_signup_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('register.store'), $this->validInput(['email' => 'bad']))->assertRedirect();
        }

        $this->post(route('register.store'), $this->validInput())->assertTooManyRequests();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signed_in_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'You have been logged out.');

        $this->assertGuest();
    }

    public function test_logout_does_not_accept_get_requests(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/logout')
            ->assertMethodNotAllowed();

        $this->assertAuthenticated();
    }
}
