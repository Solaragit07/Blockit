# BlockIT Reports System Documentation

## Overview
The BlockIT Reports System provides comprehensive analytics and reporting capabilities for monitoring network activity, device usage, and security events.

## Files Structure

### Frontend Files
- **index.php** - Main reports dashboard with interactive charts and tables
- **export_report.php** - Handles report export functionality (CSV/PDF)

### Backend Files
- **get_reports_data.php** - API endpoint for fetching report data
- **reports_functions.php** - Helper functions for data processing
- **quick_stats.php** - Quick statistics API for widgets
- **system_test.php** - System testing and diagnostics

### Test Files
- **test_reports.php** - Backend functionality tests

## Features

### 1. Interactive Dashboard
- Overview statistics cards (Total Blocked, Active Devices, Data Usage, Security Alerts)
- Real-time blocking activity chart
- Device usage distribution pie chart
- Recent blocking events table
- Top blocked sites analysis
- Detailed usage statistics

### 2. Filtering Options
- Date ranges (Today, Yesterday, Last 7 days, Last 30 days, Custom)
- Device-specific filtering
- Report type filtering (All, Blocking, Usage, Security)

### 3. Export Capabilities
- CSV export for data analysis
- PDF/HTML export for presentations
- Custom date range exports

### 4. API Endpoints

#### get_reports_data.php Actions:
- `get_devices` - Fetch all devices for filtering
- `get_all_reports` - Complete dashboard data
- `get_blocking_events` - Recent blocking activity
- `get_usage_stats` - Device usage statistics

#### Parameters:
- `dateRange` - Time period filter
- `device` - Device ID filter ('all' for all devices)
- `reportType` - Type of report filter
- `startDate`/`endDate` - Custom date range

## Database Requirements

### Required Tables
- **device** - Device information
- **activity_log** - Activity and blocking events (auto-created)

### Activity Log Schema
```sql
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    action VARCHAR(50),
    blocked_site VARCHAR(255),
    category VARCHAR(100),
    data_usage DECIMAL(10,2) DEFAULT 0,
    session_duration INT DEFAULT 0,
    severity VARCHAR(20) DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (device_id) REFERENCES device(id) ON DELETE CASCADE
);
```

## Usage Examples

### Accessing the Reports
1. Navigate to `/main/reports/` in your BlockIT dashboard
2. Use the filter controls to customize your view
3. Click "Generate Report" to refresh data
4. Use "Export Report" to download data

### API Usage
```javascript
// Get all devices
fetch('get_reports_data.php?action=get_devices')
.then(response => response.json())
.then(data => console.log(data.devices));

// Get reports for last 7 days
fetch('get_reports_data.php?action=get_all_reports&dateRange=7days')
.then(response => response.json())
.then(data => console.log(data.data));
```

### Export Reports
```
# CSV Export
export_report.php?format=csv&dateRange=7days&device=all

# PDF Export  
export_report.php?format=pdf&dateRange=30days&device=5
```

## Mock Data
If no real activity data exists, the system generates realistic mock data for demonstration purposes, including:
- Sample blocking events
- Device usage statistics
- Security alerts
- Bandwidth usage patterns

## Security Features
- Login verification required for all endpoints
- SQL injection prevention with prepared statements
- Input sanitization and validation
- Secure session management

## Browser Compatibility
- Modern browsers supporting Chart.js
- DataTables for responsive table handling
- Bootstrap 4 for mobile-friendly interface

## Troubleshooting

### Common Issues
1. **"Function redeclared" error**: Fixed by using `include_once` instead of `include`
2. **No data showing**: Check database connection and table structure
3. **Export not working**: Verify file permissions and PHP configuration

### Testing
Run `system_test.php` to verify:
- File inclusion paths
- Database connectivity
- Function availability
- Table structure

---

For more technical details, refer to the individual PHP files which contain detailed comments and documentation.
