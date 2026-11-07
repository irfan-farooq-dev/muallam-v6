<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $email = null, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id,
            'hash' => sha1($email ?? $user->email),
        ]);
    }

    public function test_signup_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Irfan Farooq',
            'email' => 'irfan@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ]);

        Notification::assertSentTo(User::where('email', 'irfan@example.com')->sole(), VerifyEmail::class);
    }

    public function test_verification_email_is_branded_and_links_to_the_verify_route(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['name' => 'Irfan Farooq']);

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
            /** @var MailMessage $mail */
            $mail = $notification->toMail($user);

            return $mail->subject === 'Verify your Muallam email address'
                && $mail->greeting === 'Hi Irfan Farooq,'
                && str_contains($mail->actionUrl, '/email/verify/'.$user->id.'/');
        });
    }

    public function test_unverified_user_sees_the_check_your_inbox_page(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'irfan@example.com']);

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Check your inbox')
            ->assertSee('irfan@example.com');
    }

    public function test_verified_user_skips_the_check_your_inbox_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_guest_is_asked_to_sign_in_before_verifying(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))->assertRedirect(route('login'));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_valid_link_verifies_the_email(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Thanks! Your email address is now verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_using_the_link_again_says_already_verified(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('info', 'Your email address is already verified.');
    }

    public function test_link_for_a_different_email_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user, 'someone-else@example.com'))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_belonging_to_another_user_is_rejected(): void
    {
        $owner = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($other)->get($this->verificationUrl($owner))->assertForbidden();

        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user, minutes: 60);

        $this->travel(61)->minutes();

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user).'x')->assertForbidden();
    }

    public function test_unverified_user_can_request_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'irfan@example.com']);

        $this->actingAs($user)->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success', 'A new verification link has been sent to irfan@example.com.');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_verified_user_does_not_get_another_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('dashboard'));

        Notification::assertNothingSent();
    }

    public function test_resending_is_limited_to_six_per_minute(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        }

        $this->actingAs($user)->post(route('verification.send'))->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
    }

    public function test_banner_reminds_unverified_users_on_other_pages(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'irfan@example.com']);

        $this->actingAs($user)->get(route('home'))
            ->assertSee('Please verify your email address.')
            ->assertSee('irfan@example.com');
    }

    public function test_banner_is_hidden_for_verified_users_guests_and_on_the_inbox_page(): void
    {
        $this->get(route('home'))->assertDontSee('Please verify your email address.');

        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertDontSee('Please verify your email address.');

        $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))
            ->assertDontSee('Please verify your email address.');
    }
}
