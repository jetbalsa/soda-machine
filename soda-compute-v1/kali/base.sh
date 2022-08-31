
echo "$NEWPASS\n$NEWPASS\n" | passwd root
echo "$NEWPASS\n$NEWPASS\n" | passwd kali
echo "auto eth0" >> /etc/network/interfaces
echo "allow-hotplug eth0" >> /etc/network/interfaces
echo "iface eth0 inet static" >> /etc/network/interfaces
echo "    address $NEWIP/$NEWCIDR" >> /etc/network/interfaces
echo "    gateway $NEWGATE" >> /etc/network/interfaces
echo "    dns-nameservers $NEWDNS" >> /etc/network/interfaces
echo "nameserver $NEWDNS" > /etc/resolv.conf
mkdir -p /etc/systemd/system/getty@tty1.service.d/
echo "[Service]
ExecStart=
ExecStart=-/sbin/agetty -o '-p -f -- \\u' --noclear --autologin root - $TERM" > /etc/systemd/system/getty@tty1.service.d/autologin.conf

systemctl disable NetworkManager.service
systemctl enable networking.service
poweroff
