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
