<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class LetterTemplateIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_complete_workflow_preserves_published_history_after_cleanup_copy_and_archiving(): void
    {
        $this->freezeTime();
        $disk = Storage::fake('local');
        $actor = UserFactory::new()->create();
        $number = LetterNumberFactory::new()->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $this->actingAs($actor)->post(route('lt.store'), ['name' => 'Surat Integrasi', 'letter_number' => $number->uuid])->assertSessionHasNoErrors();
        $template = LetterTemplate::query()->sole();
        $version = $template->draftVersion;
        $args = [$template->uuid, $version->uuid];
        $item = ['name' => 'tujuan', 'label' => 'Tujuan', 'type' => 1, 'is_required' => false, 'is_multiple' => false, 'option_source' => null, 'reference_group' => null, 'default_text' => 'Tujuan alternatif', 'columns' => []];
        $this->post(route('lt.configuration.variables.store', $args), ['item' => $item])->assertSessionHasNoErrors();
        $this->post(route('lt.configuration.slots.store', $args), ['item' => ['candidates' => [$employee->uuid]]])->assertSessionHasNoErrors();
        $guide = $this->get(route('lt.example.download', $args))->assertOk();
        $guidePath = $guide->baseResponse->getFile()->getPathname();
        $source = tempnam(sys_get_temp_dir(), 'letter-integration-');
        try {
            $word = new PhpWord();
            $word->addSection()->addText('Tujuan: ${tujuan}');
            IOFactory::createWriter($word, 'Word2007')->save($source);
            $this->post(route('lt.source.store', $args), ['docx' => UploadedFile::fake()->createWithContent('source.docx', file_get_contents($source))])->assertSessionHasNoErrors();
            $this->post(route('lt.validation.store', $args))->assertSessionHasNoErrors();
            $version->refresh();
            $this->post(route('lt.test.store', $args), ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version), 'values' => [], 'signers' => []])->assertSessionHasNoErrors();
            $version->refresh();
            $text = app(InspectLetterTemplateDocx::class)->read($disk->path($version->test_path))['word/document.xml']->textContent;
            $this->assertStringContainsString('Tujuan alternatif', $text);
            $employee->update(['status' => AsnStatusEnum::PENSIUN]);
            $this->withoutVite()->get(route('lt.edit', $template->uuid))->assertInertia(fn(Assert $page) => $page->has('configuration.data.warnings', 1));
            $this->post(route('lt.test.confirm', $args), ['test_token' => app(LetterTemplateTestEvidence::class)->token($version), 'confirmed' => true])->assertSessionHasNoErrors();
            $this->post(route('lt.versions.publish', $args), $this->publication($version))->assertInvalid('publication');
            $employee->update(['status' => AsnStatusEnum::AKTIF]);
            $this->post(route('lt.versions.publish', $args), $this->publication($version))->assertSessionHasNoErrors();
            $version->refresh();
            $sourceHash = hash_file('sha256', $disk->path($version->source_path));
            $this->travel(25)->hours();
            $this->artisan('letter-templates:cleanup')->assertExitCode(0);
            $disk->assertMissing($version->test_path);
            $this->assertSame($version->id, $template->fresh()->active_version_id);
            $version->update(['first_used_at' => now()]);
            $this->post(route('lt.versions.draft', $args), ['version_token' => app(LetterTemplateVersionState::class)->token($version->fresh()), 'confirmed' => true])->assertSessionHasNoErrors();
            $draft = $template->fresh()->draftVersion;
            $this->assertSame(2, $draft->version_number);
            $this->assertSame('Tujuan alternatif', $draft->variables->sole()->default_text);
            $this->assertNull($draft->test_path);
            $this->post(route('lt.versions.archive', [$template->uuid, $draft->uuid]), ['version_token' => app(LetterTemplateVersionState::class)->token($draft), 'confirmed' => true])->assertSessionHasNoErrors();
            $this->assertSame(LetterTemplateVersionStatusEnum::ARCHIVED, $draft->fresh()->status);
            $this->assertSame($sourceHash, hash_file('sha256', $disk->path($version->source_path)));
            $this->assertSame($sourceHash, hash_file('sha256', $disk->path($draft->source_path)));
            $this->assertSame($version->id, $template->fresh()->active_version_id);
            $this->assertSame(['draft.created', 'configuration.updated', 'configuration.updated', 'source.uploaded', 'source.validated', 'test.generated', 'test.confirmed', 'version.published', 'draft.copied', 'draft.archived'], $template->audits()->orderBy('id')->pluck('operation')->all());
        } finally {
            unlink($source);
            if (is_file($guidePath)) {
                unlink($guidePath);
            }
        }
    }

    private function publication(LetterTemplateVersion $version): array
    {
        $version->refresh();

        return ['version_token' => app(LetterTemplateVersionState::class)->token($version), 'test_token' => app(LetterTemplateTestEvidence::class)->token($version), 'confirmed' => true];
    }
}
