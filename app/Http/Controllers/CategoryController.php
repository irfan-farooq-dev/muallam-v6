<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class CategoryController extends Controller
{
    private const POSTS_PER_PAGE = 12;

    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::active()
                ->withCount(['posts' => fn ($query) => $query->publiclyVisible()])
                ->get(),
        ]);
    }

    public function show(Category $category): View
    {
        abort_unless($category->is_active, 404);

        return view('categories.show', [
            'category' => $category,
            'posts' => $category->posts()
                ->publiclyVisible()
                ->with('user:id,name,slug,avatar,is_bio_public')
                ->latest('published_at')
                ->latest('id')
                ->paginate(self::POSTS_PER_PAGE),
        ]);
    }
}
