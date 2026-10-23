<?php

namespace Tests\Feature;

use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterNumberEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_open_prefilled_edit_page(): void
    {
        $letterNumber = LetterNumberFactory::new()->create(['classification_code' => '007']);

        $response = $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ln.edit', $letterNumber->uuid));

        $response
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('letter-number/edit')
                    ->where('page.id', 'a3063dab-44a6-4de2-9c93-8fa84742e3da')
                    ->where('resource.data.id', $letterNumber->uuid)
                    ->where('resource.data.name', $letterNumber->classification_name)
                    ->where('resource.data.code', '007')
                    ->where('resource.data.page_number', 1)
                    ->where('resource.data.sequence_number', 1),
            );
    }

    public function test_update_saves_name_and_code_and_preserves_counters(): void
    {
        $letterNumber = LetterNumberFactory::new()->create([
            'classification_code' => '445',
            'page_number' => 12,
            'sequence_number' => 34,
        ]);

        $response = $this->actingAs(UserFactory::new()->create())->patch(route('ln.update', $letterNumber->uuid), [
            'classification_name' => 'Surat Tugas',
            'classification_code' => '007',
            'page_number' => 99,
            'sequence_number' => 99,
        ]);

        $response->assertRedirectToRoute('ln.index')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('letter_numbers', [
            'id' => $letterNumber->id,
            'classification_name' => 'Surat Tugas',
            'classification_code' => '007',
            'page_number' => 12,
            'sequence_number' => 34,
        ]);
        $this->assertDatabaseCount('letter_numbers', 1);
    }

    public function test_update_accepts_current_code_without_counter_fields(): void
    {
        $letterNumber = LetterNumberFactory::new()->create(['classification_code' => '007']);

        $response = $this->actingAs(UserFactory::new()->create())->patch(route('ln.update', $letterNumber->uuid), [
            'classification_name' => 'Nama Baru',
            'classification_code' => '007',
        ]);

        $response->assertRedirectToRoute('ln.index')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('letter_numbers', [
            'id' => $letterNumber->id,
            'classification_name' => 'Nama Baru',
            'classification_code' => '007',
        ]);
    }

    public function test_update_rejects_another_records_code(): void
    {
        $letterNumber = LetterNumberFactory::new()->create(['classification_code' => '445']);
        LetterNumberFactory::new()->create(['classification_code' => '007']);

        $response = $this->actingAs(UserFactory::new()->create())->patch(route('ln.update', $letterNumber->uuid), [
            'classification_name' => 'Nama Baru',
            'classification_code' => '007',
        ]);

        $response->assertInvalid(['classification_code']);
        $this->assertDatabaseHas('letter_numbers', [
            'id' => $letterNumber->id,
            'classification_name' => $letterNumber->classification_name,
            'classification_code' => '445',
        ]);
    }

    /** @param array<string, mixed> $invalidData */
    #[DataProvider('invalidUpdates')]
    public function test_update_rejects_invalid_data(array $invalidData, string $field): void
    {
        $letterNumber = LetterNumberFactory::new()->create(['classification_code' => '445']);

        $response = $this->actingAs(UserFactory::new()->create())->patch(route('ln.update', $letterNumber->uuid), [
            ...['classification_name' => 'Nama Baru', 'classification_code' => '007'],
            ...$invalidData,
        ]);

        $response->assertInvalid([$field]);
        $this->assertDatabaseHas('letter_numbers', [
            'id' => $letterNumber->id,
            'classification_name' => $letterNumber->classification_name,
            'classification_code' => '445',
        ]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidUpdates(): array
    {
        return [
            'missing name' => [['classification_name' => null], 'classification_name'],
            'short name' => [['classification_name' => 'AB'], 'classification_name'],
            'long name' => [['classification_name' => str_repeat('A', 101)], 'classification_name'],
            'non-string name' => [['classification_name' => ['name']], 'classification_name'],
            'missing code' => [['classification_code' => null], 'classification_code'],
            'short code' => [['classification_code' => '07'], 'classification_code'],
            'long code' => [['classification_code' => '0007'], 'classification_code'],
            'non-digit code' => [['classification_code' => 'A07'], 'classification_code'],
            'non-string code' => [['classification_code' => 123], 'classification_code'],
        ];
    }

    public function test_guests_cannot_open_edit_page_or_update_records(): void
    {
        $letterNumber = LetterNumberFactory::new()->create();

        $this->get(route('ln.edit', $letterNumber->uuid))->assertRedirectToRoute('login.index');
        $this->patch(route('ln.update', $letterNumber->uuid), [
            'classification_name' => 'Nama Baru',
            'classification_code' => '007',
        ])->assertRedirectToRoute('login.index');

        $this->assertDatabaseHas('letter_numbers', [
            'id' => $letterNumber->id,
            'classification_name' => $letterNumber->classification_name,
            'classification_code' => $letterNumber->classification_code,
        ]);
    }

    public function test_unknown_uuid_returns_not_found_for_edit_and_update(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $uuid = 'a3063dab-44a6-4de2-9c93-8fa84742e3da';

        $this->get(route('ln.edit', $uuid))->assertNotFound();
        $this->patch(route('ln.update', $uuid), [
            'classification_name' => 'Nama Baru',
            'classification_code' => '007',
        ])->assertNotFound();
    }

    public function test_create_still_saves_all_four_fields(): void
    {
        $data = [
            'classification_name' => 'Surat Keluar',
            'classification_code' => '007',
            'page_number' => 12,
            'sequence_number' => 34,
        ];

        $response = $this->actingAs(UserFactory::new()->create())->post(route('ln.store'), $data);

        $response->assertRedirectToRoute('ln.index')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('letter_numbers', $data);
    }
}
