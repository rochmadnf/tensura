<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Enums\LetterStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Jobs\GenerateLetterPreview;
use App\Jobs\IssueLetter;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterAudit;
use App\Models\LetterNumberAssignment;
use App\Models\LetterNumberPeriod;
use App\Models\LetterOperation;
use App\Models\LetterVersion;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterPreviewState;
use App\Services\Letter\RunLetterIssuance;
use App\Services\Letter\RunLetterPreview;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LetterIssuanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_issues_atomically_using_only_the_checked_values_and_downloads_the_same_official_word(): void
    {
        $letter = $this->draft();
        $data = $this->inspected($letter);
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $letter->draft->templateVersion->letter_number_id, 'last_page_number' => 23, 'last_sequence_number' => 44]);
        $uuid = $this->requestIssue($letter, $data);
        $this->assertSame(LetterStatusEnum::DRAFT, $letter->fresh()->status);
        $this->assertSame(44, $period->fresh()->last_sequence_number);
        Bus::assertDispatched(IssueLetter::class, fn($job) => $job->operationUuid === $uuid && $job->connection === 'letters');
        $this->renderFake();
        app(RunLetterIssuance::class)->run($uuid);
        $letter->refresh();
        $this->assertSame(LetterStatusEnum::PUBLISHED, $letter->status);
        $this->assertSame('445/23.45/PKM-KMJ/X/2026', $letter->official_number);
        $this->assertSame(45, $period->fresh()->last_sequence_number);
        $version = LetterVersion::sole();
        $artifact = $version->wordArtifact;
        $this->assertNull($artifact->expires_at);
        $this->assertNull($artifact->letter_draft_id);
        $this->assertSame($version->id, $artifact->letter_version_id);
        $this->assertNotNull($letter->draft->templateVersion->fresh()->first_used_at);
        $parts = app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path($artifact->path));
        foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
            $this->assertStringContainsString($letter->official_number, $parts[$part]->textContent);
            $this->assertStringNotContainsString('XX.XX', $parts[$part]->textContent);
            $this->assertStringNotContainsString('B45309', $parts[$part]->saveXML());
        }
        $this->assertStringContainsString('Nilai diperiksa', $parts['word/document.xml']->textContent);
        $this->get(route('ltr.versions.download', [$letter->uuid, $version->uuid]))->assertOk()->assertDownload('surat-' . $letter->uuid . '-v1.docx');
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('resource.data.official.number', $letter->official_number)->missing('resource.data.draft')->missing('resource.data.official.path'));
        app(RunLetterIssuance::class)->run($uuid);
        $this->requestIssue($letter, $data);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(45, $period->fresh()->last_sequence_number);
        $hash = $artifact->sha256;
        $this->travel(2)->days();
        config(['document-fonts.preview_renderer' => 'new-profile']);
        $this->get(route('ltr.versions.download', [$letter->uuid, $version->uuid]))->assertOk();
        $this->assertSame($hash, hash_file('sha256', Storage::disk('local')->path($artifact->path)));
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.staging')));
    }

    public function test_publishing_requires_the_actors_current_confirmed_inspection_and_explicit_period_choice(): void
    {
        $letter = $this->draft();
        $data = $this->inspected($letter);
        $this->postJson(route('ltr.issue', $letter->uuid), [...$data, 'keep_period' => false])->assertUnprocessable()->assertJsonValidationErrors('keep_period');
        $this->postJson(route('ltr.issue', $letter->uuid), [...$data, 'confirmed' => false])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson(route('ltr.issue', $letter->uuid), [...$data, 'fingerprint' => str_repeat('a', 64)])->assertUnprocessable();
        $this->postJson(route('ltr.issue', $letter->uuid), [...$data, 'publication_month' => '2026-09'])->assertUnprocessable();
        $this->actingAs(UserFactory::new()->create())->postJson(route('ltr.issue', $letter->uuid), $data)->assertUnprocessable();
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertDatabaseCount('letter_number_assignments', 0);
    }

    #[DataProvider('stalePublicationStates')]
    public function test_changed_state_during_processing_cannot_consume_a_number(string $change): void
    {
        $letter = $this->draft();
        $data = $this->inspected($letter);
        $uuid = $this->requestIssue($letter, $data);
        $this->renderFake(function () use ($letter): void { $letter->draft->update(['revision' => 2]); });
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(LetterStatusEnum::DRAFT, $letter->fresh()->status);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $this->assertDatabaseCount('letter_number_periods', 0);
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertSame(Status::STALE, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.official')));
    }

    public static function stalePublicationStates(): array
    {
        return [['draft'], ['template'], ['renderer'], ['source'], ['month']];
    }

    public function test_retry_after_render_failure_keeps_the_operation_and_uses_no_number_until_commit(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        Process::fake(['*letters:render-preview*' => Process::result(exitCode: 1)]);
        try {
            app(RunLetterIssuance::class)->run($uuid);
            $this->fail('Expected render failure.');
        } catch (RuntimeException) {
        }
        $this->assertSame(Status::QUEUED, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $this->assertDatabaseCount('letter_number_periods', 0);
        $this->renderFake();
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(LetterStatusEnum::PUBLISHED, $letter->fresh()->status);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertSame(2, LetterOperation::where('uuid', $uuid)->sole()->attempts);
    }

    public function test_audit_failure_rolls_back_the_claim_counter_and_official_file(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        $this->renderFake();
        LetterAudit::creating(function ($audit): void {
            if ($audit->event === 'letter.issued') {
                throw new RuntimeException('audit unavailable');
            }
        });
        try {
            try {
                app(RunLetterIssuance::class)->run($uuid);
                $this->fail('Expected audit failure.');
            } catch (RuntimeException) {
            }
            $this->assertDatabaseCount('letter_versions', 0);
            $this->assertDatabaseCount('letter_number_assignments', 0);
            $this->assertDatabaseCount('letter_number_periods', 0);
            $this->assertSame(LetterStatusEnum::DRAFT, $letter->fresh()->status);
            $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.official')));
        } finally {
            LetterAudit::flushEventListeners();
        }
    }

    public function test_auto_rerenders_a_changed_candidate_and_commits_only_the_latest_number(): void
    {
        $letter = $this->draft();
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $letter->draft->templateVersion->letter_number_id]);
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        $renders = 0;
        $this->renderFake(function () use ($period, &$renders): void {
            $renders++;
            if ($renders === 1) {
                $period->update(['last_page_number' => 1, 'last_sequence_number' => 1]);
            }
        });
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(2, $renders);
        $this->assertSame('445/01.02/PKM-KMJ/X/2026', $letter->fresh()->official_number);
        $text = app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path($letter->fresh()->currentVersion->wordArtifact->path))['word/document.xml']->textContent;
        $this->assertStringContainsString('445/01.02/PKM-KMJ/X/2026', $text);
        $this->assertDatabaseCount('letter_versions', 1);
    }

    public function test_manual_collision_at_commit_has_a_suggestion_and_does_not_change_the_auto_counter(): void
    {
        $letter = $this->draft();
        $letter->draft->update(['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 20]);
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $letter->draft->templateVersion->letter_number_id, 'last_page_number' => 1, 'last_sequence_number' => 9]);
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        $this->renderFake(fn() => LetterNumberAssignment::factory()->create(['letter_number_period_id' => $period->id, 'page_number' => 1, 'sequence_number' => 20, 'mode' => 2]));
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(LetterStatusEnum::DRAFT, $letter->fresh()->status);
        $this->assertSame(9, $period->fresh()->last_sequence_number);
        $this->assertStringContainsString('445/01.21', LetterOperation::where('uuid', $uuid)->sole()->message);
        $this->assertDatabaseCount('letter_versions', 0);
    }

    public function test_old_period_needs_fresh_month_acknowledgment_and_revision_stays_checked(): void
    {
        $letter = $this->draft();
        $data = $this->inspected($letter);
        $uuid = $this->requestIssue($letter, $data);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00'));
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(Status::STALE, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('issuance.crossed_month', true)->where('issuance.publication_month', '2026-11'));
    }

    public function test_issued_letters_are_protected_and_downloads_require_the_correct_parent_and_intact_file(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        $this->renderFake();
        app(RunLetterIssuance::class)->run($uuid);
        $version = LetterVersion::sole();
        $this->delete(route('ltr.destroy', $letter->uuid), ['revision' => 1, 'confirmed' => true])->assertSessionHasErrors();
        $other = Letter::factory()->create();
        $this->get(route('ltr.versions.download', [$other->uuid, $version->uuid]))->assertNotFound();
        $this->get(route('ltr.versions.file', [$letter->uuid, $version->uuid]))->assertOk();
        Storage::disk('local')->put($version->wordArtifact->path, 'modified');
        $this->get(route('ltr.versions.download', [$letter->uuid, $version->uuid]))->assertGone();
        auth()->logout();
        $this->post(route('ltr.issue', $letter->uuid), [])->assertRedirectToRoute('login.index');
        $this->get(route('ltr.versions.download', [$letter->uuid, $version->uuid]))->assertRedirectToRoute('login.index');
    }

    public function test_shared_classification_across_templates_and_manual_number_do_not_split_or_shift_auto(): void
    {
        $first = $this->draft();
        $uuid = $this->requestIssue($first, $this->inspected($first));
        $this->renderFake();
        app(RunLetterIssuance::class)->run($uuid);
        $source = $first->draft->templateVersion;
        foreach ([[2, 20], [1, null]] as [$mode, $sequence]) {
            $version = LetterTemplateVersion::factory()->create(['letter_number_id' => $source->letter_number_id, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => $source->validation_result, 'source_disk' => 'local', 'source_path' => $source->source_path, 'source_sha256' => $source->source_sha256]);
            $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);
            $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama']);
            $this->post(route('ltr.store'), ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Templat lain', 'letter_date' => '2026-10-26', 'numbering_mode' => $mode, 'number_year' => 2026, 'number_month' => 10, 'page_number' => 1, 'sequence_number' => $sequence, 'values' => [$variable->uuid => 'Nilai lain'], 'signers' => []])->assertSessionHasNoErrors();
            $letter = Letter::latest('id')->first();
            $uuid = $this->requestIssue($letter, $this->inspected($letter));
            $this->renderFake();
            app(RunLetterIssuance::class)->run($uuid);
            $this->assertSame($mode === 2 ? '445/01.20/PKM-KMJ/X/2026' : '445/01.02/PKM-KMJ/X/2026', $letter->fresh()->official_number);
        }
        $this->assertDatabaseCount('letter_number_periods', 1);
        $this->assertDatabaseCount('letter_number_assignments', 3);
        $this->assertSame(2, LetterNumberPeriod::sole()->last_sequence_number);
        $period = LetterNumberPeriod::sole();
        $this->get(route('ln.periods.usage', [$source->letterNumber->uuid, $period->uuid]))->assertInertia(fn(AssertableInertia $page) => $page->has('resources.data', 3)->where('resources.meta.total', 3)->missing('resources.data.0.path'));
        $this->get(route('ltr.index', ['filter' => 'status:2|year:2026|month:10']))->assertInertia(fn(AssertableInertia $page) => $page->where('resources.meta.total', 3));
    }

    public function test_conflicts_are_bounded_to_three_renders_and_recovery_dispatches_the_issuance_job(): void
    {
        $letter = $this->draft();
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $letter->draft->templateVersion->letter_number_id, 'last_page_number' => 1, 'last_sequence_number' => 1]);
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        $operation = LetterOperation::where('uuid', $uuid)->sole();
        $operation->update(['updated_at' => now()->subMinutes(3)]);
        $this->artisan('letters:recover-operations')->assertSuccessful();
        Bus::assertDispatched(IssueLetter::class, fn($job) => $job->operationUuid === $uuid);
        $renders = 0;
        $this->renderFake(function () use ($period, &$renders): void {
            $renders++;
            $period->increment('last_sequence_number');
        });
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(3, $renders);
        $this->assertSame(Status::FAILED, $operation->fresh()->status);
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.official')));
    }

    public function test_a_second_operator_cannot_duplicate_a_pending_or_committed_issuance(): void
    {
        $letter = $this->draft();
        $data = $this->inspected($letter);
        $uuid = $this->requestIssue($letter, $data);
        $this->postJson(route('ltr.issue', $letter->uuid), [...$data, 'revision' => 2])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->actingAs(UserFactory::new()->create());
        $check = ['revision' => 1, 'fingerprint' => $data['fingerprint'], 'preview_kind' => 'docx', 'confirmed' => true];
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $data['artifact']]), $check)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $data['artifact']]), $check)->assertOk();
        $this->postJson(route('ltr.issue', $letter->uuid), $data)->assertUnprocessable()->assertJsonValidationErrors('issuance');
        $this->renderFake();
        app(RunLetterIssuance::class)->run($uuid);
        $this->requestIssue($letter->fresh(), [...$data, 'idempotency_key' => (string) Str::uuid()]);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(1, LetterNumberPeriod::sole()->last_sequence_number);
    }

    public function test_a_used_classification_code_is_protected_while_its_name_can_change(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestIssue($letter, $this->inspected($letter));
        $this->renderFake();
        app(RunLetterIssuance::class)->run($uuid);
        $classification = $letter->draft->templateVersion->letterNumber;
        $this->patch(route('ln.update', $classification->uuid), ['classification_name' => 'Nama baru', 'classification_code' => '446'])->assertSessionHasErrors('classification_code');
        $this->assertSame('445', $classification->fresh()->classification_code);
        $this->patch(route('ln.update', $classification->uuid), ['classification_name' => 'Nama baru', 'classification_code' => '445'])->assertSessionHasNoErrors();
        $this->assertSame('Nama baru', $classification->fresh()->classification_name);
        $this->get(route('ln.edit', $classification->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('resource.data.code_locked', true));
        $other = LetterNumberPeriod::factory()->create();
        $this->get(route('ln.periods.usage', [$classification->uuid, $other->uuid]))->assertNotFound();
    }

    public function test_real_official_render_subprocess_preserves_the_final_number_without_draft_styling(): void
    {
        $letter = $this->draft();
        $snapshot = $letter->draft->snapshot;
        $snapshot['values']['nomor_surat'] = '445/01.01/PKM-KMJ/X/2026';
        $directory = 'letters/staging/' . Str::uuid() . '/' . Str::uuid();
        $staging = Storage::build(['driver' => 'local', 'root' => storage_path('app/private')]);
        try {
            $staging->put($directory . '/input.json', json_encode(['source' => Storage::disk('local')->path('tests/source.docx'), 'source_sha256' => $letter->draft->templateVersion->source_sha256, 'snapshot' => $snapshot, 'draft_preview' => false], JSON_THROW_ON_ERROR));
            $result = Process::timeout(30)->run([PHP_BINARY, base_path('artisan'), 'letters:render-preview', $directory, '--no-interaction']);
            $this->assertTrue($result->successful(), $result->errorOutput() . $result->output());
            $parts = app(InspectLetterTemplateDocx::class)->read($staging->path($directory . '/preview.docx'));
            $this->assertStringContainsString('445/01.01/PKM-KMJ/X/2026', $parts['word/document.xml']->textContent);
            $this->assertStringNotContainsString('B45309', $parts['word/document.xml']->saveXML());
            $this->assertDatabaseCount('letter_number_assignments', 0);
        } finally {
            $staging->deleteDirectory($directory);
        }
    }

    private function draft(): Letter
    {
        Storage::fake('local');
        Bus::fake([GenerateLetterPreview::class, IssueLetter::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00'));
        $this->actingAs(UserFactory::new()->create());
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nama', 'nomor_surat', 'tanggal_surat']]]);
        $version->letterNumber->update(['classification_code' => '445', 'page_number' => null, 'sequence_number' => null]);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama']);
        $disk = Storage::disk('local');
        $disk->makeDirectory('tests');
        $word = new PhpWord();
        $section = $word->addSection();
        $section->addText('${nama} ${nomor_surat} ${tanggal_surat}');
        $section->addHeader()->addText('${nomor_surat}');
        $section->addFooter()->addText('${nomor_surat}');
        IOFactory::createWriter($word, 'Word2007')->save($disk->path('tests/source.docx'));
        $version->update(['source_disk' => 'local', 'source_path' => 'tests/source.docx', 'source_sha256' => hash_file('sha256', $disk->path('tests/source.docx'))]);
        $this->post(route('ltr.store'), ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat diterbitkan', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => [$variable->uuid => 'Nilai diperiksa'], 'signers' => []])->assertSessionHasNoErrors();

        return Letter::sole();
    }

    private function inspected(Letter $letter): array
    {
        $uuid = $this->postJson(route('ltr.previews.store', $letter->uuid), ['revision' => 1, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
        $this->renderFake();
        app(RunLetterPreview::class)->run($uuid);
        $artifact = LetterArtifact::where('letter_id', $letter->id)->sole();
        $fingerprint = app(LetterPreviewState::class)->evidence($artifact);
        $data = ['revision' => 1, 'fingerprint' => $fingerprint, 'preview_kind' => 'docx', 'confirmed' => true];
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $data)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $data)->assertOk();

        return ['revision' => 1, 'artifact' => $artifact->uuid, 'fingerprint' => $fingerprint, 'publication_month' => '2026-10', 'keep_period' => true, 'confirmed' => true, 'idempotency_key' => (string) Str::uuid()];
    }

    private function requestIssue(Letter $letter, array $data): string
    {
        return $this->postJson(route('ltr.issue', $letter->uuid), $data)->assertAccepted()->json('operation.id');
    }

    private function renderFake(?callable $after = null): void
    {
        Process::fake(['*letters:render-preview*' => function ($process) use ($after) {
            $code = Artisan::call('letters:render-preview', ['directory' => $process->command[3]]);
            if ($after !== null) {
                $after();
            }

            return Process::result(exitCode: $code);
        }]);
    }
}
