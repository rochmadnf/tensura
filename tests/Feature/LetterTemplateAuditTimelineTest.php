<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\ChangeLetterTemplateConfigurationItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LetterTemplateAuditTimelineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_timeline_pages_ten_events_with_stable_order_across_versions_and_keeps_legacy_events(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $audits = LetterTemplateAudit::factory()->count(23)->for($version->template, 'template')->for($version, 'version')->create(['occurred_at' => now()->startOfSecond()]);
        LetterTemplateAudit::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        $url = route('lt.audits.index', $version->template->uuid);
        $first = $this->getJson($url)->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $audits->last()->uuid)->assertJsonPath('data.0.changes', null)->assertJsonPath('previous_cursor', null)->json();
        $second = $this->getJson($url . '?cursor=' . urlencode($first['next_cursor']))->assertOk()->assertJsonCount(10, 'data')->json();
        $last = $this->getJson($url . '?cursor=' . urlencode($second['next_cursor']))->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('next_cursor', null)->json();
        $ids = [...array_column($first['data'], 'id'), ...array_column($second['data'], 'id'), ...array_column($last['data'], 'id')];
        $this->assertCount(23, array_unique($ids));
        $this->getJson($url . '?cursor=' . urlencode($second['previous_cursor']))->assertJsonPath('data', $first['data']);
        $this->withoutVite()->get(route('lt.edit', $version->template->uuid))->assertOk()->assertInertia(fn(Assert $page) => $page->has('audits.data', 10)->missing('lifecycle.data.audits'));
    }

    public function test_activity_requires_authentication_and_rejects_invalid_cursors(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $url = route('lt.audits.index', $version->template->uuid);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create())->getJson($url . '?cursor=invalid')->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_question_audit_stores_before_after_and_survives_later_changes(): void
    {
        $actor = UserFactory::new()->create();
        $variable = LetterTemplateVariable::factory()->create(['label' => 'Tujuan', 'default_text' => 'Sebelum']);
        $version = $variable->version;
        $service = app(ChangeLetterTemplateConfigurationItem::class);
        $item = ['id' => $variable->uuid, 'name' => $variable->name, 'label' => 'Tujuan baru', 'type' => $variable->type->value, 'is_required' => true, 'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'default_text' => 'Sesudah', 'columns' => []];
        $service->change($version->template, $version, 'variable', 'save', $variable->uuid, null, ['item' => $item], $actor);
        $audit = $version->template->audits()->latest('id')->firstOrFail();
        $changes = collect($audit->changes)->keyBy('field');
        $this->assertSame('Sebelum', $changes['variable.' . $variable->uuid . '.fallback']['before']);
        $this->assertSame('Sesudah', $changes['variable.' . $variable->uuid . '.fallback']['after']);
        $this->assertSame('Tujuan', $changes['variable.' . $variable->uuid . '.label']['before']);
        $variable->refresh()->update(['label' => 'Nama berikutnya']);
        $this->assertStringContainsString('Tujuan baru', $audit->fresh()->changes[0]['label']);
    }

    public function test_audit_keeps_zero_fallback_and_records_effective_default_date_when_reset(): void
    {
        $actor = UserFactory::new()->create();
        $variable = LetterTemplateVariable::factory()->create(['type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE, 'is_multiple' => false]);
        $version = $variable->version;
        $service = app(ChangeLetterTemplateConfigurationItem::class);
        $service->change($version->template, $version, 'variable', 'attribute', $variable->uuid, 'join_date', ['default_text' => '0', 'date_format' => 'd F Y'], $actor);
        $changes = collect($version->template->audits()->latest('id')->firstOrFail()->changes)->keyBy('field');
        $prefix = 'variable.' . $variable->uuid . '.attribute.join_date.';
        $this->assertSame('0', $changes[$prefix . 'default_text']['after']);
        $this->assertSame('d-m-Y', $changes[$prefix . 'date_format']['before']);
        $service->change($version->template, $version, 'variable', 'attribute', $variable->uuid, 'join_date', ['default_text' => null, 'date_format' => null], $actor);
        $changes = collect($version->template->audits()->latest('id')->firstOrFail()->changes)->keyBy('field');
        $this->assertSame('0', $changes[$prefix . 'default_text']['before']);
        $this->assertSame('d-m-Y', $changes[$prefix . 'date_format']['after']);
    }

    public function test_invalid_mutations_and_boundary_moves_do_not_add_audits(): void
    {
        $actor = UserFactory::new()->create();
        $variable = LetterTemplateVariable::factory()->create();
        $version = $variable->version;
        app(ChangeLetterTemplateConfigurationItem::class)->change($version->template, $version, 'variable', 'move', $variable->uuid, null, ['direction' => 'up'], $actor);
        $this->assertDatabaseCount('letter_template_audits', 0);
        $this->actingAs($actor)->put(route('lt.configuration.variables.update', ['letterTemplate' => $version->template->uuid, 'version' => $version->uuid, 'item' => $variable->uuid]), ['item' => []])->assertSessionHasErrors();
        $this->assertDatabaseCount('letter_template_audits', 0);
    }
}
