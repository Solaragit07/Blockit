<?php
// /public_html/main/dashboard/recent_activity.php
// Session + includes (no CSRF)
$APP_ROOT = dirname(__DIR__, 2); // /public_html
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';
require_login();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Recent Activity — BlockIT</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root{ --bg:#0b1b24; --card:#0f2430; --muted:#8ba3af; --text:#e9f1f5; --accent:#0dcaf0; --danger:#ef4444; --ok:#10b981;}
    body{ background:linear-gradient(135deg,#0dcaf0 0%,#087990 100%); background-size:300% 300%; min-height:100vh;
          font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif; padding:20px; color:#0a1a21;}
    .wrap{ max-width:1100px; margin:0 auto; }
    .card{ background:#b6effb; border-radius:16px; box-shadow:0 12px 40px rgba(0,0,0,.12); border:1px solid rgba(13,202,240,.35); overflow:hidden; }
    .hd{ display:flex; gap:12px; align-items:center; justify-content:space-between; padding:16px 18px; background:rgba(255,255,255,.35); }
    .hd h1{ margin:0; font-size:20px; font-weight:700; color:#063c4a;}
    .filters{ display:grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap:10px; width:100%;}
    .filters input, .filters select{ padding:10px 12px; border:1px solid #cfe7ef; border-radius:10px; font-size:14px; background:#fff; }
    .btn{ padding:10px 12px; border:none; border-radius:10px; font-weight:600; cursor:pointer; }
    .btn-primary{ background:#087990; color:#fff; }
    .btn-ghost{ background:#ffffff; color:#087990; border:1px solid #cfe7ef; }
    .meta{ padding:10px 18px; font-size:13px; color:#0b4a59; display:flex; gap:14px; align-items:center;}
    .tbl{ width:100%; border-collapse: collapse; }
    .tbl th, .tbl td{ padding:12px 14px; border-bottom:1px solid rgba(8,121,144,.15); text-align:left; font-size:14px; }
    .tbl th{ background:rgba(13,202,240,.15); color:#063c4a; position:sticky; top:0; }
    .badge{ padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700; display:inline-block;}
    .bg-ok{ background:rgba(16,185,129,.15); color:#065f46; border:1px solid rgba(16,185,129,.3);}
    .bg-danger{ background:rgba(239,68,68,.15); color:#7f1d1d; border:1px solid rgba(239,68,68,.25);}
    .scroll{ max-height:65vh; overflow:auto; }
    .footer{ padding:12px 18px; display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,.35);}
    .muted{ color:#0b4a59; font-size:12px;}
    .pill{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#fff; border:1px solid #cfe7ef; font-size:12px;}
    .time{ font-variant-numeric: tabular-nums; }
    .empty{ padding:24px; text-align:center; color:#0b4a59;}
    .row-ok{ }
    .row-block{ background: rgba(239,68,68,.06); }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="hd">
        <h1><i class="fa-solid fa-bolt"></i> Recent Activity</h1>
        <div class="filters">
          <input type="text" id="device" placeholder="Device name or IP (e.g., John's Phone or 192.168.1.12)" />
          <select id="action">
            <option value="">Any action</option>
            <option value="Allowed">Allowed</option>
            <option value="Blocked">Blocked</option>
          </select>
          <input type="datetime-local" id="since" />
          <input type="datetime-local" id="until" />
          <div style="display:flex; gap:8px;">
            <button class="btn btn-ghost" id="clearBtn"><i class="fa-solid fa-eraser"></i></button>
            <button class="btn btn-primary" id="applyBtn"><i class="fa-solid fa-filter"></i> Apply</button>
          </div>
        </div>
      </div>

      <div class="meta">
        <span class="pill"><i class="fa-solid fa-rotate"></i> Auto-refresh: <span id="refreshSec">15s</span></span>
        <span class="pill">Rows: <span id="rowCount">0</span></span>
        <span class="pill">Filter: <span id="filterSummary">none</span></span>
      </div>

      <div class="scroll">
        <table class="tbl" id="tbl">
          <thead>
            <tr>
              <th style="width:160px">Time</th>
              <th>Device</th>
              <th>IP</th>
              <th>Website / App</th>
              <th style="width:120px">Action</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr><td colspan="5" class="empty">Loading…</td></tr>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <div class="muted">Example entry: <span class="time">10:15 AM</span> – Device: John’s Phone — <span class="badge bg-danger">Blocked</span> facebook.com</div>
        <div>
          <button class="btn btn-ghost" id="pauseBtn"><i class="fa-solid fa-pause"></i></button>
          <button class="btn btn-primary" id="refreshBtn"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const tbody = document.getElementById('tbody');
  const rowCountEl = document.getElementById('rowCount');
  const refreshSecEl = document.getElementById('refreshSec');
  const filterSummaryEl = document.getElementById('filterSummary');
  const deviceEl = document.getElementById('device');
  const actionEl = document.getElementById('action');
  const sinceEl  = document.getElementById('since');
  const untilEl  = document.getElementById('until');

  let intervalMs = 15000; // 15s
  let timer = null;
  let paused = false;

  function fmtTime(ts){
    // Expect ISO string from API; fallback to server format
    const d = new Date(ts);
    if (!isNaN(d)) return d.toLocaleString();
    return ts;
  }

  function badge(action){
    const ok = String(action || '').toLowerCase() === 'allowed';
    return `<span class="badge ${ok?'bg-ok':'bg-danger'}">${action||'?'}</span>`;
  }

  function rowClass(action){
    return (String(action||'').toLowerCase()==='blocked') ? 'row-block' : 'row-ok';
  }

  function summarize(){
    const parts = [];
    if (deviceEl.value.trim()) parts.push(`device=${deviceEl.value.trim()}`);
    if (actionEl.value) parts.push(`action=${actionEl.value}`);
    if (sinceEl.value) parts.push(`since=${sinceEl.value}`);
    if (untilEl.value) parts.push(`until=${untilEl.value}`);
    filterSummaryEl.textContent = parts.length? parts.join(' • ') : 'none';
  }

  async function load(){
    const params = new URLSearchParams({
      device: deviceEl.value.trim(),
      action: actionEl.value,
      since:  sinceEl.value,
      until:  untilEl.value,
      limit:  200 // cap to keep it snappy; adjust as needed
    });
    const res = await fetch('get_recent_activity.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString() });
    if (!res.ok) { tbody.innerHTML = `<tr><td colspan="5" class="empty">Failed to load (HTTP ${res.status})</td></tr>`; return; }
    const data = await res.json();

    if (!data.ok || !Array.isArray(data.rows)) {
      tbody.innerHTML = `<tr><td colspan="5" class="empty">${data.message || 'No data'}</td></tr>`;
      rowCountEl.textContent = '0';
      return;
    }

    if (data.rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="empty">No activity found for the selected filters.</td></tr>`;
      rowCountEl.textContent = '0';
      return;
    }

    tbody.innerHTML = data.rows.map(r => `
      <tr class="${rowClass(r.action)}">
        <td class="time">${fmtTime(r.time)}</td>
        <td>${(r.device_name || '').replace(/</g,'&lt;')}</td>
        <td>${(r.device_ip || '').replace(/</g,'&lt;')}</td>
        <td>${(r.resource || '').replace(/</g,'&lt;')}</td>
        <td>${badge(r.action)}</td>
      </tr>
    `).join('');
    rowCountEl.textContent = String(data.rows.length);
  }

  function start(){
    if (timer) clearInterval(timer);
    if (!paused) timer = setInterval(load, intervalMs);
    refreshSecEl.textContent = (intervalMs/1000)+'s';
  }

  // Buttons
  document.getElementById('applyBtn').addEventListener('click', ()=>{ summarize(); load(); });
  document.getElementById('clearBtn').addEventListener('click', ()=>{
    deviceEl.value=''; actionEl.value=''; sinceEl.value=''; untilEl.value='';
    summarize(); load();
  });
  document.getElementById('refreshBtn').addEventListener('click', ()=> load());
  document.getElementById('pauseBtn').addEventListener('click', (e)=>{
    paused = !paused;
    e.currentTarget.innerHTML = paused ? '<i class="fa-solid fa-play"></i>' : '<i class="fa-solid fa-pause"></i>';
    start();
  });

  summarize();
  load();
  start();
})();
</script>
</body>
</html>
