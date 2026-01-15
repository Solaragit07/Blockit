<?php
include '../../connectMySql.php';
include '../../loginverification.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

if(logged_in()){

// Handle port forwarding requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        include '../../API/connectMikrotik.php';
        
        if ($action == 'add_port_forward') {
            $external_port = $_POST['external_port'];
            $internal_ip = $_POST['internal_ip'];
            $internal_port = $_POST['internal_port'];
            $protocol = $_POST['protocol'];
            $comment = $_POST['comment'] ?? "Port Forward {$external_port}";
            
            // Add NAT rule for port forwarding
            $client->query((new Query('/ip/firewall/nat/add'))
                ->equal('chain', 'dstnat')
                ->equal('dst-port', $external_port)
                ->equal('protocol', $protocol)
                ->equal('action', 'dst-nat')
                ->equal('to-addresses', $internal_ip)
                ->equal('to-ports', $internal_port)
                ->equal('comment', $comment)
            )->read();
            
            $message = "Port forwarding rule added successfully!";
            $messageType = "success";
        }
        
        $client->disconnect();
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get existing NAT rules
$natRules = [];
try {
    include '../../API/connectMikrotik.php';
    $natRules = $client->query((new Query('/ip/firewall/nat/print'))
        ->where('chain', 'dstnat')
    )->read();
    $client->disconnect();
} catch (Exception $e) {
    $natRules = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Port Forwarding - BlockIT</title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />

    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- Custom styles for this template-->
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">`n    `n    <!-- Custom Color Palette -->`n    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    <link href="../../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="../../js/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        .port-forward-card {
            transition: all 0.3s ease;
            border-left: 4px solid #4e73df;
        }
        
        .port-forward-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .protocol-badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            background-color: #f8f9fc;
        }
        
        .nav-pills .nav-link.active {
            background-color: #4e73df;
        }
        
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include '../sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include '../nav.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Port Forwarding Management</h1>
                        <div class="d-none d-sm-inline-block">
                            <button class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#addPortForwardModal">
                                <i class="fas fa-plus fa-sm text-white-50"></i> Add Port Forward
                            </button>
                        </div>
                    </div>

                    <?php if (isset($message)): ?>
                    <div class="alert alert-<?= $messageType == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Navigation Tabs -->
                    <ul class="nav nav-pills mb-4" id="portForwardTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="rules-tab" data-toggle="pill" href="#rules" role="tab">
                                <i class="fas fa-list"></i> Port Forward Rules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="common-tab" data-toggle="pill" href="#common" role="tab">
                                <i class="fas fa-server"></i> Common Services
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="devices-tab" data-toggle="pill" href="#devices" role="tab">
                                <i class="fas fa-network-wired"></i> Network Devices
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="portForwardTabContent">
                        <!-- Port Forward Rules Tab -->
                        <div class="tab-pane fade show active" id="rules" role="tabpanel">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Active Port Forwarding Rules</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="portForwardTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>External Port</th>
                                                    <th>Internal IP</th>
                                                    <th>Internal Port</th>
                                                    <th>Protocol</th>
                                                    <th>Comment</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (!empty($natRules)) {
                                                    foreach ($natRules as $rule) {
                                                        $disabled = isset($rule['disabled']) && $rule['disabled'] == 'true';
                                                        $statusClass = $disabled ? 'secondary' : 'success';
                                                        $statusText = $disabled ? 'Disabled' : 'Active';
                                                        $protocol = $rule['protocol'] ?? 'tcp';
                                                        $protocolClass = $protocol == 'tcp' ? 'primary' : 'info';
                                                        
                                                        echo "<tr>
                                                                <td><span class='badge badge-warning'>{$rule['dst-port']}</span></td>
                                                                <td><code>{$rule['to-addresses']}</code></td>
                                                                <td><span class='badge badge-success'>{$rule['to-ports']}</span></td>
                                                                <td><span class='badge badge-{$protocolClass} protocol-badge'>" . strtoupper($protocol) . "</span></td>
                                                                <td>" . ($rule['comment'] ?? 'No comment') . "</td>
                                                                <td><span class='badge badge-{$statusClass}'>{$statusText}</span></td>
                                                                <td>
                                                                    <button class='btn btn-sm btn-outline-primary' onclick='editPortForward(\"{$rule['.id']}\")'>
                                                                        <i class='fas fa-edit'></i>
                                                                    </button>
                                                                    <button class='btn btn-sm btn-outline-danger' onclick='deletePortForward(\"{$rule['.id']}\")'>
                                                                        <i class='fas fa-trash'></i>
                                                                    </button>
                                                                </td>
                                                              </tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='7' class='text-center text-muted'>
                                                            <i class='fas fa-network-wired fa-3x mb-3 text-gray-400'></i>
                                                            <p>No port forwarding rules configured</p>
                                                          </td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Common Services Tab -->
                        <div class="tab-pane fade" id="common" role="tabpanel">
                            <div class="row">
                                <?php
                                $commonServices = [
                                    ['name' => 'Web Server (HTTP)', 'port' => '80', 'protocol' => 'tcp', 'icon' => 'fas fa-globe', 'color' => 'primary'],
                                    ['name' => 'Web Server (HTTPS)', 'port' => '443', 'protocol' => 'tcp', 'icon' => 'fas fa-lock', 'color' => 'success'],
                                    ['name' => 'FTP Server', 'port' => '21', 'protocol' => 'tcp', 'icon' => 'fas fa-folder', 'color' => 'warning'],
                                    ['name' => 'SSH Server', 'port' => '22', 'protocol' => 'tcp', 'icon' => 'fas fa-terminal', 'color' => 'dark'],
                                    ['name' => 'Minecraft Server', 'port' => '25565', 'protocol' => 'tcp', 'icon' => 'fas fa-cube', 'color' => 'info'],
                                    ['name' => 'Remote Desktop', 'port' => '3389', 'protocol' => 'tcp', 'icon' => 'fas fa-desktop', 'color' => 'secondary'],
                                    ['name' => 'VPN (OpenVPN)', 'port' => '1194', 'protocol' => 'udp', 'icon' => 'fas fa-shield-alt', 'color' => 'danger'],
                                    ['name' => 'Game Server', 'port' => '27015', 'protocol' => 'tcp', 'icon' => 'fas fa-gamepad', 'color' => 'primary']
                                ];
                                
                                foreach ($commonServices as $service) {
                                    echo "<div class='col-lg-3 col-md-6 mb-4'>
                                            <div class='card port-forward-card h-100'>
                                                <div class='card-body text-center'>
                                                    <i class='{$service['icon']} fa-3x text-{$service['color']} mb-3'></i>
                                                    <h5 class='card-title'>{$service['name']}</h5>
                                                    <p class='card-text'>
                                                        Port: <span class='badge badge-{$service['color']}'>{$service['port']}</span><br>
                                                        Protocol: <span class='badge badge-outline-{$service['color']}'>" . strtoupper($service['protocol']) . "</span>
                                                    </p>
                                                    <button class='btn btn-{$service['color']} btn-sm' onclick='quickSetupService(\"{$service['name']}\", \"{$service['port']}\", \"{$service['protocol']}\")'>
                                                        <i class='fas fa-plus'></i> Quick Setup
                                                    </button>
                                                </div>
                                            </div>
                                          </div>";
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Network Devices Tab -->
                        <div class="tab-pane fade" id="devices" role="tabpanel">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Available Network Devices</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php
                                        // Get connected devices from database
                                        $query = "SELECT *, device_name as name FROM device ORDER BY device_name ASC";
                                        $result = $conn->query($query);
                                        
                                        if ($result && $result->num_rows > 0) {
                                            while ($device = $result->fetch_assoc()) {
                                                echo "<div class='col-lg-4 col-md-6 mb-3'>
                                                        <div class='card border-left-primary'>
                                                            <div class='card-body'>
                                                                <div class='d-flex align-items-center'>
                                                                    <div class='mr-3'>
                                                                        <i class='fas fa-laptop fa-2x text-primary'></i>
                                                                    </div>
                                                                    <div class='flex-grow-1'>
                                                                        <h6 class='font-weight-bold mb-1'>{$device['name']}</h6>
                                                                        <small class='text-muted'>MAC: {$device['mac_address']}</small>
                                                                    </div>
                                                                    <div>
                                                                        <button class='btn btn-sm btn-primary' onclick='createPortForwardForDevice(\"{$device['name']}\", \"{$device['mac_address']}\")'>
                                                                            <i class='fas fa-plus'></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                      </div>";
                                            }
                                        } else {
                                            echo "<div class='col-12'>
                                                    <div class='text-center text-muted'>
                                                        <i class='fas fa-network-wired fa-3x mb-3 text-gray-400'></i>
                                                        <p>No devices found. Add devices first in the Device Control section.</p>
                                                    </div>
                                                  </div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Footer -->
            <?php include '../footer.php'; ?>
        </div>
    </div>

    <!-- Add Port Forward Modal -->
    <div class="modal fade" id="addPortForwardModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4e73df, #3653d4); color: white;">
                    <h5 class="modal-title">Add Port Forwarding Rule</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_port_forward">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="external_port">External Port</label>
                                    <input type="number" class="form-control" id="external_port" name="external_port" min="1" max="65535" required>
                                    <small class="form-text text-muted">Port accessible from internet</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="protocol">Protocol</label>
                                    <select class="form-control" id="protocol" name="protocol" required>
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="tcp,udp">Both (TCP & UDP)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="internal_ip">Internal IP Address</label>
                                    <input type="text" class="form-control" id="internal_ip" name="internal_ip" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                                    <small class="form-text text-muted">Local device IP (e.g., 192.168.88.100)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="internal_port">Internal Port</label>
                                    <input type="number" class="form-control" id="internal_port" name="internal_port" min="1" max="65535" required>
                                    <small class="form-text text-muted">Port on local device</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="comment">Comment (Optional)</label>
                            <input type="text" class="form-control" id="comment" name="comment" placeholder="Description of this rule">
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Port Forwarding Guide:</h6>
                            <ul class="mb-0">
                                <li><strong>External Port:</strong> Port that will be accessible from the internet</li>
                                <li><strong>Internal IP:</strong> IP address of the device on your local network</li>
                                <li><strong>Internal Port:</strong> Port on the local device to forward traffic to</li>
                                <li><strong>Protocol:</strong> TCP for most web services, UDP for gaming/VPN</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Port Forward
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap core JavaScript-->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    <script src="../../js/sidebar.js"></script>
    
    <!-- DataTables -->
    <script src="../../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../vendor/datatables/dataTables.bootstrap4.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="../../js/sweetalert2.all.min.js"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#portForwardTable').DataTable({
                responsive: true,
                pageLength: 10,
                info: false,
                order: [[ 0, "asc" ]],
                columnDefs: [
                    { orderable: false, targets: [6] }
                ]
            });
        });

        function quickSetupService(serviceName, port, protocol) {
            $('#external_port').val(port);
            $('#internal_port').val(port);
            $('#protocol').val(protocol);
            $('#comment').val(serviceName);
            $('#addPortForwardModal').modal('show');
        }

        function createPortForwardForDevice(deviceName, macAddress) {
            $('#comment').val('Port forward for ' + deviceName);
            $('#addPortForwardModal').modal('show');
        }

        function editPortForward(ruleId) {
            Swal.fire({
                title: 'Edit Port Forward Rule',
                text: 'This will open the router interface for advanced editing.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Open Router Interface',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('http://192.168.88.1', '_blank');
                }
            });
        }

        function deletePortForward(ruleId) {
            Swal.fire({
                title: 'Delete Port Forward Rule?',
                text: 'This will remove the port forwarding rule.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create form to delete the rule
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const actionInput = document.createElement('input');
                    actionInput.name = 'action';
                    actionInput.value = 'delete_port_forward';
                    
                    const ruleInput = document.createElement('input');
                    ruleInput.name = 'rule_id';
                    ruleInput.value = ruleId;
                    
                    form.appendChild(actionInput);
                    form.appendChild(ruleInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Auto-fill internal port when external port changes
        $('#external_port').on('input', function() {
            if ($('#internal_port').val() === '') {
                $('#internal_port').val($(this).val());
            }
        });
    </script>

</body>
</html>

<?php
} else {
    header('location:../../index.php');
}
?>
