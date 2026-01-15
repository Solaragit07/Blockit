<?php
/**
 * Auto-Detection Status Widget for Dashboard
 */

include '../../connectMySql.php';
include '../../config/auto_blocking_config.php';

$config = include '../../config/auto_blocking_config.php';

// Check last detection run
$lastRun = file_exists('../../logs/last_auto_detection.log') ? 
           file_get_contents('../../logs/last_auto_detection.log') : 'Never';

// Count unknown devices currently connected
$unknownDevices = 0;
try {
    require_once '../../vendor/autoload.php';
    use RouterOS\Client;
    use RouterOS\Query;
    
    include '../../API/connectMikrotik.php';
    $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
    
    $knownMACs = [];
    $result = $conn->query("SELECT mac_address FROM device");
    while ($row = $result->fetch_assoc()) {
        $knownMACs[] = strtolower($row['mac_address']);
    }
    
    foreach ($dhcpLeases as $lease) {
        $mac = strtolower($lease['mac-address'] ?? '');
        if ($mac && !in_array($mac, $knownMACs)) {
            $unknownDevices++;
        }
    }
} catch (Exception $e) {
    // Ignore router connection errors for widget
}

?>

<div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-info shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Auto-Detection Status
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?php if ($config['auto_detection_enabled']): ?>
                            <span class="text-success">
                                <i class="fas fa-check-circle"></i> Enabled
                            </span>
                        <?php else: ?>
                            <span class="text-warning">
                                <i class="fas fa-pause-circle"></i> Disabled
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                        Unknown devices: <strong><?= $unknownDevices ?></strong><br>
                        Last scan: <small><?= $lastRun ?></small>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-wifi fa-2x text-gray-300"></i>
                </div>
            </div>
            <div class="mt-2">
                <button class="btn btn-info btn-sm" onclick="runAutoDetection()">
                    <i class="fas fa-search"></i> Scan Now
                </button>
                <?php if ($unknownDevices > 0): ?>
                <button class="btn btn-success btn-sm" onclick="blockUnknownDevices()">
                    <i class="fas fa-shield-alt"></i> Block Unknown
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function runAutoDetection() {
    Swal.fire({
        title: 'Running Device Detection...',
        text: 'Scanning for new devices on the network',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
            
            fetch('../../auto_detect_and_block.php', {
                method: 'POST'
            })
            .then(response => response.text())
            .then(data => {
                Swal.fire({
                    title: 'Detection Complete',
                    html: `<pre style="text-align: left; font-size: 12px;">${data}</pre>`,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload(); // Refresh to show updated counts
                });
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to run device detection: ' + error,
                    icon: 'error'
                });
            });
        }
    });
}

function blockUnknownDevices() {
    Swal.fire({
        title: 'Block Unknown Devices?',
        text: 'This will apply default blocking rules to all unknown devices currently connected.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, block them!'
    }).then((result) => {
        if (result.isConfirmed) {
            runAutoDetection(); // This will detect and block
        }
    });
}
</script>
