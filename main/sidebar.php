<?php
// Prevent any output before headers
$currentPage = basename(dirname($_SERVER['PHP_SELF']));

// subscription checker
require_once $_SERVER['DOCUMENT_ROOT'] . '/main/includes/subscription.php'; 
// this file sets $isPremium, $plan, $expiresAt
?>

<style>
  .nav-item.disabled .nav-link { 
    opacity: .55; 
    pointer-events: none; 
    cursor: not-allowed; 
    filter: grayscale(.2);
  }
  .badge-premium {
    background: #f6c23e;
    color: #111;
    border-radius: 8px;
    padding: .15rem .4rem;
    font-size: .65rem;
    margin-left: .35rem;
  }
</style>

<?php
// helper to render nav items
function navItem($slug, $href, $icon, $label, $restricted = false) {
    global $currentPage, $isPremium;
    $active = ($currentPage === $slug) ? 'active' : '';
    $disabled = ($restricted && !$isPremium);
    $badge = $restricted ? '<span class="badge-premium">Premium</span>' : '';

    if ($disabled) {
        echo '<li class="nav-item disabled" title="Available on Premium">';
        echo '  <a class="nav-link" href="javascript:void(0)">';
        echo "    <i class=\"$icon\"></i><span>$label $badge</span>";
        echo '  </a>';
        echo '</li>';
    } else {
        echo "<li class=\"nav-item $active\">";
        echo "  <a class=\"nav-link\" href=\"$href\">";
        echo "    <i class=\"$icon\"></i><span>$label</span>";
        echo '  </a>';
        echo '</li>';
    }
}
?>

<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-custom sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon">
            <img class="fa fas-user img-profile" style="width:70px" src="../../img/logo2.png">
        </div>
        <div class="sidebar-brand-text mx-3">
            BlockIt
            <small style="display:block; font-size:.7rem; opacity:.85;">
                Plan: <?= $isPremium ? 'Premium' : 'Free' ?>
            </small>
        </div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <?php
    // Restricted for Free
    navItem('dashboard', '../dashboard/index.php', 'fas fa-fw fa-home', 'Dashboard', false);
    navItem('profile',   '../profile/',            'fas fa-fw fa-user', 'Mikrotik Profile',   true);
    navItem('blocklist', '../blocklist/',          'fas fa-fw fa-ban',  'Blocklist & Content Filters', false);
    navItem('device',    '../device/',             'fas fa-fw fa-cubes','Devices',   true);

    // Always accessible
    navItem('usage',     '../usage/',              'fas fa-fw fa-laptop','Usage Monitoring', false);

    // Restricted for Free
    navItem('ecommerce', '../ecommerce/',          'fas fa-fw fa-box',  'E-Commerce Block', true);
    navItem('reports','../reports/',               'fa fa-file', 'Reports', true);
    // Always accessible
    navItem('subs',      '../subs/',               'fas fa-fw fa-crown','Subscription', false);
    // navItem('account',      '../account/',               'fas fa-fw fa-user','Account', true);
    ?>

    <div class="text-center">
  <button class="rounded-circle border-0" id="sidebarToggle"></button>
</div>

</ul>
<!-- End of Sidebar -->
