#!/usr/bin/env node
/**
 * Refactor verifier.
 *
 * For every sidebar page it collects the DOM hooks (ids, classes, data-*
 * attribute names, form control names) and compares them with the pre-refactor
 * baseline captured by baseline.mjs.
 *
 * Two sources, compared like-for-like:
 *   - a page still hand-rolled  -> hooks read from its raw source
 *   - a migrated page           -> hooks read from the HTML the layout actually
 *                                  renders (via the PHP-subset harness)
 * Both sides include the shared chrome (topnav + sidebar + footer), so adding
 * the chrome to a rendered page is not counted as a difference.
 *
 * Usage:
 *   node scripts/refactor/verify.mjs                     # all pages
 *   node scripts/refactor/verify.mjs --page departments  # one page
 *   node scripts/refactor/verify.mjs --diff              # list added hooks too
 */
import { readFileSync, existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { createRequire } from 'node:module';
import { PAGES, REPO_ROOT, readRepo, uniq } from './pages.mjs';

const require = createRequire(import.meta.url);
const { renderPage, isMigrated } = require('./php-subset/harness.cjs');

const args = process.argv.slice(2);
const onlyPage = (() => {
  const i = args.indexOf('--page');
  return i !== -1 ? args[i + 1] : null;
})();
const showDiff = args.includes('--diff');
const baselinePath = args.includes('--baseline')
  ? args[args.indexOf('--baseline') + 1]
  : 'tmp/refactor/baseline.json';

const absBaseline = resolve(REPO_ROOT, baselinePath);
if (!existsSync(absBaseline)) {
  console.error(`No baseline at ${baselinePath}. Run: node scripts/refactor/baseline.mjs`);
  process.exit(2);
}
const baseline = JSON.parse(readFileSync(absBaseline, 'utf8'));
const baselineById = new Map(baseline.pages.map((p) => [p.id, p]));

const CHROME = ['frontend/topnav.php', 'frontend/sidebar.php', 'frontend/footer.php'];

function hooksFromHtml(html) {
  const ids = new Set();
  for (const m of html.matchAll(/\bid\s*=\s*(?:"([^"]*)"|'([^']*)')/g)) {
    const v = (m[1] ?? m[2] ?? '').trim();
    if (v) ids.add(v);
  }
  const classes = new Set();
  for (const m of html.matchAll(/\bclass\s*=\s*(?:"([^"]*)"|'([^']*)')/g)) {
    (m[1] ?? m[2] ?? '').split(/\s+/).filter(Boolean).forEach((c) => classes.add(c));
  }
  const dataAttrs = new Set();
  for (const m of html.matchAll(/\b(data-[a-z0-9-]+)\s*=/g)) dataAttrs.add(m[1]);
  const names = new Set();
  for (const m of html.matchAll(/\bname\s*=\s*(?:"([^"<>?]*)"|'([^'<>?]*)')/g)) {
    const v = (m[1] ?? m[2] ?? '').trim();
    if (v) names.add(v);
  }
  const assets = new Set();
  for (const m of html.matchAll(/(?:href|src)\s*=\s*"([^"]+\.(?:css|js))(?:\?[^"]*)?"/g)) {
    const url = m[1].replace(/^\.\//, '');
    assets.add(url.startsWith('frontend/') ? url : url);
  }
  return {
    ids: uniq([...ids]),
    classes: uniq([...classes]),
    dataAttrs: uniq([...dataAttrs]),
    names: uniq([...names]),
    assets: uniq([...assets]),
  };
}

/** Hooks the shared chrome contributes, so they are excluded from comparison. */
const chromeHooks = hooksFromHtml(CHROME.map(readRepo).join('\n'));
const chromeSets = {
  ids: new Set(chromeHooks.ids),
  classes: new Set(chromeHooks.classes),
  dataAttrs: new Set(chromeHooks.dataAttrs),
  names: new Set(chromeHooks.names),
};

function analyse(page) {
  const source = readRepo(page.file);
  const migrated = isMigrated(source);
  if (!migrated) {
    const raw = hooksFromHtml(source);
    return { page, migrated, hooks: raw, note: 'hand-rolled markup (not yet migrated)' };
  }
  const result = renderPage(page.file, { role: 'admin', verbose: false });
  if (result.error) {
    return { page, migrated, hooks: null, error: result.error };
  }
  if (result.html === null || result.html === undefined) {
    return { page, migrated, hooks: null, error: result.error || 'render produced no output' };
  }
  return { page, migrated, hooks: hooksFromHtml(result.html), html: result.html, exited: result.exited === true };
}

const pages = onlyPage ? PAGES.filter((p) => p.id === onlyPage) : PAGES;
if (onlyPage && pages.length === 0) {
  console.error(`Unknown page id '${onlyPage}'. Known: ${PAGES.map((p) => p.id).join(', ')}`);
  process.exit(2);
}

const results = [];
let lost = 0;
let renderErrors = 0;

console.log('\nSidebar page refactor verification\n');
console.log('page                   state       ids  classes  data  names   missing hooks');
console.log('-'.repeat(96));

for (const page of pages) {
  const result = analyse(page);
  results.push({
    id: page.id,
    file: page.file,
    migrated: result.migrated,
    error: result.error ?? null,
    hooks: result.hooks,
  });

  if (result.error) {
    renderErrors++;
    console.log(`${page.id.padEnd(22)} RENDER-ERR    -        -     -      -   ${result.error.slice(0, 60)}`);
    continue;
  }

  const prior = baselineById.get(page.id);
  const state = result.migrated ? 'migrated' : 'original';

  if (!prior) {
    console.log(`${page.id.padEnd(22)} ${state.padEnd(11)} (no baseline)`);
    continue;
  }

  // The baseline was taken from raw source without chrome; the rendered page
  // includes chrome. Exclude chrome-provided hooks from the comparison.
  const missing = {};
  let pageLost = 0;
  for (const kind of ['ids', 'classes', 'dataAttrs', 'names']) {
    const current = new Set(result.hooks[kind]);
    const gone = prior[kind].filter((v) => !current.has(v) && !chromeSets[kind].has(v));
    if (gone.length) {
      missing[kind] = gone;
      pageLost += gone.length;
    }
  }
  lost += pageLost;

  const summary = pageLost === 0 ? 'none' : Object.entries(missing).map(([k, v]) => `${k}:${v.length}`).join(' ');
  console.log(
    `${page.id.padEnd(22)} ${state.padEnd(11)} ${String(result.hooks.ids.length).padStart(4)} ${String(result.hooks.classes.length).padStart(8)} `
    + `${String(result.hooks.dataAttrs.length).padStart(5)} ${String(result.hooks.names.length).padStart(6)}   ${summary}`
  );
  if (pageLost && showDiff) {
    for (const [kind, values] of Object.entries(missing)) {
      console.log(`    lost ${kind}: ${values.join(', ')}`);
    }
  } else if (pageLost) {
    for (const [kind, values] of Object.entries(missing)) {
      console.log(`    lost ${kind}: ${values.slice(0, 8).join(', ')}${values.length > 8 ? ` (+${values.length - 8} more, use --diff)` : ''}`);
    }
  }
}

const migratedCount = results.filter((r) => r.migrated).length;
console.log('-'.repeat(96));
console.log(`migrated ${migratedCount}/${results.length} pages | render errors ${renderErrors} | hooks lost ${lost}`);

const outPath = 'tmp/refactor/verify.json';
mkdirSync(join(REPO_ROOT, 'tmp/refactor'), { recursive: true });
writeFileSync(join(REPO_ROOT, outPath), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2) + '\n');
console.log(`detail written to ${outPath}\n`);

process.exit(lost > 0 || renderErrors > 0 ? 1 : 0);
