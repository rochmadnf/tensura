<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceGroupStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_open_create_page(): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.create'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('reference/create')
                    ->where('page.id', 'a3063dcc-2750-45e0-8ea7-c859c50bd3fb'),
            );
    }

    public function test_store_ignores_system_fields_and_redirects_to_new_groups_edit_page(): void
    {
        $uuid = (string) Str::uuid();
        $response = $this->actingAs(UserFactory::new()->create())
            ->post(route('ref.store'), [
                'name' => ' Agama ',
                'description' => ' Keterangan ',
                'id' => 999,
                'uuid' => $uuid,
                'reference_group_id' => 999,
                'sort_order' => 999,
                'created_at' => '2000-01-01 00:00:00',
            ])
            ->assertSessionHasNoErrors();
        $group = ReferenceGroup::query()->sole();
        $response->assertRedirectToRoute('ref.edit', $group->uuid);
        $this->assertSame('Agama', $group->name);
        $this->assertSame('Keterangan', $group->description);
        $this->assertNotSame(999, $group->id);
        $this->assertNotSame($uuid, $group->uuid);
        $this->assertTrue(Str::isUuid($group->uuid));
        $this->assertNotSame('2000-01-01 00:00:00', $group->getRawOriginal('created_at'));
        $this->assertDatabaseCount('reference_items', 0);
    }

    public function test_store_accepts_optional_description_and_boundary_lengths(): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->post(route('ref.store'), ['name' => 'ABC'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reference_groups', ['name' => 'ABC', 'description' => null]);
        $this->post(route('ref.store'), [
            'name' => str_repeat('A', 100),
            'description' => str_repeat('B', 255),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reference_groups', 2);
    }

    #[DataProvider('invalidData')]
    public function test_store_rejects_invalid_data_with_indonesian_messages(
        array $data,
        string $field,
        string $message,
    ): void {
        $this->actingAs(UserFactory::new()->create())
            ->post(route('ref.store'), [...['name' => 'Agama'], ...$data])
            ->assertInvalid([$field => $message]);
        $this->assertDatabaseCount('reference_groups', 0);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function invalidData(): array
    {
        return [
            'required' => [['name' => ' '], 'name', 'Nama Grup wajib diisi.'],
            'short' => [['name' => 'AB'], 'name', 'Nama Grup minimal 3 karakter.'],
            'long' => [['name' => str_repeat('A', 101)], 'name', 'Nama Grup maksimal 100 karakter.'],
            'type' => [['name' => 123], 'name', 'Nama Grup harus berupa teks.'],
            'description long' => [
                ['description' => str_repeat('A', 256)],
                'description',
                'Deskripsi maksimal 255 karakter.',
            ],
            'description type' => [['description' => []], 'description', 'Deskripsi harus berupa teks.'],
        ];
    }

    public function test_store_rejects_duplicate_name(): void
    {
        ReferenceGroup::factory()->create(['name' => 'Agama']);
        $this->actingAs(UserFactory::new()->create())
            ->post(route('ref.store'), ['name' => 'Agama'])
            ->assertInvalid(['name']);
        $this->assertDatabaseCount('reference_groups', 1);
    }

    public function test_guests_cannot_create_or_store_groups(): void
    {
        $this->get(route('ref.create'))->assertRedirectToRoute('login.index');
        $this->post(route('ref.store'), ['name' => 'Agama'])->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('reference_groups', 0);
    }
}
