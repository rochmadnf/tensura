# New Feature Workflow (Tensura)

Agents **must** read this before creating a new feature/module. Simple CRUD reference: **JobTitle** (Jabatan). Use **LetterNumber** for fields protected on update, **Employee** for relation/date filters, **Reference/Archive** for recoverable deletion, and **Letter Templates (Templat Surat)** for staged management pages, per-card editors, private documents, publication, and timelines.

Detailed backend contracts (Form Request, Resource, routing, list queries) are in [LARAVEL_PATTERNS.md](LARAVEL_PATTERNS.md). This document governs work order, interviews, naming, frontend, skills, and verification. Do not duplicate the content of LARAVEL_PATTERNS.md.

The primary rules are in [AGENTS.md](AGENTS.md) and [CLAUDE.md](CLAUDE.md), which are generated from `.ai/guidelines`. If this guide or a skill example conflicts with those project rules, follow the project rules. `boost.json` is the allowlist of local skills; use only skills that are available in the session and within their usage limits.

> Language note: all user-facing text (labels, messages, toasts, placeholders, page titles) stays in **Indonesian**, as the project requires. Indonesian strings below are therefore kept verbatim, with English glosses in parentheses where helpful.

## 0. Principles

1. **Confirm requirements before implementing.** Read the specification and decisions already given, then clarify only the product decisions that are still unclear. Approval already given remains valid; do not ask for re-approval of the same work. For a feature that still needs an interview, agree on the section 1 summary before any implementation that depends on it. Fixes and documentation updates that were already requested do not need a new-feature interview.
2. **Do not ask what can be read from the code.** If `.codegraph/` is available, use `codegraph_explore` or `codegraph explore` before searching or reading code. Check `resources/js/components/app-sidebar.tsx`, `routes/web.php`, `database/migrations`, and `php artisan route:list`. Ask only what is still unknown.
3. **One topic per question**, include a default recommendation, and offer short answer choices when possible.
4. **Prefer shared components and the appropriate domain contracts.** Choose references based on need; legacy differences that must be preserved are recorded in section 8.
5. Do not refactor existing features, add dependencies, or create new base folders without approval.
6. Activate the relevant skills in each phase (section 5) and read the reference feature before imitating its pattern.
7. Separate **agreed business process**, **already-implemented patterns**, and **technical designs not yet built**. For the Letter (Surat) feature, read the [Surat business process](docs/planning/letter/probis.md); do not assume the Surat flow exists just because Letter Template test generation already works. A feature's business decisions are not copied into universal rules for all modules.

## 1. Interview

Fill in the decisions below from the user's request, earlier sessions, and existing code. Ask only about what cannot yet be determined; not every item needs its own question. An interview skill may be used if available and relevant, without repeating information already agreed.

### A. Sidebar and page naming

1. **Check whether the feature's menu already exists in `app-sidebar.tsx`.**
    - **Exists** → read that item (`id`, `title`/`name`, group, `url`). The page name follows the menu name, unless the user specifies another name.
        - Index page `pageName` = menu name. Create page = `Tambah {menu name}`, edit page = `Ubah {menu name}`. The controller's `$pageId` = the sidebar item's `id`.
        - If the user specifies a different page name, keep the menu name unless a menu change is also requested.
        - If `url` is still `'#'`, replace it with `{alias}.index().url`.
    - **Does not exist** → ask: sidebar group (Data Master / Pegawai / new group), menu name, icon (search `@hugeicons/core-free-icons`, skill `hugeicons`), and ordering position. Create a new UUID `id`; the same value is used as `$pageId` in the controller and in the sidebar item.
2. **Technical names**: model name (English, singular, PascalCase), URL slug (kebab-case), route alias (short and meaningful like `jt`, `ln`, `empl`; must be unused, check `route:list`), and domain namespace (`MasterData` for master data).
3. **Check whether the table already exists.**
    - **Yes** → read the schema with the `database-schema` tool. If legacy data needs a `uuid` column, follow the `add_uuid_to_job_titles_table` migration (add nullable, backfill existing data, change to NOT NULL).
    - **No** → continue to section B.

### B. Data

4. **Column list**: name, type, length, nullable, unique, default, and Indonesian label for each column.
5. **Any fixed-choice (enum) columns?**
    - **Yes** → create `App\Enums\{Name}Enum` (int-backed, `label()`, `options()` like `JobTitleGroupEnum`). Ask for the values and labels.
    - **No** → continue.
6. **Any relations to other tables?** If so: relation type, foreign key column, and `onDelete` behavior. Relations use numeric ids; UUIDs are only for URLs and Resources.

### C. Behavior

7. **Which operations?** Default: index, create, store, edit, update, destroy (no show).
8. **Which columns must not change on edit?** Those columns are not accepted by `Update{Name}Request`. LetterNumber now stores identity through the create/edit form; the legacy counters are only displayed during the transition and initial numbers are managed in the per-period detail, not in the identity form.
9. **Deletion behavior**: permanent, recoverable archive snapshot, or a separate soft-delete need. Simple CRUD follows the permanent deletion of JobTitle/LetterNumber if there is no recovery need. Reference uses archive snapshots, not `SoftDeletes`. If an archive is needed, define the entity/parent-child, restoration, conflicts, and permanent deletion of archives; follow the archive section in LARAVEL_PATTERNS.md. Schema/behavior changes outside the agreed scope require approval.
10. **Special validation**: unique, format, range, error messages when different from the defaults.
11. **Authorization**: login is enough (default), or is a role/per-record policy needed?

For features with a lifecycle beyond CRUD, also summarize statuses and transitions, actors, the version/data used as the reference, examination conditions, reasons for actions, resulting documents, and the success boundary of the operation. Determine draft behavior, master changes, data from other operators, retries, and partial failures from the specification; do not infer them from button labels. Use the Letter Template/Archive domain services as references for mechanisms, not to copy their business decisions.

### D. List page

12. **Table columns** to display, text columns searched by keyword (including relations if needed), a deterministic default ordering, and whether a detail page exists. Register search columns in `searchableColumns()` via the `App\Models\Searchable` trait.
13. **Are filters needed?**
    - **Yes** → which fields, option source (enum → `options()`), single or multi. Query format: `filter=group:1,2|field:x`. Follow `JobTitleController::index` (`FilterQuery::parse`, values validated against the enum) and `DataFilterDialog` + `OptionSelect`.
    - **No** → keyword and pagination only.

### E. Wrap-up

14. **Seeder/initial data needed?** If so, use `updateOrCreate` so it can be re-run and register it in `DatabaseSeeder`.
15. **Documentation/changelog** is created or updated only if the user asks; write `CHANGELOG.md` entries in Indonesian. Do not make extra documentation a condition for finishing a feature.

### Decision summary (required)

Summarize feature name, `pageId`, page name, namespace, route alias, columns, enums, relations, operations, columns protected on edit, deletion/recovery type, validation, authorization, list/search columns, ordering, filters, and seeders. Distinguish user decisions, patterns read from code, and implementation assumptions. If the specification is complete and the work is authorized, proceed without asking for re-approval; if a product decision is still unclear, ask for an answer while continuing checks that do not depend on it.

## 2. Naming rules

Examples use JobTitle (`job-title`, alias `jt`).

| Item            | Pattern                                                                                                                                                                                                                               | Example                                                     |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Model           | `App\Models\{Domain}\{Name}`; `HasUuids` with `uniqueIds()` → `['uuid']`; `$fillable`; protected `casts()`; `HasFactory` and `#[UseFactory]`. Searchable listings use `Searchable` and `searchableColumns()`                          | `App\Models\MasterData\JobTitle`                            |
| Factory         | `Database\Factories\{Name}Factory`; set `$model` if the model is in a sub-namespace                                                                                                                                                   | `JobTitleFactory`                                           |
| Migration       | plural snake_case table; `id()`, UUID column, `timestamps()`; indexes/constraints per the contract and actual schema (section 8)                                                                                                      | `create_job_titles_table`                                   |
| Controller      | `App\Http\Controllers\{Domain}\{Name}Controller`; `protected string $pageId`                                                                                                                                                          | `JobTitleController`                                        |
| Request         | `Store{Name}Request`, `Update{Name}Request`; `authorize()` → `$this->user() !== null`; `attributes()` in Indonesian; rules use `bail`                                                                                                 | `StoreJobTitleRequest`                                      |
| Resource        | `App\Http\Resources\{Domain}\{Name}Resource` with `@mixin`; `id` = `uuid`                                                                                                                                                             | `JobTitleResource`                                          |
| Enum            | `App\Enums\{Name}{Attribute}Enum`, `int`, `label()`, `options()`                                                                                                                                                                      | `JobTitleGroupEnum`                                         |
| Route           | `Route::resource('job-title', X::class)->except('show')->parameters(['job-title' => 'jobTitle'])->scoped(['jobTitle' => 'uuid'])->names('jt')`; route names `jt.index`, `jt.create`, `jt.store`, `jt.edit`, `jt.update`, `jt.destroy` | `routes/web.php`                                            |
| Pages           | `resources/js/pages/{slug}/{index,create,edit}.tsx`                                                                                                                                                                                   | `pages/job-title/index.tsx`                                 |
| Table component | `resources/js/pages/{slug}/components/data-table/{index,column,delete-action}.tsx`                                                                                                                                                    | `JobTitleDataTable`, `columns`, `DeleteAction`              |
| Feature form    | `resources/js/pages/{slug}/components/{slug}-form.tsx`; shared by the create/edit pages                                                                                                                                               | `JobTitleForm`, `LetterNumberForm`                          |
| Schema          | `resources/js/schemas/{slug}.ts` using `defineRequest` with Indonesian labels                                                                                                                                                         | `createJobTitleSchema(groups)`                              |
| Route helper    | import `@/routes/{alias}` (Wayfinder, `actions: false`); never write URLs by hand                                                                                                                                                     | `import jt from '@/routes/jt'`                              |
| Test            | `{Name}IndexTest`, `{Name}StoreTest`, `{Name}EditTest`, `{Name}DeleteTest` in `tests/Feature`                                                                                                                                         | `JobTitleStoreTest`                                         |
| Page title      | index = sidebar menu name; `Tambah {name}`; `Ubah {name}`. Add button: `{name} Baru`                                                                                                                                                  | `Jabatan`, `Tambah Jabatan`, `Ubah Jabatan`, `Jabatan Baru` |

All text shown to users (labels, messages, toasts, placeholders) is in Indonesian. Toasts: success `Berhasil`, error `Galat`.

## 3. Implementation order

1. **Read and inspect**: `AGENTS.md`, `CLAUDE.md`, LARAVEL_PATTERNS.md, the reference feature, `routes/web.php`, `app-sidebar.tsx`, and the database schema. If `.ai/rules` is available, read `index.md`, all rules with matching globs, then search that directory for related keywords before editing. Confirm package versions via Composer / `package.json` and use Boost `search-docs` for needed framework APIs. Do not copy examples from different versions.
2. **Backend** (use `php artisan make:... --no-interaction`; check options with `--help`):
    - migration → enum (if any) → model + factory → `Store`/`Update` request → resource → controller → route → seeder (if requested).
    - For searchable listings, use `Searchable`, an explicit column list, `normalizeSearchKeyword()` for the `filters.keyword` prop, and `->search($keyword)` on the query. Do not rewrite search SQL in the controller. Add ordering and pagination per LARAVEL_PATTERNS.md.
    - For archives, implement snapshot, record selection, conflicts, preview/token, restoration, and required transactions/locking before wiring the UI. A handler registry alone does not make a module recoverable.
    - For staged flows, separate domain operations into `app/Services/{Domain}` following Letter Templates: request → operation controller → service → model/storage → Resource/redirect. Keep simple CRUD without extra services if not needed. The contracts for state, transactions, examination evidence, audit, and file compensation follow LARAVEL_PATTERNS.md.
    - `php artisan make:model {Domain}/{Name} -mf --no-interaction`, `make:controller`, `make:request`, `make:resource`.
3. **Wayfinder**: `php artisan wayfinder:generate --no-interaction` whenever a route signature changes. The Vite plugin also runs the generator during development/build; the current config is `routes: true`, `actions: false`, `formVariants: false`. Do not edit generated files or rely on helpers that are not generated. The `resources/js/routes` folder is gitignored.
4. **Frontend**: schema and types → columns → `DeleteResourceAction` wrapper → `ResourceDataTable` wrapper → shared feature form → `index`/`create`/`edit` (and `show` if needed) → update the sidebar item.
    - Inertia pages must exist as files before `assertInertia` tests pass (`ensure_pages_exist` is enabled).
    - For management pages, build cards and operations from backend state; use the editor contract in section 4. Do not force all version/document management into one large create/edit form.
5. **Tests** (section 6).
6. **Verification** (section 7).

### Sidebar

- Item `id` = controller `$pageId` (used by `NavGroups`/`NavMain` for active state via `page.id`).
- `url` = `{alias}.index().url`.
- Icon renderer: `HugeiconsIcon` from `@hugeicons/react`; icon data from `@hugeicons/core-free-icons`. Verify the correct icon name via the `hugeicons` skill.
- The controller sends `'page' => ['id' => $this->pageId]` in every `inertia(...)` call.

## 4. Frontend rules

- **Toolbar**: use `SectionData` with `searchUrl`, `defaultKeyword={filters.keyword}`, and `dataTable`. The add button goes through `renderButton`/`textButton` only if the operation is available; filters go through `filterAction`. Fill `archiveUrl` only if the archive destination is supported.
- **Search**: `SectionData` already uses `useListingSearch` and the `ListingSearch` state. Use the existing behavior: 500 ms debounce, submit/reset, request cancellation, retry, preservation of typing, and Back/Forward synchronization; do not create a second search state on the page.
- **Table wrapper**: follow `job-title/components/data-table/index.tsx`, which passes `data`, `meta`, `columns`, `url`, and `getRowId` to `ResourceDataTable`. The shared component handles server pagination, loading, and footer. Use the record UUID as the row ID, not the row index. Use `DataTable`/`useDataTable` directly only when a special flow requires it, such as archive detail record selection.
- **List requests**: search, filters, and pagination use `buildListingQuery`, `preserveScroll: true`, `preserveState: true`, `showProgress: false`, and partial reload `['resources', 'filters']`; search also uses `replace: true`. Preserve other parameters and reset the page when the keyword/filter changes. Reuse `useTableLoading('resources')`; the modal navigation overlay must not take focus during list updates.
- **Filters**: use `DataFilterDialog` and `OptionSelect`. Draft values are applied on Terapkan (Apply); Reset clears the filter draft and does not immediately change results. Follow the shared parser/serializer and validate domain values per LARAVEL_PATTERNS.md.
- **Columns and actions**: define columns outside the component via `createDataTableColumnHelper` (TanStack Table v9). Row numbers follow `Subscribe` to `table.atoms.pagination`. Use `TableActionButton` for edit (`warning`, `Edit04Icon`), detail, and other icon actions; include the record name in the accessible label. `DeleteAction` is a thin wrapper around `DeleteResourceAction` with domain URL and text.
- **Create/edit forms**: combine fields in `{Name}Form` with an optional record; pages only manage metadata and props. Use `FormCard`, `FormActions`, `useValibotForm`, the `defineRequest` schema, and `FieldGroup`/`Field`/`FieldLabel`/`FieldError`. Fields and domain rules remain owned by the feature form. If `submit()` returns `false`, show an error toast; prevent double submit while processing. Protected fields may be shown disabled/readOnly, but must be excluded from the update payload and the backend allowlist.
- **Delete and archive**: use the dialog, locking, request, and toast from `DeleteResourceAction` for ordinary resource deletion. Reference messages must explain the move to the archive. Archive restoration and permanent deletion keep using dedicated hooks/dialogs with preview/token; do not replace them with the CRUD delete dialog.
- **Pages**: `<Head title>` + `meta description` in Indonesian, and `Page.layout = { pageName }`. Props types are declared explicitly.
- **Styling (skills `shadcn`, `tailwindcss-development`)**: `gap-*` not `space-*`; semantic tokens (`bg-primary`, `text-muted-foreground`), not raw colors; `size-*` when width = height; icons inside components use `data-icon` without size classes; `cn` from `'cn'`; no manual `z-index` on overlays.
- **UI components**: prefer shared components, then compose `components/ui/*` per the `shadcn` skill. If a new component is truly needed, ask for approval first and place it in `components/`, not `components/ui/`. The `components/ui/` folder is only for primitives; change it only when necessary and within scope.
- **Mobile**: use `useIsMobile`, which reads `matchMedia` from the first browser render, listens for changes at the 768 px breakpoint, and is safe without `window`. Do not duplicate an initial desktop state that causes flicker.
- Formatting follows `.ai/guidelines/skills.md`: Pint for PHP, Oxfmt (`vite.config.ts` → `fmt`) for frontend/documents, and Prettier only for Blade through `pnpm p:format`/`pnpm p:check`. Use `pnpm exec vp fmt <paths>` for scoped frontend formatting. Avoid unused imports.

### Visual consistency and composition

- The theme reference is `components.json` (`base-vega`, Base UI, Hugeicons) and `resources/css/app.css`. Keep typography, radius, control sizes, and shared component variants. Do not add themes/palettes or page-specific CSS just to imitate components that already exist.
- Use `Button`/`TableActionButton` with existing variants: primary `default`, edit `warning`, information `info`, restore/approve `approve`, and destructive actions `destructive`. Choose by the consequence of the action; editor Batal (Cancel) is not the same as cancelling a letter. Follow `FormActions` for editor Simpan/Batal.
- Use the `warning`, `success`, `info`, and `destructive` tokens and their matching foregrounds for status. Use `Badge`, `Alert`, `Skeleton`, and existing UI components; do not apply raw colors or override button colors/sizes per page. Status must have text, not just color.
- The project uses Base UI composition: check the local components' `render`/`nativeButton` props, and do not copy `asChild` from Radix examples. Inertia navigation uses `Link`; document downloads use plain links/file responses, not Inertia page visits.
- Text buttons put the icon in front with `data-icon="inline-start"`; icon actions use an accessible label and a tooltip via `TableActionButton`. `FormActions` in forms uses icon + Simpan/Batal text. Inside a `TableCell` or list item, you must use `FormActions compact` so only icon + tooltip appear; do not use `compact` outside that context. Tooltips are only for icon buttons. All icons use `<HugeiconsIcon strokeWidth={2} … />`. Do not create replacement Simpan/Batal buttons.
- One primary `default` action per page; secondary/toolbar actions use `outline`. Dialog cancel uses `AlertDialogCancel`/`DialogClose`, variant `outline`, text "Batal", no icon. Button sizes are only `default`, `sm`, `icon`, or `icon-sm`; icon buttons in rows must go through `TableActionButton`.
- Badges come from `@/components/ui/badge`, with enum-to-variant mapping in one place. Block messages use `Alert` from `components/reui/alert`. Follow the project's UI rules tables for status and actions.
- Forms follow `FormCard` → `<form noValidate>` → `FieldGroup` → `Field` (`data-invalid`, `data-disabled`) → label, control, and `FieldError`. Required fields use the `required` attribute and the asterisk marker per project rules. While processing, controls and buttons are disabled.
- List/create/edit page wrappers use `container mx-auto`; detail/multi-card pages use `container mx-auto flex min-w-0 flex-col gap-6`. `SectionData` already has a container. The H1 comes from `SiteHeader`; follow the project's heading scale and use `Card`/`Item` for bordered groups.
- Card header actions use the `action` slot on `FormCard` and `CardAction`; allow wrapping on narrow screens. Use `min-w-0`, tables with local horizontal scroll, and card spacing following the reference pages. Pages must not widen because of table content or long placeholders.

### Management pages and per-card editors

- Follow `pages/letter-templates/edit.tsx` for card coordination. `FormCard` supports `step` for numbered stages and `sectioned` for unnumbered sections; use them only when the flow requires. Simple add forms remain immediately fillable.
- Saved cards/items start in read mode. Only one editor or interacting operation dialog is active at a time; the page owner coordinates the active editor and in-flight requests. Field details and input drafts remain owned by the feature component.
- Simpan/Batal appear only while editing. Batal/Escape restores saved values, errors, and file selections without navigation; use `FormActions.onCancel`. Do not nest a form inside a form.
- Adding an item opens a local draft; the server record is created only after Simpan succeeds. Deleting a saved record is a separate operation with confirmation of domain impact. Reorder follows the Reference/Letter Template pattern and preserves item identity.
- Dynamic form editors keep the opened field definitions until closed or the operator explicitly reloads. A conflict response with a newer template version must not drop old fields that may still need to be copied.
- Errors keep the editor and input; success updates saved data and then closes the editor. Use stable keys based on record/version identity, not indexes or input values that would cause remounting while typing.
- Block conflicting actions during edit/request, preserve focus and scroll, and always release the busy state when a request finishes or fails. UI locking helps interaction; the backend still checks state and conflicts.
- Show readiness, reasons an action is held back, validation results, and history from backend props. Detailed validation messages live in the relevant section; toasts are brief feedback. Do not copy publication eligibility formulas or DOCX parsing into the frontend.
- Do not generalize template-specific components before there is real reuse. Use the shared shell first; fields, signer slots, and publication rules remain domain-owned.

## 5. Skill mapping

| Phase                                 | Skill                                                                                                                                                 |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Requirements and design               | Available interview skill when needed; `codebase-design` for module interfaces or seams                                                               |
| Backend                               | `laravel-best-practices`, `laravel-patterns`                                                                                                          |
| Routes and frontend helpers           | `wayfinder-development`                                                                                                                               |
| Inertia pages, forms, navigation      | `inertia-react-development`                                                                                                                           |
| Tables                                | `getting-started`, `table-state` (TanStack Table v9)                                                                                                  |
| Components and styling                | `shadcn`, `tailwindcss-development`, `hugeicons`; `goey-toast` when adding/changing toasts                                                            |
| UX review                             | `ui-ux-pro-max` only for task flows, usability, accessibility, keyboard navigation, feedback, and error recovery; not for UI design or implementation |
| Landing page/public site              | `design-taste-frontend` within project tokens and components; not for application pages or design generation                                          |
| Spreadsheets                          | `laravel-excel`; the package source is in `vendor/maatwebsite/excel`, not the application `src/`                                                      |
| Complex TypeScript types              | `typescript-advanced-types`                                                                                                                           |
| Tests                                 | `testing-best-practices`                                                                                                                              |
| Review of requested branch/PR/changes | `code-review`                                                                                                                                         |
| Convention inference and recording    | `infer-conventions` only if explicitly requested; not an automatic step when a pattern is unclear                                                     |
| Legacy TanStack Table v8 code only    | `migrate-v8-to-v9`                                                                                                                                    |

The catalog of skills available in the session and their triggers is the reference for activation. `boost.json` is the allowlist of local skills; align the discovery directories and symlinks with it without deleting packages in `vendor` or `node_modules`. The configuration list is not proof that every skill is available in the session. Do not invoke or install skills that are not available just to satisfy this table. Self-inspection of the diff is still done before finishing; review with the `code-review` skill is used as requested and per its instructions.

## 6. Minimum testing

Read the `testing-best-practices` skill and the testing exceptions in `.ai/guidelines/skills.md`. Follow the installed PHPUnit version; use `LazilyRefreshDatabase`, factories, `UserFactory::new()->create()` + `actingAs`, and `withoutVite()` as the endpoint requires. Each test creates its own data. Test names state the outcome. Data providers are `public static` with descriptive keys. Choose meaningful coverage for the behavior created/changed; reuse existing regression matrices.

Database tests use a disposable PostgreSQL per `.env.testing.example`, `tests/bootstrap.php`, and the testing section of LARAVEL_PATTERNS.md. Do not use the application database or switch the runner to SQLite. Local configuration is not committed; overrides for the main runner use `TEST_DB_*`, while the archive runner uses `ARCHIVE_TEST_*`.

- **Index**: guests are redirected to `login.index`; component and `page.id`; `resources.data` shape; keyword (trim, minimum 2 characters, case-insensitive, wildcard/escape characters literal, only the defined columns); malformed keyword per endpoint contract; `per_page` limited to the allowlist; stable ordering and query string carried through pagination; filter combinations including invalid values and injection. Use `ListingSearchTest` and the relevant feature index tests.
- **Store**: create page opens; data is saved, `id`/`uuid` from input is ignored; per-field validation with Indonesian messages (empty, too short/long, not a string, invalid enum, duplicate); guests rejected.
- **Edit/Update**: page is populated; only editable columns change (forbidden columns are sent and stay intact); own value accepted, another record's value rejected; unknown UUID and numeric id → 404; guests rejected.
- **Delete**: only the selected record is deleted/archived, other records remain; guests rejected; unknown UUID and numeric id → 404. If archived, check the snapshot, relations, and active data per the contract.
- **Archive**: when affected, test record selection scope, conflicts, wrong-user/wrong-action or expired tokens due to changed state, partial restore, and permanent deletion. Run the separate archive runner for concurrency tests; cases skipped by the main runner are unverified.
- **Staged/document flows**: test allowed/rejected transitions, children belonging to another parent, stale state/examination evidence, rollback, storage failures, history protection, and retry without duplicate effects per the feature contract. For Letter Template concurrency changes, use the dedicated runner `phpunit.letter-templates.xml` per LARAVEL_PATTERNS.md; do not claim skipped cases in the main suite passed.
- **Frontend**: run the affected search/query state, schema, or render tests via `vite-plus/test`. No new tests are needed for pure copy/styling/layout changes. Verify focus, slow requests, and Back/Forward in a browser when relevant.
- Do not mock the database. Prepare prerequisite records before `Event::fake()` if used.

## 7. Verification before finishing

Run checks appropriate to the type of change. Example for CRUD that changes backend and frontend (replace the JobTitle files with the relevant feature):

```sh
php artisan test --compact tests/Feature/JobTitleIndexTest.php tests/Feature/JobTitleStoreTest.php tests/Feature/JobTitleEditTest.php tests/Feature/JobTitleDeleteTest.php
vendor/bin/pint --dirty --format agent
pnpm exec vp fmt resources/js/pages/job-title
pnpm run check:ui
pnpm run check
pnpm run types:check
pnpm run build
```

- Run the relevant frontend test files with `pnpm exec vp test run --config vitest.config.ts <test-file>`. Regenerate Wayfinder when routes change; run `pnpm run build:ssr` if SSR is affected. Commands and configuration for the archive runner are in LARAVEL_PATTERNS.md.
- Re-run tests after modifying them. Pint is mandatory when PHP changes. For documentation-only changes, just check references, Markdown formatting, and `git diff --check`; no application tests or build are needed.
- For UI changes, `check:ui` must be clean on changed files: fix errors and warnings in touched files. Also run `types:check` and `check`. If the appearance changes, state which components were used and whether any new component was created.
- `types:check` remains a full-project check. Do not consider it passing if errors remain; separate errors caused by the change from pre-existing problems and report them clearly.
- Check manually when UI changes: active sidebar menu, input focus during requests, search/reset, filter/Apply/Reset, pagination, Back/Forward, add/edit/delete, toasts, loading, keyboard/accessible labels, and mobile viewport. Report if a browser or a particular check is unavailable.
- For staged editors, check read/edit mode, a single active editor, Batal/Escape, save failure, local additions, reorder, actions during slow requests, and re-opened dialogs. Browser checks and opening/rendering DOCX/PDF output are evidence separate from backend tests/build; report each as actually performed.
- Short final report: what was built, what was tested, and what could not be run and why.

### Application version and release notes

- Use Semantic Versioning `MAJOR.MINOR.PATCH`. The running version source is `config('app.version')`; the value is stored without a `v` prefix, while the display uses the prefix. Major stays `0` during MVP development; bumping major requires an explicit user decision.
- After an **agreed implementation stage** is complete and its related verification passes, bump **patch** by one, e.g. `0.1.0 → 0.1.1`. A stage means an implementation unit in the feature plan, not every working step or interim commit.
- After a whole **feature** is complete and its verification passes, including features without stages, bump **minor** by one and reset patch to `0`, e.g. `0.1.4 → 0.2.0`.
- If the last stage also completes the feature, do **one minor bump**; do not make a separate patch bump for the same completion. Discussion, unfinished work, and interim commits do not bump the version.
- If release notes were requested or agreed, in the same completion commit, update the version default in `config/app.php` and add Indonesian release notes at `resources/markdown/releases/MAJOR.MINOR.PATCH.md`, e.g. `0.2.0.md`. The file name determines version identity; write the summary, capability list, and usage notes as plain Markdown without required frontmatter. The **Tentang Aplikasi** (About) page reads those files directly and sorts versions newest first. Keep previous version files; summarize capabilities that are now available, not internal implementation details. `CHANGELOG.md` remains a separate technical record.
- Check the `APP_VERSION` override in the environment in use so it does not mask the new default; update overrides that are explicitly set and reload configuration according to the deployment method. Do not add a separate frontend version source. The **Tentang Aplikasi** page and the sidebar version link follow the shared prop `app.version`.
- Documentation/rule-only changes do not bump the version or create release notes unless requested.
- Creating this version page establishes the baseline **`0.1.0`**, with no extra bump or backward calculation for features/stages already done before.

### GitHub release and pull request workflow

- Use Semantic Versioning (`MAJOR.MINOR.PATCH`) according to the existing release rules. Version bumps and matching Indonesian notes under `resources/markdown/releases/MAJOR.MINOR.PATCH.md` may be prepared and committed on feature branches; their presence alone does not authorize a release.
- Before release or pull request work, verify GitHub CLI installation and authentication with `gh --version` and `gh auth status`. Verify the repository identity and GitHub default branch with `gh repo view`; do not assume it is named `main`.
- Create or push release tags and create or publish GitHub releases only from the detected default branch, after the changes have been merged and the release commit is present on the remote default branch. This gate also applies to draft releases, prereleases, and releases marked `--latest=false`.
- On a feature/backend/frontend branch, complete and verify the work, commit the version bump and notes, and push the branch when authorized. Report that release publication is pending merge. Do not create or push a release tag, publish a release, or merge into the default branch automatically.
- Compare the current branch with the detected default branch. If they differ, do not create a pull request automatically. First present a proposed pull request title and body based on the actual changes and request explicit user approval. After approval, create the pull request targeting the detected default branch.
- After the approved changes are merged, refresh the remote references and check out the default branch. Verify that the selected release commit is already on the remote default branch and that the version and matching release notes belong to that commit. Create and push the matching `vMAJOR.MINOR.PATCH` tag at that commit, then create the GitHub release using its committed release notes.
- Publish only the version represented by the selected default-branch commit. Multiple intermediate stage versions or note files on a feature branch do not authorize releases from those intermediate branch commits. Do not bypass this gate with a feature-branch target or by calling a backend checkpoint a release.
- Do not delete, move, or replace existing published tags or releases as part of a rule update; correcting an earlier publication requires a separate explicit user instruction.
- Documentation/rule-only changes do not bump the application version, add release notes, or create a release unless explicitly requested by the user.
- Do not add Copilot, GitHub CLI, or other assistant attribution to commits, pull requests, release notes, tags, or releases.

## 8. Pattern differences to preserve

| Topic             | Current contract                                                                                                                  | Guidance for new features                                                                                                                              |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Namespace         | JobTitle/Employee/Reference are in the `MasterData` domain; LetterNumber is directly in `App\Models` / `App\Http\Resources`       | Follow the feature's domain and existing folders; do not move old features for uniformity                                                              |
| Route             | JobTitle uses resource routes; LetterNumber keeps explicit routes, including `/store`                                             | Resource routes with UUID names and binding when they fit the need; keep old URLs                                                                      |
| Search and tables | All four CRUDs and the archive list use `Searchable`, `SectionData`, and `ResourceDataTable`                                      | Use the shared abstractions; there is no longer a reason to imitate LetterNumber's old manual query/pagination                                         |
| Resource identity | CRUD uses `id` containing the UUID; archive uses `uuid`                                                                           | Align frontend types and `getRowId` with the chosen contract                                                                                           |
| Database UUID     | JobTitle/Employee/LetterNumber use plain indexes; Reference and archive tables already use unique constraints                     | Check the actual schema; decide constraints based on invariants, including restore/concurrency needs; do not change old features' indexes without need |
| Update form       | LetterNumber create/update only handles name/code; periods use a separate preparation operation; legacy counters kept temporarily | One feature form for create/edit with payload and validation per operation                                                                             |
| Delete            | JobTitle/Employee/LetterNumber permanent; Reference uses archive snapshots                                                        | Decide recovery needs; use the appropriate backend flow and dialog                                                                                     |

- `Employee` already has a factory/`HasFactory`, protected `casts()`, and tests for relation/date filters. Use it as the reference for those needs.
- Old features are not changed merely for uniformity unless requested.
