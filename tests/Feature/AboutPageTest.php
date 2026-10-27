<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('about'))->assertRedirectToRoute('login.index');
    }

    public function test_authenticated_users_can_read_the_initial_release_and_current_version(): void
    {
        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('about/index')
            ->where('page.id', 'about')
            ->where('app.version', '0.1.0')
            ->where('releases.0.version', '0.1.0')
            ->where('releases.0.features.5.title', 'Pengelolaan surat')
            ->has('releases.0.features', 8)
            ->has('releases.0.notes', 1));
    }

    public function test_current_version_uses_configuration_and_new_releases_preserve_previous_notes(): void
    {
        config([
            'app.version' => '0.2.1',
            'releases' => [
                '0.2.1' => ['summary' => 'Pembaruan berikutnya', 'features' => [], 'notes' => []],
                ...config('releases'),
            ],
        ]);

        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('app.version', '0.2.1')
            ->has('releases', 2)
            ->where('releases.0.version', '0.2.1')
            ->where('releases.0.summary', 'Pembaruan berikutnya')
            ->where('releases.1.version', '0.1.0')
            ->has('releases.1.features', 8));
    }

    public function test_a_version_without_release_notes_does_not_inherit_another_versions_identity(): void
    {
        config(['app.version' => '0.3.0', 'releases' => []]);

        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('app.version', '0.3.0')
            ->has('releases', 0));
    }
}
