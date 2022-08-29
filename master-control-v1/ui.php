<?php
require __DIR__ . '/vendor/autoload.php';
require 'Medoo.php';
require 'webhook.php';
use Medoo\Medoo;

// Connect the database.
$database = new Medoo([
    'type' => 'sqlite',
    'database' => '../database.db',
    'error' => PDO::ERRMODE_EXCEPTION
]);
if($_GET["update"]){
    $dbstate = $database->select("state", "data", ["name" => "purchase"]);
    $button = $database->select("state", ["data", "time"], ["name" => "buttonstate"]);
    $money = $database->select("state", ["data", "time"], ["name" => "money"]);
    $return = $database->select("state", ["data", "time"], ["name" => "forcedreturn"]);
    $flavorinfo = $database->select("types", "*", ["id" => $button[0]["data"]]); // get flavor of last button pushed.
    $state = $dbstate[0];
    switch ($state){
        ///////////////////////////////////// IDLE /////////////////////////////////
        case "idle":
            $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE</legend>
            <pre>
██ ███    ██ ███████ ███████ ██████  ████████      ██████  ██████  ██ ███    ██ 
██ ████   ██ ██      ██      ██   ██    ██        ██      ██    ██ ██ ████   ██ 
██ ██ ██  ██ ███████ █████   ██████     ██        ██      ██    ██ ██ ██ ██  ██ 
██ ██  ██ ██      ██ ██      ██   ██    ██        ██      ██    ██ ██ ██  ██ ██ 
██ ██   ████ ███████ ███████ ██   ██    ██         ██████  ██████  ██ ██   ████ 
</pre>
            </fieldset>
    </div>
EOF;
            echo $html;
            break;

                    ///////////////////////////////////// IDLE /////////////////////////////////
        case "codeinput":
            $codearray = ["_","_","_","_", "_", "_", "_", "_"];
            $codestr = "";
            $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
            $arr1 = str_split($codemem[0]["data"]);
            if(!empty($codemem[0]["data"])){
            foreach($arr1 as $k => $v){
                $codearray[$k] = "*";
                //$codestr = $codemem[0]["data"];
            }}
            foreach($codearray as $v){
                $codestr .= $v . " ";
            }
            $timeleft = ($button[0]["time"] - (time() - 18));
            $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE</legend>
            <div style="font-size: 150% !important;">
                USING THE FLAVOR BUTTONS<br>TOP BEING 1, BOTTOM BEING 9<br>ENTER YOUR VOUCHER CODE.<br><br><br>
                    $codestr
            </div>
            </fieldset>
    </div>
EOF;
            echo $html;
            break;

            ////////////////////////////// VM SELECTED - INFO//////////////////////////
        case "info":
            $vminfo = $database->select("types", "*", ["id" => $button[0]["data"]]);
            $timeleft = ($button[0]["time"] - (time() - 18));
            if($button[0]["data"] > 1){
            $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE</legend>
            <div style="font-size: 250% !important;">
            {$vminfo[0]["printedname"]}<br><hr>
            <table style="width: 100%;">
            <tr><td>vCPU</td><td>{$vminfo[0]["cpu"]}</td></tr>
            <tr><td>RAM</td><td>{$vminfo[0]["ram"]}MB</td></tr>
            <tr><td>DISK</td><td>{$vminfo[0]["disk"]}MB</td></tr>
            <tr><td>PRICE</td><td>\${$vminfo[0]["price"]}</td></tr>
            <tr><td>TIME</td><td>{$vminfo[0]["alivetime"]} HOURS</td></tr>
</table><hr><div style="font-size: 50% !important;">
All VMs come with<br>Internal DEF CON IP<br>Dedicated TOR Hidden Service<br>SSH Server<br>Shared 1Gbit Connection. 
            </div></div><div>
            </fieldset>
    </div>
EOF;
            echo $html;}else{
                $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
            <div style="font-size: 250% !important;">
            DONATE<br><br>Insert Coin and any amount inserted will be donated to NUCC
            </div>
            </fieldset>
    </div>
EOF;
                echo $html;
            }
            break;
        ////////////////////////////// MONEY IDLE//////////////////////////
        case "moneyidle":
            $timeleft = ($money[0]["time"] - (time() - 42));
            if($timeleft > 33){
            $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
                <div style="font-size: 250% !important;">
                CREDIT: \${$money[0]["data"]}<HR>
                SELECT YOUR SHELL
                </div>
            </fieldset>
    </div>
EOF;
            echo $html;}else{
                $codemem = $database->select("state", ["data", "time"], ["name" => "codemem"]);
                if(empty($codemem[0]["data"])){
                $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
                <div style="font-size: 250% !important;">
                CREDIT: \${$money[0]["data"]}<HR>
                WARNING: INACTIVITY DONATION IN $timeleft SECONDS<br><br>Your remaining credit will be donated at the end of this time.
                </div>
            </fieldset>
    </div>
EOF;
                }else{
    $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
                <div style="font-size: 250% !important;">
                CREDIT: \${$money[0]["data"]}<HR>
                WARNING: INACTIVITY IN $timeleft SECONDS<br><br>Your remaining credit will be returned to your voucher at the end of this time.
                </div>
            </fieldset>
    </div>
EOF; 
                }
                echo $html;
            }
            break;

        case "insertmoremoney":
            $timeleft = ($money[0]["time"] - (time() - 42));
            $vminfo = $database->select("types", "*", ["id" => $button[0]["data"]]);
                $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
                <div style="font-size: 250% !important;">
                CREDIT: \${$money[0]["data"]}<HR>
                NOT ENOUGHT CREDIT!
                </div><br>
    </div>
EOF;
                echo $html;

            break;
        case "vmget":
            $timeleft = ($purchase[0]["time"] - (time() - 20));
            $html = <<<EOF
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
                <div style="font-size: 250% !important;">
THANK YOU FOR YOUR DONATION<hr>YOUR VM INFO IS PRINTING BELOW
                </div>
            </fieldset>
    </div>
EOF;
            echo $html;
            break;
            case "donation":
                $timeleft = ($purchase[0]["time"] - (time() - 20));
                $html = <<<EOF
        <div class="tui-window left-align popup">
            <fieldset class="tui-fieldset">
                <legend>S.O.D.A. MACHINE - {$timeleft}</legend>
                    <div style="font-size: 250% !important;">
    THANK YOU FOR YOUR DONATION<hr>YOUR INFO IS PRINTING BELOW
                    </div>
                </fieldset>
        </div>
    EOF;
                echo $html;
                break;
                case "printererror":
                    $html = <<<EOF
            <div class="tui-window left-align popup">
                <fieldset class="tui-fieldset">
                    <legend>S.O.D.A. MACHINE </legend>
                        <div style="font-size: 250% !important;">
        OH NO! SOMETHING BROKE!<br><br> A NEARBY SODA JERK HAS BEEN PINGED ON DISCORD ABOUT THIS!
                        </div>
                    </fieldset>
            </div>
        EOF;
                    echo $html;
                    break;
    }
    echo $state;
}else{
    ?>
    <!DOCTYPE html>
    <html lang="en" class="tui-bg-blue-black no-tui-scroll">
    <head>
        <title>Main UI for SODA MACHINE</title>
        <link rel="stylesheet" href="dist/tuicss.min.css"/>
        <script src="dist/tuicss.min.js"></script>
        <script src="jquery-3.6.0.min.js"></script>
        <style>
            .popup {
                position: fixed;
                top: 50%;
                left: 50%;
                -webkit-transform: translate(-50%, -50%);
                transform: translate(-50%, -50%);
            }
            @font-face{
                font-family: IBM;
                src: url('WebPlus_IBM_BIOS.woff'), url('WebPlus_IBM_BIOS.woff') format('woff');
            }
            * {
                font-family: IBM !important;
                font-size: 100%;
            }
        </style>

    </head>
    <body>
    <div id="maindiv">
    <div class="tui-window left-align popup">
        <fieldset class="tui-fieldset">
            <legend>S.O.D.A. MACHINE</legend><pre>
██       ██████   █████  ██████  ██ ███    ██  ██████
██      ██    ██ ██   ██ ██   ██ ██ ████   ██ ██
██      ██    ██ ███████ ██   ██ ██ ██ ██  ██ ██   ███
██      ██    ██ ██   ██ ██   ██ ██ ██  ██ ██ ██    ██
███████  ██████  ██   ██ ██████  ██ ██   ████  ██████
        </pre></fieldset>
    </div>
    </div>
    <div class="tui-statusbar blue-255 white-255-text absolute">
        <ul>
            <li id="date">09/06/2019</li>
            <li id="shells">Shells Popped:31337</li>
            <li style="position: fixed; right: 0%;" id="donated">$0 Donated</li>
        </ul>
    </div>
    <script>
        let clock = () => {
            let date = new Date();
            document.getElementById("date").innerText = date;
            setTimeout(clock, 1000);
        };
        clock();
        let update = () => {
            $( "#maindiv" ).load( "ui.php?update=true" );
        }
        update();
        setInterval(update, 500);
    </script>
    </body>
    </html>
<?php
}
