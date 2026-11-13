<?php

namespace Tests\Feature\Posts;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\FeaturedImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'title' => 'My First Post',
            'summary' => 'A short summary.',
            'category_id' => Category::factory()->create()->id,
            'body' => '<p>Hello <strong>world</strong>.</p>',
            'action' => 'draft',
            'visibility' => 'public',
            'allow_comments' => '1',
        ], $overrides);
    }

    public function test_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('posts.create'))->assertRedirect(route('login'));
        $this->post(route('posts.store'), $this->input())->assertRedirect(route('login'));
    }

    public function test_unverified_user_must_verify_before_writing(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('posts.create'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_editor_offers_publish_and_draft_buttons_and_active_categories(): void
    {
        $general = Category::factory()->create(['name' => 'General', 'slug' => 'general']);
        Category::factory()->create(['name' => 'Science']);
        Category::factory()->inactive()->create(['name' => 'Hidden Topic']);

        $this->actingAs(User::factory()->create())->get(route('posts.create'))
            ->assertOk()
            ->assertSee('New post')
            ->assertSee('type="submit" value="publish"', false)
            ->assertSee('type="submit" value="draft"', false)
            ->assertSee('Preview')
            ->assertSee('value="public"', false)
            ->assertSee('value="private"', false)
            ->assertSee('value="restricted"', false)
            ->assertSee('<option value="'.$general->id.'" selected>General</option>', false)
            ->assertSee('Science')
            ->assertDontSee('Hidden Topic')
            ->assertDontSee('Uncategorised');
    }

    public function test_user_can_save_a_draft(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), $this->input());

        $post = Post::sole();
        $response->assertRedirect(route('posts.mine'))->assertSessionHas('success', 'Post created as a draft.');
        $this->assertTrue($post->user->is($user));
        $this->assertSame('my-first-post', $post->slug);
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);
        $this->assertTrue($post->allow_comments);
    }

    public function test_same_title_gets_a_numbered_slug(): void
    {
        Post::factory()->create(['slug' => 'my-first-post']);

        $this->actingAs(User::factory()->create())->post(route('posts.store'), $this->input());

        $this->assertDatabaseHas('posts', ['slug' => 'my-first-post-2']);
    }

    public function test_publishing_now_sets_the_publish_time(): void
    {
        $this->freezeSecond();

        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input(['action' => 'publish']))
            ->assertSessionHas('success', 'Post created and published.');

        $post = Post::sole();
        $this->assertTrue($post->published_at->equalTo(now()));
        $this->assertTrue(Post::publiclyVisible()->whereKey($post->id)->exists());
    }

    public function test_post_can_be_scheduled_for_later(): void
    {
        $goLive = now()->addDays(2)->startOfMinute();

        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input([
                'action' => 'publish',
                'schedule' => '1',
                'scheduled_at' => $goLive->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHas('success', 'Post created. It will go live on '.$goLive->format('j M Y, g:i a').'.');

        $post = Post::sole();
        $this->assertTrue($post->isScheduled());
        $this->assertTrue($post->published_at->equalTo($goLive));
        $this->assertFalse(Post::publiclyVisible()->exists());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing title' => [['title' => ''], 'title'],
            'title over 200 characters' => [['title' => str_repeat('a', 201)], 'title'],
            'summary over 500 characters' => [['summary' => str_repeat('a', 501)], 'summary'],
            'empty editor' => [['body' => '<p><br></p>'], 'body'],
            'only a script' => [['body' => '<script>alert(1)</script>'], 'body'],
            'missing category' => [['category_id' => ''], 'category_id'],
            'unknown category' => [['category_id' => 999], 'category_id'],
            'unknown button' => [['action' => 'delete-everything'], 'action'],
            'restricted without a password' => [['action' => 'publish', 'visibility' => 'restricted'], 'access_password'],
            'restricted with a short password' => [['action' => 'publish', 'visibility' => 'restricted', 'access_password' => 'abc'], 'access_password'],
            'schedule without a time' => [['action' => 'publish', 'schedule' => '1', 'scheduled_at' => ''], 'scheduled_at'],
            'schedule in the past' => [['action' => 'publish', 'schedule' => '1', 'scheduled_at' => '2020-01-01T10:00'], 'scheduled_at'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_post_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_editor_sends_the_clicked_button_through_a_hidden_field(): void
    {
        $this->actingAs(User::factory()->create())->get(route('posts.create'))
            ->assertSee('<input type="hidden" name="action"', false)
            ->assertDontSee('name="action" value="publish" class="btn', false);
    }

    public function test_editor_has_a_write_step_and_a_compact_publishing_options_step(): void
    {
        $this->actingAs(User::factory()->create())->get(route('posts.create'))
            ->assertOk()
            ->assertSee('data-start-step="1"', false)
            ->assertSeeInOrder(['data-step="1"', 'Title', 'Content', 'Next: publishing options', 'data-step="2"'], false)
            ->assertSeeInOrder(['Who can read it?', 'Everyone', 'Only me', 'Invited people only', 'Schedule for later', 'Allow comments', 'Category', 'Featured image'])
            ->assertSee('data-bs-title="Nobody else can open it. Good for personal notes."', false)
            ->assertSee('Want feedback before everyone can read it?')
            ->assertDontSee('id="summary"', false);
    }

    public function test_scheduled_time_is_read_in_the_writers_own_time_zone_and_saved_in_utc(): void
    {
        $this->freezeSecond();
        $goLiveInKarachi = now('Asia/Karachi')->addHours(2)->startOfMinute();

        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input([
                'action' => 'publish',
                'schedule' => '1',
                'scheduled_at' => $goLiveInKarachi->format('Y-m-d\TH:i'),
                'timezone' => 'Asia/Karachi',
            ]))
            ->assertSessionHas('success', 'Post created. It will go live on '.$goLiveInKarachi->format('j M Y, g:i a').'.');

        $this->assertTrue(Post::sole()->published_at->equalTo($goLiveInKarachi));
        $this->assertSame('UTC', Post::sole()->published_at->timezoneName);
    }

    public function test_a_time_already_past_in_the_writers_time_zone_is_rejected(): void
    {
        // One hour ago in Karachi would be four hours ahead if it were read as UTC.
        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input([
                'action' => 'publish',
                'schedule' => '1',
                'scheduled_at' => now('Asia/Karachi')->subHour()->format('Y-m-d\TH:i'),
                'timezone' => 'Asia/Karachi',
            ]))
            ->assertSessionHasErrors(['scheduled_at' => 'The scheduled time must be in the future.']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_an_unknown_time_zone_is_treated_as_utc(): void
    {
        $goLive = now()->addDay()->startOfMinute();

        $this->actingAs(User::factory()->create())->post(route('posts.store'), $this->input([
            'action' => 'publish', 'schedule' => '1', 'scheduled_at' => $goLive->format('Y-m-d\TH:i'), 'timezone' => 'Mars/Olympus',
        ]));

        $this->assertTrue(Post::sole()->published_at->equalTo($goLive));
    }

    public function test_editing_a_scheduled_post_sends_its_time_in_utc_for_the_browser_to_convert(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->scheduled()->create();

        $this->actingAs($user)->get(route('posts.edit', $post))
            ->assertSee(str_replace('/', '\/', json_encode($post->published_at->toIso8601String())), false)
            ->assertSee('name="timezone"', false);
    }

    public function test_saving_returns_to_my_posts_but_a_restricted_post_opens_its_invites(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), $this->input(['action' => 'publish']))
            ->assertRedirect(route('posts.mine'))
            ->assertSessionHas('success', 'Post created and published.');

        $this->actingAs($user)->post(route('posts.store'), $this->input(['title' => 'Secret', 'action' => 'publish', 'visibility' => 'restricted', 'access_password' => 'secret123']))
            ->assertRedirect(route('posts.edit', 'secret').'#sharing');
    }

    public function test_admin_editing_someone_elses_post_returns_to_the_admin_post_list(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->put(route('posts.update', $post), $this->input())
            ->assertRedirect(route('admin.posts.index'));
    }

    public function test_an_existing_summary_is_kept_when_editing(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['summary' => 'Written before the field was removed.']);

        $this->actingAs($user)->get(route('posts.edit', $post))
            ->assertSee('<input type="hidden" name="summary" value="Written before the field was removed.">', false);
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function failedFieldStep(): array
    {
        return [
            'missing title reopens on Write' => [['title' => ''], 1],
            'missing content reopens on Write' => [['body' => ''], 1],
            'summary too long reopens on the options' => [['summary' => str_repeat('a', 501)], 2],
            'missing category reopens on the options' => [['category_id' => ''], 2],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('failedFieldStep')]
    public function test_editor_reopens_on_the_step_with_the_error(array $overrides, int $step): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('posts.create'))->post(route('posts.store'), $this->input($overrides));

        $this->actingAs($user)->get(route('posts.create'))->assertSee('data-start-step="'.$step.'"', false);
    }

    public function test_errors_without_a_field_of_their_own_are_listed_at_the_top(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), $this->input(['action' => '']));

        $this->actingAs($user)->get(route('posts.create'))
            ->assertSee('Please fix the highlighted fields below.')
            ->assertSee('The action field is required.');
    }

    public function test_inactive_category_cannot_be_chosen(): void
    {
        $hidden = Category::factory()->inactive()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input(['category_id' => $hidden->id]))
            ->assertSessionHasErrors(['category_id' => 'Choose one of the listed categories.']);
    }

    public function test_post_body_is_cleaned_of_dangerous_html(): void
    {
        $this->actingAs(User::factory()->create())->post(route('posts.store'), $this->input([
            'body' => '<h2 class="ql-align-center">Intro</h2>'
                .'<p onclick="steal()">Safe <em>text</em><script>alert(1)</script></p>'
                .'<p><a href="javascript:alert(1)">bad link</a> <a href="https://example.com">good link</a></p>'
                .'<ul><li>One</li></ul><iframe src="https://evil.test"></iframe>'
                .'<img src="https://evil.test/track.gif">',
        ]));

        $body = Post::sole()->body;
        $this->assertStringContainsString('<h2 class="ql-align-center">Intro</h2>', $body);
        $this->assertStringContainsString('<em>text</em>', $body);
        $this->assertStringContainsString('<ul><li>One</li></ul>', $body);
        $this->assertStringContainsString('<a href="https://example.com" rel="noopener noreferrer nofollow ugc">good link</a>', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('iframe', $body);
        $this->assertStringNotContainsString('evil.test', $body);
    }

    public function test_non_breaking_spaces_from_the_editor_become_normal_spaces(): void
    {
        $this->actingAs(User::factory()->create())->post(route('posts.store'), $this->input([
            'body' => '<p>Text&nbsp;with&nbsp;spaces</p>',
        ]));

        $this->assertSame('<p>Text with spaces</p>', Post::sole()->body);
    }

    public function test_featured_image_is_stored_as_a_webp_no_wider_than_1600px(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), $this->input([
            'featured_image' => UploadedFile::fake()->image('cover.jpg', 2400, 1200),
        ]));

        $path = Post::sole()->featured_image;
        $this->assertStringStartsWith("posts/{$user->id}/featured/", $path);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([FeaturedImageProcessor::MAX_WIDTH, 800, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    public function test_too_small_featured_image_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input(['featured_image' => UploadedFile::fake()->image('tiny.jpg', 200, 100)]))
            ->assertSessionHasErrors('featured_image');
    }

    public function test_author_can_edit_their_post_without_changing_its_slug(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['title' => 'Old title', 'slug' => 'old-title']);

        $this->actingAs($user)->get(route('posts.edit', $post))->assertOk()->assertSee('Edit post')->assertSee('Old title');

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['title' => 'New title']))
            ->assertRedirect(route('posts.mine'))
            ->assertSessionHas('success', 'Post saved as a draft.');

        $post->refresh();
        $this->assertSame('New title', $post->title);
        $this->assertSame('old-title', $post->slug);
    }

    public function test_editing_a_live_post_keeps_its_original_publish_date(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->published()->create(['published_at' => now()->subMonth()->startOfSecond()]);
        $original = $post->published_at;

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['action' => 'publish']));

        $this->assertTrue($post->fresh()->published_at->equalTo($original));
    }

    public function test_save_draft_on_a_live_post_unpublishes_it(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->published()->create();

        $this->actingAs($user)->get(route('posts.edit', $post))->assertSee('Unpublish (back to draft)');

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['action' => 'draft']))
            ->assertSessionHas('success', 'Post unpublished. It is a draft again, so only you can see it.');

        $post->refresh();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_hidden_schedule_time_is_ignored_unless_scheduling(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('posts.store'), $this->input(['action' => 'publish', 'visibility' => 'private', 'scheduled_at' => '2020-01-01T10:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(PostStatus::Private, Post::sole()->status);
    }

    public function test_publishing_privately_clears_the_publish_date(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->published()->create();

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['action' => 'publish', 'visibility' => 'private']))
            ->assertSessionHas('success', 'Post saved. It is private, so only you can see it.');

        $post->refresh();
        $this->assertSame(PostStatus::Private, $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_replacing_and_removing_the_featured_image_deletes_old_files(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('posts.store'), $this->input(['featured_image' => UploadedFile::fake()->image('a.jpg', 800, 400)]));
        $post = Post::sole();
        $first = $post->featured_image;

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['featured_image' => UploadedFile::fake()->image('b.jpg', 800, 400)]));
        $second = $post->fresh()->featured_image;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['remove_featured_image' => '1']));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($post->fresh()->featured_image);
    }

    public function test_already_restricted_post_can_keep_its_status_when_edited(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->restricted()->create();

        $this->actingAs($user)->get(route('posts.edit', $post))->assertSee('value="restricted"', false);

        $this->actingAs($user)->put(route('posts.update', $post), $this->input(['action' => 'publish', 'visibility' => 'restricted']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Post saved. It is restricted: only people you invite (with the password) can read it.');

        $this->assertSame(PostStatus::Restricted, $post->fresh()->status);
    }

    public function test_other_users_cannot_edit_or_delete_a_post(): void
    {
        $post = Post::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('posts.edit', $post))->assertForbidden();
        $this->actingAs($intruder)->put(route('posts.update', $post), $this->input())->assertForbidden();
        $this->actingAs($intruder)->delete(route('posts.destroy', $post))->assertForbidden();

        $this->assertModelExists($post);
    }

    public function test_admin_can_edit_any_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('posts.edit', $post))->assertOk();
    }

    public function test_author_can_delete_their_post_and_its_image(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('posts.store'), $this->input(['featured_image' => UploadedFile::fake()->image('a.jpg', 800, 400)]));
        $post = Post::sole();

        $this->actingAs($user)->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.mine'))
            ->assertSessionHas('success', '"My First Post" was deleted.');

        $this->assertModelMissing($post);
        Storage::disk('public')->assertMissing($post->featured_image);
    }

    public function test_protected_fields_cannot_be_set_from_the_form(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), $this->input([
            'user_id' => $other->id,
            'slug' => 'chosen-slug',
            'likes_count' => 999,
            'views_count' => 999,
        ]));

        $post = Post::sole();
        $this->assertTrue($post->user->is($user));
        $this->assertSame('my-first-post', $post->slug);
        $this->assertSame(0, $post->likes_count);
        $this->assertSame(0, $post->views_count);
    }
}
