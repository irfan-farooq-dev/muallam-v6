<?php

namespace Tests\Feature;

use App\Console\Commands\DeploySite;
use App\Models\User;
use App\Services\AssetVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetVersionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(DeploySite::releasePath());

        parent::tearDown();
    }

    public function test_own_stylesheets_carry_the_files_modification_time(): void
    {
        $appCss = asset('css/app.css').'?v='.filemtime(public_path('css/app.css'));
        $adminCss = asset('css/admin.css').'?v='.filemtime(public_path('css/admin.css'));

        $this->get(route('home'))->assertSee('href="'.$appCss.'"', false);
        $this->get(route('register'))->assertSee(asset('js/auth-form.js').'?v=', false);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertSee('href="'.$adminCss.'"', false);
    }

    public function test_files_outside_the_app_folder_use_the_deployed_release_code(): void
    {
        file_put_contents(DeploySite::releasePath(), "abc1234-20261109-101500\n");

        $this->assertSame(asset('css/missing.css').'?v=abc1234-20261109-101500', app(AssetVersions::class)->url('css/missing.css'));
    }

    public function test_without_file_or_release_the_version_is_fixed(): void
    {
        @unlink(DeploySite::releasePath());

        $this->assertSame(asset('css/missing.css').'?v=1', (new AssetVersions)->url('css/missing.css'));
    }
}
