<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Services\AvatarProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_user_can_upload_a_photo_that_is_cropped_to_a_square_webp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('profile.edit'))
            ->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg', 800, 500)])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Profile photo updated.');

        $path = $user->fresh()->avatar;
        $this->assertMatchesRegularExpression("#^avatars/{$user->id}/[0-9a-f-]{36}\.webp$#", $path);
        Storage::disk('public')->assertExists($path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([AvatarProcessor::SIZE, AvatarProcessor::SIZE, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    public function test_new_photo_replaces_and_deletes_the_old_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('one.png', 300, 300)]);
        $oldPath = $user->fresh()->avatar;

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('two.png', 300, 300)]);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar);
    }

    public function test_user_can_remove_their_photo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.png', 300, 300)]);
        $path = $user->fresh()->avatar;

        $this->actingAs($user)->delete(route('profile.photo.destroy'))
            ->assertSessionHas('success', 'Profile photo removed.');

        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_photo_appears_in_the_header_instead_of_initials(): void
    {
        $user = User::factory()->create(['name' => 'Irfan Farooq']);

        $this->actingAs($user)->get(route('home'))->assertSee('>IF</span>', false);

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.png', 300, 300)]);

        $this->actingAs($user)->get(route('home'))
            ->assertSee(Storage::disk('public')->url($user->fresh()->avatar))
            ->assertDontSee('>IF</span>', false);
    }

    /**
     * @return array<string, array{callable(): mixed, string}>
     */
    public static function invalidPhotos(): array
    {
        return [
            'nothing chosen' => [fn () => null, 'Choose a photo to upload.'],
            'not an image' => [fn () => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'), 'The file must be an image.'],
            'gif not allowed' => [fn () => UploadedFile::fake()->image('anim.gif', 300, 300), 'Use a JPG, PNG or WebP image.'],
            'larger than 2 MB' => [fn () => UploadedFile::fake()->image('huge.jpg', 300, 300)->size(2049), 'The photo must be 2 MB or smaller.'],
            'too small' => [fn () => UploadedFile::fake()->image('tiny.jpg', 50, 50), 'The photo must be between 100 and 6000 pixels on each side.'],
        ];
    }

    #[DataProvider('invalidPhotos')]
    public function test_invalid_photo_is_rejected(callable $makeFile, string $message): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => $makeFile()])
            ->assertSessionHasErrorsIn('updatePhoto', ['photo' => $message]);

        $this->assertNull($user->fresh()->avatar);
    }
}
