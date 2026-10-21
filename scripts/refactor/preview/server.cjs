'use strict';

/**
 * Static mock preview server.
 *
 * PHP and MySQL are not available in this environment, so the refactored pages
 * cannot be booted normally. This server makes them viewable anyway:
 *
 *   - .php page requests are rendered through the php-subset interpreter in
 *     scripts/refactor/php-subset/ (the same harness the verification suite
 *     uses), with a synthetic logged-in session;
 *   - /backend/* requests are answered with the fixtures in ./fixtures.cjs, in
 *     the shape the real endpoints return, so the *unmodified* page scripts
 *     populate their tables and charts;
 *   - everything else is served from the repository, so the real CSS and JS
 *     load and the pages look as they do in production.
 *
 * This is a review tool, not a deployment target. It performs no database
 * access and saves nothing.
 *
 * Usage:
 *   node scripts/refactor/preview/server.cjs [--port 4173] [--host 0.0.0.0]
 *
 * Then open the URL printed below. Add ?role=member to any page to preview it
 * as a non-admin (the choice sticks via a cookie so sidebar links keep it).
 */

const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');

const { REPO_ROOT, buildMockIncludes, prepareSource, isMigrated, renderMode } = require('../php-subset/harness.cjs');
const { Interpreter } = require('../php-subset/interpreter.cjs');
const { PhpArray, PhpExit } = require('../php-subset/runtime.cjs');
const { PAGES } = require('../pages.mjs');

const { routes, fallback } = require('./fixtures.cjs');

const argv = process.argv.slice(2);
const argValue = (flag, fallbackValue) => {
  const i = argv.indexOf(flag);
  return i === -1 ? fallbackValue : argv[i + 1];
};
const PORT = Number(argValue('--port', process.env.PORT || 4173));
const HOST = argValue('--host', '0.0.0.0');

const MIME = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.map': 'application/json; charset=utf-8',
};

/** Roles the preview can impersonate, in sidebar order of restrictiveness. */
const ROLES = ['admin', 'pastor', 'pcf', 'fellowship_leader', 'member'];

/** Render cache keyed by page + role; ?fresh=1 bypasses it. */
const renderCache = new Map();
let renderCount = 0;

function parseCookies(header) {
  const out = {};
  if (!header) return out;
  for (const part of header.split(';')) {
    const i = part.indexOf('=');
    if (i > -1) out[part.slice(0, i).trim()] = decodeURIComponent(part.slice(i + 1).trim());
  }
  return out;
}

/**
 * renderPage(), extended with the request context the harness does not model:
 * a query string and a per-request role. Mirrors the harness wiring exactly so
 * previewed output matches what verify.mjs measures.
 */
function renderWithRequest(pageFile, { role, query, cookies }) {
  const abs = path.resolve(REPO_ROOT, pageFile);
  const source = fs.readFileSync(abs, 'utf8');

  if (!isMigrated(source)) {
    return { migrated: false, html: null, note: 'not migrated' };
  }

  const session = new PhpArray();
  session.set('user_id', 1);
  session.set('username', 'Preview User');
  session.set('role', role);
  session.set('csrf_token', 'preview-csrf-token');
  session.set('fellowship_id', null);

  const server = new PhpArray();
  server.set('SCRIPT_NAME', `/${pageFile.replace(/^\/+/, '')}`);
  server.set('REQUEST_METHOD', 'GET');
  server.set('HTTP_HOST', 'preview.local');
  server.set('DOCUMENT_ROOT', '/');
  server.set('HTTPS', '');
  if (cookies && Object.keys(cookies).length) {
    server.set('HTTP_COOKIE', Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; '));
  }

  const getArray = new PhpArray();
  if (query) for (const [k, v] of query.entries()) getArray.set(k, v);

  const cookieArray = new PhpArray();
  for (const [k, v] of Object.entries(cookies || {})) cookieArray.set(k, v);

  const interp = new Interpreter({
    rootDir: REPO_ROOT,
    mockIncludes: buildMockIncludes(),
    verbose: false,
    predefined: {
      $_SESSION: session,
      $_SERVER: server,
      $_GET: getArray,
      $_POST: new PhpArray(),
      $_COOKIE: cookieArray,
      $_FILES: new PhpArray(),
    },
    constants: { __DIR__: path.dirname(abs), __FILE__: abs },
  });

  renderCount += 1;
  try {
    interp.runSource(prepareSource(source, pageFile), abs);
    return { migrated: true, html: interp.getOutput(), mode: renderMode(pageFile) };
  } catch (error) {
    if (error instanceof PhpExit) {
      // An auth guard or redirect stopped the page. That is a legitimate,
      // observable outcome, so surface the partial document.
      return { migrated: true, html: interp.getOutput(), exited: true };
    }
    return { migrated: true, html: null, error: error.message, partial: interp.getOutput() };
  }
}

function errorPage(title, detail) {
  return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title} - Preview</title>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
         margin: 0; padding: 3rem 1.5rem; background: #f6f7f9; color: #1f2328; }
  main { max-width: 52rem; margin: 0 auto; background: #fff; border: 1px solid #d8dee4;
         border-radius: 10px; padding: 1.75rem 2rem; }
  h1 { margin: 0 0 .5rem; font-size: 1.35rem; }
  p { color: #57606a; line-height: 1.6; }
  pre { background: #0d1117; color: #e6edf3; padding: 1rem; border-radius: 8px;
        overflow: auto; font-size: .82rem; line-height: 1.55; }
  a { color: #0969da; }
</style></head>
<body><main><h1>${title}</h1><p>${detail}</p></main></body></html>`;
}

/** A banner recording that the preview is rendering with fake data. */
function previewBanner(role, pageId, tookMs) {
  const options = ROLES
    .map((r) => `<option value="${r}"${r === role ? ' selected' : ''}>${r}</option>`)
    .join('');
  return `<div id="preview-banner" style="position:sticky;top:0;z-index:9999;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;background:#1f2328;color:#e6edf3;font:600 12px/1.4 ui-sans-serif,system-ui,sans-serif;padding:.5rem .9rem;border-bottom:2px solid #d1a53c">
  <span style="color:#d1a53c">PREVIEW</span>
  <span>${pageId} &middot; rendered statically with mock data (${tookMs} ms)</span>
  <label style="display:flex;gap:.35rem;align-items:center;margin-left:auto;font-weight:500">
    role
    <select id="preview-role" onchange="location.search='?role='+this.value" style="background:#0d1117;color:#e6edf3;border:1px solid #444c56;border-radius:6px;padding:.15rem .35rem;font:inherit">${options}</select>
  </label>
  <a href="/__preview__/" style="color:#79c0ff">all pages</a>
</div>`;
}

function injectBanner(html, role, pageId, tookMs) {
  const banner = previewBanner(role, pageId, tookMs);
  const bodyOpen = html.search(/<body[^>]*>/i);
  if (bodyOpen === -1) return banner + html;
  const endOfTag = html.indexOf('>', bodyOpen) + 1;
  return html.slice(0, endOfTag) + banner + html.slice(endOfTag);
}

/** Landing page listing every sidebar destination this refactor touched. */
function indexPage(role) {
  const rows = PAGES.map((p) => {
    const href = `/${p.file}?role=${role}`;
    return `<tr>
      <td><a href="${href}">${p.label}</a></td>
      <td><code>${p.file}</code></td>
      <td><code>${p.permission}</code></td>
      <td>${p.js.length} &middot; ${p.css.length}</td>
    </tr>`;
  }).join('\n');

  return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Refactor preview - Church Management</title>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
         margin: 0; padding: 2.5rem 1.5rem; background: #f6f7f9; color: #1f2328; }
  main { max-width: 62rem; margin: 0 auto; }
  h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
  .sub { color: #57606a; margin: 0 0 1.75rem; line-height: 1.6; }
  .card { background: #fff; border: 1px solid #d8dee4; border-radius: 10px; overflow: hidden; }
  table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  th, td { text-align: left; padding: .7rem .9rem; border-bottom: 1px solid #eaeef2; }
  th { background: #f6f8fa; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #57606a; }
  tr:last-child td { border-bottom: 0; }
  code { background: #f6f8fa; border: 1px solid #eaeef2; border-radius: 5px; padding: .1rem .35rem; font-size: .82rem; }
  a { color: #0969da; text-decoration: none; font-weight: 600; }
  a:hover { text-decoration: underline; }
  .note { margin-top: 1.5rem; padding: 1rem 1.2rem; background: #fff8e5; border: 1px solid #e8d9a0;
          border-radius: 8px; font-size: .86rem; line-height: 1.6; color: #4d3800; }
  label { font-weight: 600; margin-right: .5rem; }
  select { padding: .25rem .4rem; border: 1px solid #d8dee4; border-radius: 6px; font: inherit; }
</style></head>
<body><main>
  <h1>Sidebar page refactor &mdash; static preview</h1>
  <p class="sub">Every page below is rendered from the refactored PHP source by the php-subset
     interpreter in <code>scripts/refactor/php-subset/</code>, then fed mock data by this server.
     Nothing is written to a database.</p>
  <div class="card"><table>
    <thead><tr><th>Page</th><th>File</th><th>Permission</th><th>JS &middot; CSS assets</th></tr></thead>
    <tbody>${rows}</tbody>
  </table></div>
  <p style="margin-top:1.25rem">
    <label for="role">Preview all pages as</label>
    <select id="role" onchange="Array.from(document.querySelectorAll('tbody a')).forEach(a=>a.href='/'+a.getAttribute('href').split('/').slice(1).join('/').split('?')[0]+'?role='+this.value)">
      ${ROLES.map((r) => `<option value="${r}"${r === role ? ' selected' : ''}>${r}</option>`).join('')}
    </select>
  </p>
  <div class="note"><strong>Reading the preview.</strong> Charts, tables and counts show fixture data,
    not your church's records. Actions that would write to the database return a success message and
    change nothing. <code>?role=member</code> exercises the permission redirects, so some pages will
    deliberately bounce to the profile page.</div>
</main></body></html>`;
}

function send(res, status, body, type, extraHeaders) {
  const headers = { 'Content-Type': type || 'text/html; charset=utf-8', 'Cache-Control': 'no-store', ...extraHeaders };
  res.writeHead(status, headers);
  res.end(body);
}

function sendJson(res, status, payload) {
  send(res, status, JSON.stringify(payload), 'application/json; charset=utf-8');
}

/** Resolve a request path to a repository file, rejecting traversal. */
function resolveRepoFile(pathname) {
  const clean = decodeURIComponent(pathname).replace(/^\/+/, '');
  const abs = path.resolve(REPO_ROOT, clean);
  if (!abs.startsWith(path.resolve(REPO_ROOT))) return null;
  return abs;
}

const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  let pathname = url.pathname;

  const cookies = parseCookies(req.headers.cookie);
  const role = ROLES.includes(url.searchParams.get('role'))
    ? url.searchParams.get('role')
    : (ROLES.includes(cookies.preview_role) ? cookies.preview_role : 'admin');
  const roleCookie = `preview_role=${role}; Path=/; SameSite=Lax`;

  // Normalise an install subdirectory: the app emits /church/... links when it
  // lives in a subfolder, and this preview serves the repo from /.
  pathname = pathname.replace(/^\/church(?=\/|$)/, '');

  if (pathname === '/__preview__' || pathname === '/__preview__/' || pathname === '/') {
    if (pathname === '/') {
      // The dashboard is the real index; the listing lives at /__preview__/.
      pathname = '/index.php';
    } else {
      return send(res, 200, indexPage(role), 'text/html; charset=utf-8', { 'Set-Cookie': roleCookie });
    }
  }

  // ---- Mock API -------------------------------------------------------
  if (/^\/backend\//.test(pathname) || /^\/(ajax|api)\//.test(pathname)) {
    const key = /^\/(ajax|api)\//.test(pathname) ? `/backend${pathname}` : pathname;
    const handler = routes[key] || routes[pathname];
    const payload = handler ? handler(url.searchParams) : fallback(pathname);
    if (!handler) console.log(`  mock  ${pathname} (fallback)`);
    return sendJson(res, 200, payload);
  }

  // ---- Rendered PHP pages --------------------------------------------
  if (/\.php$/i.test(pathname)) {
    const rel = pathname.replace(/^\/+/, '');
    const abs = resolveRepoFile(rel);
    if (!abs || !fs.existsSync(abs)) {
      return send(res, 404, errorPage('Page not found', `No file at <code>${rel}</code> in the repository.`), 'text/html; charset=utf-8', { 'Set-Cookie': roleCookie });
    }

    // Backend scripts are endpoints, not pages; answer them like the API.
    if (rel.startsWith('backend/')) {
      return sendJson(res, 200, fallback(pathname));
    }

    const cacheKey = `${rel}::${role}`;
    const fresh = url.searchParams.get('fresh') === '1';
    const cached = fresh ? null : renderCache.get(cacheKey);
    const started = Date.now();
    const rendered = cached || renderWithRequest(rel, { role, query: url.searchParams, cookies });
    const tookMs = cached ? 0 : Date.now() - started;
    if (!cached) renderCache.set(cacheKey, rendered);

    if (!rendered.migrated) {
      return send(res, 200, errorPage(
        'Page not migrated',
        `<code>${rel}</code> still uses hand-rolled markup, so the interpreter has nothing to render.
         This page was outside the refactor's scope (only <code>login.php</code> and
         <code>kingschat_register.php</code> were left alone).`
      ), 'text/html; charset=utf-8', { 'Set-Cookie': roleCookie });
    }

    if (rendered.error) {
      console.error(`  FAIL  ${rel}: ${rendered.error}`);
      return send(res, 500, errorPage(
        'Render error',
        `<code>${rel}</code> failed to render as <strong>${role}</strong>.
         <pre>${String(rendered.error).replace(/[<&]/g, (c) => ({ '<': '&lt;', '&': '&amp;' }[c]))}</pre>
         ${rendered.partial ? `<p>Partial output:</p><pre>${String(rendered.partial).slice(0, 4000).replace(/[<&]/g, (c) => ({ '<': '&lt;', '&': '&amp;' }[c]))}</pre>` : ''}`
      ), 'text/html; charset=utf-8', { 'Set-Cookie': roleCookie });
    }

    const page = PAGES.find((p) => p.file === rel);
    const label = page ? page.label : rel;

    // The page called exit()/die() before emitting a document - that is the
    // permission guard firing, which is the correct behaviour for this role.
    // Say so rather than showing an empty shell.
    if (rendered.exited && !/<body/i.test(rendered.html || '')) {
      const perm = page ? page.permission : 'unknown';
      const notice = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redirected - ${label}</title>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
         margin: 0; padding: 3rem 1.5rem; background: #f6f7f9; color: #1f2328; }
  main { max-width: 44rem; margin: 0 auto; background: #fff; border: 1px solid #d8dee4;
         border-radius: 10px; padding: 1.75rem 2rem; }
  h1 { margin: 0 0 .5rem; font-size: 1.3rem; }
  p { color: #57606a; line-height: 1.65; }
  code { background: #f6f8fa; border: 1px solid #eaeef2; border-radius: 5px; padding: .1rem .35rem; }
  .pill { display: inline-block; background: #fff8e5; border: 1px solid #e8d9a0; color: #4d3800;
          border-radius: 999px; padding: .2rem .7rem; font-size: .78rem; font-weight: 700; }
</style></head>
<body><main>
  <span class="pill">PERMISSION GUARD FIRED</span>
  <h1>${label} redirected the <code>${role}</code> role</h1>
  <p>Rendering as <strong>${role}</strong>, this page called <code>layout_require_access()</code>
     (permission <code>${perm}</code>) and stopped with <code>exit()</code> before emitting any
     markup. In production that is a <code>Location:</code> redirect; the interpreter has no HTTP
     layer, so it surfaces as an empty document.</p>
  <p>This is expected behaviour, not a defect &mdash; it is the same guard the verification suite
     checks. Switch back to <a href="/${rel}?role=admin">role=admin</a> to see the page, or browse
     <a href="/__preview__/">all pages</a>.</p>
</main></body></html>`;
      console.log(`  guard ${rel} as ${role} - redirected (permission: ${perm})`);
      return send(res, 200, injectBanner(notice, role, `${label} (redirected)`, tookMs), 'text/html; charset=utf-8', { 'Set-Cookie': roleCookie });
    }

    const html = injectBanner(rendered.html || '', role, `${label}${rendered.exited ? ' (stopped early by an auth guard)' : ''}`, tookMs);
    console.log(`  page  ${rel} as ${role} - ${(html.length / 1024).toFixed(1)} KB${rendered.exited ? ' [exited]' : ''}${cached ? ' [cached]' : ''}`);
    return send(res, 200, html, 'text/html; charset=utf-8', { 'Set-Cookie': roleCookie });
  }

  // ---- Static assets --------------------------------------------------
  const abs = resolveRepoFile(pathname === '/' ? 'index.php' : pathname);
  if (!abs || !fs.existsSync(abs) || !fs.statSync(abs).isFile()) {
    return send(res, 404, errorPage('Asset not found', `Nothing to serve at <code>${url.pathname}</code>.`));
  }
  const ext = path.extname(abs).toLowerCase();
  const stream = fs.createReadStream(abs);
  res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream', 'Cache-Control': 'no-store' });
  stream.pipe(res);
});

server.listen(PORT, HOST, () => {
  const shown = HOST === '0.0.0.0' ? 'localhost' : HOST;
  console.log(`\n  Refactor preview server`);
  console.log(`  repo     ${REPO_ROOT}`);
  console.log(`  listening http://${shown}:${PORT}/__preview__/`);
  console.log(`  pages    ${PAGES.length} sidebar destinations, rendered statically`);
  console.log(`\n  Open /__preview__/ for the page list, or go straight to /index.php.`);
  console.log(`  Add ?role=member to any URL to preview permission redirects.`);
  console.log(`  Add ?fresh=1 to bypass the render cache.\n`);
});

server.on('error', (err) => {
  console.error(`preview server failed: ${err.message}`);
  process.exit(1);
});
