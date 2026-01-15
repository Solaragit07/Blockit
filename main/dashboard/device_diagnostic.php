<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';

// Check if user is logged in
if (!logged_in()) {
    header('Location: ../../index.php');
    exit;
}

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Device Detection Diagnostic - BlockIt</title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />
    
    <!-- Custom fonts and styles -->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
        }
        
        .diagnostic-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #e3e6f0;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .diagnostic-header {
            background: #b6effb;
            color: #0f3460;
            border-bottom: 2px solid #87ceeb;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
        }
        
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .status-info { color: #17a2b8; }
        
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .device-table {
            font-size: 12px;
        }
        
        .btn-diagnostic {
            margin: 5px;
        }
        
        .refresh-indicator {
            display: none;
        }
        
        .refresh-indicator.active {
            display: inline-block;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include '../sidebar.php'; ?>
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include '../nav.php'; ?>
                
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-search"></i> Device Detection Diagnostic
                        </h1>
                        <button class="btn btn-primary" onclick="runFullDiagnostic()">
                            <i class="fas fa-sync-alt refresh-indicator"></i>
                            <i class="fas fa-play"></i> Run Full Diagnostic
                        </button>
                    </div>

                    <!-- Connection Status -->
                    <div class="diagnostic-card">
                        <div class="diagnostic-header">
                            <h5 class="mb-0"><i class="fas fa-network-wired"></i> MikroTik Connection Status</h5>
                        </div>
                        <div class="card-body">
                            <div id="connection-status">
                                <p><i class="fas fa-spinner fa-spin"></i> Testing connection...</p>
                            </div>
                        </div>
                    </div>

                    <!-- DHCP Leases -->
                    <div class="diagnostic-card">
                        <div class="diagnostic-header">
                            <h5 class="mb-0"><i class="fas fa-list"></i> DHCP Lease Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div id="dhcp-analysis">
                                <p><i class="fas fa-spinner fa-spin"></i> Analyzing DHCP leases...</p>
                            </div>
                        </div>
                    </div>

                    <!-- ARP Table -->
                    <div class="diagnostic-card">
                        <div class="diagnostic-header">
                            <h5 class="mb-0"><i class="fas fa-table"></i> ARP Table Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div id="arp-analysis">
                                <p><i class="fas fa-spinner fa-spin"></i> Analyzing ARP table...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Active Connections -->
                    <div class="diagnostic-card">
                        <div class="diagnostic-header">
                            <h5 class="mb-0"><i class="fas fa-wifi"></i> Active Connection Detection</h5>
                        </div>
                        <div class="card-body">
                            <div id="connection-analysis">
                                <p><i class="fas fa-spinner fa-spin"></i> Detecting active connections...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Device Database Comparison -->
                    <div class="diagnostic-card">
                        <div class="diagnostic-header">
                            <h5 class="mb-0"><i class="fas fa-database"></i> Device Database Status</h5>
                        </div>
                        <div class="card-body">
                            <div id="database-analysis">
                                <p><i class="fas fa-spinner fa-spin"></i> Checking device database...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Troubleshooting Suggestions -->
                    <div class="diagnostic-card">
                        <div class="diagnostic-header">
                            <h5 class="mb-0"><i class="fas fa-tools"></i> Troubleshooting Suggestions</h5>
                        </div>
                        <div class="card-body">
                            <div id="troubleshooting-suggestions">
                                <div class="alert alert-info">
                                    <h6><strong>Common Reasons Why Devices Don't Appear:</strong></h6>
                                    <ul>
                                        <li><strong>Static IP Assignment:</strong> Your laptop might have a static IP instead of DHCP</li>
                                        <li><strong>WiFi vs Ethernet:</strong> Device might be on different interface than expected</li>
                                        <li><strong>DHCP Lease Expiry:</strong> Lease might have expired but device still connected</li>
                                        <li><strong>Router Configuration:</strong> DHCP server might not be logging all devices</li>
                                        <li><strong>API Permissions:</strong> User might not have access to all router tables</li>
                                        <li><strong>Network Segmentation:</strong> Device might be on different VLAN or subnet</li>
                                    </ul>
                                </div>
                                
                                <div id="specific-suggestions">
                                    <!-- Will be populated by diagnostic results -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include '../footer.php'; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Auto-run diagnostic when page loads
        $(document).ready(function() {
            runFullDiagnostic();
        });

        function runFullDiagnostic() {
            console.log('Starting full diagnostic...');
            
            // Show loading indicators
            $('.refresh-indicator').addClass('active fa-spin');
            
            // Reset all sections
            $('#connection-status').html('<p><i class="fas fa-spinner fa-spin"></i> Testing connection...</p>');
            $('#dhcp-analysis').html('<p><i class="fas fa-spinner fa-spin"></i> Analyzing DHCP leases...</p>');
            $('#arp-analysis').html('<p><i class="fas fa-spinner fa-spin"></i> Analyzing ARP table...</p>');
            $('#connection-analysis').html('<p><i class="fas fa-spinner fa-spin"></i> Detecting active connections...</p>');
            $('#database-analysis').html('<p><i class="fas fa-spinner fa-spin"></i> Checking device database...</p>');
            
            // Run diagnostic
            $.ajax({
                url: 'run_device_diagnostic.php',
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    console.log('Diagnostic response:', response);
                    
                    if (response.success) {
                        updateDiagnosticResults(response.data);
                    } else {
                        showError('Diagnostic failed: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Diagnostic error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMessage = 'Failed to run diagnostic: ' + error;
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = 'Diagnostic failed: ' + response.message;
                            }
                        } catch (e) {
                            // If response is not JSON, show first 200 chars of response
                            const preview = xhr.responseText.substring(0, 200);
                            errorMessage = 'Server returned non-JSON response: ' + preview + '...';
                        }
                    }
                    
                    showError(errorMessage);
                },
                complete: function() {
                    $('.refresh-indicator').removeClass('active fa-spin');
                }
            });
        }

        function updateDiagnosticResults(data) {
            // Connection Status
            if (data.connection) {
                const conn = data.connection;
                let html = `<div class="status-${conn.status === 'success' ? 'success' : 'error'}">
                    <h6><i class="fas fa-${conn.status === 'success' ? 'check-circle' : 'times-circle'}"></i> ${conn.message}</h6>
                </div>`;
                
                if (conn.details) {
                    html += `<div class="code-block">${conn.details}</div>`;
                }
                
                $('#connection-status').html(html);
            }

            // DHCP Analysis
            if (data.dhcp) {
                const dhcp = data.dhcp;
                let html = `<h6>DHCP Leases Found: ${dhcp.total_leases}</h6>`;
                html += `<h6>Active Leases: ${dhcp.active_leases}</h6>`;
                
                if (dhcp.leases && dhcp.leases.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm device-table">';
                    html += '<thead><tr><th>MAC Address</th><th>IP Address</th><th>Host Name</th><th>Status</th><th>Expires</th></tr></thead><tbody>';
                    
                    dhcp.leases.forEach(function(lease) {
                        html += `<tr>
                            <td><code>${lease.mac || 'N/A'}</code></td>
                            <td>${lease.ip || 'N/A'}</td>
                            <td>${lease.hostname || 'Unknown'}</td>
                            <td><span class="badge badge-${lease.status === 'bound' ? 'success' : 'secondary'}">${lease.status || 'unknown'}</span></td>
                            <td>${lease.expires || 'N/A'}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                } else {
                    html += '<div class="alert alert-warning">No DHCP leases found!</div>';
                }
                
                $('#dhcp-analysis').html(html);
            }

            // ARP Analysis
            if (data.arp) {
                const arp = data.arp;
                let html = `<h6>ARP Entries Found: ${arp.total_entries}</h6>`;
                
                if (arp.entries && arp.entries.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm device-table">';
                    html += '<thead><tr><th>MAC Address</th><th>IP Address</th><th>Interface</th><th>Status</th></tr></thead><tbody>';
                    
                    arp.entries.forEach(function(entry) {
                        html += `<tr>
                            <td><code>${entry.mac || 'N/A'}</code></td>
                            <td>${entry.ip || 'N/A'}</td>
                            <td>${entry.interface || 'N/A'}</td>
                            <td><span class="badge badge-info">${entry.status || 'reachable'}</span></td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                } else {
                    html += '<div class="alert alert-warning">No ARP entries found!</div>';
                }
                
                $('#arp-analysis').html(html);
            }

            // Connection Analysis
            if (data.connections) {
                const conn = data.connections;
                let html = `<h6>Total Detected Devices: ${conn.total_devices}</h6>`;
                html += `<h6>Dashboard Visible: ${conn.dashboard_visible}</h6>`;
                
                if (conn.detection_method) {
                    html += `<p><strong>Detection Method:</strong> ${conn.detection_method}</p>`;
                }
                
                if (conn.issues && conn.issues.length > 0) {
                    html += '<div class="alert alert-warning"><h6>Detection Issues:</h6><ul>';
                    conn.issues.forEach(function(issue) {
                        html += `<li>${issue}</li>`;
                    });
                    html += '</ul></div>';
                }
                
                $('#connection-analysis').html(html);
            }

            // Database Analysis
            if (data.database) {
                const db = data.database;
                let html = `<h6>Registered Devices: ${db.total_devices}</h6>`;
                
                if (db.devices && db.devices.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm device-table">';
                    html += '<thead><tr><th>Name</th><th>MAC Address</th><th>Device Type</th><th>Status</th></tr></thead><tbody>';
                    
                    db.devices.forEach(function(device) {
                        html += `<tr>
                            <td>${device.name || 'Unknown'}</td>
                            <td><code>${device.mac || 'N/A'}</code></td>
                            <td>${device.type || 'Unknown'}</td>
                            <td><span class="badge badge-${device.blocked === 'Yes' ? 'danger' : 'success'}">${device.blocked === 'Yes' ? 'Blocked' : 'Allowed'}</span></td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                }
                
                $('#database-analysis').html(html);
            }

            // Generate specific suggestions
            generateSpecificSuggestions(data);
        }

        function generateSpecificSuggestions(data) {
            let suggestions = [];
            
            if (data.connection && data.connection.status !== 'success') {
                suggestions.push({
                    type: 'error',
                    title: 'Connection Issue',
                    text: 'Fix MikroTik connection first before troubleshooting device detection.'
                });
            }
            
            if (data.dhcp && data.dhcp.active_leases === 0) {
                suggestions.push({
                    type: 'warning',
                    title: 'No Active DHCP Leases',
                    text: 'Your laptop might be using a static IP. Check if DHCP is enabled on your laptop\'s network adapter.'
                });
            }
            
            if (data.arp && data.arp.total_entries > data.dhcp.active_leases) {
                suggestions.push({
                    type: 'info',
                    title: 'ARP vs DHCP Mismatch',
                    text: 'Some devices appear in ARP table but not DHCP. This suggests static IP assignments.'
                });
            }
            
            if (data.connections && data.connections.total_devices !== data.connections.dashboard_visible) {
                suggestions.push({
                    type: 'warning',
                    title: 'Device Visibility Issue',
                    text: 'Some detected devices are not showing in dashboard. Check device filtering logic.'
                });
            }
            
            let html = '';
            if (suggestions.length > 0) {
                suggestions.forEach(function(suggestion) {
                    html += `<div class="alert alert-${suggestion.type}">
                        <h6><strong>${suggestion.title}</strong></h6>
                        <p>${suggestion.text}</p>
                    </div>`;
                });
            } else {
                html = '<div class="alert alert-success">No obvious issues detected. Your laptop should be visible if connected via DHCP.</div>';
            }
            
            $('#specific-suggestions').html(html);
        }

        function showError(message) {
            const errorHtml = `<div class="status-error">
                <h6><i class="fas fa-times-circle"></i> Error</h6>
                <p>${message}</p>
            </div>`;
            
            $('#connection-status').html(errorHtml);
            $('#dhcp-analysis').html(errorHtml);
            $('#arp-analysis').html(errorHtml);
            $('#connection-analysis').html(errorHtml);
            $('#database-analysis').html(errorHtml);
        }
    </script>
</body>
</html>
