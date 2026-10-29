# php-pro skill: restricted scope

The `php-pro` skill is installed in this project, but only two of its reference files apply here.

## Activation and ownership

- The `boost.json` skills array enables whole skills; the two entries below are references within `php-pro`, not separate skills.
- These restrictions override the broad description, triggers, and instructions in `php-pro/SKILL.md`. Do not activate `php-pro` just because a task mentions PHP, Laravel, Eloquent, Composer, controllers, or tests.
- Load only the allowed reference needed by the task. Routine Laravel work uses the existing Laravel skills without loading `php-pro`.

| Concern                                                                                              | Owner                               | Role of `php-pro`                                                                                                                             |
| ---------------------------------------------------------------------------------------------------- | ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP language syntax and type semantics                                                               | `php-pro`: `modern-php-features.md` | Consult when a language feature needs implementation or review; project style and naming still apply                                          |
| Explicit asynchronous PHP, coroutines, event loops, Fiber scheduling                                 | `php-pro`: `async-patterns.md`      | Consult for runtime mechanics only                                                                                                            |
| Controllers, Eloquent, validation, authorization, migrations, configuration, queues, events, caching | `laravel-best-practices`            | No framework guidance from `php-pro`                                                                                                          |
| Laravel architectural examples                                                                       | `laravel-patterns`                  | No architecture or scaffolding from `php-pro`; use examples only when needed, subordinate to project conventions and `laravel-best-practices` |
| Test design, coverage, factories, fakes, assertions                                                  | `testing-best-practices`            | No testing rules or quality gates from `php-pro`                                                                                              |
| Spreadsheet imports and exports                                                                      | `laravel-excel`                     | No spreadsheet guidance from `php-pro`                                                                                                        |

For a task spanning concerns, load each skill only for its owned concern. Do not apply competing rules to the same concern.

## Use only these references

### `references/modern-php-features.md`

- Use it for PHP language features: readonly, enums, attributes, callables, match expressions, and typing. Fiber syntax belongs here; Fiber scheduling and concurrency belong to `async-patterns.md`.
- Its custom routing/validation attributes, DTOs, and raw HTTP helpers illustrate syntax only. Do not use them to replace Laravel routing, Form Requests, Eloquent, or response helpers, or to introduce new architectural layers.
- Match the project's PHP version. Read `require.php` in `composer.json` and check `php -v`; if they differ, assume the lower one.
- Do not use syntax or features newer than that version. For example, readonly classes need PHP 8.2+, typed class constants and `#[\Override]` need 8.3+, and property hooks need 8.4+.

### `references/async-patterns.md`

- Use it only when the task explicitly involves asynchronous or concurrent PHP runtime mechanics (Swoole, ReactPHP, Amphp, Fibers). A queue job or long-running Artisan command alone does not activate this reference.
- Prefer Laravel-native options first (queues and jobs, events, Octane, the Concurrency facade) unless the task asks for one of the libraries above. Laravel APIs and lifecycle rules remain owned by the Laravel skills; this reference does not govern them.
- Check that the required package or extension already exists (`composer.json`, `php -m`). Do not add dependencies without asking.
- Treat the examples as illustrations, not copy-paste code. Some use undefined helpers or miss imports, and the Fibers example is not truly concurrent.

## Do not apply

- The `SKILL.md` Core Workflow, MUST DO / MUST NOT DO constraints, Code Patterns, and Output Templates. This includes the PHPStan level 9 gate, the 80%+ coverage requirement, and the repository/service/DTO scaffolding.
- `references/laravel-patterns.md`, `references/symfony-patterns.md`, and `references/testing-quality.md`.
- Do not confuse the excluded `php-pro/references/laravel-patterns.md` with the separately installed `laravel-patterns` skill in `boost.json`.

For everything else, follow this project's existing conventions and the other guidelines.
