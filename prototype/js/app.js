/* ═══════════════════════════════════════════════════════════════
   2DMIS v2 — Prototype application  (prototype/js/app.js)

   Interactive SPA presentation layer for stakeholder demos only.
   No backend — all data is mock. Preserves the existing navigation
   model and adds a data-driven records module plus the persistent
   resident-details slide-in panel.
   ═══════════════════════════════════════════════════════════════ */

'use strict';

/* ── Tiny DOM helpers ─────────────────────────────────────────── */
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

const money = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 0 });

/* ═══════════════════════════════════════════════════════════════
   MOCK DATA
   ═══════════════════════════════════════════════════════════════ */

const PROGRAMS = [
  'AICS', 'AKAP', 'MAIP', 'TUPAD', 'CEDSSG', 'CEAP', 'CEAP_NEW', 'CEDSSG_NEW',
  'OTEA', 'OTCES', 'COFFEE GROWERS', 'PUSO TI KABABAIHAN', 'PUSO TI AGTUTUBO',
  'PUSO TI MANNALON', 'TESDA', 'GIP', 'TODA',
];

/* P6 — programs a client can hold as a scholar (single source for scholar UI) */
const SCHOLAR_PROGRAMS = ['CEDSSG', 'CEAP', 'CEDSSG_NEW', 'CEAP_NEW', 'OTEA', 'OTCES'];

/* P6 — programs offered in the Scholars list multi-select filter.
   GIP grantees are profiled in the GIP Profiles tab but also appear in the
   Scholars list (tbl_gip_info), so GIP is filterable here as well. */
const SCHOLAR_FILTER_PROGRAMS = SCHOLAR_PROGRAMS.concat(['GIP']);

/* P6 — program picker groups. Every program belongs to exactly one group
   (union covers all 17 PROGRAMS, no duplicates). */
const PROGRAM_GROUPS = [
  { label: 'Scholarship & Education', programs: ['CEDSSG', 'CEAP', 'CEDSSG_NEW', 'CEAP_NEW', 'OTEA', 'OTCES'] },
  { label: 'Medical & Financial Assistance', programs: ['AICS', 'AKAP', 'MAIP'] },
  { label: 'Livelihood & Employment', programs: ['TUPAD', 'COFFEE GROWERS', 'PUSO TI MANNALON', 'TESDA'] },
  { label: 'Community Programs', programs: ['PUSO TI KABABAIHAN', 'PUSO TI AGTUTUBO', 'TODA', 'GIP'] },
];

const CATEGORIES = [
  { label: 'Adult (30-59)', cls: 'approved' },
  { label: 'Senior (60+)',  cls: 'pending' },
  { label: 'Youth (15-29)', cls: 'approved' },
  { label: 'PWD',           cls: 'rejected' },
  { label: 'Child (0-14)',  cls: 'approved' },
];

const AVATAR_COLORS = [
  'linear-gradient(135deg, var(--navy), var(--navy-light))',
  'linear-gradient(135deg, var(--teal), var(--teal-light))',
  'linear-gradient(135deg, var(--gold), var(--gold-light))',
  'linear-gradient(135deg, var(--red), var(--red-light))',
];
const AVATAR_TEXT = ['#fff', '#fff', 'var(--navy)', '#fff'];

/* Resident seed rows: [id, last, first, middle, muni, brgy, catIdx, sex, status] */
const RESIDENT_SEEDS = [
  [2,  'DEL ROSARIO', 'Juan',        'Santos',   'Candon City',   'Allangigan Segundo', 0, 'M', 'Active'],
  [3,  'SANTOS',      'Ana',         'Mendoza',  'Candon City',   'Allangigan Segundo', 0, 'F', 'Active'],
  [10, 'REYES',       'Grace',       'Santos',   'Candon City',   'Ayudante',           1, 'F', 'Active'],
  [14, 'BAUTISTA',    'Christian',   'Santos',   'Candon City',   'Bagani Camposanto',  0, 'M', 'Active'],
  [5,  'CRUZ',        'Mark Anthony','Domingo',  'Candon City',   'Oaig-Daya',          0, 'M', 'Active'],
  [6,  'CRUZ',        'Christine',   'Marie',    'Candon City',   'Oaig-Daya',          3, 'F', 'Active'],
  [7,  'RAMOS',       'Pedro',       'Santos',   'Santa Cruz',    'Caoayan',            1, 'M', 'Active'],
  [8,  'GARCIA',      'Maria Elena', 'Villanueva','Santa Cruz',   'Cantoria',           0, 'F', 'Active'],
  [9,  'MENDOZA',     'Jose',        'Rizal',    'Santa Lucia',   'Alzate',             2, 'M', 'Active'],
  [11, 'AQUINO',      'Benigno',     'Santos',   'Santa Lucia',   'Bitalag',            1, 'M', 'Active'],
  [12, 'DIAZ',        'Rosa',        'Dela Cruz','Santa Maria',   'Danuman',            3, 'F', 'Active'],
  [13, 'NAVARRO',     'Carmela',     'Quijano',  'Santa Maria',   'Paypayad',           0, 'F', 'Active'],
  [15, 'FERRER',      'Ramon',       'Domingo',  'Santiago',      'Canaoay',            0, 'M', 'Active'],
  [16, 'PASCUAL',     'Liza Marie',  'Ramos',    'Santiago',      'Sabangan-Asan',      2, 'F', 'Active'],
  [17, 'VILLANUEVA',  'Eduardo',     'Garcia',   'Tagudin',       'Puor',               1, 'M', 'Active'],
  [18, 'CASTRO',      'Fatima Luz',  'Mendoza',  'Tagudin',       'Magsaysay',          0, 'F', 'Active'],
  [19, 'LIM',         'Kevin',       'Santos',   'Suyo',          'Bicmica',            2, 'M', 'Active'],
  [20, 'OCAMPO',      'Daniel',      'Ramos',    'Sigay',         'Abaccan',            0, 'M', 'Active'],
  [21, 'SORIANO',     'Imelda',      'Cruz',     'Galimuyod',     'Bag-ayon',           0, 'F', 'Active'],
  [22, 'BALTAZAR',    'Henry',       'Villanueva','Galimuyod',    'Namatutan',          3, 'M', 'Active'],
  [23, 'TORRES',      'Juliet',      'Garcia',   'Salcedo',       'Lubong',             1, 'F', 'Active'],
  [24, 'ZAMORA',      'Andres',      'Domingo',  'Banayoyo',      'Lanao',              0, 'M', 'Archived'],
  [25, 'ALONZO',      'Nena',        'Santos',   'Lidlidda',      'Baiinden',           0, 'F', 'Active'],
  [26, 'QUINTO',      'Rolando',     'Reyes',    'Narvacan',      'Nagupacan',          0, 'M', 'Active'],
  [27, 'VELASCO',     'Corazon',     'Cruz',     'Burgos',        'Rimus',              1, 'F', 'Active'],
];

const OCCUPATIONS = ['Farmer', 'Housewife', 'Fisherfolk', 'Laborer', 'Driver', 'Vendor', 'Teacher', 'Retired', 'Student', 'Self-employed'];
const CIVIL = ['Married', 'Single', 'Widowed', 'Separated'];
const ADDRESS_PREFIX = ['Purok 1', 'Purok 2', 'Purok 3', 'Sitio Bato', 'Sitio Kalaw', 'Purok 4'];

function birthdateFor(id, catIdx) {
  // deterministic pseudo-dates by category
  const year = catIdx === 1 ? 1945 + (id % 12)
    : catIdx === 2 ? 1998 + (id % 12)
    : catIdx === 3 ? 2003 + (id % 8)
    : catIdx === 4 ? 2012 + (id % 8)
    : 1968 + (id % 27);
  const month = ((id * 3) % 12) + 1;
  const day = ((id * 7) % 27) + 1;
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/* P6 — deterministic default program tags shown on the resident profile */
function defaultPrograms(id) {
  const pool = PROGRAMS.slice();
  const picks = [];
  const count = 1 + (id % 3);
  for (let k = 0; k < count; k++) {
    const p = pool[(id * 7 + k * 13) % pool.length];
    if (!picks.includes(p)) picks.push(p);
  }
  return picks;
}

function govIdsFor(r) {
  const base = [
    { label: 'PhilSys (National ID)', value: '3499-' + String(1000 + r.id) + '-' + String(2000 + r.id) + '-0001' },
    { label: 'PhilHealth No.', value: '11-' + String(2200000000 + r.id * 97) },
    { label: 'SSS No.', value: '34-' + String(400000000 + r.id * 7) + '-5' },
  ];
  if (r.category.label === 'Senior (60+)') base.push({ label: 'Senior Citizen ID', value: 'SC-' + String(10000 + r.id * 13) });
  if (r.category.label === 'PWD') base.push({ label: 'PWD ID', value: 'PWD-' + String(5000 + r.id * 11) });
  return base;
}

function docsFor(r) {
  const docs = [
    { name: 'PhilHealth MDR', meta: 'PDF · 1.2 MB' },
    { name: 'Barangay Clearance', meta: 'PDF · 480 KB' },
    { name: 'Medical Abstract', meta: 'PDF · 860 KB' },
  ];
  if (r.category.label === 'Senior (60+)') docs.push({ name: 'Senior Citizen ID scan', meta: 'JPG · 320 KB' });
  if (r.category.label === 'PWD') docs.push({ name: 'PWD ID scan', meta: 'JPG · 290 KB' });
  return docs;
}

function timelineFor(r) {
  const tl = [
    { title: 'Client record created', text: 'Record registered by Jordi Admin', time: '2025-01-08 · 09:12' },
    { title: 'Profile updated', text: 'Contact details refreshed by Jordi Admin', time: '2026-05-21 · 14:03' },
  ];
  const tx = TRANSACTIONS.filter(t => t.clientId === r.id).slice(0, 2);
  tx.forEach(t => tl.push({
    title: `${t.program} — ${t.statusLabel}`,
    text: `${money(t.amount)} · ${t.type}`,
    time: `${t.date} · 16:40`,
  }));
  tl.push({ title: 'Profile viewed', text: 'Accessed from Client Registry', time: '2026-08-06 · 08:51' });
  return tl;
}

function makeResident(seed, i) {
  const [id, last, first, middle, municipality, barangay, catIdx, sex, status] = seed;
  const category = CATEGORIES[catIdx];
  const r = {
    id,
    last, first, middle,
    fullName: first + ' ' + last,
    formalName: `${last}, ${first}${middle ? ' ' + middle : ''}`,
    initials: (first[0] || '') + (last[0] || ''),
    avatar: AVATAR_COLORS[i % AVATAR_COLORS.length],
    avatarText: AVATAR_TEXT[i % AVATAR_TEXT.length],
    category,
    municipality, barangay,
    sex,
    status,
    birthdate: birthdateFor(id, catIdx),
    civilStatus: CIVIL[id % CIVIL.length],
    occupation: OCCUPATIONS[id % OCCUPATIONS.length],
    mobile: `0917-***-${String(1000 + ((id * 13) % 9000))}`,
    email: `${first.toLowerCase().replace(/\s+/g, '.')}.${last.toLowerCase()}@gmail.com`,
    address: `${ADDRESS_PREFIX[id % ADDRESS_PREFIX.length]}, Brgy. ${barangay}, ${municipality}`,
    household: 'HH-2026-' + String(1 + (i % 6)).padStart(3, '0'),
    programs: defaultPrograms(id),
    notes: 'Registered under the municipal assistance program. Priority category: ' + category.label.toLowerCase() + '. Existing assistance history on record with no flagged duplicates.',
    govIds: govIdsFor({ id, category }),
    docs: docsFor({ id, category }),
    audit: {
      createdBy: 'Jordi Admin',
      createdAt: '2025-01-08 09:12:41',
      updatedBy: 'Jordi Admin',
      updatedAt: '2026-05-21 14:03:18',
      lastView: '2026-08-06 08:51:02',
    },
  };
  return r;
}

const RESIDENTS = RESIDENT_SEEDS.map(makeResident);
const RESIDENT_BY_ID = new Map(RESIDENTS.map(r => [r.id, r]));

/* Transactions — mirrors the real v1 programs/types/statuses */
const TX_SEEDS = [
  { no: '#001', clientId: 2,  program: 'AICS', type: 'Hospitalization',    date: '2026-07-26', amount: 5000, status: 'paid' },
  { no: '#002', clientId: 2,  program: 'TUPAD', type: 'Emergency Employment', date: '2026-07-06', amount: 4350, status: 'approved' },
  { no: '#003', clientId: 10, program: 'CEAP', type: 'Cash Assistance',    date: '2026-07-18', amount: 3000, status: 'paid' },
  { no: '#004', clientId: 5,  program: 'GIP', type: 'Cash Assistance',     date: '2026-06-15', amount: 8000, status: 'paid' },
  { no: '#005', clientId: 6,  program: 'MAIP', type: 'Cash Assistance',    date: '2026-07-22', amount: 2500, status: 'pending' },
  { no: '#006', clientId: 7,  program: 'TODA', type: 'Cash Assistance',    date: '2026-07-10', amount: 1500, status: 'rejected' },
  { no: '#007', clientId: 3,  program: 'AICS', type: 'Medical',            date: '2026-07-19', amount: 5000, status: 'approved' },
  { no: '#008', clientId: 9,  program: 'OTEA', type: 'Scholarship',        date: '2026-07-15', amount: 7000, status: 'approved' },
  { no: '#009', clientId: 11, program: 'OTCES', type: 'Medical',           date: '2026-07-12', amount: 6000, status: 'pending' },
  { no: '#010', clientId: 12, program: 'CEDSSG', type: 'Cash Relief Assistance', date: '2026-07-08', amount: 4000, status: 'paid' },
  { no: '#011', clientId: 13, program: 'PUSO TI KABABAIHAN', type: 'Membership', date: '2026-07-05', amount: 2000, status: 'approved' },
  { no: '#012', clientId: 15, program: 'AKAP', type: 'Cash Assistance',    date: '2026-07-02', amount: 3500, status: 'paid' },
  { no: '#013', clientId: 16, program: 'PUSO TI AGTUTUBO', type: 'Scholarship', date: '2026-06-28', amount: 5500, status: 'pending' },
  { no: '#014', clientId: 17, program: 'TESDA', type: 'Skills Training',   date: '2026-06-24', amount: 4500, status: 'approved' },
  { no: '#015', clientId: 18, program: 'PUSO TI MANNALON', type: 'Cash For Work', date: '2026-06-20', amount: 4200, status: 'paid' },
  { no: '#016', clientId: 19, program: 'CEAP_NEW', type: 'Scholarship',    date: '2026-06-17', amount: 6000, status: 'approved' },
  { no: '#017', clientId: 20, program: 'CEDSSG_NEW', type: 'Cash Assistance', date: '2026-06-12', amount: 3800, status: 'pending' },
  { no: '#018', clientId: 21, program: 'COFFEE GROWERS', type: 'Cash Assistance', date: '2026-06-08', amount: 5000, status: 'paid' },
  { no: '#019', clientId: 22, program: 'AICS', type: 'Burial',             date: '2026-06-03', amount: 8000, status: 'approved' },
  { no: '#020', clientId: 23, program: 'GIP', type: 'CRA',                 date: '2026-05-29', amount: 7500, status: 'paid' },
];

const TX_STATUS_META = {
  paid:     { label: 'Paid',     cls: 'paid' },
  approved: { label: 'Approved', cls: 'approved' },
  pending:  { label: 'Pending',  cls: 'pending' },
  rejected: { label: 'Rejected', cls: 'rejected' },
};

const TRANSACTIONS = TX_SEEDS.map(t => {
  const r = RESIDENT_BY_ID.get(t.clientId);
  const meta = TX_STATUS_META[t.status];
  return Object.assign({}, t, {
    clientName: r ? r.fullName : '—',
    statusLabel: meta.label,
    statusCls: meta.cls,
  });
});

// Timelines reference transactions, so they are built after both datasets exist.
RESIDENTS.forEach(r => { r.timeline = timelineFor(r); });

const HOUSEHOLDS = [
  { code: 'HH-2026-001', head: 'Juan Del Rosario',   headId: 2,  members: 4, barangay: 'Allangigan Segundo', muni: 'Candon City' },
  { code: 'HH-2026-002', head: 'Ana Santos',         headId: 3,  members: 4, barangay: 'Allangigan Segundo', muni: 'Candon City' },
  { code: 'HH-2026-003', head: 'Grace Reyes',        headId: 10, members: 4, barangay: 'Ayudante',            muni: 'Candon City' },
  { code: 'HH-2026-004', head: 'Mark Anthony Cruz',  headId: 5,  members: 4, barangay: 'Oaig-Daya',           muni: 'Candon City' },
  { code: 'HH-2026-005', head: 'Pedro Santos',       headId: 7,  members: 3, barangay: 'Caoayan',             muni: 'Santa Cruz' },
  { code: 'HH-2026-006', head: 'Christian Bautista', headId: 14, members: 4, barangay: 'Bagani Camposanto',   muni: 'Candon City' },
];

/* ═══════════════════════════════════════════════════════════════
   P6 — SCHOLARS (tbl_scholar_info) · GIP (tbl_gip_info) · exams
   (tbl_exam / tbl_results) · update logs (tbl_update_logs)
   Mock rows mirror the v2 P6 data model. Client details are
   derived from the resident records above.
   ═══════════════════════════════════════════════════════════════ */

/* GIP (Government Internship Program) profiles — tbl_gip_info */
const GIP_PROFILES = [
  {
    clientId: 5,
    validGovtId: 'PhilSys (National ID)', idNumber: '3499-1005-2005-0001',
    insuranceBeneficiary: 'Maricris Cruz', emergencyContact: 'Maricris Cruz',
    ecpContactNumber: '0917-112-2334', ecpAddress: 'Oaig-Daya, Candon City',
    college: 'Ilocos Sur Polytechnic State College', course: 'BS in Public Administration',
    yearGraduated: '2025', highSchool: 'Candon National High School',
    elementarySchool: 'Oaig-Daya Elementary School', latestWorkExperience: 'Barangay Secretariat',
    position: 'Administrative Aide', periodOfEngagement: '3 months', specialSkills: 'Microsoft Office, Encoding',
    achievements: 'Dean\'s Lister (2024)',
  },
  {
    clientId: 23,
    validGovtId: 'PhilSys (National ID)', idNumber: '3499-1023-2023-0001',
    insuranceBeneficiary: 'Ramon Torres', emergencyContact: 'Ramon Torres',
    ecpContactNumber: '0918-334-4556', ecpAddress: 'Lubong, Salcedo',
    college: 'University of Northern Philippines', course: 'BS in Social Work',
    yearGraduated: '2024', highSchool: 'Salcedo Vocational High School',
    elementarySchool: 'Lubong Elementary School', latestWorkExperience: 'Volunteer youth worker',
    position: 'Program Aide', periodOfEngagement: '6 months', specialSkills: 'Case management, reporting',
    achievements: 'Top 10 (2024)',
  },
  {
    clientId: 20,
    validGovtId: 'PhilSys (National ID)', idNumber: '3499-1020-2020-0001',
    insuranceBeneficiary: 'Lucia Ocampo', emergencyContact: 'Lucia Ocampo',
    ecpContactNumber: '0919-556-6778', ecpAddress: 'Abaccan, Sigay',
    college: 'Ilocos Sur Polytechnic State College', course: 'BS in Information Technology',
    yearGraduated: '2025', highSchool: 'Sigay National High School',
    elementarySchool: 'Abaccan Elementary School', latestWorkExperience: 'Computer shop assistant',
    position: 'IT Support Aide', periodOfEngagement: '3 months', specialSkills: 'Hardware, networking',
    achievements: 'CISCO certified (2025)',
  },
  {
    clientId: 17,
    validGovtId: 'PhilSys (National ID)', idNumber: '3499-1017-2017-0001',
    insuranceBeneficiary: 'Amelia Villanueva', emergencyContact: 'Amelia Villanueva',
    ecpContactNumber: '0917-778-8990', ecpAddress: 'Puor, Tagudin',
    college: 'University of Northern Philippines', course: 'BS in Development Communication',
    yearGraduated: '2023', highSchool: 'Tagudin National High School',
    elementarySchool: 'Puor Elementary School', latestWorkExperience: 'Municipal info office intern',
    position: 'Documentation Aide', periodOfEngagement: '6 months', specialSkills: 'Writing, photography',
    achievements: 'Campus journalist awardee',
  },
];

const SCHOLAR_SEEDS = [
  {
    id: 'SC-2026-001', clientId: 2, program: 'CEDSSG',
    school: 'University of Northern Philippines', schoolType: 'University', campus: 'Vigan',
    collegeDept: 'College of Arts and Sciences', course: 'BS in Social Work', yearLevel: '3rd Year',
    isRegular: true, yearStarted: 2024, landbankNo: '1234-5678-9001', status: 'Active',
    exam: { date: '2026-03-15', venue: 'MSWDO Candon', result: 'Passed', score: 88 },
  },
  {
    id: 'SC-2026-002', clientId: 3, program: 'CEAP',
    school: 'St. Joseph Provincial College', schoolType: 'College', campus: 'Main',
    collegeDept: 'College of Education', course: 'BSEd in English', yearLevel: '2nd Year',
    isRegular: true, yearStarted: 2025, landbankNo: '2345-6789-0123', status: 'Active',
    exam: { date: '2026-04-02', venue: 'MPSO Santa Cruz', result: 'Passed', score: 82 },
  },
  {
    id: 'SC-2026-003', clientId: 10, program: 'CEDSSG_NEW',
    school: 'University of Northern Philippines', schoolType: 'University', campus: 'Vigan',
    collegeDept: 'College of Engineering', course: 'BS in Civil Engineering', yearLevel: '1st Year',
    isRegular: true, yearStarted: 2026, landbankNo: '3456-7890-1234', status: 'Active',
    exam: { date: '2026-05-20', venue: 'MSWDO Candon', result: 'Pending', score: null },
  },
  {
    id: 'SC-2026-004', clientId: 9, program: 'OTEA',
    school: 'Ilocos Sur Polytechnic State College', schoolType: 'College', campus: 'Tagudin',
    collegeDept: 'College of Nursing', course: 'BS in Nursing', yearLevel: '4th Year',
    isRegular: true, yearStarted: 2023, landbankNo: '4567-8901-2345', status: 'Active',
    exam: { date: '2026-03-28', venue: 'MSWDO Santa Lucia', result: 'Passed', score: 91 },
  },
  {
    id: 'SC-2026-005', clientId: 11, program: 'OTCES',
    school: 'Saint Mary College', schoolType: 'College', campus: 'Main',
    collegeDept: 'College of Business', course: 'BS in Accountancy', yearLevel: '2nd Year',
    isRegular: true, yearStarted: 2025, landbankNo: '5678-9012-3456', status: 'Active',
    exam: { date: '2026-04-18', venue: 'MSWDO Santa Lucia', result: 'Failed', score: 58 },
  },
  {
    id: 'SC-2026-006', clientId: 13, program: 'CEAP_NEW',
    school: 'University of Northern Philippines', schoolType: 'University', campus: 'Vigan',
    collegeDept: 'College of Agriculture', course: 'BS in Agribusiness', yearLevel: '1st Year',
    isRegular: false, yearStarted: 2026, landbankNo: '6789-0123-4567', status: 'Active',
    exam: { date: '2026-06-05', venue: 'MSWDO Santa Maria', result: 'Passed', score: 79 },
  },
  {
    id: 'SC-2026-007', clientId: 16, program: 'CEDSSG',
    school: 'Ilocos Sur Polytechnic State College', schoolType: 'College', campus: 'Santiago',
    collegeDept: 'College of Teacher Education', course: 'BEEd in Elementary Education', yearLevel: '4th Year',
    isRegular: true, yearStarted: 2023, landbankNo: '7890-1234-5678', status: 'Active',
    exam: { date: '2026-02-22', venue: 'MSWDO Santiago', result: 'Passed', score: 85 },
  },
  {
    id: 'SC-2026-008', clientId: 19, program: 'OTEA',
    school: 'University of Northern Philippines', schoolType: 'University', campus: 'Vigan',
    collegeDept: 'College of Education', course: 'BSED in Filipino', yearLevel: 'Graduated',
    isRegular: true, yearStarted: 2022, landbankNo: '8901-2345-6789', status: 'Graduated',
    exam: { date: '2026-01-12', venue: 'MSWDO Suyo', result: 'Passed', score: 90 },
  },
  /* GIP grantees (tbl_gip_info) appear in the Scholars list as program GIP so the
     multi-select OR filter can be demonstrated alongside scholarship programs. */
  {
    id: 'SC-2026-009', clientId: 5, program: 'GIP',
    school: 'Ilocos Sur Polytechnic State College', schoolType: 'College', campus: 'Main',
    collegeDept: '—', course: 'BS in Public Administration', yearLevel: 'Engaged',
    isRegular: true, yearStarted: 2025, landbankNo: '—', status: 'Active',
    exam: null,
  },
  {
    id: 'SC-2026-010', clientId: 23, program: 'GIP',
    school: 'University of Northern Philippines', schoolType: 'University', campus: 'Vigan',
    collegeDept: '—', course: 'BS in Social Work', yearLevel: 'Engaged',
    isRegular: true, yearStarted: 2024, landbankNo: '—', status: 'Active',
    exam: null,
  },
  {
    id: 'SC-2026-011', clientId: 20, program: 'GIP',
    school: 'Ilocos Sur Polytechnic State College', schoolType: 'College', campus: 'Main',
    collegeDept: '—', course: 'BS in Information Technology', yearLevel: 'Engaged',
    isRegular: true, yearStarted: 2025, landbankNo: '—', status: 'Active',
    exam: null,
  },
  {
    id: 'SC-2026-012', clientId: 17, program: 'GIP',
    school: 'University of Northern Philippines', schoolType: 'University', campus: 'Vigan',
    collegeDept: '—', course: 'BS in Development Communication', yearLevel: 'Engaged',
    isRegular: true, yearStarted: 2023, landbankNo: '—', status: 'Active',
    exam: null,
  },
];

function buildScholar(seed) {
  const r = RESIDENT_BY_ID.get(seed.clientId);
  const s = Object.assign({}, seed, {
    fullName: r ? r.fullName : '—',
    formalName: r ? r.formalName : '—',
    initials: r ? r.initials : '—',
    avatar: r ? r.avatar : AVATAR_COLORS[0],
    avatarText: r ? r.avatarText : AVATAR_TEXT[0],
    barangay: r ? r.barangay : '—',
    municipality: r ? r.municipality : '—',
    mobile: r ? r.mobile : '—',
  });
  s.gip = GIP_PROFILES.find(g => g.clientId === s.clientId) || null;
  return s;
}

const SCHOLARS = SCHOLAR_SEEDS.map(buildScholar);
const SCHOLAR_BY_ID = new Map(SCHOLARS.map(s => [s.id, s]));

/* Scholarship reports — monthly aggregated preview (mirrors v1 report export) */
const SCHOLAR_REPORTS = [
  { month: 'June 2026', program: 'CEDSSG',   new: 3, active: 12, graduated: 1, disbursed: 36000 },
  { month: 'June 2026', program: 'CEAP',     new: 2, active: 9,  graduated: 0, disbursed: 30000 },
  { month: 'June 2026', program: 'OTEA',     new: 1, active: 7,  graduated: 1, disbursed: 18000 },
  { month: 'June 2026', program: 'OTCES',    new: 1, active: 5,  graduated: 0, disbursed: 15000 },
  { month: 'July 2026', program: 'CEDSSG',   new: 4, active: 14, graduated: 1, disbursed: 44000 },
  { month: 'July 2026', program: 'CEAP',     new: 3, active: 11, graduated: 0, disbursed: 39000 },
  { month: 'July 2026', program: 'CEAP_NEW', new: 2, active: 6,  graduated: 0, disbursed: 24000 },
  { month: 'July 2026', program: 'CEDSSG_NEW', new: 2, active: 5, graduated: 0, disbursed: 21000 },
  { month: 'July 2026', program: 'OTEA',     new: 2, active: 8,  graduated: 1, disbursed: 22000 },
  { month: 'July 2026', program: 'OTCES',    new: 1, active: 6,  graduated: 0, disbursed: 17000 },
];

/* Client-initiated profile updates — tbl_update_logs */
const UPDATE_LOGS = [
  { id: 1, clientId: 2,  fullName: 'Juan Del Rosario',  action: 'Updated mobile number and Landbank account', ipAddress: '192.168.1.24', createdAt: '2026-08-04 09:12' },
  { id: 2, clientId: 10, fullName: 'Grace Reyes',       action: 'Changed year level to 1st Year',              ipAddress: '192.168.1.57', createdAt: '2026-08-03 14:02' },
  { id: 3, clientId: 9,  fullName: 'Jose Mendoza',      action: 'Updated emergency contact',                   ipAddress: '192.168.1.88', createdAt: '2026-08-01 11:45' },
  { id: 4, clientId: 16, fullName: 'Liza Marie Pascual', action: 'Requested status verification',              ipAddress: '192.168.1.12', createdAt: '2026-07-29 08:30' },
  { id: 5, clientId: 3,  fullName: 'Ana Santos',        action: 'Corrected school spelling',                   ipAddress: '192.168.1.41', createdAt: '2026-07-27 16:18' },
  { id: 6, clientId: 11, fullName: 'Benigno Aquino',    action: 'Added Landbank account',                      ipAddress: '192.168.1.93', createdAt: '2026-07-25 10:05' },
];

const NOTIFICATIONS = [
  { icon: 'gold', title: 'Pending approval', text: '5 transactions awaiting approval', time: '12 min ago', unread: true },
  { icon: 'red',  title: 'Duplicate detected', text: 'Possible duplicate for Ana Santos', time: '1 hr ago', unread: true },
  { icon: 'teal', title: 'Payout batch ready', text: 'AICS batch #12 ready for payout', time: '3 hrs ago', unread: true },
  { icon: 'navy', title: 'System maintenance', text: 'Scheduled maintenance on Sunday 02:00', time: 'Yesterday', unread: false },
];

const ACTIVITY = [
  { initials: 'JD', color: AVATAR_COLORS[0], text: '<strong>Juan Del Rosario</strong> marked AICS payout as <strong>Paid</strong>', time: '8 min ago' },
  { initials: 'AS', color: AVATAR_COLORS[1], text: '<strong>Ana Santos</strong> updated mobile number', time: '42 min ago' },
  { initials: 'GR', color: AVATAR_COLORS[2], text: '<strong>Grace Reyes</strong> registered a new household', time: '2 hrs ago' },
  { initials: 'JA', color: AVATAR_COLORS[0], text: '<strong>Jordi Admin</strong> approved 3 OTEA applications', time: '4 hrs ago' },
  { initials: 'MC', color: AVATAR_COLORS[3], text: '<strong>Mark Anthony Cruz</strong> uploaded new photo', time: 'Yesterday' },
];

/* ═══════════════════════════════════════════════════════════════
   APP STATE
   ═══════════════════════════════════════════════════════════════ */

const state = {
  page: 'dashboard',
  clients: {
    search: '',
    filters: { program: [], category: [], sex: [], civilStatus: [], status: [] },
    sortKey: null,      // name | municipality | barangay
    sortDir: 'asc',
    page: 1,
    perPage: 8,
  },
  transactions: {
    search: '',
    filters: { program: [], type: [], status: [] },
    sortKey: null,      // date | amount | name
    sortDir: 'asc',
    page: 1,
    perPage: 8,
  },
  households: { search: '', filters: { muni: [] }, page: 1, perPage: 6 },
  scholars: {
    search: '',
    filters: { program: [], status: [] },
    page: 1,
    perPage: 8,
  },
  scholarTab: 'scholars',
  openResidentId: null,
  openScholarId: null,
  panelMode: 'resident',  // 'resident' | 'scholar'
  lastFocusedRow: null,
  calendar: { month: 7, year: 2026 }, // 0-indexed month (July = August 2026 view default handled below)
};

const PAGE_NAMES = {
  dashboard: 'Dashboard', clients: 'Client Registry', households: 'Households',
  scholars: 'Scholars', transactions: 'All Transactions', scanner: 'Scanner Engine', payouts: 'Payouts',
  users: 'Access Control', audit: 'Audit Logs',
};

/* ═══════════════════════════════════════════════════════════════
   UTILITIES
   ═══════════════════════════════════════════════════════════════ */

function pad(n) { return String(n).padStart(2, '0'); }

function compare(a, b, dir) {
  if (typeof a === 'number' && typeof b === 'number') return dir === 'asc' ? a - b : b - a;
  const sa = String(a ?? '').toLowerCase();
  const sb = String(b ?? '').toLowerCase();
  const cmp = sa < sb ? -1 : sa > sb ? 1 : 0;
  return dir === 'asc' ? cmp : -cmp;
}

/* Pagination: returns items for the current page + meta */
function pageSlice(items, page, perPage) {
  const totalPages = Math.max(1, Math.ceil(items.length / perPage));
  const p = Math.min(Math.max(1, page), totalPages);
  return { items: items.slice((p - 1) * perPage, p * perPage), page: p, totalPages, total: items.length };
}

function renderPagination(containerSel, meta, key, onPage) {
  const wrap = $(containerSel);
  if (!wrap) return;
  const buttons = [];
  const go = (p) => onPage(Math.min(Math.max(1, p), meta.totalPages));
  buttons.push(`<button type="button" data-pager="${key}" data-page="prev" aria-label="Previous page" ${meta.page === 1 ? 'disabled' : ''}>&laquo;</button>`);
  for (let i = 1; i <= meta.totalPages; i++) {
    buttons.push(`<button type="button" data-pager="${key}" data-page="${i}" class="${i === meta.page ? 'active' : ''}" aria-label="Page ${i}" aria-current="${i === meta.page ? 'page' : 'false'}">${i}</button>`);
  }
  buttons.push(`<button type="button" data-pager="${key}" data-page="next" aria-label="Next page" ${meta.page === meta.totalPages ? 'disabled' : ''}>&raquo;</button>`);
  wrap.innerHTML = buttons.join('');
}

/* ═══════════════════════════════════════════════════════════════
   REUSABLE MULTI-SELECT FILTER SYSTEM
   Categorical filters across modules share one engine:
   - OR logic within a category (any selected value matches)
   - AND logic across categories
   - text search composes with filters (search AND filters)
   - searchable checkbox popovers + active filter chips + Clear all
   ═══════════════════════════════════════════════════════════════ */

/* Per-module filter categories. `options` returns the choice list for the
   menu, `get` extracts the value(s) present on a record. */
const FILTER_SPECS = {
  clients: [
    { key: 'program',     label: 'Program',      searchable: true, options: () => PROGRAMS, get: r => r.programs },
    { key: 'category',    label: 'Category',     options: () => CATEGORIES.map(c => c.label), get: r => [r.category.label] },
    { key: 'sex',         label: 'Sex',          options: () => ['M', 'F'], get: r => [r.sex] },
    { key: 'civilStatus', label: 'Civil Status', options: () => CIVIL, get: r => [r.civilStatus] },
    { key: 'status',      label: 'Status',       options: () => ['Active', 'Archived'], get: r => [r.status] },
  ],
  transactions: [
    { key: 'program', label: 'Program', searchable: true, options: () => [...new Set(TRANSACTIONS.map(t => t.program))], get: t => [t.program] },
    { key: 'type',    label: 'Type',    searchable: true, options: () => [...new Set(TRANSACTIONS.map(t => t.type))],    get: t => [t.type] },
    { key: 'status',  label: 'Status',  options: () => Object.keys(TX_STATUS_META).map(k => TX_STATUS_META[k].label), get: t => [t.statusLabel] },
  ],
  households: [
    { key: 'muni', label: 'Municipality', options: () => [...new Set(HOUSEHOLDS.map(h => h.muni))], get: h => [h.muni] },
  ],
  scholars: [
    { key: 'program', label: 'Program', searchable: true, options: () => SCHOLAR_FILTER_PROGRAMS, get: s => [s.program] },
    { key: 'status',  label: 'Status',  options: () => ['Active', 'Graduated', 'Inactive'], get: s => [s.status] },
  ],
};

/* Per-menu search query registry: `${module}|${catKey}` → string */
const filterQuery = {};

function filterMatches(record, module) {
  const spec = FILTER_SPECS[module];
  const f = state[module].filters;
  return spec.every(cat => {
    const sel = f[cat.key] || [];
    if (!sel.length) return true;
    const vals = cat.get(record);
    const arr = Array.isArray(vals) ? vals : [vals];
    return sel.some(v => arr.includes(v));
  });
}

function activeFilterCount(module) {
  return Object.keys(state[module].filters).reduce((n, k) => n + (state[module].filters[k] || []).length, 0);
}

function renderModule(module) {
  if (module === 'clients') renderClients();
  else if (module === 'transactions') renderTransactions();
  else if (module === 'households') renderHouseholds();
  else if (module === 'scholars') renderScholars();
}

function renderFilterOptions(module, cat) {
  const wrap = $(`[data-filter-host="${module}"] .filter-multi[data-filter-cat="${cat}"]`);
  if (!wrap) return;
  const el = wrap.querySelector('.filter-multi-options');
  if (!el) return;
  const spec = FILTER_SPECS[module].find(c => c.key === cat);
  const q = (filterQuery[`${module}|${cat}`] || '').trim().toLowerCase();
  const sel = state[module].filters[cat] || [];
  const opts = spec.options().filter(o => !q || String(o).toLowerCase().includes(q));
  el.innerHTML = opts.length
    ? opts.map(o => `
      <label class="filter-check">
        <input type="checkbox" value="${esc(o)}" ${sel.includes(o) ? 'checked' : ''}>
        <span>${esc(o)}</span>
      </label>`).join('')
    : `<span class="filter-no-results">No options match "${esc(filterQuery[`${module}|${cat}`] || '')}".</span>`;
}

function renderFilterBadges(module) {
  $$(`[data-filter-host="${module}"] .filter-multi`).forEach(wrap => {
    const cat = wrap.dataset.filterCat;
    const count = (state[module].filters[cat] || []).length;
    const badge = wrap.querySelector('.filter-count');
    const btn = wrap.querySelector('[data-filter-toggle]');
    if (badge) { badge.textContent = count; badge.hidden = count === 0; }
    if (btn) btn.classList.toggle('has-filters', count > 0);
  });
}

function renderFilterChips(module) {
  const host = $(`[data-filter-host="${module}"]`);
  if (!host) return;
  const chips = host.querySelector('.filter-active-chips');
  const clearAll = host.querySelector('[data-filter-clear-all]');
  if (!chips || !clearAll) return;
  const parts = [];
  FILTER_SPECS[module].forEach(cat => {
    (state[module].filters[cat.key] || []).forEach(v => parts.push({ cat: cat.key, label: `${cat.label}: ${v}`, value: v }));
  });
  chips.innerHTML = parts.map(p =>
    `<span class="filter-chip-select">${esc(p.label)}<button type="button" class="filter-chip-x" data-filter-remove="${esc(p.cat)}|${esc(p.value)}" aria-label="Remove ${esc(p.label)} filter">&times;</button></span>`).join('');
  chips.hidden = parts.length === 0;
  clearAll.hidden = parts.length === 0;
}

function renderFilterUI(module) {
  renderFilterBadges(module);
  renderFilterChips(module);
}

function closeAllFilterMenus() {
  $$('.filter-multi-menu.open').forEach(menu => {
    menu.classList.remove('open');
    const btn = menu.closest('.filter-multi').querySelector('[data-filter-toggle]');
    if (btn) { btn.classList.remove('active'); btn.setAttribute('aria-expanded', 'false'); }
  });
}

function toggleFilterMenu(wrap) {
  const menu = wrap.querySelector('.filter-multi-menu');
  const btn = wrap.querySelector('[data-filter-toggle]');
  const isOpen = menu.classList.contains('open');
  closeAllFilterMenus();
  if (!isOpen) {
    menu.classList.add('open');
    btn.classList.add('active');
    btn.setAttribute('aria-expanded', 'true');
    const search = wrap.querySelector('.filter-multi-search');
    if (search) { search.value = filterQuery[`${wrap.dataset.filterModule}|${wrap.dataset.filterCat}`] || ''; search.focus(); }
    renderFilterOptions(wrap.dataset.filterModule, wrap.dataset.filterCat);
  }
}

/* Build the filter button row + chips + clear-all for one module host */
function initFilterGroup(module) {
  const host = $(`[data-filter-host="${module}"]`);
  if (!host || host.dataset.filterReady) return;
  host.dataset.filterReady = '1';

  const btnRow = document.createElement('div');
  btnRow.className = 'filter-btns';
  FILTER_SPECS[module].forEach(cat => {
    const wrap = document.createElement('div');
    wrap.className = 'filter-multi';
    wrap.dataset.filterModule = module;
    wrap.dataset.filterCat = cat.key;
    wrap.innerHTML = `
      <button type="button" class="filter-multi-btn" data-filter-toggle aria-haspopup="true" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="22 3 2 3 10 12.5 10 19 14 21 14 12.5 22 3"/></svg>
        <span>${esc(cat.label)}</span>
        <span class="filter-count" hidden>0</span>
      </button>
      <div class="filter-multi-menu" role="listbox" aria-label="Filter by ${esc(cat.label)}">
        ${cat.searchable ? `<input type="search" class="filter-multi-search" placeholder="Search ${esc(cat.label.toLowerCase())}…" aria-label="Search ${esc(cat.label)} options">` : ''}
        <div class="filter-multi-options"></div>
        <div class="filter-multi-footer">
          <button type="button" class="btn btn-outline btn-xs" data-filter-clear-cat>Clear</button>
          <span class="filter-multi-hint">Select multiple to show any of them</span>
        </div>
      </div>`;
    btnRow.appendChild(wrap);
  });
  const chips = document.createElement('div');
  chips.className = 'filter-active-chips';
  chips.setAttribute('aria-live', 'polite');
  chips.hidden = true;
  const clearAll = document.createElement('button');
  clearAll.type = 'button';
  clearAll.className = 'filter-clear';
  clearAll.dataset.filterClearAll = '';
  clearAll.hidden = true;
  clearAll.textContent = 'Clear filters';
  host.appendChild(btnRow);
  host.appendChild(chips);
  host.appendChild(clearAll);
  renderFilterUI(module);
}

/* Delegated filter events (single set for every module) */
document.addEventListener('click', (e) => {
  const toggle = e.target.closest('[data-filter-toggle]');
  if (toggle) { toggleFilterMenu(toggle.closest('.filter-multi')); return; }

  const removeBtn = e.target.closest('[data-filter-remove]');
  if (removeBtn) {
    const [cat, value] = removeBtn.dataset.filterRemove.split('|');
    const module = removeBtn.closest('[data-filter-host]').dataset.filterHost;
    state[module].filters[cat] = (state[module].filters[cat] || []).filter(v => v !== value);
    state[module].page = 1;
    renderModule(module);
    renderFilterUI(module);
    return;
  }

  const clearCat = e.target.closest('[data-filter-clear-cat]');
  if (clearCat) {
    const wrap = clearCat.closest('.filter-multi');
    state[wrap.dataset.filterModule].filters[wrap.dataset.filterCat] = [];
    state[wrap.dataset.filterModule].page = 1;
    renderModule(wrap.dataset.filterModule);
    renderFilterUI(wrap.dataset.filterModule);
    renderFilterOptions(wrap.dataset.filterModule, wrap.dataset.filterCat);
    return;
  }

  const clearAll = e.target.closest('[data-filter-clear-all]');
  if (clearAll) {
    const module = clearAll.closest('[data-filter-host]').dataset.filterHost;
    Object.keys(state[module].filters).forEach(k => state[module].filters[k] = []);
    state[module].page = 1;
    renderModule(module);
    renderFilterUI(module);
    return;
  }

  if (!e.target.closest('.filter-multi')) closeAllFilterMenus();
});

document.addEventListener('change', (e) => {
  const cb = e.target.closest('.filter-multi-options input[type="checkbox"][value]');
  if (!cb) return;
  const wrap = cb.closest('.filter-multi');
  const module = wrap.dataset.filterModule;
  const cat = wrap.dataset.filterCat;
  const sel = state[module].filters[cat];
  if (cb.checked) { if (!sel.includes(cb.value)) sel.push(cb.value); }
  else { state[module].filters[cat] = sel.filter(v => v !== cb.value); }
  state[module].page = 1;
  renderModule(module);
  renderFilterUI(module);
  renderFilterOptions(module, cat);
});

document.addEventListener('input', (e) => {
  const search = e.target.closest('.filter-multi-search');
  if (!search) return;
  const wrap = search.closest('.filter-multi');
  filterQuery[`${wrap.dataset.filterModule}|${wrap.dataset.filterCat}`] = search.value;
  renderFilterOptions(wrap.dataset.filterModule, wrap.dataset.filterCat);
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAllFilterMenus();
});

/* ═══════════════════════════════════════════════════════════════
   NAVIGATION
   ═══════════════════════════════════════════════════════════════ */

function showPage(page) {
  state.page = page;
  if (state.panelMode === 'scholar' && $('#detailsPanel').classList.contains('open')) closeResidentPanel(false);
  $$('.page').forEach(p => p.classList.remove('active'));
  $$('.sidebar-link').forEach(l => l.classList.remove('active'));
  const target = $('#page-' + page);
  if (target) target.classList.add('active');
  $$('.sidebar-link[data-page]').forEach(l => {
    if (l.dataset.page === page) l.classList.add('active');
  });
  $('#breadcrumbPage').textContent = PAGE_NAMES[page] || page;
  closeSidebar();
}

/* ═══════════════════════════════════════════════════════════════
   CLIENT REGISTRY — search / filter / sort / pagination
   ═══════════════════════════════════════════════════════════════ */

function filteredResidents() {
  const st = state.clients;
  let list = RESIDENTS.slice();

  if (st.search) {
    const q = st.search.toLowerCase();
    list = list.filter(r =>
      r.formalName.toLowerCase().includes(q) ||
      r.fullName.toLowerCase().includes(q) ||
      r.barangay.toLowerCase().includes(q) ||
      r.municipality.toLowerCase().includes(q) ||
      String(r.id).includes(q) ||
      r.household.toLowerCase().includes(q));
  }
  list = list.filter(r => filterMatches(r, 'clients'));
  if (st.sortKey) {
    list.sort((a, b) => compare(
      st.sortKey === 'name' ? a.formalName : a[st.sortKey], 
      st.sortKey === 'name' ? b.formalName : b[st.sortKey], st.sortDir));
  }
  return list;
}

function renderClients() {
  const st = state.clients;
  const { items, page, totalPages, total } = pageSlice(filteredResidents(), st.page, st.perPage);
  st.page = page;

  const searchEl = $('#clientsSearch');
  if (document.activeElement !== searchEl) searchEl.value = st.search;

  const tbody = $('#clientsBody');
  tbody.innerHTML = items.map(r => `
    <tr class="row-clickable" data-resident-id="${r.id}" tabindex="0"
        aria-label="Open profile of ${esc(r.formalName)}">
      <td>
        <div class="td-name">
          <div class="td-avatar" style="background:${r.avatar};color:${r.avatarText}">${esc(r.initials)}</div>
          <div>
            <div style="font-weight:600">${esc(r.formalName)}</div>
            <div style="font-size:0.72rem;color:var(--text-muted)">ID: ${r.id} &middot; ${esc(r.household)}</div>
          </div>
        </div>
      </td>
      <td>${esc(r.municipality)}</td>
      <td>${esc(r.barangay)}</td>
      <td><span class="status-badge ${r.category.cls}"><span class="dot"></span>${esc(r.category.label)}</span></td>
      <td style="font-family:'Outfit',monospace;font-size:0.82rem;">${esc(r.mobile)}</td>
      <td><span class="status-badge ${r.status === 'Active' ? 'active' : 'archived'}"><span class="dot"></span>${esc(r.status)}</span></td>
      <td class="chevron-cell"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></td>
    </tr>`).join('') || `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No clients match your filters.</td></tr>`;

  $('#clientsCount').textContent = `Showing ${items.length ? (page - 1) * st.perPage + 1 : 0}–${(page - 1) * st.perPage + items.length} of ${total} clients`;
  renderPagination('#clientsPager', { page, totalPages }, 'clients', (p) => { state.clients.page = p; renderClients(); });
  updateSortIndicators('clients', st.sortKey, st.sortDir);
  renderFilterUI('clients');
}

function toggleClientSort(key) {
  const st = state.clients;
  if (st.sortKey === key) st.sortDir = st.sortDir === 'asc' ? 'desc' : 'asc';
  else { st.sortKey = key; st.sortDir = 'asc'; }
  renderClients();
}

/* ═══════════════════════════════════════════════════════════════
   TRANSACTIONS — filter / sort / pagination
   ═══════════════════════════════════════════════════════════════ */

function filteredTransactions() {
  const st = state.transactions;
  let list = TRANSACTIONS.slice();
  if (st.search) {
    const q = st.search.toLowerCase();
    list = list.filter(t =>
      t.clientName.toLowerCase().includes(q) ||
      t.program.toLowerCase().includes(q) ||
      t.type.toLowerCase().includes(q) ||
      t.statusLabel.toLowerCase().includes(q) ||
      t.no.toLowerCase().includes(q));
  }
  list = list.filter(t => filterMatches(t, 'transactions'));
  if (st.sortKey) {
    list.sort((a, b) => compare(
      st.sortKey === 'date' ? a.date : st.sortKey === 'amount' ? a.amount : a.clientName,
      st.sortKey === 'date' ? b.date : st.sortKey === 'amount' ? b.amount : b.clientName,
      st.sortDir));
  }
  return list;
}

function renderTransactions() {
  const st = state.transactions;
  const { items, page, totalPages, total } = pageSlice(filteredTransactions(), st.page, st.perPage);
  st.page = page;

  const searchEl = $('#transactionsSearch');
  if (searchEl && document.activeElement !== searchEl) searchEl.value = st.search;

  const tbody = $('#transactionsBody');
  tbody.innerHTML = items.map(t => `
    <tr class="row-clickable" data-resident-id="${t.clientId}" tabindex="0"
        aria-label="Open profile of ${esc(t.clientName)}">
      <td style="font-family:'Outfit',monospace;color:var(--text-muted)">${esc(t.no)}</td>
      <td style="font-weight:600">${esc(t.clientName)}</td>
      <td><span class="program-tag">${esc(t.program)}</span></td>
      <td>${esc(t.type)}</td>
      <td style="white-space:nowrap">${esc(t.date)}</td>
      <td class="text-money">${money(t.amount)}</td>
      <td><span class="status-badge ${t.statusCls}"><span class="dot"></span>${esc(t.statusLabel)}</span></td>
      <td class="row-actions">
        <button type="button" class="row-action" data-edit-tx="${esc(t.no)}" aria-label="Edit transaction ${esc(t.no)}" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
        </button>
        <button type="button" class="row-action danger" data-delete-tx="${esc(t.no)}" aria-label="Delete transaction ${esc(t.no)}" title="Delete">
          <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
        </button>
      </td>
      <td class="chevron-cell"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></td>
    </tr>`).join('') || `<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No transactions match your filter.</td></tr>`;

  $('#transactionsCount').textContent = `Showing ${items.length ? (page - 1) * st.perPage + 1 : 0}–${(page - 1) * st.perPage + items.length} of ${total} transactions`;
  renderPagination('#transactionsPager', { page, totalPages }, 'transactions', (p) => { state.transactions.page = p; renderTransactions(); });
  updateSortIndicators('transactions', st.sortKey, st.sortDir);
  renderFilterUI('transactions');
}

function toggleTransactionSort(key) {
  const st = state.transactions;
  if (st.sortKey === key) st.sortDir = st.sortDir === 'asc' ? 'desc' : 'asc';
  else { st.sortKey = key; st.sortDir = 'asc'; }
  renderTransactions();
}

/* ═══════════════════════════════════════════════════════════════
   HOUSEHOLDS
   ═══════════════════════════════════════════════════════════════ */

function renderHouseholds() {
  const st = state.households;
  let list = HOUSEHOLDS.slice();
  if (st.search) {
    const q = st.search.toLowerCase();
    list = list.filter(h =>
      h.code.toLowerCase().includes(q) ||
      h.head.toLowerCase().includes(q) ||
      h.barangay.toLowerCase().includes(q) ||
      h.muni.toLowerCase().includes(q));
  }
  list = list.filter(h => filterMatches(h, 'households'));
  const { items, page, totalPages, total } = pageSlice(list, st.page, st.perPage);
  st.page = page;

  const tbody = $('#householdsBody');
  tbody.innerHTML = items.map(h => `
    <tr class="row-clickable" data-resident-id="${h.headId}" tabindex="0"
        aria-label="Open profile of household head ${esc(h.head)}">
      <td style="font-family:'Outfit',monospace;font-weight:600;">${esc(h.code)}</td>
      <td style="font-weight:600;">${esc(h.head)}</td>
      <td>${h.members} members</td>
      <td>${esc(h.barangay)}</td>
      <td>${esc(h.muni)}</td>
      <td class="row-actions">
        <button type="button" class="row-action" data-edit-hh="${esc(h.code)}" aria-label="Edit household ${esc(h.code)}" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
        </button>
        <button type="button" class="row-action danger" data-delete-hh="${esc(h.code)}" aria-label="Delete household ${esc(h.code)}" title="Delete">
          <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
        </button>
      </td>
      <td class="chevron-cell"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></td>
    </tr>`).join('') || `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No households match your search.</td></tr>`;

  $('#householdsCount').textContent = `Showing ${items.length ? (page - 1) * st.perPage + 1 : 0}–${(page - 1) * st.perPage + items.length} of ${total} households`;
  renderPagination('#householdsPager', { page, totalPages }, 'households', (p) => { state.households.page = p; renderHouseholds(); });
  renderFilterUI('households');
}

/* ═══════════════════════════════════════════════════════════════
   P6 — SCHOLARS (scholars list / GIP / reports / update log)
   ═══════════════════════════════════════════════════════════════ */

function scholarStatusCls(status) {
  if (status === 'Active') return 'active';
  if (status === 'Graduated') return 'approved';
  return 'archived';
}

function examCls(result) {
  return result === 'Passed' ? 'active' : result === 'Failed' ? 'rejected' : 'pending';
}

function filteredScholars() {
  const st = state.scholars;
  let list = SCHOLARS.slice();
  if (st.search) {
    const q = st.search.toLowerCase();
    list = list.filter(s =>
      s.formalName.toLowerCase().includes(q) ||
      s.fullName.toLowerCase().includes(q) ||
      s.id.toLowerCase().includes(q) ||
      s.school.toLowerCase().includes(q) ||
      s.course.toLowerCase().includes(q) ||
      s.program.toLowerCase().includes(q));
  }
  list = list.filter(s => filterMatches(s, 'scholars'));
  return list;
}

function renderScholars() {
  const st = state.scholars;
  const { items, page, totalPages, total } = pageSlice(filteredScholars(), st.page, st.perPage);
  st.page = page;

  const searchEl = $('#scholarsSearch');
  if (searchEl && document.activeElement !== searchEl) searchEl.value = st.search;

  const tbody = $('#scholarsBody');
  tbody.innerHTML = items.map(s => `
    <tr class="row-clickable" data-scholar-id="${esc(s.id)}" tabindex="0"
        aria-label="Open scholar profile of ${esc(s.formalName)}">
      <td style="font-family:'Outfit',monospace;font-size:0.82rem;color:var(--text-muted)">${esc(s.id)}</td>
      <td>
        <div class="td-name">
          <div class="td-avatar" style="background:${s.avatar};color:${s.avatarText}">${esc(s.initials)}</div>
          <div>
            <div style="font-weight:600">${esc(s.formalName)}</div>
            <div style="font-size:0.72rem;color:var(--text-muted)">${esc(s.barangay)}</div>
          </div>
        </div>
      </td>
      <td><span class="program-tag">${esc(s.program)}</span></td>
      <td>${esc(s.school)}</td>
      <td>${esc(s.course)}</td>
      <td>${esc(s.yearLevel)}</td>
      <td><span class="status-badge ${scholarStatusCls(s.status)}"><span class="dot"></span>${esc(s.status)}</span></td>
      <td class="row-actions">
        <button type="button" class="row-action" data-edit-scholar="${esc(s.id)}" aria-label="Edit scholar ${esc(s.id)}" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
        </button>
        <button type="button" class="row-action" data-qr-scholar="${esc(s.id)}" aria-label="View QR for ${esc(s.formalName)}" title="View QR">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><line x1="17" y1="17" x2="21" y2="17"/><line x1="17" y1="21" x2="21" y2="21"/><line x1="21" y1="17" x2="21" y2="21"/></svg>
        </button>
      </td>
      <td class="chevron-cell"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></td>
    </tr>`).join('') || `<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No scholars match the selected filters.</td></tr>`;

  $('#scholarsCount').textContent = `Showing ${items.length ? (page - 1) * st.perPage + 1 : 0}–${(page - 1) * st.perPage + items.length} of ${total} scholars`;
  renderPagination('#scholarsPager', { page, totalPages }, 'scholars', (p) => { state.scholars.page = p; renderScholars(); });
  renderFilterUI('scholars');
}

function renderGipProfiles() {
  const tbody = $('#gipBody');
  tbody.innerHTML = GIP_PROFILES.map(g => {
    const r = RESIDENT_BY_ID.get(g.clientId);
    const name = r ? r.fullName : '—';
    const initials = r ? r.initials : '—';
    const avatar = r ? r.avatar : AVATAR_COLORS[0];
    const avatarText = r ? r.avatarText : AVATAR_TEXT[0];
    return `
    <tr class="row-clickable" data-resident-id="${g.clientId}" tabindex="0"
        aria-label="Open profile of ${esc(name)}">
      <td style="font-family:'Outfit',monospace;color:var(--text-muted)">GIP-${String(g.clientId).padStart(3, '0')}</td>
      <td>
        <div class="td-name">
          <div class="td-avatar" style="background:${avatar};color:${avatarText}">${esc(initials)}</div>
          <div>
            <div style="font-weight:600">${esc(name)}</div>
            <div style="font-size:0.72rem;color:var(--text-muted)">${esc(g.position)}</div>
          </div>
        </div>
      </td>
      <td>${esc(g.college)}<br><span style="font-size:0.72rem;color:var(--text-muted)">${esc(g.course)}</span></td>
      <td>${esc(g.yearGraduated)}</td>
      <td>${esc(g.latestWorkExperience)}</td>
      <td>${esc(g.achievements)}</td>
      <td class="row-actions">
        <button type="button" class="row-action" data-sim="Opening GIP profile (mock)" title="View GIP profile" aria-label="View GIP profile">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </td>
    </tr>`;
  }).join('') || `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No GIP profiles yet.</td></tr>`;
}

function renderScholarReports() {
  $('#reportBody').innerHTML = SCHOLAR_REPORTS.map(rpt => `
    <tr>
      <td>${esc(rpt.month)}</td>
      <td><span class="program-tag">${esc(rpt.program)}</span></td>
      <td>${rpt.new}</td>
      <td>${rpt.active}</td>
      <td>${rpt.graduated}</td>
      <td class="text-money">${money(rpt.disbursed)}</td>
    </tr>`).join('');

  const activeScholars = SCHOLARS.filter(s => s.status === 'Active').length;
  const passedExams = SCHOLARS.filter(s => s.exam && s.exam.result === 'Passed').length;
  const totalDisbursed = SCHOLAR_REPORTS.reduce((sum, r) => sum + r.disbursed, 0);
  $('#metricActiveScholars').textContent = activeScholars;
  $('#metricActiveGip').textContent = GIP_PROFILES.length;
  $('#metricExamsPassed').textContent = passedExams;
  $('#metricFunds').textContent = money(totalDisbursed);
}

function renderUpdateLogs() {
  $('#updateLogBody').innerHTML = UPDATE_LOGS.map(l => `
    <tr>
      <td style="font-family:'Outfit',monospace;color:var(--text-muted)">#${l.id}</td>
      <td style="font-weight:600">${esc(l.fullName)}</td>
      <td>${esc(l.action)}</td>
      <td><span class="ip-chip">${esc(l.ipAddress)}</span></td>
      <td style="white-space:nowrap">${esc(l.createdAt)}</td>
    </tr>`).join('') || `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">No updates logged yet.</td></tr>`;
}

function switchScholarTab(tab) {
  state.scholarTab = tab;
  $$('[data-scholar-tab]').forEach(t => {
    const on = t.dataset.scholarTab === tab;
    t.classList.toggle('active', on);
    t.setAttribute('aria-selected', String(on));
  });
  $$('.scholar-tab').forEach(p => {
    p.classList.toggle('active', p.id === 'scholarTab-' + tab);
  });
}

/* ═══════════════════════════════════════════════════════════════
   DASHBOARD — recent transactions, activity, calendar
   ═══════════════════════════════════════════════════════════════ */

function renderDashboardRecent() {
  const tbody = $('#recentTxBody');
  tbody.innerHTML = TRANSACTIONS.slice(0, 5).map(t => `
    <tr class="row-clickable" data-resident-id="${t.clientId}" tabindex="0"
        aria-label="Open profile of ${esc(t.clientName)}">
      <td>
        <div class="td-name">
          <div class="td-avatar" style="background:${RESIDENT_BY_ID.get(t.clientId).avatar};color:${RESIDENT_BY_ID.get(t.clientId).avatarText}">${esc(RESIDENT_BY_ID.get(t.clientId).initials)}</div>
          <div>
            <div style="font-weight:600">${esc(t.clientName)}</div>
            <div style="font-size:0.75rem;color:var(--text-muted)">${esc(RESIDENT_BY_ID.get(t.clientId).barangay)}</div>
          </div>
        </div>
      </td>
      <td><span class="program-tag">${esc(t.program)}</span></td>
      <td><span class="text-money">${money(t.amount)}</span></td>
      <td><span class="status-badge ${t.statusCls}"><span class="dot"></span>${esc(t.statusLabel)}</span></td>
    </tr>`).join('');
}

function renderActivity() {
  const list = $('#activityList');
  list.innerHTML = ACTIVITY.map(a => `
    <div class="activity-item">
      <div class="activity-avatar" style="background:${a.color}">${esc(a.initials)}</div>
      <div class="activity-body">
        <p>${a.text}</p>
        <time>${esc(a.time)}</time>
      </div>
    </div>`).join('');
}

function renderCalendar() {
  const { month, year } = state.calendar;
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  $('#calendarTitle').textContent = `${monthNames[month]} ${year}`;

  const today = new Date();
  const firstDay = new Date(year, month, 1);
  const startDow = firstDay.getDay();           // 0 = Sunday
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrev = new Date(year, month, 0).getDate();

  const events = { 10: true, 18: true, 25: true };
  const cells = [];

  for (let i = startDow - 1; i >= 0; i--) cells.push({ n: daysInPrev - i, cls: 'out' });
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = year === today.getFullYear() && month === today.getMonth() && d === today.getDate();
    const cls = [isToday ? 'today' : '', events[d] ? 'event' : ''].join(' ').trim();
    cells.push({ n: d, cls });
  }
  while (cells.length % 7 !== 0) cells.push({ n: '', cls: 'empty' });

  $('#calendarGrid').innerHTML =
    ['Su','Mo','Tu','We','Th','Fr','Sa'].map(d => `<span class="dow">${d}</span>`).join('') +
    cells.map(c => `<span class="calendar-day ${c.cls}" ${c.cls === 'empty' ? 'aria-hidden="true"' : ''}>${c.n}</span>`).join('');
}

function renderNotifications() {
  const unread = NOTIFICATIONS.filter(n => n.unread).length;
  $('#notifDot').textContent = unread;
  $('#notifDot').hidden = unread === 0;
  $('#notifList').innerHTML = NOTIFICATIONS.map((n, i) => `
    <button type="button" class="notif-item ${n.unread ? 'unread' : ''}" data-notif-index="${i}">
      <span class="notif-icon ${n.icon}">
        <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      </span>
      <span class="notif-text">
        <h5>${esc(n.title)}</h5>
        <p>${esc(n.text)}</p>
        <span class="notif-time">${esc(n.time)}</span>
      </span>
    </button>`).join('');
}

/* ═══════════════════════════════════════════════════════════════
   RESIDENT DETAILS PANEL
   ═══════════════════════════════════════════════════════════════ */

function qrPlaceholder() {
  return `<svg viewBox="0 0 21 21" role="img" aria-label="QR code placeholder" focusable="false">
    <rect x="0" y="0" width="9" height="9" fill="#0F1B2D"/><rect x="12" y="0" width="9" height="9" fill="#0F1B2D"/>
    <rect x="0" y="12" width="9" height="9" fill="#0F1B2D"/>
    <rect x="2" y="2" width="5" height="5" fill="#fff"/><rect x="14" y="2" width="5" height="5" fill="#fff"/>
    <rect x="2" y="14" width="5" height="5" fill="#fff"/>
    <rect x="3" y="3" width="3" height="3" fill="#0F1B2D"/><rect x="15" y="3" width="3" height="3" fill="#0F1B2D"/>
    <rect x="3" y="15" width="3" height="3" fill="#0F1B2D"/>
    <rect x="11" y="11" width="2" height="2" fill="#0F1B2D"/><rect x="14" y="11" width="2" height="2" fill="#0F1B2D"/><rect x="17" y="11" width="2" height="2" fill="#0F1B2D"/>
    <rect x="11" y="14" width="2" height="2" fill="#0F1B2D"/><rect x="14" y="14" width="2" height="2" fill="#0F1B2D"/><rect x="11" y="17" width="2" height="2" fill="#0F1B2D"/><rect x="17" y="17" width="2" height="2" fill="#0F1B2D"/>
    <rect x="11" y="2" width="2" height="2" fill="#0F1B2D"/><rect x="14" y="2" width="2" height="2" fill="#0F1B2D"/><rect x="17" y="5" width="2" height="2" fill="#0F1B2D"/>
    <rect x="2" y="11" width="2" height="2" fill="#0F1B2D"/><rect x="5" y="11" width="2" height="2" fill="#0F1B2D"/>
  </svg>`;
}

function fieldRow(label, value, cls = '') {
  return `<div class="details-field ${cls}"><label>${label}</label><div class="value">${value}</div></div>`;
}

function renderResidentPanel(r) {
  $('#detailsHeader').innerHTML = `
    <button type="button" class="details-close" id="detailsClose" aria-label="Close details panel">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="details-identity">
      <div class="details-avatar" style="background:${r.avatar};color:${r.avatarText}">${esc(r.initials)}</div>
      <div style="flex:1;min-width:0">
        <h2 id="detailsPanelTitle">${esc(r.formalName)}</h2>
        <div class="sub">ID: ${r.id} &middot; ${esc(r.category.label)}</div>
        <div class="details-meta">
          <span class="status-badge ${r.status === 'Active' ? 'active' : 'archived'}"><span class="dot"></span>${esc(r.status)}</span>
          <span class="program-tag">${esc(r.household)}</span>
          <div class="qr-box">${qrPlaceholder()}</div>
        </div>
      </div>
    </div>`;

  $('#detailsActions').innerHTML = `
    <button type="button" class="btn btn-gold" data-edit-client="${r.id}">Edit</button>
    <button type="button" class="btn btn-outline" data-sim="Printing profile card">Print</button>
    <button type="button" class="btn btn-outline" data-sim="Certificate generated (mock)">Generate Certificate</button>
    <button type="button" class="btn btn-outline" data-archive-client="${r.id}">Archive</button>
    <button type="button" class="btn btn-danger" data-delete-client="${r.id}">Delete</button>`;

  const govIds = r.govIds.map(g =>
    `<div class="details-doc" style="cursor:default">
      <span class="doc-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
      <span class="doc-name"><h5>${esc(g.label)}</h5><p>${esc(g.value)}</p></span>
    </div>`).join('');

  const docs = r.docs.map(d =>
    `<div class="details-doc">
      <span class="doc-icon"><svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg></span>
      <span class="doc-name"><h5>${esc(d.name)}</h5><p>${esc(d.meta)}</p></span>
      <button type="button" class="doc-more" data-sim="Previewing ${esc(d.name)} (mock)" aria-label="Preview ${esc(d.name)}">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
      </button>
    </div>`).join('');

  const timeline = r.timeline.map(t =>
    `<div class="tl-item"><h5>${esc(t.title)}</h5><p>${esc(t.text)}</p><time>${esc(t.time)}</time></div>`).join('');

  $('#detailsBody').innerHTML = `
    <section class="details-section" aria-labelledby="sec-personal">
      <h3 class="details-section-title" id="sec-personal">Personal Information</h3>
      <div class="details-grid">
        ${fieldRow('Full Name', esc(r.formalName), 'wide')}
        ${fieldRow('Birthdate', esc(r.birthdate))}
        ${fieldRow('Sex', esc(r.sex))}
        ${fieldRow('Civil Status', esc(r.civilStatus))}
        ${fieldRow('Occupation', esc(r.occupation))}
        ${fieldRow('Category', `<span class="status-badge ${r.category.cls}"><span class="dot"></span>${esc(r.category.label)}</span>`)}
      </div>
    </section>

    <section class="details-section" aria-labelledby="sec-household">
      <h3 class="details-section-title" id="sec-household">Household</h3>
      <div class="details-grid">
        ${fieldRow('Household Code', esc(r.household))}
        ${fieldRow('Household Members', '4 members')}
        ${fieldRow('Municipality', esc(r.municipality))}
        ${fieldRow('Barangay', esc(r.barangay))}
        ${fieldRow('Address', esc(r.address), 'wide')}
      </div>
    </section>

    <section class="details-section" aria-labelledby="sec-contact">
      <h3 class="details-section-title" id="sec-contact">Contact Information</h3>
      <div class="details-grid">
        ${fieldRow('Mobile Number', esc(r.mobile))}
        ${fieldRow('Email', esc(r.email))}
      </div>
    </section>

    <section class="details-section" aria-labelledby="sec-programs">
      <h3 class="details-section-title" id="sec-programs">Programs &amp; Services</h3>
      <div class="details-programs">
        ${(r.programs && r.programs.length)
          ? r.programs.map(p => `<span class="program-tag">${esc(p)}</span>`).join('')
          : '<span class="prog-empty">No programs selected yet.</span>'}
      </div>
    </section>

    <section class="details-section" aria-labelledby="sec-gov">
      <h3 class="details-section-title" id="sec-gov">Government IDs</h3>
      ${govIds}
    </section>

    <section class="details-section" aria-labelledby="sec-notes">
      <h3 class="details-section-title" id="sec-notes">Notes</h3>
      <div class="details-note">${esc(r.notes)}</div>
    </section>

    <section class="details-section" aria-labelledby="sec-timeline">
      <h3 class="details-section-title" id="sec-timeline">Timeline</h3>
      <div class="details-timeline">${timeline}</div>
    </section>

    <section class="details-section" aria-labelledby="sec-docs">
      <h3 class="details-section-title" id="sec-docs">Attached Documents</h3>
      ${docs}
    </section>

    <section class="details-section" aria-labelledby="sec-audit">
      <h3 class="details-section-title" id="sec-audit">Audit Information</h3>
      <div class="details-grid">
        ${fieldRow('Created By', esc(r.audit.createdBy))}
        ${fieldRow('Created At', esc(r.audit.createdAt))}
        ${fieldRow('Last Updated By', esc(r.audit.updatedBy))}
        ${fieldRow('Last Updated', esc(r.audit.updatedAt))}
        ${fieldRow('Last Viewed', esc(r.audit.lastView), 'wide')}
      </div>
    </section>`;

  state.openResidentId = r.id;
}

function openResidentPanel(id) {
  const r = RESIDENT_BY_ID.get(Number(id));
  if (!r) return;
  state.lastFocusedRow = document.activeElement && document.activeElement.closest('tr')
    ? document.activeElement : null;
  renderResidentPanel(r);
  $('#detailsPanel').classList.add('open');
  $('#detailsBackdrop').classList.add('show');
  lockScroll();
  $('#detailsPanel').setAttribute('aria-hidden', 'false');
  const close = $('#detailsClose');
  if (close) close.focus();
}

function closeResidentPanel(returnFocus = true) {
  $('#detailsPanel').classList.remove('open');
  $('#detailsBackdrop').classList.remove('show');
  unlockScroll();
  $('#detailsPanel').setAttribute('aria-hidden', 'true');
  state.openResidentId = null;
  state.openScholarId = null;
  state.panelMode = 'resident';
  if (returnFocus && state.lastFocusedRow && state.lastFocusedRow.isConnected) {
    state.lastFocusedRow.focus();
  }
}

/* ═══════════════════════════════════════════════════════════════
   P6 — SCHOLAR PROFILE PANEL (reuses the details slide-over)
   ═══════════════════════════════════════════════════════════════ */

function renderScholarPanel(s) {
  $('#detailsHeader').innerHTML = `
    <button type="button" class="details-close" id="detailsClose" aria-label="Close details panel">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="details-identity">
      <div class="details-avatar" style="background:${s.avatar};color:${s.avatarText}">${esc(s.initials)}</div>
      <div style="flex:1;min-width:0">
        <h2 id="detailsPanelTitle">${esc(s.formalName)}</h2>
        <div class="sub">Scholar ID: ${esc(s.id)} &middot; ${esc(s.program)}</div>
        <div class="details-meta">
          <span class="status-badge ${scholarStatusCls(s.status)}"><span class="dot"></span>${esc(s.status)}</span>
          <span class="program-tag">${esc(s.program)}</span>
        </div>
      </div>
    </div>`;

  $('#detailsActions').innerHTML = `
    <button type="button" class="btn btn-gold" data-edit-scholar="${esc(s.id)}">Edit</button>
    <button type="button" class="btn btn-outline" data-qr-scholar="${esc(s.id)}">View QR</button>
    <button type="button" class="btn btn-outline" data-sim="Scholarship certificate generated (mock)">Certificate</button>`;

  const exam = s.exam || {};
  const examBadge = exam.result
    ? `<span class="status-badge ${examCls(exam.result)}"><span class="dot"></span>${esc(exam.result)}</span>`
    : '<span class="status-badge pending"><span class="dot"></span>Not yet taken</span>';

  const gip = s.gip;
  const gipHTML = gip ? `
    <section class="details-section" aria-labelledby="sec-gip">
      <h3 class="details-section-title" id="sec-gip">GIP Profile</h3>
      <div class="details-grid">
        ${fieldRow('Valid Government ID', esc(gip.validGovtId))}
        ${fieldRow('ID Number', esc(gip.idNumber))}
        ${fieldRow('Insurance Beneficiary', esc(gip.insuranceBeneficiary))}
        ${fieldRow('Emergency Contact', esc(gip.emergencyContact))}
        ${fieldRow('ECP Contact Number', esc(gip.ecpContactNumber))}
        ${fieldRow('ECP Address', esc(gip.ecpAddress), 'wide')}
        ${fieldRow('College', esc(gip.college))}
        ${fieldRow('Course', esc(gip.course))}
        ${fieldRow('Year Graduated', esc(gip.yearGraduated))}
        ${fieldRow('High School', esc(gip.highSchool))}
        ${fieldRow('Elementary School', esc(gip.elementarySchool))}
        ${fieldRow('Latest Work Experience', esc(gip.latestWorkExperience))}
        ${fieldRow('Position', esc(gip.position))}
        ${fieldRow('Period of Engagement', esc(gip.periodOfEngagement))}
        ${fieldRow('Special Skills', esc(gip.specialSkills))}
        ${fieldRow('Achievements', esc(gip.achievements), 'wide')}
      </div>
    </section>` : '';

  const recentUpdates = UPDATE_LOGS
    .filter(l => l.clientId === s.clientId)
    .slice(0, 3)
    .map(l => `<div class="tl-item"><h5>${esc(l.action)}</h5><p>${esc(l.ipAddress)}</p><time>${esc(l.createdAt)}</time></div>`)
    .join('') || '<div class="details-note">No self-updates recorded for this scholar yet.</div>';

  $('#detailsBody').innerHTML = `
    <section class="details-section" aria-labelledby="sec-scholar">
      <h3 class="details-section-title" id="sec-scholar">Scholarship Information</h3>
      <div class="details-grid">
        ${fieldRow('Program', `<span class="program-tag">${esc(s.program)}</span>`)}
        ${fieldRow('School', esc(s.school), 'wide')}
        ${fieldRow('School Type', esc(s.schoolType))}
        ${fieldRow('Campus', esc(s.campus))}
        ${fieldRow('College / Department', esc(s.collegeDept), 'wide')}
        ${fieldRow('Course', esc(s.course), 'wide')}
        ${fieldRow('Year Level', esc(s.yearLevel))}
        ${fieldRow('Regular', s.isRegular ? 'Yes' : 'No')}
        ${fieldRow('Year Started', esc(s.yearStarted))}
        ${fieldRow('Landbank Account No.', esc(s.landbankNo))}
        ${fieldRow('Status', `<span class="status-badge ${scholarStatusCls(s.status)}"><span class="dot"></span>${esc(s.status)}</span>`)}
      </div>
    </section>

    <section class="details-section" aria-labelledby="sec-exam">
      <h3 class="details-section-title" id="sec-exam">Exam Result</h3>
      <div class="details-grid">
        ${fieldRow('Exam Date', exam.date ? esc(exam.date) : '—')}
        ${fieldRow('Venue', exam.venue ? esc(exam.venue) : '—')}
        ${fieldRow('Result', examBadge)}
        ${fieldRow('Score', exam.score != null ? String(exam.score) : '—')}
      </div>
    </section>
    ${gipHTML}

    <section class="details-section" aria-labelledby="sec-scholar-log">
      <h3 class="details-section-title" id="sec-scholar-log">Update Log</h3>
      <div class="details-timeline">${recentUpdates}</div>
    </section>

    <section class="details-section" aria-labelledby="sec-scholar-contact">
      <h3 class="details-section-title" id="sec-scholar-contact">Contact</h3>
      <div class="details-grid">
        ${fieldRow('Mobile Number', esc(s.mobile))}
        ${fieldRow('Municipality', esc(s.municipality))}
        ${fieldRow('Barangay', esc(s.barangay))}
      </div>
    </section>`;

  state.openScholarId = s.id;
  state.panelMode = 'scholar';
}

function openScholarPanel(id) {
  const s = SCHOLAR_BY_ID.get(String(id));
  if (!s) return;
  state.lastFocusedRow = document.activeElement && document.activeElement.closest('tr')
    ? document.activeElement : null;
  renderScholarPanel(s);
  $('#detailsPanel').classList.add('open');
  $('#detailsBackdrop').classList.add('show');
  lockScroll();
  $('#detailsPanel').setAttribute('aria-hidden', 'false');
  const close = $('#detailsClose');
  if (close) close.focus();
}

function openQrModal(id) {
  const s = SCHOLAR_BY_ID.get(String(id));
  if (!s) return;
  openModal({
    title: `Scholar QR — ${s.id}`,
    sub: 'QR codes will be issued with the scholarship payout card (planned P6 feature)',
    bodyHTML: `
      <div class="qr-modal-body">
        <div class="qr-large">${qrPlaceholder()}</div>
        <div class="qr-meta">
          <p class="qr-name">${esc(s.formalName)}</p>
          <p><span class="program-tag">${esc(s.program)}</span></p>
          <p class="qr-sub">${esc(s.school)} &middot; ${esc(s.course)}</p>
          <p class="qr-sub">Scan this code at the payout counter to verify the scholar record.</p>
        </div>
      </div>`,
    footerHTML: `
      <button type="button" class="btn btn-outline" data-modal-cancel>Close</button>
      <button type="button" class="btn btn-gold" data-sim="QR card print started (mock)">Print QR Card</button>`,
    onOpen: (ov) => {
      ov.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
    },
  });
}

/* ═══════════════════════════════════════════════════════════════
   P6 — MULTI-PROGRAM SELECTOR (tag / chip picker)
   Selected programs render as removable chips; available programs
   are grouped and searchable. A program can never be in both.
   ═══════════════════════════════════════════════════════════════ */

function buildProgramSelect(container, selected = []) {
  container._selected = selected.slice();
  container._query = container._query || '';
  const q = container._query;
  const withSearch = container.dataset.progSearch !== 'off';
  container.innerHTML = `
    <div class="prog-selected" data-prog-selected></div>
    ${withSearch ? `<input type="search" class="prog-search" placeholder="Search programs…" aria-label="Search available programs">` : ''}
    <div class="prog-groups" data-prog-groups></div>`;
  renderProgSelected(container);
  const search = container.querySelector('.prog-search');
  if (search) search.value = q;
  renderProgGroups(container, q);
}

function renderProgSelected(container) {
  const el = container.querySelector('[data-prog-selected]');
  if (!container._selected.length) {
    el.innerHTML = `<span class="prog-empty">No programs selected yet.</span>`;
    return;
  }
  el.innerHTML = container._selected.map(p =>
    `<span class="prog-chip">${esc(p)}<button type="button" class="prog-chip-x" data-prog-remove="${esc(p)}" aria-label="Remove ${esc(p)}">&times;</button></span>`).join('');
}

function renderProgGroups(container, query) {
  const groupsEl = container.querySelector('[data-prog-groups]');
  if (!groupsEl) return;
  const ql = String(query || '').toLowerCase().trim();
  let html = '';
  let count = 0;
  PROGRAM_GROUPS.forEach(g => {
    const avail = g.programs.filter(p =>
      !container._selected.includes(p) &&
      (!ql || p.toLowerCase().includes(ql)));
    if (!avail.length) return;
    count += avail.length;
    html += `<div class="prog-group"><span class="prog-group-title">${esc(g.label)}</span>` +
      avail.map(p => `<button type="button" class="prog-btn" data-prog-add="${esc(p)}">${esc(p)}</button>`).join('') +
      `</div>`;
  });
  groupsEl.innerHTML = html || `<span class="prog-no-results">No available programs${ql ? ' match "' + esc(ql) + '"' : ''}.</span>`;
}

/* ═══════════════════════════════════════════════════════════════
   P6 — SCHOLAR CRUD + GRANTEE SELF-UPDATE + CSV EXPORT
   ═══════════════════════════════════════════════════════════════ */

function openScholarForm(id) {
  const s = id ? SCHOLAR_BY_ID.get(String(id)) : null;
  const clientCur = s ? s.clientId : (RESIDENTS[0] ? RESIDENTS[0].id : '');

  openModal({
    title: s ? `Edit Scholar ${s.id}` : 'Add Scholar',
    sub: s ? esc(s.formalName) : 'Enroll a scholar under a scholarship program',
    size: 'modal-lg',
    bodyHTML: `
      <form id="scholarForm">
        <div class="form-grid">
          <div class="form-group span-2">
            <label class="form-label" for="sfClient">Client *</label>
            <select class="form-input" id="sfClient" name="clientId">
              ${RESIDENTS.map(r => `<option value="${r.id}" ${r.id === clientCur ? 'selected' : ''}>${esc(r.formalName)}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="sfProgram">Program *</label>
            <select class="form-input" id="sfProgram" name="program">${optList(SCHOLAR_PROGRAMS, s ? s.program : SCHOLAR_PROGRAMS[0])}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="sfStatus">Status</label>
            <select class="form-input" id="sfStatus" name="status">${optList(['Active', 'Graduated', 'Inactive'], s ? s.status : 'Active')}</select>
          </div>
          <div class="form-group span-2">
            <label class="form-label" for="sfSchool">School *</label>
            <input type="text" class="form-input" id="sfSchool" name="school" value="${s ? esc(s.school) : ''}" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="sfSchoolType">School Type</label>
            <select class="form-input" id="sfSchoolType" name="schoolType">${optList(['University', 'College', 'High School'], s ? s.schoolType : 'College')}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="sfCampus">Campus</label>
            <input type="text" class="form-input" id="sfCampus" name="campus" value="${s ? esc(s.campus) : ''}">
          </div>
          <div class="form-group span-2">
            <label class="form-label" for="sfDept">College / Department</label>
            <input type="text" class="form-input" id="sfDept" name="collegeDept" value="${s ? esc(s.collegeDept) : ''}">
          </div>
          <div class="form-group span-2">
            <label class="form-label" for="sfCourse">Course *</label>
            <input type="text" class="form-input" id="sfCourse" name="course" value="${s ? esc(s.course) : ''}" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="sfYearLevel">Year Level</label>
            <input type="text" class="form-input" id="sfYearLevel" name="yearLevel" value="${s ? esc(s.yearLevel) : '1st Year'}">
          </div>
          <div class="form-group">
            <label class="form-label" for="sfYearStarted">Year Started</label>
            <input type="number" class="form-input" id="sfYearStarted" name="yearStarted" min="2000" max="2030" value="${s ? s.yearStarted : '2026'}">
          </div>
          <div class="form-group">
            <label class="form-label" for="sfLandbank">Landbank Account No.</label>
            <input type="text" class="form-input" id="sfLandbank" name="landbankNo" value="${s ? esc(s.landbankNo) : ''}">
          </div>
          <div class="form-group">
            <label class="form-label" for="sfRegular">Regular Scholar</label>
            <select class="form-input" id="sfRegular" name="isRegular">${optList(['Yes', 'No'], s ? (s.isRegular ? 'Yes' : 'No') : 'Yes')}</select>
          </div>
        </div>
      </form>`,
    footerHTML: `
      <button type="button" class="btn btn-outline" data-modal-cancel>Cancel</button>
      <button type="submit" class="btn btn-gold" form="scholarForm">${s ? 'Save Changes' : 'Add Scholar'}</button>`,
    onOpen: (ov) => {
      ov.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
      ov.querySelector('#scholarForm').addEventListener('submit', (e) => saveScholarForm(e, s));
    },
  });
}

function saveScholarForm(e, existing) {
  e.preventDefault();
  const d = readForm(e.target);
  const client = RESIDENT_BY_ID.get(Number(d.clientId));
  if (!client) { showToast('Select a valid client'); return; }
  if (!d.school || !d.course) { showToast('School and course are required'); return; }

  const base = {
    clientId: client.id,
    program: d.program,
    school: d.school, schoolType: d.schoolType, campus: d.campus,
    collegeDept: d.collegeDept, course: d.course, yearLevel: d.yearLevel,
    yearStarted: Number(d.yearStarted) || 2026, landbankNo: d.landbankNo || '—',
    isRegular: d.isRegular === 'Yes', status: d.status,
  };

  if (existing) {
    Object.assign(existing, base, {
      fullName: client.fullName, formalName: client.formalName,
      initials: client.initials, avatar: client.avatar, avatarText: client.avatarText,
      barangay: client.barangay, municipality: client.municipality, mobile: client.mobile,
    });
    closeModal();
    renderScholars();
    showToast(`Scholar ${existing.id} updated`);
    if (state.openScholarId === existing.id) renderScholarPanel(existing);
    return;
  }

  const id = 'SC-2026-' + String(SCHOLARS.length + 1).padStart(3, '0');
  const s = buildScholar(Object.assign({}, base, {
    id,
    exam: { date: '—', venue: '—', result: null, score: null },
  }));
  SCHOLARS.unshift(s);
  SCHOLAR_BY_ID.set(s.id, s);
  closeModal();
  state.scholars.page = 1;
  renderScholars();
  prependActivity(`<strong>${esc(client.fullName)}</strong> was enrolled as a ${esc(d.program)} scholar`);
  showToast(`Scholar ${id} added`);
}

function submitGranteeUpdate(e) {
  e.preventDefault();
  const form = e.target;
  const name = $('#selfFullName').value.trim();
  if (!name) { showToast('Full name is required'); return; }
  const progHost = $('[data-prog-select="self"]');
  const selected = (progHost && progHost._selected) || [];
  const program = selected.length ? selected.join(', ') : '—';
  const action = `Self-update submitted: program ${program}, Landbank ${$('#selfLandbank').value.trim() || '—'}`;
  UPDATE_LOGS.unshift({
    id: UPDATE_LOGS.length + 1,
    clientId: null,
    fullName: name,
    action,
    ipAddress: '192.168.1.' + (10 + (UPDATE_LOGS.length % 240)),
    createdAt: nowStamp(),
  });
  renderUpdateLogs();
  form.reset();
  if (progHost) { buildProgramSelect(progHost, []); }
  prependActivity(`<strong>${esc(name)}</strong> submitted a grantee self-update (${esc(program)})`);
  showToast('Update submitted — queued for office review (mock)');
}

function exportScholarCsv() {
  const header = ['Scholar ID', 'Client ID', 'Client', 'Program', 'School', 'Course', 'Year Level', 'Status'];
  const rows = SCHOLARS.map(s => [
    s.id, s.clientId, s.fullName, s.program, s.school, s.course, s.yearLevel, s.status,
  ]);
  const csv = [header].concat(rows).map(r =>
    r.map(c => '"' + String(c ?? '').replace(/"/g, '""') + '"').join(',')).join('\r\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'scholars-report-2026.csv';
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
  showToast('scholars-report-2026.csv downloaded (UTF-8 BOM)');
}

/* ═══════════════════════════════════════════════════════════════
   SCANNER — simulated scan
   ═══════════════════════════════════════════════════════════════ */

function simulateScan() {
  const tx = TRANSACTIONS[Math.floor(Math.random() * TRANSACTIONS.length)];
  const r = RESIDENT_BY_ID.get(tx.clientId);
  const ready = tx.status !== 'rejected';
  const statusCls = ready ? 'approved' : 'rejected';
  const statusLabel = ready ? 'Ready for Payout' : 'Blocked';
  $('#scannerResult').innerHTML = `
    <div class="result-field"><label>Client Name</label><div class="value">${esc(r.fullName)}</div></div>
    <div class="result-field"><label>Program</label><div class="value"><span class="program-tag">${esc(tx.program)}</span></div></div>
    <div class="result-field"><label>Transaction ID</label><div class="value" style="font-family:'Outfit',monospace;">TXN-2026-0${String(tx.no).replace('#', '')}</div></div>
    <div class="result-field"><label>Amount Payable</label><div class="value text-money" style="font-size:1.2rem;color:var(--teal);">${money(tx.amount)}</div></div>
    <div class="result-field"><label>Payout Status</label><div class="value"><span class="status-badge ${statusCls}"><span class="dot"></span>${statusLabel}</span></div></div>
    <div class="result-actions">
      <button type="button" class="btn btn-gold btn-sm" style="flex:1" data-sim="Payout confirmed (simulated)">Confirm Payout</button>
      <button type="button" class="btn btn-outline btn-sm" style="flex:1" data-sim="Payout rejected (simulated)">Reject</button>
    </div>`;
  showToast('Scan complete — result loaded (mock data)');
}

/* ═══════════════════════════════════════════════════════════════
   TOASTS
   ═══════════════════════════════════════════════════════════════ */

function showToast(message) {
  const container = $('#toastContainer');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.setAttribute('role', 'status');
  toast.innerHTML = `
    <span class="toast-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
    <span>${esc(message)}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('out');
    setTimeout(() => toast.remove(), 300);
  }, 2600);
}

/* ═══════════════════════════════════════════════════════════════
   MODAL SYSTEM — reusable dialog + confirm (prototype CRUD)
   ═══════════════════════════════════════════════════════════════ */

let modalEl = null;
let modalLastFocus = null;
let confirmResolve = null;
let scrollLock = 0;

function lockScroll() { scrollLock++; document.body.classList.add('no-scroll'); }
function unlockScroll() { scrollLock = Math.max(0, scrollLock - 1); if (scrollLock === 0) document.body.classList.remove('no-scroll'); }

function openModal({ title, sub = '', bodyHTML = '', footerHTML = '', size = '', onOpen = null }) {
  closeModal();
  modalLastFocus = document.activeElement;
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', title);
  overlay.innerHTML = `
    <div class="modal ${size}">
      <div class="modal-header">
        <div>
          <h2>${esc(title)}</h2>
          ${sub ? `<p class="modal-sub">${esc(sub)}</p>` : ''}
        </div>
        <button type="button" class="modal-close" aria-label="Close dialog">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">${bodyHTML}</div>
      ${footerHTML ? `<div class="modal-footer">${footerHTML}</div>` : ''}
    </div>`;
  document.body.appendChild(overlay);
  modalEl = overlay;
  lockScroll();
  requestAnimationFrame(() => overlay.classList.add('show'));

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay || e.target.closest('.modal-close')) closeModal();
  });

  if (onOpen) onOpen(overlay);

  const focusables = Array.from(overlay.querySelectorAll('input, select, textarea, button:not([disabled])'));
  if (focusables.length) focusables[0].focus();
  return overlay;
}

function closeModal() {
  if (!modalEl) return;
  const overlay = modalEl;
  modalEl = null;
  overlay.classList.remove('show');
  unlockScroll();
  setTimeout(() => { if (overlay.isConnected) overlay.remove(); }, 220);
  if (confirmResolve) { const r = confirmResolve; confirmResolve = null; r(false); }
  if (modalLastFocus && modalLastFocus.isConnected) modalLastFocus.focus();
}

function confirmDialog({ title, message, confirmLabel = 'Confirm', danger = true }) {
  return new Promise((resolve) => {
    confirmResolve = resolve;
    openModal({
      title,
      bodyHTML: `<div class="confirm-body">${message}</div>`,
      footerHTML: `
        <button type="button" class="btn btn-outline" data-modal-cancel>Cancel</button>
        <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-gold'}" data-modal-confirm>${esc(confirmLabel)}</button>`,
      onOpen: (ov) => {
        const cancel = ov.querySelector('[data-modal-cancel]');
        const ok = ov.querySelector('[data-modal-confirm]');
        cancel.addEventListener('click', () => closeModal());  // closes → resolves(false)
        ok.addEventListener('click', () => {
          const r = confirmResolve; confirmResolve = null;
          closeModal();
          if (r) r(true);
        });
        ok.focus();
      },
    });
  });
}

/* ═══════════════════════════════════════════════════════════════
   CRUD HELPERS — ids, forms, refresh
   ═══════════════════════════════════════════════════════════════ */

const MUNIS = [...new Set(RESIDENTS.map(r => r.municipality))].sort();
const TX_TYPES = ['Cash Assistance', 'Medical', 'Hospitalization', 'Scholarship', 'Burial',
  'Emergency Employment', 'Cash For Work', 'CRA', 'Skills Training', 'Membership', 'Cash Relief Assistance'];
const STATUS_PAIRS = [
  ['paid', 'Paid'], ['approved', 'Approved'], ['pending', 'Pending'], ['rejected', 'Rejected'],
];

const nextClientId = () => Math.max(...RESIDENTS.map(r => r.id)) + 1;
const nextTxNo = () => '#' + String(TRANSACTIONS.length + 1).padStart(3, '0');
const nextHouseholdCode = () => {
  const nums = HOUSEHOLDS.map(h => Number(String(h.code).split('-').pop())).filter(n => !Number.isNaN(n));
  return 'HH-2026-' + String((nums.length ? Math.max(...nums) : 0) + 1).padStart(3, '0');
};

function nowStamp() {
  const d = new Date();
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function readForm(form) {
  const o = {};
  new FormData(form).forEach((v, k) => { o[k] = String(v).trim(); });
  return o;
}

const optList = (items, current) => items.map(it =>
  `<option value="${esc(it)}" ${String(it) === String(current) ? 'selected' : ''}>${esc(it)}</option>`).join('');

const optListKV = (pairs, current) => pairs.map(([v, l]) =>
  `<option value="${esc(v)}" ${String(v) === String(current) ? 'selected' : ''}>${esc(l)}</option>`).join('');

const input = (label, name, value = '', required = false) => `
  <div class="form-group">
    <label class="form-label" for="cf_${name}">${label}</label>
    <input type="text" class="form-input" id="cf_${name}" name="${name}" value="${esc(value)}" ${required ? 'required' : ''}>
  </div>`;

function refreshAllViews() {
  renderClients();
  renderTransactions();
  renderHouseholds();
  renderScholars();
  renderGipProfiles();
  renderScholarReports();
  renderUpdateLogs();
  renderDashboardRecent();
  renderActivity();
  updateMetrics();
}

function prependActivity(text) {
  ACTIVITY.unshift({ initials: 'JA', color: AVATAR_COLORS[0], text, time: 'just now' });
  renderActivity();
}

function updateMetrics() {
  const paidTotal = TRANSACTIONS.filter(t => t.status === 'paid').reduce((s, t) => s + t.amount, 0);
  $('#metricClients').textContent = RESIDENTS.length.toLocaleString();
  $('#metricTx').textContent = TRANSACTIONS.length.toLocaleString();
  $('#metricDisbursed').textContent = paidTotal >= 1000000
    ? '₱' + (paidTotal / 1000000).toFixed(1) + 'M'
    : money(paidTotal);
  $('#metricPending').textContent = TRANSACTIONS.filter(t => t.status === 'pending').length;
  $('#sidebarClientsBadge').textContent = RESIDENTS.length;
  $('#sidebarTxBadge').textContent = TRANSACTIONS.length;
  const scholarsBadge = $('#sidebarScholarsBadge');
  if (scholarsBadge) scholarsBadge.textContent = SCHOLARS.length;
}

/* ═══════════════════════════════════════════════════════════════
   CLIENT CRUD — add / edit / archive / delete
   ═══════════════════════════════════════════════════════════════ */

function openClientForm(id) {
  const r = id ? RESIDENT_BY_ID.get(id) : null;
  const hhCodes = ['auto', ...new Set(HOUSEHOLDS.map(h => h.code))];
  if (r && r.household && !hhCodes.includes(r.household)) hhCodes.push(r.household);

  openModal({
    title: r ? `Edit Client #${r.id}` : 'Add Client',
    sub: r ? esc(r.formalName) : 'Register a new client in the registry',
    size: 'modal-lg',
    bodyHTML: `
      <form id="clientForm">
        <div class="form-grid">
          ${input('First Name *', 'first', r ? r.first : '', true)}
          ${input('Middle Name', 'middle', r ? r.middle : '')}
          ${input('Last Name *', 'last', r ? r.last : '', true)}
          <div class="form-group">
            <label class="form-label" for="cfSex">Sex</label>
            <select class="form-input" id="cfSex" name="sex">${optList(['M', 'F'], r ? r.sex : 'M')}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfBirthdate">Birthdate</label>
            <input type="date" class="form-input" id="cfBirthdate" name="birthdate" value="${r ? esc(r.birthdate) : ''}">
          </div>
          <div class="form-group">
            <label class="form-label" for="cfCategory">Category</label>
            <select class="form-input" id="cfCategory" name="category">${optList(CATEGORIES.map(c => c.label), r ? r.category.label : CATEGORIES[0].label)}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfCivil">Civil Status</label>
            <select class="form-input" id="cfCivil" name="civilStatus">${optList(CIVIL, r ? r.civilStatus : CIVIL[0])}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfOcc">Occupation</label>
            <select class="form-input" id="cfOcc" name="occupation">${optList(OCCUPATIONS, r ? r.occupation : OCCUPATIONS[0])}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfMuni">Municipality *</label>
            <select class="form-input" id="cfMuni" name="municipality">${optList(MUNIS, r ? r.municipality : 'Candon City')}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfBrgy">Barangay *</label>
            <input type="text" class="form-input" id="cfBrgy" name="barangay" value="${r ? esc(r.barangay) : ''}" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfMobile">Mobile Number</label>
            <input type="text" class="form-input" id="cfMobile" name="mobile" value="${r ? esc(r.mobile) : ''}">
          </div>
          <div class="form-group">
            <label class="form-label" for="cfEmail">Email</label>
            <input type="email" class="form-input" id="cfEmail" name="email" value="${r ? esc(r.email) : ''}">
          </div>
          <div class="form-group span-2">
            <label class="form-label" for="cfAddress">Address</label>
            <input type="text" class="form-input" id="cfAddress" name="address" value="${r ? esc(r.address) : ''}">
          </div>
          <div class="form-group">
            <label class="form-label" for="cfStatus">Status</label>
            <select class="form-input" id="cfStatus" name="status">${optList(['Active', 'Archived'], r ? r.status : 'Active')}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="cfHousehold">Household</label>
            <select class="form-input" id="cfHousehold" name="household">${optList(hhCodes, r ? r.household : 'auto')}</select>
          </div>
          <div class="form-group span-2">
            <span class="form-label">Programs &amp; Services (select all that apply)</span>
            <div class="prog-select" data-prog-select="cf" data-prog-search="off" aria-label="Programs and services"></div>
          </div>
        </div>
      </form>`,
    footerHTML: `
      <button type="button" class="btn btn-outline" data-modal-cancel>Cancel</button>
      <button type="submit" class="btn btn-gold" form="clientForm">${r ? 'Save Changes' : 'Add Client'}</button>`,
    onOpen: (ov) => {
      ov.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
      buildProgramSelect(ov.querySelector('[data-prog-select="cf"]'), r ? (r.programs || []) : []);
      ov.querySelector('#clientForm').addEventListener('submit', (e) => saveClientForm(e, r));
    },
  });
}

function createResident(d, category) {
  const id = nextClientId();
  const stamp = nowStamp();
  const r = {
    id, first: d.first, middle: d.middle, last: d.last,
    fullName: d.first + ' ' + d.last,
    formalName: `${d.last.toUpperCase()}, ${d.first}${d.middle ? ' ' + d.middle : ''}`,
    initials: (d.first[0] || '') + (d.last[0] || ''),
    avatar: AVATAR_COLORS[RESIDENTS.length % AVATAR_COLORS.length],
    avatarText: AVATAR_TEXT[RESIDENTS.length % AVATAR_TEXT.length],
    category, sex: d.sex, status: d.status,
    birthdate: d.birthdate || birthdateFor(id, CATEGORIES.indexOf(category)),
    civilStatus: d.civilStatus, occupation: d.occupation,
    municipality: d.municipality, barangay: d.barangay,
    mobile: d.mobile || '—', email: d.email || '—',
    address: d.address || `${d.barangay}, ${d.municipality}`,
    household: d.household === 'auto' ? nextHouseholdCode() : d.household,
    programs: d.programs || [],
    notes: 'Registered through the prototype demo.',
    govIds: govIdsFor({ id, category }),
    docs: docsFor({ id, category }),
    audit: { createdBy: 'Jordi Admin', createdAt: stamp, updatedBy: 'Jordi Admin', updatedAt: stamp, lastView: stamp },
  };
  r.timeline = [{ title: 'Client record created', text: 'Registered in the prototype demo by Jordi Admin', time: stamp }];
  return r;
}

function syncClientDerived(r) {
  TRANSACTIONS.forEach(t => { if (t.clientId === r.id) t.clientName = r.fullName; });
  HOUSEHOLDS.forEach(h => { if (h.headId === r.id) { h.head = r.fullName; h.barangay = r.barangay; h.muni = r.municipality; } });
}

function saveClientForm(e, existing) {
  e.preventDefault();
  const d = readForm(e.target);
  const progSel = modalEl ? modalEl.querySelector('[data-prog-select="cf"]') : null;
  d.programs = progSel && progSel._selected ? progSel._selected.slice() : (existing ? (existing.programs || []) : []);
  if (!d.first || !d.last || !d.municipality || !d.barangay) {
    showToast('First name, last name, municipality and barangay are required');
    return;
  }
  const category = CATEGORIES.find(c => c.label === d.category) || CATEGORIES[0];

  if (existing) {
    Object.assign(existing, {
      first: d.first, middle: d.middle, last: d.last,
      fullName: d.first + ' ' + d.last,
      formalName: `${d.last.toUpperCase()}, ${d.first}${d.middle ? ' ' + d.middle : ''}`,
      initials: (d.first[0] || '') + (d.last[0] || ''),
      category, sex: d.sex, birthdate: d.birthdate, civilStatus: d.civilStatus,
      occupation: d.occupation, municipality: d.municipality, barangay: d.barangay,
      mobile: d.mobile || '—', email: d.email || '—',
      address: d.address || `${d.barangay}, ${d.municipality}`,
      status: d.status,
      household: d.household === 'auto' ? existing.household : d.household,
      programs: d.programs || existing.programs || [],
    });
    existing.audit.updatedBy = 'Jordi Admin';
    existing.audit.updatedAt = nowStamp();
    existing.timeline.push({ title: 'Profile updated', text: 'Record edited in the prototype demo', time: nowStamp() });
    syncClientDerived(existing);
    closeModal();
    refreshAllViews();
    prependActivity(`<strong>${esc(existing.formalName)}</strong> updated their client record`);
    showToast(`Client #${existing.id} updated`);
    if (state.openResidentId === existing.id) renderResidentPanel(existing);
    return;
  }

  const r = createResident(d, category);
  RESIDENTS.unshift(r);
  RESIDENT_BY_ID.set(r.id, r);
  if (d.household === 'auto') {
    HOUSEHOLDS.unshift({ code: r.household, head: r.fullName, headId: r.id, members: 1, barangay: r.barangay, muni: r.municipality });
  } else {
    const hh = HOUSEHOLDS.find(h => h.code === d.household);
    if (hh && !hh.headId) { hh.head = r.fullName; hh.headId = r.id; }
  }
  closeModal();
  state.clients.page = 1;
  refreshAllViews();
  prependActivity(`<strong>${esc(r.formalName)}</strong> registered as a new client`);
  showToast(`Client #${r.id} added`);
}

function toggleArchive(id) {
  const r = RESIDENT_BY_ID.get(id);
  if (!r) return;
  r.status = r.status === 'Active' ? 'Archived' : 'Active';
  if (state.openResidentId === id) renderResidentPanel(r);
  renderClients();
  prependActivity(`<strong>${esc(r.formalName)}</strong> was ${r.status === 'Archived' ? 'archived' : 'reactivated'}`);
  showToast(r.status === 'Archived' ? 'Client archived' : 'Client reactivated');
}

function deleteClient(id) {
  const r = RESIDENT_BY_ID.get(id);
  if (!r) return;
  const txCount = TRANSACTIONS.filter(t => t.clientId === id).length;
  const hh = HOUSEHOLDS.find(h => h.headId === id);
  confirmDialog({
    title: 'Delete client?',
    message: `You are about to delete <strong>${esc(r.formalName)}</strong> (ID #${id}). This will also remove ${txCount} related transaction${txCount === 1 ? '' : 's'}${hh ? ' and the household record headed by this client' : ''}. This action cannot be undone in the demo.`,
    confirmLabel: 'Delete Client',
  }).then((ok) => {
    if (!ok) return;
    for (let i = TRANSACTIONS.length - 1; i >= 0; i--) if (TRANSACTIONS[i].clientId === id) TRANSACTIONS.splice(i, 1);
    for (let i = HOUSEHOLDS.length - 1; i >= 0; i--) if (HOUSEHOLDS[i].headId === id) HOUSEHOLDS.splice(i, 1);
    const idx = RESIDENTS.findIndex(x => x.id === id);
    if (idx >= 0) RESIDENTS.splice(idx, 1);
    RESIDENT_BY_ID.delete(id);
    if (state.openResidentId === id) closeResidentPanel(false);
    state.clients.page = 1;
    refreshAllViews();
    prependActivity(`<strong>${esc(r.formalName)}</strong> was deleted from the client registry`);
    showToast(`Client #${id} deleted`);
  });
}

/* ═══════════════════════════════════════════════════════════════
   TRANSACTION CRUD — add / edit / delete
   ═══════════════════════════════════════════════════════════════ */

function openTxForm(no) {
  const tx = no ? TRANSACTIONS.find(t => t.no === no) : null;
  const clientCur = tx ? tx.clientId : (RESIDENTS[0] ? RESIDENTS[0].id : '');

  openModal({
    title: tx ? `Edit Transaction ${tx.no}` : 'New Transaction',
    sub: tx ? 'Update transaction details' : 'Record a new assistance transaction',
    size: 'modal-lg',
    bodyHTML: `
      <form id="txForm">
        <div class="form-grid">
          <div class="form-group span-2">
            <label class="form-label" for="txClient">Client *</label>
            <select class="form-input" id="txClient" name="clientId">
              ${RESIDENTS.map(r => `<option value="${r.id}" ${r.id === clientCur ? 'selected' : ''}>${esc(r.formalName)}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="txProgram">Program</label>
            <select class="form-input" id="txProgram" name="program">${optList(PROGRAMS, tx ? tx.program : 'AICS')}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="txType">Type</label>
            <select class="form-input" id="txType" name="type">${optList(TX_TYPES, tx ? tx.type : TX_TYPES[0])}</select>
          </div>
          <div class="form-group">
            <label class="form-label" for="txDate">Date Applied</label>
            <input type="date" class="form-input" id="txDate" name="date" value="${tx ? esc(tx.date) : '2026-08-06'}">
          </div>
          <div class="form-group">
            <label class="form-label" for="txAmount">Amount (₱)</label>
            <input type="number" class="form-input" id="txAmount" name="amount" min="0" step="100" value="${tx ? tx.amount : 1000}" required>
          </div>
          <div class="form-group span-2">
            <label class="form-label" for="txStatus">Status</label>
            <select class="form-input" id="txStatus" name="status">${optListKV(STATUS_PAIRS, tx ? tx.status : 'pending')}</select>
          </div>
        </div>
      </form>`,
    footerHTML: `
      <button type="button" class="btn btn-outline" data-modal-cancel>Cancel</button>
      <button type="submit" class="btn btn-gold" form="txForm">${tx ? 'Save Changes' : 'Add Transaction'}</button>`,
    onOpen: (ov) => {
      ov.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
      ov.querySelector('#txForm').addEventListener('submit', (e) => saveTxForm(e, tx));
    },
  });
}

function saveTxForm(e, existing) {
  e.preventDefault();
  const d = readForm(e.target);
  const client = RESIDENT_BY_ID.get(Number(d.clientId));
  if (!client) { showToast('Select a valid client'); return; }
  const amount = Number(d.amount);
  if (!amount || amount <= 0) { showToast('Enter a valid amount'); return; }
  const meta = TX_STATUS_META[d.status] || TX_STATUS_META.pending;
  const payload = {
    no: existing ? existing.no : nextTxNo(),
    clientId: client.id, clientName: client.fullName,
    program: d.program, type: d.type, date: d.date || '2026-08-06',
    amount, status: d.status, statusLabel: meta.label, statusCls: meta.cls,
  };
  if (existing) Object.assign(existing, payload);
  else TRANSACTIONS.unshift(payload);
  closeModal();
  state.transactions.page = 1;
  refreshAllViews();
  prependActivity(`<strong>${esc(client.fullName)}</strong> ${existing ? 'updated' : 'applied for'} a ${esc(payload.program)} transaction (${esc(payload.no)})`);
  showToast(`${existing ? 'Transaction' : 'Transaction'} ${payload.no} ${existing ? 'updated' : 'added'}`);
}

function deleteTx(no) {
  const tx = TRANSACTIONS.find(t => t.no === no);
  if (!tx) return;
  confirmDialog({
    title: 'Delete transaction?',
    message: `Delete transaction <strong>${esc(tx.no)}</strong> (${esc(tx.program)} — ${money(tx.amount)}) for <strong>${esc(tx.clientName)}</strong>? This cannot be undone in the demo.`,
    confirmLabel: 'Delete Transaction',
  }).then((ok) => {
    if (!ok) return;
    const i = TRANSACTIONS.findIndex(t => t.no === no);
    if (i >= 0) TRANSACTIONS.splice(i, 1);
    state.transactions.page = 1;
    refreshAllViews();
    prependActivity(`Transaction <strong>${esc(no)}</strong> was deleted`);
    showToast(`Transaction ${no} deleted`);
  });
}

/* ═══════════════════════════════════════════════════════════════
   HOUSEHOLD CRUD — register / edit / delete
   ═══════════════════════════════════════════════════════════════ */

function openHouseholdForm(code) {
  const h = code ? HOUSEHOLDS.find(x => x.code === code) : null;

  openModal({
    title: h ? `Edit Household ${h.code}` : 'Register Household',
    sub: h ? esc(h.head) : 'Create a new household record',
    bodyHTML: `
      <form id="hhForm">
        <div class="form-grid">
          <div class="form-group span-2">
            <label class="form-label" for="hhHead">Head of Household *</label>
            <select class="form-input" id="hhHead" name="headId">
              ${RESIDENTS.map(r => `<option value="${r.id}" ${h && h.headId === r.id ? 'selected' : ''}>${esc(r.formalName)}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="hhMembers">Members</label>
            <input type="number" class="form-input" id="hhMembers" name="members" min="1" max="20" value="${h ? h.members : 1}">
          </div>
          <div class="form-group">
            <label class="form-label" for="hhBarangay">Barangay *</label>
            <input type="text" class="form-input" id="hhBarangay" name="barangay" value="${h ? esc(h.barangay) : ''}" required>
          </div>
          <div class="form-group span-2">
            <label class="form-label" for="hhMuni">Municipality *</label>
            <select class="form-input" id="hhMuni" name="muni">${optList(MUNIS, h ? h.muni : 'Candon City')}</select>
          </div>
        </div>
      </form>`,
    footerHTML: `
      <button type="button" class="btn btn-outline" data-modal-cancel>Cancel</button>
      <button type="submit" class="btn btn-gold" form="hhForm">${h ? 'Save Changes' : 'Register Household'}</button>`,
    onOpen: (ov) => {
      ov.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);
      ov.querySelector('#hhForm').addEventListener('submit', (e) => saveHouseholdForm(e, h));
    },
  });
}

function saveHouseholdForm(e, existing) {
  e.preventDefault();
  const d = readForm(e.target);
  const head = RESIDENT_BY_ID.get(Number(d.headId));
  if (!head) { showToast('Select a valid household head'); return; }
  const payload = {
    code: existing ? existing.code : nextHouseholdCode(),
    head: head.fullName, headId: head.id,
    members: Math.max(1, Number(d.members) || 1),
    barangay: d.barangay, muni: d.muni,
  };
  if (existing) Object.assign(existing, payload);
  else HOUSEHOLDS.unshift(payload);
  closeModal();
  state.households.page = 1;
  refreshAllViews();
  prependActivity(`Household <strong>${esc(payload.code)}</strong> was ${existing ? 'updated' : 'registered'} (head: ${esc(head.fullName)})`);
  showToast(existing ? `Household ${payload.code} updated` : `Household ${payload.code} registered`);
}

function deleteHousehold(code) {
  const h = HOUSEHOLDS.find(x => x.code === code);
  if (!h) return;
  confirmDialog({
    title: 'Delete household?',
    message: `Delete household <strong>${esc(h.code)}</strong> headed by <strong>${esc(h.head)}</strong>? The head's client record will not be affected.`,
    confirmLabel: 'Delete Household',
  }).then((ok) => {
    if (!ok) return;
    const i = HOUSEHOLDS.findIndex(x => x.code === code);
    if (i >= 0) HOUSEHOLDS.splice(i, 1);
    state.households.page = 1;
    refreshAllViews();
    prependActivity(`Household <strong>${esc(code)}</strong> was deleted`);
    showToast(`Household ${code} deleted`);
  });
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR (mobile off-canvas)
   ═══════════════════════════════════════════════════════════════ */

function openSidebar() {
  $('#sidebar').classList.add('open');
  $('#sidebarBackdrop').classList.add('show');
}
function closeSidebar() {
  $('#sidebar').classList.remove('open');
  $('#sidebarBackdrop').classList.remove('show');
}

/* ═══════════════════════════════════════════════════════════════
   SORT INDICATORS
   ═══════════════════════════════════════════════════════════════ */

function updateSortIndicators(table, sortKey, sortDir) {
  $$(`[data-table="${table}"] th[data-sort]`).forEach(th => {
    const ind = th.querySelector('.sort-ind');
    th.classList.remove('sort-asc', 'sort-desc');
    if (th.dataset.sort === sortKey) {
      th.classList.add('sort-' + sortDir);
      ind.textContent = sortDir === 'asc' ? '▲' : '▼';
    } else {
      ind.textContent = '↕';
    }
  });
}

/* ═══════════════════════════════════════════════════════════════
   EVENT DELEGATION (bound once in init)
   ═══════════════════════════════════════════════════════════════ */

function onInit() {
  ['clients', 'transactions', 'households', 'scholars'].forEach(m => initFilterGroup(m));
  renderDashboardRecent();
  renderActivity();
  renderCalendar();
  renderNotifications();
  renderClients();
  renderTransactions();
  renderHouseholds();
  renderScholars();
  renderGipProfiles();
  renderScholarReports();
  renderUpdateLogs();
  updateMetrics();

  /* Login / logout */
  $('#loginForm').addEventListener('submit', (e) => {
    e.preventDefault();
    doLogin();
  });
  $('#logoutBtn').addEventListener('click', doLogout);

  /* Sidebar navigation (delegated) */
  document.addEventListener('click', (e) => {
    const link = e.target.closest('.sidebar-link[data-page]');
    if (link) { e.preventDefault(); showPage(link.dataset.page); }
  });

  /* Filter chips (delegated) — scanner profile mode selector only */
  document.addEventListener('click', (e) => {
    const chip = e.target.closest('.filter-chip[data-value]');
    if (!chip) return;
    const group = chip.closest('[data-filter-group]');
    if (group) group.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');

    if (chip.dataset.filter === 'scanner') {
      showToast(`Scanner profile set to ${chip.dataset.value}`);
    }
  });

  /* Pagination (delegated) */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-pager]');
    if (!btn) return;
    const key = btn.dataset.pager;
    let page = btn.dataset.page;
    const cur = state[key] ? state[key].page : 1;
    if (page === 'prev') page = cur - 1;
    if (page === 'next') page = cur + 1;
    if (key === 'clients') { state.clients.page = Number(page); renderClients(); }
    if (key === 'transactions') { state.transactions.page = Number(page); renderTransactions(); }
    if (key === 'households') { state.households.page = Number(page); renderHouseholds(); }
    if (key === 'scholars') { state.scholars.page = Number(page); renderScholars(); }
  });

  /* Row click / keyboard → details panel (CRUD row buttons are excluded) */
  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-resident-id]');
    if (!row) return;
    if (e.target.closest('.row-action')) return;
    row.focus(); openResidentPanel(row.dataset.residentId);
  });
  document.addEventListener('keydown', (e) => {
    if (!e.target.matches || !e.target.matches('tr[data-resident-id]')) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openResidentPanel(e.target.dataset.residentId);
    }
  });

  /* Panel close (button, backdrop, Escape) */
  document.addEventListener('click', (e) => {
    if (e.target.closest('#detailsClose') || e.target.id === 'detailsBackdrop') closeResidentPanel();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (modalEl) { closeModal(); return; }
      if ($('#detailsPanel').classList.contains('open')) { closeResidentPanel(); return; }
      if ($('.notif-menu').classList.contains('open')) toggleNotifications(false);
    }
  });

  /* Lightweight focus trap inside the open panel */
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab' || !$('#detailsPanel').classList.contains('open')) return;
    const focusables = $$('#detailsPanel button, #detailsPanel [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  /* Lightweight focus trap inside the open modal */
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab' || !modalEl) return;
    const focusables = Array.from(modalEl.querySelectorAll('input, select, textarea, button:not([disabled])'));
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  /* Add buttons (delegated) — Add Client / New Transaction / Register Household */
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-add-client]')) { openClientForm(); return; }
    if (e.target.closest('[data-add-tx]')) { openTxForm(); return; }
    if (e.target.closest('[data-add-hh]')) { openHouseholdForm(); return; }
  });

  /* Client panel actions (delegated) */
  document.addEventListener('click', (e) => {
    const edit = e.target.closest('[data-edit-client]');
    if (edit) { openClientForm(Number(edit.dataset.editClient)); return; }
    const arch = e.target.closest('[data-archive-client]');
    if (arch) { toggleArchive(Number(arch.dataset.archiveClient)); return; }
    const del = e.target.closest('[data-delete-client]');
    if (del) { deleteClient(Number(del.dataset.deleteClient)); return; }
  });

  /* Transaction row actions (delegated) */
  document.addEventListener('click', (e) => {
    const edit = e.target.closest('[data-edit-tx]');
    if (edit) { openTxForm(edit.dataset.editTx); return; }
    const del = e.target.closest('[data-delete-tx]');
    if (del) { deleteTx(del.dataset.deleteTx); return; }
  });

  /* Household row actions (delegated) */
  document.addEventListener('click', (e) => {
    const edit = e.target.closest('[data-edit-hh]');
    if (edit) { openHouseholdForm(edit.dataset.editHh); return; }
    const del = e.target.closest('[data-delete-hh]');
    if (del) { deleteHousehold(del.dataset.deleteHh); return; }
  });

  /* P6 — scholar rows, tabs, QR, edit / add / export (delegated) */
  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-scholar-id]');
    if (row) {
      if (e.target.closest('.row-action')) return;
      row.focus(); openScholarPanel(row.dataset.scholarId);
      return;
    }
    const tab = e.target.closest('[data-scholar-tab]');
    if (tab) { switchScholarTab(tab.dataset.scholarTab); return; }
    const qr = e.target.closest('[data-qr-scholar]');
    if (qr) { openQrModal(qr.dataset.qrScholar); return; }
    const edit = e.target.closest('[data-edit-scholar]');
    if (edit) { openScholarForm(edit.dataset.editScholar); return; }
    if (e.target.closest('[data-add-scholar]')) { openScholarForm(); return; }
    if (e.target.closest('[data-scholar-export]')) { exportScholarCsv(); return; }
  });
  document.addEventListener('keydown', (e) => {
    if (!e.target.matches || !e.target.matches('tr[data-scholar-id]')) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openScholarPanel(e.target.dataset.scholarId);
    }
  });

  /* P6 — program chip picker (delegated add / remove) */
  document.addEventListener('click', (e) => {
    const addBtn = e.target.closest('[data-prog-add]');
    if (addBtn) {
      const host = addBtn.closest('[data-prog-select]');
      if (!host || host._selected.includes(addBtn.dataset.progAdd)) return;
      host._selected.push(addBtn.dataset.progAdd);
      buildProgramSelect(host, host._selected);
      return;
    }
    const xBtn = e.target.closest('[data-prog-remove]');
    if (xBtn) {
      const host = xBtn.closest('[data-prog-select]');
      if (!host) return;
      host._selected = host._selected.filter(p => p !== xBtn.dataset.progRemove);
      buildProgramSelect(host, host._selected);
    }
  });

  /* P6 — program picker search (delegated input, keeps focus) */
  document.addEventListener('input', (e) => {
    if (!e.target.classList || !e.target.classList.contains('prog-search')) return;
    const host = e.target.closest('[data-prog-select]');
    if (!host) return;
    host._query = e.target.value;
    renderProgGroups(host, e.target.value);
  });

  /* P6 — grantee self-update form */
  const selfProgHost = $('[data-prog-select="self"]');
  if (selfProgHost) buildProgramSelect(selfProgHost, []);
  const granteeForm = $('#granteeForm');
  if (granteeForm) granteeForm.addEventListener('submit', submitGranteeUpdate);

  /* Simulation buttons (delegated) */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-sim]');
    if (btn) showToast(btn.dataset.sim);
  });

  /* Client search */
  $('#clientsSearch').addEventListener('input', (e) => {
    state.clients.search = e.target.value.trim();
    state.clients.page = 1;
    renderClients();
  });

  /* Global topbar search → filters client registry */
  const globalSearch = $('#globalSearch');
  globalSearch.addEventListener('input', (e) => {
    if (state.page === 'clients') {
      state.clients.search = e.target.value.trim();
      state.clients.page = 1;
      renderClients();
    }
  });
  globalSearch.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      showPage('clients');
      state.clients.search = globalSearch.value.trim();
      state.clients.page = 1;
      renderClients();
      globalSearch.blur();
    }
  });

  /* Household search */
  $('#householdsSearch').addEventListener('input', (e) => {
    state.households.search = e.target.value.trim();
    state.households.page = 1;
    renderHouseholds();
  });

  /* Transactions search */
  const transactionsSearch = $('#transactionsSearch');
  if (transactionsSearch) transactionsSearch.addEventListener('input', (e) => {
    state.transactions.search = e.target.value.trim();
    state.transactions.page = 1;
    renderTransactions();
  });

  /* Scholars search */
  const scholarsSearch = $('#scholarsSearch');
  if (scholarsSearch) scholarsSearch.addEventListener('input', (e) => {
    state.scholars.search = e.target.value.trim();
    state.scholars.page = 1;
    renderScholars();
  });

  /* Sortable headers */
  document.addEventListener('click', (e) => {
    const th = e.target.closest('th[data-sort]');
    if (!th) return;
    const table = th.closest('[data-table]').dataset.table;
    if (table === 'clients') toggleClientSort(th.dataset.sort);
    if (table === 'transactions') toggleTransactionSort(th.dataset.sort);
  });

  /* Notifications dropdown */
  $('#notifBtn').addEventListener('click', (e) => {
    e.stopPropagation();
    toggleNotifications(!$('.notif-menu').classList.contains('open'));
  });
  $('#notifMarkAll').addEventListener('click', () => {
    NOTIFICATIONS.forEach(n => n.unread = false);
    renderNotifications();
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.notif-menu') && !e.target.closest('#notifBtn')) toggleNotifications(false);
  });
  $('#notifList').addEventListener('click', (e) => {
    const item = e.target.closest('.notif-item');
    if (!item) return;
    NOTIFICATIONS[Number(item.dataset.notifIndex)].unread = false;
    renderNotifications();
    showToast('Notification marked as read');
  });

  /* Sidebar hamburger + backdrop */
  $('#sidebarToggle').addEventListener('click', () => {
    $('#sidebar').classList.contains('open') ? closeSidebar() : openSidebar();
  });
  $('#sidebarBackdrop').addEventListener('click', closeSidebar);

  /* Calendar nav */
  $('#calPrev').addEventListener('click', () => {
    state.calendar.month--;
    if (state.calendar.month < 0) { state.calendar.month = 11; state.calendar.year--; }
    renderCalendar();
  });
  $('#calNext').addEventListener('click', () => {
    state.calendar.month++;
    if (state.calendar.month > 11) { state.calendar.month = 0; state.calendar.year++; }
    renderCalendar();
  });

  /* Scanner simulation */
  $('#scanSimulate').addEventListener('click', simulateScan);
}

function toggleNotifications(open) {
  $('.notif-menu').classList.toggle('open', open);
  $('#notifBtn').setAttribute('aria-expanded', String(open));
}

function doLogin() {
  $('#loginPage').classList.remove('active');
  $('#appShell').classList.add('active');
  $('#globalSearch').focus();
}

function doLogout() {
  $('#appShell').classList.remove('active');
  $('#loginPage').classList.add('active');
  closeResidentPanel(false);
  closeSidebar();
}

document.addEventListener('DOMContentLoaded', onInit);
