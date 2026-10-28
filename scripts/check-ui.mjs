#!/usr/bin/env node
/**
 * Pemeriksa konsistensi UI (tanpa dependensi).
 *
 * Pemakaian:
 *   pnpm check:ui            -> hanya berkas yang berubah menurut git (default, dipakai agent)
 *   pnpm check:ui --all      -> audit seluruh resources/js
 *   pnpm check:ui --strict   -> peringatan (warn) ikut menggagalkan
 *
 * Mengecualikan: components/ui/, components/reui/, components/examples/, berkas test.
 * Pengecualian satu baris: tulis `// ui-allow: <id-aturan>` pada baris yang sama
 * atau baris sebelumnya (di JSX: `{/* ui-allow: <id-aturan> *\/}`), disertai alasan.
 */
import { execSync } from 'node:child_process';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

const ROOT = process.cwd();
const SCAN_DIRS = ['resources/js/pages', 'resources/js/components', 'resources/js/layouts'];
const EXCLUDE = [
    'resources/js/components/ui/',
    'resources/js/components/reui/',
    'resources/js/components/examples/',
];

const args = new Set(process.argv.slice(2));
const ALL = args.has('--all');
const STRICT = args.has('--strict');

const PALETTE =
    'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

/** Aturan per baris. */
const LINE_RULES = [
    {
        id: 'raw-element',
        level: 'error',
        test: /<(button|input|select|textarea|table)(?=[\s/>]|$)/,
        message: 'Elemen HTML mentah. Pakai Button / Input / Select|OptionSelect / Textarea / Table dari components/ui.',
    },
    {
        id: 'raw-palette',
        level: 'error',
        test: new RegExp(
            `\\b(?:bg|text|border|ring|fill|stroke|from|to|via|divide|outline|decoration|shadow)-(?:${PALETTE})-\\d{2,3}\\b`,
        ),
        message: 'Warna palet mentah. Pakai token (primary, muted, success, warning, info, destructive, invert).',
    },
    {
        id: 'raw-black-white',
        level: 'error',
        test: /\b(?:bg|text|border)-(?:white|black)\b/,
        message: 'bg/text/border white|black mentah. Pakai token (background, foreground, card, invert).',
    },
    {
        id: 'fake-badge',
        level: 'error',
        test: /<span\b[^>]*className="(?=[^"]*\bbg-secondary\b)(?=[^"]*\brounded-)/,
        message: 'Badge buatan sendiri. Pakai <Badge variant="secondary|success|warning|info|destructive|outline">.',
    },
    {
        id: 'raw-alert',
        level: 'error',
        test: /<p\b[^>]*role="alert"/,
        message: 'Galat dengan <p role="alert">. Pakai <FieldError> (per field) atau <Alert variant="destructive">.',
    },
    {
        id: 'table-action',
        level: 'error',
        only: /data-table\/(column|delete-action)\.tsx$/,
        test: /<Button(?=[\s/>]|$)/,
        message: 'Aksi di tabel = ikon + tooltip. Pakai <TableActionButton>, bukan <Button>.',
    },
    {
        id: 'icon-button-tooltip',
        level: 'error',
        only: /^resources\/js\/pages\//,
        test: /\bsize="icon(?:-xs|-sm|-lg)?"/,
        message:
            'Tombol ikon saja harus ber-tooltip: pakai <TableActionButton>. Pengecualian (handle drag, pemicu popover): // ui-allow: icon-button-tooltip (alasan).',
    },
    {
        id: 'radix-aschild',
        level: 'error',
        test: /\basChild\b/,
        message: 'asChild itu Radix. Proyek ini Base UI: pakai prop render (dan nativeButton bila perlu).',
    },
    {
        id: 'lucide-import',
        level: 'error',
        test: /from ['"]lucide-react['"]/,
        message: 'Ikon proyek ini Hugeicons (@hugeicons/react + @hugeicons/core-free-icons), bukan lucide-react.',
    },
    {
        id: 'duplicate-badge',
        level: 'error',
        test: /from ['"]@\/components\/reui\/badge['"]/,
        message: 'Pakai @/components/ui/badge (kanonik), bukan reui/badge. Pemetaan varian ada di .ai/guidelines/ui.md.',
    },
    {
        id: 'space-utility',
        level: 'error',
        test: /\bspace-[xy]-\d/,
        message: 'Pakai gap-* (flex/grid), bukan space-x/y-*.',
    },
];

/** Aturan per elemen JSX multi-baris. */
const BLOCK_RULES = [
    {
        id: 'form-actions-compact',
        level: 'error',
        // true bila blok berada di dalam <TableCell> atau <SortableItem> (tag pembuka lebih banyak dari penutup)
        when: (text, index) => {
            const before = text.slice(0, index);
            const depth = (open, close) => (before.match(open) ?? []).length - (before.match(close) ?? []).length;
            return depth(/<TableCell\b/g, /<\/TableCell>/g) > 0 || depth(/<SortableItem\b/g, /<\/SortableItem>/g) > 0;
        },
        open: /<FormActions\b/g,
        close: '/>',
        bad: (block) => !/\bcompact\b/.test(block),
        message: 'FormActions di dalam tabel/baris daftar harus <FormActions compact … /> (ikon + tooltip).',
    },
    {
        id: 'icon-stroke',
        level: 'warn',
        open: /<HugeiconsIcon\b/g,
        close: '/>',
        bad: (block) => !/strokeWidth=\{2\}/.test(block),
        message: 'HugeiconsIcon tanpa strokeWidth={2}.',
    },
];

function walk(dir, acc = []) {
    for (const name of readdirSync(dir)) {
        const full = join(dir, name);
        const st = statSync(full);
        if (st.isDirectory()) walk(full, acc);
        else acc.push(full);
    }
    return acc;
}

function inScope(rel) {
    const p = rel.split(sep).join('/');
    if (!p.endsWith('.tsx')) return false;
    if (/\.test\.[jt]sx?$/.test(p)) return false;
    if (EXCLUDE.some((x) => p.startsWith(x))) return false;
    return SCAN_DIRS.some((d) => p.startsWith(d + '/'));
}

function changedFiles() {
    try {
        const run = (cmd) => execSync(cmd, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
        const tracked = run('git diff --name-only --diff-filter=ACMR HEAD');
        const untracked = run('git ls-files --others --exclude-standard');
        return [...new Set((tracked + '\n' + untracked).split('\n').filter(Boolean))];
    } catch {
        return null;
    }
}

let files;
if (ALL) {
    files = SCAN_DIRS.filter((d) => existsDir(join(ROOT, d))).flatMap((d) => walk(join(ROOT, d)));
    files = files.map((f) => relative(ROOT, f));
} else {
    const changed = changedFiles();
    if (changed === null) {
        console.log('git tidak tersedia; memeriksa semua berkas.');
        files = SCAN_DIRS.filter((d) => existsDir(join(ROOT, d)))
            .flatMap((d) => walk(join(ROOT, d)))
            .map((f) => relative(ROOT, f));
    } else {
        files = changed;
    }
}
files = files.filter(inScope);

function existsDir(p) {
    try {
        return statSync(p).isDirectory();
    } catch {
        return false;
    }
}

function allowed(lines, index, id) {
    const re = new RegExp(`ui-allow:\\s*(?:[\\w-]+\\s*,\\s*)*${id}\\b`);
    return re.test(lines[index] ?? '') || re.test(lines[index - 1] ?? '');
}

const findings = [];

for (const rel of files) {
    const text = readFileSync(join(ROOT, rel), 'utf8');
    const lines = text.split('\n');

    lines.forEach((line, i) => {
        for (const rule of LINE_RULES) {
            if (rule.only && !rule.only.test(rel.split(sep).join('/'))) continue;
            if (rule.test.test(line) && !allowed(lines, i, rule.id)) {
                findings.push({ rel, line: i + 1, rule });
            }
        }
    });

    for (const rule of BLOCK_RULES) {
        for (const m of text.matchAll(rule.open)) {
            if (rule.when && !rule.when(text, m.index)) continue;
            const end = text.indexOf(rule.close, m.index);
            if (end === -1) continue;
            const block = text.slice(m.index, end + rule.close.length);
            if (!rule.bad(block)) continue;
            const lineNo = text.slice(0, m.index).split('\n').length;
            if (allowed(lines, lineNo - 1, rule.id)) continue;
            findings.push({ rel, line: lineNo, rule });
        }
    }
}

findings.sort((a, b) => a.rel.localeCompare(b.rel) || a.line - b.line);

for (const f of findings) {
    const tag = f.rule.level === 'error' ? 'ERROR' : 'warn ';
    console.log(`${tag} ${f.rel.split(sep).join('/')}:${f.line}  [${f.rule.id}] ${f.rule.message}`);
}

const errors = findings.filter((f) => f.rule.level === 'error').length;
const warns = findings.length - errors;
console.log(
    `\ncheck:ui — ${files.length} berkas diperiksa, ${errors} galat, ${warns} peringatan` +
        (ALL ? ' (mode --all)' : ' (hanya berkas yang berubah)'),
);

process.exit(errors > 0 || (STRICT && warns > 0) ? 1 : 0);
