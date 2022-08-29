<?php
require "config.php";
$MACPREFIX = "FC:CF:62";
$IPPREFIX = "172.21.64.";
$IPMIN = 10;
$IPMAX = 250;
$GATEWAY = "172.21.64.1";
$DNS = "192.168.0.64";
$CIDR = "18";

for ($z = 64; $z <= 124; $z++) {
$IPPREFIX = "172.21.$z";
for ($x = $IPMIN; $x <= $IPMAX; $x++) {
    $NEWIP = $IPPREFIX . ".". $x;
    $NEWMAC = strtoupper($MACPREFIX . ":" . implode(':', str_split(substr(sha1(random_bytes(5)), 0, 6), 2)));
    echo $NEWIP . "  " . $NEWMAC . PHP_EOL;
    $database->insert("net", [
        "mac" => $NEWMAC,
        "ip" => $NEWIP,
        "dnsserver" => $DNS,
        "gateway" => $GATEWAY,
        "cidr" => $CIDR
    ]);
} 
}


