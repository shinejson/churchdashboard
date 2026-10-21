#!/usr/bin/env node
/**
 * Baseline hook extractor.
 *
 * Snapshots every DOM hook (ids, classes, data-* attributes, name attributes),
 * asset reference and inline-event handler used by the sidebar pages, so the
 * refactored pages can be diffed against the state the existing JavaScript was
 * written for.
 *
 * Usage:
 *   node scripts/refactor/baseline.mjs --out tmp/refactor/baseline.json
 *   node scripts/refactor/baseline.mjs --out tmp/refactor/after.json --compare tmp/refactor/baseline.json
 */
import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { PAGES, REPO_ROOT, readRepo, uniq } from './pages.mjs';
import { createRequire } from 'node:module';

// Hooks are taken from the RENDERED document, not from a regex over page source.
// That distinction is the whole point once pages are migrated: a migrated page
// hands its header, toolbar, table and modal markup to frontend/layout/*.php and
// frontend/layout/components.php, so its own source no longer contains ids it
// still renders. A static scan would report those as lost. Unmigrated pages have
// nothing to render, so they fall back to the static scan.
const require = createRequire(import.meta.url);
const { renderPage, isMigrated } = require('./php-subset/harness.cjs');

const args = process.argv.slice(2);
const argValue = (flag, fallback = null) => {
  const i = args.indexOf(flag);
  return i !== -1 && args[i + 1] ? args[i + 1] : fallback;
};

/** Every id="..." occurrence, PHP echo variants included. */
function extractIds(source) {
  const ids = [];
  for (const m of source.matchAll(/\bid\s*=\s*(?:"([^"<>?]*)"|'([^'<>?]*)')/g)) {
    const value = (m[1] ?? m[2] ?? '').trim();
    if (value) ids.push(value);
  }
  return uniq(ids);
}

/** Every class token used in the markup (class="a b" and classList-friendly strings). */
function extractClasses(source) {
  const classes = [];
  for (const m of source.matchAll(/\bclass\s*=\s*(?:"([^"]*)"|'([^']*)')/g)) {
    const value = (m[1] ?? m[2] ?? '').replace(/<\?[\s\S]*?\?>/g, ' ');
    classes.push(...value.split(/\s+/).filter(Boolean));
  }
  return uniq(classes);
}

/** data-* attribute names (values are runtime data, names are the contract). */
function extractDataAttrs(source) {
  const attrs = [];
  for (const m of markupOnly(source).matchAll(/\b(data-[a-z0-9-]+)\s*=/g)) attrs.push(m[1]);
  return uniq(attrs);
}

/** Form control names — backend endpoints read these. */
function extractNames(source) {
  const names = [];
  for (const m of source.matchAll(/\bname\s*=\s*(?:"([^"<>?]*)"|'([^'<>?]*)')/g)) {
    const value = (m[1] ?? m[2] ?? '').trim();
    if (value) names.push(value);
  }
  return uniq(names);
}

/**
 * Page source with <style> and <script> bodies removed.
 *
 * The static hook extractors look for markup attributes, but CSS and JS bodies
 * contain the same syntax: settings.php declares `[data-theme="dark"]` selectors
 * in its inline <style>, which the data-* pattern read as a data-theme attribute
 * the page never actually put in the DOM. Stripping these regions keeps the
 * static path measuring markup only, which is what the rendered path already
 * does by construction.
 */
function markupOnly(source) {
  return source
    .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '')
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
}


/**
 * Local css/js references, normalised to repo-relative paths.
 *
 * Two notations are recognised, because the refactor moved asset declarations
 * out of markup and into config:
 *   - `<link href="...">` / `<script src="...">` tags, which is all an
 *     unmigrated page has;
 *   - bare quoted paths in a page's layout_page_init() config, e.g.
 *     'css' => ['css/style.css'], 'scripts' => ['js/main.js'], plus the
 *     $bootstrapCss-style locals those arrays are built from.
 *
 * Scanning only tags reported every migrated page as having dropped its
 * stylesheets and scripts, which was an artefact of the notation change rather
 * than a real loss.
 */
function extractAssets(source, pageFile) {
  const isRoot = !pageFile.startsWith('frontend/');

  const normalise = (href) => {
    // CDN sheets and libraries are deliberately excluded: this inventory tracks
    // the repo's own assets, and the pre-refactor snapshot did the same.
    if (/^https?:|^\/\//.test(href)) return null;
    const clean = href.split('?')[0];
    if (!/\.(?:css|js)$/.test(clean)) return null;
    if (isRoot) return clean.replace(/^\.\//, '');
    // A page in frontend/pages/ writes 'css/x.css' or '../css/x.css'; both mean
    // the same repo asset.
    if (/^(?:\.\.\/)+/.test(clean)) return clean.replace(/^(?:\.\.\/)+/, 'frontend/');
    if (/^(?:css|js|assets)\//.test(clean)) return 'frontend/' + clean;
    return clean.replace(/^\.\//, '');
  };

  const assets = [];

  for (const m of source.matchAll(/(?:href|src)\s*=\s*"([^"]+\.(?:css|js))[^"]*"/g)) {
    const rel = normalise(m[1]);
    if (rel) assets.push(rel);
  }

  for (const m of source.matchAll(/'([^']+\.(?:css|js))'/g)) {
    const rel = normalise(m[1]);
    if (rel) assets.push(rel);
  }

  return uniq(assets);
}

/** Inline handlers and inline styles — the duplication we want to drive down. */
function countSmells(source) {
  return {
    inlineOnclick: (source.match(/\bonclick\s*=/g) || []).length,
    inlineHandlers: (source.match(/\bon(?:change|input|submit|keyup|focus|blur|error|load)\s*=/g) || []).length,
    inlineStyleAttrs: (source.match(/\bstyle\s*=\s*"/g) || []).length,
    styleBlocks: (source.match(/<style[\s>]/g) || []).length,
    scriptBlocks: (source.match(/<script(?![^>]*\bsrc=)[^>]*>/g) || []).length,
    phpEchoInMarkup: (source.match(/<\?=/g) || []).length,
    lines: source.split('\n').length
  };
}

/**
 * Assets taken from a rendered document.
 *
 * A migrated page's own source no longer names most of what it loads: the layout
 * emits the stylesheets, js/env.js, js/config.js and the body scripts from
 * frontend/layout/head.php and body-close.php via layout_url()/layout_asset().
 * Scanning the page source therefore reported env.js and config.js as lost on
 * every migrated page. Reading the rendered <link>/<script> tags instead reports
 * what the browser actually receives.
 *
 * URLs are normalised back to repo-relative paths by cutting at '/frontend/',
 * which also drops the deployment subdirectory and the ?v= cache-buster. CDN URLs
 * contain no '/frontend/' segment and are excluded, matching the static scanner.
 */
function extractAssetsFromHtml(html) {
  const assets = [];
  for (const m of html.matchAll(/(?:href|src)\s*=\s*"([^"]+\.(?:css|js))[^"]*"/g)) {
    const clean = m[1].split('?')[0];
    const idx = clean.indexOf('/frontend/');
    if (idx === -1) continue;
    assets.push(clean.slice(idx + 1));
  }
  return uniq(assets);
}

/**
 * Snapshot one page.
 *
 * Hooks come from the rendered document for migrated pages and from the raw
 * source for pages still hand-rolling their markup, so a before/after comparison
 * stays meaningful across the migration. Assets and smells are always measured on
 * the page's own source: they describe how much duplication that file still
 * carries, which is exactly what the refactor is meant to reduce.
 */
function snapshotPage(page) {
  const source = readRepo(page.file);
  if (!source) {
    return { ...page, error: 'file not found' };
  }

  const migrated = isMigrated(source);
  let ids, classes, dataAttrs, names, renderError = null, renderedAssets = null;

  if (migrated) {
    const result = renderPage(page.file, { role: 'admin', verbose: false });
    const html = result && result.html;
    if (html) {
      ids = extractIds(html);
      classes = extractClasses(html);
      dataAttrs = extractDataAttrs(html);
      names = extractNames(html);
      renderedAssets = extractAssetsFromHtml(html);
    } else {
      renderError = (result && result.error) || 'render produced no output';
      ids = extractIds(source);
      classes = extractClasses(source);
      dataAttrs = extractDataAttrs(source);
      names = extractNames(source);
    }
  } else {
    ids = extractIds(source);
    classes = extractClasses(source);
    dataAttrs = extractDataAttrs(source);
    names = extractNames(source);
  }

  return {
    id: page.id,
    label: page.label,
    file: page.file,
    permission: page.permission,
    aux: page.aux === true,
    migrated,
    hookSource: migrated && !renderError ? 'rendered' : 'static',
    assetSource: renderedAssets ? 'rendered' : 'static',
    renderError,
    ids,
    classes,
    dataAttrs,
    names,
    assets: renderedAssets || extractAssets(source, page.file),
    smells: countSmells(source)
  };
}

const snapshot = {
  generatedAt: new Date().toISOString(),
  repoRoot: REPO_ROOT,
  pages: PAGES.map(snapshotPage)
};

const outPath = argValue('--out', 'tmp/refactor/baseline.json');
const absOut = join(REPO_ROOT, outPath);
mkdirSync(dirname(absOut), { recursive: true });
writeFileSync(absOut, JSON.stringify(snapshot, null, 2) + '\n');

const comparePath = argValue('--compare');
let failures = 0;

if (comparePath) {
  const absCompare = join(REPO_ROOT, comparePath);
  if (!existsSync(absCompare)) {
    console.error(`Comparison baseline not found: ${comparePath}`);
    process.exit(2);
  }
  const before = JSON.parse(readFileSync(absCompare, 'utf8'));
  const beforeById = new Map(before.pages.map((p) => [p.id, p]));

  console.log('\nHook contract comparison (before -> after)\n');
  for (const page of snapshot.pages) {
    const prior = beforeById.get(page.id);
    if (!prior) {
      console.log(`+ ${page.id}: new page (no baseline)`);
      continue;
    }
    const missing = {
      ids: prior.ids.filter((v) => !page.ids.includes(v)),
      classes: prior.classes.filter((v) => !page.classes.includes(v)),
      dataAttrs: prior.dataAttrs.filter((v) => !page.dataAttrs.includes(v)),
      names: prior.names.filter((v) => !page.names.includes(v)),
      assets: prior.assets.filter((v) => !page.assets.includes(v))
    };
    const lost = Object.values(missing).flat().length;
    failures += lost;

    const smellDelta = Object.keys(prior.smells)
      .filter((k) => k !== 'lines')
      .map((k) => `${k}: ${prior.smells[k]}→${page.smells[k]}`)
      .join(', ');

    const status = page.renderError ? 'ERR ' : lost === 0 ? 'ok  ' : 'LOST';
    console.log(`${status} ${page.id.padEnd(22)} lines ${String(prior.smells.lines).padStart(4)}→${String(page.smells.lines).padEnd(4)} | ${smellDelta}`);
    if (page.renderError) console.log(`      render error: ${page.renderError}`);
    for (const [kind, values] of Object.entries(missing)) {
      if (values.length) console.log(`      missing ${kind}: ${values.join(', ')}`);
    }
  }
  console.log(`\n${failures === 0 ? 'No hooks lost.' : `${failures} hook(s) lost.`}`);
}

console.log(`Snapshot written to ${outPath} (${snapshot.pages.length} pages)`);
process.exit(comparePath && failures > 0 ? 1 : 0);
