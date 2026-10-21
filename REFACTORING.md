# Sidebar page refactor — what changed and how to verify it

This branch refactors **every page reachable from the sidebar** onto a shared layout
system. It is a **pure refactor**: no visual redesign, no behaviour change, and no edits
to the page JavaScript. The pages should look and work exactly as they did before.

Because PHP and MySQL cannot run in the environment where this was done, the work is
backed by a verification harness (a small PHP-subset interpreter that renders each page)
plus a static preview server. Both are included so you can re-check the result yourself.

---

## Scope

All 12 destinations in `frontend/sidebar.php`'s `$menuItems`, plus the three pages that
share their components, = **15 pages**:

| Page | File | Permission key |
| --- | --- | --- |
| Dashboard | `index.php` | `dashboard` |
| Users | `frontend/pages/users.php` | `users` |
| Fellowships | `frontend/pages/fellowships.php` | `fellowships` |
| Departments | `frontend/pages/departments.php` | `fellowships` |
| Home Cells | `frontend/pages/cells.php` | `fellowships` |
| Attendance | `frontend/pages/attendance.php` | `attendance` |
| Financial | `frontend/pages/financial.php` | `financial` |
| Reports | `frontend/pages/report.php` | `reports` |
| Members | `frontend/pages/members.php` | `admin_only` |
| Birthdays | `frontend/pages/birthdays.php` | `admin_only` |
| Settings | `frontend/pages/settings.php` | `admin_only` |
| My Profile | `frontend/pages/profile.php` | `profile` |
| Permissions | `frontend/pages/permissions.php` | (admin tool) |
| Link Members | `frontend/pages/link_members.php` | (admin tool) |
| Create Member Users | `frontend/pages/create_member_users.php` | (admin tool) |

**Deliberately out of scope:** `frontend/pages/login.php` and
`frontend/pages/kingschat_register.php` — neither is a sidebar destination and both are
self-contained auth screens.

---

## What was added

### `frontend/layout/` — the shared page shell (1,366 lines, new)

| File | Lines | Purpose |
| --- | --- | --- |
| `bootstrap.php` | 372 | Path/URL helpers (`layout_url`, `layout_asset`, `layout_url_context`), the permission guard (`layout_require_access`), and `layout_page_init()`, which takes a page's config array and publishes it to the shared partials. |
| `head.php` | 147 | `<head>`: fonts, stylesheets with mtime cache-busting, `window.APP_CONFIG`, CSRF meta, theme pre-paint script. |
| `body-open.php` | 64 | `<body>`, sidebar, topnav, content wrapper open. |
| `body-close.php` | 97 | Content wrapper close, overlays, footer, script tags. |
| `components.php` | 686 | 24 shared UI components. |

A page now declares itself as a config array and hands its markup to the layout:

```php
layout_page_init([
  'id'         => 'fellowships',
  'title'      => 'Fellowships',
  'permission' => 'fellowships',
  'css'        => ['style.css', 'screens.css', 'fellowships.css'],
  'scripts'    => ['ui.js', 'main.js', 'fellowships.js'],
]);
```

**Components available:** `layout_page_header`, `layout_toolbar`, `layout_search_box`,
`layout_select`, `layout_button`, `layout_icon`, `layout_stat_card`,
`layout_stats_overview`, `layout_data_table`, `layout_table_loading`,
`layout_empty_state`, `layout_tabs`, `layout_tab_panel`, `layout_modal`, `layout_form`,
`layout_form_group`, `layout_input`, `layout_textarea`, `layout_form_actions`,
`layout_pagination`, `layout_section_title`, plus the escaping helpers `layout_e`,
`layout_attrs` and `layout_classes`.

### `frontend/css/` — styles extracted from inline `<style>` blocks (1,094 lines, new)

`cells.css` (62), `settings.css` (122), `link_members.css` (241),
`create_member_users.css` (311), `profile.css` (358). Rules were moved verbatim —
selector text and order are unchanged, so the cascade is identical.

### `scripts/refactor/` — verification tooling (4,373 lines, new)

Not part of the application; nothing in `frontend/` or `backend/` loads it.

- `php-subset/` — a PHP-subset parser, runtime and interpreter (`parser.cjs` 1,127,
  `interpreter.cjs` 788, `runtime.cjs` 521, `harness.cjs` 364) that renders each
  migrated page to HTML with a synthetic session and no database.
- `pages.mjs` — the canonical 15-page inventory.
- `baseline.mjs` — snapshots DOM hooks (ids, classes, `data-*`, names) and smell metrics
  before/after, and compares two snapshots.
- `contract.mjs` — extracts every DOM hook the page scripts ask for by string
  (`getElementById`, `querySelector`, `closest`, …) and checks the markup still exposes it.
- `verify.mjs` — renders each page and diffs its hooks against the pre-refactor baseline.
- `preview/` — the static mock preview server (see below).

---

## Per-page changes

Every page was converted to the layout; below are the page-specific decisions and the
bugs the conversion fixed.

### Dashboard (`index.php`)

- Body markup kept **verbatim** — the dashboard has its own design system in
  `css/dashboard.css` (`.stats-grid.modern`, `.chart-card`, `.widget-card`) that
  deliberately does not use the shared `.stat-card`/`.page-header`.
- Chart.js is loaded between `main.js` and `dashboard.js`, as before.
- **Bug fixed:** the page's trailing inline `window.APP_CONFIG = {...}` hard-coded paths
  that dropped the install subdirectory, breaking the app when served from `/church/`.
  Removed — `head.php` now publishes the correct API config.
- Now loads `env.js` and `config.js`, the one page that previously did not. This is safe:
  `dashboard.js` uses its own local `const API_BASE = 'backend'` and never reads
  `window.API_BASE`.
- CSRF meta is now emitted.
- **One visible change:** the hand-rolled theme snippet always defaulted to light; it now
  uses the shared pre-paint theme script, so a **first-time visitor whose OS prefers dark
  will get dark mode instead of light**. Returning visitors are unaffected (their stored
  preference still wins).

### Attendance

- Two `.page-content` siblings preserved (`#attendance-tab` active, `#reports-tab`) —
  `main.js showPage()` depends on this structure.
- Script order preserved: Chart.js (head) → `attendance-report.js` → `attendance.js` →
  `main.js`.
- **Bug fixed:** the page had **no auth guard at all** — any unauthenticated visitor could
  load it. It now requires login.
- **Bug fixed:** unquoted `<?= $_SESSION['user_id'] ?>` was an XSS/HTML-breakage risk; now
  escaped.
- **Bug fixed:** it read `$page['isAdmin']` before the page config existed; now derived
  from `$_SESSION`.
- Icons are inline SVG on this page (unchanged).

### Financial

- `showFooter => false`, as the original had no footer.
- Three modals (including the 5-step reporting wizard) kept page-local — too specific to
  generalise.
- **Bug fixed:** the page never started a session, so its role checks silently failed.
- **Bug fixed:** a `/church/`-prefixed `APP_CONFIG` override clobbered the real API base.
- Note: `report-content` on this page is a **report modal body** id, which is why the
  shared `<main>` must never take an id.

### Reports (`report.php`)

- Largest page (1,542 lines). Uses `contentTag => 'main'` (id `main-content` on `<main>`,
  no `.page-content` wrapper) and `topnavPlacement => 'in-content'` to reproduce the
  original nesting exactly.
- ~1,040-line inline script kept as a PHP heredoc and injected via `bodyInline`. It
  interpolates `API_BASE_URL`, `CSRF_TOKEN` and `USER_ROLE`, so it must stay
  PHP-interpolated rather than move to a `.js` file.
- 6 CDN libraries loaded via `preFooterScripts`, keeping their original order and
  `type="text/javascript"` attributes.
- `showFooter => false`; `emailReportModal` supplied via `overlays`.
- Keeps its own role allowlist (admin/pastor/pcf) with a database permission fallback for
  `module='reports'`.
- **Bug fixed:** two redirects pointed at `frontend/pages/dashboard.php`, which does not
  exist; they now go to the root URL.

### Members

- Filters, `.searchable-select` and all six modals kept page-local.
- **Bug fixed:** no access check — now requires login.
- **Bug fixed:** `$assetBase` was undefined, producing 404s on its assets.
- **Bug fixed:** the CSRF meta tag was emitted three times.
- **Bug fixed:** a local `API_BASE` shadowed the global.

### Settings

- Inline `<style>` extracted to `css/settings.css`, including its 13 `[data-theme="dark"]`
  selectors.
- Uses Font Awesome **6.4.0** and Bootstrap CSS before `style.css`, with jQuery after the
  Bootstrap bundle, and no `ui.js`/`main.js` — all matching the original.
- Tabs use `data-bs-*` attributes.
- **Bug fixed:** a broken admin redirect.

### Profile

- Inline `<style>` extracted to `css/profile.css` (inline style attributes fell 36 → 3).
- **Bug fixed:** `can_access('profile')` redirected to a wrong path.

### Users / Fellowships / Departments / Cells / Birthdays / Permissions / Link Members / Create Member Users

- Converted to the shared components (`layout_page_header`, `layout_toolbar`,
  `layout_data_table`, `layout_stat_card`, `layout_modal`, `layout_form_group`, …).
- Users keeps its page-local pagination and modal markup.
- Departments and cells had inline `onclick` removed (1 → 0 each); cells' inline `<style>`
  moved to `css/cells.css`.
- Link Members and Create Member Users: inline `<style>` extracted to their own CSS files;
  a local `API_BASE` that shadowed the global was removed.
- **Bug fixed (Permissions):** bad redirect target, and the CSRF meta tag was missing.

### Shared fixes applying to all pages

- Relative `include`/asset paths → `layout_url()` / `layout_asset()`, with mtime-based
  cache-busting.
- `window.APP_CONFIG` is published once, by `head.php`.
- One CSRF meta tag per page.

---

## What was deliberately **not** changed

- **No JavaScript file was edited.** `attendance.js` (134 KB), `financial.js` (88 KB),
  `members.js`, `users.js`, `cells.js`, `dashboard.js` and the rest are byte-identical.
  Every id, class, `data-*` attribute and `name` they bind to is still in the markup.
- **Inline `onclick` handlers were preserved** where the page had them. Adding delegation
  would have meant writing new listeners, which is out of scope for a neutral refactor.
- **Script and stylesheet order is preserved per page**, even where pages differ from one
  another — the cascade and initialisation order are load-bearing.
- **Duplicate ids were not "fixed."** `members.php` has duplicate `member-status` and
  `member-category` ids; renaming them would break `members.js`.
- **Pre-existing quirks in shared JS were left alone:** `main.js`/`ui.js` query ids that
  have never existed (`darkmode-toggle`, `theme-toggle`, `notifications-dropdown`,
  `profile-dropdown`).
- **`frontend/css/icons.css`** is the project's dependency-free Font Awesome replacement
  (110 `.fa-*::before` rules mapping to emoji). Audited against the pre-refactor commit
  and left as-is.

---

## Verification

Three independent gates. Run them from the repository root.

### 1. Hook contract — nothing the JS needs was lost

```bash
node scripts/refactor/verify.mjs
```

Renders all 15 pages through the interpreter and diffs their DOM hooks against the
pre-refactor baseline:

```
migrated 15/15 pages | render errors 0 | hooks lost 0
```

| Page | ids | classes | data-attrs | names |
| --- | --- | --- | --- | --- |
| dashboard | 53 | 102 | 1 | 4 |
| users | 75 | 145 | 3 | 3 |
| fellowships | 48 | 100 | 1 | 3 |
| departments | 40 | 91 | 2 | 3 |
| cells | 57 | 98 | 4 | 3 |
| attendance | 102 | 113 | 1 | 4 |
| financial | 117 | 175 | 2 | 3 |
| reports | 65 | 162 | 2 | 3 |
| members | 110 | 149 | 2 | 19 |
| birthdays | 39 | 79 | 2 | 5 |
| settings | 67 | 116 | 6 | 20 |
| profile | 51 | 94 | 1 | 3 |
| permissions | 30 | 60 | 3 | 3 |
| link_members | 38 | 70 | 1 | 3 |
| create_member_users | 40 | 72 | 1 | 3 |

Counts exceed the baseline (e.g. dashboard was 27/52/0/2) because the shared chrome
contributes hooks; the contract only requires that nothing is *lost*.

Useful flags: `--page <id>` to check one page, `--diff` to print per-hook detail,
`--baseline <path>` to compare against a different snapshot.

### 2. Smell + hook comparison, pre-refactor → now

```bash
node scripts/refactor/baseline.mjs --out tmp/refactor/after.json \
  --compare tmp/refactor/baseline-prefix.json
```

`tmp/refactor/baseline-prefix.json` is the snapshot of the pages **as they were at the
pre-refactor commit**. Result: **`No hooks lost.`**

Lines of markup fell on 10 of 15 pages — profile 757→587, link_members 551→326,
create_member_users 661→363, settings 877→790, cells 397→309, users 453→420,
fellowships 281→255, departments 172→148, birthdays 121→100, financial 832→823. It rose
on the five pages that gained explicit config arrays or preserved long inline scripts:
dashboard 286→312, attendance 545→613, reports 1506→1542, members 617→646,
permissions 160→178. Inline style attributes fell sharply where CSS was extracted
(profile 36→3, cells 40→10, departments 12→0).

Asset deltas: **zero missing assets on all 15 pages.** The only additions are legitimate —
the dashboard now loads `env.js`/`config.js`, and the five new CSS files.

### 3. JS → markup contract

```bash
node scripts/refactor/contract.mjs --check
```

Extracts every DOM hook the page scripts reference by string and confirms the markup
exposes it. Result: **28 unresolved hooks — exactly the pre-refactor floor, no growth.**

Those 28 are pre-existing and were already unresolved before this branch. They are hooks
the scripts create at runtime or query defensively: attendance (8 ids, 4 classes),
financial (3 ids, 2 classes), dashboard (2 classes), users (6 classes), members (1 id,
2 classes). The command exits 1 whenever any hook is unresolved, so exit 1 here is
expected and matches the pre-refactor behaviour.

---

## The static preview server

PHP cannot run here, so a Node server renders the pages instead. It is a **review tool,
not a deployment target** — it performs no database access and saves nothing.

```bash
node scripts/refactor/preview/server.cjs --port 4173 --host 0.0.0.0
```

Open `/__preview__/` for a list of all 15 pages, or go straight to `/index.php`.

What it does:

- `.php` page requests are rendered through the same interpreter `verify.mjs` uses, with a
  synthetic logged-in session;
- `/backend/*` requests are answered from `preview/fixtures.cjs`, in the exact shape the
  real endpoints return (`backend/members/read.php` returns `{data, pagination}`,
  `backend/fellowships/read.php` returns a bare array, `backend/users/read.php` returns
  `{users, pagination}`, and so on), so the **unmodified** page scripts populate their
  tables and charts;
- everything else is served from the repository, so the real CSS and JS load.

Options:

- `?role=member` (or `pastor`, `pcf`, `fellowship_leader`) previews the page as that role.
  The choice sticks via a cookie so sidebar links keep it. Admin-only pages respond with an
  explicit **"PERMISSION GUARD FIRED"** notice, because the guard's `exit()` has no HTTP
  layer to redirect through.
- `?fresh=1` bypasses the render cache.

Reading the preview: charts, tables and counts show fixture data, not real records.
Anything that would write to the database returns a success message and changes nothing.

---

## Click-test checklist for a real environment

The interpreter cannot execute JavaScript, click a button, or talk to MySQL. These checks
need a local PHP + MySQL install with real data.

**Every page**

- [ ] Page loads with no PHP notices or warnings (check the error log).
- [ ] Sidebar highlights the current page.
- [ ] Breadcrumbs/page title are correct.
- [ ] Styles match the pre-refactor look at desktop and mobile widths.
- [ ] No 404s in the network tab (CSS, JS, fonts, images).
- [ ] Dark mode: toggle it, reload, confirm the choice persists.

**Dashboard**

- [ ] Stat cards populate; charts render.
- [ ] Refresh button re-fetches data.
- [ ] Quick links to members/attendance/financial work.
- [ ] First visit with OS dark preference → confirm the new dark-by-default is acceptable.

**Members**

- [ ] Search, and each filter, narrows the table.
- [ ] Pagination moves through pages.
- [ ] Add / edit / delete a member; confirm the modal opens, saves, and the table updates.
- [ ] `.searchable-select` dropdowns open and select.
- [ ] All six modals open and close cleanly.

**Attendance**

- [ ] Both tabs (`#attendance-tab`, `#reports-tab`) switch.
- [ ] Record attendance for a service; save; reload; confirm it persisted.
- [ ] Date picker and report generation work.
- [ ] Report chart renders; email report sends.

**Financial**

- [ ] Income/expense records list and filter.
- [ ] Stat cards show correct totals.
- [ ] The 5-step reporting wizard advances through all steps.
- [ ] Chart renders; export and send-report work.
- [ ] Footer is absent, as before.

**Reports**

- [ ] All 6 CDN libraries load (moment, daterangepicker, DataTables, …).
- [ ] DataTables sorting/paging works.
- [ ] Date-range filter applies.
- [ ] Email report modal opens and sends.

**Settings**

- [ ] Every tab (`data-bs-*`) switches and shows its panel.
- [ ] Save each settings section; reload; confirm values persisted.
- [ ] Dark-mode selectors from `css/settings.css` still apply correctly.

**Users / Permissions**

- [ ] Create, edit, delete a user.
- [ ] Permission matrix saves.
- [ ] Redirect after each action lands on the right page.

**Fellowships / Departments / Cells**

- [ ] Create, edit, delete on each.
- [ ] Cell member list loads (`backend/ajax/cell_members.php`).
- [ ] Filters and export buttons work.

**Profile / Birthdays / Link Members / Create Member Users**

- [ ] Profile updates save; avatar placeholder renders.
- [ ] Birthday list loads and "send wish" fires.
- [ ] Link Members and Create Member Users complete their flows end to end.

**Permissions (log in as each role)**

- [ ] `member` is redirected away from users, birthdays, settings, permissions,
      link_members, create_member_users — and away from fellowships/departments/cells/reports.
- [ ] `fellowship_leader` can reach attendance, financial, members, profile only.
- [ ] `pastor`/`pcf` cannot reach users, birthdays, settings, permissions.
- [ ] Attendance, financial and members now **require login** — confirm an unauthenticated
      visitor is redirected (previously these three had no guard).

---

## Known limitations of the tooling

- The interpreter covers the PHP subset these pages use. It does not model `static`
  variable caching, by-reference returns (`function &f()`), or exception objects — the
  layout guards use `layout_fail()` rather than `throw` for this reason.
- It cannot run JavaScript, so anything a script builds at runtime is invisible to the
  hook check. That is what the 28 unresolved contract hooks are.
- `login.php` and `kingschat_register.php` are unmigrated, so the preview server reports
  them as "not migrated" rather than rendering them.

---

## Files in this branch

**Modified (15):** `index.php` and the 14 pages under `frontend/pages/`.

**Added:** `frontend/layout/` (5 files), 5 CSS files under `frontend/css/`,
`scripts/refactor/` (10 modules), and this document.

**Not committed:** `tmp/refactor/` holds generated snapshots (`baseline.json`,
`after.json`, `contract.json`, `verify.json`). `tmp/refactor/baseline-prefix.json` is the
pre-refactor reference that gate 2 compares against; regenerate it from the pre-refactor
commit if you need a clean copy.
