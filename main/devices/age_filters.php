<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Device Age-Based Filtering | BlockIt</title>
    
    <!-- CSS Files -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        .device-card {
            background: linear-gradient(135deg, rgba(78, 115, 223, 0.1) 0%, rgba(78, 115, 223, 0.05) 100%);
            border: 1px solid rgba(78, 115, 223, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.15);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: linear-gradient(45deg, #1cc88a, #17a673);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(45deg, #f6c23e, #e3a532);
            color: white;
        }
        
        .status-no-age {
            background: linear-gradient(45deg, #e74a3b, #c0392b);
            color: white;
        }
        
        .rules-count {
            background: rgba(78, 115, 223, 0.1);
            border: 1px solid rgba(78, 115, 223, 0.3);
            border-radius: 10px;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            display: inline-block;
            font-size: 0.875rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
        }
        
        .btn-gradient {
            background: linear-gradient(45deg, #4e73df, #224abe);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(78, 115, 223, 0.4);
            color: white;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .device-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .device-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .info-item {
            text-align: center;
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4e73df;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: #6c757d;
            text-transform: uppercase;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <!-- Include sidebar here if needed -->
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="container-fluid">
                    <!-- Page Header -->
                    <div class="page-header text-center">
                        <h1 class="h2 mb-3">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Device Age-Based Filtering
                        </h1>
                        <p class="mb-0">Manage and enforce age-based content filtering for connected devices</p>
                    </div>
                    
                    <!-- Control Panel -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-cogs mr-2"></i>Control Panel
                                    </h6>
                                    <div>
                                        <button class="btn btn-gradient btn-sm" onclick="refreshDevices()">
                                            <i class="fas fa-sync-alt mr-1"></i> Refresh
                                        </button>
                                        <button class="btn btn-success btn-sm ml-2" onclick="bulkApplyFilters()">
                                            <i class="fas fa-check-double mr-1"></i> Apply to All
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="info-value" id="totalDevices">0</div>
                                                <div class="info-label">Total Devices</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="info-value text-success" id="activeFilters">0</div>
                                                <div class="info-label">Active Filters</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="info-value text-warning" id="pendingFilters">0</div>
                                                <div class="info-label">Pending Filters</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="info-value text-danger" id="noAgeSet">0</div>
                                                <div class="info-label">No Age Set</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Devices List -->
                    <div class="row">
                        <div class="col-12">
                            <div id="devicesContainer">
                                <!-- Devices will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-3">Processing...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let devicesData = [];
        
        $(document).ready(function() {
            loadDevices();
        });
        
        function showLoading() {
            $('#loadingOverlay').show();
        }
        
        function hideLoading() {
            $('#loadingOverlay').hide();
        }
        
        function loadDevices() {
            showLoading();
            
            $.ajax({
                url: '../../api/device_age_enforcement.php',
                method: 'POST',
                data: { action: 'get_all_devices_age_status' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        devicesData = response.devices;
                        displayDevices(response.devices);
                        updateStats(response.devices);
                    } else {
                        Swal.fire('Error', response.message || 'Failed to load devices', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Network error occurred', 'error');
                },
                complete: function() {
                    hideLoading();
                }
            });
        }
        
        function displayDevices(devices) {
            const container = $('#devicesContainer');
            container.empty();
            
            if (devices.length === 0) {
                container.html(`
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            No devices found. Please add devices to the system first.
                        </div>
                    </div>
                `);
                return;
            }
            
            devices.forEach(device => {
                const deviceHtml = createDeviceCard(device);
                container.append(deviceHtml);
            });
        }
        
        function createDeviceCard(device) {
            const statusClass = device.filter_status === 'active' ? 'status-active' : 
                               device.filter_status === 'pending' ? 'status-pending' : 'status-no-age';
            
            const statusText = device.filter_status === 'active' ? 'Filters Active' : 
                              device.filter_status === 'pending' ? 'Filters Pending' : 'No Age Set';
            
            const ageDisplay = device.age > 0 ? `${device.age} years` : 'Not Set';
            
            return `
                <div class="col-lg-6 col-xl-4">
                    <div class="device-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-${getDeviceIcon(device.device)} mr-2"></i>
                                    ${device.name}
                                </h5>
                                <small class="text-muted">${device.device} • ${device.mac_address}</small>
                            </div>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                        
                        <div class="device-info">
                            <div class="info-item">
                                <div class="info-value">${ageDisplay}</div>
                                <div class="info-label">Age Setting</div>
                            </div>
                            <div class="info-item">
                                <div class="info-value text-danger">${device.blocked_count}</div>
                                <div class="info-label">Blocked</div>
                            </div>
                            <div class="info-item">
                                <div class="info-value text-success">${device.allowed_count}</div>
                                <div class="info-label">Allowed</div>
                            </div>
                        </div>
                        
                        <div class="device-actions">
                            ${device.age > 0 ? `
                                <button class="btn btn-primary btn-sm" onclick="viewDeviceRules(${device.id})">
                                    <i class="fas fa-eye mr-1"></i> View Rules
                                </button>
                                <button class="btn btn-success btn-sm" onclick="applyFilters(${device.id})">
                                    <i class="fas fa-shield-alt mr-1"></i> Apply Filters
                                </button>
                            ` : `
                                <button class="btn btn-warning btn-sm" onclick="setDeviceAge(${device.id})">
                                    <i class="fas fa-calendar mr-1"></i> Set Age
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getDeviceIcon(deviceType) {
            const icons = {
                'mobile': 'mobile-alt',
                'laptop': 'laptop',
                'desktop': 'desktop',
                'tablet': 'tablet-alt',
                'smart-tv': 'tv',
                'gaming': 'gamepad'
            };
            return icons[deviceType] || 'device';
        }
        
        function updateStats(devices) {
            const totalDevices = devices.length;
            const activeFilters = devices.filter(d => d.filter_status === 'active').length;
            const pendingFilters = devices.filter(d => d.filter_status === 'pending').length;
            const noAgeSet = devices.filter(d => d.filter_status === 'no_age_set').length;
            
            $('#totalDevices').text(totalDevices);
            $('#activeFilters').text(activeFilters);
            $('#pendingFilters').text(pendingFilters);
            $('#noAgeSet').text(noAgeSet);
        }
        
        function viewDeviceRules(deviceId) {
            showLoading();
            
            $.ajax({
                url: '../../api/device_age_enforcement.php',
                method: 'POST',
                data: { action: 'get_device_age_rules', device_id: deviceId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showDeviceRulesModal(response);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to load device rules', 'error');
                },
                complete: function() {
                    hideLoading();
                }
            });
        }
        
        function showDeviceRulesModal(data) {
            const blockedList = data.blocked_domains.map(d => `<span class="badge badge-danger mr-1 mb-1">${d}</span>`).join('');
            const allowedList = data.allowed_domains.map(d => `<span class="badge badge-success mr-1 mb-1">${d}</span>`).join('');
            
            Swal.fire({
                title: `${data.device_name} (Age: ${data.device_age})`,
                html: `
                    <div class="text-left">
                        <h6 class="text-danger"><i class="fas fa-ban mr-2"></i>Blocked Domains (${data.total_blocked})</h6>
                        <div class="mb-3" style="max-height: 150px; overflow-y: auto;">
                            ${blockedList || '<span class="text-muted">No blocked domains</span>'}
                        </div>
                        
                        <h6 class="text-success"><i class="fas fa-check mr-2"></i>Allowed Domains (${data.total_allowed})</h6>
                        <div style="max-height: 150px; overflow-y: auto;">
                            ${allowedList || '<span class="text-muted">No specifically allowed domains</span>'}
                        </div>
                    </div>
                `,
                width: 600,
                showCloseButton: true,
                showConfirmButton: false
            });
        }
        
        function applyFilters(deviceId) {
            Swal.fire({
                title: 'Apply Age-Based Filters?',
                text: 'This will apply age-based content filtering rules to this device.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Apply Filters',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    
                    $.ajax({
                        url: '../../api/device_age_enforcement.php',
                        method: 'POST',
                        data: { action: 'apply_age_filters_to_device', device_id: deviceId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Success', response.message, 'success');
                                loadDevices(); // Refresh the list
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Failed to apply filters', 'error');
                        },
                        complete: function() {
                            hideLoading();
                        }
                    });
                }
            });
        }
        
        function bulkApplyFilters() {
            const devicesWithAge = devicesData.filter(d => d.age > 0);
            
            if (devicesWithAge.length === 0) {
                Swal.fire('Info', 'No devices with age settings found. Please set device ages first.', 'info');
                return;
            }
            
            Swal.fire({
                title: 'Apply Filters to All Devices?',
                text: `This will apply age-based filters to ${devicesWithAge.length} devices with age settings.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Apply to All',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    
                    $.ajax({
                        url: '../../api/device_age_enforcement.php',
                        method: 'POST',
                        data: { action: 'bulk_apply_age_filters' },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                let message = response.message;
                                if (response.errors && response.errors.length > 0) {
                                    message += '\n\nErrors:\n' + response.errors.join('\n');
                                }
                                Swal.fire('Success', message, 'success');
                                loadDevices(); // Refresh the list
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Failed to apply bulk filters', 'error');
                        },
                        complete: function() {
                            hideLoading();
                        }
                    });
                }
            });
        }
        
        function setDeviceAge(deviceId) {
            Swal.fire({
                title: 'Set Device Age',
                text: 'Please set the age for this device to enable age-based filtering.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Go to Device Settings',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to device management page
                    window.location.href = '../devices/'; // Adjust path as needed
                }
            });
        }
        
        function refreshDevices() {
            loadDevices();
        }
    </script>
</body>
</html>
