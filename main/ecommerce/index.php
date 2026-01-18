<?php
// /main/ecommerce/checkout-blocking.php
// UI for DNS-based checkout blocking with platform toggles (mobile-first)

$APP_ROOT = dirname(__DIR__, 2); // /public_html
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';
if (function_exists('require_login')) { require_login(); }
if (session_status() === PHP_SESSION_NONE) session_start();

// pull API key safely from server config
$config  = require $APP_ROOT . '/config/router.php';
$API_KEY = isset($config['api_key']) ? $config['api_key'] : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>BlockIT – Checkout Blocking</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet"/>
  <link href="/css/sb-admin-2.min.css" rel="stylesheet"/>
  <link href="/css/custom-color-palette.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <style>
    :root{
      --ink:#063c4a; --muted:#0b4a59;
      --cyan:#0dcaf0; --cyan-2:#087990;
      --panel:rgba(255,255,255,.92);
      --border:#b3e5fc; --card:#ffffff;
      --chip:#b6effb; --danger-bg:#fff7f7; --danger-br:#ffd2d2;
    }

    /* Page */
    html, body {
  height: 100%;
  width: 100%;
  margin: 0;
  padding: 0;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  background: linear-gradient(135deg, #e3f2fd, #bbdefb);
  color: #0a1a21;
}

/* Override SB-Admin wrapper spacing */
#wrapper, #content-wrapper, #content {
  width: 100%;
  min-height: 100vh;
  margin: 0;
  padding: 0;
}

/* Let the page fill the whole viewport width */
.wrap {
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 12px;
}

/* Responsive panel */
.panel {
  width: 100%;
  background: rgba(255, 255, 255, .95);
  border: 1px solid #b3e5fc;
  border-radius: 14px;
  box-shadow: 0 10px 25px rgba(13, 202, 240, .12);
  overflow: hidden;
}

/* Header */
.panel-hd {
  padding: 14px 16px;
  border-bottom: 1px solid #d9f3ff;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.panel-hd h1 {
  font-size: clamp(1rem, 2vw + .6rem, 1.2rem);
  font-weight: 800;
  color: #063c4a;
  display: flex;
  gap: 8px;
  align-items: center;
  margin: 0;
}

/* Body */
.panel-bd {
  padding: 16px;
}

/* Platforms grid auto-fits responsively */
.platforms {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

/* Tiles */
.tile {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  background: #fff;
  border: 1px solid #cfe7ef;
  border-radius: 12px;
  padding: 12px;
  flex-wrap: wrap; /* so contents stack on narrow screens */
}

.name {
  font-weight: 800;
  color: #063c4a;
  line-height: 1.2;
}
.note {
  color: #5c7480;
  font-size: 12px;
}

/* Switch */
.switch {
  --h: 28px;
  --w: 52px;
  --p: 4px;
  position: relative;
  width: var(--w);
  height: var(--h);
  background: #d4eef6;
  border-radius: 999px;
  cursor: pointer;
  flex-shrink: 0;
}
.switch .knob {
  position: absolute;
  top: var(--p);
  left: var(--p);
  width: calc(var(--h) - var(--p) * 2);
  height: calc(var(--h) - var(--p) * 2);
  background: #fff;
  border-radius: 50%;
  transition: left .18s ease;
}
.switch[aria-checked="true"] {
  background: #087990;
}
.switch[aria-checked="true"] .knob {
  left: calc(var(--w) - var(--h) + var(--p));
}

/* Controls stack on mobile */
.controls {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}
@media (min-width: 600px) {
  .controls {
    flex-direction: row;
    align-items: center;
  }
}
.input {
  flex: 1;
  min-width: 0;
  padding: 10px 12px;
  border: 1px solid #cfe7ef;
  border-radius: 10px;
}
.btn {
  padding: 10px 12px;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
}
.btn-primary {
  background: #087990;
  color: #fff;
}
.btn-ghost {
  background: #fff;
  color: #087990;
  border: 1px solid #cfe7ef;
}

/* Danger Zone */
.tile.danger {
  background: #fff7f7;
  border-color: #ffd2d2;
}

/* Sidebar auto-collapse */
@media (max-width: 992px) {
  #wrapper.toggled #sidebar {
    transform: translateX(-100%);
  }
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

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/js/sweetalert2.all.js"></script>
</head>
<body id="page-top">
  <div id="wrapper">
    <?php include '../sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include '../nav.php'; ?>

        <div class="wrap fade-in">
          <div class="panel">
            <div class="panel-hd">
              <h1><i class="fa-solid fa-cart-shopping"></i> Checkout Blocking</h1>
              <!-- Optional header actions
              <div class="actions">
                <button id="btn-refresh" class="btn btn-ghost"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                <a class="btn btn-primary" href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
              </div> -->
            </div>

            <div class="panel-bd">
              <!-- Platforms -->
              <div id="platforms" class="platforms" style="margin:8px 0 18px 0"></div>

              <!-- Custom block -->
              <div class="tile" style="margin-top:2px">
                <div style="flex:1; min-width:0">
                  <div class="name">Custom</div>
                  <div class="help">
                    DNS blocks <b>hostnames</b>. For root-bound platforms (for example, <em>temu.com</em>, <em>shein.com</em>),
                    blocking the hostname will block the whole site.
                  </div>
                </div>
                <div class="controls">
                  <input id="in-custom" class="input" placeholder="e.g. checkout.ebay.com or pay.gcash.com" aria-label="Hostname to block"/>
                  <button id="btn-custom" class="btn btn-primary" type="button"><i class="fa-solid fa-plus"></i> Block Custom</button>
                </div>
              </div>

              <!-- Danger -->
              <div class="tile danger" style="margin-top:14px">
                <div class="name"><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</div>
                <button id="btn-clear" class="btn btn-ghost" type="button"><i class="fa-solid fa-broom"></i> Clear All</button>
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

<script>
/** ==== CONFIG ==== **/
const API_URL = "./API/ecom_checkout_api.php";
const API_KEY = <?php echo json_encode($API_KEY, JSON_UNESCAPED_SLASHES); ?>;

/** ==== UI DATA ==== **/
/* PH-most-used first. Keys must match API platforms. */
const PLATFORM_META = [
  { key:"Shopee",       note:"Block checkout/payment" },
  { key:"Lazada",       note:"Block checkout/payment" },
  // { key:"TikTok Shop",  note:"Block checkout/payment" },
  { key:"Temu",         note:"Root-bound" },
  { key:"Shein",        note:"Root-bound" },
  { key:"Zalora",       note:"PH fashion" },
  { key:"eBay",         note:"Checkout/pay" },
  { key:"Amazon",       note:"Checkout/pay" },
  { key:"GCash",        note:"Wallet pay" },
  { key:"Maya",         note:"Wallet pay" },
  { key:"Grab",         note:"GrabPay" },
  { key:"Foodpanda",    note:"Orders/Pay" },
];

/** ==== HELPERS ==== **/
function toastOK(msg){
  Swal.fire({icon:'success', title:'Done', text:msg, timer:1400, showConfirmButton:false});
}
function toastErr(msg){
  Swal.fire({icon:'error', title:'Error', text:msg||'Failed'});
}
async function api(action, payload={}){
  const r = await fetch(`${API_URL}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-API-Key': API_KEY },
    body: JSON.stringify(Object.assign({action}, payload))
  });
  let j; try { j = await r.json(); } catch { j = {ok:false,message:`HTTP ${r.status}`} }
  if (!j.ok) throw new Error(j.message || `HTTP ${r.status}`);
  return j;
}

/** ==== RENDER ==== **/
function platformSwitch(name, isOn){
  // Use a real <button> with switch semantics for accessibility and mobile focus styles
  return `
    <button class="switch" data-name="${name}"
            role="switch" aria-checked="${isOn ? 'true' : 'false'}"
            aria-label="Toggle ${name}">
      <span class="knob" aria-hidden="true"></span>
    </button>
  `;
}

function renderPlatforms(activeComments = []) {
  const container = document.getElementById('platforms');
  if (!container) return;
  container.innerHTML = '';

  // Derive active platform names from comments like "ECOM-CHECKOUT [Shopee] ..."
  const active = new Set(
    activeComments.map(s => {
      const m = /\[([^\]]+)\]/.exec(String(s));
      return m ? m[1] : '';
    }).filter(Boolean)
  );

  const frag = document.createDocumentFragment();
  PLATFORM_META.forEach(p => {
    const tile = document.createElement('div');
    tile.className = 'tile';
    tile.innerHTML = `
      <div style="min-width:0">
        <div class="name">${p.key}</div>
        <div class="note">${p.note || ''}</div>
      </div>
      ${platformSwitch(p.key, active.has(p.key))}
    `;
    frag.appendChild(tile);
  });
  container.appendChild(frag);
}

/** ==== EVENTS ==== **/
document.addEventListener('click', async (e) => {
  const sw = e.target.closest('.switch');
  if (!sw) return;

  const name = sw.dataset.name;
  const enable = sw.getAttribute('aria-checked') !== 'true';

  // Optimistic UI
  sw.setAttribute('aria-checked', enable ? 'true' : 'false');

  try{
    const res = await api('togglePlatform', { platform: name, enable });
    toastOK(res.message || `${enable ? 'Enabled' : 'Disabled'} ${name}`);
  }catch(err){
    // Revert on failure
    sw.setAttribute('aria-checked', enable ? 'false' : 'true');
    toastErr(err.message);
  }
});

document.getElementById('btn-custom').addEventListener('click', async () => {
  const input = document.getElementById('in-custom');
  const v = input.value.trim();
  if (!v) return;
  try{
    const res = await api('addCustom', { site: v, enable: true });
    input.value = '';
    toastOK(res.message || 'Custom blocked');
  }catch(err){ toastErr(err.message); }
});

document.getElementById('btn-clear').addEventListener('click', async () => {
  const ok = await Swal.fire({
    icon:'warning',
    title:'Clear all rules?',
    text:'Removes every ECOM-CHECKOUT rule',
    showCancelButton:true,
    confirmButtonText:'Yes, clear'
  });
  if (!ok.isConfirmed) return;

  try{
    const res = await api('clearAll');
    toastOK(res.message || 'Cleared');
    await loadState();
  }catch(err){ toastErr(err.message); }
});

document.getElementById('in-custom').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('btn-custom').click();
});

/** ==== INITIAL LOAD ==== **/
async function loadState(){
  try{
    const res = await api('get');
    renderPlatforms(res.active || []);
  }catch(err){
    toastErr('Load error: ' + (err.message || 'Unauthorized'));
  }
}
loadState();
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
