<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()
                ->withCount(['posts', 'posts as public_posts_count' => fn ($query) => $query->publiclyVisible()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'defaultSlug' => config('muallam.default_category_slug'),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $category = new Category($request->validated());
        $category->sort_order = (int) Category::max('sort_order') + 1;
        $category->save();

        return redirect()->route('admin.categories.index')->with('success', "Category \"{$category->name}\" added.");
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', ['category' => $category->loadCount('posts')]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('success', "Category \"{$category->name}\" saved.");
    }

    /**
     * Swaps the category with its neighbour above or below in the menu order.
     */
    public function move(Request $request, Category $category): RedirectResponse
    {
        $request->validate(['direction' => ['required', 'in:up,down']]);

        DB::transaction(function () use ($request, $category): void {
            // Renumber 0..n first so every category has its own position.
            $ordered = Category::query()->orderBy('sort_order')->orderBy('name')->lockForUpdate()->get()->values();
            $index = $ordered->search(fn (Category $item): bool => $item->is($category));
            $swapWith = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

            if ($swapWith < 0 || $swapWith >= $ordered->count()) {
                return;
            }

            [$ordered[$index], $ordered[$swapWith]] = [$ordered[$swapWith], $ordered[$index]];
            $ordered->each(function (Category $item, int $position): void {
                if ($item->sort_order !== $position) {
                    $item->update(['sort_order' => $position]);
                }
            });
        });

        return redirect()->route('admin.categories.index');
    }

    /**
     * Deletes a category. Its posts (if any) must be moved to another category first, in the same step.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->slug === config('muallam.default_category_slug')) {
            return back()->with('error', 'The default category cannot be deleted; new posts start in it.');
        }

        $postCount = $category->posts()->count();

        if ($postCount > 0) {
            $request->validate([
                'move_to' => ['required', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category->getKey()])],
            ], ['move_to.required' => 'Choose where its posts should go.']);
        }

        DB::transaction(function () use ($category, $request, $postCount): void {
            if ($postCount > 0) {
                // Query-builder update so the posts' "updated" time is not touched.
                Post::whereBelongsTo($category)->toBase()->update(['category_id' => $request->integer('move_to')]);
            }

            $category->delete();
        });

        $message = $postCount > 0
            ? "Category \"{$category->name}\" deleted; its {$postCount} posts moved to \"".Category::find($request->integer('move_to'))?->name.'".'
            : "Category \"{$category->name}\" deleted.";

        return redirect()->route('admin.categories.index')->with('success', $message);
    }
}
