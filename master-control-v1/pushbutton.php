<?php
require __DIR__ . '/vendor/autoload.php';
require 'Medoo.php';
use Medoo\Medoo;

// Connect the database.
$database = new Medoo([
    'type' => 'sqlite',
    'database' => '../database.db'
]);
$database->update("state", ["data" => $argv[1], "time" => time()], ["name" => "buttonstate"]);
