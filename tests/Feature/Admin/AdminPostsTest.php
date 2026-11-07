<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPostsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    public function test_members_cannot_use_the_admin_post_list_or_actions(): void
    {
        $member = User::factory()->create();
        $post = Post::factory()->published()->create();

        $this->actingAs($member)->get(route('admin.posts.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.posts.unpublish', $post))->assertForbidden();
        $this->actingAs($member)->delete(route('admin.posts.destroy', $post))->assertForbidden();

        $this->assertModelExists($post);
        $this->assertSame(PostStatus::Published, $post->fresh()->status);
    }

    public function test_list_shows_every_authors_posts_including_drafts(): void
    {
        Post::factory()->published()->for(User::factory()->create(['name' => 'Amina Khan']))->create(['title' => 'Amina public post']);
        Post::factory()->draft()->for(User::factory()->create(['name' => 'Bilal Ahmed']))->create(['title' => 'Bilal secret draft']);

        $this->actingAs($this->admin)->get(route('admin.posts.index'))
            ->assertOk()
            ->assertSee('Amina public post')
            ->assertSee('by Amina Khan')
            ->assertSee('Bilal secret draft')
            ->assertSee('by Bilal Ahmed')
            ->assertSee('2 posts');
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function filters(): array
    {
        return [
            'title search' => [['search' => 'garden'], 'Garden diary', 'Science notes'],
            'author name' => [['author' => 'amina'], 'Garden diary', 'Science notes'],
            'author email' => [['author' => 'bilal@example.com'], 'Science notes', 'Garden diary'],
            'status draft' => [['status' => 'draft'], 'Science notes', 'Garden diary'],
            'status published' => [['status' => 'published'], 'Garden diary', 'Science notes'],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('filters')]
    public function test_list_can_be_filtered(array $query, string $shown, string $hidden): void
    {
        Post::factory()->published()->for(User::factory()->create(['name' => 'Amina Khan']))->create(['title' => 'Garden diary']);
        Post::factory()->draft()->for(User::factory()->create(['name' => 'Bilal Ahmed', 'email' => 'bilal@example.com']))->create(['title' => 'Science notes']);

        $this->actingAs($this->admin)->get(route('admin.posts.index', $query))
            ->assertOk()
            ->assertSee($shown)
            ->assertDontSee($hidden);
    }

    public function test_list_can_be_filtered_by_category_and_scheduled_status(): void
    {
        $travel = Category::factory()->create(['name' => 'Travel']);
        Post::factory()->scheduled()->for($travel)->create(['title' => 'Trip next month']);
        Post::factory()->published()->for($travel)->create(['title' => 'Trip last year']);
        Post::factory()->scheduled()->create(['title' => 'Other scheduled post']);

        $this->actingAs($this->admin)->get(route('admin.posts.index', ['category' => $travel->id, 'status' => 'scheduled']))
            ->assertOk()
            ->assertSee('Trip next month')
            ->assertDontSee('Trip last year')
            ->assertDontSee('Other scheduled post');
    }

    public function test_admin_can_unpublish_a_post_back_to_draft(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Off-topic post']);

        $this->actingAs($this->admin)
            ->from(route('admin.posts.index'))
            ->post(route('admin.posts.unpublish', $post))
            ->assertRedirect(route('admin.posts.index'))
            ->assertSessionHas('success', '"Off-topic post" is now a draft and no longer public.');

        $post->refresh();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);
        $this->get(route('posts.show', $post))->assertOk();
        $this->post(route('logout'));
        $this->get(route('posts.show', $post))->assertNotFound();
    }

    public function test_admin_can_delete_a_post_with_its_images(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Spam post', 'featured_image' => 'featured/1/cover.webp']);
        $image = PostImage::factory()->for($post)->create();
        Storage::disk('public')->put($image->path, 'image');
        Storage::disk('public')->put('featured/1/cover.webp', 'image');

        $this->actingAs($this->admin)->delete(route('admin.posts.destroy', $post))
            ->assertRedirect(route('admin.posts.index'))
            ->assertSessionHas('success', '"Spam post" was deleted.');

        $this->assertModelMissing($post);
        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);
        Storage::disk('public')->assertMissing('featured/1/cover.webp');
    }

    public function test_admin_deleting_someone_elses_post_from_the_editor_returns_to_the_admin_list(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($this->admin)->delete(route('posts.destroy', $post))->assertRedirect(route('admin.posts.index'));

        $this->assertModelMissing($post);
    }

    public function test_admin_list_marks_posts_with_unreviewed_admin_changes(): void
    {
        $post = Post::factory()->create(['title' => 'Edited post']);
        $post->adminEdits()->create(['previous_title' => 'Old', 'previous_summary' => null, 'previous_body' => '<p>Old</p>']);

        $this->actingAs($this->admin)->get(route('admin.posts.index'))->assertSee('Admin edit');
    }
}
