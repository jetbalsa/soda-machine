#!/bin/bash
set +x
source /opt/soda/settings.sh
echo "/////////CHECK IF I NEED TO SPIN UP MORE VMS/////////////////"
INCOME=`curl "$APIURL?do=check&serverip=$SERVERIP&type=9"`
echo $INCOME

if [ -z "$INCOME" ];
then
 echo "=== No new machines needed ==="
else

#SPLIT JOB
TYPEID=`echo $INCOME | cut -d',' -f1`
TYPECPU=`echo $INCOME | cut -d',' -f2`
TYPEMEM=`echo $INCOME | cut -d',' -f3`
TYPEDISK=`echo $INCOME | cut -d',' -f4`



#GETMAC FROM MGR
NEWNET=`curl "$APIURL?do=newnet&serverip=$SERVERIP"`
NEWMAC=`echo $NEWNET | cut -d',' -f2`
NEWIP=`echo $NEWNET | cut -d',' -f3`
NEWDNS=`echo $NEWNET | cut -d',' -f4`
NEWGATE=`echo $NEWNET | cut -d',' -f5`
NEWCIDR=`echo $NEWNET | cut -d',' -f6`
NEWMASK="255.255.192.0"
UUID=`cat /proc/sys/kernel/random/uuid`
NEWPASS=`shuf -i1000000000-9999999999 -n1`

echo $NEWNET
echo "==== SETTING TOR ===="
echo "HiddenServiceDir /var/lib/tor/hs/$UUID/" > /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 22 $NEWIP:22" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 80 $NEWIP:80" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 443 $NEWIP:443" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2000 $NEWIP:2000" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2001 $NEWIP:2001" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2002 $NEWIP:2002" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2003 $NEWIP:2003" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2004 $NEWIP:2004" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2005 $NEWIP:2005" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 2006 $NEWIP:2006" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServicePort 3389 $NEWIP:3389" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServiceNonAnonymousMode 1" >> /var/lib/tor/torrc.d/$UUID.conf
echo "HiddenServiceSingleHopMode 1" >> /var/lib/tor/torrc.d/$UUID.conf
echo "SocksPort 0" >> /var/lib/tor/torrc.d/$UUID.conf
service tor reload
mkdir -p /opt/soda/machines/$UUID
echo $NEWPASS > /opt/soda/machines/$UUID/password
echo before
qemu-img create -f qcow2 -b /opt/soda/winsrvcore/winsrv2022core-base.qcow2 /opt/soda/machines/$UUID/disk.qcow2 ${TYPEDISK}M
echo after

echo "\$IP = \"$NEWIP\"" >/opt/soda/machines/$UUID/basescript.ps1
echo "\$MaskBits = \"$NEWCIDR\"" >>/opt/soda/machines/$UUID/basescript.ps1
echo "\$Gateway = \"$NEWGATE\"" >>/opt/soda/machines/$UUID/basescript.ps1
echo "\$Dns = \"$NEWDNS\"" >>/opt/soda/machines/$UUID/basescript.ps1
echo "\$newpw = \"$NEWPASS\"" >>/opt/soda/machines/$UUID/basescript.ps1

cat /opt/soda/winsrvcore/basescript.ps1 >> /opt/soda/machines/$UUID/basescript.ps1

echo screen
qemu-system-x86_64 \
                -name $UUID -uuid $UUID \
                -enable-kvm -cpu host,hv_relaxed,hv_spinlocks=0x1fff,hv_vapic,hv_time \
                -drive file=/opt/soda/machines/$UUID/disk.qcow2,if=virtio \
                -m 4096 -smp 4 -daemonize -nodefaults -no-user-config -nographic -device virtio-vga --sandbox on \
                -netdev user,id=tap0,hostfwd=tcp::2222-:22 \
                -device virtio-net-pci,netdev=tap0,mac=$NEWMAC
retVal=$?
if [ $retVal -ne 0 ]; then
  curl \
  -H "Content-Type: application/json" \
  -d "{\"username\": \"CRON\", \"content\": \"[CRON][$SERVERIP] First Stage Failed for ID: $TYPEID\"}" \
  $WEBHOOK_URL
  echo "DIED"
  sleep 1;
  exit;
fi
echo "Waiting Machine Bootup"
sleep 30
sshpass -p 'defcon1337!' scp -P2222 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no /opt/soda/machines/$UUID/basescript.ps1 administrator@127.0.0.1:/C:/windows/temp/basescript.ps1
retVal=$?
if [ $retVal -ne 0 ]; then
  curl \
  -H "Content-Type: application/json" \
  -d "{\"username\": \"CRON\", \"content\": \"[CRON][$SERVERIP] Second Stage Failed for ID: $TYPEID\"}" \
  $WEBHOOK_URL
  echo "DIED"
  sleep 1;
  exit;
fi
sshpass -p 'defcon1337!' ssh -p2222 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no administrator@127.0.0.1 powershell.exe -ep bypass C:\\windows\\temp\\basescript.ps1
sleep 5
qemu-system-x86_64 \
                -name $UUID -uuid $UUID \
                -enable-kvm -cpu host,hv_relaxed,hv_spinlocks=0x1fff,hv_vapic,hv_time \
                -drive file=/opt/soda/machines/$UUID/disk.qcow2,if=virtio \
                -m 4096 -smp 4 -daemonize -nodefaults -no-user-config -nographic -device virtio-vga --sandbox on \
                -netdev bridge,id=tap0,br=fastbr0 \
	        -device virtio-net-pci,netdev=tap0,mac=$NEWMAC
retVal=$?
if [ $retVal -ne 0 ]; then
  curl \
  -H "Content-Type: application/json" \
  -d "{\"username\": \"CRON\", \"content\": \"[CRON][$SERVERIP] Third Stage Failed for ID: $TYPEID\"}" \
  $WEBHOOK_URL
  echo "DIED"
  sleep 1;
  exit;
fi
echo "===== $UUID UP! ===="
TORADDR=`cat /var/lib/tor/hs/$UUID/hostname`
while [ -z "$TORADDR" ]
do
    echo "in while loop"
    sleep 10s
    TORADDR=`cat /var/lib/tor/hs/$UUID/hostname`
done
curl "$APIURL?do=add&serverip=$SERVERIP&type=$TYPEID&toraddr=$TORADDR&username=administrator&password=$NEWPASS&uuid=$UUID&macaddr=$NEWMAC&ipaddr=$NEWIP"
fi
exit
