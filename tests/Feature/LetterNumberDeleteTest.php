<?php

namespace Tests\Feature;

use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LetterNumberDeleteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_delete_only_the_selected_record(): void
    {
        $letterNumber = LetterNumberFactory::new()->create();
        $otherLetterNumber = LetterNumberFactory::new()->create();

        $response = $this->actingAs(UserFactory::new()->create())->delete(route('ln.destroy', $letterNumber->uuid));

        $response->assertRedirectToRoute('ln.index');
        $this->assertModelMissing($letterNumber);
        $this->assertModelExists($otherLetterNumber);
    }

    public function test_guests_cannot_delete_a_record(): void
    {
        $letterNumber = LetterNumberFactory::new()->create();

        $response = $this->delete(route('ln.destroy', $letterNumber->uuid));

        $response->assertRedirectToRoute('login.index');
        $this->assertModelExists($letterNumber);
    }

    public function test_deleting_unknown_uuid_returns_not_found(): void
    {
        $letterNumber = LetterNumberFactory::new()->create();

        $response = $this->actingAs(UserFactory::new()->create())->delete(
            route('ln.destroy', 'a3063dab-44a6-4de2-9c93-8fa84742e3da'),
        );

        $response->assertNotFound();
        $this->assertModelExists($letterNumber);
    }

    public function test_numeric_database_id_cannot_be_used_instead_of_uuid(): void
    {
        $letterNumber = LetterNumberFactory::new()->create();

        $response = $this->actingAs(UserFactory::new()->create())->delete(route('ln.destroy', $letterNumber->id));

        $response->assertNotFound();
        $this->assertModelExists($letterNumber);
    }
}
