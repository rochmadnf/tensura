<?php

namespace Tests\Feature;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\AdjustLetterDraftTemplate;
use App\Services\Letter\RenderLetterDocument;
use App\Services\Letter\ResolveLetterDocumentSnapshot;
use App\Services\LetterTemplate\CopyLetterTemplateVersion;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateExchangeConfiguration;
use App\Services\LetterTemplate\LetterTemplateExchangePackage;
use App\Services\LetterTemplate\LetterTemplateExchangeStage;
use App\Services\LetterTemplate\SaveLetterTemplateConfiguration;
use App\Services\LetterTemplate\ValidateLetterTemplateDocx;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class LetterQuestionRenderingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function prepared(): LetterTemplateVersion
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $user = UserFactory::new()->create();
        $this->actingAs($user);
        $base = ['is_required' => true, 'is_multiple' => false, 'columns' => [], 'option_source' => null, 'default_text' => null];
        app(SaveLetterTemplateConfiguration::class)->save($version->template, $version, ['variables' => [
            [...$base, 'name' => 'layanan', 'label' => 'Pelayanan', 'type' => 5, 'radio' => ['selected_text' => '✔', 'options' => [['code' => 'jalan', 'label' => 'Jalan'], ['code' => 'inap', 'label' => 'Inap']]]],
            [...$base, 'name' => 'biaya', 'label' => 'Biaya', 'type' => 7, 'numeric' => ['mode' => 'decimal', 'scale' => 2, 'allow_negative' => false]],
        ], 'signer_slots' => []], $user);
        $version = $version->fresh();
        $word = new PhpWord();
        $section = $word->addSection();
        $section->addText('${layanan:jalan} ${layanan:inap} ${biaya}');
        $section->addHeader()->addText('${biaya:terbilang}');
        $section->addFooter()->addText('${biaya:terbilang}');
        $table = $section->addTable();
        $table->addRow();
        $run = $table->addCell(4000)->addTextRun();
        $run->addText('${biaya:');
        $run->addText('terbilang}');
        Storage::disk('local')->makeDirectory('tests');
        IOFactory::createWriter($word, 'Word2007')->save(Storage::disk('local')->path('tests/question.docx'));
        $result = app(ValidateLetterTemplateDocx::class)->inspectPlaceholders(Storage::disk('local')->path('tests/question.docx'), $version);
        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
        $version->update(['validation_result' => $result]);

        return $version->fresh();
    }

    public function test_snapshot_and_renderer_use_radio_and_money_tokens_and_freeze_the_result(): void
    {
        $version = $this->prepared();
        $variables = $version->variables->keyBy('name');
        $snapshot = app(ResolveLetterDocumentSnapshot::class)->resolve($version, ['values' => [$variables['layanan']->uuid => 'inap', $variables['biaya']->uuid => '1250000.50'], 'signers' => []], CarbonImmutable::parse('2026-11-02'), 'CONTOH');
        $this->assertSame('', $snapshot['values']['layanan:jalan']);
        $this->assertSame('✔', $snapshot['values']['layanan:inap']);
        $this->assertSame('Rp1.250.000,50', $snapshot['values']['biaya']);
        $variables['layanan']->update(['radio' => ['selected_text' => 'X']]);
        app(RenderLetterDocument::class)->render(Storage::disk('local')->path('tests/question.docx'), $snapshot, Storage::disk('local')->path('tests/output.docx'));
        $parts = app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path('tests/output.docx'));
        $this->assertStringContainsString('✔', $parts['word/document.xml']->textContent);
        $this->assertStringContainsString('Lima Puluh Sen', $parts['word/header1.xml']->textContent);
        $this->assertStringContainsString('Lima Puluh Sen', $parts['word/footer1.xml']->textContent);
        $this->assertStringNotContainsString('${biaya:', $parts['word/document.xml']->textContent);
    }

    public function test_resolver_endpoint_rejects_other_question_and_returns_backend_computed_text(): void
    {
        $version = $this->prepared();
        $money = $version->variables->firstWhere('name', 'biaya');
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$money->uuid => '12.50']])->assertOk()->assertJsonPath('data.fields.0.words', 'Dua Belas Rupiah Dan Lima Puluh Sen');
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => ['00000000-0000-4000-8000-000000000099' => '12']])->assertUnprocessable();
        auth()->logout();
        $this->postJson(route('lt.values.resolve'), [])->assertUnauthorized();
    }

    public function test_optional_zero_and_invalid_precision_are_not_confused_with_empty_answers(): void
    {
        $version = $this->prepared();
        $money = $version->variables->firstWhere('name', 'biaya');
        $money->update(['is_required' => false]);
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$money->uuid => '0']])->assertOk()->assertJsonPath('data.fields.0.words', 'Nol Rupiah');
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$money->uuid => null]])->assertOk()->assertJsonPath('data.fields.0.words', null)->assertJsonPath('data.fields.0.tokens.biaya', '');
        foreach (['1e3', '1.000,50', '0.001', '-1', '1000000000000'] as $input) {
            $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$money->uuid => $input]])->assertUnprocessable()->assertJsonValidationErrors('values.' . $money->uuid);
        }
        $radio = $version->variables->firstWhere('name', 'layanan');
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$radio->uuid => ['jalan', 'inap']]])->assertUnprocessable()->assertJsonValidationErrors('values.' . $radio->uuid);
    }

    public function test_copy_preserves_radio_codes_and_adjustment_clears_incompatible_choices_and_precision(): void
    {
        $source = $this->prepared();
        $files = [];
        $copy = app(CopyLetterTemplateVersion::class)->copy($source->template, $source, $files);
        $copy->update(['validation_result' => $source->validation_result]);
        $old = $source->variables->keyBy('name');
        $new = $copy->fresh()->variables->keyBy('name');
        $this->assertSame(['jalan', 'inap'], $new['layanan']->radioOptions->pluck('code')->all());
        $this->assertNotSame($old['layanan']->radioOptions->first()->uuid, $new['layanan']->radioOptions->first()->uuid);
        $new['layanan']->radioOptions()->where('code', 'inap')->update(['code' => 'lain']);
        $new['biaya']->update(['numeric' => ['mode' => 'integer', 'scale' => 0, 'allow_negative' => false]]);
        $mapped = app(AdjustLetterDraftTemplate::class)->map($source, $copy->fresh(), ['values' => [$old['layanan']->uuid => 'inap', $old['biaya']->uuid => '12.50'], 'signers' => []]);
        $this->assertNull($mapped['data']['values'][$new['layanan']->uuid]);
        $this->assertNull($mapped['data']['values'][$new['biaya']->uuid]);
    }

    public function test_exchange_preserves_codes_but_omits_local_option_ids(): void
    {
        $version = $this->prepared();
        $config = app(LetterTemplateExchangeConfiguration::class)->export($version);
        app(LetterTemplateExchangeConfiguration::class)->validate($config);
        $this->assertSame('jalan', $config['variables'][0]['radio']['options'][0]['code']);
        $this->assertArrayNotHasKey('id', $config['variables'][0]['radio']['options'][0]);
        $path = 'tests/question.docx';
        $version->update(['source_disk' => 'local', 'source_path' => $path, 'source_sha256' => hash_file('sha256', Storage::disk('local')->path($path))]);
        $package = app(LetterTemplateExchangePackage::class);
        $prepared = $package->prepare($version->template, $version->fresh(), true, false);
        $decoded = $package->decode($package->encode($prepared, 'Test-password-1234'), 'Test-password-1234');
        $token = app(LetterTemplateExchangeStage::class)->create($decoded, auth()->user());
        $this->post(route('lt.exchange.store', $token), ['name' => 'Hasil impor pertanyaan', 'target' => null, 'letter_number' => $version->letterNumber->uuid, 'reference_groups' => [], 'signers' => [], 'replace_draft' => false, 'version_token' => null, 'confirmed' => true])->assertSessionHasNoErrors();
        $imported = LetterTemplate::where('name', 'Hasil impor pertanyaan')->sole()->draftVersion;
        $this->assertSame($config, app(LetterTemplateExchangeConfiguration::class)->export($imported));
        $this->assertNotSame($version->variables->first()->radioOptions->first()->uuid, $imported->variables->first()->radioOptions->first()->uuid);
        $this->assertSame(Storage::disk('local')->get($path), Storage::disk('local')->get($imported->source_path));
    }
}
