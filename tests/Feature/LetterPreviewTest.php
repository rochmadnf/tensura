<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Jobs\GenerateLetterPreview;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterAudit;
use App\Models\LetterOperation;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterPreviewState;
use App\Services\Letter\RenderLetterDocument;
use App\Services\Letter\RunLetterPreview;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
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

class LetterPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_preview_is_durable_deduplicated_and_rendered_without_allocating_numbers(): void
    {
        $letter = $this->draft();
        $key = (string) Str::uuid();
        $first = $this->postJson($this->url($letter), ['revision' => 1, 'idempotency_key' => $key])->assertAccepted()->json('operation.id');
        $this->postJson($this->url($letter), ['revision' => 1, 'idempotency_key' => $key])->assertAccepted()->assertJsonPath('operation.id', $first);
        $this->postJson($this->url($letter), ['revision' => 1, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->assertJsonPath('operation.id', $first);
        $this->assertDatabaseCount('letter_operations', 1);
        Bus::assertDispatched(GenerateLetterPreview::class, fn($job) => $job->operationUuid === $first && $job->connection === 'letters' && $job->queue === 'letters');
        $this->render($first);
        $operation = LetterOperation::sole();
        $this->assertSame(Status::SUCCEEDED, $operation->status);
        $artifact = LetterArtifact::sole();
        $source = Storage::disk('local')->get($letter->draft->templateVersion->source_path);
        $parts = app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path($artifact->path));
        foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
            $this->assertStringContainsString('/XX.XX/PKM-KMJ/X/2026', $parts[$part]->textContent);
            $this->assertStringContainsString('B45309', $parts[$part]->saveXML());
        }
        $this->assertSame($source, Storage::disk('local')->get($letter->draft->templateVersion->source_path));
        $this->assertDatabaseCount('letter_number_periods', 0);
        $this->assertNull($letter->fresh()->official_number);
        app(RunLetterPreview::class)->run($first);
        $this->assertDatabaseCount('letter_artifacts', 1);
        $this->get(route('ltr.previews.file', [$letter->uuid, $artifact->uuid]))->assertOk()->assertHeader('Content-Disposition', 'inline; filename="draft.docx"');
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.staging')));
    }

    public function test_confirmation_requires_completed_render_explicit_consent_and_same_actor(): void
    {
        $letter = $this->draft();
        $this->render($this->requestPreview($letter));
        $artifact = LetterArtifact::sole();
        $data = $this->checkData($artifact);
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $data)->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $data)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), [...$data, 'confirmed' => false])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $other = UserFactory::new()->create();
        $this->actingAs($other)->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $data)->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $data)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $data)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $data)->assertOk();
        $this->assertDatabaseHas('letter_preview_checks', ['actor_uuid' => $other->uuid, 'fingerprint' => $data['fingerprint']]);
        $this->assertSame(1, $letter->audits()->where('event', 'preview.confirmed')->count());
        $this->assertNotNull($artifact->checks()->where('actor_uuid', $other->uuid)->first()->confirmed_at);
    }

    public function test_editing_a_draft_invalidates_old_files_and_evidence_and_queued_work_becomes_stale(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $letter->draft->update(['revision' => 2]);
        $this->render($uuid);
        $this->assertSame(Status::STALE, LetterOperation::sole()->status);
        $this->assertDatabaseCount('letter_artifacts', 0);
        $uuid = $this->requestPreview($letter, 2);
        $this->render($uuid);
        $artifact = LetterArtifact::sole();
        $letter->draft->update(['revision' => 3]);
        $this->getJson(route('ltr.previews.file', [$letter->uuid, $artifact->uuid]))->assertUnprocessable();
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $this->checkData($artifact))->assertUnprocessable();
        $this->assertDatabaseCount('letter_preview_checks', 0);
    }

    public function test_template_changes_while_rendering_do_not_promote_a_stale_output(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $this->render($uuid, function () use ($letter): void { $letter->template->update(['is_active' => false]); });
        $this->assertSame(Status::STALE, LetterOperation::sole()->status);
        $this->assertDatabaseCount('letter_artifacts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.previews')));
    }

    public function test_infrastructure_retries_use_the_same_operation_and_commit_only_once(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        Process::fake(['*letters:render-preview*' => Process::result(exitCode: 1)]);
        try {
            app(RunLetterPreview::class)->run($uuid);
            $this->fail('Expected infrastructure failure.');
        } catch (RuntimeException) {
        }
        $this->assertSame(Status::QUEUED, LetterOperation::sole()->status);
        $this->assertDatabaseCount('letter_artifacts', 0);
        $this->render($uuid);
        $this->assertSame(Status::SUCCEEDED, LetterOperation::sole()->status);
        $this->assertSame(2, LetterOperation::sole()->attempts);
        $this->assertDatabaseCount('letter_artifacts', 1);
    }

    public function test_validation_failure_is_terminal_and_does_not_remove_draft_inputs(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        Process::fake(['*letters:render-preview*' => Process::result(exitCode: 2)]);
        app(RunLetterPreview::class)->run($uuid);
        $this->assertSame(Status::FAILED, LetterOperation::sole()->status);
        $this->assertSame(1, LetterOperation::sole()->attempts);
        $this->assertDatabaseCount('letter_drafts', 1);
        $this->assertDatabaseCount('letter_artifacts', 0);
        $this->assertStringNotContainsString('storage/', LetterOperation::sole()->message);
        Process::assertRan(fn($process) => is_array($process->command) && in_array('letters:render-preview', $process->command, true));
    }

    public function test_audit_failure_rolls_back_output_and_leaves_a_retryable_operation(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $this->renderFake();
        LetterAudit::creating(function ($audit): void {
            if ($audit->event === 'preview.generated') {
                throw new RuntimeException('audit unavailable');
            }
        });
        try {
            try {
                app(RunLetterPreview::class)->run($uuid);
                $this->fail('Expected audit failure.');
            } catch (RuntimeException) {
            }
            $this->assertDatabaseCount('letter_artifacts', 0);
            $this->assertSame(Status::QUEUED, LetterOperation::sole()->status);
            $this->assertSame([], Storage::disk('local')->allFiles(config('letters.paths.previews')));
        } finally {
            LetterAudit::flushEventListeners();
        }
    }

    public function test_expired_or_corrupted_artifact_and_changed_profile_cannot_be_confirmed(): void
    {
        $letter = $this->draft();
        $this->render($this->requestPreview($letter));
        $artifact = LetterArtifact::sole();
        $data = $this->checkData($artifact);
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $data)->assertOk();
        config(['document-fonts.preview_renderer' => 'changed']);
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $data)->assertUnprocessable();
        $this->assertNull($artifact->checks()->sole()->confirmed_at);
    }

    public function test_authentication_uuid_scoping_and_operation_actor_are_enforced(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $this->render($uuid);
        $artifact = LetterArtifact::sole();
        $other = Letter::factory()->create();
        $this->getJson(route('ltr.previews.file', [$other->uuid, $artifact->uuid]))->assertNotFound();
        $this->getJson(route('ltr.operations.show', [$other->uuid, $uuid]))->assertNotFound();
        $this->actingAs(UserFactory::new()->create())->getJson(route('ltr.operations.show', [$letter->uuid, $uuid]))->assertNotFound();
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->where('preview.operation', null)->where('preview.artifact.id', $artifact->uuid));
        $this->getJson(route('ltr.previews.file', [$letter->uuid, $artifact->uuid]))->assertOk();
        auth()->logout();
        $this->get(route('ltr.events'))->assertRedirectToRoute('login.index');
        $this->get(route('ltr.previews.file', [$letter->uuid, $artifact->uuid]))->assertRedirectToRoute('login.index');
        $this->post($this->url($letter), [])->assertRedirectToRoute('login.index');
    }

    public function test_changed_renderer_during_generate_rejects_the_output(): void
    {
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $this->render($uuid, fn() => config(['document-fonts.preview_renderer' => 'new-renderer']));
        $this->assertSame(Status::STALE, LetterOperation::sole()->status);
        $this->assertDatabaseCount('letter_artifacts', 0);
    }

    #[DataProvider('unusableArtifacts')]
    public function test_unusable_artifacts_are_refused_and_can_be_regenerated(string $kind): void
    {
        $this->freezeTime();
        $letter = $this->draft();
        $this->render($this->requestPreview($letter));
        $artifact = LetterArtifact::sole();
        match ($kind) {
            'expired' => $artifact->update(['expires_at' => now()]),
            'missing' => Storage::disk('local')->delete($artifact->path),
            'corrupted' => Storage::disk('local')->put($artifact->path, 'broken'),
        };
        $this->getJson(route('ltr.previews.file', [$letter->uuid, $artifact->uuid]))->assertUnprocessable();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $this->checkData($artifact))->assertUnprocessable();
        $this->requestPreview($letter);
        $this->assertDatabaseCount('letter_operations', 2);
        $this->assertDatabaseCount('letter_drafts', 1);
    }

    public static function unusableArtifacts(): array
    {
        return [['expired'], ['missing'], ['corrupted']];
    }

    public function test_requests_reject_invalid_state_keys_and_mismatched_evidence(): void
    {
        $letter = $this->draft();
        $this->postJson($this->url($letter), [])->assertUnprocessable()->assertJsonValidationErrors(['revision', 'idempotency_key']);
        $this->postJson($this->url($letter), ['revision' => 9, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $key = (string) Str::uuid();
        $uuid = $this->postJson($this->url($letter), ['revision' => 1, 'idempotency_key' => $key])->assertAccepted()->json('operation.id');
        $other = Letter::factory()->create();
        $this->postJson($this->url($other), ['revision' => 1, 'idempotency_key' => $key])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->render($uuid);
        $artifact = LetterArtifact::sole();
        $data = $this->checkData($artifact);
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), [...$data, 'fingerprint' => str_repeat('a', 64)])->assertUnprocessable();
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), [...$data, 'preview_kind' => 'pdf'])->assertUnprocessable();
        $this->assertDatabaseCount('letter_preview_checks', 0);
        $letter->draft->update(['revision' => 2]);
        $this->postJson($this->url($letter), ['revision' => 1, 'idempotency_key' => $key])->assertAccepted()->assertJsonPath('operation.id', $uuid);
        $letter->template->update(['is_active' => false]);
        $this->postJson($this->url($letter), ['revision' => 2, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->assertDatabaseCount('letter_operations', 1);
    }

    public function test_active_lease_is_not_executed_twice_and_expired_lease_is_recovered(): void
    {
        $this->freezeTime();
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $operation = LetterOperation::sole();
        $operation->update(['status' => Status::RUNNING, 'attempts' => 1, 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addSeconds(110)]);
        app(RunLetterPreview::class)->run($uuid);
        $this->assertDatabaseCount('letter_artifacts', 0);
        $this->artisan('letters:recover-operations')->assertSuccessful();
        $this->assertSame(Status::RUNNING, $operation->fresh()->status);
        $this->travel(111)->seconds();
        $this->artisan('letters:recover-operations')->assertSuccessful();
        $this->assertSame(Status::QUEUED, $operation->fresh()->status);
        $this->assertNull($operation->fresh()->lease_token);
        Bus::assertDispatched(GenerateLetterPreview::class, fn($job) => $job->operationUuid === $uuid);
        $this->render($uuid);
        $this->assertSame(Status::SUCCEEDED, $operation->fresh()->status);
        $this->assertSame(2, $operation->fresh()->attempts);
    }

    public function test_recovery_dispatches_forgotten_queued_work_and_stops_after_three_attempts(): void
    {
        $this->freezeTime();
        $letter = $this->draft();
        $uuid = $this->requestPreview($letter);
        $operation = LetterOperation::sole();
        $operation->update(['updated_at' => now()->subMinutes(3)]);
        $this->artisan('letters:recover-operations')->assertSuccessful();
        Bus::assertDispatched(GenerateLetterPreview::class, fn($job) => $job->operationUuid === $uuid);
        $operation->update(['status' => Status::RUNNING, 'attempts' => 3, 'lease_expires_at' => now()->subSecond()]);
        $this->artisan('letters:recover-operations')->assertSuccessful();
        $this->assertSame(Status::FAILED, $operation->fresh()->status);
        $this->assertDatabaseCount('letter_artifacts', 0);
        $this->assertDatabaseCount('letter_drafts', 1);
    }

    public function test_real_render_subprocess_reads_the_staged_snapshot_and_returns_docx(): void
    {
        $letter = $this->draft();
        $directory = 'letters/staging/' . Str::uuid() . '/' . Str::uuid();
        $staging = Storage::build(['driver' => 'local', 'root' => storage_path('app/private')]);
        try {
            $staging->put($directory . '/input.json', json_encode(['source' => Storage::disk('local')->path($letter->draft->templateVersion->source_path), 'source_sha256' => $letter->draft->templateVersion->source_sha256, 'snapshot' => $letter->draft->snapshot], JSON_THROW_ON_ERROR));
            $result = Process::timeout(30)->run([PHP_BINARY, base_path('artisan'), 'letters:render-preview', $directory, '--no-interaction']);
            $this->assertTrue($result->successful(), $result->errorOutput() . $result->output());
            $this->assertTrue($staging->exists($directory . '/result.json'));
            $parts = app(InspectLetterTemplateDocx::class)->read($staging->path($directory . '/preview.docx'));
            $this->assertStringContainsString('/XX.XX/PKM-KMJ/X/2026', $parts['word/document.xml']->textContent);
            $this->assertDatabaseCount('letter_artifacts', 0);
        } finally {
            $staging->deleteDirectory($directory);
        }
    }

    public function test_draft_number_warning_survives_repeated_table_row_suffixes(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $word = new PhpWord();
        $table = $word->addSection()->addTable();
        $table->addRow();
        $table->addCell()->addText('${baris}');
        $table->addCell()->addText('${nomor_surat}');
        IOFactory::createWriter($word, 'Word2007')->save($disk->path('source.docx'));
        app(RenderLetterDocument::class)->render($disk->path('source.docx'), ['schema_version' => 1, 'draft_preview' => true, 'values' => ['baris#1' => 'A', 'baris#2' => 'B', 'nomor_surat' => '445/XX.XX/PKM-KMJ/X/2026'], 'rows' => [['anchor' => 'baris', 'count' => 2]]], $disk->path('result.docx'));
        $parts = app(InspectLetterTemplateDocx::class)->read($disk->path('result.docx'));
        $this->assertSame(2, substr_count($parts['word/document.xml']->saveXML(), 'B45309'));
        $this->assertSame(2, substr_count($parts['word/document.xml']->textContent, '445/XX.XX/PKM-KMJ/X/2026'));
    }

    private function draft(): Letter
    {
        Storage::fake('local');
        Bus::fake([GenerateLetterPreview::class]);
        $this->actingAs(UserFactory::new()->create());
        $version = LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nomor_surat', 'tanggal_surat']]]);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);
        $disk = Storage::disk('local');
        $disk->makeDirectory('tests');
        $word = new PhpWord();
        $section = $word->addSection();
        $section->addText('Surat ${nomor_surat} ${tanggal_surat}');
        $section->addHeader()->addText('${nomor_surat}');
        $section->addFooter()->addText('${nomor_surat}');
        IOFactory::createWriter($word, 'Word2007')->save($disk->path('tests/source.docx'));
        $version->update(['source_disk' => 'local', 'source_path' => 'tests/source.docx', 'source_sha256' => hash_file('sha256', $disk->path('tests/source.docx'))]);
        $this->post(route('ltr.store'), ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat uji', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => [], 'signers' => []])->assertSessionHasNoErrors();

        return Letter::sole();
    }

    private function url(Letter $letter): string
    {
        return route('ltr.previews.store', $letter->uuid);
    }

    private function requestPreview(Letter $letter, int $revision = 1): string
    {
        return $this->postJson($this->url($letter), ['revision' => $revision, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
    }

    private function checkData(LetterArtifact $artifact): array
    {
        return ['revision' => $artifact->draft_revision, 'fingerprint' => app(LetterPreviewState::class)->evidence($artifact), 'preview_kind' => 'docx', 'confirmed' => true];
    }

    private function render(string $uuid, ?callable $after = null): void
    {
        $this->renderFake($after);
        app(RunLetterPreview::class)->run($uuid);
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
