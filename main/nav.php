<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
  <div class="sidebar-toggle-container">
  <button class="border-0 bg-transparent" id="sidebarToggleMobile" aria-label="Toggle sidebar">
    <i class="fa fa-bars"></i>
  </button>
</div>

<!-- optional backdrop element to allow tap-to-close -->
<div id="sb-backdrop" hidden></div>


  <!-- Topbar Navbar -->
  <ul class="navbar-nav ml-auto">

    <!-- Profile Settings -->
    <li class="nav-item dropdown no-arrow">
      <a
        class="nav-link dropdown-toggle"
        href="../account/index.php"
        id="profileDropdown"
        role="button"
        data-toggle="dropdown"
        aria-haspopup="true"
        aria-expanded="false"
        aria-label="Open profile settings"
      >
        <span class="d-lg-inline text-gray-600">
          <i class="fas fa-user-cog fa-lg text-primary" aria-hidden="true"></i>
        </span>
      </a>

      <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="profileDropdown">
        <h6 class="dropdown-header">Settings:</h6>

        <a class="dropdown-item" href="#" onclick="openEmailNotificationsModal()">
          <i class="fas fa-envelope fa-sm fa-fw mr-2 text-primary" aria-hidden="true"></i>
          Email &amp; Notifications
        </a>

        <a class="dropdown-item" href="../profile/">
          <i class="fas fa-users fa-sm fa-fw mr-2 text-info" aria-hidden="true"></i>
          Device Management
        </a>

        <div class="dropdown-divider"></div>

        <a class="dropdown-item" href="#" onclick="changePassword()">
          <i class="fas fa-key fa-sm fa-fw mr-2 text-warning" aria-hidden="true"></i>
          Change Password
        </a>
      </div>
    </li>

    <div class="topbar-divider d-none d-sm-block" role="separator" aria-hidden="true"></div>

    <!-- Logout Button (No Dropdown) -->
    <li class="nav-item no-arrow">
      <a
        class="nav-link"
        href="../../logout.php"
        data-toggle="modal"
        data-target="#logoutModal"
        role="button"
        aria-label="Logout"
        title="Logout"
        data-toggle="tooltip"
        data-placement="bottom"
      >
        <span class="d-lg-inline text-gray-600">
          <i class="fas fa-sign-out-alt fa-lg text-danger" aria-hidden="true"></i>
        </span>
      </a>
    </li>

  </ul>
</nav>
<!-- End of Topbar -->

<!-- Change Password Modal-->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" aria-modal="true">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">
          <i class="fas fa-key" aria-hidden="true"></i> Change Password
        </h5>
        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="changePasswordForm" method="post" action="../email/" novalidate>
        <div class="modal-body">
          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="off" required>
          </div>

          <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="off" minlength="6" required>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="off" minlength="6" required>
          </div>

          <input type="hidden" name="change_password" value="1">
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">
            <i class="fas fa-save" aria-hidden="true"></i> Change Password
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Email & Notifications Modal-->
<div class="modal fade" id="emailNotificationsModal" tabindex="-1" role="dialog" aria-labelledby="emailNotificationsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" aria-modal="true">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
        <h5 class="modal-title" id="emailNotificationsModalLabel">
          <i class="fas fa-envelope" aria-hidden="true"></i> Email &amp; Notification Settings
        </h5>
        <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div id="emailNotificationsContent">
          <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: #4CAF50;" aria-hidden="true"></i>
            <p class="mt-2 mb-0">Loading settings...</p>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Close</button>
        <button
          class="btn"
          type="button"
          onclick="window.open('../email/', '_blank')"
          style="background: linear-gradient(135deg, #4CAF50, #45a049); color: #fff; border: none; font-weight: 600;"
        >
          <i class="fas fa-external-link-alt" aria-hidden="true"></i> Open Full Page
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  // Open modals from menu
  function changePassword() {
    var jq = window.jQuery;
    if (jq && jq.fn && typeof jq.fn.modal === 'function') {
      jq('#changePasswordModal').modal('show');
      return;
    }
    alert('UI is still loading (missing jQuery/Bootstrap). Please refresh and try again.');
  }

  function openEmailNotificationsModal() {
    var jq = window.jQuery;
    var contentEl = document.getElementById('emailNotificationsContent');

    if (jq && jq.fn && typeof jq.fn.modal === 'function') {
      jq('#emailNotificationsModal').modal('show');
    } else {
      // Don't hard-fail if jQuery isn't available; user can still open the full page.
      alert('Email settings modal needs the UI libraries to finish loading. If this keeps happening, open the full page instead.');
      return;
    }

    // Load content (prefer jQuery when present; fallback to fetch)
    if (jq && typeof jq.ajax === 'function') {
      jq.ajax({
        url: '../email/modal_content.php',
        type: 'GET',
        success: function (response) {
          jq('#emailNotificationsContent').html(response);
        },
        error: function () {
          jq('#emailNotificationsContent').html(
            '<div class="alert alert-warning mb-0">' +
              '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ' +
              '<strong>Unable to load settings.</strong><br>' +
              '<a href="../email/" class="btn btn-primary btn-sm mt-2">' +
                '<i class="fas fa-external-link-alt" aria-hidden="true"></i> Open in New Page' +
              '</a>' +
            '</div>'
          );
        }
      });
      return;
    }

    fetch('../email/modal_content.php', { credentials: 'same-origin' })
      .then(function(r){ return r.text(); })
      .then(function(html){ if (contentEl) contentEl.innerHTML = html; })
      .catch(function(){
        if (contentEl) {
          contentEl.innerHTML =
            '<div class="alert alert-warning mb-0">' +
              '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ' +
              '<strong>Unable to load settings.</strong><br>' +
              '<a href="../email/" class="btn btn-primary btn-sm mt-2">' +
                '<i class="fas fa-external-link-alt" aria-hidden="true"></i> Open in New Page' +
              '</a>' +
            '</div>';
        }
      });
  }

  // Password validation (simple client-side checks)
  document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
    var newPassword = document.getElementById('new_password').value.trim();
    var confirmPassword = document.getElementById('confirm_password').value.trim();

    if (newPassword !== confirmPassword) {
      e.preventDefault();
      alert('New passwords do not match!');
      return false;
    }

    if (newPassword.length < 6) {
      e.preventDefault();
      alert('Password must be at least 6 characters long!');
      return false;
    }
  });

  // Initialize Bootstrap tooltips (for title on logout icon)
  (function(){
    var jq = window.jQuery;
    if (!(jq && jq.fn && typeof jq.fn.tooltip === 'function')) return;
    jq(function(){ jq('[data-toggle="tooltip"]').tooltip(); });
  })();
</script>
