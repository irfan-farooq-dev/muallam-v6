<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\PostShare;
use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_defaults_to_author_with_private_bio(): void
    {
        $user = User::forceCreate([
            'name' => 'Irfan Farooq',
            'slug' => 'irfan-farooq',
            'email' => 'irfan@example.com',
            'password' => 'password',
        ])->refresh();

        $this->assertSame(UserRole::Author, $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->is_bio_public);
        $this->assertTrue($user->is_active);
    }

    public function test_two_users_cannot_share_a_slug(): void
    {
        User::factory()->create(['slug' => 'irfan-farooq']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['slug' => 'irfan-farooq']);
    }

    public function test_a_retired_slug_can_only_be_recorded_once(): void
    {
        UserSlugHistory::factory()->create(['slug' => 'irfan-farooq']);

        $this->expectException(UniqueConstraintViolationException::class);

        UserSlugHistory::factory()->create(['slug' => 'irfan-farooq']);
    }

    public function test_a_user_can_like_a_post_only_once(): void
    {
        $like = Like::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Like::factory()->create(['user_id' => $like->user_id, 'post_id' => $like->post_id]);
    }

    public function test_a_post_can_be_shared_with_an_email_only_once(): void
    {
        $share = PostShare::factory()->create(['email' => 'friend@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        PostShare::factory()->for($share->post)->create(['email' => 'friend@example.com']);
    }

    public function test_the_same_email_can_be_invited_to_different_posts(): void
    {
        PostShare::factory()->create(['email' => 'friend@example.com']);
        PostShare::factory()->create(['email' => 'friend@example.com']);

        $this->assertSame(2, PostShare::where('email', 'friend@example.com')->count());
    }

    public function test_deleting_a_category_keeps_its_posts_without_a_category(): void
    {
        $post = Post::factory()->create();

        $post->category->delete();

        $this->assertNull($post->fresh()->category_id);
    }

    public function test_deleting_a_post_removes_its_likes_comments_images_and_shares(): void
    {
        $post = Post::factory()->published()->create();
        Like::factory()->for($post)->create();
        Comment::factory()->for($post)->create();
        PostImage::factory()->for($post)->create();
        PostShare::factory()->for($post)->create();

        $post->delete();

        $this->assertDatabaseCount('likes', 0);
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('post_images', 0);
        $this->assertDatabaseCount('post_shares', 0);
    }

    public function test_deleting_a_user_removes_their_content(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();
        UserSlugHistory::factory()->for($user)->create();
        PostImage::factory()->for($user)->unattached()->create();
        $otherPost = Post::factory()->published()->create();
        Like::factory()->for($user)->for($otherPost)->create();
        Comment::factory()->for($user)->for($otherPost)->create();

        $user->delete();

        $this->assertModelMissing($post);
        $this->assertModelExists($otherPost);
        $this->assertDatabaseCount('user_slug_history', 0);
        $this->assertDatabaseCount('post_images', 0);
        $this->assertDatabaseCount('likes', 0);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_soft_deleting_a_comment_keeps_its_replies(): void
    {
        $comment = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($comment)->create();

        $comment->delete();

        $this->assertSoftDeleted($comment);
        $this->assertModelExists($reply);
        $this->assertTrue($reply->fresh()->parent()->withTrashed()->first()->is($comment));
    }

    public function test_editor_image_can_exist_before_it_belongs_to_a_post(): void
    {
        $image = PostImage::factory()->unattached()->create();

        $this->assertNull($image->post_id);
        $this->assertStringStartsWith('posts/'.$image->user_id.'/', $image->path);
    }

    public function test_active_categories_are_listed_in_sort_order(): void
    {
        Category::factory()->create(['name' => 'Second', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'First', 'sort_order' => 1]);
        Category::factory()->inactive()->create(['name' => 'Hidden', 'sort_order' => 0]);

        $this->assertSame(['First', 'Second'], Category::active()->pluck('name')->all());
    }
}
