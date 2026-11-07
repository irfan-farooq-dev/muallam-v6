<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserSlugHistory;
use App\Services\UserSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserSlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generate(string $name): string
    {
        return app(UserSlugGenerator::class)->generate($name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'plain name' => ['Irfan Farooq', 'irfan-farooq'],
            'accented letters' => ['Ünal Özdemir', 'unal-ozdemir'],
            'extra spaces and punctuation' => ["  Irfan   O'Brien-Farooq ", 'irfan-obrien-farooq'],
            'short name is padded' => ['Al', 'al-user'],
            'reserved word gets a number' => ['Admin', 'admin-2'],
        ];
    }

    #[DataProvider('names')]
    public function test_slug_is_built_from_the_name(string $name, string $expected): void
    {
        $this->assertSame($expected, $this->generate($name));
    }

    public function test_urdu_name_is_transliterated_to_latin_letters(): void
    {
        $slug = $this->generate('عرفان فاروق');

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
        $this->assertGreaterThanOrEqual(3, strlen($slug));
    }

    public function test_numbers_continue_after_existing_ones(): void
    {
        User::factory()->create(['slug' => 'irfan-farooq']);
        User::factory()->create(['slug' => 'irfan-farooq-2']);

        $this->assertSame('irfan-farooq-3', $this->generate('Irfan Farooq'));
    }

    public function test_retired_slugs_are_never_handed_out_again(): void
    {
        UserSlugHistory::factory()->create(['slug' => 'irfan-farooq']);

        $this->assertSame('irfan-farooq-2', $this->generate('Irfan Farooq'));
    }

    public function test_similar_but_different_slugs_do_not_count_as_taken(): void
    {
        User::factory()->create(['slug' => 'irfan-farooqi']);

        $this->assertSame('irfan-farooq', $this->generate('Irfan Farooq'));
    }

    public function test_very_long_name_stays_within_fifty_characters(): void
    {
        $name = str_repeat('Abcdefghij ', 10);
        User::factory()->create(['slug' => $this->generate($name)]);

        $second = $this->generate($name);

        $this->assertLessThanOrEqual(50, strlen($second));
        $this->assertStringEndsWith('-2', $second);
    }
}
