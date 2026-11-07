<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSlugTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['name' => 'Irfan Farooq', 'slug' => 'irfan-farooq'], $attributes));
    }

    public function test_check_reports_an_available_slug_and_its_url(): void
    {
        $this->actingAs($this->createUser())
            ->getJson(route('profile.slug.check', ['slug' => 'Irfan Farooq Dev']))
            ->assertOk()
            ->assertExactJson([
                'slug' => 'irfan-farooq-dev',
                'available' => true,
                'message' => 'Available',
                'url' => route('users.bio', 'irfan-farooq-dev'),
            ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unavailableSlugs(): array
    {
        return [
            'own current slug' => ['irfan-farooq', 'This is already your link.'],
            'too short' => ['ab', 'Use between 3 and 50 characters.'],
            'too long' => [str_repeat('a', 51), 'Use between 3 and 50 characters.'],
            'reserved word' => ['admin', 'This word is reserved. Please choose another.'],
            'taken by another user' => ['taken-slug', 'This link is already taken.'],
            'retired by another user' => ['old-slug-of-someone', 'This link was used before and is not available.'],
        ];
    }

    #[DataProvider('unavailableSlugs')]
    public function test_check_explains_why_a_slug_is_unavailable(string $slug, string $message): void
    {
        $this->createUser(['slug' => 'taken-slug', 'email' => 'other@example.com']);
        UserSlugHistory::factory()->create(['slug' => 'old-slug-of-someone']);

        $this->actingAs($this->createUser())
            ->getJson(route('profile.slug.check', ['slug' => $slug]))
            ->assertOk()
            ->assertJson(['available' => false, 'message' => $message]);
    }

    public function test_check_requires_sign_in(): void
    {
        $this->getJson(route('profile.slug.check', ['slug' => 'anything']))->assertUnauthorized();
    }

    public function test_user_can_change_their_slug(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('profile.slug.update'), ['slug' => 'irfan-farooq-dev'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('irfan-farooq-dev', $user->slug);
        $this->assertNotNull($user->slug_changed_at);
        $this->assertDatabaseHas('user_slug_history', ['user_id' => $user->id, 'slug' => 'irfan-farooq']);
    }

    public function test_typed_text_is_turned_into_a_valid_slug(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => '  Irfan Farooq DEV ']);

        $this->assertSame('irfan-farooq-dev', $user->fresh()->slug);
    }

    public function test_unavailable_slug_is_not_saved(): void
    {
        $user = $this->createUser();
        $this->createUser(['slug' => 'taken-slug', 'email' => 'other@example.com']);

        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'taken-slug'])
            ->assertSessionHasErrorsIn('updateSlug', ['slug' => 'This link is already taken.']);

        $this->assertSame('irfan-farooq', $user->fresh()->slug);
        $this->assertDatabaseCount('user_slug_history', 0);
    }

    public function test_slug_can_only_change_once_every_thirty_days(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'second-slug']);

        $this->travel(29)->days();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'third-slug'])
            ->assertSessionHasErrorsIn('updateSlug', 'slug');
        $this->assertSame('second-slug', $user->fresh()->slug);

        $this->travel(2)->days();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'third-slug'])
            ->assertSessionHasNoErrors();
        $this->assertSame('third-slug', $user->fresh()->slug);
    }

    public function test_profile_page_shows_the_link_and_hides_the_form_during_cooldown(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSee(route('users.bio', 'irfan-farooq'))
            ->assertSee('Save link');

        $user->forceFill(['slug_changed_at' => now()])->save();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertDontSee('Save link')
            ->assertSee('You can change your link again on '.now()->addDays(30)->format('j M Y'));
    }

    public function test_user_can_take_back_their_own_old_slug(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'temporary-slug']);
        $this->travel(31)->days();

        $this->actingAs($user)->getJson(route('profile.slug.check', ['slug' => 'irfan-farooq']))
            ->assertJson(['available' => true]);

        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'irfan-farooq'])->assertSessionHasNoErrors();

        $this->assertSame('irfan-farooq', $user->fresh()->slug);
        $this->assertDatabaseMissing('user_slug_history', ['slug' => 'irfan-farooq']);
        $this->assertDatabaseHas('user_slug_history', ['user_id' => $user->id, 'slug' => 'temporary-slug']);
    }

    public function test_new_signups_never_get_a_retired_slug(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'irfan-the-teacher']);
        $this->post(route('logout'));

        $this->post(route('register.store'), [
            'name' => 'Irfan Farooq',
            'email' => 'twin@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ]);

        $this->assertSame('irfan-farooq-2', User::where('email', 'twin@example.com')->value('slug'));
    }
}
