<?php
require __DIR__ . '/vendor/autoload.php';
require 'Medoo.php';
ini_set("auto_detect_line_endings", true);
use Medoo\Medoo;
require "webhook.php";
// Connect the database.
$database = new Medoo([
    'type' => 'sqlite',
    'database' => '../database.db'
]);

$mbdipo = '192.168.196.167';
$mbdport = 2001;
function sendmbd($message){
    global $mbdipo, $mbdport;
    $fp = fsockopen($mbdipo, $mbdport, $errno, $errstr, 5);
    if (!$fp) {
        echo "$errstr ($errno)\n";
        return;
    } else {
        fgets($fp, 4096);
        fwrite($fp,$message.PHP_EOL);
        $data = fgets($fp, 4096);
        $data2 = fgets($fp, 4096);
        $data3 = fgets($fp, 4096);
        fclose($fp);
        if(trim($data3) == "p,ACK"){
            echo ".";
        }
        if(trim($data3) == "p,NACK"){
            echo "!";
        }
        if(trim($data3) == "p,0a"){
            echo ".";
        }
        return trim($data3);
    }
}
function updategiftcard(){
    global $database;
    $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    if(!empty($codemem[0]["data"])){
        $database->update("codes", ["amount" => $money[0]["data"]], ["code" => $codemem[0]["data"]]);
        logtodiscord("[COIN][GIFTCARD] Updating Code to Amt: " . $money[0]["data"]);
         return true;
    }else{
        return false;
    }
}
while (true) {
    echo "STARTLOOP".PHP_EOL;
    $purchase = $database->select("state", ["data", "time"], ["name" => "purchase"]);
    $lastpurchase = $purchase[0]["time"];
    $database->update("state", ["data" => 0, "time" => time()], ["name" => "forcereturn"]);
    $database->update("state", ["data" => 0, "time" => time()], ["name" => "money"]);
    //// MBD-USB SOCAT SOCKET
    //// socat -v -v TCP-LISTEN:4161,fork,reuseaddr FILE:/dev/ttyACM0,b115200,raw
    $version = sendmbd("V");
    if($version !== "v,4.0.0.0,16df464a3031340136313930"){
        die("INVAILD DATA FROM VERSION: $version");
        logtodiscord("[DAEMON] @everyone USB OFFLINE");
    }
    // ENABLE MASTER MODE
    sendmbd("M,1");
    /// GET CURRENT STATUS OF BILL ACCEPTOR
    $resetstatus = sendmbd("R,30");
    sleep(2);
    $billtype = sendmbd("R,34,FFFFFFF");
    sleep(1);
    $pollstatus = sendmbd("R,33");
    sleep(1);
    //// SETUP COIN ACCEPTOR
    sendmbd("R,08");
    sleep(1);
    sendmbd("R,0B");
    sleep(1);
    sendmbd("R,09");
    sleep(1);
    sendmbd("R,0F,00");
    sleep(1);
    sendmbd("R,0F,0100000000");
    sleep(1);
    sendmbd("R,0C,000F0000");
    sleep(1);
    sendmbd("R,0B");
    sendmbd("R,35,F");
    /// MAIN POLLING LOOP FOR A BILL!
    logtodiscord("[DAEMON] MAIN LOOP ONLINE");
    $moneyescrow = 0;
    $currentcoins[25] = 0;
    $currentcoins[10] = 0;
    $currentcoins[5] = 0;
    $moneyinacceptor = 0;
    $flip = 0;
    while (true){
        $pollcoin = str_split(substr(sendmbd("R,0B"), 2), 4);
        $money = $database->select("state", ["data", "time"], ["name" => "money"]);
        if($money[0]["data"] == 0){
            $totalamount = 0;
        }
        foreach($pollcoin as $coins){
            $coins = substr($coins, 0, 2);
        switch ($coins) {
            case '01':
                $money = $database->select("state", ["data", "time"], ["name" => "money"]);
                $database->update("state", ["data" => 1, "time" => time()], ["name" => "forcereturn"]);
                $totalamount = $money[0]["data"];
                echo "COIN RETURN PUSHED".PHP_EOL;
                logtodiscord("[COIN] RETURN PUSHED");
                $database->update("state", ["data" => 99, "time" => time()], ["name" => "buttonstate"]);
                if($totalamount == 0){
                    $database->update("state", ["data" => 99, "time" => time()], ["name" => "buttonstate"]);
                    logtodiscord("[COIN] MENU BUTTON PUSHED FROM COIN RETURN");
                }
                while($totalamount > 0){
                    echo "== PROCESSING RETURN: " . $totalamount.PHP_EOL;
                    logtodiscord("[COIN] RETURNING $totalamount");
                    if($totalamount > 3){
                        $database->update("state", ["data" => 99, "time" => time()], ["name" => "buttonstate"]);
                        logtodiscord("[COIN] SKIPPING REFUND DUE TO AMOUNT ABOVE 3 " . $totalamount);
                        break;
                    }
                    $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
                    if($codemem[0]["data"]){
                        logtodiscord("[COIN] SKIPPING REFUND DUE TO CODE IN MEM");
                        break;
                    }
                    if($totalamount >= 1){
                        sendmbd('R,0D,32');
                        sleep(2);
                        sendmbd('R,0D,21');
                        sleep(2);
                        sendmbd('R,0D,10');
                        sleep(2);
                        $totalamount = $totalamount - 1;
                        $database->update("state", ["data[-]" => 1, "time" => time()], ["name" => "money"]);
                        echo "=== DUMPING COINS FOR $1".PHP_EOL;
                        logtodiscord("[COIN] $1 Return - Left: $totalamount");
                    }else{
                        if($totalamount >= 0.25){
                            sendmbd('R,0D,12');
                            $totalamount = $totalamount - 0.25;
                            $database->update("state", ["data[-]" => 0.25, "time" => time()], ["name" => "money"]);
                            echo "=== DUMPING COINS FOR 25c".PHP_EOL;
                            logtodiscord("[COIN] 25c Return - Left: $totalamount");
                        }else{
                            if($totalamount >= 0.10){
                                sendmbd('R,0D,11');
                                $totalamount = $totalamount - 0.10;
                                $database->update("state", ["data[-]" => 0.10, "time" => time()], ["name" => "money"]);
                                echo "=== DUMPING COINS FOR 10c - Left: $totalamount".PHP_EOL;
                                logtodiscord("[COIN] 10c Return - Left: $totalamount");
                            }else{
                                if($totalamount >= 0.05){
                                    sendmbd('R,0D,10');
                                    $totalamount = 0;
                                    $database->update("state", ["data" => 0, "time" => time()], ["name" => "money"]);
                                    echo "=== DUMPING COINS FOR 5c - Left: $totalamount".PHP_EOL;
                                    logtodiscord("[COIN] 5c Return - Left: $totalamount");
                                }else{
                                    $totalamount = 0;
                                }
                            }
                        }
                    }
                    sleep(1);
                }
                $database->update("state", ["data" => 0, "time" => time()], ["name" => "forcereturn"]);
                break;
            case '52':
                echo "0.25 DETECTED".PHP_EOL;
                logtodiscord("[COIN] 25c Inserted");
                $currentcoins[25] += 1;
                $database->update("state", ["data[+]" => 0.25, "time" => time()], ["name" => "money"]);
                break;
            case '42':
                echo "0.25 DETECTED".PHP_EOL;
                logtodiscord("[COIN] 25c Inserted");
                $currentcoins[25] += 1;
                $database->update("state", ["data[+]" => 0.25, "time" => time()], ["name" => "money"]);
                break;
            case '51':
                echo "0.10 DETECTED".PHP_EOL;
                logtodiscord("[COIN] 10c Inserted");
                $currentcoins[10] += 1;
                $database->update("state", ["data[+]" => 0.10, "time" => time()], ["name" => "money"]);
                break;
            case '41':
                echo "0.10 DETECTED".PHP_EOL;
                logtodiscord("[COIN] 10c Inserted");
                $currentcoins[10] += 1;
                $database->update("state", ["data[+]" => 0.10, "time" => time()], ["name" => "money"]);
                break;
            case '50':
                echo "0.05 DETGECTED".PHP_EOL;
                logtodiscord("[COIN] 5c Inserted");
                $currentcoins[5] += 1;
                $database->update("state", ["data[+]" => 0.05, "time" => time()], ["name" => "money"]);
                break;
            case '40':
                echo "0.05 DETGECTED".PHP_EOL;
                logtodiscord("[COIN] 5c Inserted");
                $currentcoins[5] += 1;
                $database->update("state", ["data[+]" => 0.05, "time" => time()], ["name" => "money"]);
                break;
        }
    }
    $pollbill = sendmbd("R,33");
    if(trim($pollbill) != "p,0a"){
    echo PHP_EOL."B: ".$pollbill.PHP_EOL;
    }
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    $dbstate = $database->select("state", "data", ["name" => "purchase"]);
    $state = $dbstate[0];
    if($state = "idle"){
        $moneyinacceptor = 0;
        $moneyescrow = 0;
        sendmbd("R,35,F");
    }
    switch (substr($pollbill, 0, 6)) {
        case 'p,9009':
            echo "One dollar detected".PHP_EOL;
            logtodiscord("[BILL] $1 Inserted");
            $database->update("state", ["data[+]" => 1.00, "time" => time()], ["name" => "money"]);
            sendmbd("R,35,F");
            break;
        case 'p,9209':
                    echo "====== Five dollar detected".PHP_EOL;
                    logtodiscord("[BILL] $5 Inserted");
                    $database->update("state", ["data[+]" => 5.00, "time" => time()], ["name" => "money"]);
                    sendmbd("R,35,F");
                    break;         
    }
    sleep(0.5);
    }
    
}
