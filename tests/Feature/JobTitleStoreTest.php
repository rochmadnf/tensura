<?php

namespace Tests\Feature;

use App\Enums\JobTitleGroupEnum;
use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTitleStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_open_create_page(): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.create'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('job-title/create')
                    ->where('page.id', 'a30c2273-d730-4e1a-af2c-479e149cf54b')
                    ->where('groups', JobTitleGroupEnum::options()),
            );
    }

    public function test_store_generates_uuid_and_auto_incrementing_numeric_id(): void
    {
        $existing = JobTitle::factory()->create();
        $data = ['name' => 'Dokter Ahli Pertama', 'group' => JobTitleGroupEnum::DOKTER->value];

        $this->actingAs(UserFactory::new()->create())
            ->post(route('jt.store'), [...$data, 'id' => 999, 'uuid' => (string) Str::uuid()])
            ->assertRedirectToRoute('jt.index')
            ->assertSessionHasNoErrors();

        $jobTitle = JobTitle::query()->where('name', $data['name'])->sole();
        $this->assertDatabaseHas('job_titles', $data);
        $this->assertIsInt($jobTitle->id);
        $this->assertSame($existing->id + 1, $jobTitle->id);
        $this->assertTrue(Str::isUuid($jobTitle->uuid));
        $this->assertNotSame($existing->uuid, $jobTitle->uuid);
    }

    #[DataProvider('invalidData')]
    public function test_store_rejects_invalid_data(array $invalidData, string $field): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->post(route('jt.store'), [
                ...['name' => 'Dokter Ahli Pertama', 'group' => JobTitleGroupEnum::DOKTER->value],
                ...$invalidData,
            ])
            ->assertInvalid([$field]);

        $this->assertDatabaseCount('job_titles', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidData(): array
    {
        return [
            'empty name' => [['name' => ''], 'name'],
            'short name' => [['name' => 'AB'], 'name'],
            'long name' => [['name' => str_repeat('A', 101)], 'name'],
            'non string name' => [['name' => ['Dokter']], 'name'],
            'empty group' => [['group' => null], 'group'],
            'invalid group' => [['group' => 99], 'group'],
            'non scalar group' => [['group' => [1]], 'group'],
        ];
    }

    public function test_required_messages_use_indonesian_labels(): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->post(route('jt.store'), [])
            ->assertInvalid([
                'name' => 'Nama Jabatan wajib diisi.',
                'group' => 'Kelompok Jabatan wajib diisi.',
            ]);
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        $jobTitle = JobTitle::factory()->create();

        $this->actingAs(UserFactory::new()->create())
            ->post(route('jt.store'), [
                'name' => $jobTitle->name,
                'group' => JobTitleGroupEnum::DOKTER->value,
            ])
            ->assertInvalid(['name']);

        $this->assertDatabaseCount('job_titles', 1);
    }

    public function test_guests_cannot_open_create_page_or_store_records(): void
    {
        $this->get(route('jt.create'))->assertRedirectToRoute('login.index');
        $this->post(route('jt.store'), ['name' => 'Dokter', 'group' => 1])->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('job_titles', 0);
    }

    public function test_seeder_creates_uuids_and_can_run_again_without_replacing_ids(): void
    {
        $this->seed(JobTitleSeeder::class);
        $before = JobTitle::query()
            ->orderBy('id')
            ->get(['id', 'uuid', 'name', 'group'])
            ->toArray();
        $this->assertNotEmpty($before);

        foreach ($before as $jobTitle) {
            $this->assertTrue(Str::isUuid($jobTitle['uuid']));
        }

        $this->seed(JobTitleSeeder::class);
        $this->assertSame(
            $before,
            JobTitle::query()
                ->orderBy('id')
                ->get(['id', 'uuid', 'name', 'group'])
                ->toArray(),
        );
    }
}
