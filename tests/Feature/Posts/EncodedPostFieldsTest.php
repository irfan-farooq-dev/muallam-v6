<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The editor sends title, summary and content as one base64 field so the host's web firewall does not block them.
 */
class EncodedPostFieldsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string|null>  $fields
     */
    private function encode(array $fields): string
    {
        return base64_encode(json_encode($fields, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'encoded_fields' => $this->encode([
                'title' => 'Distributed Systems: Foundations',
                'summary' => 'SELECT * FROM ideas; <a href="https://example.com/?a=1&b=2">link</a>',
                'body' => '<p>اردو متن and <a href="https://example.com/?q=union+select">a link</a></p><p><img src="/storage/posts/1/body/a.webp" alt="Diagram"></p>',
            ]),
            'category_id' => Category::factory()->create()->id,
            'action' => 'draft',
            'visibility' => 'public',
            'allow_comments' => '1',
        ], $overrides);
    }

    public function test_new_post_is_saved_from_encoded_fields(): void
    {
        $this->actingAs(User::factory()->create())->post(route('posts.store'), $this->input())->assertSessionHasNoErrors();

        $post = Post::sole();
        $this->assertSame('Distributed Systems: Foundations', $post->title);
        $this->assertSame('distributed-systems-foundations', $post->slug);
        $this->assertStringStartsWith('SELECT * FROM ideas;', $post->summary);
        $this->assertStringContainsString('اردو متن', $post->body);
        $this->assertStringContainsString('<a href="https://example.com/?q&#61;union&#43;select"', $post->body);
        $this->assertStringContainsString('alt="Diagram"', $post->body);
    }

    public function test_encoded_fields_replace_plain_ones_when_editing(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['title' => 'Old title']);

        $this->actingAs($user)
            ->put(route('posts.update', $post), $this->input(['title' => 'Plain title is ignored']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Distributed Systems: Foundations', $post->fresh()->title);
    }

    public function test_preview_reads_encoded_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('posts.preview'), $this->input(['action' => 'preview']))
            ->assertOk()
            ->assertSee('Distributed Systems: Foundations')
            ->assertSee('اردو متن');
    }

    public function test_only_text_fields_are_taken_from_the_encoded_value(): void
    {
        $user = User::factory()->create();
        $input = $this->input();
        $input['encoded_fields'] = $this->encode(['title' => 'Mine', 'body' => '<p>Text</p>', 'user_id' => (string) User::factory()->create()->id]);

        $this->actingAs($user)->post(route('posts.store'), $input)->assertSessionHasNoErrors();

        $this->assertTrue(Post::sole()->user->is($user));
    }

    public function test_broken_encoded_value_fails_validation_and_is_not_kept_in_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('posts.create'))
            ->post(route('posts.store'), $this->input(['encoded_fields' => 'not base64!']))
            ->assertRedirect(route('posts.create'))
            ->assertSessionHasErrors(['title', 'body'])
            ->assertSessionMissing('_old_input.encoded_fields');

        $this->assertDatabaseCount('posts', 0);
    }
}
