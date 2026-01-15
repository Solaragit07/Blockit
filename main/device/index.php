<?php
// /main/usage/bw_limit.php  — NO DATABASE
$APP_ROOT = dirname(__DIR__, 2);
require_once $APP_ROOT . '/loginverification.php';
if (function_exists('require_login')) { require_login(); }
if (session_status() === PHP_SESSION_NONE) session_start();

$config  = require $APP_ROOT . '/config/router.php';
$API_KEY = $config['api_key'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>BlockIT – Bandwidth Limit per Device</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet"/>
  <link href="/css/sb-admin-2.min.css" rel="stylesheet"/>
  <link href="/css/custom-color-palette.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    :root{ --ink:#063c4a; --muted:#0b4a59; --panel:rgba(255,255,255,.9) }
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
    .btn-danger{ background:#dc3545; color:#fff }
    table{ width:100%; border-collapse:collapse }
    th,td{ padding:8px; border:1px solid #cfe7ef; font-size:13px }
    th{ background:#f8f9fa; font-weight:700; color:var(--ink) }
    .rate{ font-variant-numeric:tabular-nums }
    /* Back to Top Button */
#backToTop {
  position: fixed;
  bottom: 28px;
  right: 28px;
  z-index: 1050;
  width: 44px;
  height: 44px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
  color: black;
  font-size: 18px;
  box-shadow: 0 6px 14px rgba(13,202,240,0.25);
  display: none;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: opacity 0.3s ease, transform 0.3s ease;
}
#backToTop:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(13,202,240,0.35);
}
#backToTop.show {
  display: flex;
  opacity: 1;
}
.help strong {
  color: var(--ink);
  font-weight: 700;
}

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
              <h1><i class="fa-solid fa-gauge-high"></i> Bandwidth Limit per Device</h1>
              <button id="btn-refresh" class="btn btn-ghost"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
            </div>

            <div class="panel-bd">
              <div class="row row-2">
                <div class="cardx">
                  <h2>Set Bandwidth</h2>
                  <form id="form-bw" autocomplete="off">
                    <label for="sel-device">Device</label>
                    <select id="sel-device" required></select>

                    <div class="row" style="grid-template-columns:1fr 1fr; margin-top:10px">
                      <div>
                        <label for="down_kbps">Max Download</label>
                        <input type="number" id="down_kbps" min="0" placeholder="e.g. 2000 (kbps)">
                      </div>
                      <div>
                        <label for="up_kbps">Max Upload</label>
                        <input type="number" id="up_kbps" min="0" placeholder="e.g. 1000 (kbps)">
                      </div>
                    </div>
                    <div class="help" style="margin-top:8px; line-height:1.5">
                      <strong>Note:</strong><br>
                      • 512–1000 kbps → Basic browsing, social media<br>
                      • 1500–2500 kbps → Watch 480p–720p videos<br>
                      • 3000–5000 kbps → Watch 1080p HD videos<br>
                      • 5000–8000 kbps → Upload photos/videos quickly<br>
                      • 8000+ kbps → Online gaming or streaming in 4K
                    </div>
                    <div class="form-check" style="margin-top:10px">
                      <input class="form-check-input" type="checkbox" id="priority_device">
                      <label class="form-check-label" for="priority_device">Priority Device</label>
                    </div>

                    <button type="submit" class="btn btn-success" style="margin-top:12px">
                      <i class="fa-solid fa-floppy-disk"></i> Save Bandwidth
                    </button>
                  </form>
                </div>

                <div class="cardx">
                  <h2>Realtime</h2>
                  <div id="realtime" class="help">Loading…</div>
                </div>
              </div>

              <div class="cardx" style="margin-top:14px">
                <h2>Connected Devices (Wi-Fi / LAN)</h2>
                <div class="table-responsive">
                  <table id="tbl-devices">
                    <thead>
                      <tr>
                        <th>Device</th><th>IP</th><th>Limits (Down/Up)</th><th>Priority</th><th>Now (Rx/Tx)</th><th>Actions</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>
        <button id="backToTop" aria-label="Back to top">
  <i class="fas fa-chevron-up"></i>
</button>
      </div>
      <?php include '../footer.php'; ?>
    </div>
  </div>
<?php include '../script.php'; ?>
<script>
const API_KEY = <?php echo json_encode($API_KEY, JSON_UNESCAPED_SLASHES); ?>;

// Always append the API key for environments that strip headers
const API_URL = "./API/bw_limit_api.php?api_key=" + encodeURIComponent(API_KEY);

async function api(action, payload={}, method='GET') {
  // normalize action name to camelCase before sending
  const actionMap = {
    'set-limit': 'setLimit',
    'clearlimit': 'clearLimit',
    'getdevices': 'getDevices',
    'getrealtime': 'getRealtime'
  };
  const normAction = actionMap[action.toLowerCase()] || action;

  const opts = { method, headers: { 'X-API-Key': API_KEY } };
  let url = API_URL + '&action=' + encodeURIComponent(normAction);

  if (method === 'POST') {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(payload);
  }

  const r = await fetch(url, opts);
  let j;
  try { j = await r.json(); }
  catch { j = { ok:false, message:`HTTP ${r.status}` }; }

  if (!j.ok) throw new Error(j.message || `HTTP ${r.status}`);
  return j;
}


async function loadDevices(){
  const { devices=[] } = await api('getDevices');
  const sel=document.getElementById('sel-device');
  const tbody=document.querySelector('#tbl-devices tbody');
  sel.innerHTML='<option value="">-- Choose Device --</option>'; tbody.innerHTML='';

  devices.forEach(d=>{
    const opt=document.createElement('option');
    opt.value=d.mac; opt.textContent=`${d.name||'Unknown'} (${d.mac})`;
    sel.appendChild(opt);

    const limits = `${d.max_down_kbps?d.max_down_kbps+' kbps':'∞'} / ${d.max_up_kbps?d.max_up_kbps+' kbps':'∞'}`;
    const priority = d.is_priority_device? '<span class="badge badge-success">Priority</span>' : '<span class="badge">Normal</span>';
    const now = `<span class="rate">${(d.rx_rate_kbps??0)} / ${(d.tx_rate_kbps??0)} kbps</span>`;

    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td>${d.name||'Unknown'}<br><small>${d.mac}</small></td>
      <td>${d.ip||''}</td>
      <td>${limits}</td>
      <td>${priority}</td>
      <td>${now}</td>
      <td>
        <button class="btn btn-ghost btn-edit" data-mac="${d.mac}"><i class="fa-solid fa-pen"></i></button>
        <button class="btn btn-danger btn-clear" data-mac="${d.mac}"><i class="fa-solid fa-broom"></i></button>
      </td>`;
    tbody.appendChild(tr);
  });

  document.querySelectorAll('.btn-edit').forEach(btn=>{
    btn.onclick = () => {
      const mac = btn.dataset.mac;
      const d = devices.find(x=>x.mac===mac) || {};
      document.getElementById('sel-device').value=mac;
      document.getElementById('down_kbps').value=d.max_down_kbps||'';
      document.getElementById('up_kbps').value=d.max_up_kbps||'';
      document.getElementById('priority_device').checked=!!d.is_priority_device;
      window.scrollTo({top:0,behavior:'smooth'});
    };
  });

  document.querySelectorAll('.btn-clear').forEach(btn=>{
    btn.onclick = async () => {
      const mac = btn.dataset.mac;
      if(!(await Swal.fire({title:'Clear limits for this device?',showCancelButton:true,confirmButtonText:'Clear'})).isConfirmed) return;
      try{
        await api('clearLimit',{mac},'POST');
        await refreshAll();
        Swal.fire({icon:'success',title:'Cleared',timer:1200,showConfirmButton:false});
      }catch(e){
        Swal.fire({icon:'error',title:'Error',text:e.message});
      }
    };
  });
}

async function loadRealtime(){
  const { lines=[] } = await api('getRealtime');
  document.getElementById('realtime').textContent = lines.join('') || 'No data';
}

async function refreshAll(){
  await Promise.all([loadDevices(), loadRealtime()]);
}

document.getElementById('form-bw').onsubmit=async e=>{
  e.preventDefault();
  const mac=document.getElementById('sel-device').value;
  const down_kbps=parseInt(document.getElementById('down_kbps').value||'0',10)||0;
  const up_kbps=parseInt(document.getElementById('up_kbps').value||'0',10)||0;
  const is_priority_device=document.getElementById('priority_device').checked;
  if(!mac){ Swal.fire('Choose a device','','info'); return; }
  try{
    await api('setLimit',{mac,down_kbps,up_kbps,is_priority_device},'POST');
    await refreshAll();
    Swal.fire('Saved','Bandwidth settings saved','success');
  }catch(e){
    Swal.fire({icon:'error',title:'Error',text:e.message});
  }
};

document.getElementById('btn-refresh').onclick=()=>{ refreshAll(); };

setInterval(loadRealtime, 5000);
setInterval(loadDevices, 15000);

(async()=>{ await refreshAll(); })();
</script>

  <script>
(function(){
  const ids = ['sidebarToggle', 'sidebarToggleTop', 'sidebarToggleMobile'];
  const btns = ids.map(id => document.getElementById(id)).filter(Boolean);
  const sidebar = document.getElementById('accordionSidebar');
  const backdrop = document.getElementById('sb-backdrop');

  function flip(e){
    if (e) e.preventDefault();
    document.body.classList.toggle('sidebar-toggled');
    if (sidebar) sidebar.classList.toggle('toggled');

    // update aria-expanded on the mobile burger if present
    const mob = document.getElementById('sidebarToggleMobile');
    if (mob) mob.setAttribute('aria-expanded',
      document.body.classList.contains('sidebar-toggled') ? 'true' : 'false');

    // show/hide backdrop (CSS will also guard this, but we flip hidden attr too)
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
<script>
const backToTop = document.getElementById('backToTop');

window.addEventListener('scroll', () => {
  if (window.scrollY > 250) {
    backToTop.classList.add('show');
  } else {
    backToTop.classList.remove('show');
  }
});

backToTop.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

</body>
</html>
