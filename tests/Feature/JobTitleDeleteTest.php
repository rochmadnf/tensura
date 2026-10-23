<?php

namespace Tests\Feature;

use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTitleDeleteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_permanently_delete_only_the_selected_record(): void
    {
        $jobTitle = JobTitle::factory()->create();
        $other = JobTitle::factory()->create();

        $this->actingAs(UserFactory::new()->create())
            ->delete(route('jt.destroy', $jobTitle->uuid))
            ->assertRedirectToRoute('jt.index');

        $this->assertModelMissing($jobTitle);
        $this->assertDatabaseMissing('job_titles', ['id' => $jobTitle->id]);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('job_titles', 1);
    }

    public function test_guests_cannot_delete_records(): void
    {
        $jobTitle = JobTitle::factory()->create();

        $this->delete(route('jt.destroy', $jobTitle->uuid))->assertRedirectToRoute('login.index');
        $this->assertModelExists($jobTitle);
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_destroy_rejects_unknown_uuid_or_numeric_id(bool $useNumericId): void
    {
        $jobTitle = JobTitle::factory()->create();
        $identifier = $useNumericId ? $jobTitle->id : (string) Str::uuid();

        $this->actingAs(UserFactory::new()->create())
            ->delete(route('jt.destroy', $identifier))
            ->assertNotFound();
        $this->assertModelExists($jobTitle);
    }

    /** @return array<string, array{bool}> */
    public static function invalidIdentifiers(): array
    {
        return ['numeric id' => [true], 'unknown uuid' => [false]];
    }
}
