<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterTemplateMasterProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('versionStatuses')]
    public function test_referenced_classification_cannot_be_deleted(LetterTemplateVersionStatusEnum $status): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => $status]);
        $classification = $version->letterNumber;

        $this->actingAs(UserFactory::new()->create())->delete(route('ln.destroy', $classification->uuid))
            ->assertInvalid(['deletion' => 'Klasifikasi tidak dapat dihapus karena masih dirujuk templat surat, termasuk versi historis atau diarsipkan.']);

        $this->assertModelExists($classification);
        $this->assertModelExists($version);
    }

    #[DataProvider('versionStatuses')]
    public function test_referenced_signer_employee_cannot_be_deleted(LetterTemplateVersionStatusEnum $status): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => $status]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $candidate = LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create();
        $employee = $candidate->employee;

        $this->actingAs(UserFactory::new()->create())->delete(route('empl.destroy', $employee->uuid))
            ->assertInvalid(['deletion' => 'Pegawai tidak dapat dihapus karena masih menjadi kandidat penandatangan templat surat, termasuk versi historis atau diarsipkan.']);

        $this->assertModelExists($employee);
        $this->assertModelExists($candidate);
    }

    #[DataProvider('versionStatuses')]
    public function test_reference_group_archive_is_rejected_without_leaving_snapshot_or_deleting_items(LetterTemplateVersionStatusEnum $status): void
    {
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create();
        $version = LetterTemplateVersion::factory()->create(['status' => $status]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create([
            'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::REFERENCE,
            'reference_group_id' => $group->id,
        ]);

        $this->actingAs(UserFactory::new()->create())->delete(route('ref.destroy', $group->uuid))
            ->assertInvalid(['deletion' => 'Grup referensi tidak dapat diarsipkan karena masih dirujuk templat surat, termasuk versi historis atau diarsipkan.']);

        $this->assertModelExists($group);
        $this->assertModelExists($item);
        $this->assertModelExists($variable);
        $this->assertDatabaseCount('archives', 0);
        $this->assertDatabaseCount('archive_records', 0);
    }

    public function test_individual_reference_item_can_still_be_archived_when_group_is_referenced(): void
    {
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create();
        $variable = LetterTemplateVariable::factory()->create(['type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::REFERENCE, 'reference_group_id' => $group->id]);

        $this->actingAs(UserFactory::new()->create())->delete(route('ref.item.destroy', [$group->uuid, $item->uuid]))->assertSessionHasNoErrors();

        $this->assertModelMissing($item);
        $this->assertModelExists($group);
        $this->assertModelExists($variable);
        $this->assertDatabaseCount('archives', 1);
        $this->assertDatabaseCount('archive_records', 1);
    }

    public function test_removing_unused_draft_configuration_releases_employee_and_reference_group(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::REFERENCE, 'reference_group_id' => ReferenceGroup::factory()]);
        $group = $variable->referenceGroup;
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $candidate = LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create();
        $employee = $candidate->employee;
        $this->actingAs(UserFactory::new()->create());

        $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [], 'signer_slots' => []])->assertSessionHasNoErrors();
        $this->delete(route('empl.destroy', $employee->uuid))->assertRedirectToRoute('empl.index');
        $this->delete(route('ref.destroy', $group->uuid))->assertRedirectToRoute('ref.index');

        $this->assertModelMissing($employee);
        $this->assertModelMissing($group);
        $this->assertModelExists($version);
        $this->assertDatabaseCount('archives', 1);
    }

    public static function versionStatuses(): array
    {
        return ['draft' => [LetterTemplateVersionStatusEnum::DRAFT], 'published' => [LetterTemplateVersionStatusEnum::PUBLISHED], 'archived' => [LetterTemplateVersionStatusEnum::ARCHIVED]];
    }
}
