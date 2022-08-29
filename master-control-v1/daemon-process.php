<?php
require __DIR__ . '/vendor/autoload.php';
require 'Medoo.php';
require 'webhook.php';
use Medoo\Medoo;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;

$printerip[] = "10.11.12.96";
$printerip[] = "10.11.12.149";
//$printerip[] = "172.23.42.11";
//$printerip[] = "172.23.42.11";
$lastprint = 0;
$tux = EscposImage::load("nucclogo.png", false);

// Connect the database.
$database = new Medoo([
    'type' => 'sqlite',
    'database' => '../database.db',
    'error' => PDO::ERRMODE_EXCEPTION
]);
$database->update("state", ["data" => 0], ["name" => "buttonstate"]);

//// SETUP TASKS
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function buyvm(){
    global $database;
    global $printerip;
    global $lastprint;
    global $tux;
    // GET CURRENT STATE OF MIND
    $button = $database->select("state", ["data", "time"], ["name" => "buttonstate"]);
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    $return = $database->select("state", ["data", "time"], ["name" => "forcedreturn"]);
    $flavorinfo = $database->select("types", "*", ["id" => $button[0]["data"]]); // get flavor of last button pushed.
    if($button[0]["data"] == 99){
        logtodiscord("[PROCESS][BUYVM] BUTTON 99 DETECTED, BAILING");
        return;
    }

    var_dump($button);
    var_dump($money);
    //// CHECK IF WE HAVE ENOUGH MONEY
    if($money[0]["data"] >= $flavorinfo[0]["price"]){
        /// WE HAVE THE MONEY
        /// DO WE HAVE THE VM?
        $vm = $database->select("queue", "*", ["type" => $button[0]["data"]]);
        if(!empty($vm[0]["uuid"])){
            /// PRINTER SETUP
            try {
            /// WHAT PRINTER DO WE WANT?
            if($lastprint == 0){
                $printip = $printerip[0];
                $lastprint = 1;
            }else{
                $printip = $printerip[1];
                $lastprint = 0;
            }
            $profile = CapabilityProfile::load("TSP600");
            $connector = new NetworkPrintConnector($printip, 9100);
            $printer = new Printer($connector, $profile);
            $printer->initialize();
            $statuscommand = Printer::GS . "1" . Printer::NUL;
            $printer->getPrintConnector()->write($statuscommand);
            $statusout = bin2hex($printer->getPrintConnector()->read(8));
            /// PRINTER ERROR!
            if($statusout != "14000000"){
                if($lastprint == 0){
                    $printip = $printerip[0];
                    $lastprint = 1;
                }else{
                    $printip = $printerip[1];
                    $lastprint = 0;
                }
                $printer -> close();
                echo "ERORR! $statusout".PHP_EOL;
                logtodiscord("[PRINT] @everyone PRINTER ERROR: $statusout - ".$printip);
                $rand_keys = array_rand($printerip, 2);
                unset($connector);
                unset($printer);
                $connector = new NetworkPrintConnector($printip, 9100);
                $printer = new Printer($connector, $profile);
                $printer->initialize();
                $statuscommand = Printer::GS . "1" . Printer::NUL;
                $printer->getPrintConnector()->write($statuscommand);
                $statusout = bin2hex($printer->getPrintConnector()->read(8));
            }
            /// WE HAVE A FREE VM!
            /// DELETE IT OUT OF THE QUEUE AND GIVE IT TO THE USER
            $vm = $database->select("queue", "*", ["type" => $button[0]["data"]]);
            $vm[0]["created"] = time();
            $vm[0]["expires"] = time() + (($flavorinfo[0]["alivetime"] * 60) * 60);
            $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]); 
            $codenumber = $codemem[0]["data"];
            $vm[0]["code"] = $codenumber;
            $prettyexpiredate = date(DATE_ATOM, $vm[0]["expires"]);
            $database->insert("active", $vm[0]);
            $database->delete("queue", ["id" => $vm[0]["id"]]);
            $database->update("state", ["data[-]" => $flavorinfo[0]["price"]], ["name" => "money"]);
            updategiftcard();
            logtodiscord("[PROCESS] PURCHASE SUCCESS - Price:".$flavorinfo[0]["price"]. " Bal:".$money[0]["data"] . " Flav:" . $flavorinfo[0]["printedname"] . " uuid:" . $vm[0]["uuid"] . " Ex:" .  $prettyexpiredate);
            $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
            if(substr($codemem[0]["data"], 7, 1) != 8){
            $database->update("state", ["data[+]" => $money[0]["data"]], ["name" => "totals"]);
            }
            //////////////// PRINTER STUFF ////////////////
            $database->update("state", ["data" => "vmget", "time" => time()], ["name" => "purchase"]); // Update UI
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> graphics($tux);
            $printer -> text("S.O.D.A. MACHINE\n");
            $printer -> text("POP`N OUT SHELLS\n\n\n");
            $printer -> setTextSize(1, 1);

            $printer -> setJustification();
            $printer -> text("Shell Flavor:\n");
            $printer -> setEmphasis(true);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($flavorinfo[0]["printedname"]."\n");
            $printer -> setEmphasis(false);

            $printer -> setJustification();
            $printer -> text("SSH IP Address:\n");
            $printer -> setEmphasis(true);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($vm[0]["ipaddr"]."\n");
            $printer -> setEmphasis(false);

            $printer -> setJustification();
            $printer -> text("SSH Username:\n");
            $printer -> setEmphasis(true); 
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($vm[0]["username"]."\n");
            $printer -> setEmphasis(false);

            $printer -> setJustification();
            $printer -> text("SSH Password:\n");
            $printer -> setEmphasis(true); 
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($vm[0]["password"]."\n");
            $printer -> setEmphasis(false);

            $printer -> setJustification();
            $printer -> text("VM Expires at:\n");
            $printer -> setEmphasis(true); 
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text("$prettyexpiredate\n");
            $printer -> setEmphasis(false);

            $printer -> setJustification();
            $printer -> text("Tor Hostname:\n");
            $printer -> setEmphasis(true); 
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($vm[0]["toraddr"]."\n");
            $printer -> setEmphasis(false);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> qrCode($vm[0]["toraddr"]);


            $printer -> feed();
            $printer -> setJustification();
            $printer -> text("Donation: ".$flavorinfo[0]["price"]."\n");

            $printer -> feed();
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> setEmphasis(true);
            $printer -> text("\n\nTerms of Service:\n\n");
            $printer -> setEmphasis(false);
            $printer -> setJustification();

            //// 42 LINES OF TEXT
            //////////////////////////////////////////
            $text = <<<EOF
            This SHELL ON DEMAND APPLIANCE
            (“S.O.D.A. MACHINE”) is the property of
            NATIONAL UPCYCLED COMPUTING COLLECTIVE INC
            (“NUCC”)

            NUCC is a 501(c)(3) nonprofit organization
            (EIN 82-1177433) as determined by the
            Internal Revenue Service with a
            National Taxonomy of Exempt Entities
            U41 Classification as a Computer Science,
            Technology and Engineering,
            Research Institute.

            S.O.D.A. MACHINE services are provided by
            NUCC at DEF CON as a NUCC Fundraiser
            and as such fall under the
            established DEF CON policies.

            Use of the services distributed by this
            S.O.D.A. MACHINE indicate your awareness
            of and consent to ALL DEF CON policies.

            www.defcon.org/html/links/dc-policy.html

            Unauthorized or improper use of these
            services may result in disciplinary
            action and civil/criminal penalties.

            EOF;

            $printer -> text($text);
            $printer -> setEmphasis(true);
            $printer -> text("\nFURTHERMORE\n");
            $printer -> setEmphasis(false);

            $text = <<<EOF
            User agrees to release, indemnify,
            and hold harmless NUCC and DEF CON and
            its employees from liability for any
            claims or damages of any kind or
            description that may arise from any
            unauthorized or improper
            use of these services.

            EOF;
            $printer -> text($text);
            $printer -> setEmphasis(true);
            $printer -> text("\nCEASE USE IMMEDIATELY\n");
            $printer -> setEmphasis(false);
            $text = <<<EOF
            If you do not agree to the conditions
            stated in this warning.

            If you are under 18 years of age,
            a parent, guardian or guarantor must
            also read and agree to ALL of the above.

            EOF;

            $printer -> text($text);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> setEmphasis(false);
            $printer -> setTextSize(3, 3);
            $printer -> text("\n\nTL;DR: DFIU\n\n");
            $printer -> setEmphasis(false);
            $printer -> setJustification();
            $printer -> feed();
            $printer -> setTextSize(1, 1);

            $printer -> pdf417Code($vm[0]["uuid"]);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($vm[0]["uuid"]."\n\n");
            $printer -> text("Donate: paypal.me/NUCC\n\n");
            $printer -> setEmphasis(true);
            $printer -> text("*Tech Support: +1.747.377.4355*\n\n");
            $printer -> feed();
            $printer -> cut();
            $printer -> close();
            return;
	    $database->update("state", ["time" => time()+60], ["name" => "money"]);
	    $database->update("state", ["data" => 0, "time" => time()], ["name" => "buttonstate"]); 
            /// END OF PRINTER / GET VM!
        } catch (\Throwable $th) {
            logtodiscord("!== DIED! @everyone PRINTER ERROR: " . $th->getMessage());
            $database->update("state", ["data" => "printererror", "time" => time()], ["name" => "purchase"]);
            while(true){
                $dbstate = $database->select("state", "data", ["name" => "purchase"]);
                $state = $dbstate[0];
                if($state == "printererror"){
                    sleep(1);
                    echo "E";
                }else{
                    $database->update("state", ["time" => time()+60], ["name" => "money"]);
                    break;
                }
            }
        }
        }else{
            /// OUT OF VMS!
            $database->update("state", ["data" => "outofvms", "time" => time()], ["name" => "purchase"]);
            logtodiscord("[PROCESS] @everyone OUT OF VMS! " . $flavorinfo[0]["printedname"]);
            $database->update("state", ["data" => "printererror", "time" => time()], ["name" => "purchase"]);
            while(true){
                $dbstate = $database->select("state", "data", ["name" => "purchase"]);
                $state = $dbstate[0];
                if($state == "printererror"){
                    sleep(1);
                    echo "E";
                }else{
                    $database->update("state", ["time" => time()+60], ["name" => "money"]);
                    break;
                }
            }
            return;
        }

    }else{
            // NOT ENOUGH MONEY, BAILING
            $database->update("state", ["data" => "insertmoremoney", "time" => time()], ["name" => "purchase"]); /// update UI
            logtodiscord("[PROCESS] USER TRIED TO BY SOMETHING BUT DIDN'T HAVE THE CASH" . $money[0]["data"] . " " . $flavorinfo[0]["printedname"] . " " . $flavorinfo[0]["price"]);
            sleep(2);
            return;
    }

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function donation(){
        global $database;
        global $printerip;
        global $lastprint;
        global $tux;
        //////////////// PRINTER STUFF ////////////////

        /// WHAT PRINTER DO WE WANT?
        if($lastprint == 0){
            $printip = $printerip[0];
            $lastprint = 1;
        }else{
            $printip = $printerip[1];
            $lastprint = 0;
        }
        try {
        /// PRINTER SETUP
        $profile = CapabilityProfile::load("TSP600");
        $connector = new NetworkPrintConnector($printip, 9100);
        $printer = new Printer($connector, $profile);
        $printer->initialize();
        $statuscommand = Printer::GS . "1" . Printer::NUL;
        $printer->getPrintConnector()->write($statuscommand);
        $statusout = bin2hex($printer->getPrintConnector()->read(8));
        /// PRINTER ERROR!
        if($statusout != "14000000"){
            if($lastprint == 0){
                $printip = $printerip[0];
                $lastprint = 1;
            }else{
                $printip = $printerip[1];
                $lastprint = 0;
            }
            $printer -> close();
            echo "ERORR! $statusout".PHP_EOL;
            logtodiscord("[PRINT] @everyone PRINTER ERROR: $statusout - ".$printip);
            $rand_keys = array_rand($printerip, 2);
            unset($connector);
            unset($printer);
            $connector = new NetworkPrintConnector($printip, 9100);
            $printer = new Printer($connector, $profile);
            $printer->initialize();
            $statuscommand = Printer::GS . "1" . Printer::NUL;
            $printer->getPrintConnector()->write($statuscommand);
            $statusout = bin2hex($printer->getPrintConnector()->read(8));
        }
        $money = $database->select("state", ["data", "time"], ["name" => "money"]);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> graphics($tux);
        $printer -> text("S.O.D.A. MACHINE\n");
        $printer -> text("POP`N OUT SHELLS\n\n\n");
        $printer -> setTextSize(3, 3);
        $printer -> setJustification();

        $database->update("state", ["data" => "donation", "time" => time()], ["name" => "purchase"]); // Update UI
        $database->update("state", ["data" => 0], ["name" => "money"]);
        updategiftcard();
        $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
        if(substr($codemem[0]["data"], 7, 1) != 8){
        $database->update("state", ["data[+]" => $money[0]["data"]], ["name" => "totals"]);
        }
        $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
        $printer -> setJustification();
        $printer -> text("DONATION:\n");
        $printer -> setEmphasis(true);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> text("$ ". $money[0]["data"] ."\n");
        $printer -> setEmphasis(false);
        $printer -> feed();
        $printer -> setTextSize(1, 1);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> setEmphasis(true);
        $printer -> text("\n\nTerms of Donation:\n\n");
        $printer -> setEmphasis(false);
        $printer -> setJustification();

        //// 42 LINES OF TEXT
        //////////////////////////////////////////
        $text = <<<EOF
        This SHELL ON DEMAND APPLIANCE
        (“S.O.D.A. MACHINE”) is the property of
        NATIONAL UPCYCLED COMPUTING COLLECTIVE INC
        (“NUCC”)

        NUCC is a 501(c)(3) nonprofit organization
        (EIN 82-1177433) as determined by the
        Internal Revenue Service with a
        National Taxonomy of Exempt Entities
        U41 Classification as a Computer Science,
        Technology and Engineering,
        Research Institute.

        S.O.D.A. MACHINE services are provided by
        NUCC at DEF CON as a NUCC Fundraiser
        and as such fall under the
        established DEF CON policies.

        Use of the services distributed by this
        S.O.D.A. MACHINE indicate your awareness
        of and consent to ALL DEF CON policies.

        www.defcon.org/html/links/dc-policy.html

        Unauthorized or improper use of these
        services may result in disciplinary
        action and civil/criminal penalties.

        EOF;

        $printer -> text($text);
        $printer -> setEmphasis(true);
        $printer -> text("\nFURTHERMORE\n");
        $printer -> setEmphasis(false);

        $text = <<<EOF
        User agrees to release, indemnify,
        and hold harmless NUCC and DEF CON and
        its employees from liability for any
        claims or damages of any kind or
        description that may arise from any
        unauthorized or improper
        use of these services.

        EOF;
        $printer -> text($text);
        $printer -> setEmphasis(true);
        $printer -> text("\nCEASE USE IMMEDIATELY\n");
        $printer -> setEmphasis(false);
        $text = <<<EOF
        If you do not agree to the conditions
        stated in this warning.

        If you are under 18 years of age,
        a parent, guardian or guarantor must
        also read and agree to ALL of the above.

        EOF;

        $printer -> text($text);
        $printer -> feed();
        $printer -> feed();
        $printer -> text("Donate: paypal.me/NUCC\n\n");
        $printer -> setEmphasis(true);
        $printer -> text("*Tech Support: +1.747.377.4355*\n\n");
        $printer -> feed();
        $printer -> cut();
        $printer -> close();
    } catch (\Throwable $th) {
        logtodiscord("!== @everyone PRINTER ERROR: " . $th->getMessage());
        $database->update("state", ["data" => "printererror", "time" => time()], ["name" => "purchase"]);
        while(true){
            $dbstate = $database->select("state", "data", ["name" => "purchase"]);
            $state = $dbstate[0];
            if($state == "printererror"){
                sleep(1);
                echo "E";
            }else{
                $database->update("state", ["time" => time()+60], ["name" => "money"]);
                break;
            }
        }
    }
}

function updategiftcard(){
    global $database;
    $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    if(!empty($codemem[0]["data"])){
        $database->update("codes", ["amount" => $money[0]["data"], "lastused" => time()], ["code" => $codemem[0]["data"]]);
        logtodiscord("[PROCESS][GIFTCARD] Updating Code " . $codemem[0]["data"] ." to Amt: " . $money[0]["data"]);
         return true;
    }else{
        return false;
    }
}

function processrefund(){
    global $database;
    global $printerip;
    global $lastprint;
    global $tux;
    //////////////// PRINTER STUFF ////////////////

    /// WHAT PRINTER DO WE WANT?
    if($lastprint == 0){
        $printip = $printerip[0];
        $lastprint = 1;
    }else{
        $printip = $printerip[1];
        $lastprint = 0;
    }
    try {
    /// PRINTER SETUP
    $profile = CapabilityProfile::load("TSP600");
    $connector = new NetworkPrintConnector($printip, 9100);
    $printer = new Printer($connector, $profile);
    $printer->initialize();
    $statuscommand = Printer::GS . "1" . Printer::NUL;
    $printer->getPrintConnector()->write($statuscommand);
    $statusout = bin2hex($printer->getPrintConnector()->read(8));
    /// PRINTER ERROR!
    if($statusout != "14000000"){
        if($lastprint == 0){
            $printip = $printerip[0];
            $lastprint = 1;
        }else{
            $printip = $printerip[1];
            $lastprint = 0;
        }
        $printer -> close();
        echo "ERORR! $statusout".PHP_EOL;
        logtodiscord("[PRINT] @everyone PRINTER ERROR: $statusout - ".$printip);
        $rand_keys = array_rand($printerip, 2);
        unset($connector);
        unset($printer);
        $connector = new NetworkPrintConnector($printip, 9100);
        $printer = new Printer($connector, $profile);
        $printer->initialize();
        $statuscommand = Printer::GS . "1" . Printer::NUL;
        $printer->getPrintConnector()->write($statuscommand);
        $statusout = bin2hex($printer->getPrintConnector()->read(8));
    }
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    $printer -> setJustification(Printer::JUSTIFY_CENTER);
    $printer -> graphics($tux);
    $printer -> text("S.O.D.A. MACHINE\n");
    $printer -> text("POP`N OUT SHELLS\n\n\n");
    $printer -> setTextSize(1, 1);
    $printer -> setJustification();
    $database->update("state", ["data" => "giftcard", "time" => time()], ["name" => "purchase"]); // Update UI
    $database->update("state", ["data" => 0], ["name" => "money"]);
    $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
    $database->insert("donations", ["amount" => $money[0]["data"], "time" => time(), "type"=>"button1donation"]);
    //// GIFT CARD CODE GENERATION
    while(true){
        $code = mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,9).mt_rand(1,7)."7";
        $checkcode = $database->select("codes", "*", ["code" => $code]);
        if(empty($checkcode[0]["code"])){
            /// CODE OK
            $database->insert("codes", ["code" => $code, "amount"=>$money[0]["data"], "lastused" => time()]);
            logtodiscord("[PROCESS][GIFT] NEW CODE GENERATED " . $code . " Amt: " . $money[0]["data"]);
            break;
        }
    }

    $printer -> setJustification();
    $printer -> setTextSize(3, 3);
    $printer -> text("VOUCHER:\n");
    $printer -> setEmphasis(true);
    $printer -> setJustification(Printer::JUSTIFY_CENTER);
    $printer -> text("$ ". $money[0]["data"] ."\n");
    $printer -> setEmphasis(false);
    $printer -> feed();

    $printer -> setJustification();
    $printer -> text("Code:\n");
    $printer -> setEmphasis(true);
    $printer -> setJustification(Printer::JUSTIFY_CENTER);
    $printer -> text("$code\n");
    $printer -> setEmphasis(false);
    $printer -> feed();
    $printer -> setEmphasis(true);
    $printer -> setTextSize(1, 1);
    $printer -> text("\n\nHOW TO USE:\n\n");
    $printer -> setJustification();
    $printer -> setEmphasis(false);
    //////////////////////////////////////////
    $text = <<<EOF
    While machine is idle, press COIN RETURN
    Enter Code listed above to restore amount.

    Any money inserted while VOUCHER is active
    will add to the current VOUCHER amount.

    If you wish to exchange this VOUCHER for
    money back, Please see SODA ADMIN/SCAVHUNT
    at their respective table.

    The Voucher balance expires at the end of
    DEF CON and will be a donation to NUCC

    EOF;
    $printer -> text($text);
    $printer -> setJustification(Printer::JUSTIFY_CENTER);
    $printer -> setEmphasis(true);
    $printer -> text("\n\nTerms of Service:\n\n");
    $printer -> setEmphasis(false);
    $printer -> setJustification();

    //// 42 LINES OF TEXT
    //////////////////////////////////////////
    $text = <<<EOF
    This SHELL ON DEMAND APPLIANCE
    (“S.O.D.A. MACHINE”) is the property of
    NATIONAL UPCYCLED COMPUTING COLLECTIVE INC
    (“NUCC”)

    NUCC is a 501(c)(3) nonprofit organization
    (EIN 82-1177433) as determined by the
    Internal Revenue Service with a
    National Taxonomy of Exempt Entities
    U41 Classification as a Computer Science,
    Technology and Engineering,
    Research Institute.

    S.O.D.A. MACHINE services are provided by
    NUCC at DEF CON as a NUCC Fundraiser
    and as such fall under the
    established DEF CON policies.

    Use of the services distributed by this
    S.O.D.A. MACHINE indicate your awareness
    of and consent to ALL DEF CON policies.

    www.defcon.org/html/links/dc-policy.html

    Unauthorized or improper use of these
    services may result in disciplinary
    action and civil/criminal penalties.

    EOF;

    $printer -> text($text);
    $printer -> setEmphasis(true);
    $printer -> text("\nFURTHERMORE\n");
    $printer -> setEmphasis(false);

    $text = <<<EOF
    User agrees to release, indemnify,
    and hold harmless NUCC and DEF CON and
    its employees from liability for any
    claims or damages of any kind or
    description that may arise from any
    unauthorized or improper
    use of these services.

    EOF;
    $printer -> text($text);
    $printer -> setEmphasis(true);
    $printer -> text("\nCEASE USE IMMEDIATELY\n");
    $printer -> setEmphasis(false);
    $text = <<<EOF
    If you do not agree to the conditions
    stated in this warning.

    If you are under 18 years of age,
    a parent, guardian or guarantor must
    also read and agree to ALL of the above.

    EOF;

    $printer -> text($text);
    $printer -> feed();
    $printer -> feed();
    $printer -> text("Donate: paypal.me/NUCC\n\n");
    $printer -> setEmphasis(true);
    $printer -> text("*Tech Support: +1.747.377.4355*\n\n");
    $printer -> feed();
    $printer -> cut();
    $printer -> close();
} catch (\Throwable $th) {
    logtodiscord("!== @everyone PRINTER ERROR: " . $th->getMessage());
    $database->update("state", ["data" => "printererror", "time" => time()], ["name" => "purchase"]);
    while(true){
        $dbstate = $database->select("state", "data", ["name" => "purchase"]);
        $state = $dbstate[0];
        if($state == "printererror"){
            sleep(1);
            echo "E";
        }else{
            $database->update("state", ["time" => time()+60], ["name" => "money"]);
            break;
        }
    }
}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
logtodiscord("[PROCESS] MAIN LOOP START!");
while (true){
    //// START OF MAIN LOOP

    $button = $database->select("state", ["data", "time"], ["name" => "buttonstate"]);
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    $return = $database->select("state", ["data", "time"], ["name" => "forcedreturn"]);
    if($return[0]["data"]){
        sleep(1);
        echo "^";
        break;
    }
    sleep(1); // SLOW DOWN THE LOOP TO GIVE EVERYTHING SOME TIME TO PROCESS
    echo ".";
    ////////////// DO WE HAVE MONEY?
    if($return[0]["data"] == 0){ // not in return state
        
        if($money[0]["data"]){ /// WE HAVE MONEY!

            $database->update("state", ["data" => "moneyidle", "time" => time()], ["name" => "purchase"]); /// update UI
            $dloop = 0;
            while(true){ /// WAITING FOR BUTTON PRESS
                $button = $database->select("state", ["data", "time"], ["name" => "buttonstate"]); /// UPDATE BUTTON STATE
                $money = $database->select("state", ["data", "time"], ["name" => "money"]); /// UPDATE AMOUNT STATE
                $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]); // GIFT CODES
                
                if($dloop == 100){
                    echo "$";
                    $loop = 0;
                }
                $dloop++;
                if($button[0]["data"] > 0){ // BUTTON DETECTED IN MONEY STATE
                    // if($button[0]["data"] == 99){
                    //     while(true){
                    //         $money = $database->select("state", ["data", "time"], ["name" => "money"]);
                    //         echo "~";
                    //         sleep(1);
                    //         if($money[0]["data"] == 0){
                    //             $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                    //             sleep(1);
                    //             break 2;
                    //         }
                    //     }
                    // }
                    switch ($button[0]["data"]) {
                        case '99': // RETURN PUSHED?!
                            $database->update("state", ["data" => "moneyidle", "time" => time()], ["name" => "purchase"]); /// update UI
                            logtodiscord("[PROCESS] RETURN PUSHED IN MONEYIDLE");
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            if($codemem[0]["data"]){
                                updategiftcard();
                                $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                                $database->update("state", ["data" => 0], ["name" => "money"]);
                                logtodiscord("[PROCESS][GIFT] RETURNED MONEY TO GIFT");
                            }else{
                                /// PRINT GIFTCARD
                                if($money[0]["data"]>3){
                                logtodiscord("[PROCESS][GIFT] GENERATING GIFT CARD FOR RETURNED MONEY");
                                processrefund();
                                }else{
                                    while(true){
                                        $money = $database->select("state", ["data", "time"], ["name" => "money"]);
                                        echo "~";
                                        sleep(1);
                                        if($money[0]["data"] == 0){
                                            sleep(1);
                                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                                            break 2;
                                        }
                                    }
                                }
                                $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            }
                            break 2; break;
                        case in_array($button[0]["data"], range(2,9)):
                            buyvm();
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            break 2; break;
                        case '1':
                            logtodiscord("[PROCESS] DONATION PRESSED! DONATING " . $money[0]["data"]);
                            donation();
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            break 2; break;
                        default:
                            logtodiscord("[PROCESS] UNKNOWN BUTTON DETECTED! DEFAULT REACHED IN INMONEY! " . $button[0]["data"]);
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            break 2; break;
                    }
                }else{
                    //// MONEY IDLE TIMEOUT
                    $moneytimeleft = ($money[0]["time"] - (time() - 42));
                    if($moneytimeleft <= 0){
                            if(!updategiftcard()){
                            $database->insert("donations", ["amount" => $money[0]["data"], "time" => time(), "type"=>"idletimeout"]);
                            }else{
                                $database->update("state", ["data" => 0, "time" => time()], ["name" => "money"]);
                                $money = $database->select("state", ["data", "time"], ["name" => "money"]);
                            }
                            $database->update("state", ["data" => 0, "time" => time()], ["name" => "money"]);
                            $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            logtodiscord("[PROCESS] IDLE TIMEOUT WITH AMOUNT OF: $" .$money[0]["data"]);
                            $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                            break;
                    }
                }
                if($money[0]["data"] <= 0){
                    //// MONEY GONE! 
                    logtodiscord("[PROCESS] MONEY GONE, GOING BACK TO IDLE" . $money[0]["data"]);
                    $database->update("state", ["data" => 0], ["name" => "money"]);
                    $database->update("state", ["data" => 0], ["name" => "button"]);
                    $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                    $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                    break;
                }
            }


        }else{ // WE DON'T HAVE MONEY
            $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
            if($button[0]["data"] == 99){
                /// CODE ENTRY
                $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                $loop = 0;
                while (true){
                    echo "#";
                    sleep(1);
                    $loop++;
                    $database->update("state", ["data" => "codeinput", "time" => time()], ["name" => "purchase"]);
                    $button = $database->select("state", ["data", "time"], ["name" => "buttonstate"]);
                    if($button[0]["data"] > 0){
                        $loop = 0;
                        switch ($button[0]["data"]) {
                            case in_array($button[0]["data"], range(1,9)):
                                echo $button[0]["data"];
                                $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
                                if(empty($codemem[0]["data"])){$codemem[0]["data"] = "";}
                                $codemem[0]["data"] .= $button[0]["data"];
                                $database->update("state", ["data" => $codemem[0]["data"], "time" => time()], ["name" => "codemem"]);

                                if(strlen( $codemem[0]["data"]) >= 8){
                                    $codeinfo = $database->select("codes", "*", ["code" => $codemem[0]["data"]]);
                                    if(!empty($codeinfo[0]["amount"])){
                                        if($codeinfo[0]["amount"] <= 0){
                                            logtodiscord("[PROCESS][MENU] Drained Entered! ".$codemem[0]["data"]);
                                            $database->update("state", ["name" => "", "time" => time()], ["name" => "codemem"]);
                                            $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                                            break 2; break;
                                        }
                                        logtodiscord("[PROCESS][MENU] Vaild Code Redeemed in system Amt:" . $codeinfo[0]["amount"] . " Code:" . $codemem[0]["data"]);
                                        $database->update("state", ["data" => $codeinfo[0]["amount"], "time" => time()], ["name" => "money"]);
                                        $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                                        $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                                        break 2; break;
                                    }else{
                                        logtodiscord("[PROCESS][MENU] Invaild Code Entered! ".$codemem[0]["data"]);
                                        $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                                        $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                                        $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                                        break 2; break;
                                    }
                                }
                                break;
                            case "99":
                                $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                                $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                                $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                                break 2; break;
                        }
                        $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                    }
                    if($loop > 15){
                        echo "c";
                        $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                        $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                        $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                        break;
                    }
                }
            }else{
                $loop = 0;
                $lastbutton = 0;
                if($button[0]["data"] > 0){
                    while(true){
                        echo "i";
                        $money = $database->select("state", ["data", "time"], ["name" => "money"]);
                        $button = $database->select("state", ["data", "time"], ["name" => "buttonstate"]); /// UPDATE BUTTON STATE
                        $database->update("state", ["data" => "info", "time" => time()], ["name" => "purchase"]);
                        $loop++;
                        if($button[0]["data"] == 99){
                            $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                            break;
                        }
                        if($lastbutton != $button[0]["data"]){
                        $lastbutton = $button[0]["data"];
                        $loop = 0;
                        echo "r";
                        }

                        if($loop > 15){
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            $database->update("state", ["data" => "idle", "time" => time()], ["name" => "purchase"]);
                            $database->update("state", ["data" => NULL, "time" => time()], ["name" => "codemem"]);
                            break;
                        }
                        if($money[0]["data"] > 0){
                            $database->update("state", ["data" => 0], ["name" => "buttonstate"]);
                            break;
                        }
                        sleep(1);
                    }
                 }
            }
        } /// END OF WE DON'T HAVE MONEY
    } // out of return state checks





} // END OF MAIN LOOP
