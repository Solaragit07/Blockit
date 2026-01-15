<?php
// /public_html/main/account/index.php
// Edit Account Page — BlockIT

$APP_ROOT = $_SERVER['DOCUMENT_ROOT']; // safer across stacks
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';
if (function_exists('require_login')) { require_login(); }
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
  header('Location: /index.php');
  exit;
}

$user_id = (int) $_SESSION['user_id'];

/* Load the latest admin info from DB (avoid stale session values) */
$stmt = $conn->prepare('SELECT email, name, status, image FROM admin WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc() ?: ['email'=>'', 'name'=>'', 'status'=>'ACTIVE', 'image'=>null];
$stmt->close();

$email  = (string)($row['email'] ?? '');
$name   = (string)($row['name'] ?? '');
$status = (string)($row['status'] ?? 'ACTIVE');
$image  = (string)($row['image'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>BlockIT › Edit Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="icon" type="image/x-icon" href="/img/logo1.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <style>
    @layer sbadmin;
    @import url("/css/sb-admin-2.min.css") layer(sbadmin);
    @import url("/css/custom-color-palette.css") layer(sbadmin);
  </style>

  <style>
    :root { --bg1:#0dcaf0; --bg2:#087990; --ink:#063c4a; --border:rgba(13,202,240,.35); --card:#b6effb; }
    *{box-sizing:border-box}
    body{margin:0; font-family:'Inter',system-ui; background:linear-gradient(135deg,var(--bg1),var(--bg2)); min-height:100vh}
    .wrap{max-width:760px; margin:0 auto; padding:20px}
    .header{display:flex; align-items:center; justify-content:space-between; gap:12px; background:rgba(255,255,255,.35); border:1px solid var(--border); border-radius:16px; padding:12px 16px; box-shadow:0 12px 40px rgba(0,0,0,.12); margin-bottom:16px}
    .header h1{margin:0; font-weight:800; color:var(--ink); font-size:1.25rem; display:flex; gap:8px; align-items:center}
    .card{background:var(--card); border:1px solid var(--border); border-radius:16px; box-shadow:0 12px 40px rgba(0,0,0,.12); overflow:hidden}
    .card-hd{background:rgba(255,255,255,.35); padding:12px 14px; font-weight:700; color:var(--ink); display:flex; align-items:center; gap:8px}
    .pad{padding:16px}
    form{display:grid; grid-template-columns:1fr 1fr; gap:14px}
    .col-span-2{grid-column:1 / -1}
    label{font-weight:600; color:var(--ink)}
    input, select{width:100%; padding:10px 12px; border:1px solid #cfe7ef; border-radius:10px; font-size:15px; background:#fff}
    .btn{border:none; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; background:#087990; color:#fff}
    .btn:hover{background:#06697a}
    .avatar{display:flex; align-items:center; gap:12px}
    .avatar img{width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #cfe7ef; background:#fff}
    @media (max-width:640px){ form{grid-template-columns:1fr} }
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

      <div class="wrap">
        <div class="header">
          <h1><i class="fa-solid fa-user-pen"></i> Edit Account</h1>
        </div>

        <div class="card">
          <div class="card-hd"><i class="fa-solid fa-id-card"></i> Account Details</div>
          <div class="pad">
            <form id="edit-account">

              <div>
                <label for="name">Full Name</label>
                <input type="text" id="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Your name">
              </div>

              <div hidden>
                <label for="status">Status</label>
                <select id="status" disabled>
                  <option value="ACTIVE"   <?php echo $status==='ACTIVE'?'selected':''; ?>>ACTIVE</option>
                  <option value="INACTIVE" <?php echo $status==='INACTIVE'?'selected':''; ?>>INACTIVE</option>
                </select>
              </div>

              <div class="col-span-2">
                <label for="email">Email</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
              </div>

              <div class="col-span-2">
                <label for="password">New Password</label>
                <input type="password" id="password" placeholder="Leave blank to keep current">
              </div>

              <div class="col-span-2" style="display:flex; gap:10px; flex-wrap:wrap">
                <!-- ADD id so JS can find the button -->
                <button type="submit" id="btn-save" class="btn"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                <!-- <button type="button" id="btn-delete" class="btn" style="background:#dc3545"><i class="fa-solid fa-trash"></i> Delete Account</button> -->
              </div>
            </form>
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

<script src="/vendor/jquery/jquery.min.js"></script>
<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="/js/sb-admin-2.min.js"></script>
<script src="/js/sweetalert2.all.js"></script>

<script>
const $ = (s)=>document.querySelector(s);
function toast(icon,title,text){ Swal.fire({icon,title,text}); }

async function saveAccount(){
  const email = $('#email').value.trim();
  const name = $('#name').value.trim();
  const status = $('#status').value;
  const password = $('#password').value.trim();

  if(!email){ return toast('error','Error','Email is required'); }

  $('#btn-save').disabled = true;

  try{
    const res = await fetch('./API/account_update.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'include',
      body: JSON.stringify({ email, name, status, password })
    });

    const text = await res.text();
    let data; try { data = JSON.parse(text); } catch { data = { ok:false, message: text.slice(0,200) }; }

    if(!res.ok || !data.ok){
      return toast('error','Error', data.message || ('HTTP '+res.status));
    }
    toast('success','Saved', data.message || 'Account updated');
    $('#password').value = '';
  }catch(e){
    toast('error','Error','Connection error');
  }finally{
    $('#btn-save').disabled = false;
  }
}

async function deleteAccount(){
  const c = await Swal.fire({
    icon:'warning', title:'Delete your account?', text:'This cannot be undone.',
    showCancelButton:true, confirmButtonText:'Yes, delete', confirmButtonColor:'#dc3545'
  });
  if(!c.isConfirmed) return;

  try{
    const res = await fetch('./API/account_delete.php', { method:'POST', credentials:'include' });
    const j = await res.json();
    if(j.ok){
      Swal.fire({icon:'success', title:'Deleted', text:j.message||'Your account was removed.'});
      setTimeout(()=>location.href='/logout.php', 1200);
    }else{
      toast('error','Error', j.message || 'Delete failed');
    }
  }catch(e){
    toast('error','Error','Connection error');
  }
}

// keep your click bindings
$('#btn-save')?.addEventListener('click', (e)=>{ e.preventDefault(); saveAccount(); });
$('#btn-delete')?.addEventListener('click', deleteAccount);

// IMPORTANT: prevent native form submission on Enter or click
document.getElementById('edit-account')?.addEventListener('submit', (e)=>{
  e.preventDefault();
  saveAccount();
});
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
