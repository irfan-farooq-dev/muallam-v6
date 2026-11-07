<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostAdminEdit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminEditHighlightsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $admin;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->author = User::factory()->create(['name' => 'Amina Khan']);
        $this->admin = User::factory()->admin()->create(['name' => 'Site Admin']);
        $this->post = Post::factory()->published()->for($this->author)->create([
            'title' => 'My garden',
            'summary' => 'Notes from spring.',
            'body' => '<p>The roses grew quickly this year.</p><p>Tomatoes did well.</p>',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'title' => $this->post->title,
            'summary' => $this->post->summary,
            'category_id' => $this->post->category_id ?? Category::factory()->create()->id,
            'body' => $this->post->body,
            'action' => 'publish',
            'visibility' => 'public',
            'allow_comments' => '1',
        ], $overrides);
    }

    private function adminEdits(array $overrides = []): void
    {
        $this->actingAs($this->admin)->put(route('posts.update', $this->post), $this->input($overrides))->assertSessionHasNoErrors();
    }

    public function test_admin_changing_the_words_keeps_the_authors_version(): void
    {
        $this->adminEdits(['body' => '<p>The roses grew slowly this year.</p><p>Tomatoes did well.</p>']);

        $edit = PostAdminEdit::sole();
        $this->assertSame($this->post->id, $edit->post_id);
        $this->assertTrue($edit->editor->is($this->admin));
        $this->assertSame('My garden', $edit->previous_title);
        $this->assertSame('Notes from spring.', $edit->previous_summary);
        $this->assertSame('<p>The roses grew quickly this year.</p><p>Tomatoes did well.</p>', $edit->previous_body);
        $this->assertNull($edit->resolved_at);
        $this->assertStringContainsString('slowly', $this->post->fresh()->body);
    }

    public function test_admin_saving_without_changing_the_words_records_nothing(): void
    {
        $this->adminEdits(['allow_comments' => '0']);

        $this->assertDatabaseCount('post_admin_edits', 0);
    }

    public function test_later_admin_edits_still_compare_with_the_authors_own_version(): void
    {
        $this->adminEdits(['title' => 'My spring garden']);
        $this->post->refresh();
        $this->adminEdits(['title' => 'Our spring garden']);

        $edit = PostAdminEdit::sole();
        $this->assertSame('My garden', $edit->previous_title);
    }

    public function test_admin_editing_their_own_post_records_nothing(): void
    {
        $this->post->user()->associate($this->admin)->save();

        $this->adminEdits(['title' => 'Admin owned post']);

        $this->assertDatabaseCount('post_admin_edits', 0);
    }

    public function test_author_sees_the_changed_words_highlighted(): void
    {
        $this->adminEdits([
            'title' => 'My spring garden',
            'body' => '<p>The roses grew slowly this year.</p><p>Tomatoes did well.</p><p>Next year: peppers.</p>',
        ]);

        $this->actingAs($this->author)->get(route('posts.changes', $this->post))
            ->assertOk()
            ->assertSee('Site Admin')
            ->assertSee('My <ins class="diff-added">spring </ins>garden', false)
            ->assertSee('<del class="diff-removed">quickly</del><ins class="diff-added">slowly</ins>', false)
            ->assertSee('<ins class="diff-added">Next year: peppers.</ins>', false)
            ->assertSee('data-changed-blocks="2"', false)
            ->assertSee('Mark as reviewed');
    }

    public function test_readers_only_ever_see_the_plain_post(): void
    {
        $this->adminEdits(['body' => '<p>The roses grew slowly this year.</p><p>Tomatoes did well.</p>']);
        $this->post(route('logout'));

        $this->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('The roses grew slowly this year.')
            ->assertDontSee('diff-added', false)
            ->assertDontSee('<del', false)
            ->assertDontSee('An admin edited this post');

        $this->get(route('posts.changes', $this->post))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('posts.changes', $this->post))->assertForbidden();
    }

    public function test_author_is_told_on_the_dashboard_my_posts_post_page_and_editor(): void
    {
        $this->adminEdits(['title' => 'My spring garden']);
        $changesUrl = route('posts.changes', $this->post);

        $this->actingAs($this->author)->get(route('dashboard'))->assertSee('An admin edited one of your posts')->assertSee($changesUrl);
        $this->actingAs($this->author)->get(route('posts.mine'))->assertSee('Edited by admin')->assertSee($changesUrl);
        $this->actingAs($this->author)->get(route('posts.show', $this->post))->assertSee('An admin edited this post.')->assertSee($changesUrl);
        $this->actingAs($this->author)->get(route('posts.edit', $this->post))->assertSee('edited this post')->assertSee('See what changed');
    }

    public function test_admin_sees_a_warning_when_editing_someone_elses_post(): void
    {
        $this->actingAs($this->admin)->get(route('posts.edit', $this->post))
            ->assertOk()
            ->assertSee('as an admin')
            ->assertSee('Amina Khan')
            ->assertSee(route('admin.posts.index'));
    }

    public function test_author_can_mark_the_changes_as_reviewed(): void
    {
        $this->adminEdits(['title' => 'My spring garden']);

        $this->actingAs($this->author)->post(route('posts.changes.reviewed', $this->post))
            ->assertRedirect(route('posts.edit', $this->post))
            ->assertSessionHas('success', 'Admin changes marked as reviewed.');

        $this->assertNotNull(PostAdminEdit::sole()->resolved_at);
        $this->actingAs($this->author)->get(route('posts.mine'))->assertDontSee('Edited by admin');
        $this->actingAs($this->author)->get(route('posts.changes', $this->post))->assertRedirect(route('posts.edit', $this->post));
    }

    public function test_only_the_author_can_mark_changes_as_reviewed(): void
    {
        $this->adminEdits(['title' => 'My spring garden']);

        $this->actingAs($this->admin)->get(route('posts.changes', $this->post))->assertOk()->assertDontSee('Mark as reviewed');
        $this->actingAs($this->admin)->post(route('posts.changes.reviewed', $this->post))->assertForbidden();

        $this->assertNull(PostAdminEdit::sole()->resolved_at);
    }

    public function test_author_saving_the_post_counts_as_reviewed_and_starts_afresh(): void
    {
        $this->adminEdits(['title' => 'My spring garden']);
        $this->post->refresh();

        $this->actingAs($this->author)->put(route('posts.update', $this->post), $this->input(['title' => 'My spring garden!']));
        $this->assertNotNull(PostAdminEdit::sole()->resolved_at);

        $this->post->refresh();
        $this->adminEdits(['title' => 'Spring garden']);

        $open = $this->post->pendingAdminEdit()->sole();
        $this->assertSame('My spring garden!', $open->previous_title);
    }

    public function test_deleting_the_admin_keeps_the_record_without_a_name(): void
    {
        $this->adminEdits(['title' => 'My spring garden']);

        $this->admin->delete();

        $this->assertNull(PostAdminEdit::sole()->editor_id);
        $this->actingAs($this->author)->get(route('posts.changes', $this->post))->assertOk()->assertSee('An admin');
    }
}
