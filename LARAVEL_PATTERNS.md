# Laravel patterns for Tensura

Use this guide when adding or changing a Tensura feature. It describes the implemented backend and frontend patterns, including shared search, tables, forms, archive workflows, and PostgreSQL testing. Follow these defaults for new modules and preserve existing contracts when refactoring.

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
| Database test isolation                       | [Main bootstrap](tests/bootstrap.php), [Archive bootstrap](tests/archive-bootstrap.php), [.env.testing.example](.env.testing.example)                                                                                                                        |

Use the current source as the reference for component props and contracts. Documents under `docs/planning` retain implementation history; they are not substitutes for these shared modules.

## Responsibilities

| Layer                       | Owns                                                                          | Letter-number example                                   |
| --------------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------- |
| Routes                      | HTTP methods, names, authentication, model binding                            | `routes/web.php`, `ln.*` routes                         |
| Controller                  | Coordinate requests, persistence, resources, and redirects                    | `LetterNumberController`                                |
| Form Request                | Authorization, server validation, translated field labels                     | `StoreLetterNumberRequest`, `UpdateLetterNumberRequest` |
| Model                       | Persistence, mass assignment, relationships, casts, search columns and scopes | `LetterNumber`, `Searchable`                            |
| Resource                    | Explicit data exposed to the frontend                                         | `LetterNumberResource`                                  |
| Page and feature components | Domain fields, columns, filters, routes, and presentation                     | `pages/letter-number`, `pages/employee`                 |
| Shared frontend             | Listing navigation, table setup, form shell, and action controls              | `SectionData`, `ResourceDataTable`, `FormCard`          |
| Feature test                | Observable endpoint behavior and important failure modes                      | `LetterNumberEditTest`, `LetterNumberDeleteTest`        |

For simple CRUD, use **route → controller → Eloquent**, with Form Requests validating writes and Resources shaping reads. The controller should remain readable without following several one-line wrappers.

Extract an action when an operation gains reusable business rules or multiple coordinated steps. Add a service when several operations need shared orchestration. Use constructor or method injection for those dependencies. Add a transaction for writes that must succeed together. Introduce a DTO when a typed payload actually crosses a domain boundary; validated arrays are sufficient for the current CRUD operations.

An Eloquent repository, generic CRUD base controller, service interface, or shared request superclass needs a concrete reuse or isolation benefit before being introduced. Keep store and update validation explicit because their writable fields and uniqueness rules differ.

## Request and response contracts

- Use `Store{Resource}Request` and `Update{Resource}Request`, with typed `authorize(): bool`, `rules(): array`, and `attributes(): array`. Use PHPDoc array shapes for known returned data and framework-compatible types for rule arrays.
- Use the request's `user()` for request-specific authentication checks. Letter-number currently permits authenticated users; introduce policies when the product needs roles, ownership, or per-record permissions. Authentication and UUIDs do not replace authorization.
- Persist only intended, validated attributes. Keep the model's mass-assignment configuration aligned with the feature. Use framework `validated()` or `safe()->only(...)` directly when a custom method would only return the same data.
- Keep database constraints for unique values. On update, ignore the bound model in `Rule::unique(...)->ignore($model)`; never take the ignored identifier from submitted input.
- Preserve classification codes as strings so `007` remains `007`. Store accepts name, code, page number, and sequence number. Update accepts only name and code, even when a crafted payload includes counters.
- Declare method parameter and return types. Resources expose explicit fields and document their response shapes; avoid returning whole models with incidental columns.
- Generate UUIDs using the existing model conventions (`HasUuids` and `uniqueIds(): array` returning `['uuid']`). Keep numeric primary keys internal. CRUD Resources expose the UUID as `id`; archive Resources expose `uuid`. Preserve that distinction in frontend types and row identifiers.
- Keep enum values and option lists on the backend using the existing `*Enum` naming and `options()` pattern. Translate submitted relationship UUIDs to internal keys only after validation, as Employee does for job titles.

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

- Use standard controller actions: `index`, `create`, `store`, `edit`, `update`, and `destroy`. Prefer resource routes for new resources when they match the intended URLs. Preserve existing explicit URLs and names for letter-number, including `/letter-number/store` and `ln.*`.
- Bind letter-number using `{letterNumber:uuid}`. Its internal primary key remains numeric; the Resource exposes its UUID as `id`. Unknown identifiers return 404 through model binding.
- Scope nested route bindings to their parent, following `reference.items`; a valid child UUID under a different parent must not resolve.
- Render pages from `resources/js/pages` through Inertia and shape records through Resources. Preserve `resource.data` for a single record, and `resources.data`, `resources.meta`, and `resources.links` for a paginated collection. Return normalized active values in `filters` so the client can synchronize its controls.
- Include `page.id` and configure the page's `layout.pageName` using the existing navigation conventions. Use a distinct page identity for a new module rather than copying another module's UUID.
- Keep simple list queries in the controller. Eager load only required relationships and fields, including keys needed to hydrate them. Use `withCount()` when the page needs counts rather than child records.
- Apply explicit, deterministic ordering before pagination. Use a unique final key when the preceding sort fields can repeat. Letter-number sorts by its unique string classification code; JobTitle and Employee finish with `id`.
- Bound `per_page` to `[5, 10, 15, 20, 50, 100]`, defaulting to `10`, and call `withQueryString()` after pagination.
- Combine related filters with their intended semantics. Employee puts job-title UUID and group conditions inside one `whereHas('jobTitle')` with AND semantics. Compare date columns directly when the input is already a date; preserve the existing tenure guard, leap-year handling, and reversed-range behavior.
- Use jobs for slow or retryable external work, and events for meaningful domain side effects. Add caching only when there is a measured need and a clear invalidation strategy.

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
- Use `useValibotForm` with schemas under `resources/js/schemas` and `defineRequest` from `@/lib/request`. Its submit path sends validated/transformed data and maps field errors. Server Form Requests remain authoritative; align client validation and Indonesian field labels with them.
- Keep store and update payloads distinct when writable fields differ. Letter-number counters are editable only during creation, disabled during editing, and excluded by the update validator and server allowlist. Disabled inputs alone do not protect data.
- Prevent repeated submission while processing, disable relevant controls, render field errors, and report success/error through the existing `gooeyToast` wrapper. Reuse `OptionSelect` and `DatePickerInput` for their supported field types.
- Use Wayfinder named-route helpers from `@/routes` for links and submissions. Pass the generated action's `method` and `url` to the form helper. The Vite plugin currently generates routes with controller actions and form variants disabled; do not assume `@/actions` or `.form()` helpers are generated. Do not hand-edit generated files.
- Use Inertia `Link` for application navigation. Keep React page metadata and navigation labels consistent with Indonesian product language: Arsip, Tambah, Ubah, Simpan, Batal, Pulihkan, and Hapus Permanen. Retain established domain names where appropriate.
- For icon row actions, reuse `TableActionButton`: it owns `icon-sm` sizing, tooltip, accessible label, and optional Inertia link composition. Include the record name in `label`. Reuse existing Hugeicons and button variants; archive actions use `InfoIcon`/`info`, `DataRecoveryIcon`/`approve`, and `ShredderIcon`/`destructive`.
- Use `DeleteResourceAction` for the standard redirect-based resource delete flow. The feature supplies the generated URL, record name, confirmation text, and result messages. The shared component owns confirmation, processing, duplicate-operation locking, request, and toast handling.
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
- Follow `testing-best-practices` and the existing PHPUnit 12 style. Prefer feature tests with factories and `LazilyRefreshDatabase` for new database-backed tests; keep existing test conventions when editing them. Assert observable results, persisted state for writes, and important failure modes. Do not create ad hoc verification scripts when tests already cover the behavior.
- For listing changes, cover allowed search columns and exclusions, trim/minimum length, malformed inputs per endpoint contract, case/literal matching, combined filters, counts, stable ordering, and retained query parameters as applicable. Reuse `ListingSearchTest`, feature index tests, and the frontend listing-state/query tests rather than repeating every matrix in every layer.
- For archive changes, cover selection scope, conflicts, stale/wrong-user confirmation tokens, partial restore, permanent deletion, and concurrent changes when affected. For form changes, cover protected fields as well as visible validation.
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
