# may/20/2025 21:04:55 by RouterOS 6.49.13
# software id = TGEP-5JG3
#
# model = RB952Ui-5ac2nD
# serial number = HGE09JMTVFE
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
