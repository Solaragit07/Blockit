<?php
/**
 * Lightweight OUI vendor lookup
 * Note: This is a minimal map for common brands. Unknown OUIs will return 'Unknown'.
 * You can extend this list or swap to a full database later.
 */
class OuiVendor {
    private static $map = [
        // Apple
        'A4:5E:60' => 'Apple', '28:CF:E9' => 'Apple', '3C:15:C2' => 'Apple', '60:F8:1D' => 'Apple',
        '88:E9:FE' => 'Apple', '7C:6D:62' => 'Apple', 'D4:F4:6F' => 'Apple', 'F0:18:98' => 'Apple',
        // Samsung
        '5C:49:79' => 'Samsung', '60:21:C0' => 'Samsung', '00:1F:CC' => 'Samsung', '18:28:61' => 'Samsung',
        '38:2D:D1' => 'Samsung', '40:4D:8E' => 'Samsung',
        // Huawei
        '84:2A:FD' => 'Huawei', '10:47:80' => 'Huawei', '94:65:2D' => 'Huawei',
        // Xiaomi
        '64:CC:2E' => 'Xiaomi', '8C:AA:B5' => 'Xiaomi', '28:6C:07' => 'Xiaomi',
        // Oppo / OnePlus / Realme (BBK)
        '94:8D:50' => 'OPPO', 'CC:3D:82' => 'OnePlus', 'B4:6B:FC' => 'realme',
        // Vivo
        'B0:FC:36' => 'Vivo',
        // Google
        '3C:5A:B4' => 'Google', '08:00:09' => 'Google',
        // Microsoft
        '28:18:78' => 'Microsoft', '00:15:5D' => 'Microsoft',
        // Intel
        'F8:34:41' => 'Intel', '8C:16:45' => 'Intel',
        // Realtek
        '00:E0:4C' => 'Realtek', '10:7B:44' => 'Realtek',
        // TP-Link
        'F4:F2:6D' => 'TP-Link', '50:3E:AA' => 'TP-Link',
        // Cisco
        '00:1F:9F' => 'Cisco', '00:1E:49' => 'Cisco',
        // Ubiquiti
        'F0:9F:C2' => 'Ubiquiti', '24:A4:3C' => 'Ubiquiti',
        // MikroTik
        '4C:5E:0C' => 'MikroTik', '78:9A:18' => 'MikroTik',
        // Dell
        'F0:92:1C' => 'Dell', '00:14:22' => 'Dell',
        // HP
        '1C:1B:0D' => 'HP', '08:2E:5F' => 'HP',
        // Lenovo
        'B8:BB:AF' => 'Lenovo', '00:21:86' => 'Lenovo',
        // Sony
        '1C:65:9D' => 'Sony', '00:1A:A0' => 'Sony',
        // LG
        '00:1E:75' => 'LG', '64:BC:0C' => 'LG',
        // Tenda
        'C8:3A:35' => 'Tenda', '00:23:8E' => 'Tenda',
    ];

    public static function vendorFromMac($mac) {
        if (!$mac) return 'Unknown';
        $mac = strtoupper(str_replace('-', ':', $mac));
        // Normalize to AA:BB:CC:DD:EE:FF
        $mac = preg_replace('/[^0-9A-F:]/', '', $mac);
        $parts = explode(':', $mac);
        if (count($parts) < 3) return 'Unknown';
        $oui = implode(':', array_slice($parts, 0, 3));
        if (isset(self::$map[$oui])) return self::$map[$oui];
        // Try AA:BB fallback
        $oui2 = implode(':', array_slice($parts, 0, 2));
        foreach (self::$map as $k => $v) {
            if (strpos($k, $oui2) === 0) return $v;
        }
        return 'Unknown';
    }

    public static function guessBrand($mac, $hostname = '') {
        $vendor = self::vendorFromMac($mac);
        if ($vendor !== 'Unknown') return $vendor;
        $h = strtolower($hostname);
        if (!$h) return 'Unknown';
        if (strpos($h, 'iphone') !== false || strpos($h, 'ipad') !== false || strpos($h, 'mac') !== false) return 'Apple';
        if (strpos($h, 'samsung') !== false || strpos($h, 'galaxy') !== false) return 'Samsung';
        if (strpos($h, 'huawei') !== false || strpos($h, 'honor') !== false) return 'Huawei';
        if (strpos($h, 'xiaomi') !== false || strpos($h, 'redmi') !== false || strpos($h, 'mi ') !== false) return 'Xiaomi';
        if (strpos($h, 'oppo') !== false) return 'OPPO';
        if (strpos($h, 'vivo') !== false) return 'Vivo';
        if (strpos($h, 'oneplus') !== false) return 'OnePlus';
        if (strpos($h, 'realme') !== false) return 'realme';
        if (strpos($h, 'sony') !== false || strpos($h, 'xperia') !== false) return 'Sony';
        if (strpos($h, 'nokia') !== false) return 'Nokia';
        if (strpos($h, 'lenovo') !== false) return 'Lenovo';
        if (strpos($h, 'microsoft') !== false || strpos($h, 'surface') !== false) return 'Microsoft';
        if (strpos($h, 'hp') !== false || strpos($h, 'hewlett') !== false) return 'HP';
        if (strpos($h, 'dell') !== false) return 'Dell';
        if (strpos($h, 'asus') !== false) return 'ASUS';
        if (strpos($h, 'acer') !== false) return 'Acer';
        return 'Unknown';
    }
}
