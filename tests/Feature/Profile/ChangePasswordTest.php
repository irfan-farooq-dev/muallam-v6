<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password' => 'old-pass-123'], $attributes));
    }

    public function test_user_can_change_their_password(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'old-pass-123',
                'password' => 'new-pass-456',
                'password_confirmation' => 'new-pass-456',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Password changed. You have been signed out on your other devices.');

        $this->assertTrue(Hash::check('new-pass-456', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidChanges(): array
    {
        return [
            'wrong current password' => [['current_password' => 'wrong-pass'], 'current_password', 'The current password is incorrect.'],
            'missing current password' => [['current_password' => ''], 'current_password', 'The current password field is required.'],
            'new password too short' => [['password' => 'short1', 'password_confirmation' => 'short1'], 'password', 'The password field must be at least 8 characters.'],
            'confirmation does not match' => [['password_confirmation' => 'different-pass'], 'password', 'The password field confirmation does not match.'],
            'same as current' => [['password' => 'old-pass-123', 'password_confirmation' => 'old-pass-123'], 'password', 'The new password must be different from your current one.'],
        ];
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_change_is_rejected(array $overrides, string $field, string $message): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->put(route('profile.password.update'), array_merge([
            'current_password' => 'old-pass-123',
            'password' => 'new-pass-456',
            'password_confirmation' => 'new-pass-456',
        ], $overrides))->assertSessionHasErrorsIn('updatePassword', [$field => $message]);

        $this->assertTrue(Hash::check('old-pass-123', $user->fresh()->password));
    }

    public function test_changing_password_signs_out_other_devices_only(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createUser(['remember_token' => 'old-remember-token']);
        DB::table('sessions')->insert([
            'id' => 'other-device-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'old-pass-123',
            'password' => 'new-pass-456',
            'password_confirmation' => 'new-pass-456',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
    }
}
