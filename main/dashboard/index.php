<?php
// /public_html/main/dashboard/index.php
// Inline dashboard with MikroTik widgets, reusing sidebar/nav/footer includes + styles safely

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
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <!-- Shared styles loaded in a lower-priority CSS layer so your dashboard styles still win -->
  <style>
    @layer sbadmin;
    /* Use root-relative paths; change to ../../css/... if your setup needs it */
    @import url("/css/sb-admin-2.min.css") layer(sbadmin);
    @import url("/css/custom-color-palette.css") layer(sbadmin);
  </style>

  <!-- Your dashboard styles (unlayered) – these override the layered imports above -->
  <style>
    :root{
      --bg1:#0dcaf0; --bg2:#087990;
      --card:#b6effb; --ink:#063c4a; --muted:#0b4a59;
      --border:rgba(13,202,240,.35);
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:linear-gradient(135deg,var(--bg1) 0%,var(--bg2) 100%);
      background-size:300% 300%; min-height:100vh; color:#0a1a21;
    }
    .wrap{max-width:1200px; margin:0 auto; padding:20px;}
    .header{
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      background:rgba(255,255,255,.35); border:1px solid var(--border); border-radius:16px;
      padding:12px 16px; box-shadow:0 12px 40px rgba(0,0,0,.12); margin-bottom:16px;
    }
    .brand{display:flex; align-items:center; gap:10px;}
    .brand img{width:36px;height:36px}
    .brand h1{margin:0; font-weight:800; font-size:20px; color:var(--ink)}
    .actions{display:flex; gap:8px; align-items:center}
    .btn{padding:10px 12px; border:none; border-radius:10px; font-weight:700; cursor:pointer}
    .btn-ghost{background:#fff; color:#087990; border:1px solid #cfe7ef}
    .btn-primary{background:#087990; color:#fff}
    .grid{display:grid; gap:12px}
    @media(min-width:900px){ .grid-4{grid-template-columns:repeat(4,1fr)} .grid-2{grid-template-columns: 1fr 1fr} }
    .card{
      background:var(--card); border:1px solid var(--border); border-radius:16px;
      box-shadow:0 12px 40px rgba(0,0,0,.12); overflow:hidden;
    }
    .card-hd{display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:rgba(255,255,255,.35)}
    .card-hd h2{margin:0; font-size:18px; font-weight:800; color:var(--ink); display:flex; gap:8px; align-items:center}
    .pad{padding:12px 14px}
    /* KPI tiles */
    .kpi{background:#fff; border:1px solid #cfe7ef; border-radius:12px; padding:12px}
    .kpi .lbl{color:var(--muted); font-size:12px; margin:0 0 4px}
    .kpi .num{font-size:24px; font-weight:800; color:var(--ink)}
    /* Tables */
    .table{width:100%; border-collapse:collapse}
    .table th,.table td{padding:8px 10px; border-bottom:1px solid #e5f4f8; font-size:14px; text-align:left}
    .table th{background:rgba(13,202,240,.15); color:var(--ink); position:sticky; top:0}
    .scroll{max-height:48vh; overflow:auto}
    .controls{display:flex; gap:8px; align-items:center; flex-wrap:wrap}
    .input{padding:8px 10px; border:1px solid #cfe7ef; border-radius:10px; background:#fff; font-size:14px; min-width:160px}
    /* MikroTik inline module (same look & logic) */
    .mk-wrap{margin:16px 0}
    .mk-grid{display:grid; gap:10px}
    @media(min-width:900px){ .mk-grid{grid-template-columns:repeat(4,1fr)} }
    .mk-card{background:#fff;border:1px solid #cfe7ef;border-radius:12px;padding:12px}
    .mk-label{color:#0b4a59;font-size:12px;margin-bottom:4px}
    .mk-big{font-weight:800;color:#063c4a;font-size:20px}
    .mk-two{display:grid;gap:12px}
    @media(min-width:900px){ .mk-two{grid-template-columns:1fr 1fr} }
    .mk-table{width:100%;border-collapse:collapse}
    .mk-table th,.mk-table td{padding:8px 10px;border-bottom:1px solid #e5f4f8;font-size:14px;text-align:left}
    .mk-table th{background:rgba(13,202,240,.15);color:#063c4a;position:sticky;top:0}
    .mk-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .mk-input{padding:8px 10px;border:1px solid #cfe7ef;border-radius:10px;font-size:14px;background:#fff}
    .mk-btn{padding:8px 10px;border:none;border-radius:10px;background:#087990;color:#fff;font-weight:700;cursor:pointer}
    .mk-btn-ghost{padding:8px 10px;border:1px solid #cfe7ef;border-radius:10px;background:#fff;color:#087990;font-weight:700;cursor:pointer}
    .mk-toggle {
    border: 1px solid #cfe7ef; background:#fff; color:#087990;
    border-radius:10px; padding:6px 10px; font-weight:700; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px; font-size:12px;
    }
    .mk-chev { display:inline-block; transition: transform .2s ease; }
    .mk-collapsed .mk-chev { transform: rotate(-90deg); }
    .mk-hidden { display:none !important; }
    .mk-label-row{display:flex; align-items:center; justify-content:space-between; gap:8px}
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

  </style>
</head>
<body id="page-top">

<div id="wrapper">
  <?php include '../sidebar.php'; ?>

  <div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
      <?php include '../nav.php'; ?>

      <!-- ====== Your dashboard content ====== -->
      <div class="wrap fade-in"><!-- /.wrap opens -->

        <!-- Top header -->
        <div class="header">
          <div class="brand">
            <img src="/img/logo1.png" alt="BlockIT"/>
            <h1>BlockIT Dashboard</h1>
          </div>
          <div class="actions">
            <!-- optional actions -->
          </div>
        </div><!-- /.header -->

        <!-- KPI Row -->
        <div class="grid grid-4">
          <div class="kpi">
            <div class="lbl">Blocklist</div>
            <div class="num" id="kpi-block">—</div>
          </div>
          <div class="kpi">
            <div class="lbl">Whitelist</div>
            <div class="num" id="kpi-white">—</div>
          </div>
          <div class="kpi">
            <div class="lbl">Active Devices</div>
            <div class="num" id="kpi-active">—</div>
          </div>
          <div class="kpi">
            <div class="lbl">WAN IP</div>
            <div class="num" id="kpi-wan">—</div>
          </div>
        </div><!-- /.grid grid-4 -->

        <!-- MikroTik Control -->
        <div class="card mk-wrap" id="mk"><!-- /.card.mk-wrap opens -->
          <div class="card-hd">
            <h2><i class="fa-solid fa-network-wired"></i> MikroTik Control</h2>
          </div>

          <div class="pad"><!-- /.pad opens -->

            <!-- Address lists manager -->
            <div class="mk-two"><!-- /.mk-two opens -->

              <!-- Blocklist -->
              <div class="mk-card">
                <div class="mk-label fw-bold">Blocklist</div>
                <div class="mk-controls mt-2" hidden>
                  <input id="blk-input" class="mk-input" placeholder="domain or IP to block">
                  <button id="blk-add" class="mk-btn">Add</button>
                </div>
                <div class="scroll">
                  <table class="mk-table" id="tbl-block">
                    <thead><tr><th>Address</th><th>Domain / Comment</th></tr></thead>
                    <tbody><tr><td colspan="2">Loading…</td></tr></tbody>
                  </table>
                </div>
              </div><!-- /.mk-card (Blocklist) -->

              <!-- Whitelist -->
              <div class="mk-card">
                <div class="mk-label fw-bold">Whitelist (view)</div>
                <div class="scroll">
                  <table class="mk-table" id="tbl-white">
                    <thead><tr><th>Address</th><th>Comment</th></tr></thead>
                    <tbody><tr><td colspan="2">Loading…</td></tr></tbody>
                  </table>
                </div>
              </div><!-- /.mk-card (Whitelist) -->

            </div><!-- /.mk-two -->

            <!-- Active devices & Interfaces -->
            <div class="mk-card"><!-- Active Devices card -->
              <div class="mk-label-row">
                <div class="mk-label" style="font-weight:700">Active Devices</div>
                <button class="mk-toggle" data-toggle="active">
                  <span class="mk-chev">▾</span> <span class="mk-txt">Hide</span>
                </button>
              </div>
              <div class="scroll" id="pane-active">
                <table class="mk-table" id="tbl-active">
                  <thead><tr><th>IP</th><th>MAC</th><th>Name</th><th>Status</th><th>Last Seen</th></tr></thead>
                  <tbody><tr><td colspan="5">Loading…</td></tr></tbody>
                </table>
              </div>
            </div><!-- /.mk-card (Active Devices) -->

            <div class="mk-card"><!-- Ports card -->
              <div class="mk-label-row">
                <div class="mk-label" style="font-weight:700">Ports status (Real Time)</div>
                <button class="mk-toggle" data-toggle="ports">
                  <span class="mk-chev">▾</span> <span class="mk-txt">Hide</span>
                </button>
              </div>
              <div class="scroll" id="pane-ports">
                <table class="mk-table" id="tbl-if">
                  <thead><tr><th>Interface</th><th>Status</th><th>RX Bytes</th><th>TX Bytes</th></tr></thead>
                  <tbody><tr><td colspan="4">Loading…</td></tr></tbody>
                </table>
              </div>
              <div class="mk-label">Auto-refresh: 10s</div>
            </div><!-- /.mk-card (Ports) -->

          </div><!-- /.pad -->
        </div><!-- /.card.mk-wrap -->

      </div><!-- /.wrap -->
      <!-- ====== /Your dashboard content ====== -->
      <button id="backToTop" aria-label="Back to top">
  <i class="fas fa-chevron-up"></i>
</button>

    </div><!-- /#content -->
    
    <?php include '../footer.php'; ?>
  </div><!-- /#content-wrapper -->
</div><!-- /#wrapper -->


  <!-- SB-Admin JS (optional but recommended if your sidebar/nav need it) -->
  <script src="/vendor/jquery/jquery.min.js"></script>
  <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="/js/sb-admin-2.min.js"></script>

  <script>
  const API_KEY = "c6d93fd745d852657b700d865690c8bee8a5fe66104a6248291d54b1e899e0a5";

  // === Shared helpers
  const fmtBytes = n => { if(n==null) return '—'; const s=['B','KB','MB','GB','TB']; let i=0,v=Number(n);while(v>=1024&&i<s.length-1){v/=1024;i++;}return v.toFixed(1)+' '+s[i]; };
  const esc = s => String(s||'').replace(/</g,'&lt;');

  async function getJSON(url, body=null){
    const opt = body ? {
      method: 'POST',
      headers: { 'Content-Type':'application/json','X-API-Key':API_KEY },
      body: JSON.stringify(body)
    } : { headers: { 'X-API-Key': API_KEY } };
    const r = await fetch(url, opt);
    if(!r.ok) throw new Error('HTTP '+r.status);
    return await r.json();
  }

  // === API loaders
  async function loadLists(){
    const j = await getJSON('./api/address_lists.php');
    if(!j.ok) throw new Error(j.message||'address_lists failed');
    document.getElementById('kpi-block').textContent = j.blocklist.length;
    document.getElementById('kpi-white').textContent = j.whitelist.length;

    const tb1 = document.querySelector('#tbl-block tbody');
    const tb2 = document.querySelector('#tbl-white tbody');

    tb1.innerHTML = j.blocklist.length ? j.blocklist.map(r=>`
      <tr><td>${esc(r.address)}</td><td>${esc(r.comment)}</td>
    `).join('') : '<tr><td colspan="3">Empty</td></tr>';

    tb2.innerHTML = j.whitelist.length ? j.whitelist.map(r=>`
      <tr><td>${esc(r.address)}</td><td>${esc(r.comment)}</td></tr>
    `).join('') : '<tr><td colspan="2">Empty</td></tr>';

    // remove handlers
    tb1.querySelectorAll('button[data-id],button[data-addr]').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const addr = btn.dataset.addr || '';
        const id   = btn.dataset.id || '';

        btn.disabled = true;
        try {
          const result = await getJSON('./api/remove_block.php', { id, address: addr.replace(" (exists)", "") });

          if (result.ok) {
            let msg = `Removed ${addr || id}\n`;
            if (result.removed_ips?.length) msg += `IPs: ${result.removed_ips.join(', ')}\n`;
            if (result.removed_dns?.length) msg += `DNS: ${result.removed_dns.join(', ')}\n`;
            if (result.debug?.length) msg += `\nDebug:\n- ${result.debug.join('\n- ')}`;
            alert(msg);
          } else {
            alert('Error: ' + (result.message || 'Remove failed'));
            if (result.debug) console.log('Debug:', result.debug);
          }

          await loadLists();
        } catch (e) {
          console.error(e);
          alert('Remove failed');
        }
        btn.disabled = false;
      });
    });
  }

  // === Add button handler
  document.getElementById('blk-add').addEventListener('click', async () => {
    const v = document.getElementById('blk-input').value.trim();
    if (!v) return;

    try {
      const result = await getJSON('./api/add_block.php', {
        address: v,
        comment: "From: " + v
      });

      if (result.ok) {
        let msg = `Blocked: ${result.input}\n`;
        if (Array.isArray(result.added_ips) && result.added_ips.length > 0) {
          msg += "Firewall entries:\n" + result.added_ips.map(x => "  - " + x).join("\n") + "\n";
        }
        if (Array.isArray(result.dns_sinkhole) && result.dns_sinkhole.length > 0) {
          msg += "DNS sinkholes:\n" + result.dns_sinkhole.map(x => "  - " + x).join("\n") + "\n";
        }
        alert(msg.trim());
        document.getElementById('blk-input').value = '';
        loadLists();
      } else {
        alert('Error: ' + (result.message || 'Unknown error'));
      }
    } catch (e) {
      console.error(e);
      alert('Add failed');
    }
  });

  // === Other loaders
  async function loadActive(){ const j = await getJSON('./api/active_devices.php'); if(!j.ok) throw new Error(j.message||'active failed');
    document.getElementById('kpi-active').textContent = j.clients.length;
    const tb = document.querySelector('#tbl-active tbody');
    tb.innerHTML = j.clients.length? j.clients.map(c=>`
      <tr><td>${esc(c.ip)}</td><td>${esc(c.mac)}</td><td>${esc(c.name)}</td><td>${esc(c.status)}</td><td>${esc(c.last_seen)}</td></tr>
    `).join('') : '<tr><td colspan="5">No active clients</td></tr>';
  }

  async function loadIfs(){ const j = await getJSON('./api/interface_stats.php'); if(!j.ok) throw new Error(j.message||'ifs failed');
    const tb = document.querySelector('#tbl-if tbody');
    tb.innerHTML = j.interfaces.length? j.interfaces.map(i=>{
      const st = i.disabled? 'Disabled' : (i.running? 'Running':'Down');
      return `<tr><td>${esc(i.name)}</td><td>${esc(st)}</td><td>${fmtBytes(i.rx)}</td><td>${fmtBytes(i.tx)}</td></tr>`;
    }).join('') : '<tr><td colspan="4">No interfaces</td></tr>';
  }

  async function loadStatus(){ const j = await getJSON('./api/router_status.php'); if(!j.ok) throw new Error(j.message||'status failed');
    document.getElementById('kpi-wan').textContent = j.wan_ip || '—';
  }

  // Top-level refresh
  // document.getElementById('mk-refresh').addEventListener('click', async ()=>{ await Promise.all([loadStatus(), loadLists(), loadActive(), loadIfs()]); });
  // document.getElementById('do-refresh').addEventListener('click', async ()=>{ await Promise.all([loadStatus(), loadLists(), loadActive(), loadIfs()]); });

  // Initial load + timers (Recent Activity removed → no loadRecent / 15s interval)
  (async()=>{ try{ await Promise.all([loadStatus(), loadLists(), loadActive(), loadIfs()]); }catch(e){} setInterval(loadIfs,10000); })();
  // --- Collapse / Expand for Active Devices & Ports ---
function wireToggles(){
  const map = {
    active: { pane: document.getElementById('pane-active') },
    ports:  { pane: document.getElementById('pane-ports')  }
  };
  document.querySelectorAll('.mk-toggle[data-toggle]').forEach(btn=>{
    const key = btn.dataset.toggle;
    const cfg = map[key];
    if(!cfg || !cfg.pane) return;

    // initialize state
    btn.setAttribute('aria-expanded','true');
    btn.closest('.mk-card')?.classList.remove('mk-collapsed');

    btn.addEventListener('click', ()=>{
      const expanded = btn.getAttribute('aria-expanded') === 'true';
      const now = !expanded;
      btn.setAttribute('aria-expanded', String(now));
      btn.querySelector('.mk-txt').textContent = now ? 'Hide' : 'Show';
      btn.closest('.mk-card')?.classList.toggle('mk-collapsed', !now);
      cfg.pane.classList.toggle('mk-hidden', !now);
    });
  });
}

// call after DOM is ready (your script is at the bottom so DOM is ready)
wireToggles();
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
