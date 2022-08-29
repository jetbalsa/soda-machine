<?php
require __DIR__ . '/vendor/autoload.php';
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
require 'Medoo.php';

// Using Medoo namespace.
use Medoo\Medoo;

// Connect the database.
$database = new Medoo([
    	'type' => 'sqlite',
    	'database' => '../database.db'
]);



