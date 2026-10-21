'use strict';

/**
 * Fake data for the static preview server.
 *
 * PHP and MySQL cannot run in this environment, so the preview server answers
 * the /backend/* calls that the page scripts make with the payloads below.
 * Each fixture mirrors the shape the *real* endpoint returns (checked against
 * backend/**\/read.php), so the unmodified page scripts render as they would
 * against a live database.
 *
 * Nothing here is part of the application: this file is only ever loaded by
 * scripts/refactor/preview/server.cjs.
 */

const fellowships = [
  { id: 1, name: 'Grace Fellowship', description: 'Sunday morning congregation', leader: 'Pastor Daniel Osei', status: 'active', meeting_location: 'Main Auditorium', meeting_day: 'Sunday', meeting_time: '09:00', member_count: 148, assistant: 'Sarah Mensah' },
  { id: 2, name: 'Worship Ministry', description: 'Music and worship team', leader: 'Kojo Antwi', status: 'active', meeting_location: 'Rehearsal Hall', meeting_day: 'Saturday', meeting_time: '16:00', member_count: 24, assistant: 'Abena Boateng' },
  { id: 3, name: 'Youth Fellowship', description: 'Ages 13-25', leader: 'Nana Yaw Darko', status: 'active', meeting_location: 'Youth Centre', meeting_day: 'Friday', meeting_time: '18:30', member_count: 67, assistant: 'Efua Asare' },
  { id: 4, name: "Women's Fellowship", description: 'Womens ministry and outreach', leader: 'Mrs. Akosua Frimpong', status: 'active', meeting_location: 'Fellowship Hall', meeting_day: 'Wednesday', meeting_time: '10:00', member_count: 91, assistant: 'Gifty Owusu' },
  { id: 5, name: "Men's Fellowship", description: 'Mens ministry and mentorship', leader: 'Mr. Kwabena Adjei', status: 'active', meeting_location: 'Fellowship Hall', meeting_day: 'Thursday', meeting_time: '19:00', member_count: 58, assistant: 'Samuel Tetteh' },
  { id: 6, name: 'Evangelism Team', description: 'Outreach and street evangelism', leader: 'Rev. Joseph Amoah', status: 'inactive', meeting_location: 'Church Office', meeting_day: 'Saturday', meeting_time: '07:00', member_count: 19, assistant: null },
];

const departments = [
  { id: 1, name: 'Ushering', description: 'Welcoming and seating members during services', leader: 'Deacon Isaac Bediako', fellowship_id: 1, member_count: 22, status: 'active' },
  { id: 2, name: 'Choir', description: 'Lead congregational worship', leader: 'Kojo Antwi', fellowship_id: 2, member_count: 18, status: 'active' },
  { id: 3, name: 'Media & Sound', description: 'Sound engineering, projection and streaming', leader: 'Kofi Asante', fellowship_id: 2, member_count: 9, status: 'active' },
  { id: 4, name: 'Sunday School', description: 'Childrens teaching ministry', leader: 'Mrs. Mercy Appiah', fellowship_id: 3, member_count: 15, status: 'active' },
  { id: 5, name: 'Welfare', description: 'Support for members in need', leader: 'Elder Francis Owusu', fellowship_id: 1, member_count: 7, status: 'active' },
  { id: 6, name: 'Prayer Band', description: 'Intercession before and after services', leader: 'Mrs. Comfort Yeboah', fellowship_id: 4, member_count: 12, status: 'active' },
];

const cells = [
  { id: 1, name: 'Adom Cell', fellowship_id: 1, fellowship_name: 'Grace Fellowship', leader: 'Alexander Nartey', location: 'East Legon - Plot 12', meeting_day: 'Tuesday', meeting_time: '19:00', member_count: 14, status: 'active' },
  { id: 2, name: 'Bethel Cell', fellowship_id: 1, fellowship_name: 'Grace Fellowship', leader: 'Priscilla Addo', location: 'Madina - Street 5', meeting_day: 'Wednesday', meeting_time: '19:00', member_count: 11, status: 'active' },
  { id: 3, name: 'Canaan Cell', fellowship_id: 4, fellowship_name: "Women's Fellowship", leader: 'Grace Amoateng', location: 'Achimota - New Otun', meeting_day: 'Thursday', meeting_time: '18:30', member_count: 9, status: 'active' },
  { id: 4, name: 'Damascus Cell', fellowship_id: 5, fellowship_name: "Men's Fellowship", leader: 'Emmanuel Quartey', location: 'Spintex - Baatsona', meeting_day: 'Friday', meeting_time: '19:30', member_count: 8, status: 'active' },
  { id: 5, name: 'Elim Cell', fellowship_id: 3, fellowship_name: 'Youth Fellowship', leader: 'Daniel Kumi', location: 'Legon - Bush Campus', meeting_day: 'Saturday', meeting_time: '17:00', member_count: 16, status: 'active' },
  { id: 6, name: 'Faith Cell', fellowship_id: 1, fellowship_name: 'Grace Fellowship', leader: null, location: 'Tema Community 8', meeting_day: 'Wednesday', meeting_time: '19:00', member_count: 5, status: 'inactive' },
];

const firstNames = ['Kwame', 'Abena', 'Yaw', 'Akua', 'Kojo', 'Ama', 'Kofi', 'Adjoa', 'Kwesi', 'Afia', 'Nana', 'Esi'];
const lastNames = ['Mensah', 'Owusu', 'Asante', 'Boateng', 'Frimpong', 'Agyeman', 'Darko', 'Amoah', 'Tetteh', 'Appiah', 'Yeboah', 'Ofori'];
const rolesInChurch = ['Member', 'Deacon', 'Deaconess', 'Elder', 'Usher', 'Choir Member', 'Sunday School Teacher', 'Youth Leader'];
const statuses = ['active', 'active', 'active', 'active', 'inactive', 'new'];
const categories = ['Regular', 'Regular', 'Regular', 'First Timer', 'Youth', 'Senior'];

/** Deterministic pseudo-random so the preview looks the same on every load. */
function pick(list, i) {
  return list[i % list.length];
}

const members = [];
for (let i = 0; i < 24; i += 1) {
  const fellowship = pick(fellowships, i);
  const cell = pick(cells, i);
  const department = pick(departments, i);
  const joinYear = 2016 + (i % 9);
  members.push({
    id: i + 1,
    first_name: pick(firstNames, i),
    last_name: pick(lastNames, i + 5),
    phone: `0${20 + (i % 7)} ${100 + i} ${4000 + i * 37}`.slice(0, 14),
    email: `${pick(firstNames, i).toLowerCase()}.${pick(lastNames, i + 5).toLowerCase()}@example.com`,
    address: `House ${i + 3}, Street ${1 + (i % 8)}, ${pick(['East Legon', 'Madina', 'Achimota', 'Spintex', 'Tema', 'Dansoman'], i)}`,
    birth_date: `${1970 + (i % 35)}-${String(1 + (i % 12)).padStart(2, '0')}-${String(1 + ((i * 3) % 28)).padStart(2, '0')}`,
    join_date: `${joinYear}-0${1 + (i % 9)}-1${i % 9}`,
    fellowship_id: fellowship.id,
    fellowship_name: fellowship.name,
    cell_id: cell.id,
    cell_name: cell.name,
    department_id: department.id,
    department_name: department.name,
    role_in_church: pick(rolesInChurch, i),
    status: pick(statuses, i),
    category: pick(categories, i),
    photo: null,
    photo_url: null,
    notes: i % 4 === 0 ? 'Prefers evening services.' : '',
  });
}

const users = [
  { id: 1, username: 'admin', full_name: 'System Administrator', email: 'admin@church.local', role: 'admin', status: 'active', created_at: '2016-01-04 09:12:00', last_login: '2026-10-20 07:41:00', fellowship_id: null, fellowship_name: null },
  { id: 2, username: 'pastor', full_name: 'Pastor Daniel Osei', email: 'pastor@church.local', role: 'pastor', status: 'active', created_at: '2016-02-11 10:02:00', last_login: '2026-10-19 18:05:00', fellowship_id: 1, fellowship_name: 'Grace Fellowship' },
  { id: 3, username: 'p.adiomah', full_name: 'Priscilla Adiomah', email: 'p.adiomah@church.local', role: 'pcf', status: 'active', created_at: '2019-06-30 14:20:00', last_login: '2026-10-18 12:44:00', fellowship_id: 4, fellowship_name: "Women's Fellowship" },
  { id: 4, username: 'k.antwi', full_name: 'Kojo Antwi', email: 'k.antwi@church.local', role: 'fellowship_leader', status: 'active', created_at: '2020-03-15 08:55:00', last_login: '2026-10-17 16:30:00', fellowship_id: 2, fellowship_name: 'Worship Ministry' },
  { id: 5, username: 'e.asare', full_name: 'Efua Asare', email: 'e.asare@church.local', role: 'member', status: 'active', created_at: '2022-09-01 11:10:00', last_login: '2026-10-11 09:02:00', fellowship_id: 3, fellowship_name: 'Youth Fellowship' },
  { id: 6, username: 's.tetteh', full_name: 'Samuel Tetteh', email: 's.tetteh@church.local', role: 'member', status: 'inactive', created_at: '2021-05-22 13:47:00', last_login: '2025-12-30 19:15:00', fellowship_id: 5, fellowship_name: "Men's Fellowship" },
];

const paginationFor = (total, perPage) => ({
  current_page: 1,
  total_pages: Math.max(1, Math.ceil(total / perPage)),
  total_records: total,
  total_count: total,
  per_page: perPage,
});

const today = new Date();
const iso = (d) => d.toISOString().slice(0, 10);
const daysAgo = (n) => { const d = new Date(today); d.setDate(d.getDate() - n); return iso(d); };

/** Six weeks of Sunday attendance, trending gently upward. */
const attendanceServices = [];
for (let w = 5; w >= 0; w -= 1) {
  const d = new Date(today);
  d.setDate(d.getDate() - d.getDay() - w * 7);
  const adults = 180 + (5 - w) * 7 + (w % 3) * 4;
  attendanceServices.push({
    id: attendanceServices.length + 1,
    service_date: iso(d),
    service_type: 'Sunday Service',
    fellowship_id: 1,
    fellowship_name: 'Grace Fellowship',
    total_attendance: adults + 40,
    adults,
    children: 40,
    first_timers: 2 + (w % 4),
    notes: w === 0 ? 'Combined service with the youth fellowship.' : '',
    recorded_by: 'Pastor Daniel Osei',
    created_at: `${iso(d)} 12:30:00`,
  });
}

const birthdays = members
  .map((m) => ({ ...m, birthday: m.birth_date.slice(5), age: today.getFullYear() - Number(m.birth_date.slice(0, 4)) }))
  .sort((a, b) => a.birthday.localeCompare(b.birthday))
  .slice(0, 10);

const financialRecords = [];
let recordId = 1;
for (let w = 5; w >= 0; w -= 1) {
  const date = daysAgo(w * 7);
  const tithes = 4200 + (5 - w) * 180;
  financialRecords.push({
    id: recordId++, type: 'income', category: 'Tithes', amount: tithes, date,
    description: 'Weekly tithes and offerings', recorded_by: 'Kojo Antwi',
    payment_method: 'Bank Transfer', reference: `TT-${1000 + recordId}`, fellowship_id: 1,
  });
  financialRecords.push({
    id: recordId++, type: 'income', category: 'Offerings', amount: 1150 + (5 - w) * 45, date,
    description: 'Sunday offerings', recorded_by: 'Kojo Antwi',
    payment_method: 'Cash', reference: `OF-${2000 + recordId}`, fellowship_id: 1,
  });
  financialRecords.push({
    id: recordId++, type: 'expense', category: 'Utilities', amount: 640, date,
    description: 'Electricity and water', recorded_by: 'Samuel Tetteh',
    payment_method: 'Bank Transfer', reference: `UT-${3000 + recordId}`, fellowship_id: 1,
  });
}

const totalIncome = financialRecords.filter((r) => r.type === 'income').reduce((s, r) => s + r.amount, 0);
const totalExpenses = financialRecords.filter((r) => r.type === 'expense').reduce((s, r) => s + r.amount, 0);

const labels = [];
const tithesSeries = [];
const expensesSeries = [];
for (let w = 5; w >= 0; w -= 1) {
  labels.push(daysAgo(w * 7));
  tithesSeries.push(4200 + (5 - w) * 180);
  expensesSeries.push(640);
}

const cellMembers = (cellId) => members
  .filter((m) => m.cell_id === Number(cellId))
  .map((m) => ({
    id: m.id, member_id: m.id, first_name: m.first_name, last_name: m.last_name,
    name: `${m.first_name} ${m.last_name}`, phone: m.phone, email: m.email,
    role: m.role_in_church, status: 'active', joined_date: m.join_date,
  }));

const ok = (data) => ({ success: true, data });

/**
 * Endpoint table.
 *
 * Keys are matched against the request pathname *after* the install prefix is
 * normalised away, so both /backend/... and /church/backend/... resolve here.
 * A value may be a plain object or a function of the parsed query.
 */
const routes = {
  '/backend/members/read.php': () => ({ data: members, members, pagination: paginationFor(members.length, 20) }),
  '/backend/members/birthdays_read.php': () => ({ success: true, data: birthdays, birthdays }),
  '/backend/members/send_birthday_wish.php': () => ({ success: true, message: 'Birthday wish queued (preview - nothing was sent).' }),
  '/backend/members/create.php': () => ({ success: true, message: 'Member created (preview - nothing was saved).' }),
  '/backend/members/update.php': () => ({ success: true, message: 'Member updated (preview - nothing was saved).' }),
  '/backend/members/delete.php': () => ({ success: true, message: 'Member deleted (preview - nothing was saved).' }),

  '/backend/fellowships/read.php': () => fellowships,
  '/backend/fellowships/create.php': () => ({ success: true, message: 'Fellowship created (preview).' }),
  '/backend/fellowships/update.php': () => ({ success: true, message: 'Fellowship updated (preview).' }),
  '/backend/fellowships/delete.php': () => ({ success: true, message: 'Fellowship deleted (preview).' }),

  '/backend/users/read.php': () => ({ users, data: users, pagination: paginationFor(users.length, 20) }),
  '/backend/users/get_csrf.php': () => ({ success: true, csrf_token: 'preview-csrf-token', token: 'preview-csrf-token' }),
  '/backend/users/create.php': () => ({ success: true, message: 'User created (preview).' }),
  '/backend/users/update.php': () => ({ success: true, message: 'User updated (preview).' }),
  '/backend/users/delete.php': () => ({ success: true, message: 'User deleted (preview).' }),

  '/backend/attendance/read.php': () => ok({ services: attendanceServices, attendance: attendanceServices }),
  '/backend/attendance/report.php': (query) => {
    const type = query.get('type') || 'overview';
    if (type === 'trends') return ok({ labels, values: tithesSeries.map((_, i) => 180 + i * 7), data: tithesSeries.map((_, i) => 180 + i * 7) });
    return ok({
      labels,
      values: tithesSeries.map((_, i) => 180 + i * 7),
      total_attendance: attendanceServices.reduce((s, r) => s + r.total_attendance, 0),
      total_first_timers: attendanceServices.reduce((s, r) => s + r.first_timers, 0),
      average_attendance: Math.round(attendanceServices.reduce((s, r) => s + r.total_attendance, 0) / attendanceServices.length),
      services: attendanceServices,
    });
  },
  '/backend/attendance/get_report_data.php': () => ok({ labels, values: tithesSeries.map((_, i) => 180 + i * 7) }),
  '/backend/attendance/get_by_date.php': () => ok(attendanceServices[attendanceServices.length - 1]),
  '/backend/attendance/create.php': () => ({ success: true, message: 'Attendance recorded (preview).' }),
  '/backend/attendance/update.php': () => ({ success: true, message: 'Attendance updated (preview).' }),
  '/backend/attendance/delete.php': () => ({ success: true, message: 'Attendance deleted (preview).' }),
  '/backend/attendance/record.php': () => ({ success: true, message: 'Attendance recorded (preview).' }),
  '/backend/attendance/send_report.php': () => ({ success: true, message: 'Report sent (preview - nothing was emailed).' }),
  '/backend/attendance/check_members.php': () => ok(members.slice(0, 8).map((m) => ({ id: m.id, name: `${m.first_name} ${m.last_name}`, present: false }))),

  '/backend/financial/read.php': () => ({
    success: true,
    data: financialRecords,
    records: financialRecords,
    summary: { total_income: totalIncome, total_expenses: totalExpenses, net_balance: totalIncome - totalExpenses },
    pagination: paginationFor(financialRecords.length, 20),
  }),
  '/backend/financial/chart_data.php': () => ({ labels, tithes: tithesSeries, expenses: expensesSeries }),
  '/backend/financial/report.php': () => ({
    success: true,
    data: { labels, tithes: tithesSeries, expenses: expensesSeries, total_income: totalIncome, total_expenses: totalExpenses, net_balance: totalIncome - totalExpenses },
  }),
  '/backend/financial/create.php': () => ({ success: true, message: 'Record created (preview).' }),
  '/backend/financial/update.php': () => ({ success: true, message: 'Record updated (preview).' }),
  '/backend/financial/delete.php': () => ({ success: true, message: 'Record deleted (preview).' }),
  '/backend/financial/record.php': () => ({ success: true, message: 'Record saved (preview).' }),
  '/backend/financial/send_report.php': () => ({ success: true, message: 'Report sent (preview - nothing was emailed).' }),
  '/backend/financial/tithe.php': () => ({ success: true, data: financialRecords.filter((r) => r.category === 'Tithes') }),

  '/backend/tithe_periods/read.php': () => ({
    success: true,
    periods: [{ id: 1, name: 'October 2026', start_date: daysAgo(20), end_date: iso(today), status: 'open' }],
    data: [{ id: 1, name: 'October 2026', start_date: daysAgo(20), end_date: iso(today), status: 'open' }],
  }),
  '/backend/tithe_transactions/read.php': () => ok(financialRecords.filter((r) => r.category === 'Tithes')),

  '/backend/partnerships/read.php': () => ({ success: true, data: [], partnerships: [] }),
  '/backend/api/partnerships/read.php': () => ({ success: true, data: [], partnerships: [] }),
  '/backend/partnerships/create.php': () => ({ success: true, message: 'Partnership created (preview).' }),
  '/backend/partnerships/update.php': () => ({ success: true, message: 'Partnership updated (preview).' }),
  '/backend/partnerships/delete.php': () => ({ success: true, message: 'Partnership deleted (preview).' }),

  '/backend/ajax/cells_crud.php': () => ({ success: true, data: cells, cells }),
  '/backend/ajax/cell_members.php': (query) => ({ success: true, data: cellMembers(query.get('cell_id') || 1), members: cellMembers(query.get('cell_id') || 1) }),
  '/backend/ajax/departments_crud.php': () => ({ success: true, data: departments, departments }),
  '/backend/ajax/services_crud.php': () => ({ success: true, data: attendanceServices, services: attendanceServices }),

  '/backend/api/search.php': (query) => {
    const q = String(query.get('q') || '').toLowerCase();
    const hit = members.filter((m) => `${m.first_name} ${m.last_name}`.toLowerCase().includes(q));
    return { success: true, data: hit.slice(0, 8), results: hit.slice(0, 8) };
  },

  '/backend/auth/logout.php': () => ({ success: true, message: 'Logout is a no-op in the preview.' }),
  '/backend/settings/read.php': () => ok({ church_name: 'Grace Community Church', currency: 'GHS', timezone: 'Africa/Accra' }),
  '/backend/settings/update.php': () => ({ success: true, message: 'Settings saved (preview - nothing was written).' }),
  '/backend/profile/read.php': () => ok(users[0]),
  '/backend/profile/update.php': () => ({ success: true, message: 'Profile updated (preview).' }),
  '/backend/reports/email.php': () => ({ success: true, message: 'Report emailed (preview - nothing was sent).' }),
};

/** Generic fallback so an unmapped write/read never hard-fails the preview. */
function fallback(pathname) {
  const looksLikeRead = /read|get|list|search|report|chart/i.test(pathname);
  if (looksLikeRead) return { success: true, data: [], message: 'No preview fixture for this endpoint - returning an empty list.' };
  return { success: true, message: 'Preview mode: the request was accepted but nothing was saved.' };
}

module.exports = { routes, fallback, members, fellowships, departments, cells, users };
