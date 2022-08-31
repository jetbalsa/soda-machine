
echo "$NEWPASS\n$NEWPASS" | passwd root
echo "$NEWPASS\n$NEWPASS" | passwd debian
echo "auto lo" > /etc/network/interfaces
echo "iface lo inet loopback" >> /etc/network/interfaces
echo "auto eth0" >> /etc/network/interfaces
echo "allow-hotplug ens3" >> /etc/network/interfaces
echo "iface ens3 inet static" >> /etc/network/interfaces
echo "    address $NEWIP/$NEWCIDR" >> /etc/network/interfaces
echo "    gateway $NEWGATE" >> /etc/network/interfaces
echo "    dns-nameservers $NEWDNS" >> /etc/network/interfaces
echo "auto ens2" >> /etc/network/interfaces
echo "allow-hotplug ens2" >> /etc/network/interfaces
echo "iface ens2 inet static" >> /etc/network/interfaces
echo "    address $NEWIP/$NEWCIDR" >> /etc/network/interfaces
echo "    gateway $NEWGATE" >> /etc/network/interfaces
echo "    dns-nameservers $NEWDNS" >> /etc/network/interfaces
echo "nameserver $NEWDNS" > /etc/resolv.conf
growpart /dev/vda 1
resize2fs /dev/vda1
/bin/rm -v /etc/ssh/ssh_host_*
dpkg-reconfigure openssh-server
poweroff
