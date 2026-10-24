<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Services\LetterTemplate\RecordLetterTemplateAudit;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterTemplateStorageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configuration_round_trips_with_ordered_relationships_and_casts(): void
    {
        $version = LetterTemplateVersion::factory()->create([
            'source_disk' => 'local', 'source_path' => 'letter-templates/sources/test.docx',
            'source_original_name' => 'contoh.docx', 'source_size' => 1234,
            'source_sha256' => str_repeat('a', 64), 'validated_at' => now(),
            'validation_result' => ['valid' => true],
        ]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create([
            'type' => LetterTemplateVariableTypeEnum::COMBOBOX,
            'option_source' => LetterTemplateOptionSourceEnum::REFERENCE,
            'reference_group_id' => ReferenceGroup::factory(), 'is_multiple' => true,
        ]);
        $last = LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['sort_order' => 2]);
        $first = LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['sort_order' => 1]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $candidate = LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create();

        $stored = $version->fresh();

        $this->assertTrue($stored->template->is_active);
        $this->assertNull($stored->template->activeVersion);
        $this->assertTrue($stored->template->versions->sole()->is($version));
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $stored->status);
        $this->assertNotNull($stored->letterNumber);
        $this->assertSame(1234, $stored->source_size);
        $this->assertSame(str_repeat('a', 64), $stored->source_sha256);
        $this->assertSame(['valid' => true], $stored->validation_result);
        $this->assertNotNull($stored->validated_at);
        $this->assertNull($stored->first_used_at);
        $this->assertNull($stored->published_at);
        $this->assertTrue($stored->variables->sole()->is($variable));
        $this->assertSame(LetterTemplateVariableTypeEnum::COMBOBOX, $variable->fresh()->type);
        $this->assertSame(LetterTemplateOptionSourceEnum::REFERENCE, $variable->fresh()->option_source);
        $this->assertTrue($variable->fresh()->is_multiple);
        $this->assertNotNull($variable->referenceGroup);
        $this->assertSame([$first->id, $last->id], $variable->columns->modelKeys());
        $this->assertTrue($first->variable->version->is($version));
        $this->assertTrue($stored->signerSlots->sole()->candidates->sole()->is($candidate));
        $this->assertTrue($candidate->slot->version->is($version));
        $this->assertNotNull($candidate->employee);
    }

    public function test_published_version_can_become_active_for_its_template(): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED]);

        $version->template->update(['active_version_id' => $version->id]);

        $this->assertTrue($version->template->fresh()->activeVersion->is($version));
    }

    public function test_draft_cannot_become_active(): void
    {
        $version = LetterTemplateVersion::factory()->create();

        $this->expectException(ValidationException::class);
        $version->template->update(['active_version_id' => $version->id]);
    }

    public function test_database_rejects_active_version_owned_by_another_template(): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED]);
        $other = LetterTemplate::factory()->create();

        $this->expectException(QueryException::class);
        LetterTemplate::whereKey($other->id)->update(['active_version_id' => $version->id]);
    }

    public function test_audit_keeps_actor_identity_after_account_deletion(): void
    {
        $this->travelTo(now()->startOfSecond());
        $version = LetterTemplateVersion::factory()->create();
        $actor = UserFactory::new()->create();
        $audit = app(RecordLetterTemplateAudit::class)->record($version->template, $version, $actor, 'draft.created', 'Draft awal dibuat.');

        $actor->delete();

        $stored = $audit->fresh();
        $this->assertNull($stored->actor);
        $this->assertSame($actor->uuid, $stored->actor_uuid);
        $this->assertSame($actor->name, $stored->actor_name);
        $this->assertSame('draft.created', $stored->operation);
        $this->assertSame('Draft awal dibuat.', $stored->description);
        $this->assertTrue($stored->occurred_at->equalTo(now()));
        $this->assertTrue($stored->template->audits->sole()->is($audit));
        $this->assertTrue($stored->version->audits->sole()->is($audit));
    }

    public function test_audit_rejects_version_from_another_template(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $other = LetterTemplate::factory()->create();
        $actor = UserFactory::new()->create();

        $this->expectException(InvalidArgumentException::class);
        app(RecordLetterTemplateAudit::class)->record($other, $version, $actor, 'draft.created', 'Tidak valid.');
    }

    #[DataProvider('uniqueRecords')]
    public function test_duplicate_configuration_keys_are_rejected(string $modelClass, array $keys): void
    {
        $record = $modelClass::factory()->create();

        $this->expectException(QueryException::class);
        $modelClass::factory()->create($record->only($keys));
    }

    /** @return array<string, array{class-string, list<string>}> */
    public static function uniqueRecords(): array
    {
        return [
            'template name' => [LetterTemplate::class, ['name']],
            'version number' => [LetterTemplateVersion::class, ['letter_template_id', 'version_number']],
            'variable name' => [LetterTemplateVariable::class, ['version_id', 'name']],
            'column placeholder' => [LetterTemplateVariableColumn::class, ['variable_id', 'placeholder']],
            'slot order' => [LetterTemplateSignerSlot::class, ['version_id', 'sort_order']],
            'candidate' => [LetterTemplateSignerCandidate::class, ['slot_id', 'employee_id']],
            'template uuid' => [LetterTemplate::class, ['uuid']],
            'version uuid' => [LetterTemplateVersion::class, ['uuid']],
            'variable uuid' => [LetterTemplateVariable::class, ['uuid']],
            'column uuid' => [LetterTemplateVariableColumn::class, ['uuid']],
            'slot uuid' => [LetterTemplateSignerSlot::class, ['uuid']],
            'candidate uuid' => [LetterTemplateSignerCandidate::class, ['uuid']],
            'audit uuid' => [LetterTemplateAudit::class, ['uuid']],
        ];
    }

    #[DataProvider('referencedRecords')]
    public function test_referenced_records_cannot_be_deleted(string $relation): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::ARCHIVED]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create([
            'type' => LetterTemplateVariableTypeEnum::COMBOBOX,
            'option_source' => LetterTemplateOptionSourceEnum::REFERENCE,
            'reference_group_id' => ReferenceGroup::factory(),
        ]);
        $column = LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create();
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $candidate = LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create();
        $record = match ($relation) {
            'template' => $version->template,
            'version' => $version,
            'classification' => $version->letterNumber,
            'reference' => $variable->referenceGroup,
            'variable' => $column->variable,
            'slot' => $candidate->slot,
            'employee' => $candidate->employee,
        };

        $this->expectException(QueryException::class);
        $record->delete();
    }

    /** @return array<string, array{string}> */
    public static function referencedRecords(): array
    {
        return array_combine(
            ['template', 'version', 'classification', 'reference', 'variable', 'slot', 'employee'],
            array_map(fn(string $relation): array => [$relation], ['template', 'version', 'classification', 'reference', 'variable', 'slot', 'employee']),
        );
    }
}
