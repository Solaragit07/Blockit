<?php
// Accepts full URLs, bare domains, IPv4, or CIDR.
// Returns ['type'=>'ip'|'cidr'|'domain','value'=>'normalized','display'=>'pretty for comments']
// On error: throws InvalidArgumentException

function normalize_input_address(string $raw): array {
  $orig = trim($raw);
  if ($orig === '') throw new InvalidArgumentException('Empty input');

  $x = strtolower($orig);

  // ---- quick IP / CIDR checks
  if (filter_var($x, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return ['type'=>'ip', 'value'=>$x, 'display'=>$x];
  }
  if (preg_match('~^(?P<ip>(?:25[0-5]|2[0-4]\d|1?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|1?\d{1,2})){3})/(?P<prefix>[0-9]|[12][0-9]|3[0-2])$~', $x)) {
    return ['type'=>'cidr', 'value'=>$x, 'display'=>$x];
  }

  // ---- tolerate full URLs; if no scheme, add one so parse_url works
  $u = $x;
  if (!preg_match('~^https?://~i', $u)) $u = 'http://' . $u;
  $parts = parse_url($u);
  $host  = $parts['host'] ?? '';

  // If parse_url failed and user pasted something like "example.com/path"
  if (!$host) {
    // last-resort: take until first slash
    $host = strtok($x, '/');
  }

  if (!$host) throw new InvalidArgumentException('Could not parse host');

  // IDN punycode → ASCII (if available)
  if (function_exists('idn_to_ascii')) {
    $host_ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
    if ($host_ascii) $host = $host_ascii;
  }

  // strip trailing dot and spaces
  $host = rtrim($host, ". \t\r\n");

  // validate it’s a domain (not an IP)
  if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return ['type'=>'ip', 'value'=>$host, 'display'=>$host];
  }

  // domain pattern
  if (!preg_match('~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$~i', $host)) {
    throw new InvalidArgumentException('Invalid domain or IP');
  }

  return ['type'=>'domain', 'value'=>$host, 'display'=>$host];
}
