<?php
require 'Medoo.php';
use Medoo\Medoo;
require "webhook.php";
ini_set("auto_detect_line_endings", true);
$database = new Medoo([
    'type' => 'sqlite',
    'database' => '../database.db',
    'error' => PDO::ERRMODE_EXCEPTION
]);
$database->update("state", ["data" => 0], ["name" => "buttonstate"]);
$arduinoipo = '192.168.196.167';
$arduinoport = 2000;
$fp = fsockopen($arduinoipo, $arduinoport, $errno, $errstr, 5);
stream_set_timeout($fp, 2);
function sendcmd($message){
    global $fp;
    if (!$fp) {
        logtodiscord("[BUTTON] @everyone LOST CONN!");
        die("no conn");        
        return;
    } else {
        fwrite($fp,$message."\r\n");
        $data = fgets($fp, 1028);
        //echo "== BUTT-MSG: " . trim($data) .PHP_EOL;
        return trim($data);
    }
}
function sendyolo($message){
    global $fp;
    if (!$fp) {
        logtodiscord("[BUTTON] @everyone LOST CONN!");
        die("no conn");   
        return;
    } else {
        fwrite($fp,$message."\r\n");
        return;
    }
}
echo "=== STARTING UP";
///logtodiscord("[BUTTON] STARTING UP");
sendcmd("b");
echo "b";
sendcmd("b");
echo "b";
sendcmd("b");
echo "b";
sendyolo("s c");
$flip = 0;
$lastmoney = 0;
$laststate = "";
$loop = 0;
$lastbutton = 0;
while (true){
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    $buttonraw = sendcmd("b");
    echo ".";
    if(substr($buttonraw, 0, 1) == "^"){
        $buttonexplode = str_split(substr($buttonraw, 1, 9));
        foreach($buttonexplode as $k => $v){
            if($v == 0){
            $database->update("state", ["data" => ($k+1)], ["name" => "buttonstate"]);
            echo "BT:" . ($k+1) . PHP_EOL;
	    sendcmd("b"); sendcmd("b");
            if($money[0]["data"] == 0){
                $flavorinfo = $database->select("types", "*", ["id" => ($k+1)]);
                sendyolo("s a c");
                sendyolo("s o 4 0 0 \$" . "{$flavorinfo[0]["price"]}");
                $flip = 6;
            }
            }
        }
    }

    /// SET AMOUNT
    if($money[0]["data"] > 0){
        if($money[0]["data"] != $lastmoney){
        sendyolo("s a c");
        sendyolo("s o 4 0 0 \$" . "{$money[0]["data"]}");
        echo "\$";
        $lastmoney = $money[0]["data"]; 
        $flip = 0;
        $flavorinfo = $database->select("types", "*");
        foreach($flavorinfo as $flaverflav){
        sendcmd("s l 0 0 55 0");
        sendcmd("s l 9 255 255 255");
        sendcmd("s l 10 255 255 255");
        $flip = 4;
        if($flaverflav["price"] <= $money[0]["data"]){
            sendcmd("s l ".($flaverflav["id"] - 1)." 0 55 0");
        }else{
            sendcmd("s l ".($flaverflav["id"] - 1)." 55 25 0"); 
        }
        }
        }
    }else{
        $lastmoney = 0;
        $flip++;
        if($flip == 1){
            sendcmd("s l 10 0 0 0");
            sendcmd("s l 9 0 0 0");
            sendyolo("s a c");
            sendyolo("s o 3 0 0 INSERT");
            echo "F";
        }
        if($flip == 5){
            sendyolo("s a c");
            sendyolo("s o 4 0 0 COIN");
            echo "f";
        }
        if($flip > 10){
            $flip = 0;
        }
    }
    $dbstate = $database->select("state", "data", ["name" => "purchase"]);
    $state = $dbstate[0];
    switch ($state) {
        case 'idle':
            if($laststate != "idle"){
            sendcmd("s l 0 0 15 0");
            sendcmd("s l 1 0 15 0");
            sendcmd("s l 2 0 15 0");
            sendcmd("s l 3 0 15 0");
            sendcmd("s l 4 0 15 0");
            sendcmd("s l 5 0 15 0");
            sendcmd("s l 6 0 15 0");
            sendcmd("s l 7 0 15 0");
            sendcmd("s l 8 0 15 0");
            $laststate = "idle";
            }
            break;
        case 'codeinput':
            if($laststate != "codeinput"){
            sendcmd("s l 0 0 0 55");
            sendcmd("s l 1 0 0 55");
            sendcmd("s l 2 0 0 55");
            sendcmd("s l 3 0 0 55");
            sendcmd("s l 4 0 0 55");
            sendcmd("s l 5 0 0 55");
            sendcmd("s l 6 0 0 55");
            sendcmd("s l 7 0 0 55");
            sendcmd("s l 8 0 0 55");
            //sendcmd("s l 9 0 0 0");
           // sendcmd("s l 10 0 0 0");
            $laststate = "codeinput";
            }
        default:
        $laststate = $state;
            break;
    }
sleep(1);
}



/// WHITE, IDLE
/// GREEN, Money Idle you can afford
/// BLUE, CODEENTRY
/// Orange, Cant Afford
/// RED, Disabled.
