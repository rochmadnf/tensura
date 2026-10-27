<laravel-boost-guidelines>
=== .ai/database rules ===

## Database

- Local PostgreSQL 18 via mise (data in `.pgdata`, port 5433)
- Use Laravel migrations; avoid raw SQL unless necessary and approved
- Use Postgres-specific features only when there is a clear reason

=== .ai/skills rules ===

## Skill integration

These project rules override conflicting skill instructions and examples.

### Skills activation

- This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain, within the scope allowed by these project rules. Do not wait until you are stuck.

### Installed skills

- `boost.json` is the allowlist for project-local skills. Keep skill directories and discovery symlinks in `.ai/skills`, `.agents/skills`, `.claude/skills`, and `.github/skills` aligned with its `skills` array. Remove obsolete entries and duplicate aliases when the list changes; do not delete the underlying packages in `vendor` or `node_modules`.

### Documentation and API discovery

- An empty documentation search does not prove that a feature is unavailable. Try broader queries, then inspect the installed package source and version before making that claim. If availability remains uncertain, state the uncertainty instead of inventing an API or declaring it unsupported.

### Testing skill exceptions

- Create behavior-specific fakes inside the test that needs them. Global test infrastructure may live in the base `TestCase::setUp()`: `Http::preventStrayRequests()`, `Sleep::fake()`, and `Exceptions::fake()` are exceptions to the testing skill's prohibition on fakes in `setUp()`. Add them only when needed by the suite, and preserve established test conventions.
- Assertions for fakes apply to behavior under test. A global guard against network calls, real sleeping, or external exception reporting does not require an unrelated assertion in every test.

### Laravel Excel skill paths

- This repository is a Laravel application, not the Laravel Excel package repository. Resolve package source paths such as `src/Excel.php`, `src/Concerns/`, and `src/Fakes/ExcelFake.php` under `vendor/maatwebsite/excel/`. Follow this application's conventions for export/import classes and tests.
- The installed skill's `../../references/package.md` and `../../examples/exports-imports.md` references are missing. Do not require these files or claim to have read them. Use the installed package source and available version-matched package documentation instead; disclose any coverage that cannot be verified.

=== .ai/ui rules ===

## UI (read before changing anything in `resources/js`)

Goal: a uniform look. Do not invent new styles; pick from the tables below. If a need is not covered, **ask first** and do not improvise.

Language: this file is in English, but all user-facing copy (labels, tooltips, toasts, page titles, `meta description`) stays in Indonesian. Labels quoted below, such as "Ubah" or "Batal", are the literal strings to use.

### Priority: project rules over skills

This repo ships many skills in `.ai/skills`. If a skill contradicts this file, **this file wins**.

| Skill                                                                  | Use for                                                                                                                                          | Do not use for                                                                                                                                     |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `shadcn`                                                               | Composition rules (`FieldGroup`/`Field`, items inside groups, `Title` on dialogs), `gap-*`, `size-*`, semantic tokens; aligned with this project | Replacing existing components or tokens                                                                                                            |
| `hugeicons`                                                            | Looking up valid icon names                                                                                                                      | —                                                                                                                                                  |
| `ui-ux-pro-max` | UX review only: task flows, usability, accessibility, keyboard navigation, feedback, and error recovery | UI design or implementation; choosing components, layouts, colors, fonts, icons, decoration, or generating a design system |
| `design-taste-frontend` | Art direction for landing pages and public sites, within existing project tokens and components | Application pages; new palettes, fonts, gradients, decoration, or design generators; Radix (`asChild`) and lucide examples |

- The UX-only restriction on `ui-ux-pro-max` overrides its description, workflow, scripts, and examples. Do not activate it for UI implementation. When reviewing UX, report usability findings; use the existing shared components and the appropriate implementation skills (`shadcn`, `tailwindcss-development`, `hugeicons`, and `inertia-react-development`) for any requested UI changes.
- Examples in frontend skills describe APIs, not this project's UI conventions. Adapt Inertia, Wayfinder, and Tailwind examples to shared components, semantic tokens, Indonesian copy, `FieldError`, `FormActions`, and Wayfinder route functions. Do not copy raw controls, hardcoded route URLs, raw colors, or extra page h1 elements.

- This project uses Base UI (`render`, `nativeButton`) and Hugeicons, not Radix (`asChild`) or lucide.
- Colors, fonts, and radii come only from `resources/css/app.css`. Do not run design generators.
- Application pages (lists, forms, details) do not use art-direction skills; those are for landing pages or public sites.

### Decision order

1. Use the existing shared components: `SectionData`, `ResourceDataTable`, `FormCard`, `FormActions`, `TableActionButton`, `DeleteResourceAction`, `DataFilterDialog`, `OptionSelect`, `DatePickerInput`.
2. Compose the existing `components/ui/*` components.
3. If a new component is truly needed, ask first. If approved, put it in `components/` (not `components/ui/`) and do not copy existing patterns.
4. Structure references: simple CRUD → `pages/job-title/*`; card-based editor → `pages/letter-templates/edit.tsx`.

### Sources of truth

- Color, radius, and font tokens: `resources/css/app.css`. Theme: `components.json` (`base-vega`, Base UI, Hugeicons).
- Button/badge variants: `components/ui/button.tsx`, `components/ui/badge.tsx`. Do not add variants or override colors through `className`.

### Action → appearance

| Action                                            | Component                           | Variant                   | Icon                  | Appearance                                                 |
| ------------------------------------------------- | ----------------------------------- | ------------------------- | --------------------- | ---------------------------------------------------------- |
| Detail (table)                                    | `TableActionButton`                 | `info`                    | `InfoIcon`            | icon + tooltip                                             |
| Edit, "Ubah" (table)                              | `TableActionButton`                 | `warning`                 | `Edit04Icon`          | icon + tooltip                                             |
| Delete, "Hapus"                                   | `DeleteResourceAction`              | `destructive`             | `Delete02Icon`        | table: icon + tooltip; confirmation in dialog: icon + verb |
| Save / Cancel (form)                              | `FormActions`                       | `default` / `destructive` | `SaveIcon` / `XIcon`  | icon + verb                                                |
| Save / Cancel (inline editor in a table/list row) | `FormActions compact`               | `default` / `destructive` | `SaveIcon` / `XIcon`  | icon + tooltip                                             |
| Archive, "Arsip" (toolbar link)                   | `SectionData` `archiveUrl`          | `dark`                    | `Archive01Icon`       | "Arsip"                                                    |
| Cancel in a dialog                                | `AlertDialogCancel` / `DialogClose` | `outline`                 | no icon               | "Batal" (not "Kembali")                                    |
| Primary page action                               | `Button`                            | `default`                 | optional leading icon | verb                                                       |
| Secondary/toolbar action                          | `Button`                            | `outline`                 | optional leading icon | verb                                                       |
| Restore / approve                                 | `Button`                            | `approve`                 | per action            | verb                                                       |

- Write "Ubah", not "Edit". The accessible label includes the record name: `Ubah ${name}`.
- One primary action (`default`) per page. Button sizes are only `default`, `sm`, `icon`, `icon-sm`.

### Buttons: icon only or icon + verb

| Context                                                                                                                                              | Appearance                                                                                           | How                                                                                                                              |
| ---------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Inside a table or list row (`DataTable` action column, `TableCell`, list item), including move up/down, edit, delete, and inline Save/Cancel editors | **Icon only + tooltip**, no visible text                                                             | `TableActionButton`; inline editors: `<FormActions compact />`; the accessible `label` includes the record name (`Ubah ${name}`) |
| Inside a form (`FormCard`, card editor)                                                                                                              | **Leading icon + verb**                                                                              | `Button` with `<HugeiconsIcon data-icon="inline-start" />` followed by text; Save/Cancel through `FormActions`                   |
| Confirmation/action dialog footer                                                                                                                    | Confirm: verb, optional leading icon (see `DeleteResourceAction`); cancel: `outline` labeled "Batal" | `AlertDialogCancel` / `DialogClose` + `Button`                                                                                   |
| Anywhere else (page toolbar, card header)                                                                                                            | Text button (verb); icon optional                                                                    | `Button`. Icon-only only for established conventions (Archive, Close, pagination), with `aria-label` + tooltip                   |

- Do not mix the two styles in one context, and do not use `<Button size="icon*">` directly in a table column; always use `TableActionButton`.
- Tooltips are only for icon-only buttons. A button that already has text gets no tooltip.
- Icons are always `<HugeiconsIcon strokeWidth={2} … />`. Icons inside text buttons: no size class; spinner = `LoaderIcon` + `animate-spin`, and the text changes ("Menyimpan…", "Menghapus...").
- Do not create another Save/Cancel button; use `FormActions` (`onCancel` for editors that do not navigate). `compact` is **required** when `FormActions` sits inside a `TableCell` or a list item (`SortableItem`); do not use it anywhere else.
- Every icon-only button on a page (move up, move down, edit, delete, and similar) goes through `TableActionButton` so it has a tooltip. Legitimate exceptions are marked `// ui-allow: icon-button-tooltip (reason)`, for example dnd-kit drag handles and popover triggers.

### Status → component

| Meaning                         | Component                       |
| ------------------------------- | ------------------------------- |
| Neutral, code, label            | `<Badge variant="secondary">`   |
| Success / active / issued       | `<Badge variant="success">`     |
| Needs attention / draft         | `<Badge variant="warning">`     |
| Information                     | `<Badge variant="info">`        |
| Failed / problem                | `<Badge variant="destructive">` |
| Light distinction (border only) | `<Badge variant="outline">`     |

- A status always has text, not just color. Status icon/text colors use tokens (`text-success`, `text-warning`, `text-destructive`), not `text-emerald-500` or `text-yellow-500`.
- Badges come only from `@/components/ui/badge`; size via `size="default|sm"`. Do not import `components/reui/badge` (registry source). Mapping when you find reui usage: `warning-light` → `warning`, `destructive-light` → `destructive`, `success-light` → `success`, `info-light` → `info`, `size="sm"` stays `sm`. Other reui variants (solid `text-white`, `*-outline`, `primary-*`, `focus*`, the `radius` prop) are intentionally not carried over; add them to `ui/badge` only when there is a real need.
- `reui/frame` and `reui/icon-tile` have no usage rules yet; ask before using them on a new page.
- Block-level messages (not per field): `<Alert variant="destructive|info|success|warning">` from `components/reui/alert`.
- Enum → variant mapping is written in **one** place per enum (a function/constant next to the column), not on every page.

### Forms

- Structure: `FormCard` → `<form noValidate>` → `FieldGroup` → `Field` (`data-invalid`, `data-disabled`) → `FieldLabel` + control + `FieldError`.
- Field errors go only through `FieldError`. Never `<p role="alert" className="text-destructive">`.
- Required marker: `<span className="text-destructive" aria-hidden="true">*</span>` after the label text, plus the `required` attribute. One way only.
- Toasts: title "Berhasil" for success, "Galat" for failure (`gooeyToast`); details go in `description`.
- While `processing`, controls and buttons are `disabled`; prevent double submits.

### Page structure

- Wrapper for list/create/edit pages: `<div className="container mx-auto">`. Detail/multi-card pages: `<div className="container mx-auto flex min-w-0 flex-col gap-6">`. Do not add other wrapper variants. `SectionData` already provides its own container.
- `<Head title>` and `meta description` are in Indonesian; set `Component.layout = { pageName }` on the page component. The page title (h1) comes from `SiteHeader`; do not create another h1.
- Heading scale (only these): card title = `CardTitle` / `FormCard title`; block title inside a card = `<h3 className="text-sm font-medium">`; entity name on a detail page = `<h2 className="text-xl font-semibold break-words">`, one per page.
- Bordered groups inside a card: use `Card` / `Item`. Do not rewrite `rounded-lg border p-3|p-4`.
- Spacing: `gap-*`, not `space-*`. `size-*` when width = height. No manual `z-index`. Mobile: `useIsMobile`.

### Prohibited

- Raw colors (`bg-slate-900`, `text-blue-500`, `bg-white`, `text-white`, hex). Use tokens: `primary`, `muted`, `success`, `warning`, `info`, `destructive`, `invert`.
- Raw `<button>`, `<input>`, `<select>`, `<textarea>`, `<table>` outside `components/ui/`.
- Hand-made badges (`<span className="rounded-md bg-secondary …">`).
- Button color/size overrides on pages, `!important` (`border-x!`), new UI libraries, new themes/palettes.
- Copying Radix examples (`asChild`) or `lucide-react` from skills/docs; this project uses Base UI (`render`, `nativeButton`) and Hugeicons.
- Putting new components in `components/ui/`. That folder is for primitives only; the animated registry icons there (`archive-02`, `notification-01`) are not a pattern to copy.

### Before declaring done

1. `pnpm check:ui` must be clean for the files you changed (errors must be fixed; warnings should be fixed when you touched that file).
2. `pnpm types:check` and `pnpm check`.
3. Legitimate exceptions (for example a file dropzone `<input>`) are marked `// ui-allow: <rule-id>` with a short reason.
4. If the change affects appearance, state in your summary which components you used and whether you created any new ones.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== maatwebsite/excel/core rules ===

# Laravel Excel

- Use `maatwebsite/excel` for spreadsheet exports, imports, queued spreadsheet work, CSV handling, and PhpSpreadsheet integration in Laravel applications.
- Prefer explicit export/import classes with package concerns over ad-hoc spreadsheet generation in controllers, jobs, or commands.
- Activate the `laravel-excel` skill when working with `Excel::download()`, `Excel::store()`, `Excel::queue()`, `Excel::raw()`, `Excel::import()`, `Excel::toArray()`, `Excel::toCollection()`, export/import concerns, queued imports/exports, validation, CSV settings, styling, events, formulas, charts, drawings, multiple sheets, mapped cells, macros, config, cache, transactions, temporary files, or `Excel::fake()`.
- For broad docs, all-feature tasks, or missing-feature audits, use the skill's `references/package.md` feature matrix before answering.
- For large datasets, prefer `FromQuery` with queued exports or `WithChunkReading` and `WithBatchInserts` for imports.
- Test spreadsheet behavior with `Excel::fake()` when asserting dispatch/download/store/import intent, and inspect generated files only when cell contents, formatting, sheets, or writer behavior must be proven.

</laravel-boost-guidelines>
