require('dotenv').config();
const express = require('express');
const cors = require('cors');
const MikroTikConnector = require('./mikrotik-connector');

const app = express();
const PORT = process.env.PORT || 3000;

// CORS configuration
const corsOptions = {
    origin: process.env.CORS_ORIGINS ? process.env.CORS_ORIGINS.split(',') : ['http://localhost:3000'],
    methods: ['GET', 'POST'],
    allowedHeaders: ['Content-Type', 'Authorization'],
};

app.use(cors(corsOptions));
app.use(express.json());

// Initialize MikroTik connector
const mikrotik = new MikroTikConnector({
    host: process.env.MIKROTIK_HOST,
    user: process.env.MIKROTIK_USER,
    password: process.env.MIKROTIK_PASSWORD,
    port: parseInt(process.env.MIKROTIK_PORT)
});

// Middleware for error handling
const asyncHandler = (fn) => (req, res, next) => {
    Promise.resolve(fn(req, res, next)).catch(next);
};

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        mikrotik_connected: mikrotik.isConnected
    });
});

// Main devices endpoint - returns connected devices with bandwidth
app.get('/devices', asyncHandler(async (req, res) => {
    try {
        console.log('📡 Incoming request for /devices');
        
        const devices = await mikrotik.getConnectedDevicesWithBandwidth();
        
        // Format response according to requirements
        const formattedDevices = devices.map(device => ({
            ip: device.ip,
            mac: device.mac,
            hostname: device.hostname,
            rx: device.rx,
            tx: device.tx,
            status: device.status,
            lastSeen: device.lastSeen,
            isActive: device.isActive
        }));
        
        res.json({
            success: true,
            count: formattedDevices.length,
            timestamp: new Date().toISOString(),
            devices: formattedDevices
        });
        
        console.log(`✅ Returned ${formattedDevices.length} devices`);
        
    } catch (error) {
        console.error('❌ Error in /devices endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch devices',
            message: error.message,
            timestamp: new Date().toISOString()
        });
    }
}));

// Simple devices endpoint (exact format requested)
app.get('/devices/simple', asyncHandler(async (req, res) => {
    try {
        const devices = await mikrotik.getConnectedDevicesWithBandwidth();
        
        // Return exact format requested: [{ "ip": "...", "mac": "...", "hostname": "...", "rx": 1024, "tx": 2048 }]
        const simpleFormat = devices.map(device => ({
            ip: device.ip,
            mac: device.mac,
            hostname: device.hostname,
            rx: device.rx,
            tx: device.tx
        }));
        
        res.json(simpleFormat);
        
    } catch (error) {
        console.error('❌ Error in /devices/simple endpoint:', error.message);
        res.status(500).json({
            error: 'Failed to fetch devices',
            message: error.message
        });
    }
}));

// Real-time internet-connected devices endpoint
app.get('/devices/internet', asyncHandler(async (req, res) => {
    try {
        const devices = await mikrotik.getInternetConnectedDevices();
        
        res.json({
            success: true,
            count: devices.length,
            devices: devices,
            timestamp: new Date().toISOString(),
            description: 'Devices with active internet connectivity'
        });
        
        console.log(`🌐 Returned ${devices.length} internet-connected devices`);
        
    } catch (error) {
        console.error('❌ Error in /devices/internet endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch internet-connected devices',
            message: error.message
        });
    }
}));

// Real-time internet devices (simple format)
app.get('/devices/internet/simple', asyncHandler(async (req, res) => {
    try {
        const devices = await mikrotik.getInternetConnectedDevices();
        
        // Return exact format requested: [{"ip", "mac", "hostname", "rx", "tx"}]
        const simpleFormat = devices.map(device => ({
            ip: device.ip,
            mac: device.mac,
            hostname: device.hostname,
            rx: device.rx,
            tx: device.tx
        }));
        
        res.json(simpleFormat);
        
    } catch (error) {
        console.error('❌ Error in /devices/internet/simple endpoint:', error.message);
        res.status(500).json({
            error: 'Failed to fetch internet-connected devices',
            message: error.message
        });
    }
}));

// Ultra-strict active devices (only devices with significant recent traffic)
app.get('/devices/active', asyncHandler(async (req, res) => {
    try {
        const allDevices = await mikrotik.getConnectedDevicesWithBandwidth();
        
        // Filter for devices with significant recent activity
        const minTraffic = parseInt(req.query.minTraffic) || 500; // At least 500 bytes
        const activeDevices = allDevices.filter(device => {
            const totalTraffic = device.rx + device.tx;
            return totalTraffic >= minTraffic;
        });
        
        res.json({
            success: true,
            count: activeDevices.length,
            totalScanned: allDevices.length,
            minTrafficFilter: minTraffic,
            devices: activeDevices,
            timestamp: new Date().toISOString()
        });
        
        console.log(`🔥 Ultra-strict filter: ${activeDevices.length}/${allDevices.length} devices with ≥${minTraffic} bytes traffic`);
        
    } catch (error) {
        console.error('❌ Error in /devices/active endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch active devices',
            message: error.message
        });
    }
}));

// DHCP leases endpoint
app.get('/dhcp', asyncHandler(async (req, res) => {
    try {
        const leases = await mikrotik.getDHCPLeases();
        res.json({
            success: true,
            count: leases.length,
            timestamp: new Date().toISOString(),
            leases: leases
        });
    } catch (error) {
        console.error('❌ Error in /dhcp endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch DHCP leases',
            message: error.message
        });
    }
}));

// ARP table endpoint
app.get('/arp', asyncHandler(async (req, res) => {
    try {
        const arpEntries = await mikrotik.getARPTable();
        res.json({
            success: true,
            count: arpEntries.length,
            timestamp: new Date().toISOString(),
            arp: arpEntries
        });
    } catch (error) {
        console.error('❌ Error in /arp endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch ARP table',
            message: error.message
        });
    }
}));

// System info endpoint
app.get('/system', asyncHandler(async (req, res) => {
    try {
        const systemInfo = await mikrotik.getSystemInfo();
        res.json({
            success: true,
            timestamp: new Date().toISOString(),
            system: systemInfo
        });
    } catch (error) {
        console.error('❌ Error in /system endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch system info',
            message: error.message
        });
    }
}));

// Interface stats endpoint
app.get('/interfaces', asyncHandler(async (req, res) => {
    try {
        const interfaces = await mikrotik.getInterfaceStats();
        res.json({
            success: true,
            count: interfaces.length,
            timestamp: new Date().toISOString(),
            interfaces: interfaces
        });
    } catch (error) {
        console.error('❌ Error in /interfaces endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch interface stats',
            message: error.message
        });
    }
}));

// Bandwidth monitoring endpoint with real-time updates
app.get('/bandwidth/:interface?', asyncHandler(async (req, res) => {
    try {
        const interfaceName = req.params.interface || 'bridge-lan';
        const duration = parseInt(req.query.duration) || 2;
        
        const bandwidth = await mikrotik.getBandwidthUsage(interfaceName, duration);
        res.json({
            success: true,
            interface: interfaceName,
            duration: duration,
            count: bandwidth.length,
            timestamp: new Date().toISOString(),
            bandwidth: bandwidth
        });
    } catch (error) {
        console.error('❌ Error in /bandwidth endpoint:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch bandwidth data',
            message: error.message
        });
    }
}));

// Test connection endpoint
app.get('/test', asyncHandler(async (req, res) => {
    try {
        await mikrotik.connect();
        const systemInfo = await mikrotik.getSystemInfo();
        
        res.json({
            success: true,
            message: 'MikroTik connection successful',
            timestamp: new Date().toISOString(),
            mikrotik: {
                host: process.env.MIKROTIK_HOST,
                connected: mikrotik.isConnected,
                identity: systemInfo.identity.name || 'Unknown'
            }
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: 'MikroTik connection failed',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
}));

// Error handling middleware
app.use((error, req, res, next) => {
    console.error('❌ Unhandled error:', error);
    res.status(500).json({
        success: false,
        error: 'Internal server error',
        message: process.env.NODE_ENV === 'development' ? error.message : 'Something went wrong',
        timestamp: new Date().toISOString()
    });
});

// 404 handler
app.use('*', (req, res) => {
    res.status(404).json({
        success: false,
        error: 'Endpoint not found',
        message: `Route ${req.method} ${req.originalUrl} not found`,
        timestamp: new Date().toISOString(),
        availableEndpoints: [
            'GET /health',
            'GET /devices',
            'GET /devices/simple', 
            'GET /devices/active',
            'GET /devices/internet',
            'GET /devices/internet/simple',
            'GET /dhcp',
            'GET /arp',
            'GET /system',
            'GET /interfaces',
            'GET /bandwidth/:interface?',
            'GET /test'
        ]
    });
});

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('\n🛑 Shutting down server...');
    await mikrotik.disconnect();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('\n🛑 Shutting down server...');
    await mikrotik.disconnect();
    process.exit(0);
});

// Start server
app.listen(PORT, () => {
    console.log(`🚀 MikroTik Device Monitor API running on port ${PORT}`);
    console.log(`📡 MikroTik Host: ${process.env.MIKROTIK_HOST}`);
    console.log(`🌐 Available endpoints:`);
    console.log(`   📊 GET http://localhost:${PORT}/devices - Connected devices with bandwidth`);
    console.log(`   📋 GET http://localhost:${PORT}/devices/simple - Simple format devices`);
    console.log(`   🔍 GET http://localhost:${PORT}/test - Test MikroTik connection`);
    console.log(`   ❤️  GET http://localhost:${PORT}/health - Health check`);
    
    // Test connection on startup
    setTimeout(async () => {
        try {
            await mikrotik.connect();
            console.log('✅ Initial MikroTik connection established');
        } catch (error) {
            console.error('❌ Initial MikroTik connection failed:', error.message);
        }
    }, 1000);
});

module.exports = app;
