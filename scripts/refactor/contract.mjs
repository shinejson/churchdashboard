#!/usr/bin/env node
/**
 * JS -> markup contract builder.
 *
 * For each sidebar page, reads the local JS files that page loads and collects
 * every DOM hook the script asks for by string (getElementById, querySelector,
 * closest, createElement id assignment, innerHTML lookups). Those strings are
 * the hard contract: the refactored markup must keep them.
 *
 * Usage:
 *   node scripts/refactor/contract.mjs --out tmp/refactor/contract.json
 *   node scripts/refactor/contract.mjs --check   # verify pages satisfy the contract
 */
import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { PAGES, REPO_ROOT, readRepo, uniq } from './pages.mjs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { renderPage, isMigrated } = require('./php-subset/harness.cjs');

const args = process.argv.slice(2);
const argValue = (flag, fallback = null) => {
  const i = args.indexOf(flag);
  return i !== -1 && args[i + 1] ? args[i + 1] : fallback;
};
const checking = args.includes('--check');

/** Selector-shaped string literals found in DOM lookup calls. */
const LOOKUP_CALLS = [
  /getElementById\(\s*(['"`])([^'"`]+)\1/g,
  /querySelector(?:All)?\(\s*(['"`])([^'"`]+)\1/g,
  /closest\(\s*(['"`])([^'"`]+)\1/g,
  /matches\(\s*(['"`])([^'"`]+)\1/g,
  /getElementsByName\(\s*(['"`])([^'"`]+)\1/g,
  /\.id\s*=\s*(['"`])([A-Za-z][\w-]*)\1/g,
  /data-[a-z-]+-target/g
];

function hooksFromScript(source) {
  const ids = new Set();
  const selectors = new Set();

  for (const re of LOOKUP_CALLS) {
    re.lastIndex = 0;
    let m;
    while ((m = re.exec(source)) !== null) {
      if (m.length >= 3) {
        const value = m[2].trim();
        if (!value || /\s|\$\{/.test(value)) {
          if (value && /^[.#[]/.test(value)) selectors.add(value);
          continue;
        }
        if (/^[.#[]/.test(value)) selectors.add(value);
        else ids.add(value);
      } else if (m[0].startsWith('data-')) {
        selectors.add(`[${m[0]}]`);
      }
    }
  }

  // Template-built selectors like `#modal-${kind}` are not statically known;
  // capture the literal prefix so the report can flag them for manual review.
  for (const m of source.matchAll(/querySelector(?:All)?\(\s*`([^`$]{1,60})\$\{/g)) {
    selectors.add(`${m[1]}<dynamic>`);
  }

  // classList / className usage reveals class names the script toggles.
  const classes = new Set();
  for (const m of source.matchAll(/classList\.(?:add|remove|toggle|contains)\(([^)]*)\)/g)) {
    for (const lit of m[1].matchAll(/(['"`])([\w-]+)\1/g)) classes.add(lit[2]);
  }
  for (const m of source.matchAll(/className\s*=\s*(['"`])([^'"`]+)\1/g)) {
    m[2].split(/\s+/).filter(Boolean).forEach((c) => classes.add(c));
  }

  // Attributes read/written by name.
  const attrs = new Set();
  for (const m of source.matchAll(/(?:getAttribute|setAttribute|hasAttribute|removeAttribute)\(\s*(['"`])([\w-]+)\1/g)) {
    attrs.add(m[2]);
  }

  return {
    ids: uniq([...ids]),
    selectors: uniq([...selectors]),
    classes: uniq([...classes]),
    attrs: uniq([...attrs])
  };
}

/** Hooks required from the shared chrome (topnav + sidebar + footer). */
function sharedChromeHooks() {
  const source = ['frontend/topnav.php', 'frontend/sidebar.php', 'frontend/footer.php'].map(readRepo).join('\n');
  return hooksFromScript(source);
}

const chrome = sharedChromeHooks();

const contract = {
  generatedAt: new Date().toISOString(),
  sharedChrome: chrome,
  pages: PAGES.map((page) => {
    const scripts = page.js.map((rel) => ({ file: rel, hooks: hooksFromScript(readRepo(rel)) }));
    const merged = {
      ids: uniq(scripts.flatMap((s) => s.hooks.ids)),
      selectors: uniq(scripts.flatMap((s) => s.hooks.selectors)),
      classes: uniq(scripts.flatMap((s) => s.hooks.classes)),
      attrs: uniq(scripts.flatMap((s) => s.hooks.attrs))
    };
    return {
      id: page.id,
      label: page.label,
      file: page.file,
      aux: page.aux === true,
      scripts: scripts.map((s) => s.file),
      required: merged
    };
  })
};

const outPath = argValue('--out', 'tmp/refactor/contract.json');
const absOut = join(REPO_ROOT, outPath);
mkdirSync(dirname(absOut), { recursive: true });
writeFileSync(absOut, JSON.stringify(contract, null, 2) + '\n');

if (!checking) {
  console.log(`Contract written to ${outPath}`);
  for (const page of contract.pages) {
    console.log(
      `  ${page.id.padEnd(22)} ids ${String(page.required.ids.length).padStart(3)} | selectors ${String(page.required.selectors.length).padStart(3)} | classes ${String(page.required.classes.length).padStart(3)} | attrs ${String(page.required.attrs.length).padStart(3)}`
    );
  }
  process.exit(0);
}

/** --check: confirm each page's markup still exposes what its scripts ask for. */
function markupHooks(file) {
  const source = readRepo(file);
  return hooksFromMarkup(source);
}

/**
 * Hooks exposed by a rendered page rather than by its source text.
 *
 * A migrated page hands most of its markup to frontend/layout/*.php and
 * components.php, so scanning the page file alone misses every hook the shared
 * layout emits (page headers, toolbars, tables, stat cards, modals). Rendering
 * the page through the php-subset interpreter is the only faithful source.
 */
function pageMarkupHooks(page) {
  const source = readRepo(page.file);
  if (!isMigrated(source)) return { ...markupHooks(page.file), source: 'static' };
  const rendered = renderPage(page.file, { role: 'admin' });
  if (rendered.error || !rendered.html) return { ...markupHooks(page.file), source: 'static', renderError: rendered.error };
  return { ...hooksFromMarkup(rendered.html), source: 'rendered' };
}

function hooksFromMarkup(source) {
  const ids = new Set();
  for (const m of source.matchAll(/\bid\s*=\s*(?:"([^"<>?]*)"|'([^'<>?]*)')/g)) {
    const v = (m[1] ?? m[2] ?? '').trim();
    if (v) ids.add(v);
  }
  const classes = new Set();
  for (const m of source.matchAll(/\bclass\s*=\s*(?:"([^"]*)"|'([^']*)')/g)) {
    (m[1] ?? m[2] ?? '').replace(/<\?[\s\S]*?\?>/g, ' ').split(/\s+/).filter(Boolean).forEach((c) => classes.add(c));
  }
  const attrs = new Set();
  for (const m of source.matchAll(/\b(data-[a-z0-9-]+|name|type|value|role|aria-[\w-]+|href|src|action|method)\s*=/g)) {
    attrs.add(m[1]);
  }
  // Ids/classes created at runtime by the page's own inline scripts count too.
  const inline = hooksFromScript(source);
  inline.ids.forEach((v) => ids.add(v));
  inline.classes.forEach((v) => classes.add(v));
  return { ids, classes, attrs, source };
}

/**
 * Hooks that exist outside a single page file but are still available at
 * runtime: the shared chrome (topnav, sidebar, footer) and elements the shared
 * scripts build dynamically (toasts, dropdown panels, generated table rows).
 */
const SHARED_CHROME_FILES = ['frontend/topnav.php', 'frontend/sidebar.php', 'frontend/footer.php'];
const RUNTIME_SCRIPTS = ['frontend/js/ui.js', 'frontend/js/main.js'];

/** Hooks a script creates at runtime rather than expecting in the markup. */
const RUNTIME_CREATED = new Set([
  'toast-container', 'modal', 'modal-body', 'global-loader', 'spinner-overlay',
  'form', 'table', 'tbody', 'tr', 'td', 'button', 'h3', 'notifications-dropdown',
  'profile-dropdown', 'darkmode-toggle', 'darkmode-icon'
]);
/** Template-interpolated values are never static markup hooks. */
const isTemplateNoise = (v) => v.includes('$') || v.includes('<dynamic>');

const chromeIds = new Set(chrome.ids);
const chromeClasses = new Set(chrome.classes);
for (const file of SHARED_CHROME_FILES) {
  const hooks = markupHooks(file);
  hooks.ids.forEach((v) => chromeIds.add(v));
  hooks.classes.forEach((v) => chromeClasses.add(v));
}
for (const file of RUNTIME_SCRIPTS) {
  const hooks = hooksFromScript(readRepo(file));
  hooks.ids.forEach((v) => chromeIds.add(v));
  hooks.classes.forEach((v) => chromeClasses.add(v));
}

const isSatisfied = (value, own, shared) =>
  RUNTIME_CREATED.has(value) || isTemplateNoise(value) || own.has(value) || shared.has(value);

let problems = 0;
console.log('\nContract check: page markup vs script requirements\n');
for (const page of contract.pages) {
  const markup = pageMarkupHooks(page);
  const missingIds = page.required.ids.filter((id) => !isSatisfied(id, markup.ids, chromeIds));
  const missingClasses = page.required.classes.filter((c) => !isSatisfied(c, markup.classes, chromeClasses));
  const total = missingIds.length + missingClasses.length;
  problems += total;
  const status = total === 0 ? 'ok  ' : 'WARN';
  console.log(`${status} ${page.id.padEnd(22)} markup ids ${String(markup.ids.size).padStart(3)} | required ids ${String(page.required.ids.length).padStart(3)} | required classes ${String(page.required.classes.length).padStart(3)}`);
  if (missingIds.length) console.log(`      ids not in markup: ${missingIds.join(', ')}`);
  if (missingClasses.length) console.log(`      classes not in markup: ${missingClasses.join(', ')}`);
}
console.log(`\n${problems === 0 ? 'All statically-known hooks are present.' : `${problems} hook(s) not found in markup (may be created at runtime — review).`}`);
process.exit(problems > 0 ? 1 : 0);
