#!/bin/bash
set +x
source /opt/soda/settings.sh
echo "/////////CHECK IF I NEED TO SPIN UP MORE VMS/////////////////"
INCOME=`curl "$APIURL?do=check&serverip=$SERVERIP&type=6"`
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
qemu-img create -f qcow2 -b /opt/soda/rocky/rocky.qcow2 /opt/soda/machines/$UUID/disk.qcow2 ${TYPEDISK}M
echo after

echo screen
qemu-system-x86_64 \
                -name freerocky-firstboot \
                -enable-kvm -cpu host \
                -drive file=/opt/soda/machines/$UUID/disk.qcow2,if=virtio \
                -m 1024 -netdev type=user,id=net0,hostfwd=tcp::2215-:22 \
                -device virtio-net-pci,netdev=net0,mac=$NEWMAC \
                -daemonize -nodefaults -no-user-config -nographic -device virtio-vga --sandbox on
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
echo "NEWPASS=$NEWPASS" > /opt/soda/machines/$UUID/start.sh
echo "NEWIP=$NEWIP" >> /opt/soda/machines/$UUID/start.sh
echo "NEWCIDR=$NEWCIDR" >> /opt/soda/machines/$UUID/start.sh
echo "NEWGATE=$NEWGATE" >> /opt/soda/machines/$UUID/start.sh
echo "NEWDNS=$NEWDNS" >> /opt/soda/machines/$UUID/start.sh
cat /opt/soda/rocky/base.sh >> /opt/soda/machines/$UUID/start.sh
echo sshing
sshpass -p "defcon1337" scp -P2215 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no /opt/soda/machines/$UUID/start.sh root@127.0.0.1:/tmp/start.sh
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
sshpass -p "defcon1337" ssh -p2215 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no root@127.0.0.1 sh /tmp/start.sh
while true; do
MACHINE=`ps aux | grep qemu | grep "freerocky-firstboot" | head -n 1`
if [ -z "$MACHINE" ];
then
break;
else
echo -n "."
sleep 1;
fi
done
qemu-system-x86_64 \
                -name $UUID -uuid $UUID \
                -enable-kvm -cpu host \
                -drive file=/opt/soda/machines/$UUID/disk.qcow2,if=virtio \
                -m 1024 -daemonize -nodefaults -no-user-config  -nographic -device virtio-vga --sandbox on \
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
curl "$APIURL?do=add&serverip=$SERVERIP&type=$TYPEID&toraddr=$TORADDR&username=root&password=$NEWPASS&uuid=$UUID&macaddr=$NEWMAC&ipaddr=$NEWIP"
fi
exit
