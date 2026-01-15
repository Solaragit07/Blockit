<?php
// /public_html/main/dashboard/index.php
// Inline dashboard with MikroTik widgets, reusing sidebar/nav/footer includes + styles safely
// Adds: Quick Block Categories (static presets) that call your existing add_block.php endpoint.

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

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
      --primary-blue:#0dcaf0; --accent-blue:#87e6f7;
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
    .card-hd{display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:rgba(255,255,255,.35); gap:8px; flex-wrap:wrap}
    .card-hd h2{margin:0; font-size:18px; font-weight:800; color:var(--ink); display:flex; gap:8px; align-items:center}
    .pad{padding:12px 14px}
    .controls{display:flex; gap:8px; align-items:center; flex-wrap:wrap}
    .input{padding:8px 10px; border:1px solid #cfe7ef; border-radius:10px; background:#fff; font-size:14px; min-width:160px}
    /* Tables */
    .tbl-wrap{border:1px solid var(--border); border-radius:12px; overflow:hidden; background:#fff}
    table{width:100%; border-collapse:collapse}
    th, td{padding:8px 10px; border-bottom:1px solid #e5f4f8; font-size:14px; text-align:left; background:#fff}
    thead th{background:rgba(13,202,240,.15); color:var(--ink); position:sticky; top:0}
    /* keep checkbox column neat */
    th:first-child, td:first-child { text-align:center; width:32px; }
    /* MikroTik inline module aesthetics remain (not used in these two tables) */
    .mk-btn{padding:8px 10px;border:none;border-radius:10px;background:#087990;color:#fff;font-weight:700;cursor:pointer}
    .mk-btn-ghost{padding:8px 10px;border:1px solid #cfe7ef;border-radius:10px;background:#fff;color:#087990;font-weight:700;cursor:pointer}
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
    #backToTop:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(13,202,240,0.35); }
    #backToTop.show { display: flex; opacity: 1; }
  </style>
</head>
<body id="page-top">

  <!-- Wrapper so we can reuse your shared sidebar/nav/footer includes -->
  <div id="wrapper">
    <?php include '../sidebar.php'; ?>

    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include '../nav.php'; ?>

        <div class="wrap">
          <div class="header">
            <div class="brand">
              <img src="/img/logo1.png" alt="logo">
              <h1>Block & Whitelist</h1>
            </div>
            <div class="actions">
              <!-- <button id="btn-refresh" class="btn btn-ghost"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
              <a class="btn btn-primary" href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a> -->
            </div>
          </div>

          <!-- Quick Block Categories (NEW, static lists calling add_block.php) -->
          <div class="card" id="cat-card">
            <div class="card-hd">
              <h2><i class="fa-solid fa-layer-group"></i> Quick Block Categories</h2>
              <div class="controls">
                <button class="btn btn-primary cat-btn" data-cat="pornsites"><i class="fa-solid fa-ban"></i> Pornsites</button>
                <button class="btn btn-primary cat-btn" data-cat="gambling"><i class="fa-solid fa-dice"></i> Gambling</button>
                <button class="btn btn-primary cat-btn" data-cat="gaming"><i class="fa-solid fa-gamepad"></i> Gaming</button>
                <button class="btn btn-primary cat-btn" data-cat="entertainment"><i class="fa-solid fa-film"></i> Entertainment</button>
                <button class="btn btn-ghost" id="view-cat-list"><i class="fa-solid fa-list"></i> View domains</button>
              </div>
            </div>
            <div class="pad" id="cat-hint" style="display:none">
              <div class="tbl-wrap">
                <table>
                  <thead><tr><th style="width:160px">Category</th><th>Domains</th></tr></thead>
                  <tbody id="cat-domains-body"><tr><td colspan="2">Loading…</td></tr></tbody>
                </table>
              </div>
              <!-- <p style="margin-top:8px;color:#0b4a59">
                Tip: Edit the lists in <code>CATEGORIES</code> (JS below). Each click adds entries via <code>add_block.php</code> with comment -->
                <!-- <em>Category: &lt;name&gt;</em>. Extend as needed.
              </p> -->
            </div>
          </div>

          <div class="grid grid-2">
            <!-- Blocklist -->
            <div class="card">
              <div class="card-hd">
                <h2><i class="fa-solid fa-ban"></i> Blocklist <span id="count-block" class="pill pill-blue">—</span></h2>
                <div class="controls">
                  <input id="in-block" class="input" placeholder="domain or IPv4 / CIDR">
                  <button id="add-block" class="btn btn-primary">Add to Blocklist</button>

                  <input id="comment-filter-block" class="input" placeholder='Comment e.g. "From: www.youtube.com"'>
                  <button id="select-comment-block" class="btn btn-ghost">Select by comment</button>
                  <button id="remove-selected-block" class="btn btn-primary">Remove selected</button>
                  <button id="clear-selected-block" class="btn btn-ghost">Deselect all</button>
                </div>
              </div>
              <div class="pad">
                <div class="tbl-wrap">
                  <table id="tbl-block">
                    <thead>
                      <tr>
                        <th><input type="checkbox" id="sel-all-block" title="Select all"></th>
                        <th>Address</th>
                        <th>Comment</th>
                        <th style="width:160px">Action</th>
                      </tr>
                    </thead>
                    <tbody><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Whitelist -->
            <div class="card">
              <div class="card-hd">
                <h2><i class="fa-solid fa-shield-heart"></i> Whitelist <span id="count-white" class="pill pill-blue">—</span></h2>
                <div class="controls">
                  <input id="in-white" class="input" placeholder="domain or IPv4 / CIDR">
                  <button id="add-white" class="btn btn-primary">Add to Whitelist</button>

                  <input id="comment-filter-white" class="input" placeholder='Comment e.g. "From: www.youtube.com"'>
                  <button id="select-comment-white" class="btn btn-ghost">Select by comment</button>
                  <button id="remove-selected-white" class="btn btn-primary">Remove selected</button>
                  <button id="clear-selected-white" class="btn btn-ghost">Deselect all</button>
                </div>
              </div>
              <div class="pad">
                <div class="tbl-wrap">
                  <table id="tbl-white">
                    <thead>
                      <tr>
                        <th><input type="checkbox" id="sel-all-white" title="Select all"></th>
                        <th>Address</th>
                        <th>Comment</th>
                        <th style="width:160px">Action</th>
                      </tr>
                    </thead>
                    <tbody><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
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

<!-- ======================= SCRIPTS ======================= -->
<script>
/* ==== CONFIG ==== */
const API_KEY = "c6d93fd745d852657b700d865690c8bee8a5fe66104a6248291d54b1e899e0a5";

/* ==== HELPERS ==== */
const esc = s => String(s||'').replace(/</g,'&lt;');
async function getJSON(url, body=null){
  const opt = body ? {
    method: 'POST',
    headers: { 'Content-Type':'application/json','X-API-Key':API_KEY },
    body: JSON.stringify(body)
  } : { headers: { 'X-API-Key': API_KEY } };
  const r = await fetch(url, opt);
  let data; try { data = await r.json(); } catch { data = { ok:false, message:'HTTP '+r.status }; }
  data.httpStatus = r.status;
  return data;
}

/* ==== RENDER ==== */
async function loadLists(){
  const j = await getJSON('./api/address_lists.php');
  if (!j.ok) throw new Error(j.message || 'address_lists failed');

  document.getElementById('count-block').textContent = (j.blocklist || []).length;
  document.getElementById('count-white').textContent = (j.whitelist || []).length;

  const tbBlock = document.querySelector('#tbl-block tbody');
  const tbWhite = document.querySelector('#tbl-white tbody');

  // BLOCKLIST TABLE
  tbBlock.innerHTML = (j.blocklist && j.blocklist.length)
    ? j.blocklist.map(r => {
        const rid  = r['.id'] ?? r['id'] ?? '';
        const addr = r.address || '';
        const cmt  = r.comment || '';
        return `
          <tr>
            <td><input type="checkbox" class="row-check"
                       data-id="${esc(rid)}"
                       data-addr="${esc(addr)}"
                       data-comment="${esc(cmt)}"></td>
            <td>${esc(addr)}</td>
            <td>${esc(cmt)}</td>
            <td>
              <button class="mk-btn-ghost"
                      data-id="${esc(rid)}"
                      data-addr="${esc(addr)}">Remove</button>
            </td>
          </tr>
        `;
      }).join('')
    : '<tr><td colspan="4">Empty</td></tr>';

  // WHITELIST TABLE
  tbWhite.innerHTML = (j.whitelist && j.whitelist.length)
    ? j.whitelist.map(r => {
        const rid  = r['.id'] ?? r['id'] ?? '';
        const addr = r.address || '';
        const cmt  = r.comment || '';
        return `
          <tr>
            <td><input type="checkbox" class="row-check"
                       data-id="${esc(rid)}"
                       data-addr="${esc(addr)}"
                       data-comment="${esc(cmt)}"></td>
            <td>${esc(addr)}</td>
            <td>${esc(cmt)}</td>
            <td>
              <button class="mk-btn-ghost mk-white-remove"
                      data-id="${esc(rid)}"
                      data-addr="${esc(addr)}">Remove</button>
            </td>
          </tr>
        `;
      }).join('')
    : '<tr><td colspan="4">Empty</td></tr>';

  // keep header select-all states in sync after fresh render
  syncSelectAllHeader('tbl-block', 'sel-all-block');
  syncSelectAllHeader('tbl-white', 'sel-all-white');
}

/* ==== SELECTION HELPERS ==== */
function getCheckedRows(tableId){
  return Array.from(document.querySelectorAll(`#${tableId} tbody input.row-check:checked`))
    .map(el => ({ id: el.dataset.id || '', address: el.dataset.addr || '', comment: el.dataset.comment || '', el }));
}
function setAllChecks(tableId, checked){
  document.querySelectorAll(`#${tableId} tbody input.row-check`).forEach(cb => cb.checked = checked);
  const hdrId = tableId === 'tbl-block' ? 'sel-all-block' : 'sel-all-white';
  syncSelectAllHeader(tableId, hdrId);
}
function syncSelectAllHeader(tableId, headerId){
  const boxes = Array.from(document.querySelectorAll(`#${tableId} tbody input.row-check`));
  const hdr   = document.getElementById(headerId);
  if (!hdr) return;
  if (!boxes.length){ hdr.indeterminate = false; hdr.checked = false; return; }
  const all   = boxes.every(cb => cb.checked);
  const none  = boxes.every(cb => !cb.checked);
  hdr.indeterminate = !all && !none;
  hdr.checked = all && !none;
}

/* ==== BULK REMOVE ==== */
async function bulkRemove(rows, url){
  if (!rows.length){ alert('Nothing selected.'); return; }
  if (!confirm(`Remove ${rows.length} selected entr${rows.length>1?'ies':'y'}?`)) return;

  const tasks = rows.map(r =>
    getJSON(url, { id: r.id, address: r.address })
      .then(res => ({ ok: res.httpStatus === 200 || res.ok === true, res, row: r }))
      .catch(err => ({ ok:false, err, row:r }))
  );

  const settled = await Promise.allSettled(tasks);
  const oks = [], fails = [];
  settled.forEach(s => {
    const v = s.value || s.reason || {};
    const { ok, res, row, err } = v;
    if (ok){
      const ips    = res?.removed_ips || res?.removed_block_ips || [];
      const dns    = res?.removed_dns || [];
      const tls    = res?.removed_tls || res?.removed_tls_drops || [];
      const sinks  = res?.removed_sinkholes || [];
      const overs  = res?.removed_overrides || [];
      let detail = [];
      if (ips.length)   detail.push(`IPs: ${ips.join(', ')}`);
      if (dns.length)   detail.push(`DNS: ${dns.join(', ')}`);
      if (tls.length)   detail.push(`TLS: ${tls.join(', ')}`);
      if (sinks.length) detail.push(`DNS Sinkholes: ${sinks.join(', ')}`);
      if (overs.length) detail.push(`DNS Overrides: ${overs.join(', ')}`);
      oks.push(`✓ ${row.address || row.id}${detail.length?'\n   ' + detail.join('\n   '):''}`);
    } else {
      const msg = res?.message ? `(${res.message})` : (err?.message || '');
      fails.push(`✗ ${row.address || row.id} ${msg}`);
    }
  });

  let msg = `Bulk remove completed.\n\nSuccess: ${oks.length}\nFailed: ${fails.length}\n`;
  if (oks.length)   msg += `\n— Removed —\n${oks.join('\n')}\n`;
  if (fails.length) msg += `\n— Failed —\n${fails.join('\n')}\n`;
  alert(msg.trim());

  await loadLists();
}

/* ==== EVENT WIRING ==== */

// Delegated single remove (Blocklist)
const tblBlockEl = document.getElementById('tbl-block');
if (tblBlockEl && !tblBlockEl.__removeBound) {
  tblBlockEl.addEventListener('click', async (e) => {
    const btn = e.target.closest('button.mk-btn-ghost');
    if (!btn || btn.classList.contains('mk-white-remove')) return;
    const id   = (btn.dataset.id || '').trim();
    const addr = (btn.dataset.addr || '').trim();
    btn.disabled = true;
    try {
      const res = await getJSON('./api/remove_block.php', { id, address: addr });
      if (res.httpStatus === 200) {
        const ips   = res.removed_ips       || [];
        const dns   = res.removed_dns       || [];
        const tls   = res.removed_tls       || [];
        const sinks = res.removed_sinkholes || [];
        let msg = res.message || `Removed ${addr || id}`;
        if (ips.length)   msg += `\nIPs: ${ips.join(', ')}`;
        if (dns.length)   msg += `\nDNS: ${dns.join(', ')}`;
        if (tls.length)   msg += `\nTLS: ${tls.join(', ')}`;
        if (sinks.length) msg += `\nDNS Sinkholes: ${sinks.join(', ')}`;
        if (Array.isArray(res.debug) && res.debug.length) msg += `\n\nDebug:\n- ${res.debug.join('\n- ')}`;
        alert(msg);
      } else {
        alert(`Error: ${res.message || 'Remove failed'}`);
        if (res.debug) console.log('Debug:', res.debug);
      }
      await loadLists();
    } catch (err) {
      console.error(err);
      alert('Remove failed (network error).');
    } finally {
      btn.disabled = false;
    }
  });
  tblBlockEl.__removeBound = true;
}

// Delegated single remove (Whitelist)
const tblWhiteEl = document.getElementById('tbl-white');
if (tblWhiteEl && !tblWhiteEl.__removeWhiteBound) {
  tblWhiteEl.addEventListener('click', async (e) => {
    const btn = e.target.closest('button.mk-white-remove');
    if (!btn) return;
    const id   = (btn.dataset.id || '').trim();
    const addr = (btn.dataset.addr || '').trim();
    btn.disabled = true;
    try {
      const res = await getJSON('./api/remove_whitelist.php', { id, address: addr });
      if (res.httpStatus === 200 || res.ok === true) {
        const ips    = res.removed_ips        || [];
        const dns    = res.removed_dns        || [];
        const tls    = res.removed_tls        || res.removed_tls_drops || [];
        const overs  = res.removed_overrides  || [];
        let msg = res.message || `Removed ${addr || id} from whitelist`;
        if (ips.length)   msg += `\nIPs: ${ips.join(', ')}`;
        if (dns.length)   msg += `\nDNS: ${dns.join(', ')}`;
        if (tls.length)   msg += `\nTLS: ${tls.join(', ')}`;
        if (overs.length) msg += `\nDNS Overrides: ${overs.join(', ')}`;
        if (Array.isArray(res.debug) && res.debug.length) msg += `\n\nDebug:\n- ${res.debug.join('\n- ')}`;
        alert(msg);
      } else {
        alert(`Error: ${res.message || 'Whitelist remove failed'}`);
        if (res.debug) console.log('Debug:', res.debug);
      }
      await loadLists();
    } catch (err) {
      console.error(err);
      alert('Whitelist remove failed (network error).');
    } finally {
      btn.disabled = false;
    }
  });
  tblWhiteEl.__removeWhiteBound = true;
}

// Select-all headers
document.getElementById('sel-all-block')?.addEventListener('change', e => {
  setAllChecks('tbl-block', e.target.checked);
});
document.getElementById('sel-all-white')?.addEventListener('change', e => {
  setAllChecks('tbl-white', e.target.checked);
});

// Row checkbox changes keep header indeterminate state correct
['tbl-block','tbl-white'].forEach((tid) => {
  const hdrId = tid === 'tbl-block' ? 'sel-all-block' : 'sel-all-white';
  const tbl = document.getElementById(tid);
  if (tbl && !tbl.__syncBound){
    tbl.addEventListener('change', e => {
      if (e.target.matches('input.row-check')){
        syncSelectAllHeader(tid, hdrId);
      }
    });
    tbl.__syncBound = true;
  }
});

// Deselect all
document.getElementById('clear-selected-block')?.addEventListener('click', () => {
  setAllChecks('tbl-block', false);
});
document.getElementById('clear-selected-white')?.addEventListener('click', () => {
  setAllChecks('tbl-white', false);
});

// Select by comment (case-insensitive contains)
document.getElementById('select-comment-block')?.addEventListener('click', () => {
  const q = (document.getElementById('comment-filter-block')?.value || '').toLowerCase();
  document.querySelectorAll('#tbl-block tbody input.row-check').forEach(cb => {
    const cmt = (cb.dataset.comment || '').toLowerCase();
    cb.checked = !!q && cmt.includes(q);
  });
  syncSelectAllHeader('tbl-block', 'sel-all-block');
});
document.getElementById('select-comment-white')?.addEventListener('click', () => {
  const q = (document.getElementById('comment-filter-white')?.value || '').toLowerCase();
  document.querySelectorAll('#tbl-white tbody input.row-check').forEach(cb => {
    const cmt = (cb.dataset.comment || '').toLowerCase();
    cb.checked = !!q && cmt.includes(q);
  });
  syncSelectAllHeader('tbl-white', 'sel-all-white');
});

// Bulk remove buttons
document.getElementById('remove-selected-block')?.addEventListener('click', async () => {
  const rows = getCheckedRows('tbl-block');
  await bulkRemove(rows, './api/remove_block.php');
});
document.getElementById('remove-selected-white')?.addEventListener('click', async () => {
  const rows = getCheckedRows('tbl-white');
  await bulkRemove(rows, './api/remove_whitelist.php');
});

/* ==== ADD FLOWS (unchanged) ==== */
document.getElementById('add-block')?.addEventListener('click', async ()=>{
  const el = document.getElementById('in-block');
  const v = el ? el.value.trim() : '';
  if (!v) return;
  try{
    const res = await getJSON('./api/add_block.php', { address: v, comment: 'From: ' + v });
    if (res.ok){
      let msg = `Blocked: ${res.input}\n`;
      if (Array.isArray(res.added_ips) && res.added_ips.length)
        msg += 'Firewall entries:\n' + res.added_ips.map(x=>'  - '+x).join('\n') + '\n';
      if (Array.isArray(res.added_sinkholes) && res.added_sinkholes.length)
        msg += 'DNS sinkholes:\n' + res.added_sinkholes.map(x=>'  - '+x).join('\n') + '\n';
      if (Array.isArray(res.added_tls) && res.added_tls.length)
        msg += 'TLS rules:\n' + res.added_tls.map(x=>'  - '+x).join('\n') + '\n';
      alert(msg.trim());
      if (el) el.value = '';
      loadLists();
    } else {
      alert('Error: ' + (res.message || 'Unknown error'));
    }
  }catch(e){
    console.error(e);
    alert('Add failed');
  }
});

const addWhiteBtn = document.getElementById('add-white');
if (addWhiteBtn && !addWhiteBtn.__bound) {
  addWhiteBtn.addEventListener('click', async ()=>{
    const inputEl = document.getElementById('in-white');
    const v = (inputEl?.value || '').trim();
    if (!v) return;

    try{
      const res = await getJSON('./api/add_whitelist.php', { address: v, comment: 'From: ' + v });
      if (res.ok){
        let msg = `Whitelisted: ${res.input}\n`;

        const addedIps         = res.added_ips || [];
        const tlsAccept        = res.added_tls_accept || [];
        const removedBlockIps  = res.removed_block_ips || [];
        const removedSinks     = res.removed_sinkholes || [];
        const removedDrops     = res.removed_tls_drops || [];

        if (addedIps.length)
          msg += 'Firewall whitelist entries:\n' + addedIps.map(x=>'  - '+x).join('\n') + '\n';
        if (tlsAccept.length)
          msg += 'TLS ACCEPT rules:\n' + tlsAccept.map(x=>'  - '+x).join('\n') + '\n';
        if (removedBlockIps.length)
          msg += 'Removed from blocklist:\n' + removedBlockIps.map(x=>'  - '+x).join('\n') + '\n';
        if (removedSinks.length)
          msg += 'Removed DNS sinkholes:\n' + removedSinks.map(x=>'  - '+x).join('\n') + '\n';
        if (removedDrops.length)
          msg += 'Removed TLS DROP rules:\n' + removedDrops.map(x=>'  - '+x).join('\n') + '\n';

        alert(msg.trim());
        if (inputEl) inputEl.value = '';
        loadLists();
      } else {
        alert('Error: ' + (res.message || 'Unknown error'));
      }
    }catch(e){
      console.error(e);
      alert('Whitelist add failed');
    }
  });
  addWhiteBtn.__bound = true;
}

/* ==== Misc wiring ==== */
document.getElementById('btn-refresh')?.addEventListener('click', loadLists);

// Enter to add
const inBlock = document.getElementById('in-block');
if (inBlock && !inBlock.__enterBound) {
  inBlock.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('add-block')?.click(); });
  inBlock.__enterBound = true;
}
const inWhite = document.getElementById('in-white');
if (inWhite && !inWhite.__enterBound) {
  inWhite.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('add-white')?.click(); });
  inWhite.__enterBound = true;
}
</script>

<!-- ===== Quick Block Categories JS (static lists + handlers) ===== -->
<script>
/* ==== CATEGORY DEFINITIONS (static) ==== */
/* ===== Category Packs (v1, FULL URLs) ===== */
const CATEGORIES = {
  pornsites: [
    "https://www.pornhub.com/",
    "https://www.xvideos.com/",
    "https://www.xnxx.com/",
    "https://xhamster.com/",
    "https://www.redtube.com/",
    "https://www.youporn.com/",
    "https://www.spankbang.com/"
  ],
  gambling: [
    "https://www.bet365.com/",
    "https://www.1xbet.com/",
    "https://www.pokerstars.com/",
    "https://www.betfair.com/",
    "https://www.williamhill.com/",
    "https://www.draftkings.com/",
    "https://www.fanduel.com/",
    "https://www.betway.com/",
    "https://www.888.com/",
    "https://gg.bet/"
  ],
  entertainment: [
    "https://www.netflix.com/",
    "https://www.hulu.com/",
    "https://www.disneyplus.com/",
    "https://www.primevideo.com/",
    "https://www.hbo.com/",
    "https://www.max.com/",
    "https://www.crunchyroll.com/",
    "https://www.viu.com/",
    "https://www.iq.com/",
    "https://www.bilibili.com/",
    "https://www.tiktok.com/",
    "m.tiktok.com",
    "https://www.tiktokv.com/",
    "https://www.tiktokcdn.com/",
    "v16m.tiktokcdn.com",
    "api.tiktok.com",
    "https://www.byteoversea.com/",
    "https://www.musical.ly/",
    "https://www.facebook.com/",
    "m.facebook.com",
    "graph.facebook.com",
    "static.xx.fbcdn.net",
    "scontent.xx.fbcdn.net",
    "connect.facebook.net",
    "fbcdn.net",
    "edge-mqtt.facebook.com",
    "b-graph.facebook.com",
    "star.c10r.facebook.com",
    "messenger.com",
    "m.me",
    "https://www.youtube.com/",
    "m.youtube.com",
    "youtu.be",
    "i.ytimg.com",
    "s.ytimg.com",
    "r*.sn-*.googlevideo.com",   // wildcard-ish; your backend normalizes to host
    "googlevideo.com",
    "youtubei.googleapis.com",   // YouTube app API
    "youtube.googleapis.com"
  ],
  gaming: [
    "https://store.steampowered.com/",
    "https://www.epicgames.com/",
    "https://www.roblox.com/",
    "https://www.minecraft.net/",
    "https://www.playstation.com/",
    "https://www.xbox.com/",
    "https://www.riotgames.com/",
    "https://www.leagueoflegends.com/",
    "https://playvalorant.com/",
    "https://www.mobilelegends.com/",
    "ml.youngjoygame.com",
    "mlbb.mobilelegends.com",
    "sdk.moonton.com",
    "api.moonton.com",
    "moonton.com",
    "dl.mobilelegends.com",
    "mlbb.com"
  ]
};

const prettyCat = k => k.charAt(0).toUpperCase() + k.slice(1);

/* Optional viewer for lists */
(function wireCategoryViewer(){
  const btn = document.getElementById('view-cat-list');
  const hint = document.getElementById('cat-hint');
  const body = document.getElementById('cat-domains-body');
  if (!btn || !hint || !body) return;

  btn.addEventListener('click', () => {
    if (hint.style.display === 'none'){
      body.innerHTML = Object.keys(CATEGORIES).map(cat => {
        const rows = CATEGORIES[cat].map(d => esc(d)).join(', ');
        return `<tr><td><strong>${prettyCat(cat)}</strong></td><td>${rows || '<em>None</em>'}</td></tr>`;
      }).join('');
      hint.style.display = 'block';
      btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide domains';
    } else {
      hint.style.display = 'none';
      btn.innerHTML = '<i class="fa-solid fa-list"></i> View domains';
    }
  });
})();

/* Category bulk add -> add_block.php per domain */
async function blockCategory(cat){
  const domains = CATEGORIES[cat] || [];
  if (!domains.length){ alert('No domains configured for ' + prettyCat(cat)); return; }

  const confirmMsg =
    `Block all ${domains.length} domains in “${prettyCat(cat)}”?`;
  if (!confirm(confirmMsg)) return;

  const btn = document.querySelector(`.cat-btn[data-cat="${cat}"]`);
  const oldLabel = btn ? btn.innerHTML : '';
  if (btn){ btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Working…'; }

  const chunkSize = 5;
  const results = [];
  try{
    for (let i = 0; i < domains.length; i += chunkSize){
      const slice = domains.slice(i, i + chunkSize);
      const batch = await Promise.allSettled(slice.map(domain => {
        const payload = { address: domain, comment: `Category: ${prettyCat(cat)} | From: ${domain}` };
        return getJSON('./api/add_block.php', payload).then(res => ({ domain, res }));
      }));
      results.push(...batch);
      await new Promise(r => setTimeout(r, 150));
    }

    const oks = [], fails = [];
    results.forEach(item => {
      if (item.status === 'fulfilled' && (item.value.res?.ok || item.value.res?.httpStatus === 200)){
        const d = item.value.domain;
        const r = item.value.res;
        let detail = [];
        if (Array.isArray(r.added_ips) && r.added_ips.length) detail.push(`IPs: ${r.added_ips.join(', ')}`);
        if (Array.isArray(r.added_sinkholes) && r.added_sinkholes.length) detail.push(`DNS sinkholes: ${r.added_sinkholes.join(', ')}`);
        if (Array.isArray(r.added_tls) && r.added_tls.length) detail.push(`TLS rules: ${r.added_tls.join(', ')}`);
        oks.push(`✓ ${d}${detail.length ? '\n   ' + detail.join('\n   ') : ''}`);
      } else {
        const d = (item.value && item.value.domain) || 'unknown';
        const msg = (item.value && item.value.res && (item.value.res.message || item.value.res.httpStatus)) || (item.reason && item.reason.message) || 'Failed';
        fails.push(`✗ ${d} (${msg})`);
      }
    });

    let msg = `Category block: ${prettyCat(cat)}\n\nSuccess: ${oks.length}\nFailed: ${fails.length}\n`;
    if (oks.length)   msg += `\n— Added —\n${oks.join('\n')}\n`;
    if (fails.length) msg += `\n— Failed —\n${fails.join('\n')}\n`;
    alert(msg.trim());

    await loadLists();
  } catch (e){
    console.error(e);
    alert('Bulk category add failed (unexpected error).');
  } finally {
    if (btn){ btn.disabled = false; btn.innerHTML = oldLabel; }
  }
}

/* Wire category buttons */
(function wireCategoryButtons(){
  document.querySelectorAll('.cat-btn').forEach(b => {
    if (b.__bound) return;
    b.addEventListener('click', () => {
      const cat = b.getAttribute('data-cat');
      if (!cat) return;
      blockCategory(cat);
    });
    b.__bound = true;
  });
})();
</script>

<!-- ===== Sidebar toggle behavior (unchanged) ===== -->
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

<!-- ===== Back-to-top button (unchanged) ===== -->
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

// Initial load at the very end, after all functions exist:
(async()=>{ try{ await loadLists(); }catch(e){ console.error(e); } })();
</script>

</body>
</html>
