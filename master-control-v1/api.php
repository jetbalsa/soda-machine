<?php
require "config.php";
require "webhook.php";
function baderror($text){
    die("You little fuck, get out of my system! Reporting your IP to a GOON".PHP_EOL.$text);
}

switch ($_GET["do"]) {
    case "add":
        // This is for adding a new VM to the pool.
        $database->insert("queue", [
            "serverip" => $_GET["serverip"],
            "type" => $_GET["type"],
            "toraddr" => $_GET["toraddr"],
            "timestamp" => time(),
            "username" => $_GET["username"],
            "password" => $_GET["password"],
            "uuid" => $_GET["uuid"],
            "macaddr" => $_GET["macaddr"],
            "ipaddr" => $_GET["ipaddr"]
        ]);
        break;
    case "newnet":
            // get new mac/ip pair, requires serverip
            if(empty($_GET["serverip"])){baderror("Empty IP");}
            $newnet = $database->select("net", ["id","mac","ip","dnsserver","gateway","cidr"], ["serverip" => null]);
            $database->update("net", ["timestamp1" => time(), "serverip" => $_GET["serverip"]], ["id" => $newnet[0]["id"]]);
            $out = fopen('php://output', 'w');
            fputcsv($out, $newnet[0]);
            fclose($out);
    break;
    case "check":
        // This checks the queue to see if any new boxes need to be made
        // Parms: serverip = "internalip of the server"
        if(empty($_GET["serverip"])){baderror("Empty IP");}
        if(empty($_GET["type"])){baderror("Empty type");}
        //Get all VMs in queue and shove them back to the host, also pull all type and let the  host figure out what it wants to do.
        $output["types"] = $database->select("types", ["id","cpu","ram","disk","prewarm","maxamount"], ["id"=>$_GET["type"]]);
        // prep start.sh command lines
        foreach($output["types"] as $type){
            $count = $database->count("queue", ["type" => $type["id"],["serverip"] => $_GET["serverip"]]);
            if($count < $type["prewarm"]){
                $out = fopen('php://output', 'w');
                fputcsv($out, $type);
                fclose($out);
                die();
            }
        }
        break;
    case "types":
        $type = $database->select("types", "*");
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($type[0]));
        foreach($type as $row){
        fputcsv($out, $row);
        }
        fclose($out);
        break;
    case "freevoucher":
        while(true){
            $code = mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9)."8"."7";
            $checkcode = $database->select("codes", "*", ["code" => $code]);
            if(empty($checkcode[0]["code"])){
                /// CODE OK
                echo $code.PHP_EOL.PHP_EOL;
                $database->insert("codes", ["code" => $code, "amount"=>$_GET["amt"], "lastused" => time()]);
                logtodiscord("[API][GIFT][FREE] NEW CODE GENERATED " . $code . " Amt: " .$_GET["amt"]);
                break;
            }
        }
        break;
        case "paidvoucher":
            while(true){
                $code = mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9)."9"."7";
                $checkcode = $database->select("codes", "*", ["code" => $code]);
                if(empty($checkcode[0]["code"])){
                    /// CODE OK
                    echo $code.PHP_EOL.PHP_EOL;
                    $database->insert("codes", ["code" => $code, "amount"=>$_GET["amt"], "lastused" => time()]);
                    logtodiscord("[API][GIFT][PAID] NEW CODE GENERATED " . $code . " Amt: " .$_GET["amt"]);
                    echo $code;
                    break;
                }
            }
            break;
            case "refund":
                $checkcode = $database->select("codes", "*", ["code" => $_GET["code"]]);
                $database->update("codes", ["amount"=> 0, "lastused" => time()], ["code" => $_GET["code"]]);
                echo $checkcode[0]["amount"].PHP_EOL.PHP_EOL;
                logtodiscord("[API][GIFT] REFUND FOR ".$checkcode[0]["data"]." AMT: ".$checkcode[0]["amount"]);
            break;
            case "buyvm":
                break;
    default:
        echo "You little fuck, get out of my system! Reporting your IP to a GOON";
}

