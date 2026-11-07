<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the local database with demo content. Every account's password is "password".
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Muallam Admin',
            'slug' => 'muallam-admin',
            'email' => 'admin@muallam.test',
        ]);

        $authors = collect([
            User::factory()->withPublicBio()->create([
                'name' => 'Demo Author',
                'slug' => 'demo-author',
                'email' => 'author@muallam.test',
            ]),
        ])->merge(User::factory()->count(4)->withPublicBio()->create());

        $readers = User::factory()->count(6)->create();

        $this->call(CategorySeeder::class);
        $categories = Category::all();

        $authors->each(function (User $author) use ($categories): void {
            Post::factory()->count(5)->published()->for($author)
                ->sequence(fn ($sequence) => ['category_id' => $categories->random()->id])
                ->create();
            Post::factory()->draft()->for($author)->recycle($categories)->create();
            Post::factory()->scheduled()->for($author)->recycle($categories)->create();
            Post::factory()->private()->for($author)->recycle($categories)->create();
        });

        Post::factory()->restricted()->for($authors->first())->recycle($categories)->create([
            'title' => 'Restricted demo post',
            'slug' => 'restricted-demo-post',
        ]);

        $people = $authors->merge($readers);

        Post::query()->publiclyVisible()->get()->each(function (Post $post) use ($people): void {
            $people->random(random_int(0, 6))->each(
                fn (User $user) => Like::factory()->for($user)->for($post)->create()
            );

            Comment::factory()->count(random_int(0, 3))->for($post)->recycle($people)->create()
                ->each(fn (Comment $comment) => Comment::factory()->count(random_int(0, 2))
                    ->replyTo($comment)->recycle($people)->create());

            $post->forceFill([
                'likes_count' => $post->likes()->count(),
                'comments_count' => $post->comments()->where('is_approved', true)->count(),
            ])->save();
        });
    }
}
