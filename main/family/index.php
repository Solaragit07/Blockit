<?php
include '../../connectMySql.php';
include '../../loginverification.php';
if(logged_in()){

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>BlockIt </title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />

    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <!-- Custom styles for this template-->
    <script src="../../js/html2canvas.min.js"></script>
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- Custom Color Palette -->
    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../js/sweetalert2.all.js"></script>
    <script src="../../js/sweetalert2.css"></script>
    <script src="../../js/sweetalert2.js"></script>
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <style>
        /* Exact Blocklist Design System Applied */
        
        /* Blue Dashboard Theme - Exact Match from Blocklist */
        :root {
            --primary-blue: #0dcaf0;
            --primary-blue-dark: #087990;
            --primary-blue-light: #b6effb;
            --secondary-blue: #0f5132;
            --accent-blue: #17a2b8;
            --background-blue: #e3f2fd;
            --card-blue: #f0f9ff;
            --text-blue: #0f3460;
            --border-blue: #b3e5fc;
        }

        /* Global Background - Exact Blocklist Match */
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }

        /* Page Wrapper - Exact Blocklist Match */
        #page-top {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
        }

        /* Content Wrapper - Exact Blocklist Match */
        #content-wrapper {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
        }

        /* Main Content Container - Exact Blocklist Match */
        .container-fluid {
            background: linear-gradient(135deg, rgba(227, 242, 253, 0.3), rgba(187, 222, 251, 0.3)) !important;
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }

        /* Enhanced Title Section - Blocklist Style */
        h1 {
            color: var(--text-blue) !important;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .text-muted {
            color: var(--text-blue) !important;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        /* Card System - Exact Blocklist Match */
        .card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb) !important;
            border: 1px solid var(--border-blue) !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 32px rgba(13, 202, 240, 0.1) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(13, 202, 240, 0.15) !important;
        }

        .card-body {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb) !important;
            padding: 2rem;
        }

        /* Enhanced Form Controls - Exact Blocklist Match */
        .form-control {
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid var(--border-blue) !important;
            border-radius: 0.35rem !important;
            color: var(--text-blue) !important;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: white !important;
            border-color: var(--primary-blue) !important;
            box-shadow: 0 0 0 0.2rem rgba(13, 202, 240, 0.25) !important;
        }

        .form-control[readonly] {
            background: var(--primary-blue-light) !important;
            color: var(--text-blue) !important;
        }

        /* Enhanced Input Group - Blocklist Style */
        .input-group {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.1);
        }

        /* Enhanced Buttons - Exact Blocklist Match */
        .btn {
            border-radius: 0.35rem;
            font-weight: 400;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue)) !important;
            border-color: var(--primary-blue) !important;
            color: white !important;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-blue-dark), var(--primary-blue)) !important;
            border-color: var(--primary-blue-dark) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #36b9cc 0%, #2c9faf 50%, #228591 100%) !important;
            border-color: var(--primary-blue) !important;
            color: white !important;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #4dc3d6 0%, #36b9cc 50%, #2c9faf 100%) !important;
            border-color: var(--primary-blue-dark) !important;
        }

        .btn-outline-secondary {
            border-color: #e3e6f0;
            color: #6c757d;
            background: rgba(255, 255, 255, 0.9);
        }

        .btn-outline-secondary:hover {
            background: var(--primary-blue-light);
            border-color: var(--primary-blue);
            color: var(--text-blue);
        }

        .btn-outline-success {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            background: rgba(255, 255, 255, 0.9);
        }

        .btn-outline-success:hover {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }

        .btn-outline-primary {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            background: rgba(255, 255, 255, 0.9);
        }

        .btn-outline-primary:hover {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }

        .btn-outline-danger {
            border-color: #e74a3b;
            color: #e74a3b;
            background: rgba(255, 255, 255, 0.9);
        }

        .btn-outline-danger:hover {
            background: linear-gradient(135deg, #e74a3b 0%, #c0392b 50%, #a93226 100%);
            border-color: #e74a3b;
            color: white;
        }

        /* Enhanced Typography - Blocklist Match */
        h6.font-weight-bold {
            color: var(--text-blue) !important;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        p {
            color: var(--text-blue) !important;
        }

        .text-secondary {
            color: var(--text-blue) !important;
        }

        .small {
            color: var(--text-blue) !important;
        }

        /* QR Code Container - Enhanced Style */
        #qrCodeContainer {
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid var(--border-blue) !important;
            border-radius: 15px !important;
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.1) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #qrCodeContainer:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(13, 202, 240, 0.2) !important;
        }

        #qrPlaceholder {
            color: var(--primary-blue) !important;
        }

        /* Enhanced List Group - Exact Blocklist Match */
        .list-group {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.1);
            backdrop-filter: blur(10px);
        }

        .list-group-item {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb) !important;
            border: 1px solid var(--border-blue) !important;
            color: var(--text-blue) !important;
            transition: all 0.3s ease;
        }

        .list-group-item:hover {
            background: linear-gradient(135deg, #bbdefb, #90caf9) !important;
            transform: translateX(10px);
        }

        .list-group-item strong {
            color: var(--text-blue) !important;
            font-weight: 700;
        }

        .list-group-item small {
            color: var(--text-blue) !important;
        }

        /* Enhanced Dividers - Blocklist Style */
        hr {
            border-color: var(--border-blue) !important;
            margin: 2rem 0;
        }

        /* Text Colors - Exact Blocklist Match */
        .text-primary {
            color: var(--text-blue) !important;
        }

        .text-success {
            color: var(--primary-blue) !important;
        }

        .text-info {
            color: var(--accent-blue) !important;
        }

        .text-warning {
            color: #f6c23e !important;
        }

        .text-danger {
            color: #e74a3b !important;
        }

        .text-gray-500 {
            color: var(--primary-blue) !important;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            #qrCodeContainer {
                width: 80px !important;
                height: 80px !important;
            }
            
            #qrCodeCanvas {
                width: 60px !important;
                height: 60px !important;
            }
        }

        /* Animation Classes - Exact Blocklist Match */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

       <?php include'../sidebar.php';?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

               <?php include'../nav.php';?>

<div class="container-fluid fade-in">

     <h1 class="mb-0">Family Access Code</h1>
        <div class="text-muted small">Manage your household's device access and security settings</div>
  <div class="row">

    <div class="card shadow mb-4 slide-in" style="animation-delay: 0.2s;">
      <div class="card-body">
        <p class="mb-2">This code controls which devices join your household.</p>
        <div class="input-group mb-3">
          <input type="text" class="form-control bg-light border-0 small" id="familyCode" value="BLKT-A7Z3" readonly>
          <div class="input-group-append">
            <button class="btn btn-primary btn-sm" type="button" onclick="copyFamilyCode()">Copy Code</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="regenerateFamilyCode()">Regenerate</button>
          </div>
        </div>

        <hr>

        <h6 class="font-weight-bold text-secondary">Scan to Add Device to Household</h6>
        <p class="text-muted small">Scan with BlackIt App on child's device to link instantly</p>
        <div class="d-flex align-items-center mb-4">
          <div class="border p-3 bg-white mr-3" style="width: 100px; height: 100px;" id="qrCodeContainer">
            <canvas id="qrCodeCanvas" width="76" height="76" style="display: none;"></canvas>
            <i class="fas fa-qrcode fa-2x text-gray-500 d-flex justify-content-center align-items-center h-100" id="qrPlaceholder"></i>
          </div>
          <div>
            <button class="btn btn-success btn-sm mb-1" onclick="generateQRCode()">Generate QR</button><br>
            <button class="btn btn-outline-success btn-sm" onclick="downloadQRCode()">Download QR</button>
          </div>
        </div>

        <hr>

        <h6 class="font-weight-bold text-secondary">Linked Devices</h6>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span></span>
          <button class="btn btn-outline-primary btn-sm" onclick="addNewDevice()">
            <i class="fas fa-plus"></i> Add Device
          </button>
        </div>
        <ul class="list-group" id="linkedDevicesList">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong>Harriette's android</strong><br>
              <small class="text-muted">Last synced: 2 hours ago</small>
            </div>
            <div>
              <button class="btn btn-outline-secondary btn-sm mr-2" onclick="editDevice(this)">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-outline-danger btn-sm" onclick="removeDevice(this)">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </li>
        </ul>
      </div>
    </div>

                 
            </div>
        </div>
    </div>
        <!-- End of Content Wrapper -->
            <?php include'../footer.php';?>

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="../../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="../../js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="../../vendor/chart.js/Chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
<!-- Chart.js Library (included in SB Admin 2 by default, but add this if not yet present) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Family Settings JavaScript -->
<script>
// Generate random family access code
function generateFamilyCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = 'BLKT-';
    for (let i = 0; i < 4; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return code;
}

// Copy family code to clipboard
function copyFamilyCode() {
    const codeInput = document.getElementById('familyCode');
    codeInput.select();
    codeInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(codeInput.value).then(function() {
        Swal.fire({
            title: 'Code Copied!',
            text: 'Family access code has been copied to clipboard',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    }).catch(function(err) {
        // Fallback for older browsers
        document.execCommand('copy');
        Swal.fire({
            title: 'Code Copied!',
            text: 'Family access code has been copied to clipboard',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

// Regenerate family access code
function regenerateFamilyCode() {
    Swal.fire({
        title: 'Regenerate Access Code?',
        text: 'This will invalidate the current code and create a new one. Existing devices will need to reconnect.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, regenerate',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const newCode = generateFamilyCode();
            document.getElementById('familyCode').value = newCode;
            
            // Save to localStorage (in real app, save to server)
            localStorage.setItem('familyAccessCode', newCode);
            
            Swal.fire({
                title: 'Code Regenerated!',
                text: 'New family access code has been generated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Clear existing QR code
            document.getElementById('qrCodeCanvas').style.display = 'none';
            document.getElementById('qrPlaceholder').style.display = 'flex';
        }
    });
}

// Generate QR Code
function generateQRCode() {
    const familyCode = document.getElementById('familyCode').value;
    const canvas = document.getElementById('qrCodeCanvas');
    const placeholder = document.getElementById('qrPlaceholder');
    
    // QR code data (in real app, this would be a deep link or API endpoint)
    const qrData = `blockit://family/join?code=${familyCode}`;
    
    QRCode.toCanvas(canvas, qrData, {
        width: 76,
        height: 76,
        margin: 1,
        color: {
            dark: '#000000',
            light: '#ffffff'
        }
    }, function (error) {
        if (error) {
            console.error(error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to generate QR code',
                icon: 'error'
            });
        } else {
            canvas.style.display = 'block';
            placeholder.style.display = 'none';
            
            Swal.fire({
                title: 'QR Code Generated!',
                text: 'QR code is ready for scanning',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Download QR Code
function downloadQRCode() {
    const canvas = document.getElementById('qrCodeCanvas');
    
    if (canvas.style.display === 'none') {
        Swal.fire({
            title: 'No QR Code',
            text: 'Please generate a QR code first',
            icon: 'warning'
        });
        return;
    }
    
    // Convert canvas to download link
    const link = document.createElement('a');
    link.download = 'family-access-qr.png';
    link.href = canvas.toDataURL();
    link.click();
    
    Swal.fire({
        title: 'Downloaded!',
        text: 'QR code has been downloaded',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

// Add new device
function addNewDevice() {
    Swal.fire({
        title: 'Add New Device',
        html: `
            <div class="form-group text-left">
                <label for="deviceName">Device Name:</label>
                <input type="text" class="form-control" id="deviceName" placeholder="Enter device name">
            </div>
            <div class="form-group text-left">
                <label for="deviceType">Device Type:</label>
                <select class="form-control" id="deviceType">
                    <option value="android">Android</option>
                    <option value="ios">iOS</option>
                    <option value="windows">Windows</option>
                    <option value="mac">Mac</option>
                    <option value="other">Other</option>
                </select>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Add Device',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
        preConfirm: () => {
            const deviceName = document.getElementById('deviceName').value;
            const deviceType = document.getElementById('deviceType').value;
            
            if (!deviceName.trim()) {
                Swal.showValidationMessage('Please enter a device name');
                return false;
            }
            
            return { deviceName: deviceName.trim(), deviceType: deviceType };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { deviceName, deviceType } = result.value;
            addDeviceToList(deviceName, deviceType);
            
            Swal.fire({
                title: 'Device Added!',
                text: `${deviceName} has been added to your family`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Add device to the list
function addDeviceToList(deviceName, deviceType) {
    const devicesList = document.getElementById('linkedDevicesList');
    const deviceItem = document.createElement('li');
    deviceItem.className = 'list-group-item d-flex justify-content-between align-items-center';
    
    const now = new Date();
    const timeStr = now.toLocaleString();
    
    deviceItem.innerHTML = `
        <div>
            <strong>${deviceName}</strong><br>
            <small class="text-muted">Added: ${timeStr}</small>
        </div>
        <div>
            <button class="btn btn-outline-secondary btn-sm mr-2" onclick="editDevice(this)">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="removeDevice(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    devicesList.appendChild(deviceItem);
}

// Edit device
function editDevice(button) {
    const listItem = button.closest('li');
    const deviceNameElement = listItem.querySelector('strong');
    const currentName = deviceNameElement.textContent;
    
    Swal.fire({
        title: 'Edit Device',
        input: 'text',
        inputLabel: 'Device Name',
        inputValue: currentName,
        showCancelButton: true,
        confirmButtonText: 'Update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
        inputValidator: (value) => {
            if (!value.trim()) {
                return 'Please enter a device name';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            deviceNameElement.textContent = result.value.trim();
            
            // Update the time
            const timeElement = listItem.querySelector('small');
            const now = new Date();
            timeElement.textContent = `Updated: ${now.toLocaleString()}`;
            
            Swal.fire({
                title: 'Device Updated!',
                text: 'Device name has been updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Remove device
function removeDevice(button) {
    const listItem = button.closest('li');
    const deviceName = listItem.querySelector('strong').textContent;
    
    Swal.fire({
        title: 'Remove Device?',
        text: `Are you sure you want to remove "${deviceName}" from your family?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            listItem.remove();
            
            Swal.fire({
                title: 'Device Removed!',
                text: `${deviceName} has been removed from your family`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Initialize on page load
$(document).ready(function() {
    // Load saved family code if exists
    const savedCode = localStorage.getItem('familyAccessCode');
    if (savedCode) {
        document.getElementById('familyCode').value = savedCode;
    }
    
    // Auto-generate QR code on page load
    generateQRCode();
});
</script>


</body>

</html>
<?php
}
else
{
    header('location:../../index.php');
}?>
