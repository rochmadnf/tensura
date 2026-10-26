<?php

use App\Http\Controllers\AccountAvatarController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchiveDestroyController;
use App\Http\Controllers\ArchivePreviewController;
use App\Http\Controllers\ArchiveReferenceGroupController;
use App\Http\Controllers\ArchiveRestoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentFontController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LetterDocumentController;
use App\Http\Controllers\LetterDraftChangeController;
use App\Http\Controllers\LetterIssuanceController;
use App\Http\Controllers\LetterOperationController;
use App\Http\Controllers\LetterPdfController;
use App\Http\Controllers\LetterPreviewController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\EmployeeExportController;
use App\Http\Controllers\MasterData\EmployeeImportController;
use App\Http\Controllers\MasterData\JobTitleController;
use App\Http\Controllers\MasterData\LetterNumberController;
use App\Http\Controllers\MasterData\LetterNumberPeriodController;
use App\Http\Controllers\MasterData\LetterNumberUsageController;
use App\Http\Controllers\MasterData\LetterTemplateAuditController;
use App\Http\Controllers\MasterData\LetterTemplateConfigurationController;
use App\Http\Controllers\MasterData\LetterTemplateConfigurationItemController;
use App\Http\Controllers\MasterData\LetterTemplateController;
use App\Http\Controllers\MasterData\LetterTemplateDateFormatController;
use App\Http\Controllers\MasterData\LetterTemplateExampleController;
use App\Http\Controllers\MasterData\LetterTemplateSourceController;
use App\Http\Controllers\MasterData\LetterTemplateSystemFieldsController;
use App\Http\Controllers\MasterData\LetterTemplateTestController;
use App\Http\Controllers\MasterData\LetterTemplateValidationController;
use App\Http\Controllers\MasterData\LetterTemplateVersionController;
use App\Http\Controllers\MasterData\ReferenceGroupController;
use App\Http\Controllers\MasterData\ReferenceItemController;
use App\Http\Controllers\OptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('letter-operations/events', [LetterOperationController::class, 'stream'])->name('ltr.events');
    Route::prefix('letters/{letter:uuid}')->whereUuid('letter')->group(function (): void {
        Route::post('artifacts/{artifact:uuid}/pdf', [LetterPdfController::class, 'store'])->whereUuid('artifact')->middleware('throttle:20,1')->name('ltr.pdfs.store');
        Route::get('pdfs/{artifact:uuid}/file', [LetterPdfController::class, 'file'])->whereUuid('artifact')->name('ltr.pdfs.file');
        Route::get('pdfs/{artifact:uuid}/download', [LetterPdfController::class, 'file'])->whereUuid('artifact')->name('ltr.pdfs.download');
        Route::post('issue', [LetterIssuanceController::class, 'store'])->middleware('throttle:20,1')->name('ltr.issue');
        Route::get('versions/{version:uuid}/word', [LetterDocumentController::class, 'show'])->whereUuid('version')->name('ltr.versions.file');
        Route::get('versions/{version:uuid}/download', [LetterDocumentController::class, 'show'])->whereUuid('version')->name('ltr.versions.download');
        Route::post('previews', [LetterPreviewController::class, 'store'])->middleware('throttle:20,1')->name('ltr.previews.store');
        Route::get('previews/{artifact:uuid}/file', [LetterPreviewController::class, 'file'])->whereUuid('artifact')->name('ltr.previews.file');
        Route::post('previews/{artifact:uuid}/rendered', [LetterPreviewController::class, 'rendered'])->whereUuid('artifact')->name('ltr.previews.rendered');
        Route::post('previews/{artifact:uuid}/confirm', [LetterPreviewController::class, 'confirm'])->whereUuid('artifact')->name('ltr.previews.confirm');
        Route::get('operations/{operation:uuid}', [LetterOperationController::class, 'show'])->whereUuid('operation')->name('ltr.operations.show');
        Route::get('master-refresh', [LetterDraftChangeController::class, 'preview'])->defaults('kind', 'masters')->name('ltr.masters.preview');
        Route::post('master-refresh', [LetterDraftChangeController::class, 'apply'])->defaults('kind', 'masters')->name('ltr.masters.apply');
        Route::get('template-adjustment', [LetterDraftChangeController::class, 'preview'])->defaults('kind', 'template')->name('ltr.adjustment.preview');
        Route::post('template-adjustment', [LetterDraftChangeController::class, 'apply'])->defaults('kind', 'template')->name('ltr.adjustment.apply');
    });
    Route::resource('letters', LetterController::class)->scoped(['letter' => 'uuid'])->whereUuid('letter')->names('ltr');
    Route::get('document-fonts', [DocumentFontController::class, 'index'])->name('document-fonts.index');
    Route::post('document-fonts', [DocumentFontController::class, 'store'])->middleware('throttle:20,1')->name('document-fonts.store');
    Route::get('document-fonts/{documentFont:uuid}/file', [DocumentFontController::class, 'file'])->whereUuid('documentFont')->name('document-fonts.file');
    Route::get('options', OptionController::class)->name('options.index');
    Route::get('letter-templates/{letterTemplate:uuid}/audits', LetterTemplateAuditController::class)->whereUuid('letterTemplate')->name('lt.audits.index');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::get('reference/archive', [ArchiveController::class, 'index'])->name('ref.archive.index');
    Route::get('archives/{archive:uuid}', [ArchiveController::class, 'show'])->name('archives.show');
    Route::post('archives/{archive:uuid}/preview', ArchivePreviewController::class)->name('archives.preview');
    Route::post('archives/{archive:uuid}/restore', ArchiveRestoreController::class)->name('archives.restore');
    Route::delete('archives/{archive:uuid}', ArchiveDestroyController::class)->name('archives.destroy');
    Route::patch('archives/{archive:uuid}/reference-group', ArchiveReferenceGroupController::class)->name('archives.reference-group.update');

    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('account/profile', [AccountController::class, 'update'])->name('account.profile.update');
    Route::put('account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    Route::post('account/avatar', [AccountAvatarController::class, 'store'])->name('account.avatar.store');
    Route::delete('account/avatar', [AccountAvatarController::class, 'destroy'])->name('account.avatar.destroy');

    Route::get('employees/export', EmployeeExportController::class)->name('empl.export');
    Route::get('employees/import', [EmployeeImportController::class, 'create'])->name('empl.import.create');
    Route::get('employees/import/template', [EmployeeImportController::class, 'template'])->name('empl.import.template');
    Route::post('employees/import', [EmployeeImportController::class, 'store'])->name('empl.import.store');

    Route::resource('employees', EmployeeController::class)
        ->parameters(['employees' => 'employee'])
        ->scoped(['employee' => 'uuid'])
        ->names('empl');

    Route::resource('job-title', JobTitleController::class)
        ->except('show')
        ->parameters(['job-title' => 'jobTitle'])
        ->scoped(['jobTitle' => 'uuid'])
        ->names('jt');

    Route::get('letter-templates/date-format', LetterTemplateDateFormatController::class)->name('lt.date-format');

    Route::prefix('letter-templates/{letterTemplate:uuid}/versions/{version:uuid}')->scopeBindings()->whereUuid(['letterTemplate', 'version'])->group(function (): void {
        Route::post('configuration/variables', LetterTemplateConfigurationItemController::class)->defaults('kind', 'variable')->defaults('operation', 'save')->whereUuid('item')->name('lt.configuration.variables.store');
        Route::put('configuration/variables/{item}', LetterTemplateConfigurationItemController::class)->defaults('kind', 'variable')->defaults('operation', 'save')->whereUuid('item')->name('lt.configuration.variables.update');
        Route::delete('configuration/variables/{item}', LetterTemplateConfigurationItemController::class)->defaults('kind', 'variable')->defaults('operation', 'delete')->whereUuid('item')->name('lt.configuration.variables.destroy');
        Route::patch('configuration/variables/{item}/attributes/{attribute}', LetterTemplateConfigurationItemController::class)->defaults('kind', 'variable')->defaults('operation', 'attribute')->whereUuid('item')->name('lt.configuration.variables.attribute');
        Route::patch('configuration/variables/{item}/order', LetterTemplateConfigurationItemController::class)->defaults('kind', 'variable')->defaults('operation', 'move')->whereUuid('item')->name('lt.configuration.variables.move');
        Route::post('configuration/slots', LetterTemplateConfigurationItemController::class)->defaults('kind', 'slot')->defaults('operation', 'save')->whereUuid('item')->name('lt.configuration.slots.store');
        Route::put('configuration/slots/{item}', LetterTemplateConfigurationItemController::class)->defaults('kind', 'slot')->defaults('operation', 'save')->whereUuid('item')->name('lt.configuration.slots.update');
        Route::delete('configuration/slots/{item}', LetterTemplateConfigurationItemController::class)->defaults('kind', 'slot')->defaults('operation', 'delete')->whereUuid('item')->name('lt.configuration.slots.destroy');
        Route::patch('configuration/slots/{item}/attributes/{attribute}', LetterTemplateConfigurationItemController::class)->defaults('kind', 'slot')->defaults('operation', 'attribute')->whereUuid('item')->name('lt.configuration.slots.attribute');
        Route::post('publish', [LetterTemplateVersionController::class, 'publish'])->name('lt.versions.publish');
        Route::post('draft', [LetterTemplateVersionController::class, 'draft'])->name('lt.versions.draft');
        Route::post('archive', [LetterTemplateVersionController::class, 'archive'])->name('lt.versions.archive');
        Route::patch('activation', [LetterTemplateVersionController::class, 'activation'])->name('lt.activation.update');
        Route::patch('system-fields', LetterTemplateSystemFieldsController::class)->name('lt.system-fields.update');
        Route::post('test', [LetterTemplateTestController::class, 'store'])->name('lt.test.store');
        Route::get('test', [LetterTemplateTestController::class, 'show'])->name('lt.test.download');
        Route::post('test/confirm', [LetterTemplateTestController::class, 'confirm'])->name('lt.test.confirm');
        Route::post('source', [LetterTemplateSourceController::class, 'store'])->name('lt.source.store');
        Route::get('source', [LetterTemplateSourceController::class, 'show'])->name('lt.source.download');
        Route::post('validate', LetterTemplateValidationController::class)->name('lt.validation.store');
        Route::get('example', LetterTemplateExampleController::class)->name('lt.example.download');
    });

    Route::put('letter-templates/{letterTemplate:uuid}/versions/{version:uuid}/configuration', LetterTemplateConfigurationController::class)
        ->scopeBindings()->whereUuid(['letterTemplate', 'version'])->name('lt.configuration.update');

    Route::resource('letter-templates', LetterTemplateController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->parameters(['letter-templates' => 'letterTemplate'])
        ->scoped(['letterTemplate' => 'uuid'])
        ->whereUuid('letterTemplate')
        ->names('lt');

    Route::resource('reference', ReferenceGroupController::class)
        ->except('show')
        ->parameters(['reference' => 'referenceGroup'])
        ->scoped(['referenceGroup' => 'uuid'])
        ->names('ref');

    Route::patch('reference/{referenceGroup:uuid}/items/reorder', [ReferenceItemController::class, 'reorder'])->name(
        'ref.item.reorder',
    );

    Route::resource('reference.items', ReferenceItemController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['reference' => 'referenceGroup', 'items' => 'item'])
        ->scoped(['referenceGroup' => 'uuid', 'item' => 'uuid'])
        ->names(['store' => 'ref.item.store', 'update' => 'ref.item.update', 'destroy' => 'ref.item.destroy']);

    Route::controller(LetterNumberController::class)
        ->prefix('letter-number')
        ->group(function () {
            Route::get('/', 'index')->name('ln.index');
            Route::get('/create', 'create')->name('ln.create');
            Route::post('/store', 'store')->name('ln.store');
            Route::get('/{letterNumber:uuid}', 'show')->name('ln.show');
            Route::get('/{letterNumber:uuid}/periods/{period:uuid}/usage', LetterNumberUsageController::class)->whereUuid(['letterNumber', 'period'])->name('ln.periods.usage');
            Route::post('/{letterNumber:uuid}/periods', [LetterNumberPeriodController::class, 'store'])->name('ln.periods.store');
            Route::get('/{letterNumber:uuid}/edit', 'edit')->name('ln.edit');
            Route::patch('/{letterNumber:uuid}', 'update')->name('ln.update');
            Route::delete('/{letterNumber:uuid}', 'destroy')->name('ln.destroy');
        });
});

require __DIR__ . '/auth.php';
