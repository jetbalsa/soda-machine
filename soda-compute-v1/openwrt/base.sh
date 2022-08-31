
echo -e "$NEWPASS\n$NEWPASS\n" | passwd root
uci set network.eth0.proto=static
uci set network.eth0.ipaddr=$NEWIP
uci set network.eth0.netmask=255.255.192.0
uci set network.eth0.gateway=$NEWGATE
uci set network.eth0.dns=1.1.1.1
uci commit
uci show network
poweroff
