#!/bin/bash
source settings.sh
renice -n 10 $$
while true; do
bash winsrvcore/cron.sh | logger -s -t winsrvcore
bash rocky/cron.sh | logger -s -t rocky
bash rocky/cron-lite.sh | logger -s -t rocky-lite
bash openwrt/cron.sh | logger -s -t openwrt
bash kali/cron.sh | logger -s -t kali
bash debian/cron.sh | logger -s -t debian
bash debian/cron-lite.sh | logger -s -t debian-lite
bash bsd/cron.sh | logger -s -t bsd

echo "///////// CLEANUP DEAD VMS/////////////////"
for d in /opt/soda/machines/*/ ; do
    echo -n "."
    if [ $d == "/opt/soda/machines/*/" ];
    then
       echo "!!! NO VMs FOUND IN MACHINES FOLDER, IS THIS A NEW SERVER?!"
    else
    UUID=`basename "$d"`
    MACHINE=`ps aux | grep qemu | grep $UUID | head -n 1`
    if [ -z "$MACHINE" ];
    then
        echo -e "\r\n==== PURGING OUT $UUID =====\r\n"
        rm -rvf /opt/soda/machines/$UUID
        rm -rvf /var/lib/tor/hs/$UUID
        rm -rvf /var/lib/tor/torrc.d/$UUID.conf
        service tor reload
        curl "$APIURL?do=delete&uuid=$UUID"
  	curl \
  	-H "Content-Type: application/json" \
  	-d "{\"username\": \"CRON\", \"content\": \"[CRON][$SERVERIP] Found Dead VM: $UUID -- Removing\"}" \
  	$WEBHOOK_URL
        echo "=== DONE ==="
    fi
    fi
done
sleep 10
done
