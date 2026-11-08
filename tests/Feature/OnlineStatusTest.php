<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\PostComments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    private const DOT = 'class="online-dot"';

    public function test_signed_in_requests_record_activity_at_most_once_a_minute(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['last_seen_at' => null, 'updated_at' => now()->subYear()]);

        $this->actingAs($user)->get(route('home'));
        $first = $user->fresh()->last_seen_at;
        $this->assertTrue($first->equalTo(now()));
        $this->assertTrue($user->fresh()->updated_at->lessThan(now()->subMonths(11)), 'updated_at must not change');

        $this->travel(30)->seconds();
        $this->actingAs($user->fresh())->get(route('home'));
        $this->assertTrue($user->fresh()->last_seen_at->equalTo($first));

        $this->travel(31)->seconds();
        $this->actingAs($user->fresh())->get(route('home'));
        $this->assertTrue($user->fresh()->last_seen_at->greaterThan($first));
    }

    public function test_the_notification_bells_check_counts_as_activity(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subHour()]);

        $this->actingAs($user)->getJson(route('notifications.feed'))->assertOk();

        $this->assertTrue($user->fresh()->isOnline());
    }

    public function test_online_means_active_in_the_last_five_minutes(): void
    {
        $this->assertTrue(User::factory()->make(['last_seen_at' => now()->subMinutes(4)])->isOnline());
        $this->assertFalse(User::factory()->make(['last_seen_at' => now()->subMinutes(6)])->isOnline());
        $this->assertFalse(User::factory()->make(['last_seen_at' => null])->isOnline());
    }

    public function test_author_box_and_comments_show_the_dot_only_for_members_online_now(): void
    {
        $author = User::factory()->create(['last_seen_at' => now()->subMinute()]);
        $post = Post::factory()->published()->for($author)->create();
        $onlineReader = User::factory()->create(['name' => 'Online Reader', 'last_seen_at' => now()]);
        $awayReader = User::factory()->create(['name' => 'Away Reader', 'last_seen_at' => now()->subHour()]);
        app(PostComments::class)->add($onlineReader, $post, 'Hello');
        app(PostComments::class)->add($awayReader, $post, 'Hi');

        $html = $this->get(route('posts.show', $post))->assertOk()->getContent();

        // Author box + the online commenter.
        $this->assertSame(2, substr_count($html, self::DOT));
        $this->assertStringContainsString('Online Reader is online now', $html);
        $this->assertStringNotContainsString('Away Reader is online now', $html);
    }

    public function test_members_who_hide_their_status_get_no_dot_except_for_themselves(): void
    {
        $author = User::factory()->create(['last_seen_at' => now(), 'show_online_status' => false]);
        $post = Post::factory()->published()->for($author)->create();

        $this->get(route('posts.show', $post))->assertDontSee(self::DOT, false);
        $this->actingAs(User::factory()->create())->get(route('posts.show', $post))->assertDontSee(self::DOT, false);
        $this->actingAs($author)->get(route('posts.show', $post))->assertSee(self::DOT, false);
    }

    public function test_bio_page_says_active_now_or_last_seen(): void
    {
        $member = User::factory()->withPublicBio()->create(['slug' => 'sara', 'last_seen_at' => now()]);

        $this->get(route('users.bio', 'sara'))->assertSee('Active now')->assertSee(self::DOT, false);

        $member->forceFill(['last_seen_at' => now()->subHours(3)])->save();
        $this->get(route('users.bio', 'sara'))->assertSee('Last seen 3 hours ago')->assertDontSee(self::DOT, false);

        $member->forceFill(['last_seen_at' => null])->save();
        $this->get(route('users.bio', 'sara'))->assertDontSee('data-presence', false);

        $member->forceFill(['last_seen_at' => now(), 'show_online_status' => false])->save();
        $this->get(route('users.bio', 'sara'))->assertDontSee('Active now')->assertDontSee(self::DOT, false);
    }

    public function test_admin_users_list_counts_online_members_and_shows_last_seen(): void
    {
        $admin = User::factory()->admin()->create(['last_seen_at' => now()]);
        User::factory()->create(['name' => 'Online Hidden', 'last_seen_at' => now(), 'show_online_status' => false]);
        User::factory()->create(['name' => 'Seen Yesterday', 'last_seen_at' => now()->subDay()]);
        User::factory()->create(['name' => 'Never Seen', 'last_seen_at' => null]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-online-count="2"', false)
            ->assertSee('2 online now')
            ->assertSee('Online now')
            ->assertSee('1 day ago')
            ->assertSee('Never');

        // Admins see online members even when they hide their status from others.
        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'online']))
            ->assertSee('Online Hidden')
            ->assertDontSee('Seen Yesterday')
            ->assertDontSee('Never Seen');
    }

    public function test_members_can_turn_their_online_status_off_and_on(): void
    {
        $user = User::factory()->create();
        $input = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->get(route('profile.edit'))->assertSee("Show when I'm online", false);

        $this->actingAs($user)->patch(route('profile.update'), $input)->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->show_online_status);

        $this->actingAs($user)->patch(route('profile.update'), [...$input, 'show_online_status' => '1']);
        $this->assertTrue($user->fresh()->show_online_status);
    }
}
