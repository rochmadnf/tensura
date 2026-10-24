<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterTemplateStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_page_exposes_uuid_classifications_in_code_order(): void
    {
        $last = LetterNumberFactory::new()->create(['classification_code' => '020']);
        $first = LetterNumberFactory::new()->create(['classification_code' => '007']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.create'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('letter-templates/create')
            ->where('page.id', 'a3063dc3-7414-4385-bf3f-0cf0742e3980')
            ->where('classifications', [
                ['id' => $first->uuid, 'label' => '007 — ' . $first->classification_name],
                ['id' => $last->uuid, 'label' => '020 — ' . $last->classification_name],
            ]));
    }

    public function test_store_creates_only_initial_identity_and_draft_without_changing_counters(): void
    {
        $classification = LetterNumberFactory::new()->create(['page_number' => 17, 'sequence_number' => 45]);
        $actor = UserFactory::new()->create();
        $suppliedUuid = (string) Str::uuid();

        $response = $this->actingAs($actor)->post(route('lt.store'), [
            'name' => ' Surat Tugas ', 'letter_number' => $classification->uuid,
            'id' => 999, 'uuid' => $suppliedUuid, 'is_active' => false, 'active_version_id' => 999,
            'status' => 2, 'version_number' => 999, 'first_used_at' => now()->toDateTimeString(),
            'letter_template_id' => 999, 'letter_number_id' => 999,
            'source_path' => 'untrusted.docx', 'page_number' => 99, 'sequence_number' => 99,
        ])->assertSessionHasNoErrors();

        $template = LetterTemplate::query()->sole();
        $response->assertRedirectToRoute('lt.edit', $template->uuid);
        $this->assertSame('Surat Tugas', $template->name);
        $this->assertTrue($template->is_active);
        $this->assertNull($template->active_version_id);
        $this->assertNotSame($suppliedUuid, $template->uuid);
        $this->assertNotSame(999, $template->id);
        $version = $template->versions->sole();
        $this->assertSame(1, $version->version_number);
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->status);
        $this->assertSame($classification->id, $version->letter_number_id);
        $this->assertNull($version->source_path);
        $this->assertNull($version->first_used_at);
        $this->assertSame(17, $classification->fresh()->page_number);
        $this->assertSame(45, $classification->fresh()->sequence_number);
        $this->assertDatabaseCount('letter_template_variables', 0);
        $this->assertDatabaseCount('letter_template_signer_slots', 0);
        $this->assertDatabaseHas('letter_template_audits', [
            'letter_template_id' => $template->id, 'version_id' => $version->id,
            'actor_uuid' => $actor->uuid, 'operation' => 'draft.created',
        ]);
    }

    #[DataProvider('validNames')]
    public function test_store_accepts_name_length_boundaries(string $name): void
    {
        $classification = LetterNumberFactory::new()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('lt.store'), ['name' => $name, 'letter_number' => $classification->uuid])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('letter_templates', ['name' => $name]);
    }

    public static function validNames(): array
    {
        return [['ABC'], [str_repeat('A', 100)]];
    }

    #[DataProvider('invalidData')]
    public function test_store_rejects_invalid_identity_without_partial_writes(array $changes, string $field, string $message): void
    {
        $classification = LetterNumberFactory::new()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('lt.store'), [
            ...['name' => 'Surat Tugas', 'letter_number' => $classification->uuid], ...$changes,
        ])->assertInvalid([$field => $message]);
        $this->assertDatabaseCount('letter_templates', 0);
        $this->assertDatabaseCount('letter_template_versions', 0);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function invalidData(): array
    {
        return [
            'empty name' => [['name' => ' '], 'name', 'Nama Templat wajib diisi.'],
            'short name' => [['name' => 'AB'], 'name', 'Nama Templat minimal 3 karakter.'],
            'long name' => [['name' => str_repeat('A', 101)], 'name', 'Nama Templat maksimal 100 karakter.'],
            'name type' => [['name' => 123], 'name', 'Nama Templat harus berupa teks.'],
            'empty classification' => [['letter_number' => null], 'letter_number', 'Klasifikasi wajib diisi.'],
            'classification numeric' => [['letter_number' => 1], 'letter_number', 'Klasifikasi yang dipilih tidak valid.'],
            'classification unknown' => [['letter_number' => '00000000-0000-4000-8000-000000000000'], 'letter_number', 'Klasifikasi yang dipilih tidak valid.'],
        ];
    }

    public function test_store_rejects_duplicate_name(): void
    {
        LetterTemplate::factory()->create(['name' => 'Surat Tugas']);
        $classification = LetterNumberFactory::new()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('lt.store'), ['name' => 'Surat Tugas', 'letter_number' => $classification->uuid])
            ->assertInvalid(['name']);
        $this->assertDatabaseCount('letter_templates', 1);
        $this->assertDatabaseCount('letter_template_versions', 0);
    }

    public function test_guests_cannot_create_or_store_templates(): void
    {
        $this->get(route('lt.create'))->assertRedirectToRoute('login.index');
        $this->post(route('lt.store'), ['name' => 'Surat Tugas'])->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('letter_templates', 0);
    }
}
