
echo -e "$NEWPASS\n$NEWPASS\n" | passwd root

nmcli con mod ethernet-ens2 ipv4.addresses $NEWIP/$NEWCIDR
nmcli con mod ethernet-ens2 ipv4.gateway "$NEWGATE"
nmcli con mod ethernet-ens2 ipv4.dns 1.1.1.1
nmcli con mod ethernet-ens2 ipv4.method manual

nmcli con mod ens3 ipv4.addresses $NEWIP/$NEWCIDR
nmcli con mod ens3 ipv4.gateway "$NEWGATE"
nmcli con mod ens3 ipv4.dns 1.1.1.1
nmcli con mod ens3 ipv4.method manual
sudo dnf makecache --refresh
sudo dnf -y install cloud-utils-growpart
growpart /dev/vda 1
resize2fs /dev/vda1
poweroff
