<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Site Admin', 'email' => 'admin@example.com']);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active ? '1' : '0',
            'email_verified' => $user->hasVerifiedEmail() ? '1' : '0',
        ], $overrides);
    }

    public function test_members_cannot_open_user_management(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($member)->put(route('admin.users.update', $member), $this->input($member, ['role' => 'admin']))->assertForbidden();
        $this->assertFalse($member->fresh()->isAdmin());
    }

    public function test_list_shows_users_with_role_status_and_post_counts(): void
    {
        $author = User::factory()->create(['name' => 'Busy Author']);
        Post::factory()->for($author)->count(3)->create();
        User::factory()->inactive()->create(['name' => 'Blocked Person']);
        User::factory()->unverified()->create(['name' => 'New Person']);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Busy Author')
            ->assertSee('Deactivated')
            ->assertSee('Not verified')
            ->assertSee('>3</a>', false);
    }

    public function test_list_can_be_searched_and_filtered(): void
    {
        User::factory()->create(['name' => 'Zafar Iqbal', 'email' => 'zafar@example.com']);
        User::factory()->inactive()->create(['name' => 'Blocked Person']);
        User::factory()->unverified()->create(['name' => 'New Person']);

        $list = fn (array $query) => $this->actingAs($this->admin)->get(route('admin.users.index', $query));

        $list(['search' => 'zafar@'])->assertSee('Zafar Iqbal')->assertDontSee('Blocked Person');
        $list(['status' => 'inactive'])->assertSee('Blocked Person')->assertDontSee('Zafar Iqbal');
        $list(['verified' => 'no'])->assertSee('New Person')->assertDontSee('Zafar Iqbal');
        $list(['role' => 'admin'])->assertSee('Site Admin')->assertDontSee('Zafar Iqbal');
    }

    public function test_admin_can_edit_name_email_role_and_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($this->admin)->get(route('admin.users.edit', $user))->assertOk()->assertSee('Delete this user');

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), $this->input($user, [
            'name' => 'Renamed Person',
            'email' => 'New@Example.com',
            'role' => 'admin',
            'email_verified' => '1',
        ]))->assertRedirect(route('admin.users.edit', $user))->assertSessionHas('success', 'User saved.');

        $user->refresh();
        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_email_already_used_by_someone_else_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), $this->input($user, ['email' => 'admin@example.com']))
            ->assertSessionHasErrors(['email' => 'Another account already uses this email.']);
    }

    public function test_deactivating_signs_the_user_out_everywhere(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'their-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), $this->input($user, ['is_active' => '0']))
            ->assertSessionHas('success', "{$user->name} is deactivated and has been signed out everywhere.");

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'their-session']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_a_signed_in_user_who_gets_deactivated_is_signed_out_on_their_next_click(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account has been deactivated. Please contact support.');
        $this->assertGuest();
    }

    public function test_admins_cannot_lock_themselves_out(): void
    {
        User::factory()->admin()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), $this->input($this->admin, ['role' => 'author']))
            ->assertSessionHasErrors(['role' => 'You cannot remove your own admin role.']);
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), $this->input($this->admin, ['is_active' => '0']))
            ->assertSessionHasErrors(['is_active' => 'You cannot deactivate your own account.']);
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin), ['confirm_email' => 'admin@example.com'])
            ->assertSessionHas('error', 'You cannot delete your own account here.');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_the_last_active_admin_cannot_be_demoted(): void
    {
        $other = User::factory()->admin()->inactive()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $other), $this->input($other, ['role' => 'author', 'is_active' => '0']))
            ->assertSessionHasNoErrors();

        $second = User::factory()->admin()->create();
        $this->actingAs($second)->put(route('admin.users.update', $this->admin), $this->input($this->admin, ['role' => 'author']))
            ->assertSessionHasNoErrors();
        $this->actingAs($second)->put(route('admin.users.update', $second), $this->input($second, ['role' => 'author']))
            ->assertSessionHasErrors('role');
    }

    public function test_delete_needs_the_email_typed_to_confirm(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user), ['confirm_email' => 'wrong@example.com'])
            ->assertSessionHasErrors(['confirm_email' => "Type the user's email exactly to confirm."]);

        $this->assertModelExists($user);
    }

    public function test_deleting_a_user_removes_their_content_and_fixes_other_posts_counters(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email' => 'person@example.com', 'avatar' => 'avatars/9/me.webp']);
        $ownPost = Post::factory()->for($user)->published()->create(['featured_image' => 'posts/9/featured/cover.webp']);
        PostImage::factory()->for($user)->for($ownPost)->create(['path' => 'posts/9/inline.webp']);
        Storage::disk('public')->put('avatars/9/me.webp', 'x');
        Storage::disk('public')->put('posts/9/featured/cover.webp', 'x');
        Storage::disk('public')->put('posts/9/inline.webp', 'x');

        $otherPost = Post::factory()->published()->create(['likes_count' => 2, 'comments_count' => 3]);
        Like::factory()->for($user)->for($otherPost)->create();
        Like::factory()->for($otherPost)->create();
        $theirComment = Comment::factory()->for($user)->for($otherPost)->create();
        Comment::factory()->replyTo($theirComment)->create();
        Comment::factory()->for($otherPost)->create();

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user), ['confirm_email' => 'person@example.com'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', "{$user->name} was deleted, with 1 posts, 1 comments and 1 likes.");

        $this->assertModelMissing($user);
        $this->assertModelMissing($ownPost);
        Storage::disk('public')->assertMissing(['avatars/9/me.webp', 'posts/9/featured/cover.webp', 'posts/9/inline.webp']);

        $otherPost->refresh();
        $this->assertSame(1, $otherPost->likes_count);
        $this->assertSame(1, $otherPost->comments_count);
    }

    public function test_an_inactive_admin_can_be_deleted_by_the_remaining_active_admin(): void
    {
        $other = User::factory()->admin()->create(['email' => 'second@example.com']);
        $this->admin->forceFill(['is_active' => false])->save();

        $this->actingAs($other)->delete(route('admin.users.destroy', $this->admin), ['confirm_email' => 'admin@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($this->admin);
    }

    public function test_sidebar_links_to_users(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee(route('admin.users.index'));
    }
}
