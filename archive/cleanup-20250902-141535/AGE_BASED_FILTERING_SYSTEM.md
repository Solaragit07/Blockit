# Age-Based Content Filtering System - Complete Implementation

## Overview

The Age-Based Content Filtering System is a comprehensive parental control solution that allows administrators to set age-appropriate content restrictions across all connected devices. The system implements sophisticated filtering rules with priority-based enforcement and real-time application.

## System Architecture

### 1. Database Structure

#### Age-Based Blacklist Table
```sql
CREATE TABLE age_based_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    max_age INT NOT NULL,
    category VARCHAR(100) DEFAULT 'general',
    added_by VARCHAR(100),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_domain_age (domain, max_age),
    INDEX idx_domain (domain),
    INDEX idx_max_age (max_age),
    INDEX idx_category (category)
);
```

#### Age-Based Whitelist Table
```sql
CREATE TABLE age_based_whitelist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    min_age INT NOT NULL,
    category VARCHAR(100) DEFAULT 'educational',
    added_by VARCHAR(100),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_domain_age (domain, min_age),
    INDEX idx_domain (domain),
    INDEX idx_min_age (min_age),
    INDEX idx_category (category)
);
```

#### Age Filter Logs Table
```sql
CREATE TABLE age_filter_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    device_age INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rules_count INT DEFAULT 0,
    INDEX idx_device_id (device_id),
    INDEX idx_applied_at (applied_at),
    FOREIGN KEY (device_id) REFERENCES device(id) ON DELETE CASCADE
);
```

### 2. Core Components

#### AgeBasedFilterEngine Class
**Location:** `includes/AgeBasedFilterEngine.php`

**Key Methods:**
- `checkDomainAccess($domain, $age)` - Determines if a domain is accessible for a specific age
- `addToBlacklist($domain, $maxAge, $category)` - Adds domain to age-based blacklist
- `addToWhitelist($domain, $minAge, $category)` - Adds domain to age-based whitelist
- `getBlockedDomainsForAge($age)` - Gets all blocked domains for a specific age
- `getAllowedDomainsForAge($age)` - Gets all allowed domains for a specific age
- `exportForRouter($age)` - Exports filtering rules for router configuration

**Priority Logic:**
1. **Blacklist Override:** If a domain is blacklisted for an age, it's blocked regardless of whitelist
2. **Whitelist Allow:** If a domain is whitelisted and user meets minimum age, it's allowed
3. **Default Policy:** If no specific rules exist, follows system default (typically allow)

#### Device Age Enforcement API
**Location:** `api/device_age_enforcement.php`

**Available Endpoints:**
- `get_device_age_rules` - Get age-based rules for a specific device
- `apply_age_filters_to_device` - Apply age-based filters to a specific device
- `get_all_devices_age_status` - Get age-based filter status for all devices
- `bulk_apply_age_filters` - Apply age-based filters to all devices with valid ages

### 3. User Interfaces

#### Age-Based Filters Management
**Location:** `main/blocklist/age_based_filters.php`

**Features:**
- Add/remove domains from blacklist/whitelist
- Category-based filtering (Educational, Adult Content, Gambling, etc.)
- Real-time preview of allowed/blocked domains for any age
- Bulk operations for category management
- AJAX-powered interface with instant updates

**Categories Available:**
- Educational
- Adult Content
- Gambling
- Social Media
- Gaming
- News
- Entertainment
- Shopping
- General

#### Device Age Filter Management
**Location:** `main/devices/age_filters.php`

**Features:**
- View all devices with their age-based filter status
- Apply filters to individual devices
- Bulk apply filters to all devices
- Real-time status monitoring (Active, Pending, No Age Set)
- Device-specific rule viewing

### 4. Integration Points

#### Router Integration
The system integrates with MikroTik routers through the FastApiHelper class:
- Exports filtering rules in router-compatible format
- Applies rules instantly across network infrastructure
- Maintains synchronization between database and router configuration

#### Device Management
Integrates with existing device management system:
- Uses device age settings for filter application
- Tracks filter application history
- Provides device-specific filtering status

## Usage Workflow

### 1. Setting Up Age-Based Rules

1. **Access Age-Based Filters:**
   - Navigate to BlockList Management → Age-Based Filters
   - Or directly access `main/blocklist/age_based_filters.php`

2. **Add Blacklist Rules:**
   - Select "Blacklist" tab
   - Enter domain name (e.g., "facebook.com")
   - Set maximum age (users above this age will be blocked)
   - Choose appropriate category
   - Click "Add to Blacklist"

3. **Add Whitelist Rules:**
   - Select "Whitelist" tab
   - Enter domain name (e.g., "education.com")
   - Set minimum age (users below this age will be blocked)
   - Choose appropriate category
   - Click "Add to Whitelist"

4. **Preview Rules:**
   - Use the "Preview for Age" feature
   - Enter any age to see what domains would be allowed/blocked
   - Verify rules work as expected

### 2. Applying Filters to Devices

1. **Set Device Ages:**
   - Ensure all devices have proper age settings in device management
   - This is required for age-based filtering to work

2. **Apply to Individual Device:**
   - Navigate to Devices → Age Filters
   - Or directly access `main/devices/age_filters.php`
   - Click "Apply Filters" for specific device

3. **Bulk Apply:**
   - Use "Apply to All" button to apply filters to all devices with age settings
   - Monitor application status in real-time

### 3. Monitoring and Management

1. **Real-Time Status:**
   - Device filter status shows as Active, Pending, or No Age Set
   - Statistics panel shows filter distribution across devices

2. **Rule Management:**
   - View device-specific rules by clicking "View Rules"
   - Modify age-based rules anytime in the filters interface
   - Changes apply instantly to connected devices

## Priority Rules and Conflict Resolution

### Rule Priority (Highest to Lowest):
1. **Age-Based Blacklist:** Domain blocked if user's age ≤ max_age
2. **Age-Based Whitelist:** Domain allowed if user's age ≥ min_age
3. **System Default:** Standard blocking/allowing behavior

### Conflict Examples:
- Domain: `gaming.com`
- Blacklist rule: max_age = 16 (blocked for ages 16 and under)
- Whitelist rule: min_age = 10 (allowed for ages 10 and over)
- **Result for age 12:** BLOCKED (blacklist takes priority)
- **Result for age 18:** ALLOWED (not affected by blacklist)

## Technical Implementation Details

### Database Optimization
- Indexed columns for fast lookups
- Unique constraints prevent duplicate rules
- Foreign key relationships maintain data integrity
- Efficient query patterns for real-time filtering

### Performance Considerations
- AJAX-powered interfaces for responsive user experience
- Efficient caching of filtering rules
- Optimized database queries with proper indexing
- Background processing for bulk operations

### Security Features
- SQL injection protection through prepared statements
- Session-based authentication for all operations
- Input validation and sanitization
- Audit logging for all filter applications

## Integration with Router System

### Rule Export Format
```php
$rules = [
    'blacklist' => ['domain1.com', 'domain2.com'],
    'whitelist' => ['education.com', 'school.edu']
];
```

### Router Update Process
1. Generate rules for specific device age
2. Format rules for router API
3. Apply rules through FastApiHelper
4. Log application for audit trail
5. Update device status in database

## Future Enhancements

### Planned Features
- Time-based filtering (restrict access during certain hours)
- Bandwidth limiting based on age groups
- Content category scoring system
- Machine learning for automatic categorization
- Mobile app for remote management
- Detailed reporting and analytics

### API Extensions
- RESTful API for third-party integrations
- Webhook support for real-time notifications
- Bulk import/export functionality
- Advanced query capabilities

## Troubleshooting

### Common Issues

1. **Device not applying filters:**
   - Check if device age is set properly
   - Verify router connectivity
   - Check age_filter_logs table for application history

2. **Rules not working as expected:**
   - Use preview feature to test rule logic
   - Check for conflicting blacklist/whitelist rules
   - Verify domain name format (no http:// prefix)

3. **Performance issues:**
   - Check database indexes
   - Monitor query execution times
   - Consider rule optimization for large datasets

### Debug Information
- Enable detailed logging in AgeBasedFilterEngine
- Check PHP error logs for system issues
- Monitor router API response times
- Use browser developer tools for AJAX debugging

## Conclusion

The Age-Based Content Filtering System provides a robust, scalable solution for managing content access based on user age. With its priority-based rule system, real-time enforcement, and comprehensive management interfaces, it offers administrators complete control over age-appropriate content filtering across their network infrastructure.

The system is designed for ease of use while maintaining the flexibility needed for complex filtering scenarios. Its integration with existing device management and router systems ensures seamless operation within the broader network management ecosystem.
