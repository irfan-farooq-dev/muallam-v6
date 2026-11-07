<?php

namespace Tests\Feature\Admin;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\PostComments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCommentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->post = Post::factory()->published()->create(['title' => 'Busy post']);
    }

    private function comment(string $body, ?User $author = null, ?Post $post = null): Comment
    {
        return app(PostComments::class)->add($author ?? User::factory()->create(), $post ?? $this->post, $body);
    }

    public function test_members_cannot_moderate_from_the_admin_panel(): void
    {
        $member = User::factory()->create();
        $comment = $this->comment('Hello');

        $this->actingAs($member)->get(route('admin.comments.index'))->assertForbidden();
        $this->actingAs($member)->patch(route('admin.comments.visibility', $comment), ['visible' => '0'])->assertForbidden();
        $this->actingAs($member)->delete(route('admin.comments.destroy', $comment))->assertForbidden();
        $this->actingAs($member)->post(route('admin.comments.bulk'), ['action' => 'delete', 'comments' => [$comment->id]])->assertForbidden();

        $this->assertTrue($comment->fresh()->is_approved);
        $this->assertNotSoftDeleted($comment);
    }

    public function test_list_shows_comments_newest_first_with_writer_and_post(): void
    {
        $this->comment('First comment', User::factory()->create(['name' => 'Amina Khan']));
        $this->travel(1)->minute();
        $parent = $this->comment('Second comment', User::factory()->create(['name' => 'Bilal Ahmed']));
        app(PostComments::class)->add(User::factory()->create(['name' => 'Sara Ali']), $this->post, 'A reply', $parent);

        $this->actingAs($this->admin)->get(route('admin.comments.index'))
            ->assertOk()
            ->assertSeeInOrder(['A reply', 'Second comment', 'First comment'])
            ->assertSee('replying to Bilal Ahmed')
            ->assertSee('Busy post')
            ->assertSee(route('posts.show', $this->post).'#comment-'.$parent->id, false)
            ->assertSee('3 comments');
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function filters(): array
    {
        return [
            'text' => [['search' => 'spam'], 'Buy cheap spam', 'Lovely article'],
            'writer name' => [['author' => 'amina'], 'Lovely article', 'Buy cheap spam'],
            'writer email' => [['author' => 'spammer@example.com'], 'Buy cheap spam', 'Lovely article'],
            'hidden' => [['status' => 'hidden'], 'Buy cheap spam', 'Lovely article'],
            'visible' => [['status' => 'visible'], 'Lovely article', 'Buy cheap spam'],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('filters')]
    public function test_list_can_be_filtered(array $query, string $shown, string $hidden): void
    {
        $this->comment('Lovely article', User::factory()->create(['name' => 'Amina Khan']));
        $spam = $this->comment('Buy cheap spam', User::factory()->create(['email' => 'spammer@example.com']));
        app(PostComments::class)->setVisible($spam, false);

        $this->actingAs($this->admin)->get(route('admin.comments.index', $query))
            ->assertOk()
            ->assertSee($shown)
            ->assertDontSee($hidden);
    }

    public function test_deleted_comments_appear_only_under_the_deleted_filter(): void
    {
        $this->comment('Still here');
        app(PostComments::class)->remove($this->comment('Gone now'));

        $this->actingAs($this->admin)->get(route('admin.comments.index'))->assertSee('Still here')->assertDontSee('Gone now');
        $this->actingAs($this->admin)->get(route('admin.comments.index', ['status' => 'deleted']))
            ->assertSee('Gone now')
            ->assertDontSee('Still here');
    }

    public function test_list_can_show_the_comments_of_one_post(): void
    {
        $this->comment('On the busy post');
        $this->comment('Somewhere else', post: Post::factory()->published()->create());

        $this->actingAs($this->admin)->get(route('admin.comments.index', ['post' => $this->post->id]))
            ->assertOk()
            ->assertSee('Comments on')
            ->assertSee('On the busy post')
            ->assertDontSee('Somewhere else');
    }

    public function test_admin_can_hide_and_show_a_comment_and_the_count_follows(): void
    {
        $comment = $this->comment('Borderline remark');
        $this->assertSame(1, $this->post->fresh()->comments_count);

        $this->actingAs($this->admin)->from(route('admin.comments.index'))
            ->patch(route('admin.comments.visibility', $comment), ['visible' => '0'])
            ->assertRedirect(route('admin.comments.index'))
            ->assertSessionHas('success', 'Comment hidden from readers.');
        $this->assertFalse($comment->fresh()->is_approved);
        $this->assertSame(0, $this->post->fresh()->comments_count);

        $this->actingAs($this->admin)->patch(route('admin.comments.visibility', $comment), ['visible' => '1'])
            ->assertSessionHas('success', 'Comment is visible again.');
        $this->assertTrue($comment->fresh()->is_approved);
        $this->assertSame(1, $this->post->fresh()->comments_count);
    }

    public function test_admin_can_delete_a_comment(): void
    {
        $comment = $this->comment('Rude remark');

        $this->actingAs($this->admin)->from(route('admin.comments.index'))
            ->delete(route('admin.comments.destroy', $comment))
            ->assertRedirect(route('admin.comments.index'))
            ->assertSessionHas('success', 'Comment deleted.');

        $this->assertSoftDeleted($comment);
        $this->assertSame(0, $this->post->fresh()->comments_count);
    }

    public function test_bulk_hide_show_and_delete(): void
    {
        $first = $this->comment('Spam one');
        $second = $this->comment('Spam two');
        $untouched = $this->comment('Fine comment');
        $ids = [$first->id, $second->id];

        $this->actingAs($this->admin)->post(route('admin.comments.bulk'), ['action' => 'hide', 'comments' => $ids])
            ->assertSessionHas('success', '2 comments hidden.');
        $this->assertFalse($first->fresh()->is_approved);
        $this->assertFalse($second->fresh()->is_approved);
        $this->assertSame(1, $this->post->fresh()->comments_count);

        $this->actingAs($this->admin)->post(route('admin.comments.bulk'), ['action' => 'show', 'comments' => [$first->id]])
            ->assertSessionHas('success', '1 comment made visible.');
        $this->assertSame(2, $this->post->fresh()->comments_count);

        $this->actingAs($this->admin)->post(route('admin.comments.bulk'), ['action' => 'delete', 'comments' => $ids])
            ->assertSessionHas('success', '2 comments deleted.');
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($untouched);
        $this->assertSame(1, $this->post->fresh()->comments_count);
    }

    public function test_bulk_action_needs_a_selection_and_a_known_action(): void
    {
        $comment = $this->comment('Hello');

        $this->actingAs($this->admin)->post(route('admin.comments.bulk'), ['action' => 'hide'])
            ->assertSessionHasErrors(['comments' => 'Tick at least one comment first.']);
        $this->actingAs($this->admin)->post(route('admin.comments.bulk'), ['action' => 'approve-all', 'comments' => [$comment->id]])
            ->assertSessionHasErrors('action');

        $this->assertTrue($comment->fresh()->is_approved);
    }

    public function test_admin_post_list_links_to_the_posts_comments(): void
    {
        $this->actingAs($this->admin)->get(route('admin.posts.index'))
            ->assertSee(route('admin.comments.index', ['post' => $this->post->id]), false);
    }
}
