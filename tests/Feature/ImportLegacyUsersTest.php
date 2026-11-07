<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class ImportLegacyUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

        DB::connection('legacy_test')->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->tinyInteger('role')->default(30);
            $table->tinyInteger('status')->default(1);
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function oldUser(array $attributes = []): void
    {
        DB::connection('legacy_test')->table('users')->insert(array_merge([
            'name' => 'Aleeza Owais',
            'slug' => 'aleeza-owais',
            'email' => 'aleeza@example.com',
            'password' => Hash::driver('bcrypt')->make('old-site-pass', ['rounds' => 10]),
            'role' => 30,
            'status' => 1,
            'phone' => '03001234567',
            'gender' => 'Female',
            'created_at' => '2025-11-20 16:26:35',
            'updated_at' => '2025-11-21 10:00:00',
        ], $attributes));
    }

    private function runImport(bool $dryRun = false): PendingCommand
    {
        return $this->artisan('legacy:import-users', array_filter(['--connection' => 'legacy_test', '--dry-run' => $dryRun]));
    }

    public function test_member_is_copied_and_can_sign_in_with_their_old_password(): void
    {
        $this->oldUser();

        $this->runImport()->expectsOutputToContain('Imported 1 user(s), skipped 0.')->assertSuccessful();

        $user = User::sole();
        $this->assertSame('Aleeza Owais', $user->name);
        $this->assertSame('aleeza-owais', $user->slug);
        $this->assertSame('aleeza@example.com', $user->email);
        $this->assertSame(UserRole::Author, $user->role);
        $this->assertSame(Gender::Female, $user->gender);
        $this->assertSame('03001234567', $user->phone);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('2025-11-20 16:26:35', $user->created_at->format('Y-m-d H:i:s'));

        $this->assertTrue(Auth::attempt(['email' => 'aleeza@example.com', 'password' => 'old-site-pass']));
    }

    public function test_old_admins_become_admins_and_reserved_addresses_are_replaced(): void
    {
        $this->oldUser(['name' => 'Admin User', 'slug' => 'admin', 'email' => 'admin@example.com', 'role' => 20]);

        $this->runImport()->expectsOutputToContain('new address admin-user (old one was admin); admin');

        $admin = User::sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('admin-user', $admin->slug);
    }

    public function test_address_already_taken_here_gets_a_numbered_one(): void
    {
        User::factory()->create(['slug' => 'aleeza-owais']);
        $this->oldUser();

        $this->runImport();

        $this->assertSame('aleeza-owais-2', User::where('email', 'aleeza@example.com')->value('slug'));
    }

    public function test_spam_existing_and_deleted_accounts_are_skipped(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->oldUser(['name' => '* * * <a href="https://spam.example">$3,222 payment</a>', 'slug' => 'spam', 'email' => 'spam@example.com', 'status' => 0]);
        $this->oldUser(['name' => 'Already Here', 'slug' => 'already-here', 'email' => 'TAKEN@example.com']);
        $this->oldUser(['name' => 'Gone Away', 'slug' => 'gone-away', 'email' => 'gone@example.com', 'deleted_at' => now()]);

        $this->runImport()
            ->expectsOutputToContain('spam account (link in the name)')
            ->expectsOutputToContain('email already registered here')
            ->expectsOutputToContain('Imported 0 user(s), skipped 2.');

        $this->assertSame(1, User::count());
    }

    public function test_bot_sign_ups_with_random_letter_names_are_skipped(): void
    {
        $this->oldUser(['name' => 'wkOgcgiPKtmjPHYcgvs', 'slug' => 'wkogcgipktmjphycgvs', 'email' => 'i.we.ya@example.com']);
        $this->oldUser(['name' => 'MUHAMMAD SHAZAB', 'slug' => 'muhammad-shazab', 'email' => 'shazab@example.com']);
        $this->oldUser(['name' => 'Christopherson', 'slug' => 'christopherson', 'email' => 'chris@example.com']);

        $this->runImport()->expectsOutputToContain('bot sign-up (random-letter name)');

        $this->assertEqualsCanonicalizing(['MUHAMMAD SHAZAB', 'Christopherson'], User::pluck('name')->all());
    }

    public function test_deactivated_member_stays_deactivated_and_bad_details_are_dropped(): void
    {
        $this->oldUser(['status' => 0, 'phone' => 'call me maybe', 'gender' => 'unknown']);

        $this->runImport();

        $user = User::sole();
        $this->assertFalse($user->is_active);
        $this->assertNull($user->phone);
        $this->assertNull($user->gender);
    }

    public function test_dry_run_saves_nothing_and_running_twice_is_safe(): void
    {
        $this->oldUser();

        $this->runImport(dryRun: true)->expectsOutputToContain('Dry run: would import 1 user(s)');
        $this->assertSame(0, User::count());

        $this->runImport();
        $this->runImport()->expectsOutputToContain('Imported 0 user(s), skipped 1.');
        $this->assertSame(1, User::count());
    }

    public function test_old_site_addresses_redirect_permanently(): void
    {
        $this->get('/blogs')->assertStatus(301)->assertRedirect('/');
        $this->get('/blogs/some-post')->assertStatus(301)->assertRedirect('/posts/some-post');
        $this->get('/signup')->assertStatus(301)->assertRedirect('/register');
        $this->get('/aleeza-owais/blogs')->assertStatus(301)->assertRedirect('/users/aleeza-owais/posts');
    }
}
