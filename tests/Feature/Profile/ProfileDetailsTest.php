<?php

namespace Tests\Feature\Profile;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Irfan Farooq',
            'slug' => 'irfan-farooq',
            'email' => 'irfan@example.com',
            'password' => 'secret-pass-1',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Irfan Farooq',
            'email' => 'irfan@example.com',
            'phone' => '+92 300 1234567',
            'gender' => Gender::Male->value,
            'bio' => 'I teach PHP and databases.',
            'is_bio_public' => '1',
        ], $overrides);
    }

    public function test_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), $this->input())->assertRedirect(route('login'));
    }

    public function test_profile_page_shows_the_current_details(): void
    {
        $user = $this->createUser(['phone' => '0300 1234567', 'gender' => Gender::Female, 'bio' => 'Hello there']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Personal details')
            ->assertSee('value="Irfan Farooq"', false)
            ->assertSee('value="0300 1234567"', false)
            ->assertSee('<option value="female" selected>Female</option>', false)
            ->assertSee('Prefer not to say')
            ->assertSee('Hello there');
    }

    public function test_member_sidebar_links_to_the_profile(): void
    {
        $this->actingAs($this->createUser())->get(route('dashboard'))->assertSee(route('profile.edit'));
    }

    public function test_user_can_update_their_details(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->input(['name' => '  Irfan   Ahmed  Farooq ']))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Profile saved.');

        $user->refresh();
        $this->assertSame('Irfan Ahmed Farooq', $user->name);
        $this->assertSame('+92 300 1234567', $user->phone);
        $this->assertSame(Gender::Male, $user->gender);
        $this->assertSame('I teach PHP and databases.', $user->bio);
        $this->assertTrue($user->is_bio_public);
    }

    public function test_changing_the_name_keeps_the_slug(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->patch(route('profile.update'), $this->input(['name' => 'Someone Else']));

        $this->assertSame('irfan-farooq', $user->fresh()->slug);
    }

    public function test_optional_fields_can_be_cleared(): void
    {
        $user = $this->createUser(['phone' => '0300 1234567', 'gender' => Gender::Male, 'bio' => 'Old bio', 'is_bio_public' => true]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Irfan Farooq',
            'email' => 'irfan@example.com',
            'phone' => '',
            'gender' => '',
            'bio' => '   ',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->phone);
        $this->assertNull($user->gender);
        $this->assertNull($user->bio);
        $this->assertFalse($user->is_bio_public);
    }

    public function test_protected_fields_cannot_be_changed_from_the_form(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->patch(route('profile.update'), $this->input([
            'role' => UserRole::Admin->value,
            'slug' => 'new-slug',
            'is_active' => '0',
            'avatar' => 'avatars/evil.webp',
            'password' => 'hijacked-pass',
        ]));

        $user->refresh();
        $this->assertSame(UserRole::Author, $user->role);
        $this->assertSame('irfan-farooq', $user->slug);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->avatar);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'name with digits' => [['name' => 'Irfan 2'], 'name'],
            'invalid email' => [['email' => 'nope'], 'email'],
            'phone with letters' => [['phone' => '0300-CALL-ME'], 'phone'],
            'phone too short' => [['phone' => '12345'], 'phone'],
            'unknown gender' => [['gender' => 'other'], 'gender'],
            'bio too long' => [['bio' => str_repeat('a', 1001)], 'bio'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_details_are_rejected(array $overrides, string $field): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->patch(route('profile.update'), $this->input($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame('Irfan Farooq', $user->fresh()->name);
    }

    public function test_changing_email_needs_the_current_password(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->patch(route('profile.update'), $this->input(['email' => 'new@example.com']))
            ->assertSessionHasErrors(['current_password' => 'Enter your current password to change your email.']);

        $this->actingAs($user)->patch(route('profile.update'), $this->input(['email' => 'new@example.com', 'current_password' => 'wrong-pass']))
            ->assertSessionHasErrors(['current_password' => 'The current password is incorrect.']);

        $this->assertSame('irfan@example.com', $user->fresh()->email);
    }

    public function test_changing_email_requires_verifying_the_new_address(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->input(['email' => 'New@Example.com', 'current_password' => 'secret-pass-1']))
            ->assertSessionHas('success', "Profile saved. We've sent a verification link to new@example.com.");

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_keeping_the_same_email_needs_no_password_and_stays_verified(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->actingAs($user)->patch(route('profile.update'), $this->input(['email' => ' IRFAN@example.com ']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_email_used_by_another_account_is_rejected(): void
    {
        $user = $this->createUser();
        $this->createUser(['email' => 'taken@example.com', 'slug' => 'other']);

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->input(['email' => 'taken@example.com', 'current_password' => 'secret-pass-1']))
            ->assertSessionHasErrors(['email' => 'Another account already uses this email.']);
    }
}
