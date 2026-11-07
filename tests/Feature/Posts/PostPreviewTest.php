<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_shows_unsaved_content_as_card_and_full_post_without_saving(): void
    {
        $category = Category::factory()->create(['name' => 'Science']);
        $user = User::factory()->create(['name' => 'Irfan Farooq']);

        $this->actingAs($user)->post(route('posts.preview'), [
            'title' => 'Draft title',
            'summary' => 'Short summary',
            'category_id' => $category->id,
            'body' => '<h2>Heading</h2><p>Body&nbsp;text<script>alert(1)</script></p>',
            'action' => 'preview',
        ])
            ->assertOk()
            ->assertSee('This is how readers will see your post.')
            ->assertSee('On the home page and category lists')
            ->assertSee('Draft title')
            ->assertSee('Short summary')
            ->assertSee('Science')
            ->assertSee('Irfan Farooq')
            ->assertSee('<h2>Heading</h2><p>Body text</p>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('noindex', false);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_preview_shows_a_newly_chosen_image_without_storing_it(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())->post(route('posts.preview'), [
            'title' => 'With image',
            'body' => '<p>Text</p>',
            'featured_image' => UploadedFile::fake()->image('cover.jpg', 800, 400),
        ])->assertSee('src="data:image/jpeg;base64,', false);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_preview_from_the_edit_form_uses_the_saved_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['featured_image' => 'posts/1/featured/cover.webp']);

        $this->actingAs($user)->put(route('posts.preview'), ['title' => 'Edited', 'body' => '<p>Text</p>', 'preview_post' => $post->slug])
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('posts/1/featured/cover.webp'));
    }

    public function test_preview_of_someone_elses_post_is_forbidden(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('posts.preview'), ['title' => 'x', 'body' => '<p>x</p>', 'preview_post' => $post->slug])
            ->assertForbidden();
    }

    public function test_empty_title_falls_back_to_untitled(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('posts.preview'), ['title' => '', 'body' => ''])
            ->assertOk()
            ->assertSee('Untitled post');
    }

    public function test_reopening_a_preview_address_goes_back_to_my_posts(): void
    {
        $this->actingAs(User::factory()->create())->get('/posts/preview')
            ->assertRedirect(route('posts.mine'))
            ->assertSessionHas('info');
    }

    public function test_guests_cannot_preview(): void
    {
        $this->post(route('posts.preview'), ['title' => 'x'])->assertRedirect(route('login'));
    }
}
