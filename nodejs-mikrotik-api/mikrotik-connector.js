const RouterOSAPI = require('routeros-api');

class MikroTikConnector {
    constructor(config) {
        this.config = {
            host: config.host || '192.168.10.1',
            user: config.user || 'user1',
            password: config.password || 'admin',
            port: config.port || 8728,
            timeout: config.timeout || 10000
        };
        this.conn = null;
        this.isConnected = false;
    }

    async connect() {
        try {
            // For routeros-api v1.0.2, use the default export directly
            this.conn = RouterOSAPI({
                host: this.config.host,
                user: this.config.user,
                password: this.config.password,
                port: this.config.port,
                timeout: this.config.timeout
            });

            await this.conn.connect();
            this.isConnected = true;
            console.log('✅ Connected to MikroTik RouterOS successfully');
            return true;
        } catch (error) {
            console.error('❌ Failed to connect to MikroTik:', error.message);
            this.isConnected = false;
            throw error;
        }
    }

    async disconnect() {
        if (this.conn && this.isConnected) {
            try {
                await this.conn.close();
                this.isConnected = false;
                console.log('🔌 Disconnected from MikroTik');
            } catch (error) {
                console.error('Error disconnecting from MikroTik:', error.message);
            }
        }
    }

    async ensureConnection() {
        if (!this.isConnected || !this.conn) {
            await this.connect();
        }
    }

    async getDHCPLeases() {
        try {
            await this.ensureConnection();
            
            const leases = await this.conn.write('/ip/dhcp-server/lease/print');
            return leases;
        } catch (error) {
            console.error('Error getting DHCP leases:', error.message);
            throw error;
        }
    }

    async getARPTable() {
        try {
            await this.ensureConnection();
            
            const arpEntries = await this.conn.write('/ip/arp/print');
            return arpEntries;
        } catch (error) {
            console.error('Error getting ARP table:', error.message);
            throw error;
        }
    }

    async getInterfaceStats() {
        try {
            await this.ensureConnection();
            
            const interfaces = await this.conn.write('/interface/print', {
                '.proplist': '.id,name,type,running,rx-byte,tx-byte,rx-packet,tx-packet'
            });
            return interfaces;
        } catch (error) {
            console.error('Error getting interface stats:', error.message);
            throw error;
        }
    }

    async getBandwidthUsage(interfaceName = 'bridge-lan', duration = 1) {
        try {
            await this.ensureConnection();
            
            // Use torch to get real-time bandwidth per IP
            const torchData = await this.conn.write('/tool/torch', {
                'interface': interfaceName,
                'duration': duration.toString(),
                'port': 'any',
                'protocol': 'any'
            });
            
            return torchData;
        } catch (error) {
            console.error('Error getting bandwidth usage:', error.message);
            return [];
        }
    }

    async getInternetConnectedDevices() {
        try {
            console.log('🌐 Detecting devices with ACTIVE internet connectivity...');
            
            // Get real-time bandwidth data (longer duration for better detection)
            const torchData = await this.getBandwidthUsage('bridge-lan', 5);
            console.log(`📊 Torch captured ${torchData.length} traffic entries`);
            
            // Get ARP table for device identification
            const arpEntries = await this.getARPTable();
            console.log(`📡 Found ${arpEntries.length} ARP entries`);
            
            // Get DHCP leases for hostnames
            const dhcpLeases = await this.getDHCPLeases();
            console.log(`📋 Found ${dhcpLeases.length} DHCP leases`);
            
            // Create maps for quick lookup
            const dhcpMap = new Map();
            dhcpLeases.forEach(lease => {
                if (lease.address && lease['mac-address']) {
                    dhcpMap.set(lease.address, {
                        mac: lease['mac-address'],
                        hostname: lease['host-name'] || 'Unknown Device',
                        status: lease.status
                    });
                }
            });

            const arpMap = new Map();
            arpEntries.forEach(arp => {
                if (arp.complete === 'true' && arp.address && arp['mac-address']) {
                    arpMap.set(arp.address, {
                        mac: arp['mac-address'],
                        interface: arp.interface,
                        isReachable: true
                    });
                }
            });

            // Analyze traffic data to find devices with internet activity
            const internetActiveDevices = new Map();
            
            torchData.forEach(entry => {
                if (entry.src && entry.dst) {
                    const srcIP = entry.src;
                    const dstIP = entry.dst;
                    const rxBytes = parseInt(entry['rx-bytes']) || 0;
                    const txBytes = parseInt(entry['tx-bytes']) || 0;
                    
                    // Check if this is internet traffic (not local subnet)
                    const isInternetTraffic = !this.isLocalIP(dstIP) || !this.isLocalIP(srcIP);
                    
                    if (isInternetTraffic && (rxBytes > 0 || txBytes > 0)) {
                        // Source device is accessing internet
                        if (this.isLocalIP(srcIP) && arpMap.has(srcIP)) {
                            const existing = internetActiveDevices.get(srcIP) || { rx: 0, tx: 0, connections: 0 };
                            internetActiveDevices.set(srcIP, {
                                rx: existing.rx + rxBytes,
                                tx: existing.tx + txBytes,
                                connections: existing.connections + 1,
                                lastActivity: new Date().toISOString()
                            });
                        }
                        
                        // Destination device receiving from internet
                        if (this.isLocalIP(dstIP) && arpMap.has(dstIP)) {
                            const existing = internetActiveDevices.get(dstIP) || { rx: 0, tx: 0, connections: 0 };
                            internetActiveDevices.set(dstIP, {
                                rx: existing.rx + txBytes,
                                tx: existing.tx + rxBytes,
                                connections: existing.connections + 1,
                                lastActivity: new Date().toISOString()
                            });
                        }
                    }
                }
            });

            // Build final device list
            const connectedDevices = [];
            
            internetActiveDevices.forEach((traffic, ip) => {
                const arpInfo = arpMap.get(ip);
                const dhcpInfo = dhcpMap.get(ip);
                
                if (arpInfo && (traffic.rx > 0 || traffic.tx > 0)) {
                    connectedDevices.push({
                        ip: ip,
                        mac: arpInfo.mac,
                        hostname: dhcpInfo ? dhcpInfo.hostname : 'Internet Device',
                        status: dhcpInfo ? dhcpInfo.status : 'active',
                        rx: traffic.rx,
                        tx: traffic.tx,
                        connections: traffic.connections,
                        interface: arpInfo.interface,
                        lastActivity: traffic.lastActivity,
                        isInternetActive: true,
                        isActive: true
                    });
                }
            });

            // Sort by total internet traffic
            connectedDevices.sort((a, b) => (b.rx + b.tx) - (a.rx + a.tx));

            console.log(`🌐 Found ${connectedDevices.length} devices with ACTIVE internet connectivity`);
            console.log(`   - Total traffic entries analyzed: ${torchData.length}`);
            console.log(`   - Devices with internet activity: ${internetActiveDevices.size}`);
            
            return connectedDevices;

        } catch (error) {
            console.error('❌ Error detecting internet-connected devices:', error.message);
            throw error;
        }
    }

    isLocalIP(ip) {
        if (!ip) return false;
        
        // Check for common local IP ranges
        const localRanges = [
            /^192\.168\./,
            /^10\./,
            /^172\.(1[6-9]|2[0-9]|3[0-1])\./,
            /^127\./,
            /^169\.254\./
        ];
        
        return localRanges.some(range => range.test(ip));
    }

    async getConnectedDevicesWithBandwidth() {
        try {
            console.log('🔍 Fetching ONLY truly active/connected devices...');
            
            // Get bandwidth data first - this shows devices with current network activity
            const bandwidthData = await this.getBandwidthUsage('bridge-lan', 3);
            console.log(`� Found ${bandwidthData.length} bandwidth entries`);
            
            // Get ARP table for active devices
            const arpEntries = await this.getARPTable();
            console.log(`📡 Found ${arpEntries.length} ARP entries`);
            
            // Get DHCP leases
            const dhcpLeases = await this.getDHCPLeases();
            console.log(`� Found ${dhcpLeases.length} DHCP leases`);
            
            // Create a map of devices with recent network activity (bandwidth > 0)
            const activeTrafficDevices = new Set();
            const bandwidthMap = new Map();
            
            bandwidthData.forEach(entry => {
                if (entry.src) {
                    const rx = parseInt(entry['rx-bytes']) || 0;
                    const tx = parseInt(entry['tx-bytes']) || 0;
                    
                    // Only consider devices with actual traffic
                    if (rx > 0 || tx > 0) {
                        activeTrafficDevices.add(entry.src);
                        
                        const existing = bandwidthMap.get(entry.src) || { rx: 0, tx: 0, rxBytes: 0, txBytes: 0 };
                        bandwidthMap.set(entry.src, {
                            rx: existing.rx + (parseInt(entry['rx-packets']) || 0),
                            tx: existing.tx + (parseInt(entry['tx-packets']) || 0),
                            rxBytes: existing.rxBytes + rx,
                            txBytes: existing.txBytes + tx
                        });
                    }
                }
            });

            // Create a map of devices with complete ARP entries (reachable right now)
            const currentlyReachable = new Map();
            arpEntries.forEach(arp => {
                if (arp.complete === 'true' && arp.interface !== 'ether2' && arp.address) {
                    currentlyReachable.set(arp.address, {
                        ip: arp.address,
                        mac: arp['mac-address'],
                        interface: arp.interface
                    });
                }
            });

            // Create DHCP lookup map
            const dhcpMap = new Map();
            dhcpLeases.forEach(lease => {
                if (lease.address && lease['mac-address']) {
                    dhcpMap.set(lease.address, {
                        mac: lease['mac-address'],
                        hostname: lease['host-name'] || 'Unknown',
                        status: lease.status
                    });
                }
            });

            // Only include devices that are BOTH reachable AND have recent traffic
            const connectedDevices = [];
            
            currentlyReachable.forEach((arpDevice, ip) => {
                // Must be reachable AND have recent network activity
                if (activeTrafficDevices.has(ip)) {
                    const dhcpInfo = dhcpMap.get(ip);
                    const bandwidth = bandwidthMap.get(ip) || { rx: 0, tx: 0, rxBytes: 0, txBytes: 0 };
                    
                    connectedDevices.push({
                        ip: ip,
                        mac: arpDevice.mac || (dhcpInfo ? dhcpInfo.mac : 'Unknown'),
                        hostname: dhcpInfo ? dhcpInfo.hostname : 'Active Device',
                        status: dhcpInfo ? dhcpInfo.status : 'active',
                        rx: bandwidth.rxBytes,
                        tx: bandwidth.txBytes,
                        rxPackets: bandwidth.rx,
                        txPackets: bandwidth.tx,
                        lastSeen: new Date().toISOString(),
                        isActive: true,
                        interface: arpDevice.interface
                    });
                }
            });

            // Sort by total traffic (most active first)
            connectedDevices.sort((a, b) => (b.rx + b.tx) - (a.rx + a.tx));

            console.log(`✅ Found ${connectedDevices.length} TRULY active devices (reachable + recent traffic)`);
            console.log(`   - ${activeTrafficDevices.size} devices with recent traffic`);
            console.log(`   - ${currentlyReachable.size} devices currently reachable`);
            
            return connectedDevices;

        } catch (error) {
            console.error('❌ Error getting connected devices with bandwidth:', error.message);
            throw error;
        }
    }

    async getSystemInfo() {
        try {
            await this.ensureConnection();
            
            const identity = await this.conn.write('/system/identity/print');
            const resource = await this.conn.write('/system/resource/print');
            
            return {
                identity: identity[0] || {},
                resource: resource[0] || {}
            };
        } catch (error) {
            console.error('Error getting system info:', error.message);
            throw error;
        }
    }
}

module.exports = MikroTikConnector;
