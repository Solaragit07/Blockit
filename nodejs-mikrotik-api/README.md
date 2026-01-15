# MikroTik Device Monitor API

A Node.js Express backend that connects to MikroTik RouterOS to retrieve connected devices and real-time bandwidth usage.

## Features

- **Real-time device detection** from DHCP leases and ARP table
- **Bandwidth monitoring** using MikroTik torch functionality
- **RESTful API endpoints** for device information
- **Automatic connection management** with reconnection logic
- **CORS support** for web applications
- **Error handling** and logging

## Quick Start

### 1. Install Dependencies

```bash
cd nodejs-mikrotik-api
npm install
```

### 2. Configure Environment

Edit `.env` file with your MikroTik settings:

```env
MIKROTIK_HOST=192.168.10.1
MIKROTIK_USER=user1
MIKROTIK_PASSWORD=admin
MIKROTIK_PORT=8728
PORT=3000
```

### 3. Start Server

```bash
# Development mode
npm run dev

# Production mode
npm start
```

## API Endpoints

### Main Devices Endpoint
```
GET /devices
```
Returns connected devices with bandwidth data:
```json
{
  "success": true,
  "count": 2,
  "timestamp": "2025-08-18T15:15:00.000Z",
  "devices": [
    {
      "ip": "192.168.8.101",
      "mac": "AA:BB:CC:DD:EE:FF",
      "hostname": "Laptop",
      "rx": 1024000,
      "tx": 2048000,
      "status": "bound",
      "lastSeen": "2025-08-18T15:15:00.000Z",
      "isActive": true
    }
  ]
}
```

### Simple Format (Exact Specification)
```
GET /devices/simple
```
Returns exactly the requested format:
```json
[
  { "ip": "192.168.8.101", "mac": "AA:BB:CC:DD:EE:FF", "hostname": "Laptop", "rx": 1024, "tx": 2048 }
]
```

### Other Endpoints
- `GET /health` - Health check
- `GET /test` - Test MikroTik connection
- `GET /dhcp` - DHCP leases
- `GET /arp` - ARP table
- `GET /system` - System information
- `GET /interfaces` - Interface statistics
- `GET /bandwidth/:interface` - Real-time bandwidth data

## Usage Examples

### Test Connection
```bash
curl http://localhost:3000/test
```

### Get Connected Devices
```bash
curl http://localhost:3000/devices/simple
```

### Get Real-time Bandwidth
```bash
curl http://localhost:3000/bandwidth/bridge-lan?duration=5
```

## How It Works

1. **Device Detection**: Combines DHCP leases and ARP table to identify truly connected devices
2. **Bandwidth Monitoring**: Uses MikroTik's torch feature to capture real-time traffic data
3. **Data Correlation**: Matches bandwidth data to devices by IP address
4. **Active Filtering**: Only shows devices that are currently active on the network

## MikroTik Requirements

- **API Service Enabled**: `/ip service set api disabled=no`
- **Firewall Rule**: Allow port 8728 access
- **User Permissions**: API user needs read permissions

## Installation Commands

```bash
# Create project directory
mkdir nodejs-mikrotik-api
cd nodejs-mikrotik-api

# Install all dependencies
npm install express cors routeros-api dotenv nodemon

# Start development server
npm run dev
```

## Troubleshooting

### Connection Issues
- Verify MikroTik API service is enabled
- Check firewall rules allow port 8728
- Confirm credentials in `.env` file

### No Devices Showing
- Check if devices are actually connected
- Verify DHCP server is running
- Ensure devices have active network traffic

### Bandwidth Data Missing
- Torch feature requires active traffic
- Try increasing duration parameter
- Check interface name (default: bridge-lan)

## Security Notes

- Use environment variables for credentials
- Consider API user with minimal permissions
- Enable HTTPS in production
- Implement rate limiting for production use

## Dependencies

- **express**: Web framework
- **routeros-api**: MikroTik RouterOS API client
- **cors**: Cross-origin resource sharing
- **dotenv**: Environment configuration
