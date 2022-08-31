

echo $NEWPASS | pw mod user root -h 0
sysrc ifconfig_vtnet0="inet $NEWIP netmask $NEWMASK"
sysrc defaultrouter="$NEWGATE"
sysrc growfs_enable="YES"
cat /etc/rc.conf
echo "nameserver 1.1.1.1" > /etc/resolv.conf
touch /firstboot
poweroff
