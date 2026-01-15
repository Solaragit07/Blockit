<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Age-Based Content Filter Demo</title>
    <link href="../../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            min-height: 100vh;
        }
        .demo-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(13, 202, 240, 0.1);
            border: 1px solid #b3e5fc;
        }
        .access-allowed {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-color: #28a745;
        }
        .access-blocked {
            background: linear-gradient(135deg, #f8d7da, #f1b0b7);
            border-color: #dc3545;
        }
        .access-neutral {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="demo-card p-4 mb-4">
                    <h2 class="text-primary mb-3">
                        <i class="fas fa-shield-alt"></i> Age-Based Content Filter Demo
                    </h2>
                    <p class="text-muted">This demo shows how age-based filtering works in real-time</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Test Controls -->
            <div class="col-md-4">
                <div class="demo-card p-4">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-cogs"></i> Test Parameters
                    </h5>
                    
                    <div class="mb-3">
                        <label for="user_age" class="form-label">User Age</label>
                        <input type="number" class="form-control" id="user_age" value="12" min="1" max="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="test_domain" class="form-label">Test Domain</label>
                        <input type="text" class="form-control" id="test_domain" value="facebook.com" placeholder="Enter domain to test">
                    </div>
                    
                    <button class="btn btn-primary w-100 mb-3" onclick="testSingleDomain()">
                        <i class="fas fa-search"></i> Test Single Domain
                    </button>
                    
                    <button class="btn btn-outline-primary w-100" onclick="testMultipleDomains()">
                        <i class="fas fa-list"></i> Test Common Domains
                    </button>
                </div>
                
                <!-- Age Summary -->
                <div class="demo-card p-4 mt-4">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-chart-bar"></i> Age-Based Rules Summary
                    </h5>
                    <div id="age_summary">
                        <p class="text-muted">Select an age and click "Test Common Domains" to see rules</p>
                    </div>
                </div>
            </div>
            
            <!-- Test Results -->
            <div class="col-md-8">
                <div class="demo-card p-4">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-clipboard-list"></i> Test Results
                    </h5>
                    <div id="test_results">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-play-circle fa-3x mb-3"></i>
                            <p>Run a test to see results here</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Device Management -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="demo-card p-4">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-mobile-alt"></i> Device Age Management
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Device Name</th>
                                    <th>Current Age</th>
                                    <th>Blocked Domains</th>
                                    <th>Allowed Domains</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="devices_table">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Loading devices...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/sweetalert2.all.min.js"></script>

    <script>
        // Test single domain access
        function testSingleDomain() {
            const age = $('#user_age').val();
            const domain = $('#test_domain').val();
            
            if (!age || !domain) {
                Swal.fire('Error', 'Please enter both age and domain', 'error');
                return;
            }
            
            $.get('../../api/age_based_domain_check.php', {
                domain: domain,
                age: age
            }, function(response) {
                if (response.success) {
                    displaySingleResult(response);
                } else {
                    Swal.fire('Error', response.error, 'error');
                }
            }).fail(function() {
                Swal.fire('Error', 'Failed to check domain access', 'error');
            });
        }
        
        // Test multiple common domains
        function testMultipleDomains() {
            const age = $('#user_age').val();
            
            if (!age) {
                Swal.fire('Error', 'Please enter an age', 'error');
                return;
            }
            
            const commonDomains = [
                'facebook.com', 'youtube.com', 'instagram.com', 'tiktok.com',
                'khanacademy.org', 'google.com', 'wikipedia.org',
                'pornhub.com', 'bet365.com', 'netflix.com', 'codecademy.com'
            ];
            
            $.post('../../api/age_based_domain_check.php', 
                JSON.stringify({
                    action: 'bulk_check',
                    domains: commonDomains,
                    age: age
                }), 
                function(response) {
                    if (response.success) {
                        displayBulkResults(response);
                        loadAgeSummary(age);
                    } else {
                        Swal.fire('Error', response.error, 'error');
                    }
                }, 'json'
            ).fail(function() {
                Swal.fire('Error', 'Failed to check domains', 'error');
            });
        }
        
        // Display single test result
        function displaySingleResult(response) {
            const accessClass = getAccessClass(response.access);
            const accessIcon = getAccessIcon(response.access);
            
            const html = `
                <div class="alert ${accessClass} border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="${accessIcon} fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">${response.domain}</h6>
                            <p class="mb-0">${response.message}</p>
                            <small class="text-muted">Age: ${response.age} | Status: ${response.access.toUpperCase()}</small>
                        </div>
                    </div>
                </div>
            `;
            
            $('#test_results').html(html);
        }
        
        // Display bulk test results
        function displayBulkResults(response) {
            let html = `<h6 class="mb-3">Test Results for Age ${response.age}</h6>`;
            
            response.results.forEach(function(result) {
                const accessClass = getAccessClass(result.access);
                const accessIcon = getAccessIcon(result.access);
                
                html += `
                    <div class="mb-2 p-3 rounded ${accessClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${result.domain}</strong>
                                <br><small>${result.message}</small>
                            </div>
                            <div class="text-end">
                                <i class="${accessIcon}"></i>
                                <br><small class="text-uppercase">${result.access}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#test_results').html(html);
        }
        
        // Load age summary
        function loadAgeSummary(age) {
            $.post('../../api/age_based_domain_check.php',
                JSON.stringify({
                    action: 'get_blocked_domains',
                    age: age
                }),
                function(response) {
                    if (response.success) {
                        let html = `
                            <div class="mb-3">
                                <h6 class="text-danger">
                                    <i class="fas fa-ban"></i> Blocked (${response.count})
                                </h6>
                                <div class="small">
                        `;
                        
                        response.blocked_domains.slice(0, 5).forEach(function(domain) {
                            html += `<span class="badge bg-danger me-1">${domain.domain}</span>`;
                        });
                        
                        if (response.count > 5) {
                            html += `<span class="text-muted">... and ${response.count - 5} more</span>`;
                        }
                        
                        html += `</div></div>`;
                        
                        // Get allowed domains
                        $.post('../../api/age_based_domain_check.php',
                            JSON.stringify({
                                action: 'get_allowed_domains',
                                age: age
                            }),
                            function(allowedResponse) {
                                if (allowedResponse.success) {
                                    html += `
                                        <div>
                                            <h6 class="text-success">
                                                <i class="fas fa-check"></i> Allowed (${allowedResponse.count})
                                            </h6>
                                            <div class="small">
                                    `;
                                    
                                    allowedResponse.allowed_domains.slice(0, 5).forEach(function(domain) {
                                        html += `<span class="badge bg-success me-1">${domain.domain}</span>`;
                                    });
                                    
                                    if (allowedResponse.count > 5) {
                                        html += `<span class="text-muted">... and ${allowedResponse.count - 5} more</span>`;
                                    }
                                    
                                    html += `</div></div>`;
                                    $('#age_summary').html(html);
                                }
                            }, 'json'
                        );
                    }
                }, 'json'
            );
        }
        
        // Load devices
        function loadDevices() {
            $.post('../../api/device_age_enforcement.php', {
                action: 'get_all_devices_age_status'
            }, function(response) {
                if (response.success) {
                    let html = '';
                    
                    response.devices.forEach(function(device) {
                        const statusClass = device.filter_status === 'active' ? 'success' : 
                                          device.filter_status === 'pending' ? 'warning' : 'secondary';
                        const statusText = device.filter_status === 'active' ? 'Active' :
                                         device.filter_status === 'pending' ? 'Pending' : 'No Age Set';
                        
                        html += `
                            <tr>
                                <td><strong>${device.name}</strong><br><small class="text-muted">${device.device}</small></td>
                                <td>${device.age || 'Not Set'}</td>
                                <td><span class="badge bg-danger">${device.blocked_count}</span></td>
                                <td><span class="badge bg-success">${device.allowed_count}</span></td>
                                <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="applyFiltersToDevice(${device.id})">
                                        <i class="fas fa-sync"></i> Apply Filters
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    $('#devices_table').html(html);
                } else {
                    $('#devices_table').html('<tr><td colspan="6" class="text-center text-danger">Failed to load devices</td></tr>');
                }
            }).fail(function() {
                $('#devices_table').html('<tr><td colspan="6" class="text-center text-danger">Error loading devices</td></tr>');
            });
        }
        
        // Apply filters to specific device
        function applyFiltersToDevice(deviceId) {
            $.post('../../api/device_age_enforcement.php', {
                action: 'apply_age_filters_to_device',
                device_id: deviceId
            }, function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    loadDevices(); // Reload table
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }).fail(function() {
                Swal.fire('Error', 'Failed to apply filters', 'error');
            });
        }
        
        // Helper functions
        function getAccessClass(access) {
            switch(access) {
                case 'blocked': return 'access-blocked';
                case 'allowed': return 'access-allowed';
                case 'neutral': return 'access-neutral';
                default: return 'access-neutral';
            }
        }
        
        function getAccessIcon(access) {
            switch(access) {
                case 'blocked': return 'fas fa-ban text-danger';
                case 'allowed': return 'fas fa-check-circle text-success';
                case 'neutral': return 'fas fa-question-circle text-warning';
                default: return 'fas fa-question-circle text-muted';
            }
        }
        
        // Initialize page
        $(document).ready(function() {
            loadDevices();
        });
    </script>
</body>
</html>
