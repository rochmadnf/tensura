<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_every_account_endpoint(): void
    {
        foreach ([
            ['GET', 'account.edit'],
            ['PATCH', 'account.profile.update'],
            ['PUT', 'account.password.update'],
            ['POST', 'account.avatar.store'],
            ['DELETE', 'account.avatar.destroy'],
        ] as [$method, $route]) {
            $this->call($method, route($route))->assertRedirectToRoute('login.index');
        }
    }

    public function test_account_page_shows_only_the_authenticated_users_profile(): void
    {
        $user = UserFactory::new()->create();
        $user->refresh();

        $this->actingAs($user)->withoutVite()->get(route('account.edit'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('account/edit')
            ->where('page.id', 'account')
            ->where('hasAvatar', false)
            ->where('auth.user.id', $user->uuid)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.username', $user->username)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.password'));
    }

    public function test_profile_changes_are_normalized_and_extra_fields_cannot_change_either_users_protected_data(): void
    {
        $verifiedAt = now()->startOfSecond();
        $user = UserFactory::new()->create(['email_verified_at' => $verifiedAt, 'avatar' => 'avatars/original.png']);
        $user->refresh();
        $other = UserFactory::new()->create();
        $other->refresh();
        $otherAttributes = $other->getAttributes();
        $oldPassword = $user->password;

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => ' Nama Baru ', 'username' => ' pengguna_baru ', 'email' => ' BARU@Example.Test ',
            'password' => 'malicious-password', 'avatar' => 'avatars/other.png',
            'id' => $other->id, 'uuid' => $other->uuid, 'email_verified_at' => null,
        ])->assertRedirectToRoute('account.edit')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('pengguna_baru', $user->username);
        $this->assertSame('baru@example.test', $user->email);
        $this->assertSame($oldPassword, $user->password);
        $this->assertSame('avatars/original.png', $user->avatar);
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
        $this->assertSame($otherAttributes, $other->fresh()->getAttributes());
    }

    public function test_unchanged_username_and_email_are_accepted(): void
    {
        $user = UserFactory::new()->create(['username' => 'pengguna', 'email' => 'user@example.test']);
        $user->refresh();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'Nama Baru', 'username' => $user->username, 'email' => $user->email,
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('account.edit');

        $this->assertSame('Nama Baru', $user->fresh()->name);
    }

    /** @return array<string, array{string, string}> */
    public static function occupiedValues(): array
    {
        return [
            'username' => ['username', 'other_user'],
            'email' => ['email', 'other@example.test'],
            'uppercase input' => ['email', 'OTHER@EXAMPLE.TEST'],
        ];
    }

    #[DataProvider('occupiedValues')]
    public function test_another_users_unique_values_are_rejected(string $field, string $value): void
    {
        $user = UserFactory::new()->create();
        $user->refresh();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@example.test']);
        $other->refresh();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            ...$this->payload(), $field => $value, 'id' => $other->id, 'uuid' => $other->uuid,
        ])->assertInvalid([$field]);

        $this->assertSame($user->getAttributes(), $user->fresh()->getAttributes());
        $this->assertSame($other->getAttributes(), $other->fresh()->getAttributes());
    }

    public function test_email_matching_legacy_uppercase_data_is_rejected(): void
    {
        $user = UserFactory::new()->create();
        $user->refresh();
        UserFactory::new()->create(['email' => 'Legacy@Example.Test']);

        $this->actingAs($user)->patch(route('account.profile.update'), [
            ...$this->payload(), 'email' => 'legacy@example.test',
        ])->assertInvalid(['email' => 'Email sudah digunakan.']);

        $this->assertSame($user->email, $user->fresh()->email);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidProfiles(): array
    {
        return [
            'empty name' => ['name', ''], 'short name' => ['name', 'AB'],
            'long name' => ['name', str_repeat('A', 101)], 'non-string name' => ['name', ['name']],
            'empty username' => ['username', ''], 'short username' => ['username', 'abcde'],
            'long username' => ['username', str_repeat('a', 101)],
            'numeric prefix' => ['username', '1user_name'], 'punctuation' => ['username', 'user-name'],
            'empty email' => ['email', ''], 'invalid email' => ['email', 'not-an-email'],
            'long email' => ['email', str_repeat('a', 60) . '@' . str_repeat('b', 35) . '.test'],
            'non-string email' => ['email', ['email']],
        ];
    }

    #[DataProvider('invalidProfiles')]
    public function test_invalid_profile_is_rejected_without_changing_data(string $field, mixed $value): void
    {
        $user = UserFactory::new()->create();
        $user->refresh();

        $this->actingAs($user)->patch(route('account.profile.update'), [...$this->payload(), $field => $value])
            ->assertInvalid([$field]);

        $this->assertSame($user->getAttributes(), $user->fresh()->getAttributes());
    }

    public function test_redirect_refreshes_shared_email_and_gravatar(): void
    {
        $user = UserFactory::new()->create(['avatar' => null]);
        $user->refresh();
        $this->actingAs($user)->withoutVite();

        $response = $this->patch(route('account.profile.update'), $this->payload());

        $this->followRedirects($response)->assertInertia(fn(Assert $page) => $page
            ->where('auth.user.email', 'baru@example.test')
            ->where('auth.user.avatar', 'https://gravatar.com/avatar/' . hash('sha256', 'baru@example.test') . '?s=200&d=404'));
    }

    public function test_login_accepts_the_new_six_character_username_and_rejects_the_old_one(): void
    {
        $user = UserFactory::new()->create(['username' => 'old_user']);
        $user->refresh();
        $this->actingAs($user)->patch(route('account.profile.update'), [...$this->payload(), 'username' => 'baru_1'])
            ->assertSessionHasNoErrors();
        $this->post(route('logout'));

        $this->post(route('login.post'), ['username' => 'old_user', 'password' => 'password'])
            ->assertInvalid(['username']);
        $this->assertGuest();
        $this->post(route('login.post'), ['username' => 'baru_1', 'password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /** @return array{name: string, username: string, email: string} */
    private function payload(): array
    {
        return ['name' => 'Nama Baru', 'username' => 'pengguna_baru', 'email' => 'baru@example.test'];
    }
}
