require('dotenv').config();
const express = require('express');
const cors = require('cors');
const http = require('http');

const app = express();
const PORT = process.env.PORT || 3000;

// CORS configuration
app.use(cors());
app.use(express.json());

// Proxy function to call PHP backend
async function callPHPBackend(action) {
    return new Promise((resolve, reject) => {
        const options = {
            hostname: 'localhost',
            port: 80,
            path: `/blockit/api_real_internet_devices.php?action=${action}`,
            method: 'GET',
            headers: {
                'User-Agent': 'Node.js MikroTik API'
            }
        };

        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', (chunk) => {
                data += chunk;
            });
            res.on('end', () => {
                try {
                    const result = JSON.parse(data);
                    resolve(result);
                } catch (error) {
                    reject(new Error('Invalid JSON response from PHP backend'));
                }
            });
        });

        req.on('error', (error) => {
            reject(error);
        });

        req.setTimeout(10000, () => {
            req.abort();
            reject(new Error('Request timeout'));
        });

        req.end();
    });
}

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        backend: 'PHP + Node.js Proxy'
    });
});

// Real-time internet-connected devices endpoint
app.get('/devices/internet', async (req, res) => {
    try {
        console.log('📡 Fetching internet-connected devices via PHP backend...');
        const result = await callPHPBackend('internet');
        
        if (result.success) {
            console.log(`🌐 Found ${result.count} internet-connected devices`);
            res.json(result);
        } else {
            throw new Error(result.error || 'PHP backend returned error');
        }
    } catch (error) {
        console.error('❌ Error in /devices/internet:', error.message);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch internet-connected devices',
            message: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

// Simple format internet devices
app.get('/devices/internet/simple', async (req, res) => {
    try {
        console.log('📋 Fetching internet devices (simple format)...');
        const result = await callPHPBackend('simple');
        
        if (Array.isArray(result)) {
            console.log(`📊 Returning ${result.length} devices in simple format`);
            res.json(result);
        } else {
            throw new Error('Invalid response format from PHP backend');
        }
    } catch (error) {
        console.error('❌ Error in /devices/internet/simple:', error.message);
        res.status(500).json({
            error: 'Failed to fetch internet devices',
            message: error.message
        });
    }
});

// Test connection endpoint
app.get('/test', async (req, res) => {
    try {
        console.log('🔍 Testing MikroTik connection via PHP backend...');
        const result = await callPHPBackend('test');
        
        if (result.success) {
            res.json({
                success: true,
                message: 'MikroTik connection successful via PHP backend',
                timestamp: new Date().toISOString(),
                backend: 'PHP RouterOS Library'
            });
        } else {
            throw new Error(result.error || 'Connection test failed');
        }
    } catch (error) {
        console.error('❌ Connection test failed:', error.message);
        res.status(500).json({
            success: false,
            message: 'MikroTik connection failed',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

// Legacy endpoints for compatibility
app.get('/devices', async (req, res) => {
    try {
        const result = await callPHPBackend('internet');
        res.json(result);
    } catch (error) {
        res.status(500).json({
            success: false,
            error: 'Failed to fetch devices',
            message: error.message
        });
    }
});

app.get('/devices/simple', async (req, res) => {
    try {
        const result = await callPHPBackend('simple');
        res.json(result);
    } catch (error) {
        res.status(500).json({
            error: 'Failed to fetch devices',
            message: error.message
        });
    }
});

// Error handling middleware
app.use((error, req, res, next) => {
    console.error('❌ Unhandled error:', error);
    res.status(500).json({
        success: false,
        error: 'Internal server error',
        message: error.message,
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
            'GET /devices/internet',
            'GET /devices/internet/simple',
            'GET /devices',
            'GET /devices/simple',
            'GET /test'
        ]
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`🚀 MikroTik Internet Device Monitor running on port ${PORT}`);
    console.log(`📡 Using PHP backend at http://localhost/blockit/api_real_internet_devices.php`);
    console.log(`🌐 Available endpoints:`);
    console.log(`   🔥 GET http://localhost:${PORT}/devices/internet - Internet-connected devices only`);
    console.log(`   📋 GET http://localhost:${PORT}/devices/internet/simple - Simple format`);
    console.log(`   🔍 GET http://localhost:${PORT}/test - Test connection`);
    console.log(`   ❤️  GET http://localhost:${PORT}/health - Health check`);
    
    // Test PHP backend connection
    setTimeout(async () => {
        try {
            const result = await callPHPBackend('test');
            if (result.success) {
                console.log('✅ PHP backend connection verified');
            } else {
                console.log('❌ PHP backend test failed:', result.error);
            }
        } catch (error) {
            console.error('❌ PHP backend connection failed:', error.message);
        }
    }, 1000);
});

module.exports = app;
