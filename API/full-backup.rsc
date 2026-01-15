# may/20/2025 21:07:25 by RouterOS 6.49.13
# software id = TGEP-5JG3
#
# model = RB952Ui-5ac2nD
# serial number = HGE09JMTVFE
/interface bridge
add name=bridge-lan
/interface wireless
set [ find default-name=wlan2 ] ssid=MikroTik
/interface wireless security-profiles
set [ find default=yes ] supplicant-identity=MikroTik
add authentication-types=wpa2-psk mode=dynamic-keys name=BLOCKIT-PROFILE \
    supplicant-identity=MikroTik wpa2-pre-shared-key=password102
/interface wireless
set [ find default-name=wlan1 ] disabled=no mode=ap-bridge security-profile=\
    BLOCKIT-PROFILE ssid=BLOCKIT
/ip pool
add name=lan_pool ranges=192.168.88.10-192.168.88.254
/ip dhcp-server
add address-pool=lan_pool disabled=no interface=bridge-lan name=dhcp1
/interface bridge port
add bridge=bridge-lan hw=no interface=ether2
add bridge=bridge-lan hw=no interface=ether3
add bridge=bridge-lan hw=no interface=ether4
add bridge=bridge-lan interface=wlan1
add bridge=bridge-lan interface=ether5
/ip neighbor discovery-settings
set discover-interface-list=!dynamic
/interface l2tp-server server
set enabled=yes
/interface pptp-server server
set enabled=yes
/ip address
add address=192.168.88.1/24 interface=bridge-lan network=192.168.88.0
/ip dhcp-client
add interface=ether1 use-peer-dns=no
add interface=ether1 use-peer-dns=no
add interface=ether1 use-peer-dns=no
add disabled=no interface=ether1
/ip dhcp-server lease
add address=192.168.88.253 client-id=1:f8:75:a4:34:9f:69 mac-address=\
    F8:75:A4:34:9F:69 server=dhcp1
add address=192.168.88.254 client-id=1:f2:31:95:93:8a:2b mac-address=\
    F2:31:95:93:8A:2B server=dhcp1
add address=192.168.88.252 client-id=1:98:b7:1e:1c:c3:95 mac-address=\
    98:B7:1E:1C:C3:95 server=dhcp1
/ip dhcp-server network
add address=192.168.88.0/24 dns-server=192.168.88.1 gateway=192.168.88.1
/ip dns
set allow-remote-requests=yes servers=1.1.1.1,1.0.0.1
/ip firewall address-list
add address=www.youtube.com comment=\
    "Site www.youtube.com for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
add address=youtube.com comment="Site youtube.com for MAC F8:75:A4:34:9F:69" \
    list="blocked-sites F8:75:A4:34:9F:69"
add address=m.youtube.com comment=\
    "Site m.youtube.com for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
add address=youtubei.googleapis.com comment=\
    "Site youtubei.googleapis.com for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
add address=youtube.googleapis.com comment=\
    "Site youtube.googleapis.com for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
add address=googlevideo.com comment=\
    "Site googlevideo.com for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
add address=ytimg.com comment="Site ytimg.com for MAC F8:75:A4:34:9F:69" \
    list="blocked-sites F8:75:A4:34:9F:69"
add address=youtu.be comment="Site youtu.be for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
/ip firewall filter
add action=drop chain=forward comment="Auto block for F8:75:A4:34:9F:69" \
    dst-address-list="blocked-sites F8:75:A4:34:9F:69" log=yes log-prefix=\
    BLOCKED-SITE src-address=192.168.88.253
/ip firewall nat
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=redirect chain=dstnat dst-port=53 protocol=udp to-ports=53
add action=redirect chain=dstnat dst-port=53 protocol=tcp to-ports=53
add action=masquerade chain=srcnat out-interface=ether1
add action=dst-nat chain=dstnat comment=MIKROTIK dst-port=9000 protocol=tcp \
    src-address=192.168.100.1 to-addresses=192.168.88.1 to-ports=9000
/ip service
set winbox port=9000
/system clock
set time-zone-name=Asia/Manila
/system scheduler
add interval=1d name=unblock_F8:75:A4:34:9F:69 on-event="/ip firewall filter e\
    nable [find comment=\"Auto block for F8:75:A4:34:9F:69\"]" policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-date=may/13/2025 start-time=08:00:00
add interval=1d name=block_F8:75:A4:34:9F:69 on-event="/ip firewall filter dis\
    able [find comment=\"Auto block for F8:75:A4:34:9F:69\"]" policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-date=may/13/2025 start-time=08:00:00
add interval=1d name=unblock_F2:31:95:93:8A:2B on-event="/ip firewall filter e\
    nable [find comment=\"Auto block for F2:31:95:93:8A:2B\"]" policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-date=may/13/2025 start-time=08:00:00
add interval=1d name=block_F2:31:95:93:8A:2B on-event="/ip firewall filter dis\
    able [find comment=\"Auto block for F2:31:95:93:8A:2B\"]" policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-date=may/13/2025 start-time=08:00:00
add interval=1d name=unblock_98:B7:1E:1C:C3:95 on-event="/ip firewall filter e\
    nable [find comment=\"Auto block for 98:B7:1E:1C:C3:95\"]" policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-date=may/13/2025 start-time=08:00:00
add interval=1d name=block_98:B7:1E:1C:C3:95 on-event="/ip firewall filter dis\
    able [find comment=\"Auto block for 98:B7:1E:1C:C3:95\"]" policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-date=may/13/2025 start-time=08:00:00
/system script
add dont-require-permissions=no name=check-blocked-access owner=admin policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon source=":\
    local logs [/log find where message~\"BLOCKED-SITE\"]; :if ([:len \$logs] \
    > 0) do={ :local msg \"Access attempt to blocked site detected:\r\
    \n\r\
    \n\"; :foreach i in=\$logs do={ :local time [/log get \$i time]; :local me\
    ssage [/log get \$i message]; :set msg (\$msg . \$time . \" | \" . \$messa\
    ge . \"\r\
    \n\"); }; /tool e-mail send to=\"jvaleza1997@gmail.com\" subject=\" Blocke\
    d Site Access Attempt\" body=\$msg; /log info \"Blocked site access attemp\
    t email sent\"; }"
/tool e-mail
set address=smtp.gmail.com from=sendernotifalert@gmail.com password=\
    "asng husd wqqr xuwp" port=587 start-tls=yes user=\
    sendernotifalert@gmail.com
