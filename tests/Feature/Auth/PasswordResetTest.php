<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Irfan Farooq',
            'email' => 'irfan@example.com',
            'password' => 'old-pass-123',
        ], $attributes));
    }

    /**
     * @return array<string, string>
     */
    private function resetInput(string $token, array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'email' => 'irfan@example.com',
            'password' => 'new-pass-456',
            'password_confirmation' => 'new-pass-456',
        ], $overrides);
    }

    public function test_sign_in_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'));
    }

    public function test_guest_can_open_the_forgot_password_page(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password?');
    }

    public function test_signed_in_user_cannot_open_the_reset_pages(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get(route('password.request'))->assertRedirect(route('home'));
        $this->actingAs($user)->get(route('password.reset', ['token' => 'abc']))->assertRedirect(route('home'));
    }

    public function test_reset_link_is_emailed_to_a_registered_address(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => '  IRFAN@example.com '])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', PasswordResetLinkController::SENT_MESSAGE);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            /** @var MailMessage $mail */
            $mail = $notification->toMail($user);

            return $mail->subject === 'Reset your Muallam password'
                && $mail->greeting === 'Hi Irfan Farooq,'
                && str_contains($mail->actionUrl, '/reset-password/'.$notification->token)
                && str_contains($mail->actionUrl, 'email=irfan%40example.com');
        });
    }

    public function test_unknown_email_gets_the_same_message_and_no_email(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('success', PasswordResetLinkController::SENT_MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_only_one_link_per_minute_is_sent_to_the_same_address(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => 'irfan@example.com']);
        $this->post(route('password.email'), ['email' => 'irfan@example.com'])
            ->assertSessionHas('success', PasswordResetLinkController::SENT_MESSAGE);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_invalid_email_is_rejected_on_the_request_form(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_reset_form_opens_with_the_email_filled_in(): void
    {
        $this->get(route('password.reset', ['token' => 'some-token', 'email' => 'irfan@example.com']))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('value="some-token"', false)
            ->assertSee('irfan@example.com');
    }

    public function test_valid_link_changes_the_password(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->createUser();
        $token = Password::createToken($user);

        $this->post(route('password.store'), $this->resetInput($token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been changed. Please sign in with your new password.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-pass-456', $user->password));
        $this->assertFalse(Hash::check('old-pass-123', $user->password));
        $this->assertGuest();
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_link_works_only_once(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $this->post(route('password.store'), $this->resetInput($token));

        $this->post(route('password.store'), $this->resetInput($token, [
            'password' => 'third-pass-789',
            'password_confirmation' => 'third-pass-789',
        ]))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('new-pass-456', $user->fresh()->password));
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->post(route('password.store'), $this->resetInput($token))
            ->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired. Please request a new one.']);

        $this->assertTrue(Hash::check('old-pass-123', $user->fresh()->password));
    }

    public function test_link_cannot_be_used_for_a_different_account(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser(['email' => 'other@example.com']);
        $token = Password::createToken($owner);

        $this->post(route('password.store'), $this->resetInput($token, ['email' => 'other@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-pass-123', $other->fresh()->password));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidNewPasswords(): array
    {
        return [
            'too short' => [['password' => 'short1', 'password_confirmation' => 'short1'], 'password'],
            'confirmation does not match' => [['password_confirmation' => 'different-pass'], 'password'],
            'missing token' => [['token' => ''], 'token'],
        ];
    }

    #[DataProvider('invalidNewPasswords')]
    public function test_invalid_new_password_is_rejected(array $overrides, string $field): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $this->post(route('password.store'), $this->resetInput($token, $overrides))->assertSessionHasErrors($field);

        $this->assertTrue(Hash::check('old-pass-123', $user->fresh()->password));
    }

    public function test_reset_also_verifies_the_email(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $this->post(route('password.store'), $this->resetInput(Password::createToken($user)));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_reset_signs_the_user_out_everywhere(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createUser(['remember_token' => 'old-remember-token']);
        DB::table('sessions')->insert([
            'id' => 'stolen-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->post(route('password.store'), $this->resetInput(Password::createToken($user)));

        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_new_password_works_for_signing_in(): void
    {
        config(['auth.timebox_duration' => 0]);
        $user = $this->createUser();

        $this->post(route('password.store'), $this->resetInput(Password::createToken($user)));
        $this->post(route('login.store'), ['email' => 'irfan@example.com', 'password' => 'new-pass-456']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_requests_are_limited_per_ip(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => "user{$attempt}@example.com"])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'another@example.com'])->assertTooManyRequests();
    }
}
