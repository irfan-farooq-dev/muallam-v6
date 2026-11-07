<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $general;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->general = Category::factory()->create(['name' => 'General', 'slug' => 'general', 'sort_order' => 9]);
    }

    public function test_members_cannot_manage_categories(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.categories.store'), ['name' => 'Sneaky', 'is_active' => '1'])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Sneaky']);
    }

    public function test_list_shows_categories_in_menu_order_with_post_counts(): void
    {
        $science = Category::factory()->create(['name' => 'Science', 'sort_order' => 1]);
        Category::factory()->inactive()->create(['name' => 'Old Topic', 'sort_order' => 2]);
        Post::factory()->for($science)->published()->count(2)->create();
        Post::factory()->for($science)->draft()->create();

        $this->actingAs($this->admin)->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Science', 'Old Topic', 'General'])
            ->assertSee('Hidden')
            ->assertSee('Default')
            ->assertSee('>2</span>', false)
            ->assertSee('/ 3', false);
    }

    public function test_admin_can_add_a_category_and_it_joins_the_end_of_the_menu(): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'name' => '  Food   & Cooking ',
            'slug' => '',
            'description' => 'Recipes and kitchen tips.',
            'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'))->assertSessionHas('success', 'Category "Food & Cooking" added.');

        $category = Category::where('slug', 'food-cooking')->sole();
        $this->assertSame('Food & Cooking', $category->name);
        $this->assertSame(10, $category->sort_order);
        $this->assertTrue($category->is_active);

        $this->get(route('home'))->assertSee(route('categories.show', 'food-cooking'));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidCategories(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'duplicate name' => [['name' => 'General'], 'name'],
            'duplicate link' => [['name' => 'Other', 'slug' => 'general'], 'slug'],
            'name without letters' => [['name' => '---'], 'slug'],
        ];
    }

    #[DataProvider('invalidCategories')]
    public function test_invalid_category_is_rejected(array $input, string $field): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), [...$input, 'is_active' => '1'])->assertSessionHasErrors($field);

        $this->assertSame(1, Category::count());
    }

    public function test_admin_can_rename_hide_and_change_the_link(): void
    {
        $category = Category::factory()->create(['name' => 'Science', 'slug' => 'science']);

        $this->actingAs($this->admin)->put(route('admin.categories.update', $category), [
            'name' => 'Science & Nature',
            'slug' => 'science-nature',
            'description' => '',
            'is_active' => '0',
        ])->assertRedirect(route('admin.categories.index'));

        $category->refresh();
        $this->assertSame('science-nature', $category->slug);
        $this->assertFalse($category->is_active);
        $this->get(route('home'))->assertDontSee(route('categories.show', 'science-nature'));
    }

    public function test_default_category_cannot_be_hidden_renamed_in_its_link_or_deleted(): void
    {
        $this->actingAs($this->admin)->put(route('admin.categories.update', $this->general), [
            'name' => 'General', 'slug' => 'misc', 'is_active' => '0',
        ])->assertSessionHasErrors(['is_active', 'slug']);

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $this->general))
            ->assertSessionHas('error', 'The default category cannot be deleted; new posts start in it.');

        $this->assertModelExists($this->general);
        $this->assertTrue($this->general->fresh()->is_active);
    }

    public function test_default_category_can_still_get_a_new_name_and_description(): void
    {
        $this->actingAs($this->admin)->put(route('admin.categories.update', $this->general), [
            'name' => 'Everything Else', 'slug' => 'general', 'description' => 'Posts that fit nowhere else.', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Everything Else', $this->general->fresh()->name);
    }

    public function test_categories_can_be_moved_up_and_down(): void
    {
        $first = Category::factory()->create(['name' => 'Alpha', 'sort_order' => 0]);
        $second = Category::factory()->create(['name' => 'Beta', 'sort_order' => 0]);

        $this->actingAs($this->admin)->post(route('admin.categories.move', $this->general), ['direction' => 'up']);

        $this->assertSame(['Alpha', 'General', 'Beta'], Category::orderBy('sort_order')->pluck('name')->all());

        $this->actingAs($this->admin)->post(route('admin.categories.move', $first), ['direction' => 'up']);
        $this->assertSame(['Alpha', 'General', 'Beta'], Category::orderBy('sort_order')->pluck('name')->all());

        $this->actingAs($this->admin)->post(route('admin.categories.move', $first), ['direction' => 'down']);
        $this->assertSame(['General', 'Alpha', 'Beta'], Category::orderBy('sort_order')->pluck('name')->all());
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Unused']);

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('success', 'Category "Unused" deleted.');

        $this->assertModelMissing($category);
    }

    public function test_category_with_posts_needs_a_destination_and_its_posts_move_there(): void
    {
        $category = Category::factory()->create(['name' => 'Old Topic']);
        $posts = Post::factory()->for($category)->count(3)->create(['updated_at' => now()->subWeek()->startOfSecond()]);

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasErrors(['move_to' => 'Choose where its posts should go.']);
        $this->assertModelExists($category);

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category), ['move_to' => $this->general->id])
            ->assertSessionHas('success', 'Category "Old Topic" deleted; its 3 posts moved to "General".');

        $this->assertModelMissing($category);
        $this->assertSame(3, Post::whereBelongsTo($this->general)->count());
        $this->assertTrue($posts->first()->fresh()->updated_at->equalTo($posts->first()->updated_at));
    }

    public function test_posts_cannot_be_moved_into_the_category_being_deleted(): void
    {
        $category = Category::factory()->create();
        Post::factory()->for($category)->create();

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category), ['move_to' => $category->id])
            ->assertSessionHasErrors('move_to');
    }

    public function test_sidebar_links_to_categories(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee(route('admin.categories.index'));
    }
}
