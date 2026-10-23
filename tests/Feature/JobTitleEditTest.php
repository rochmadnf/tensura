<?php

namespace Tests\Feature;

use App\Enums\JobTitleGroupEnum;
use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTitleEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_open_prefilled_edit_page(): void
    {
        $jobTitle = JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.edit', $jobTitle->uuid))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('job-title/edit')
                    ->where('page.id', 'a30c2273-d730-4e1a-af2c-479e149cf54b')
                    ->where('groups', JobTitleGroupEnum::options())
                    ->where('resource.data', [
                        'id' => $jobTitle->uuid,
                        'name' => $jobTitle->name,
                        'group' => ['id' => 1, 'label' => 'Dokter'],
                    ]),
            );
    }

    public function test_update_changes_only_name_and_group(): void
    {
        $jobTitle = JobTitle::factory()->create();
        $originalUuid = $jobTitle->uuid;

        $this->actingAs(UserFactory::new()->create())
            ->patch(route('jt.update', $originalUuid), [
                'name' => 'Perawat Ahli Pertama',
                'group' => JobTitleGroupEnum::PERAWAT->value,
                'id' => 999,
                'uuid' => (string) Str::uuid(),
                'created_at' => '2000-01-01 00:00:00',
                'unknown_field' => 'ignored',
            ])
            ->assertRedirectToRoute('jt.index')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_titles', [
            'id' => $jobTitle->id,
            'uuid' => $originalUuid,
            'name' => 'Perawat Ahli Pertama',
            'group' => JobTitleGroupEnum::PERAWAT->value,
            'created_at' => $jobTitle->getRawOriginal('created_at'),
        ]);
        $this->assertDatabaseCount('job_titles', 1);
    }

    public function test_update_accepts_the_records_own_name(): void
    {
        $jobTitle = JobTitle::factory()->create();

        $this->actingAs(UserFactory::new()->create())
            ->patch(route('jt.update', $jobTitle->uuid), [
                'name' => $jobTitle->name,
                'group' => JobTitleGroupEnum::BIDAN->value,
            ])
            ->assertRedirectToRoute('jt.index')
            ->assertSessionHasNoErrors();

        $this->assertSame(JobTitleGroupEnum::BIDAN, $jobTitle->fresh()->group);
    }

    public function test_update_rejects_another_records_name(): void
    {
        $jobTitle = JobTitle::factory()->create();
        $other = JobTitle::factory()->create();

        $this->actingAs(UserFactory::new()->create())
            ->patch(route('jt.update', $jobTitle->uuid), [
                'name' => $other->name,
                'group' => JobTitleGroupEnum::DOKTER->value,
            ])
            ->assertInvalid(['name']);

        $this->assertSame($jobTitle->name, $jobTitle->fresh()->name);
    }

    /** @param array<string, mixed> $invalidData */
    #[DataProvider('invalidUpdates')]
    public function test_update_rejects_invalid_data(array $invalidData, string $field): void
    {
        $jobTitle = JobTitle::factory()->create();

        $this->actingAs(UserFactory::new()->create())
            ->patch(route('jt.update', $jobTitle->uuid), [
                ...['name' => 'Nama Baru', 'group' => JobTitleGroupEnum::DOKTER->value],
                ...$invalidData,
            ])
            ->assertInvalid([$field]);

        $this->assertSame($jobTitle->name, $jobTitle->fresh()->name);
        $this->assertSame($jobTitle->group, $jobTitle->fresh()->group);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidUpdates(): array
    {
        return [
            'empty name' => [['name' => null], 'name'],
            'short name' => [['name' => 'AB'], 'name'],
            'long name' => [['name' => str_repeat('A', 101)], 'name'],
            'non string name' => [['name' => 123], 'name'],
            'empty group' => [['group' => null], 'group'],
            'invalid group' => [['group' => 99], 'group'],
        ];
    }

    public function test_guests_cannot_open_edit_page_or_update_records(): void
    {
        $jobTitle = JobTitle::factory()->create();

        $this->get(route('jt.edit', $jobTitle->uuid))->assertRedirectToRoute('login.index');
        $this->patch(route('jt.update', $jobTitle->uuid), ['name' => 'Nama Baru', 'group' => 1])->assertRedirectToRoute(
            'login.index',
        );

        $this->assertSame($jobTitle->name, $jobTitle->fresh()->name);
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_edit_and_update_reject_unknown_uuid_or_numeric_id(bool $useNumericId): void
    {
        $jobTitle = JobTitle::factory()->create();
        $identifier = $useNumericId ? $jobTitle->id : (string) Str::uuid();
        $this->actingAs(UserFactory::new()->create());

        $this->get(route('jt.edit', $identifier))->assertNotFound();
        $this->patch(route('jt.update', $identifier), ['name' => 'Nama Baru', 'group' => 1])->assertNotFound();

        $this->assertSame($jobTitle->name, $jobTitle->fresh()->name);
    }

    /** @return array<string, array{bool}> */
    public static function invalidIdentifiers(): array
    {
        return ['numeric id' => [true], 'unknown uuid' => [false]];
    }
}
