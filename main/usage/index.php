<?php
// /main/usage/time_limit.php
$APP_ROOT = dirname(__DIR__, 2);
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$config  = require $APP_ROOT . '/config/router.php';
$API_KEY = $config['api_key'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>BlockIT – Time Limit Control</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet"/>
  <link href="/css/sb-admin-2.min.css" rel="stylesheet"/>
  <link href="/css/custom-color-palette.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    :root{ --ink:#063c4a; --panel:rgba(255,255,255,.9) }
    body{ font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:linear-gradient(135deg,#e3f2fd,#bbdefb); min-height:100vh }
    .wrap{ max-width:1200px; margin:0 auto; padding:16px }
    .panel{ background:var(--panel); border:1px solid #b3e5fc; border-radius:14px; box-shadow:0 10px 30px rgba(13,202,240,.12) }
    .panel-hd{ padding:14px 16px; border-bottom:1px solid #d9f3ff; display:flex; align-items:center; justify-content:space-between }
    .panel-hd h1{ font-size:20px; font-weight:800; color:var(--ink); margin:0; display:flex; gap:10px; align-items:center }
    .panel-bd{ padding:16px }
    .row{ display:grid; gap:14px }
    @media(min-width:980px){ .row-2{ grid-template-columns:1fr 1fr } }
    .cardx{ background:#fff; border:1px solid #cfe7ef; border-radius:12px; padding:14px }
    .cardx h2{ font-size:15px; font-weight:800; color:var(--ink); margin:0 0 10px 0 }
    .help{ font-size:12px; color:#5c7480 }
    select,input{ width:100%; padding:9px 12px; border:1px solid #cfe7ef; border-radius:10px; background:#fff }
    .btn{ padding:7px 12px; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px }
    .btn-ghost{ background:#fff; color:#087990; border:1px solid #cfe7ef }
    .btn-success{ background:#198754; color:#fff }
    .badge{ display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; font-weight:700 }
    .badge-active{ background:#e8f7f0; color:#18794e; border:1px solid #b8e2cc }
    .badge-blocked{ background:#fbeaea; color:#a30000; border:1px solid #f5c2c2 }
    table{ width:100%; border-collapse:collapse }
    th,td{ padding:8px; border:1px solid #cfe7ef; font-size:13px }
    th{ background:#f8f9fa; font-weight:700; color:var(--ink) }
    .logs{ background:#fff; border:1px solid #cfe7ef; border-radius:10px; padding:10px; min-height:220px; max-height:340px; overflow:auto; font-family:ui-monospace,Consolas,monospace; font-size:12px; white-space:pre-wrap }
  </style>
  <script src="/vendor/jquery/jquery.min.js"></script>
  <script src="/js/sweetalert2.all.js"></script>
</head>
<body id="page-top">
  <div id="wrapper">
    <?php include '../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include '../nav.php'; ?>
        <div class="wrap">
          <div class="panel">
            <div class="panel-hd">
              <h1><i class="fa-solid fa-clock"></i> Time Limit Control</h1>
              <div>
                <button id="btn-whoami" class="btn btn-ghost">Who am I?</button>
                <button id="btn-refresh" class="btn btn-ghost"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
              </div>
            </div>

            <div class="panel-bd">
              <div class="row row-2">
                <div class="cardx">
                  <h2>Set Device Usage Limits</h2>
                  <form id="form-limit" autocomplete="off">
                    <label for="sel-device">Device</label>
                    <select id="sel-device" required></select>

                    <label for="sel-type" style="margin-top:10px">Limit Type</label>
                    <select id="sel-type">
                      <option value="daily">Daily</option>
                      <option value="weekly">Weekly</option>
                    </select>

                    <label for="minutesAllowed" style="margin-top:10px">Allowed Minutes</label>
                    <input type="number" id="minutesAllowed" min="1" max="1440" value="30">

                    <button type="submit" class="btn btn-success" style="margin-top:12px">
                      <i class="fa-solid fa-floppy-disk"></i> Save Limit
                    </button>
                  </form>
                </div>

                <div class="cardx">
                  <h2>Logs</h2>
                  <div id="logs" class="logs">Loading…</div>
                </div>
              </div>

              <div class="cardx" style="margin-top:14px">
                <h2>Connected Devices (Wi-Fi)</h2>
                <div class="table-responsive">
                  <table id="tbl-devices">
                    <thead>
                      <tr><th>Device</th><th>IP</th><th>Status</th><th>Used</th><th>Remaining</th><th>Actions</th></tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>

            </div><!-- /.panel-bd -->
          </div>
        </div>
      </div><?php include '../footer.php'; ?>
    </div>
  </div>

<script>
const API_KEY=<?php echo json_encode($API_KEY,JSON_UNESCAPED_SLASHES); ?>;
const API_URL="./API/time_limit_api.php";
const USER_EMAIL = <?php echo json_encode($_SESSION['email'] ?? null); ?>;

function hasSwal(){ return typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function'; }

async function uiConfirm(title, text, confirmText='OK'){
  if (hasSwal()){
    const r = await Swal.fire({title, text, icon:'question', showCancelButton:true, confirmButtonText:confirmText});
    return !!r.isConfirmed;
  }
  return window.confirm((title ? title + "\n\n" : "") + (text || ''));
}

function fmtMin(v){
  if (v === null || typeof v === 'undefined') return null;
  const n = Number(v);
  if (!Number.isFinite(n)) return null;
  return Math.round(n * 10) / 10;
}

async function api(action, payload = {}, method = 'GET') {
  const opts = {
    method,
    headers: {
      'X-API-Key': API_KEY,          // ✅ required for authorization
      'X-User-Email': USER_EMAIL,    // ✅ required so PHP knows who to email
      'Content-Type': 'application/json'
    },
    credentials: 'same-origin', // send cookies (session)
    cache: 'no-store'
  };

  // Keep requests relative to /main/usage/
  const url = `API/time_limit_api.php?action=${encodeURIComponent(action)}`;
  if (method === 'POST') {
    opts.body = JSON.stringify(payload);
  }

  const res = await fetch(url, opts);
  const data = await res.json();
  if (!data.ok) throw new Error(data.message || `HTTP ${res.status}`);
  return data;
}

async function loadDevices(){
  const [devRes, stateRes] = await Promise.allSettled([ api('getDevices'), api('getState') ]);
  let list = [];
  if (devRes.status==='fulfilled' && devRes.value.devices) list = devRes.value.devices;
  if (stateRes.status==='fulfilled' && stateRes.value.state){
    const byMac = Object.create(null);
    list.forEach(d => { byMac[(d.mac||'').toLowerCase()] = d; });
    stateRes.value.state.forEach(s => {
      const key = (s.mac||'').toLowerCase();
      if (!byMac[key]) {
        byMac[key] = { mac:key, name:'Unknown (offline)', ip:null,
          status:s.status||'active', used:s.used||0, minutes:s.minutes||null, type:s.type||null };
      } else {
        byMac[key].status  = s.status  ?? byMac[key].status  ?? 'active';
        byMac[key].used    = s.used    ?? byMac[key].used    ?? 0;
        byMac[key].minutes = s.minutes ?? byMac[key].minutes ?? null;
        byMac[key].type    = s.type    ?? byMac[key].type    ?? null;
      }
    });
    list = Object.values(byMac);
  }

  const sel=document.getElementById('sel-device');
  const tbody=document.querySelector('#tbl-devices tbody');
  const prevValue = sel.value;
  sel.innerHTML='<option value="">-- Choose Device --</option>';
  tbody.innerHTML='';

  list.forEach(d=>{
    const opt=document.createElement('option');
    opt.value=d.mac; opt.textContent=`${d.name||'Unknown'} (${d.mac})`;
    sel.appendChild(opt);
    const used = fmtMin(d.used ?? 0) ?? 0;
    const minutes = fmtMin(d.minutes ?? null);
    const remaining = minutes === null ? null : Math.max(fmtMin(minutes - used) ?? 0, 0);
    const tr=document.createElement('tr');
    tr.innerHTML=`<td>${d.name||'Unknown'}<br><small>${d.mac}</small></td>
      <td>${d.ip||''}</td>
      <td><span class="badge ${d.status==='blocked'?'badge-blocked':'badge-active'}">${d.status||'active'}</span></td>
      <td>${minutes === null ? `${used}/Not set min` : `${used}/${minutes} min`}</td>
      <td>${remaining === null ? 'Not set' : `${remaining} min`}</td>
      <td>${(() => {
        const mac = d.mac;
        const blockBtn = d.status==='blocked'
          ? `<button type="button" class="btn btn-success btn-unblock" data-mac="${mac}"><i class="fa-solid fa-unlock"></i> Unblock</button>`
          : `<button type="button" class="btn btn-ghost btn-block" data-mac="${mac}"><i class="fa-solid fa-ban"></i> Block Now</button>`;
        const setBtn = (minutes === null)
          ? ` <button type="button" class="btn btn-success btn-set" data-mac="${mac}"><i class="fa-solid fa-clock"></i> Set Limit</button>`
          : '';
        return blockBtn + setBtn;
      })()}</td>`;
    tbody.appendChild(tr);
  });

  // restore previous selection if still present
  if (prevValue && Array.from(sel.options).some(o => o.value === prevValue)) {
    sel.value = prevValue;
  }

  document.querySelectorAll('.btn-unblock').forEach(btn=>{
    btn.onclick = async () => {
      const mac = btn.dataset.mac;
      try {
        const r = await api('unblock', { mac }, 'POST');
        if (hasSwal()) Swal.fire({icon:'success', title:'Unblocked', text:r.message, timer:1400, showConfirmButton:false});
        await Promise.all([loadDevices(), loadLogs()]);
      } catch(e) {
        if (hasSwal()) Swal.fire({icon:'error', title:'Error', text:e.message});
        else alert('Error: ' + e.message);
      }
    };
  });

  document.querySelectorAll('.btn-block').forEach(btn=>{
    btn.onclick = async () => {
      const mac = btn.dataset.mac;
      const ok = await uiConfirm('Block this device now?', mac, 'Block');
      if(!ok) return;
      try {
        const r = await api('block', { mac }, 'POST');
        if (hasSwal()) Swal.fire({icon:'success', title:'Blocked', text:r.message, timer:1400, showConfirmButton:false});
        await Promise.all([loadDevices(), loadLogs()]);
      } catch(e) {
        if (hasSwal()) Swal.fire({icon:'error', title:'Error', text:e.message});
        else alert('Error: ' + e.message);
      }
    };
  });

  document.querySelectorAll('.btn-set').forEach(btn=>{
    btn.onclick = () => {
      const mac = btn.dataset.mac;
      const sel = document.getElementById('sel-device');
      if (sel) sel.value = mac;
      document.getElementById('minutesAllowed')?.focus();
      window.scrollTo({top:0,behavior:'smooth'});
    };
  });
}

async function loadLogs(){
  const res=await api('getLogs');
  document.getElementById('logs').textContent=(res.logs||[]).join('')||'No logs';
}

async function pulse(){
  try{
    const res=await api('pulse'); // accrues usage + blocks + sends email
    if(res && res.state){ await loadDevices(); }
  }catch(e){ console.warn('pulse failed',e.message); }
}

async function refreshAll(){ await pulse(); await Promise.all([loadDevices(), loadLogs()]); }

document.getElementById('form-limit').onsubmit=async e=>{
  e.preventDefault();
  const mac=document.getElementById('sel-device').value;
  const type=document.getElementById('sel-type').value;
  const minutes=parseInt(document.getElementById('minutesAllowed').value,10)||0;
  if(!mac){ Swal.fire('Choose a device','','info'); return; }
  if(minutes<1){ Swal.fire('Invalid minutes','','error'); return; }
  await api('setLimit',{mac,type,minutes},'POST');
  await refreshAll();
  Swal.fire('Saved','Limit saved','success');
};

document.getElementById('btn-refresh').onclick=()=>{ refreshAll(); };
document.getElementById('btn-whoami').onclick=async()=>{
  try{
    const r=await api('whoami');
    Swal.fire('Session', `Email: ${r.email}\nSessionID: ${r.sid}\nKeys: ${r.keys.join(', ')}`, 'info');
  }catch(e){ Swal.fire('Error', e.message, 'error'); }
};

setInterval(pulse, 5000);
setInterval(loadDevices, 15000);
setInterval(loadLogs, 20000);
document.addEventListener('visibilitychange',()=>{ if(!document.hidden) refreshAll(); });

(async()=>{ await refreshAll(); })();
</script>

  <script>
(function(){
  const ids = ['sidebarToggle', 'sidebarToggleTop', 'sidebarToggleMobile'];
  const btns = ids.map(id => document.getElementById(id)).filter(Boolean);
  const sidebar = document.getElementById('accordionSidebar') || document.querySelector('.sidebar');
  const backdrop = document.getElementById('sb-backdrop');

  function flip(e){
    if (e) e.preventDefault();
    document.body.classList.toggle('sidebar-toggled');
    if (sidebar) sidebar.classList.toggle('toggled');

    const mob = document.getElementById('sidebarToggleMobile');
    if (mob) mob.setAttribute('aria-expanded',
      document.body.classList.contains('sidebar-toggled') ? 'true' : 'false');

    if (backdrop){
      const open = document.body.classList.contains('sidebar-toggled');
      backdrop.hidden = !open;
    }
  }

  btns.forEach(b => b.addEventListener('click', flip));
  if (backdrop) backdrop.addEventListener('click', () => {
    document.body.classList.remove('sidebar-toggled');
    if (sidebar) sidebar.classList.remove('toggled');
    backdrop.hidden = true;
    const mob = document.getElementById('sidebarToggleMobile');
    if (mob) mob.setAttribute('aria-expanded','false');
  });
})();
</script>
</body>
</html>
