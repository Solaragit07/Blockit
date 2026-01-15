# jun/22/2025 21:57:55 by RouterOS 6.49.13
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
/queue simple
add max-limit=1M/1M name=BW_98:B7:1E:1C:C3:95 target=192.168.88.252/32
/user group
set full policy="local,telnet,ssh,ftp,reboot,read,write,policy,test,winbox,pas\
    sword,web,sniff,sensitive,api,romon,dude,tikapp"
add name=admin policy="local,ftp,reboot,read,write,test,winbox,password,web,sn\
    iff,sensitive,romon,dude,tikapp,!telnet,!ssh,!policy,!api"
add name=superadmin policy="local,telnet,ssh,ftp,reboot,read,write,policy,test\
    ,winbox,password,web,sniff,sensitive,api,romon,dude,tikapp"
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
add interface=ether1 use-peer-dns=no
add interface=ether1 use-peer-dns=no
add interface=ether1 use-peer-dns=no
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
add address=youtube.com comment="Site youtube.com for MAC 98:B7:1E:1C:C3:95" \
    list="blocked-sites 98:B7:1E:1C:C3:95"
add address=m.youtube.com comment=\
    "Site m.youtube.com for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=youtubei.googleapis.com comment=\
    "Site youtubei.googleapis.com for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=youtube.googleapis.com comment=\
    "Site youtube.googleapis.com for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=googlevideo.com comment=\
    "Site googlevideo.com for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=ytimg.com comment="Site ytimg.com for MAC 98:B7:1E:1C:C3:95" \
    list="blocked-sites 98:B7:1E:1C:C3:95"
add address=youtu.be comment="Site youtu.be for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=www.google.com comment=\
    "Site www.google.com for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=fast.com comment="Site fast.com for MAC 98:B7:1E:1C:C3:95" list=\
    "blocked-sites 98:B7:1E:1C:C3:95"
add address=fast.com comment="Site fast.com for MAC F8:75:A4:34:9F:69" list=\
    "blocked-sites F8:75:A4:34:9F:69"
/ip firewall filter
add action=drop chain=forward comment="Block MLBB UDP Ports" disabled=yes \
    dst-port=\
    4001-4009,5000-5221,5224-5241,5243-5508,5551-5559,5601-5700,30000-30999 \
    protocol=udp
add action=drop chain=forward comment="Block MLBB TCP Ports" disabled=yes \
    dst-port=\
    4001-4009,5000-5221,5224-5241,5243-5508,5551-5559,5601-5700,30000-30999 \
    protocol=tcp
add action=drop chain=forward comment="Auto block for 98:B7:1E:1C:C3:95" \
    dst-address-list="blocked-sites 98:B7:1E:1C:C3:95" log=yes log-prefix=\
    BLOCKED-SITE src-address=192.168.88.252
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
add action=dst-nat chain=dstnat comment=MIKROTIK dst-port=8728 protocol=tcp \
    src-address=192.168.100.1 to-addresses=192.168.88.1 to-ports=9000
/ip service
set api address=0.0.0.0/0
set winbox port=9000
set api-ssl disabled=yes
/system clock
set time-zone-name=Asia/Manila
/system logging
set 0 disabled=yes
set 1 disabled=yes
set 2 disabled=yes
set 3 disabled=yes
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
add name=API-Config-On-Boot on-event="/system script run ForceAPIconfig" \
    policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon \
    start-time=startup
/system script
add dont-require-permissions=no name=check-blocked-access owner=user1 policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon source=":\
    local logs [/log find where message~\"BLOCKED-SITE\"];\r\
    \n:if ([:len \$logs] > 0) do={\r\
    \n    :local msg \"\E2\9A\A0\EF\B8\8F Warning: Someone tried to visit a bl\
    ocked website on your network.\r\
    \n\r\
    \nDetails of the attempts:\r\
    \n------------------------\r\
    \n\r\
    \n\";\r\
    \n    :foreach i in=\$logs do={\r\
    \n        :local time [/log get \$i time];\r\
    \n        :local message [/log get \$i message];\r\
    \n        :set msg (\$msg . \"- Time: \" . \$time . \"\
    \n  Description: \" . \$message . \"\
    \n\
    \n\");\r\
    \n    };\r\
    \n    /tool e-mail send to=\"jvaleza1997@gmail.com\" subject=\"Alert: Bloc\
    ked Website Access Attempt Detected\" body=\$msg;\r\
    \n    /log info \"Blocked site access attempt email sent\";\r\
    \n}"
add dont-require-permissions=no name=ForceAPIconfig owner=user1 policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon source=\
    "/ip service set api disabled=no\r\
    \n/ip service set api port=8728\r\
    \n"
add dont-require-permissions=no name=blocked-site-alert owner=user1 policy=\
    ftp,reboot,read,write,policy,test,password,sniff,sensitive,romon source=":\
    local logs [/log find where message~\"BLOCKED-SITE\"];:if ([:len \$logs] >\
    \_0) do={:local msg \" Warning: Blocked website access attempts\\n\\n\";:f\
    oreach i in=\$logs do={:local time [/log get \$i time];:local message [/lo\
    g get \$i message];:set msg (\$msg . \"Time: \${time}\\nMessage: \${messag\
    e}\\n\\n\");};/tool e-mail send to=\"jvaleza1997@gmail.com\" subject=\"Blo\
    cked Website Access Alert\" body=\$msg;/log remove \$logs;/log info \"Bloc\
    ked site notification email sent\";}"
/tool e-mail
set address=smtp.gmail.com from=sendernotifalert@gmail.com password=\
    "asng husd wqqr xuwp" port=587 start-tls=yes user=\
    sendernotifalert@gmail.com
