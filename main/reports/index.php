<?php
// /public_html/main/dashboard/index.php
// Mobile-first BlockIT Dashboard (responsive rewrite)

$APP_ROOT = dirname(__DIR__, 2); // /public_html
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';
if (function_exists('require_login')) { require_login(); }
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>BlockIT Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>

  <!-- Fonts and base resets -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <!-- Shared styles loaded in a lower-priority CSS layer so our overrides win -->
  <style>
    @layer sbadmin;
    @import url("/css/sb-admin-2.min.css") layer(sbadmin);
    @import url("/css/custom-color-palette.css") layer(sbadmin);
  </style>

  <!-- Dashboard styles (mobile-first) -->
  <style>
    :root{
      --bg1:#0dcaf0; --bg2:#087990;
      --surface:#ffffff;
      --card:#e9f8fe;
      --ink:#063c4a; --muted:#0b4a59;
      --border:rgba(13,202,240,.35);
      --ink-soft:#0a1a21;
      --chip:#b6effb;
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:linear-gradient(135deg,var(--bg1) 0%,var(--bg2) 100%);
      background-size:300% 300%;
      min-height:100vh;
      color:var(--ink-soft);
    }

    /* Wrapper aligns with your sidebar + nav includes */
    #content-wrapper{background:transparent}
    .wrap{
      max-width:1200px;
      margin:0 auto;
      padding:16px;
    }

    /* Top header */
    .header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      background:rgba(255,255,255,.45);
      border:1px solid var(--border);
      border-radius:16px;
      padding:12px;
      box-shadow:0 10px 36px rgba(0,0,0,.12);
    }
    .brand{display:flex; align-items:center; gap:10px; min-width:0}
    .brand img{width:36px;height:36px;flex:0 0 auto}
    .brand h1{margin:0; font-weight:800; font-size:18px; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .actions{display:flex; gap:8px; align-items:center}
    .btn{padding:10px 12px; border:none; border-radius:10px; font-weight:700; cursor:pointer}
    .btn-ghost{background:#fff; color:#087990; border:1px solid #cfe7ef}
    .btn-primary{background:#087990; color:#fff}

    /* Grid helpers */
    .grid{display:grid; gap:12px}
    .grid-tiles{grid-template-columns:1fr}            /* default mobile */
    @media (min-width:600px){ .grid-tiles{grid-template-columns:repeat(2,1fr)} }
    @media (min-width:900px){ .grid-tiles{grid-template-columns:repeat(4,1fr)} }

    .two-col{display:grid; gap:12px; grid-template-columns:1fr}
    @media (min-width:900px){ .two-col{grid-template-columns:1.2fr .8fr} }

    /* Cards */
    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      box-shadow:0 12px 40px rgba(0,0,0,.12);
      overflow:hidden;
    }
    .card-hd{
      display:flex; align-items:center; justify-content:space-between;
      gap:10px; padding:12px 14px; background:rgba(255,255,255,.5)
    }
    .card-hd h2{
      margin:0; font-size:16px; font-weight:800; color:var(--ink);
      display:flex; gap:8px; align-items:center
    }
    .pad{padding:12px 14px}

    /* KPI tiles */
    .kpi{
      background:var(--surface);
      border:1px solid #cfe7ef;
      border-radius:12px;
      padding:12px;
      display:flex;
      flex-direction:column;
      min-height:92px;
    }
    .kpi .lbl{color:var(--muted); font-size:12px; margin:0 0 6px}
    .kpi .num{font-size:22px; font-weight:800; color:var(--ink)}
    .kpi .delta{font-size:12px; color:#0b684a}

    /* Filters */
    .filters{
      display:grid; gap:8px; grid-template-columns:1fr;
    }
    @media (min-width:600px){ .filters{grid-template-columns:repeat(2,1fr)} }
    @media (min-width:900px){ .filters{grid-template-columns:repeat(4,1fr)} }

    .input, .select{
      width:100%;
      padding:10px 12px;
      border:1px solid #cfe7ef;
      border-radius:10px;
      background:#fff;
      font-size:14px;
    }
    .chip{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:999px; background:var(--chip);
      color:#065a6e; font-weight:700; font-size:12px;
    }

    /* Tables */
    .table-wrap{
      background:var(--surface);
      border:1px solid #cfe7ef;
      border-radius:12px;
      overflow:auto;
      max-height:55vh;          /* mobile-friendly scroll window */
    }
    .table{width:100%; border-collapse:collapse; min-width:560px}
    .table th,.table td{padding:10px 12px; border-bottom:1px solid #e5f4f8; font-size:14px; text-align:left}
    .table th{background:rgba(13,202,240,.15); color:var(--ink); position:sticky; top:0; z-index:1}
    .row-bad{background:rgba(239,68,68,.06)}
    .table-empty{padding:16px; text-align:center; color:#3a5158}

    /* MikroTik module look */
    .mk-card{background:var(--card); border:1px solid var(--border); border-radius:16px; box-shadow:0 12px 40px rgba(0,0,0,.12); overflow:hidden}
    .mk-hd{display:flex; align-items:center; justify-content:space-between; gap:8px; padding:12px 14px; background:rgba(255,255,255,.5)}
    .mk-hd h3{margin:0; font-size:16px; font-weight:800; color:var(--ink)}
    .mk-body{padding:12px 14px}
  </style>
</head>
<body id="page-top">

  <div id="wrapper">
    <?php include '../sidebar.php'; ?>

    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include '../nav.php'; ?>

        <div class="wrap fade-in">

          <header class="header" role="banner">
            <div class="brand">
              <img src="/img/logo1.png" alt="BlockIT logo" width="36" height="36"/>
              <h1>BlockIT Report</h1>
            </div>
          </header>

          <!-- <section class="grid grid-tiles" aria-label="Summary tiles" style="margin-top:12px">
            <div class="kpi"><p class="lbl">Total Requests (24h)</p><p class="num" id="kpi-requests">—</p></div>
            <div class="kpi"><p class="lbl">Blocked Events (24h)</p><p class="num" id="kpi-blocked">—</p></div>
            <div class="kpi"><p class="lbl">Active Devices</p><p class="num" id="kpi-devices">—</p></div>
            <div class="kpi"><p class="lbl">Network Health</p><p class="num" id="kpi-health">—</p><span class="chip"><i class="fa-solid fa-signal"></i> OK</span></div>
          </section> -->

          <section class="" style="margin-top:12px">

            <article class="mk-card" aria-labelledby="recent-title">
              <div class="mk-hd">
                <h3 id="recent-title"><i class="fa-regular fa-clock"></i> Recent Activity</h3>
                <span class="chip" title="Auto refresh cadence">Auto refresh 15s</span>
              </div>
              <div class="mk-body">

                <div class="filters" style="margin-bottom:10px">
                  <input id="ra-device" class="input" placeholder="Device or IP"/>
                  <select id="ra-action" class="select">
                    <option value="">Any action</option>
                    <option>Allowed</option>
                    <option>Blocked</option>
                  </select>
                  <input type="datetime-local" id="ra-since" class="input"/>
                  <input type="datetime-local" id="ra-until" class="input"/>
                </div>

                <div class="actions" style="justify-content:flex-end; margin:6px 0 10px">
                  <button id="ra-apply" class="btn btn-ghost" type="button"><i class="fa-solid fa-filter"></i> Filter</button>
                  <button id="do-refresh" class="btn btn-primary" type="button"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                  <!-- New Download Button -->
                  <button id="btn-dl-csv" class="btn btn-ghost" type="button"><i class="fa-solid fa-file-arrow-down"></i> Download CSV</button>
                </div>

                <div class="table-wrap">
                  <table class="table" id="tbl-ra">
                    <thead><tr><th>Time</th><th>Device</th><th>IP</th><th>Website or App</th><th>Action</th></tr></thead>
                    <tbody><tr><td colspan="5" class="table-empty">Loading…</td></tr></tbody>
                  </table>
                </div>

              </div>
            </article>

            <article class="mk-card" aria-labelledby="topblocked-title" style="margin-top:12px">
              <div class="mk-hd">
                <h3 id="topblocked-title"><i class="fa-solid fa-ban"></i> Top Blocked Websites</h3>
                <span class="chip" title="Aggregated from MySQL logs">Most frequent attempts</span>
              </div>
              <div class="mk-body">

                <div class="filters" style="margin-bottom:10px">
                  <input id="tb-device" class="input" placeholder="Device name or IP (optional)"/>
                  <select id="tb-sort" class="select">
                    <option value="attempts">Sort: Attempts</option>
                    <option value="last">Sort: Most recent</option>
                  </select>
                  <input type="datetime-local" id="tb-since" class="input"/>
                  <input type="datetime-local" id="tb-until" class="input"/>
                </div>

                <div class="actions" style="justify-content:flex-end; margin:6px 0 10px">
                  <button id="tb-apply" class="btn btn-ghost" type="button"><i class="fa-solid fa-filter"></i> Filter</button>
                  <button id="tb-refresh" class="btn btn-primary" type="button"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                </div>

                <div class="table-wrap">
                  <table class="table" id="tbl-tb">
                    <thead><tr><th>Website</th><th>Attempts</th><th>Last Attempt</th></tr></thead>
                    <tbody><tr><td colspan="3" class="table-empty">Loading…</td></tr></tbody>
                  </table>
                </div>

              </div>
            </article>

          </section>
        </div>
      </div>
      <?php include '../footer.php'; ?>
    </div>
  </div>

  <!-- Scripts -->
  <script src="/vendor/jquery/jquery.min.js"></script>
  <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="/js/sb-admin-2.min.js"></script>

  <script>
  const API_KEY = "c6d93fd745d852657b700d865690c8bee8a5fe66104a6248291d54b1e899e0a5";
  const RECENT_URL = "/main/reports/api/get_recent_activity.php";
  const TOP_BLOCKED_URL = "/main/reports/api/get_top_blocked_sites.php";
  let recentRows = [];

  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  async function getJSON(url, body=null, timeoutMs=12000){
    const controller = new AbortController();
    const t = setTimeout(()=>controller.abort(), timeoutMs);
    const opt = body
      ? {method:'POST',headers:{'Content-Type':'application/json','X-API-Key':API_KEY},body:JSON.stringify(body),signal:controller.signal}
      : {headers:{'X-API-Key':API_KEY},signal:controller.signal};
    try{
      const r = await fetch(url,opt);
      if(!r.ok){throw new Error('HTTP '+r.status);}
      return await r.json();
    } catch (e){
      if (e.name === 'AbortError') throw new Error('Request timed out');
      throw e;
    } finally {
      clearTimeout(t);
    }
  }

  function rowActionClass(a){return String(a||'').toLowerCase()==='blocked'?'row-bad':'';}
  function renderRows(rows){
    if(!rows||!rows.length)return'<tr><td colspan="5" class="table-empty">No recent activity</td></tr>';
    return rows.map(r=>`<tr class=\"${rowActionClass(r.action)}\"><td>${esc(r.time)}</td><td>${esc(r.device_name)}</td><td>${esc(r.device_ip)}</td><td>${esc(r.resource)}</td><td>${esc(r.action ?? '')}</td></tr>`).join('');
  }

  async function loadRecent(){
    const tb=document.querySelector('#tbl-ra tbody');
    tb.innerHTML='<tr><td colspan="5" class="table-empty">Loading…</td></tr>';
    const body={device:document.getElementById('ra-device').value||'',action:document.getElementById('ra-action').value||'',since:document.getElementById('ra-since').value||'',until:document.getElementById('ra-until').value||'',limit:200};
    try{
      const j=await getJSON(RECENT_URL,body);
      if (j && j.ok === false) throw new Error(j.message || 'API error');
      recentRows=j.rows||[];
      tb.innerHTML=renderRows(recentRows);
    }catch(e){tb.innerHTML=`<tr><td colspan="5" class="table-empty">Error loading data: ${esc(e.message)}</td></tr>`;}
  }

  function renderTopBlocked(rows){
    if(!rows||!rows.length)return'<tr><td colspan="3" class="table-empty">No blocked website attempts found</td></tr>';
    return rows.map(r=>`<tr class="row-bad"><td>${esc(r.site)}</td><td>${esc(r.attempts)}</td><td>${esc(r.lastAttempt)}</td></tr>`).join('');
  }

  async function loadTopBlocked(){
    const tb=document.querySelector('#tbl-tb tbody');
    tb.innerHTML='<tr><td colspan="3" class="table-empty">Loading…</td></tr>';
    const body={
      device:document.getElementById('tb-device').value||'',
      sort:document.getElementById('tb-sort').value||'attempts',
      since:document.getElementById('tb-since').value||'',
      until:document.getElementById('tb-until').value||'',
      limit:50
    };
    try{
      const j=await getJSON(TOP_BLOCKED_URL,body);
      if (j && j.ok === false) throw new Error(j.message || 'API error');
      tb.innerHTML=renderTopBlocked(j.rows||[]);
    }catch(e){
      tb.innerHTML=`<tr><td colspan="3" class="table-empty">Error loading data: ${esc(e.message)}</td></tr>`;
    }
  }

  // Download CSV feature
  const CSV_EOL = '\r\n';

  function csvEscape(v){
    const s = String(v ?? '');
    // Quote if value contains a quote, comma, or newline
    if (/[",\n]/.test(s)) {
      return `"${s.replace(/"/g,'""')}"`;
    }
    return s;
  }

  function rowsToCSV(rows){
    const header = ['Time','Device','IP','Website or App','Action'];
    const lines = [header.join(',')];

    for (const r of rows){
      lines.push([
        csvEscape(r.time),
        csvEscape(r.device_name),
        csvEscape(r.device_ip),
        csvEscape(r.resource),
        csvEscape(r.action)
      ].join(','));
    }

    // Join with real newlines so headers + rows are on separate lines
    return lines.join(CSV_EOL) + CSV_EOL;
  }

  function downloadBlob(name, mime, content){
    // Prepend UTF-8 BOM so Excel opens it correctly
    const BOM = '\uFEFF';
    const b = new Blob([BOM, content], { type: mime });
    const u = URL.createObjectURL(b);
    const a = document.createElement('a');
    a.href = u;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(u);
  }
  function ts(){const d=new Date(),p=n=>String(n).padStart(2,'0');return d.getFullYear()+p(d.getMonth()+1)+p(d.getDate())+'_'+p(d.getHours())+p(d.getMinutes())+p(d.getSeconds());}
  document.getElementById('btn-dl-csv')?.addEventListener('click',()=>{if(!recentRows.length){alert('No data to download.');return;}const csv=rowsToCSV(recentRows);downloadBlob('recent_activity_'+ts()+'.csv','text/csv;charset=utf-8',csv);});

  document.getElementById('ra-apply')?.addEventListener('click',loadRecent);
  document.getElementById('do-refresh')?.addEventListener('click',loadRecent);

  document.getElementById('tb-apply')?.addEventListener('click',loadTopBlocked);
  document.getElementById('tb-refresh')?.addEventListener('click',loadTopBlocked);

  (async()=>{
    await loadRecent();
    await loadTopBlocked();
    setInterval(loadRecent,15000);
  })();
  </script>
</body>
</html>
