<?php
use RouterOS\Client;
use RouterOS\Query;
/**
 * BlockIT Global Application Block API
 * Applies router-wide blocking for an application to ALL devices behind MikroTik.
 * Actions:
 *   - block:    /blockit/API/block_global.php?action=block&app=YouTube
 *   - unblock:  /blockit/API/block_global.php?action=unblock&app=YouTube
 *
 * Notes:
 * - Uses interface list 'LAN' if present; otherwise falls back to the main bridge.
 * - Adds IPv4 and IPv6 rules when IPv6 is available.
 * - Uses domains from DB (application_blocks) when available; falls back to static map.
 */

header('Content-Type: application/json');

try {
    // Allow enough time for router operations
    @set_time_limit(120);
    $__start = microtime(true);
    $action = strtolower($_GET['action'] ?? $_POST['action'] ?? 'block');
    $app    = trim($_GET['app'] ?? $_POST['app'] ?? '');
    if ($app === '') {
        throw new Exception('Missing required parameter: app');
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    include __DIR__ . '/connectMikrotik.php';
    // Recreate client with a more lenient timeout for this heavy endpoint
    try {
        if (isset($router_ip, $api_port, $credentials['user'], $credentials['pass'])) {
            $client = new Client([
                'host' => $router_ip,
                'user' => $credentials['user'],
                'pass' => $credentials['pass'],
                'port' => $api_port,
                'timeout' => 8,   // allow slower routers
                'attempts' => 2,
                'delay' => 1
            ]);
            // quick sanity call
            $client->query('/system/identity/print')->read();
        }
    } catch (\Throwable $e) {
        // Fall back to whatever connectMikrotik produced; if still null, error out
    }
    if (!isset($client) || $client === null) {
        throw new Exception('Router connection not available');
    }

    // Optionally fetch domains from DB
    $domains = [];
    $conn = null;
    $dbPath = __DIR__ . '/../connectMySql.php';
    if (file_exists($dbPath)) {
        include $dbPath; // defines $conn
        if (isset($conn) && $conn) {
            if ($stmt = mysqli_prepare($conn, "SELECT domains FROM application_blocks WHERE application_name = ? AND status = 'active' ORDER BY id DESC LIMIT 1")) {
                mysqli_stmt_bind_param($stmt, 's', $app);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $domainsStr);
                if (mysqli_stmt_fetch($stmt) && $domainsStr) {
                    $parts = array_map('trim', explode(',', $domainsStr));
                    foreach ($parts as $d) { if ($d !== '') { $domains[] = $d; } }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // Fallback to static app map if DB empty
    if (empty($domains)) {
        $helper = __DIR__ . '/../includes/fast_api_helper.php';
        if (file_exists($helper)) {
            include_once $helper;
            if (class_exists('FastApiHelper') && method_exists('FastApiHelper','getDomainsForApplication')) {
                $domains = FastApiHelper::getDomainsForApplication($app);
            }
        }
    }

    // Last resort: use the provided app name as domain
    if (empty($domains)) { $domains = [strtolower($app) . '.com']; }

    // Normalize domains
    $domains = array_values(array_unique(array_filter(array_map(function($d){
        $d = strtolower(trim($d));
        $d = preg_replace('#^https?://#','', $d);
        if (strpos($d, '/') !== false) { $d = explode('/', $d)[0]; }
        $d = preg_replace('/^www\./','', $d);
        return $d;
    }, $domains))));
    // Hard cap to avoid long operations
    if (count($domains) > 20) { $domains = array_slice($domains, 0, 20); }

    // Query class imported at top

    // Helpers
    $pickLanSelector = function($client) {
        // Try interface list 'LAN'
        try {
            $lists = $client->query((new Query('/interface/list/print')))->read();
            foreach ($lists as $l) {
                $name = strtolower($l['name'] ?? '');
                if ($name === 'lan') { return ['in-interface-list' => $l['name']]; }
            }
        } catch (\Throwable $e) { /* ignore */ }
        // Fallback to bridge interface
        try {
            $bridges = $client->query((new Query('/interface/bridge/print')))->read();
            if (!empty($bridges)) {
                return ['in-interface' => $bridges[0]['name']];
            }
        } catch (\Throwable $e) { /* ignore */ }
        // Final fallback: no selector
        return [];
    };

    $lanSelIn  = $pickLanSelector($client);        // for inbound (prerouting/forward in)
    $lanSelOut = [];                                // for reverse (out-interface-list)
    if (isset($lanSelIn['in-interface-list'])) {
        $lanSelOut = ['out-interface-list' => $lanSelIn['in-interface-list']];
    } elseif (isset($lanSelIn['in-interface'])) {
        $lanSelOut = ['out-interface' => $lanSelIn['in-interface']];
    }

    $LIST_V4 = 'blocked-sites-global';
    $LIST_V6 = 'blocked-sites-global-v6';
    $TAG     = 'BlockIT Global ' . $app;

    $resolveA = function($domain){
        $ips = [];
        $a = @dns_get_record($domain, DNS_A);
        if (is_array($a)) { foreach ($a as $r) { if (!empty($r['ip'])) $ips[] = $r['ip']; } }
        return array_values(array_unique($ips));
    };
    $resolveAAAA = function($domain){
        if (!defined('DNS_AAAA')) { define('DNS_AAAA', 0x10); }
        $ips = [];
        $a = @dns_get_record($domain, DNS_AAAA);
        if (is_array($a)) { foreach ($a as $r) { if (!empty($r['ipv6'])) $ips[] = $r['ipv6']; } }
        return array_values(array_unique($ips));
    };

    if ($action === 'unblock') {
        // Remove FILTER rules
        try {
            $rules = $client->query((new Query('/ip/firewall/filter/print')))->read();
            foreach ($rules as $r) {
                if (strpos($r['comment'] ?? '', $TAG) !== false) {
                    $client->query((new Query('/ip/firewall/filter/remove'))->equal('.id', $r['.id']))->read();
                }
            }
        } catch (\Throwable $e) {}
        // Remove RAW rules
        try {
            $raw = $client->query((new Query('/ip/firewall/raw/print')))->read();
            foreach ($raw as $r) {
                if (strpos($r['comment'] ?? '', $TAG) !== false) {
                    $client->query((new Query('/ip/firewall/raw/remove'))->equal('.id', $r['.id']))->read();
                }
            }
        } catch (\Throwable $e) {}
        // Remove address-list entries
        try {
            $alist = $client->query((new Query('/ip/firewall/address-list/print'))->where('list', $LIST_V4))->read();
            foreach ($alist as $a) {
                if (strpos($a['comment'] ?? '', $TAG) !== false) {
                    $client->query((new Query('/ip/firewall/address-list/remove'))->equal('.id', $a['.id']))->read();
                }
            }
        } catch (\Throwable $e) {}
        // IPv6 cleanup
        try {
            $alist6 = $client->query((new Query('/ipv6/firewall/address-list/print'))->where('list', $LIST_V6))->read();
            foreach ($alist6 as $a) {
                if (strpos($a['comment'] ?? '', $TAG) !== false) {
                    $client->query((new Query('/ipv6/firewall/address-list/remove'))->equal('.id', $a['.id']))->read();
                }
            }
            $rules6 = $client->query((new Query('/ipv6/firewall/filter/print')))->read();
            foreach ($rules6 as $r) {
                if (strpos($r['comment'] ?? '', $TAG) !== false) {
                    $client->query((new Query('/ipv6/firewall/filter/remove'))->equal('.id', $r['.id']))->read();
                }
            }
        } catch (\Throwable $e) {}

        echo json_encode(['success' => true, 'message' => "Unblocked $app globally"]);
        exit;
    }

    // BLOCK flow
    // 1) Populate address-lists with resolved IPs
    foreach ($domains as $d) {
        foreach ($resolveA($d) as $ip4) {
            try {
                $client->query((new Query('/ip/firewall/address-list/add'))
                    ->equal('list', $LIST_V4)
                    ->equal('address', $ip4)
                    ->equal('comment', $TAG))
                ->read();
            } catch (\Throwable $e) { /* dup ok */ }
        }
        try {
            foreach ($resolveAAAA($d) as $ip6) {
                $client->query((new Query('/ipv6/firewall/address-list/add'))
                    ->equal('list', $LIST_V6)
                    ->equal('address', $ip6)
                    ->equal('comment', $TAG))
                ->read();
            }
        } catch (\Throwable $e) { /* no ipv6 */ }
    }

    // 2) RAW early drops by IP list (pre-conntrack)
    try {
        $q = (new Query('/ip/firewall/raw/add'))
            ->equal('chain', 'prerouting')
            ->equal('action', 'drop')
            ->equal('dst-address-list', $LIST_V4)
            ->equal('comment', $TAG . ' RAW IP');
        foreach ($lanSelIn as $k=>$v) { $q->equal($k, $v); }
        $client->query($q)->read();
        // Move to top
        $rawRules = $client->query((new Query('/ip/firewall/raw/print')))->read();
        foreach ($rawRules as $rr) {
            if (strpos($rr['comment'] ?? '', $TAG . ' RAW IP') !== false) {
                $client->query((new Query('/ip/firewall/raw/move'))
                    ->equal('numbers', $rr['.id'])
                    ->equal('destination', '0'))
                ->read();
            }
        }
    } catch (\Throwable $e) { /* raw may not be present */ }

    // 3) FILTER rules for TLS SNI, HTTP Host, QUIC and IP lists
    foreach ($domains as $d) {
        // TLS SNI (TCP 443)
        try {
            $q = (new Query('/ip/firewall/filter/add'))
                ->equal('chain', 'forward')
                ->equal('protocol', 'tcp')
                ->equal('dst-port', '443')
                ->equal('tls-host', $d)
                ->equal('action', 'drop')
                ->equal('log', 'yes')
                ->equal('log-prefix', 'BLOCKED-TLS')
                ->equal('comment', $TAG . ' TLS');
            foreach ($lanSelIn as $k=>$v) { $q->equal($k, $v); }
            $client->query($q)->read();
        } catch (\Throwable $e) { /* tls-host needs v7; ignore if unsupported */ }

        // HTTP Host/content (TCP 80)
        try {
            $q = (new Query('/ip/firewall/filter/add'))
                ->equal('chain', 'forward')
                ->equal('protocol', 'tcp')
                ->equal('dst-port', '80')
                ->equal('content', $d)
                ->equal('action', 'drop')
                ->equal('log', 'yes')
                ->equal('log-prefix', 'BLOCKED-HTTP')
                ->equal('comment', $TAG . ' HTTP');
            foreach ($lanSelIn as $k=>$v) { $q->equal($k, $v); }
            $client->query($q)->read();
        } catch (\Throwable $e) { /* ignore if content matcher not available */ }
    }

    // QUIC drop to blocked IPs
    try {
        $q = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('protocol', 'udp')
            ->equal('dst-port', '443')
            ->equal('dst-address-list', $LIST_V4)
            ->equal('action', 'drop')
            ->equal('log', 'yes')
            ->equal('log-prefix', 'BLOCKED-QUIC')
            ->equal('comment', $TAG . ' QUIC');
        foreach ($lanSelIn as $k=>$v) { $q->equal($k, $v); }
        $client->query($q)->read();
    } catch (\Throwable $e) {}

    // Generic IP-list drops both directions
    try {
        $q1 = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('dst-address-list', $LIST_V4)
            ->equal('action', 'drop')
            ->equal('comment', $TAG . ' IP');
        foreach ($lanSelIn as $k=>$v) { $q1->equal($k, $v); }
        $client->query($q1)->read();

        $q2 = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('src-address-list', $LIST_V4)
            ->equal('action', 'drop')
            ->equal('comment', $TAG . ' REPLY');
        foreach ($lanSelOut as $k=>$v) { $q2->equal($k, $v); }
        $client->query($q2)->read();
    } catch (\Throwable $e) {}

    // IPv6 best-effort rules
    try {
        $qv6q = (new Query('/ipv6/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('protocol', 'udp')
            ->equal('dst-port', '443')
            ->equal('dst-address-list', $LIST_V6)
            ->equal('action', 'drop')
            ->equal('comment', $TAG . ' QUIC v6');
        foreach ($lanSelIn as $k=>$v) { $qv6q->equal($k, $v); }
        $client->query($qv6q)->read();

        $qv6a = (new Query('/ipv6/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('dst-address-list', $LIST_V6)
            ->equal('action', 'drop')
            ->equal('comment', $TAG . ' IP v6');
        foreach ($lanSelIn as $k=>$v) { $qv6a->equal($k, $v); }
        $client->query($qv6a)->read();

        $qv6b = (new Query('/ipv6/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('src-address-list', $LIST_V6)
            ->equal('action', 'drop')
            ->equal('comment', $TAG . ' REPLY v6');
        foreach ($lanSelOut as $k=>$v) { $qv6b->equal($k, $v); }
        $client->query($qv6b)->read();
    } catch (\Throwable $e) {}

    // Move filter rules to top for reliability
    try {
        $all = $client->query((new Query('/ip/firewall/filter/print')))->read();
        foreach ($all as $r) {
            if (strpos($r['comment'] ?? '', 'BlockIT Global ' . $app) !== false) {
                $client->query((new Query('/ip/firewall/filter/move'))
                    ->equal('numbers', $r['.id'])
                    ->equal('destination', '0'))
                ->read();
            }
        }
    } catch (\Throwable $e) {}

    echo json_encode([
        'success' => true,
        'message' => "Blocked $app globally for all LAN devices",
        'domains' => $domains,
    'lists' => [$LIST_V4, $LIST_V6],
    'elapsed_ms' => (int) round((microtime(true) - $__start) * 1000)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>