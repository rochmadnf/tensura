# Laravel patterns for Tensura

This guide combines the selected `laravel-best-practices` and `laravel-patterns` skills with the existing letter-number feature. Use that feature as the working CRUD example. These are defaults for new features; preserve an existing feature's contracts when refactoring it.

## Responsibilities

| Layer        | Owns                                                       | Letter-number example                                   |
| ------------ | ---------------------------------------------------------- | ------------------------------------------------------- |
| Routes       | HTTP methods, names, authentication, model binding         | `routes/web.php`, `ln.*` routes                         |
| Controller   | Coordinate requests, persistence, resources, and redirects | `LetterNumberController`                                |
| Form Request | Authorization, server validation, translated field labels  | `StoreLetterNumberRequest`, `UpdateLetterNumberRequest` |
| Model        | Persistence, mass-assignment rules, relationships, casts   | `LetterNumber`                                          |
| Resource     | Explicit data exposed to the frontend                      | `LetterNumberResource`                                  |
| Feature test | Observable endpoint behavior and important failure modes   | `LetterNumberEditTest`, `LetterNumberDeleteTest`        |

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

## Routing, reads, and frontend integration

- Use standard controller actions: `index`, `create`, `store`, `edit`, `update`, and `destroy`. Prefer resource routes for new resources when they match the intended URLs. Preserve existing explicit URLs and names for letter-number, including `/letter-number/store` and `ln.*`.
- Bind letter-number using `{letterNumber:uuid}`. Its internal primary key remains numeric; the Resource exposes its UUID as `id`. Unknown identifiers return 404 through model binding.
- Paginate list queries and select eager-loaded relationships when needed. Keep simple list queries in the controller; extract a scope or query object once constraints are reused or become complex.
- Preserve the existing keyword behavior: trim whitespace, search only from two characters, escape SQL LIKE wildcards, and retain query parameters during pagination. Keep page sizes bounded to the current allowlist.
- Render pages with Inertia and expose records through Resources. Generate frontend route helpers with Wayfinder and use `@/routes` functions rather than hardcoded URLs. Preserve the Resource's existing `resource.data` and `resources.data` shapes.
- Confirm deletes in the UI and show processing and result feedback. Letter-number uses permanent deletion; recoverable deletion would need a separately designed schema and behavior change.
- Use jobs for slow or retryable external work, and events for meaningful domain side effects. Add caching only when there is a measured need and a clear invalidation strategy.

## Adding or changing a feature

1. Read applicable project rules and nearby implementations. Activate the relevant skills, confirm installed package versions, and use Boost `search-docs` before relying on framework APIs. Inspect the schema before changing models or migrations.
2. Generate Laravel files using `php artisan make:... --no-interaction`. Keep existing directories and avoid adding dependencies or architectural layers without a concrete need.
3. Define authentication and any required authorization, route names and binding keys, writable fields, validation, Resource output, and redirects. Reuse existing UI components and frontend form patterns.
4. Add meaningful feature coverage using factories and the isolated test database. Cover success and persisted state, invalid input, duplicate/current codes, guests, missing UUIDs, and protected fields when applicable. Delete tests must verify only the selected record disappears. Reuse coverage already present rather than duplicating it.
5. Run the affected tests and PHP formatter. Regenerate Wayfinder helpers when route signatures change. Run frontend lint, type checking, and a production build when frontend code changes. Report existing unrelated failures separately.

Current backend checks:

```sh
php artisan test --compact tests/Feature/LetterNumberEditTest.php tests/Feature/LetterNumberDeleteTest.php
php vendor/bin/pint --dirty --format agent
```

Additional checks when relevant:

```sh
php artisan wayfinder:generate --no-interaction
pnpm run types:check
pnpm run build
```

Recheck installed versions as the project evolves. The reference implementation currently uses Laravel 13, PHP 8.4, Inertia 3, and PHPUnit 12; examples in a skill are guidance, not proof that an API exists in the installed version.
