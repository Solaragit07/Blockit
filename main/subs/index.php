<?php
include '../../connectMySql.php';
include '../../loginverification.php';

if (logged_in()) {
  require_once $_SERVER['DOCUMENT_ROOT'] . '/main/includes/subscription.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>BlockIt</title>
  <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />

  <!-- Fonts and icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css?family=Nunito:300,400,600,700,800,900&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <!-- Core CSS (Bootstrap theme) -->
  <link href="../../css/sb-admin-2.min.css" rel="stylesheet" />
  <link href="../../css/custom-color-palette.css" rel="stylesheet" />

  <!-- SweetAlert2 (single include) -->
  <script src="../../js/sweetalert2.all.js" defer></script>

  <!-- Chart.js (single include) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

  <!-- Utilities -->
  <script src="../../js/html2canvas.min.js" defer></script>

  <!-- PayPal JS SDK (Sandbox) -->
  <script src="https://www.paypal.com/sdk/js?client-id=AcfIQ1WFhu2xb0FKtc9CH7sh5cK6dD3wy1ZNkmigvIYdcB5GlxHU3oIVM4IIJPG9QupG3_N3VUCg7_wU&currency=PHP" defer></script>

  <style>
    :root{
      --primary-blue:#0dcaf0;
      --primary-blue-dark:#087990;
      --primary-blue-light:#b6effb;
      --accent-blue:#17a2b8;
      --background-blue:#e3f2fd;
      --text-blue:#0f3460;
      --border-blue:#b3e5fc;
    }

    /* Page background */
    html,body{height:100%}
    body{
      font-family:'Nunito',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      background:linear-gradient(135deg,#e3f2fd 0%,#bbdefb 100%) !important;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
    }

    #content-wrapper{background:transparent !important}
    .container-fluid{
      background:linear-gradient(135deg, rgba(227,242,253,.35), rgba(187,222,251,.35)) !important;
      border-radius:16px;
      padding:24px;
      backdrop-filter:saturate(1.2) blur(10px);
    }

    /* Headings */
    h1.card-title{
      color:var(--text-blue);
      font-weight:800;
      font-size:clamp(1.6rem, 2.2vw + 1rem, 2.25rem);
      margin-bottom:.5rem;
    }
    .subtitle{
      color:var(--text-blue);
      font-size:clamp(.95rem, .4vw + .8rem, 1.05rem);
      margin-bottom:1.25rem;
      opacity:.95;
    }

    /* Pricing cards */
    .plan-card{
      background:#ffffffeb;
      border:1px solid var(--border-blue);
      border-radius:16px;
      box-shadow:0 8px 24px rgba(13,202,240,.12);
      padding:20px;
      height:100%;
      transition:transform .22s ease, box-shadow .22s ease, background .22s ease;
    }
    .plan-card:hover{
      transform:translateY(-2px);
      box-shadow:0 12px 32px rgba(13,202,240,.18);
    }
    .plan-card.featured{
      border:2px solid var(--primary-blue);
      position:relative;
      overflow:hidden;
    }
    .plan-card.featured::before{
      content:'';
      position:absolute;
      left:0;right:0;top:0;height:4px;
      background:linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
    }

    .plan-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.75rem;
      flex-wrap:wrap;
    }
    .plan-title{
      color:var(--text-blue);
      font-weight:800;
      font-size:1.1rem;
      margin:0;
      display:flex;
      align-items:center;
      gap:.5rem;
    }
    .plan-price{
      color:var(--text-blue);
      font-weight:800;
      font-size:1.1rem;
      white-space:nowrap;
    }
    .badge{
      border-radius:999px;
      font-weight:800;
      letter-spacing:.3px;
      font-size:.68rem;
      padding:.32rem .6rem;
      text-transform:uppercase;
    }
    .badge-pop{
      background:linear-gradient(135deg,#f6c23e, #dda20a 50%, #c4910c);
      color:#fff;
    }

    .plan-card ul{
      margin:0 0 1rem;
    }
    .plan-card li{
      color:var(--text-blue);
      padding:.42rem 0;
      font-weight:600;
      font-size:.95rem;
      display:flex;
      align-items:center;
      gap:.5rem;
    }
    .plan-card i.fa-check{color:var(--primary-blue)}
    .plan-card i.fa-times{color:#e74a3b}

    /* Buttons */
    .btn{
      border-radius:.55rem;
      font-weight:700;
      position:relative;
      overflow:hidden;
    }
    .btn::before{
      content:'';
      position:absolute;
      inset:0 auto 0 -110%;
      width:100%;
      background:linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
      transition:left .5s;
    }
    .btn:hover::before{left:110%}
    .btn-primary{
      background:linear-gradient(135deg, var(--primary-blue), var(--accent-blue)) !important;
      border-color:var(--primary-blue) !important;
      color:#fff !important;
    }
    .btn-outline-primary{
      border-color:var(--primary-blue);
      color:var(--primary-blue);
      background:#fff;
    }
    .btn-outline-primary:hover{
      background:var(--primary-blue);
      color:#fff;
    }

    /* Table */
    .table{
      background:linear-gradient(135deg,#e3f2fd,#bbdefb) !important;
      border-radius:14px;
      overflow:hidden;
    }
    .table thead th{
      background:#b6effb !important;
      color:#0f3460 !important;
      border-bottom:2px solid #87ceeb !important;
      font-weight:800;
      font-size:.82rem;
      letter-spacing:.3px;
      padding:.85rem;
    }
    .table tbody td{
      border:1px solid var(--border-blue) !important;
      color:var(--text-blue) !important;
      font-weight:600;
      padding:.85rem;
      vertical-align:middle;
    }

    hr{
      border-color:var(--border-blue) !important;
      margin:1.25rem 0;
      height:1px;
      background:linear-gradient(90deg, transparent, var(--border-blue), transparent);
      opacity:1;
    }

    /* Animations (safe) */
    @media (prefers-reduced-motion:no-preference){
      .fade-in{animation:fadeIn .55s ease-out both}
      .slide-in{animation:slideIn .6s ease-out both}
      @keyframes fadeIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
      @keyframes slideIn{from{opacity:0;transform:translateX(-22px)}to{opacity:1;transform:translateX(0)}}
    }

    /* SweetAlert2 responsive */
    .swal2-popup.swal-responsive{
      width:min(520px, calc(100vw - 2rem)) !important;
      padding:1rem 1rem 1.25rem;
    }
    #paypal-button-container{width:100%}

    /* Mobile tweaks */
    @media (max-width: 992px){
      .container-fluid{padding:20px}
    }
    @media (max-width: 768px){
      .container-fluid{padding:16px}
      .plan-title,.plan-price{font-size:1rem}
      .plan-card li{font-size:.9rem}
      .table thead th,.table tbody td{font-size:.85rem}
    }
    @media (max-width: 420px){
      .container-fluid{padding:12px}
      .badge{font-size:.6rem}
      .table thead th,.table tbody td{font-size:.8rem; padding:.65rem}
    }
  </style>
</head>
<body id="page-top">

  <div id="wrapper">
    <?php include '../sidebar.php'; ?>

    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php include '../nav.php'; ?>

        <div class="container-fluid fade-in">
          <h1 class="card-title">Choose Your Perfect Plan</h1>
          <p class="subtitle">Get the protection your family needs with our flexible pricing plans</p>

          <div class="row g-3">
            <!-- Free -->
            <div class="col-12 col-lg-6">
              <div class="plan-card h-100">
                <div class="plan-head">
                  <h6 class="plan-title mb-0">Free Plan</h6>
                  <span class="plan-price" aria-label="price">₱0</span>
                </div>
                <p class="text-muted mt-2 mb-3">Perfect to get started</p>

                <ul class="list-unstyled mb-4" role="list">
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Content Blocking</span></li>
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Custom Schedules</span></li>
                  <li><i class="fa-solid fa-times" aria-hidden="true"></i><span>Real-Time Monitoring</span></li>
                </ul>

                <button class="btn btn-outline-primary w-100" onclick="downgradeToFree()" type="button">
                  Current Plan
                </button>
              </div>
            </div>

            <!-- Premium -->
            <div class="col-12 col-lg-6">
              <div class="plan-card featured h-100">
                <div class="plan-head">
                  <h6 class="plan-title mb-0">
                    Premium Plan
                    <span class="badge badge-pop ms-2">Most Popular</span>
                  </h6>
                  <span class="plan-price" aria-label="price">₱499</span>
                </div>
                <p class="text-muted mt-2 mb-3">Advanced protection and features</p>

                <ul class="list-unstyled mb-4" role="list">
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Can Access Everything</span></li>
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Unlimited Profiles</span></li>
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Ecommerce Checkout Blocking</span></li>
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Real-Time Monitoring</span></li>
                  <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Advanced Analytics</span></li>
                </ul>

                <button class="btn btn-primary w-100" onclick="upgradeToPremium()" type="button">
                  Upgrade Now
                </button>
              </div>
            </div>
          </div>

          <hr />

          <h6 class="text-muted mb-2">Full Feature Comparison</h6>
          <div class="table-responsive">
            <table class="table table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col">Feature</th>
                  <th scope="col">Free</th>
                  <th scope="col">Premium</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Content Blocking</td>
                  <td><i class="fa-solid fa-check" aria-label="yes"></i></td>
                  <td><i class="fa-solid fa-check" aria-label="yes"></i></td>
                </tr>
                <tr>
                  <td>Multi-Profile Support</td>
                  <td><i class="fa-solid fa-times" aria-label="no"></i></td>
                  <td>Unlimited</td>
                </tr>
                <tr>
                  <td>Real-Time Monitoring</td>
                  <td><i class="fa-solid fa-check" aria-label="yes"></i></td>
                  <td><i class="fa-solid fa-check" aria-label="yes"></i></td>
                </tr>
                <tr>
                  <td>Advanced Analytics</td>
                  <td><i class="fa-solid fa-times" aria-label="no"></i></td>
                  <td><i class="fa-solid fa-check" aria-label="yes"></i></td>
                </tr>
                <tr>
                  <td>Ecommerce Checkout Blocking</td>
                  <td><i class="fa-solid fa-times" aria-label="no"></i></td>
                  <td><i class="fa-solid fa-check" aria-label="yes"></i></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div> <!-- /container-fluid -->
      </div> <!-- /content -->
      
    <?php include '../footer.php'; ?>
    </div> <!-- /content-wrapper -->
  </div> <!-- /wrapper -->

  <!-- Scroll to Top -->
  <a class="scroll-to-top rounded" href="#page-top" aria-label="Scroll to top">
    <i class="fas fa-angle-up"></i>
  </a>

  <!-- Logout Modal -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="logoutLabel">Ready to Leave?</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
          <a class="btn btn-primary" href="../../logout.php">Logout</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap and core scripts -->
  <!-- Core JS -->
<script src="../../vendor/jquery/jquery.min.js"></script>
<script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="../../js/sb-admin-2.min.js"></script>

<!-- Then your custom scripts -->
<!-- <script src="your-custom-script.js"></script> -->

  <script>
    function upgradeToPremium() {
      Swal.fire({
        title: 'Upgrade to Premium',
        html: `
          <div class="text-start">
            <h6>Premium Plan Benefits:</h6>
            <ul class="list-unstyled mb-3">
              <li><i class="fas fa-check me-2"></i> Unlimited Device Profiles</li>
              <li><i class="fas fa-check me-2"></i> Real-Time Monitoring</li>
              <li><i class="fas fa-check me-2"></i> Advanced Analytics</li>
              <li><i class="fas fa-check me-2"></i> Custom Blocking Rules</li>
            </ul>
            <div class="mt-2 mb-3"><strong>Price: ₱499 / month</strong></div>
            <div id="paypal-button-container" class="mb-2"></div>
            <div id="pp-progress" style="display:none;font-size:.9rem;opacity:.85;">Connecting to PayPal…</div>
          </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        customClass: { popup: 'swal-responsive' },
        didOpen: () => {
          if (!window.paypal || !paypal.Buttons) {
            Swal.showValidationMessage('PayPal SDK failed to load. Please refresh and try again.');
            return;
          }

          const safeFetchJson = async (url, options) => {
            const res = await fetch(url, options);
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch { data = { raw: text }; }
            if (!res.ok) {
              const err = new Error('HTTP ' + res.status);
              err.httpStatus = res.status;
              err.payload = data;
              throw err;
            }
            return data;
          };

          const showError = (title, payload) => {
            console.error(title, payload);
            let msg = '';
            try {
              msg = payload?.detail || payload?.paypal?.error_description || payload?.paypal?.message || payload?.message || JSON.stringify(payload);
            } catch(_) { msg = String(payload); }
            Swal.fire('Error', `${title}\n${msg}`, 'error');
          };

          paypal.Buttons({
            style: { layout: 'vertical', shape: 'rect' },

            createOrder: () => {
              document.getElementById('pp-progress').style.display = 'block';
              return safeFetchJson('paypal/create_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan: 'premium' })
              })
              .then(d => {
                const id = d?.id || d?.orderID;
                if (!id) { showError('Could not initialize payment.', d); throw new Error('No order ID returned'); }
                return id;
              })
              .finally(() => { document.getElementById('pp-progress').style.display = 'none'; });
            },

            onApprove: (data) => {
              if (!data || !data.orderID) { showError('Missing orderID from PayPal.', data); return; }
              const progress = document.getElementById('pp-progress');
              progress.textContent = 'Finalizing payment…';
              progress.style.display = 'block';

              return safeFetchJson('paypal/capture_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderID: data.orderID })
              })
              .then(details => {
                const completed = details?.status === 'COMPLETED' || details?.result?.status === 'COMPLETED' || details?.ok === true;
                if (!completed) { showError('Payment Not Completed', details); return; }

                updateSubscriptionStatus('premium', { orderID: data.orderID, expires_at: details.expires_at || '' })
                  .catch(e => console.warn('updateSubscriptionStatus warn:', e));

                Swal.fire({
                  title: 'Upgrade Successful!',
                  text: 'Welcome to Premium! Your account has been upgraded.',
                  icon: 'success',
                  confirmButtonText: 'Continue',
                  confirmButtonColor: '#28a745'
                }).then(() => location.reload());
              })
              .catch(err => showError('We could not confirm your payment.', err.payload || err.message || err))
              .finally(() => { progress.style.display = 'none'; });
            },

            onError: (err) => showError('PayPal Error', err),
            onCancel: () => Swal.fire('Payment Cancelled', 'No worries, you can upgrade anytime.', 'info')
          }).render('#paypal-button-container');
        }
      });
    }

    async function updateSubscriptionStatus(plan, meta = {}) {
      try {
        localStorage.setItem('subscriptionPlan', plan);
        localStorage.setItem('subscriptionDate', new Date().toISOString());
        if (meta.expires_at) localStorage.setItem('subscriptionExpiresAt', meta.expires_at);
      } catch(_) {}

      const form = new URLSearchParams({
        plan,
        user_id: String(<?php echo (int)($_SESSION['user_id'] ?? 0); ?>),
        order_id: meta.orderID || '',
        expires_at: meta.expires_at || ''
      });

      if (window.fetch) {
        const res = await fetch('update_subscription.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form
        });
        if (!res.ok) throw new Error('Server update failed: HTTP ' + res.status);
        return;
      }

      if (window.$ && $.ajax) {
        return new Promise((resolve, reject) => {
          $.ajax({
            url: 'update_subscription.php',
            method: 'POST',
            data: Object.fromEntries(form),
            success: () => resolve(),
            error: (xhr) => reject(new Error(xhr?.responseText || xhr?.status || 'ajax error'))
          });
        });
      }
    }

    // Optional: make the "Current Plan" button functional
    async function downgradeToFree() {
      try {
        await updateSubscriptionStatus('free', {});
        Swal.fire('Switched', 'You are now on the Free plan.', 'success').then(() => location.reload());
      } catch (e) {
        Swal.fire('Error', 'Could not update your plan.', 'error');
      }
    }
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
<?php
} else {
  header('location:../../index.php');
}
?>
