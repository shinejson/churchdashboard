/**
 * Shared page inventory for the sidebar-page refactor harness.
 *
 * Keeps the list of pages, their assets and their JS/CSS dependencies in one
 * place so the baseline snapshot, the contract report and the static preview
 * all agree on what "the pages in the sidebar" means.
 */
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

/**
 * Pages reachable from the sidebar (plus the root dashboard they land on).
 *
 * `js` / `css` are the local assets the page loads today; `cdn` are third-party
 * libraries whose globals the page scripts expect to exist.
 * `aux` marks pages that are not sidebar entries themselves but are opened from
 * one (member account linking, KingsChat registration).
 */
export const PAGES = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    file: 'index.php',
    permission: 'dashboard',
    js: ['frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/dashboard.js'],
    css: [
      'frontend/css/style.css',
      'frontend/css/screens.css',
      'frontend/css/icons.css',
      'frontend/css/dashboard.css'
    ],
    cdn: ['chart.js']
  },
  {
    id: 'users',
    label: 'Users',
    file: 'frontend/pages/users.php',
    permission: 'users',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/users.js'],
    css: ['frontend/css/style.css', 'frontend/css/screens.css', 'frontend/css/icons.css', 'frontend/css/users.css'],
    cdn: []
  },
  {
    id: 'fellowships',
    label: 'Fellowships',
    file: 'frontend/pages/fellowships.php',
    permission: 'fellowships',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/fellowships.js'],
    css: ['frontend/css/style.css', 'frontend/css/fellowships.css', 'frontend/css/icons.css'],
    cdn: []
  },
  {
    id: 'departments',
    label: 'Departments',
    file: 'frontend/pages/departments.php',
    permission: 'fellowships',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/departments.js'],
    css: ['frontend/css/style.css', 'frontend/css/fellowships.css', 'frontend/css/icons.css'],
    cdn: []
  },
  {
    id: 'cells',
    label: 'Home Cells',
    file: 'frontend/pages/cells.php',
    permission: 'fellowships',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/cells.js'],
    css: ['frontend/css/style.css', 'frontend/css/fellowships.css', 'frontend/css/icons.css'],
    cdn: []
  },
  {
    id: 'attendance',
    label: 'Attendance',
    file: 'frontend/pages/attendance.php',
    permission: 'attendance',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/main.js', 'frontend/js/attendance.js', 'frontend/js/attendance-report.js'],
    css: ['frontend/css/style.css', 'frontend/css/attendance.css'],
    cdn: ['chart.js']
  },
  {
    id: 'financial',
    label: 'Financial',
    file: 'frontend/pages/financial.php',
    permission: 'financial',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/financial.js', 'frontend/js/financial-report.js'],
    css: ['frontend/css/style.css', 'frontend/css/screens.css', 'frontend/css/financial.css', 'frontend/css/icons.css'],
    cdn: []
  },
  {
    id: 'reports',
    label: 'Reports',
    file: 'frontend/pages/report.php',
    permission: 'reports',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js'],
    css: ['frontend/css/style.css', 'frontend/css/report.css'],
    cdn: ['jquery', 'bootstrap', 'datatables', 'chart.js', 'moment', 'daterangepicker', 'font-awesome']
  },
  {
    id: 'members',
    label: 'Members',
    file: 'frontend/pages/members.php',
    permission: 'admin_only',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js', 'frontend/js/members.js'],
    css: ['frontend/css/style.css', 'frontend/css/screens.css', 'frontend/css/members.css'],
    cdn: ['papaparse', 'xlsx', 'jszip', 'font-awesome']
  },
  {
    id: 'birthdays',
    label: 'Birthdays',
    file: 'frontend/pages/birthdays.php',
    permission: 'admin_only',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/birthdays.js'],
    css: ['frontend/css/style.css', 'frontend/css/screens.css', 'frontend/css/birthdays.css'],
    cdn: ['font-awesome']
  },
  {
    id: 'settings',
    label: 'Settings',
    file: 'frontend/pages/settings.php',
    permission: 'admin_only',
    js: ['frontend/js/env.js', 'frontend/js/config.js'],
    css: ['frontend/css/style.css'],
    cdn: ['jquery', 'bootstrap', 'font-awesome']
  },
  {
    id: 'profile',
    label: 'My Profile',
    file: 'frontend/pages/profile.php',
    permission: 'profile',
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js'],
    css: ['frontend/css/style.css'],
    cdn: []
  },
  {
    id: 'permissions',
    label: 'Permissions (from profile menu)',
    file: 'frontend/pages/permissions.php',
    permission: 'admin_only',
    aux: true,
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/main.js'],
    css: ['frontend/css/style.css', 'frontend/css/icons.css'],
    cdn: []
  },
  {
    id: 'link_members',
    label: 'Link Accounts (from Members)',
    file: 'frontend/pages/link_members.php',
    permission: 'admin_only',
    aux: true,
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js'],
    css: ['frontend/css/style.css', 'frontend/css/screens.css'],
    cdn: ['font-awesome']
  },
  {
    id: 'create_member_users',
    label: 'Create User Accounts (from Members)',
    file: 'frontend/pages/create_member_users.php',
    permission: 'admin_only',
    aux: true,
    js: ['frontend/js/env.js', 'frontend/js/config.js', 'frontend/js/ui.js', 'frontend/js/main.js'],
    css: ['frontend/css/style.css', 'frontend/css/screens.css'],
    cdn: ['font-awesome']
  }
];

/** Read a repo-relative file, or '' when it is missing. */
export function readRepo(relPath) {
  const abs = join(REPO_ROOT, relPath);
  return existsSync(abs) ? readFileSync(abs, 'utf8') : '';
}

/** Unique, sorted array helper. */
export function uniq(values) {
  return [...new Set(values)].sort();
}

/**
 * Strip PHP tags from a page source.
 *
 * Used only by the preview/verification tooling: it approximates what a page
 * would render with no session, which is enough to compare structure before and
 * after a refactor. Conditional PHP blocks are removed along with their tags.
 */
export function stripPhp(source) {
  return source
    .replace(/<\?php[\s\S]*?\?>/g, '')
    .replace(/<\?=[\s\S]*?\?>/g, '')
    .replace(/<\?[\s\S]*?\?>/g, '');
}
