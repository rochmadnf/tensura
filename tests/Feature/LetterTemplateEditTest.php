<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterTemplateEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_edit_page_exposes_only_public_identity_and_draft_fields(): void
    {
        $version = LetterTemplateVersion::factory()->create(['source_path' => 'private.docx']);
        $template = $version->template;
        $classification = $version->letterNumber;

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.edit', $template->uuid))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->component('letter-templates/edit')
            ->where('page.id', 'a3063dc3-7414-4385-bf3f-0cf0742e3980')
            ->where('resource.data', [
                'id' => $template->uuid, 'name' => $template->name, 'is_active' => true,
                'active_version' => null, 'has_draft' => true,
                'classification' => ['id' => $classification->uuid, 'code' => $classification->classification_code, 'name' => $classification->classification_name],
                'draft' => ['id' => $version->uuid, 'number' => 1, 'letter_number' => $classification->uuid],
            ]));
    }

    public function test_update_changes_only_identity_keeps_source_and_history_and_invalidates_evidence(): void
    {
        $version = LetterTemplateVersion::factory()->create([
            'version_number' => 2, 'source_disk' => 'local', 'source_path' => 'original.docx',
            'source_sha256' => str_repeat('a', 64), 'validated_at' => now(), 'validation_result' => ['valid' => true],
            'validated_source_sha256' => str_repeat('a', 64), 'validated_configuration_sha256' => str_repeat('b', 64),
            'test_disk' => 'local', 'test_path' => 'test.docx', 'tested_at' => now(), 'test_expires_at' => now()->addHour(),
            'tested_source_sha256' => str_repeat('a', 64), 'tested_configuration_sha256' => str_repeat('b', 64),
            'visually_confirmed_at' => now(), 'visually_confirmed_by' => UserFactory::new(),
            'visually_confirmed_by_uuid' => (string) Str::uuid(), 'visually_confirmed_by_name' => 'Pemeriksa',
        ]);
        $template = $version->template;
        $history = LetterTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'source_path' => 'historical.docx', 'first_used_at' => now()]);
        $template->update(['active_version_id' => $history->id]);
        $original = $version->letterNumber;
        $replacement = LetterNumberFactory::new()->create();
        $counters = [$original->only(['page_number', 'sequence_number']), $replacement->only(['page_number', 'sequence_number'])];

        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $template->uuid), [
            'name' => ' Nama Baru ', 'letter_number' => $replacement->uuid,
            'id' => 999, 'uuid' => (string) Str::uuid(), 'active_version_id' => null, 'is_active' => false,
            'version_id' => $history->uuid, 'version_number' => 99, 'status' => 3, 'source_path' => 'overwrite.docx',
            'first_used_at' => now()->toDateTimeString(), 'letter_number_id' => $history->letter_number_id,
            'page_number' => 99, 'sequence_number' => 99,
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', $template->uuid);

        $this->assertSame('Nama Baru', $template->fresh()->name);
        $this->assertTrue($template->fresh()->is_active);
        $this->assertSame($history->id, $template->fresh()->active_version_id);
        $stored = $version->fresh();
        $this->assertSame($replacement->id, $stored->letter_number_id);
        $this->assertSame(2, $stored->version_number);
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $stored->status);
        $this->assertNull($stored->first_used_at);
        $this->assertSame('original.docx', $stored->source_path);
        $this->assertSame(str_repeat('a', 64), $stored->source_sha256);
        foreach (['validated_source_sha256', 'validated_configuration_sha256', 'validated_at', 'validation_result', 'tested_source_sha256', 'tested_configuration_sha256', 'test_disk', 'test_path', 'tested_at', 'test_expires_at', 'visually_confirmed_at', 'visually_confirmed_by', 'visually_confirmed_by_uuid', 'visually_confirmed_by_name'] as $field) {
            $this->assertNull($stored->$field, $field);
        }
        $this->assertSame('historical.docx', $history->fresh()->source_path);
        $this->assertSame($history->letter_number_id, $history->fresh()->letter_number_id);
        $this->assertSame($counters, [$original->fresh()->only(['page_number', 'sequence_number']), $replacement->fresh()->only(['page_number', 'sequence_number'])]);
        $this->assertDatabaseCount('letter_template_versions', 2);
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'identity.updated']);
    }

    #[DataProvider('identityFields')]
    public function test_changing_either_identity_field_invalidates_evidence(string $field): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        $data = ['name' => $version->template->name, 'letter_number' => $version->letterNumber->uuid];
        $data[$field] = $field === 'name' ? 'Nama Baru' : LetterNumberFactory::new()->create()->uuid;

        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $version->template->uuid), $data)
            ->assertSessionHasNoErrors();

        $this->assertNull($version->fresh()->validated_at);
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'identity.updated']);
    }

    /** @return array<string, array{string}> */
    public static function identityFields(): array
    {
        return ['name' => ['name'], 'classification' => ['letter_number']];
    }

    public function test_used_draft_cannot_be_changed(): void
    {
        $version = LetterTemplateVersion::factory()->create(['first_used_at' => now()]);

        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $version->template->uuid), ['name' => 'Nama Baru', 'letter_number' => $version->letterNumber->uuid])
            ->assertInvalid(['name']);

        $this->assertSame($version->template->name, $version->template->fresh()->name);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_unchanged_identity_keeps_evidence_and_does_not_add_an_audit(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $version->template->uuid), ['name' => $version->template->name, 'letter_number' => $version->letterNumber->uuid])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($version->fresh()->validated_at);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    #[DataProvider('invalidData')]
    public function test_update_rejects_invalid_identity_without_changes(array $changes, string $field, string $message): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $version->template->uuid), [
            ...['name' => 'Nama Baru', 'letter_number' => $version->letterNumber->uuid], ...$changes,
        ])->assertInvalid([$field => $message]);
        $this->assertSame($version->template->name, $version->template->fresh()->name);
        $this->assertSame($version->letter_number_id, $version->fresh()->letter_number_id);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function invalidData(): array
    {
        return LetterTemplateStoreTest::invalidData();
    }

    public function test_update_rejects_another_templates_name(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $other = LetterTemplate::factory()->create();
        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $version->template->uuid), ['name' => $other->name, 'letter_number' => $version->letterNumber->uuid])
            ->assertInvalid(['name']);
        $this->assertSame($version->template->name, $version->template->fresh()->name);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_update_requires_an_editable_draft(LetterTemplateVersionStatusEnum $status): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => $status]);
        $otherDraft = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->patch(route('lt.update', $version->template->uuid), ['name' => 'Nama Baru', 'letter_number' => $version->letterNumber->uuid, 'version_id' => $otherDraft->uuid])
            ->assertInvalid(['name' => 'Identitas hanya dapat diubah pada draft yang belum digunakan.']);
        $this->assertSame($version->template->name, $version->template->fresh()->name);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function nonDraftStatuses(): array
    {
        return [[LetterTemplateVersionStatusEnum::PUBLISHED], [LetterTemplateVersionStatusEnum::ARCHIVED]];
    }

    public function test_guests_cannot_edit_or_update_templates(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->get(route('lt.edit', $version->template->uuid))->assertRedirectToRoute('login.index');
        $this->patch(route('lt.update', $version->template->uuid), ['name' => 'Nama Baru'])->assertRedirectToRoute('login.index');
        $this->assertSame($version->template->name, $version->template->fresh()->name);
    }

    public function test_unknown_uuid_and_numeric_identifiers_return_404(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        foreach ([$version->template->id, (string) Str::uuid()] as $identifier) {
            $this->get(route('lt.edit', $identifier))->assertNotFound();
            $this->patch(route('lt.update', $identifier), [])->assertNotFound();
        }
    }
}
