<?php
/**
 * Enhanced Block User API with better MikroTik blocking
 * This version addresses common issues why sites don't get blocked
 */

include 'connectMikrotik.php';

require_once __DIR__ . '/../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

// PHP 7 compatibility: define str_starts_with if missing
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle !== '' && strpos($haystack, $needle) === 0;
    }
}

// Initialize response array
$response = [
    'status' => 'success',
    'messages' => [],
    'debug_info' => []
];

// Get input data from both GET and POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_GET + $_POST;
}

$mac_address = $input['mac_address'] ?? null;
$site = $input['sites'] ?? null;
$hours_allowed = $input['hours_allowed'] ?? null;

// Validate required parameters
if (!$mac_address || !$site || $hours_allowed === null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameters: mac_address, sites, or hours_allowed.',
        'received' => $input
    ]);
    exit;
}

// Validate MAC address format (support both standard and IPv6 link-local)
if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac_address) && 
    !preg_match('/^fe80::[0-9a-f:]+$/', $mac_address)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid MAC address format: ' . $mac_address
    ]);
    exit;
}

// Check if client connection is available
if (!isset($client)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'RouterOS client connection not available. Check connectMikrotik.php'
    ]);
    exit;
}

// Clean up sites list and add variations
$sites = array_map('trim', explode(';', $site));
$sites = array_filter($sites); // Remove empty values
$sites = array_unique($sites); // Remove duplicates

// Skip if sites is just 'na' (used for device setup without specific sites)
if (count($sites) === 1 && strtolower($sites[0]) === 'na') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Device setup completed (no specific sites to block)'
    ]);
    exit;
}

if (empty($sites)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'No valid sites provided after cleanup'
    ]);
    exit;
}

// Expand sites to include common variations
$expandedSites = [];
foreach ($sites as $originalSite) {
    $expandedSites[] = $originalSite;
    
    // Add www. version if not present
    if (!str_starts_with($originalSite, 'www.') && !str_starts_with($originalSite, 'http')) {
        $expandedSites[] = 'www.' . $originalSite;
    }
    
    // Remove www. if present to add non-www version
    if (str_starts_with($originalSite, 'www.')) {
        $expandedSites[] = substr($originalSite, 4);
    }
    
    // Remove protocol if present
    $cleanSite = preg_replace('/^https?:\/\//', '', $originalSite);
    if ($cleanSite !== $originalSite) {
        $expandedSites[] = $cleanSite;
        if (!str_starts_with($cleanSite, 'www.')) {
            $expandedSites[] = 'www.' . $cleanSite;
        }
    }
}

$sites = array_unique($expandedSites);
$response['messages'][] = "Processing " . count($sites) . " sites (including variations) for MAC: $mac_address";

try {
    // Find DHCP lease for the MAC address (handle both standard and IPv6)
    $query = (new Query('/ip/dhcp-server/lease/print'));
    
    // For IPv6 link-local addresses, we need to find by different method
    if (str_starts_with($mac_address, 'fe80::')) {
        // Try to find by looking at all leases and matching part of the address
        $leases = $client->query($query)->read();
        $matchedLease = null;
        
        foreach ($leases as $lease) {
            if (isset($lease['mac-address'])) {
                // Convert IPv6 link-local to potential MAC format
                $macPart = substr($mac_address, 5); // Remove 'fe80::'
                if (strpos($lease['mac-address'], str_replace(':', '', $macPart)) !== false) {
                    $matchedLease = $lease;
                    break;
                }
            }
        }
        
        if ($matchedLease) {
            $leases = [$matchedLease];
            $mac_address = $matchedLease['mac-address']; // Use actual MAC from DHCP
        } else {
            $leases = [];
        }
    } else {
        $query->where('mac-address', $mac_address);
        $leases = $client->query($query)->read();
    }

    if (count($leases) === 0) {
        // Fallback 1: ARP table lookup by MAC
        try {
            $arpEntries = $client->query((new Query('/ip/arp/print'))
                ->where('mac-address', $mac_address))->read();
            if (!empty($arpEntries) && !empty($arpEntries[0]['address'])) {
                $leases = [[ '.id' => null, 'address' => $arpEntries[0]['address'], 'dynamic' => 'true' ]];
                $response['messages'][] = 'ℹ️ DHCP lease not found; using ARP-derived IP ' . $arpEntries[0]['address'];
            }
        } catch (Exception $e) { /* ignore */ }

        // Fallback 2: Neighbor discovery
        if (count($leases) === 0) {
            try {
                $neighbors = $client->query((new Query('/ip/neighbor/print')))->read();
                foreach ($neighbors as $n) {
                    if (($n['mac-address'] ?? '') === $mac_address && !empty($n['address'])) {
                        $leases = [[ '.id' => null, 'address' => $n['address'], 'dynamic' => 'true' ]];
                        $response['messages'][] = 'ℹ️ Using neighbor-derived IP ' . $n['address'];
                        break;
                    }
                }
            } catch (Exception $e) { /* ignore */ }
        }
    }

    if (count($leases) > 0) {
        $leaseId = $leases[0]['.id'] ?? null;
        $ip = $leases[0]['address'];
        
        $response['debug_info']['device_ip'] = $ip;
        $response['debug_info']['mac_used'] = $mac_address;

        // Make lease static if it's dynamic
        if ($leaseId && isset($leases[0]['dynamic']) && $leases[0]['dynamic'] === 'true') {
            try {
                $makeStatic = (new Query('/ip/dhcp-server/lease/make-static'))
                    ->equal('.id', $leaseId);
                $client->query($makeStatic)->read();
                $response['messages'][] = "✅ Made DHCP lease static for MAC $mac_address";
            } catch (Exception $e) {
                $response['messages'][] = '⚠️ Could not make DHCP lease static: ' . $e->getMessage();
            }
        } else if ($leaseId) {
            $response['messages'][] = "✅ DHCP lease already static for MAC $mac_address";
        } else {
            $response['messages'][] = 'ℹ️ Proceeding without DHCP lease (using ARP/neighbor IP)';
        }

        // Remove existing firewall rules for this MAC (address-list, TLS, and HTTP)
        $toRemove = 0;
        $allRules = $client->query((new Query('/ip/firewall/filter/print')))->read();
        foreach ($allRules as $rule) {
            $c = $rule['comment'] ?? '';
            if (
                strpos($c, "Auto block for $mac_address") !== false ||
                strpos($c, "Auto block TLS for $mac_address") !== false ||
                strpos($c, "Auto block HTTP for $mac_address") !== false
            ) {
                $client->query((new Query('/ip/firewall/filter/remove'))->equal('.id', $rule['.id']))->read();
                $toRemove++;
            }
        }
        $response['messages'][] = "✅ Removed $toRemove existing firewall rules for $mac_address";

        // Create address list name
        $addressListName = "blocked-sites-" . str_replace([':', '-'], '', $mac_address);
        
    // Add improved firewall rule (both forward and output chains for better coverage)
        $blockRules = [
            [
                'chain' => 'forward',
                'src-address' => $ip,
                'dst-address-list' => $addressListName,
                'action' => 'drop',
                'comment' => "Auto block for $mac_address (forward)"
            ],
            // Reverse direction: drop traffic coming back from blocked IPs to the device
            [
                'chain' => 'forward',
                'dst-address' => $ip,
                'src-address-list' => $addressListName,
                'action' => 'drop',
                'comment' => "Auto block for $mac_address (reverse)"
            ],
            [
                'chain' => 'output',
                'src-address' => $ip, 
                'dst-address-list' => $addressListName,
                'action' => 'drop',
                'comment' => "Auto block for $mac_address (output)"
            ]
        ];

        foreach ($blockRules as $ruleConfig) {
            $blockRule = (new Query('/ip/firewall/filter/add'))
                ->equal('chain', $ruleConfig['chain'])
                ->equal('src-address', $ruleConfig['src-address'] ?? null)
                ->equal('dst-address', $ruleConfig['dst-address'] ?? null)
                ->equal('src-address-list', $ruleConfig['src-address-list'] ?? null)
                ->equal('dst-address-list', $ruleConfig['dst-address-list'] ?? null)
                ->equal('action', $ruleConfig['action'])
                ->equal('disabled', 'no')
                ->equal('log', 'yes')
                ->equal('log-prefix', 'BLOCKED-SITE')
                ->equal('comment', $ruleConfig['comment']);
            $client->query($blockRule)->read();
            $response['messages'][] = "✅ Added firewall rule for " . $ruleConfig['chain'] . " chain";
        }

    // Block QUIC globally for the device to ensure HTTPS filtering catches TLS SNI
        try {
            $client->query((new Query('/ip/firewall/filter/add'))
                ->equal('chain', 'forward')
                ->equal('protocol', 'udp')
                ->equal('dst-port', '443')
                ->equal('src-address', $ip)
                ->equal('action', 'drop')
                ->equal('disabled', 'no')
                ->equal('log', 'yes')
                ->equal('log-prefix', 'BLOCKED-QUIC')
                ->equal('comment', "Auto block UDP 443 for $mac_address"))
            ->read();
            $response['messages'][] = '✅ Added UDP/443 (QUIC) drop for device to enforce HTTPS filtering';
        } catch (Exception $e) {
            // Non-fatal
        }

        // Extra-early QUIC drop in RAW (pre-conntrack)
        try {
            $client->query((new Query('/ip/firewall/raw/add'))
                ->equal('chain', 'prerouting')
                ->equal('protocol', 'udp')
                ->equal('dst-port', '443')
                ->equal('src-address', $ip)
                ->equal('action', 'drop')
                ->equal('comment', "Auto block RAW UDP 443 for $mac_address"))
            ->read();
        } catch (Exception $e) { /* best-effort */ }

        // Bridge-level IPv6 frame drop by MAC to prevent IPv6 bypass (privacy addresses)
        try {
            // Remove any existing IPv6 bridge filter rules for this MAC
            $existingBridge = $client->query((new Query('/interface/bridge/filter/print')))->read();
            foreach ($existingBridge as $br) {
                $c = $br['comment'] ?? '';
                if (strpos($c, "Auto block IPv6 frames for $mac_address") !== false) {
                    $client->query((new Query('/interface/bridge/filter/remove'))->equal('.id', $br['.id']))->read();
                }
            }
            // Add the drop rule
            $client->query((new Query('/interface/bridge/filter/add'))
                ->equal('chain', 'forward')
                ->equal('mac-protocol', 'ipv6')
                ->equal('src-mac-address', $mac_address)
                ->equal('action', 'drop')
                ->equal('disabled', 'no')
                ->equal('comment', "Auto block IPv6 frames for $mac_address"))
            ->read();
            // Move to top of bridge filter
            $bridgeRules = $client->query((new Query('/interface/bridge/filter/print')))->read();
            foreach ($bridgeRules as $br) {
                $c = $br['comment'] ?? '';
                if (strpos($c, "Auto block IPv6 frames for $mac_address") !== false) {
                    $client->query((new Query('/interface/bridge/filter/move'))
                        ->equal('numbers', $br['.id'])
                        ->equal('destination', '0'))
                    ->read();
                }
            }
            $response['messages'][] = '✅ Added bridge-level IPv6 drop to prevent IPv6 bypass';
        } catch (Exception $e) {
            $response['messages'][] = '⚠️ Could not add bridge IPv6 drop (may be no bridge): ' . $e->getMessage();
        }

        // Try to place our rules before fasttrack/accept rules for reliability (top of chain)
        try {
            $moveBefore = function($id, $targetId) use ($client) {
                if (!$id) return; 
                $q = (new Query('/ip/firewall/filter/move'))
                    ->equal('numbers', $id)
                    ->equal('destination', '0');
                $client->query($q)->read();
            };

            // Collect our rule ids by comment patterns and move them
            $ownComments = [
                "Auto block for $mac_address (forward)",
                "Auto block TLS for $mac_address",
                "Auto block HTTP for $mac_address",
                "Auto block UDP 443 for $mac_address",
                "Auto block RAW UDP 443 for $mac_address"
            ];
            $allFilter = $client->query((new Query('/ip/firewall/filter/print')))->read();
            foreach ($allFilter as $r) {
                $c = $r['comment'] ?? '';
                foreach ($ownComments as $oc) {
                    if (strpos($c, $oc) !== false) {
                        // Place to top of chain
                        $moveBefore($r['.id'], '0');
                    }
                }
            }
            $response['messages'][] = '✅ Reordered block rules before fasttrack/top for reliability';
        } catch (Exception $e) {
            // Non-fatal
            $response['messages'][] = '⚠️ Could not reorder rules: ' . $e->getMessage();
        }

        // Add RAW table early drops (v6-friendly, before conntrack/fasttrack)
        try {
            $client->query((new Query('/ip/firewall/raw/add'))
                ->equal('chain', 'prerouting')
                ->equal('src-address', $ip)
                ->equal('dst-address-list', $addressListName)
                ->equal('action', 'drop')
                ->equal('comment', "Auto block RAW for $mac_address (prerouting)"))
            ->read();
            $client->query((new Query('/ip/firewall/raw/add'))
                ->equal('chain', 'output')
                ->equal('src-address', $ip)
                ->equal('dst-address-list', $addressListName)
                ->equal('action', 'drop')
                ->equal('comment', "Auto block RAW for $mac_address (output)"))
            ->read();
            // Reverse direction in RAW: drop packets from blocked IPs toward the device
            $client->query((new Query('/ip/firewall/raw/add'))
                ->equal('chain', 'prerouting')
                ->equal('src-address-list', $addressListName)
                ->equal('dst-address', $ip)
                ->equal('action', 'drop')
                ->equal('comment', "Auto block RAW reverse for $mac_address (prerouting)"))
            ->read();

            // Move RAW rules to top
            $rawRules = $client->query((new Query('/ip/firewall/raw/print')))->read();
            foreach ($rawRules as $rr) {
                $c = $rr['comment'] ?? '';
                if (
                    strpos($c, "Auto block RAW for $mac_address") !== false ||
                    strpos($c, "Auto block RAW UDP 443 for $mac_address") !== false ||
                    strpos($c, "Auto block RAW reverse for $mac_address") !== false
                ) {
                    $client->query((new Query('/ip/firewall/raw/move'))
                        ->equal('numbers', $rr['.id'])
                        ->equal('destination', '0'))
                    ->read();
                }
            }
            $response['messages'][] = '✅ Added RAW table drops at top (pre-conntrack)';
        } catch (Exception $e) {
            // raw not present or other issue; non-fatal
        }

        // Add stronger enforcement for HTTPS (TLS SNI) and HTTP (Host header/content)
        // Build a sanitized unique set of domains (skip IPs)
        $domainPattern = '/^(?=.{1,253}$)(?!-)([A-Za-z0-9-]{1,63}\.)+[A-Za-z]{2,}$/';
        $tlsDomains = [];
        foreach ($sites as $s) {
            $d = strtolower($s);
            // Strip protocol and leading www.
            $d = preg_replace('/^https?:\/\//', '', $d);
            if (strpos($d, '/') !== false) { $d = explode('/', $d)[0]; }
            if (strpos($d, 'www.') === 0) { $d = substr($d, 4); }
            if (preg_match($domainPattern, $d)) { $tlsDomains[$d] = true; }
        }
        $tlsDomains = array_keys($tlsDomains);

        $tlsAdded = 0; $httpAdded = 0;
    foreach ($tlsDomains as $d) {
            // TLS SNI rule (RouterOS v7+). If not supported, the API will throw and we continue.
        try {
                $client->query((new Query('/ip/firewall/filter/add'))
                    ->equal('chain', 'forward')
                    ->equal('protocol', 'tcp')
                    ->equal('dst-port', '443')
            ->equal('src-address', $ip)
                    ->equal('tls-host', $d)
                    ->equal('action', 'drop')
                    ->equal('disabled', 'no')
                    ->equal('log', 'yes')
                    ->equal('log-prefix', 'BLOCKED-TLS')
                    ->equal('comment', "Auto block TLS for $mac_address"))
                ->read();
                $tlsAdded++;
            } catch (Exception $e) {
                // Ignore if tls-host not available
            }

            // HTTP Host/content match (port 80)
        try {
                $client->query((new Query('/ip/firewall/filter/add'))
                    ->equal('chain', 'forward')
                    ->equal('protocol', 'tcp')
                    ->equal('dst-port', '80')
            ->equal('src-address', $ip)
                    ->equal('content', $d)
                    ->equal('action', 'drop')
                    ->equal('disabled', 'no')
                    ->equal('log', 'yes')
                    ->equal('log-prefix', 'BLOCKED-HTTP')
                    ->equal('comment', "Auto block HTTP for $mac_address"))
                ->read();
                $httpAdded++;
            } catch (Exception $e) {
                // Ignore if content matcher not available
            }
        }
        $response['messages'][] = "🔒 Added $tlsAdded TLS and $httpAdded HTTP hostname-based drop rules";

        // Re-run reordering now that TLS/HTTP/QUIC rules exist
        try {
            $allFilter = $client->query((new Query('/ip/firewall/filter/print')))->read();
            foreach ($allFilter as $r) {
                $c = $r['comment'] ?? '';
                if (strpos($c, "Auto block for $mac_address (forward)") !== false ||
                    strpos($c, "Auto block for $mac_address (output)") !== false ||
                    strpos($c, "Auto block TLS for $mac_address") !== false ||
                    strpos($c, "Auto block HTTP for $mac_address") !== false ||
                    strpos($c, "Auto block UDP 443 for $mac_address") !== false) {
                    $client->query((new Query('/ip/firewall/filter/move'))
                        ->equal('numbers', $r['.id'])
                        ->equal('destination', '0'))
                    ->read();
                }
            }
        } catch (Exception $e) { /* ignore */ }

        // Clean up old address list entries for this MAC
        $existingEntries = $client->query((new Query('/ip/firewall/address-list/print'))
            ->where('list', $addressListName))->read();
        
        foreach ($existingEntries as $entry) {
            $client->query((new Query('/ip/firewall/address-list/remove'))->equal('.id', $entry['.id']))->read();
        }
        $response['messages'][] = "✅ Cleaned up old address list entries";

        // Add sites and resolved IPs (IPv4 + IPv6) to address lists
        $addedSites = 0; $addedIPsManual = 0; $addedIPv6Manual = 0;
        foreach ($sites as $siteToBlock) {
            $siteClean = strtolower(trim($siteToBlock));
            $siteClean = preg_replace('/^https?:\/\//','', $siteClean);
            if (strpos($siteClean, '/') !== false) { $siteClean = explode('/', $siteClean)[0]; }
            try {
                // Keep the domain entry for reference / DNS cache enrichment
                $client->query((new Query('/ip/firewall/address-list/add'))
                    ->equal('address', $siteClean)
                    ->equal('list', $addressListName)
                    ->equal('comment', "Site $siteClean for MAC $mac_address"))->read();
                $addedSites++;
            } catch (Exception $e) {
                // ignore duplicates
            }
            // Resolve to A records and add IPs directly
            try {
                $ips = [];
                if (filter_var($siteClean, FILTER_VALIDATE_IP)) {
                    $ips = [$siteClean];
                } else {
                    $records = @dns_get_record($siteClean, DNS_A);
                    if (is_array($records)) {
                        foreach ($records as $rr) { if (!empty($rr['ip'])) { $ips[] = $rr['ip']; } }
                    }
                }
                foreach (array_unique($ips) as $ipA) {
                    try {
                        $client->query((new Query('/ip/firewall/address-list/add'))
                            ->equal('address', $ipA)
                            ->equal('list', $addressListName)
                            ->equal('comment', "Resolved $siteClean -> $ipA for $mac_address"))
                        ->read();
                        $addedIPsManual++;
                    } catch (Exception $e) {
                        // duplicate or invalid; ignore
                    }
                }
                // Resolve AAAA (IPv6) and attempt to add to IPv6 address list
                try {
                    if (!defined('DNS_AAAA')) { define('DNS_AAAA', 0x00000010); }
                    $records6 = @dns_get_record($siteClean, DNS_AAAA);
                    $ips6 = [];
                    if (is_array($records6)) {
                        foreach ($records6 as $rr6) { if (!empty($rr6['ipv6'])) { $ips6[] = $rr6['ipv6']; } }
                    }
                    $ips6 = array_unique($ips6);
                    if (!empty($ips6)) {
                        $addressListName6 = $addressListName . "-v6";
                        foreach ($ips6 as $ip6) {
                            try {
                                $client->query((new Query('/ipv6/firewall/address-list/add'))
                                    ->equal('address', $ip6)
                                    ->equal('list', $addressListName6)
                                    ->equal('comment', "Resolved6 $siteClean -> $ip6 for $mac_address"))
                                ->read();
                                $addedIPv6Manual++;
                            } catch (Exception $e) { /* ignore dup */ }
                        }
                    }
                } catch (Exception $e) { /* ipv6 not available, ignore */ }
            } catch (Exception $e) {
                // Non-fatal
            }
        }
        $response['messages'][] = "✅ Added $addedSites domains and $addedIPsManual resolved IPv4 IPs to list: $addressListName; plus $addedIPv6Manual IPv6 IPs if supported";

        // Enrich address-list with current DNS cache IPs for subdomains (v6-friendly)
        try {
            $dnsCache = $client->query((new Query('/ip/dns/cache/print')))->read();
            $addedIPs = 0;
            foreach ($dnsCache as $entry) {
                $name = strtolower($entry['name'] ?? '');
                $ipAddr = $entry['data'] ?? '';
                $type = strtolower($entry['type'] ?? '');
                if ($type !== 'a') continue; // only IPv4 for filter rules example
                if (empty($ipAddr) || empty($name)) continue;
                foreach ($tlsDomains as $suffix) {
                    if ($name === $suffix || substr($name, -strlen($suffix) - 1) === '.' . $suffix) {
                        try {
                            $client->query((new Query('/ip/firewall/address-list/add'))
                                ->equal('address', $ipAddr)
                                ->equal('list', $addressListName)
                                ->equal('comment', "DNS cache $name -> $ipAddr for $mac_address"))
                            ->read();
                            $addedIPs++;
                        } catch (Exception $e) {
                            // likely duplicate; ignore
                        }
                        break;
                    }
                }
            }
            if ($addedIPs > 0) {
                $response['messages'][] = "✅ Added $addedIPs IPs from DNS cache to $addressListName (subdomain coverage)";
            }
        } catch (Exception $e) {
            // Ignore if DNS cache not accessible
        }

        // Proactively drop existing connections from this device to force immediate effect
        try {
            $conns = $client->query((new Query('/ip/firewall/connection/print'))
                ->where('src-address', $ip))->read();
            $killed = 0;
            foreach ($conns as $cx) {
                $dp = $cx['dst-port'] ?? '';
                $proto = $cx['protocol'] ?? '';
                if ($dp === '80' || $dp === '443' || ($proto === 'udp' && $dp === '443')) {
                    $client->query((new Query('/ip/firewall/connection/remove'))
                        ->equal('.id', $cx['.id']))->read();
                    $killed++;
                }
            }
            $response['messages'][] = "✅ Killed $killed existing connections on ports 80/443 for $ip";
        } catch (Exception $e) {
            // Safe to ignore
        }

        // Best-effort IPv6 device rules: try to detect IPv6 of the device and apply IPv6 filter/raw
        try {
            $ipv6Address = null;
            // Try neighbor table first
            try {
                $neighbors = $client->query((new Query('/ipv6/neighbor/print')))->read();
                foreach ($neighbors as $n) {
                    if (($n['mac-address'] ?? '') === $mac_address && !empty($n['address'])) { $ipv6Address = $n['address']; break; }
                }
            } catch (Exception $e) { /* try alternate path */ }
            if (!$ipv6Address) {
                try {
                    $neighbors = $client->query((new Query('/ipv6/nd/neighbor/print')))->read();
                    foreach ($neighbors as $n) {
                        if (($n['mac-address'] ?? '') === $mac_address && !empty($n['address'])) { $ipv6Address = $n['address']; break; }
                    }
                } catch (Exception $e) { /* ignore */ }
            }
            if ($ipv6Address) {
                $addressListName6 = $addressListName . '-v6';
                // Base IPv6 dst-address-list drop
                try {
                    $client->query((new Query('/ipv6/firewall/filter/add'))
                        ->equal('chain', 'forward')
                        ->equal('src-address', $ipv6Address)
                        ->equal('dst-address-list', $addressListName6)
                        ->equal('action', 'drop')
                        ->equal('comment', "Auto block v6 for $mac_address (forward)"))
                    ->read();
                    $client->query((new Query('/ipv6/firewall/filter/add'))
                        ->equal('chain', 'output')
                        ->equal('src-address', $ipv6Address)
                        ->equal('dst-address-list', $addressListName6)
                        ->equal('action', 'drop')
                        ->equal('comment', "Auto block v6 for $mac_address (output)"))
                    ->read();
                } catch (Exception $e) { /* ignore */ }
                // QUIC v6 drop
                try {
                    $client->query((new Query('/ipv6/firewall/filter/add'))
                        ->equal('chain', 'forward')
                        ->equal('protocol', 'udp')
                        ->equal('dst-port', '443')
                        ->equal('src-address', $ipv6Address)
                        ->equal('action', 'drop')
                        ->equal('comment', "Auto block UDP 443 v6 for $mac_address"))
                    ->read();
                } catch (Exception $e) { /* ignore */ }
                // RAW v6 drops
                try {
                    $client->query((new Query('/ipv6/firewall/raw/add'))
                        ->equal('chain', 'prerouting')
                        ->equal('src-address', $ipv6Address)
                        ->equal('dst-address-list', $addressListName6)
                        ->equal('action', 'drop')
                        ->equal('comment', "Auto block v6 RAW for $mac_address (prerouting)"))
                    ->read();
                    $client->query((new Query('/ipv6/firewall/raw/add'))
                        ->equal('chain', 'output')
                        ->equal('src-address', $ipv6Address)
                        ->equal('dst-address-list', $addressListName6)
                        ->equal('action', 'drop')
                        ->equal('comment', "Auto block v6 RAW for $mac_address (output)"))
                    ->read();
                } catch (Exception $e) { /* ignore */ }
            }
        } catch (Exception $e) { /* ipv6 not configured, ignore */ }

        // Set up time-based scheduling only when hours_allowed > 0
        if (is_numeric($hours_allowed) && intval($hours_allowed) > 0) {
            $enableTime = "08:00:00";
            $disableTime = date("H:i:s", strtotime("+".intval($hours_allowed)." hours", strtotime($enableTime)));

            // Remove existing schedulers for this MAC
            $existingSchedulers = $client->query((new Query('/system/scheduler/print'))
                ->where('name', "*" . str_replace([':', '-'], '', $mac_address) . "*"))->read();
            foreach ($existingSchedulers as $scheduler) {
                $client->query((new Query('/system/scheduler/remove'))->equal('.id', $scheduler['.id']))->read();
            }
            $response['messages'][] = "✅ Removed existing schedulers";

            $schedulers = [
                [
                    'name' => "unblock_" . str_replace([':', '-'], '', $mac_address),
                    'start-time' => $enableTime,
                    'interval' => '1d',
                    'on-event' => "/ip firewall filter enable [find comment~\"Auto block for $mac_address\"]"
                ],
                [
                    'name' => "block_" . str_replace([':', '-'], '', $mac_address),
                    'start-time' => $disableTime,
                    'interval' => '1d', 
                    'on-event' => "/ip firewall filter disable [find comment~\"Auto block for $mac_address\"]"
                ]
            ];

            foreach ($schedulers as $schedulerConfig) {
                $client->query((new Query('/system/scheduler/add'))
                    ->equal('name', $schedulerConfig['name'])
                    ->equal('start-time', $schedulerConfig['start-time'])
                    ->equal('interval', $schedulerConfig['interval'])
                    ->equal('on-event', $schedulerConfig['on-event']))->read();
            }

            $response['messages'][] = "✅ Schedulers created: unblock at $enableTime, block at $disableTime";
        } else {
            $response['messages'][] = 'ℹ️ No time scheduler created (hours_allowed=0) — rules stay active';
        }
        $response['messages'][] = "✅ Access control configured for $mac_address ($ip)";
        $response['messages'][] = "🔒 Will block access to " . count($sites) . " sites (including variations)";
        $response['messages'][] = "⏰ Daily schedule: Allow $hours_allowed hours from $enableTime to $disableTime";
        
        $response['debug_info']['address_list_name'] = $addressListName;
        $response['debug_info']['sites_processed'] = count($sites);
        $response['debug_info']['time_schedule'] = "$enableTime to $disableTime";
        
    } else {
        $response = [
            'status' => 'error',
            'message' => "❌ MAC address $mac_address not found in router (DHCP/ARP/neighbor).",
            'debug_info' => [
                'searched_mac' => $mac_address,
                'suggestion' => 'Ensure the device is connected to this router and visible in DHCP leases or ARP table.'
            ]
        ];
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'RouterOS API Error: ' . $e->getMessage(),
        'mac_address' => $mac_address,
        'sites_count' => count($sites ?? []),
        'debug_info' => [
            'error_type' => get_class($e),
            'error_line' => $e->getLine()
        ]
    ];
}

// Log the API call for debugging
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'mac_address' => $mac_address,
    'sites_count' => count($sites ?? []),
    'hours_allowed' => $hours_allowed,
    'response_status' => $response['status'],
    'response_message' => $response['message'] ?? '',
    'first_few_sites' => array_slice($sites ?? [], 0, 5)
];

file_put_contents(__DIR__ . '/api_log.txt', json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');
echo json_encode($response);
?>
