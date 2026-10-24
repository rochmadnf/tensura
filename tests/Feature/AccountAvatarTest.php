<?php

namespace Tests\Feature;

use App\Models\MasterData\User;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AccountAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_replaces_only_current_users_avatar_and_refreshes_shared_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'old');
        Storage::disk('public')->put('avatars/other.png', 'other');
        $user = UserFactory::new()->create(['avatar' => 'avatars/old.png']);
        $user->refresh();
        $other = UserFactory::new()->create(['avatar' => 'avatars/other.png']);
        $other->refresh();
        $this->actingAs($user)->withoutVite();

        $response = $this->post(route('account.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('photo.png', 200, 200),
            'id' => $other->id, 'uuid' => $other->uuid, 'name' => 'Attacker',
        ]);

        $response->assertRedirectToRoute('account.edit')->assertSessionHasNoErrors();
        $path = $user->fresh()->avatar;
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertNotSame('avatars/photo.png', $path);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('avatars/old.png');
        Storage::disk('public')->assertExists('avatars/other.png');
        $this->assertSame($other->getAttributes(), $other->fresh()->getAttributes());
        $this->assertSame($user->name, $user->fresh()->name);
        $this->followRedirects($response)->assertInertia(fn(Assert $page) => $page
            ->where('hasAvatar', true)
            ->where('auth.user.avatar', Storage::disk('public')->url($path)));
    }

    /** @return array<string, array{string}> */
    public static function validFormats(): array
    {
        return ['jpg' => ['jpg'], 'jpeg' => ['jpeg'], 'png' => ['png'], 'webp' => ['webp']];
    }

    #[DataProvider('validFormats')]
    public function test_supported_images_can_be_uploaded(string $extension): void
    {
        Storage::fake('public');
        $user = UserFactory::new()->create();
        $user->refresh();

        $this->actingAs($user)->post(route('account.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('photo.' . $extension, 100, 100),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists($user->fresh()->avatar);
    }

    /** @return array<string, array{string}> */
    public static function invalidAvatars(): array
    {
        return [
            'non-image' => ['text'], 'svg' => ['svg'], 'too large' => ['large'],
            'too wide' => ['wide'], 'too tall' => ['tall'], 'missing' => ['missing'],
            'unsupported image' => ['gif'],
        ];
    }

    #[DataProvider('invalidAvatars')]
    public function test_invalid_avatars_preserve_existing_file_and_database(string $kind): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'old');
        $user = UserFactory::new()->create(['avatar' => 'avatars/old.png']);
        $user->refresh();
        $file = match ($kind) {
            'text' => UploadedFile::fake()->create('file.txt', 1, 'text/plain'),
            'svg' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>'),
            'large' => UploadedFile::fake()->image('photo.png')->size(2049),
            'wide' => UploadedFile::fake()->image('photo.png', 4097, 1),
            'tall' => UploadedFile::fake()->image('photo.png', 1, 4097),
            'gif' => UploadedFile::fake()->image('photo.gif'),
            'missing' => null,
        };

        $this->actingAs($user)->post(route('account.avatar.store'), ['avatar' => $file])->assertInvalid(['avatar']);

        $this->assertSame('avatars/old.png', $user->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/old.png');
        Storage::disk('public')->assertCount('avatars', 1);
    }

    public function test_delete_clears_avatar_and_refreshes_gravatar_without_affecting_other_user(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'old');
        Storage::disk('public')->put('avatars/other.png', 'other');
        $user = UserFactory::new()->create(['avatar' => 'avatars/old.png']);
        $user->refresh();
        $other = UserFactory::new()->create(['avatar' => 'avatars/other.png']);
        $other->refresh();
        $this->actingAs($user)->withoutVite();

        $response = $this->delete(route('account.avatar.destroy'), ['id' => $other->id, 'uuid' => $other->uuid]);

        $response->assertRedirectToRoute('account.edit');
        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing('avatars/old.png');
        Storage::disk('public')->assertExists('avatars/other.png');
        $this->assertSame($other->getAttributes(), $other->fresh()->getAttributes());
        $this->followRedirects($response)->assertInertia(fn(Assert $page) => $page
            ->where('hasAvatar', false)
            ->where('auth.user.avatar', 'https://gravatar.com/avatar/' . hash('sha256', strtolower($user->email)) . '?s=200&d=404'));
    }

    /** @return array<string, array{?string}> */
    public static function unmanagedAvatars(): array
    {
        return [
            'external URL' => ['https://example.test/photo.png'],
            'different folder' => ['documents/keep.png'],
            'traversal' => ['avatars/../documents/keep.png'],
            'already empty' => [null],
        ];
    }

    #[DataProvider('unmanagedAvatars')]
    public function test_delete_never_removes_unmanaged_files(?string $avatar): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/keep.png', 'keep');
        $user = UserFactory::new()->create(['avatar' => $avatar]);
        $user->refresh();

        $this->actingAs($user)->delete(route('account.avatar.destroy'))->assertRedirectToRoute('account.edit');

        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertExists('documents/keep.png');
    }

    public function test_external_avatar_url_is_preserved_until_replaced(): void
    {
        Storage::fake('public');
        $user = UserFactory::new()->create(['avatar' => 'https://example.test/photo.png']);
        $user->refresh();
        $this->actingAs($user)->withoutVite()->get(route('account.edit'))
            ->assertInertia(fn(Assert $page) => $page->where('auth.user.avatar', 'https://example.test/photo.png'));

        $this->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('photo.jpg')])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists($user->fresh()->avatar);
    }

    public function test_failed_storage_preserves_the_old_avatar(): void
    {
        $user = UserFactory::new()->create(['avatar' => 'avatars/old.png']);
        $user->refresh();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($disk);

        $this->actingAs($user)->post(route('account.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('photo.png'),
        ])->assertInvalid(['avatar' => 'Foto profil gagal disimpan. Silakan coba lagi.']);

        $this->assertSame('avatars/old.png', $user->fresh()->avatar);
    }

    public function test_database_failure_removes_new_file_and_preserves_old_avatar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'old');
        $user = UserFactory::new()->create(['avatar' => 'avatars/old.png']);
        $user->refresh();
        Exceptions::fake();
        $event = 'eloquent.updating: ' . User::class;
        Event::listen($event, function (): void {
            throw new RuntimeException('Database unavailable');
        });

        try {
            $this->actingAs($user)->post(route('account.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('photo.png'),
            ])->assertInternalServerError();

            $this->assertSame('avatars/old.png', $user->fresh()->avatar);
            Storage::disk('public')->assertExists('avatars/old.png');
            Storage::disk('public')->assertCount('avatars', 1);
            Exceptions::assertReported(RuntimeException::class);
        } finally {
            Event::forget($event);
        }
    }

    public function test_failed_old_file_deletion_is_reported_after_database_update(): void
    {
        $user = UserFactory::new()->create(['avatar' => 'avatars/old.png']);
        $user->refresh();
        Exceptions::fake();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')->with('avatars/old.png')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($disk);

        $this->actingAs($user)->delete(route('account.avatar.destroy'))->assertRedirectToRoute('account.edit');

        $this->assertNull($user->fresh()->avatar);
        Exceptions::assertReported(RuntimeException::class);
    }
}
