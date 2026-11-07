<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBioPageTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Irfan Farooq',
            'slug' => 'irfan-farooq',
            'bio' => "I teach PHP.\nAnd databases.",
            'is_bio_public' => true,
            'phone' => '0300 1234567',
        ], $attributes));
    }

    public function test_anyone_can_read_a_public_bio(): void
    {
        $this->createUser();

        $this->get(route('users.bio', 'irfan-farooq'))
            ->assertOk()
            ->assertSee('<title>Irfan Farooq | Muallam</title>', false)
            ->assertSee('I teach PHP.<br />', false)
            ->assertSee('And databases.')
            ->assertDontSee('0300 1234567');
    }

    public function test_bio_text_cannot_inject_html(): void
    {
        $this->createUser(['bio' => '<script>alert(1)</script>']);

        $this->get(route('users.bio', 'irfan-farooq'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_hidden_bio_is_not_found_for_others(): void
    {
        $this->createUser(['is_bio_public' => false]);

        $this->get(route('users.bio', 'irfan-farooq'))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('users.bio', 'irfan-farooq'))->assertNotFound();
    }

    public function test_owner_can_preview_their_hidden_bio(): void
    {
        $user = $this->createUser(['is_bio_public' => false]);

        $this->actingAs($user)->get(route('users.bio', 'irfan-farooq'))
            ->assertOk()
            ->assertSee('Only you can see this page.');
    }

    public function test_deactivated_users_bio_is_not_found(): void
    {
        $this->createUser(['is_active' => false]);

        $this->get(route('users.bio', 'irfan-farooq'))->assertNotFound();
    }

    public function test_unknown_slug_is_not_found(): void
    {
        $this->get(route('users.bio', 'nobody-here'))->assertNotFound();
    }

    public function test_old_links_redirect_permanently_to_the_current_slug(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'second-slug']);
        $this->travel(31)->days();
        $this->actingAs($user)->put(route('profile.slug.update'), ['slug' => 'third-slug']);
        $this->post(route('logout'));

        $this->get(route('users.bio', 'irfan-farooq'))->assertStatus(301)->assertRedirect(route('users.bio', 'third-slug'));
        $this->get(route('users.bio', 'second-slug'))->assertStatus(301)->assertRedirect(route('users.bio', 'third-slug'));
    }

    public function test_empty_bio_shows_a_friendly_placeholder(): void
    {
        $this->createUser(['bio' => null]);

        $this->get(route('users.bio', 'irfan-farooq'))->assertSee("Irfan Farooq hasn't written a bio yet.", false);
    }
}
