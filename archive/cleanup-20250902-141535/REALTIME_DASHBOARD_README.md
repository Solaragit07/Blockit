# BlockIt Real-time Parental Control Dashboard

A comprehensive real-time bandwidth monitoring and parental control system that integrates with MikroTik routers to provide live activity tracking and intelligent categorization.

## 🚀 Features

### Real-time Monitoring
- **Live Bandwidth Tracking**: Monitor upload/download speeds per device in real-time
- **WebSocket Connectivity**: Instant updates without page refreshing
- **Activity Categorization**: Intelligent detection of user activities:
  - 📖 **READING**: Low bandwidth, long sessions (news, blogs, text content)
  - 📱 **SCROLLING**: Medium bandwidth, frequent bursts (social media)
  - 🎥 **WATCHING**: High sustained bandwidth (YouTube, Netflix, streaming)
  - 🎮 **PLAYING**: High bandwidth + low latency (gaming, Steam)
  - 🌐 **BROWSING**: General web browsing
  - 😴 **IDLE**: No significant activity

### Dashboard Features
- **Live Statistics Cards**: Total bandwidth, active devices, peak activity
- **Interactive Charts**: Real-time bandwidth graphs and activity distribution
- **Enhanced Device Table**: Detailed device information with activity indicators
- **Connection Status**: Real-time monitoring of server connectivity
- **Fallback System**: Automatic fallback to API polling if WebSocket fails

## 🛠️ Installation

### Prerequisites
1. **MikroTik Router** with API enabled (default: 192.168.10.1)
2. **XAMPP/WAMP** server running PHP
3. **Node.js** (version 14 or higher)

### Step 1: MikroTik Configuration
1. Enable API on your MikroTik router:
   ```
   /ip service enable api
   /ip service set api port=8728
   ```
2. Create API user (optional but recommended):
   ```
   /user add name=blockit password=your_password group=read
   ```

### Step 2: Node.js Server Setup
1. Navigate to the realtime-server directory:
   ```cmd
   cd c:\xampp\htdocs\blockit\realtime-server
   ```

2. Run the installation script:
   ```cmd
   install.bat
   ```
   Or manually install dependencies:
   ```cmd
   npm install
   ```

### Step 3: Start the Real-time Server
```cmd
start.bat
```
Or manually:
```cmd
node server.js
```

The server will start on:
- **WebSocket**: `ws://localhost:8080`
- **HTTP API**: `http://localhost:3001`

### Step 4: Access the Dashboard
1. Start your XAMPP server
2. Navigate to: `http://localhost/blockit/main/dashboard/`
3. The dashboard will automatically connect to the real-time server

## 🔧 Configuration

### MikroTik Connection Settings
Edit the configuration in both files:

**server.js** (Node.js):
```javascript
const MIKROTIK_CONFIG = {
    host: '192.168.10.1',  // Your router IP
    user: 'admin',         // Router username
    password: ''           // Router password
};
```

**MikroTikBandwidthMonitor.php**:
```php
$monitor = new MikroTikBandwidthMonitor('192.168.10.1', 'admin', '');
```

### Activity Detection Tuning
Modify the activity categorization rules in `server.js`:

```javascript
const ACTIVITY_RULES = {
    READING: {
        bandwidth_threshold: 50000,    // 50KB/s max
        session_duration: 300,         // 5 minutes minimum
        burst_frequency: 0.2           // Low burst frequency
    },
    WATCHING: {
        bandwidth_threshold: 1000000,  // 1MB/s minimum
        sustained_duration: 30,        // 30 seconds sustained
        video_domains: ['youtube.com', 'netflix.com']
    }
    // ... more rules
};
```

## 📊 API Endpoints

### WebSocket Events
- **Connection**: `{ type: 'connection', message: 'Connected' }`
- **Bandwidth Update**: `{ type: 'bandwidth_update', data: {...} }`
- **Ping/Pong**: Keep-alive mechanism

### HTTP API Fallback
- **GET** `/api/devices` - Current device bandwidth data
- **GET** `/api/activities` - Activity distribution summary

### PHP API Endpoint
- **GET** `/api/realtime-bandwidth.php` - Fallback PHP endpoint

## 🎯 Dashboard Interface

### Statistics Cards
- **Total Download**: Live download speed across all devices
- **Total Upload**: Live upload speed across all devices  
- **Active Devices**: Number of currently active devices
- **Peak Activity**: Most common activity type

### Real-time Charts
1. **Bandwidth Chart**: Line chart showing download/upload over time
2. **Activity Distribution**: Doughnut chart showing activity breakdown

### Device Table Columns
1. **Avatar**: Device profile image
2. **Device Name**: Hostname and MAC address
3. **IP Address**: Current IP assignment
4. **Download**: Real-time download speed
5. **Upload**: Real-time upload speed
6. **Total**: Combined bandwidth usage
7. **Activity**: Detected activity with confidence
8. **Confidence**: Activity detection confidence %
9. **Status**: Connection status
10. **Actions**: Control buttons

## 🔍 Activity Detection Algorithm

### Detection Logic
1. **Bandwidth Analysis**: Measures sustained vs burst patterns
2. **Domain Classification**: Checks accessed domains against known categories
3. **Session Patterns**: Analyzes connection duration and frequency
4. **Port Analysis**: Identifies gaming and streaming protocols
5. **Confidence Scoring**: Provides accuracy percentage for each detection

### Activity Types Explained

| Activity | Characteristics | Common Examples |
|----------|----------------|-----------------|
| **READING** | Low bandwidth (< 50KB/s), long sessions | News sites, blogs, documentation |
| **SCROLLING** | Medium bursts, frequent requests | Facebook, Instagram, Twitter |
| **WATCHING** | High sustained (> 1MB/s), video domains | YouTube, Netflix, Twitch |
| **PLAYING** | High bandwidth + low latency patterns | Steam games, online gaming |
| **BROWSING** | Variable patterns, mixed content | General web browsing |
| **IDLE** | Minimal activity (< 10KB/s) | Background sync, standby |

## 🚨 Troubleshooting

### WebSocket Connection Issues
1. **Check Node.js server**: Ensure `node server.js` is running
2. **Firewall**: Allow ports 8080 (WebSocket) and 3001 (HTTP)
3. **Console errors**: Check browser developer console for errors

### MikroTik API Issues
1. **API Status**: Verify API is enabled on router
2. **Credentials**: Check username/password in configuration
3. **Network**: Ensure router is accessible from server

### Dashboard Not Updating
1. **Fallback Mode**: Dashboard automatically switches to PHP API polling
2. **PHP Errors**: Check error logs in `/blockit/api/` directory
3. **CORS Issues**: Verify CORS headers in API responses

### Performance Optimization
- **Update Interval**: Adjust WebSocket broadcast frequency (default: 2 seconds)
- **Data Retention**: Modify chart data points limit (default: 20 points)
- **Device Limit**: Set maximum devices to monitor simultaneously

## 📝 File Structure

```
blockit/
├── realtime-server/           # Node.js WebSocket Server
│   ├── server.js             # Main server application
│   ├── package.json          # Node.js dependencies
│   ├── install.bat           # Windows installation script
│   └── start.bat             # Windows startup script
├── includes/
│   └── MikroTikBandwidthMonitor.php  # PHP monitoring class
├── api/
│   └── realtime-bandwidth.php        # PHP API fallback
├── main/dashboard/
│   └── index.php             # Real-time dashboard interface
└── README.md                 # This file
```

## 🔮 Future Enhancements

- [ ] Historical bandwidth reporting
- [ ] Custom activity rule configuration UI
- [ ] Email/SMS alerts for unusual activity
- [ ] Mobile responsive dashboard
- [ ] Multi-router support
- [ ] Machine learning activity detection
- [ ] Bandwidth scheduling and automation

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Submit a pull request with detailed description

## 📄 License

This project is part of the BlockIt Parental Control System.

---

**Need Help?** Check the troubleshooting section or create an issue with detailed error information.
