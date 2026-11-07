<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const PER_PAGE = 12;

    public function __invoke(): View
    {
        return view('home', [
            'posts' => Post::publiclyVisible()
                ->with(['user:id,name,slug,avatar,is_bio_public', 'category:id,name,slug'])
                ->latest('published_at')
                ->latest('id')
                ->paginate(self::PER_PAGE),
            'categories' => Category::forNavigation(),
        ]);
    }
}
