<?php
/* Call this file 'hello-world.php' */
require __DIR__ . '/vendor/autoload.php';
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
require 'Medoo.php';
require 'webhook.php';
// Using Medoo namespace.
use Medoo\Medoo;

// Connect the database.
$database = new Medoo([
    	'type' => 'sqlite',
    	'database' => '../database.db',
        'error' => PDO::ERRMODE_EXCEPTION
]);

//$printerip[] = "10.11.12.149";
$printerip[] = "10.11.12.96";


/// grab a VM from the queue;
$vm = $database->select("queue", "*", ["type" => $button[0]["data"]]);
$vm[0]["created"] = time();
$vm[0]["expires"] = time() + (($flavorinfo[0]["alivetime"] * 60) * 60);
$database->insert("active", $vm[0]);
$database->delete("queue", ["id" => $vm[0]["id"]]);

$profile = CapabilityProfile::load("TSP600");
$connector = new NetworkPrintConnector("10.11.12.96", 9100);
$printer = new Printer($connector, $profile);
$printer->initialize();
$statuscommand = Printer::GS . "1" . Printer::NUL;
$printer->getPrintConnector()->write($statuscommand);
$statusout = bin2hex($printer->getPrintConnector()->read(8));

if($statusout != "14000000"){
echo "ERORR! $statusout".PHP_EOL;
$rand_keys = array_rand($printerip, 2);
unset($connector);
unset($printer);
$connector = new NetworkPrintConnector($printerip[$rand_keys[1]], 9100);
$printer = new Printer($connector, $profile);
$printer->initialize();
$statuscommand = Printer::GS . "1" . Printer::NUL;
$printer->getPrintConnector()->write($statuscommand);
$statusout = bin2hex($printer->getPrintConnector()->read(8));
}
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$tux = EscposImage::load("nucclogo.png", false);
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
$printer -> text("VM Expires in:\n");
$printer -> setEmphasis(true); 
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> text($flavorinfo[0]["alivetime"]." Hours\n");
$printer -> setEmphasis(false);

$printer -> setJustification();
$printer -> text("Tor Hostname:\n");
$printer -> setEmphasis(true); 
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> text($vm[0]["toraddr"]."\n");
$printer -> setEmphasis(false);
$printer -> setJustification(Printer::JUSTIFY_CENTER);
//$printer -> qrCode($vm[0]["toraddr"]);


$printer -> feed();
$printer -> setJustification();
$printer -> text("Price: ".$flavorinfo[0]["price"]."\n");
$printer -> text("Balance: ".$money[0]["data"]."\n");

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

//$printer -> pdf417Code($vm[0]["uuid"]);
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> text($vm[0]["uuid"]."\n\n");
$printer -> text("Donate: paypal.me/NUCC\n\n");
$printer -> setEmphasis(true);
$printer -> text("*Tech Support: +1.747.377.4355*\n\n");
$printer -> feed();
$printer -> cut();
$printer -> close();

