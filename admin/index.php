<?php
// admin/index.php
// Minimal Admin UI (no login). Protect by IP/.htpasswd if public.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BlockIT Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Includes you requested -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/custom-color-palette.css">
  <style>
    :root{ --teal:#4fd1c7; --teal-dark:#0F766E; }
    body{background:#f6f9fc;}
    .aside{position:fixed;left:0;top:0;bottom:0;width:260px;background:linear-gradient(180deg,var(--teal),#38b2ac);color:#fff;padding:20px;}
    .aside h4{font-weight:700}
    .nav-link{color:#eafaf9}
    .nav-link.active,.nav-link:hover{background:rgba(255,255,255,.2);border-radius:8px;color:#fff}
    .main{margin-left:260px;padding:24px;}
    .card{border:0;border-radius:12px}
    .badge-premium{background:linear-gradient(135deg,#f6c23e,#dda20a);}
    .btn-outline-teal{border-color:var(--teal);color:var(--teal)}
    .btn-outline-teal:hover{background:var(--teal);color:#fff}
    .small-muted{font-size:.85rem;color:#6c757d}
    .hidden{display:none!important}
  </style>
</head>
<body>
  <aside class="aside">
    <h4 class="mb-3"><i class="fa-solid fa-shield-halved me-2"></i>BlockIT Admin</h4>
    <nav class="nav flex-column">
      <a class="nav-link active" href="#users" data-section="users"><i class="fa-solid fa-users me-2"></i>Users</a>
      <a class="nav-link" href="#payments" data-section="payments"><i class="fa-solid fa-receipt me-2"></i>Payments</a>
      <hr class="border-light">
      <div class="small">Panel Features</div>
      <ul class="small mt-2">
        <li>Show list of all users (Free vs Premium)</li>
        <li>Manual upgrade / downgrade</li>
        <li>View payment history & expirations</li>
      </ul>
    </nav>
  </aside>

  <main class="main">
    <div class="container-fluid">

      <!-- USERS SECTION -->
      <section id="section-users">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h3 class="m-0">Users & Subscriptions</h3>
          <button id="btn-refresh-users" class="btn btn-outline-dark btn-sm"><i class="fa-solid fa-rotate"></i> Refresh</button>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table align-middle" id="users-table">
                <thead>
                  <tr>
                    <th style="width:70px">ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th style="width:340px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="7" class="text-center py-4">Loading…</td></tr>
                </tbody>
              </table>
            </div>
            <div class="small-muted mt-2">
              Upgrade = <strong>Premium + Active</strong> (+30 days). &nbsp;
              Downgrade = <strong>Free + Inactive</strong>.
            </div>
          </div>
        </div>
      </section>

      <!-- PAYMENTS SECTION -->
      <section id="section-payments" class="hidden">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h3 class="m-0">Payments (₱499 per Premium)</h3>
          <button id="btn-refresh-payments" class="btn btn-outline-dark btn-sm"><i class="fa-solid fa-rotate"></i> Refresh</button>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table align-middle" id="payments-table">
                <thead>
                  <tr>
                    <th style="width:70px">User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Order</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Expires</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="8" class="text-center py-4">Loading…</td></tr>
                </tbody>
              </table>
            </div>
            <div class="small-muted mt-2">Showing all premium subscriptions as payments (source: <code>subscriptions</code> table).</div>
          </div>
        </div>
      </section>

    </div>
  </main>

  <!-- Per-user Payments Modal -->
  <div class="modal fade" id="paymentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Payment History</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="payments-wrap">
          <div class="text-center py-4">Loading…</div>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const API = 'admin_api.php';
    const sectionUsers = document.getElementById('section-users');
    const sectionPayments = document.getElementById('section-payments');
    const paymentsModal = new bootstrap.Modal(document.getElementById('paymentsModal'));
    const paymentsWrap  = document.getElementById('payments-wrap');

    // Simple router (sidebar)
    document.querySelectorAll('.nav-link[data-section]').forEach(a=>{
      a.addEventListener('click', e=>{
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(n=>n.classList.remove('active'));
        a.classList.add('active');
        const target = a.dataset.section;
        sectionUsers.classList.toggle('hidden', target!=='users');
        sectionPayments.classList.toggle('hidden', target!=='payments');
        if (target==='payments') loadPaymentsAll();
      });
    });

    // Helpers
    function fmtDate(s){ if(!s) return '—'; const d=new Date(s.replace(' ','T')); return isNaN(d)?s:d.toLocaleString(); }
    function badge(text, cls='secondary'){ return `<span class="badge bg-${cls}">${text}</span>`; }

    // USERS
    async function loadUsers(){
      const tbody = document.querySelector('#users-table tbody');
      tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4">Loading…</td></tr>`;
      try{
        const r = await fetch(`${API}?action=list_users`);
        const js = await r.json();
        if(!js.success){ throw new Error(js.message||'Failed'); }
        if(js.data.length===0){ tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4">No users found</td></tr>`; return; }

        const statusMap = {active:'success', pending:'warning', inactive:'secondary', canceled:'dark', expired:'danger'};
        tbody.innerHTML = js.data.map(u=>{
          const isPremium = (u.plan||'free').toLowerCase()==='premium';
          const planBadge = isPremium ? `<span class="badge-premium badge text-dark">Premium</span>` : badge('Free','info');
          const stBadge   = badge(u.status||'inactive', statusMap[(u.status||'inactive').toLowerCase()]||'secondary');
          const safeName  = (u.name||'-').replace(/</g,'&lt;');
          return `
            <tr>
              <td>${u.user_id}</td>
              <td>${safeName}</td>
              <td>${u.email??'-'}</td>
              <td>${planBadge}</td>
              <td>${stBadge}</td>
              <td>${fmtDate(u.expires_at)}</td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-teal" onclick="upgrade(${u.user_id})"><i class="fa-solid fa-crown me-1"></i>Upgrade</button>
                  <button class="btn btn-outline-secondary" onclick="downgrade(${u.user_id})"><i class="fa-solid fa-arrow-down me-1"></i>Downgrade</button>
                  <button class="btn btn-outline-dark" onclick="viewPayments(${u.user_id}, '${safeName.replace(/'/g,'&#39;')}')"><i class="fa-solid fa-receipt me-1"></i>Payments</button>
                  <button class="btn btn-outline-danger" onclick="deleteUser(${u.user_id}, '${safeName.replace(/'/g,'&#39;')}')"><i class="fa-solid fa-trash me-1"></i>Delete</button>
                </div>
              </td>
            </tr>`;
        }).join('');
      }catch(err){
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">${err.message}</td></tr>`;
      }
    }

    async function upgrade(user_id){
      if(!confirm('Upgrade this user to Premium (+30 days)?')) return;
      const fd = new FormData(); fd.append('action','upgrade'); fd.append('user_id',user_id);
      const r = await fetch(API, {method:'POST', body:fd});
      const js = await r.json().catch(()=>({success:false,message:'Invalid JSON'}));
      if(!js.success) return alert(js.message||'Upgrade failed');
      loadUsers();
    }

    async function downgrade(user_id){
      if(!confirm('Downgrade this user to Free (inactive)?')) return;
      const fd = new FormData(); fd.append('action','downgrade'); fd.append('user_id',user_id);
      const r = await fetch(API, {method:'POST', body:fd});
      const js = await r.json().catch(()=>({success:false,message:'Invalid JSON'}));
      if(!js.success) return alert(js.message||'Downgrade failed');
      loadUsers();
    }

    // NEW: Delete user (hard or soft-delete per API)
    async function deleteUser(user_id, name){
      if(!confirm(`Permanently delete this user?\n\nUser: ${name}\nID: ${user_id}\n\nThis cannot be undone.`)) return;

      // Optional: disable this row's buttons while processing
      const row = [...document.querySelectorAll('#users-table tbody tr')]
        .find(tr => tr.firstElementChild?.textContent.trim() === String(user_id));
      const btns = row ? row.querySelectorAll('button') : [];
      btns.forEach(b => b.disabled = true);

      const fd = new FormData();
      fd.append('action','delete_user'); // Backend should handle this
      fd.append('user_id', user_id);

      try{
        const r = await fetch(API, {method:'POST', body:fd});
        const js = await r.json().catch(()=>({success:false,message:'Invalid JSON'}));
        if(!js.success) throw new Error(js.message || 'Delete failed');
        loadUsers();
      }catch(err){
        alert(err.message || 'Delete failed');
        btns.forEach(b => b.disabled = false);
      }
    }

    // PER-USER PAYMENTS
    async function viewPayments(user_id, name){
      paymentsWrap.innerHTML = `<div class="text-center py-4">Loading…</div>`;
      paymentsModal.show();
      try{
        const r = await fetch(`${API}?action=payments&user_id=${encodeURIComponent(user_id)}`);
        const js = await r.json();
        if(!js.success) throw new Error(js.message||'Failed to load');

        let html = `<h6 class="mb-3">User: ${name} (ID ${user_id})</h6>`;
        if(!js.data || js.data.length===0){
          html += `<div class="alert alert-info">No payment records found.</div>`;
        }else{
          html += `<div class="table-responsive"><table class="table table-sm table-striped align-middle">
            <thead><tr><th>Order</th><th>Amount</th><th>Status</th><th>Started</th><th>Expires</th></tr></thead><tbody>`;
          html += js.data.map(p=>{
            const st = (p.status||'').toLowerCase();
            const cls = st==='active' ? 'success' : (st==='pending'?'warning':'secondary');
            return `<tr>
              <td>${p.order_id}</td>
              <td>${p.amount} ${p.currency}</td>
              <td><span class="badge bg-${cls}">${p.status}</span></td>
              <td>${fmtDate(p.created_at)}</td>
              <td>${fmtDate(p.expires_at)}</td>
            </tr>`;
          }).join('');
          html += `</tbody></table></div>`;
        }
        paymentsWrap.innerHTML = html;
      }catch(err){
        paymentsWrap.innerHTML = `<div class="alert alert-danger">${err.message||'Error'}</div>`;
      }
    }

    // ALL PAYMENTS (sidebar)
    async function loadPaymentsAll(){
      const tbody = document.querySelector('#payments-table tbody');
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4">Loading…</td></tr>`;
      try{
        const r = await fetch(`${API}?action=payments_all`);
        const js = await r.json();
        if(!js.success) throw new Error(js.message||'Failed to load');
        if(!js.data || js.data.length===0){
          tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4">No premium subscriptions found.</td></tr>`;
          return;
        }
        const statusMap = {active:'success', pending:'warning', inactive:'secondary', canceled:'dark', expired:'danger'};
        tbody.innerHTML = js.data.map(x=>{
          return `<tr>
            <td>${x.user_id}</td>
            <td>${(x.name||'-').replace(/</g,'&lt;')}</td>
            <td>${x.email||'-'}</td>
            <td>${x.order_id}</td>
            <td>${x.amount} ${x.currency}</td>
            <td><span class="badge bg-${statusMap[(x.status||'inactive')]||'secondary'}">${x.status}</span></td>
            <td>${fmtDate(x.created_at)}</td>
            <td>${fmtDate(x.expires_at)}</td>
          </tr>`;
        }).join('');
      }catch(err){
        tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-4">${err.message}</td></tr>`;
      }
    }

    // init
    document.getElementById('btn-refresh-users').addEventListener('click', loadUsers);
    document.getElementById('btn-refresh-payments').addEventListener('click', loadPaymentsAll);
    loadUsers();
  </script>
</body>
</html>
