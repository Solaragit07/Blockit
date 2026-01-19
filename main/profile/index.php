<?php
// /public_html/main/profile/index.php

$APP_ROOT = dirname(__DIR__, 2); // /public_html
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';
if (function_exists('require_login')) { require_login(); }
if (session_status() === PHP_SESSION_NONE) session_start();

/* === CSRF helper === */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$SESSION_API_KEY = $_SESSION['blockit_api_key'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>BlockIT › Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>"/>
  <meta name="api-key" content="<?php echo htmlspecialchars($SESSION_API_KEY, ENT_QUOTES); ?>"/>

  <!-- Load shared styles in a lower layer -->
  <style>
    @layer sbadmin;
    @import url("/css/sb-admin-2.min.css") layer(sbadmin);
    @import url("/css/custom-color-palette.css") layer(sbadmin);
  </style>

  <!-- Page styles (MOBILE-FIRST) -->
  <style>
    :root{
      --bg1:#0dcaf0; --bg2:#087990; --card:#e9f8fe;
      --ink:#063c4a; --muted:#0b4a59; --border:rgba(13,202,240,.35);
      --surface:#fff; --thead:#b6effb;

      /* ADDED: safe fallbacks for backToTop gradient */
      --primary-blue: #0dcaf0;
      --accent-blue: #087990;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { height: 100%; width: 100%; }
    body{
      margin:0;
      font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:linear-gradient(135deg,var(--bg1),var(--bg2));
      background-size:300% 300%;
      min-height:100vh;
      color:#0a1a21;
    }

    /* Ensure SB-Admin wrappers don't constrain width */
    #wrapper, #content-wrapper, #content { width:100%; min-height:100vh; margin:0; padding:0; background:transparent; }

    /* Page container */
    .wrap{
      width:100%;
      max-width:100%;
      margin:0;
      padding:12px;
    }

    /* Header */
    .header{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      background:rgba(255,255,255,.45);
      border:1px solid var(--border); border-radius:16px;
      padding:12px;
      box-shadow:0 10px 36px rgba(0,0,0,.12);
      margin-bottom:12px;
    }
    .header h1{
      margin:0; color:var(--ink); font-weight:800;
      font-size: clamp(1.05rem, 1.2vw + .8rem, 1.35rem);
      display:flex; align-items:center; gap:.6rem;
    }

    /* Cards */
    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      box-shadow:0 10px 32px rgba(0,0,0,.12);
      overflow:hidden;
      margin-bottom:12px;
    }
    .card-hd{
      display:flex; align-items:center; gap:.6rem;
      padding:12px 14px; background:rgba(255,255,255,.55);
      color:var(--ink); font-weight:800; font-size: clamp(.95rem, .4vw + .8rem, 1.05rem);
    }
    .pad{ padding:12px 14px; }

    /* Controls row – stacks on mobile */
    .controls{
      display:grid; gap:8px;
      grid-template-columns: 1fr;
      align-items: start;
    }
    @media (min-width: 720px){
      .controls{ grid-template-columns: repeat(3, 1fr); }
      .controls > .wide { grid-column: span 2; }
    }
    @media (min-width: 1050px){
      .controls{ grid-template-columns: repeat(4, 1fr); }
    }
    .input{
      width:100%; min-width:0;
      padding:10px 12px; border:1px solid #cfe7ef; border-radius:10px; background:#fff; font-size:14px;
    }
    .btn{
      padding:10px 12px; border:none; border-radius:10px; font-weight:800; cursor:pointer;
      display:inline-flex; align-items:center; gap:.5rem; justify-content:center;
    }
    .btn[disabled]{opacity:.6; cursor:not-allowed}
    .btn-ghost{ background:#fff; color:#087990; border:1px solid #cfe7ef }
    .btn-primary{ background:#087990; color:#fff }

    /* Responsive “two columns” sections */
    .two-col{
      display:grid; gap:12px;
      grid-template-columns: 1fr;
    }
    @media (min-width: 900px){
      .two-col{ grid-template-columns: 1fr 1fr; }
    }

    /* Tables: sticky head + horizontal scroll on small screens */
    .scroll{
      max-height: 42vh;
      overflow:auto;
      background: var(--surface);
      border:1px solid #cfe7ef;
      border-radius:10px;
    }
    .mk-table{
      width:100%;
      table-layout: fixed;
      border-collapse: collapse;
    }
    .mk-table th, .mk-table td{
      padding:10px 12px;
      border-bottom:1px solid #e5f4f8;
      font-size:14px;
      text-align:left;
      background:#fff;
      vertical-align:middle;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .mk-table thead th{
      position:sticky; top:0; z-index:1;
      background: var(--thead);
      color: var(--ink);
    }
    .mk-table th:first-child, .mk-table td:first-child { text-align:center; width:36px; }

    /* Allow long MAC lists to wrap while keeping other columns compact */
    .macs-cell{
      white-space: normal;
      word-break: break-all;
    }
    .actions-cell{
      white-space: nowrap;
    }

    /* Column sizing for profile table */
    #tbl-profiles th:nth-child(1), #tbl-profiles td:nth-child(1){ width: 26%; }
    #tbl-profiles th:nth-child(2), #tbl-profiles td:nth-child(2){ width: 16%; }
    #tbl-profiles th:nth-child(3), #tbl-profiles td:nth-child(3){ width: 38%; }
    #tbl-profiles th:nth-child(4), #tbl-profiles td:nth-child(4){ width: 20%; }

    h3, h4{
      margin: 0 0 .5rem 0;
      color: var(--ink);
      font-weight: 800;
      font-size: clamp(.95rem, .35vw + .85rem, 1.05rem);
    }

    .pill{display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700}
    .pill-blue{background:#e0f7ff; color:#075a6d; border:1px solid #bfe8f6}

    /* Motion-safe */
    @media (prefers-reduced-motion:no-preference){
      .fade-in{animation:fade .45s ease-out both}
      @keyframes fade{from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:translateY(0)}}
    }

    /* Tiny devices polish */
    @media (max-width: 380px){
      .btn{ padding:9px 10px; }
      .input{ padding:9px 10px; }
    }
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

      <div class="wrap fade-in">
        <div class="header">
          <h1><i class="fa-solid fa-ban"></i> BlockIT Profile</h1>
          <!-- optional action
          <a class="btn btn-primary" href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a> -->
        </div>

        <!-- Profiles -->
        <section class="two-col">
          <!-- Left card: create/sync + connected devices -->
          <div class="card">
            <div class="card-hd">
              <i class="fa-solid fa-users"></i> Profiles &amp; Age Groups
            </div>
            <!-- body -->
            <div class="pad">

              <!-- =========================
                   ADDED: Create / Save Profile UI
                   ========================= -->
              <div class="controls" style="margin-bottom:12px">
                <input id="profile-name" class="input wide" placeholder="Profile name (e.g., Juan’s iPad)">
                <select id="profile-group" class="input" aria-label="Age group">
                  <option value="over18">Over 18</option>
                  <option value="under18">Under 18</option>
                </select>
                <button id="btn-sync-profiles" class="btn btn-primary">
                  <i class="fa-solid fa-floppy-disk"></i> Save to Router
                </button>
              </div>
              <p class="muted" style="margin:6px 2px 12px;color:var(--muted);font-size:12px">
                Tip: check one or more devices below to attach their MACs to this profile.
              </p>
              <!-- =========================
                   /ADDED UI
                   ========================= -->

              <h4>Connected Devices</h4>
              <div class="scroll">
                <table class="mk-table" id="tbl-devices">
                  <thead>
                    <tr>
                      <th></th>
                      <th>Name</th>
                      <th>IP</th>
                      <th>MAC</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr><td colspan="4">Loading…</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Right card: saved profiles -->
          <div class="card">
            <div class="card-hd">
              Saved Profiles
            </div>

            <div class="pad">
              <h4>Profiles</h4>
              <div class="scroll">
                <table class="mk-table" id="tbl-profiles">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Group</th>
                      <th>MACs</th>
                      <th>Actions</th> <!-- ADDED -->
                    </tr>
                  </thead>
                  <tbody>
                    <tr><td colspan="4">Loading…</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <!-- Grouped Rules -->
        <section class="two-col">
          <div class="card">
            <div class="card-hd">Over 18 Rules</div>

            <div class="pad controls">
              <input id="in-over18" class="input wide" placeholder="Domain, URL, or IP (over18)">
              <button id="in-over18-block" class="btn btn-ghost">Add Block</button>
              <button id="in-over18-white" class="btn btn-primary">Add Whitelist</button>

              <!-- ADDED: category selector + Block Category button -->
              <select id="cat-over18" class="input">
                <option value="Porn">Pornsites</option>
                <option value="Gambling">Gambling</option>
                <option value="Entertainment">Entertainment</option>
                <option value="Gaming">Gaming</option>
              </select>
              <button id="cat-over18-block" class="btn btn-primary">Block Category</button>

              <input id="filter-over18" class="input wide" placeholder='Comment filter e.g. "From: www.youtube.com" or "cat: gambling"'>
              <button id="select-comment-over18-block" class="btn btn-ghost" title="Select blocked by comment">Select Blocked</button>
              <button id="select-comment-over18-white" class="btn btn-ghost" title="Select Whitelisted by comment">Select Whitelisted</button>
              <button id="remove-selected-over18" class="btn btn-primary">Remove selected</button>
              <button id="clear-selected-over18" class="btn btn-ghost">Deselect all</button>
            </div>

            <div class="pad">
              <h4>Blocked</h4>
              <div class="scroll">
                <table class="mk-table" id="tbl-over18-block">
                  <!-- UPDATED: added Category column -->
                  <thead><tr>
                    <th><input type="checkbox" id="sel-all-over18-block" title="Select all"></th>
                    <th>Address</th>
                    <th>Category</th>
                    <th>Comment</th>
                    <th>Action</th>
                  </tr></thead>
                  <tbody></tbody>
                </table>
              </div>

              <h4 style="margin-top:12px">Whitelisted</h4>
              <div class="scroll">
                <table class="mk-table" id="tbl-over18-white">
                  <!-- UPDATED: added Category column -->
                  <thead><tr>
                    <th><input type="checkbox" id="sel-all-over18-white" title="Select all"></th>
                    <th>Address</th>
                    <th>Category</th>
                    <th>Comment</th>
                    <th>Action</th>
                  </tr></thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-hd">Under 18 Rules</div>

            <div class="pad controls">
              <input id="in-under18" class="input wide" placeholder="Domain, URL, or IP (under18)">
              <button id="in-under18-block" class="btn btn-ghost">Add Block</button>
              <button id="in-under18-white" class="btn btn-primary">Add Whitelist</button>

              <!-- ADDED: category selector + Block Category button -->
              <select id="cat-under18" class="input">
                <option value="Porn">Pornsites</option>
                <option value="Gambling">Gambling</option>
                <option value="Entertainment">Entertainment</option>
                <option value="Gaming">Gaming</option>
              </select>
              <button id="cat-under18-block" class="btn btn-primary">Block Category</button>

              <input id="filter-under18" class="input wide" placeholder='Comment filter e.g. "From: www.youtube.com" or "cat: gaming"'>
              <button id="select-comment-under18-block" class="btn btn-ghost" title="Select blocked by comment">Select Blocked</button>
              <button id="select-comment-under18-white" class="btn btn-ghost" title="Select Whitelisted by comment">Select Whitelisted</button>
              <button id="remove-selected-under18" class="btn btn-primary">Remove selected</button>
              <button id="clear-selected-under18" class="btn btn-ghost">Deselect all</button>
            </div>

            <div class="pad">
              <h4>Blocked</h4>
              <div class="scroll">
                <table class="mk-table" id="tbl-under18-block">
                  <!-- UPDATED: added Category column -->
                  <thead><tr>
                    <th><input type="checkbox" id="sel-all-under18-block" title="Select all"></th>
                    <th>Address</th>
                    <th>Category</th>
                    <th>Comment</th>
                    <th>Action</th>
                  </tr></thead>
                  <tbody></tbody>
                </table>
              </div>

              <h4 style="margin-top:12px">Whitelisted</h4>
              <div class="scroll">
                <table class="mk-table" id="tbl-under18-white">
                  <!-- UPDATED: added Category column -->
                  <thead><tr>
                    <th><input type="checkbox" id="sel-all-under18-white" title="Select all"></th>
                    <th>Address</th>
                    <th>Category</th>
                    <th>Comment</th>
                    <th>Action</th>
                  </tr></thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </div> <!-- /.wrap -->
      <button id="backToTop" aria-label="Back to top">
  <i class="fas fa-chevron-up"></i>
</button>
    </div> <!-- /#content -->
  </div> <!-- /#content-wrapper -->
</div> <!-- /#wrapper -->

<script>
'use strict';

/* ===== meta tokens (ADDED) ===== */
const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content || '';
const APIKY = document.querySelector('meta[name="api-key"]')?.content || '';

/* ===== API endpoints =====
   Profiles are served by /main/blocklist/api/
   Devices & group rules remain in this page’s ./API/ folder
*/
const PROFILES_BASE = './API/';
const PROFILE_PAGE_BASE = './API/';

const ENDPOINTS = {
  // local to /main/profile/API/
  devices:          PROFILE_PAGE_BASE + 'active_devices.php',
  groupGet:         PROFILE_PAGE_BASE + 'group_rules_get.php',
  groupAddBlock:    PROFILE_PAGE_BASE + 'group_add_block.php',
  groupAddWhite:    PROFILE_PAGE_BASE + 'group_add_whitelist.php',
  groupRmBlock:     PROFILE_PAGE_BASE + 'group_remove_block.php',
  groupRmWhite:     PROFILE_PAGE_BASE + 'group_remove_whitelist.php',

  // in /main/blocklist/api/
  profilesGet:      PROFILES_BASE + 'profiles_get.php',
  profilesSave:     PROFILES_BASE + 'profiles_save.php',
  profilesDelete:   PROFILES_BASE + 'profiles_delete.php', // ADDED
};

const DEFAULT_GROUP = 'over18';

/* ===== HTTP helpers (resilient JSON + diagnostics) ===== */
async function readMaybeJSON(res, url){
  const text = await res.text();
  const ct = res.headers.get('content-type') || '';
  try {
    const data = JSON.parse(text);
    if (!res.ok) data.ok = false;
    data.httpStatus = res.status;
    data.url = url;
    return data;
  } catch {
    return {
      ok:false, message:'Bad JSON', httpStatus:res.status, url,
      contentType: ct, raw: text.slice(0, 1000)
    };
  }
}
async function postJSON(url, payload){
  try{
    const r = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept':'application/json',
        /* ADDED: auth & CSRF */
        'X-CSRF-Token': CSRF,
        'X-API-KEY': APIKY
      },
      body: JSON.stringify(payload ?? {})
    });
    return await readMaybeJSON(r, url);
  }catch(e){
    console.error('fetch error', url, e);
    return { ok:false, message:'Network error', url };
  }
}
async function getJSON(url){
  try{
    const r = await fetch(url, {
      headers: {
        'Accept':'application/json',
        /* ADDED: auth & CSRF */
        'X-CSRF-Token': CSRF,
        'X-API-KEY': APIKY
      }
    });
    return await readMaybeJSON(r, url);
  }catch(e){
    console.error('fetch error', url, e);
    return { ok:false, message:'Network error', url };
  }
}

/* ===== small utils ===== */
const esc = s => String(s ?? '')
  .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');

function setLoading(tbodySel, cols=3){
  const el = document.querySelector(tbodySel);
  if (el) el.innerHTML = `<tr><td colspan="${cols}">Loading…</td></tr>`;
}

/* ===== Devices + Profiles ===== */
async function loadDevices(){
  const tbSel = '#tbl-devices tbody';
  const tb = document.querySelector(tbSel);
  if (!tb) return;
  setLoading(tbSel, 4);

  const j = await getJSON(ENDPOINTS.devices);
  if (!j.ok){
    tb.innerHTML = `<tr><td colspan="4">${esc(j.message||'Load failed')}</td></tr>`;
    console.debug('devices debug:', j.raw || j);
    return;
  }

  const rows = (j.clients||[]).map(c => `
    <tr>
      <td><input type="checkbox" class="pick-mac" data-mac="${esc((c.mac||'').toLowerCase())}"></td>
      <td>${esc(c.name||'—')}</td>
      <td>${esc(c.ip||'—')}</td>
      <td><code>${esc((c.mac||'').toLowerCase())}</code></td>
    </tr>
  `);
  tb.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="4">No devices</td></tr>';
}

async function loadProfiles(){
  const tbSel = '#tbl-profiles tbody';
  const tb = document.querySelector(tbSel);
  if (!tb) return;
  setLoading(tbSel, 4); // ADDED: 4 cols now

  const j = await getJSON(ENDPOINTS.profilesGet);
  if (!j.ok){
    tb.innerHTML = `<tr><td colspan="4">${esc(j.message||'Load failed')}</td></tr>`;
    console.debug('profilesGet debug:', j.raw || j);
    return;
  }

  const rows = (j.profiles||[]).map(p => `
    <tr>
      <td>${esc(p.name)}</td>
      <td>${esc(p.group)}</td>
      <td class="macs-cell" style="font-family:ui-monospace,monospace">${(p.macs||[]).map(m=>`<code>${esc(m)}</code>`).join(', ') || '—'}</td>
      <td class="actions-cell">
        <button class="btn btn-ghost btn-del-prof" data-name="${esc(p.name)}" title="Delete profile">
          <i class="fa-solid fa-trash"></i> Delete
        </button>
      </td>
    </tr>
  `);
  tb.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="4">No profiles yet</td></tr>';
}

async function saveProfileAndSync(){
  const name  = (document.getElementById('profile-name')?.value || '').trim();
  const group = (document.getElementById('profile-group')?.value || 'over18').trim();
  const macs  = Array.from(document.querySelectorAll('.pick-mac:checked')).map(x=>x.dataset.mac).filter(Boolean);

  if (!name){ alert('Enter a profile name'); return; }

  const res = await postJSON(ENDPOINTS.profilesSave, { name, group, macs });
  console.debug('profilesSave response:', res);
  if (!res.ok){
    alert((res.message || 'Save failed') + (res.raw ? `\n\nRaw:\n${res.raw.substring(0,400)}` : ''));
    return;
  }
  /* UPDATED: show server message (covers router unreachable case) */
  alert(res.message || 'Saved! Router synced.');
  await loadProfiles();
}

/* ADDED: delete profile */
async function deleteProfile(name){
  if (!name) return;
  if (!confirm(`Delete profile "${name}"?\nThis will remove it from the website and re-sync MikroTik address-lists.`)) return;

  const res = await postJSON(ENDPOINTS.profilesDelete, { name });
  console.debug('profilesDelete response:', res);

  if (!res.ok){
    alert((res.message || 'Delete failed') + (res.raw ? `\n\nRaw:\n${res.raw.substring(0,400)}` : ''));
    return;
  }
  alert(res.message || 'Profile deleted.');
  await loadProfiles();
  // Optional: refresh rules to reflect re-sync changes
  await loadGroupRules('over18');
  await loadGroupRules('under18');
}

/* ===== Category Packs (v1) ===== *//* ADDED */
const CATEGORY_PACKS = {
  Porn: [
    "https://www.pornhub.com/",
    "https://www.xvideos.com/",
    "https://www.xnxx.com/",
    "https://xhamster.com/",
    "https://www.redtube.com/",
    "https://www.youporn.com/",
    "https://www.spankbang.com/"
  ],
  Gambling: [
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
  Entertainment: [
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
    "tiktokv.com",
    "tiktokcdn.com",
    "v16m.tiktokcdn.com",
    "api.tiktok.com",
    "byteoversea.com",
    "musical.ly",
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
    "https://m.youtube.com/",
    "https://youtu.be/",
    "https://i.ytimg.com/",
    "https://s.ytimg.com/",
    "https://r*.sn-*.googlevideo.com/",
    "https://googlevideo.com/",
    "https://youtubei.googleapis.com/",
    "https://youtube.googleapis.com/"
  ],
  Gaming: [
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
/* ===== Group rules (per group) ===== */
function renderGroupTable(group, data){
  // UPDATED: now includes Category column + data-category attribute
  const mapRows = (itemsIp, sel, which) => {
    const tb = document.querySelector(sel); if (!tb) return;

    const getCat = (cmt) => {
      const m = String(cmt || '').match(/(?:^|;)\s*Cat:\s*([A-Za-z0-9 _-]+)/i);
      return m ? m[1] : '—';
    };

    const rows = (itemsIp||[]).map(r=>({
      ip: r.address,
      id: r.id,
      comment: r.comment || '',
      category: getCat(r.comment)
    }));

    tb.innerHTML = rows.length ? rows.map(r=>`
      <tr>
        <td><input type="checkbox" class="row-check"
                   data-group="${group}"
                   data-which="${which}"
                   data-id="${esc(r.id||'')}"
                   data-val="${esc(r.ip)}"
                   data-comment="${esc(r.comment)}"
                   data-category="${esc(r.category)}"></td>
        <td>${esc(r.ip)}</td>
        <td><span class="pill pill-blue">${esc(r.category)}</span></td>
        <td>${esc(r.comment)}</td>
        <td>
          <button class="rm-${which}"
                  data-group="${group}"
                  data-id="${esc(r.id||'')}"
                  data-val="${esc(r.ip)}">Remove</button>
        </td>
      </tr>
    `).join('') : '<tr><td colspan="5">Empty</td></tr>';
  };

  mapRows(data.blocks?.ip,  `#tbl-${group}-block tbody`, 'block');
  mapRows(data.whites?.ip,  `#tbl-${group}-white tbody`, 'white');

  syncSelectAllHeader(`#tbl-${group}-block`, `#sel-all-${group}-block`);
  syncSelectAllHeader(`#tbl-${group}-white`, `#sel-all-${group}-white`);
}

async function loadGroupRules(group){
  // UPDATED: loaders now expect 5 columns (Address, Category, Comment, Action + checkbox)
  setLoading(`#tbl-${group}-block tbody`, 5);
  setLoading(`#tbl-${group}-white tbody`, 5);
  const j = await getJSON(`${ENDPOINTS.groupGet}?group=${encodeURIComponent(group)}`);
  console.debug('groupGet', group, j);
  if (!j.ok){
    const msg = esc(j.message||'Load failed');
    const b = document.querySelector(`#tbl-${group}-block tbody`);
    const w = document.querySelector(`#tbl-${group}-white tbody`);
    if (b) b.innerHTML = `<tr><td colspan="5">${msg}</td></tr>`;
    if (w) w.innerHTML = `<tr><td colspan="5">${msg}</td></tr>`;
    return;
  }
  renderGroupTable(group, j);
}

async function addRule(group, which){
  // reuse single inputs (over18/under18)
  const inputId = group==='over18' ? 'in-over18' : 'in-under18';
  const box = document.getElementById(inputId);
  const raw = (box?.value || '').trim();
  if (!raw) { alert('Enter a domain/URL/IP'); return; }

  const ep  = (which==='block') ? ENDPOINTS.groupAddBlock : ENDPOINTS.groupAddWhite;
  // UPDATED: keep comment simple for manual adds; category is set by pack adds
  const res = await postJSON(ep, { group, address: raw, comment: `UI:${raw}` });
  console.debug('addRule', group, which, res);

  if (!res.ok){
    alert((res.message || 'Add failed') + (res.raw ? `\n\nRaw:\n${res.raw.substring(0,400)}` : ''));
    return;
  }
  if (box) box.value = '';
  await loadGroupRules(group);
}

async function removeRule(group, which, id, val){
  if (!confirm(`Remove ${val||id} from ${group} ${which}?`)) return;
  const ep  = (which==='block') ? ENDPOINTS.groupRmBlock : ENDPOINTS.groupRmWhite;
  const res = await postJSON(ep, { group, id, address: val });
  console.debug('removeRule', group, which, res);

  if (!res.ok){
    alert((res.message || 'Remove failed') + (res.raw ? `\n\nRaw:\n${res.raw.substring(0,400)}` : ''));
    return;
  }
  await loadGroupRules(group);
}

/* ===== Apply Category to Group (bulk add) ===== *//* ADDED */
async function applyCategoryToGroup(group, which){ // which = 'block' or 'white'
  const selId = group === 'over18' ? 'cat-over18' : 'cat-under18';
  const category = (document.getElementById(selId)?.value || '').trim();
  if (!category){ alert('Pick a category'); return; }

  const domains = CATEGORY_PACKS[category] || [];
  if (!domains.length){ alert('No domains in this category.'); return; }

  if (!confirm(`Add ${domains.length} ${category} site(s) to ${group} ${which}?`)) return;

  const ep = (which === 'block') ? ENDPOINTS.groupAddBlock : ENDPOINTS.groupAddWhite;

  // Small concurrency to avoid hammering the server/router
  const concurrency = 4;
  let ok = 0, fail = 0, done = 0;

  const queue = domains.slice();
  const commentPrefix = `Cat:${category}; Pack:v1`;

  async function worker(){
    while(queue.length){
      const address = queue.shift();
      const res = await postJSON(ep, { group, address, comment: `${commentPrefix}; UI:${address}` });
      if (res?.ok) ok++; else fail++;
      done++;
      if (done % 5 === 0) console.debug(`Progress ${done}/${domains.length} for ${group}/${category}`);
    }
  }

  const jobs = Array.from({length: Math.min(concurrency, domains.length)}, () => worker());
  await Promise.all(jobs);

  alert(`Category applied to ${group}.\nCategory: ${category}\nSuccess: ${ok}\nFailed: ${fail}`);
  await loadGroupRules(group);
}

/* ===== bulk helpers ===== */
function getCheckedRowsIn(tableSel){
  return Array.from(document.querySelectorAll(`${tableSel} tbody input.row-check:checked`))
    .map(cb => ({ group: cb.dataset.group, which: cb.dataset.which, id: cb.dataset.id || '', val: cb.dataset.val || '', comment: cb.dataset.comment || '', category: cb.dataset.category || '', cb }));
}
function setAllChecks(tableSel, checked){
  document.querySelectorAll(`${tableSel} tbody input.row-check`).forEach(cb => cb.checked = checked);
  const hdrSel = tableSel.replace('#tbl','#sel-all');
  syncSelectAllHeader(tableSel, hdrSel);
}
function syncSelectAllHeader(tableSel, headerSel){
  const boxes = Array.from(document.querySelectorAll(`${tableSel} tbody input.row-check`));
  const hdr   = document.querySelector(headerSel);
  if (!hdr) return;
  if (!boxes.length){ hdr.indeterminate = false; hdr.checked = false; return; }
  const all   = boxes.every(cb => cb.checked);
  const none  = boxes.every(cb => !cb.checked);
  hdr.indeterminate = !all && !none;
  hdr.checked = all && !none;
}
/* UPDATED: filter supports "cat: <name>" */
function selectByComment(tableSel, query){
  const q = (query || '').toLowerCase().trim();

  if (q.startsWith('cat:')){
    const want = q.slice(4).trim();
    document.querySelectorAll(`${tableSel} tbody input.row-check`).forEach(cb => {
      const cat = (cb.dataset.category || '').toLowerCase().trim();
      cb.checked = !!want && cat === want;
    });
  } else {
    document.querySelectorAll(`${tableSel} tbody input.row-check`).forEach(cb => {
      const cmt = (cb.dataset.comment || '').toLowerCase();
      cb.checked = !!q && cmt.includes(q);
    });
  }

  const hdrSel = tableSel.replace('#tbl','#sel-all');
  syncSelectAllHeader(tableSel, hdrSel);
}
async function bulkRemoveSelected(section){
  const tables = [`#tbl-${section}-block`, `#tbl-${section}-white`];
  const rows = tables.flatMap(sel => getCheckedRowsIn(sel));
  if (!rows.length){ alert('Nothing selected.'); return; }
  if (!confirm(`Remove ${rows.length} selected entr${rows.length>1?'ies':'y'} from ${section}?`)) return;

  let ok=0, fail=0;
  for (const r of rows){
    try{
      const ep = (r.which==='block') ? ENDPOINTS.groupRmBlock : ENDPOINTS.groupRmWhite;
      const res = await postJSON(ep, { group:r.group, id:r.id, address:r.val });
      if (res.ok) ok++; else fail++;
    }catch{ fail++; }
  }
  alert(`Bulk remove completed for ${section}.\nSuccess: ${ok}\nFailed: ${fail}`);
  await loadGroupRules(section);
}

/* ===== events ===== */
document.getElementById('btn-sync-profiles')?.addEventListener('click', saveProfileAndSync);

document.getElementById('in-over18-block')?.addEventListener('click', ()=>addRule('over18','block'));
document.getElementById('in-over18-white')?.addEventListener('click', ()=>addRule('over18','white'));
document.getElementById('in-under18-block')?.addEventListener('click', ()=>addRule('under18','block'));
document.getElementById('in-under18-white')?.addEventListener('click', ()=>addRule('under18','white'));

/* ADDED: bind Block Category buttons */
document.getElementById('cat-over18-block')?.addEventListener('click', ()=>applyCategoryToGroup('over18','block'));
document.getElementById('cat-under18-block')?.addEventListener('click', ()=>applyCategoryToGroup('under18','block'));

document.addEventListener('click', e=>{
  const b = e.target.closest('button.rm-block');
  const w = e.target.closest('button.rm-white');
  if (b) removeRule(b.dataset.group, 'block', b.dataset.id||'', b.dataset.val||'');
  if (w) removeRule(w.dataset.group, 'white', b?.dataset?.id || w.dataset.id||'', b?.dataset?.val || w.dataset.val||'');

  // ADDED: delete profile handler
  const d = e.target.closest('.btn-del-prof');
  if (d) deleteProfile(d.dataset.name || '');
});

/* Select-all headers + sync */
[
  { sel:'#tbl-over18-block', hdr:'#sel-all-over18-block' },
  { sel:'#tbl-over18-white', hdr:'#sel-all-over18-white' },
  { sel:'#tbl-under18-block', hdr:'#sel-all-under18-block' },
  { sel:'#tbl-under18-white', hdr:'#sel-all-under18-white' },
].forEach(({sel,hdr})=>{
  const hdrEl = document.querySelector(hdr);
  if (hdrEl && !hdrEl.__bound){
    hdrEl.addEventListener('change', e => setAllChecks(sel, e.target.checked));
    hdrEl.__bound = true;
  }
  const tbl = document.querySelector(sel);
  if (tbl && !tbl.__syncBound){
    tbl.addEventListener('change', e=>{
      if (e.target.matches('input.row-check')) syncSelectAllHeader(sel, hdr);
    });
    tbl.__syncBound = true;
  }
});

/* Comment filter buttons + bulk remove + deselect */
document.getElementById('select-comment-over18-block')?.addEventListener('click', ()=>selectByComment('#tbl-over18-block', (document.getElementById('filter-over18')?.value||'')));
document.getElementById('select-comment-over18-white')?.addEventListener('click', ()=>selectByComment('#tbl-over18-white', (document.getElementById('filter-over18')?.value||'')));
document.getElementById('remove-selected-over18')?.addEventListener('click', ()=>bulkRemoveSelected('over18'));
document.getElementById('clear-selected-over18')?.addEventListener('click', ()=>{ setAllChecks('#tbl-over18-block', false); setAllChecks('#tbl-over18-white', false); });

document.getElementById('select-comment-under18-block')?.addEventListener('click', ()=>selectByComment('#tbl-under18-block', (document.getElementById('filter-under18')?.value||'')));
document.getElementById('select-comment-under18-white')?.addEventListener('click', ()=>selectByComment('#tbl-under18-white', (document.getElementById('filter-under18')?.value||'')));
document.getElementById('remove-selected-under18')?.addEventListener('click', ()=>bulkRemoveSelected('under18'));
document.getElementById('clear-selected-under18')?.addEventListener('click', ()=>{ setAllChecks('#tbl-under18-block', false); setAllChecks('#tbl-under18-white', false); });

/* ===== initial load ===== */
(async()=>{
  await loadDevices();
  await loadProfiles();
  await loadGroupRules('over18');
  await loadGroupRules('under18');
})();
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
