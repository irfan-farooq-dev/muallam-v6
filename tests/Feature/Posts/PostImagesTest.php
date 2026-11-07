<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Services\PostImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function upload(User $user, string $name = 'diagram.png', int $width = 2000, int $height = 1000): PostImage
    {
        $this->actingAs($user)
            ->postJson(route('posts.images.store'), ['image' => UploadedFile::fake()->image($name, $width, $height)])
            ->assertCreated();

        return PostImage::latest('id')->first();
    }

    private function imageTag(PostImage $image): string
    {
        return '<img src="'.Storage::disk('public')->url($image->path).'" alt="A diagram" width="'.$image->width.'" height="'.$image->height.'">';
    }

    /**
     * @return array<string, mixed>
     */
    private function postInput(string $body): array
    {
        return [
            'title' => 'Post with images',
            'category_id' => Category::factory()->create()->id,
            'body' => $body,
            'action' => 'draft',
            'visibility' => 'public',
        ];
    }

    public function test_upload_returns_the_url_and_size_of_a_resized_webp(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.images.store'), [
            'image' => UploadedFile::fake()->image('diagram.png', 2400, 1200),
            'alt' => 'Network diagram',
        ]);

        $image = PostImage::sole();
        $response->assertCreated()->assertExactJson([
            'url' => Storage::disk('public')->url($image->path),
            'width' => PostImageStore::MAX_WIDTH,
            'height' => 800,
            'alt' => 'Network diagram',
        ]);

        $this->assertNull($image->post_id);
        $this->assertTrue($image->user->is($user));
        $this->assertMatchesRegularExpression("#^posts/{$user->id}/[0-9a-f-]{36}\.webp$#", $image->path);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($image->path));
        $this->assertSame([1600, 800, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    /**
     * @return array<string, array{callable(): mixed}>
     */
    public static function invalidUploads(): array
    {
        return [
            'nothing sent' => [fn () => null],
            'pdf' => [fn () => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            'gif' => [fn () => UploadedFile::fake()->image('anim.gif', 300, 300)],
            'over 5 MB' => [fn () => UploadedFile::fake()->image('big.jpg', 300, 300)->size(5121)],
            'too small' => [fn () => UploadedFile::fake()->image('dot.png', 20, 20)],
        ];
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_upload_is_rejected(callable $makeFile): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('posts.images.store'), ['image' => $makeFile()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('post_images', 0);
    }

    public function test_only_verified_signed_in_users_can_upload(): void
    {
        $file = fn () => UploadedFile::fake()->image('a.png', 300, 300);

        $this->postJson(route('posts.images.store'), ['image' => $file()])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson(route('posts.images.store'), ['image' => $file()])
            ->assertForbidden();
    }

    public function test_saving_a_post_links_the_images_its_body_uses(): void
    {
        $user = User::factory()->create();
        $used = $this->upload($user);
        $unused = $this->upload($user, 'other.png');

        $this->actingAs($user)->post(route('posts.store'), $this->postInput('<p>See:</p><p>'.$this->imageTag($used).'</p>'));

        $post = Post::sole();
        $this->assertStringContainsString('<img src="'.Storage::disk('public')->url($used->path).'"', $post->body);
        $this->assertSame($post->id, $used->fresh()->post_id);
        $this->assertNull($unused->fresh()->post_id);
    }

    public function test_removing_an_image_from_the_body_deletes_it_on_save(): void
    {
        $user = User::factory()->create();
        $first = $this->upload($user);
        $second = $this->upload($user, 'second.png');
        $this->actingAs($user)->post(route('posts.store'), $this->postInput('<p>'.$this->imageTag($first).$this->imageTag($second).'</p>'));
        $post = Post::sole();

        $this->actingAs($user)->put(route('posts.update', $post), $this->postInput('<p>'.$this->imageTag($second).'</p><p>Text</p>'));

        $this->assertModelMissing($first);
        Storage::disk('public')->assertMissing($first->path);
        $this->assertSame($post->id, $second->fresh()->post_id);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_someone_elses_upload_is_not_linked_to_your_post(): void
    {
        $theirs = $this->upload(User::factory()->create());
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), $this->postInput('<p>'.$this->imageTag($theirs).'</p>'));

        $this->assertNull($theirs->fresh()->post_id);
    }

    public function test_images_from_other_websites_are_removed_from_the_body(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('posts.store'), $this->postInput('<p>Text <img src="https://elsewhere.test/pic.jpg"></p>'));

        $this->assertStringNotContainsString('elsewhere.test', Post::sole()->body);
    }

    public function test_deleting_a_post_deletes_its_body_images(): void
    {
        $user = User::factory()->create();
        $image = $this->upload($user);
        $this->actingAs($user)->post(route('posts.store'), $this->postInput('<p>'.$this->imageTag($image).'</p>'));

        $this->actingAs($user)->delete(route('posts.destroy', Post::sole()));

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_prune_command_deletes_only_old_unused_uploads(): void
    {
        $user = User::factory()->create();
        $old = $this->upload($user, 'old.png');
        $this->travel(25)->hours();
        $recent = $this->upload($user, 'recent.png');
        $attached = PostImage::factory()->create(['created_at' => now()->subDays(3)]);

        $this->artisan('posts:prune-images')->expectsOutput('Deleted 1 unused image(s).')->assertSuccessful();

        $this->assertModelMissing($old);
        Storage::disk('public')->assertMissing($old->path);
        $this->assertModelExists($recent);
        $this->assertModelExists($attached);
    }

    public function test_editor_has_an_image_button_wired_to_the_upload_route(): void
    {
        $this->actingAs(User::factory()->create())->get(route('posts.create'))
            ->assertSee("['link', 'image']", false)
            ->assertSee('imageUploadUrl', false)
            ->assertSee('\\\\\\/posts\\\\\\/images', false);
    }
}
