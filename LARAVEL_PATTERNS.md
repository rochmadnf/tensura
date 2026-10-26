# Laravel patterns for Tensura

Use this guide when adding or changing a Tensura feature. It describes the implemented backend and frontend patterns, including shared search, tables, forms, staged template workflows, private documents, archive workflows, and PostgreSQL testing. Follow these defaults for new modules and preserve existing contracts when refactoring. [FEATURE_WORKFLOW.md](FEATURE_WORKFLOW.md) owns the implementation sequence and UI composition checklist; this guide owns backend boundaries and shared data contracts.

Read `AGENTS.md`, applicable `.ai/rules` when present, and relevant skills first. Confirm installed versions from Composer and `package.json`; use Boost `search-docs` for version-sensitive framework behavior. The current stack is PHP 8.4, Laravel 13, Inertia 3, React 19, TanStack Table 9, and PostgreSQL. Examples in a skill do not establish the installed package API.

## Working references

| Concern                                       | Start here                                                                                                                                                                                                                                                   |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Simple CRUD and protected update fields       | [LetterNumberController](app/Http/Controllers/MasterData/LetterNumberController.php), [LetterNumberForm](resources/js/pages/letter-number/components/letter-number-form.tsx)                                                                                 |
| Combined relationship, enum, and date filters | [EmployeeController](app/Http/Controllers/MasterData/EmployeeController.php), [Employee index](resources/js/pages/employee/index.tsx)                                                                                                                        |
| Reusable model search                         | [Searchable](app/Models/Searchable.php), [JobTitle](app/Models/MasterData/JobTitle.php), [Archive](app/Models/Archive.php)                                                                                                                                   |
| Listing toolbar and server pagination         | [SectionData](resources/js/components/section-data.tsx), [ResourceDataTable](resources/js/components/resource-data-table.tsx)                                                                                                                                |
| Shared form and row actions                   | [FormCard](resources/js/components/form-card.tsx), [FormActions](resources/js/components/form-actions.tsx), [TableActionButton](resources/js/components/table-action-button.tsx), [DeleteResourceAction](resources/js/components/delete-resource-action.tsx) |
| Recoverable deletion and archive workflows    | [ArchiveReference](app/Services/Archive/ArchiveReference.php), [ArchiveController](app/Http/Controllers/ArchiveController.php), [Archive pages](resources/js/pages/archive)                                                                                  |
| Staged editors and lifecycle operations       | [Template editor](resources/js/pages/letter-templates/edit.tsx), [ManageLetterTemplateVersions](app/Services/LetterTemplate/ManageLetterTemplateVersions.php), [LetterTemplateVersionController](app/Http/Controllers/MasterData/LetterTemplateVersionController.php) |
| Private document storage and validation       | [StoreLetterTemplateDocx](app/Services/LetterTemplate/StoreLetterTemplateDocx.php), [ValidateLetterTemplateDocx](app/Services/LetterTemplate/ValidateLetterTemplateDocx.php), [CleanupLetterTemplateFiles](app/Services/LetterTemplate/CleanupLetterTemplateFiles.php) |
| State, examination evidence, and audit        | [LetterTemplateVersionState](app/Services/LetterTemplate/LetterTemplateVersionState.php), [LetterTemplateTestEvidence](app/Services/LetterTemplate/LetterTemplateTestEvidence.php), [RecordLetterTemplateAudit](app/Services/LetterTemplate/RecordLetterTemplateAudit.php) |
| Shared letter drafts and explicit snapshot changes | [ManageLetterDraft](app/Services/Letter/ManageLetterDraft.php), [LetterForm](resources/js/pages/letters/components/letter-form.tsx), [draft tests](tests/Feature/LetterDraftTest.php) |
| Database test isolation                       | [Main bootstrap](tests/bootstrap.php), [Archive bootstrap](tests/archive-bootstrap.php), [.env.testing.example](.env.testing.example)                                                                                                                        |

Use the current source as the reference for component props and implemented contracts. Documents under `docs/planning` establish agreed product behavior and retain implementation history; they are not proof that a capability has been implemented. In particular, [Surat business process](docs/planning/letter/probis.md) governs Surat; shared draft management, queued DOCX preview with SSE, initial issuance, and official Word downloads are implemented; PDF and the remaining lifecycle follow the staged technical plan. Do not import its numbering, recovery, or PDF failure decisions into the template workflow or treat template test generation as issued-letter generation.

## Responsibilities

| Layer                       | Owns                                                                          | Letter-number example                                   |
| --------------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------- |
| Routes                      | HTTP methods, names, authentication, model binding                            | `routes/web.php`, `ln.*` routes                         |
| Controller                  | Coordinate requests, persistence, resources, and redirects                    | `LetterNumberController`                                |
| Form Request                | Authorization, server validation, translated field labels                     | `StoreLetterNumberRequest`, `UpdateLetterNumberRequest` |
| Domain service              | Coordinated operations, current-state checks, transactions, and audit where required | `ManageLetterTemplateVersions`, `StoreLetterTemplateDocx` |
| Model                       | Persistence, mass assignment, relationships, casts, search columns and scopes | `LetterNumber`, `Searchable`                            |
| Resource                    | Explicit data exposed to the frontend                                         | `LetterNumberResource`                                  |
| Page and feature components | Domain fields, columns, filters, routes, and presentation                     | `pages/letter-number`, `pages/employee`                 |
| Shared frontend             | Listing navigation, table setup, form shell, and action controls              | `SectionData`, `ResourceDataTable`, `FormCard`          |
| Feature test                | Observable endpoint behavior and important failure modes                      | `LetterNumberEditTest`, `LetterNumberDeleteTest`        |

For simple CRUD, use **route → controller → Eloquent**, with Form Requests validating writes and Resources shaping reads. The controller should remain readable without following several one-line wrappers.

Extract an action when an operation gains reusable business rules or multiple coordinated steps. Add a service when several operations need shared orchestration. Use constructor or method injection for those dependencies. Add a transaction for writes that must succeed together. Introduce a DTO when a typed payload actually crosses a domain boundary; validated arrays are sufficient for the current CRUD operations.

Coordinated workflows already use concrete classes in `app/Services/{Domain}`. Follow that boundary rather than adding an `Actions`/repository hierarchy beside it for the same operations. Operation controllers validate, delegate with explicit arguments and actor, then return a Resource, redirect, or file response. Keep lifecycle rules, document inspection, and audit diff construction out of controllers and React components.

An Eloquent repository, generic CRUD base controller, service interface, or shared request superclass needs a concrete reuse or isolation benefit before being introduced. Keep store and update validation explicit because their writable fields and uniqueness rules differ.

## Request and response contracts

- Use `Store{Resource}Request` and `Update{Resource}Request`, with typed `authorize(): bool`, `rules(): array`, and `attributes(): array`. Use PHPDoc array shapes for known returned data and framework-compatible types for rule arrays.
- Use the request's `user()` for request-specific authentication checks. Letter-number currently permits authenticated users; introduce policies when the product needs roles, ownership, or per-record permissions. Authentication and UUIDs do not replace authorization.
- Persist only intended, validated attributes. Keep the model's mass-assignment configuration aligned with the feature. Use framework `validated()` or `safe()->only(...)` directly when a custom method would only return the same data.
- Allowlist nested array keys as well as top-level fields. For dynamic forms, derive accepted variables, types, sources, and limits from the selected server-side configuration; do not accept arbitrary fields or client-provided storage paths/state as authoritative.
- Keep database constraints for unique values. On update, ignore the bound model in `Rule::unique(...)->ignore($model)`; never take the ignored identifier from submitted input.
- Preserve classification codes as strings so `007` remains `007`. Letter-number store/update accept only name and code, even when a crafted payload includes counters. New classifications have null legacy counters; existing values remain read-only during the numbering transition.
- Declare method parameter and return types. Resources expose explicit fields and document their response shapes; avoid returning whole models with incidental columns.
- Generate UUIDs using the existing model conventions (`HasUuids` and `uniqueIds(): array` returning `['uuid']`). Keep numeric primary keys internal. CRUD Resources expose the UUID as `id`; archive Resources expose `uuid`. Preserve that distinction in frontend types and row identifiers.
- Keep enum values and option lists on the backend using the existing `*Enum` naming and `options()` pattern. Translate submitted relationship UUIDs to internal keys only after validation, as Employee does for job titles.
- Return field-addressable domain validation errors using the established `ValidationException::withMessages(...)` pattern. Keep unexpected infrastructure exceptions reportable; a cleanup failure must not conceal the original operation failure or turn a failed write into a success response.

For example, the update boundary stays small:

```php
public function update(UpdateLetterNumberRequest $request, LetterNumber $letterNumber): RedirectResponse
{
    $letterNumber->update($request->safe()->only([
        'classification_name',
        'classification_code',
    ]));

    return redirect()->route('ln.index');
}
```

## Routing and read contracts

- Use standard controller actions: `index`, `create`, `store`, `edit`, `update`, and `destroy`. Prefer resource routes for new resources when they match the intended URLs. Add focused operation endpoints for workflow commands such as validation, generation, publication, or restoration; do not overload an ordinary update/delete with unrelated state transitions. Preserve existing explicit URLs and names for letter-number, including `/letter-number/store` and `ln.*`.
- Bind letter-number using `{letterNumber:uuid}`. Its internal primary key remains numeric; the Resource exposes its UUID as `id`. Unknown identifiers return 404 through model binding.
- Scope nested route bindings to their parent, following `reference.items`; a valid child UUID under a different parent must not resolve.
- Render pages from `resources/js/pages` through Inertia and shape records through Resources. Preserve `resource.data` for a single record, and `resources.data`, `resources.meta`, and `resources.links` for a paginated collection. Return normalized active values in `filters` so the client can synchronize its controls.
- Include `page.id` and configure the page's `layout.pageName` using the existing navigation conventions. Use a distinct page identity for a new module rather than copying another module's UUID.
- Keep simple list queries in the controller. Eager load only required relationships and fields, including keys needed to hydrate them. Use `withCount()` when the page needs counts rather than child records.
- When records have versions, define one server-side reference version for listing values, search, and filters. Follow `LetterTemplate::displayVersion()` for this consistency principle; its active/draft/latest precedence is specific to templates and must not be copied into another domain without its business contract.
- Apply explicit, deterministic ordering before pagination. Use a unique final key when the preceding sort fields can repeat. Letter-number sorts by its unique string classification code; JobTitle and Employee finish with `id`.
- Bound `per_page` to `[5, 10, 15, 20, 50, 100]`, defaulting to `10`, and call `withQueryString()` after pagination.
- Combine related filters with their intended semantics. Employee puts job-title UUID and group conditions inside one `whereHas('jobTitle')` with AND semantics. Compare date columns directly when the input is already a date; preserve the existing tenure guard, leap-year handling, and reversed-range behavior.
- Use jobs for slow or retryable external work, and events for meaningful domain side effects. Add caching only when there is a measured need and a clear invalidation strategy.

## Workflow state, transactions, and history

- Keep status transitions and eligibility rules in domain services. Resources expose explicit status labels, capabilities, readiness checks, and reasons for the UI. Disabled buttons and client-side checks do not enforce the operation.
- Form Requests validate the submitted shape and authorization; re-read and verify mutable conditions when executing the operation. Scope versions/items through their parent relationship and recheck current status, permitted candidates, and related master availability as required by that operation.
- Use `DB::transaction()` for database changes that must succeed together. Acquire `lockForUpdate()` locks inside it, with a consistent parent-before-child order for operations on the same aggregate. Re-read the locked rows rather than trusting models loaded before the transaction.
- Preserve database constraints for uniqueness and relationship integrity. Locks, validation, and client operation guards complement constraints; a lookup followed by an unprotected insert is not a concurrency strategy.
- Where operations depend on an examined state, validate its version token/fingerprint and examination evidence again under the transaction locks. Templat Surat separates version state, publication readiness, and test evidence. Invalidate affected evidence when source/configuration changes. Do not use a stale UI flag or an unbound `confirmed: true` as proof that the current content was examined.
- Conflict detection, idempotent retry, and pessimistic locking solve different problems. For workflows that require stale-edit rejection or safe retries, specify and test those contracts rather than assuming a database lock alone provides them. Shared Surat drafts use revision checks under template → letter → draft locks in `ManageLetterDraft`. Master refresh and template adjustment require an explicit, state-bound change summary and confirmation. Initial issuance uses actor-bound idempotency and a durable operation; a retry after commit returns the same official version.
- Transaction callbacks can run again after deadlocks (`DB::transaction(..., 5)` is used by template operations). Any file creation or external side effect within retryable work needs explicit duplicate/cleanup handling; a database rollback does not undo external work.
- Record successful state changes and their audit data together when they must be atomic. Preserve actor identity/name, event time, operation, description, and relevant before/after values, following `RecordLetterTemplateAudit` and `LetterTemplateAuditChanges`. Present history through a dedicated reader/Resource rather than exposing raw internal payloads.
- Preserve historical source files, configurations, and rendered values according to the feature's version/snapshot contract. Do not regenerate a historical result using today's master data. Template versions already protect used versions; Surat draft snapshots preserve selected master attributes and resolved values; initial issued-letter snapshots and artifacts are stored on `LetterVersion` and retained independently of draft preview expiry.

## Private documents, processing, and cleanup

- Centralize disk, paths, upload/expansion limits, processing budgets, and expiry in `config/{feature}.php`; read environment values only there. Pass the limits needed by the frontend through props instead of duplicating constants in TypeScript.
- Use server-generated paths on a private disk and store the metadata needed to identify source/output versions and content hashes. Client filenames are display metadata, not storage paths. A `.docx` extension or MIME check alone does not validate the document structure; reuse the template inspection/validation pipeline where its contract applies.
- Database and filesystem changes are not one atomic transaction. Follow `StoreLetterTemplateDocx`: stage the new file, validate current state and persist metadata under locks, remove the staged file on failure, and delete a superseded source only after commit and after verifying it is no longer referenced. Preserve the original exception if cleanup also fails, and report cleanup failures.
- Serve files through authenticated, parent-scoped endpoints with current availability checks and suitable file responses. Expose only the metadata/URLs needed by the UI; keep private filesystem paths out of Resources. A document download is not an Inertia page response.
- Distinguish generation, conversion, confirmation, and publication results. Define which failure blocks the business operation and which derived output may be retried independently. Do not impose one all-or-nothing policy on every domain; Surat explicitly allows publication when official Word succeeds but official PDF conversion fails.
- Keep queued or external processing bounded and retryable according to its operation contract. Do not hold database locks through a slow external conversion by default; design the state check and final persistence boundary explicitly. Dispatch work dependent on committed data only after that data commits.
- Queued Surat previews use `ManageLetterPreview`, `RunLetterPreview`, and durable `LetterOperation` state. Render outside aggregate locks, fence completion with a lease, then recheck revision/source/template/font state before attaching an artifact. Browser render acknowledgment and human confirmation are separate, per-actor records bound to the same evidence fingerprint.
- `RunLetterIssuance` renders a checked snapshot outside locks, rechecks the candidate under template → letter → draft → classification → operation locks, then commits claim/counter/version/artifact/audit/state together. Only the automatic counter in `letter_number_periods` advances; manual claims do not move it. Partial unique indexes protect active claims, and used classification codes cannot be changed. `RenderLetterOperation` serves preview and official generation with explicit draft styling.
- On-demand Surat PDFs use `ManageLetterPdf`, `RunLetterPdf`, and `ConvertLetterPdf`. Convert only a verified working copy of an existing Word artifact, outside aggregate locks, with an isolated LibreOffice profile and the shared Fontconfig environment. Fence the final attachment with a lease and recheck source/profile/state. Official PDFs are frozen per version; draft PDFs require new examination after relevant changes. Keep DOCX examination valid when optional PDF conversion fails. `letter_pdf_requests` binds actor/idempotency keys to shared PDF work and permits subscriber-only SSE/status access.
- Mount Surat operation notifications once in the persistent authenticated layout. Use PostgreSQL LISTEN/NOTIFY as a wake-up signal after transactional status writes, with durable reconnect snapshots scoped to the actor. Keep listener connections separate from transactions and close them on stream rotation. Refresh only relevant preview metadata; never reset an open editor from a background event.
- Cleanup must distinguish temporary/test outputs from historical sources and official documents. Follow `CleanupLetterTemplateFiles` for reference checks, expiry, and safe handling of in-flight files; do not apply template preview retention to issued letters. Specify scheduling and concurrency protection when adding a cleanup operation.

## Shared search and filters

Every new model with the same listing search behavior should use `App\Models\Searchable` and define `protected searchableColumns(): array`. Keep the non-empty allowlist in application code. Choose only the text columns the feature is intended to search; do not infer it from `$fillable` or request parameters.

For example, `LetterNumber` declares:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LetterNumber extends Model
{
    use HasUuids, Searchable;

    protected $fillable = ['classification_name', 'classification_code', 'page_number', 'sequence_number'];

    /** @return non-empty-list<string> */
    protected function searchableColumns(): array
    {
        return ['classification_name', 'classification_code'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
```

The controller normalizes the keyword for its response and delegates the query to the scope:

```php
$keyword = LetterNumber::normalizeSearchKeyword($request->query('keyword'));

$records = LetterNumber::query()
    ->search($keyword)
    ->orderBy('classification_code')
    ->paginate($perPage)
    ->withQueryString();
```

- The trait trims strings, ignores non-string and fewer-than-two-character keywords, compares lowercase text, and binds a literal substring pattern. `%`, `_`, `!`, backslashes, and quotes remain literal search input. Keep this logic in the trait rather than rebuilding `LOWER(...) LIKE ... ESCAPE '!'` blocks in controllers.
- Use the scope through an Eloquent builder (`->search(...)`). It is implemented with Laravel 13's protected `#[Scope]` method and normalizes its own input, so other callers can also use it directly.
- Allowlisted `relation.column` paths search related records through `whereHas` semantics. Archive declares `['label', 'records.label']`; matching multiple records still returns one archive and keeps the complete record counts. All search alternatives are grouped so other filters still apply.
- Keep feature-specific query validation in its HTTP boundary. Ordinary CRUD listings ignore malformed keyword arrays; archive endpoints retain their existing string and length validation. Using the trait does not replace those endpoint contracts.
- For structured master-data filters, use `App\Http\FilterQuery::parse()` and frontend `parseFilterQuery` / `serializeFilterQuery` from `@/lib/filter-query`. The format is `field:value1,value2|other:value`. Parsing does not authorize field names or validate domain values: each feature must allowlist fields and validate enum values, UUIDs, and bounds.
- Use `buildListingQuery` from `@/lib/listing-query` to change keyword, pagination, or filters while preserving other query parameters, including array values. Reset `page` when keyword or filters change. Resetting filters preserves the keyword and page size; clearing the keyword preserves active filters.
- Keep existing compatibility contracts, such as JobTitle's legacy `groups` input. Archive currently uses separate `module` and `kind` parameters; use the shared query builder without changing its public query format.

## Listing pages and tables

- Compose listing pages with `SectionData`. Supply `searchUrl`, the normalized `defaultKeyword`, and `dataTable`. Use its `filterAction` slot for `DataFilterDialog`; use `OptionSelect` and existing field components for choices.
- Supply `renderButton` and `textButton` when creating records is supported. Omit `renderButton` on read-only listings such as archives. Supply `archiveUrl` only when the feature has a working archive destination; it renders the shared icon link before the search input.
- Reuse `useListingSearch` through `SectionData`. It owns the 500 ms debounce, explicit submit, reset, validation, cancellation, and Back/Forward synchronization. The hook keeps draft input separate from the pending/applied state in `ListingSearch`. Keep retries possible after failure or cancellation, ignore stale completions, and preserve newer typing while responses arrive.
- For listing search, filters, and pagination, use Inertia GET visits with `preserveState: true`, `preserveScroll: true`, `showProgress: false`, and `only: ['resources', 'filters']`. Search additionally uses `replace: true`. Partial reloads must target the same page component and include any extra props that a particular operation needs to refresh.
- Keep table loading through `useTableLoading('resources')` and the existing skeletons. Its tracking supports overlapping requests. The global loading overlay is modal; enabling it during listing updates can steal search focus. Keep its normal page-navigation behavior.
- Use `ResourceDataTable` for paginated resource listings. Pass `data`, `meta`, `columns`, the generated listing URL, and a stable `getRowId` returning the row's UUID (`id` for CRUD rows, `uuid` for archive rows). Optional props include `columnVisibility`, `disabled`, and `emptyMessage`.
- Keep each feature's column definitions and thin table wrapper in `pages/{feature}/components/data-table`. Reuse `createDataTableColumnHelper` from `@/components/data-table`; this project uses TanStack Table 9's configured `createTableHook`, not the v8 `useReactTable` API.
- `ResourceDataTable` owns server pagination, loading, navigation, and footer composition. Use the lower-level `DataTable` only when the workflow needs different behavior, as archive detail record selection does. Keep selection and domain operations in that feature.

For a new CRUD listing, follow the existing composition:

```tsx
<SectionData
    searchUrl={ln.index().url}
    defaultKeyword={filters.keyword}
    renderButton={<Link href={ln.create().url} />}
    textButton="Nomor Baru"
    dataTable={<LetterNumberDataTable data={resources.data} meta={resources.meta} />}
/>
```

Replace the route helpers, labels, and table with the new feature's equivalents. Keep the shared toolbar and table behavior.

## Forms, navigation, and user feedback

- Keep create/edit pages small: page metadata, layout, and one shared feature form accepting an optional record. Follow `EmployeeForm`, `JobTitleForm`, `LetterNumberForm`, or `ReferenceGroupForm`.
- Reuse `FormCard` for title, description, close link, and content; reuse `FormActions` for Simpan/Batal and processing states. Domain fields, initial values, and create-versus-update rules remain in the feature form.
- Staged management pages use `FormCard.step`/`sectioned` and page-owned coordination of the active editor and processing state. Local editor cancel uses `FormActions.onCancel` without navigation. Keep item draft state local until successful save, retain input on errors, and use stable identities for component keys. The detailed visual/editor checklist is in FEATURE_WORKFLOW.md.
- Keep a dynamic editor’s opened field definition and local values until it closes or the operator explicitly reloads. A conflict response may carry a newer template or a published letter; retain the opened resource as well as its field definition so the editor is not unmounted before the operator can copy unsaved input. LetterForm demonstrates this alongside the revision guard.
- Use `useValibotForm` with schemas under `resources/js/schemas` and `defineRequest` from `@/lib/request`. Its submit path sends validated/transformed data and maps field errors. Server Form Requests remain authoritative; align client validation and Indonesian field labels with them.
- Keep store and update payloads distinct when writable fields differ. Letter-number identity forms exclude legacy counters entirely. Its detail page prepares monthly periods through `PrepareLetterNumberPeriod`, locking the classification and copying legacy values once, with `initial_period_id` as the migration marker. The period table retains its nine-column contract; duplicate periods and invalid positions are also rejected by PostgreSQL constraints. Disabled inputs alone do not protect data.
- Letter option fields set `remote.preserveSelectedLabels` to keep snapshot labels while resolving new options; ordinary master forms retain current-label behavior. The cached master record is shared within one draft, pruned when no longer selected, and replaced only by explicit refresh or a genuinely new selection.
- Prevent repeated submission while processing, disable relevant controls, render field errors, and report success/error through the existing `gooeyToast` wrapper. Reuse `OptionSelect` and `DatePickerInput` for their supported field types.
- Use Wayfinder named-route helpers from `@/routes` for links and submissions. Pass the generated action's `method` and `url` to the form helper. The Vite plugin currently generates routes with controller actions and form variants disabled; do not assume `@/actions` or `.form()` helpers are generated. Do not hand-edit generated files.
- Use Inertia `Link` for application navigation. Keep React page metadata and navigation labels consistent with Indonesian product language: Arsip, Tambah, Ubah, Simpan, Batal, Pulihkan, and Hapus Permanen. Retain established domain names where appropriate.
- Follow the local Base UI component APIs (`render` and `nativeButton` where supported), the `base-vega` configuration, and semantic tokens in `resources/css/app.css`. Reuse the existing button variants and `gooeyToast` wrapper; skill examples using Radix `asChild`, a different toast system, or custom button colors do not override project composition.
- For icon row actions, reuse `TableActionButton`: it owns `icon-sm` sizing, tooltip, accessible label, and optional Inertia link composition. Include the record name in `label`. Reuse existing Hugeicons and button variants; archive actions use `InfoIcon`/`info`, `DataRecoveryIcon`/`approve`, and `ShredderIcon`/`destructive`.
- Use `DeleteResourceAction` for the standard redirect-based resource delete flow. The feature supplies the generated URL, record name, confirmation text, and result messages. The shared component owns confirmation, processing, duplicate-operation locking, request, and toast handling. Its optional `data`, `disabled`, and `onOpenChange` props support domain revision/confirmation payloads and page-owned operation coordination; ordinary CRUD keeps an empty payload.
- Keep confirmation copy aligned with persistence behavior. Reference group deletion moves the group and items into the archive; Employee, JobTitle, and LetterNumber currently delete permanently. Do not promise recovery for a feature without archive support.
- Reuse `useIsMobile` for responsive behavior. It reads `matchMedia` on the first browser render, listens for changes at the 768 px breakpoint, and guards access to `window` for server rendering. Avoid a separate initially-desktop state that causes mobile layout flicker.

## Archive workflows and new archive modules

The archive is a snapshot workflow, separate from ordinary CRUD deletion. Its current creation and restore implementation supports Reference groups and items. A module name appearing in the central archive list does not make that module restorable.

- Store archive metadata in `Archive` and versioned entity snapshots in `ArchiveRecord`. Preserve source identifiers, parent relationships, actor display name, counts, revision, and schema version according to the existing snapshot contracts. Create snapshots and remove active records in one transaction, with the existing locking discipline.
- Use `ArchiveReference`, `ReferenceSnapshot`, `ReadArchive`, `PreviewArchive`, `ArchiveConfirmation`, `RestoreReference`, and `DeleteArchivedRecords` as the concrete references. Keep domain orchestration in `app/Services/Archive` and the operation controllers small.
- Preserve preview → confirmation token → execution for restoration and permanent archive deletion. The token binds the actor, archive, normalized selection, and state fingerprint. Verify it against current state under transaction locks; reject stale selections and previews. Client operation locks complement this server validation.
- Keep package, individual-record, and selected-record modes explicit. Scope selected UUIDs to the archive; preserve partial success/conflict reporting, remaining records, and counts. Do not expose raw snapshot payloads or internal state through listing Resources.
- Reuse archive-specific restore/delete hooks, dialogs, and workbench components under `pages/archive/components`. Permanent archive deletion has its own preview/token flow and must not be replaced by `DeleteResourceAction`.
- Keep the central and Reference archive listings on `SectionData`, `DataFilterDialog`, `OptionSelect`, and `ResourceDataTable`, without an add button. Reference context fixes the module, hides its selector/column, and preserves context in detail and restore links. Keep the restore anchor and operation disabled states.
- A new archive-enabled module needs its own snapshot schema, conflict rules, restore implementation, selection semantics, and tests. Register supported entities in `ArchiveEntityRegistry` and extend the current Reference-specific preview, selection, confirmation, and UI behavior as needed. Adding a handler or an `archiveUrl` alone is insufficient.

## Database, dependencies, and testing

- PostgreSQL is the application and database-test target. Do not reintroduce SQLite assumptions or the removed Turso integration. Keep database-specific behavior covered on PostgreSQL, including case/literal search, JSON snapshots, constraints, and concurrency where relevant.
- Dependency changes require approval. Include the corresponding `composer.lock` or `pnpm-lock.yaml` changes produced by the package manager. Generated routes, build output, SSR artifacts, and local environment files remain untracked according to `.gitignore`.
- Configure a disposable test database using `.env.testing.example` copied to the ignored `.env.testing`; generate its test key with `php artisan key:generate --env=testing` and set the local connection details. Do not copy application credentials into documentation.
- The main runner uses `tests/bootstrap.php`: process `TEST_DB_*` overrides take priority over `.env.testing`, the driver is forced to `pgsql`, and it accepts only `tensura_test` / `tensura_archive_test` names with permitted suffixes. It rejects cached application configuration and does not fall back to the application database. PHPUnit fixes locale to `id` and timezone to `Asia/Makassar`.
- The opt-in archive integration runner uses `phpunit.archive.xml` and `tests/archive-bootstrap.php`, requires `ARCHIVE_TEST_DRIVER=pgsql` and a disposable `ARCHIVE_TEST_DATABASE=tensura_archive_test_*`, and takes connection settings from `ARCHIVE_TEST_*`. Run it for archive concurrency changes; concurrency cases skipped by the ordinary suite are not evidence those cases passed.
- The template concurrency runner uses `phpunit.letter-templates.xml` and `tests/letter-template-bootstrap.php`. It requires `LETTER_TEMPLATE_TEST_DATABASE=tensura_test_letters_*`, reads connection settings from `LETTER_TEMPLATE_TEST_HOST`, `LETTER_TEMPLATE_TEST_PORT`, `LETTER_TEMPLATE_TEST_USERNAME`, and `LETTER_TEMPLATE_TEST_PASSWORD`, and rejects cached application configuration. Use a dedicated disposable database; it is not the main `TEST_DB_*` or archive runner.
- Follow `testing-best-practices` and the existing PHPUnit 12 style. Prefer feature tests with factories and `LazilyRefreshDatabase` for new database-backed tests; keep existing test conventions when editing them. Assert observable results, persisted state for writes, and important failure modes. Do not create ad hoc verification scripts when tests already cover the behavior.
- For listing changes, cover allowed search columns and exclusions, trim/minimum length, malformed inputs per endpoint contract, case/literal matching, combined filters, counts, stable ordering, and retained query parameters as applicable. Reuse `ListingSearchTest`, feature index tests, and the frontend listing-state/query tests rather than repeating every matrix in every layer.
- For archive changes, cover selection scope, conflicts, stale/wrong-user confirmation tokens, partial restore, permanent deletion, and concurrent changes when affected. For form changes, cover protected fields as well as visible validation.
- For coordinated workflows, cover permitted/forbidden transitions, foreign children, stale state/evidence, important master-data changes, atomic audit/state writes, and preservation of historical values. When files or retries are affected, cover failed storage, rollback/compensation, expired/unreferenced outputs, and retry without duplicate effects. Test actual document structure with representative fixtures where the renderer is the behavior under test.
- Frontend tests use `vite-plus/test` and `vitest.config.ts`; run relevant existing state, schema, or render tests for behavior changes. Pure copy, styling, and layout changes do not need new tests. Inspect focus, slow requests, Back/Forward, keyboard labels, and mobile layout in a real browser when relevant, and report if that verification is unavailable.

## Adding or changing a feature

1. Read applicable project rules and nearby implementations. Activate the relevant skills, confirm installed package versions, and use Boost `search-docs` before relying on framework APIs. Inspect the schema before changing models or migrations.
2. Generate Laravel files using `php artisan make:... --no-interaction`. Follow existing directories; obtain approval before adding a new base folder or changing dependencies. Introduce architectural layers only for a concrete need.
3. Define authentication and any required authorization, route names and UUID bindings, writable fields, validation, Resource shapes, filters, redirects, and deletion semantics. Add `Searchable` with the intended columns for searchable listings.
4. Compose the feature from existing listing, table, form, and action modules. Add Wayfinder route usage, page identity, navigation, Indonesian labels, and frontend types. Keep domain-specific fields, columns, and operations local to the feature.
5. Add meaningful coverage using factories and the isolated PostgreSQL database. Cover success, invalid input, duplicate/current unique values, guests, missing or foreign UUIDs, protected fields, and relevant listing/archive contracts. Delete tests must verify that unrelated records remain.
6. Run the affected tests. Run Pint when PHP changes. Regenerate Wayfinder helpers when route signatures change; the Vite plugin also generates them during development/build. Run relevant frontend tests, lint, type checking, and a production build when frontend code changes. Report unrelated failures and unperformed verification separately.

Current backend checks:

```sh
php artisan test --compact tests/Feature/ListingSearchTest.php tests/Feature/LetterNumberIndexTest.php
php artisan test --compact tests/Feature/LetterNumberEditTest.php tests/Feature/LetterNumberDeleteTest.php
vendor/bin/pint --dirty --format agent
```

Additional checks when relevant:

```sh
php artisan wayfinder:generate --no-interaction
pnpm exec vp test run --config vitest.config.ts resources/js/lib/listing-search.test.ts resources/js/lib/listing-query.test.ts
pnpm run lint
pnpm run types:check
pnpm run build
pnpm run build:ssr
```

Choose the test files for the changed feature; the commands above are examples, not a requirement to run unrelated tests. Run `build:ssr` when SSR behavior or configuration is affected.

For archive database/concurrency work, configure a dedicated disposable database and run:

```sh
ARCHIVE_TEST_DRIVER=pgsql ARCHIVE_TEST_DATABASE=tensura_archive_test_local vendor/bin/phpunit -c phpunit.archive.xml
```

Set `ARCHIVE_TEST_HOST`, `ARCHIVE_TEST_PORT`, `ARCHIVE_TEST_USERNAME`, and `ARCHIVE_TEST_PASSWORD` as needed for the local test server. For documentation-only changes, check the updated references, examples, formatting, and diff; application tests and builds are unnecessary.

For template concurrency changes, configure its dedicated disposable database and run:

```sh
LETTER_TEMPLATE_TEST_DATABASE=tensura_test_letters_local vendor/bin/phpunit -c phpunit.letter-templates.xml
```

Set the `LETTER_TEMPLATE_TEST_*` connection variables listed above as needed. Do not claim skipped concurrency cases passed through the main suite. Browser interaction checks and visual inspection of generated DOCX/PDF are separate evidence from backend tests and production builds; report what was actually performed.
