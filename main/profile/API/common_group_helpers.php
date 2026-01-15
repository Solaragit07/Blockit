<?php
function is_ip($s){ return (bool)filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4); }
function is_cidr($s){
  if (!preg_match('~^(\d{1,3}\.){3}\d{1,3}/\d{1,2}$~',$s)) return false;
  [$ip,$m]=explode('/',$s,2); if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
  $m=(int)$m; return $m>=0 && $m<=32;
}
function normalize_hostname($s){
  $s = strtolower(trim($s));
  $s = preg_replace('~^[a-z][a-z0-9+.\-]*://~i','',$s);
  $s = preg_replace('~[/\?#].*$~','',$s);
  return rtrim($s,'.');
}
function is_hostname($s){
  $h = normalize_hostname($s);
  return $h !== '' && (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',$h);
}
