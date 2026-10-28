<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Enums\LetterStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Jobs\ConvertLetterToPdf;
use App\Jobs\GenerateLetterPreview;
use App\Jobs\IssueLetter;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterAudit;
use App\Models\LetterOperation;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterOperationEvents;
use App\Services\Letter\LetterPdfState;
use App\Services\Letter\LetterPreviewState;
use App\Services\Letter\RunLetterIssuance;
use App\Services\Letter\RunLetterPdf;
use App\Services\Letter\RunLetterPreview;
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

class LetterPdfTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pdf_is_on_demand_deduplicated_for_two_actors_and_notifies_both_without_allocating_a_number(): void
    {
        [$letter, $word] = $this->draft();
        $original = Storage::disk('local')->get($word->path);
        $this->assertSame(0, $letter->operations()->where('kind', 'pdf')->count());
        $firstActor = auth()->user();
        $key = (string) Str::uuid();
        $uuid = $this->requestPdf($letter, $word, $key);
        $this->assertSame($uuid, $this->requestPdf($letter, $word, $key));
        $this->assertSame($uuid, $this->requestPdf($letter, $word));
        $second = UserFactory::new()->create();
        $this->actingAs($second);
        $this->assertSame($uuid, $this->requestPdf($letter, $word));
        $this->assertSame(1, $letter->operations()->where('kind', 'pdf')->count());
        $this->assertDatabaseCount('notifications', 0);
        Bus::assertDispatched(ConvertLetterToPdf::class, fn($job) => $job->operationUuid === $uuid && $job->connection === 'letters' && $job->timeout === 90);
        $this->fakeConversion();
        app(RunLetterPdf::class)->run($uuid);
        $pdf = $word->letter->artifacts()->where('kind', 'pdf')->sole();
        $this->assertSame($word->id, $pdf->source_artifact_id);
        $this->assertSame($word->expires_at->toIso8601String(), $pdf->expires_at->toIso8601String());
        $this->assertSame('Liberation Sans', $pdf->font_usage['substitutions']['Missing PDF Font']);
        $this->assertSame($original, Storage::disk('local')->get($word->path));
        foreach ([$firstActor, $second] as $actor) {
            $notification = $actor->notifications()->sole();
            $this->assertSame($uuid, $notification->data['operation_id']);
            $this->assertSame('succeeded', $notification->data['status']);
            $this->assertSame('pdf', $notification->data['kind']);
            $this->assertNull($notification->read_at);
            $this->actingAs($actor)->getJson(route('ltr.operations.show', [$letter->uuid, $uuid]))->assertOk()->assertJsonPath('operation.status', 'succeeded');
            $snapshot = app(LetterOperationEvents::class)->snapshot($actor->uuid, $uuid);
            $this->assertSame($word->uuid, $snapshot[0]['source_id']);
            $this->assertSame('succeeded', $snapshot[0]['status']);
        }
        $lateActor = UserFactory::new()->create();
        $this->actingAs($lateActor)->getJson(route('ltr.operations.show', [$letter->uuid, $uuid]))->assertNotFound();
        $this->assertSame(0, $lateActor->notifications()->count());
        $lateKey = (string) Str::uuid();
        $this->assertSame($uuid, $this->requestPdf($letter, $word, $lateKey));
        $lateNotification = $lateActor->notifications()->sole();
        $this->assertSame($uuid, $lateNotification->data['operation_id']);
        $this->assertSame('succeeded', $lateNotification->data['status']);
        $this->assertNull($lateNotification->read_at);
        $lateNotification->markAsRead();
        $readAt = $lateNotification->fresh()->read_at->toIso8601String();
        $this->assertSame($uuid, $this->requestPdf($letter, $word, $lateKey));
        $this->assertSame($uuid, $this->requestPdf($letter, $word));
        app(RunLetterPdf::class)->run($uuid);
        $this->assertSame($lateNotification->id, $lateActor->notifications()->sole()->id);
        $this->assertSame($readAt, $lateNotification->fresh()->read_at->toIso8601String());
        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame(1, $firstActor->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());
        $this->assertSame(1, $letter->artifacts()->where('kind', 'pdf')->count());
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.staging')));
        Process::assertRan(fn($process) => is_array($process->command) && in_array('--headless', $process->command, true) && str_contains(implode(' ', $process->command), 'TiledWatermark') && isset($process->environment['FONTCONFIG_FILE']));
    }

    public function test_pdf_confirmation_is_actor_bound_and_can_authorize_issuance_without_docx_viewer_confirmation(): void
    {
        [$letter, $word] = $this->draft();
        $uuid = $this->requestPdf($letter, $word);
        $this->fakeConversion();
        app(RunLetterPdf::class)->run($uuid);
        $pdf = $letter->artifacts()->where('kind', 'pdf')->sole();
        $data = $this->checkData($pdf);
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $pdf->uuid]), $data)->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $pdf->uuid]), [...$data, 'preview_kind' => 'docx'])->assertUnprocessable()->assertJsonValidationErrors('preview_kind');
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $pdf->uuid]), $data)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $pdf->uuid]), [...$data, 'confirmed' => false])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $actor = auth()->user();
        $this->actingAs(UserFactory::new()->create())->postJson(route('ltr.previews.confirm', [$letter->uuid, $pdf->uuid]), $data)->assertUnprocessable();
        $this->actingAs($actor)->postJson(route('ltr.previews.confirm', [$letter->uuid, $pdf->uuid]), $data)->assertOk();
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('pdf.artifact.confirmed', true)->where('preview.artifact.confirmed', false));
        $this->issue($letter, $pdf);
        $this->assertSame(LetterStatusEnum::PUBLISHED, $letter->fresh()->status);
        $issuedNotification = $actor->notifications()->get()->sole(fn($entry): bool => $entry->data['kind'] === 'issue');
        $this->assertSame('succeeded', $issuedNotification->data['status']);
        $this->assertSame($letter->fresh()->currentVersion->operation->uuid, $issuedNotification->data['operation_id']);
        $this->assertNull($issuedNotification->read_at);
        $this->assertSame('pdf', $letter->fresh()->currentVersion->inspection['kind']);
        $this->assertSame($pdf->uuid, $letter->fresh()->currentVersion->inspection['artifact']);
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(1, $letter->artifacts()->where('kind', 'pdf')->count());
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('pdf.artifact', null)->where('pdf.source_id', $letter->fresh()->currentVersion->wordArtifact->uuid));
    }

    public function test_official_pdf_is_frozen_across_font_and_converter_changes_and_word_bytes_remain_identical(): void
    {
        [$letter, $word] = $this->draft();
        $this->issue($letter, $word);
        $letter = $letter->fresh();
        $word = $letter->currentVersion->wordArtifact;
        $bytes = Storage::disk('local')->get($word->path);
        $uuid = $this->requestPdf($letter, $word);
        $this->fakeConversion();
        app(RunLetterPdf::class)->run($uuid);
        $pdf = $letter->artifacts()->where('source_artifact_id', $word->id)->where('kind', 'pdf')->sole();
        $this->assertNull($pdf->expires_at);
        $this->assertSame($word->letter_version_id, $pdf->letter_version_id);
        $this->assertSame($bytes, Storage::disk('local')->get($word->path));
        config(['document-fonts.executables.version' => '/not-installed', 'document-fonts.executables.list' => '/not-installed', 'letters.pdf.profile_revision' => 'new']);
        $this->assertSame($uuid, $this->requestPdf($letter, $word));
        $this->get(route('ltr.pdfs.file', [$letter->uuid, $pdf->uuid]))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('ltr.pdfs.download', [$letter->uuid, $pdf->uuid]))->assertOk()->assertDownload('surat-' . $letter->uuid . '-v1.pdf');
        $this->get(route('ltr.versions.download', [$letter->uuid, $letter->currentVersion->uuid]))->assertOk();
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(1, $letter->currentVersion->assignment->period->last_sequence_number);
        Storage::disk('local')->put($pdf->path, 'corrupt');
        $this->getJson(route('ltr.pdfs.file', [$letter->uuid, $pdf->uuid]))->assertUnprocessable()->assertJsonValidationErrors('pdf');
    }

    public function test_conversion_retries_then_allows_a_fresh_manual_request_without_confirming_the_word_source(): void
    {
        [$letter, $word] = $this->draft();
        $key = (string) Str::uuid();
        $uuid = $this->requestPdf($letter, $word, $key);
        Process::fake(['*--convert-to*' => Process::result(exitCode: 1, errorOutput: '/private/path')]);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                app(RunLetterPdf::class)->run($uuid);
                $this->fail('Expected converter failure.');
            } catch (RuntimeException) {
            }
        }
        $operation = LetterOperation::query()->where('uuid', $uuid)->sole();
        $this->assertSame(Status::FAILED, $operation->status);
        $failureNotification = auth()->user()->notifications()->sole();
        $this->assertSame($uuid, $failureNotification->data['operation_id']);
        $this->assertSame('failed', $failureNotification->data['status']);
        $this->assertStringNotContainsString('/private', json_encode($failureNotification->data, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('/private', $operation->message);
        $this->assertSame($uuid, $this->requestPdf($letter, $word, $key));
        $retry = $this->requestPdf($letter, $word);
        $this->assertNotSame($uuid, $retry);
        $this->fakeConversion();
        app(RunLetterPdf::class)->run($retry);
        $this->assertSame(Status::SUCCEEDED, LetterOperation::query()->where('uuid', $retry)->sole()->status);
        $this->assertSame(0, $word->checks()->count());
        $this->assertDatabaseCount('letter_number_assignments', 0);
    }

    #[DataProvider('staleChanges')]
    public function test_a_changed_draft_or_profile_during_conversion_discards_output_without_consuming_numbers(string $change): void
    {
        [$letter, $word] = $this->draft();
        $uuid = $this->requestPdf($letter, $word);
        $this->fakeConversion(function () use ($letter, $word, $change): void {
            match ($change) {
                'revision' => $letter->draft->update(['revision' => 2]),
                'template' => $letter->template->update(['is_active' => false]),
                'profile' => config(['letters.pdf.profile_revision' => 'changed']),
                'word' => Storage::disk('local')->put($word->path, 'changed'),
                'lease' => LetterOperation::query()->where('kind', 'pdf')->update(['lease_token' => (string) Str::uuid()]),
            };
        });
        app(RunLetterPdf::class)->run($uuid);
        $operation = LetterOperation::query()->where('uuid', $uuid)->sole();
        $this->assertSame($change === 'lease' ? Status::RUNNING : Status::STALE, $operation->status);
        $this->assertSame(0, $letter->artifacts()->where('kind', 'pdf')->count());
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.pdfs')));
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.staging')));
        $this->assertDatabaseCount('letter_number_assignments', 0);
    }

    public static function staleChanges(): array
    {
        return ['revision changed' => ['revision'], 'template inactive' => ['template'], 'converter profile changed' => ['profile'], 'word bytes changed' => ['word'], 'lost lease' => ['lease']];
    }

    public function test_audit_failure_rolls_back_pdf_and_recovery_dispatches_the_pdf_job(): void
    {
        [$letter, $word] = $this->draft();
        $uuid = $this->requestPdf($letter, $word);
        $this->fakeConversion();
        LetterAudit::creating(function ($audit): void {
            if ($audit->event === 'pdf.generated') {
                throw new RuntimeException('audit unavailable');
            }
        });
        try {
            try {
                app(RunLetterPdf::class)->run($uuid);
                $this->fail('Expected audit failure.');
            } catch (RuntimeException) {
            }
        } finally {
            LetterAudit::flushEventListeners();
        }
        $this->assertSame(0, $letter->artifacts()->where('kind', 'pdf')->count());
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.pdfs')));
        $operation = LetterOperation::query()->where('uuid', $uuid)->sole();
        $operation->update(['updated_at' => now()->subMinutes(3)]);
        $this->artisan('letters:recover-operations')->assertSuccessful();
        Bus::assertDispatched(ConvertLetterToPdf::class, fn($job) => $job->operationUuid === $uuid);
        app(RunLetterPdf::class)->run($uuid);
        $this->assertSame(Status::SUCCEEDED, $operation->fresh()->status);
    }

    public function test_pdf_routes_require_login_uuid_parent_scope_and_word_source_and_reject_reused_keys(): void
    {
        [$letter, $word] = $this->draft();
        $other = Letter::factory()->create();
        $this->postJson(route('ltr.pdfs.store', [$other->uuid, $word->uuid]), ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->postJson(route('ltr.pdfs.store', [$letter->uuid, $word->uuid]), [])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->postJson(route('ltr.pdfs.store', [$letter->uuid, $word->uuid]), ['idempotency_key' => 'invalid'])->assertUnprocessable();
        $this->postJson(route('ltr.pdfs.store', [$letter->uuid, $word->id]), ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $preview = $word->operation;
        $this->postJson(route('ltr.pdfs.store', [$letter->uuid, $word->uuid]), ['idempotency_key' => $preview->idempotency_key])->assertUnprocessable();
        $uuid = $this->requestPdf($letter, $word);
        $this->fakeConversion();
        app(RunLetterPdf::class)->run($uuid);
        $pdf = $letter->artifacts()->where('kind', 'pdf')->sole();
        $this->postJson(route('ltr.pdfs.store', [$letter->uuid, $pdf->uuid]), ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->get(route('ltr.pdfs.file', [$other->uuid, $pdf->uuid]))->assertNotFound();
        $this->get(route('ltr.pdfs.download', [$letter->uuid, $pdf->uuid]))->assertNotFound();
        auth()->logout();
        $this->post(route('ltr.pdfs.store', [$letter->uuid, $word->uuid]), [])->assertRedirectToRoute('login.index');
        $this->get(route('ltr.pdfs.file', [$letter->uuid, $pdf->uuid]))->assertRedirectToRoute('login.index');
    }

    public function test_a_new_draft_pdf_profile_requires_new_examination_and_has_no_docx_check(): void
    {
        [$letter, $word] = $this->draft();
        $first = $this->requestPdf($letter, $word);
        $this->fakeConversion();
        app(RunLetterPdf::class)->run($first);
        $old = $letter->artifacts()->where('kind', 'pdf')->sole();
        $this->confirm($letter, $old);
        config(['letters.pdf.profile_revision' => 'new-profile']);
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $old->uuid]), $this->checkData($old))->assertUnprocessable();
        $second = $this->requestPdf($letter, $word);
        $this->assertNotSame($first, $second);
        app(RunLetterPdf::class)->run($second);
        $current = $letter->artifacts()->where('kind', 'pdf')->latest('id')->firstOrFail();
        $this->assertNotSame($old->uuid, $current->uuid);
        $this->assertNotSame($old->font_fingerprint, $current->font_fingerprint);
        $this->assertSame(0, $current->checks()->count());
        $this->assertSame(0, $word->checks()->count());
        $this->assertSame($second, $this->requestPdf($letter, $word));
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('pdf.artifact.id', $current->uuid)->where('pdf.artifact.confirmed', false)->where('preview.artifact.confirmed', false));
        $this->assertDatabaseCount('letter_number_assignments', 0);
    }

    public function test_subscriber_request_keys_cannot_be_reused_for_another_source_preview_or_issuance(): void
    {
        [$letter, $word] = $this->draft();
        $uuid = $this->requestPdf($letter, $word);
        $this->actingAs(UserFactory::new()->create());
        $key = (string) Str::uuid();
        $this->assertSame($uuid, $this->requestPdf($letter, $word, $key));
        $this->postJson(route('ltr.previews.store', $letter->uuid), ['revision' => 1, 'idempotency_key' => $key])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->postJson(route('ltr.issue', $letter->uuid), ['revision' => 1, 'artifact' => $word->uuid, 'fingerprint' => $this->checkData($word)['fingerprint'], 'publication_month' => '2026-10', 'keep_period' => true, 'confirmed' => true, 'idempotency_key' => $key])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $different = LetterArtifact::factory()->create();
        $this->postJson(route('ltr.pdfs.store', [$different->letter->uuid, $different->uuid]), ['idempotency_key' => $key])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertSame(0, $letter->operations()->where('kind', 'issue')->count());
    }

    public function test_invalid_pdf_output_is_rejected_and_does_not_consume_a_number(): void
    {
        [$letter, $word] = $this->draft();
        $uuid = $this->requestPdf($letter, $word);
        $this->fakeConversion(contents: str_repeat('not a pdf', 10));
        try {
            app(RunLetterPdf::class)->run($uuid);
            $this->fail('Expected invalid PDF error.');
        } catch (RuntimeException) {
        }
        $this->assertSame(0, $letter->artifacts()->where('kind', 'pdf')->count());
        $this->assertDatabaseCount('letter_number_assignments', 0);
    }

    public function test_real_libreoffice_conversion_embeds_fallback_fonts_preserves_word_and_marks_only_draft_pdf(): void
    {
        [$letter, $word] = $this->draft();
        $original = Storage::disk('local')->get($word->path);
        $uuid = $this->requestPdf($letter, $word);
        app(RunLetterPdf::class)->run($uuid);
        $pdf = $letter->artifacts()->where('kind', 'pdf')->sole();
        $this->assertSame(Status::SUCCEEDED, $pdf->operation->status);
        $text = Process::run(['/usr/bin/pdftotext', '-raw', Storage::disk('local')->path($pdf->path), '-']);
        $this->assertTrue($text->successful(), $text->errorOutput());
        $this->assertStringContainsString('XX.XX', $text->output());
        $this->assertStringContainsString('DRAFT', preg_replace('/\s+/', '', $text->output()));
        $this->assertStringContainsString('Halaman kedua', $text->output());
        $fonts = Process::run(['/usr/bin/pdffonts', Storage::disk('local')->path($pdf->path)]);
        $this->assertStringContainsString('LiberationSans', $fonts->output());
        $this->assertSame($original, Storage::disk('local')->get($word->path));
        $this->issue($letter, $word);
        $official = $letter->fresh()->currentVersion->wordArtifact;
        $uuid = $this->requestPdf($letter->fresh(), $official);
        app(RunLetterPdf::class)->run($uuid);
        $pdf = $letter->artifacts()->where('letter_version_id', $official->letter_version_id)->where('kind', 'pdf')->sole();
        $text = Process::run(['/usr/bin/pdftotext', '-raw', Storage::disk('local')->path($pdf->path), '-']);
        $this->assertStringNotContainsString('DRAFT', $text->output());
        $this->assertStringContainsString('445/01.01/PKM-KMJ/X/2026', $text->output());
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.staging')));
    }

    /** @return array{Letter, LetterArtifact} */
    private function draft(): array
    {
        Storage::fake('local');
        Bus::fake([GenerateLetterPreview::class, IssueLetter::class, ConvertLetterToPdf::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00'));
        config(['document-fonts.fallbacks' => ['Liberation Sans']]);
        $this->actingAs(UserFactory::new()->create());
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nomor_surat', 'tanggal_surat']]]);
        $version->letterNumber->update(['classification_code' => '445', 'page_number' => null, 'sequence_number' => null]);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);
        $disk = Storage::disk('local');
        $disk->makeDirectory('tests');
        $doc = new PhpWord();
        $doc->setDefaultFontName('Missing PDF Font');
        $section = $doc->addSection();
        $section->addText('Surat ${nomor_surat} ${tanggal_surat}');
        $section->addPageBreak();
        $section->addText('Halaman kedua');
        IOFactory::createWriter($doc, 'Word2007')->save($disk->path('tests/source.docx'));
        $version->update(['source_disk' => 'local', 'source_path' => 'tests/source.docx', 'source_sha256' => hash_file('sha256', $disk->path('tests/source.docx'))]);
        $this->post(route('ltr.store'), ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat PDF', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => [], 'signers' => []])->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $uuid = $this->postJson(route('ltr.previews.store', $letter->uuid), ['revision' => 1, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
        $this->fakeRenderer();
        app(RunLetterPreview::class)->run($uuid);

        return [$letter->fresh(), LetterArtifact::sole()];
    }

    private function requestPdf(Letter $letter, LetterArtifact $word, ?string $key = null): string
    {
        return $this->postJson(route('ltr.pdfs.store', [$letter->uuid, $word->uuid]), ['idempotency_key' => $key ?? (string) Str::uuid()])->assertAccepted()->json('operation.id');
    }

    private function checkData(LetterArtifact $artifact): array
    {
        return ['revision' => $artifact->draft_revision, 'fingerprint' => $artifact->kind === 'pdf' ? app(LetterPdfState::class)->evidence($artifact) : app(LetterPreviewState::class)->evidence($artifact), 'preview_kind' => $artifact->kind, 'confirmed' => true];
    }

    private function confirm(Letter $letter, LetterArtifact $artifact): void
    {
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $this->checkData($artifact))->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $this->checkData($artifact))->assertOk();
    }

    private function issue(Letter $letter, LetterArtifact $artifact): void
    {
        if ($artifact->kind === 'docx') {
            $pdf = $letter->artifacts()->where('source_artifact_id', $artifact->id)->where('kind', 'pdf')->latest('id')->first();
            if ($pdf === null) {
                $uuid = $this->requestPdf($letter, $artifact);
                $this->fakeConversion();
                app(RunLetterPdf::class)->run($uuid);
                $pdf = $letter->artifacts()->where('source_artifact_id', $artifact->id)->where('kind', 'pdf')->latest('id')->firstOrFail();
            }
            $artifact = $pdf;
            $this->confirm($letter, $artifact);
        }
        $uuid = $this->postJson(route('ltr.issue', $letter->uuid), ['revision' => 1, 'artifact' => $artifact->uuid, 'fingerprint' => $this->checkData($artifact)['fingerprint'], 'publication_month' => '2026-10', 'keep_period' => true, 'confirmed' => true, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
        $this->fakeRenderer();
        app(RunLetterIssuance::class)->run($uuid);
    }

    private function fakeRenderer(): void
    {
        Process::fake(['*letters:render-preview*' => fn($process) => Process::result(exitCode: Artisan::call('letters:render-preview', ['directory' => $process->command[3]]))]);
    }

    private function fakeConversion(?callable $after = null, string $contents = "%PDF-1.7\n1 0 obj <</Type /Catalog>> endobj\n%%EOF\n"): void
    {
        Process::fake(['*--convert-to*' => function ($process) use ($after, $contents) {
            $directory = $process->command[array_search('--outdir', $process->command, true) + 1];
            file_put_contents($directory . '/document.pdf', $contents);
            $after?->__invoke();

            return Process::result();
        }]);
    }
}
