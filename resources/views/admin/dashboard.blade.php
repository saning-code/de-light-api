<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>De-Light — Admin Panel</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#F1F5F9;color:#0F172A;display:flex;min-height:100vh}
/* ── Sidebar ── */
.sidebar{width:240px;background:#0F172A;min-height:100vh;position:fixed;top:0;left:0;display:flex;flex-direction:column}
.sb-logo{padding:24px 20px;border-bottom:1px solid #1E293B}
.sb-logo h2{color:white;font-size:16px;font-weight:800}
.sb-logo span{color:#64748B;font-size:11px}
.sb-badge{background:#EF4444;color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:10px;margin-left:6px}
.sb-nav{padding:16px 0;flex:1}
.sb-item{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#94A3B8;cursor:pointer;font-size:13px;font-weight:600;transition:all 0.15s;border-left:3px solid transparent}
.sb-item:hover{color:white;background:#1E293B}
.sb-item.active{color:white;background:#1E3A8A;border-left-color:#3B82F6}
.sb-item .icon{font-size:16px;width:20px;text-align:center}
.sb-footer{padding:16px 20px;border-top:1px solid #1E293B}
.sb-admin{color:#64748B;font-size:12px}
.sb-admin strong{color:white;display:block;font-size:13px}
.sb-logout{color:#EF4444;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;display:block;margin-top:8px}
/* ── Main ── */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column}
.topbar{background:white;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #E2E8F0;position:sticky;top:0;z-index:10}
.topbar h1{font-size:18px;font-weight:700;color:#0F172A}
.topbar-right{display:flex;align-items:center;gap:12px}
.badge-online{background:#DCFCE7;color:#16A34A;padding:5px 10px;border-radius:20px;font-size:11px;font-weight:700}
.content{padding:24px 28px;flex:1}
/* ── KPI Cards ── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.kpi{background:white;border-radius:16px;padding:20px;border-left:4px solid #3B82F6;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.kpi.green{border-left-color:#10B981}
.kpi.amber{border-left-color:#F59E0B}
.kpi.red{border-left-color:#EF4444}
.kpi.purple{border-left-color:#8B5CF6}
.kpi-label{font-size:11px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px}
.kpi-value{font-size:26px;font-weight:800;color:#0F172A}
.kpi-sub{font-size:11px;color:#94A3B8;margin-top:4px}
/* ── Section ── */
.section{background:white;border-radius:16px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.section-title{font-size:15px;font-weight:700}
/* ── Table ── */
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th{text-align:left;padding:10px 12px;background:#F8FAFC;color:#64748B;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #E2E8F0}
.tbl td{padding:12px;border-bottom:1px solid #F1F5F9;vertical-align:middle}
.tbl tr:hover td{background:#F8FAFC}
/* ── Badges ── */
.tag{display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700}
.tag-green{background:#DCFCE7;color:#16A34A}
.tag-amber{background:#FEF3C7;color:#D97706}
.tag-red{background:#FEE2E2;color:#DC2626}
.tag-blue{background:#DBEAFE;color:#2563EB}
.tag-gray{background:#F1F5F9;color:#64748B}
/* ── Buttons ── */
.btn{padding:7px 14px;border-radius:8px;border:none;font-size:12px;font-weight:600;cursor:pointer;transition:opacity 0.15s}
.btn:hover{opacity:0.85}
.btn-blue{background:#3B82F6;color:white}
.btn-green{background:#10B981;color:white}
.btn-red{background:#EF4444;color:white}
.btn-amber{background:#F59E0B;color:white}
.btn-ghost{background:#F1F5F9;color:#374151}
.btn-sm{padding:5px 10px;font-size:11px}
/* ── Search ── */
.search-bar{display:flex;gap:10px;margin-bottom:16px}
.search-bar input{flex:1;padding:10px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13px;outline:none}
.search-bar input:focus{border-color:#3B82F6}
.search-bar select{padding:10px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13px;outline:none;background:white}
/* ── Pages ── */
.page{display:none}
.page.active{display:block}
/* ── Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:20px;padding:28px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto}
.modal-title{font-size:18px;font-weight:700;margin-bottom:20px}
.modal-field{margin-bottom:14px}
.modal-field label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
.modal-field input,.modal-field select,.modal-field textarea{width:100%;padding:10px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13px;outline:none}
.modal-field input:focus{border-color:#3B82F6}
.modal-actions{display:flex;gap:10px;margin-top:20px;justify-content:flex-end}
/* ── Loading ── */
.loading{text-align:center;padding:40px;color:#94A3B8}
.spinner{display:inline-block;width:24px;height:24px;border:3px solid #E2E8F0;border-top-color:#3B82F6;border-radius:50%;animation:spin 0.8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
/* ── Alert ── */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px;display:none}
.alert.success{background:#DCFCE7;color:#16A34A;display:block}
.alert.error{background:#FEE2E2;color:#DC2626;display:block}
/* ── Responsive ── */
@media(max-width:768px){.sidebar{display:none}.main{margin-left:0}}
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar">
  <div class="sb-logo">
    <h2>🏪 De-Light <span class="sb-badge">ADMIN</span></h2>
    <span>SaaS Control Panel</span>
  </div>
  <nav class="sb-nav">
    <div class="sb-item active" onclick="showPage('overview')"><span class="icon">📊</span> Overview</div>
    <div class="sb-item" onclick="showPage('businesses')"><span class="icon">🏢</span> Businesses</div>
    <div class="sb-item" onclick="showPage('plans')"><span class="icon">💳</span> Subscription Plans</div>
    <div class="sb-item" onclick="showPage('signups')"><span class="icon">🆕</span> Recent Signups</div>
  </nav>
  <div class="sb-footer">
    <div class="sb-admin">
      <strong id="admin-name">{{ $adminName }}</strong>
      Super Admin
    </div>
    <a href="#" onclick="doLogout()" class="sb-logout">⬅ Sign Out</a>
  </div>
</div>

<!-- ── Main Content ── -->
<div class="main">
  <div class="topbar">
    <h1 id="page-title">Overview</h1>
    <div class="topbar-right">
      <span class="badge-online">🟢 Platform Online</span>
      <span style="font-size:13px;color:#64748B">{{ now()->format('D, M j Y') }}</span>
    </div>
  </div>

  <div class="content">
    <div id="alert-box" class="alert"></div>

    <!-- ══ PAGE: OVERVIEW ══ -->
    <div id="page-overview" class="page active">
      <div class="kpi-grid" id="kpi-grid">
        <div class="loading"><div class="spinner"></div></div>
      </div>
      <div class="section">
        <div class="section-header">
          <span class="section-title">📈 Platform Activity — Last 30 Days</span>
        </div>
        <canvas id="chart-signups" height="80"></canvas>
      </div>
      <div class="section">
        <div class="section-header">
          <span class="section-title">🆕 Recent Business Signups</span>
          <button class="btn btn-blue btn-sm" onclick="showPage('signups')">View All</button>
        </div>
        <div id="recent-signups-table"><div class="loading"><div class="spinner"></div></div></div>
      </div>
    </div>

    <!-- ══ PAGE: BUSINESSES ══ -->
    <div id="page-businesses" class="page">
      <div class="search-bar">
        <input type="text" id="biz-search" placeholder="🔍 Search by name, email or business code…" oninput="searchBusinesses()">
        <select id="biz-filter" onchange="searchBusinesses()">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="trial">Trial</option>
          <option value="suspended">Suspended</option>
          <option value="expired">Expired</option>
        </select>
        <button class="btn btn-ghost btn-sm" onclick="loadBusinesses()">🔄 Refresh</button>
      </div>
      <div class="section">
        <div id="businesses-table"><div class="loading"><div class="spinner"></div></div></div>
        <div id="biz-pagination" style="display:flex;gap:8px;margin-top:16px;justify-content:center"></div>
      </div>
    </div>

    <!-- ══ PAGE: PLANS ══ -->
    <div id="page-plans" class="page">
      <div class="section">
        <div class="section-header">
          <span class="section-title">💳 Subscription Plans</span>
        </div>
        <div id="plans-table"><div class="loading"><div class="spinner"></div></div></div>
      </div>
    </div>

    <!-- ══ PAGE: RECENT SIGNUPS ══ -->
    <div id="page-signups" class="page">
      <div class="section">
        <div class="section-header">
          <span class="section-title">🆕 Recent Business Signups</span>
          <button class="btn btn-ghost btn-sm" onclick="loadRecentSignups()">🔄 Refresh</button>
        </div>
        <div id="all-signups-table"><div class="loading"><div class="spinner"></div></div></div>
      </div>
    </div>
  </div><!-- /content -->
</div><!-- /main -->

<!-- ══ BUSINESS DETAIL MODAL ══ -->
<div class="modal-overlay" id="modal-biz">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 class="modal-title" style="margin-bottom:0" id="modal-biz-name">Business Details</h2>
      <span style="cursor:pointer;font-size:20px;color:#94A3B8" onclick="closeModal('modal-biz')">✕</span>
    </div>
    <div id="modal-biz-content"><div class="loading"><div class="spinner"></div></div></div>
  </div>
</div>

<!-- ══ EXTEND TRIAL MODAL ══ -->
<div class="modal-overlay" id="modal-trial">
  <div class="modal" style="max-width:360px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 class="modal-title" style="margin-bottom:0">Extend Trial</h2>
      <span style="cursor:pointer;font-size:20px;color:#94A3B8" onclick="closeModal('modal-trial')">✕</span>
    </div>
    <p style="font-size:13px;color:#64748B;margin-bottom:16px" id="modal-trial-biz-name"></p>
    <div class="modal-field">
      <label>Extra Days to Add</label>
      <select id="trial-days">
        <option value="7">7 days</option>
        <option value="14" selected>14 days</option>
        <option value="30">30 days</option>
        <option value="60">60 days</option>
        <option value="90">90 days</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-trial')">Cancel</button>
      <button class="btn btn-amber" onclick="confirmExtendTrial()">Extend Trial</button>
    </div>
  </div>
</div>

<!-- ══ EDIT PLAN MODAL ══ -->
<div class="modal-overlay" id="modal-plan">
  <div class="modal" style="max-width:400px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 class="modal-title" style="margin-bottom:0">Edit Plan</h2>
      <span style="cursor:pointer;font-size:20px;color:#94A3B8" onclick="closeModal('modal-plan')">✕</span>
    </div>
    <input type="hidden" id="edit-plan-id">
    <div class="modal-field"><label>Plan Name</label><input type="text" id="edit-plan-name"></div>
    <div class="modal-field"><label>Monthly Price (GH₵)</label><input type="number" id="edit-plan-price" step="0.01"></div>
    <div class="modal-field"><label>Max Users</label><input type="number" id="edit-plan-users"></div>
    <div class="modal-field"><label>Max Products</label><input type="number" id="edit-plan-products"></div>
    <div class="modal-field"><label>Trial Days</label><input type="number" id="edit-plan-trial"></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-plan')">Cancel</button>
      <button class="btn btn-blue" onclick="savePlan()">Save Changes</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Redirect to login if no token
if (!localStorage.getItem('admin_token')) { window.location.href = '/admin/login'; }

// Show admin name from localStorage
const savedName = localStorage.getItem('admin_name');
if (savedName) {
  const el = document.getElementById('admin-name');
  if (el) el.textContent = savedName;
}

function doLogout() {
  localStorage.removeItem('admin_token');
  localStorage.removeItem('admin_name');
  window.location.href = '/admin/login';
}
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const BASE = '/admin/api';
let currentBizPage = 1, activeTenantId = null, chartInstance = null;

// ── Helpers ──────────────────────────────────────────────────────────────────
function getToken() { return localStorage.getItem('admin_token') || ''; }

function req(url, opts={}) {
  return fetch(url, {
    headers: {
      'Authorization': 'Bearer ' + getToken(),
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...opts.headers
    },
    ...opts
  }).then(r => {
    if (r.status === 401) { window.location.href = '/admin/login'; }
    return r.json();
  });
}

function showAlert(msg, type='success') {
  const el = document.getElementById('alert-box');
  el.className = 'alert ' + type;
  el.textContent = msg;
  setTimeout(() => { el.className = 'alert'; el.textContent = ''; }, 4000);
}

function fmtCurrency(v) { return 'GH₵ ' + parseFloat(v||0).toLocaleString('en-GH', {minimumFractionDigits:2}); }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '—'; }

function statusTag(s) {
  const map = { active:'tag-green', trial:'tag-blue', suspended:'tag-red', expired:'tag-amber' };
  return `<span class="tag ${map[s]||'tag-gray'}">${(s||'').toUpperCase()}</span>`;
}

function showPage(name) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.sb-item').forEach(i => i.classList.remove('active'));
  document.getElementById('page-' + name).classList.add('active');
  document.querySelectorAll('.sb-item').forEach(i => { if(i.textContent.trim().toLowerCase().includes(name.substring(0,4))) i.classList.add('active'); });
  const titles = { overview:'Overview', businesses:'Businesses', plans:'Subscription Plans', signups:'Recent Signups' };
  document.getElementById('page-title').textContent = titles[name] || name;

  if(name === 'overview')    { loadOverview(); }
  if(name === 'businesses')  { loadBusinesses(); }
  if(name === 'plans')       { loadPlans(); }
  if(name === 'signups')     { loadRecentSignups(true); }
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Overview ─────────────────────────────────────────────────────────────────
async function loadOverview() {
  const [stats, chart] = await Promise.all([req(BASE+'/stats'), req(BASE+'/chart-data')]);
  renderKpis(stats);
  renderChart(chart);
  loadRecentSignups(false);
}

function renderKpis(s) {
  document.getElementById('kpi-grid').innerHTML = `
    <div class="kpi"><div class="kpi-label">Total Businesses</div><div class="kpi-value">${s.total_tenants}</div><div class="kpi-sub">${s.new_this_month} new this month</div></div>
    <div class="kpi green"><div class="kpi-label">Active</div><div class="kpi-value">${s.active_tenants}</div><div class="kpi-sub">Paid subscriptions</div></div>
    <div class="kpi amber"><div class="kpi-label">On Trial</div><div class="kpi-value">${s.trial_tenants}</div><div class="kpi-sub">${s.trial_expiring_soon} expiring soon</div></div>
    <div class="kpi red"><div class="kpi-label">Suspended</div><div class="kpi-value">${s.suspended_tenants}</div><div class="kpi-sub">Needs attention</div></div>
    <div class="kpi purple"><div class="kpi-label">Total Users</div><div class="kpi-value">${s.total_users}</div><div class="kpi-sub">Across all shops</div></div>
    <div class="kpi"><div class="kpi-label">Total Sales</div><div class="kpi-value">${s.total_sales.toLocaleString()}</div><div class="kpi-sub">All transactions</div></div>
    <div class="kpi green"><div class="kpi-label">Platform GMV</div><div class="kpi-value">${fmtCurrency(s.total_revenue)}</div><div class="kpi-sub">Gross merchandise value</div></div>
    <div class="kpi"><div class="kpi-label">Total Products</div><div class="kpi-value">${s.total_products.toLocaleString()}</div><div class="kpi-sub">Across all shops</div></div>
  `;
}

function renderChart(data) {
  const ctx = document.getElementById('chart-signups').getContext('2d');
  if(chartInstance) chartInstance.destroy();
  const labels = [...new Set([...data.signups.map(d=>d.date), ...data.revenue.map(d=>d.date)])].sort();
  const signupMap = Object.fromEntries(data.signups.map(d=>[d.date, d.count]));
  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'New Signups',
        data: labels.map(l => signupMap[l] || 0),
        backgroundColor: 'rgba(59,130,246,0.7)',
        borderRadius: 6,
        yAxisID: 'y'
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });
}
</script>

<script>
// ── Businesses ────────────────────────────────────────────────────────────────
async function loadBusinesses(page=1) {
  currentBizPage = page;
  const search = document.getElementById('biz-search')?.value || '';
  const status = document.getElementById('biz-filter')?.value || '';
  const data = await req(`${BASE}/tenants?page=${page}&search=${encodeURIComponent(search)}&status=${status}`);
  renderBusinessesTable(data);
}

function searchBusinesses() { loadBusinesses(1); }

function renderBusinessesTable(data) {
  const rows = data.data.map(t => `
    <tr>
      <td><strong>${t.business_name}</strong><br><span style="font-size:11px;color:#94A3B8">${t.business_code||''}</span></td>
      <td>${t.owner_name}<br><span style="font-size:11px;color:#94A3B8">${t.owner_email}</span></td>
      <td>${statusTag(t.status)}</td>
      <td>${t.subscription_plan?.name || '—'}</td>
      <td>${t.users_count || 0}</td>
      <td>${t.trial_ends_at ? fmtDate(t.trial_ends_at) : '—'}</td>
      <td>${fmtDate(t.created_at)}</td>
      <td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="viewBusiness(${t.id})">👁 View</button>
        ${t.status==='suspended'
          ? `<button class="btn btn-green btn-sm" onclick="activateBusiness(${t.id},'${t.business_name}')">✓ Activate</button>`
          : `<button class="btn btn-red btn-sm" onclick="suspendBusiness(${t.id},'${t.business_name}')">⊘ Suspend</button>`}
        <button class="btn btn-amber btn-sm" onclick="openExtendTrial(${t.id},'${t.business_name}')">＋ Trial</button>
      </td>
    </tr>`).join('');

  document.getElementById('businesses-table').innerHTML = `
    <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr>
        <th>Business</th><th>Owner</th><th>Status</th><th>Plan</th>
        <th>Users</th><th>Trial Ends</th><th>Joined</th><th>Actions</th>
      </tr></thead>
      <tbody>${rows || '<tr><td colspan="8" style="text-align:center;color:#94A3B8;padding:40px">No businesses found</td></tr>'}</tbody>
    </table></div>`;

  // Pagination
  const pg = document.getElementById('biz-pagination');
  pg.innerHTML = '';
  for(let i=1; i<=data.meta.last_page; i++) {
    const btn = document.createElement('button');
    btn.className = `btn btn-sm ${i===currentBizPage?'btn-blue':'btn-ghost'}`;
    btn.textContent = i;
    btn.onclick = () => loadBusinesses(i);
    pg.appendChild(btn);
  }
}

async function viewBusiness(id) {
  openModal('modal-biz');
  document.getElementById('modal-biz-content').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
  activeTenantId = id;
  const d = await req(`${BASE}/tenants/${id}`);
  const t = d.tenant;
  document.getElementById('modal-biz-name').textContent = t.business_name;
  document.getElementById('modal-biz-content').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
      ${detailRow('Business Code', t.business_code)}
      ${detailRow('Type', t.business_type)}
      ${detailRow('Owner', t.owner_name)}
      ${detailRow('Email', t.owner_email)}
      ${detailRow('Phone', t.owner_phone)}
      ${detailRow('City', t.city)}
      ${detailRow('Status', statusTag(t.status))}
      ${detailRow('Plan', t.subscription_plan?.name || '—')}
      ${detailRow('Trial Ends', fmtDate(t.trial_ends_at))}
      ${detailRow('Joined', fmtDate(t.created_at))}
      ${detailRow('Total Shops', t.shops_count || 0)}
      ${detailRow('Total Users', t.users_count || 0)}
    </div>
    <hr style="border:none;border-top:1px solid #E2E8F0;margin:16px 0">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px">
      <div style="background:#F0FDF4;border-radius:12px;padding:14px;text-align:center">
        <div style="font-size:20px;font-weight:800;color:#16A34A">${fmtCurrency(d.sales_total)}</div>
        <div style="font-size:11px;color:#64748B;margin-top:4px">Total Revenue</div>
      </div>
      <div style="background:#EFF6FF;border-radius:12px;padding:14px;text-align:center">
        <div style="font-size:20px;font-weight:800;color:#2563EB">${d.sales_count}</div>
        <div style="font-size:11px;color:#64748B;margin-top:4px">Total Sales</div>
      </div>
      <div style="background:#F5F3FF;border-radius:12px;padding:14px;text-align:center">
        <div style="font-size:20px;font-weight:800;color:#7C3AED">${d.products_count}</div>
        <div style="font-size:11px;color:#64748B;margin-top:4px">Products</div>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      ${t.status==='suspended'
        ? `<button class="btn btn-green" onclick="activateBusiness(${t.id},'${t.business_name}');closeModal('modal-biz')">✓ Activate Account</button>`
        : `<button class="btn btn-red" onclick="suspendBusiness(${t.id},'${t.business_name}');closeModal('modal-biz')">⊘ Suspend Account</button>`}
      <button class="btn btn-amber" onclick="closeModal('modal-biz');openExtendTrial(${t.id},'${t.business_name}')">＋ Extend Trial</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-biz')">Close</button>
    </div>`;
}

function detailRow(label, value) {
  return `<div><div style="font-size:10px;font-weight:600;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">${label}</div>
    <div style="font-size:13px;font-weight:600">${value||'—'}</div></div>`;
}

async function suspendBusiness(id, name) {
  if(!confirm(`Suspend "${name}"? Their staff will be locked out immediately.`)) return;
  const r = await req(`${BASE}/tenants/${id}/suspend`, { method:'POST' });
  showAlert(r.message, r.success ? 'success' : 'error');
  loadBusinesses(currentBizPage);
}

async function activateBusiness(id, name) {
  if(!confirm(`Activate "${name}"?`)) return;
  const r = await req(`${BASE}/tenants/${id}/activate`, { method:'POST' });
  showAlert(r.message, r.success ? 'success' : 'error');
  loadBusinesses(currentBizPage);
}

function openExtendTrial(id, name) {
  activeTenantId = id;
  document.getElementById('modal-trial-biz-name').textContent = `Business: ${name}`;
  openModal('modal-trial');
}

async function confirmExtendTrial() {
  const days = document.getElementById('trial-days').value;
  const r = await req(`${BASE}/tenants/${activeTenantId}/extend-trial`, { method:'POST', body: JSON.stringify({days}) });
  closeModal('modal-trial');
  showAlert(r.message, r.success ? 'success' : 'error');
  loadBusinesses(currentBizPage);
}

// ── Plans ─────────────────────────────────────────────────────────────────────
async function loadPlans() {
  const data = await req(BASE+'/plans');
  const rows = data.data.map(p => `
    <tr>
      <td><strong>${p.name}</strong></td>
      <td>${fmtCurrency(p.price)}/mo</td>
      <td>${p.max_users}</td>
      <td>${p.max_products?.toLocaleString()}</td>
      <td>${p.trial_days} days</td>
      <td>${p.tenants_count || 0}</td>
      <td><button class="btn btn-blue btn-sm" onclick='openEditPlan(${JSON.stringify(p)})'>✏ Edit</button></td>
    </tr>`).join('');

  document.getElementById('plans-table').innerHTML = `
    <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr><th>Plan</th><th>Price</th><th>Max Users</th><th>Max Products</th><th>Trial</th><th>Businesses</th><th>Actions</th></tr></thead>
      <tbody>${rows}</tbody>
    </table></div>`;
}

function openEditPlan(p) {
  document.getElementById('edit-plan-id').value      = p.id;
  document.getElementById('edit-plan-name').value    = p.name;
  document.getElementById('edit-plan-price').value   = p.price;
  document.getElementById('edit-plan-users').value   = p.max_users;
  document.getElementById('edit-plan-products').value= p.max_products;
  document.getElementById('edit-plan-trial').value   = p.trial_days;
  openModal('modal-plan');
}

async function savePlan() {
  const id = document.getElementById('edit-plan-id').value;
  const payload = {
    name: document.getElementById('edit-plan-name').value,
    price: document.getElementById('edit-plan-price').value,
    max_users: document.getElementById('edit-plan-users').value,
    max_products: document.getElementById('edit-plan-products').value,
    trial_days: document.getElementById('edit-plan-trial').value,
  };
  const r = await req(`${BASE}/plans/${id}`, { method:'PUT', body: JSON.stringify(payload) });
  closeModal('modal-plan');
  showAlert(r.message, r.success ? 'success' : 'error');
  loadPlans();
}

// ── Recent Signups ────────────────────────────────────────────────────────────
async function loadRecentSignups(full=true) {
  const data = await req(BASE+'/recent-signups');
  const rows = data.data.map(t => `
    <tr>
      <td><strong>${t.business_name}</strong><br><span style="font-size:11px;color:#94A3B8">${t.owner_email}</span></td>
      <td>${t.city||'—'}</td>
      <td>${statusTag(t.status)}</td>
      <td>${t.subscription_plan?.name||'—'}</td>
      <td>${fmtDate(t.created_at)}</td>
      <td><button class="btn btn-ghost btn-sm" onclick="viewBusiness(${t.id})">👁 View</button></td>
    </tr>`).join('');

  const html = `
    <div style="overflow-x:auto">
    <table class="tbl">
      <thead><tr><th>Business</th><th>City</th><th>Status</th><th>Plan</th><th>Joined</th><th></th></tr></thead>
      <tbody>${rows}</tbody>
    </table></div>`;

  if(full) document.getElementById('all-signups-table').innerHTML = html;
  else document.getElementById('recent-signups-table').innerHTML = html;
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => loadOverview());
</script>
</body>
</html>
