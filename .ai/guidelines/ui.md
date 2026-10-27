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
