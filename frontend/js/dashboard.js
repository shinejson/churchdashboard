/* ChurchDashboard - Professional Dashboard JS v2
   Compact, robust, handles missing APIs gracefully
*/
(function(){
'use strict';

const API_BASE = 'backend';

// --- Date/time ---
function updateCurrentDateTime(){
  const el = document.getElementById('current-date');
  if(!el) return;
  const now = new Date();
  const opts = { weekday:'short', month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' };
  el.textContent = now.toLocaleDateString('en-US', opts);
  el.title = now.toString();
}
updateCurrentDateTime();
setInterval(updateCurrentDateTime, 60_000);

// --- Helpers ---
function escapeHtml(s){
  if(s==null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function fmtMoney(n){
  const v = parseFloat(n||0);
  return `$${v.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}`;
}
function getTodayDate(){ return new Date().toISOString().split('T')[0]; }
function getLastMonthDate(){ const d=new Date(); d.setMonth(d.getMonth()-1); return d.toISOString().split('T')[0]; }
function getDateWeeksAgo(w){ const d=new Date(); d.setDate(d.getDate()-w*7); return d.toISOString().split('T')[0]; }
function safeFetch(url, opts={}){
  return fetch(url, opts).then(r=>{
    if(!r.ok) throw new Error(`HTTP ${r.status} for ${url}`);
    return r.json();
  });
}

// --- Main loader (exposed globally for refresh) ---
async function loadDashboardData(){
  const setText = (id, val) => { const el=document.getElementById(id); if(el) el.textContent = val; };

  // skeletons: set loading state
  setText('total-members','…'); setText('active-members','…'); setText('weekly-attendance','…');
  setText('active-fellowships','…'); setText('total-fellowships','…');
  setText('current-tithe-total','…'); setText('current-period-name','Loading…');

  let memberList = [];
  let fellowshipList = [];
  let activeFellowships = [];
  let attendanceData = null;
  let currentPeriod = null;

  // 1. Members
  try{
    const j = await safeFetch(`${API_BASE}/members/read.php`);
    memberList = Array.isArray(j) ? j : (j.data || j.members || []);
    if(!Array.isArray(memberList)) memberList = [];
    setText('total-members', memberList.length);
    const active = memberList.filter(m=> (m.status||'').toLowerCase()==='active').length;
    setText('active-members', active);
    // update subtitle count
    const sub = document.getElementById('stat-members-sub');
    if(sub) sub.textContent = memberList.length ? `${active} of ${memberList.length} active` : 'All fellowships';
  }catch(e){
    console.warn('members load failed', e);
    setText('total-members','0'); setText('active-members','0');
  }

  // 2. Fellowships
  try{
    const j = await safeFetch(`${API_BASE}/fellowships/read.php`);
    fellowshipList = Array.isArray(j) ? j : (j.fellowships || j.data || []);
    if(!Array.isArray(fellowshipList)) fellowshipList = [];
    activeFellowships = fellowshipList.filter(f=> (f.status||'').toLowerCase()==='active');
    setText('total-fellowships', fellowshipList.length);
    setText('active-fellowships', activeFellowships.length);
    const fc = document.getElementById('fellowship-count');
    if(fc) fc.textContent = `${activeFellowships.length} active`;
  }catch(e){
    console.warn('fellowships load failed', e);
  }

  // 3. Weekly attendance
  try{
    const j = await safeFetch(`${API_BASE}/attendance/report.php?type=overview&start_date=${getLastMonthDate()}&end_date=${getTodayDate()}`);
    // API may return {data:{records:[]}} or {records:[]}
    attendanceData = j.data || j;
    let count = 0;
    if(attendanceData && Array.isArray(attendanceData.records)){
      const now = new Date();
      const startOfWeek = new Date(now);
      startOfWeek.setDate(now.getDate() - now.getDay());
      startOfWeek.setHours(0,0,0,0);
      const thisWeek = attendanceData.records.filter(r=>{
        const d = new Date(r.date || r.service_date || r.created_at);
        return !isNaN(d) && d >= startOfWeek;
      });
      count = thisWeek.reduce((s,r)=> s + parseInt(r.present || r.attendance_count || 0,10), 0);
      // fallback: if no records this week, show last record present
      if(count===0 && attendanceData.records.length){
        count = parseInt(attendanceData.records[0].present || 0,10);
      }
    }
    setText('weekly-attendance', count);
    const fc = document.getElementById('recent-count');
    if(fc && attendanceData?.records) fc.textContent = `${attendanceData.records.length} records`;
  }catch(e){
    console.warn('attendance overview failed', e);
    setText('weekly-attendance','0');
  }

  // 4. Tithe period & total
  try{
    const pj = await safeFetch(`${API_BASE}/tithe_periods/read.php`);
    const periods = pj.periods || pj.data || (Array.isArray(pj)? pj: []);
    currentPeriod = (Array.isArray(periods)? periods: []).find(p=> String(p.is_active)==='1' || p.is_active===1 || p.status==='active') || null;
    if(currentPeriod){
      const label = `${currentPeriod.period_name} • ${currentPeriod.start_date} → ${currentPeriod.end_date}`;
      setText('current-period-name', label);
      try{
        const tj = await safeFetch(`${API_BASE}/tithe_transactions/read.php?period_id=${currentPeriod.id}`);
        const tithes = tj.data || (Array.isArray(tj)? tj: []);
        const total = (Array.isArray(tithes)? tithes: []).reduce((s,t)=> s + parseFloat(t.amount||0),0);
        setText('current-tithe-total', fmtMoney(total));
      }catch(_e){
        setText('current-tithe-total', fmtMoney(currentPeriod.total_amount || 0));
      }
    }else{
      setText('current-tithe-total', '$0.00');
      setText('current-period-name','No active period');
    }
  }catch(e){
    console.warn('tithe period failed', e);
    setText('current-tithe-total','$0.00');
    setText('current-period-name','No active period');
  }

  // 5. Widgets
  try{ populateWidgets(attendanceData, activeFellowships, currentPeriod); }catch(e){ console.warn('populateWidgets', e); }

  // 6. Charts
  try{
    const trendStart = getDateWeeksAgo(12);
    const [trendRes, financialRes] = await Promise.allSettled([
      safeFetch(`${API_BASE}/attendance/report.php?type=trends&start_date=${trendStart}&end_date=${getTodayDate()}`),
      safeFetch(`${API_BASE}/financial/chart_data.php`)
    ]);
    const trendData = trendRes.status==='fulfilled' ? (trendRes.value.data || trendRes.value) : null;
    const financialData = financialRes.status==='fulfilled' ? financialRes.value : null;
    const categoryData = processCategoryData(memberList);
    initCharts(trendData, financialData, categoryData);
  }catch(e){ console.warn('charts init failed', e); }

  // footer notes
  const afn = document.getElementById('attendance-footer-note');
  if(afn) afn.textContent = new Date().toLocaleDateString('en-US',{month:'short', day:'numeric'});
}

// --- Category ---
function processCategoryData(members){
  const map={};
  (members||[]).forEach(m=>{
    const cat = (m.category||m.member_category||'Uncategorized').trim() || 'Uncategorized';
    const key = cat.charAt(0).toUpperCase()+cat.slice(1).toLowerCase();
    map[key] = (map[key]||0)+1;
  });
  let labels = Object.keys(map);
  let data = Object.values(map);
  if(labels.length===0){ labels=['No data']; data=[1]; }
  return { labels, data };
}

// --- Widgets with compact, no inline styles ---
function populateWidgets(attendanceData, fellowships, period){
  // Recent services
  const servicesEl = document.getElementById('recent-services-list');
  if(servicesEl){
    const records = (attendanceData && Array.isArray(attendanceData.records)) ? attendanceData.records.slice(0,5) : [];
    if(records.length){
      const wrap = document.createElement('div'); wrap.className='widget-list';
      records.forEach(rec=>{
        const row = document.createElement('div'); row.className='widget-item';
        const title = escapeHtml(rec.service_name || rec.service_type || `Service #${rec.service_id||''}`);
        const date = escapeHtml(rec.date || rec.service_date || '');
        const present = escapeHtml(rec.present ?? rec.attendance_count ?? 0);
        row.innerHTML = `
          <div class="widget-item-main">
            <div class="widget-item-title">${title}</div>
            <div class="widget-item-sub">${date}</div>
          </div>
          <div class="widget-item-right">
            <div class="widget-item-value">${present}</div>
            <div class="widget-item-caption">present</div>
          </div>`;
        wrap.appendChild(row);
      });
      servicesEl.innerHTML=''; servicesEl.appendChild(wrap);
    }else{
      servicesEl.innerHTML = `<div class="empty"><div class="empty-icon">◷</div><strong>No recent services</strong><span>Attendance will appear once recorded.</span></div>`;
    }
    const rc = document.getElementById('recent-count');
    if(rc) rc.textContent = records.length ? `${records.length} recent` : '—';
  }

  // Fellowships
  const fEl = document.getElementById('fellowships-list');
  if(fEl){
    const list = Array.isArray(fellowships) ? fellowships.slice(0,5) : [];
    if(list.length){
      const wrap = document.createElement('div'); wrap.className='widget-list';
      list.forEach(f=>{
        const row = document.createElement('div'); row.className='widget-item';
        const name = escapeHtml(f.name || f.fellowship_name || 'Fellowship');
        const status = (f.status||'active').toLowerCase();
        const badgeCls = status==='active' ? 'badge-success' : 'badge-secondary';
        row.innerHTML = `
          <div class="widget-item-main">
            <div class="widget-item-title">${name}</div>
            <div class="widget-item-sub">${escapeHtml(f.location||f.meeting_location||'Location not set')}</div>
          </div>
          <span class="badge ${badgeCls}">${escapeHtml(status)}</span>`;
        wrap.appendChild(row);
      });
      fEl.innerHTML=''; fEl.appendChild(wrap);
    }else{
      fEl.innerHTML = `<div class="empty"><div class="empty-icon">◌</div><strong>No fellowships</strong><span>Create your first fellowship.</span></div>`;
    }
    const fc = document.getElementById('fellowship-count');
    if(fc) fc.textContent = list.length ? `${list.length} shown` : '—';
  }

  // Tithe period
  const pEl = document.getElementById('tithe-period-info');
  if(pEl){
    if(period){
      const s = period.start_date ? new Date(period.start_date).toLocaleDateString('en-US',{month:'short', day:'numeric', year:'numeric'}) : '';
      const e = period.end_date ? new Date(period.end_date).toLocaleDateString('en-US',{month:'short', day:'numeric', year:'numeric'}) : '';
      pEl.innerHTML = `
        <div class="tithe-card">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
            <strong>${escapeHtml(period.period_name)}</strong>
            <span class="badge badge-primary">Active</span>
          </div>
          <div class="muted" style="margin-top:6px;">${escapeHtml(s)} → ${escapeHtml(e)}</div>
          <div style="margin-top:10px; display:flex; gap:8px;">
            <a href="frontend/pages/financial.php" class="btn-sm" style="flex:1; justify-content:center; text-decoration:none;">View ledger</a>
            <a href="frontend/pages/financial.php" class="btn-sm primary" style="flex:1; justify-content:center; text-decoration:none;">Record tithe</a>
          </div>
        </div>`;
    }else{
      pEl.innerHTML = `<div class="empty"><div class="empty-icon">₵</div><strong>No active period</strong><span>Create a period in Financials to track tithes.</span></div>`;
    }
  }
}

// --- Charts ---
let attendanceChartInstance=null, financialChartInstance=null, categoryChartInstance=null;
let chartsInitializing=false;

function getThemeColors(){
  const isDark = document.documentElement.getAttribute('data-theme')==='dark';
  return {
    grid: isDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.06)',
    text: isDark ? '#a1a1aa' : '#64748b',
    primary: '#3a86ff',
    secondary:'#8338ec',
    success:'#06d6a0',
    danger:'#ef476f'
  };
}

function initCharts(attendanceTrendData, financialData, categoryData){
  if(chartsInitializing) return;
  chartsInitializing=true;
  const theme = getThemeColors();
  try{
    // Attendance
    const ac = document.getElementById('attendanceChart');
    if(ac){
      if(attendanceChartInstance){ try{attendanceChartInstance.destroy();}catch(_e){} attendanceChartInstance=null; }
      let labels=[], values=[];
      if(attendanceTrendData && attendanceTrendData.dates && attendanceTrendData.records){
        labels = attendanceTrendData.dates;
        values = attendanceTrendData.records.map(r=> parseInt(r.present||r.count||0,10));
      }else if(attendanceTrendData && Array.isArray(attendanceTrendData.labels)){
        labels = attendanceTrendData.labels; values = attendanceTrendData.values || attendanceTrendData.data || [];
      }else{
        // fallback mock: flat line to avoid empty chart error
        labels = ['W1','W2','W3','W4','W5','W6','W7','W8'];
        values = [0,0,0,0,0,0,0,0];
      }
      const ctx = ac.getContext('2d');
      // gradient
      let grad = ctx.createLinearGradient(0,0,0,160);
      grad.addColorStop(0, 'rgba(58,134,255,.18)');
      grad.addColorStop(1, 'rgba(58,134,255,0)');
      attendanceChartInstance = new Chart(ac,{
        type:'line',
        data:{
          labels: labels,
          datasets:[{
            label:'Attendance',
            data: values,
            borderColor: theme.primary,
            backgroundColor: grad,
            borderWidth:2,
            tension:.38,
            fill:true,
            pointRadius:0,
            pointHoverRadius:4,
            pointBackgroundColor: theme.primary
          }]
        },
        options:{
          responsive:true, maintainAspectRatio:false,
          interaction:{ mode:'index', intersect:false },
          animation:{ duration: 400 },
          plugins:{
            legend:{ display:false },
            tooltip:{
              backgroundColor:'#0f172a',
              titleColor:'#fff', bodyColor:'#e2e8f0',
              padding:10, cornerRadius:10, displayColors:false,
              callbacks:{ label: ctx=> ` ${ctx.parsed.y} present` }
            }
          },
          scales:{
            x:{ grid:{ display:false }, ticks:{ color: theme.text, font:{size:11}, maxTicksLimit:8 } , border:{ display:false }},
            y:{ beginAtZero:true, grid:{ color: theme.grid }, ticks:{ color: theme.text, font:{size:11}, precision:0 }, border:{ display:false }}
          }
        }
      });
    }

    // Financial
    const fc = document.getElementById('financialChart');
    if(fc){
      if(financialChartInstance){ try{financialChartInstance.destroy();}catch(_e){} financialChartInstance=null; }
      // financialData expected: {labels:[], tithes:[], expenses:[]}
      let labels = financialData?.labels || financialData?.months || ['Jan','Feb','Mar','Apr','May','Jun'];
      let tithes = financialData?.tithes || financialData?.income || [0,0,0,0,0,0];
      let expenses = financialData?.expenses || financialData?.costs || [0,0,0,0,0,0];
      // if financialData is null/empty, show placeholder
      if(!labels.length){
        labels=['Jan','Feb','Mar','Apr']; tithes=[0,0,0,0]; expenses=[0,0,0,0];
      }
      financialChartInstance = new Chart(fc,{
        type:'bar',
        data:{
          labels,
          datasets:[
            { label:'Tithes & Offering', data:tithes, backgroundColor:'#3a86ff', borderRadius:8, borderSkipped:false, barThickness: 14, maxBarThickness:18 },
            { label:'Expenses', data:expenses, backgroundColor:'#e2e8f0', borderRadius:8, borderSkipped:false, barThickness:14, maxBarThickness:18 }
          ]
        },
        options:{
          responsive:true, maintainAspectRatio:false,
          interaction:{ mode:'index', intersect:false },
          animation:{ duration: 350 },
          plugins:{
            legend:{ position:'bottom', labels:{ usePointStyle:true, pointStyle:'circle', boxWidth:8, boxHeight:8, padding:16, font:{ size:11, weight:'600' }, color: theme.text } },
            tooltip:{ backgroundColor:'#0f172a', titleColor:'#fff', bodyColor:'#e2e8f0', padding:10, cornerRadius:10 }
          },
          scales:{
            x:{ grid:{ display:false }, ticks:{ color: theme.text, font:{size:11}}, border:{ display:false } },
            y:{ beginAtZero:true, grid:{ color: theme.grid }, ticks:{ color: theme.text, font:{size:11}, callback:v=> '$'+Number(v).toLocaleString() }, border:{ display:false } }
          }
        }
      });
      // dark mode fix for expenses bar contrast
      if(document.documentElement.getAttribute('data-theme')==='dark'){
        financialChartInstance.data.datasets[1].backgroundColor = 'rgba(255,255,255,.18)';
        financialChartInstance.update('none');
      }
    }

    // Category doughnut
    const cc = document.getElementById('categoryChart');
    if(cc && categoryData){
      if(categoryChartInstance){ try{categoryChartInstance.destroy();}catch(_e){} categoryChartInstance=null; }
      const palette = ['#3a86ff','#06d6a0','#8338ec','#ffbe0b','#fb5607','#ff006e','#118ab2','#073b4c'];
      categoryChartInstance = new Chart(cc,{
        type:'doughnut',
        data:{
          labels: categoryData.labels,
          datasets:[{
            data: categoryData.data,
            backgroundColor: palette.slice(0, categoryData.labels.length),
            borderWidth:0,
            hoverOffset:6
          }]
        },
        options:{
          responsive:true, maintainAspectRatio:false,
          cutout:'68%',
          animation:{ duration: 350 },
          plugins:{
            legend:{ position:'right', labels:{ usePointStyle:true, pointStyle:'circle', boxWidth:8, boxHeight:8, padding:14, font:{ size:11, weight:'500'}, color: theme.text } },
            tooltip:{ backgroundColor:'#0f172a', titleColor:'#fff', bodyColor:'#e2e8f0', padding:10, cornerRadius:10 }
          }
        }
      });
    }
  }catch(e){
    console.error('initCharts error', e);
  } finally {
    chartsInitializing=false;
  }
}

// expose globally for refresh button
window.loadDashboardData = loadDashboardData;
document.addEventListener('DOMContentLoaded', loadDashboardData);

// Re-render charts on theme change (observe data-theme)
const mo = new MutationObserver(()=>{
  // debounce
  clearTimeout(window.__themeTimer);
  window.__themeTimer = setTimeout(()=> {
    // re-init with existing data if possible, else reload
    const c1=document.getElementById('attendanceChart');
    if(c1 && attendanceChartInstance) loadDashboardData();
  }, 180);
});
mo.observe(document.documentElement,{ attributes:true, attributeFilter:['data-theme'] });

})();
