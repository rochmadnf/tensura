<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidPasswords(): array
    {
        return [
            'wrong current password' => [['current_password' => 'wrong-password'], 'current_password'],
            'missing current password' => [['current_password' => ''], 'current_password'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'missing password' => [['password' => ''], 'password'],
            'mismatched confirmation' => [['password_confirmation' => 'something-else'], 'password'],
            'missing confirmation' => [['password_confirmation' => ''], 'password_confirmation'],
            'same password' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
        ];
    }

    /** @param array<string, string> $invalid */
    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_changes_preserve_the_hash_and_do_not_flash_secrets(array $invalid, string $field): void
    {
        $user = UserFactory::new()->create();
        $user->refresh();

        $this->actingAs($user)->from(route('account.edit'))->put(route('account.password.update'), [
            'current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
            ...$invalid,
        ])->assertInvalid([$field])
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_password_is_hashed_only_for_current_user_and_new_password_works_at_login(): void
    {
        $user = UserFactory::new()->create(['username' => 'pengguna']);
        $user->refresh();
        $other = UserFactory::new()->create();
        $other->refresh();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
            'id' => $other->id, 'uuid' => $other->uuid, 'email' => 'attacker@example.test',
        ])->assertRedirectToRoute('account.edit')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame($other->getAttributes(), $other->fresh()->getAttributes());
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));
        $this->post(route('login.post'), ['username' => 'pengguna', 'password' => 'password'])
            ->assertInvalid(['username']);
        $this->assertGuest();
        $this->post(route('login.post'), ['username' => 'pengguna', 'password' => 'new-password'])
            ->assertRedirectToRoute('dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }
}
