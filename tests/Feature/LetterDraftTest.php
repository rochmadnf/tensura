<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\Letter;
use App\Models\LetterAudit;
use App\Models\LetterDraft;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterDraftTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_snapshot_without_allocating_a_number_and_stale_second_save_is_rejected(): void
    {
        $version = $this->version();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama', 'type' => 1]);
        $actor = UserFactory::new()->create(['name' => 'Ani']);
        $payload = $this->payload($version, [$variable->uuid => 'Surat pertama']);
        $this->actingAs($actor)->post(route('ltr.store'), $payload)->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $this->assertSame('Surat pertama', $letter->draft->snapshot['values']['nama']);
        $this->assertNull($letter->official_number);
        $this->assertDatabaseCount('letter_number_periods', 0);
        $payload['revision'] = 1;
        $payload['subject'] = 'Perihal disimpan Ani';
        $this->put(route('ltr.update', $letter->uuid), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $letter->draft->fresh()->revision);
        $payload['subject'] = 'Perihal operator kedua';
        $this->actingAs(UserFactory::new()->create())->put(route('ltr.update', $letter->uuid), $payload)->assertSessionHasErrors('revision');
        $this->assertSame('Perihal disimpan Ani', $letter->fresh()->subject);
        $this->assertStringContainsString('Ani', session('errors')->first('revision'));
    }

    public function test_reading_and_saving_preserve_employee_reference_and_tenure_snapshots_until_explicit_refresh(): void
    {
        $version = $this->version();
        $version->update(['validation_result' => ['valid' => true, 'placeholders' => ['pegawai', 'pegawai_nip', 'pegawai_masa_kerja', 'referensi', 'nomor_surat']]]);
        $employee = Employee::factory()->create(['name' => 'Nama Lama', 'front_title' => null, 'back_title' => null, 'nip' => '111111111111111111', 'join_date' => '2020-10-26', 'status' => 1]);
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Referensi Lama']);
        $person = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2]);
        $reference = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'referensi', 'type' => 4, 'option_source' => 1, 'reference_group_id' => $group->id]);
        $payload = $this->payload($version, [$person->uuid => $employee->uuid, $reference->uuid => $item->uuid]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $payload)->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $employee->update(['name' => 'Nama Baru', 'nip' => '222222222222222222', 'join_date' => '2025-10-26']);
        $item->update(['content' => 'Referensi Baru']);
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->component('letters/show')->where('resource.data.draft.resolved.pegawai', 'Nama Lama')->where('resource.data.draft.resolved.pegawai_nip', '111111111111111111')->where('resource.data.configuration.variables.0.options.0.label', 'Nama Lama')->missing('resource.data.snapshot')->missing('resource.data.draft.masters'));
        $payload = [...$payload, 'revision' => 1, 'letter_date' => '2026-10-27'];
        $this->put(route('ltr.update', $letter->uuid), $payload)->assertSessionHasNoErrors();
        $values = $letter->draft->fresh()->snapshot['values'];
        $this->assertSame('Nama Lama', $values['pegawai']);
        $this->assertSame('111111111111111111', $values['pegawai_nip']);
        $this->assertSame('Referensi Lama', $values['referensi']);
        $this->assertSame('6 tahun 1 hari', $values['pegawai_masa_kerja']);
        $preview = $this->getJson(route('ltr.masters.preview', [$letter->uuid, 'revision' => 2]))->assertOk()->json();
        $this->assertNotEmpty($preview['changes']);
        $this->assertSame(2, $letter->draft->fresh()->revision);
        $this->post(route('ltr.masters.apply', $letter->uuid), ['revision' => 2, 'confirmed' => true, 'change_token' => $preview['change_token']])->assertSessionHasNoErrors();
        $values = $letter->draft->fresh()->snapshot['values'];
        $this->assertSame('Nama Baru', $values['pegawai']);
        $this->assertSame('222222222222222222', $values['pegawai_nip']);
        $this->assertSame('Referensi Baru', $values['referensi']);
        $this->assertSame('1 tahun 1 hari', $values['pegawai_masa_kerja']);
        $this->assertDatabaseHas('letter_audits', ['letter_id' => $letter->id, 'event' => 'masters.refreshed']);
    }

    public function test_selection_order_and_signer_values_survive_round_trip_and_unused_fields_are_not_required(): void
    {
        $version = $this->version();
        $version->update(['validation_result' => ['valid' => true, 'placeholders' => ['nama_baris', 'penandatangan_nip']]]);
        $a = Employee::factory()->create(['name' => 'A', 'front_title' => null, 'back_title' => null, 'status' => 1]);
        $b = Employee::factory()->create(['name' => 'B', 'front_title' => null, 'back_title' => null, 'status' => 1, 'nip' => '123456789012345678']);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => true, 'is_required' => true]);
        $variable->columns()->create(['source_attribute' => 'name', 'placeholder' => 'nama_baris', 'sort_order' => 0]);
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'tidak_dipakai', 'is_required' => true]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $slot->candidates()->create(['employee_id' => $b->id]);
        $payload = $this->payload($version, [$variable->uuid => [$b->uuid, $a->uuid]], [$slot->uuid => $b->uuid]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $payload)->assertSessionHasNoErrors();
        $draft = Letter::sole()->draft;
        $this->assertSame([$b->uuid, $a->uuid], $draft->form_data['values'][$variable->uuid]);
        $this->assertSame('B', $draft->snapshot['values']['nama_baris#1']);
        $this->assertSame('A', $draft->snapshot['values']['nama_baris#2']);
        $this->assertSame('123456789012345678', $draft->snapshot['values']['penandatangan_nip']);
        $this->get(route('ltr.show', $draft->letter->uuid))->assertOk();
    }

    #[DataProvider('invalidDrafts')]
    public function test_rejects_invalid_identity_dates_and_manual_position_without_writing(array $override, string $field): void
    {
        $version = $this->version();
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), [...$this->payload($version), ...$override])->assertSessionHasErrors($field);
        $this->assertDatabaseCount('letters', 0);
        $this->assertDatabaseCount('letter_number_periods', 0);
    }

    public static function invalidDrafts(): array
    {
        return [[['subject' => ''], 'subject'], [['subject' => str_repeat('x', 256)], 'subject'], [['letter_date' => '2026-02-30'], 'letter_date'], [['letter_date' => '0000-10-26'], 'letter_date'], [['number_month' => 11], 'number_month'], [['number_year' => 0], 'number_year'], [['numbering_mode' => 7], 'numbering_mode'], [['numbering_mode' => 2], 'page_number'], [['numbering_mode' => 2, 'page_number' => 0, 'sequence_number' => 1], 'page_number'], [['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 100], 'sequence_number']];
    }

    public function test_required_fields_foreign_choices_inactive_employees_and_unknown_values_are_rejected(): void
    {
        $version = $this->version();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama', 'is_required' => true]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($version))->assertSessionHasErrors('values.' . $variable->uuid);
        $this->post(route('ltr.store'), $this->payload($version, [fake()->uuid() => 'unknown']))->assertSessionHasErrors('values');
        $variable->update(['type' => 4, 'option_source' => 2]);
        $inactive = Employee::factory()->create(['status' => 2]);
        $this->post(route('ltr.store'), $this->payload($version, [$variable->uuid => $inactive->uuid]))->assertSessionHasErrors('values.' . $variable->uuid);
        $group = ReferenceGroup::factory()->create();
        $foreign = ReferenceItem::factory()->create();
        $variable->update(['option_source' => 1, 'reference_group_id' => $group->id]);
        $this->post(route('ltr.store'), $this->payload($version, [$variable->uuid => $foreign->uuid]))->assertSessionHasErrors('values.' . $variable->uuid);
        $this->assertDatabaseCount('letters', 0);
    }

    public function test_only_current_active_published_template_can_create_and_system_fields_cannot_be_spoofed(): void
    {
        $version = $this->version();
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $version->template->update(['is_active' => false]);
        $this->post(route('ltr.store'), $this->payload($version))->assertSessionHasErrors('template');
        $version->template->update(['is_active' => true]);
        $this->post(route('ltr.store'), [...$this->payload($version), 'template_version' => fake()->uuid()])->assertSessionHasErrors('template_version');
        $this->post(route('ltr.store'), [...$this->payload($version), 'status' => 2, 'official_number' => 'Forged', 'snapshot' => ['values' => ['nomor_surat' => 'Forged']], 'created_by' => 999, 'numbering_mode' => 1, 'page_number' => 88, 'sequence_number' => 88])->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $this->assertSame(1, $letter->status->value);
        $this->assertSame($actor->id, $letter->created_by);
        $this->assertNull($letter->official_number);
        $this->assertNull($letter->draft->page_number);
        $this->assertStringContainsString('/XX.XX/', $letter->draft->snapshot['values']['nomor_surat']);
    }

    public function test_inactive_template_keeps_draft_readable_and_editable_with_readiness_reason(): void
    {
        $version = $this->version();
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($version))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $version->template->update(['is_active' => false]);
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('resource.data.editable', true)->where('resource.data.draft.reasons.0', 'Templat nonaktif atau belum memiliki versi aktif. Penerbitan tertahan.'));
        $this->put(route('ltr.update', $letter->uuid), [...$this->payload($version), 'revision' => 1, 'subject' => 'Masih bisa disimpan'])->assertSessionHasNoErrors();
        $this->assertSame('Masih bisa disimpan', $letter->fresh()->subject);
    }

    public function test_list_search_filters_pagination_order_and_public_resource_contract(): void
    {
        $version = $this->version();
        $creator = UserFactory::new()->create(['name' => 'Operator A']);
        $this->actingAs($creator);
        for ($index = 0; $index < 7; $index++) {
            $this->post(route('ltr.store'), [...$this->payload($version), 'subject' => 'Surat kesehatan ' . $index])->assertSessionHasNoErrors();
        }
        $other = LetterDraft::factory()->create();
        $this->get(route('ltr.index', ['keyword' => 'kesehatan', 'filter' => 'status:1|template:' . $version->template->uuid . '|creator:' . $creator->uuid . '|year:2026|month:10', 'per_page' => 5]))->assertInertia(fn(AssertableInertia $page) => $page->component('letters/index')->has('resources.data', 5)->where('resources.meta.total', 7)->where('resources.data.0.subject', 'Surat kesehatan 6')->missing('resources.data.0.draft')->missing('resources.data.0.created_by')->where('filters.keyword', 'kesehatan'));
        $this->get(route('ltr.index', ['keyword' => 'kesehatan', 'page' => 2, 'per_page' => 5]))->assertInertia(fn(AssertableInertia $page) => $page->has('resources.data', 2));
        $this->get(route('ltr.index', ['filter' => 'status:invalid|year:99999|month:13', 'per_page' => 999]))->assertInertia(fn(AssertableInertia $page) => $page->where('resources.meta.per_page', 10)->where('filters.status', null)->where('filters.year', null)->where('filters.month', null));
        $this->assertModelExists($other);
    }

    public function test_delete_requires_confirmation_current_revision_and_initial_draft_and_preserves_other_letters(): void
    {
        $version = $this->version();
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($version))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $other = LetterDraft::factory()->create();
        $this->delete(route('ltr.destroy', $letter->uuid), ['revision' => 1])->assertSessionHasErrors('confirmed');
        $this->delete(route('ltr.destroy', $letter->uuid), ['revision' => 2, 'confirmed' => true])->assertSessionHasErrors('revision');
        $letter->update(['status' => 2]);
        $this->delete(route('ltr.destroy', $letter->uuid), ['revision' => 1, 'confirmed' => true])->assertSessionHasErrors('draft');
        $letter->update(['status' => 1]);
        $this->delete(route('ltr.destroy', $letter->uuid), ['revision' => 1, 'confirmed' => true])->assertSessionHasNoErrors()->assertRedirectToRoute('ltr.index');
        $this->assertModelMissing($letter);
        $this->assertDatabaseMissing('letter_audits', ['letter_id' => $letter->id]);
        $this->assertModelExists($other);
        $this->assertModelExists($other->letter);
    }

    public function test_endpoints_require_authentication_uuid_scope_and_do_not_offer_document_generation(): void
    {
        $letter = LetterDraft::factory()->create()->letter;
        foreach (['ltr.index' => [], 'ltr.create' => [], 'ltr.show' => [$letter->uuid], 'ltr.edit' => [$letter->uuid]] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertRedirectToRoute('login.index');
        }
        $this->postJson(route('ltr.store'))->assertUnauthorized();
        $this->putJson(route('ltr.update', $letter->uuid))->assertUnauthorized();
        $this->deleteJson(route('ltr.destroy', $letter->uuid))->assertUnauthorized();
        $this->getJson(route('ltr.masters.preview', [$letter->uuid, 'revision' => 1]))->assertUnauthorized();
        $this->postJson(route('ltr.adjustment.apply', $letter->uuid))->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create());
        $this->get('/letters/' . $letter->id)->assertNotFound();
        $this->get(route('ltr.show', fake()->uuid()))->assertNotFound();
        $this->get('/letters/' . $letter->uuid . '/download')->assertNotFound();
    }

    public function test_database_failure_rolls_back_identity_snapshot_revision_and_audit_together(): void
    {
        $version = $this->version();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama']);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($version, [$variable->uuid => 'Original']))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        Exceptions::fake();
        LetterAudit::creating(function (): void { throw new \RuntimeException('Simulated audit failure'); });
        try {
            $this->put(route('ltr.update', $letter->uuid), [...$this->payload($version, [$variable->uuid => 'New value']), 'revision' => 1, 'subject' => 'New subject'])->assertInternalServerError();
        } finally {
            LetterAudit::getEventDispatcher()->forget('eloquent.creating: ' . LetterAudit::class);
        }
        $this->assertSame('Surat tugas', $letter->fresh()->subject);
        $this->assertSame(1, $letter->draft->fresh()->revision);
        $this->assertSame('Original', $letter->draft->fresh()->snapshot['values']['nama']);
        $this->assertDatabaseCount('letter_audits', 1);
    }

    public function test_manual_number_is_saved_as_an_unreserved_choice_and_database_enforces_one_draft(): void
    {
        $version = $this->version();
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), [...$this->payload($version), 'numbering_mode' => 2, 'page_number' => 23, 'sequence_number' => 44])->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $this->assertSame(23, $letter->draft->page_number);
        $this->assertSame(44, $letter->draft->sequence_number);
        $this->assertNull($letter->official_number);
        $this->assertDatabaseCount('letter_number_periods', 0);
        $this->post(route('ltr.store'), [...$this->payload($version), 'numbering_mode' => 2, 'page_number' => 23, 'sequence_number' => 44])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('letters', 2);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn() => LetterDraft::factory()->create(['letter_id' => $letter->id, 'template_version_id' => $version->id]));
    }

    public function test_reference_labels_are_frozen_even_when_multiple_rows_only_use_row_numbers(): void
    {
        $version = $this->version();
        $version->update(['validation_result' => ['valid' => true, 'placeholders' => ['urut']]]);
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Pilihan semula']);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'referensi', 'type' => 4, 'option_source' => 1, 'reference_group_id' => $group->id, 'is_multiple' => true]);
        $variable->columns()->create(['source_attribute' => 'row_number', 'placeholder' => 'urut', 'sort_order' => 0]);
        $payload = $this->payload($version, [$variable->uuid => [$item->uuid]]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $payload)->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $item->update(['content' => 'Pilihan terbaru']);
        $this->put(route('ltr.update', $letter->uuid), [...$payload, 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Pilihan semula', $letter->draft->snapshot['masters']['references'][$item->uuid]['content']);
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('resource.data.configuration.variables.0.options.0.label', 'Pilihan semula'));
    }

    protected function version(): LetterTemplateVersion
    {
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nama', 'nomor_surat', 'tanggal_surat']]]);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);

        return $version;
    }

    protected function payload(LetterTemplateVersion $version, array $values = [], array $signers = []): array
    {
        return ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat tugas', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => $values, 'signers' => $signers];
    }
}
