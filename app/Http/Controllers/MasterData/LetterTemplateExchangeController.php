<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\LetterTemplateExchangeRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\ImportLetterTemplateExchange;
use App\Services\LetterTemplate\LetterTemplateExchangeConfiguration;
use App\Services\LetterTemplate\LetterTemplateExchangePackage;
use App\Services\LetterTemplate\LetterTemplateExchangeStage;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use App\Services\LetterTemplate\RecordLetterTemplateAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Response as InertiaResponse;

class LetterTemplateExchangeController extends Controller
{
    public function create(): InertiaResponse
    {
        return inertia('letter-templates/exchange', ['mode' => 'import', 'page' => ['id' => 'a3063dc3-7414-4385-bf3f-0cf0742e3980']]);
    }

    public function exportPage(LetterTemplate $letterTemplate, LetterTemplateVersion $version): InertiaResponse
    {
        return inertia('letter-templates/exchange', [
            'mode' => 'export', 'page' => ['id' => 'a3063dc3-7414-4385-bf3f-0cf0742e3980'],
            'source' => ['template' => $letterTemplate->uuid, 'version' => $version->uuid, 'name' => $letterTemplate->name, 'number' => $version->version_number],
        ]);
    }

    public function estimate(LetterTemplateExchangeRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, LetterTemplateExchangePackage $packages): JsonResponse
    {
        $prepared = $packages->prepare($letterTemplate, $version, $request->boolean('include_docx'), $request->boolean('include_fonts'));
        $configurationBytes = strlen(json_encode($prepared['manifest'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $fileBytes = array_sum(array_map(strlen(...), $prepared['files']));
        $estimated = $configurationBytes + $fileBytes + 1024 + count($prepared['files']) * 256 + (int) ceil(($configurationBytes + $fileBytes) / 65536) * 17;

        return response()->json(['estimated_bytes' => $estimated, 'configuration_bytes' => $configurationBytes, 'docx_bytes' => strlen($prepared['files']['source.docx'] ?? ''), 'font_bytes' => $fileBytes - strlen($prepared['files']['source.docx'] ?? ''), 'missing_fonts' => $prepared['missing_fonts'], 'limitations' => $prepared['limitations']]);
    }

    public function export(LetterTemplateExchangeRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, LetterTemplateExchangePackage $packages, LetterTemplateExchangeStage $stages, RecordLetterTemplateAudit $audit): JsonResponse
    {
        $prepared = $packages->prepare($letterTemplate, $version, $request->boolean('include_docx'), $request->boolean('include_fonts'));
        $bytes = $packages->encode($prepared, $request->validated('password'));
        $token = $stages->create(['manifest' => ['files' => ['package.tnex' => ['size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]]], 'files' => ['package.tnex' => $bytes]], $request->user());
        $audit->record($letterTemplate, $version, $request->user(), 'exchange.exported', 'Paket templat terenkripsi dibuat.');

        return response()->json(['download_url' => route('lt.exchange.download', $token), 'size' => strlen($bytes)]);
    }

    public function download(LetterTemplateExchangeRequest $request, string $token, LetterTemplateExchangeStage $stages): Response
    {
        return $stages->use($token, $request->user(), function (array $manifest, array $files) use ($token, $stages): Response {
            abort_unless(isset($files['package.tnex']), 404);
            $bytes = file_get_contents($files['package.tnex']);
            $stages->delete($token);

            return response($bytes, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="templat.tnex"', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
        });
    }

    public function inspect(LetterTemplateExchangeRequest $request, LetterTemplateExchangePackage $packages, LetterTemplateExchangeStage $stages): JsonResponse
    {
        $package = $packages->decode(file_get_contents($request->file('package')->getPathname()), $request->validated('password'));
        $token = $stages->create($package, $request->user());

        return response()->json(['review_url' => route('lt.exchange.review', $token)]);
    }

    public function review(LetterTemplateExchangeRequest $request, string $token, LetterTemplateExchangeStage $stages, LetterTemplateExchangeConfiguration $configuration, ImportLetterTemplateExchange $imports): InertiaResponse
    {
        return $stages->use($token, $request->user(), function (array $manifest, array $files, int $expires) use ($token, $configuration, $imports): InertiaResponse {
            abort_unless(isset($manifest['configuration']), 404);

            return inertia('letter-templates/exchange', [
                'mode' => 'review', 'page' => ['id' => 'a3063dc3-7414-4385-bf3f-0cf0742e3980'],
                'review' => [
                    'token' => $token, 'name' => $manifest['template']['name'], 'source_version' => $manifest['template']['version_number'],
                    'has_docx' => isset($files['source.docx']), 'fonts' => count($files) - (isset($files['source.docx']) ? 1 : 0),
                    'expires_at' => gmdate('c', $expires), 'incompatibilities' => [...$configuration->incompatibilities($manifest['configuration'], $manifest['employee_contract']), ...$imports->fontConflicts($manifest, $files)],
                    'variables' => array_map(fn(array $variable): array => ['name' => $variable['name'], 'label' => $variable['label'], 'needs_reference' => $variable['option_source'] === LetterTemplateOptionSourceEnum::REFERENCE->value], $manifest['configuration']['variables']),
                    'signer_slots' => count($manifest['configuration']['signer_slots']), 'font_limitations' => $manifest['font_limitations'],
                ],
                'targets' => LetterTemplate::query()->with(['draftVersion.variables.columns', 'draftVersion.variables.employeeAttributes', 'draftVersion.signerSlots.candidates', 'draftVersion.signerSlots.employeeAttributes'])->orderBy('name')->orderBy('id')->get()->map(function (LetterTemplate $template): array {
                    $template->draftVersion?->setRelation('template', $template);

                    return [
                        'id' => $template->uuid, 'label' => $template->name,
                        'version_token' => $template->draftVersion === null ? null : app(LetterTemplateVersionState::class)->token($template->draftVersion),
                    ];
                })->all(),
            ]);
        });
    }

    public function store(LetterTemplateExchangeRequest $request, string $token, ImportLetterTemplateExchange $imports): RedirectResponse
    {
        $version = $imports->save($token, $request->validated(), $request->user());

        return redirect()->route('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => $version->uuid]);
    }

    public function destroy(LetterTemplateExchangeRequest $request, string $token, LetterTemplateExchangeStage $stages): RedirectResponse
    {
        $stages->use($token, $request->user(), fn() => $stages->delete($token));

        return redirect()->route('lt.index');
    }
}
